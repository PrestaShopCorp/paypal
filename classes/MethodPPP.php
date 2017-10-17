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

use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
require(_PS_MODULE_DIR_.'paypal/sdk/paypalREST/vendor/autoload.php');

class MethodPPP extends AbstractMethodPaypal
{
    public $name = 'paypal';

    public function setConfig($params)
    {
        $mode = Configuration::get('PAYPAL_SANDBOX') ? 'SANDBOX' : 'LIVE';
        $paypal = Module::getInstanceByName($this->name);

        if (Tools::isSubmit('paypal_config')) {
            Configuration::updateValue('PAYPAL_API_ADVANTAGES', $params['paypal_show_advantage']);
            Configuration::updateValue('PAYPAL_PPP_CONFIG_TITLE', $params['ppp_config_title']);
            Configuration::updateValue('PAYPAL_PPP_CONFIG_BRAND', $params['ppp_config_brand']);

            if (isset($_FILES['ppp_config_logo']['tmp_name'])) {
                $tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS');
                move_uploaded_file($_FILES['ppp_config_logo']['tmp_name'], $tmpName);
                ImageManager::resize($tmpName, _PS_MODULE_DIR_.'paypal/views/img/ppp_logo.png');
                Configuration::updateValue('PAYPAL_PPP_CONFIG_LOGO', _PS_MODULE_DIR_.'paypal/views/img/ppp_logo.png');
            }
        }

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
            Configuration::updateValue('PAYPAL_METHOD', 'PPP');
            Configuration::updateValue('PAYPAL_PLUS_ENABLED', 1);

            $experience_web = $this->createWebExperience();
            if ($experience_web) {
                Configuration::updateValue('PAYPAL_EXPERIENCE_PROFILE_CARD', $experience_web->id);
            }
        }
    }

    public function createWebExperience()
    {
        $brand_name = Configuration::get('PAYPAL_PPP_CONFIG_BRAND')?Configuration::get('PAYPAL_PPP_CONFIG_BRAND'):Configuration::get('PS_SHOP_NAME');
        $brand_logo = file_exists(_PS_MODULE_DIR_.'paypal/views/img/ppp_logo.png')?_PS_MODULE_DIR_.'paypal/views/img/ppp_logo.png':_PS_IMG_DIR_.Configuration::get('PS_LOGO');

        $flowConfig = new \PayPal\Api\FlowConfig();
        // Type of PayPal page to be displayed when a user lands on the PayPal site for checkout. Allowed values: Billing or Login. When set to Billing, the Non-PayPal account landing page is used. When set to Login, the PayPal account login landing page is used.
        $flowConfig->setLandingPageType("Billing");
        // The URL on the merchant site for transferring to after a bank transfer payment.
        $flowConfig->setBankTxnPendingUrl(Context::getContext()->link->getModuleLink($this->name, 'pppValidation', array(), true));
        // When set to "commit", the buyer is shown an amount, and the button text will read "Pay Now" on the checkout page.
        $flowConfig->setUserAction("commit");
        // Defines the HTTP method to use to redirect the user to a return URL. A valid value is `GET` or `POST`.
        $flowConfig->setReturnUriHttpMethod("GET");
        // Parameters for style and presentation.
        $presentation = new \PayPal\Api\Presentation();
        // A URL to logo image. Allowed vaues: .gif, .jpg, or .png.
        $presentation->setLogoImage($brand_logo)
        //	A label that overrides the business name in the PayPal account on the PayPal pages.
            ->setBrandName($brand_name)
        //  Locale of pages displayed by PayPal payment experience.
            ->setLocaleCode(Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT')))
        // A label to use as hypertext for the return to merchant link.
            ->setReturnUrlLabel("Return")
        // A label to use as the title for the note to seller field. Used only when `allow_note` is `1`.
            ->setNoteToSellerLabel("Thanks!");
        // Parameters for input fields customization.
        $inputFields = new \PayPal\Api\InputFields();
        // Enables the buyer to enter a note to the merchant on the PayPal page during checkout.
        $inputFields->setAllowNote(false)
            // Determines whether or not PayPal displays shipping address fields on the experience pages. Allowed values: 0, 1, or 2. When set to 0, PayPal displays the shipping address on the PayPal pages. When set to 1, PayPal does not display shipping address fields whatsoever. When set to 2, if you do not pass the shipping address, PayPal obtains it from the buyer’s account profile. For digital goods, this field is required, and you must set it to 1.
            ->setNoShipping(1)
            // Determines whether or not the PayPal pages should display the shipping address and not the shipping address on file with PayPal for this buyer. Displaying the PayPal street address on file does not allow the buyer to edit that address. Allowed values: 0 or 1. When set to 0, the PayPal pages should not display the shipping address. When set to 1, the PayPal pages should display the shipping address.
            ->setAddressOverride(0);
        // #### Payment Web experience profile resource
        $webProfile = new \PayPal\Api\WebProfile();
        // Name of the web experience profile. Required. Must be unique
        $webProfile->setName(Tools::substr(Configuration::get('PS_SHOP_NAME'), 0, 30) . uniqid())
            // Parameters for flow configuration.
            ->setFlowConfig($flowConfig)
            // Parameters for style and presentation.
            ->setPresentation($presentation)
            // Parameters for input field customization.
            ->setInputFields($inputFields)
            // Indicates whether the profile persists for three hours or permanently. Set to `false` to persist the profile permanently. Set to `true` to persist the profile for three hours.
            ->setTemporary(false);
        // For Sample Purposes Only.

        try {
            // Use this call to create a profile.
            $createProfileResponse = $webProfile->create($this->_getCredentialsInfo());
            //echo '<pre>';print_r($createProfileResponse);die;
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            echo '<pre>';print_r($ex);die;
            exit(1);
        }

        return $createProfileResponse;
    }

    public function _getCredentialsInfo()
    {
        switch (Configuration::get('PAYPAL_SANDBOX')) {
            case 0:
                $params['acct1.UserName'] = Configuration::get('PAYPAL_USERNAME_LIVE');
                $params['acct1.Password'] = Configuration::get('PAYPAL_PSWD_LIVE');
                $params['acct1.Signature'] = Configuration::get('PAYPAL_SIGNATURE_LIVE');
                $params['mode'] = Configuration::get('PAYPAL_SANDBOX') ? 'sandbox' : 'live';
                $params['log.LogEnabled'] = false;
                break;
            case 1:
                $apiContext = new ApiContext(
                    new OAuthTokenCredential(
                        Configuration::get('PAYPAL_SANDBOX_CLIENTID'),
                        Configuration::get('PAYPAL_SANDBOX_SECRET')
                    )
                );

                $apiContext->setConfig(
                    array(
                        'mode' => Configuration::get('PAYPAL_SANDBOX') ? 'sandbox' : 'live',
                        'log.LogEnabled' => false,
                        'cache.enabled' => false,
                    )
                );
                break;
        }
        return $apiContext;
    }

    public function getConfig(Paypal $module)
    {
        $params = array('inputs' => array(
            array(
                'type' => 'text',
                'label' => $module->l('Title'),
                'name' => 'ppp_config_title',
                'placeholder' => $module->l('Leave it empty to use default PayPal payment method title'),
            ),
            array(
                'type' => 'text',
                'label' => $module->l('Brand name'),
                'name' => 'ppp_config_brand',
                'placeholder' => $module->l('Leave it empty to use your Shop name'),
            ),
            array(
                'type' => 'file',
                'label' => $module->l('Shop logo field'),
                'name' => 'ppp_config_logo',
                'hint' => $module->l('Leave it empty to use your default shop logo'),
            ),
            array(
                'type' => 'switch',
                'label' => $module->l('Show PayPal benefits to your customers'),
                'name' => 'paypal_show_advantage',
                'desc' => $module->l(''),
                'is_bool' => true,
                'hint' => $module->l('You can increase your conversion rate by presenting PayPal benefits to your customers on payment methods selection page.'),
                'values' => array(
                    array(
                        'id' => 'paypal_show_advantage_on',
                        'value' => 1,
                        'label' => $module->l('Enabled'),
                    ),
                    array(
                        'id' => 'paypal_show_advantage_off',
                        'value' => 0,
                        'label' => $module->l('Disabled'),
                    )
                ),
            )
        ));

        $params['fields_value'] = array(
            'ppp_config_title' => Configuration::get('PAYPAL_PPP_CONFIG_TITLE'),
            'ppp_config_brand' => Configuration::get('PAYPAL_PPP_CONFIG_BRAND'),
            'ppp_config_logo' => Configuration::get('PAYPAL_PPP_CONFIG_LOGO'),
            'paypal_show_advantage' => Configuration::get('PAYPAL_API_ADVANTAGES'),
        );

        $context = Context::getContext();
        $context->smarty->assign(array(
            'ppp_active' => Configuration::get('PAYPAL_PLUS_ENABLED'),
            'PAYPAL_SANDBOX_CLIENTID' => Configuration::get('PAYPAL_SANDBOX_CLIENTID'),
            'PAYPAL_SANDBOX_SECRET' => Configuration::get('PAYPAL_SANDBOX_SECRET'),
            'PAYPAL_LIVE_CLIENTID' => Configuration::get('PAYPAL_LIVE_CLIENTID'),
            'PAYPAL_LIVE_SECRET' => Configuration::get('PAYPAL_LIVE_SECRET'),
        ));

        return $params;
    }

    public function init($params)
    {
        $payer = new Payer();
        $payer->setPaymentMethod("paypal");
        // ### Itemized information
        // (Optional) Lets you specify item wise
        // information
        $item1 = new Item();
        $item1->setName('Ground Coffee 40 oz')
            ->setCurrency('USD')
            ->setQuantity(1)
            ->setSku("123123") // Similar to `item_number` in Classic API
            ->setPrice(7.5);
        $item2 = new Item();
        $item2->setName('Granola bars')
            ->setCurrency('USD')
            ->setQuantity(5)
            ->setSku("321321") // Similar to `item_number` in Classic API
            ->setPrice(2);
        $itemList = new ItemList();
        $itemList->setItems(array($item1, $item2));
        // ### Additional payment details
        // Use this optional field to set additional
        // payment information such as tax, shipping
        // charges etc.
        $details = new Details();
        $details->setShipping(1.2)
            ->setTax(1.3)
            ->setSubtotal(17.50);
        // ### Amount
        // Lets you specify a payment amount.
        // You can also specify additional details
        // such as shipping, tax.
        $amount = new Amount();
        $amount->setCurrency("USD")
            ->setTotal(20)
            ->setDetails($details);
        // ### Transaction
        // A transaction defines the contract of a
        // payment - what is the payment for and who
        // is fulfilling it.
        $transaction = new Transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Payment description")
            ->setInvoiceNumber(uniqid());
        // ### Redirect urls
        // Set the urls that the buyer must be redirected to after
        // payment approval/ cancellation.
        $baseUrl = getBaseUrl();
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl("$baseUrl/ExecutePayment.php?success=true")
            ->setCancelUrl("$baseUrl/ExecutePayment.php?success=false");
        // ### Payment
        // A Payment Resource; create one using
        // the above types and intent set to 'sale'
        $payment = new Payment();
        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirectUrls)
            ->setTransactions(array($transaction));
        // For Sample Purposes Only.

        // ### Create Payment
        // Create a payment by calling the 'create' method
        // passing it a valid apiContext.
        // (See bootstrap.php for more on `ApiContext`)
        // The return object contains the state and the
        // url to which the buyer must be redirected to
        // for payment approval
        try {
            $payment->create($this->_getCredentialsInfo());
        } catch (Exception $ex) {

            exit(1);
        }
        // ### Get redirect url
        // The API response provides the url that you must redirect
        // the buyer to. Retrieve the url from the $payment->getApprovalLink()
        // method
        $approvalUrl = $payment->getApprovalLink();
        return $approvalUrl;
    }

    public function validation()
    {

    }
    public function confirmCapture()
    {

    }
    public function check()
    {

    }
    public function refund()
    {

    }

    public function void($params)
    {

    }
}
