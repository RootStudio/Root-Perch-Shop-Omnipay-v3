<?php

// Trigger payment
$guest = (!perch_member_logged_in());
$canPay = ((perch_member_is_passwordless() || (perch_member_logged_in() && perch_shop_addresses_set())) && !perch_get('edit'));
$confirm = (perch_member_is_passwordless() || perch_member_logged_in());

/**
 * Check if the paymentMethodID has been posted from the form
 *
 * Also want ot double check that the customer can complete the order
 */
if ($canPay && perch_post('paymentMethodID')) {

    /**
     * We use the stripe_intents Gateway here, instead of stripe.
     */
    perch_shop_checkout('stripe_intents', [
        'return_url'     => 'https://yourdomain.com/checkout/complete', // Should probably define these in your config file.
        'cancel_url'     => 'https://yourdomain.com/checkout/complete',
        'payment_method' => perch_post('paymentMethodID'), // Pass the payment method to the gateway
        'confirm'        => true, // Tell the gateway to confirm the gateway. Require, if you leave this out the intents gateway won't work!
    ]);
}


// Page templates and other checks that you want

// You might want to add the address to the payment form for better fraud detection.
$address = perch_shop_order_addresses([
    'skip-template' => true,
]);
// Pass the address into the template if you want or set global variables...

// Show the payments form using the intents gateway
perch_shop_payment_form('stripe_intents');


