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

    public function setConfig($params)
    {
    }

    public function init($data)
    {
        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));

        $params = array(
            'CANCELURL' => Tools::getShopDomain(true, true).'/index.php?controller=order&step=1',
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
        }
        return $return;
    }

    public function _getPaymentInfo(&$params)
    {
        // Set cart products list
        $cart = Context::getContext()->cart;
        $products = $cart->getProducts();
        $discounts = Context::getContext()->cart->getCartRules();
        $wrapping = Context::getContext()->cart->gift ? $this->getGiftWrappingPrice() : 0;
        $params['PAYMENT_LIST'] = array(
            'PRODUCTS' => $products,
            'DISCOUNTS' => $discounts,
            'WRAPPING' => $wrapping,
        );

        // Payment values
        $params['CURRENCY'] = Context::getContext()->currency->iso_code;
        $params['PAYMENTREQUEST_0_PAYMENTACTION'] = Configuration::get('PAYPAL_API_INTENT');

        $shipping_cost_wt = Context::getContext()->cart->getTotalShippingCost();
        $total = $cart->getOrderTotal(true, Cart::BOTH);
        $summary = $cart->getSummaryDetails();
        $subtotal = Tools::ps_round($summary['total_products'], 2);

        $params['COSTS'] = array(
            'SHIPPING_COST' => (float) $shipping_cost_wt,
            'TOTAL' => (float) $total,
            'SUBTOTAL' => (float) $subtotal,
            'CARRIER' => new Carrier(Context::getContext()->cart->id_carrier),
        );

        // Set address information
        $id_address = (int) Context::getContext()->cart->id_address_delivery;
        if (($id_address == 0) && (Context::getContext()->customer)) {
            $id_address = Address::getFirstCustomerAddressId(Context::getContext()->customer->id);
        }
        $address = new Address($id_address);
        $state = '';
        if ($address->id_state) {
            $state = new State((int) $address->id_state);
        }
        $country = new Country((int) $address->id_country);
        $params['SHIPPING'] = array(
            'ADDRESS_OBJ' => $address,
            'EMAIL' => Context::getContext()->customer->email,
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
       /* echo '<pre>';
        print_r($this->makeCallPaypal($params));
        echo '<pre>';
        die;*/
        $exec_payment = $sdk->doExpressCheckout($params);

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
            $order_state = Configuration::get('PS_OS_PAYPAL');
        }

        $paypal->validateOrder($cart->id, $order_state, $total, 'paypal', null, $exec_payment, (int)$currency->id, false, $customer->secure_key);
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
            Db::getInstance()->update(
                'paypal_capture',
                array(
                    'id_capture' => $response['TRANSACTIONID'],
                    'capture_amount' => $response['AMT'],
                    'result' => $response['PAYMENTSTATUS'],
                ),
                'id_paypal_order = '.(int)$id_paypal_order
            );
        }
        
        return $response;
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
}
