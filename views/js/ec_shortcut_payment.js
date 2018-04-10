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

$(document).ready(function(){
    if ($('section#checkout-payment-step').hasClass('js-current-step')) {
        $('.payment-options div').hide();
        if ($('input[data-module-name=express_checkout_schortcut]').length > 0) {
            $('input[data-module-name=express_checkout_schortcut]').click();
            $('.payment-options').append($('#paypal-es-checked').show());
        } else if($('input[data-module-name=paypal_plus_schortcut]').length > 0) {
            $('input[data-module-name=paypal_plus_schortcut]').click();
            $('.payment-options').append($('#paypal-ppp-checked').show());
        }
    }
});