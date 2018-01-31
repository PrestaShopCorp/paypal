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
<div class="container-fluid">
    <div class="col-sm-12">
        <div class="paypal_block_info panel">
            <p>{l s='If you have just created your PayPal account, check the email sent by PayPal to confirm your email address.' mod='paypal'}</p>
            <p>{l s='If you encounter rounding issues with your orders, please change PrestaShop round mode in:' mod='paypal'} <a target="_blank" href="{$preference|escape:'javascript':'UTF-8'}}">{l s='Preferences > General' mod='paypal'}</a> {l s='then change for:' mod='paypal'}</p>
            <p><b>{l s='Round mode: "Round up away from zero, when it is half way there (recommended) "' mod='paypal'}</b></p>
            <p><b>{l s='Round type: "Round on each item"' mod='paypal'}</b></p>
        </div>
    </div>
</div>

<div style="display: none;">
    <div id="content-rounding-settings">
        <form action="{$return_url|escape:'javascript':'UTF-8'}" method="post" id="credential-configuration" class="bootstrap">

            <h4>{l s='Warning' mod='paypal'}</h4>

            <p>{l s='Your product rounding settings are not compliant with PayPal module.' mod='paypal'}</p>
            <p style='margin-bottom: 30px;'>{l s='Without modification of your PrastaShop configuration, PayPal will round items from cart to your customers.' mod='paypal'}</p>

            <p>
                <button class="btn btn-default"  onclick="$.fancybox.close();return false;">{l s='I understand' mod='paypal'}</button>
                <button class="btn btn-info" name="save_rounding_settings">{l s='Change rounding settings' mod='paypal'}</button>
            </p>
        </form>
    </div>

</div>

<script type="text/javascript">

    function display_rounding()
    {
        $.fancybox.open([
            {
                type: 'inline',
                autoScale: true,
                minHeight: 30,
                content: $('#content-rounding-settings').html(),
            }
        ]);
    }

    $(document).ready(function(){

        var need_rounding = {$need_rounding|escape:'html':'UTF-8'};

        $('#configuration_form input[name=paypal_sandbox]').change(function(event) {
            sandbox = $('#configuration_form input[name=paypal_sandbox]:checked').val();
            if (need_rounding && sandbox == 0) {
                display_rounding();
            }
        });

    });

</script>