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

class PaypalScInitModuleFrontController extends ModuleFrontController
{
    public $name = 'paypal';

    public function postProcess()
    {
        switch (Tools::getValue('source_page')) {
            case 'cart':
                $this->prepareCart();
                break;
            case 'product':
                $this->prepareProduct();
                break;
            default:
        }

        $method = AbstractMethodPaypal::load(Configuration::get('PAYPAL_METHOD'));

        try {
            $response = $method->init(array('use_card'=>0, 'short_cut' => 1));
        } catch (Exception $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink('paypal', 'error', array('error_msg' => $e->getMessage())));
        }

        $method->processCheckoutSc($response);
    }

    public function prepareCart()
    {
        if (Tools::getValue('checkAvailability')) {
            if ($this->context->cart->checkQuantities()) {
                die(Tools::jsonEncode(1));
            } else {
                die(Tools::jsonEncode(0));
            }
        }
    }

    public function prepareProduct()
    {
        if (Tools::getValue('checkAvailability')) {
            $product = new Product(Tools::getValue('id_product'));
            $product->id_product_attribute = Tools::getValue('product_attribute') != 0 ? Tools::getValue('product_attribute') : Tools::getValue('id_product_attribute');
            if ($product->checkQty(Tools::getValue('quantity'))) {
                die(Tools::jsonEncode(1));
            } else {
                die(Tools::jsonEncode(0));
            }
        }

        if (empty($this->context->cart->id)) {
            $this->context->cart->add();
            $this->context->cookie->id_cart = $this->context->cart->id;
            $this->context->cookie->write();
        } else {
            // delete all product in cart
            $products = $this->context->cart->getProducts();
            foreach ($products as $product) {
                $this->context->cart->deleteProduct($product['id_product'], $product['id_product_attribute'], $product['id_customization'], $product['id_address_delivery']);
            }
        }

        if (Tools::getValue('combination')) {
            // build group for search product attribute
            $temp_group = explode('|', Tools::getValue('combination'));
            $group = array();
            foreach ($temp_group as $item) {
                $temp = explode(':', $item);
                $group[$temp[0]] = $temp[1];
            }
            $this->context->cart->updateQty(Tools::getValue('quantity'), Tools::getValue('id_product'), Product::getIdProductAttributesByIdAttributes(Tools::getValue('id_product'), $group));
        } else {
            $this->context->cart->updateQty(Tools::getValue('quantity'), Tools::getValue('id_product'));
        }
    }
}
