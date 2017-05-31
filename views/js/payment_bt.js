
    $(document).ready(function(){
        if ($('section#checkout-payment-step').hasClass('js-current-step')) {
            initBraintreeCard();
        }
    });


    function initBraintreeCard() {
        var bt_button = document.getElementById('payment-confirmation');
        var bt_form = document.querySelector('#braintree-form');

        braintree.client.create({
            authorization: authorization
        }, function (clientErr, clientInstance) {
            if (clientErr) {
                $.fancybox.open([
                    {
                        type: 'inline',
                        autoScale: true,
                        minHeight: 30,
                        content: bt_translations.client
                    }
                ]);
                return;
            }

            braintree.hostedFields.create({
                client: clientInstance,
                styles: {
                    'input': {
                        'color': '#999999',
                        'font-size': '14px',
                        'font-family': 'PayPal Forward, sans-serif'
                    }
                },
                fields: {
                    number: {
                        selector: "#card-number",
                        placeholder: bt_translations.card_nmb
                    },
                    cvv: {
                        selector: "#cvv",
                        placeholder: bt_translations.cvc
                    },
                    expirationDate: {
                        selector: "#expiration-date",
                        placeholder: bt_translations.date
                    }
                }
            },function (hostedFieldsErr, hostedFieldsInstance) {
                if (hostedFieldsErr) {
                    $.fancybox.open([
                        {
                            type: 'inline',
                            autoScale: true,
                            minHeight: 30,
                            content: bt_translations.hosted
                        }
                    ]);
                    return;
                }


                bt_button.addEventListener('click', function (event) {
                    payment_selected = $('input[name=payment-option]:checked').attr('id');
                    if (!$('#pay-with-'+payment_selected+'-form .payment_module').hasClass('braintree-card')) {
                        return true;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    hostedFieldsInstance.tokenize(function (tokenizeErr, payload) {
                        if (tokenizeErr) {
                            var popup_message = '';
                            switch (tokenizeErr.code) {
                                case 'HOSTED_FIELDS_FIELDS_EMPTY':
                                    popup_message = bt_translations.empty;
                                    break;
                                case 'HOSTED_FIELDS_FIELDS_INVALID':
                                    popup_message = bt_translations.invalid+tokenizeErr.details.invalidFieldKeys;
                                    break;
                                case 'HOSTED_FIELDS_FAILED_TOKENIZATION':
                                    popup_message = bt_translations.token;
                                    break;
                                case 'HOSTED_FIELDS_TOKENIZATION_NETWORK_ERROR':
                                    popup_message = bt_translations.network;
                                    break;
                                default:
                                    popup_message = bt_translations.tkn_failed;
                            }
                            $.fancybox.open([
                                {
                                    type: 'inline',
                                    autoScale: true,
                                    minHeight: 30,
                                    content: ''+popup_message+''
                                }
                            ]);
                            return false;
                        }
                        if (check3DS) {
                            braintree.threeDSecure.create({
                                client: clientInstance
                            }, function (ThreeDSecureerror,threeDSecure) {

                                if(ThreeDSecureerror)
                                {
                                    switch (ThreeDSecureerror.code) {
                                        case 'THREEDS_HTTPS_REQUIRED':
                                            popup_message = bt_translations.https;
                                            break;
                                        default:
                                            popup_message = bt_translations.load_3d;
                                    }
                                    $.fancybox.open([
                                        {
                                            type: 'inline',
                                            autoScale: true,
                                            minHeight: 30,
                                            content: ''+popup_message+''
                                        }
                                    ]);
                                    return false;
                                }
                                threeDSecure.verifyCard({
                                    nonce: payload.nonce,
                                    amount: bt_amount,
                                    addFrame: function (err, iframe) {
                                        $.fancybox.open([
                                            {
                                                type: 'inline',
                                                autoScale: true,
                                                minHeight: 30,
                                                content: '<p class="braintree-iframe">'+iframe.outerHTML+''
                                            }
                                        ]);
                                    },
                                    removeFrame: function () {

                                    }
                                }, function (err, three_d_secure_response) {
                                    if (err) {
                                        var popup_message = '';
                                        switch (err.code) {
                                            case 'CLIENT_REQUEST_ERROR':
                                                popup_message = bt_translations.request_problem;
                                                break;
                                            default:
                                                popup_message = bt_translations.failed_3d;
                                        }
                                        $.fancybox.open([
                                            {
                                                type: 'inline',
                                                autoScale: true,
                                                minHeight: 30,
                                                content: ''+popup_message+''
                                            }
                                        ]);
                                        return false;
                                    }

                                    document.querySelector('input[name="payment_method_nonce"]').value = three_d_secure_response.nonce;
                                    document.querySelector('input[name="card_type"]').value = payload.details.cardType;
                                    bt_form.submit()

                                });
                            });
                        } else {
                            document.querySelector('input[name="payment_method_nonce"]').value = payload.nonce;

                            bt_form.submit();
                        }

                    });
                },true);
            });
        });
    }




/*$(document).ready(function() {
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
 });*/
