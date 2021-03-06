<?php

namespace account\token;

use account\sign\sign as Sign;

class verify extends token
{
    /**
     * 多站点模式下,缓存系统所保存的站点需固定,
     * 因在用户登录的时候还没有切换站点,缓存系统使用的是默认的主站点,
     * 如果不强制设置,这会导致登录后无法获取到缓存系统内容
     */

    public function run()
    {
        $expire = parent::_expireTime();
        $userid = self::_getID('', $expire);
        if (self::_isGetUser()) {
            $ret = Sign::signInAccount(array(
                'type' => 'id',
                'field' => $userid
            ), false, false);
            $ret['expire'] = time() + $expire;

            dfoxaGateway($ret);
        } else {
            dfoxaGateway(array(
                'expire' => time() + $expire,
                'sub_msg' => 'access_token验证通过'
            ));
        }
    }

    /**
     * 根据access_token获取指定用户
     * @param string $access_token 留空表示获取当前登录用户
     * @param bool $get_user 是否获取用户的信息
     * @return array|int 返回用户信息或用户ID
     */
    public static function check($access_token = '', $get_user = false)
    {
        $userid = self::_getID($access_token);

        // 自动登录用户
        wp_set_current_user($userid);
        wp_set_auth_cookie( $userid );

        if ($get_user) {
            // 登录用户账号
            $account = Sign::signInAccount(array(
                'type' => 'id',
                'field' => $userid
            ), false, false, false);

            return $account;
        } else {
            return $userid;
        }
    }

    /**
     * 获取当前登录用户的ID
     */
    public static function getSignUserID()
    {
        return self::check();
    }

    /**
     * 获取当前登录用户的详细信息
     */
    public static function getSignUser()
    {
        return self::check('', true);
    }

    /**
     * 获取当前登录用户提交的Token
     */
    public static function getSignUserAccessToken()
    {
        return self::_getAccessToken();
    }

    /**
     * 通过 access_token 获取用户ID
     * @param $access_token
     * @param null $expire 过期时间
     * @return int 用户ID
     */
    private static function _getID($access_token = '', $expire = null)
    {
        switch_to_blog(1);
        if ($access_token === '')
            $access_token = self::_getAccessToken();

        // 从缓存中根据 accesstoken 获取 onlytoken
        $onlytoken = wp_cache_get($access_token, '_access_token');

        if (empty($onlytoken))
            dfoxaError('account.expired-accesstoken');

        $userid = (int)explode('#', $onlytoken)[0];
        if (empty($userid))
            dfoxaError('account.expired-accesstoken');

        // 判断 onlytoken
        if ($onlytoken != parent::_creatOnlyToken($userid))
            dfoxaError('account.expired-accesstoken');

        $group_key = '_access_token_' . $userid;
        if (absint(wp_cache_get($onlytoken, $group_key)) !== 1) {
            // 如果用户的 group_key 不存在，则清空 access_token 的值
            wp_cache_delete($access_token, '_access_token');
            dfoxaError('account.distance-accesstoken');
        }

        // 更新 access_token 过期时间
        $expire = $expire === null ? parent::_expireTime() : (int)$expire;
        wp_cache_set($access_token, $onlytoken, '_access_token', $expire);

        switch_to_blog(dfoxa_get_query_mulitsite_blog_id());
        return $userid;
    }

    /**
     * 通过各种方式获取用户可能提交到的 access_token
     * @return mixed 用户access_token
     */
    private static function _getAccessToken()
    {
        $query = bizContentFilter(array(
            'access_token'
        ));

        // 如果请求参数中有access_token则直接返回参数中的token
        if (!empty($query->access_token))
            return $query->access_token;

        // 从 URL地址 中获取
        if (isset($_GET['access_token']))
            return $_GET['access_token'];

        // 从 Cookie 中获取
        if (isset($_COOKIE['access_token']))
            return $_COOKIE['access_token'];

        // 从 请求头 获取
        if (isset($_SERVER['HTTP_ACCESS_TOKEN'])) {
            return $_SERVER['HTTP_ACCESS_TOKEN'];
        }

        // ...

        // 报错
        dfoxaError('account.empty-accesstoken');
    }


    /**
     * 判断请求中是否需要获取用户信息或仅需获取用户ID
     * @return bool
     */
    private static function _isGetUser()
    {
        $query = bizContentFilter(array(
            'get_user'
        ));

        // 如果请求参数中有access_token则直接返回参数中的token
        if (!empty($query->get_user) && $query->get_user === true)
            return true;

        // 从 URL地址 中获取
        if (isset($_GET['get_user']) && $_GET['get_user'] === 'true')
            return true;

        return false;
    }
}

?>