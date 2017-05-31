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

include_once(_PS_MODULE_DIR_.'paypal/sdk/braintree/lib/Braintree.php');

class MethodBT extends AbstractMethodPaypal
{
    public $name = 'paypal';

    public $token;

    public $mode;

    public function getMethodContent()
    {
        Context::getContext()->smarty->assign(array(
            'braintree_dispo' => true,
            'bt_active' => Configuration::get('PAYPAL_BRAINTREE_ENABLED'),
            'need_rounding' => Configuration::get('PS_ROUND_TYPE') == Order::ROUND_ITEM ? 0 : 1,
        ));
    }

    public function setConfig()
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';

        if (Tools::isSubmit('paypal_braintree_curr')) {
            $ps_currencies = Currency::getCurrencies();
            foreach ($ps_currencies as $curr) {
                $new_accounts[$curr['iso_code']] = Tools::getValue('braintree_curr_'.$curr['iso_code']);
            }
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID', Tools::jsonEncode($new_accounts));
        }

        if (Tools::getValue('accessToken') && Tools::getValue('expiresAt') && Tools::getValue('refreshToken') && Tools::getValue('merchantId')) {
            Configuration::updateValue('PAYPAL_BRAINTREE_ENABLED', 1);
            $method_bt = AbstractMethodPaypal::load('BT');
            $merchant_accounts = $method_bt->createForCurrency();
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_ACCESS_TOKEN', Tools::getValue('accessToken'));
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_EXPIRES_AT', Tools::getValue('expiresAt'));
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_REFRESH_TOKEN', Tools::getValue('refreshToken'));
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_MERCHANT_ID', Tools::getValue('merchantId'));
            if ($merchant_accounts) {
                Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID', $merchant_accounts);
            }
        }
    }

    private function initConfig()
    {

        $this->mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
        $this->gateway = new Braintree_Gateway(['accessToken' => Configuration::get('PAYPAL_'.$this->mode.'_BRAINTREE_ACCESS_TOKEN') ]);
        $this->error = '';
    }


    public function init($data)
    {
        try {
            $this->initConfig();
            $clientToken = $this->gateway->clientToken()->generate();
            return $clientToken;
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getCode().'=>'.$e->getMessage());
            return false;
        }
    }

    public function createForCurrency($currency = null)
    {
        $this->initConfig();
        $result = '';

        if ($currency) {
            try {
                $response = $this->gateway->merchantAccount()->createForCurrency([
                    'currency' => $currency,
                ]);
                if ($response->success) {
                    $result[$response->merchantAccount->currencyIsoCode] = $response->merchantAccount->id;
                }
            }
            catch  (Exception $e) {
            }
        } else {
            $currencies = Currency::getCurrencies();
            foreach ($currencies as $curr) {
                try {
                    $response = $this->gateway->merchantAccount()->createForCurrency([
                        'currency' => $curr['iso_code'],
                    ]);
                    if ($response->success) {
                        $result[$response->merchantAccount->currencyIsoCode] = $response->merchantAccount->id;
                    }
                }
                catch  (Exception $e) {
                }
            }
        }

        return $result;

    }


    public function getTransactionStatus($transactionId)
    {
        $this->initConfig();

        try {
            $result = $this->gateway->transaction()->find($transactionId);

            return $result->status;
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getCode().'=>'.$e->getMessage());
            return false;
        }
    }

    public function validation()
    {
        $paypal = new PayPal();

        $transaction = $this->sale(context::getContext()->cart, Tools::getValue('payment_method_nonce'), Tools::getValue('deviceData'));

        if (!$transaction) {
            Tools::redirect('index.php?controller=order&step=3&bt_error_msg='.urlencode($this->error));
        }
        $transactionDetail = $this->getDetailsTransaction($transaction);
        if (Configuration::get('PAYPAL_API_INTENT') == "sale") {
            $order_state = Configuration::get('PS_OS_PAYMENT');
        } else {
            $order_state = Configuration::get('PAYPAL_BRAINTREE_OS_AWAITING');
        }
        $paypal->validateOrder(context::getContext()->cart->id, $order_state, $transaction->amount, 'Braintree', $paypal->l('Payment accepted.'), $transactionDetail, context::getContext()->cart->id_currency, false, context::getContext()->customer->secure_key);
        $order_id = Order::getOrderByCartId(context::getContext()->cart->id);

       // echo'<pre>';print_r($braintree_presta);echo'<pre>';die;
    }

    public function getDetailsTransaction($transaction)
    {
        return array(
            'method' => 'BT',
            'currency' => pSQL($transaction->currencyIsoCode),
            'transaction_id' => pSQL($transaction->id),
            'payment_method' => $transaction->type,
            'payment_status' => $transaction->status,
            'id_payment' => Tools::getValue('payment_method_nonce'),
            'client_token' => Tools::getValue('client_token'),
            'capture' => $transaction->status == "authorized" ? true : false,
        );
    }

    public function sale($cart, $token_payment, $device_data)
    {

        $this->initConfig();
        $bt_method = Tools::getValue('payment_method_bt');
        $merchant_accounts = Tools::jsonDecode(Configuration::get('PAYPAL_'.$this->mode.'_BRAINTREE_ACCOUNT_ID'));
        $address_billing = new Address($cart->id_address_invoice);
        $country_billing = new Country($address_billing->id_country);
        $address_shipping = new Address($cart->id_address_delivery);
        $country_shipping = new Country($address_shipping->id_country);
        $current_currency = context::getContext()->currency->iso_code;
//TODO:update for 2 methods : cards and paypal and Add Device data???
        try {
            $data = [
                'amount'                => $cart->getOrderTotal(),
                'paymentMethodNonce'    => $token_payment,//'fake-processor-declined-visa-nonce',//
                'merchantAccountId'     => $merchant_accounts->$current_currency,
                'orderId'               => $cart->id,
                'channel'               => 'PrestaShop_Cart_Braintree',
                'billing' => [
                    'firstName'         => $address_billing->firstname,
                    'lastName'          => $address_billing->lastname,
                    'company'           => $address_billing->company,
                    'streetAddress'     => $address_billing->address1,
                    'extendedAddress'   => $address_billing->address2,
                    'locality'          => $address_billing->city,
                    'postalCode'        => $address_billing->postcode,
                    'countryCodeAlpha2' => $country_billing->iso_code,
                ],
                'shipping' => [
                    'firstName'         => $address_shipping->firstname,
                    'lastName'          => $address_shipping->lastname,
                    'company'           => $address_shipping->company,
                    'streetAddress'     => $address_shipping->address1,
                    'extendedAddress'   => $address_shipping->address2,
                    'locality'          => $address_shipping->city,
                    'postalCode'        => $address_shipping->postcode,
                    'countryCodeAlpha2' => $country_shipping->iso_code,
                ],
                "deviceData"            => $device_data,

                'options' => [
                    'submitForSettlement' => Configuration::get('PAYPAL_API_INTENT') == "sale" ? true : false,
                    'threeDSecure' => [
                        'required' => Configuration::get('PAYPAL_USE_3D_SECURE')
                    ]
                ]
            ];

            $result = $this->gateway->transaction()->sale($data);
           // print_r("result");echo'<pre>';print_r($result);echo'<pre>';die;
            if (($result instanceof Braintree_Result_Successful) && $result->success && $this->isValidStatus($result->transaction->status)) {
                return $result->transaction;
            } else {
                $this->error = $result->transaction->status;
            }

        } catch (Exception $e) {
            $this->error = $e->getCode().' : '.$e->getMessage();
            return false;
        }

        return false;
    }

    public function isValidStatus($status)
    {
        return in_array($status, array('submitted_for_settlement','authorized','settled'));
    }




    public function confirmCapture()
    {
        $this->initConfig();
        try {
            $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
            $result = $this->gateway->transaction()->submitForSettlement($paypal_order->id_transaction, number_format($paypal_order->total_paid, 2, ".", ","));
           // echo '<pre>';print_r($result);die;
            if ($result instanceof Braintree_Result_Successful && $result->success) {
                PaypalCapture::updateCapture($result->transaction->id, $result->transaction->amount, $result->transaction->status, $paypal_order->id);
                $response =  array(
                    'success' => true,
                    'authorization_id' => $result->transaction->id,
                    'status' => $result->transaction->status,
                    'amount' => $result->transaction->amount,
                    'currency' => $result->transaction->currencyIsoCode,
                    'payment_type' => $result->transaction->payment_type,
                    'merchantAccountId' => $result->transaction->merchantAccountId,
                );
            } else {
                $errors = $result->errors->deepAll();

                foreach ($errors as $error) {
                    $response = array(
                        'transaction_capture_id' => $result->transaction->id,
                        'status' => $result->transaction->status,
                        'error_code' => $error->code,
                        'error_message' => $error->message,
                    );
                    if ($error->code == Braintree_Error_Codes::TRANSACTION_CANNOT_SUBMIT_FOR_SETTLEMENT) {
                        $response['already_captured'] = true;
                    }
                }
            }
            return $response;
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getCode().'=>'.$e->getMessage());
            return false;
        }
    }

    public function check()
    {
    }

    public function refund()
    {
        $this->initConfig();
        try {
            $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
            $capture = PaypalCapture::loadByOrderPayPalId($paypal_order->id);
            $id_transaction = Validate::isLoadedObject($capture) ? $capture->id_capture : $paypal_order->id_transaction;
         //  echo '<pre>';print_r($this->gateway->transaction()->find($id_transaction));die;
            $result = $this->gateway->transaction()->refund($id_transaction, number_format($paypal_order->total_paid, 2, ".", ","));

            if ($result->success) {
                $response =  array(
                    'success' => true,
                    'refund_id' => $result->transaction->refundedTransactionId,
                    'transaction_id' => $result->transaction->id,
                    'status' => $result->transaction->status,
                    'amount' => $result->transaction->amount,
                    'currency' => $result->transaction->currencyIsoCode,
                    'payment_type' => $result->transaction->payment_type,
                    'merchantAccountId' => $result->transaction->merchantAccountId,
                );

            } else {
                $errors = $result->errors->deepAll();
                foreach ($errors as $error) {
                    $response = array(
                        'transaction_id' => $result->transaction->refundedTransactionId,
                        'status' => 'Failure',
                        'error_code' => $error->code,
                        'error_message' => $error->message,
                    );
                    if ($error->code == Braintree_Error_Codes::TRANSACTION_HAS_ALREADY_BEEN_REFUNDED) {
                        $response['already_refunded'] = true;
                    }
                }
            }
            return $response;
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getCode().'=>'.$e->getMessage());
            return false;
        }
    }

    public function void($authorization)
    {
        $this->initConfig();
        try {
            $result = $this->gateway->transaction()->void($authorization['authorization_id']);
            if ($result instanceof Braintree_Result_Successful && $result->success) {
                $response =  array(
                    'success' => true,
                    'transaction_id' => $result->transaction->id,
                    'status' => $result->transaction->status,
                    'amount' => $result->transaction->amount,
                    'currency' => $result->transaction->currencyIsoCode,
                );
            } else {
                $response =  array(
                    'transaction_id' => $result->params['id'],
                    'error_message' => $result->message,
                );
            }
            return $response;
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getCode().'=>'.$e->getMessage());
            return false;
        }
    }
}
