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
var ppp;
$(document).ready(function() {
    if ($('section#checkout-payment-step').hasClass('js-current-step')) {
        if (ppp_mode == 'sandbox')
            showPui = true
        else
            showPui = false

        ppp = PAYPAL.apps.PPP({
            "approvalUrl": ppp_approval_url,
            "placeholder": "ppplus",
            "mode": ppp_mode,
            "language": ppp_language_iso_code,
            "country": ppp_country_iso_code,
            "buttonLocation": "outside",
            "useraction": "continue",
            "showPuiOnSandbox": showPui,
        });
    }
});
exec_ppp_payment = true;
function doPatchPPP() {
    if (exec_ppp_payment) {
        exec_ppp_payment = false;
        $.fancybox.open({
            content : '<div id="popup-ppp-waiting"><p>'+waiting_redirection+'</p></div>',
            closeClick : false,
            height : "auto",
            helpers : {
                overlay : {
                    closeClick: false
                }
            },
        });
        $.ajax({
            type    : 'POST',
            url     : ajax_patch_url,
            dataType: 'json',
            success : function (json) {
                if (json && json.success) {
                    ppp.doCheckout();
                }
            },
            error   : function (xhr, ajaxOptions, thrownError) {

            }
        });
    }
}

