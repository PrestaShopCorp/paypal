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
document.addEventListener("DOMContentLoaded", function(){
    $(document).on('click', '#desc-order-partial_refund', function(){
        if ($('#doPartialRefundPaypal').length == 0) {
            var newCheckBox = '<p class="checkbox"><label for="doPartialRefundPaypal">\n' +
                '<input type="checkbox" id="doPartialRefundPaypal" name="doPartialRefundPaypal">\n' +
                chb_paypal_refund + '</label></p>';
            $('button[name=partialRefund]').parent('.partial_refund_fields').prepend(newCheckBox);
        }
    });
});


