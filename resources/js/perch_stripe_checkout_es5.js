
function PerchStripeCheckout ( key = '' ) {

    //Check if stripe has been loaded
    if ( typeof Stripe != 'undefined' ) {
        var stripe = Stripe( key );

        var elements = stripe.elements();
    }
    // Get the card element container
    if ( document.querySelector( '#card-element' ) ) {

        // Create stripe card elements
        var cardElement = elements.create( 'card', {
            hidePostalCode: true
        } );

        cardElement.mount( '#card-element' );

        // Get hidden fields for validation
        var cardholderName = document.querySelector( '[name="cardholderName"]' );
        var email = document.querySelector( '[name="email"]' );
        var address = document.querySelector( '[name="address"]' );
        var city = document.querySelector( '[name="city"]' );
        var postcode = document.querySelector( '[name="postcode"]' );
        var cardButton = document.getElementById( 'card-button' );
        var paymentMethodID = document.querySelector( '[name="paymentMethodID"]' );
        var form = document.querySelector( '#stripeForm' );

        // Add listener for the form button
        cardButton.addEventListener( 'click', function( ev ) {
            // Get payment intent from stripe
            stripe.createPaymentMethod( 'card', cardElement, {
                billing_details: {
                    name: cardholderName.value,
                    email: email.value,
                    address: {
                        line1: address.value,
                        postal_code: postcode.value,
                        city: city.value,
                        country: 'GB'
                    }
                }
            } ).then( function( result ) {
                // set the hidden value for payment field
                paymentMethodID.value = result.paymentMethod.id;
                // Submit form for next step
                form.submit();
            } );
        } );
    }

}