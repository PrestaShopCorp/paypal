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

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_4_4_0($module)
{
    Configuration::updateValue('PAYPAL_VAULTING', 0);
    Configuration::updateValue('PAYPAL_CONFIG_BRAND', '');
    Configuration::updateValue('PAYPAL_CONFIG_LOGO', '');

    if (!$module->registerHook('displayMyAccountBlock')
        || !$module->registerHook('displayCustomerAccount'))
    {
        return false;
    }

    $sql = array();

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "paypal_customer` (
              `id_paypal_customer` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
              `id_customer` INT(11),
              `reference` VARCHAR(55),
              `method` VARCHAR(55),
              `date_add` DATETIME,
              `date_upd` DATETIME
        ) ENGINE = " . _MYSQL_ENGINE_ ;

    $sql[] = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "paypal_vaulting` (
              `id_paypal_vaulting` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
              `id_paypal_customer` INT(11),
              `token` VARCHAR(255),
              `name_card` VARCHAR(255),
              `info_card` VARCHAR(255),
              `method` VARCHAR(255),
              `date_add` DATETIME,
              `date_upd` DATETIME
        ) ENGINE = " . _MYSQL_ENGINE_ ;


    foreach ($sql as $q) {
        if (!DB::getInstance()->execute($q)) {
            return false;
        }
    }

    return true;
}
