<?php

	function perch_shop_payment_form($gateway, $opts=[], $return=false)
	{
		switch ($gateway) {
			case 'stripe':
				return perch_shop_stripe_payment_form($opts, $return);
				break;
            case 'stripe_intents':
                $opts = array_merge([
                    'gateway' => 'stripe_intents',
                    'template' => 'gateways/stripe_intents_payment_form.html'
                ], $opts);
                return perch_shop_stripe_payment_form($opts, $return);
                break;

			case 'braintree':
				return perch_shop_braintree_payment_form($opts, $return);
				break;
		}
	}


	function perch_shop_stripe_payment_form($opts=[], $return=false)
	{
		$default_opts = [
				'template'      => 'gateways/stripe_payment_form.html',
				'skip-template' => false,
				'cache'         => true,
				'cache-ttl'     => 900,
                'gateway'       => 'stripe',
			];

		$opts = PerchUtil::extend($default_opts, $opts);

		if (isset($opts['template'])) {
			$opts['template'] = 'shop/'.$opts['template'];
		}

		if ($opts['skip-template']==true) $return = true;

		$API = new PerchAPI(1.0, 'perch_shop');
		$Template = $API->get('Template', 'shop');
		$Template->set($opts['template'], 'shop');

        $Gateway = PerchShop_Gateways::get($opts['gateway']);
        $config  = PerchShop_Config::get('gateways', $opts['gateway']);
		$key 	 = $Gateway->get_public_api_key($config);

		$ShopRuntime = PerchShop_Runtime::fetch();

		$html = $Template->render([
				'amount' => floatval($ShopRuntime->get_cart_val('grand_total', [], []))*100,
				'amount_formatted' => $ShopRuntime->get_cart_val('grand_total_formatted', [], []),
				'currency' => $ShopRuntime->get_cart_val('currency_code', [], []),
				'publishable_key' => $key,
				'shop_name' => 'Shop',
			]);
		$r = $Template->apply_runtime_post_processing($html);

		if ($return) return $r;
		echo $r;
	}

	function perch_shop_braintree_payment_form($opts=[], $return=false)
	{
		$default_opts = [
				'template'      => 'gateways/braintree_payment_form.html',
				'skip-template' => false,
				'cache'         => true,
				'cache-ttl'     => 900,
			];

		$opts = PerchUtil::extend($default_opts, $opts);

		if (isset($opts['template'])) {
			$opts['template'] = 'shop/'.$opts['template'];
		}

		if ($opts['skip-template']==true) $return = true;

		$API = new PerchAPI(1.0, 'perch_shop');
		$Template = $API->get('Template', 'shop');
		$Template->set($opts['template'], 'shop');

		$Gateway = PerchShop_Gateways::get('braintree');
		$config  = PerchShop_Config::get('gateways', 'braintree');
		$key 	 = $Gateway->get_public_api_key($config);

		$ShopRuntime = PerchShop_Runtime::fetch();

		$html = $Template->render([
				'amount'           => number_format(floatval($ShopRuntime->get_cart_val('grand_total', [], [])),2, '.', ''),
				'amount_formatted' => $ShopRuntime->get_cart_val('grand_total_formatted', [], []),
				'currency'         => $ShopRuntime->get_cart_val('currency_code', [], []),
				'client_token'     => $key,
			]);
		$r = $Template->apply_runtime_post_processing($html);

		if ($return) return $r;
		echo $r;
	}
