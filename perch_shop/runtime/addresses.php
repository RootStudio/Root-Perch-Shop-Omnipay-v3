<?php

	function perch_shop_addresses_set()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->addresses_are_set();
	}

	function perch_shop_order_addresses($opts=array(), $return=false)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		if ($ShopRuntime->addresses_are_set()) {
			list($BillingAddress, $ShippingAddress) = $ShopRuntime->get_addresses();

			$opts = PerchUtil::extend([
				'template'   => 'shop/addresses/confirm.html',
				'cache'      => true,
				'cache-ttl'  => 900,
				'skip-template' => false,
			], $opts);

			if ($opts['skip-template']) $return = true;


			$API  = new PerchAPI(1.0, 'perch_shop'); 
			$Template = $API->get('Template');
			$Template->set($opts['template'], 'shop');

			$data = $BillingAddress->to_array();

			if ($ShippingAddress) {
				$shipping = $ShippingAddress->to_array();
				$tmp = [];
				if (PerchUtil::count($shipping)) {
					foreach($shipping as $key=>$val){
						$tmp['shipping_'.$key] = $val;
					}
				}
				$data = array_merge($data, $tmp);

			}
            // Changed this to respect skip template and return data
			if ($return) return $data;
            $r = $Template->render($data);
			echo $r;
			PerchUtil::flush_output();

		}
	}

	function perch_shop_customer_addresses($opts=array(), $return=false)
	{
		if (!perch_member_logged_in()) return false;

		$ShopRuntime = PerchShop_Runtime::fetch();
		$customerID = $ShopRuntime->get_customer_id();

		$filters = [];
		$filters[] = [
				'filter' => 'customerID',
				'match'  => 'eq',
				'value'  => $customerID,
			];

		$opts = PerchUtil::extend([
			'template'   => 'addresses/list.html',
			'sort'       => 'addressTitle',
			'sort-order' => 'ASC',
			'cache'      => true,
			'cache-ttl'  => 900,
			'skip-template' => false,
			'filter'	=> $filters,
		], $opts);

		if ($opts['skip-template']) $return = true;

		$ShopRuntime = PerchShop_Runtime::fetch();
		$r = $ShopRuntime->get_custom('Addresses', $opts);

		if ($return) return $r;
		echo $r;
		PerchUtil::flush_output();
	}

	function perch_shop_customer_address($id, $opts=array(), $return=false)
	{
		if (!perch_member_logged_in()) return false;

		$ShopRuntime = PerchShop_Runtime::fetch();
		$customerID = $ShopRuntime->get_customer_id();

		$filters = [];
		$filters[] = [
				'filter' => 'customerID',
				'match'  => 'eq',
				'value'  => $customerID,
			];
		$filters[] = [
				'filter' => 'addressID',
				'match'  => 'eq',
				'value'  => $id,
			];

		$opts = PerchUtil::extend([
				'template'   => 'addresses/address.html',
				'cache'      => true,
				'cache-ttl'  => 900,
				'filter'	=> $filters,
				'skip-template' => false,
				'defeat-restrictions' => true,
		], $opts);

		if ($opts['skip-template']) $return = true;

		$ShopRuntime = PerchShop_Runtime::fetch();
		$r = $ShopRuntime->get_custom('Addresses', $opts);

		if ($return) return $r;
		echo $r;
		PerchUtil::flush_output();
	}

	function perch_shop_order_address_form($opts=array(), $return=false)
    {
        $API  = new PerchAPI(1.0, 'perch_shop'); 

        $defaults = [];
        $defaults['template'] = 'checkout/order_address_form.html';

        if (is_array($opts)) {
            $opts = array_merge($defaults, $opts);
        }else{
            $opts = $defaults;
        }

        $ShopRuntime = PerchShop_Runtime::fetch();

        $data = [];
        $data['items'] = $ShopRuntime->get_addresses_for_template();

    
        $Template = $API->get('Template');
        $Template->set('shop/'.$opts['template'], 'shop');
        $html = $Template->render($data);
        $html = $Template->apply_runtime_post_processing($html);

        if ($return) return $html;
        echo $html;
    }

    function perch_shop_edit_address_form($id=false, $opts=array(), $return=false)
    {
        $API  = new PerchAPI(1.0, 'perch_shop'); 

        $defaults = [];
        $defaults['template'] = 'addresses/edit.html';

        if (is_array($opts)) {
            $opts = array_merge($defaults, $opts);
        }else{
            $opts = $defaults;
        }

        $ShopRuntime = PerchShop_Runtime::fetch();
        PerchSystem::set_var('country_list', PerchShop_Countries::get_list_options());

    
        $Template = $API->get('Template');
        $Template->set('shop/'.$opts['template'], 'shop');

        $Address = $ShopRuntime->get_address_by_id($id);

        if ($Address) {
        	$adr = $Address->format_for_template(null);
        	$html = $Template->render($adr);
        	$html = $Template->apply_runtime_post_processing($html, $adr);
        }else{
        	$html = $Template->render([]);
        	$html = $Template->apply_runtime_post_processing($html);
        }
        
       

        if ($return) return $html;
        echo $html;
    }

    function perch_shop_delete_adddress($id)
    {
		$API         = new PerchAPI(1.0, 'perch_shop'); 
		
		$ShopRuntime = PerchShop_Runtime::fetch();
		$Address     = $ShopRuntime->get_address_by_id($id);
		
		if ($Address) {
			$Address->delete();
			return true;
		}
		
		return false;
    }

    function perch_shop_set_tax_location($locationID)
    {
    	$ShopRuntime = PerchShop_Runtime::fetch();
    	$ShopRuntime->set_location($locationID);
    }
