<?php
/**
 * 2007-2015 PrestaShop
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
 *  @copyright 2007-2015 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class TLSVerificator
{
    private $tls_version;
    private $url;
    private $paypal;

    public function __construct($check, $paypal)
    {
        $this->url = 'https://www.howsmyssl.com/a/check';
        $this->paypal = $paypal;
        if ($check) {
            $this->makeCheck();
        }

    }

    public function getVersion()
    {
        return $this->tls_version;
    }

    public function makeCheck()
    {

        if (function_exists('curl_exec')) {
            $tls_check = $this->_connectByCURL($this->url);
        } else {
            $tls_check = Tools::file_get_contents($this->url);
        }

        if ($tls_check == false) {
            $this->tls_version = false; // Not detectable
            return false;
        }

        $tls_check = Tools::jsonDecode($tls_check);
        if ($tls_check->tls_version == 'TLS 1.2') {
            $this->tls_version = 1.2;
        } else {
            $this->tls_version = 1;
        }

    }

    /************************************************************/
    /********************** CONNECT METHODS *********************/
    /************************************************************/
    private function _connectByCURL($url, $http_header = false)
    {
        $ch = @curl_init();

        if (!$ch) {
            $this->_logs[] = $this->paypal->l('Connect failed with CURL method');
        } else {
            $this->_logs[] = $this->paypal->l('Connect with CURL method successful');
            $this->_logs[] = '<b>'.$this->paypal->l('Sending this params:').'</b>';
            $this->_logs[] = '<b>'.$url.'</b>';

            @curl_setopt($ch, CURLOPT_URL, $url);

            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_HEADER, false);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            @curl_setopt($ch, CURLOPT_VERBOSE, false);
            if ($http_header) {
                @curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
            }

            $result = @curl_exec($ch);

            if (!$result) {
                $this->_logs[] = $this->paypal->l('Send with CURL method failed ! Error:').' '.curl_error($ch);
            } else {
                $this->_logs[] = $this->paypal->l('Send with CURL method successful');
            }

            @curl_close($ch);
        }
        return $result ? $result : false;
    }
}
