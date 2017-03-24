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
    private $token;
    private $clientId;
    private $secret;
    private $urlAPI;

    public function __construct($clientId, $secret, $sandbox=0)
    {
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->action = 'POST';
        if ($sandbox) {
            $this->urlAPI = 'https://api.sandbox.paypal.com/';
        } else {
            $this->urlAPI = 'https://api.paypal.com/';
        }
    }

    public function createAccessToken($body = false)
    {
        if (!$body) {
            $body = 'grant_type=client_credentials';
        }
        $this->action = 'POST';
        $this->endpoint = 'v1/oauth2/token';
        $response = $this->makeCall($body, "application/json", true);
        if (!isset($response->access_token)) {
            return false;
        }
        $this->token = $response->access_token;
        return true;
    }

    public function createPayment($body)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'POST';
        $this->endpoint = 'v1/payments/payment';
        $response = $this->makeCall($this->getBody($body));
        return $response;
    }

    public function createWebExperience($body)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'POST';
        $this->endpoint = 'v1/payment-experience/web-profiles';
        $response = $this->makeCall($this->getBody($body));
        return $response;
    }

    public function executePayment($payment_id, $payer_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'POST';
        $this->endpoint = 'v1/payments/payment/'.$payment_id.'/execute';
        $body = array('payer_id' => $payer_id);
        $response = $this->makeCall($this->getBody($body));

        return $response;
    }

    public function updatePayment($payment_id, $body)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'PATCH';
        $this->endpoint = 'v1/payments/payment/'.$payment_id;
        $response = $this->makeCall($this->getBody($body));

        return $response;
    }

    public function refundSale($body, $sale_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'POST';
        $this->endpoint = 'v1/payments/sale/'.$sale_id.'/refund';
        $response = $this->makeCall($this->getBody($body));
        return $response;
    }

    public function showRefund($sale_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'GET';
        $this->endpoint = 'v1/payments/refund/'.$sale_id;
        $response = $this->makeCall(null);
        return $response;
    }

    public function showAuthorization($authorization_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'GET';
        $this->endpoint = 'v1/payments/authorization/'.$authorization_id;
        $response = $this->makeCall(null);
        return $response;
    }

    public function captureAuthorization($body, $authorization_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'POST';
        $this->endpoint = 'v1/payments/authorization/'.$authorization_id.'/capture';
        $response = $this->makeCall($this->getBody($body));
        return $response;
    }

    public function voidAuthorization($authorization_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'POST';
        $this->endpoint = 'v1/payments/authorization/'.$authorization_id.'/void';
        $response = $this->makeCall(null);
        return $response;
    }

    public function showCapture($capture_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'GET';
        $this->endpoint = 'v1/payments/capture/'.$capture_id;
        $response = $this->makeCall(null);
        return $response;
    }

    public function refundCapture($body, $capture_id)
    {
        if (!$this->createAccessToken()) {
            return false;
        }

        $this->action = 'POST';
        $this->endpoint = 'v1/payments/capture/'.$capture_id.'/refund';
        $response = $this->makeCall($this->getBody($body));
        return $response;
    }


    protected function getBody(array $fields)
    {
        $return = true;

        // if fields not empty
        if (empty($fields)) {
            $return = false;
        }

        // if not empty
        if ($return) {
            return json_encode($fields);
        }

        return $return;
    }

    protected function makeCall($body = null, $cnt_type = "application/json", $need_user = false)
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
        if ($need_user) {
            /*
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Accept: application/json",
                "Accept-Language: en_US",
                "Authorization: Basic ".base64_encode($this->clientId.':'.$this->secret)
            ));
            //*/
            curl_setopt($curl, CURLOPT_USERPWD, $this->clientId.':'.$this->secret);
        } else {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Content-type: ".$cnt_type,
                'Content-Length: ' . strlen($body),
                "Authorization: Bearer ".$this->token,
                "PayPal-Partner-Attribution-Id: PrestaShop_Cart_EC",
            ));
        }

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            //die('error occured during curl exec. Additioanl info: ' . curl_errno($curl).':'. curl_error($curl));
        }
        return json_decode($response);
    }
}
