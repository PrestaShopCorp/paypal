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
include_once(_PS_MODULE_DIR_.'paypal/sdk/PaypalSDK.php');
include_once(_PS_MODULE_DIR_.'paypal/sdk/BraintreeSiSdk.php');
include_once 'classes/AbstractMethodPaypal.php';
include_once 'classes/PaypalCapture.php';
include_once 'classes/PaypalOrder.php';


// EC = express checkout
// ECS = express checkout sortcut
// BT = Braintree

class PayPal extends PaymentModule
{
    public static $dev = true;
    public $express_checkout;
    public $message;
    public $amount_paid_paypal;
    public $module_link;
    public $errors;

    public function __construct()
    {
        $this->name = 'paypal';
        $this->tab = 'payments_gateways';
        $this->version = '4.2.0';
        $this->author = 'PrestaShop';
        $this->display = 'view';
        $this->module_key = '336225a5988ad434b782f2d868d7bfcd';
        $this->is_eu_compatible = 1;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->controllers = array('payment', 'validation');
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'radio';

        parent::__construct();

        $this->displayName = $this->l('PayPal');
        $this->description = $this->l('Benefit from PayPal’s complete payments platform and grow your business online, on mobile and internationally. Accept credit cards, debit cards and PayPal payments.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
        $this->express_checkout = $this->l('PayPal Express Checkout ');
        $this->module_link = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
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
        // Registration order status
        if (!$this->installOrderState()) {
            return false;
        }
        // Registration hook
        if (!$this->registrationHook()) {
            return false;
        }

        if (!Configuration::updateValue('PAYPAL_MERCHANT_ID', '')
            || !Configuration::updateValue('PAYPAL_USERNAME_SANDBOX', '')
            || !Configuration::updateValue('PAYPAL_PSWD_SANDBOX', '')
            || !Configuration::updateValue('PAYPAL_SIGNATURE_SANDBOX', '')
            || !Configuration::updateValue('PAYPAL_SANDBOX_ACCESS', 0)
            || !Configuration::updateValue('PAYPAL_USERNAME_LIVE', '')
            || !Configuration::updateValue('PAYPAL_PSWD_LIVE', '')
            || !Configuration::updateValue('PAYPAL_SIGNATURE_LIVE', '')
            || !Configuration::updateValue('PAYPAL_LIVE_ACCESS', 0)
            || !Configuration::updateValue('PAYPAL_SANDBOX', 0)
            || !Configuration::updateValue('PAYPAL_API_INTENT', 'sale')
            || !Configuration::updateValue('PAYPAL_API_ADVANTAGES', 1)
            || !Configuration::updateValue('PAYPAL_API_CARD', 0)
            || !Configuration::updateValue('PAYPAL_METHOD', '')
            || !Configuration::updateValue('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT', 0)
            || !Configuration::updateValue('PAYPAL_CRON_TIME', date('Y-m-d H:m:s'))
            || !Configuration::updateValue('PAYPAL_BY_BRAINTREE', 0)
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
              `method` VARCHAR(255),
              `payment_tool` VARCHAR(255),
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
        if (!Configuration::get('PAYPAL_BRAINTREE_OS_AWAITING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('PAYPAL_BRAINTREE_OS_AWAITING')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'fr') {
                    $order_state->name[$language['id_lang']] = 'En attente de paiement Braintree';
                } else {
                    $order_state->name[$language['id_lang']] = 'Awaiting for Braintree payment';
                }
            }
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->add();
            Configuration::updateValue('PAYPAL_BRAINTREE_OS_AWAITING', (int) $order_state->id);
        }
        if (!Configuration::get('PAYPAL_BRAINTREE_OS_AWAITING_VALIDATION')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('PAYPAL_BRAINTREE_OS_AWAITING_VALIDATION')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'fr') {
                    $order_state->name[$language['id_lang']] = 'En attente de validation Braintree';
                } else {
                    $order_state->name[$language['id_lang']] = 'Awaiting for Braintree validation';
                }
            }
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->add();
            Configuration::updateValue('PAYPAL_BRAINTREE_OS_AWAITING_VALIDATION', (int) $order_state->id);
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
            || !$this->registerHook('header')
            || !$this->registerHook('actionObjectCurrencyAddAfter')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('displayFooterProduct')
            || !$this->registerHook('actionBeforeCartUpdateQty')
            || !$this->registerHook('displayReassurance')
        ) {
            return false;
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
            'PAYPAL_USERNAME_SANDBOX',
            'PAYPAL_PSWD_SANDBOX',
            'PAYPAL_SIGNATURE_SANDBOX',
            'PAYPAL_SANDBOX_ACCESS',
            'PAYPAL_USERNAME_LIVE',
            'PAYPAL_PSWD_LIVE',
            'PAYPAL_SIGNATURE_LIVE',
            'PAYPAL_LIVE_ACCESS',
            'PAYPAL_METHOD',
            'PAYPAL_MERCHANT_ID',
            'PAYPAL_LIVE_BRAINTREE_ACCESS_TOKEN',
            'PAYPAL_LIVE_BRAINTREE_EXPIRES_AT',
            'PAYPAL_LIVE_BRAINTREE_REFRESH_TOKEN',
            'PAYPAL_LIVE_BRAINTREE_MERCHANT_ID',
            'PAYPAL_BRAINTREE_ENABLED',
            'PAYPAL_SANDBOX_BRAINTREE_ACCESS_TOKEN',
            'PAYPAL_SANDBOX_BRAINTREE_EXPIRES_AT',
            'PAYPAL_SANDBOX_BRAINTREE_REFRESH_TOKEN',
            'PAYPAL_SANDBOX_BRAINTREE_MERCHANT_ID',
            'PAYPAL_BY_BRAINTREE',
            'PAYPAL_CRON_TIME',
            'PAYPAL_EXPRESS_CHECKOUT'
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

    public function getUrlBt()
    {
        if (Configuration::get('PAYPAL_SANDBOX')) {
            return 'https://sandbox.pp-ps-auth.com/';
        } else {
            return 'https://pp-ps-auth.com/';
        }
    }

    public function getContent()
    {
        $this->_postProcess();

        $country_default = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        $context = $this->context;

        $context->smarty->assign(array(
            'path' => $this->_path,
            'active_products' => $this->express_checkout,
            'return_url' => $this->module_link,
            'country' => Country::getNameById($context->language->id, $context->country->id),
            'localization' => $context->link->getAdminLink('AdminLocalization', true),
            'preference' => $context->link->getAdminLink('AdminPreferences', true),
            'paypal_card' => Configuration::get('PAYPAL_API_CARD'),
        ));

        if ($country_default == "FR" || $country_default == "UK") {
            $context->smarty->assign(array(
                'braintree_available' => true,
            ));
        }


        $fields_form = array();
        $inputs = array(
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
        );
        $fields_value = array(
            'paypal_sandbox' => Configuration::get('PAYPAL_SANDBOX'),
        );

        $method_name = Configuration::get('PAYPAL_METHOD');
        $config = '';
        if ($method_name) {
            $method = AbstractMethodPaypal::load($method_name);

            $config = $method->getConfig($this);
            $inputs = array_merge($inputs, $config['inputs']);
            $fields_value = array_merge($fields_value, $config['fields_value']);
        }

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('MODULE SETTINGS'),
                'icon' => 'icon-cogs',
            ),
            'input' => $inputs,
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right button',
            ),
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
            'back_url' => $this->module_link.'#paypal_params'
        );
        $form = $helper->generateForm($fields_form);


        if (count($this->errors)) {
            $this->message .= $this->errors;
        } elseif (Configuration::get('PAYPAL_SANDBOX') == 1) {
            $this->message .= $this->displayWarning($this->l('Your PayPal account is currently configured to accept payments on the Sandbox (test environment). Any transaction will be fictitious. Disable the option, to accept actual payments (production environment) and log in with your PayPal credentials'));
        } elseif (Configuration::get('PAYPAL_SANDBOX') == 0) {
            $this->message .= $this->displayConfirmation($this->l('Your PayPal account is properly connected, you can now receive payments'));
        }

        $context->controller->addCSS($this->_path.'views/css/paypal-bo.css', 'all');

        $result = $this->message;
        if (isset($config['block_info'])) {
            $result .= $config['block_info'];
        }
        $result .= $this->display(__FILE__, 'views/templates/admin/configuration.tpl').$form;
        if (isset($config['form'])) {
            $result .= $config['form'];
        }

        return $result;
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('paypal_config')) {
            Configuration::updateValue('PAYPAL_SANDBOX', Tools::getValue('paypal_sandbox'));
        }

        if (Tools::getValue('method')) {
            $method_name = Tools::getValue('method');
        } elseif (Tools::getValue('active_method')) {
            $method_name = Tools::getValue('active_method');
        } else {
            $method_name = Configuration::get('PAYPAL_METHOD');
        }


        if ($method_name) {
            $method = AbstractMethodPaypal::load($method_name);
            $method->setConfig(Tools::getAllValues());
        }
    }

    public function getBtConnectUrl()
    {
        $connect_params = array(
            'user_country' => $this->context->country->iso_code,
            'user_email' => Configuration::get('PS_SHOP_EMAIL'),
            'business_name' => Configuration::get('PS_SHOP_NAME'),
            'redirect_url' => $this->module_link.'&active_method='.Tools::getValue('method'),
        );
        $sdk = new BraintreeSDK(Configuration::get('PAYPAL_SANDBOX'));
        return $sdk->getUrlConnect($connect_params);
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

        $method_active = Configuration::get('PAYPAL_METHOD');
        $payments_options = array();

        switch ($method_active) {
            case 'EC':
                if (!Configuration::get('PAYPAL_SANDBOX') && (Configuration::get('PAYPAL_USERNAME_LIVE') && Configuration::get('PAYPAL_PSWD_LIVE') && Configuration::get('PAYPAL_PSWD_LIVE'))
                    || (Configuration::get('PAYPAL_SANDBOX') && (Configuration::get('PAYPAL_USERNAME_SANDBOX') && Configuration::get('PAYPAL_PSWD_SANDBOX') && Configuration::get('PAYPAL_SIGNATURE_SANDBOX')))) {
                    $payment_options = new PaymentOption();
                    $action_text = $this->l('Pay with Paypal');
                    $payment_options->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/paypal_sm.png'));
                    $payment_options->setModuleName($this->name);
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

                    if (Configuration::get('PAYPAL_API_CARD')) {
                        $payment_options = new PaymentOption();
                        $action_text = $this->l('Pay with debit or credit card');
                        $payment_options->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/logo_card.png'));
                        $payment_options->setCallToActionText($action_text);
                        $payment_options->setModuleName($this->name);
                        $payment_options->setAction($this->context->link->getModuleLink($this->name, 'ecInit', array('credit_card'=>'1'), true));
                        $payment_options->setAdditionalInformation($this->context->smarty->fetch('module:paypal/views/templates/front/payment_infos_card.tpl'));
                        $payments_options[] = $payment_options;
                    }
                    if (Configuration::get('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT') && isset($this->context->cookie->paypal_ecs)) {
                        $payment_options = new PaymentOption();
                        $action_text = $this->l('Pay with paypal express checkout');
                        $payment_options->setCallToActionText($action_text);
                        $payment_options->setModuleName($this->name);
                        $payment_options->setAction($this->context->link->getModuleLink($this->name, 'ecValidation', array('shortcut'=>'1'), true));
                        $payment_options->setAdditionalInformation($this->context->smarty->fetch('module:paypal/views/templates/front/express_checkout.tpl'));
                        $payments_options[] = $payment_options;
                    }
                }
                break;
            case 'BT':
                $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
                $merchant_accounts = Tools::jsonDecode(Configuration::get('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID'));
                $curr = context::getContext()->currency->iso_code;
                if (!isset($merchant_accounts->$curr)) {
                    return $payments_options;
                }
                if (Configuration::get('PAYPAL_BRAINTREE_ENABLED')) {
                    if (Configuration::get('PAYPAL_BY_BRAINTREE')) {
                        $embeddedOption = new PaymentOption();
                        $action_text = $this->l('Pay with paypal by braintree');
                        if (Configuration::get('PAYPAL_API_ADVANTAGES')) {
                            $action_text .= ' | '.$this->l('It\'s easy, simple and secure');
                        }
                        $embeddedOption->setCallToActionText($action_text)
                            ->setForm($this->generateFormPaypalBt())
                            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/logo_card.png'));
                        $embeddedOption->setModuleName('braintree');
                        $payments_options[] = $embeddedOption;
                    }

                    $embeddedOption = new PaymentOption();
                    $embeddedOption->setCallToActionText($this->l('Pay braintree'))
                        ->setForm($this->generateFormBt())
                        ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/logo_card.png'));
                    $embeddedOption->setModuleName('braintree');

                    $payments_options[] = $embeddedOption;
                }
                break;
        }

        return $payments_options;
    }

    public function hookHeader()
    {
        if (Tools::getValue('controller') == "order") {
            if (Configuration::get('PAYPAL_METHOD') == 'BT') {
                if (Configuration::get('PAYPAL_BRAINTREE_ENABLED')) {
                    $this->context->controller->addJqueryPlugin('fancybox');
                    $this->context->controller->registerJavascript($this->name . '-braintreegateway-client', 'https://js.braintreegateway.com/web/3.16.0/js/client.min.js', array('server' => 'remote'));
                    $this->context->controller->registerJavascript($this->name . '-braintreegateway-hosted', 'https://js.braintreegateway.com/web/3.16.0/js/hosted-fields.min.js', array('server' => 'remote'));
                    $this->context->controller->registerJavascript($this->name . '-braintreegateway-data', 'https://js.braintreegateway.com/web/3.16.0/js/data-collector.min.js', array('server' => 'remote'));
                    $this->context->controller->registerJavascript($this->name . '-braintreegateway-3ds', 'https://js.braintreegateway.com/web/3.16.0/js/three-d-secure.min.js', array('server' => 'remote'));
                    $this->context->controller->registerStylesheet($this->name . '-braintreecss', 'modules/' . $this->name . '/views/css/braintree.css');
                    $this->context->controller->registerJavascript($this->name . '-braintreejs', 'modules/' . $this->name . '/views/js/payment_bt.js');
                }
                if (Configuration::get('PAYPAL_BY_BRAINTREE')) {
                    $this->context->controller->registerJavascript($this->name . '-paypal-checkout', 'https://www.paypalobjects.com/api/checkout.js', array('server' => 'remote'));
                    $this->context->controller->registerJavascript($this->name . '-paypal-checkout-min', 'https://js.braintreegateway.com/web/3.16.0/js/paypal-checkout.min.js', array('server' => 'remote'));
                    $this->context->controller->registerJavascript($this->name . '-paypal-braintreejs', 'modules/' . $this->name . '/views/js/payment_pbt.js');
                }
            }
            if (Configuration::get('PAYPAL_METHOD') == 'EC' && Configuration::get('PAYPAL_EXPRESS_CHECKOUT_SHORTCUT') && isset($this->context->cookie->paypal_ecs)) {
                $this->context->controller->registerJavascript($this->name . '-paypal-ec-sc', 'modules/' . $this->name . '/views/js/ec_shortcut_payment.js');
            }
        }
    }

    public function hookDisplayBackOfficeHeader()
    {
        $diff_cron_time = date_diff(date_create('now'), date_create(Configuration::get('PAYPAL_CRON_TIME')));
        if ($diff_cron_time->h > 4) {
            $bt_orders = PaypalOrder::getPaypalBtOrdersIds();
            if (!$bt_orders) {
                return true;
            }
            Configuration::updateValue('PAYPAL_CRON_TIME', date('Y-m-d H:i:s'));
            $method = AbstractMethodPaypal::load('BT');
            $transactions = $method->searchTransactions($bt_orders);
            foreach ($transactions as $transaction) {
                $paypal_order_id = PaypalOrder::getIdOrderByTransactionId($transaction->id);
                $paypal_order = PaypalOrder::loadByOrderId($paypal_order_id);
                $ps_order = new Order($paypal_order_id);
                switch ($transaction->status) {
                    case 'declined':
                        $paypal_order->payment_status = $transaction->status;
                        $ps_order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                        break;
                    case 'settled':
                        $paypal_order->payment_status = $transaction->status;
                        $ps_order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                        break;
                    default:
                        // do nothing and check later one more time
                        break;
                }
                $paypal_order->update();
            }
        }
    }

    public function hookActionObjectCurrencyAddAfter($params)
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
        $merchant_accounts = (array)Tools::jsonDecode(Configuration::get('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID'));
        $method_bt = AbstractMethodPaypal::load('BT');
        $merchant_account = $method_bt->createForCurrency($params['object']->iso_code);

        if ($merchant_account) {
            $merchant_accounts[$params['object']->iso_code] = $merchant_account[$params['object']->iso_code];
            Configuration::updateValue('PAYPAL_'.$mode.'_BRAINTREE_ACCOUNT_ID', Tools::jsonEncode($merchant_accounts));
        }
    }

    protected function generateFormPaypalBt()
    {
        $context = $this->context;
        $amount = $context->cart->getOrderTotal();

        $braintree = AbstractMethodPaypal::load('BT');
        $clientToken = $braintree->init(true);
        $this->context->smarty->assign(array(
            'braintreeToken'=> $clientToken,
            'braintreeSubmitUrl'=> $context->link->getModuleLink('paypal', 'btValidation', array(), true),
            'braintreeAmount'=> $amount,
            'baseDir' => $context->link->getBaseLink($context->shop->id, true),
            'path' => $this->_path,
        ));


        return $this->context->smarty->fetch('module:paypal/views/templates/front/payment_pb.tpl');
    }


    protected function generateFormBt()
    {
        $context = $this->context;
        $amount = $context->cart->getOrderTotal();

        $braintree = AbstractMethodPaypal::load('BT');
        $clientToken = $braintree->init(true);
        $check3DS = 0;
        $required_3ds_amount = Tools::convertPrice(Configuration::get('PAYPAL_3D_SECURE_AMOUNT'), Currency::getCurrencyInstance((int)$context->currency->id));
        if (Configuration::get('PAYPAL_USE_3D_SECURE') && $amount > $required_3ds_amount) {
            $check3DS = 1;
        }
        $this->context->smarty->assign(array(
            'error_msg'=> Tools::getValue('bt_error_msg'),
            'braintreeToken'=> $clientToken,
            'braintreeSubmitUrl'=> $context->link->getModuleLink('paypal', 'btValidation', array(), true),
            'braintreeAmount'=> $amount,
            'check3Dsecure'=> $check3DS,
            'baseDir' => $context->link->getBaseLink($context->shop->id, true),
        ));


        return $this->context->smarty->fetch('module:paypal/views/templates/front/payment_bt.tpl');
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
            'method' => $paypal_order->method,
        ));
        $this->context->controller->registerJavascript($this->name.'-order_confirmation_js', $this->_path.'/views/js/order_confirmation.js');
        return $this->context->smarty->fetch('module:paypal/views/templates/hook/order_confirmation.tpl');
    }

    /* public function hookDisplayFooterProduct($params)
    {
        if (Configuration::get('PAYPAL_METHOD') != 'EC') {
            return false;
        }
        $method = AbstractMethodPaypal::load('EC');
        return $method->renderExpressCheckout($this->context, 'EC');
    }*/

    public function hookDisplayReassurance()
    {
        if ('product' !== $this->context->controller->php_self || Configuration::get('PAYPAL_METHOD') != 'EC') {
            return false;
        }
        $method = AbstractMethodPaypal::load('EC');
        return $method->renderExpressCheckout($this->context, 'EC');
    }

    public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown', $message = null, $transaction = array(), $currency_special = null, $dont_touch_amount = false, $secure_key = false, Shop $shop = null)
    {
        $this->amount_paid_paypal = (float)$amount_paid;
        $cart = new Cart((int) $id_cart);
        $total_ps = (float)$cart->getOrderTotal(true, Cart::BOTH);
        parent::validateOrder(
            (int) $id_cart,
            (int) $id_order_state,
            (float) $total_ps,
            $payment_method,
            $message,
            array('transaction_id' => $transaction['transaction_id']),
            $currency_special,
            $dont_touch_amount,
            $secure_key,
            $shop
        );


        $paypal_order = new PaypalOrder();
        $paypal_order->id_order = $this->currentOrder;
        $paypal_order->id_cart = Context::getContext()->cart->id;
        $paypal_order->id_transaction = $transaction['transaction_id'];
        $paypal_order->id_payment = $transaction['id_payment'];
        $paypal_order->client_token = $transaction['client_token'];
        $paypal_order->payment_method = $transaction['payment_method'];
        $paypal_order->currency = $transaction['currency'];
        $paypal_order->total_paid = (float) $amount_paid;
        $paypal_order->payment_status = $transaction['payment_status'];
        $paypal_order->total_prestashop = (float) $total_ps;
        $paypal_order->method = $transaction['method'];
        $paypal_order->payment_tool = isset($transaction['payment_tool']) ? $transaction['payment_tool'] : '';
        $paypal_order->save();


        if ($transaction['capture']) {
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
        if ($order->current_state == Configuration::get('PS_OS_REFUND') &&  $paypal_order->payment_status == 'Refunded') {
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
            $paypal_msg .= $this->displayWarning('<p class="paypal-warning">'.$this->l('Product pricing has been modified as your rounding settings aren\'t compliant with PayPal.').' '.
                $this->l('To avoid automatic rounding to customer for PayPal payments, please update your rounding settings.').' '.
                '<a target="_blank" href="'.$preferences.'">'.$this->l('Reed more.').'</a></p>'
            );
        }

        return $paypal_msg.$this->display(__FILE__, 'views/templates/hook/paypal_order.tpl');
    }

    public function hookActionBeforeCartUpdateQty($params)
    {
        if (isset($this->context->cookie->paypal_ecs) || isset($this->context->cookie->paypal_ecs_payerid)) {
            //unset cookie of payment init if it's no more same cart
            Context::getContext()->cookie->__unset('paypal_ecs');
            Context::getContext()->cookie->__unset('paypal_ecs_payerid');
        }
    }


    public function hookActionOrderStatusUpdate($params)
    {


        $paypal_order = PaypalOrder::loadByOrderId($params['id_order']);
        if (!Validate::isLoadedObject($paypal_order)) {
            return false;
        }
        $method = AbstractMethodPaypal::load($paypal_order->method);
        $orderMessage = new Message();
        $orderMessage->message = "";

        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_CANCELED')) {
            $orderPayPal = PaypalOrder::loadByOrderId($params['id_order']);
            $paypalCapture = PaypalCapture::loadByOrderPayPalId($orderPayPal->id);

            $response_void = $method->void(array('authorization_id'=>$orderPayPal->id_transaction));

            if ($response_void['success']) {
                $paypalCapture->result = 'voided';
                $paypalCapture->save();
                $orderPayPal->payment_status = 'voided';
                $orderPayPal->save();
            }

            foreach ($response_void as $key => $msg) {
                $orderMessage->message .= $key." : ".$msg.";\r";
            }

            $orderMessage->id_order = $params['id_order'];
            $orderMessage->id_customer = $this->context->customer->id;
            $orderMessage->private = 1;
            $orderMessage->save();
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
            $status = '';
            if ($paypal_order->method == "BT") {
                $status = $method->getTransactionStatus($paypal_order->id_transaction);
            }

            if ($paypal_order->method == "BT" && $status == "submitted_for_settlement") {
                $refund_response = $method->void(array('authorization_id'=>$paypal_order->id_transaction));
                if ($refund_response['success']) {
                    $capture->result = 'voided';
                    $paypal_order->payment_status = 'voided';
                }
            } else {
                $refund_response = $method->refund();
                if ($refund_response['success']) {
                    $capture->result = 'refunded';
                    $paypal_order->payment_status = 'refunded';
                }
            }

            if ($refund_response['success']) {
                $capture->save();
                $paypal_order->save();
            }


            foreach ($refund_response as $key => $msg) {
                $orderMessage->message .= $key." : ".$msg.";\r";
            }

            $orderMessage->id_order = $params['id_order'];
            $orderMessage->id_customer = $this->context->customer->id;
            $orderMessage->private = 1;
            $orderMessage->save();

            if (!isset($refund_response['already_refunded']) && !isset($refund_response['success'])) {
                Tools::redirect($_SERVER['HTTP_REFERER'].'&error_refund=1');
            }
        }

        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_PAYMENT')) {
            $capture = PaypalCapture::loadByOrderPayPalId($paypal_order->id);
            if (!Validate::isLoadedObject($capture)) {
                return false;
            }

            $capture_response = $method->confirmCapture();

            if ($capture_response['success']) {
                $paypal_order->payment_status = $capture_response['status'];
                $paypal_order->save();
            }

            foreach ($capture_response as $key => $msg) {
                $orderMessage->message .= $key." : ".$msg.";\r";
            }

            $orderMessage->id_order = $params['id_order'];
            $orderMessage->id_customer = $this->context->customer->id;
            $orderMessage->private = 1;
            $orderMessage->save();

            if (!isset($capture_response['already_captured']) && !isset($capture_response['success'])) {
                Tools::redirect($_SERVER['HTTP_REFERER'].'&error_capture=1');
            }
        }
    }

    public function getPartnerInfo($method)
    {
        $return_url = $this->context->link->getAdminLink('AdminModules', true).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name.'&active_method='.Tools::getValue('method');
        if (Configuration::get('PS_SSL_ENABLED')) {
            $shop_url = Tools::getShopDomainSsl(true);
        } else {
            $shop_url = Tools::getShopDomain(true);
        }

        $partner_info = array(
            'email'         => $this->context->employee->email,
            'shop_url'      => Tools::getShopDomainSsl(true),
            'address1'      => Configuration::get('PS_SHOP_ADDR1', null, null, null, ''),
            'address2'      => Configuration::get('PS_SHOP_ADDR2', null, null, null, ''),
            'city'          => Configuration::get('PS_SHOP_CITY', null, null, null, ''),
            'country_code'  => Tools::strtoupper($this->context->country->iso_code),
            'postal_code'   => Configuration::get('PS_SHOP_CODE', null, null, null, ''),
            'state'         => Configuration::get('PS_SHOP_STATE_ID', null, null, null, ''),
            'return_url'    => $return_url,
            'first_name'    => $this->context->employee->firstname,
            'last_name'     => $this->context->employee->lastname,
            'shop_name'     => Configuration::get('PS_SHOP_NAME', null, null, null, ''),
            'ref_merchant'  => ((defined(PLATEFORM) && PLATEFORM == 'PSREAD')?'presto':'prestashop_')._PS_VERSION_.'_'.$this->version,
        );

        $sdk = new PaypalSDK(Configuration::get('PAYPAL_SANDBOX'));
        $response = $sdk->getUrlOnboarding($partner_info);
        return $response;
    }
}
