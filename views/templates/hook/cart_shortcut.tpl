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

<div id="container_express_checkout" style="float:right; margin: 10px 40px 0 0">
    <form id="paypal_payment_form_cart" class="paypal_payment_form" action="{$action_url|escape:'htmlall':'UTF-8'}" title="{l s='Pay with PayPal' mod='paypal'}" method="post" data-ajax="false">
        <input type="hidden" name="method" value="{$PayPal_payment_type|escape:'htmlall':'UTF-8'}"/>
        <input type="hidden" name="current_shop_url" id="paypal_url_page" value="" />
        <input type="hidden" name="bn" value="{$PayPal_tracking_code|escape:'htmlall':'UTF-8'}" />
        <input type="hidden" id="in_context_checkout_enabled" value="0">
        <input type="hidden" id="source_page" value="cart">
        <img id="payment_paypal_express_checkout" src="{$PayPal_img_esc|escape:'htmlall':'UTF-8'}" alt="{l s='PayPal' mod='paypal'}" style="cursor:pointer;" onclick="setInput();return false"/>

    </form>
</div>
<div class="clearfix"></div>
<script type="text/javascript" src="https://www.paypalobjects.com/api/checkout.js"></script>
<script type="text/javascript" src="{$shop_url}modules/paypal/views/js/shortcut_cart.js"></script>
