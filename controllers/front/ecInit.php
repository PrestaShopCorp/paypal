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

include_once _PS_MODULE_DIR_.'paypal/classes/AbstractMethodPaypal.php';

class PaypalEcInitModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $paypal = Module::getInstanceByName('paypal');
        $method_ec = AbstractMethodPaypal::load('EC');
        try {
            $url = $method_ec->init(array('use_card'=>Tools::getValue('credit_card')));
        } catch (PayPal\Exception\PPConnectionException $e) {
            $ex_detailed_message = $paypal->l('Error connecting to ') . $e->getUrl();
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $ex_detailed_message)));
        } catch (PayPal\Exception\PPMissingCredentialException $e) {
            $ex_detailed_message = $e->errorMessage();
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $ex_detailed_message)));
        } catch (PayPal\Exception\PPConfigurationException $e) {
            $ex_detailed_message = $paypal->l('Invalid configuration. Please check your configuration file');
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $ex_detailed_message)));
        } catch (Exception $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_code' => $e->getCode())));
        }

        if (Tools::getvalue('getToken')) {
            die($method_ec->token);
        }

        Tools::redirect($url.'&useraction=commit');
    }
}
