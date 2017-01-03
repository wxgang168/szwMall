<?php

define('ROOT_PATH', dirname(__FILE__));
$xml = $GLOBALS['HTTP_RAW_POST_DATA'];

file_put_contents(ROOT_PATH.'/temp/logs/debug.log', "[".date('Y-m-d H:i:s', time())."][wx_callback_begin]----".$xml."\r\n",FILE_APPEND);

/**
 * 安装判断
 */
if (!file_exists(ROOT_PATH . "/bbs.52jscn.com.lock") && is_dir(ROOT_PATH . "/install")){
	@header("location: install");
	exit;
}

include(ROOT_PATH . '/eccore/ecmall.php');

/* 定义配置信息 */
ecm_define(ROOT_PATH . '/data/config.inc.php');


//define('ECMALL_WAP', 1);
/* 启动ECMall */
ECMall::startup(array(
    'default_app'   =>  'paywxnotify',
    'default_act'   =>  'notify',
    'app_root'      =>  ROOT_PATH . '/app',
    'external_libs' =>  array(
        ROOT_PATH . '/includes/global.lib.php',
        ROOT_PATH . '/includes/libraries/time.lib.php',
        ROOT_PATH . '/includes/ecapp.base.php',
        ROOT_PATH . '/includes/plugin.base.php',
        ROOT_PATH . '/app/frontend.base.php',
        ROOT_PATH . '/includes/subdomain.inc.php',
    ),
));
?>
