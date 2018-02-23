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

include_once(_PS_MODULE_DIR_.'paypal/sdk/braintree/lib/Braintree.php');

class MethodBT extends AbstractMethodPaypal
{
    public $name = 'paypal';

    public $token;

    public $mode;

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
                'label' => $module->l('Accept PayPal Payments'),
                'name' => 'activate_paypal',
                'desc' => $module->l(''),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'activate_paypal_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'activate_paypal_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Activate 3D Secure for Braintree'),
                'name' => 'paypal_3DSecure',
                'desc' => $module->l(''),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'paypal_3DSecure_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'paypal_3DSecure_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            ),
            array(
                'type' => 'text',
                'label' => $module->l('Amount for 3DS in ').Currency::getCurrency(Configuration::get('PS_CURRENCY_DEFAULT'))['iso_code'],
                'name' => 'paypal_3DSecure_amount',
                'hint' => $module->l('Activate 3D Secure only for orders which total is bigger that this amount in your context currency'),
            ),
        ));

        $params['fields_value'] = array(
            'paypal_intent' => Configuration::get('PAYPAL_API_INTENT'),
            'paypal_show_advantage' => Configuration::get('PAYPAL_API_ADVANTAGES'),
            'activate_paypal' => Configuration::get('PAYPAL_BY_BRAINTREE'),
            'paypal_3DSecure' => Configuration::get('PAYPAL_USE_3D_SECURE'),
            'paypal_3DSecure_amount' => Configuration::get('PAYPAL_3D_SECURE_AMOUNT'),
        );
        $context = Context::getContext();
        $context->smarty->assign(array(
            'bt_paypal_active' => Configuration::get('PAYPAL_BY_BRAINTREE'),
            'bt_active' => Configuration::get('PAYPAL_BRAINTREE_ENABLED'),
        ));


        $params['form'] = $this->getMerchantCurrenciesForm($module);

        return $params;
    }

    public function getMerchantCurrenciesForm($module)
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
        $merchant_accounts = (array)Tools::jsonDecode(Configuration::get('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID'));

        $ps_currencies = Currency::getCurrencies();
        $fields_form2 = array();
        $fields_form2[0]['form'] = array(
            'legend' => array(
                'title' => $module->l('Braintree merchant accounts'),
                'icon' => 'icon-cogs',
            ),
        );
        $fields_value = array();
        foreach ($ps_currencies as $curr) {
            $fields_form2[0]['form']['input'][] =
                array(
                    'type' => 'text',
                    'label' => $module->l('Merchant account Id for ').$curr['iso_code'],
                    'name' => 'braintree_curr_'.$curr['iso_code'],
                    'value' => isset($merchant_accounts[$curr['iso_code']])?$merchant_accounts[$curr['iso_code']] : ''
                );
            $fields_value['braintree_curr_'.$curr['iso_code']] =  isset($merchant_accounts[$curr['iso_code']])?$merchant_accounts[$curr['iso_code']] : '';
        }
        $fields_form2[0]['form']['submit'] = array(
            'title' => $module->l('Save'),
            'class' => 'btn btn-default pull-right button',
        );

        $helper = new HelperForm();
        $helper->module = $module;
        $helper->name_controller = $module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$module->name;
        $helper->title = $module->displayName;
        $helper->show_toolbar = false;
        $helper->submit_action = 'paypal_braintree_curr';
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->tpl_vars = array(
            'fields_value' => $fields_value,
            'id_language' => Context::getContext()->language->id,
            'back_url' => $module->module_link.'#paypal_params'
        );
        return $helper->generateForm($fields_form2);
    }

    public function setConfig($params)
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
        $paypal = Module::getInstanceByName($this->name);
        $ps_currencies = Currency::getCurrencies();
        $new_accounts = array();
        if (Tools::isSubmit('paypal_braintree_curr')) {
            foreach ($ps_currencies as $curr) {
                $new_accounts[$curr['iso_code']] = Tools::getValue('braintree_curr_'.$curr['iso_code']);
            }
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID', Tools::jsonEncode($new_accounts));
        }

        if (Tools::getValue('accessToken') && Tools::getValue('expiresAt') && Tools::getValue('refreshToken') && Tools::getValue('merchantId')) {
            Configuration::updateValue('PAYPAL_METHOD', 'BT');
            Configuration::updateValue('PAYPAL_BRAINTREE_ENABLED', 1);
            $method_bt = AbstractMethodPaypal::load('BT');
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_ACCESS_TOKEN', Tools::getValue('accessToken'));
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_EXPIRES_AT', Tools::getValue('expiresAt'));
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_REFRESH_TOKEN', Tools::getValue('refreshToken'));
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_MERCHANT_ID', Tools::getValue('merchantId'));
            $existing_merchant_accounts = $method_bt->getAllCurrency();

            $new_merchant_accounts = $method_bt->createForCurrency();

            $all_merchant_accounts = array_merge((array)$existing_merchant_accounts, (array)$new_merchant_accounts);
            unset($all_merchant_accounts[0]);
            if ($all_merchant_accounts) {
                Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID', Tools::jsonEncode($all_merchant_accounts));
            }
        }

        if (Tools::isSubmit('paypal_config')) {
            Configuration::updateValue('PAYPAL_API_INTENT', $params['paypal_intent']);
            Configuration::updateValue('PAYPAL_BY_BRAINTREE', $params['activate_paypal']);
            Configuration::updateValue('PAYPAL_USE_3D_SECURE', $params['paypal_3DSecure']);
            Configuration::updateValue('PAYPAL_3D_SECURE_AMOUNT', (int)$params['paypal_3DSecure_amount']);
            Configuration::updateValue('PAYPAL_API_ADVANTAGES', $params['paypal_show_advantage']);
        }

        if (isset($params['method'])) {
            if (isset($params['with_paypal'])) {
                Configuration::updateValue('PAYPAL_BY_BRAINTREE', $params['with_paypal']);
            }
            if ((isset($params['modify']) && $params['modify']) || (Configuration::get('PAYPAL_METHOD') != $params['method'])) {
                $response = $paypal->getBtConnectUrl();
                $result = Tools::jsonDecode($response);
                if ($result->error) {
                    $paypal->errors .= $paypal->displayError($paypal->l('Error onboarding Braintree : ') . $result->error);
                } elseif (isset($result->data->url_connect)) {
                    Tools::redirectLink($result->data->url_connect);
                }
            }
        }

        if (!Configuration::get('PAYPAL_'.$mode.'_BRAINTREE_ACCESS_TOKEN') || !Configuration::get('PAYPAL_'.$mode.'_BRAINTREE_EXPIRES_AT')
            || !Configuration::get('PAYPAL_'.$mode.'_BRAINTREE_MERCHANT_ID')) {
            $paypal->errors .= $paypal->displayError($paypal->l('An error occurred. Please, check your credentials Braintree.'));
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
            return false;
        }
    }

    public function getAllCurrency()
    {
        $this->initConfig();
        $result = array();
        try {
            $response = $this->gateway->merchantAccount()->all();
            foreach ($response as $account) {
                $result[$account->currencyIsoCode] = $account->id;
            }
        } catch (Exception $e) {
        }
        return $result;
    }

    public function createForCurrency($currency = null)
    {
        $this->initConfig();
        $result = array();

        if ($currency) {
            try {
                $response = $this->gateway->merchantAccount()->createForCurrency([
                    'currency' => $currency,
                ]);
                if ($response->success) {
                    $result[$response->merchantAccount->currencyIsoCode] = $response->merchantAccount->id;
                }
            } catch (Exception $e) {
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
                } catch (Exception $e) {
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
        if (Configuration::get('PAYPAL_API_INTENT') == "sale" && $transaction->paymentInstrumentType == "paypal_account" && $transaction->status == "settling") { // or submitted for settlement?
            $order_state = Configuration::get('PAYPAL_BRAINTREE_OS_AWAITING_VALIDATION');
        } else if ((Configuration::get('PAYPAL_API_INTENT') == "sale" && $transaction->paymentInstrumentType == "paypal_account" && $transaction->status == "settled")
        || (Configuration::get('PAYPAL_API_INTENT') == "sale" && $transaction->paymentInstrumentType == "credit_card")) {
            $order_state = Configuration::get('PS_OS_PAYMENT');
        } else {
            $order_state = Configuration::get('PAYPAL_BRAINTREE_OS_AWAITING');
        }
        $paypal->validateOrder(context::getContext()->cart->id, $order_state, $transaction->amount, 'Braintree', $paypal->l('Payment accepted.'), $transactionDetail, context::getContext()->cart->id_currency, false, context::getContext()->customer->secure_key);
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
            'payment_tool' => $transaction->paymentInstrumentType,
        );
    }
    public function getOrderId($cart)
    {
        return $cart->secure_key.'_'.$cart->id;
    }

    public function formatPrice($price)
    {
        $context = Context::getContext();
        $context_currency = $context->currency;
        $paypal = Module::getInstanceByName('paypal');
        if ($paypal->needConvert()) {
            $price = Tools::ps_round(Tools::convertPrice($price, $context_currency, false), _PS_PRICE_COMPUTE_PRECISION_);
        }
        return $price;
    }

    public function sale($cart, $token_payment, $device_data)
    {

        $this->initConfig();
        $bt_method = Tools::getValue('payment_method_bt');
        if ($bt_method == "paypal-braintree") {
            $options = array(
                'submitForSettlement' => Configuration::get('PAYPAL_API_INTENT') == "sale" ? true : false,
                'threeDSecure' => array(
                    'required' => Configuration::get('PAYPAL_USE_3D_SECURE')
                )
            );
        } else {
            $options = array(
                'submitForSettlement' => Configuration::get('PAYPAL_API_INTENT') == "sale" ? true : false,
            );
        }
        $merchant_accounts = (array)Tools::jsonDecode(Configuration::get('PAYPAL_'.$this->mode.'_BRAINTREE_ACCOUNT_ID'));
        $address_billing = new Address($cart->id_address_invoice);
        $country_billing = new Country($address_billing->id_country);
        $address_shipping = new Address($cart->id_address_delivery);
        $country_shipping = new Country($address_shipping->id_country);
        $amount = $this->formatPrice($cart->getOrderTotal());
        $paypal = Module::getInstanceByName('paypal');
        $currency = $paypal->getPaymentCurrencyIso();

        try {
            $data = [
                'amount'                => $amount,
                'paymentMethodNonce'    => $token_payment,
                'merchantAccountId'     => $merchant_accounts[$currency],
                'orderId'               => $this->getOrderId($cart),
                'channel'               => (getenv('PLATEFORM') == 'PSREAD')?'PrestaShop_Cart_Ready_Braintree':'PrestaShop_Cart_Braintree',
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
                'options' => $options,
            ];

            $result = $this->gateway->transaction()->sale($data);
           // echo '<pre>';print_r($result);die;
            if (($result instanceof Braintree_Result_Successful) && $result->success && $this->isValidStatus($result->transaction->status)) {
                return $result->transaction;
            } else {
                $errors = $result->errors->deepAll();
                if ($errors) {
                    $error_code = $errors[0]->code;
                } else {
                    $error_code = $result->transaction->processorResponseCode;
                }
                Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_code' => $error_code)));
            }
        } catch (Exception $e) {
            $this->error = $e->getCode().' : '.$e->getMessage();
            return false;
        }

        return false;
    }

    public function isValidStatus($status)
    {
        return in_array($status, array('submitted_for_settlement','authorized','settled', 'settling'));
    }


    public function confirmCapture()
    {
        $this->initConfig();
        try {
            $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
            $result = $this->gateway->transaction()->submitForSettlement($paypal_order->id_transaction, number_format($paypal_order->total_paid, 2, ".", ''));
            if ($result instanceof Braintree_Result_Successful && $result->success) {
                PaypalCapture::updateCapture($result->transaction->id, $result->transaction->amount, $result->transaction->status, $paypal_order->id);
                $response =  array(
                    'success' => true,
                    'authorization_id' => $result->transaction->id,
                    'status' => $result->transaction->status,
                    'amount' => $result->transaction->amount,
                    'currency' => $result->transaction->currencyIsoCode,
                    'payment_type' => isset($result->transaction->payment_type) ? $result->transaction->payment_type : '',
                    'merchantAccountId' => $result->transaction->merchantAccountId,
                );
            } else if ($result->transaction->status == Braintree_Transaction::SETTLEMENT_DECLINED) {
                $order = new Order(Tools::getValue('id_order'));
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
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
            $response =  array(
                'error_message' => $e->getCode().'=>'.$e->getMessage(),
            );
            return $response;
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
            $result = $this->gateway->transaction()->refund($id_transaction, number_format($paypal_order->total_paid, 2, ".", ''));

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
            } elseif ($result->transaction->status == Braintree_Transaction::SETTLEMENT_DECLINED) {
                $order = new Order(Tools::getValue('id_order'));
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                $response =  array(
                    'transaction_id' => $result->params['id'],
                    'error_message' => $result->message,
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
            $response =  array(
                'error_message' => $e->getCode().'=>'.$e->getMessage(),
            );
            return $response;
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
            } elseif ($result->transaction->status == Braintree_Transaction::SETTLEMENT_DECLINED) {
                $order = new Order(Tools::getValue('id_order'));
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                $response =  array(
                    'transaction_id' => $result->params['id'],
                    'error_message' => $result->message,
                );
            } else {
                $response =  array(
                    'transaction_id' => $result->params['id'],
                    'error_message' => $result->message,
                );
            }
            return $response;
        } catch (Exception $e) {
            $response =  array(
                'error_message' => $e->getCode().'=>'.$e->getMessage(),
            );
            return $response;
        }
    }

    public function searchTransactions($ids) {
        $this->initConfig();
        $ids_transaction =  Braintree_TransactionSearch::ids()->in($ids);
        $collection = $this->gateway->transaction()->search([
            $ids_transaction
        ]);
        return $collection;
    }
}
