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

abstract class AbstractMethodPaypal
{
    // Force les classes filles à définir cette méthode
    abstract public function init($params);
    abstract public function validation();
    abstract public function confirmCapture();
    abstract public function check();
    abstract public function refund();
    abstract public function setConfig($params);
    abstract public function getConfig(Paypal $module);
    abstract public function void($params);
    abstract public function partialRefund($params);

    public static function load($method)
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $method) && file_exists(_PS_MODULE_DIR_.'paypal/classes/Method'.$method.'.php')) {
            include_once _PS_MODULE_DIR_.'paypal/classes/Method'.$method.'.php';
            $method_class = 'Method'.$method;
            return new $method_class();
        }
    }
}
