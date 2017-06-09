{*
* 2007-2017 PrestaShop
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
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<div class="row">
    <div class="col-xs-12 col-md-10">
        <div class="paypal-braintree-row-payment">
            <div class="payment_module paypal-braintree">
            <form action="{$braintreeSubmitUrl}" id="paypal-braintree-form" method="post">
                <input type="hidden" name="payment_method_nonce" id="paypal_payment_method_nonce"/>
                <input type="hidden" name="payment_method_bt" value="paypal-braintree"/>
                <div id="aaaaaa-button"></div>
            </form>
            </div>
        </div>
    </div>
</div>

<!--<script src="https://www.paypalobjects.com/api/checkout.js" data-version-4 log-level="warn"></script>
<script src="https://js.braintreegateway.com/web/3.16.0/js/client.min.js"></script>
<script src="https://js.braintreegateway.com/web/3.16.0/js/paypal-checkout.min.js"></script>-->
<script>

    var authorization = '{$braintreeToken}';
    var bt_amount = {$braintreeAmount};
    var bt_translations = {
        client:"{l s='Error create Client' mod='paypal'}",
        card_nmb:"{l s='Card number' mod='paypal'}",
        cvc:"{l s='CVC' mod='paypal'}",
        date:"{l s='MM/YY' mod='paypal'}",
        hosted:"{l s='Error create Hosted fields' mod='paypal'}",
        empty:"{l s='All fields are empty! Please fill out the form.' mod='paypal'}",
        invalid:"{l s='Some fields are invalid :' mod='paypal'}",
        token:"{l s='Tokenization failed server side. Is the card valid?' mod='paypal'}",
        network:"{l s='Network error occurred when tokenizing.' mod='paypal'}",
        tkn_failed:"{l s='Tokenize failed' mod='paypal'}",
        https:"{l s='3D Secure requires HTTPS.' mod='paypal'}",
        load_3d:"{l s='Load 3D Secure Failed' mod='paypal'}",
        request_problem:"{l s='There was a problem with your request.' mod='paypal'}",
        failed_3d:"{l s='3D Secure Failed' mod='paypal'}"
    };
</script>