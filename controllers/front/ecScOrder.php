<?php
/**
 * 2007-2017 PrestaShop
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
 *  @copyright 2007-2017 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

include_once _PS_MODULE_DIR_.'paypal/classes/AbstractMethodPaypal.php';

class PaypalEcScOrderModuleFrontController extends ModuleFrontController
{
    public $name = 'paypal';

    public function postProcess()
    {
        $method = AbstractMethodPaypal::load('EC');
        $info = $method->getInfo(array('TOKEN'=>Tools::getValue('token')));
        if($info['ACK'] != 'Success')
        {
            Tools::redirect($this->context->link->getModuleLink('paypal','error',array('error_code'=>$info['L_ERRORCODE0'])));
        }
        if ($this->context->cookie->logged) {
            $customer = $this->context->customer;
        } elseif ($id_customer = Customer::customerExists($info['EMAIL'], true)) {
            $customer = new Customer($id_customer);
        } else {
            $customer = new Customer();
            $customer->email = $info['EMAIL'];
            $customer->firstname = $info['FIRSTNAME'];
            $customer->lastname = $info['LASTNAME'];
            $customer->passwd = Tools::encrypt(Tools::passwdGen());

            $customer->add();
        }
        $id_cart = $this->context->cart->id; // save id cart

        // Login Customer
        $this->context->updateCustomer($customer);

        $this->context->cart = new Cart($id_cart); // Reload cart
        $this->context->cart->id_customer = $customer->id;
        $this->context->cart->update();

        Hook::exec('actionAuthentication', ['customer' => $this->context->customer]);
        // Login information have changed, so we check if the cart rules still apply
        CartRule::autoRemoveFromCart($this->context);
        CartRule::autoAddToCart($this->context);
        // END Login
        $addresses = $customer->getAddresses($this->context->language->id);
        $orderAddress = new Address();
        foreach ($addresses as $address)
        {
            if($address['alias'] == 'Paypal_Address')
            {
                $orderAddress = new Address($address['id_address']);
                break;
            }
        }
        $orderAddress->firstname = $info['FIRSTNAME'];
        $orderAddress->lastname = $info['LASTNAME'];
        $orderAddress->address1 = $info['PAYMENTREQUEST_0_SHIPTOSTREET'];
        if (isset($info['PAYMENTREQUEST_0_SHIPTOSTREET2'])) {
            $orderAddress->address2 = $info['PAYMENTREQUEST_0_SHIPTOSTREET2'];
        }
        $orderAddress->id_country = Country::getByIso($info['PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE']);
        $orderAddress->city = $info['PAYMENTREQUEST_0_SHIPTOCITY'];
        if (Country::containsStates($orderAddress->id_country)) {
            $orderAddress->id_state = (int) State::getIdByIso($info['PAYMENTREQUEST_0_SHIPTOSTATE'], $address->id_country);
        }

        $orderAddress->postcode = $info['PAYMENTREQUEST_0_SHIPTOZIP'];
        if (isset($info['PAYMENTREQUEST_0_SHIPTOPHONENUM'])) {
            $orderAddress->phone = $info['PAYMENTREQUEST_0_SHIPTOPHONENUM'];
        }

        $orderAddress->id_customer = $customer->id;
        $orderAddress->alias = 'Paypal_Address';

        $orderAddress->save();
        $this->context->cookie->paypal_ecs = true;
        Tools::redirect($this->context->link->getPageLink('order',NULL,NULL,array('step'=>2)));
    }
}
