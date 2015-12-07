<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ApiPaypalPlus
 *
 * author Stef
 */
class ApiPaypalPlus
{
    /*     * ********************************************************* */
    /*     * ******************** CONNECT METHODS ******************** */
    /*     * ********************************************************* */

    public function __construct()
    {
        if (class_exists('Context')) {
            $this->context = Context::getContext();
        } else {
            global $smarty, $cookie;
            $this->context         = new StdClass();
            $this->context->smarty = $smarty;
            $this->context->cookie = $cookie;
        }
    }

    protected function sendByCURL($url, $body, $http_header = false, $identify = false)
    {
        $ch = curl_init();

        if ($ch) {

            curl_setopt($ch, CURLOPT_URL, 'https://api.sandbox.paypal.com'.$url);

            if ($identify) {
                curl_setopt($ch, CURLOPT_USERPWD,
                    Configuration::get('PAYPAL_PLUS_CLIENT_ID').':'.Configuration::get('PAYPAL_PLUS_SECRET'));
            }

            if ($http_header) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
            }
            if ($body) {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($identify) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
                else curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSLVERSION, defined('CURL_SSLVERSION_TLSv1') ? CURL_SSLVERSION_TLSv1 : 1);
            curl_setopt($ch, CURLOPT_VERBOSE, false);

            $result = curl_exec($ch);

            curl_close($ch);
        }

        return $result;
    }

    public function getToken($url, $body)
    {
        $result = $this->sendByCURL($url, $body, false, true);
        /*
         * Init variable
         */
        $oPayPalToken = json_decode($result);

        $time_max     = time() + $oPayPalToken->expires_in;
        $access_token = $oPayPalToken->access_token;


        /*
         * Set Token in Cookie
         */
        $this->context->cookie->__set('paypal_access_token_time_max', $time_max);
        $this->context->cookie->__set('paypal_access_token_access_token', $access_token);
        $this->context->cookie->write();

        return $access_token;
    }

    public function refreshToken()
    {
        if ($this->context->cookie->paypal_access_token_time_max < time()) {
            return $this->getToken(URL_PPP_CREATE_TOKEN, array('grant_type' => 'client_credentials'));
        } else {
            return $this->context->cookie->paypal_access_token_access_token;
        }
    }

    private function _createObjectPayment($customer, $cart)
    {
        /*
         * Init Variable
         */
        $oCurrency = new Currency($cart->id_currency);

        if (version_compare(_PS_VERSION_, '1.5', '<'))
                $totalShippingCostWithoutTax = $cart->getOrderShippingCost(null, false);
        else $totalShippingCostWithoutTax = $cart->getTotalShippingCost(null, false);

        $totalCartWithTax    = $cart->getOrderTotal(true);
        $totalCartWithoutTax = $cart->getOrderTotal(false);
        $total_tax           = $totalCartWithTax - $totalCartWithoutTax;

        if ($cart->gift) {
            if (version_compare(_PS_VERSION_, '1.5.3.0', '>=')) $giftWithoutTax = $cart->getGiftWrappingPrice(false);
            else $giftWithoutTax = (float) (Configuration::get('PS_GIFT_WRAPPING_PRICE'));
        }else{
            $giftWithoutTax = 0;
        }

        $cartItems = $cart->getProducts();

        $shop_url = PayPal::getShopDomainSsl(true, true);

        /*
         * Création de l'obj à envoyer à Paypal
         */
        $payer                 = new stdClass();
        $payer->payment_method = "paypal";

        /* Item */
        foreach ($cartItems as $cartItem) {

            $item           = new stdClass();
            $item->name     = $cartItem['name'];
            $item->currency = $oCurrency->iso_code;
            $item->quantity = $cartItem['quantity'];
            $item->price    = number_format(round($cartItem['price'], 2), 2);
            $item->tax      = number_format(round($cartItem['price_wt'] - $cartItem['price'], 2), 2);
            $aItems[]       = $item;
            unset($item);
        }

        /* ItemList */
        $itemList        = new stdClass();
        $itemList->items = $aItems;

        /* Detail */
        $details               = new stdClass();
        $details->shipping     = number_format($totalShippingCostWithoutTax, 2);
        $details->tax          = number_format($total_tax, 2);
        $details->handling_fee = number_format($giftWithoutTax, 2);
        $details->subtotal     = number_format($totalCartWithoutTax - $totalShippingCostWithoutTax - $giftWithoutTax, 2);

        /* Amount */
        $amount           = new stdClass();
        $amount->total    = number_format($totalCartWithTax, 2);
        $amount->currency = $oCurrency->iso_code;
        $amount->details  = $details;

        /* Transaction */
        $transaction              = new stdClass();
        $transaction->amount      = $amount;
        $transaction->item_list   = $itemList;
        $transaction->description = "Payment description";

        /* Redirecte Url */


        $redirectUrls             = new stdClass();
        $redirectUrls->cancel_url = $shop_url._MODULE_DIR_.'paypal/paypal_plus/submit.php?id_cart='.(int) $cart->id;
        $redirectUrls->return_url = $shop_url._MODULE_DIR_.'paypal/paypal_plus/submit.php?id_cart='.(int) $cart->id;

        /* Payment */
        $payment                = new stdClass();
        $payment->transactions  = array($transaction);
        $payment->payer         = $payer;
        $payment->intent        = "sale";
        $payment->redirect_urls = $redirectUrls;

        return $payment;
    }

    protected function createPayment($customer, $cart, $access_token)
    {

        $data = $this->_createObjectPayment($customer, $cart);

        $header = array(
            'Content-Type:application/json',
            'Authorization:Bearer '.$access_token
        );
       
        $result = $this->sendByCURL(URL_PPP_CREATE_PAYMENT, json_encode($data), $header);
       
        return $result;
    }
}