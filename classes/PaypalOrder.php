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

class PaypalOrder extends ObjectModel
{
    public $id_order;

    public $id_cart;

    public $id_transaction;

    public $id_payment;

    public $client_token;

    public $payment_method;

    public $currency;

    public $total_paid;

    public $payment_status;

    public $total_prestashop;

    public $method;

    public $payment_tool;

    public $date_add;

    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'paypal_order',
        'primary' => 'id_paypal_order',
        'multilang' => false,
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_cart' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId'),
            'id_transaction' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'id_payment' => array('type' => self::TYPE_STRING),
            'client_token' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'payment_method' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'currency' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'total_paid' => array('type' => self::TYPE_FLOAT),
            'payment_status' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'total_prestashop' => array('type' => self::TYPE_FLOAT),
            'method' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'payment_tool' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
        )
    );


    public static function getIdOrderByTransactionId($id_transaction)
    {
        $sql = 'SELECT `id_order`
			FROM `'._DB_PREFIX_.'paypal_order`
			WHERE `id_transaction` = \''.pSQL($id_transaction).'\'';
        $result = Db::getInstance()->getRow($sql);
        if ($result != false) {
            return (int) $result['id_order'];
        }
        return 0;
    }

    public static function getOrderById($id_order)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'paypal_order`
			WHERE `id_order` = '.(int) $id_order
        );
    }

    public static function loadByOrderId($id_order)
    {
        $sql = new DbQuery();
        $sql->select('id_paypal_order');
        $sql->from('paypal_order');
        $sql->where('id_order = '.(int)$id_order);
        $id_paypal_order = Db::getInstance()->getValue($sql);
        return new self($id_paypal_order);
    }

    public static function getPaypalBtOrdersIds()
    {
        $ids = array();
        $sql = new DbQuery();
        $sql->select('id_transaction');
        $sql->from('paypal_order');
        $sql->where('method = "BT" AND payment_method = "sale" AND payment_tool = "paypal_account" AND (payment_status = "settling" OR payment_status = "submitted_for_settlement")');
        $results = Db::getInstance()->executeS($sql);
        foreach ($results as $result) {
            $ids[] =  $result['id_transaction'];
        }
        return $ids;
    }
}
