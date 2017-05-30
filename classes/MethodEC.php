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

    public function getMethodContent()
    {
        Configuration::updateValue('PAYPAL_USERNAME_SANDBOX', 'claloum-facilitator_api1.202-ecommerce.com');
        Configuration::updateValue('PAYPAL_PSWD_SANDBOX', '2NRPZ3FZQXN9LY2N');
        Configuration::updateValue('PAYPAL_SIGNATURE_SANDBOX', 'AFcWxV21C7fd0v3bYYYRCpSSRl31Am6xsFqhy1VTTuSmPwEstqKmFDaX');

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
    }

    public function setConfig()
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';

        if (Tools::getValue('api_username') && Tools::getValue('api_password') && Tools::getValue('api_signature')) {
            Configuration::updateValue('PAYPAL_USERNAME_'.$mode, Tools::getValue('api_username'));
            Configuration::updateValue('PAYPAL_PSWD_'.$mode, Tools::getValue('api_password'));
            Configuration::updateValue('PAYPAL_SIGNATURE_'.$mode, Tools::getValue('api_signature'));
            Configuration::updateValue('PAYPAL_'.$mode.'_ACCESS', 1);
        }

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
       /* echo '<pre>';
        print_r($exec_payment);
        echo '<pre>';
        die;*/
        if (isset($exec_payment['L_ERRORCODE0'])) {
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('L_ERRORCODE0' => $exec_payment['L_ERRORCODE0'])));
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
        $params = array();
        $params['AUTHORIZATIONID'] = $authorization['authorization_id'];
        $this->_getCredentialsInfo($params);
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        $result = $sdk->doVoid($params);
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
}
