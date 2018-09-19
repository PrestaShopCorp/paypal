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
    var ec_sc_qty_wanted = $('#quantity_wanted').val();
    var ec_sc_productId = $('#paypal_payment_form_cart input[name="id_product"]').val();
    EcCheckProductAvailability(ec_sc_qty_wanted, ec_sc_productId, $('#es_cs_product_attribute').val());
    prestashop.on('updatedProduct', function(e, xhr, settings) {
        EcCheckProductAvailability(ec_sc_qty_wanted, ec_sc_productId, e.id_product_attribute);
    });
    if (typeof ec_sc_in_context != "undefined" && ec_sc_in_context) {
        window.paypalCheckoutReady = function () {
            paypal.checkout.setup(merchant_id, {
                environment: ec_sc_environment,
            });
        };
    }
});

function EcCheckProductAvailability(qty, productId, id_product_attribute) {
    $.ajax({
        url: sc_init_url,
        type: "POST",
        data: 'checkAvailability=1&source_page=product&id_product='+productId+'&quantity='+qty+'&product_attribute='+id_product_attribute,
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
    if (typeof ec_sc_in_context != "undefined" && ec_sc_in_context) {
        ECSInContext(combination);
    } else {
        $('#paypal_payment_form_cart').submit();
    }

}

function ECSInContext(combination) {
    paypal.checkout.initXO();
    $.support.cors = true;
    $.ajax({
        url: ec_sc_action_url,
        type: "GET",
        data: 'getToken=1&id_product='+$('#paypal_payment_form_cart input[name="id_product"]').val()+'&quantity='+$('[name="qty"]').val()+'&combination='+combination.join('|'),
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