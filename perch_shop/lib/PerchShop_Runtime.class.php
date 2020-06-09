<?php

class PerchShop_Runtime
{
	private static $instance;

	private $api                  = null;
	private $cart_id              = null;
	private $Cart                 = null;
	public $cart_items            = [];
	public $order_items           = [];
	public $Cache                 = null;
	
	public $Order                 = null;
	
	public  $location_set_by_user = false;
	
	private $currencyID           = null;
	private $Currency             = null;
	private $taxLocationID        = null;
	
	private $shippingAddress      = null;
	private $billingAddress       = null;
	private $shippingID			  = null;
	
	
	private $sale_enabled         = false;
	private $trade_enabled        = false;

	public static function fetch()
	{
		if (!isset(self::$instance)) self::$instance = new PerchShop_Runtime;
        return self::$instance;
	}

	public function __construct()
	{
		$this->api = new PerchAPI(1.0, 'perch_shop');
		$this->Cache = PerchShop_Cache::fetch();

		$this->init_currency_id();
	}

	public function reset_after_logout()
	{
		$this->cart_id               = null;
		$this->Cart                  = null;
		$this->cart_items            = [];
		$this->order_items           = [];
		
		$this->Order                 = null;
		
		$this->location_set_by_user  = false;
		
		$this->currencyID            = null;
		$this->Currency              = null;
		$this->taxLocationID         = null;
		
		$this->shippingAddress       = null;
		$this->billingAddress        = null;
		$this->shippingID            = null;
		
		
		$this->sale_enabled          = false;
		$this->trade_enabled         = false;

		$this->init_currency_id();
	}

	private function session_is_active()
	{
		return session_status() === PHP_SESSION_ACTIVE;
	}

	private function init_currency_id()
	{
		if ($this->session_is_active()) {
			$this->init_cart();
			$this->currencyID = (int) $this->Cart->get_cart_field('currencyID');
		}

		if (!$this->currencyID) {
			// currency defaults
			$Settings = $this->api->get('Settings');
			$this->currencyID = (int) $Settings->get('perch_shop_default_currency')->val();
		}
	}

	public function get_currency_id()
	{
		return $this->currencyID;
	}

	public function get_currency()
	{
		if ($this->Currency) {
			return $this->Currency;
		}

		$Currencies = new PerchShop_Currencies($this->api);
		$this->Currency = $Currencies->find($this->currencyID);
		return $this->Currency;
	}

	public function sale_enabled()
	{
		return $this->sale_enabled;
	}

	public function activate_sales()
	{
		$Sales = new PerchShop_Sales($this->api);
		$active_sales = $Sales->get_currently_active();
		if (PerchUtil::count($active_sales)) {
			$this->enable_sale_pricing();
		}
	}

	public function enable_sale_pricing()
	{
		$this->init_cart();
		if ($this->Cart->get_pricing_mode()!='sale') {
			$this->Cache->expire_like('cart.');
			$this->Cart->set_pricing_mode('sale');
		}
	
		$this->sale_enabled = true;
	}

	public function trade_enabled()
	{
		if (!PERCH_RUNWAY) return false;

		return $this->trade_enabled;
	}

	public function enable_trade_pricing()
	{
		if (PERCH_RUNWAY) {
			$this->init_cart();
			if ($this->Cart->get_pricing_mode()!='trade') {
				$this->Cache->expire_like('cart.');
				$this->Cart->set_pricing_mode('trade');
			}
			$this->trade_enabled = true;
		}
	}

	public function get_custom($class, $opts=array())
	{
		$c = 'PerchShop_'.$class;
		$Factory = new $c($this->api);

		if (isset($opts['template'])) {
			$opts['template'] = 'shop/'.$opts['template'];
		}

		$where_callback 	  = (is_callable([$Factory, 'standard_where_callback']) ? [$Factory, 'standard_where_callback'] : null);
		$pretemplate_callback = (is_callable([$Factory, 'runtime_pretemplate_callback']) ? [$Factory, 'runtime_pretemplate_callback'] : null);
		$r = $Factory->get_filtered_listing($opts, $where_callback, $pretemplate_callback);

		return $r;
	}

	public function add_to_cart($product, $qty=1, $replace=false)
	{
		$this->init_cart();
		$this->Cache->expire_like('cart.');
		$this->Cart->add_to_cart($product, $qty, $replace);
	}

	public function add_to_cart_from_form($SubmittedForm)
	{
		$this->init_cart();

		$product   = null;
		$qty       = 1;
		$discount_code  = null;

		if (isset($SubmittedForm->data['product']))   		$product = $SubmittedForm->data['product'];
		if (isset($SubmittedForm->data['qty']))  	  		$qty = $SubmittedForm->data['qty'];
		if (isset($SubmittedForm->data['discount_code']))  	$discount_code = $SubmittedForm->data['discount_code'];

		// find options
		
		if (PerchUtil::count($SubmittedForm->data)) {
			$options = [];
			foreach($SubmittedForm->data as $key=>$value) {
				if (substr($key, 0, 4)=='opt-') {
					$options[substr($key, 4)] = $value;
				}
			}
			if (count($options)) {
				// find the product variant that matches these options
				$Products = new PerchShop_Products($this->api);
				$Product = $Products->find_from_options($product, $options);
				if ($Product) {
					$product = $Product->id();
				}
			}
		}

		$this->Cache->expire_like('cart.');

		$this->Cart->add_to_cart($product, $qty);

		if ($discount_code) {
			$this->Cart->set_discount_code($discount_code);
		}

		$this->Cart->stash_data($SubmittedForm->data);
	}

	public function set_discount_code($code)
	{
		$this->init_cart();
		$this->Cache->expire_like('cart.');
		$this->Cart->set_discount_code($code);
	}

	public function set_discount_code_from_form($SubmittedForm)
	{
		$this->init_cart();

		$discount_code  = null;

		if (isset($SubmittedForm->data['discount_code']))  $discount_code = $SubmittedForm->data['discount_code'];

		$this->Cache->expire_like('cart.');

		if ($discount_code) {
			$this->Cart->set_discount_code($discount_code);
		}
	}

	public function set_addresses_from_form($SubmittedForm)
	{
		$this->Cache->expire_like('cart.');

		$this->init_cart();

		if (isset($SubmittedForm->data['billing'])) {
			$this->billingAddress   = $SubmittedForm->data['billing'];
			// also set this in case no shipping address is needed. Overridden below.
			$this->shippingAddress  = $SubmittedForm->data['billing'];
		}  
		if (isset($SubmittedForm->data['shipping'])) $this->shippingAddress = $SubmittedForm->data['shipping'];

		$this->Cart->set_addresses($this->billingAddress, $this->shippingAddress);

		$this->set_location_from_address($this->billingAddress);
	}

	public function set_addresses($billingAddress, $shippingAddress=null)
	{
		$this->Cache->expire_like('cart.');

		$this->init_cart();

		if ($shippingAddress===null) {
			$shippingAddress = $billingAddress;
		}

		$this->billingAddress   = $billingAddress;
		$this->shippingAddress  = $shippingAddress;

		$this->Cart->set_addresses($this->billingAddress, $this->shippingAddress);

		$this->set_location_from_address($this->billingAddress);
	}

	public function set_location_from_address($addressSlug='default', $skip_if_set=false)
	{
		if (trim($addressSlug) == '') {
			$addressSlug = 'default';
		}

		PerchUtil::mark('setting loc from adr '.$addressSlug);
		PerchUtil::debug($addressSlug);

		if ($skip_if_set && $this->taxLocationID) {
			return;
		}
		

		$memberID = perch_member_get('memberID');
		if ($memberID) {
			$Customer = $this->get_customer($memberID);
			$Addresses = new PerchShop_Addresses($this->api);
			$Address = $Addresses->find_for_customer($Customer->id(), $addressSlug);

			if ($Address) {
				$Locations = new PerchShop_TaxLocations($this->api);
				$Location = $Locations->find_matching($Address->countryID(), $Address->regionID());

				if ($Location) {
					$this->taxLocationID = (int)$Location->id();
					$this->Cart->set_location($Location->id());		
				}	
			}
		}
	}

	public function location_is_set()
	{
		return boolval($this->taxLocationID);
	}

	public function get_addresses_for_template()
	{
		$Addresses = new PerchShop_Addresses($this->api);
		$Customer = $this->get_customer();
		if (!$Customer) return false;

		return $Addresses->get_for_customer($Customer->id());
	}

	public function get_address_by_id($id)
	{
		$Customer = $this->get_customer();
		if (!$Customer) return false;


		$Addresses = new PerchShop_Addresses($this->api);
		return $Addresses->find_for_customer_by_id($Customer->id(), $id);
	}

	public function edit_address_from_form($SubmittedForm)
	{
		$Customer = $this->get_customer();
		if (!$Customer) return false;

		$Addresses = new PerchShop_Addresses($this->api);
		$Address   = false;

		if (isset($SubmittedForm->data['addressID']) && $SubmittedForm->data['addressID'] > 0) {
			$id = (int)$SubmittedForm->data['addressID'];
			$Address = $Addresses->find_for_customer_by_id($Customer->id(), $id);
		}

		if ($Address) {
			PerchUtil::debug($SubmittedForm->data);
			$Address->intelliupdate($SubmittedForm->data);
		}else{
			$data = $SubmittedForm->data;
			if (isset($data['address_1']) && $data['address_1']!='') {
				$data['customer'] = $Customer->id();
				$data['title'] = substr($SubmittedForm->data['address_1'], 0, 24);
				$Addresses->intellicreate($data);
			}

		}

		$this->set_location_from_address('default');
	}

	public function set_cart_properties_from_form($SubmittedForm)
	{
		$attr_map = $SubmittedForm->get_attribute_map('cart-property');
		
		if (PerchUtil::count($attr_map)) {

			$props = [];

			foreach($attr_map as $fieldID=>$property) {
				if (isset($SubmittedForm->data[$fieldID])) {
					if ($SubmittedForm->data[$fieldID]!='') {
						$props[$property] = $SubmittedForm->data[$fieldID];	
					} else {
						$props[$property] = null;
					}
				}
			}

			if (PerchUtil::count($props)) {
				if (!$this->Cart) $this->init_cart();
				$this->Cart->set_properties($props);
			}
		}
	}

	public function apply_discount_to_cart($code)
	{
		$this->init_cart();
		$this->Cart->apply_discount($code);
	}


	public function init_cart()
	{
		if (!$this->cart_id) {
			$this->Cart = new PerchShop_Cart($this->api);
			$this->cart_id = $this->Cart->init();

			if ($this->location_set_by_user) {
				$this->Cart->set_location($this->taxLocationID);
			} else {
				$this->taxLocationID = (int) $this->Cart->get_cart_field('locationID');
				if (!$this->taxLocationID) {
					$this->set_location_from_address('default');	
				}
				
			}

			switch($this->Cart->get_pricing_mode()) {
				case 'sale':
					$this->sale_enabled = true;
					break;

				case 'trade':
					if (PERCH_RUNWAY) $this->trade_enabled = true;
					break;
			}
		}
	}

	public function get_shipping_options($opts=array())
	{
		if (!$this->Cart) $this->init_cart();
		return $this->Cart->get_shipping_options($opts, $this->Cache);
	}

	public function get_shipping_list_options($opts=array())
	{
		if (!$this->Cart) $this->init_cart();
		return $this->Cart->get_shipping_list_options($opts, $this->Cache);
	}


	public function get_cart($opts=array())
	{
		if (!$this->Cart) $this->init_cart();
		return $this->Cart->get_cart($opts, $this->Cache);
	}

	public function get_cart_for_api($opts=array())
	{
		if (!$this->Cart) $this->init_cart();
		return $this->Cart->get_cart_for_api($opts, $this->Cache);
	}

	public function get_cart_val($property='total', $opts=array(), $default_opts=array())
	{
		if ($this->session_is_active()) {
			$opts = PerchUtil::extend($default_opts, $opts);

			if (isset($opts['template'])) {
				$opts['template'] = 'shop/'.$opts['template'];
			}
			if (!$this->Cart) $this->init_cart();
			return $this->Cart->get_cart_val($property, $opts, $this->Cache);
		}
		
		return false;
	}

	public function get_cart_property($prop)
	{
		if (!$this->Cart) $this->init_cart();
		return $this->Cart->get_property($prop);
	}

	public function set_cart_property($prop, $val)
	{
		if (!$this->Cart) $this->init_cart();
		return $this->Cart->set_property($prop, $val);
	}

	public function get_cart_has_property($prop)
	{
		if (!$this->Cart) $this->init_cart();
		if ($this->Cart->get_property($prop)!==null) {
			return true;
		}

		return false;
	}

	public function empty_cart()
	{
		$this->init_cart();
		$this->Cart->destroy($this->cart_id);
		$this->cart_id = false;
		$this->Cart = false;
		$this->Cache->expire_like('cart.');
	}

	public function update_cart_from_form($SubmittedForm)
	{
		$this->Cache->expire_like('cart.');

		$discount_code  = null;
		if (isset($SubmittedForm->data['discount_code']))  $discount_code = $SubmittedForm->data['discount_code'];

		$this->init_cart();

		if ($discount_code) {
			$this->Cart->set_discount_code($discount_code);
		}

		$this->Cart->update_from_form($SubmittedForm);
	}

	public function set_location($locationID)
	{
		$this->Cache->expire_like('cart.');

		$this->init_cart();

		$this->taxLocationID = (int)$locationID;

		$this->Cart->set_location($locationID);
	}

	public function set_location_from_form($SubmittedForm)
	{
		$this->Cache->expire_like('cart.');

		$this->init_cart();

		$this->taxLocationID = (int)$SubmittedForm->data['location'];

		$this->Cart->set_location($SubmittedForm->data['location']);
	}

	public function set_shipping_method_from_form($SubmittedForm)
	{
		$this->Cache->expire_like('cart.');

		$this->init_cart();

		$this->shippingID = (int)$SubmittedForm->data['shipping'];

		$this->Cart->set_shipping($SubmittedForm->data['shipping']);
	}

	public function set_currency_from_form($SubmittedForm)
	{
		$this->Cache->expire_like('cart.');

		$this->init_cart();

		$this->currencyID = (int)$SubmittedForm->data['currency'];
		$this->Currency = false;

		$this->Cart->set_currency($SubmittedForm->data['currency']);
	}

	public function set_currency($currencyCode)
	{
		$Currencies = new PerchShop_Currencies($this->api);
		$Currency = $Currencies->find_by_code($currencyCode);

		if ($Currency) {
			$this->Cache->expire_like('cart.');

			$this->init_cart();

			$this->currencyID = (int)$Currency->id();
			$this->Currency = $Currency;

			$this->Cart->set_currency($Currency->id());

			return true;
		}

		return false;
	}

	public function get_addresses()
	{
		$this->init_cart();
		
		$memberID = perch_member_get('memberID');

		$Customer = $this->get_customer($memberID);

		if ($this->addresses_are_set()) {
			$BillingAddress   = $this->get_address($Customer, $this->billingAddress);
			$ShippingAddress  = $this->get_address($Customer, $this->shippingAddress);	
		}else{
			$BillingAddress   = $this->get_address($Customer, 'default');
			$ShippingAddress  = $this->get_address($Customer, 'shipping');
			if (!$ShippingAddress) $ShippingAddress = $BillingAddress;
		}

		return [$BillingAddress, $ShippingAddress];
	}

	public function checkout($gateway, $payment_opts=[])
	{
		$this->init_cart();
		PerchUtil::debug('Checking out with '.$gateway);

		$memberID = perch_member_get('memberID');
		PerchUtil::debug('Member ID: '.$memberID);

		$Customer = $this->get_customer($memberID);
		if ($this->addresses_are_set()) {
			$BillingAddress   = $this->get_address($Customer, $this->billingAddress);
			$ShippingAddress  = $this->get_address($Customer, $this->shippingAddress);	
		}else{
			$BillingAddress   = $this->get_address($Customer, 'default');
			$ShippingAddress  = $this->get_address($Customer, 'shipping');
			if (!$ShippingAddress) $ShippingAddress = $BillingAddress;
		}
		

		$Orders = new PerchShop_Orders($this->api);

		if ($Customer && $BillingAddress) {
			$Order  = $Orders->create_from_cart($this->Cart, $gateway, $Customer, $BillingAddress, $ShippingAddress);

			if ($Order) {
				$this->Order = $Order;

				PerchShop_Session::set('shop_order_id', $Order->id());

				$Gateway = PerchShop_Gateways::get($gateway);
				$result = $Order->take_payment($Gateway->payment_method, $payment_opts);
				PerchUtil::debug($result);
			}

		}else{
			PerchUtil::debug('Customer or Address or Shipping missing', 'error');
		}
	}

	public function get_customer_id()
	{
		$memberID = perch_member_get('memberID');
		$Customer = $this->get_customer($memberID);
		return $Customer->id();
	}

	public function complete_payment($gateway, $get, $post, $server, $gateway_opts=array())
	{
		$this->init_cart();
		PerchUtil::debug('Runtime complete_payment for '.$gateway);

		$Orders = new PerchShop_Orders($this->api);
		$Order  = false;

		$Gateway = PerchShop_Gateways::get($gateway);

		if ($Gateway->callback_looks_valid($get, $post)) {
			$Order = $Gateway->get_order_from_env($Orders, $get, $post);
			if ($Order) {
				$this->Order = $Order;
				$args   = $Gateway->get_callback_args($get, $post);
				$result = $Gateway->action_payment_callback($Order, $args, $gateway_opts);

				if ($result) {
					PerchUtil::debug('Completing order');
					return $Order->complete_payment($args, $gateway_opts);
				}else{
					return $result;
				}
			}else{
				return [
					'status' => 'error',
					'message' => 'Order not found.',
				];
			}
		}else{
			return [
				'status' => 'error',
				'message' => 'Invalid callback.',
			];
		}
	}

    /**
     * Method to confirm redirect payment for new stripe intents, then completes payment the same as complete_payment would
     *
     * @param       $gateway
     * @param       $get
     * @param       $post
     * @param       $server
     * @param array $gateway_opts
     *
     * @return array|bool
     */
    public function confirm_payment($gateway, $get, $post, $server, $gateway_opts=array())
    {
        $this->init_cart();
        PerchUtil::debug('Runtime complete_payment for '.$gateway);

        $Orders = new PerchShop_Orders($this->api);
        $Order  = false;

        $Gateway = PerchShop_Gateways::get($gateway);

        if ($Gateway->callback_looks_valid($get, $post)) {
            $Order = $Gateway->get_order_from_env($Orders, $get, $post);
            if ($Order) {
                $this->Order = $Order;
                $args   = $Gateway->get_callback_args($get, $post);
                $result = $Gateway->action_payment_callback($Order, $args, $gateway_opts);

                if ($result) {
                    PerchUtil::debug('Completing order');
                    return $Order->confirm_payment($args, $gateway_opts);
                }else{
                    return $result;
                }
            }else{
                return [
                    'status' => 'error',
                    'message' => 'Order not found.',
                ];
            }
        }else{
            return [
                'status' => 'error',
                'message' => 'Invalid callback.',
            ];
        }
    }

	public function get_value_of_shipped_goods()
	{
		$this->init_cart();
		return $this->Cart->get_value_of_shipped_goods();
	}

	public function get_files($opts)
	{
		$memberID = perch_member_get('memberID');
		$Customer = $this->get_customer($memberID);

		if (!$Customer) return '';

		$Files = new PerchShop_ProductFiles($this->api);
		return $Files->get_for_customer($Customer, $opts);
	}

	public function customer_has_purchased_file($fileID)
	{
		$memberID = perch_member_get('memberID');
		$Customer = $this->get_customer($memberID);

		if (!$Customer) return false;

		$Files = new PerchShop_ProductFiles($this->api);
		return $Files->customer_has_purchased_file($Customer, $fileID);
	}

	public function get_file_path_and_bucket($fileID)
	{
		$Files = new PerchShop_ProductFiles($this->api);
		return $Files->get_file_path_and_bucket($fileID);
	}

	public function get_active_order()
	{
		
		if (PerchShop_Session::is_set('shop_order_id')) {
			$orderID = PerchShop_Session::get('shop_order_id');
			$Orders = new PerchShop_Orders($this->api);
			$Order = $Orders->find((int)$orderID);
			if ($Order) {
				return $Order;
			}
		}

		return false;
	}

	public function get_orders($opts)
	{
		$this->init_cart();
		$memberID = perch_member_get('memberID');
		$Customer = $this->get_customer($memberID);
		$db       = PerchDB::fetch();
		$Orders   = new PerchShop_Orders($this->api);


		// Get the listing
		$r = $Orders->get_filtered_listing($opts, function(PerchQuery $Query) use ($opts, $Customer, $db){

			$Statuses = new PerchShop_OrderStatuses($this->api);

			$Query->where[] = ' customerID='.$db->pdb($Customer->id()).' ';
			$Query->where[] = ' orderStatus IN ('.$db->implode_for_sql_in($Statuses->get_status_and_above('paid')).') ';

			// filter for a single
			if (isset($opts['orderID'])) {
				// We do this here because standard filter functions convert numbers to floats, which 
				// fails with overly large values. Sigh.
				$Query->where[] = ' orderID='.$db->pdb($opts['orderID']).' ';
			}

			return $Query;
		});

		return $r;
	}

	public function get_order_items($opts)
	{
		$this->init_cart();
		$memberID   = perch_member_get('memberID');
		$Customer   = $this->get_customer($memberID);
		$db         = PerchDB::fetch();
		$Orders     = new PerchShop_Orders($this->api);

		$Order = $Orders->find_runtime_for_customer($opts['orderID'], $Customer);

		$r = false;

		if ($Order) {
			$r = $Order->template($opts);	
		}
		
		return $r;
	}

	public function get_email_content($id, $secret)
	{
		$Emails = new PerchShop_Emails($this->api);
		$Email  = $Emails->find($id);

		if ($Email && $Email->emailSecret()==$secret) {
			$Template = $this->api->get('Template');
			$Template->set('shop/emails/'.$id.'.html', 'shop');
			return $Template->render($Email);
		}
	}

	public function register_member_login($Event)
	{
		$this->init_cart();
		$memberID   = perch_member_get('memberID');
		$this->Cart->set_member($memberID);

		$Customers = new PerchShop_Customers($this->api);
		$Customer = $Customers->find_by_memberID($memberID);
		if ($Customer) {
			$this->Cart->set_customer($Customer->id());	
			$this->set_location_from_address('default');
		}
	}

	public function addresses_are_set()
	{
		if ($this->billingAddress && $this->shippingAddress) return true;

		$addresses = $this->Cart->get_addresses();
		if ($addresses) {
			$this->billingAddress = $addresses['billingAddress'];
			$this->shippingAddress = $addresses['shippingAddress'];
			return true;
		}

		return false;
	}

	public function register_customer_from_form($SubmittedForm)
	{
		$Session = PerchMembers_Session::fetch();

		$MembersForm = $SubmittedForm->duplicate(['first_name', 'last_name', 'email', 'password'], ['password']);

		$MembersForm->redispatched = true;
		$MembersForm->redispatch('perch_members');

		if ($Session->logged_in) {
			$Customers = new PerchShop_Customers($this->api);
			$Customer = $Customers->create_from_form($SubmittedForm);
		}

	}

	public function update_customer_from_form($SubmittedForm)
	{
		$Session = PerchMembers_Session::fetch();		

		if ($Session->logged_in) {

			$MembersForm = $SubmittedForm->duplicate(['first_name', 'last_name', 'email', 'token'], ['token']);
			$MembersForm->redispatch('perch_members');

			$Customers = new PerchShop_Customers($this->api);
			$Customer = $Customers->find_from_logged_in_member();
			$Customer->update_from_form($SubmittedForm);
			

			$this->set_location_from_address($this->billingAddress);
		}

	}

	public function get_customer_details()
	{
		$Customer = $this->get_customer();
		$out = $Customer->to_array();

		$Billing  = $this->get_address($Customer, 'default');
		if ($Billing) {
			$out = array_merge($out, $Billing->to_array());	
		}

		$Shipping = $this->get_address($Customer, 'shipping');
		if ($Shipping) {
			$ship = $Shipping->to_array();
			if (PerchUtil::count($ship)) {
				foreach($ship as $key=>$val) {
					$out['shipping_'.$key] = $val;
				}
			}
		}

		return $out;
	}

	public function get_product_id($slug)
	{
		$Products = new PerchShop_Products($this->api);
		$Product = $Products->get_one_by('productSlug', $slug);

		if ($Product) return $Product->id();

		return false;
	}

	private function get_customer($memberID=false)
	{
		if (!$memberID) $memberID = perch_member_get('id');

		$Customers = new PerchShop_Customers($this->api);
		$Customer = $Customers->find_by_memberID($memberID);

		if (!$Customer) {

			// does customer exist against another Member? (e.g. for anon login)
			$Customer = $Customers->find_from_logged_in_member();

			if ($Customer) {
				$Customer->update_locally(['memberID'=>$memberID]);

				$Addresses = new PerchShop_Addresses($this->api);
				$Addresses->deprecate_default_address($Customer->id());
				$Addresses->create_from_logged_in_member($Customer->id());

				return $Customer;
			}

			$Customer = $Customers->create_from_logged_in_member();
		}

		return $Customer;
	}

	private function get_address($Customer, $address_type='default')
	{
		
		$Address   = null;
		$Addresses = new PerchShop_Addresses($this->api);

		if ($Customer) {
			$Address = $Addresses->find_for_customer($Customer->id(), $address_type);
		}

		if (!$Address) {
			PerchUtil::debug("no address");

			if (!$address_type != 'default') {
				$Address = $Addresses->create_from_default($Customer->id(), $address_type);				
			}

			if (!$Address) {
				$Address = $Addresses->create_from_logged_in_member($Customer->id(), $address_type);
			}
		}

		return $Address;
	}

	private function get_shipping($shipping_method)
	{
		$Shippings = new PerchShop_Shippings($this->api);
		return $Shippings->get_one_by('shippingSlug', $shipping_method);
	}


}
