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