<?php
 
require_once ROOT_PATH."/includes/payments/wxpayv3/lib/WxPay.Api.php";




/**
 *    支付网关通知接口
 *
 *    @author    Garbin
 *    @usage    none
 */
class PaywxnotifyApp extends MallbaseApp
{
	/**
	 *    回调入口,支付完成后，微信支付网关的通知地址，在此会进行订单状态的改变，这里严格验证，改变订单状态
	 *
	 *    @author    Garbin
	 *    @return    void
	 */
	public function notify()
	{
		$WxPayNotify = new WxPayNotify();
		$result = $WxPayNotify->handle();
		if (false && $result['target'] == ORDER_ACCEPTED)
		{
			/* 发送邮件给卖家，提醒付款成功 */
			$model_member =& m('member');
			$seller_info  = $model_member->get($result['order']['seller_id']);
			 
			$mail = get_mail('toseller_online_pay_success_notify', array('order' => $result['order']));
			$this->_mailto($seller_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

			/* 同步发送 */
			$this->_sendmail(true);
		}
	}
}

/**
 *    支付网关通知接口
 *
 *    @author    Garbin
 *    @usage    none
 */
class WxPayNotify extends WxPayNotifyReply
{
    /**
     *    回调入口,支付完成后，微信支付网关的通知地址，在此会进行订单状态的改变，这里严格验证，改变订单状态
     *
     *    @author    Garbin
     *    @return    void
     */
    public function handle()
    {
    	$msg = "OK";
    	
    	//当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
    	$result = WxpayApi::notify(array($this, 'NotifyCallBack'), $msg);
    	
    	if($result == false){
    		$this->SetReturn_code("FAIL");
    		$this->SetReturn_msg($msg);
    		$this->ReplyNotify(false);
    		return;
    	} else {
    		//该分支在成功回调到NotifyCallBack方法，处理完成之后流程
    		$this->SetReturn_code("SUCCESS");
    		$this->SetReturn_msg("OK");
    	}
    	
    	// @param bool $needSign  是否需要签名输出
    	$this->ReplyNotify(true);
    	return $result;
    	
    }

    /**
     *
     * 回调方法入口，子类可重写该方法
     * 注意：
     * 1、微信回调超时时间为2s，建议用户使用异步处理流程，确认成功之后立刻回复微信服务器
     * 2、微信服务器在调用失败或者接到回包为非确认包的时候，会发起重试，需确保你的回调是可以重入
     * @param array $data 回调解释出的参数
     * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    public function NotifyProcess($data, &$msg)
    {
    	
    	$result = $this->InnerNotifyProcess($data,$msg);
    	if (!$result){
    		return false;
    	}
    	//TODO 用户基础该类之后需要重写该方法，成功的时候返回true，失败返回false
    	//------return true;
    	$out_trade_no   = $data['out_trade_no'];
    	
    	if (!$out_trade_no)
    	{
    		/* 无效的通知请求 */
    		//$this->show_warning('no_such_order');
    		$msg = 'no out_trade_no';
    		return false;
    	}
    	
    	/* 获取订单信息 */
    	$model_order =& m('order');
    	$order_info  = $model_order->get(array('out_trade_no' => $out_trade_no));
    	if (empty($order_info))
    	{
    		/* 没有该订单 */
    		//$this->show_warning('no_such_order');
    		$msg = 'no_such_order';
    		return false;
    	}
    	
    	
    	$notify_result['target'] = ORDER_ACCEPTED;
    	//改变订单状态
    	$this->_change_order_status($order_info['order_id'], $order_info['extension'], $notify_result);
    	$notify_result['order'] = $order_info;
    	return $notify_result;
    }
    
    /**
     *
     * notify回调方法，该方法中需要赋值需要输出的参数,不可重写
     * @param array $data
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    final public function NotifyCallBack($data)
    {
    	$msg = "OK";
    	$result = $this->NotifyProcess($data, $msg);
    
    	if($result == true){
    		$this->SetReturn_code("SUCCESS");
    		$this->SetReturn_msg("OK");
    	} else {
    		$this->SetReturn_code("FAIL");
    		$this->SetReturn_msg($msg);
    	}
    	return $result;
    }
    
    /**
     *
     * 回复通知
     * @param bool $needSign 是否需要签名输出
     */
    final private function ReplyNotify($needSign = true)
    {
    	//如果需要签名
    	if($needSign == true &&
    			$this->GetReturn_code($return_code) == "SUCCESS")
    	{
    		$this->SetSign();
    	}
    	WxpayApi::replyNotify($this->ToXml());
    }
    
    
    //查询订单
    public function Queryorder($transaction_id)
    {
    	$input = new WxPayOrderQuery();
    	$input->SetTransaction_id($transaction_id);
    	$result = WxPayApi::orderQuery($input);
    	
    	file_put_contents(ROOT_PATH.'/temp/logs/debug.log', "[".date('Y-m-d H:i:s', time())."][wx_callback]----".print_r($result,true)."\r\n",FILE_APPEND);
    	
    	if(array_key_exists("return_code", $result)
    			&& array_key_exists("result_code", $result)
    			&& $result["return_code"] == "SUCCESS"
    			&& $result["result_code"] == "SUCCESS")
    	{
    		return true;
    	}
    	return false;
    }
    
    //重写回调处理函数
    public function InnerNotifyProcess($data, &$msg)
    {
    	Log::DEBUG("call back:" . json_encode($data));
    	$notfiyOutput = array();
    
    	if(!array_key_exists("transaction_id", $data)){
    		$msg = "输入参数不正确";
    		return false;
    	}
    	//查询订单，判断订单真实性
    	if(!$this->Queryorder($data["transaction_id"])){
    		$msg = "订单查询失败";
    		return false;
    	}
    	return true;
    }
    
    
    /**
     *    改变订单状态
     *
     *    @author    Garbin
     *    @param     int $order_id
     *    @param     string $order_type
     *    @param     array  $notify_result
     *    @return    void
     */
    function _change_order_status($order_id, $order_type, $notify_result)
    {
        /* 将验证结果传递给订单类型处理 */
        $order_type  =& ot($order_type);
        $order_type->respond_notify($order_id, $notify_result);    //响应通知
    }
}

?>
