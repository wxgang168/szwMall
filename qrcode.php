<?php
error_reporting(E_ERROR);

define('ROOT_PATH', dirname(__FILE__));

require_once(ROOT_PATH . '/includes/libraries/phpqrcode.lib.php');
$url = urldecode($_GET["data"]);
QRcode::png($url);