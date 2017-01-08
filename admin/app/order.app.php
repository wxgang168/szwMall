<?php

/**
 *    合作伙伴控制器
 *
 *    @author    Garbin
 *    @usage    none
 */
class OrderApp extends BackendApp
{
    /**
     *    管理
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function index()
    {
        $search_options = array(
            'seller_name'   => Lang::get('store_name'),
            'buyer_name'   => Lang::get('buyer_name'),
            'payment_name'   => Lang::get('payment_name'),
            'order_sn'   => Lang::get('order_sn'),
        );
        /* 默认搜索的字段是店铺名 */
        $field = 'seller_name';
        array_key_exists($_GET['field'], $search_options) && $field = $_GET['field'];
        $conditions = $this->_get_query_conditions(array(array(
                'field' => $field,       //按用户名,店铺名,支付方式名称进行搜索
                'equal' => 'LIKE',
                'name'  => 'search_name',
            ),array(
                'field' => 'status',
                'equal' => '=',
                'type'  => 'numeric',
            ),array(
                'field' => 'add_time',
                'name'  => 'add_time_from',
                'equal' => '>=',
                'handler'=> 'gmstr2time',
            ),array(
                'field' => 'add_time',
                'name'  => 'add_time_to',
                'equal' => '<=',
                'handler'   => 'gmstr2time_end',
            ),array(
                'field' => 'order_amount',
                'name'  => 'order_amount_from',
                'equal' => '>=',
                'type'  => 'numeric',
            ),array(
                'field' => 'order_amount',
                'name'  => 'order_amount_to',
                'equal' => '<=',
                'type'  => 'numeric',
            ),
        ));
        $model_order =& m('order');
        $page   =   $this->_get_page(10);    //获取分页信息
        //更新排序
        if (isset($_GET['sort']) && isset($_GET['order']))
        {
            $sort  = strtolower(trim($_GET['sort']));
            $order = strtolower(trim($_GET['order']));
            if (!in_array($order,array('asc','desc')))
            {
             $sort  = 'add_time';
             $order = 'desc';
            }
        }
        else
        {
            $sort  = 'add_time';
            $order = 'desc';
        }
        $orders = $model_order->find(array(
            'conditions'    => '1=1 ' . $conditions,
            'limit'         => $page['limit'],  //获取当前页的数据
            'order'         => "$sort $order",
            'count'         => true             //允许统计
        )); //找出所有商城的合作伙伴
        $page['item_count'] = $model_order->getCount();   //获取统计的数据
        $this->_format_page($page);
        $this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('order_status_list', array(
            ORDER_PENDING => Lang::get('order_pending'),
            ORDER_SUBMITTED => Lang::get('order_submitted'),
            ORDER_ACCEPTED => Lang::get('order_accepted'),
            ORDER_SHIPPED => Lang::get('order_shipped'),
            ORDER_FINISHED => Lang::get('order_finished'),
            ORDER_CANCELED => Lang::get('order_canceled'),
        ));
        $this->assign('search_options', $search_options);
        $this->assign('page_info', $page);          //将分页信息传递给视图，用于形成分页条
        $this->assign('orders', $orders);
        $this->import_resource(array('script' => 'inline_edit.js,jquery.ui/jquery.ui.js,jquery.ui/i18n/' . i18n_code() . '.js',
                                      'style'=> 'jquery.ui/themes/ui-lightness/jquery.ui.css'));
        $this->display('order.index.html');
    }

    /**
     *    查看
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function view()
    {
        $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }

        /* 获取订单信息 */
        $model_order =& m('order');
        $order_info = $model_order->get(array(
            'conditions'    => $order_id,
            'join'          => 'has_orderextm',
            'include'       => array(
                'has_ordergoods',   //取出订单商品
            ),
        ));

        if (!$order_info)
        {
            $this->show_warning('no_such_order');
            return;
        }
        $order_type =& ot($order_info['extension']);
        $order_detail = $order_type->get_order_detail($order_id, $order_info);
        $order_info['group_id'] = 0;
        if ($order_info['extension'] == 'groupbuy')
        {
            $groupbuy_mod =& m('groupbuy');
            $groupbuy = $groupbuy_mod->get(array(
                'fields' => 'groupbuy.group_id',
                'join' => 'be_join',
                'conditions' => "order_id = {$order_info['order_id']} ",
                )
            );
            $order_info['group_id'] = $groupbuy['group_id'];
        }
        foreach ($order_detail['data']['goods_list'] as $key => $goods)
        {
            if (substr($goods['goods_image'], 0, 7) != 'http://')
            {
                $order_detail['data']['goods_list'][$key]['goods_image'] = SITE_URL . '/' . $goods['goods_image'];
            }
        }
        $this->assign('order', $order_info);
        $this->assign($order_detail['data']);
        $this->display('order.view.html');
    }


    /**
     *    调整费用
     *
     *    @author    Garbin
     *    @return    void
     */
    function adjust_fee()
    {
        list($order_id, $order_info)    = $this->_get_valid_order_info(array(ORDER_SUBMITTED, ORDER_PENDING));
        if (!$order_id)
        {
            $this->show_warning('no_such_order');

            return;
        }
        $model_order    =&  m('order');
        $model_orderextm =& m('orderextm');
        $shipping_info   = $model_orderextm->get($order_id);
        if (!IS_POST)
        {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->assign('shipping', $shipping_info);
            $this->display('seller_order.adjust_fee.html');
        }
        else
        {
            /* 配送费用 */
            $shipping_fee = isset($_POST['shipping_fee']) ? abs(floatval($_POST['shipping_fee'])) : 0;
            /* 折扣金额 */
            $goods_amount     = isset($_POST['goods_amount'])     ? abs(floatval($_POST['goods_amount'])) : 0;
            /* 订单实际总金额 */
            $order_amount = round($goods_amount + $shipping_fee, 2);
            if ($order_amount <= 0)
            {
                /* 若商品总价＋配送费用扣队折扣小于等于0，则不是一个有效的数据 */
                $this->show_warning('invalid_fee');

                return;
            }
            $data = array(
                'goods_amount'  => $goods_amount,    //修改商品总价
                'order_amount'  => $order_amount,     //修改订单实际总金额
                'pay_alter' => 1    //支付变更
            );

            if ($shipping_fee != $shipping_info['shipping_fee'])
            {
                /* 若运费有变，则修改运费 */

                $model_extm =& m('orderextm');
                $model_extm->edit($order_id, array('shipping_fee' => $shipping_fee));
            }
            $model_order->edit($order_id, $data);

            if ($model_order->has_error())
            {
                $this->show_warning($model_order->get_error());

                return;
            }
            /* 记录订单操作日志 */
            $order_log =& m('orderlog');
            $order_log->add(array(
                'order_id'  => $order_id,
                'operator'  => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status($order_info['status']),
                'remark'    => Lang::get('adjust_fee'),
                'log_time'  => gmtime(),
            ));

            /* 发送给买家邮件通知，订单金额已改变，等待付款 */
            $model_member =& m('member');
            $buyer_info   = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_adjust_fee_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'order_amount'  => price_format($order_amount),
            );

            $this->show_message('ok',
                'back_list',    'index.php?app=order',
                'edit_again',   'index.php?app=order&amp;act=adjust_fee&amp;id=' . $order_id
            );
        }
    }

    /**
     *    收到货款
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function received_pay()
    {
        list($order_id, $order_info)    = $this->_get_valid_order_info(ORDER_PENDING);
        if (!$order_id)
        {
            //echo Lang::get('no_such_order');

            $this->show_warning('no_such_order');

            return;
        }
        if (!IS_POST)
        {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('seller_order.received_pay.html');
        }
        else
        {
            $model_order    =&  m('order');
            $model_order->edit(intval($order_id), array('status' => ORDER_ACCEPTED, 'pay_time' => gmtime()));
            if ($model_order->has_error())
            {
                $this->show_warning($model_order->get_error());

                return;
            }

            #TODO 发邮件通知
            /* 记录订单操作日志 */
            $order_log =& m('orderlog');
            $order_log->add(array(
                'order_id'  => $order_id,
                'operator'  => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_ACCEPTED),
                'remark'    => $_POST['remark'],
                'log_time'  => gmtime(),
            ));

            /* 发送给买家邮件，提示等待安排发货 */
            $model_member =& m('member');
            $buyer_info   = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_offline_pay_success_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status'    => Lang::get('order_accepted'),
                'actions'   => array(
                    'cancel',
                    'shipped'
                ), //可以取消可以发货
            );

            $this->show_message('ok');
        }

    }

    /**
     *    货到付款的订单的确认操作
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function confirm_order()
    {
        list($order_id, $order_info)    = $this->_get_valid_order_info(ORDER_SUBMITTED);
        if (!$order_id)
        {
            //echo Lang::get('no_such_order');
            $this->show_warning('no_such_order');
            return;
        }
        if (!IS_POST)
        {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('seller_order.confirm.html');
        }
        else
        {
            $model_order    =&  m('order');
            $model_order->edit($order_id, array('status' => ORDER_ACCEPTED));
            if ($model_order->has_error())
            {
                $this->show_warning($model_order->get_error());

                return;
            }

            /* 记录订单操作日志 */
            $order_log =& m('orderlog');
            $order_log->add(array(
                'order_id'  => $order_id,
                'operator'  => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_ACCEPTED),
                'remark'    => $_POST['remark'],
                'log_time'  => gmtime(),
            ));

            /* 发送给买家邮件，订单已确认，等待安排发货 */
            $model_member =& m('member');
            $buyer_info   = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_confirm_cod_order_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status'    => Lang::get('order_accepted'),
                'actions'   => array(
                    'cancel',
                    'shipped'
                ), //可以取消可以发货
            );

            $this->show_warning('ok');;
        }
    }

    /**
     *    待发货的订单发货
     *
     *    @author    Garbin
     *    @return    void
     */
    function shipped()
    {
        list($order_id, $order_info)    = $this->_get_valid_order_info(array(ORDER_ACCEPTED, ORDER_SHIPPED));
        if (!$order_id)
        {
            //echo Lang::get('no_such_order');
            $this->show_warning('no_such_order');
            return;
        }
        $model_order    =&  m('order');
        if (!IS_POST)
        {
            /* 显示发货表单 */
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('order', $order_info);
            $this->display('seller_order.shipped.html');
        }
        else
        {
            if (!$_POST['invoice_no'])
            {
                $this->show_warning('invoice_no_empty');

                return;
            }
            $edit_data = array('status' => ORDER_SHIPPED, 'invoice_no' => $_POST['invoice_no']);
            $is_edit = true;
            if (empty($order_info['invoice_no']))
            {
                /* 不是修改发货单号 */
                $edit_data['ship_time'] = gmtime();
                $is_edit = false;
            }
            $model_order->edit(intval($order_id), $edit_data);
            if ($model_order->has_error())
            {
                $this->show_warning($model_order->get_error());

                return;
            }

            #TODO 发邮件通知
            /* 记录订单操作日志 */
            $order_log =& m('orderlog');
            $order_log->add(array(
                'order_id'  => $order_id,
                'operator'  => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_SHIPPED),
                'remark'    => $_POST['remark'],
                'log_time'  => gmtime(),
            ));


            /* 发送给买家订单已发货通知 */
            /*
            $model_member =& m('member');
            $buyer_info   = $model_member->get($order_info['buyer_id']);
            $order_info['invoice_no'] = $edit_data['invoice_no'];
            $mail = get_mail('tobuyer_shipped_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));
                //发送短信给买家 by andcpp
            $mod_order_extm =& m('orderextm');
            $row_order_extm = $mod_order_extm->getrow("select * from ".DB_PREFIX."order_extm where order_id=".$order_id);
            
            $user_id = $order_info['seller_id'];
            $user_name = $order_info['seller_name'];

            $mod_msg =& m('msg');

            $row_msg = $mod_msg->getrow("select * from ".DB_PREFIX."msg where user_id=".$user_id);
            $mobile = $row_order_extm['phone_mob']; //在订单中取买家手机号
            $smsText = "您的订单：".$order_info['order_sn'].",卖家：".$order_info['seller_name']."已经发货，请及时查收！";//内容
            $time = time();
            
            $checked_functions = $functions = array();
            $functions = $this->_get_functions();
            $tmp = explode(',', $row_msg['functions']);
            if ($functions)
            {
                foreach ($functions as $func)
                {
                    $checked_functions[$func] = in_array($func, $tmp);
                }
            }
            
            if($row_msg['state']==0)
            {
                $this->show_warning('ok');
                return;
            }
            if($checked_functions['send'] != 1)
            {
                $this->show_warning('ok');
                return;
            }
            if($row_msg['num']<=0)
            {
                $this->show_warning('ok');
                return;
            }
            if($mobile == '')
            {
                $this->show_warning('ok');
                return;
            }
            if($smsText == '')
            {
                $this->show_warning('ok');
                return;
            }
            $url='http://utf8.sms.webchinese.cn/?Uid='.SMS_UID.'&Key='.SMS_KEY.'&smsMob='.$mobile.'&smsText='.$smsText; 
            $res = $this->Sms_Get($url);*/
            if($res == '')
            {
                $this->show_warning('ok');
                return;
            }
            else if($res>0)
            {
                $num = $row_msg['num']-1;
                $edit_msg = array(
                    'num' => $num,
                );
                $add_msglog = array(
                    'user_id' => $user_id,
                    'user_name' => $user_name,
                    'to_mobile' => $mobile,
                    'content' => $smsText,
                    'state' => $res,
                    'time' => $time,
                );
                $this->mod_msglog->add($add_msglog);
                $this->mod_msg->edit('user_id='.$user_id,$edit_msg);
                $this->show_warning('ok');
                return;
            }
            else
            {
                $add_msglog = array(
                    'user_id' => $user_id,
                    'user_name' => $user_name,
                    'to_mobile' => $mobile,
                    'content' => $content,
                    'state' => $res,
                    'time' => $time,
                );
                $this->mod_msglog->add($add_msglog);
                $this->show_warning('ok');
                return;
            }
            // end

            $new_data = array(
                'status'    => Lang::get('order_shipped'),
                'actions'   => array(
                    'cancel',
                    'edit_invoice_no'
                ), //可以取消可以发货
            );
            if ($order_info['payment_code'] == 'cod')
            {
                $new_data['actions'][] = 'finish';
            }

            $this->show_warning('ok');
        }
    }

    /**
     *    取消订单
     *
     *    @author    Garbin
     *    @return    void
     */
    function cancel_order()
    {
        /* 取消的和完成的订单不能再取消 */
        //list($order_id, $order_info)    = $this->_get_valid_order_info(array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED));
        $order_id = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!$order_id)
        {
            $this->show_message('no_such_order');
            return;
        }
        $status = array(ORDER_SUBMITTED, ORDER_PENDING, ORDER_ACCEPTED, ORDER_SHIPPED);
        $order_ids = explode(',', $order_id);
        if ($ext)
        {
            $ext = ' AND ' . $ext;
        }

        $model_order    =&  m('order');
        /* 只有已发货的货到付款订单可以收货 */
        $order_info     = $model_order->find(array(
            'conditions'    => "order_id" . db_create_in($order_ids) ." AND status " . db_create_in($status) . $ext,
        ));
        $ids = array_keys($order_info);
        if (!$order_info)
        {
            //echo Lang::get('no_such_order');
            $this->show_message('no_such_order');
            return;
        }
        if (!IS_POST)
        {
            header('Content-Type:text/html;charset=' . CHARSET);
            $this->assign('orders', $order_info);
            $this->assign('order_id', count($ids) == 1 ? current($ids) : implode(',', $ids));
            $this->display('seller_order.cancel.html');
        }
        else
        {
            $model_order    =&  m('order');
            foreach ($ids as $val)
            {
                $id = intval($val);
                $model_order->edit($id, array('status' => ORDER_CANCELED));
                if ($model_order->has_error())
                {
                    //$_erros = $model_order->get_error();
                    //$error = current($_errors);
                    //$this->json_error(Lang::get($error['msg']));
                    //return;
                    continue;
                }

                /* 加回订单商品库存 */
                $model_order->change_stock('+', $id);
                $cancel_reason = (!empty($_POST['remark'])) ? $_POST['remark'] : $_POST['cancel_reason'];
                /* 记录订单操作日志 */
                $order_log =& m('orderlog');
                $order_log->add(array(
                    'order_id'  => $id,
                    'operator'  => addslashes($this->visitor->get('user_name')),
                    'order_status' => order_status($order_info[$id]['status']),
                    'changed_status' => order_status(ORDER_CANCELED),
                    'remark'    => $cancel_reason,
                    'log_time'  => gmtime(),
                ));

                /* 发送给买家订单取消通知 */
                $model_member =& m('member');
                $buyer_info   = $model_member->get($order_info[$id]['buyer_id']);
                $mail = get_mail('tobuyer_cancel_order_notify', array('order' => $order_info[$id], 'reason' => $_POST['remark']));
                $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

                $new_data = array(
                    'status'    => Lang::get('order_canceled'),
                    'actions'   => array(), //取消订单后就不能做任何操作了
                );
            }
            $this->show_warning('ok', 'seller_order_cancel_order');
        }

    }

    /**
     *    完成交易(货到付款的订单)
     *
     *    @author    Garbin
     *    @return    void
     */
    function finished()
    {
        list($order_id, $order_info)    = $this->_get_valid_order_info(ORDER_SHIPPED, 'payment_code=\'cod\'');
        if (!$order_id)
        {
            //echo Lang::get('no_such_order');
            $this->show_warning('no_such_order');
            return;
        }
        if (!IS_POST)
        {
            header('Content-Type:text/html;charset=' . CHARSET);
            /* 当前用户中心菜单 */
            $this->_curitem('seller_order');
            /* 当前所处子菜单 */
            $this->_curmenu('finished');
            $this->assign('_curmenu','finished');
            $this->assign('order', $order_info);
            $this->display('seller_order.finished.html');
        }
        else
        {
            $now = gmtime();
            $model_order    =&  m('order');
            $model_order->edit($order_id, array('status' => ORDER_FINISHED, 'pay_time' => $now, 'finished_time' => $now));
            if ($model_order->has_error())
            {
                $this->show_warning($model_order->get_error());

                return;
            }

            /* 记录订单操作日志 */
            $order_log =& m('orderlog');
            $order_log->add(array(
                'order_id'  => $order_id,
                'operator'  => addslashes($this->visitor->get('user_name')),
                'order_status' => order_status($order_info['status']),
                'changed_status' => order_status(ORDER_FINISHED),
                'remark'    => $_POST['remark'],
                'log_time'  => gmtime(),
            ));

            /* 更新累计销售件数 */
            $model_goodsstatistics =& m('goodsstatistics');
            $model_ordergoods =& m('ordergoods');
            $order_goods = $model_ordergoods->find("order_id={$order_id}");
            foreach ($order_goods as $goods)
            {
                $model_goodsstatistics->edit($goods['goods_id'], "sales=sales+{$goods['quantity']}");
            }
            
            
            /* 发送给买家交易完成通知，提示评论 */
            $model_member =& m('member');
            $buyer_info   = $model_member->get($order_info['buyer_id']);
            $mail = get_mail('tobuyer_cod_order_finish_notify', array('order' => $order_info));
            $this->_mailto($buyer_info['email'], addslashes($mail['subject']), addslashes($mail['message']));

            $new_data = array(
                'status'    => Lang::get('order_finished'),
                'actions'   => array(), //完成订单后就不能做任何操作了
            );

            $this->show_warning('ok');
        }

    }

    /**
     *    获取有效的订单信息
     *
     *    @author    Garbin
     *    @param     array $status
     *    @param     string $ext
     *    @return    array
     */
    function _get_valid_order_info($status, $ext = '')
    {
        $order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$order_id)
        {

            return array();
        }
        if (!is_array($status))
        {
            $status = array($status);
        }

        if ($ext)
        {
            $ext = ' AND ' . $ext;
        }

        $model_order    =&  m('order');
        /* 只有已发货的货到付款订单可以收货 */
        $order_info     = $model_order->get(array(
            'conditions'    => "order_id={$order_id} " . " AND status " . db_create_in($status) . $ext,
        ));
        if (empty($order_info))
        {

            return array();
        }

        return array($order_id, $order_info);
    }

}
?>
