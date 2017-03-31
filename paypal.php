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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}
include_once 'classes/AbstractMethodPaypal.php';
include_once 'classes/PaypalCapture.php';
include_once 'classes/PaypalOrder.php';


class PayPal extends PaymentModule
{
    public $express_checkout;
    public $message;
    public $amount_paid_paypal;

    public function __construct()
    {
        $this->name = 'paypal';
        $this->tab = 'payments_gateways';
        $this->version = '4.0.1';
        $this->author = 'PrestaShop';
        $this->module_key = '336225a5988ad434b782f2d868d7bfcd';
        $this->is_eu_compatible = 1;
        $this->ps_versions_compliancy = array('min' => '1.7');
        $this->controllers = array('payment', 'validation');
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'radio';

        parent::__construct();

        $this->displayName = $this->l('PayPal');
        $this->description = $this->l('Benefit from PayPal’s complete payments platform and grow your business online, on mobile and internationally. Accept credit cards, debit cards and PayPal payments.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
        $this->express_checkout = $this->l('PayPal Express Checkout ');
    }

    public function install()
    {
        // Install default
        if (!parent::install()) {
            return false;
        }
        // install DataBase
        if (!$this->installSQL()) {
            return false;
        }
        // Registration hook
        if (!$this->registrationHook()) {
            return false;
        }

        // Registration order status
        if (!$this->installOrderState()) {
            return false;
        }

        if (!Configuration::updateValue('PAYPAL_SANDBOX', 0)
            || !Configuration::updateValue('PAYPAL_API_INTENT', 'sale')
            || !Configuration::updateValue('PAYPAL_API_ADVANTAGES', 1)
            || !Configuration::updateValue('PAYPAL_API_CARD', 0)
            || !Configuration::updateValue('PAYPAL_SANDBOX_CLIENTID', '')
            || !Configuration::updateValue('PAYPAL_SANDBOX_SECRET', '')
            || !Configuration::updateValue('PAYPAL_LIVE_CLIENTID', '')
            || !Configuration::updateValue('PAYPAL_LIVE_SECRET', '')
            || !Configuration::updateValue('PAYPAL_METHOD', '')
        ) {
            return false;
        }

        return true;
    }
    
    /**
     * Install DataBase table
     * @return boolean if install was successfull
     */
    private function installSQL()
    {
        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."paypal_order` (
              `id_paypal_order` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
              `id_order` INT(11),
              `id_cart` INT(11),
              `id_transaction` VARCHAR(55),
              `id_payment` VARCHAR(55),
              `client_token` VARCHAR(255),
              `payment_method` VARCHAR(255),
              `currency` VARCHAR(21),
              `total_paid` FLOAT(11),
              `payment_status` VARCHAR(255),
              `total_prestashop` FLOAT(11),
              `date_add` DATETIME,
              `date_upd` DATETIME
        ) ENGINE = "._MYSQL_ENGINE_;

        $sql[] = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "paypal_capture` (
              `id_paypal_capture` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
              `id_capture` VARCHAR(55),
              `id_paypal_order` INT(11),
              `capture_amount` FLOAT(11),
              `result` VARCHAR(255),
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

    /**
     * [registrationHook description]
     * @return [type] [description]
     */
    private function registrationHook()
    {
        if (!$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('displayOrderConfirmation')
            || !$this->registerHook('displayAdminOrder')
            || !$this->registerHook('actionOrderStatusPostUpdate')
            || !$this->registerHook('actionValidateOrder')
            || !$this->registerHook('actionOrderStatusUpdate')
        ) {
            return false;
        }


        return true;
    }

    /**
     * Create order state
     * @return boolean
     */
    public function installOrderState()
    {
        if (!Configuration::get('PAYPAL_OS_WAITING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('PAYPAL_OS_WAITING')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'fr') {
                    $order_state->name[$language['id_lang']] = 'En attente de paiement PayPal';
                } else {
                    $order_state->name[$language['id_lang']] = 'Awaiting for PayPal payment';
                }
            }
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_.'paypal/views/img/os_paypal.png';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }

            Configuration::updateValue('PAYPAL_OS_WAITING', (int) $order_state->id);
        }
        return true;
    }

    public function uninstall()
    {
        $config = array(
            'PAYPAL_SANDBOX',
            'PAYPAL_API_INTENT',
            'PAYPAL_API_ADVANTAGES',
            'PAYPAL_API_CARD',
            'PAYPAL_SANDBOX_CLIENTID',
            'PAYPAL_SANDBOX_SECRET',
            'PAYPAL_LIVE_CLIENTID',
            'PAYPAL_LIVE_SECRET',
            'PAYPAL_METHOD',
        );
        foreach ($config as $var) {
            Configuration::deleteByName($var);
        }

        //Uninstall DataBase
        if (!$this->uninstallSQL()) {
            return false;
        }

        // Uninstall default
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    /**
     * Uninstall DataBase table
     * @return boolean if install was successfull
     */
    private function uninstallSQL()
    {
        $sql = array();

        $sql[] = "DROP TABLE IF EXISTS `"._DB_PREFIX_."paypal_capture`";

        $sql[] = "DROP TABLE IF EXISTS `"._DB_PREFIX_."paypal_order`";

        foreach ($sql as $q) {
            if (!DB::getInstance()->execute($q)) {
                return false;
            }
        }

        return true;
    }

    public function getUrl()
    {
        if (Configuration::get('PAYPAL_SANDBOX')) {
            return 'https://www.sandbox.paypal.com/';
        } else {
            return 'https://www.paypal.com/';
        }
    }

    public function getContent()
    {
        $this->_postProcess();
        $return_url = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        /*
        $PartnerboardingURL = "";
        if ((Configuration::get('PAYPAL_SANDBOX') && !Configuration::get('PAYPAL_LIVE_ACCESS'))
        || (!Configuration::get('PAYPAL_SANDBOX') && !Configuration::get('PAYPAL_SANDBOX_ACCESS'))) {
            $partner_info = $this->getUrlOnboarding(Configuration::get('PAYPAL_METHOD'));
            if (!$partner_info->error) {
                $PartnerboardingURL = $partner_info->data->link;
            }
        }
        */

        if (Configuration::get('PAYPAL_SANDBOX')) {
            if (Configuration::get('PAYPAL_SANDBOX_CLIENTID') != '' && Configuration::get('PAYPAL_SANDBOX_SECRET') != '') {
                $ec_card_active = Configuration::get('PAYPAL_API_CARD');
                $ec_paypal_active = !Configuration::get('PAYPAL_API_CARD');
            } else {
                $ec_card_active = false;
                $ec_paypal_active = false;
            }
        } else {
            if (Configuration::get('PAYPAL_LIVE_CLIENTID') != '' && Configuration::get('PAYPAL_LIVE_SECRET') != '') {
                $ec_card_active = Configuration::get('PAYPAL_API_CARD');
                $ec_paypal_active = !Configuration::get('PAYPAL_API_CARD');
            } else {
                $ec_card_active = false;
                $ec_paypal_active = false;
            }
        }

        $this->context->smarty->assign(array(
            'path' => $this->_path,
            //'path_ajax_sandbox' => $this->context->link->getAdminLink('AdminModules',true,array(),array('configure'=>'paypal')),
            'country' => Country::getNameById($this->context->language->id, $this->context->country->id),
            'localization' => $this->context->link->getAdminLink('AdminLocalization', true),
            'preference' => $this->context->link->getAdminLink('AdminPreferences', true),
            'active_products' => $this->express_checkout,
            'return_url' => $return_url,
            'PAYPAL_SANDBOX_CLIENTID' => Configuration::get('PAYPAL_SANDBOX_CLIENTID'),
            'PAYPAL_SANDBOX_SECRET' => Configuration::get('PAYPAL_SANDBOX_SECRET'),
            'PAYPAL_LIVE_CLIENTID' => Configuration::get('PAYPAL_LIVE_CLIENTID'),
            'PAYPAL_LIVE_SECRET' => Configuration::get('PAYPAL_LIVE_SECRET'),
            'paypal_card' => Configuration::get('PAYPAL_API_CARD'),
            'ec_card_active' => $ec_card_active,
            'ec_paypal_active' => $ec_paypal_active,
            'need_rounding' => Configuration::get('PS_ROUND_TYPE') == Order::ROUND_ITEM ? 0 : 1,
        ));
        $this->context->controller->addCSS($this->_path.'views/css/paypal-bo.css', 'all');

        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('MODULE SETTINGS'),
                'icon' => 'icon-cogs',
            ),

            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Activate sandbox'),
                    'name' => 'paypal_sandbox',
                    'is_bool' => true,
                    'hint' => $this->l('Set up a test environment in your PayPal account (only if you are a developer)'),
                    'values' => array(
                        array(
                            'id' => 'paypal_sandbox_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ),
                        array(
                            'id' => 'paypal_sandbox_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        )
                    ),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Payment action'),
                    'name' => 'paypal_intent',
                    'desc' => $this->l(''),
                    'hint' => $this->l('Sale: the money moves instantly from the buyer’s account to the seller’s account at the time of payment. Authorization/capture: The authorized mode is a deferred mode of payment that requires the funds to be collected manually when you want to transfer the money. This mode is used if you want to ensure that you have the merchandise before depositing the money, for example. Be careful, you have 29 days to collect the funds.'),
                    'options' => array(
                        'query' => array(
                            array(
                                'id' => 'sale',
                                'name' => $this->l('Sale')
                            ),
                            array(
                                'id' => 'authorize',
                                'name' => $this->l('Authorize')
                            )
                        ),
                        'id' => 'id',
                        'name' => 'name'
                    ),
                ),
                /*
                array(
                    'type' => 'switch',
                    'label' => $this->l('Accept credit and debit card payment'),
                    'name' => 'paypal_card',
                    'is_bool' => true,
                    'hint' => $this->l('Your customers can pay with debit and credit cards as well as local payment systems whether or not they use PayPal'),
                    'values' => array(
                        array(
                            'id' => 'paypal_card_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ),
                        array(
                            'id' => 'paypal_card_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        )
                    ),
                ),
                */
                array(
                    'type' => 'switch',
                    'label' => $this->l('Show PayPal benefits to your customers'),
                    'name' => 'paypal_show_advantage',
                    'desc' => $this->l(''),
                    'is_bool' => true,
                    'hint' => $this->l('You can increase your conversion rate by presenting PayPal benefits to your customers on payment methods selection page.'),
                    'values' => array(
                        array(
                            'id' => 'paypal_show_advantage_on',
                            'value' => 1,
                            'label' => $this->l('Enabled'),
                        ),
                        array(
                            'id' => 'paypal_show_advantage_off',
                            'value' => 0,
                            'label' => $this->l('Disabled'),
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right button',
            ),
        );

        $fields_value = array(
            'paypal_sandbox' => Configuration::get('PAYPAL_SANDBOX'),
            'paypal_intent' => Configuration::get('PAYPAL_API_INTENT'),
            'paypal_card' => Configuration::get('PAYPAL_API_CARD'),
            'paypal_show_advantage' => Configuration::get('PAYPAL_API_ADVANTAGES'),
        );
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->submit_action = 'paypal_config';
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->tpl_vars = array(
            'fields_value' => $fields_value,
            'id_language' => $this->context->language->id,
            'back_url' => $this->context->link->getAdminLink('AdminModules')
                .'&configure='.$this->name
                .'&tab_module='.$this->tab
                .'&module_name='.$this->name
                .'#paypal_params'
        );
        $form = $helper->generateForm($fields_form);
        if (count($this->_errors)) {
            $this->message .= $this->displayError($this->_errors);
        } elseif (Configuration::get('PAYPAL_SANDBOX') == 1 && Configuration::get('PAYPAL_SANDBOX_CLIENTID')) {
            $this->message .= $this->displayWarning($this->l('Your PayPal account is currently configured to accept payments on the Sandbox (test environment). Any transaction will be fictitious. Disable the option, to accept actual payments (production environment) and log in with your PayPal credentials'));
        } elseif (Configuration::get('PAYPAL_SANDBOX') == 0 && Configuration::get('PAYPAL_LIVE_CLIENTID')) {
            $this->message .= $this->displayConfirmation($this->l('Your PayPal account is properly connected, you can now receive payments'));
        }
        $block_info = '';
        if (Configuration::get('PS_ROUND_TYPE') != Order::ROUND_ITEM) {
            $block_info = $this->display(__FILE__, 'views/templates/admin/block_info.tpl');
        }

        return $this->message.$block_info.$this->display(__FILE__, 'views/templates/admin/configuration.tpl').$form;
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('paypal_config')) {
            Configuration::updateValue('PAYPAL_SANDBOX', Tools::getValue('paypal_sandbox'));
            Configuration::updateValue('PAYPAL_API_INTENT', Tools::getValue('paypal_intent'));
            Configuration::updateValue('PAYPAL_API_ADVANTAGES', Tools::getValue('paypal_show_advantage'));
        }
/*
        if (Tools::getValue('activate_method')) {
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT', 1);
            Configuration::updateValue('PAYPAL_METHOD', Tools::getValue('activate_method'));

            Configuration::updateValue('PAYPAL_SANDBOX_ACCESS', 1);
            Configuration::updateValue('PAYPAL_LIVE_ACCESS', 1);
        }
*/
        if (Tools::isSubmit('save_credentials')) {
            $sandbox = Tools::getValue('sandbox');
            $live = Tools::getValue('live');

            if ($sandbox['client_id'] && $sandbox['secret'] && (!$live['client_id'] || !$live['secret'])) {
                Configuration::updateValue('PAYPAL_SANDBOX', 1);
            }

            Configuration::updateValue('PAYPAL_SANDBOX_CLIENTID', $sandbox['client_id']);
            Configuration::updateValue('PAYPAL_SANDBOX_SECRET', $sandbox['secret']);

            Configuration::updateValue('PAYPAL_LIVE_CLIENTID', $live['client_id']);
            Configuration::updateValue('PAYPAL_LIVE_SECRET', $live['secret']);

            Configuration::updateValue('PAYPAL_API_CARD', Tools::getValue('with_card'));
            Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT', 1);
            Configuration::updateValue('PAYPAL_METHOD', Tools::getValue('method'));
        }

        if (Tools::isSubmit('save_rounding_settings')) {
            Configuration::updateValue('PAYPAL_SANDBOX', 0);
            Configuration::updateValue('PS_ROUND_TYPE', Order::ROUND_ITEM);
            Tools::redirect($this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name);
        }

        switch (Configuration::get('PAYPAL_METHOD')) {
            case 'EXPRESS_CHECKOUT':
                $method = AbstractMethodPaypal::load('EC');
                $id_experience_web = $method->setConfig(array(
                    'name' => Configuration::get('PS_SHOP_NAME').microtime(true),
                    'flow_config' => array(
                        'landing_page_type' => 'login',
                        'bank_txn_pending_url' => Context::getContext()->link->getModuleLink($this->name, 'ecValidation', array(), true),
                    ),
                ));

                if ($id_experience_web) {
                    Configuration::updateValue('PAYPAL_EXPERIENCE_PROFILE', $id_experience_web);
                } else {
                    Configuration::updateValue('PAYPAL_EXPERIENCE_PROFILE', '');
                    $this->_errors[] = $this->l('An error occurred. Please, check your credentials.');
                }

                if (Configuration::get('PAYPAL_API_CARD')) {
                    $id_experience_web = $method->setConfig(array(
                        'name' => Configuration::get('PS_SHOP_NAME').microtime(true),
                        'flow_config' => array(
                            'landing_page_type' => 'billing',
                            'bank_txn_pending_url' => Context::getContext()->link->getModuleLink($this->name, 'ecValidation', array(), true),
                        ),
                    ));

                    if ($id_experience_web) {
                        Configuration::updateValue('PAYPAL_EXPERIENCE_PROFILE_CARD', $id_experience_web);
                    } else {
                        Configuration::updateValue('PAYPAL_EXPERIENCE_PROFILE_CARD', '');
                        $this->_errors[] = $this->l('An error occurred. Please, check your credentials.');
                    }
                }
                break;
        }
    }

    public function hookPaymentOptions($params)
    {
        $not_refunded = 0;
        foreach ($params['cart']->getProducts() as $key => $product) {
            if ($product['is_virtual']) {
                $not_refunded = 1;
                break;
            }
        }

        $payments_options = '';

        if (Configuration::get('PAYPAL_EXPERIENCE_PROFILE') != '') {
            $payment_options = new PaymentOption();
            $action_text = $this->l('Pay with Paypal');
            $payment_options->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/paypal_sm.png'));
            if (Configuration::get('PAYPAL_API_ADVANTAGES')) {
                $action_text .= ' | '.$this->l('It\'s easy, simple and secure');
            }
            $this->context->smarty->assign(array(
                'path' => $this->_path,
            ));
            $payment_options->setCallToActionText($action_text);
            $payment_options->setAction($this->context->link->getModuleLink($this->name, 'ecInit', array('credit_card'=>'0'), true));
            if (!$not_refunded) {
                $payment_options->setAdditionalInformation($this->context->smarty->fetch('module:paypal/views/templates/front/payment_infos.tpl'));
            }
            $payments_options = [
                $payment_options,
            ];
        }

        

        if (Configuration::get('PAYPAL_API_CARD') && Configuration::get('PAYPAL_EXPERIENCE_PROFILE_CARD') !=  '') {
            $payment_options = new PaymentOption();
            $action_text = $this->l('Pay with debit or credit card');
            $payment_options->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/logo_card.png'));
            $payment_options->setCallToActionText($action_text);
            $payment_options->setAction($this->context->link->getModuleLink($this->name, 'ecInit', array('credit_card'=>'1'), true));
            $payment_options->setAdditionalInformation($this->context->smarty->fetch('module:paypal/views/templates/front/payment_infos_card.tpl'));
            $payments_options[] = $payment_options;
        }

        return $payments_options;
    }

    public function hookPaymentReturn($params)
    {
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $paypal_order = PaypalOrder::loadByOrderId($params['order']->id);
        if (!Validate::isLoadedObject($paypal_order)) {
            return;
        }
        $this->context->smarty->assign(array(
            'transaction_id' => $paypal_order->id_transaction,
        ));
        $this->context->controller->registerJavascript($this->name.'-order_confirmation_js', $this->_path.'/views/js/order_confirmation.js');
        return $this->context->smarty->fetch('module:paypal/views/templates/hook/order_confirmation.tpl');
    }

    public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown', $message = null, $transaction = array(), $currency_special = null, $dont_touch_amount = false, $secure_key = false, Shop $shop = null)
    {
        $this->amount_paid_paypal = (float)$amount_paid;

        $cart = new Cart((int) $id_cart);
        $total_ps = (float)$cart->getOrderTotal(true, Cart::BOTH);


        // hack for wrong rounding -> order failed
        if($amount_paid > $total_ps+0.10 || $amount_paid < $total_ps-0.10)
        {
            $total_ps = $amount_paid;
        }

        parent::validateOrder(
            (int) $id_cart,
            (int) $id_order_state,
            (float) $total_ps,
            $payment_method,
            $message,
            $transaction,
            $currency_special,
            $dont_touch_amount,
            $secure_key,
            $shop
        );
        $paypal_order = new PaypalOrder();

        $paypal_order->id_order = $this->currentOrder;
        $paypal_order->id_cart = Context::getContext()->cart->id;
        $paypal_order->id_transaction = $transaction['transaction_id'];
        $paypal_order->id_payment = $transaction['id'];
        $paypal_order->client_token = "";
        $paypal_order->payment_method = $transaction['payment_method'];
        $paypal_order->currency = $transaction['currency'];
        $paypal_order->total_paid = (float) $amount_paid;
        $paypal_order->payment_status = $transaction['status'];
        $paypal_order->total_prestashop = (float) $total_ps;
        $paypal_order->save();


        if ($transaction['intent'] == "authorization") {
            $paypal_capture = new PaypalCapture();
            $paypal_capture->id_paypal_order = $paypal_order->id;
            $paypal_capture->save();
        }
    }

    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        $amount_paid = (float) $this->amount_paid_paypal;
        if (isset($amount_paid) && $amount_paid != 0 && $order->total_paid != $amount_paid) {
            $order->total_paid = $amount_paid;
            $order->total_paid_real = $amount_paid;
            $order->total_paid_tax_incl = $amount_paid;
            $order->update();

            $sql = 'UPDATE `'._DB_PREFIX_.'order_payment`
		    SET `amount` = '.(float)$amount_paid.'
		    WHERE  `order_reference` = "'.pSQL($order->reference).'"';
            Db::getInstance()->execute($sql);
        }
    }


    public function hookDisplayAdminOrder($params)
    {
        $id_order = $params['id_order'];
        $order = new Order((int)$id_order);
        $paypal_msg = '';
        $paypal_order = PaypalOrder::loadByOrderId($id_order);
        $paypal_capture = PaypalCapture::loadByOrderPayPalId($paypal_order->id);
        if (!Validate::isLoadedObject($paypal_order)) {
            return false;
        }

        if (Tools::getValue('not_payed_capture')) {
            $paypal_msg .= $this->displayWarning(
                '<p class="paypal-warning">'.$this->l('You couldn\'t refund order, it\'s not payed yet.').'</p>'
            );
        }
        if (Tools::getValue('error_refund')) {
            $paypal_msg .= $this->displayWarning(
                '<p class="paypal-warning">'.$this->l('We have unexpected problem during refund operation. See massages for more details').'</p>'
            );
        }
        if ($order->current_state == Configuration::get('PS_OS_REFUND') &&  $paypal_order->payment_status == 'refunded') {
            $paypal_msg .= $this->displayWarning(
                '<p class="paypal-warning">'.$this->l('Your order is fully refunded by PayPal.').'</p>'
            );
        }
        if ($order->current_state == Configuration::get('PS_OS_PAYMENT') && Validate::isLoadedObject($paypal_capture) && $paypal_capture->id_capture) {
            $paypal_msg .= $this->displayWarning(
                '<p class="paypal-warning">'.$this->l('Your order is fully captured by PayPal.').'</p>'
            );
        }
        if (Tools::getValue('error_capture')) {
            $paypal_msg .= $this->displayWarning(
                '<p class="paypal-warning">'.$this->l('We have unexpected problem during capture operation. See massages for more details').'</p>'
            );
        }

        if ($paypal_order->total_paid != $paypal_order->total_prestashop) {
            $preferences = $this->context->link->getAdminLink('AdminPreferences', true);
            $paypal_msg .= $this->displayWarning(
                '<p class="paypal-warning">'.$this->l('Product pricing has been modified as your rounding settings aren\'t compliant with PayPal.').' '.
                $this->l('To avoid automatic rounding to customer for PayPal payments, please update your rounding settings.').' '.
                '<a target="_blank" href="'.$preferences.'">'.$this->l('Reed more.').'</a></p>'
            );
        }

        return $paypal_msg.$this->display(__FILE__, 'views/templates/hook/paypal_order.tpl');
    }


    public function hookActionOrderStatusUpdate($params)
    {
        $paypal_order = PaypalOrder::loadByOrderId($params['id_order']);

        if (!Validate::isLoadedObject($paypal_order)) {
            return false;
        }


        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_CANCELED')) {
            $method = AbstractMethodPaypal::load('EC');
            $response = $method->void(array('authorization_id'=>$paypal_order->id_transaction));
            if (isset($response->state) && $response->state == 'voided') {
                $paypalCapture = PaypalCapture::loadByOrderPayPalId($paypal_order->id);
                $paypalCapture->result = $response->state;
                $paypalCapture->save();
                $paypal_order->payment_status = $response->state;
                $paypal_order->save();
            }
        }

        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_REFUND')) {
            $capture = PaypalCapture::loadByOrderPayPalId($paypal_order->id);
            if (Validate::isLoadedObject($capture) && !$capture->id_capture) {
                $orderMessage = new Message();
                $orderMessage->message = $this->l('You couldn\'t refund order, it\'s not payed yet.');
                $orderMessage->id_order = $params['id_order'];
                $orderMessage->id_customer = $this->context->customer->id;
                $orderMessage->private = 1;
                $orderMessage->save();
                Tools::redirect($_SERVER['HTTP_REFERER'].'&not_payed_capture=1');
            }
            $method = AbstractMethodPaypal::load('EC');
            $refund_response = $method->refund();
            $orderMessage = new Message();

            if (isset($refund_response->id)) {
                $orderMessage->message = $this->l('Refund id : ').$refund_response->id.";\r";
                $orderMessage->message .= $this->l('Refund state : ').$refund_response->state.";\r";
                $orderMessage->message .= $this->l('Refund amount : ').$refund_response->amount->total." ".$refund_response->amount->currency.";\r";
                $orderMessage->message .= $this->l('Sale id : ').$refund_response->sale_id.";\r";
                $orderMessage->message .= $this->l('Parent payment : ').$refund_response->parent_payment.";\r";
                $orderMessage->message .= $this->l('Creation time : ').$refund_response->create_time.";\r";
            } else {
                $orderMessage->message = "";
                foreach ($refund_response as $key => $msg) {
                    $orderMessage->message .= $key." : ".$msg.";\r";
                }
            }

            $orderMessage->id_order = $params['id_order'];
            $orderMessage->id_customer = $this->context->customer->id;
            $orderMessage->private = 1;

            $orderMessage->save();

            if (!isset($refund_response->id) && $refund_response->message != "Request was refused.This transaction has already been fully refunded") {
                Tools::redirect($_SERVER['HTTP_REFERER'].'&error_refund=1');
            }
        }


        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_PAYMENT')) {
            $capture = PaypalCapture::loadByOrderPayPalId($paypal_order->id);
            if (!Validate::isLoadedObject($capture)) {
                return false;
            }
            $method = AbstractMethodPaypal::load('EC');
            $capture_response = $method->confirmCapture();

            $orderMessage = new Message();

            if (isset($capture_response->id)) {
                $orderMessage->message = $this->l('Capture id : ').$capture_response->id.";\r";
                $orderMessage->message .= $this->l('Capture state : ').$capture_response->state.";\r";
                $orderMessage->message .= $this->l('Capture amount : ').$capture_response->amount->total." ".$capture_response->amount->currency.";\r";
                $orderMessage->message .= $this->l('Transaction fee : ').$capture_response->transaction_fee->value." ".$capture_response->transaction_fee->currency.";\r";
                $orderMessage->message .= $this->l('Parent payment : ').$capture_response->parent_payment.";\r";
                $orderMessage->message .= $this->l('Creation time : ').$capture_response->create_time.";\r";
            } else {
                $orderMessage->message = "";
                foreach ($capture_response as $key => $msg) {
                    $orderMessage->message .= $key." : ".$msg.";\r";
                }
            }

            $orderMessage->id_order = $params['id_order'];
            $orderMessage->id_customer = $this->context->customer->id;
            $orderMessage->private = 1;

            $orderMessage->save();

            if (!isset($capture_response->id) && $capture_response->name != "AUTHORIZATION_ALREADY_COMPLETED" || $capture_response->state == 'pending') {
                Tools::redirect($_SERVER['HTTP_REFERER'].'&error_capture=1');
            }
        }
    }
}
