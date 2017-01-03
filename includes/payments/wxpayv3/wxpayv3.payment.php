<?php
/**
 *    微信支付插件
 *
 *    @author    bbs.52jscn.com
 *    
 */

require_once dirname(__FILE__)."/lib/WxPay.Api.php";
require_once dirname(__FILE__).'/lib/WxPay.Notify.php';

class Wxpayv3Payment extends BasePayment
{
	
	function __construct()
	{
		
	}
	
	function get_payform($order_info)
	{
		if(!defined('WXAPPID'))
        {
            define("WXAPPID", $this->_config['appid']);
            define("WXMCHID", $this->_config['mchid']);
            define("WXKEY", $this->_config['key']);
            define("WXAPPSECRET", $this->_config['appsecret']);
            define("WXCURL_TIMEOUT", 30);
            define('WXNOTIFY_URL',$this->_create_notify_url($order_info['order_id']));
//             define('WXJS_API_CALL_URL',$this->_create_notify_url($order_info['order_id']));
//             define('WXSSLCERT_PATH',ROOT_PATH.'/data/cacert/'.$order_info['seller_id'].'/apiclient_cert.pem');
//             define('WXSSLKEY_PATH',ROOT_PATH.'/data/cacert/'.$order_info['seller_id'].'/apiclient_key.pem');
            define('WXSSLCERT_PATH',ROOT_PATH.'/data/cacert/apiclient_cert.pem');
            define('WXSSLKEY_PATH',ROOT_PATH.'/data/cacert/apiclient_key.pem');
        }
        
//         require_once(dirname(__FILE__)."/WxPayPubHelper/WxPayPubHelper.php");
        $out_trade_no = $this->_get_trade_sn($order_info);
        
        /* 
		$jsApi = new JsApi_pub();
		if (!isset($_GET['code']))
        {
            $redirect = urlencode(SITE_URL.'/index.php?app=cashier&act=wxjsapi&order_id='.$order_info['order_id']);
            $url = $jsApi->createOauthUrlForCode($redirect);
            Header("Location: $url"); 
        }else
        {
            $code = $_GET['code'];
            $jsApi->setCode($code);
            $openid = $jsApi->getOpenId();
        }
          */
        
        if(true)
        {
        	//模式二
        	/**
        	 * 流程：
        	 * 1、调用统一下单，取得code_url，生成二维码
        	 * 2、用户扫描二维码，进行支付
        	 * 3、支付完成之后，微信服务器会通知支付成功
        	 * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
        	 */
        	
        	$unifiedOrder = new WxPayUnifiedOrder();
        	$unifiedOrder->SetBody("微信扫码支付模式二:{$out_trade_no}");
        	$unifiedOrder->SetAttach("test");
        	$unifiedOrder->SetOut_trade_no($out_trade_no);//商户订单号 
        	$unifiedOrder->SetTotal_fee(strval(intval($order_info['order_amount']*100)));//总金额
        	$unifiedOrder->SetTime_start(date("YmdHis"));
        	$unifiedOrder->SetTime_expire(date("YmdHis", time() + 600));
        	$unifiedOrder->SetGoods_tag("test");
        	$unifiedOrder->SetNotify_url($this->_create_notify_url());
        	$unifiedOrder->SetTrade_type("NATIVE");
        	$unifiedOrder->SetProduct_id(strval($order_info['order_id']));
        	$result = $this->GetPayUrl($unifiedOrder);
        	$native2url = $result["code_url"];
        	//$native2url = $this->GetPrePayUrl($out_trade_no);
        	$html .= '<div style="margin-left: 10px;color:#556B2F;font-size:30px;font-weight: bolder;">扫描支付</div><br/>';
        	$html .= '<img alt="扫码支付" src="'.SITE_URL .'/qrcode.php?data='.urlencode($native2url).'" style="width:150px;height:150px;"/>';
        }
        else
        {
            $html .= '<script language="javascript">';
            $html .= 'function callpay(){';
            $html .= 'alert("您的微信不支持支付功能,请更新您的微信到最新版本")';
            $html .= "}";
            $html .= '</script>';
            $html .= '<input style=" width:150px; height:30px;line-height:30px;text-align:center;background-color:#903; color:#fff; font-size:14px;" type="button" onclick="callpay()" value="点击微信支付" />';
            //$html .= '<button class="red_btn" type="button" onclick="callpay()">微信支付</button>';
        }
        return $html;

	}
	/**
	 *
	 * 生成扫描支付URL,模式一
	 * @param BizPayUrlInput $bizUrlInfo
	 */
	public function GetPrePayUrl($productId)
	{
		$biz = new WxPayBizPayUrl();
		$biz->SetProduct_id($productId);
		$values = WxpayApi::bizpayurl($biz);
		$url = "weixin://wxpay/bizpayurl?" . $this->ToUrlParams($values);
		return $url;
	}
	
	/**
	 *
	 * 参数数组转换为url参数
	 * @param array $urlObj
	 */
	private function ToUrlParams($urlObj)
	{
		$buff = "";
		foreach ($urlObj as $k => $v)
		{
			$buff .= $k . "=" . $v . "&";
		}
	
		$buff = trim($buff, "&");
		return $buff;
	}
	
	/**
	 *
	 * 生成直接支付url，支付url有效期为2小时,模式二
	 * @param UnifiedOrderInput $input
	 */
	public function GetPayUrl($input)
	{
		if($input->GetTrade_type() == "NATIVE")
		{
			$result = WxPayApi::unifiedOrder($input);
			return $result;
		}
	}
	
    function _create_notify_url($order_id)
    {
        return SITE_URL . "/wx_callback.php";
    }
}
?>