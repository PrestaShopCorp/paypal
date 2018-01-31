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
{if isset($error_msg)}
<div class="alert alert-danger">
    {$error_msg}
</div>
{/if}
<li id="paypal_transaction_id">
    {if $method == 'BT'}
        {l s='Braintree transaction id :' mod='paypal'}
    {else}
        {l s='Paypal transaction id :' mod='paypal'}
    {/if}
    {$transaction_id}
</li>
{if isset($ppp_information)}
    <dl>
        <dd>
            {l s='The bank name' mod='paypal'} : {$ppp_information->recipient_banking_instruction->bank_name}
        </dd>
        <dd>
            {l s='Account holder name' mod='paypal'} : {$ppp_information->recipient_banking_instruction->account_holder_name}
        </dd>
        <dd>
            {l s='IBAN' mod='paypal'} : {$ppp_information->recipient_banking_instruction->international_bank_account_number}
        </dd>
        <dd>
            {l s='BIC' mod='paypal'} : {$ppp_information->recipient_banking_instruction->bank_identifier_code}
        </dd>
        <dd>
            {l s='Amount due / currency' mod='paypal'} : {$ppp_information->amount->value} {$ppp_information->amount->currency}
        </dd>
        <dd>
            {l s='Payment due date' mod='paypal'} : {$ppp_information->payment_due_date}
        </dd>
        <dd>
            {l s='Reference' mod='paypal'} : {$ppp_information->reference_number}
        </dd>
    </dl>
{/if}