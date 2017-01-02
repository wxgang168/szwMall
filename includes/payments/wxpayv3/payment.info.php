<?php

return array(
    'code'      => 'wxpayv3',
    'name'      => Lang::get('wxpayv3'),
    'desc'      => Lang::get('wxpayv3_desc'),
    'is_online' => '1',
    'author'    => '282182901@qq.com',
    'website'   => 'http://mp.weixin.qq.com',
    'version'   => '1.0',
    'currency'  => Lang::get('wxpayv3_currency'),
    'config'    => array(
        'appid'   => array(        //AppId
            'text'  => Lang::get('wxpayv3_appid'),
            'desc'  => Lang::get('wxpayv3_appid_desc'),
            'type'  => 'text',
        ),
		'mchid'   => array(        //Mchid
            'text'  => Lang::get('wxpayv3_mchid'),
            'desc'  => Lang::get('wxpayv3_mchid_desc'),
            'type'  => 'text',
        ),
		'key'   => array(        //key
            'text'  => Lang::get('wxpayv3_key'),
            'desc'  => Lang::get('wxpayv3_key_desc'),
            'type'  => 'text',
        ),
		'appsecret'   => array(        //Appsecret
            'text'  => Lang::get('wxpayv3_appsecret'),
            'desc'  => Lang::get('wxpayv3_appsecret_desc'),
            'type'  => 'text',
        )
    ),
);

?>