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

// init in-context
document.addEventListener("DOMContentLoaded", function(){    window.paypalCheckoutReady = function() {
        paypal.checkout.setup(merchant_id, {
            environment: environment,
        });
    };
});

function ECInContext() {
    paypal.checkout.initXO();
    $.support.cors = true;
    $.ajax({
        url: url_token,
        type: "GET",

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
