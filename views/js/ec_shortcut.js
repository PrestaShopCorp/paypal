/**
 * 2007-2017 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   http://addons.prestashop.com/en/content/12-terms-and-conditions-of-use
 * International Registered Trademark & Property of PrestaShop SA
 */

function setInput()
{
    $('#paypal_quantity').val($('[name="qty"]').val());
    var combination = [];
    var re = /group\[([0-9]+)\]/;
    $.each($('#add-to-cart-or-refresh').serializeArray(),function(key, item){
        if(res = item.name.match(re))
        {
            combination.push(res[1]+':'+item.value);
        }
    });
    $('#paypal_url_page').val(document.location.href);
    $('#paypal_combination').val(combination.join('|'));
    $('#paypal_payment_form_cart').submit();
}