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

        // Seller informations

        $params['VERSION'] = $this->version;
        return $this->makeCallPaypal($params);

    }

    private function _setUserCredentials(&$fields, $params)
    {
        $fields['USER'] =  $params['USER'];
        $fields['PWD'] = $params['PWD'];
        $fields['SIGNATURE'] = $params['SIGNATURE'];
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
