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

class PaypalCapture extends ObjectModel
{
    public $id_capture;

    public $id_paypal_order;

    public $capture_amount;

    public $result;

    public $date_add;

    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'paypal_capture',
        'primary' => 'id_paypal_capture',
        'multilang' => false,
        'fields' => array(
            'id_capture' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'id_paypal_order' => array('type' => self::TYPE_INT),
            'capture_amount' => array('type' => self::TYPE_FLOAT),
            'result' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDateFormat'),
        )
    );


    public static function loadByOrderPayPalId($orderPayPalId)
    {
        $sql = new DbQuery();
        $sql->select('id_paypal_capture');
        $sql->from('paypal_capture');
        $sql->where('id_paypal_order = '.(int)$orderPayPalId);
        $id_paypal_capture = Db::getInstance()->getValue($sql);

        return new self($id_paypal_capture);
    }

    public static function getByOrderId($id_order)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('paypal_order', 'po');
        $sql->innerJoin('paypal_capture', 'pc', 'po.`id_paypal_order` = pc.`id_paypal_order`');
        $sql->where('po.id_order = '.(int)$id_order);
        return Db::getInstance()->getRow($sql);
    }

    public static function updateCapture($transaction_id, $amount, $status, $id_paypal_order)
    {
        Db::getInstance()->update(
            'paypal_capture',
            array(
                'id_capture' => pSQL($transaction_id),
                'capture_amount' => (float)$amount,
                'result' => pSQL($status),
            ),
            'id_paypal_order = '.(int)$id_paypal_order
        );
    }
}
