
// init in-context
document.addEventListener("DOMContentLoaded", function(){
    window.paypalCheckoutReady = function () {
        paypal.checkout.setup($('#paypal_merchant_id').val(), {
            environment: $('#paypal_mode').val(),
    });
    };

    $(document).on('click', '#paypal_process_payment, #payment_paypal_express_checkout', function(event) {
        event.preventDefault();
        if ($('#paypal_ssl_enabled').val()) {
            var baseDirPP = baseDir.replace('http:', 'https:');
        } else {
            var baseDirPP = baseDir;
        }
        paypal.checkout.initXO();
        updateFormDatas();
        var str = '';
        if($('.paypal_payment_form input[name="id_product"]').length > 0)
            str += '&id_product='+$('.paypal_payment_form input[name="id_product"]').val();
        if($('.paypal_payment_form input[name="quantity"]').length > 0)
            str += '&quantity='+$('.paypal_payment_form input[name="quantity"]').val();
        if($('.paypal_payment_form input[name="id_p_attr"]').length > 0)
            str += '&id_p_attr='+$('.paypal_payment_form input[name="id_p_attr"]').val();

        $.support.cors = true;
        $.ajax({
            url: baseDirPP+"modules/paypal/express_checkout/payment.php",
            type: "GET",
            data: '&ajax=1&onlytoken=1&express_checkout='+$('input[name="express_checkout"]').val()+'&current_shop_url='+$('input[name="current_shop_url"]').val()+'&bn='+$('input[name="bn"]').val()+str,
            async: true,
            crossDomain: true,


            success: function (token) {
                var url = paypal.checkout.urlPrefix +token;

                paypal.checkout.startFlow(url);
            },
            error: function (responseData, textStatus, errorThrown) {
                alert("Error in ajax post"+responseData.statusText);

                paypal.checkout.closeFlow();
            }
        });
    });
});

