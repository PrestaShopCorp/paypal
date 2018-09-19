<?php
/**
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
 */

use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Refund;
use PayPal\Api\RefundRequest;
use PayPal\Api\Sale;

require(_PS_MODULE_DIR_.'paypal/sdk/paypalREST/vendor/autoload.php');

class MethodPPP extends AbstractMethodPaypal
{
    public $name = 'paypal';

    private $_items = array();

    private $_itemTotalValue = 0;

    private $_taxTotalValue = 0;

    private $_itemList;

    private $_amount;

    public function setConfig($params)
    {
        $paypal = Module::getInstanceByName($this->name);
        if (Tools::isSubmit('paypal_config')) {
            Configuration::updateValue('PAYPAL_API_ADVANTAGES', $params['paypal_show_advantage']);
            Configuration::updateValue('PAYPAL_PPP_CONFIG_TITLE', $params['ppp_config_title']);
            Configuration::updateValue('PAYPAL_PPP_CONFIG_BRAND', $params['ppp_config_brand']);
            if (isset($_FILES['ppp_config_logo']['tmp_name']) && $_FILES['ppp_config_logo']['tmp_name'] != '') {
                if (!in_array($_FILES['ppp_config_logo']['type'], array('image/gif', 'image/png', 'image/jpeg'))) {
                    $paypal->errors .= $paypal->displayError($paypal->l('Use a valid graphics format, such as .gif, .jpg, or .png.'));
                    return;
                }
                $size = getimagesize($_FILES['ppp_config_logo']['tmp_name']);
                if ($size[0] > 190 || $size[1] > 60) {
                    $paypal->errors .= $paypal->displayError($paypal->l('Limit the image to 190 pixels wide by 60 pixels high.'));
                    return;
                }
                if (!($tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS')) ||
                !move_uploaded_file($_FILES['ppp_config_logo']['tmp_name'], $tmpName)) {
                    $paypal->errors .= $paypal->displayError($paypal->l('An error occurred while copying the image.'));
                }
                if (!ImageManager::resize($tmpName, _PS_MODULE_DIR_.'paypal/views/img/ppp_logo'.Context::getContext()->shop->id.'.png')) {
                    $paypal->errors .= $paypal->displayError($paypal->l('An error occurred while copying the image.'));
                }
                Configuration::updateValue('PAYPAL_PPP_CONFIG_LOGO', _PS_MODULE_DIR_.'paypal/views/img/ppp_logo'.Context::getContext()->shop->id.'.png');
            }
            if ((Configuration::get('PAYPAL_SANDBOX') && Configuration::get('PAYPAL_SANDBOX_CLIENTID') && Configuration::get('PAYPAL_SANDBOX_SECRET'))
                || (!Configuration::get('PAYPAL_SANDBOX') && Configuration::get('PAYPAL_LIVE_CLIENTID') && Configuration::get('PAYPAL_LIVE_SECRET'))) {
                $experience_web = $this->createWebExperience();
                if ($experience_web) {
                    Configuration::updateValue('PAYPAL_PLUS_EXPERIENCE', $experience_web->id);
                } else {
                    $paypal->errors .= $paypal->displayError($paypal->l('An error occurred while creating your web experience. Check your credentials.'));
                }
            }
        }
        if (Tools::isSubmit('submit_shortcut')) {
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT', $params['paypal_show_shortcut']);
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT_CART', $params['paypal_show_shortcut_cart']);
        }

        if (Tools::getValue('deleteLogoPp')) {
            unlink(Configuration::get('PAYPAL_PPP_CONFIG_LOGO'));
            Configuration::updateValue('PAYPAL_PPP_CONFIG_LOGO', '');
        }

        if (Tools::isSubmit('save_credentials')) {
            $sandbox = Tools::getValue('sandbox');
            $live = Tools::getValue('live');
            if ($sandbox['client_id'] && $sandbox['secret'] && (!$live['client_id'] || !$live['secret'])) {
                Configuration::updateValue('PAYPAL_SANDBOX', 1);
            }
            Configuration::updateValue('PAYPAL_SANDBOX_CLIENTID', $sandbox['client_id']);
            Configuration::updateValue('PAYPAL_SANDBOX_SECRET', $sandbox['secret']);
            Configuration::updateValue('PAYPAL_LIVE_CLIENTID', $live['client_id']);
            Configuration::updateValue('PAYPAL_LIVE_SECRET', $live['secret']);
            Configuration::updateValue('PAYPAL_METHOD', 'PPP');
            Configuration::updateValue('PAYPAL_PLUS_ENABLED', 1);

            if ((Configuration::get('PAYPAL_SANDBOX') && $sandbox['client_id'] && $sandbox['secret'])
            || (!Configuration::get('PAYPAL_SANDBOX') && $live['client_id'] && $live['secret'])) {
                $experience_web = $this->createWebExperience();
                if ($experience_web) {
                    Configuration::updateValue('PAYPAL_PLUS_EXPERIENCE', $experience_web->id);
                } else {
                    $paypal->errors .= $paypal->displayError($paypal->l('An error occurred while creating your web experience. Check your credentials.'));
                }
            }
        }
    }

    public function getConfig(Paypal $module)
    {
        $params = array('inputs' => array(
            array(
                'type' => 'text',
                'label' => $module->l('Title'),
                'name' => 'ppp_config_title',
                'placeholder' => $module->l('Leave it empty to use default PayPal payment method title'),
            ),
            array(
                'type' => 'text',
                'label' => $module->l('Brand name'),
                'name' => 'ppp_config_brand',
                'placeholder' => $module->l('Leave it empty to use your Shop name'),
                'hint' => $module->l('A label that overrides the business name in the PayPal account on the PayPal pages.'),
            ),
            array(
                'type' => 'file',
                'label' => $module->l('Shop logo field'),
                'name' => 'ppp_config_logo',
                'display_image' => true,
                'delete_url' => $module->module_link.'&deleteLogoPp=1',
                'hint' => $module->l('An image must be stored on a secure (https) server. Use a valid graphics format, such as .gif, .jpg, or .png. Limit the image to 190 pixels wide by 60 pixels high. PayPal crops images that are larger. This logo will replace brand name  at the top of the cart review area.'),
                'image' => file_exists(_PS_MODULE_DIR_.'paypal/views/img/ppp_logo'.Context::getContext()->shop->id.'.png')?'<img src="'.Context::getContext()->link->getBaseLink().'modules/paypal/views/img/ppp_logo'.Context::getContext()->shop->id.'.png" class="img img-thumbnail" />':''
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Show PayPal benefits to your customers'),
                'name' => 'paypal_show_advantage',
                'desc' => $module->l(''),
                'is_bool' => true,
                'hint' => $module->l('You can increase your conversion rate by presenting PayPal benefits to your customers on payment methods selection page.'),
                'values' => array(
                    array(
                        'id' => 'paypal_show_advantage_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'paypal_show_advantage_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            ),
        ));

        $params['fields_value'] = array(
            'ppp_config_title' => Configuration::get('PAYPAL_PPP_CONFIG_TITLE'),
            'ppp_config_brand' => Configuration::get('PAYPAL_PPP_CONFIG_BRAND'),
            'ppp_config_logo' => Configuration::get('PAYPAL_PPP_CONFIG_LOGO'),
            'paypal_show_advantage' => Configuration::get('PAYPAL_API_ADVANTAGES'),
        );

        $params['shortcut'] = $this->createShortcutForm($module);


        $context = Context::getContext();
        $context->smarty->assign(array(
            'need_rounding' => ((Configuration::get('PS_ROUND_TYPE') == Order::ROUND_ITEM) || (Configuration::get('PS_PRICE_ROUND_MODE') != PS_ROUND_HALF_DOWN) ? 0 : 1),
            'ppp_active' => Configuration::get('PAYPAL_PLUS_ENABLED'),
        ));
        if ((Configuration::get('PS_ROUND_TYPE') != Order::ROUND_ITEM) || (Configuration::get('PS_PRICE_ROUND_MODE') != PS_ROUND_HALF_DOWN))  {
            $params['block_info'] = $module->display(_PS_MODULE_DIR_.$module->name, 'views/templates/admin/block_info.tpl');
        }

        return $params;
    }

    public function createShortcutForm($module)
    {
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $module->l('PayPal Express Shortcut'),
                'icon' => 'icon-cogs',
            ),
            'submit' => array(
                'title' => $module->l('Save'),
                'class' => 'btn btn-default pull-right button',
            ),
        );

        $fields_form[0]['form']['input'] = array(
            array(
                'type' => 'html',
                'name' => 'paypal_desc_shortcut',
                'html_content' => $module->l('The PayPal shortcut is displayed directly in the cart or on your product pages, allowing a faster checkout experience for your buyers. It requires fewer pages, clicks and seconds in order to finalize the payment. PayPal provides you with the client’s billing and shipping information so that you don’t have to collect it yourself.'),
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Display the shortcut on product pages'),
                'name' => 'paypal_show_shortcut',
                'is_bool' => true,
                'hint' => $module->l('Recommended for mono-product websites.'),
                'values' => array(
                    array(
                        'id' => 'paypal_show_shortcut_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'paypal_show_shortcut_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Display shortcut in the cart'),
                'name' => 'paypal_show_shortcut_cart',
                'is_bool' => true,
                'hint' => $module->l('Recommended for multi-products websites.'),
                'values' => array(
                    array(
                        'id' => 'paypal_show_shortcut_cart_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'paypal_show_shortcut_cart_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            ),
        );

        $fields_value = array(
            'paypal_show_shortcut' => Configuration::get('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT'),
            'paypal_show_shortcut_cart' => Configuration::get('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT_CART'),
        );

        $helper = new HelperForm();
        $helper->module = $module;
        $helper->name_controller = 'form_shortcut';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$module->name;
        $helper->title = $module->displayName;
        $helper->show_toolbar = false;
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->default_form_language = $default_lang;
        $helper->submit_action = 'submit_shortcut';
        $helper->allow_employee_form_lang = $default_lang;
        $helper->tpl_vars = array(
            'fields_value' => $fields_value,
            'id_language' => Context::getContext()->language->id,
            'back_url' => $module->module_link.'#paypal_params'
        );

        return $helper->generateForm($fields_form);
    }

    public function _getCredentialsInfo()
    {
        switch (Configuration::get('PAYPAL_SANDBOX')) {
            case 0:
                $apiContext = new ApiContext(
                    new OAuthTokenCredential(
                        Configuration::get('PAYPAL_LIVE_CLIENTID'),
                        Configuration::get('PAYPAL_LIVE_SECRET')
                    )
                );
                break;
            case 1:
                $apiContext = new ApiContext(
                    new OAuthTokenCredential(
                        Configuration::get('PAYPAL_SANDBOX_CLIENTID'),
                        Configuration::get('PAYPAL_SANDBOX_SECRET')
                    )
                );
                break;
        }

        $apiContext->setConfig(
            array(
                'mode' => Configuration::get('PAYPAL_SANDBOX') ? 'sandbox' : 'live',
                'log.LogEnabled' => false,
                'cache.enabled' => true,
            )
        );
        $apiContext->addRequestHeader('PayPal-Partner-Attribution-Id', (getenv('PLATEFORM') == 'PSREAD')?'PrestaShop_Cart_Ready_PPP':'PrestaShop_Cart_PPP_17');
        return $apiContext;
    }

    public function createWebExperience()
    {

        $brand_name = Configuration::get('PAYPAL_PPP_CONFIG_BRAND')?Configuration::get('PAYPAL_PPP_CONFIG_BRAND'):Configuration::get('PS_SHOP_NAME');
        $brand_logo = file_exists(_PS_MODULE_DIR_.'paypal/views/img/ppp_logo'.Context::getContext()->shop->id.'.png')?Context::getContext()->link->getBaseLink(Context::getContext()->shop->id, true).'modules/paypal/views/img/ppp_logo'.Context::getContext()->shop->id.'.png':Context::getContext()->link->getBaseLink().'img/'.Configuration::get('PS_LOGO');

        $flowConfig = new \PayPal\Api\FlowConfig();
        // When set to "commit", the buyer is shown an amount, and the button text will read "Pay Now" on the checkout page.
        $flowConfig->setUserAction("commit");
        // Defines the HTTP method to use to redirect the user to a return URL. A valid value is `GET` or `POST`.
        $flowConfig->setReturnUriHttpMethod("GET");
        // Parameters for style and presentation.
        $presentation = new \PayPal\Api\Presentation();
        // A URL to logo image. Allowed vaues: .gif, .jpg, or .png.
        $presentation->setLogoImage($brand_logo)
            //	A label that overrides the business name in the PayPal account on the PayPal pages.
            ->setBrandName($brand_name)
            //  Locale of pages displayed by PayPal payment experience.
            ->setLocaleCode(Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT')))
            // A label to use as hypertext for the return to merchant link.
            ->setReturnUrlLabel("Return");
        // Parameters for input fields customization.
        $inputFields = new \PayPal\Api\InputFields();
        // Enables the buyer to enter a note to the merchant on the PayPal page during checkout.
        $inputFields->setAllowNote(false)
            // Determines whether or not PayPal displays shipping address fields on the experience pages. Allowed values: 0, 1, or 2. When set to 0, PayPal displays the shipping address on the PayPal pages. When set to 1, PayPal does not display shipping address fields whatsoever. When set to 2, if you do not pass the shipping address, PayPal obtains it from the buyer’s account profile. For digital goods, this field is required, and you must set it to 1.
            ->setNoShipping(0)
            // Determines whether or not the PayPal pages should display the shipping address and not the shipping address on file with PayPal for this buyer. Displaying the PayPal street address on file does not allow the buyer to edit that address. Allowed values: 0 or 1. When set to 0, the PayPal pages should not display the shipping address. When set to 1, the PayPal pages should display the shipping address.
            ->setAddressOverride(1);
        // #### Payment Web experience profile resource
        $webProfile = new \PayPal\Api\WebProfile();
        // Name of the web experience profile. Required. Must be unique
        $webProfile->setName(Tools::substr(Configuration::get('PS_SHOP_NAME'), 0, 30) . uniqid())
            // Parameters for flow configuration.
            ->setFlowConfig($flowConfig)
            // Parameters for style and presentation.
            ->setPresentation($presentation)
            // Parameters for input field customization.
            ->setInputFields($inputFields)
            // Indicates whether the profile persists for three hours or permanently. Set to `false` to persist the profile permanently. Set to `true` to persist the profile for three hours.
            ->setTemporary(false);
        // For Sample Purposes Only.
        try {
            // Use this call to create a profile.
            $createProfileResponse = $webProfile->create($this->_getCredentialsInfo());
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            return false;
        }

        return $createProfileResponse;
    }

    public function init($params)
    {
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");
        // ### Itemized information
        // (Optional) Lets you specify item wise information

        $this->_itemList = new ItemList();
        $this->_amount = new Amount();

        $this->_getPaymentDetails();

        // ### Transaction
        // A transaction defines the contract of a
        // payment - what is the payment for and who
        // is fulfilling it.


        $transaction = new Transaction();
        $transaction->setAmount($this->_amount)
            ->setItemList($this->_itemList)
            ->setDescription("Payment description")
            ->setInvoiceNumber(uniqid());

        // ### Redirect urls
        // Set the urls that the buyer must be redirected to after
        // payment approval/ cancellation.

        $redirectUrls = new RedirectUrls();
        if ($params['short_cut']) {
            $return_url = Context::getContext()->link->getModuleLink($this->name, 'pppScOrder', array(), true);
        } else {
            $return_url = Context::getContext()->link->getModuleLink($this->name, 'pppValidation', array(), true);
        }
        $redirectUrls->setReturnUrl($return_url)
            ->setCancelUrl(Context::getContext()->link->getPageLink('order', true));

        // ### Payment
        // A Payment Resource; create one using
        // the above types and intent set to 'sale'

        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction))
            ->setExperienceProfileId(Configuration::get('PAYPAL_PLUS_EXPERIENCE'));

        // ### Create Payment
        // Create a payment by calling the 'create' method
        // passing it a valid apiContext.
        // The return object contains the state and the
        // url to which the buyer must be redirected to
        // for payment approval
        $payment->create($this->_getCredentialsInfo());

        // ### Get redirect url
        // The API response provides the url that you must redirect
        // the buyer to. Retrieve the url from the $payment->getApprovalLink() method
        return array('approval_url' => $payment->getApprovalLink(), 'payment_id' => $payment->id);
    }

    public function formatPrice($price)
    {
        $context = Context::getContext();
        $context_currency = $context->currency;
        $paypal = Module::getInstanceByName('paypal');
        if ($paypal->needConvert()) {
            $price = Tools::convertPrice($price, $context_currency, false);
        }
        $price = number_format($price, Paypal::getDecimal(), ".", '');
        return $price;
    }

    private function _getPaymentDetails()
    {
        $paypal = Module::getInstanceByName('paypal');
        $currency = $paypal->getPaymentCurrencyIso();
        $this->_getProductsList($currency);
        //$this->_getDiscountsList($items, $total_products);
        $this->_getGiftWrapping($currency);
        $this->_getPaymentValues($currency);
    }

    private function _getProductsList($currency)
    {
        $products = Context::getContext()->cart->getProducts();
        foreach ($products as $product) {
            $product['product_tax'] = $this->formatPrice($product['price_wt']) - $this->formatPrice($product['price']);
            $item = new Item();
            $item->setName(Tools::substr($product['name'], 0, 126))
                ->setCurrency($currency)
                ->setDescription(isset($product['attributes']) ? $product['attributes'] : '')
                ->setQuantity($product['quantity'])
                ->setSku($product['id_product']) // Similar to `item_number` in Classic API
                ->setPrice($this->formatPrice($product['price']));

            $this->_items[] = $item;
            $this->_itemTotalValue += $this->formatPrice($product['price']) * $product['quantity'];
            $this->_taxTotalValue += $product['product_tax'] * $product['quantity'];
        }
    }

    private function _getGiftWrapping($currency)
    {
        $wrapping_price = Context::getContext()->cart->gift ? Context::getContext()->cart->getGiftWrappingPrice() : 0;
        if ($wrapping_price > 0) {
            $wrapping_price = $this->formatPrice($wrapping_price);
            $item = new Item();
            $item->setName('Gift wrapping')
                ->setCurrency($currency)
                ->setQuantity(1)
                ->setSku('wrapping') // Similar to `item_number` in Classic API
                ->setPrice($wrapping_price);
            $this->_items[] = $item;
            $this->_itemTotalValue += $wrapping_price;
        }
    }

    private function _getPaymentValues($currency)
    {
        $this->_itemList->setItems($this->_items);
        $context = Context::getContext();
        $cart = $context->cart;
        $shipping_cost_wt = $cart->getTotalShippingCost();
        $shipping = $this->formatPrice($shipping_cost_wt);
        $total = $this->formatPrice($cart->getOrderTotal(true, Cart::BOTH));
        $summary = $cart->getSummaryDetails();
        $subtotal = $this->formatPrice($summary['total_products']);
        $total_tax = number_format($this->_taxTotalValue, Paypal::getDecimal(), ".", '');
        // total shipping amount
        $shippingTotal = $shipping;

        if ($subtotal != $this->_itemTotalValue) {
            $subtotal = $this->_itemTotalValue;
        }
        //total
        $total_cart = $shippingTotal + $this->_itemTotalValue + $this->_taxTotalValue;

        if ($total != $total_cart) {
            $total = $total_cart;
        }

        // ### Additional payment details
        // Use this optional field to set additional
        // payment information such as tax, shipping
        // charges etc.
        $details = new Details();
        $details->setShipping($shippingTotal)
            ->setTax($total_tax)
            ->setSubtotal($subtotal);
        // ### Amount
        // Lets you specify a payment amount.
        // You can also specify additional details
        // such as shipping, tax.
        $this->_amount->setCurrency($currency)
            ->setTotal($total)
            ->setDetails($details);
    }

    public function doPatch()
    {
        $discounts = Context::getContext()->cart->getCartRules();
        $total_discount = 0;
        if (count($discounts) > 0) {
            foreach ($discounts as $discount) {
                $total_discount += $this->formatPrice($discount['value_real']);
            }
        }

        // Retrieve the payment object by calling the tatic `get` method
        // on the Payment class by passing a valid Payment ID
        $payment = Payment::get(Context::getContext()->cookie->paypal_plus_payment, $this->_getCredentialsInfo());

        $cart = new Cart(Context::getContext()->cart->id);
        $address_delivery = new Address($cart->id_address_delivery);

        $state = '';
        if ($address_delivery->id_state) {
            $state = new State((int) $address_delivery->id_state);
        }
        $state_name = $state ? $state->iso_code : '';

        $this->_itemList = new ItemList();
        $this->_amount = new Amount();
        $this->_getPaymentDetails();

        $this->_amount->getDetails()->setShippingDiscount(-$total_discount);
        $this->_amount->setTotal($this->_amount->getTotal()-$total_discount);

        $patchReplace = new Patch();
        $patchReplace->setOp('replace')
            ->setPath('/transactions/0/amount')
            ->setValue(json_decode($this->_amount->toJSON()));

        $patchAdd = new Patch();
        $patchAdd->setOp('add')
            ->setPath('/transactions/0/item_list/shipping_address')
            ->setValue(json_decode('{
                    "recipient_name": "'.$address_delivery->firstname.' '.$address_delivery->lastname.'",
                    "line1": "'.$address_delivery->address1.'",
                    "city": "'.$address_delivery->city.'",
                    "state": "'.$state_name.'",
                    "postal_code": "'.$address_delivery->postcode.'",
                    "country_code": "'.Country::getIsoById($address_delivery->id_country).'"
                }'));

        $patchRequest = new PatchRequest();
        $patchRequest->setPatches(array($patchReplace, $patchAdd));
        return $payment->update($patchRequest, $this->_getCredentialsInfo());
    }

    public function validation()
    {
        $context = Context::getContext();
        $cart = $context->cart;
        // Get the payment Object by passing paymentId
        // payment id was previously stored in session in
        // CreatePaymentUsingPayPal.php
        $paymentId = Tools::getValue('shortcut') ? $context->cookie->paypal_pSc : Tools::getValue('paymentId');
        $payment = Payment::get($paymentId, $this->_getCredentialsInfo());
        if (Tools::getValue('shortcut')) {
            $discounts = Context::getContext()->cart->getCartRules();
            if (count($discounts) > 0) {
                Context::getContext()->cookie->__unset('paypal_pSc');
                Context::getContext()->cookie->__unset('paypal_pSc_payerid');
                throw new Exception('The total of the order do not match amount paid.');
            }
            $address_delivery = new Address($cart->id_address_delivery);
            $state = '';
            if ($address_delivery->id_state) {
                $state = new State((int) $address_delivery->id_state);
            }
            $state_name = $state ? $state->iso_code : '';
            $patchAdd = new Patch();
            $patchAdd->setOp('replace')
                ->setPath('/transactions/0/item_list/shipping_address')
                ->setValue(json_decode('{
                    "recipient_name": "'.$address_delivery->firstname.' '.$address_delivery->lastname.'",
                    "line1": "'.$address_delivery->address1.'",
                    "city": "'.$address_delivery->city.'",
                    "state": "'.$state_name.'",
                    "postal_code": "'.$address_delivery->postcode.'",
                    "country_code": "'.Country::getIsoById($address_delivery->id_country).'"
                }'));

            $patchRequest = new PatchRequest();
            $patchRequest->setPatches(array($patchAdd));
            $payment->update($patchRequest, $this->_getCredentialsInfo());
        }

        // ### Payment Execute
        // PaymentExecution object includes information necessary
        // to execute a PayPal account payment.
        // The payer_id is added to the request query parameters
        // when the user is redirected from paypal back to your site
        $execution = new PaymentExecution();
        $execution->setPayerId(Tools::getValue('shortcut') ? $context->cookie->paypal_pSc_payerid : Tools::getValue('PayerID'));
        // ### Optional Changes to Amount
        // If you wish to update the amount that you wish to charge the customer,
        // based on the shipping address or any other reason, you could
        // do that by passing the transaction object with just `amount` field in it.
        $exec_payment = $payment->execute($execution, $this->_getCredentialsInfo());

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        $currency = $context->currency;
        $total = (float)$exec_payment->transactions[0]->amount->total;
        $paypal = Module::getInstanceByName('paypal');
        $order_state = Configuration::get('PS_OS_PAYMENT');
        $transactionDetail = $this->getDetailsTransaction($exec_payment);
        $paypal->validateOrder($cart->id, $order_state, $total, 'PayPal', null, $transactionDetail, (int)$currency->id, false, $customer->secure_key);
        return true;
    }

    public function getDetailsTransaction($transaction)
    {
        $payment_info = $transaction->transactions[0];

        return array(
            'method' => 'PPP',
            'currency' => $payment_info->amount->currency,
            'transaction_id' => pSQL($payment_info->related_resources[0]->sale->id),
            'payment_status' => $transaction->state,
            'payment_method' => $transaction->payer->payment_method,
            'id_payment' => pSQL($transaction->id),
            'client_token' => "",
            'capture' => false,
            'payment_tool' => isset($transaction->payment_instruction)?$transaction->payment_instruction->instruction_type:'',
        );
    }

    public function confirmCapture()
    {
    }
    public function check()
    {
    }

    public function refund()
    {
        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));

        $sale = Sale::get($paypal_order->id_transaction, $this->_getCredentialsInfo());

        // Includes both the refunded amount (to Payer)
        // and refunded fee (to Payee). Use the $amt->details
        // field to mention fees refund details.
        $amt = new Amount();
        $amt->setCurrency($sale->getAmount()->getCurrency())
            ->setTotal($sale->getAmount()->getTotal());
        $refundRequest = new RefundRequest();
        $refundRequest->setAmount($amt);

        $response = $sale->refundSale($refundRequest, $this->_getCredentialsInfo());

        $result =  array(
            'success' => true,
            'refund_id' => $response->id,
            'status' => $response->state,
            'total_amount' => $response->total_refunded_amount->value,
            'currency' => $response->total_refunded_amount->currency,
            'saleId' => $response->sale_id,
        );

        return $result;
    }

    public function partialRefund($params)
    {
        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));

        $sale = Sale::get($paypal_order->id_transaction, $this->_getCredentialsInfo());

        $amount = 0;
        foreach ($params['productList'] as $product) {
            $amount += $product['amount'];
        }
        if (Tools::getValue('partialRefundShippingCost')) {
            $amount += Tools::getValue('partialRefundShippingCost');
        }

        $amt = new Amount();
        $amt->setCurrency($sale->getAmount()->getCurrency())
            ->setTotal(number_format($amount, Paypal::getDecimal(), ".", ''));
        $refundRequest = new RefundRequest();
        $refundRequest->setAmount($amt);

        $response = $sale->refundSale($refundRequest, $this->_getCredentialsInfo());

        $result =  array(
            'success' => true,
            'refund_id' => $response->id,
            'status' => $response->state,
            'total_amount' => $response->total_refunded_amount->value,
            'currency' => $response->total_refunded_amount->currency,
            'saleId' => $response->sale_id,
        );

        return $result;
    }

    public function void($params)
    {
    }

    public function getInstructionInfo($id_payment)
    {
        $sale = Payment::get($id_payment, $this->_getCredentialsInfo());
        return $sale->payment_instruction;
    }

    public function renderExpressCheckoutShortCut(&$context, $type, $page_source)
    {
        $lang = $context->country->iso_code;
        $environment = (Configuration::get('PAYPAL_SANDBOX')?'sandbox':'live');
        $img_esc = "modules/paypal/views/img/ECShortcut/".Tools::strtolower($lang)."/buy/buy.png";

        if (!file_exists(_PS_ROOT_DIR_.$img_esc)) {
            $img_esc = "modules/paypal/views/img/ECShortcut/us/buy/buy.png";
        }
        $shop_url = Context::getContext()->link->getBaseLink(Context::getContext()->shop->id, true);
        $context->smarty->assign(array(
            'shop_url' => $shop_url,
            'PayPal_payment_type' => $type,
            'PayPal_img_esc' => $shop_url.$img_esc,
            'action_url' => $context->link->getModuleLink('paypal', 'ScInit', array(), true),
            'environment' => $environment,
        ));

        if ($page_source == 'product') {
            $context->smarty->assign(array(
                'es_cs_product_attribute' => Tools::getValue('id_product_attribute'),
            ));
            return $context->smarty->fetch('module:paypal/views/templates/hook/PPP_shortcut.tpl');
        } elseif ($page_source == 'cart') {
            return $context->smarty->fetch('module:paypal/views/templates/hook/cart_shortcut.tpl');
        }
    }

    public function processCheckoutSc($response)
    {
        if (isset($response['approval_url'])) {
            Tools::redirect($response['approval_url']);
        } else {
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_code' => '00000')));
        }
    }
}
