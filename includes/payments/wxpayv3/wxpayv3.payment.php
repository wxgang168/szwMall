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
        $out_trade_no = $this->_get_trade_sn($order_info);
	   //模式二
    	/**
    	 * 流程：
    	 * 1、调用统一下单，取得code_url，生成二维码
    	 * 2、用户扫描二维码，进行支付
    	 * 3、支付完成之后，微信服务器会通知支付成功
    	 * 4、在支付成功通知中需要查单确认是否真正支付成功（见：notify.php）
    	 */
        $unifiedOrder = new WxPayUnifiedOrder();
        $unifiedOrder->SetBody("支付 订单号:{$out_trade_no}");
        $unifiedOrder->SetAttach("test");
        $unifiedOrder->SetOut_trade_no($out_trade_no);//商户订单号
        $unifiedOrder->SetTotal_fee(strval(intval($order_info['order_amount']*100)));//总金额
        $unifiedOrder->SetTime_start(date("YmdHis"));
        $unifiedOrder->SetTime_expire(date("YmdHis", time() + 600));
        $unifiedOrder->SetGoods_tag("test");
        $unifiedOrder->SetNotify_url($this->_create_notify_url());
        $unifiedOrder->SetTrade_type("NATIVE");
        $unifiedOrder->SetProduct_id(strval($order_info['order_id']));
        $queryCounter = 0;$issucess = false;
        do{
            try {
                $html = '';
                $result = $this->GetPayUrl($unifiedOrder);
                if (empty($result["code_url"])) {
                    throw new WxPayException("对接错误");
                }else{
                    $native2url = $result["code_url"];
                    $html .= '<img alt="扫码支付" src="'.SITE_URL .'/qrcode.php?data='.urlencode($native2url).'" style="width:150px;height:150px;"/>';
                    $issucess = true;
                }
            } catch (Exception $e) {
                $html .= '<div>'.$e->getMessage().'<div/>';
            }
        } while (!$issucess && $queryCounter <3);
    	
    	
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
	
    function _create_notify_url()
    {
        return SITE_URL . "/wx_callback.php";
    }
}
?>