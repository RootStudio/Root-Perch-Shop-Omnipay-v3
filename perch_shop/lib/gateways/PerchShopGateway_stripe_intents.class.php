<?php

class PerchShopGateway_stripe_intents extends PerchShopGateway_stripe
{
    public $omnipay_name = 'Stripe_PaymentIntents';

    public function handle_successful_payment($Order, $response, $gateway_opts)
    {
        $Order->finalize_as_paid();
        if (isset($gateway_opts['return_url'])) {
            PerchUtil::redirect($gateway_opts['return_url']);
        }
    }

    public function store_data_before_redirect($Order, $response, $opts)
    {
        $data = $response->getData();

        $Order->update([
            'orderGatewayRef' => $data['id']
        ]);
    }

    public function callback_looks_valid($get, $post)
    {
        return (isset($get['payment_intent']));
    }

    public function get_order_from_env($Orders, $get, $post)
    {
        return $Orders->find_with_gateway_ref($get['payment_intent']);
    }
}
