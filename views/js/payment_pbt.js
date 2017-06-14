
$(document).ready(function(){
    if ($('section#checkout-payment-step').hasClass('js-current-step')) {
        initPaypalBraintree();
    }
});

function initPaypalBraintree() {
    var paypal_bt_form = document.querySelector('#paypal-braintree-form');

    braintree.client.create({
        authorization: authorization
    }, function (clientErr, clientInstance) {

        // Stop if there was a problem creating the client.
        // This could happen if there is a network error or if the authorization
        // is invalid.
        if (clientErr) {
            console.error('Error creating client:', clientErr);
            return;
        }

        // Create a PayPal Checkout component.
        braintree.paypalCheckout.create({
            client: clientInstance
        }, function (paypalCheckoutErr, paypalCheckoutInstance) {

            // Stop if there was a problem creating PayPal Checkout.
            // This could happen if there was a network error or if it's incorrectly
            // configured.
            if (paypalCheckoutErr) {
                console.error('Error creating PayPal Checkout:', paypalCheckoutErr);
                return;
            }

            paypal.Button.render({
                env: 'sandbox', // or 'sandbox'

                payment: function () {
                    return paypalCheckoutInstance.createPayment({
                        flow: 'vault',
                        billingAgreementDescription: 'Your agreement description',
                        enableShippingAddress: true,
                        shippingAddressEditable: false,
                    });
                },

                onAuthorize: function (data, actions) {
                    return paypalCheckoutInstance.tokenizePayment(data)
                        .then(function (payload) {
                            // Submit `payload.nonce` to your server.
                            document.querySelector('input#paypal_payment_method_nonce').value = payload.nonce;

                            paypal_bt_form.submit();
                        });
                },

                onCancel: function (data) {
                    console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2));
                },

                onError: function (err) {
                    console.error('checkout.js error', err);
                }
            }, '#aaaaaa-button').then(function (e) {
              /*  console.log('this', this);
                console.log('event', e);
                var test = $(document).find('#paypal-button');
                console.log('test', test);
                var paypal_bt_form2 = document.querySelector('#paypal-button');
                paypal_bt_form2.click();
                //   $('.paypal-button-container').click();
                // The PayPal button will be rendered in an html element with the id
                // `paypal-button`. This function will be called when the PayPal button
                // is set up and ready to be used.
                // paypal_bt_form.submit();*/
            });

            // Set up PayPal with the checkout.js library
            $('#payment-confirmation button').click(function(){

                event.preventDefault();
                event.stopPropagation();
                $('#paypal-button').click();
            });


        });

    });
}
