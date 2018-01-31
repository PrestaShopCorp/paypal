{*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<div class="row">
    <div class="col-xs-12 col-md-10">
        <div class="paypal-braintree-row-payment">
            <div class="payment_module paypal-braintree">
                <form action="{$braintreeSubmitUrl}" id="paypal-braintree-form" method="post">
                {include file="module:paypal/views/templates/front/payment_infos.tpl"}
                <input type="hidden" name="payment_method_nonce" id="paypal_payment_method_nonce"/>
                <input type="hidden" name="payment_method_bt" value="paypal-braintree"/>
                <div id="paypal-button"></div>
                <div id="paypal-vault-info"><p>{l s='You have to finish your payment done with your account PayPal:' mod='paypal'}</p></div>
            </form>
                <div id="bt-paypal-error-msg"></div>
            </div>
        </div>
    </div>
</div>


<script>

    var authorization = '{$braintreeToken}';
    var bt_amount = {$braintreeAmount};
    var pbt_translations = {
        empty_nonce:"{l s='Click paypal button first' mod='paypal'}"
    };
    var mode = '{$mode}';
</script>