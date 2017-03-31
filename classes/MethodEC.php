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

    public function setConfig($params)
    {
        $sdk = new PaypalSDK(
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_CLIENTID'):Configuration::get('PAYPAL_LIVE_CLIENTID'),
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_SECRET'):Configuration::get('PAYPAL_LIVE_SECRET'),
            Configuration::get('PAYPAL_SANDBOX')
        );
        $web_experience = $sdk->createWebExperience($params);
        if (isset($web_experience->id)) {
            return $web_experience->id;
        } else {
            return false;
        }
    }

    public function init($data)
    {
        $sdk = new PaypalSDK(
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_CLIENTID'):Configuration::get('PAYPAL_LIVE_CLIENTID'),
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_SECRET'):Configuration::get('PAYPAL_LIVE_SECRET'),
            Configuration::get('PAYPAL_SANDBOX')
        );
        $context = Context::getContext();
        $cart = $context->cart;
        //$currency = $context->currency;
        $module = Module::getInstanceByName('paypal');

        // change currency with configuration paypal
        $currency = $module->getCurrency($context->cart->id_currency);
        $context->cart->id_currency = (int) $currency->id;
        $context->cart->save();
        $context->currency = $currency;
        $context->cookie->id_currency = (int) $currency->id;

        $customer = $context->customer;
        $paypal = Module::getInstanceByName('paypal');

        $summary = $cart->getSummaryDetails();

        $products = $cart->getProducts();
        $address = new Address($cart->id_address_delivery);
        $country = new Country($address->id_country);
        $state = new State($address->id_state);

        $params = array(
            'intent' => Configuration::get('PAYPAL_API_INTENT'), //sale
            'payer' => array(
                'payment_method' => 'paypal', //credit_card
                'payer_info' => array(
                    'email' => $customer->email,
                )
            ),
            'redirect_urls' => array(
                'return_url' => Context::getContext()->link->getModuleLink($this->name, 'ecValidation', array(), true),
                'cancel_url' => Tools::getShopDomain(true, true).'/index.php?controller=order&step=1',
            ),
        );

        if ($data['use_card']) {
            $params['experience_profile_id'] = Configuration::get('PAYPAL_EXPERIENCE_PROFILE_CARD');
        } else {
            $params['experience_profile_id'] = Configuration::get('PAYPAL_EXPERIENCE_PROFILE');
        }
        $items = array();
        $total_products = $total_tax = $total_cart = $shipping_cost = $total = 0;
        foreach ($products as $product) {
            $tax_product = str_replace(',', '.', round($product['total_wt'] - $product['total'], 2));
            $items[] = array(
                'quantity' => $product['cart_quantity'],
                'name' => $product['name'],
                'price' =>  str_replace(',', '.', round($product['price'], 2)),
                'currency' => $currency->iso_code,
                'description' => strip_tags($product['description_short']),
                'tax' => str_replace(',', '.', round($product['total_wt'] - $product['total'], 2)),
            );
            $total_products = $total_products + str_replace(',', '.', (round($product['price'], 2) * $product['cart_quantity']));
            $total_tax = $total_tax + $tax_product;
        }

        $discounts = $cart->getCartRules();
        foreach ($discounts as $discount) {
            $price_discount = -1 * str_replace(',', '.', round($discount['value_tax_exc'], 2));
            $tax_discount = -1 * str_replace(',', '.', round($discount['value_real'] - $discount['value_tax_exc'], 2));
            $items[] = array(
                'quantity' => 1,
                'name' => $paypal->l('Discount : ').$discount['name'],
                'price' =>  $price_discount,
                'currency' => $currency->iso_code,
                'description' => strip_tags($discount['description']),
                'tax' => $tax_discount,
            );
            $total_products = $total_products + $price_discount;
            $total_tax = $total_tax + $tax_discount;
        }

        if ($cart->gift == 1) {
            $gift_wrapping_price = str_replace(',', '.', round($this->getGiftWrappingPrice(), 2));
            $items[] = array(
                'quantity' => 1,
                'name' => $paypal->l('Gift wrapping'),
                'price' =>  $gift_wrapping_price,
                'currency' => $currency->iso_code,
                'description' => '',
                'tax' => 0,
            );
            $total_products = $total_products + $gift_wrapping_price;
        }

        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $subtotal = str_replace(',', '.', round($summary['total_products'], 2));

        if ($subtotal != $total_products) {
            $subtotal = $total_products;
        }
        $tax = str_replace(',', '.', round($summary['total_tax'], 2));
        if ($tax != $total_tax) {
            $tax = $total_tax;
        }

        $shipping_cost = $cart->getTotalShippingCost();
        $shipping = str_replace(',', '.', round($shipping_cost, 2));

        $total_cart = $total_products + $total_tax + $shipping;

        if (($total_cart) != $total) {
            $total = $total_cart;
            $params['note_to_payer'] = $paypal->l('Price of your cart was rounded with few centimes');
        }

        $trans = array(
            'amount' => array(
                'total' => $total,
                'currency' => $currency->iso_code,
                'details' => array(
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'shipping' => $shipping,
                ),
            ),
            'custom' => "PS_"._PS_VERSION_."_Module_".$paypal->version,
            'item_list' => array(
                'items' => $items,
                "shipping_address" => array(
                    "recipient_name" => $address->firstname.' '.$address->lastname,
                    "line1" => $address->address1,
                    "line2" => $address->address2,
                    "city" => $address->city,
                    "country_code" => $country->iso_code,
                    "postal_code" => $address->postcode,
                    "state" => $state->iso_code
                ),
            ),
        );

        if (isset($address->phone) && !empty($address->phone)) {
            $trans['item_list']['shipping_address']['phone'] = $address->phone;
        }


        $params['transactions'][] = $trans;
        $return = false;

        $payment = $sdk->createPayment($params);

        // add for security test
        if (isset($payment->id)) {
            $context->cookie->paymentId = $payment->id;
        }


        if (isset($payment->links)) {
            foreach ($payment->links as $redirect_urls) {
                if ($redirect_urls->method == "REDIRECT") {
                    $return = $redirect_urls->href;
                }
            }
        }
        return $return;
    }

    public function validation()
    {
        $context = Context::getContext();

        $sdk = new PaypalSDK(
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_CLIENTID'):Configuration::get('PAYPAL_LIVE_CLIENTID'),
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_SECRET'):Configuration::get('PAYPAL_LIVE_SECRET'),
            Configuration::get('PAYPAL_SANDBOX')
        );


        // add security check
        if (Tools::getValue('paymentId') != $context->cookie->paymentId) {
            die('payment Id is invalid');
        }

        $exec_payment = $sdk->executePayment(Tools::getValue('paymentId'), Tools::getValue('PayerID'));
        

        if (empty($exec_payment) || !isset($exec_payment->id)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $cart = $context->cart;
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $total = (float)$exec_payment->transactions[0]->amount->total;
        $paypal = Module::getInstanceByName('paypal');
        $currency = $paypal->getCurrency($context->currency->id);
        $context->cart->id_currency = (int) $currency->id;
        $context->cart->update();
        $context->currency = $currency;


        if (Configuration::get('PAYPAL_API_INTENT') == "sale") {
            $order_state = Configuration::get('PS_OS_PAYMENT');
        } else {
            $order_state = Configuration::get('PAYPAL_OS_WAITING');
        }
        if ($exec_payment->intent == "authorize") {
            $intent = "authorization";
        } else {
            $intent = $exec_payment->intent;
        }

        $transaction = array(
            'transaction_id' => $exec_payment->transactions[0]->related_resources[0]->$intent->id,
            'id' => $exec_payment->id,
            'payment_method' => $exec_payment->payer->payment_method,
            'status' => $exec_payment->state,
            'currency' => $exec_payment->transactions[0]->amount->currency,
            'intent' => $intent
        );
        $paypal->validateOrder($cart->id, $order_state, $total, 'paypal', null, $transaction, (int)$currency->id, false, $customer->secure_key);
        unset($context->cookie->paymentId);
    }

    public function confirmCapture()
    {
        $sdk = new PaypalSDK(
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_CLIENTID'):Configuration::get('PAYPAL_LIVE_CLIENTID'),
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_SECRET'):Configuration::get('PAYPAL_LIVE_SECRET'),
            Configuration::get('PAYPAL_SANDBOX')
        );
        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
        $id_paypal_order = $paypal_order->id;
        $body = array(
            'amount' => array(
                'total' => number_format($paypal_order->total_paid, 2, ".", ","),
                'currency' => $paypal_order->currency
            ),
            'is_final_capture' => true,

        );
        $response = $sdk->captureAuthorization($body, $paypal_order->id_transaction);
        if (isset($response->state) && $response->state == 'completed' || isset($response->name) && $response->name == 'AUTHORIZATION_ALREADY_COMPLETED') {
            Db::getInstance()->update(
                'paypal_capture',
                array(
                    'id_capture' => pSQL($response->id),
                    'capture_amount' => pSQL($response->amount->total),
                    'result' => 'completed',
                ),
                'id_paypal_order = ' . (int)$id_paypal_order
            );
        }
        return $response;
    }

    public function check()
    {
    }

    public function refund()
    {
        $sdk = new PaypalSDK(
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_CLIENTID'):Configuration::get('PAYPAL_LIVE_CLIENTID'),
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_SECRET'):Configuration::get('PAYPAL_LIVE_SECRET'),
            Configuration::get('PAYPAL_SANDBOX')
        );

        $paypal_order = PaypalOrder::loadByOrderId(Tools::getValue('id_order'));
        $id_paypal_order = $paypal_order->id;

        $body = array(
            'amount' => array(
                'total' => number_format($paypal_order->total_paid, 2, ".", ","),
                'currency' => $paypal_order->currency
            )
        );

        $capture = PaypalCapture::loadByOrderPayPalId($id_paypal_order);

        if ($capture->id_capture) {
            $response = $sdk->refundCapture($body, $capture->id_capture);
            if (isset($response->id)) {
                Db::getInstance()->update(
                    'paypal_capture',
                    array(
                        'result' => pSQL($response->state),
                    ),
                    'id_paypal_order = '.(int)$id_paypal_order
                );
            }
        } else {
            $response = $sdk->refundSale($body, $paypal_order->id_transaction);
        }
        if (isset($response->id)) {
            $paypal_order->payment_status = 'refunded';
            $paypal_order->update();
        }

        return $response;
    }

    public function void($params)
    {
        $sdk = new PaypalSDK(
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_CLIENTID'):Configuration::get('PAYPAL_LIVE_CLIENTID'),
            Configuration::get('PAYPAL_SANDBOX')?Configuration::get('PAYPAL_SANDBOX_SECRET'):Configuration::get('PAYPAL_LIVE_SECRET'),
            Configuration::get('PAYPAL_SANDBOX')
        );
        return $sdk->voidAuthorization($params['authorization_id']);
    }
}
