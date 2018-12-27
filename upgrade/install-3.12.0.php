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

function upgrade_module_3_12_0($object, $install = false)
{
    if (!Db::getInstance()->Execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'paypal_hss_email_error` (
                `id_paypal_hss_email_error` int(11) NOT NULL AUTO_INCREMENT,
                `id_cart` int(11) NOT NULL,
                `email` varchar(255) NOT NULL,
                PRIMARY KEY (`id_paypal_hss_email_error`)
                ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
        ')) {
        return false;
    }

    if (!$object->registerHook('ActionBeforeCartUpdateQty')) {
        return false;
    }

    /** Paypal HSS  */
    if (!Configuration::get('PAYPAL_OS_AWAITING_HSS')) {
        $order_state = new OrderState();
        $order_state->name = array();

        foreach (Language::getLanguages() as $language) {
            if (Tools::strtolower($language['iso_code']) == 'fr') {
                $order_state->name[$language['id_lang']] = 'En attente de confirmation par PayPal';
            } else {
                $order_state->name[$language['id_lang']] = 'Waiting for validation by PayPal';
            }
        }
        $order_state->send_email = false;
        $order_state->paid = false;
        $order_state->color = '#DDEEFF';
        $order_state->hidden = false;
        $order_state->delivery = false;
        $order_state->logable = false;
        $order_state->invoice = false;

        if ($order_state->add()) {
            $source = dirname(__FILE__).'/../../img/os/'.Configuration::get('PS_OS_PAYPAL').'.gif';
            $destination = dirname(__FILE__).'/../../img/os/'.(int) $order_state->id.'.gif';
            copy($source, $destination);
            Configuration::updateValue('PAYPAL_OS_AWAITING_HSS', (int) $order_state->id);
        } else {
            return false;
        }
    }

    return true;
}
