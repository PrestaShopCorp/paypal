<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
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
 *  @copyright 2007-2016 PrestaShop SA
 *  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

require_once _PS_MODULE_DIR_.'paypal/api/ApiPaypalPlus.php';

define('URL_PPP_CREATE_TOKEN', '/v1/oauth2/token');
define('URL_PPP_CREATE_PAYMENT', '/v1/payments/payment');
define('URL_PPP_LOOK_UP', '/v1/payments/payment/');
define('URL_PPP_WEBPROFILE', '/v1/payment-experience/web-profiles');
define('URL_PPP_EXECUTE_PAYMENT', '/v1/payments/payment/');
define('URL_PPP_EXECUTE_REFUND', '/v1/payments/sale/');

class CallApiPaypalPlus extends ApiPaypalPlus
{
    protected $cart = null;
    protected $customer = null;

    public function setParams($params)
    {
        $this->cart = new Cart($params['cart']->id);
        $this->customer = new Customer($params['cookie']->id_customer);
    }

    public function getApprovalUrl()
    {
        /*
         * Récupération du token
         */
        $accessToken = $this->getToken(URL_PPP_CREATE_TOKEN, array('grant_type' => 'client_credentials'));

        if ($accessToken != false) {

            $result = Tools::jsonDecode($this->createPayment($this->customer, $this->cart, $accessToken));

            if (isset($result->links)) {

                foreach ($result->links as $link) {

                    if ($link->rel == 'approval_url') {
                        return $link->href;
                    }
                }
            }
        }
        return false;
    }

    public function lookUpPayment($paymentId)
    {

        if ($paymentId == 'NULL') {
            return false;
        }

        $accessToken = $this->refreshToken();

        $header = array(
            'Content-Type:application/json',
            'Authorization:Bearer '.$accessToken,
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
            'Authorization:Bearer '.$accessToken,
        );

        $data = array('payer_id' => $payer_id);

        return $this->sendByCURL(URL_PPP_EXECUTE_PAYMENT.$paymentId.'/execute/', Tools::jsonEncode($data), $header);
    }

    public function executeRefund($paymentId, $data)
    {

        if ($paymentId == 'NULL' || !is_object($data)) {
            return false;
        }

        $accessToken = $this->refreshToken();

        $header = array(
            'Content-Type:application/json',
            'Authorization:Bearer '.$accessToken,
        );

        return $this->sendByCURL(URL_PPP_EXECUTE_REFUND.$paymentId.'/refund', Tools::jsonEncode($data), $header);
    }
}
