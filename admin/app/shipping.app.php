<?php

/**
 *    配送方式控制器
 *
 *    @author    Garbin
 *    @usage    none
 */

class ShippingApp extends BackendApp
{
    
    function index()
    {
        $model_shipping =& m('shipping');
        //更新排序
        if (isset($_GET['sort']) && !empty($_GET['order']))
        {
            $sort  = strtolower(trim($_GET['sort']));
            $order = strtolower(trim($_GET['order']));
            if (!in_array($order,array('asc','desc')))
            {
             $sort  = 'shipping_id';
             $order = 'asc';
            }
        }
        else
        {
            if (isset($_GET['sort']) && empty($_GET['order']))
            {
                $sort  = strtolower(trim($_GET['sort']));
                $order = "";
            }
            else
            {
                $sort  = 'shipping_id';
                $order = 'asc';
            }
        }
        $page = $this->_get_page();
        $shippings = $model_shipping->find(array(
            //'join' => 'has_store,manage_mall',
            'fields' => '*',
            'conditions' => 'store_id = 0' ,
            'limit' => $page['limit'],
            'order' => "$sort $order",
            'count' => true,
        ));

		$shippingsetting = $this->_get_shippings();
			$this->assign('expresses',$shippingsetting['expresses']);

        foreach ($shippings as $key => $val)
        {
            /*if ($val['priv_store_id'] == 0 && $val['privs'] != '')
            {
                $shippings[$key]['if_admin'] = true;
            }
			*/
			if ($val['first_weight'] !=0)
			{
				$shippings[$key]['first_weight'] = $val['first_weight']/1000;
			}
			if ($val['step_weight'] !=0)
			{
				$shippings[$key]['step_weight'] = $val['step_weight']/1000;
			}
			$shippings[$key]['express_desc'] = $shippingsetting['expresses'][$val['express_id']];
        }
        $this->assign('shippings', $shippings);
        $page['item_count'] = $model_shipping->getCount();
        $this->_format_page($page);
        //$this->assign('filtered', $conditions? 1 : 0); //是否有查询条件
        $this->assign('page_info', $page);
        /* 导入jQuery的表单验证插件 */
        $this->import_resource(array(
            'script' => 'jqtreetable.js,inline_edit.js',
            'style'  => 'res:style/jqtreetable.css'
        ));
/*
        $this->assign('query_fields', array(
            'user_name' => LANG::get('user_name'),
            'email'     => LANG::get('email'),
            'real_name' => LANG::get('real_name'),
        ));
        $this->assign('sort_options', array(
            'reg_time'   => LANG::get('reg_time'),
            'last_login' => LANG::get('last_login'),
            'logins'     => LANG::get('logins'),
        ));
        */
        //$this->assign('if_system_manager', $this->_admin_mod->check_system_manager($this->visitor->get('user_id')) ? 1 : 0);
        $this->display('shipping.index.html');
    }

    function add()
    {
        if (!IS_POST)
        {
            $this->_assign_form();
			
			$shippingsetting = $this->_get_shippings();
			$this->assign('expresses',$shippingsetting['expresses']);
			$this->assign('first_weights',$shippingsetting['first_weights'] );
			$this->assign('step_weights',$shippingsetting['step_weights'] );

            $this->_get_regions();
			$this->assign('cod_regions', array());
            
			/* 导入jQuery的表单验证插件 */
            
            $this->import_resource('mlselection.js, jquery.plugins/jquery.validate.js');
            header("Content-Type:text/html;charset=" . CHARSET);
            $this->display('shipping.form.html');
        }
        else
        {
            $data = array(
                'store_id'      => $this->visitor->get('manage_store'),
                'shipping_name' => $_POST['shipping_name'],
                'shipping_desc' => $_POST['shipping_desc'],
                'express_id'	=> $_POST['express_id'],
				'first_weight'  => $_POST['first_weight'],
                'step_weight'   => $_POST['step_weight'],
                'first_price'   => $_POST['first_price'],
                'step_price'    => $_POST['step_price'],
                'enabled'       => $_POST['enabled'],
                'sort_order'    => $_POST['sort_order'],
            );
			$data['cod_regions'] = (empty($_POST['cod_region_assign']) || empty($_POST['cod_regions'])) ? array() : $_POST['cod_regions'];
			$data['cod_regions']    =   serialize($_POST['cod_regions']);

            $model_shipping =& m('shipping');
            if (!($shipping_id = $model_shipping->add($data)))
            {
                //$this->show_warning($model_shipping->get_error());
                $this->show_warning($model_shipping->get_error());
                return;
            }
			$this->show_message(Lang::get('add_ok'),
                'back_list',    'index.php?app=shipping',
                'continue_add', 'index.php?app=shipping&amp;act=add'
            );
        }
    }
    
    /**
     *    编辑配送方式
     *
     *    @author    Garbin
     *    @return    void
     */
    function edit()
    {
        $shipping_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$shipping_id)
        {
            //echo Lang::get('no_such_shipping');
			$this->show_warning(Lang::get('no_such_shipping'));
            return;
        }

        /* 判断是否是自己的 */
        $model_shipping =& m('shipping');
        $shipping = $model_shipping->get("store_id= 0" . //$this->visitor->get('manage_store') . 
			" AND shipping_id={$shipping_id}");
        if (!$shipping)
        {
			$this->show_warning(Lang::get('no_such_shipping'));
            return;
        }
        if (!IS_POST)
        {
            $shippingsetting = $this->_get_shippings();
			$this->assign('expresses',$shippingsetting['expresses']);
			$this->assign('first_weights',$shippingsetting['first_weights'] );
			$this->assign('step_weights',$shippingsetting['step_weights'] );

            $this->_get_regions();

			$this->assign('cod_regions', array());
            $cod_regions = unserialize($shipping['cod_regions']);
            !$cod_regions && $cod_regions = array();

			
            $this->assign('shipping', $shipping);
            $this->assign('cod_regions', $cod_regions);
            $this->assign('yes_or_no', array(1 => Lang::get('yes'), 0 => Lang::get('no')));
            $this->import_resource('mlselection.js, jquery.plugins/jquery.validate.js');
            
            $this->display('shipping.form.html');
        }
        else
        {
            $data = array(
                //'shipping_name' => $_POST['shipping_name'],
                'shipping_desc' => $_POST['shipping_desc'],
				'express_id'	=> $_POST['express_id'],
				'first_weight'  => $_POST['first_weight'],
                'step_weight'   => $_POST['step_weight'],
                'first_price'   => $_POST['first_price'],
                'step_price'    => $_POST['step_price'],
                'enabled'       => $_POST['enabled'],
                'sort_order'    => $_POST['sort_order'],
            );
            $cod_regions = (empty($_POST['cod_region_assign']) || empty($_POST['cod_regions'])) ? array() : $_POST['cod_regions'];
            $data['cod_regions']    =   serialize($cod_regions);
            $model_shipping =& m('shipping');
            $model_shipping->edit($shipping_id, $data);
            if ($model_shipping->has_error())
            {
                //$this->show_warning($model_shipping->get_error());
                $msg = $model_shipping->get_error();
                $this->show_warning($msg['msg']);
                return;
            }
			$this->show_message(Lang::get('edit_ok'),
                'back_list',    'index.php?app=shipping',
                'continue_edit', 'index.php?app=shipping&amp;act=edit&amp;id='.$shipping_id
            );
        }
    }

    /**
     *    删除配送方式
     *
     *    @author    Garbin
     *    @param    none
     *    @return    void
     */
    function drop()
    {
        $shipping_id = isset($_GET['id']) ? trim($_GET['id']) : 0;
        if (!$shipping_id)
        {
            $this->show_warning(Lang::get('no_such_shipping'));

            return;
        }
        $ids = explode(',', $shipping_id);//获取一个类似array(1, 2, 3)的数组
        $model_shipping  =& m('shipping');
        $drop_count = $model_shipping->drop("store_id = 0" . //$this->visitor->get('manage_store') . 
		" AND shipping_id " . db_create_in($ids));
        if (!$drop_count)
        {
            /* 没有可删除的项 */
            $this->show_warning(Lang::get('no_such_shipping'));

            return;
        }

        if ($model_shipping->has_error())    //出错了
        {
            $this->show_warning($model_shipping->get_error());

            return;
        }

        $this->show_message(Lang::get('drop_shipping_successed'));
    }

	/**
     *    启用
     *
     *    @author    Garbin
     *    @return    void
     */
    function enable()
    {
        $shipping_id = isset($_GET['id'])    ? trim($_GET['id']) : 0;
        if (!$shipping_id)
        {
            $this->show_warning(Lang::get('no_such_shipping'));

            return;
        }
        $model_shipping =& m('shipping');
        if (!$model_shipping->enable_builtin($shipping_id))
        {
            $this->show_warning($model_shipping->get_error());

            return;
        }

        $this->show_message(Lang::get('enable_shipping_successed'));

    }

	 //异步修改数据
   function ajax_col()
   {
       $id     = empty($_GET['id']) ? 0 : intval($_GET['id']);
       $column = empty($_GET['column']) ? '' : trim($_GET['column']);
       $value  = isset($_GET['value']) ? trim($_GET['value']) : '';
       $data   = array();

       if (in_array($column ,array('goods_name', 'brand', 'closed')))
       {
           $data[$column] = $value;
           $this->_goods_mod->edit($id, $data);
           if(!$this->_goods_mod->has_error())
           {
               echo ecm_json_encode(true);
           }
       }
       else
       {
           return ;
       }
       return ;
   }

    function _get_shippings()
    {
        $shipping = include(APP_ROOT . '/../data/shipping.inc.php');

        return $shipping;
    }
    

    /**
     *    三级菜单
     *
     *    @author    Garbin
     *    @return    void
     */
    function _get_member_submenu()
    {
        $menus = array(
            array(
                'name'  => 'shipping_list',
                'url'   => 'index.php?app=my_shipping',
            ),
/*            array(
                'name'  => 'add_shipping',
                'url'   => 'index.php?app=my_shipping&act=add',
            ),*/
        );
        if (ACT == 'edit')
        {
            $menus[] = array(
                'name'  => 'edit_shipping',
            );
        }
        return $menus;
    }
    function _get_regions()
    {
        $model_region =& m('region');
        $regions = $model_region->get_list(0);
        if ($regions)
        {
            $tmp  = array();
            foreach ($regions as $key => $value)
            {
                $tmp[$key] = $value['region_name'];
            }
            $regions = $tmp;
        }
        $this->assign('regions', $regions);
    }
    function _assign_form()
    {
        /*赋初始值*/
        $shipping = array(
            'enabled'       => 1,
            'sort_order'    => 255,
        );
        $yes_or_no = array(
            1 => Lang::get('yes'),
            0 => Lang::get('no'),
        );
        $this->assign('yes_or_no', $yes_or_no);
        $this->assign('shipping' , $shipping);
    }
}

?>