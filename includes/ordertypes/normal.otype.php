<?php

/**
 *    普通订单类型
 *
 *    @author    Garbin
 *    @usage    none
 */
class NormalOrder extends BaseOrder
{
    var $_name = 'normal';

    /**
     *    查看订单
     *
     *    @author    Garbin
     *    @param     int $order_id
     *    @param     array $order_info
     *    @return    array
     */
    function get_order_detail($order_id, $order_info)
    {
        if (!$order_id)
        {
            return array();
        }

        /* 获取商品列表 */
        $data['goods_list'] =   $this->_get_goods_list($order_id);

        /* 配关信息 */
        $data['order_extm'] =   $this->_get_order_extm($order_id);

        /* 支付方式信息 */
        if ($order_info['payment_id'])
        {
            $payment_model      =& m('payment');
            $payment_info       =  $payment_model->get("payment_id={$order_info['payment_id']}");
            $data['payment_info']   =   $payment_info;
        }

        /* 订单操作日志 */
        $data['order_logs'] =   $this->_get_order_logs($order_id);

        return array('data' => $data);
    }

    /* 显示订单表单 */
    function get_order_form($store_id)
    {
        $model_region =& m('region');
        
		$data = array();
        $template = 'order.form.html';

        $visitor =& env('visitor');

        /* 获取我的收货地址 */
        $data['my_address']         = $this->_get_my_address($visitor->get('user_id'));
        $data['addresses']          =   ecm_json_encode($data['my_address']);
        $data['regions']            = $this->_get_regions();
		
        /* 配送方式 */
        $data['shipping_methods']   = $this->_get_shipping_methods(0);//TODO $store_id update by wxgang
        if (empty($data['shipping_methods']))
        {
            $this->_error('no_shipping_methods');

            return false;
        }
		foreach ($data['shipping_methods'] as &$shipping)
        {
            $data['shipping_options'][$shipping['shipping_id']] = $shipping['shipping_name'];
			$shipping['first_weight_kg'] = $shipping['first_weight']/1000;
			$shipping['step_weight_kg'] = $shipping['step_weight']/1000;
			$shipping['cod_regions'] = unserialize($shipping['cod_regions']);
			$shipping['cod_region_allids'] = array();

			foreach ($shipping['cod_regions'] as  $cod_region_id => $cod_region_value){
				$descendant_region_ids = $model_region->get_descendant($cod_region_id);
				$shipping['cod_region_allids'][$cod_region_id] = $descendant_region_ids;
			}
        }

        $data['shippings']          = ecm_json_encode($data['shipping_methods']);

        return array('data' => $data, 'template' => $template);
    }

    /**
     *    提交生成订单，外部告诉我要下的单的商品类型及用户填写的表单数据以及商品数据，我生成好订单后返回订单ID
     *
     *    @author    Garbin
     *    @param     array $data
     *    @return    int
     */
    function submit_order($data)
    {
        /* 释放goods_info和post两个变量 */
        extract($data);
        /* 处理订单基本信息 */
        $base_info = $this->_handle_order_info($goods_info, $post);
        if (!$base_info)
        {
            /* 基本信息验证不通过 */

            return 0;
        }

        /* 处理订单收货人信息 */
        $consignee_info = $this->_handle_consignee_info($goods_info, $post);

		//print_r($consignee_info);die("xxxxx");
        if (!$consignee_info)
        {
            /* 收货人信息验证不通过 */
            return 0;
        }

        /* 至此说明订单的信息都是可靠的，可以开始入库了 */

        /* 插入订单基本信息 */
        //订单总实际总金额，可能还会在此减去折扣等费用
        $base_info['order_amount']  =   $base_info['goods_amount'] + $consignee_info['shipping_fee'] - $base_info['discount'];
        
        /* 如果优惠金额大于商品总额和运费的总和 */
        if ($base_info['order_amount'] < 0)
        {
            $base_info['order_amount'] = 0;
            $base_info['discount'] = $base_info['goods_amount'] + $consignee_info['shipping_fee'];
        }
        $order_model =& m('order');
        $order_id    = $order_model->add($base_info);

        if (!$order_id)
        {
            /* 插入基本信息失败 */
            $this->_error('create_order_failed');

            return 0;
        }

        /* 插入收货人信息 */
        $consignee_info['order_id'] = $order_id;
        $order_extm_model =& m('orderextm');
        $order_extm_model->add($consignee_info);

        /* 插入商品信息 */
        $goods_items = array();
        foreach ($goods_info['items'] as $key => $value)
        {
            $goods_items[] = array(
                'order_id'      =>  $order_id,
                'goods_id'      =>  $value['goods_id'],
                'goods_name'    =>  $value['goods_name'],
                'spec_id'       =>  $value['spec_id'],
                'specification' =>  $value['specification'],
                'price'         =>  $value['price'],
                'quantity'      =>  $value['quantity'],
                'goods_image'   =>  $value['goods_image'],
            );
        }
        $order_goods_model =& m('ordergoods');
        $order_goods_model->add(addslashes_deep($goods_items)); //防止二次注入

        return $order_id;
    }
}

?>