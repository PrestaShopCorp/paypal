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
    private $urlSI;
    private $version = '204';

    public function __construct($sandbox=0)
    {
        $this->action = 'POST';
        if ($sandbox) {
            $this->urlAPI = 'https://api-3t.sandbox.paypal.com/nvp';
            $this->urlSI = 'https://paypal-sandbox.pp-ps-auth.com/';
        } else {
            $this->urlAPI = 'https://api-3t.paypal.com/nvp';
            $this->urlSI = 'https://paypal-live.pp-ps-auth.com/';
        }
    }

    public function setExpressCheckout($params)
    {
        $fields = array();
        // Seller informations
        $this->_setUserCredentials($fields, $params);
        $fields['METHOD'] = 'SetExpressCheckout';
        $fields['VERSION'] = $this->version;
        $fields['CANCELURL'] = $params['cancel_url'];
        $fields['SOLUTIONTYPE'] = $params['solution_type'];
        $fields['LANDINGPAGE'] = $params['landing_page'];
        $fields['RETURNURL'] = $params['return_url'];
        $fields['ADDROVERRIDE'] = $params['addr_override'];
        $fields['NOSHIPPING'] = $params['no_shipping'];
        // Set payment detail (reference)
        $this->_setPaymentDetails($fields, $params);

        return $this->makeCallPaypal($fields);
    }

    private function _setPaymentDetails(&$fields, $params)
    {
        // Set cart products list
        $index = -1;
        $this->_setProductsList($fields, $params['products_list']['products'], $index);
        $this->_setDiscountsList($fields, $params['products_list']['discounts'], $index);
        $this->_setGiftWrapping($fields, $params['products_list']['wrapping'], $index);
        // Payment values
        $fields['PAYMENTREQUEST_0_PAYMENTACTION'] = $params['payment_action'];
        $fields['PAYMENTREQUEST_0_CURRENCYCODE'] = $params['currency'];
        $this->_setPaymentValues($fields, $params['costs'], $index);
        // Set address information
        $this->_setShippingAddress($fields, $params['shipping']);

    }

    private function _setProductsList(&$fields, $products, &$index)
    {
        foreach ($products as $product) {
            $fields['L_PAYMENTREQUEST_0_NUMBER'.++$index] = (int) $product['id_product'];
            $fields['L_PAYMENTREQUEST_0_NAME'.$index] = $product['name'];
            $fields['L_PAYMENTREQUEST_0_DESC'.$index] = $product['description_short'];
            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = $product['price'];
            $fields['L_PAYMENTREQUEST_0_TAXAMT'.$index] = $product['product_tax'];
            $fields['L_PAYMENTREQUEST_0_QTY'.$index] = $product['quantity'];
        }
    }
    private function _setDiscountsList(&$fields, $discounts, &$index)
    {
        if (count($discounts) > 0) {
            foreach ($discounts as $discount) {
                $fields['L_PAYMENTREQUEST_0_NUMBER'.++$index] = $discount['id_discount'];
                $fields['L_PAYMENTREQUEST_0_NAME'.$index] = $discount['name'];
                $fields['L_PAYMENTREQUEST_0_DESC'.$index] = $discount['description'];
                $fields['L_PAYMENTREQUEST_0_AMT'.$index] = $discount['value_real'];
                $fields['L_PAYMENTREQUEST_0_QTY'.$index] = $discount['quantity'];
            }
        }
    }
    private function _setGiftWrapping(&$fields, $wrapping, &$index)
    {
        if ($wrapping) {
            $fields['L_PAYMENTREQUEST_0_NAME'.++$index] = $wrapping['name'];
            $fields['L_PAYMENTREQUEST_0_AMT'.$index] = $wrapping['amount'];
            $fields['L_PAYMENTREQUEST_0_QTY'.$index] = $wrapping['quantity'];
        }
    }
    private function _setPaymentValues(&$fields, $costs, &$index)
    {
        $fields['PAYMENTREQUEST_0_SHIPPINGAMT'] = $costs['shipping_cost'];
        $fields['PAYMENTREQUEST_0_ITEMAMT'] = $costs['subtotal'];
        $fields['PAYMENTREQUEST_0_TAXAMT'] = $costs['total_tax'];
        $fields['PAYMENTREQUEST_0_AMT'] = $costs['total'];
    }
    private function _setUserCredentials(&$fields, $params)
    {
        $fields['USER'] =  $params['user'];
        $fields['PWD'] = $params['pwd'];
        $fields['SIGNATURE'] = $params['signature'];
    }
    private function _setShippingAddress(&$fields, $params)
    {
        $fields['EMAIL'] = $params['email'];
        $fields['PAYMENTREQUEST_0_SHIPTONAME'] = $params['ship_name'];
        $fields['PAYMENTREQUEST_0_SHIPTOPHONENUM'] = $params['phone'];
        $fields['PAYMENTREQUEST_0_SHIPTOSTREET'] = $params['address']->address1;
        $fields['PAYMENTREQUEST_0_SHIPTOSTREET2'] = $params['address']->address2;
        $fields['PAYMENTREQUEST_0_SHIPTOCITY'] = $params['address']->city;
        $fields['PAYMENTREQUEST_0_SHIPTOSTATE'] = $params['state'];
        $fields['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE'] = $params['country'];
        $fields['PAYMENTREQUEST_0_SHIPTOZIP'] = $params['address']->postcode;
    }

    public function getExpressCheckout($params)
    {
        $fields = array();
        $fields['METHOD'] = 'GetExpressCheckoutDetails';
        $fields['VERSION'] = $this->version;
        $fields['TOKEN'] = $params['token'];
        $this->_setUserCredentials($fields, $params);
        return $this->makeCallPaypal($fields);
    }

    public function doExpressCheckout($params)
    {
        $fields = array();
        $fields['METHOD'] = 'DoExpressCheckoutPayment';
        $fields['VERSION'] = $this->version;
        $fields['TOKEN'] = $params['token'];
        $fields['PAYERID'] = $params['payer_id'];
        $fields['BUTTONSOURCE'] = $params['button_source'];
        // Seller informations
        $this->_setUserCredentials($fields, $params);
        // Set payment detail (reference)
        $this->_setPaymentDetails($fields, $params);
        $return = $this->makeCallPaypal($fields);
        return $return;
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
        $fields['AMT'] = $params['amount'];
        $fields['AUTHORIZATIONID'] = $params['authorization_id'];
        $fields['CURRENCYCODE'] = $params['currency_code'];
        $fields['COMPLETETYPE'] = $params['complete_type'];
        return $this->makeCallPaypal($fields);
    }

    public function refundTransaction($params)
    {
        $fields = array();
        $this->_setUserCredentials($fields, $params);
        $fields['METHOD'] = 'RefundTransaction';
        $fields['VERSION'] = $this->version;
        $fields['TRANSACTIONID'] = $params['transaction_id'];
        $fields['REFUNDTYPE'] = $params['refund_type'];
        return $this->makeCallPaypal($fields);
    }

    public function getUrlOnboarding($body)
    {
        $this->endpoint = 'getUrl';
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
        curl_setopt($curl, CURLOPT_URL, $this->urlSI.$this->endpoint.'?'.$body );
        curl_setopt($curl, CURLOPT_URL, $this->urlSI.$this->endpoint.'?'.$body );
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_CAINFO, _PS_CACHE_CA_CERT_FILE_);

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
            die('error occured during curl exec. Additional info: ' . curl_errno($curl).':'. curl_error($curl));
        }
        return $return;
    }
}
