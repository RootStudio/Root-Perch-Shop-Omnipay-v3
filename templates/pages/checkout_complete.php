<?php

/**
 * Here we check if the payment was redirected or not. If the payment_intent field exists we need to finish the payment
 */
if (PerchUtil::get('payment_intent')) {

    $payment_opts = [
        'paymentIntentReference' => PerchUtil::get('payment_intent'), // set the reference ID
        'returnUrl'              => 'http://ecofriendlyshop.local/checkout/complete',
        'confirm'                => true, // We are confirming the payment so we want this true
    ];

    // New method to confirm the payment. Use the above options and the new intents gateway
    perch_shop_confirm_payment('stripe_intents', $payment_opts);
}

// Rest of the page should be you success/unsuccessful order page
