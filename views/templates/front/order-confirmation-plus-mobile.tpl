{*
* 2007-2015 PrestaShop
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
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if $smarty.const._PS_VERSION_ < 1.5 && isset($use_mobile) && $use_mobile}
    {include file="$tpl_dir./modules/paypal/views/templates/front/order-confirmation-plus.tpl"}
{else}
{capture name=path}{l s='Order confirmation' mod='paypal'}{/capture}
{if $smarty.const._PS_VERSION_ < 1.6}
    {include file="$tpl_dir./breadcrumb.tpl"}
{/if}
<h1>{l s='Order confirmation' mod='paypal'}</h1>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{include file="$tpl_dir./errors.tpl"}

{if $smarty.const._PS_VERSION_ < 1.5}
	<link rel="stylesheet" href="{$base_dir}/modules/paypal/views/css/paypal_1_4.css">
{/if}

<div class="inforeturn"></div>
<div class="confirm_PPP">
    {if $state == 'approved' || $state == 'created'}
        <h2>{l s="Confirmation de votre commande ?" mod='paypal'}</h2>
        <p>{l s="Voulez-vous confirmer votre commande d'un montant de " mod='paypal'}{$totalAmount}</p>
        <form method="POST" action="" id="formConfirm">
            <input type="hidden" name="payerID" value="{$PayerID}" />
            <input type="hidden" name="paymentId" value="{$paymentId}" />
            <input type="hidden" name="id_cart" value="{$id_cart}" />

            <input id="cancel" class="{if $smarty.const._PS_VERSION_ < 1.5}button_large{else}button btn btn-large{/if}" type="submit" name="confirmCancel" value="{l s='Annulation de votre achat' mod='paypal'}" />
            <input id="confirm" class="{if $smarty.const._PS_VERSION_ < 1.5}button_large{else}button btn btn-large{/if}" type="submit" name="confirmPayment" value="{l s='Confirmation de votre achat' mod='paypal'}" />
        </form>
        <script type="text/javascript">

            $(document).ready(function () {


                $("#formConfirm input[type=submit]").click(function () {
                    $("input[type=submit]", $(this).parents("form")).removeAttr("clicked");
                    $(this).attr("clicked", "true");
                });

                $('#formConfirm').submit(function () {

                    var form = $('#formConfirm');
                    var nameSubmit = $("input[type=submit][clicked=true]").attr('name');
					
                    $('#cancel').attr('disabled','disabled');
                    $('#confirm').attr('disabled','disabled');

                    $.ajax({
                        url: '{$linkSubmitPlus}',
                        type: 'POST',
                        data: form.serialize() + '&ajax=true&submit=' + nameSubmit,
                        success: function (data) {

                            var json = JSON.parse(data);

                            $('.paypal-error').remove();

                            if (typeof json.success != 'undefined') {
                                $('.inforeturn').html('<p class="alert alert-success">' + json.success + '</p>');


                                setTimeout(function () {
                                   document.location.href="{$base_dir}";
                                }, 2000);

                            }

                            if (typeof json.error != 'undefined') {
                                $('.inforeturn').html('<p class="alert alert-warning paypal-error">' + json.error + '</p>');


                                setTimeout(function () {
                                    document.location.href="{$base_dir}";
                                }, 2000);

                            }
                        }
                    });

                    return false;
                });

            });

        </script>
        <div style="margin-top:15px;">
            {if $is_guest}
                <a href="{$link->getPageLink('guest-tracking.php', true)}?id_order={$order_reference}" title="{l s='Follow my order' mod='paypal'}" data-ajax="false">
                    {if $smarty.const._PS_VERSION_ < 1.6}
                        <img src="{$img_dir}icon/order.gif" alt="{l s='Follow my order' mod='paypal'}" class="icon" />
                    {else}
                        <i class="icon-chevron-left"></i>
                    {/if}
                </a>
                <a href="{$link->getPageLink('guest-tracking.php', true)}?id_order={$order_reference}" title="{l s='Follow my order' mod='paypal'}" data-ajax="false">{l s='Follow my order' mod='paypal'}</a>
            {else}
                <a href="{$link->getPageLink('history.php', true)}" title="{l s='Back to orders' mod='paypal'}" data-ajax="false">{if $smarty.const._PS_VERSION_ < 1.6}
                    <img src="{$img_dir}icon/order.gif" alt="{l s='Follow my order' mod='paypal'}" class="icon" />
                {else}
                    <i class="icon-chevron-left"></i>
                {/if}</a>
            <a href="{$link->getPageLink('history.php', true)}" title="{l s='Back to orders' mod='paypal'}" data-ajax="false">{l s='Back to orders' mod='paypal'}</a>
        {/if}
    </div>

{elseif $state == 'failed' || $state == 'expired'}
    <p class="alert alert-warning paypal-error">{l s="Une erreur est survenue pendant votre commande" mod='paypal'}</p>

{elseif $state == 'canceled'}
    <p class="alert alert-warning paypal-error">{l s="Votre commande a été annulé" mod='paypal'}</p>
{/if}
</div>
{/if}
