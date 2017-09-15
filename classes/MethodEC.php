<?php
/**
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
 */

include_once(_PS_MODULE_DIR_.'paypal/sdk/PaypalSDK.php');

class MethodEC extends AbstractMethodPaypal
{
    public $name = 'paypal';

    public $token;

    public function getConfig(PayPal $module)
    {
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
            array(
                'type' => 'switch',
                'label' => $module->l('Enabled Shortcut'),
                'name' => 'paypal_show_shortcut',
                'desc' => $module->l(''),
                'is_bool' => true,
                'hint' => $module->l('Express Checkout Shortcut involves placing the Check Out with PayPal button on your product and shopping cart pages. This commences the PayPal payment earlier in the checkout flow, allowing buyers to complete a purchase without manually entering information that can be obtained from PayPal.'),
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
            )
        ));

        $params['fields_value'] = array(
            'paypal_intent' => Configuration::get('PAYPAL_API_INTENT'),
            'paypal_show_advantage' => Configuration::get('PAYPAL_API_ADVANTAGES'),
            'paypal_show_shortcut' => Configuration::get('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT'),
        );

        $country_default = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if ($country_default != "FR" && $country_default != "UK" && $country_default != "IT" && $country_default != "ES") {
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
            'need_rounding' => Configuration::get('PS_ROUND_TYPE') == Order::ROUND_ITEM ? 0 : 1,
            'ec_active' => Configuration::get('PAYPAL_EXPRESS_CHECKOUT'),
        ));

        if (Configuration::get('PS_ROUND_TYPE') != Order::ROUND_ITEM) {
            $params['block_info'] = $module->display(_PS_MODULE_DIR_.$module->name, 'views/templates/admin/block_info.tpl');
        }

        $params['form'] = $this->getApiUserName($module);

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
        $helper->name_controller = $module->name;
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
        }
        if (Tools::isSubmit('paypal_config')) {
            Configuration::updateValue('PAYPAL_API_INTENT', $params['paypal_intent']);
            Configuration::updateValue('PAYPAL_API_ADVANTAGES', $params['paypal_show_advantage']);
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT', $params['paypal_show_shortcut']);
        }

        $country_default = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if ($country_default != "FR" && $country_default != "UK" && $country_default != "IT" && $country_default != "ES") {
            if (Tools::isSubmit('paypal_config')) {
                Configuration::updateValue('PAYPAL_API_CARD', $params['paypal_card']);
            }
        }

        if (Tools::isSubmit('save_rounding_settings')) {
            Configuration::updateValue('PAYPAL_SANDBOX', 0);
            Configuration::updateValue('PS_ROUND_TYPE', Order::ROUND_ITEM);
            Tools::redirect($this->module_link);
        }

        if (isset($params['method'])) {
            Configuration::updateValue('PAYPAL_API_CARD', $params['with_card']);
            if ((isset($params['modify']) && $params['modify']) || (Configuration::get('PAYPAL_METHOD') != $params['method'])) {
                $response = $paypal->getPartnerInfo($params['method']);
                $result = Tools::jsonDecode($response);
                if (!$result->error && isset($result->data->url)) {
                    $PartnerboardingURL = $result->data->url;
                    Tools::redirectLink($PartnerboardingURL);
                } else {
                    $paypal->errors .= $paypal->displayError($paypal->l('Error onboarding Paypal : ').$result->error);
                }
            }
        }

        if (!Configuration::get('PAYPAL_USERNAME_'.$mode) || !Configuration::get('PAYPAL_PSWD_'.$mode)
            || !Configuration::get('PAYPAL_SIGNATURE_'.$mode)) {
            $paypal->errors .= $paypal->displayError($paypal->l('An error occurred. Please, check your credentials Paypal.'));
        }
    }

    public function init($data)
    {
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        $params = array(
            'cancel_url' => Context::getContext()->link->getPageLink('order', true).'&step=1',
            'landing_page' => $data['use_card'] ? 'Billing' : 'Login',
            'return_url' => Context::getContext()->link->getModuleLink($this->name, 'ecValidation', array(), true),
            'no_shipping' => 1,
            'solution_type' => 'Sole',
            'addr_override' => '0',
        );
        if (isset($data['short_cut'])) {
            $params['landing_page'] = 'Login';
            $params['return_url'] = Context::getContext()->link->getModuleLink($this->name, 'ecScOrder', array(), true);
            $params['no_shipping'] = 2;
        }

        $this->_getCredentialsInfo($params);
        $params = $this->_getPaymentDetails($params);

        $payment = $sdk->setExpressCheckout($params);

        $return = false;
        if (isset($payment['TOKEN'])) {
            $this->token = $payment['TOKEN'];
            $return = $this->redirectToAPI($payment['TOKEN'], 'setExpressCheckout');
        } elseif (isset($payment['L_ERRORCODE0'])) {
            $return = $payment;
        }
        return $return;
    }

    private function _getPaymentDetails($params)
    {
        $tax = $total_products = 0;

        $params['currency'] = Context::getContext()->currency->iso_code;
        $params['payment_action'] = Configuration::get('PAYPAL_API_INTENT');

        $this->_getProductsList($params, $total_products, $tax);
        $this->_getDiscountsList($params, $total_products);
        $this->_getGiftWrapping($params, $total_products);
        $this->_getPaymentValues($params, $total_products, $tax);
        if (!isset($params['short_cut'])) {
            $this->_getShippingAddress($params);
        }


        return $params;
    }

    private function _getProductsList(&$params, &$total_products, &$tax)
    {
        $products = Context::getContext()->cart->getProducts();
        foreach ($products as $product) {
            if (isset($product['attributes']) && (empty($product['attributes']) === false)) {
                $product['name'] .= ' - '.$product['attributes'];
            }
            $product['description_short'] = Tools::substr(strip_tags($product['description_short']), 0, 50).'...';
            $product['price'] = number_format($product['price'], 2, ".", '');
            $product['product_tax'] = number_format(($product['price_wt'] - $product['price']), 2, ".", '');
            $total_products += (number_format($product['price'], 2, ".", '') * $product['quantity']);
            $tax += (number_format($product['price_wt'] - $product['price'], 2, ".", '') * $product['quantity']);
            $params['products_list']['products'][] = $product;
        }
    }

    private function _getDiscountsList(&$params, &$total_products)
    {
        $discounts = Context::getContext()->cart->getCartRules();
        $params['products_list']['discounts'] = array();
        if (count($discounts) > 0) {
            foreach ($discounts as $discount) {
                if (isset($discount['description']) && !empty($discount['description'])) {
                    $discount['description'] = Tools::substr(strip_tags($discount['description']), 0, 50).'...';
                }
                /* It is a discount so we store a negative value */
                $discount['value_real'] = -1 * number_format($discount['value_real'], 2, ".", '');
                $discount['quantity'] = 1;
                $total_products = round($total_products + $discount['value_real'], 2);
                $params['products_list']['discounts'][] = $discount;
            }
        }
    }

    private function _getGiftWrapping(&$params, &$total_products)
    {
        $wrapping_price = Context::getContext()->cart->gift ? Context::getContext()->cart->getGiftWrappingPrice() : 0;
        $wrapping = array();
        if ($wrapping_price > 0) {
            $wrapping['name'] = 'Gift wrapping';
            $wrapping['amount'] = number_format($wrapping_price, 2, ".", '');
            $wrapping['quantity'] = 1;
            $total_products = round($total_products + $wrapping_price, 2);
        }
        $params['products_list']['wrapping'] = $wrapping;
    }

    private function _getPaymentValues(&$params, &$total_products, &$tax)
    {
        $context = Context::getContext();
        $cart = $context->cart;
        $shipping_cost_wt = $cart->getTotalShippingCost();
        $shipping = round($shipping_cost_wt, 2);
        $total = $cart->getOrderTotal(true, Cart::BOTH);
        $summary = $cart->getSummaryDetails();
        $subtotal = Tools::ps_round($summary['total_products'], 2);
        $total_tax = round($tax, 2);
        if ($subtotal != $total_products) {
            $subtotal = $total_products;
        }
        $total_cart = $total_products + $shipping + $tax;
        if ($total != $total_cart) {
            $total = $total_cart;
        }
        $params['costs'] = array(
            'shipping_cost' => number_format($shipping, 2, ".", ''),
            'total' => number_format($total, 2, ".", ''),
            'subtotal' => number_format($subtotal, 2, ".", ''),
            'carrier' => new Carrier($cart->id_carrier),
            'total_tax' => $total_tax,
        );
    }

    private function _getShippingAddress(&$params)
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
        $params['shipping'] = array(
            'address' => $address,
            'ship_name' => $address->firstname.' '.$address->lastname,
            'phone' => (empty($address->phone)) ? $address->phone_mobile : $address->phone,
            'email' => $customer->email,
            'state' => $state ? $state->iso_code : '',
            'country' => $country->iso_code,
        );
    }

    public function redirectToAPI($token, $method)
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
        return $paypal->getUrl().$url.'&token='.urldecode($token);
    }

    public function useMobile()
    {
        if ((method_exists(Context::getContext(), 'getMobileDevice') && Context::getContext()->getMobileDevice())
            || Tools::getValue('ps_mobile_site')) {
            return true;
        }

        return false;
    }

    public function _getCredentialsInfo(&$params)
    {
        switch (Configuration::get('PAYPAL_SANDBOX')) {
            case 0:
                $params['user'] = Configuration::get('PAYPAL_USERNAME_LIVE');
                $params['pwd'] = Configuration::get('PAYPAL_PSWD_LIVE');
                $params['signature'] = Configuration::get('PAYPAL_SIGNATURE_LIVE');
                break;
            case 1:
                $params['user'] = Configuration::get('PAYPAL_USERNAME_SANDBOX');
                $params['pwd'] = Configuration::get('PAYPAL_PSWD_SANDBOX');
                $params['signature'] = Configuration::get('PAYPAL_SIGNATURE_SANDBOX');
                break;
        }
    }

    public function validation()
    {
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        $context = Context::getContext();
        $params = array(
            'token' => Tools::getValue('shortcut') ? $context->cookie->paypal_ecs : Tools::getValue('token'),
            'payer_id' => Tools::getValue('shortcut') ? $context->cookie->paypal_ecs_payerid : Tools::getValue('PayerID'),
            'button_source' => (defined('PLATEFORM') && PLATEFORM == 'PSREAD')?'PrestaShop_Cart_Presto':'PrestaShop_Cart_EC',
        );
        $this->_getCredentialsInfo($params);
        $params = $this->_getPaymentDetails($params);
        $exec_payment = $sdk->doExpressCheckout($params);

        if (isset($exec_payment['L_ERRORCODE0'])) {
            Tools::redirect($context->link->getModuleLink('paypal', 'error', array('error_code' => $exec_payment['L_ERRORCODE0'])));
        }

        $cart = $context->cart;
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        $currency = $context->currency;
        $total = (float)$exec_payment['PAYMENTINFO_0_AMT'];
        $paypal = Module::getInstanceByName('paypal');
        if (Configuration::get('PAYPAL_API_INTENT') == "sale") {
            $order_state = Configuration::get('PS_OS_PAYMENT');
        } else {
            $order_state = Configuration::get('PAYPAL_OS_WAITING');
        }
        $transactionDetail = $this->getDetailsTransaction($exec_payment);
        $paypal->validateOrder($cart->id, $order_state, $total, 'PayPal', null, $transactionDetail, (int)$currency->id, false, $customer->secure_key);
    }

    public function getDetailsTransaction($transaction)
    {
        return array(
            'method' => 'EC',
            'currency' => $transaction['PAYMENTINFO_0_CURRENCYCODE'],
            'transaction_id' => pSQL($transaction['PAYMENTINFO_0_TRANSACTIONID']),
            'payment_status' => $transaction['PAYMENTINFO_0_PAYMENTSTATUS'],
            'payment_method' => $transaction['PAYMENTINFO_0_PAYMENTTYPE'],
            'id_payment' => $transaction['TOKEN'],
            'client_token' => "",
            'capture' => $transaction['PAYMENTINFO_0_PAYMENTSTATUS'] == "Pending" && $transaction['PAYMENTINFO_0_PENDINGREASON'] == "authorization" ? true : false,
        );
    }


    public function confirmCapture()
    {
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));

        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
        $id_paypal_order = $paypal_order->id;
        $params = array();
        $params['amount'] = number_format($paypal_order->total_paid, 2, ".", '');
        $params['authorization_id'] = $paypal_order->id_transaction;
        $params['currency_code'] = $paypal_order->currency;
        $params['complete_type'] = 'complete';
        $this->_getCredentialsInfo($params);

        $response = $sdk->doCapture($params);

        if ($response['ACK'] == "Success") {
            PaypalCapture::updateCapture($response['TRANSACTIONID'], $response['AMT'], $response['PAYMENTSTATUS'], $id_paypal_order);
            $result =  array(
                'success' => true,
                'authorization_id' => $response['AUTHORIZATIONID'],
                'status' => $response['PAYMENTSTATUS'],
                'amount' => $response['AMT'],
                'transaction_id' => $response['TRANSACTIONID'],
                'currency' => $response['CURRENCYCODE'],
                'parent_payment' => $response['PARENTTRANSACTIONID'],
                'pending_reason' => $response['PENDINGREASON'],
            );
        } else {
            $result = array(
                'authorization_id' => $response['AUTHORIZATIONID'],
                'status' => $response['ACK'],
                'error_code' => $response['L_ERRORCODE0'],
                'error_message' => $response['L_LONGMESSAGE0'],
            );
            if ($response['L_ERRORCODE0'] == "10602") {
                $result['already_captured'] = true;
            }
        }

        return $result;
    }

    public function check()
    {
    }

    public function refund()
    {
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
        $id_paypal_order = $paypal_order->id;
        $capture = PaypalCapture::loadByOrderPayPalId($id_paypal_order);

        $id_transaction = Validate::isLoadedObject($capture) ? $capture->id_capture : $paypal_order->id_transaction;

        $params = array();
        $this->_getCredentialsInfo($params);
        $params['transaction_id'] = $id_transaction;
        $params['refund_type'] = 'Full';
        $response = $sdk->refundTransaction($params);

        if ($response['ACK'] == "Success") {
            $result =  array(
                'success' => true,
                'refund_id' => $response['REFUNDTRANSACTIONID'],
                'status' => $response['ACK'],
                'total_amount' => $response['TOTALREFUNDEDAMOUNT'],
                'net_amount' => $response['NETREFUNDAMT'],
                'currency' => $response['CURRENCYCODE'],
            );
        } else {
            $result = array(
                'status' => $response['ACK'],
                'error_code' => $response['L_ERRORCODE0'],
                'error_message' => $response['L_LONGMESSAGE0'],
            );
            if ($response['L_ERRORCODE0'] == "10009") {
                $result['already_refunded'] = true;
            }
        }

        return $result;
    }

    public function void($authorization)
    {
        $this->_getCredentialsInfo($authorization);
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        $result = $sdk->doVoid($authorization);
        if ($result['ACK'] == "Success") {
            $response =  array(
                'authorization_id' => $result['AUTHORIZATIONID'],
                'status' => $result['ACK'],
                'success' => true,
            );
        } else {
            $response =  array(
                'error_code' => $result['L_ERRORCODE0'],
                'error_message' => $result['L_LONGMESSAGE0'],
            );
        }
        return $response;
    }

    public function renderExpressCheckout(&$context, $type)
    {
        if (!Configuration::get('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT')) {
            return false;
        }

        $lang = $context->country->iso_code;

        $img_esc = "/modules/paypal/views/img/ECShortcut/".strtolower($lang)."/buy/buy.png";

        if (!file_exists(_PS_ROOT_DIR_.$img_esc)) {
            $img_esc = "/modules/paypal/views/img/ECShortcut/us/buy/buy.png";
        }
        $context->smarty->assign(array(
            'PayPal_payment_type' => $type,
            'PayPal_tracking_code' => 'PRESTASHOP_ECM',
            'PayPal_img_esc' => $img_esc,
            'action_url' => $context->link->getModuleLink('paypal', 'ecScInit', array(), true)
        ));
        $context->controller->registerJavascript($this->name.'-order_confirmation_js', 'modules/paypal/views/js/ec_shortcut.js');

        return $context->smarty->fetch('module:paypal/views/templates/hook/EC_shortcut.tpl');
    }

    public function getInfo($params)
    {
        $this->_getCredentialsInfo($params);

        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        return $sdk->getExpressCheckout($params);
    }
}
