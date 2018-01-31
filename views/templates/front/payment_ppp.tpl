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

<style>
    #popup-ppp-waiting p{
        font-size: 16px;
        margin: 10px;
        line-height: 1.5em;
        color: #373a3c;
    }
</style>
<div class="row">
    <div class="col-xs-12 col-md-10">
        <div class="paypal-plus-row-payment">
            <div class="payment_module paypal-plus">

                {include file="module:paypal/views/templates/front/payment_infos.tpl"}

                <div id="ppplus" style="width: 100%;"> </div>
                <div id="bt-paypal-error-msg"></div>

            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var ppp_approval_url = '{$approval_url_ppp nofilter}';
    var ppp_mode = '{$mode}';
    var ppp_language_iso_code = '{$ppp_language_iso_code}';
    var ppp_country_iso_code = '{$ppp_country_iso_code}';
    var ajax_patch_url = '{$ajax_patch_url nofilter}';
    var waiting_redirection = "{l s='In few seconds you will be redirected to PayPal. Please wait.' mod='paypal'}";
</script>