# Root Perch Shop Omnipay update

This is an update to the gateways for Perch Shop aimed to make use of the latest Stripe Omnipay gateway so that stripe is able to use 3DS2.

### Note
**I have only tested the Stripe gateways, and as all the Omnipay gateways were upgraded to v3 it is likely they won't work. If anyone can confirm the status of the other gateways it would be much appreciated**

## Changes

### Shop Core
Some core shop files needed to be changed for this to work, though only new methods were used, so any existing shop methods should work as before.

### Gateways
The shop gateway files have not been touched, and only the `PerchShopGateway_stripe_intents.class.php` has been added. However all the Omnipay packages were upgraded from v2 to v3 so it is quite likely that they will no longer work.

### Templates
You'll need to update your page templates and gateway form to make use of the newest gateway. The repo contains some examples for what you would need, but I've outlined the most important below.

##### Payment Form
Probably the biggest change that needs to be done is the change to the gateway payment form. The popup checkout v2 form won't work the Stripe Intents, so we need to create a Payment Method our selves.

We create a new template called `stripe_intents_payment_form.html` in the `templates/shop/gateway` folder

```html
<!-- Regular form, don't want to use perch forms here -->
<form id="stripeForm" action="/checkout" method="post">
    <label>
        Card details
    </label>
    <div id="card-element"></div>
    <!-- Hidden fields used to send to stripe checkout -->
    <input type="hidden" value="<perch:member id="email" escape="true" />" name="email"/>
    <input type="hidden" value="<perch:member id="first_name" escape="true" /> <perch:member id="last_name" escape="true" />" name="cardholderName"/>
        <!-- Address Fields if passed through -->
        <input type="hidden" value="<perch:member id="addressLine1" escape="true" />" name="address"/>
        <input type="hidden" value="<perch:member id="city" escape="true" />" name="city"/>
        <input type="hidden" value="<perch:member id="postcode" escape="true" />" name="postcode"/>
        <!-- ./Address Fields if passed through -->
    <input type="hidden" id="paymentMethodID" name="paymentMethodID"/>
    <!-- ./Hidden fields used to send to stripe checkout -->
    <!-- Regular button as we want to submit with JS -->
    <button type="button">Make Payment</button>
</form>

<script src="https://js.stripe.com/v3/"></script>
``` 

We call this template with the usual way but with the updated gateway `perch_shop_payment_form('stripe_intents')`

By itself the form will do nothing so we need to add the JavaScript to get the payment method from stripe. You can use [resources/js/perch_stripe_checkout.js](visit resources/js/perch_stripe_checkout.js) for an example if you use a bundler, or you can use [resources/js/perch_stripe_checkout.js](visit resources/js/perch_stripe_checkout_es5.js) if you are not using a bundler.

You'll most likely need to customise the JS to some degree to match your forms and if you want to add additional styling to the stripe elements.

For more information on stripe elements and how to style them [visit the elements docs](https://stripe.com/docs/stripe-js)

For more information on how to use the payment create the method [visit the js docs here](https://stripe.com/docs/js/payment_methods/create_payment_method)

##### Checkout
Once the form has been submitted we need to take the payment, again this is done in the typical manner and we just need to update the gateway and a few of the arguments

```php
perch_shop_checkout('stripe_intents', [
        'return_url'     => 'https://yourdomain.com/checkout/complete', // Should probably define these in your config file.
        'cancel_url'     => 'https://yourdomain.com/checkout/complete',
        'payment_method' => perch_post('paymentMethodID'), // Pass the payment method to the gateway
        'confirm'        => true, // Tell the gateway to confirm the gateway. Require, if you leave this out the intents gateway won't work!
    ]);
```
##### Complete
Finally on the complete page we need to check if the customer was redirected, and if they were we need to confirm/reject the payment. Here we need to use a newly added method, although it functions very similar to the `perch_shop_checkout` method.

```php
if (PerchUtil::get('payment_intent')) {

    $payment_opts = [
        'paymentIntentReference' => PerchUtil::get('payment_intent'), // set the reference ID
        'returnUrl'              => 'http://ecofriendlyshop.local/checkout/complete',
        'confirm'                => true, // We are confirming the payment so we want this true
    ];

    // New method to confirm the payment. Use the above options and the new intents gateway
    perch_shop_confirm_payment('stripe_intents', $payment_opts);
}
```
### Issues

If you find any issues please send them in [here](https://github.com/RootStudio/Root-Perch-Shop-Omnipay-v3/issues)
