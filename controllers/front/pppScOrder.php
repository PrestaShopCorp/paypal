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

use PayPal\Api\Payment;
include_once _PS_MODULE_DIR_.'paypal/classes/AbstractMethodPaypal.php';

class PaypalPppScOrderModuleFrontController extends ModuleFrontController
{
    public $name = 'paypal';

    public function postProcess()
    {
        $method = AbstractMethodPaypal::load('PPP');
        $paypal = Module::getInstanceByName('paypal');
        $paymentId = Tools::getValue('paymentId');
        try {
            $info = Payment::get($paymentId, $method->_getCredentialsInfo());
        } catch (PayPal\Exception\PayPalConnectionException $e) {
            $decoded_message = Tools::jsonDecode($e->getData());
            $ex_detailed_message = $decoded_message->message;
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $ex_detailed_message)));
        } catch (PayPal\Exception\PayPalInvalidCredentialException $e) {
            $ex_detailed_message = $e->errorMessage();
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $ex_detailed_message)));
        } catch (PayPal\Exception\PayPalMissingCredentialException $e) {
            $ex_detailed_message = $paypal->l('Invalid configuration. Please check your configuration file');
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $ex_detailed_message)));
        } catch (Exception $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $e->getMessage())));
        }

        $payer_info = $info->payer->payer_info;
        $ship_addr = $info->transactions[0]->item_list->shipping_address;

        if ($this->context->cookie->logged) {
            $customer = $this->context->customer;
        } elseif ($id_customer = Customer::customerExists($payer_info->email, true)) {
            $customer = new Customer($id_customer);
        } else {
            $customer = new Customer();
            $customer->email = $payer_info->email;
            $customer->firstname = $payer_info->first_name;
            $customer->lastname = $payer_info->last_name;
            $customer->passwd = Tools::encrypt(Tools::passwdGen());
            $customer->add();
        }
        $id_cart = $this->context->cart->id; // save id cart

        // Login Customer
        $this->context->updateCustomer($customer);

        $this->context->cart = new Cart($id_cart); // Reload cart
        $this->context->cart->id_customer = $customer->id;
        $this->context->cart->update();

        Hook::exec('actionAuthentication', array('customer' => $this->context->customer));
        // Login information have changed, so we check if the cart rules still apply
        CartRule::autoRemoveFromCart($this->context);
        CartRule::autoAddToCart($this->context);
        // END Login
        $addresses = $this->context->customer->getAddresses($this->context->language->id);
        $address_exist = false;
        $count = 1;
        $id_address = 0;
        foreach ($addresses as $address) {

            if ($address['firstname'].' '.$address['lastname'] == $ship_addr->recipient_name
                && $address['address1'] == $ship_addr->line1
                && $address['id_country'] == Country::getByIso($ship_addr->country_code)
                && $address['city'] == $ship_addr->city
                && (empty($ship_addr->state) || $address['id_state'] == State::getIdByName($ship_addr->state))
                && $address['postcode'] == $ship_addr->postal_code
                && (empty($ship_addr->line2) || $address['address2'] == $ship_addr->line2)
            ) {
                $address_exist = true;
                $id_address = $address['id_address'];
                break;
            } else {
                if ((strrpos($address['alias'], 'Paypal_Address')) !== false) {
                    $count = (int)(Tools::substr($address['alias'], -1)) + 1;
                }
            }
        }
        if (!$address_exist) {
            $orderAddress = new Address();
            $pos_separator = strpos($ship_addr->recipient_name,' ');
            $orderAddress->firstname = Tools::substr($ship_addr->recipient_name,0,$pos_separator);
            $orderAddress->lastname = Tools::substr($ship_addr->recipient_name,$pos_separator+1);
            $orderAddress->address1 = $ship_addr->line1;
            if (isset($ship_addr->line2)) {
                $orderAddress->address2 = $ship_addr->line2;
            }
            $orderAddress->id_country = Country::getByIso($ship_addr->country_code);
            $orderAddress->city = $ship_addr->city;
            if (Country::containsStates($orderAddress->id_country)) {
                $orderAddress->id_state = (int) State::getIdByName($ship_addr->state);
            }
            $orderAddress->postcode = $ship_addr->postal_code;
            $orderAddress->id_customer = $customer->id;
            $orderAddress->alias = 'Paypal_Address '.($count);
            $orderAddress->save();
            $id_address = $orderAddress->id;
        }

        $this->context->cart->id_address_delivery = $id_address;
        $this->context->cart->id_address_invoice = $id_address;
        $product = $this->context->cart->getProducts();
        $this->context->cart->setProductAddressDelivery($product[0]['id_product'], $product[0]['id_product_attribute'], $product[0]['id_address_delivery'], $id_address);
        $this->context->cart->save();

        $this->context->cookie->__set('paypal_pSc', $info->id);
        $this->context->cookie->__set('paypal_pSc_payerid', $payer_info->payer_id);
        $this->context->cookie->__set('paypal_pSc_email', $payer_info->email);
        Tools::redirect($this->context->link->getPageLink('order', null, null, array('step'=>2)));
    }
}
