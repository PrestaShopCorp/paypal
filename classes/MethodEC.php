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

use PayPal\CoreComponentTypes\BasicAmountType;
use PayPal\EBLBaseComponents\DoExpressCheckoutPaymentRequestDetailsType;
use PayPal\EBLBaseComponents\AddressType;
use PayPal\EBLBaseComponents\BillingAgreementDetailsType;
use PayPal\EBLBaseComponents\PaymentDetailsItemType;
use PayPal\EBLBaseComponents\PaymentDetailsType;
use PayPal\EBLBaseComponents\SetExpressCheckoutRequestDetailsType;
use PayPal\PayPalAPI\SetExpressCheckoutReq;
use PayPal\PayPalAPI\SetExpressCheckoutRequestType;
use PayPal\PayPalAPI\DoExpressCheckoutPaymentReq;
use PayPal\PayPalAPI\DoExpressCheckoutPaymentRequestType;
use PayPal\PayPalAPI\RefundTransactionReq;
use PayPal\PayPalAPI\RefundTransactionRequestType;
use PayPal\PayPalAPI\DoCaptureReq;
use PayPal\PayPalAPI\DoCaptureRequestType;
use PayPal\PayPalAPI\DoVoidReq;
use PayPal\PayPalAPI\DoVoidRequestType;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsRequestType;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsReq;
use PayPal\Service\PayPalAPIInterfaceServiceService;

require_once(_PS_MODULE_DIR_.'paypal/sdk/paypalNVP/PPBootStrap.php');

class MethodEC extends AbstractMethodPaypal
{
    public $name = 'paypal';

    public $token;

    private $_paymentDetails;

    private $_itemTotalValue = 0;

    private $_taxTotalValue = 0;


    public function getConfig(PayPal $module)
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
        $params = array('inputs' => array(
            array(
                'type' => 'select',
                'label' => $module->l('Payment action'),
                'name' => 'paypal_intent',
                'desc' => $module->l(''),
                'hint' => $module->l('Sale: the money moves instantly from the buyer\'s account to the seller\'s account at the time of payment. Authorization/capture: The authorized mode is a deferred mode of payment that requires the funds to be collected manually when you want to transfer the money. This mode is used if you want to ensure that you have the merchandise before depositing the money, for example. Be careful, you have 29 days to collect the funds.'),
                'options' => array(
                    'query' => array(
                        array(
                            'id' => 'sale',
                            'name' => $module->l('Sale')
                        ),
                        array(
                            'id' => 'authorization',
                            'name' => $module->l('Authorize')
                        )
                    ),
                    'id' => 'id',
                    'name' => 'name'
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Show PayPal benefits to your customers'),
                'name' => 'paypal_show_advantage',
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
            array(
                'type' => 'switch',
                'label' => $module->l('PayPal In-Context'),
                'name' => 'paypal_ec_in_context',
                'is_bool' => true,
                'hint' => $module->l('PayPal opens in a pop-up window, allowing your buyers to finalize their payment without leaving your website. Optimized, modern and reassuring experience which benefits from the same security standards than during a redirection to the PayPal website.'),
                'values' => array(
                    array(
                        'id' => 'paypal_ec_in_context_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'paypal_ec_in_context_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            ),
            array(
                'type' => 'text',
                'label' => $module->l('Brand name'),
                'name' => 'config_brand',
                'placeholder' => $module->l('Leave it empty to use your Shop name'),
                'hint' => $module->l('A label that overrides the business name in the PayPal account on the PayPal pages.'),
            ),
            array(
                'type' => 'file',
                'label' => $module->l('Shop logo field'),
                'name' => 'config_logo',
                'display_image' => true,
                'image' => file_exists(Configuration::get('PAYPAL_CONFIG_LOGO'))?'<img src="'.Context::getContext()->link->getBaseLink().'modules/paypal/views/img/p_logo_'.Context::getContext()->shop->id.'.png" class="img img-thumbnail" />':'',
                'delete_url' => $module->module_link.'&deleteLogoPp=1',
                'hint' => $module->l('An image must be stored on a secure (https) server. Use a valid graphics format, such as .gif, .jpg, or .png. Limit the image to 190 pixels wide by 60 pixels high. PayPal crops images that are larger. This logo will replace brand name  at the top of the cart review area.'),
            ),
        ));
        $params['fields_value'] = array(
            'paypal_intent' => Configuration::get('PAYPAL_API_INTENT'),
            'paypal_show_advantage' => Configuration::get('PAYPAL_API_ADVANTAGES'),
            'paypal_ec_in_context' => Configuration::get('PAYPAL_EXPRESS_CHECKOUT_IN_CONTEXT'),
            'paypal_ec_merchant_id' => Configuration::get('PAYPAL_MERCHANT_ID_'.$mode),
            'config_brand' => Configuration::get('PAYPAL_CONFIG_BRAND'),
            'config_logo' => Configuration::get('PAYPAL_CONFIG_LOGO'),
        );

        $country_default = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (!in_array($country_default, $module->bt_countries)) {
            $params['inputs'][] = array(
                'type' => 'switch',
                'label' => $module->l('Accept credit and debit card payment'),
                'name' => 'paypal_card',
                'is_bool' => true,
                'hint' => $module->l('Your customers can pay with debit and credit cards as well as local payment systems whether or not they use PayPal'),
                'values' => array(
                    array(
                        'id' => 'paypal_card_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'paypal_card_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            );
            $params['fields_value']['paypal_card'] = Configuration::get('PAYPAL_API_CARD');
        }


        $context = Context::getContext();

        $context->smarty->assign(array(
            'access_token_sandbox' => Configuration::get('PAYPAL_SANDBOX_ACCESS'),
            'access_token_live' => Configuration::get('PAYPAL_LIVE_ACCESS'),
            'ec_card_active' => Configuration::get('PAYPAL_API_CARD'),
            'ec_paypal_active' => !Configuration::get('PAYPAL_API_CARD'),
            'need_rounding' => ((Configuration::get('PS_ROUND_TYPE') == Order::ROUND_ITEM) || (Configuration::get('PS_PRICE_ROUND_MODE') != PS_ROUND_HALF_UP) ? 0 : 1),
            'ec_active' => Configuration::get('PAYPAL_EXPRESS_CHECKOUT'),
        ));

        if (Configuration::get('PS_ROUND_TYPE') != Order::ROUND_ITEM || Configuration::get('PS_PRICE_ROUND_MODE') != PS_ROUND_HALF_UP) {
            $params['block_info'] = $module->display(_PS_MODULE_DIR_.$module->name, 'views/templates/admin/block_info.tpl');
        }

        $params['form'] = $this->getApiUserName($module);

        $params['shortcut'] = $this->createShortcutForm($module);

        return $params;
    }

    public function getApiUserName($module)
    {
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $module->l('Api user name'),
                'icon' => 'icon-cogs',
            ),
        );
        $apiUserName = (Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_USERNAME_SANDBOX'):Configuration::get('PAYPAL_USERNAME_LIVE'));

        $fields_form[0]['form']['input'] = array(
            array(
                'type' => 'text',
                'label' => $module->l('API user name'),
                'name'=>'api_user_name',
                'disabled'=>'disabled'
            )
        );

        $helper = new HelperForm();
        $helper->module = $module;
        $helper->name_controller = 'form_api_username';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$module->name;
        $helper->title = $module->displayName;
        $helper->show_toolbar = false;
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->tpl_vars = array(
            'fields_value' => array('api_user_name'=>$apiUserName),
            'id_language' => Context::getContext()->language->id,
            'back_url' => $module->module_link.'#paypal_params'
        );
        return $helper->generateForm($fields_form);
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

    public function setConfig($params)
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
        $paypal = Module::getInstanceByName($this->name);
        if (isset($params['api_username']) && isset($params['api_password']) && isset($params['api_signature'])) {
            Configuration::updateValue('PAYPAL_METHOD', 'EC');
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT', 1);
            Configuration::updateValue('PAYPAL_USERNAME_'.$mode, $params['api_username']);
            Configuration::updateValue('PAYPAL_PSWD_'.$mode, $params['api_password']);
            Configuration::updateValue('PAYPAL_SIGNATURE_'.$mode, $params['api_signature']);
            Configuration::updateValue('PAYPAL_'.$mode.'_ACCESS', 1);
            Configuration::updateValue('PAYPAL_MERCHANT_ID_'.$mode, $params['merchant_id']);
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_IN_CONTEXT', 1);
            Tools::redirect($paypal->module_link);

        }
        if (Tools::isSubmit('submit_shortcut')) {
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT', $params['paypal_show_shortcut']);
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT_CART', $params['paypal_show_shortcut_cart']);
        }
        if (Tools::isSubmit('paypal_config')) {
            Configuration::updateValue('PAYPAL_API_INTENT', $params['paypal_intent']);
            Configuration::updateValue('PAYPAL_API_ADVANTAGES', $params['paypal_show_advantage']);
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_IN_CONTEXT', $params['paypal_ec_in_context']);
            Configuration::updateValue('PAYPAL_CONFIG_BRAND', $params['config_brand']);
            if (isset($_FILES['config_logo']['tmp_name']) && $_FILES['config_logo']['tmp_name'] != '') {
                if (!in_array($_FILES['config_logo']['type'], array('image/gif', 'image/png', 'image/jpeg'))) {
                    $paypal->errors .= $paypal->displayError($paypal->l('Use a valid graphics format, such as .gif, .jpg, or .png.'));
                    return;
                }
                $size = getimagesize($_FILES['config_logo']['tmp_name']);
                if ($size[0] > 190 || $size[1] > 60) {
                    $paypal->errors .= $paypal->displayError($paypal->l('Limit the image to 190 pixels wide by 60 pixels high.'));
                    return;
                }
                if (!($tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS')) ||
                    !move_uploaded_file($_FILES['config_logo']['tmp_name'], $tmpName)) {
                    $paypal->errors .= $paypal->displayError($paypal->l('An error occurred while copying the image.'));
                }
                if (!ImageManager::resize($tmpName, _PS_MODULE_DIR_.'paypal/views/img/p_logo_'.Context::getContext()->shop->id.'.png')) {
                    $paypal->errors .= $paypal->displayError($paypal->l('An error occurred while copying the image.'));
                }
                Configuration::updateValue('PAYPAL_CONFIG_LOGO', _PS_MODULE_DIR_.'paypal/views/img/p_logo_'.Context::getContext()->shop->id.'.png');
            }
        }

        if (Tools::getValue('deleteLogoPp')) {
            unlink(Configuration::get('PAYPAL_CONFIG_LOGO'));
            Configuration::updateValue('PAYPAL_CONFIG_LOGO', '');
        }

        $country_default = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (!in_array($country_default, $paypal->bt_countries)) {
            if (Tools::isSubmit('paypal_config')) {
                Configuration::updateValue('PAYPAL_API_CARD', $params['paypal_card']);
            }
        }

        if (Tools::isSubmit('save_rounding_settings')) {
            Configuration::updateValue('PAYPAL_SANDBOX', 0);
            Configuration::updateValue('PS_ROUND_TYPE', Order::ROUND_ITEM);
            Tools::redirect($paypal->module_link);
        }

        if (isset($params['method'])) {
            Configuration::updateValue('PAYPAL_API_CARD', $params['with_card']);
            if ((isset($params['modify']) && $params['modify']) || (Configuration::get('PAYPAL_METHOD') != $params['method'])) {
                $response = $paypal->getPartnerInfo($params['method']);
                Tools::redirectLink($response);
            }
        }

        if (!Configuration::get('PAYPAL_USERNAME_'.$mode) || !Configuration::get('PAYPAL_PSWD_'.$mode)
            || !Configuration::get('PAYPAL_SIGNATURE_'.$mode)) {
            $paypal->errors .= $paypal->displayError($paypal->l('An error occurred. Please, check your credentials Paypal.'));
        }
    }

    /*
    * The SetExpressCheckout API operation initiates an Express Checkout transaction
    */
    public function init($data)
    {
        // details about payment
        $this->_paymentDetails = new PaymentDetailsType();

        // shipping address
        if (!isset($data['short_cut']) && !Context::getContext()->cart->isVirtualCart()) {
            $address = $this->_getShippingAddress();
            $this->_paymentDetails->ShipToAddress = $address;
        }

        /** The total cost of the transaction to the buyer. If shipping cost and tax charges are known, include them in this value. If not, this value should be the current subtotal of the order. If the transaction includes one or more one-time purchases, this field must be equal to the sum of the purchases. If the transaction does not include a one-time purchase such as when you set up a billing agreement for a recurring payment, set this field to 0.*/
        $this->_getPaymentDetails();

        $this->_paymentDetails->PaymentAction = Tools::ucfirst(Configuration::get('PAYPAL_API_INTENT'));
        $setECReqDetails = new SetExpressCheckoutRequestDetailsType();
        $setECReqDetails->PaymentDetails[0] = $this->_paymentDetails;
        $setECReqDetails->CancelURL = Context::getContext()->link->getPageLink('order', true);
        $setECReqDetails->ReturnURL = Context::getContext()->link->getModuleLink($this->name, 'ecValidation', array(), true);
        $setECReqDetails->NoShipping = 1;
        $setECReqDetails->AddressOverride = 1;
        $setECReqDetails->ReqConfirmShipping = 0;
        $setECReqDetails->LandingPage = ((isset($data['use_card']) && $data['use_card']) ? 'Billing' : 'Login');

        if (isset($data['short_cut'])) {
            $setECReqDetails->ReturnURL = Context::getContext()->link->getModuleLink($this->name, 'ecScOrder', array(), true);
            $setECReqDetails->NoShipping = 2;
        }

        if (Configuration::get('PAYPAL_CONFIG_BRAND')) {
            $setECReqDetails->BrandName = Configuration::get('PAYPAL_CONFIG_BRAND');
        }
        if (file_exists(Configuration::get('PAYPAL_CONFIG_LOGO'))) {
            $setECReqDetails->cppheaderimage = Context::getContext()->link->getBaseLink(Context::getContext()->shop->id, true).'modules/paypal/views/img/p_logo_'.Context::getContext()->shop->id.'.png';
        }

        // Advanced options
        $setECReqDetails->AllowNote = 0;
        $setECReqType = new SetExpressCheckoutRequestType();
        $setECReqType->SetExpressCheckoutRequestDetails = $setECReqDetails;

        $setECReq = new SetExpressCheckoutReq();
        $setECReq->SetExpressCheckoutRequest = $setECReqType;
        /*
         * 	 ## Creating service wrapper object
        Creating service wrapper object to make API call and loading
        Configuration::getAcctAndConfig() returns array that contains credential and config parameters
        */
        $paypalService = new PayPalAPIInterfaceServiceService($this->_getCredentialsInfo());
        /* wrap API method calls on the service object with a try catch */

        $payment = $paypalService->SetExpressCheckout($setECReq);

        //You are not signed up to accept payment for digitally delivered goods.
        if (isset($payment->Errors)) {
            throw new Exception('ERROR in SetExpressCheckout', $payment->Errors[0]->ErrorCode);
        }
        $this->token = $payment->Token;
        return $this->redirectToAPI('setExpressCheckout');
    }

    private function _getPaymentDetails()
    {
        $paypal = Module::getInstanceByName('paypal');
        $currency = $paypal->getPaymentCurrencyIso();
        $this->_getProductsList($currency);
        $this->_getDiscountsList($currency);
        $this->_getGiftWrapping($currency);
        $this->_getPaymentValues($currency);
    }

    private function _getProductsList($currency)
    {
        $products = Context::getContext()->cart->getProducts();
        foreach ($products as $product) {
            $itemDetails = new PaymentDetailsItemType();
            $product['product_tax'] = $this->formatPrice($product['price_wt']) - $this->formatPrice($product['price']);
            $itemAmount = new BasicAmountType($currency, $this->formatPrice($product['price']));
            if (isset($product['attributes']) && (empty($product['attributes']) === false)) {
                $product['name'] .= ' - '.$product['attributes'];
            }
            $itemDetails->Name = $product['name'];
            $itemDetails->Amount = $itemAmount;
            $itemDetails->Quantity = $product['quantity'];
            $itemDetails->Tax = new BasicAmountType($currency, $product['product_tax']);
            $this->_paymentDetails->PaymentDetailsItem[] = $itemDetails;
            $this->_itemTotalValue += $this->formatPrice($product['price']) * $product['quantity'];
            $this->_taxTotalValue += $product['product_tax'] * $product['quantity'];
        }

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



    private function _getDiscountsList($currency)
    {
        $discounts = Context::getContext()->cart->getCartRules();
        if (count($discounts) > 0) {
            foreach ($discounts as $discount) {
                if (isset($discount['description']) && !empty($discount['description'])) {
                    $discount['description'] = Tools::substr(strip_tags($discount['description']), 0, 50).'...';
                }
                $discount['value_real'] = -1 * $this->formatPrice($discount['value_real']);
                $itemDetails = new PaymentDetailsItemType();
                $itemDetails->Name = $discount['name'];
                $itemDetails->Amount = new BasicAmountType($currency, $discount['value_real']);;
                $itemDetails->Quantity = 1;
                $this->_paymentDetails->PaymentDetailsItem[] = $itemDetails;
                $this->_itemTotalValue += $discount['value_real'];
            }
        }
    }

    private function _getGiftWrapping($currency)
    {
        $wrapping_price = Context::getContext()->cart->gift ? Context::getContext()->cart->getGiftWrappingPrice() : 0;
        if ($wrapping_price > 0) {
            $wrapping_price = $this->formatPrice($wrapping_price);
            $itemDetails = new PaymentDetailsItemType();
            $itemDetails->Name = 'Gift wrapping';
            $itemDetails->Amount = new BasicAmountType($currency, $wrapping_price);;
            $itemDetails->Quantity = 1;
            $this->_paymentDetails->PaymentDetailsItem[] = $itemDetails;
            $this->_itemTotalValue += $wrapping_price;
        }
    }

    private function _getPaymentValues($currency)
    {
        $context = Context::getContext();
        $cart = $context->cart;
        $shipping_cost_wt = $cart->getTotalShippingCost();
        $shipping = $this->formatPrice($shipping_cost_wt);
        $total = $this->formatPrice($cart->getOrderTotal(true, Cart::BOTH));
        $summary = $cart->getSummaryDetails();
        $subtotal = $this->formatPrice($summary['total_products']);
        $total_tax = number_format($this->_taxTotalValue, Paypal::getDecimal(), ".", '');
        // total shipping amount
        $shippingTotal = new BasicAmountType($currency, $shipping);
        //total handling amount if any
        $handlingTotal = new BasicAmountType($currency, number_format(0, Paypal::getDecimal(), ".", ''));
        //total insurance amount if any
        $insuranceTotal = new BasicAmountType($currency, number_format(0, Paypal::getDecimal(), ".", ''));

        if ($subtotal != $this->_itemTotalValue) {
            $subtotal = $this->_itemTotalValue;
        }
        //total
        $total_cart = $shippingTotal->value + $handlingTotal->value +
            $insuranceTotal->value +
            $this->_itemTotalValue + $this->_taxTotalValue;

        if ($total != $total_cart) {
            $total = $total_cart;
        }

        $this->_paymentDetails->ItemTotal = new BasicAmountType($currency, $subtotal);
        $this->_paymentDetails->TaxTotal = new BasicAmountType($currency, $total_tax);
        $this->_paymentDetails->OrderTotal = new BasicAmountType($currency, $total);

        $this->_paymentDetails->HandlingTotal = $handlingTotal;
        $this->_paymentDetails->InsuranceTotal = $insuranceTotal;
        $this->_paymentDetails->ShippingTotal = $shippingTotal;
    }

    private function _getShippingAddress()
    {
        $context = Context::getContext();
        $cart = $context->cart;
        $customer = $context->customer;
        $id_address = (int) $cart->id_address_delivery;
        if (($id_address == 0) && ($customer)) {
            $id_address = Address::getFirstCustomerAddressId($customer->id);
        }
        $address = new Address($id_address);
        $state = '';
        if ($address->id_state) {
            $state = new State((int) $address->id_state);
        }
        $country = new Country((int) $address->id_country);
        $address_pp = new AddressType();
        $address_pp->CityName = $address->city;
        $address_pp->Name = $address->firstname.' '.$address->lastname;
        $address_pp->Street1 = $address->address1;
        $address_pp->StateOrProvince = $state ? $state->iso_code : '';
        $address_pp->PostalCode = $address->postcode;
        $address_pp->Country = $country->iso_code;
        $address_pp->Phone = (empty($address->phone)) ? $address->phone_mobile : $address->phone;
        return $address_pp;
    }

    public function redirectToAPI($method)
    {
        if ($this->useMobile()) {
            $url = '/cgi-bin/webscr?cmd=_express-checkout-mobile';
        } else {
            $url = '/websc&cmd=_express-checkout';
        }

        if (($method == 'SetExpressCheckout') && ($this->type == 'payment_cart')) {
            $url .= '&useraction=commit';
        }
        $paypal = Module::getInstanceByName('paypal');
        return $paypal->getUrl().$url.'&token='.urldecode($this->token);
    }

    public function useMobile()
    {
        if ((method_exists(Context::getContext(), 'getMobileDevice') && Context::getContext()->getMobileDevice())
            || Tools::getValue('ps_mobile_site')) {
            return true;
        }

        return false;
    }

    public function _getCredentialsInfo()
    {
        $params = array();
        switch (Configuration::get('PAYPAL_SANDBOX')) {
            case 0:
                $params['acct1.UserName'] = Configuration::get('PAYPAL_USERNAME_LIVE');
                $params['acct1.Password'] = Configuration::get('PAYPAL_PSWD_LIVE');
                $params['acct1.Signature'] = Configuration::get('PAYPAL_SIGNATURE_LIVE');
                $params['acct1.Signature'] = Configuration::get('PAYPAL_SIGNATURE_LIVE');
                $params['mode'] = Configuration::get('PAYPAL_SANDBOX') ? 'sandbox' : 'live';
                $params['log.LogEnabled'] = false;
                break;
            case 1:
                $params['acct1.UserName'] = Configuration::get('PAYPAL_USERNAME_SANDBOX');
                $params['acct1.Password'] = Configuration::get('PAYPAL_PSWD_SANDBOX');
                $params['acct1.Signature'] = Configuration::get('PAYPAL_SIGNATURE_SANDBOX');
                $params['mode'] = Configuration::get('PAYPAL_SANDBOX') ? 'sandbox' : 'live';
                $params['log.LogEnabled'] = false;
                break;
        }
        return $params;
    }

    public function validation()
    {
        $context = Context::getContext();

        $this->_paymentDetails = new PaymentDetailsType();

        if (!Context::getContext()->cart->isVirtualCart()) {
            $address = $this->_getShippingAddress();
            $this->_paymentDetails->ShipToAddress = $address;
        }

        $this->_getPaymentDetails();

        $DoECRequestDetails = new DoExpressCheckoutPaymentRequestDetailsType();
        $DoECRequestDetails->PayerID = Tools::getValue('shortcut') ? $context->cookie->paypal_ecs_payerid : Tools::getValue('PayerID');
        $DoECRequestDetails->Token = Tools::getValue('shortcut') ? $context->cookie->paypal_ecs : Tools::getValue('token');
        $DoECRequestDetails->ButtonSource = 'PrestaShop_Cart_'.(getenv('PLATEFORM') == 'PSREADY' ? 'Ready_':'').'EC';
        $DoECRequestDetails->PaymentAction = Tools::ucfirst(Configuration::get('PAYPAL_API_INTENT'));;
        $DoECRequestDetails->PaymentDetails[0] = $this->_paymentDetails;

        $DoECRequest = new DoExpressCheckoutPaymentRequestType();
        $DoECRequest->DoExpressCheckoutPaymentRequestDetails = $DoECRequestDetails;
        $DoECReq = new DoExpressCheckoutPaymentReq();
        $DoECReq->DoExpressCheckoutPaymentRequest = $DoECRequest;

        $paypalService = new PayPalAPIInterfaceServiceService($this->_getCredentialsInfo());

        $exec_payment = $paypalService->DoExpressCheckoutPayment($DoECReq);

        if (isset($exec_payment->Errors)) {
            throw new Exception('ERROR in SetExpressCheckout', $exec_payment->Errors[0]->ErrorCode);
        }

        $cart = $context->cart;
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order');
        }
        $currency = $context->currency;
        $payment_info = $exec_payment->DoExpressCheckoutPaymentResponseDetails->PaymentInfo[0];

        $total = $payment_info->GrossAmount->value;
        $paypal = Module::getInstanceByName('paypal');
        if (Configuration::get('PAYPAL_API_INTENT') == "sale") {
            $order_state = Configuration::get('PS_OS_PAYMENT');
        } else {
            $order_state = Configuration::get('PAYPAL_OS_WAITING');
        }
        $transactionDetail = $this->getDetailsTransaction($exec_payment->DoExpressCheckoutPaymentResponseDetails);
        $paypal->validateOrder($cart->id, $order_state, $total, 'PayPal', null, $transactionDetail, (int)$currency->id, false, $customer->secure_key);
        return true;
    }

    public function getDetailsTransaction($transaction)
    {
        $payment_info = $transaction->PaymentInfo[0];
        return array(
            'method' => 'EC',
            'currency' => $payment_info->GrossAmount->currencyID,
            'transaction_id' => pSQL($payment_info->TransactionID),
            'payment_status' => $payment_info->PaymentStatus,
            'payment_method' => $payment_info->PaymentType,
            'id_payment' => pSQL($transaction->Token),
            'client_token' => "",
            'capture' =>$payment_info->PaymentStatus == "Pending" && $payment_info->PendingReason == "authorization" ? true : false,
        );
    }


    public function confirmCapture()
    {
        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
        $id_paypal_order = $paypal_order->id;
        $currency = $paypal_order->currency;
        $amount = $paypal_order->total_paid;
        $doCaptureRequestType = new DoCaptureRequestType();
        $doCaptureRequestType->AuthorizationID = $paypal_order->id_transaction;
        $doCaptureRequestType->Amount = new BasicAmountType($currency, number_format($amount, Paypal::getDecimal(), ".", ''));
        $doCaptureRequestType->CompleteType = 'Complete';
        $doCaptureReq = new DoCaptureReq();
        $doCaptureReq->DoCaptureRequest = $doCaptureRequestType;

        $paypalService = new PayPalAPIInterfaceServiceService($this->_getCredentialsInfo());
        $response = $paypalService->DoCapture($doCaptureReq);

        if ($response instanceof PayPal\PayPalAPI\DoCaptureResponseType) {
            $authorization_id = $response->DoCaptureResponseDetails->AuthorizationID;
            if (isset($response->Errors)) {
                $result = array(
                    'authorization_id' => $authorization_id,
                    'status' => $response->Ack,
                    'error_code' => $response->Errors[0]->ErrorCode,
                    'error_message' => $response->Errors[0]->LongMessage,
                );
                if ($response->Errors[0]->ErrorCode == "10602") {
                    $result['already_captured'] = true;
                }
            } else {
                $payment_info = $response->DoCaptureResponseDetails->PaymentInfo;
                PaypalCapture::updateCapture($payment_info->TransactionID, $payment_info->GrossAmount->value, $payment_info->PaymentStatus, $id_paypal_order);
                $result =  array(
                    'success' => true,
                    'authorization_id' => $payment_info->TransactionID,
                    'status' => $payment_info->PaymentStatus,
                    'amount' => $payment_info->GrossAmount->value,
                    'currency' => $payment_info->GrossAmount->currencyID,
                    'parent_payment' => $payment_info->ParentTransactionID,
                    'pending_reason' => $payment_info->PendingReason,
                );
            }
        }

        return $result;
    }

    public function check()
    {
    }

    public function refund()
    {
        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
        $id_paypal_order = $paypal_order->id;
        $capture = PaypalCapture::loadByOrderPayPalId($id_paypal_order);

        $id_transaction = Validate::isLoadedObject($capture) ? $capture->id_capture : $paypal_order->id_transaction;

        $refundTransactionReqType = new RefundTransactionRequestType();
        $refundTransactionReqType->TransactionID = $id_transaction;
        $refundTransactionReqType->RefundType = 'Full';
        $refundTransactionReq = new RefundTransactionReq();
        $refundTransactionReq->RefundTransactionRequest = $refundTransactionReqType;

        $paypalService = new PayPalAPIInterfaceServiceService($this->_getCredentialsInfo());
        $response = $paypalService->RefundTransaction($refundTransactionReq);

        if ($response instanceof PayPal\PayPalAPI\RefundTransactionResponseType) {
            if (isset($response->Errors)) {
                $result = array(
                    'status' => $response->Ack,
                    'error_code' => $response->Errors[0]->ErrorCode,
                    'error_message' => $response->Errors[0]->LongMessage,
                );
                if (Validate::isLoadedObject($capture) && $response->Errors[0]->ErrorCode == "10009") {
                    $result['already_refunded'] = true;
                }
            } else {
                $result =  array(
                    'success' => true,
                    'refund_id' => $response->RefundTransactionID,
                    'status' => $response->Ack,
                    'total_amount' => $response->TotalRefundedAmount->value,
                    'net_amount' => $response->NetRefundAmount->value,
                    'currency' => $response->TotalRefundedAmount->currencyID,
                );
            }
        }

        return $result;
    }

    public function partialRefund($params)
    {
        $paypal_order = PaypalOrder::loadByOrderId($params['order']->id);
        $id_paypal_order = $paypal_order->id;
        $capture = PaypalCapture::loadByOrderPayPalId($id_paypal_order);
        $id_transaction = Validate::isLoadedObject($capture) ? $capture->id_capture : $paypal_order->id_transaction;
        $currency = $paypal_order->currency;
        $amount = 0;
        foreach ($params['productList'] as $product) {
            $amount += $product['amount'];
        }
        if (Tools::getValue('partialRefundShippingCost')) {
            $amount += Tools::getValue('partialRefundShippingCost');
        }
        $refundTransactionReqType = new RefundTransactionRequestType();
        $refundTransactionReqType->TransactionID = $id_transaction;
        $refundTransactionReqType->RefundType = 'Partial';
        $refundTransactionReqType->Amount =  new BasicAmountType($currency, number_format($amount, Paypal::getDecimal(), ".", ''));
        $refundTransactionReq = new RefundTransactionReq();
        $refundTransactionReq->RefundTransactionRequest = $refundTransactionReqType;

        $paypalService = new PayPalAPIInterfaceServiceService($this->_getCredentialsInfo());
        $response = $paypalService->RefundTransaction($refundTransactionReq);

        if ($response instanceof PayPal\PayPalAPI\RefundTransactionResponseType) {
            if (isset($response->Errors)) {
                $result = array(
                    'status' => $response->Ack,
                    'error_code' => $response->Errors[0]->ErrorCode,
                    'error_message' => $response->Errors[0]->LongMessage,
                );
                if (Validate::isLoadedObject($capture) && $response->Errors[0]->ErrorCode == "10009") {
                    $result['already_refunded'] = true;
                }
            } else {
                $result =  array(
                    'success' => true,
                    'refund_id' => $response->RefundTransactionID,
                    'status' => $response->Ack,
                    'total_amount' => $response->TotalRefundedAmount->value,
                    'net_amount' => $response->NetRefundAmount->value,
                    'currency' => $response->TotalRefundedAmount->currencyID,
                );
            }
        }

        return $result;
    }

    public function void($authorization)
    {
        $doVoidReqType = new DoVoidRequestType();
        $doVoidReqType->AuthorizationID = $authorization;
        $doVoidReq = new DoVoidReq();
        $doVoidReq->DoVoidRequest = $doVoidReqType;

        $paypalService = new PayPalAPIInterfaceServiceService($this->_getCredentialsInfo());
        $response = $paypalService->DoVoid($doVoidReq);

        if ($response instanceof PayPal\PayPalAPI\DoVoidResponseType) {
            if (isset($response->Errors)) {
                $response =  array(
                    'error_code' => $response->Errors[0]->ErrorCode,
                    'error_message' => $response->Errors[0]->LongMessage,
                );
            } else {
                $response =  array(
                    'authorization_id' => $response->AuthorizationID,
                    'status' => $response->Ack,
                    'success' => true,
                );
            }
        }
        return $response;
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
            'PayPal_tracking_code' => 'PRESTASHOP_ECM',
            'PayPal_img_esc' => $shop_url.$img_esc,
            'action_url' => $context->link->getModuleLink('paypal', 'ScInit', array(), true),
            'ec_sc_in_context' => Configuration::get('PAYPAL_EXPRESS_CHECKOUT_IN_CONTEXT'),
            'merchant_id' => Configuration::get('PAYPAL_MERCHANT_ID_'.Tools::strtoupper($environment)),
            'environment' => $environment,
        ));

        if ($page_source == 'product') {
            $context->smarty->assign(array(
                'es_cs_product_attribute' => Tools::getValue('id_product_attribute'),
            ));
            return $context->smarty->fetch('module:paypal/views/templates/hook/EC_shortcut.tpl');
        } elseif ($page_source == 'cart') {
            return $context->smarty->fetch('module:paypal/views/templates/hook/cart_shortcut.tpl');
        }
    }

    public function processCheckoutSc($response)
    {
        if (!isset($response['L_ERRORCODE0'])) {
            if (Tools::getvalue('getToken')) {
                die($this->token);
            }
            Tools::redirect($response);
        } else {
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_code' => $response['L_ERRORCODE0'])));
        }
    }

    public function getInfo($params)
    {
        $getExpressCheckoutDetailsRequest = new GetExpressCheckoutDetailsRequestType($params['token']);
        $getExpressCheckoutReq = new GetExpressCheckoutDetailsReq();
        $getExpressCheckoutReq->GetExpressCheckoutDetailsRequest = $getExpressCheckoutDetailsRequest;
        $paypalService = new PayPalAPIInterfaceServiceService($this->_getCredentialsInfo());
        $response = $paypalService->GetExpressCheckoutDetails($getExpressCheckoutReq);
        if (isset($response->Errors)) {
            throw new Exception('ERROR in SetExpressCheckout', $response->Errors[0]->ErrorCode);
        }
        return $response;
    }
}
