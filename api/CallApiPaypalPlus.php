<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once(_PS_MODULE_DIR_.'paypal/api/ApiPaypalPlus.php');

define('URL_PPP_CREATE_TOKEN', '/v1/oauth2/token');
define('URL_PPP_CREATE_PAYMENT', '/v1/payments/payment');
define('URL_PPP_LOOK_UP', '/v1/payments/payment/');
define('URL_PPP_EXECUTE_PAYMENT', '/v1/payments/payment/');
define('URL_PPP_EXECUTE_REFUND', '/v1/payments/sale/');

class CallApiPaypalPlus extends ApiPaypalPlus
{
    protected $cart     = NULL;
    protected $customer = NULL;

    public function setParams($params)
    {
        $this->cart     = new Cart($params['cart']->id);
        $this->customer = new Customer($params['cookie']->id_customer);
    }

    public function getApprovalUrl()
    {
        /*
         * Récupération du token
         */
        $accessToken = $this->getToken(URL_PPP_CREATE_TOKEN, array('grant_type' => 'client_credentials'));

        $result = json_decode($this->createPayment($this->customer, $this->cart, $accessToken));

        if (isset($result->links)) {

            foreach ($result->links as $link) {

                if ($link->rel == 'approval_url') {
                    return $link->href;
                }
            }
        } else {
            return false;
        }
    }

    public function lookUpPayment($paymentId)
    {

        if ($paymentId == 'NULL') {
            return false;
        }

        $accessToken = $this->refreshToken();

        $header = array(
            'Content-Type:application/json',
            'Authorization:Bearer '.$accessToken
        );

        return $this->sendByCURL(URL_PPP_LOOK_UP.$paymentId, false, $header);
    }

    public function executePayment($payer_id, $paymentId)
    {

        if ($payer_id == 'NULL' || $paymentId == 'NULL') {
            return false;
        }

        $accessToken = $this->refreshToken();

        $header = array(
            'Content-Type:application/json',
            'Authorization:Bearer '.$accessToken
        );

        $data = array('payer_id' => $payer_id);


        return $this->sendByCURL(URL_PPP_EXECUTE_PAYMENT.$paymentId.'/execute/', json_encode($data), $header);
    }
	
	public function executeRefund($paymentId, $data)
    {

        if ($paymentId == 'NULL' || !is_object($data)) {
            return false;
        }

        $accessToken = $this->refreshToken();
		
        $header = array(
            'Content-Type:application/json',
            'Authorization:Bearer '.$accessToken
        );

        return $this->sendByCURL(URL_PPP_EXECUTE_REFUND.$paymentId.'/refund', json_encode($data), $header);
    }
}