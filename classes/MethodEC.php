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
        $params = array(
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
                'name' => 'paypal_show_advantage',
                'desc' => $module->l(''),
                'is_bool' => true,
                'hint' => $module->l(''),
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
            )
        );

        if (Configuration::get('PAYPAL_LIVE_ACCESS') || Configuration::get('PAYPAL_SANDBOX_ACCESS')) {
            $ec_card_active = Configuration::get('PAYPAL_API_CARD');
            $ec_paypal_active = !Configuration::get('PAYPAL_API_CARD');
        } else {
            $ec_card_active = false;
            $ec_paypal_active = false;
        }
        $context = Context::getContext();

        $context->smarty->assign(array(
            //'path_ajax_sandbox' => $context->link->getAdminLink('AdminModules',true,array(),array('configure'=>'paypal')),
            'country' => Country::getNameById($context->language->id, $context->country->id),
            'localization' => $context->link->getAdminLink('AdminLocalization', true),
            'preference' => $context->link->getAdminLink('AdminPreferences', true),
            'access_token_sandbox' => Configuration::get('PAYPAL_SANDBOX_ACCESS'),
            'access_token_live' => Configuration::get('PAYPAL_LIVE_ACCESS'),
            'paypal_card' => Configuration::get('PAYPAL_API_CARD'),
            'ec_card_active' => $ec_card_active,
            'ec_paypal_active' => $ec_paypal_active,
        ));

        return $params;
    }

    public function setConfig($params)
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';

        if (isset($params['api_username']) && isset($params['api_password']) && isset($params['api_signature'])) {
            Configuration::updateValue('PAYPAL_USERNAME_'.$mode, $params['api_username']);
            Configuration::updateValue('PAYPAL_PSWD_'.$mode, $params['api_password']);
            Configuration::updateValue('PAYPAL_SIGNATURE_'.$mode, $params['api_signature']);
            Configuration::updateValue('PAYPAL_'.$mode.'_ACCESS', 1);
        }
        Configuration::updateValue('PAYPAL_'.$mode.'_ACCESS', 1);

    }

    public function init($data)
    {
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));

        $params = array(
            'CANCELURL' => Context::getContext()->link->getPageLink('order', true).'&step=1',
            'LANDINGPAGE' => Tools::getValue('credit_card') ? 'Billing' : 'Login',
            'RETURNURL' => Context::getContext()->link->getModuleLink($this->name, 'ecValidation', array(), true),
        );

        $this->_getCredentialsInfo($params);

        $this->_getPaymentInfo($params);
        $params = $this->_setPaymentDetails($params);


        if(isset($data['short_cut']))
        {
            $params['LANDINGPAGE'] = 'Login';
            $params['RETURNURL'] = Context::getContext()->link->getModuleLink($this->name, 'ecScOrder', array(), true);
        }

        if(isset($data['short_cut']))
        {
            $params['NOSHIPPING'] = 2;
        }

        $payment = $sdk->setExpressCheckout($params);
       /* echo '<pre>';
        print_r($payment);
        echo '<pre>';
        die;*/
        $return = false;
        if (isset($payment['TOKEN'])) {
            $this->token = $payment['TOKEN'];
            $return = $this->redirectToAPI($payment['TOKEN'], 'setExpressCheckout');
        } elseif(isset($payment['L_ERRORCODE0'])) {
            $return = $payment;
        }
        return $return;
    }

    private function _setPaymentDetails($params)
    {
        // Products
        $tax = $total_products = 0;
        $index = -1;
        $fields = array();
        $fields['USER'] =  $params['USER'];
        $fields['PWD'] = $params['PWD'];
        $fields['SIGNATURE'] = $params['SIGNATURE'];
        $fields['CANCELURL'] = $params['CANCELURL'];
        $fields['LANDINGPAGE'] = $params['LANDINGPAGE'];
        $fields['RETURNURL'] = $params['RETURNURL'];

        // Set cart products list
        $this->setProductsList($fields, $params['PAYMENT_LIST']['PRODUCTS'], $index, $total_products, $tax);
        $this->setDiscountsList($fields, $params['PAYMENT_LIST']['DISCOUNTS'], $index, $total_products, $tax);
        $this->setGiftWrapping($fields, $params['PAYMENT_LIST']['WRAPPING'], $index, $total_products, $tax);

        // Payment values
        $fields['PAYMENTREQUEST_0_PAYMENTACTION'] = $params['PAYMENTREQUEST_0_PAYMENTACTION'];
        $fields['PAYMENTREQUEST_0_CURRENCYCODE'] = $params['CURRENCY'];
        $this->setPaymentValues($fields, $params['COSTS'], $index, $total_products, $tax);


        if(!isset($params['short_cut']))
        {
            // Set address information
            $this->_setShippingAddress($fields, $params['SHIPPING']);
        }

        foreach ($params as &$field) {
            if (is_numeric($field)) {
                $field = number_format($field, 2, ".", ",");
            }
        }
        $fields['METHOD'] = 'SetExpressCheckout';
        $fields['SOLUTIONTYPE'] = 'Sole';
        return $fields;
    }

    private function setProductsList(&$fields, $products, &$index, &$total_products, &$tax)
    {
        foreach ($products as $product) {
            $fields['L_PAYMENTREQUEST_0_NUMBER'.++$index] = (int) $product['id_product'];

            $fields['L_PAYMENTREQUEST_0_NAME'.$index] = $product['name'];

            if (isset($product['attributes']) && (empty($product['attributes']) === false)) {
                $fields['L_PAYMENTREQUEST_0_NAME'.$index] .= ' - '.$product['attributes'];
            }

            $fields['L_PAYMENTREQUEST_0_DESC'.$index] = substr(strip_tags($product['description_short']), 0, 50).'...';

            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = number_format($product['price'], 2);
            $fields['L_PAYMENTREQUEST_0_TAXAMT'.$index] = number_format($product['price_wt'] - $product['price'], 2);
            $fields['L_PAYMENTREQUEST_0_QTY'.$index] = $product['quantity'];

            $total_products = $total_products + ($fields['L_PAYMENTREQUEST_0_AMT'.$index] * $product['quantity']);
            $tax = $tax + ($fields['L_PAYMENTREQUEST_0_TAXAMT'.$index] * $product['quantity']);
        }
    }

    private function setDiscountsList(&$fields, $discounts, &$index, &$total_products)
    {
        if (count($discounts) > 0) {
            foreach ($discounts as $discount) {
                $fields['L_PAYMENTREQUEST_0_NUMBER'.++$index] = $discount['id_discount'];
                $fields['L_PAYMENTREQUEST_0_NAME'.$index] = $discount['name'];
                if (isset($discount['description']) && !empty($discount['description'])) {
                    $fields['L_PAYMENTREQUEST_0_DESC'.$index] = substr(strip_tags($discount['description']), 0, 50).'...';
                }

                /* It is a discount so we store a negative value */
                $fields['L_PAYMENTREQUEST_0_AMT'.$index] = -1 * number_format($discount['value_real'], 2);
                $fields['L_PAYMENTREQUEST_0_QTY'.$index] = 1;

                $total_products = round($total_products + $fields['L_PAYMENTREQUEST_0_AMT'.$index], 2);
            }
        }
    }

    private function setGiftWrapping(&$fields, $wrapping, &$index, &$total_products)
    {
        if ($wrapping > 0) {
            $fields['L_PAYMENTREQUEST_0_NAME'.++$index] = 'Gift wrapping';
            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = number_format($wrapping, 2);
            $fields['L_PAYMENTREQUEST_0_QTY'.$index] = 1;
            $total_products = round($total_products + $wrapping, 2);
        }
    }

    private function setPaymentValues(&$fields, $costs, &$index, &$total_products, &$tax)
    {
        $subtotal = $costs['SUBTOTAL'];
        $total = $costs['TOTAL'];
        $total_tax = round($tax, 2);

        if ($subtotal != $total_products) {
            $subtotal = $total_products;
        }
        $shipping = round($costs['SHIPPING_COST'], 2);
        $total_cart = $total_products + $shipping + $tax;

        if ($total != $total_cart) {
            $total = $total_cart;
        }

        /**
         * If the total amount is lower than 1 we put the shipping cost as an item
         * so the payment could be valid.
         */
        if ($total <= 1) {
            $fields['L_PAYMENTREQUEST_0_NUMBER'.++$index] = $costs['CARRIER']->id_reference;
            $fields['L_PAYMENTREQUEST_0_NAME'.$index] = $costs['CARRIER']->name;
            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = number_format($shipping, 2);
            $fields['L_PAYMENTREQUEST_0_QTY'.$index] = 1;
            $fields['PAYMENTREQUEST_0_ITEMAMT'] = $subtotal + $shipping;
            $fields['PAYMENTREQUEST_0_AMT'] = $total + $shipping;
        } else {
            $fields['PAYMENTREQUEST_0_SHIPPINGAMT'] = number_format($shipping, 2);
            $fields['PAYMENTREQUEST_0_ITEMAMT'] = number_format($subtotal, 2);
            $fields['PAYMENTREQUEST_0_TAXAMT'] = number_format($total_tax, 2);
            $fields['PAYMENTREQUEST_0_AMT'] = number_format($total, 2);
        }
    }

    private function _setShippingAddress(&$fields, $params)
    {
        $fields['ADDROVERRIDE'] = '0';
        $fields['NOSHIPPING'] = (isset($params['NOSHIPPING'])?$params['NOSHIPPING']:'1');
        $fields['EMAIL'] = $params['EMAIL'];
        $fields['PAYMENTREQUEST_0_SHIPTONAME'] = $params['ADDRESS_OBJ']->firstname.' '.$params['ADDRESS_OBJ']->lastname;
        $fields['PAYMENTREQUEST_0_SHIPTOPHONENUM'] = (empty($params['ADDRESS_OBJ']->phone)) ? $params['ADDRESS_OBJ']->phone_mobile : $params['ADDRESS_OBJ']->phone;
        $fields['PAYMENTREQUEST_0_SHIPTOSTREET'] = $params['ADDRESS_OBJ']->address1;
        $fields['PAYMENTREQUEST_0_SHIPTOSTREET2'] = $params['ADDRESS_OBJ']->address2;
        $fields['PAYMENTREQUEST_0_SHIPTOCITY'] = $params['ADDRESS_OBJ']->city;
        $fields['PAYMENTREQUEST_0_SHIPTOSTATE'] = $params['STATE'];
        $fields['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] = $params['COUNTRY'];
        $fields['PAYMENTREQUEST_0_SHIPTOZIP'] = $params['ADDRESS_OBJ']->postcode;
    }

    public function _getPaymentInfo(&$params)
    {
        // Set cart products list
        $context = Context::getContext();
        $cart = $context->cart;
        $customer = $context->customer;
        $products = $cart->getProducts();
        $discounts = $context->cart->getCartRules();
        $wrapping = $context->cart->gift ? $context->cart->getGiftWrappingPrice() : 0;
        $params['PAYMENT_LIST'] = array(
            'PRODUCTS' => $products,
            'DISCOUNTS' => $discounts,
            'WRAPPING' => $wrapping,
        );

        // Payment values
        $params['CURRENCY'] = $context->currency->iso_code;
        $params['PAYMENTREQUEST_0_PAYMENTACTION'] = Configuration::get('PAYPAL_API_INTENT');

        $shipping_cost_wt = $cart->getTotalShippingCost();
        $total = $cart->getOrderTotal(true, Cart::BOTH);
        $summary = $cart->getSummaryDetails();
        $subtotal = Tools::ps_round($summary['total_products'], 2);

        $params['COSTS'] = array(
            'SHIPPING_COST' => (float) $shipping_cost_wt,
            'TOTAL' => (float) $total,
            'SUBTOTAL' => (float) $subtotal,
            'CARRIER' => new Carrier($cart->id_carrier),
        );

        // Set address information
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
        $params['SHIPPING'] = array(
            'ADDRESS_OBJ' => $address,
            'EMAIL' => $customer->email,
            'STATE' => $state ? $state->iso_code : '',
            'COUNTRY' => $country->iso_code,
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
                $params['USER'] = Configuration::get('PAYPAL_USERNAME_LIVE');
                $params['PWD'] = Configuration::get('PAYPAL_PSWD_LIVE');
                $params['SIGNATURE'] = Configuration::get('PAYPAL_SIGNATURE_LIVE');
                break;
            case 1:
                $params['USER'] = Configuration::get('PAYPAL_USERNAME_SANDBOX');
                $params['PWD'] = Configuration::get('PAYPAL_PSWD_SANDBOX');
                $params['SIGNATURE'] = Configuration::get('PAYPAL_SIGNATURE_SANDBOX');
                break;
        }
    }

    public function validation()
    {
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        $params = array(
            'TOKEN' => Tools::getValue('token'),
            'PAYERID' => Tools::getValue('PayerID'),
        );
        $this->_getCredentialsInfo($params);
        $this->_getPaymentInfo($params);


        $exec_payment = $sdk->doExpressCheckout($params);
        if (isset($exec_payment['L_ERRORCODE0'])) {
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_code' => $exec_payment['L_ERRORCODE0'])));
        }

        $cart = Context::getContext()->cart;
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        $currency = Context::getContext()->currency;
        $total = (float)$exec_payment['PAYMENTINFO_0_AMT'];
        $paypal = Module::getInstanceByName('paypal');
        if (Configuration::get('PAYPAL_API_INTENT') == "sale") {
            $order_state = Configuration::get('PS_OS_PAYMENT');
        } else {
            $order_state = Configuration::get('PAYPAL_OS_WAITING');
        }
        $transactionDetail = $this->getDetailsTransaction($exec_payment);
        $paypal->validateOrder($cart->id, $order_state, $total, 'paypal', null, $transactionDetail, (int)$currency->id, false, $customer->secure_key);
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

        $params['AMT'] = $paypal_order->total_paid;
        $params['AUTHORIZATIONID'] = $paypal_order->id_transaction;
        $params['CURRENCYCODE'] = $paypal_order->currency;
        $params['COMPLETETYPE'] = 'complete';
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
        $params['TRANSACTIONID'] = $id_transaction;
        $params['REFUNDTYPE'] = 'Full';
        $response = $sdk->refundTransaction($params);

        if (Validate::isLoadedObject($capture) && $capture->capture_id) {
            if (isset($response['REFUNDTRANSACTIONID']) && $response['ACK'] == 'Success') {
                Db::getInstance()->update(
                    'paypal_capture',
                    array(
                        'result' => 'Refunded',
                    ),
                    'id_paypal_order = '.(int)$id_paypal_order
                );
            }
        }
        if (isset($response['REFUNDTRANSACTIONID']) && $response['ACK'] == 'Success') {
            $paypal_order->payment_status = 'Refunded';
            $paypal_order->update();
        }
 
        return $response;
    }
    
    public function void($authorization)
    {
        $params = array();
        $params['AUTHORIZATIONID'] = $authorization['authorization_id'];
        $this->_getCredentialsInfo($params);
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        return $sdk->doVoid($params);
    }

    public function renderExpressCheckout(&$context,$type)
    {
        if (!Configuration::get('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT')) {
            return false;
        }

        $lang = Language::getLanguage($context->language->id);
        $context->smarty->assign(array(
            'PayPal_payment_type' => $type,
            'PayPal_tracking_code' => 'PRESTASHOP_ECM',
            'PayPal_lang_code' => str_replace('-','_' , $lang['locale']),
            'action_url' => $context->link->getModuleLink('paypal','ecScInit',array(),true)
        ));
        $context->controller->registerJavascript($this->name.'-order_confirmation_js', 'modules/paypal/views/js/ec_shortcut.js');

        return $context->smarty->fetch('module:paypal/views/templates/hook/EC_shortcut.tpl');
    }

    public function getInfo($params)
    {
        switch (Configuration::get('PAYPAL_SANDBOX')) {
            case 0:
                $params['USER'] = Configuration::get('PAYPAL_USERNAME_LIVE');
                $params['PWD'] = Configuration::get('PAYPAL_PSWD_LIVE');
                $params['SIGNATURE'] = Configuration::get('PAYPAL_SIGNATURE_LIVE');
                break;
            case 1:
                $params['USER'] = Configuration::get('PAYPAL_USERNAME_SANDBOX');
                $params['PWD'] = Configuration::get('PAYPAL_PSWD_SANDBOX');
                $params['SIGNATURE'] = Configuration::get('PAYPAL_SIGNATURE_SANDBOX');
                break;
        }

        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        return $sdk->getExpressCheckout($params);

    }
}
