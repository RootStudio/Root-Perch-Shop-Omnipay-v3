<?php

class PerchShop_Order extends PerchShop_Base
{
	protected $factory_classname = 'PerchShop_Orders';
	protected $table             = 'shop_orders';
	protected $pk                = 'orderID';
	protected $index_table       = 'shop_admin_index';

	protected $modified_date_column = 'orderUpdated';
    public $deleted_date_column  = 'orderDeleted';

    protected $event_prefix = 'shop.order';

    protected $date_fields = ['orderUpdated', 'orderCreated'];

	protected $duplicate_fields  = [
										'orderStatus'       => 'status',
										'customerID'        => 'customer',
										'orderTotal'        => 'total',
										'orderCurrency'     => 'currency',
										'orderGateway'      => 'gateway'
									];

	public function get_currency_code()
	{
		$Currencies = new PerchShop_Currencies($this->api);
		$Currency = $Currencies->find($this->currencyID());
		return $Currency->currencyCode();
	}

    public function get_reporting_currency()
    {
        $Currencies = new PerchShop_Currencies($this->api);
        return $Currencies->get_reporting_currency();
    }

    public function get_shipping()
    {
        if ($this->shippingID()) {
            $Shippings = new PerchShop_Shippings($this->api);
            return $Shippings->find((int)$this->shippingID());
        }
            

        return null;
    }

    public function get_promotions()
    {
        $sql = 'SELECT promoID FROM '.PERCH_DB_PREFIX.'shop_order_promotions
                WHERE orderID='.$this->db->pdb((int)$this->id());
        $ids = $this->db->get_rows_flat($sql);

        if (PerchUtil::count($ids)) {

            $out = [];
            $Promotions = new PerchShop_Promotions($this->api);

            foreach($ids as $id) {
                $Promotion = $Promotions->find((int)$id);
                if ($Promotion) {
                    $out[]  = $Promotion;
                }
            }

            return $out;
        }
        return null;
    }

    public function get_discount_code()
    {
        $promos = $this->get_promotions();

        if (PerchUtil::count($promos)) {
            foreach($promos as $Promo) {
                $code = $Promo->get('discount_code');
                if ($code) return $code;
            }
        }

        return false;
    }


	public function take_payment($method='purchase', $opts=array())
	{
		$Gateway = PerchShop_Gateways::get($this->orderGateway());
		$result  = $Gateway->take_payment($this, $opts);

		return false;
	}

    /**
     * Complete payment after 3DS(2) redirect then finalise the order
     *
     * @param       $args
     * @param array $gateway_opts
     */
	public function confirm_payment($args, $gateway_opts=array()) {
        PerchUtil::debug('completing payment');
        $Gateway = PerchShop_Gateways::get($this->orderGateway());
        $Gateway->complete_payment($this, $gateway_opts);
        $this->finalize_as_paid();
    }

	public function complete_payment($args, $gateway_opts=array())
	{
		PerchUtil::debug('completing payment');
		$this->finalize_as_paid();
	}

	public function set_status($status)
	{
        PerchUtil::debug('Setting order status to '.$status);
        if ($this->orderStatus() != $status) {
            

            $result = $this->intelliupdate([ 'status' => $status ]);

            $Perch = Perch::fetch();
            $Perch->event('shop.order_status_update', $this, $status);

            return $result;    
        }else{
            PerchUtil::debug('Status already set.', 'error');
        }

		return true;
	}

	public function finalize_as_paid($status='paid')
	{
		$Gateway = PerchShop_Gateways::get($this->orderGateway());
		$Gateway->finalize_as_paid($this);

        $this->assign_invoice_number();

        $this->set_status($status);

		// Get products
		$Products = new PerchShop_Products($this->api);
		$products = $Products->get_for_order($this->id());

        // Get customer
        $Customers = new PerchShop_Customers($this->api);
        $Customer = $Customers->find($this->customerID());

		// Update stock levels
		if (PerchUtil::count($products)) {
			foreach($products as $Product) {
				if ($Product->itemQty()) {
					$adjustment = 0 - ((int)$Product->itemQty());
					$Product->update_stock_level($adjustment);	
				}
				
                // Apply tags
                $Product->apply_tags_to_customer($Customer);
			}
		}

        // Get exchange rate, if we can.
        $exchange_rate = $Gateway->get_exchange_rate($this);
        if ($exchange_rate!==null) {
            $this->update([
                'orderExchangeRate' => $exchange_rate,
                ]);
        }else{
            $Currencies = new PerchShop_Currencies($this->api);
            $ReportingCurrency = $Currencies->get_reporting_currency();

            if ($ReportingCurrency) {
                if ($this->currencyID() == $ReportingCurrency->id()) {
                    $exchange_rate = 1;
                }else{
                    $Currency = $Currencies->find((int)$this->currencyID());
                    $exchange_rate = $Currency->currencyRate();
                }

                $this->update([
                    'orderExchangeRate' => $exchange_rate,
                ]);
            }

        }


	}

	public function is_paid()
	{
		PerchUtil::debug('Is paid?');
		if ($this->details['orderStatus']=='paid') {
			return true;
		}

		return false;
	}

	public function sync_order_items()
	{
		$OrderItems = new PerchShop_OrderItems($this->api);
		$OrderItems->sync_for_order($this->orderID());
	}

	public function copy_order_items_from_cart($Cart, $cart_data)
	{
		$OrderItems = new PerchShop_OrderItems($this->api);
		$OrderItems->copy_from_cart($this->orderID(), $Cart, $cart_data);

        if (PerchUtil::count($cart_data['promotions'])) {
            foreach($cart_data['promotions'] as $Promotion) {
                $this->log_promotion($Promotion);
            }
        }
	}

    public function freeze_addresses()
    {
        $Addresses = new PerchShop_Addresses($this->api);

        $data = [];

        if ($this->orderBillingAddress()) {
            $data['orderBillingAddress'] = $Addresses->freeze_for_order($this->orderBillingAddress(), $this->id());
        }

        if ($this->orderShippingAddress()) {

            if ($this->orderShippingAddress() == $this->orderBillingAddress()) {
                // same address for both
                $data['orderShippingAddress'] = $data['orderBillingAddress'];
            }else{
                $data['orderShippingAddress'] = $Addresses->freeze_for_order($this->orderShippingAddress(), $this->id());    
            }
            
        }

        $this->update($data);
    }

    private function log_promotion($Promotion)
    {
        $data = [
            'orderID'    => $this->id(),
            'promoID'    => $Promotion->id(),
            'customerID' => $this->customerID(),
        ];

        $this->db->insert(PERCH_DB_PREFIX.'shop_order_promotions', $data);
    }

	private function _get_gateway_payment_options($opts)
	{
		$Gateway = PerchShop_Gateways::get($this->orderGateway());
		return $Gateway->format_payment_options($opts);
	}

	private function _process_gateway_specific_response($args, $gateway_opts=array())
	{
		$Gateway = PerchShop_Gateways::get($this->orderGateway());
		return $Gateway->produce_payment_response($args, $gateway_opts);
	}

    private function assign_invoice_number()
    {
        $number = $this->get_next_invoice_number();
        $Settings = $this->api->get('Settings');
        $format = $Settings->get('perch_shop_invoice_number_format')->val();

        $invoice_number = sprintf($format, $number);

        $this->update([
            'orderInvoiceNumber' => $invoice_number,
            ]);
    }

    private function get_next_invoice_number()
    {
        $sql = "UPDATE ".PERCH_DB_PREFIX."shop_orders_meta SET metaValue=last_insert_id(metaValue+1) WHERE id='last_invoice_number'";
        $this->db->execute($sql);
        $val = $this->db->get_value('SELECT last_insert_id()');
        return (int)$val;
    }

	public function to_array()
    {
        $out = $this->details;

        $dynamic_field_col = str_replace('ID', 'DynamicFields', $this->pk);
        if (isset($out[$dynamic_field_col]) && $out[$dynamic_field_col] != '') {
            $dynamic_fields = PerchUtil::json_safe_decode($out[$dynamic_field_col], true);
            if (PerchUtil::count($dynamic_fields)) {
            	$dynamic_fields = $this->flatten_array('', $dynamic_fields);
                //$out = array_merge($dynamic_fields, $out);

                foreach($dynamic_fields as $key=>$value) {
                    $out['perch_'.$key] = $value;
                }
                $out = array_merge($dynamic_fields, $out);
            }
        }

        $Statuses = new PerchShop_OrderStatuses($this->api);
        $Status = $Statuses->get_one_by('statusKey', $out['orderStatus']);
        if ($Status) {
            $out = array_merge($out, $Status->to_array());
        }

        return $out;
    }

    public function set_transaction_reference($ref)
    {
    	$this->update(['orderGatewayRef'=>$ref]);
    }

    public function send_order_email(PerchShop_Email $ShopEmail)
    {
        PerchUtil::debug('Sending customer email');

    	$Customers = new PerchShop_Customers($this->api);
    	$Customer = $Customers->find($this->customerID());

    	$Members = new PerchMembers_Members($this->api);
    	$Member = $Members->find($Customer->memberID());


    	$Email = $this->api->get('Email');
        $Email->set_template('shop/emails/'.$ShopEmail->emailTemplate(), 'shop');
        $Email->set_bulk($this->to_array());
        $Email->set_bulk($ShopEmail->to_array());
        $Email->set_bulk($Customer->to_array());
        $Email->set_bulk($Member->to_array());

        $Addresses = new PerchShop_Addresses($this->api);

        $ShippingAddr = $Addresses->find((int)$this->orderShippingAddress());
        $Email->set_bulk($ShippingAddr->format_for_template('shipping'));

		$BillingAddr = $Addresses->find((int)$this->orderBillingAddress());
        $Email->set_bulk($BillingAddr->format_for_template('billing'));

        $OrderItems = new PerchShop_OrderItems($this->api);
        $items = $OrderItems->get_by('orderID', $this->id());

        $order_items = [];

        if (PerchUtil::count($items)) {
        	foreach($items as $Item) {
        		$order_items[]  = $Item->to_array();	
        	}
        }
        $result = $this->to_array();
        $result['items'] = $order_items;

        $data = $this->format_invoice_for_template($result);

		$Email->set_bulk($data);
        
        $Email->senderName($ShopEmail->sender_name());
        $Email->senderEmail($ShopEmail->sender_email());

        switch ($ShopEmail->emailFor()) {

            // Send to the customer
            case 'customer':
                $Email->recipientEmail($Member->memberEmail());
                break;

            // Send to the customer, BCC the admin
            case 'customer_bcc':
                $Email->recipientEmail($Member->memberEmail());
                $Email->bccToEmail($ShopEmail->emailRecipient());
                break;

            // Send to the admin
            case 'admin':
                $Email->recipientEmail($ShopEmail->emailRecipient());
                break;

        }


        
        $Email->send();
    }

    public function get_for_template()
    {
    	$OrderItems = new PerchShop_OrderItems($this->api);
        $items = $OrderItems->get_by('orderID', $this->id());

        $order_items = [];

        if (PerchUtil::count($items)) {
        	foreach($items as $Item) {
        		$order_items[]  = $Item->to_array();	
        	}
        }
        $result = $this->to_array();
        $result['items'] = $order_items;

        $result = $this->format_invoice_for_template($result);

        return $result;
    }

    public function template($opts)
    {
    	$html  = '';
        $single_mode = false;
    	$items = [$this->get_for_template()];

    	if (isset($opts['return-objects']) && $opts['return-objects']) {
            return $items;
        }
        $render_html = true;

        if (isset($opts['skip-template']) && $opts['skip-template']==true) {
            $render_html = false;
            if (isset($opts['return-html'])&& $opts['return-html']==true) {
                $render_html = true;
            }
        }

    	// template
        if (is_callable($opts['template'])) {
            $callback = $opts['template'];
            $template = $callback($items);
        }else{
            $template = $opts['template'];
        }

        if (is_object($this->api)) {
            $Template = $this->api->get('Template');
            $Template->set($template,'shop');
        }else{
            $Template = new PerchTemplate($template, 'shop');
        }


        if ($render_html) {

            if (PerchUtil::count($items)) {

                if (isset($opts['split-items']) && $opts['split-items']) {
                    $html = $Template->render_group($items, false);
                }else{
                    $html = $Template->render_group($items, true);
                }

            }else{

                $Template->use_noresults();
                $html = $Template->render(array());
            }

        }


        if (isset($opts['skip-template']) && $opts['skip-template']==true) {

            if ($single_mode) return $Item->to_array();

            $processed_vars = $items;
            #if (PerchUtil::count($items)) {
            #    foreach($items as $Item) {
            #        $processed_vars[] = $Item->to_array();
            #    }
            #}

            if (PerchUtil::count($processed_vars)) {

                $category_field_ids    = $Template->find_all_tag_ids('categories');
                //PerchUtil::debug($category_field_ids, 'notice');

                foreach($processed_vars as &$item) {
                    if (PerchUtil::count($item)) {
                        foreach($item as $key => &$field) {
                            if (in_array($key, $category_field_ids)) {
                                $field = $this->_process_category_field($field);
                            }
                            if (is_array($field) && isset($field['processed'])) {
                                $field = $field['processed'];
                            }
                            if (is_array($field) && isset($field['_default'])) {
                                $field = $field['_default'];
                            }
                        }
                    }
                }
            }

            if (isset($opts['return-html'])&& $opts['return-html']==true) {
                $processed_vars['html'] = $html;
            }

            return $processed_vars;
        }
 
        if (is_array($html)) {
            // split-items
            if (PerchUtil::count($html)) {
                $Template = new PerchTemplate();
                foreach($html as &$html_item) {
                    if (strpos($html_item, '<perch:')!==false) {
                        $html_item = $Template->apply_runtime_post_processing($html_item);
                    }
                }
            }
        }else{
            if (strpos($html, '<perch:')!==false) {
                $Template = new PerchTemplate();
                $html     = $Template->apply_runtime_post_processing($html);
            }
        }


        return $html;
    }

	private function format_invoice_for_template($result)
	{
		if (PerchUtil::count($result)) {

			$Products 	= new PerchShop_Products($this->api);
			$Currencies = new PerchShop_Currencies($this->api);
			$Shippings  = new PerchShop_Shippings($this->api);

			$Currency = $Currencies->find((int)$this->currencyID());
			$Shipping = $Shippings->find((int)$this->shippingID());

			if (isset($result['items'])) {

				$Totaliser = new PerchShop_CartTotaliser;

                $items = [];

				foreach($result['items'] as $item) {

					if ($item['itemType'] == 'product') {
						$item['identifier'] = $item['itemID'];
						$item['quantity'] = $item['itemQty'];

						$Product = $Products->find((int)$item['productID']);


						$item = array_merge($item, $Product->to_array());

						$exclusive_price = $item['itemPrice'];
						$qty             = $item['itemQty'];
						$inclusive_price = $item['itemTotal'];
						$tax_rate        = $item['itemTaxRate'];
                        $discount        = $item['itemDiscount'];
                        $tax_discount    = $item['itemTaxDiscount'];

						$Totaliser->add_to_items($exclusive_price*$qty, $tax_rate);
	                	$Totaliser->add_to_tax(($inclusive_price - $exclusive_price)*$qty, $tax_rate);

                        $Totaliser->add_to_item_discounts($discount, $tax_rate);
                        $Totaliser->add_to_tax_discounts($tax_discount, $tax_rate);

						$item['price_without_tax']           = $Currency->format_numeric($exclusive_price);
		                $item['price_without_tax_formatted'] = $Currency->format_display($exclusive_price);

		                $item['total_without_tax']           = $Currency->format_numeric($exclusive_price*$qty);
		                $item['total_without_tax_formatted'] = $Currency->format_display($exclusive_price*$qty);

		                $item['tax']                         = $Currency->format_numeric($inclusive_price - $exclusive_price);
		                $item['tax_formatted']               = $Currency->format_display($inclusive_price - $exclusive_price);

		                $item['total_tax']                   = $Currency->format_numeric(($inclusive_price - $exclusive_price)*$qty);
		                $item['total_tax_formatted']         = $Currency->format_display(($inclusive_price - $exclusive_price)*$qty);

		                $item['tax_rate']                    = $tax_rate;

		                $item['price_with_tax']              = $Currency->format_numeric($inclusive_price);
		                $item['price_with_tax_formatted']    = $Currency->format_display($inclusive_price);

		                $item['total_with_tax']              = $Currency->format_numeric($inclusive_price*$qty);
		                $item['total_with_tax_formatted']    = $Currency->format_display($inclusive_price*$qty);

                        $item['discount']                    = $discount;
                        $item['tax_discount']                = $tax_discount;


						if (isset($item['productVariantDesc'])) {
							$item['variant_desc'] = $item['productVariantDesc'];
						}

						unset($item['Product']);

						ksort($item);
                        $items[] = $item;
					}

					if ($item['itemType'] == 'shipping') {

						$exclusive_price = $item['itemPrice'];
                    	$inclusive_price = $item['itemTotal'];
                    	$tax_rate        = $item['itemTaxRate'];

                    	$Totaliser->add_to_shipping($exclusive_price, $tax_rate);
                		$Totaliser->add_to_shipping_tax(($inclusive_price - $exclusive_price), $tax_rate);

                        $result['shipping_method']                = $Shipping->title();
                        $result['shipping']                       = true;

						$result['shipping_without_tax']           = $Currency->format_numeric($exclusive_price);
						$result['shipping_without_tax_formatted'] = $Currency->format_display($exclusive_price);
						
						$result['shipping_tax']                   = $Currency->format_numeric($inclusive_price - $exclusive_price);
						$result['shipping_tax_formatted']         = $Currency->format_display($inclusive_price - $exclusive_price);
						
						$result['shipping_tax_rate']              = $tax_rate;
						
						$result['shipping_with_tax']              = $Currency->format_numeric($inclusive_price);
						$result['shipping_with_tax_formatted']    = $Currency->format_display($inclusive_price);

						$result['shipping_id'] 					  = $Shipping->id();

						#unset($item);
					}

					
				}

                $result['items'] = $items;

                $result = array_merge($result, $Totaliser->to_array($Currency, $this));
				$result = array_merge($result, $Currency->to_array());

                $result['invoice_number'] = $result['orderInvoiceNumber'];
                $result['exchange_rate'] = $result['orderExchangeRate'];

                $discount_code = $this->get_discount_code();
                if ($discount_code) {
                    $result['discount_code'] = $discount_code;
                };
				
			}

		}

		return $result;
	}

}
