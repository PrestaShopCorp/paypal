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

class PaypalSDK
{
    private $action;
    private $endpoint;
    private $urlAPI;
    private $version = '204';

    public function __construct($sandbox=0)
    {
        $this->action = 'POST';
        if ($sandbox) {
            $this->urlAPI = 'https://api-3t.sandbox.paypal.com/nvp';
        } else {
            $this->urlAPI = 'https://api-3t.paypal.com/nvp';
        }
    }

    public function setExpressCheckout($params)
    {
        $fields = array();

        // Seller informations
        $this->_setUserCredentials($fields, $params);

        $fields['METHOD'] = 'SetExpressCheckout';
        $fields['VERSION'] = $this->version;
        $fields['CANCELURL'] = $params['CANCELURL'];
        $fields['SOLUTIONTYPE'] = 'Sole';
        $fields['LANDINGPAGE'] = $params['LANDINGPAGE'];
        $fields['RETURNURL'] = $params['RETURNURL'];

        // Set payment detail (reference)
        $this->_setPaymentDetails($fields, $params);

        return $this->makeCallPaypal($fields);

    }

    private function _setPaymentDetails(&$fields, $params)
    {
        // Products
        $tax = $total_products = 0;
        $index = -1;

        // Set cart products list
        $this->setProductsList($fields, $params['PAYMENT_LIST']['PRODUCTS'], $index, $total_products, $tax);
        $this->setDiscountsList($fields, $params['PAYMENT_LIST']['DISCOUNTS'], $index, $total_products, $tax);
        $this->setGiftWrapping($fields, $params['PAYMENT_LIST']['WRAPPING'], $index, $total_products, $tax);

        // Payment values
        $fields['PAYMENTREQUEST_0_PAYMENTACTION'] = $params['PAYMENTREQUEST_0_PAYMENTACTION'];
        $fields['PAYMENTREQUEST_0_CURRENCYCODE'] = $params['CURRENCY'];
        $this->setPaymentValues($fields, $params['COSTS'], $index, $total_products, $tax);

        // Set address information
        $this->_setShippingAddress($fields, $params['SHIPPING']);

        foreach ($params as &$field) {
            if (is_numeric($field)) {
                $field = str_replace(',', '.', $field);
            }
        }

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

            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = round($product['price'], 2);
            $fields['L_PAYMENTREQUEST_0_TAXAMT'.$index] = round($product['price_wt'] - $product['price'], 2);
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
                $fields['L_PAYMENTREQUEST_0_AMT'.$index] = -1 * round($discount['value_real'], 2);
                $fields['L_PAYMENTREQUEST_0_QTY'.$index] = 1;

                $total_products = round($total_products + $fields['L_PAYMENTREQUEST_0_AMT'.$index], 2);
            }
        }
    }

    private function setGiftWrapping(&$fields, $wrapping, &$index, &$total_products)
    {
        if ($wrapping > 0) {
            $fields['L_PAYMENTREQUEST_0_NAME'.++$index] = 'Gift wrapping';
            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = round($wrapping, 2);
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
            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = $shipping;
            $fields['L_PAYMENTREQUEST_0_QTY'.$index] = 1;
            $fields['PAYMENTREQUEST_0_ITEMAMT'] = $subtotal + $shipping;
            $fields['PAYMENTREQUEST_0_AMT'] = $total + $shipping;
        } else {
            $fields['PAYMENTREQUEST_0_SHIPPINGAMT'] = $shipping;
            $fields['PAYMENTREQUEST_0_ITEMAMT'] = $subtotal;
            $fields['PAYMENTREQUEST_0_TAXAMT'] = $total_tax;
            $fields['PAYMENTREQUEST_0_AMT'] = $total;
        }
    }

    private function _setUserCredentials(&$fields, $params)
    {
        $fields['USER'] =  $params['USER'];
        $fields['PWD'] = $params['PWD'];
        $fields['SIGNATURE'] = $params['SIGNATURE'];
    }

    private function _setShippingAddress(&$fields, $params)
    {
        $fields['ADDROVERRIDE'] = '0';
        $fields['NOSHIPPING'] = '1';
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

    public function getExpressCheckout($params)
    {
        $fields = array();
        $this->_setUserCredentials($fields, $params);
        $fields['METHOD'] = 'GetExpressCheckoutDetails';
        $fields['VERSION'] = $this->version;
        $fields['TOKEN'] = $params['TOKEN'];
        return $this->makeCallPaypal($fields);
    }

    public function doExpressCheckout($params)
    {
        $fields = array();
        // Seller informations
        $this->_setUserCredentials($fields, $params);

        $fields['METHOD'] = 'DoExpressCheckoutPayment';
        $fields['VERSION'] = $this->version;
        $fields['TOKEN'] = $params['TOKEN'];
        $fields['PAYERID'] = $params['PAYERID'];

        // Set payment details
        $this->_setPaymentDetails($fields, $params);

        /* echo '<pre>';
        print_r($this->makeCallPaypal($fields));
        echo '<pre>';
        die;*/
        return $this->makeCallPaypal($fields);
    }

    public function doVoid($params)
    {
        $fields = array();
        $this->_setUserCredentials($fields, $params);
        $fields['METHOD'] = 'DoVoid';
        $fields['VERSION'] = $this->version;
        $fields['AUTHORIZATIONID'] = $params['authorization_id'];
        return $this->makeCallPaypal($fields);
    }

    public function doCapture($params)
    {
        $fields = array();
        $this->_setUserCredentials($fields, $params);
        $fields['METHOD'] = 'DoCapture';
        $fields['VERSION'] = $this->version;
        $fields['AMT'] = number_format($params['AMT'], 2);
        $fields['AUTHORIZATIONID'] = $params['AUTHORIZATIONID'];
        $fields['CURRENCYCODE'] = $params['CURRENCYCODE'];
        $fields['COMPLETETYPE'] = $params['COMPLETETYPE'];
        return $this->makeCallPaypal($fields);
    }

    public function refundTransaction($params)
    {
        $fields = array();
        $this->_setUserCredentials($fields, $params);
        $fields['METHOD'] = 'RefundTransaction';
        $fields['VERSION'] = $this->version;
        $fields['TRANSACTIONID'] = $params['TRANSACTIONID'];
        $fields['REFUNDTYPE'] = $params['REFUNDTYPE'];
        return $this->makeCallPaypal($fields);
    }

    public function getUrlOnboarding($body)
    {
        $response = $this->makeCallSI(http_build_query($body, '', '&'));
        return $response;
    }

    private function makeCallPaypal($body)
    {
        $response = $this->makeCall(http_build_query($body, '', '&'));
        return $response;
    }

    private function makeCallSI($body = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, "https://paypal-sandbox.pp-ps-auth.com/getUrl?".$body);
        $response = curl_exec($curl);
        return $response;
    }

    private function makeCall($body = null)
    {
        $curl = curl_init();
        if ($this->action == "GET") {
            $body = (is_array($body)) ? http_build_query($body) : $body;
            $this->endpoint = $this->endpoint.$body;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $this->urlAPI.$this->endpoint);
        if ($this->action == "PUT" || $this->action == "DELETE" || $this->action == "PATCH") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->action);
        }
        if ($this->action == "POST") {
            curl_setopt($curl, CURLOPT_POST, true);
        }
        if ($this->action == "PUT") {
            curl_setopt($curl, CURLOPT_PUT, true);
        }
        if ($this->action == "POST" || $this->action == "PUT" || $this->action == "DELETE") {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);
        $result = explode('&', $response);
        foreach ($result as $value) {
            $tmp = explode('=', $value);
            $return[$tmp[0]] = urldecode(!isset($tmp[1]) ? $tmp[0] : $tmp[1]);
        }

        if (curl_errno($curl)) {
            die('error occured during curl exec. Additioanl info: ' . curl_errno($curl).':'. curl_error($curl));
        }
        return $return;
    }
}
