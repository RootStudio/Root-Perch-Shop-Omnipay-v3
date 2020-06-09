<?php

	if (PERCH_RUNWAY) {
        $cart_init = function(){
            $API  = new PerchAPI(1.0, 'perch_shop');
            $API->on('page.loaded', 'perch_shop_initialise_cart');
        };
        $cart_init();
    }else{
        perch_shop_initialise_cart();
    }

	function perch_shop_initialise_cart()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		$ShopRuntime->init_cart(); 

		if (PERCH_RUNWAY) {
			$ShopRuntime->activate_sales();
		}
	}

	function perch_shop_cart_is_preloaded()
	{
		$Cache = PerchShop_Cache::fetch();
		return $Cache->exists('cart.contents');
	}

	function perch_shop_location_selected()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->location_is_set();
	}

	function perch_shop_cart_has_property($prop)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->get_cart_has_property($prop);
	}

	function perch_shop_get_cart_property($prop)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->get_cart_property($prop);
	}

	function perch_shop_set_cart_property($prop, $val)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->set_cart_property($prop, $val);
	}



	function perch_shop_enforce_location_selection()
	{
		// TODO
	}

	function perch_shop_get_shipping_weight()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->get_cart_val('shipping_weight', []);
	}

	function perch_preload_cart_js($return=false)
	{
		if (!perch_shop_cart_is_preloaded()) {
			$s = '<script async src="'.PerchUtil::html(PERCH_LOGINPATH.'/addons/apps/perch_shop/api/preload.php',true).'"></script>';
			if ($return) return $s;
			echo $s;
		}
	}

	function perch_shop_enable_trade_pricing()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		$ShopRuntime->enable_trade_pricing();
	}

	function perch_shop_enable_sale_pricing()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		$ShopRuntime->enable_sale_pricing();
	}

	function perch_shop_cart($opts=array(), $return=false)
	{
		$default_opts = [
				'template'      => 'cart/cart.html',
				'skip-template' => false,
				'cache'         => true,
				'cache-ttl'     => 900,
			];

		$opts = PerchUtil::extend($default_opts, $opts);

		if (isset($opts['template'])) {
			$opts['template'] = 'shop/'.$opts['template'];
		}

		if ($opts['skip-template']==true) $return = true;

		$ShopRuntime = PerchShop_Runtime::fetch();
		$r = $ShopRuntime->get_cart($opts);

		if ($return) return $r;
		echo $r;
		PerchUtil::flush_output();
	}

	function perch_shop_cart_total($opts=array(), $return=false)
	{
		$default_opts = [
				'cache'         => true,
				'cache-ttl'     => 900,
			];

		$opts = PerchUtil::extend($default_opts, $opts);

		$ShopRuntime = PerchShop_Runtime::fetch();
		$r = $ShopRuntime->get_cart_val('grand_total_formatted', $opts, []);

		if ($return) return $r;
		echo $r;
		PerchUtil::flush_output();
	}

	function perch_shop_cart_property($property, $opts=array(), $return=false)
	{
		$default_opts = [
				'cache'         => true,
				'cache-ttl'     => 900,
			];

		$opts = PerchUtil::extend($default_opts, $opts);

		$ShopRuntime = PerchShop_Runtime::fetch();
		$r = $ShopRuntime->get_cart_val($property, $opts, []);

		if ($return) return $r;
		echo $r;
		PerchUtil::flush_output();
	}

	function perch_shop_cart_item_count($opts=array(), $return=false)
	{
		$default_opts = [
				'cache'         => true,
				'cache-ttl'     => 900,
			];

		$opts = PerchUtil::extend($default_opts, $opts);

		$ShopRuntime = PerchShop_Runtime::fetch();
		$r = $ShopRuntime->get_cart_val('item_count', $opts, []);

		if (!$r) $r = 0;

		if ($return) return $r;
		echo $r;
		PerchUtil::flush_output();
	}

	function perch_shop_empty_cart()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		$ShopRuntime->empty_cart();
	}

	function perch_shop_add_to_cart($product, $qty=1, $replace=false)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->add_to_cart($product, $qty, $replace);
	}

	function perch_shop_remove_from_cart($product)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->add_to_cart($product, 0, true);
	}

	function perch_shop_checkout($gateway, $opts=[], $address='default')
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		$ShopRuntime->checkout($gateway, $opts, $address);
	}

	function perch_shop_complete_payment($gateway, $gateway_opts=array())
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->complete_payment($gateway, $_GET, $_POST, $_SERVER, $gateway_opts);
	}

    /**
     * New method to confirm payment through the gateway, then complete how perch would normally
     *
     * @param       $gateway
     * @param array $gateway_opts
     *
     * @return array|bool
     */
    function perch_shop_confirm_payment($gateway, $gateway_opts=array())
    {
        $ShopRuntime = PerchShop_Runtime::fetch();
        return $ShopRuntime->confirm_payment($gateway, $_GET, $_POST, $_SERVER, $gateway_opts);
    }

	function perch_shop_value_of_shipped_goods()
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->get_value_of_shipped_goods();
	}

	function perch_shop_shipping_options($opts=array(), $return=false)
	{
		$default_opts = [
				'template'      => 'shippings/options.html',
				'skip-template' => false,
				'cache'         => true,
				'cache-ttl'     => 900,
			];

		$opts = PerchUtil::extend($default_opts, $opts);

		if (isset($opts['template'])) {
			$opts['template'] = 'shop/'.$opts['template'];
		}

		if ($opts['skip-template']==true) $return = true;

		$ShopRuntime = PerchShop_Runtime::fetch();
		$r = $ShopRuntime->get_shipping_options($opts);

		if ($return) return $r;
		echo $r;
		PerchUtil::flush_output();
	}

	function perch_shop_set_discount_code($code)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		$ShopRuntime->set_discount_code($code);
	}

	function perch_shop_set_currency($code)
	{
		$ShopRuntime = PerchShop_Runtime::fetch();
		return $ShopRuntime->set_currency($code);
	}

