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
 *  @version  Release: $Revision: 13573 $
 *  International Registered Trademark & Property of PrestaShop SA
 */

/**
 * @since 1.5.0
 */

class PayPalSubmitModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $this->paypal = new PayPal();
        $this->context = Context::getContext();
        $this->id_module = (int) Tools::getValue('id_module');
        $id_cart = Tools::getValue('id_cart');
        $res = @fopen(dirname(__FILE__).'/../../'.$id_cart.'.txt', 'x');
        if (!$res) {
            while (file_exists(dirname(__FILE__).'/../../'.$id_cart.'.txt')) {
                sleep(1);
            }
        } else {
            $id_order = Order::getOrderByCartId((int) $id_cart);
            if (!$id_order) {
                $this->createTmpOrder();
            }
        }
        if ($res) {
            fclose($res);
            unlink(dirname(__FILE__).'/../../'.$id_cart.'.txt');
        }
        $id_order = Order::getOrderByCartId((int) $id_cart);
        $this->id_order = (int) $id_order;
        $order = new Order($id_order);
        if ($order->current_state == Configuration::get('PAYPAL_OS_AWAITING_HSS')) {
            $this->displayAwaitingConfirmation($order);
        } else {
            $this->displayConfirmation($order);
        }
    }

    public function createTmpOrder()
    {
        $id_cart = Tools::getValue('id_cart');
        $cart = Context::getContext()->cart;
        if ($cart->id != $id_cart) {
            Tools::redirect($this->context->link->getPageLink('history'));
        }

        $order_total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $customer = new Customer((int) $cart->id_customer);
        $paypal = Module::getInstanceByName('paypal');
        $paypal->validateOrder((int)$id_cart, Configuration::get('PAYPAL_OS_AWAITING_HSS'), $order_total, $paypal->displayName, null, array(), $cart->id_currency, false, $customer->secure_key, Context::getContext()->shop);
    }

    public function displayConfirmation($order)
    {
        // fix security issue
        if ($order->id_cart != Tools::getValue('id_cart') || $order->secure_key != Tools::getValue('key')) {
            Tools::redirect($this->context->link->getPageLink('history'));
        }

        $order_state = new OrderState($order->current_state);
        $paypal_order = PayPalOrder::getOrderById($this->id_order);

        if ($order_state->template[$this->context->language->id] == 'payment_error') {
            $this->context->smarty->assign(
                array(
                    'message' => $order_state->name[$this->context->language->id],
                    'logs' => array(
                        $this->paypal->l('An error occurred while processing payment.'),
                    ),
                    'order' => $paypal_order,
                    'price' => Tools::displayPrice($paypal_order['total_paid'], $this->context->currency),
                )
            );

            return $this->setTemplate('error.tpl');
        }

        $order_currency = new Currency((int) $order->id_currency);
        $display_currency = new Currency((int) $this->context->currency->id);

        $price = Tools::convertPriceFull($paypal_order['total_paid'], $order_currency, $display_currency);

        $this->context->smarty->assign(
            array(
                'is_guest' => (($this->context->customer->is_guest) || $this->context->customer->id == false),
                'order' => $paypal_order,
                'price' => Tools::displayPrice($price, $this->context->currency->id),
                'HOOK_ORDER_CONFIRMATION' => $this->displayOrderConfirmation(),
                'HOOK_PAYMENT_RETURN' => $this->displayPaymentReturn(),
            )
        );
        if (version_compare(_PS_VERSION_, '1.5', '>')) {
            $this->context->smarty->assign(array(
                'reference_order' => Order::getUniqReferenceOf($paypal_order['id_order']),
            ));
        }

        if (($this->context->customer->is_guest) || $this->context->customer->id == false) {
            $this->context->smarty->assign(
                array(
                    'id_order' => (int) $this->id_order,
                    'id_order_formatted' => sprintf('#%06d', (int) $this->id_order),
                    'order_reference' => $order->reference,
                )
            );

            /* If guest we clear the cookie for security reason */
            $this->context->customer->mylogout();
        }

        $this->module->assignCartSummary();
        if ($this->context->getMobileDevice() == true) {
            return $this->setTemplate('order-confirmation-mobile.tpl');
        } else {
            return $this->setTemplate('order-confirmation.tpl');
        }
    }

    public function displayAwaitingConfirmation($order)
    {
        $this->context->smarty->assign(
            array(
                'order' => $order,
                'is_guest' => (($this->context->customer->is_guest) || $this->context->customer->id == false),
                'price' => Tools::displayPrice($order->total_paid, $this->context->currency->id),
                'HOOK_ORDER_CONFIRMATION' => $this->displayOrderConfirmation(),
                'HOOK_PAYMENT_RETURN' => $this->displayPaymentReturn(),
            )
        );
        return $this->setTemplate('order-awaiting.tpl');
    }

    private function displayHook()
    {
        if (Validate::isUnsignedId($this->id_order) && Validate::isUnsignedId($this->id_module)) {
            $order = new Order((int) $this->id_order);
            $currency = new Currency((int) $order->id_currency);

            if (Validate::isLoadedObject($order)) {
                $params = array();
                $params['objOrder'] = $order;
                $params['currencyObj'] = $currency;
                $params['currency'] = $currency->sign;
                $params['total_to_pay'] = $order->getOrdersTotalPaid();

                return $params;
            }
        }

        return false;
    }

    /**
     * Execute the hook displayPaymentReturn
     */
    public function displayPaymentReturn()
    {
        $params = $this->displayHook();

        if ($params && is_array($params)) {
            return Hook::exec('displayPaymentReturn', $params, (int) $this->id_module);
        }

        return false;
    }

    /**
     * Execute the hook displayOrderConfirmation
     */
    public function displayOrderConfirmation()
    {
        $params = $this->displayHook();

        if ($params && is_array($params)) {
            return Hook::exec('displayOrderConfirmation', $params);
        }

        return false;
    }
}
