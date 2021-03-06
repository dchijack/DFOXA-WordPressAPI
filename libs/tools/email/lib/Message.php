<?php

namespace tools\email;

use Respect\Validation\Validator as Validator;

class Message extends sendEmail
{
    function __construct()
    {
        parent::__construct();
    }

    public function sendWelcome($email, $user_nickname, $event, $password = null)
    {
        if (!Validator::email()->validate($email))
            dfoxaError('account.error-email');


        $appname = is_multisite() ? get_blog_option(get_main_site_id(), 'dfoxa_t_email_param_appname') : get_option('dfoxa_t_email_param_appname');
        $subject = '欢迎加入我们！来自' . $appname . '的问候';
        $sendTo = $email;

        if ($password === null) {
            $theme = dirname(__DIR__) . '/templates/welcome.theme';
        } else {
            $theme = dirname(__DIR__) . '/templates/welcome_pw.theme';
        }

        $sendBody = parent::filterParam($theme, array(
            'user_nickname' => $user_nickname,
            'query_event' => $event,
            'user_email' => $email,
            'password' => $password
        ));

        $request = parent::send($subject, $sendTo, $sendBody);
        if (!$request)
            dfoxaError('account.senderror-email');

        return true;
    }
}
