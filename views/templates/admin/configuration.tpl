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
<div dir="ltr" style="text-align: left;" trbidi="on">
    <script type="text/javascript">
         (function(d, s, id){
         var js, ref = d.getElementsByTagName(s)[0];
            if (!d.getElementById(id)){
                js = d.createElement(s); js.id = id; js.async = true;
                js.src = "https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js";
                ref.parentNode.insertBefore(js, ref);
            }
         }(document, "script", "paypal-js"));
    </script>
</div>

<div class="container-fluid paypal-nav">
    <ul class="nav nav-pills navbar-separator">
        <li {if !isset($ec_paypal_active) && !isset($ec_card_active) && !isset($bt_active) && !isset($ppp_active)}class="active"{/if}><a data-toggle="pill" href="#paypal_conf"><span>{l s='Products' mod='paypal'}</span></a></li>
        <li {if isset($ec_paypal_active) || isset($ec_card_active) || isset($bt_active) || isset($ppp_active)}class="active"{/if}><a data-toggle="pill" href="#paypal_params"><span>{l s='Settings' mod='paypal'}</span></a></li>
    </ul>
    <div class="tab-content">
        <div id="paypal_conf"  class="tab-pane fade {if !isset($ec_paypal_active) && !isset($ec_card_active) && !isset($bt_active) && !isset($ppp_active)}in active{/if}">
        <div class="box half left">
            <div class="logo">
                 <img src="{$path|escape:'html':'UTF-8'}/views/img/paypal_btm.png" alt=""  />
                <div>{l s='The smart choice for business' mod='paypal'}</div>
            </div>
            <ul class="tick">
                <li><span class="paypal-bold">{l s='Target more customers' mod='paypal'}</span><br />{l s='More than 200 million PayPal active users worldwide' mod='paypal'}.</li>
                <li><span class="paypal-bold">{l s='Truly global' mod='paypal'}</span><br />{l s='Access a whole world of customers. PayPal is available in more than 200 markets and in 25 currencies' mod='paypal'}.</li>
                <li><span class="paypal-bold">{l s='Accept all types of payments' mod='paypal'}</span><br />{l s='Use PayPal with simple buy button or also payment by card due to Braintree (un service PayPal)' mod='paypal'}.</li>
                <li><span class="paypal-bold">{l s='Safety' mod='paypal'}</span><br />{l s='Protect your profit from the risks of fraud thanks to our program of' mod='paypal'} <a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/ua/useragreement-full#011">{l s='Seller Protection' mod='paypal'}</a>.</li>
            </ul>

        </div>

        <div class="box half right">
            <div class="info">
                <p class="paypal-bold">{l s='Merchant Country' mod='paypal'} {$country|escape:'html':'UTF-8'}</p>
                <p><i>
                        {l s='To modify country : ' mod='paypal'}
                        <a target="_blank" href="{$localization|escape:'html':'UTF-8'}">{l s='International > Localization' mod='paypal'}</a>
                    </i></p>
            </div>
        </div>
        <div style="clear:both;"></div>

        <div class="active-products">
            <p><b>{l s='2 PayPal products selected for you' mod='paypal'}</b></p>
            <div class="col-sm-6">
                <div class="panel {if isset($ec_paypal_active) && $ec_paypal_active}active-panel{/if}">
                    <img class="paypal-products" src="{$path|escape:'html':'UTF-8'}/views/img/paypal.png">
                    <p>
                            {l s='Accept PayPal payments, you can optimize your conversion rate.' mod='paypal'}
                    </p>
                    <p><ul>
                        <li>{l s='Fast, simple & secure, used by over 200 million active users' mod='paypal'}</li>
                        <li>{l s='OneTouch' mod='paypal'}&trade; {l s='optimizes your conversion rate up to 87.5%.' mod='paypal'}
                            {l s='Rate with OneTouch' mod='paypal'}&trade; {l s='in theme of comScore, 4th trilestre 2015 in United States' mod='paypal'}</li>
                        <li>{l s='Fully optimized for mobile payments' mod='paypal'}</li>
                        <li>{l s='Benefit of in-context checkout so your buyers never leave your site' mod='paypal'}</li>
                        <li>{l s='With our Seller Protection Program and advanced fraud screening, xe can protect your business' mod='paypal'}</li>
                    </ul></p>
                    <p>
                        <a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/express-checkout">{l s='More Information' mod='paypal'}</a>
                    </p>
                    <div class="bottom">
                        <img src="{$img_checkout|escape:'html':'UTF-8'}" class="product-img">
                        <a class="btn btn-default pull-right" href="{$return_url|escape:'html':'UTF-8'}&method=EC&with_card=0{if isset($ec_paypal_active) &&  $ec_paypal_active}&modify=1{/if}" >{if isset($ec_paypal_active) && $ec_paypal_active}{l s='Modify' mod='paypal'}{else}{l s='Activate' mod='paypal'}{/if}</a>
                    </div>
                </div>
            </div>
            {if !isset($braintree_available) && !isset($ppp_available)}
            <div class="col-sm-6">
                <div class="panel {if isset($ec_active) && $ec_active && isset($ec_card_active) && $ec_card_active}actvie-panel{/if}">
                    <img class="paypal-products" src="{$path|escape:'html':'UTF-8'}/views/img/paypal.png">
                    <p>
                            {l s='Accept credit cards, debit cards and PayPal payments' mod='paypal'}
                    </p>
                    <p><ul>
                        <li>{l s='Fast, simple & secure, used by over 200 million active users' mod='paypal'}</li>
                        <li>{l s='OneTouch' mod='paypal'}&trade; {l s='optimizes your conversion rate up to 87.5%.' mod='paypal'}
                            {l s='Rate with OneTouch' mod='paypal'}&trade; {l s='in theme of comScore, 4th trilestre 2015 in United States' mod='paypal'}</li>
                        <li>{l s='Fully optimized for mobile payments' mod='paypal'}</li>
                        <li>{l s='Benefit of in-context checkout so your buyers never leave your site' mod='paypal'}</li>
                        <li>{l s='With our Seller Protection Program and advanced fraud screening, xe can protect your business' mod='paypal'}</li>
                    </ul></p>
                    <p><a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/express-checkout">{l s='More Information' mod='paypal'}</a></p>
                    <div class="bottom">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/paypal_btm.png" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/visa.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/mastercard.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/maestro.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/jcb.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/discover.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/amex.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/diners.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/unionpay.svg" class="product-img">
                        <a class="btn btn-default pull-right" href="{$return_url|escape:'html':'UTF-8'}&method=EC&with_card=1{if isset($ec_active) && $ec_active && isset($ec_card_active) && $ec_card_active}&modify=1{/if}">{if  isset($ec_active) && $ec_active && isset($ec_card_active) && $ec_card_active}{l s='Modify' mod='paypal'}{else}{l s='Activate' mod='paypal'}{/if}</a>
                    </div>
                </div>
            </div>
            {/if}
            {if isset($braintree_available)}
            <div class="col-sm-6">
                <div class="panel {if isset($bt_paypal_active) && $bt_paypal_active}active-panel{/if}">
                    <img class="paypal-products" src="{$path|escape:'html':'UTF-8'}/views/img/braintree-paypal.png">
                    <p>
                        {l s='Accept PayPal, debit and credit card payments via Braintree (a PayPal service)' mod='paypal'}.
                    </p>
                    <p><ul>
                        <li>{l s='Get the best of PayPal & Braintree in a single solution' mod='paypal'}</li>
                        <li>{l s='Benefit of PayPal\'s OneTouch' mod='paypal'}&trade; {l s='conversion rate improvements, in-context payments & Seller Protection Program' mod='paypal'}</li>
                        <li>{l s='Offer debit and credit card payments with all major global card networks' mod='paypal'}</li>
                        <li>{l s='No monthly or setup fee - check PayPal\'s & Braintree\'s pricing' mod='paypal'}</li>
                        <li>{l s='Protect your payments with 3D Secure & PCI DSS v3.0 SAQ-A compliance' mod='paypal'}</li>
                    </ul></p>
                    <p><a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/hosted">{l s='More Information' mod='paypal'}</a></p>
                    <div class="bottom">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/paypal_btm.png" class="product-img"> <b>+</b>
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/visa.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/mastercard.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/maestro.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/jcb.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/discover.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/amex.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/diners.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/unionpay.svg" class="product-img">
                        <a class="btn btn-default pull-right" href="{$return_url|escape:'html':'UTF-8'}&method=BT&with_paypal=1{if isset($bt_paypal_active) && $bt_paypal_active}&modify=1{/if}">{if isset($bt_paypal_active) && $bt_paypal_active}{l s='Modify' mod='paypal'}{else}{l s='Activate' mod='paypal'}{/if}</a>
                    </div>
                </div>
            </div>
            {/if}
            {if isset($ppp_available)}
            <div class="col-sm-6">
                <div class="panel {if isset($ppp_active)}active-panel{/if}">
                    <img class="paypal-products" src="{$path|escape:'html':'UTF-8'}/views/img/paypal.png">
                    <p>
                        {l s='PayPal Plus' mod='paypal'}
                    </p>
                    <p><ul>
                        <li>{l s='Get the best of PayPal & Braintree in a single solution' mod='paypal'}</li>
                        <li>{l s='Benefit of PayPal\'s OneTouch' mod='paypal'}&trade; {l s='conversion rate improvements, in-context payments & Seller Protection Program' mod='paypal'}</li>
                        <li>{l s='Offer debit and credit card payments with all major global card networks' mod='paypal'}</li>
                        <li>{l s='No monthly or setup fee - check PayPal\'s & Braintree\'s pricing' mod='paypal'}</li>
                        <li>{l s='Protect your payments with 3D Secure & PCI DSS v3.0 SAQ-A compliance' mod='paypal'}</li>
                    </ul></p>
                    <p><a target="_blank" href="https://www.paypal.com/webapps/mpp/standard">{l s='More Information' mod='paypal'}</a></p>
                    <div class="bottom">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/paypal.png" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/visa.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/mastercard.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/amex.svg" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/ppp-bank-logo.png" class="product-img">
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/kauf.png" class="product-img">
                        <a class="btn btn-default pull-right" href="#" onclick="display_popup('PPP')">{if isset($ppp_active)}{l s='Modify' mod='paypal'}{else}{l s='Activate' mod='paypal'}{/if}</a>
                    </div>
                </div>
            </div>
            {/if}
        </div>

        <div style="clear:both;"></div>
        <div class="blue">
            <div class="row-fluid" id="rtb1">
                <header class="containerCentered center-text">
                    <h2 class="pulloutHeadline ">{l s='Find out why 17 million businesses worldwide choose PayPal.' mod='paypal'}</h2>
                </header>
                <div class="containerCentered">
                    <div class="span4">
                        <h2 class="contentHead large h3">{l s='Safer and more protected' mod='paypal'}</h2>
                        <p class="contentPara">{l s='With our Seller Protection and advanced fraud screening, we can protect your business.' mod='paypal'}</p>
                    </div>
                    <div class="span4">
                        <h2 class="contentHead large h3">{l s='Easy and convenient' mod='paypal'}</h2>
                        <p class="contentPara">{l s='Customers need just an email address and password or mobile number and PIN to pay quickly and more securely.' mod='paypal'}</p>
                    </div>
                    <div class="span4">
                        <h2 class="contentHead large h3">{l s='Preferred by customers' mod='paypal'}</h2>
                        <p class="contentPara">{l s='We’re the smart choice: Great Britain’s No.1 preferred online and mobile payment method' mod='paypal'}<sup>4</sup>.
                            {l s='For invaluable insights into what makes British shoppers tick' mod='paypal'},
                            <a data-pa-click="link|shopping-habits" href="https://www.paypal.com/uk-shopping-habits">{l s='click here' mod='paypal'}</a>.</p>
                    </div>
                </div>
            </div>
            <div class="row-fluid" id="rtb2">
                <div class="containerCentered">
                    <div class="span4">
                        <h2 class="contentHead large h3">{l s='Truly global' mod='paypal'}</h2>
                        <p class="contentPara">{l s='Access a whole world of customers. PayPal is available in 202 countries and markets, and in 25 currencies.' mod='paypal'}</p>
                    </div>
                    <div class="span4">
                        <h2 class="contentHead large h3">{l s='Simple to integrate' mod='paypal'}</h2>
                        <p class="contentPara">{l s='Works with all major shopping carts and ecommerce platforms.' mod='paypal'}</p>
                    </div>
                    <div class="span4">
                        <h2 class="contentHead large h3">{l s='24/7 customer support' mod='paypal'}</h2>
                        <p class="contentPara">{l s='Whatever your query, we\'ve got it covered. Online or on the phone, we\'re here to help.' mod='paypal'}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
        <div id="paypal_params" class="tab-pane fade col-sm-12 {if isset($ec_paypal_active) || isset($ec_card_active) || isset($bt_active) || isset($ppp_active)}in active{/if}">
        {if isset($ec_paypal_active) || isset($ec_card_active) || isset($bt_active) || isset($ppp_active)}
        <div class="panel parametres">
            <div class="panel-body">
                <div class="col-sm-8 help-left">
                    {if isset($ec_paypal_active) && $ec_paypal_active}
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/paypal.png">
                        <p>
                                {l s='Accept PayPal payments, you can optimize your conversion rate.' mod='paypal'} : {$active_products|escape:'html':'UTF-8'}
                        </p>
                        <p><ul>
                            <li>{l s='Fast, simple & secure, used by over 200 million active users' mod='paypal'}</li>
                            <li>{l s='OneTouch' mod='paypal'}&trade; {l s='optimizes your conversion rate up to 87.5%.' mod='paypal'}
                                {l s='Rate with OneTouch' mod='paypal'}&trade; {l s='in theme of comScore, 4th trimestre 2015 in United States' mod='paypal'}</li>
                            <li>{l s='Fully optimized for mobile payments' mod='paypal'}</li>
                            <li>{l s='Benefit of in-context checkout so your buyers never leave your site' mod='paypal'}</li>
                            <li>{l s='With our Seller Protection Program and advanced fraud screening, we can protect your business' mod='paypal'}</li>
                        </ul></p>
                        <p>
                            <a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/express-checkout">{l s='More Information' mod='paypal'}</a>
                        </p>
                    {elseif isset($ec_card_active) && $ec_card_active}
                        <img src="{$path|escape:'html':'UTF-8'}/views/img/paypal.png">
                        <p>
                                {l s='Accept credit cards, debit cards and PayPal payments' mod='paypal'} : {$active_products|escape:'html':'UTF-8'}
                        </p>
                        <p><ul>
                            <li>{l s='Fast, simple & secure, used by over 200 million active users' mod='paypal'}</li>
                            <li>{l s='OneTouch' mod='paypal'}&trade; {l s='optimizes your conversion rate up to 87.5%.' mod='paypal'}
                                {l s='Rate with OneTouch' mod='paypal'}&trade; {l s='in theme of comScore, 4th trilestre 2015 in United States' mod='paypal'}</li>
                            <li>{l s='Fully optimized for mobile payments' mod='paypal'}</li>
                            <li>{l s='Benefit of in-context checkout so your buyers never leave your site' mod='paypal'}</li>
                            <li>{l s='With our Seller Protection Program and advanced fraud screening, xe can protect your business' mod='paypal'}</li>
                        </ul></p>
                        <p><a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/express-checkout">{l s='More Information' mod='paypal'}</a></p>
                    {elseif isset($bt_paypal_active) && $bt_paypal_active}
                        <img class="paypal-products" src="{$path|escape:'html':'UTF-8'}/views/img/braintree-paypal.png">
                        <p>
                            {l s='Accept PayPal, debit and credit card payments via Braintree (a PayPal service)' mod='paypal'}.
                        </p>
                        <p><ul>
                            <li>{l s='Get the best of PayPal & Braintree in a single solution' mod='paypal'}</li>
                            <li>{l s='Benefit of PayPal\'s OneTouch' mod='paypal'}&trade; {l s='conversion rate improvements, in-context payments & Seller Protection Program' mod='paypal'}</li>
                            <li>{l s='Offer debit and credit card payments with all major global card networks' mod='paypal'}</li>
                            <li>{l s='No monthly or setup fee - check PayPal\'s & Braintree\'s pricing' mod='paypal'}</li>
                            <li>{l s='Protect your payments with 3D Secure & PCI DSS v3.0 SAQ-A compliance' mod='paypal'}</li>
                        </ul></p>
                        <p><a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/hosted">{l s='More Information' mod='paypal'}</a></p>
                    {elseif isset($bt_active) && !$bt_paypal_active && $bt_active}
                        <img class="paypal-products" src="{$path|escape:'html':'UTF-8'}/views/img/braintree-paypal.png">
                        <p>
                            {l s='Accept Braintree payments' mod='paypal'}
                        </p>
                        <p>
                            {l s='Your customers can pay with a selection of local and international debit and credit cards. Make online payments simple. PayPal customers can buy from you quickly if they use One Touch' mod='paypal'}&trade;
                        </p>
                        <p><a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/express-checkout">{l s='More Information' mod='paypal'}</a></p>
                    {elseif isset($ppp_available) && isset($ppp_active)}
                        <img class="paypal-products" src="{$path|escape:'html':'UTF-8'}/views/img/paypal.png">
                        <p>
                            {l s='PayPal Plus' mod='paypal'}
                        </p>
                        <p><ul>
                            <li>{l s='Get the best of PayPal & Braintree in a single solution' mod='paypal'}</li>
                            <li>{l s='Benefit of PayPal\'s OneTouch' mod='paypal'}&trade; {l s='conversion rate improvements, in-context payments & Seller Protection Program' mod='paypal'}</li>
                            <li>{l s='Offer debit and credit card payments with all major global card networks' mod='paypal'}</li>
                            <li>{l s='No monthly or setup fee - check PayPal\'s & Braintree\'s pricing' mod='paypal'}</li>
                            <li>{l s='Protect your payments with 3D Secure & PCI DSS v3.0 SAQ-A compliance' mod='paypal'}</li>
                        </ul></p>
                        <p><a target="_blank" href="https://www.paypal.com/{$iso_code|escape:'html':'UTF-8'}/webapps/mpp/hosted">{l s='More Information' mod='paypal'}</a></p>
                    {/if}
                </div>
                <div class="col-sm-3 help-right">
                        <p>
                    {l s='More Information' mod='paypal'} ?
                    <a target="_blank" href="{l s='https://www.paypal.com/fr/webapps/mpp/contact-us' mod='paypal'}">{l s='Contact us' mod='paypal'}</a>
                </div>
            </div>
        </div>
        {/if}
        <div class="configuration-block"></div>
    </div>

    </div>
</div>
{if isset($ppp_available)}
<div style="display: none;">
    <div id="content-fancybox-configuration">
        <form action="{$return_url|escape:'javascript':'UTF-8'}" method="post" id="credential-configuration" class="bootstrap">
            <h4>{l s='API Credentials' mod='paypal'}</h4>
            <p>{l s='In order to accept PayPal Plus payments, please fill your API REST credentials.' mod='paypal'}</p>
            <ul>
                <li>{l s='Access' mod='paypal'} <a target="_blank" href="https://developer.paypal.com/developer/applications/">{l s='https://developer.paypal.com/developer/applications/' mod='paypal'}</a></li>
                <li>{l s='Log in or Create a business account' mod='paypal'}</li>
                <li>{l s='Create a « REST API apps »' mod='paypal'}</li>
                <li>{l s='Click « Show » en dessous de « Secret: »' mod='paypal'}</li>
                <li>{l s='Copy/paste your « Client ID » and « Secret » below for each environment' mod='paypal'}</li>
            </ul>
            <hr/>
            <input type="hidden" id="method" name="method"/>
            <input type="hidden" id="with_card" name="with_card"/>
            <h4>{l s='Sandbox' mod='paypal'}</h4>
            <p>
                <label for="sandbox_client_id">{l s='Client ID' mod='paypal'}</label>
                <input type="text" id="sandbox_client_id" name="sandbox[client_id]" value="{if isset($PAYPAL_SANDBOX_CLIENTID)}{$PAYPAL_SANDBOX_CLIENTID|escape:'htmlall':'UTF-8'}{/if}"/>
            </p>
            <p>
                <label for="sandbox_secret">{l s='Secret' mod='paypal'}</label>
                <input type="password" id="sandbox_secret" name="sandbox[secret]" value="{if isset($PAYPAL_SANDBOX_SECRET)}{$PAYPAL_SANDBOX_SECRET|escape:'htmlall':'UTF-8'}{/if}"/>
            </p>
            <h4>{l s='Live' mod='paypal'}</h4>
            <ul>
                <li>{l s='You can switch to "Live" environment on top right' mod='paypal'}</li>
            </ul>
            <p>
                <label for="live_client_id">{l s='Client ID' mod='paypal'}</label>
                <input type="text" id="live_client_id" name="live[client_id]" value="{if isset($PAYPAL_LIVE_CLIENTID)}{$PAYPAL_LIVE_CLIENTID|escape:'htmlall':'UTF-8'}{/if}"/>
            </p>
            <p>
                <label for="live_secret">{l s='Secret' mod='paypal'}</label>
                <input type="password" id="live_secret" name="live[secret]" value="{if isset($PAYPAL_LIVE_SECRET)}{$PAYPAL_LIVE_SECRET|escape:'htmlall':'UTF-8'}{/if}"/>
            </p>
            <hr/>
            <p>
                <button class="btn btn-default"  onclick="$.fancybox.close();return false;">{l s='Cancel' mod='paypal'}</button>
                <button class="btn btn-info" name="save_credentials">{l s='Confirm API Credentials' mod='paypal'}</button>
            </p>
        </form>
    </div>
</div>
{/if}

<script type="text/javascript">

    function display_popup(method)
    {
        $('#method').val(method);
        $.fancybox.open([
            {
                type: 'inline',
                autoScale: true,
                minHeight: 30,
                content: $('#content-fancybox-configuration').html(),
            }
        ]);
    }

    $(document).ready(function(){

        $('#change_product').click(function(event) {
            event.preventDefault();
            $('a[href=#paypal_conf]').click();
        });
        $('.main_form').insertAfter($('.configuration-block'));
        $('.bt_currency_form').insertAfter($('.main_form'));
        $('.form_shortcut').insertAfter($('.main_form'));
        $('.form_api_username').insertAfter($('.form_shortcut'));
        $('input[name=paypal_ec_in_context]').on("change", function(){
            if (this.value != 0) {
                $('#config_logo-name').parents('.form-group').hide();
            } else {
                $('#config_logo-name').parents('.form-group').show();
            }
        });
        if ($('input[name=paypal_ec_in_context]:checked').val() != 0) {
            $('#config_logo-name').parents('.form-group').hide();
        }
        $('input[name=paypal_vaulting]').on("change", function(){
            if (this.value == 0) {
                $('#card_verification_on').parents('.form-group').hide();
            } else {
                $('#card_verification_on').parents('.form-group').show();
            }
        });
        if ($('input[name=paypal_vaulting]:checked').val() == 0) {
            $('#card_verification_on').parents('.form-group').hide();
        }
        var ssl_active = "{$ssl_active|escape:'htmlall':'UTF-8'}";
        if ($('#config_logo-images-thumbnails').length && !ssl_active) {
            $('#config_logo-images-thumbnails').after("{l s='An image is on a insecure (http) server and will not be shown on paypal' mod='paypal'}");
        }
    });

</script>
