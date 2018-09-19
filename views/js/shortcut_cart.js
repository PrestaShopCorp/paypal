/**
 * 2007-2018 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   http://addons.prestashop.com/en/content/12-terms-and-conditions-of-use
 * International Registered Trademark & Property of PrestaShop SA
 */
// init incontext
document.addEventListener("DOMContentLoaded", function(){
    if (typeof ec_sc_in_context != "undefined" && ec_sc_in_context) {
        window.paypalCheckoutReady = function () {
            paypal.checkout.setup(merchant_id, {
                environment: ec_sc_environment,
            });
        };
    }
    prestashop.on('updateCart', function (event) {
        EcCheckProductAvailability();
    });

});

function EcCheckProductAvailability() {
    $.ajax({
        url: sc_init_url,
        type: "POST",
        data: 'checkAvailability=1&source_page=cart',
        success: function (json) {
            if (json == 1) {
                $('#container_express_checkout').show();
            } else {
                $('#container_express_checkout').hide();
            }
        },
        error: function (responseData, textStatus, errorThrown) {
        }
    });
}

function setInput()
{
    $('#paypal_url_page').val(document.location.href);
    if (typeof ec_sc_in_context != "undefined" && ec_sc_in_context) {
        ECSInContext();
    } else {
        $('#paypal_payment_form_cart').submit();
    }

}

function ECSInContext() {
    paypal.checkout.initXO();
    $.support.cors = true;
    $.ajax({
        url: ec_sc_action_url,
        type: "GET",
        data: 'getToken=1',
        success: function (token) {
            var url = paypal.checkout.urlPrefix +token;
            paypal.checkout.startFlow(url);
        },
        error: function (responseData, textStatus, errorThrown) {
            alert("Error in ajax post"+responseData.statusText);

            paypal.checkout.closeFlow();
        }
    });
}