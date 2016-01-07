        <p>
            <img src="{$logos.LocalPayPalLogoMedium}" alt="{l s='PayPal' mod='paypal'}" class="paypal_logo" />
            <br />{l s='You have chosen to pay with PayPal.' mod='paypal'}
            <br/><br />
        {l s='Here is a short summary of your order:' mod='paypal'}
        </p>

        <p class="shipping_address col-sm-3 column grid_2">
            <strong>{l s='Shipping address' mod='paypal'}</strong><br/>
            {AddressFormat::generateAddress($address_shipping, $patternRules, '<br/>')}
            
        </p>
        <p class="billing_address col-sm-3">
            <strong>{l s='Billing address' mod='paypal'}</strong><br/>
            {AddressFormat::generateAddress($address_billing, $patternRules, '<br/>')}
            
        </p>

        <div class="clearfix"></div>
        
        <div class="col-sm-12 cart_container">
            <strong class="title">{l s='Your cart' mod='paypal'}</strong>
            <table id="cart_summary" class="table table-bordered stock-management-on">
            <thead>
                <tr>
                    <th>{l s='Image' mod='paypal'}</th>
                    <th>{l s='Name' mod='paypal'}</th>
                    <th>{l s='Quantity' mod='paypal'}</th>
                </tr>
            </thead>
            {foreach from=$cart->getProducts() item=product}
                <tr>
                    <td>
                        <img src="{$link->getImageLink('small', $product.id_image, $cart_image_size)}" alt="">
                    </td>
                    <td>
                        {$product.name}<br/>
                        {if isset($product.attributes) && $product.attributes}<small>{$product.attributes|escape:'html':'UTF-8'}</small>{/if}
                    </td>
                    <td>
                        {$product.quantity}
                    </td>
                </tr>
            {/foreach}
            </table>
        </div>

        <p class="paypal_total_amount">
            - {l s='The total amount of your order is' mod='paypal'}
            <span id="amount" class="price"><strong>{$total|escape:'htmlall':'UTF-8'}</strong></span> {if $use_taxes == 1}{l s='(tax incl.)' mod='paypal'}{/if}
        </p>
        <p>
            - {l s='We accept the following currency to be sent by PayPal:' mod='paypal'}&nbsp;<b>{$currency->name|escape:'htmlall':'UTF-8'}</b>
        </p>

        
<link rel="stylesheet" href="{$base_dir}/modules/paypal/views/css/paypal-cart_summary.css">
{if $useStyle14}
    <link rel="stylesheet" href="{$base_dir}/modules/paypal/views/css/paypal_1_4_paypal-cart_summary.css">
{/if}

{if $useStyle15}
    <link rel="stylesheet" href="{$base_dir}/modules/paypal/views/css/paypal_1_5_paypal-cart_summary.css">
{/if}