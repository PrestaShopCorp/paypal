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

{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='My payment methods' mod='paypal'}
{/block}

{block name='page_content'}
    <!-- Page content -->
    {if $methods}
        <form action="{$link->getModuleLink('paypal', 'account', ['process' => 'save'])}" method="post">
        {foreach from=$methods key=method_key  item=method}
            <p class="method">
                {if $method.name_card}<b>{$method.name_card} : </b>{/if}
                {$method.info_card|escape:'htmlall':'UTF-8'}
                <a href="{$link->getModuleLink('paypal', 'account', ['process' => 'delete', 'id_method' => {$method.id_paypal_vaulting|escape:'htmlall':'UTF-8'}])}"><i class="material-icons">delete</i></a>
                <br />
                {if !$method.name_card}{l s='Add name' mod='paypal'}{else}{l s='Edit name' mod='paypal'}{/if}
                <span class="edit_name" data-method_id="{$method.id_paypal_vaulting|escape:'htmlall':'UTF-8'}"><i class="material-icons">mode_edit</i></span>
                <input type="text" value="{$method.name_card|escape:'htmlall':'UTF-8'}" name="name_{$method.id_paypal_vaulting|escape:'htmlall':'UTF-8'}" class="form-control" style="display: none"/>
            </p>
        {/foreach}
            <p><button class="btn btn-default" type="submit">{l s='Save' mod='paypal'} <i class="material-icons">save</i></button></p>
        </form>
    {else}
        {l s='You don\'t have saved payment methods from Paypal' mod='paypal'}
    {/if}

    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function(){
            $(document).on('click', '.edit_name', function(){
               var methodId = $(this).data('method_id');
               $('input[name=name_'+methodId+']').show();
            });
        });
    </script>
{/block}
