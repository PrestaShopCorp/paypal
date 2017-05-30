$(document).ready(function() {
    var button = document.getElementById('payment-confirmation');
    var braintree_form = document.getElementById('braintree-form');
    braintree.dropin.create({
        authorization: authorization,
        selector: '#dropin-container'
    }, function (createErr, instance) {
        if (createErr) {
            // An error in the create call is likely due to
            // incorrect configuration values or network issues.
            // An appropriate error will be shown in the UI.
            console.error(createErr);
            return;
        }
        clientInstance = instance;
        button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            instance.requestPaymentMethod(function (requestPaymentMethodErr, payload) {
                if (requestPaymentMethodErr) {
                    // No payment method is available.
                    // An appropriate error will be shown in the UI.
                    console.error(requestPaymentMethodErr);
                    return;
                }
                console.log(clientInstance);
                braintree.threeDSecure.create({
                    client: clientInstance
                }, function (threeDSecureErr, threeDSecureInstance) {
                    if (threeDSecureErr) {
                        // Handle error in 3D Secure component creation
                        return;
                    }

                    threeDSecure = threeDSecureInstance;
                    threeDSecure.verifyCard({
                        nonce: payload.nonce,
                        amount: 20,
                        addFrame: function (err, iframe) {
                            $.fancybox.open([
                                {
                                    type: 'inline',
                                    autoScale: true,
                                    minHeight: 30,
                                    content: '<p class="braintree-iframe">'+iframe.outerHTML+'</p>'
                                }
                            ]);
                        },
                        removeFrame: function () {

                        }
                    }, function (err, three_d_secure_response) {
                        if (err) {
                            return false;
                        }
                        console.log(three_d_secure_response);return;
                        if(three_d_secure_response.liabilityShifted)
                        {
                            document.querySelector('input[name="liabilityShifted"]').value = three_d_secure_response.liabilityShifted;
                        }
                        else
                        {
                            document.querySelector('input[name="liabilityShifted"]').value = false;
                        }

                        if(three_d_secure_response.liabilityShiftPossible)
                        {
                            document.querySelector('input[name="liabilityShiftPossible"]').value = three_d_secure_response.liabilityShiftPossible;
                        }
                        else
                        {
                            document.querySelector('input[name="liabilityShiftPossible"]').value = false;
                        }
                        document.querySelector('input[name="payment_method_nonce"]').value = three_d_secure_response.nonce;
                        document.querySelector('input[name="card_type"]').value = payload.details.cardType;
                        form.submit()

                    });
                });
                document.querySelector('input[name="payment_method_nonce"]').value = payload.nonce;
                document.querySelector('input[name="card_type"]').value = payload.details.cardType;
                braintree_form.submit();
            });
        });
    });
});