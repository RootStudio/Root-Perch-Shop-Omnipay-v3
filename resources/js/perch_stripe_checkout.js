export class PerchStripeCheckout {

    constructor ( key = '' ) {
        // Check stripe has been loaded
        if ( typeof Stripe != 'undefined' ) {
            this.stripe = Stripe( key );

            this.elements = this.stripe.elements();
        }
    }

    init () {
        // Get the card element container
        if ( document.querySelector( '#card-element' ) ) {

            // Create stripe card elements
            this.cardElement = this.elements.create( 'card', {
                hidePostalCode: true
            } );
            this.cardElement.mount( '#card-element' );

            // Get hidden fields for validation
            const cardholderName = document.querySelector( '[name="cardholderName"]' );
            const email = document.querySelector( '[name="email"]' );
            const address = document.querySelector( '[name="address"]' );
            const city = document.querySelector( '[name="city"]' );
            const postcode = document.querySelector( '[name="postcode"]' );
            const cardButton = document.getElementById( 'card-button' );
            const paymentMethodID = document.querySelector( '[name="paymentMethodID"]' );
            const form = document.querySelector( '#stripeForm' );

            // Add listener for the form button
            cardButton.addEventListener( 'click', async ( ev ) => {
                // Get payment intent from stripe
                const { paymentMethod, error } =
                    await this.stripe.createPaymentMethod( 'card', this.cardElement, {
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
                    } );
                if ( error ) {
                    console.log( error );
                } else {
                    // Assign payment method to our hidden field
                    paymentMethodID.value = paymentMethod.id;

                    // Submit form for next step
                    form.submit();


                }
            } );
        }
    }

    destroy () {
        if ( this.cardElement ) {
            this.cardElement.destroy();
        }
    }
}
