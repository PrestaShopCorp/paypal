<p>
            <img src="{$logos.LocalPayPalLogoMedium}" alt="{l s='PayPal' mod='paypal'}" style="margin-bottom: 5px" />
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

        <p style="margin-top:20px;">
            - {l s='The total amount of your order is' mod='paypal'}
            <span id="amount" class="price"><strong>{$total|escape:'htmlall':'UTF-8'}</strong></span> {if $use_taxes == 1}{l s='(tax incl.)' mod='paypal'}{/if}
        </p>
        <p>
            - {l s='We accept the following currency to be sent by PayPal:' mod='paypal'}&nbsp;<b>{$currency->name|escape:'htmlall':'UTF-8'}</b>
        </p>

{if $useStyle14}
<style>
    .shipping_address{
        width:35%;
        float:left;
    }
    .billing_address{
        width:35%;
        float:left;
    }
    .clearfix
    {
        clear:both;
    }
    .cart_container
    {
        margin-top:30px;
    }

    .cart_container .title
    {
        margin-bottom:20px;
        display:block;
    }

    #cart_summary
    {
        width:100%;
    }

    input.button_large[disabled="disabled"] {
        opacity: 0.2;
    }

</style>
{/if}

{if $useStyle15}
<style>
    #cart_summary, .cart_container
    {
        width:100%;
    }
</style>
{/if}