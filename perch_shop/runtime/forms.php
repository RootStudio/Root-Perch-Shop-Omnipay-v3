<?php
	function perch_shop_form_handler($SubmittedForm)
    {
    	if ($SubmittedForm->validate()) {

    		$API  = new PerchAPI(1.0, 'perch_shop');
    		$ShopRuntime = PerchShop_Runtime::fetch();

    		switch($SubmittedForm->formID) {
    			case 'cart':
    				$ShopRuntime->update_cart_from_form($SubmittedForm);
    				break;

                case 'discount_code':
                    $ShopRuntime->set_discount_code_from_form($SubmittedForm);
                    break;

                case 'location':
                    $ShopRuntime->set_location_from_form($SubmittedForm);
                    break;

                case 'currency':
                    $ShopRuntime->set_currency_from_form($SubmittedForm);
                    break;

                case 'shipping_method':
                    $ShopRuntime->set_shipping_method_from_form($SubmittedForm);
                    break;

                case 'add_to_cart':
                    $ShopRuntime->add_to_cart_from_form($SubmittedForm);
                    break;

                case 'order_address':
                    $ShopRuntime->set_addresses_from_form($SubmittedForm);
                    break;

                case 'address':
                    $ShopRuntime->edit_address_from_form($SubmittedForm);
                    break;

                case 'register':
                    $ShopRuntime->register_customer_from_form($SubmittedForm);
                    break;

                case 'profile':
                    $ShopRuntime->update_customer_from_form($SubmittedForm);
                    break;
    		}

            // For all forms
            $ShopRuntime->set_cart_properties_from_form($SubmittedForm);  

            $Tag = $SubmittedForm->get_form_attributes();
            if ($Tag && $Tag->next()) {
                $Perch = Perch::fetch();
                if (!$Perch->get_form_errors($SubmittedForm->formID)) {
                    PerchUtil::redirect($Tag->next());    
                }
            }
    	}

        $Perch = Perch::fetch();
        $errors = $Perch->get_form_errors($SubmittedForm->formID);
        if ($errors) PerchUtil::debug($errors);
    }

    function perch_shop_form($template, $return=false)
    {
        $API  = new PerchAPI(1.0, 'perch_shop');
        $Template = $API->get('Template');
        $Template->set('shop'.DIRECTORY_SEPARATOR.$template, 'shop');
        $html = $Template->render(array());
        $html = $Template->apply_runtime_post_processing($html);
        
        if ($return) return $html;
        echo $html;
    }

    function perch_shop_location_form($opts=array(), $return=false)
    {
        $API  = new PerchAPI(1.0, 'perch_shop'); 

        $defaults = [];
        $defaults['template'] = 'tax/location_form.html';

        if (is_array($opts)) {
            $opts = array_merge($defaults, $opts);
        }else{
            $opts = $defaults;
        }

        PerchSystem::set_var('locations_list', PerchShop_TaxLocations::get_list_options());

        $Template = $API->get('Template');
        $Template->set('shop/'.$opts['template'], 'shop');
        $html = $Template->render(array());
        $html = $Template->apply_runtime_post_processing($html);

        if ($return) return $html;
        echo $html;
    }

    function perch_shop_currency_form($opts=array(), $return=false)
    {
        $API  = new PerchAPI(1.0, 'perch_shop'); 

        $defaults = [];
        $defaults['template'] = 'currencies/currency_form.html';

        if (is_array($opts)) {
            $opts = array_merge($defaults, $opts);
        }else{
            $opts = $defaults;
        }

        PerchSystem::set_var('currency_list', PerchShop_Currencies::get_list_options());

        $Template = $API->get('Template');
        $Template->set('shop/'.$opts['template'], 'shop');
        $html = $Template->render(array());
        $html = $Template->apply_runtime_post_processing($html);

        if ($return) return $html;
        echo $html;
    }

    function perch_shop_shipping_method_form($opts=array(), $return=false)
    {
        $API  = new PerchAPI(1.0, 'perch_shop'); 

        $defaults = [];
        $defaults['template'] = 'shippings/method_form.html';

        if (is_array($opts)) {
            $opts = array_merge($defaults, $opts);
        }else{
            $opts = $defaults;
        }

        $ShopRuntime = PerchShop_Runtime::fetch();

        PerchSystem::set_var('shippings_list', $ShopRuntime->get_shipping_list_options());

        $Template = $API->get('Template');
        $Template->set('shop/'.$opts['template'], 'shop');
        $html = $Template->render(array());
        $html = $Template->apply_runtime_post_processing($html);

        if ($return) return $html;
        echo $html;
    }

