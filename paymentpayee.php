<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PaymentPayee extends PaymentModule
{
    const CONFIG_OS_OFFLINE = 'PAYMENTPAYEE_OS_OFFLINE';
    const CONFIG_PO_OFFLINE_ENABLED = 'PAYMENTPAYEE_PO_OFFLINE_ENABLED';
    const CONFIG_PO_EXTERNAL_ENABLED = 'PAYMENTPAYEE_PO_EXTERNAL_ENABLED';
    const CONFIG_PO_EMBEDDED_ENABLED = 'PAYMENTPAYEE_PO_EMBEDDED_ENABLED';
    const CONFIG_PO_BINARY_ENABLED = 'PAYMENTPAYEE_PO_BINARY_ENABLED';
	
    const CONFIG_PAYEE_NAME = 'CONFIG_PAYEE_NAME';
	const CONFIG_PAYEE_NAME_SUBSCRIPTION = 'CONFIG_PAYEE_NAME_SUBSCRIPTION';

	const CONFIG_LIVE_ACCESS_KEY = 'CONFIG_LIVE_ACCESS_KEY';
	const CONFIG_LIVE_SECRET_KEY = 'CONFIG_LIVE_SECRET_KEY';
	const CONFIG_LIVE_MERCHANT_NUMBER = 'CONFIG_LIVE_MERCHANT_NUMBER';
	
	const CONFIG_STAGING_ACCESS_KEY = 'CONFIG_STAGING_ACCESS_KEY';
	const CONFIG_STAGING_SECRET_KEY = 'CONFIG_STAGING_SECRET_KEY';
	const CONFIG_STAGING_MERCHANT_NUMBER = 'CONFIG_STAGING_MERCHANT_NUMBER';
	
	const CONFIG_LIVE = 'CONFIG_LIVE';
	const CONFIG_SUBSCRIPTION_ON = 'CONFIG_SUBSCRIPTION_ON';
	
    const MODULE_ADMIN_CONTROLLER = 'AdminConfigurePaymentPayee';
    const HOOKS = [
        /*'actionPaymentCCAdd',*/
        'actionObjectShopAddAfter',
		
        'paymentOptions',
        'displayAdminOrderLeft',
        'displayAdminOrderMainBottom',
        'displayCustomerAccount',
        'displayOrderConfirmation',
        'displayOrderDetail',
        'displayPaymentByBinaries',
        'displayPaymentReturn',
        'displayPDFInvoice',
    ];
	public function hookActionOrderConfirmation($params) {
		
	}
    public function __construct()
    {
        $this->name = 'paymentpayee';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.1';
        $this->author = 'PrestaShop';
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];
        $this->controllers = [
            'account',
            'cancel',
            'external',
            'validation'
			
        ];

        parent::__construct();

        $this->displayName = $this->l('Payment Payee.no');
        $this->description = $this->l('Payment Module Payee.no');
    
		//ini_set('display_errors', 1); 
		
	
	}
	//expects one of the return URLs (cancel, return or IPN)
	public function getTransaction() {
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php');
		$db = n_db::getDBO(); 
		
		$order_id = (int)Tools::getValue('order_id');
		if (empty($order_id)) return false; 
		
		$payee_transaction_id = (int)Tools::getValue('payee_transaction_id'); 
		if (empty($payee_transaction_id)) return false; 
		$get_amount = (int)Tools::getValue('total');
		$q = 'select * from #__order_payeedata where order_id = '.(int)$order_id.' order by created_on asc limit 1'; 
		$db->setQuery($q); 
		$res = $db->loadAssoc(); 
		
		
		
		$is_live = Configuration::get(PaymentPayee::CONFIG_LIVE); 
		if (!empty($is_live)) {
			define('PAYEE_CONFIG_ACCESS_KEY', Configuration::get(PaymentPayee::CONFIG_LIVE_ACCESS_KEY)); 
			define('PAYEE_CONFIG_SECRET_KEY', Configuration::get(PaymentPayee::CONFIG_LIVE_SECRET_KEY)); 
			define('PAYEE_CONFIG_MERCHANT_NUMBER', Configuration::get(PaymentPayee::CONFIG_LIVE_MERCHANT_NUMBER)); 
			define('PAYEE_ENDPOINT', "https://checkout.payee.no/api/v1");
			$is_live = true; 
		}
		else {
			define('PAYEE_CONFIG_ACCESS_KEY', Configuration::get(PaymentPayee::CONFIG_STAGING_ACCESS_KEY)); 
			define('PAYEE_CONFIG_SECRET_KEY',  Configuration::get(PaymentPayee::CONFIG_STAGING_SECRET_KEY)); 
			define('PAYEE_CONFIG_MERCHANT_NUMBER', Configuration::get(PaymentPayee::CONFIG_STAGING_MERCHANT_NUMBER)); 
			define('PAYEE_ENDPOINT', "https://test.checkout.payee.no/api/v1");
			$is_live = false; 
		}
		if ($res['public_key'] !== PAYEE_CONFIG_ACCESS_KEY) {
			return false; 
		}
		if ((int)$res['transaction_id'] === $payee_transaction_id) {
			
			$tr = payee::getTransaction($payee_transaction_id); 
			if (is_object($tr) && (isset($tr->status))) 
			{
				if (isset($tr->amount))
				if ((int)$tr->amount !== (int)$get_amount) {
					return false; 
				}
				$isPaid = payee::isPaid($tr);
				$order = new Order($order_id);
					if (false === Validate::isLoadedObject($order)) {
						return false; 
					}
					if ($order->reference !== $tr->order_reference) {
						
						
						return false;  
					}
				return $tr; 
			}
		}
		return false; 
	}
    /**
     * @return bool
     */
    public function install()
    {
        return (bool) parent::install()
            && (bool) $this->registerHook(static::HOOKS)
            && $this->installOrderState()
            && $this->installConfiguration()
            && $this->installTabs();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return (bool) parent::uninstall()
            && $this->deleteOrderState()
            && $this->uninstallConfiguration()
            && $this->uninstallTabs();
    }

    /**
     * Module configuration page
     */
    public function getContent()
    {
        // Redirect to our ModuleAdminController when click on Configure button
        Tools::redirectAdmin($this->context->link->getAdminLink(static::MODULE_ADMIN_CONTROLLER));
    }

    /**
     * This hook is used to save additional information will be displayed on BO Order View, Payment block with "Details" button
     *
     * @param array $params
     */
    public function hookActionPaymentCCAdd(array $params)
    {
		
        if (empty($params['paymentCC'])) {
            return;
        }

        /** @var OrderPayment $orderPayment */
        $orderPayment = $params['paymentCC'];

        if (false === Validate::isLoadedObject($orderPayment) || empty($orderPayment->order_reference)) {
            return;
        }

        /** @var Order[] $orderCollection */
        $orderCollection = Order::getByReference($orderPayment->order_reference);

        foreach ($orderCollection as $order) {
            if ($this->name !== $order->module) {
                return;
            }
        }

        if ('embedded' !== Tools::getValue('option') || !Configuration::get(static::CONFIG_PO_EMBEDDED_ENABLED)) {
            return;
        }

        $cardNumber = Tools::getValue('cardNumber');
        $cardBrand = Tools::getValue('cardBrand');
        $cardHolder = Tools::getValue('cardHolder');
        $cardExpiration = Tools::getValue('cardExpiration');

        if (false === empty($cardNumber) && Validate::isGenericName($cardNumber)) {
            $orderPayment->card_number = $cardNumber;
        }

        if (false === empty($cardBrand) && Validate::isGenericName($cardBrand)) {
            $orderPayment->card_brand = $cardBrand;
        }

        if (false === empty($cardHolder) && Validate::isGenericName($cardHolder)) {
            $orderPayment->card_holder = $cardHolder;
        }

        if (false === empty($cardExpiration) && Validate::isGenericName($cardExpiration)) {
            $orderPayment->card_expiration = $cardExpiration;
        }

        $orderPayment->save();
    }

    /**
     * This hook called after a new Shop is created
     *
     * @param array $params
     */
    public function hookActionObjectShopAddAfter(array $params)
    {
        if (empty($params['object'])) {
            return;
        }

        /** @var Shop $shop */
        $shop = $params['object'];

        if (false === Validate::isLoadedObject($shop)) {
            return;
        }

        $this->addCheckboxCarrierRestrictionsForModule([(int) $shop->id]);
        $this->addCheckboxCountryRestrictionsForModule([(int) $shop->id]);

        if ($this->currencies_mode === 'checkbox') {
            $this->addCheckboxCurrencyRestrictionsForModule([(int) $shop->id]);
        } elseif ($this->currencies_mode === 'radio') {
            $this->addRadioCurrencyRestrictionsForModule([(int) $shop->id]);
        }
    }

    /**
     * @param array $params
     *
     * @return array Should always return an array
     */
    public function hookPaymentOptions(array $params)
    {
        /** @var Cart $cart */
        $cart = $params['cart'];

        if (false === Validate::isLoadedObject($cart) || false === $this->checkCurrency($cart)) {
            return [];
        }

        $paymentOptions = [];
		
        

        if (Configuration::get(static::CONFIG_PO_EXTERNAL_ENABLED)) {
            $paymentOptions[] = $this->getExternalPaymentOption();
        }

        

        return $paymentOptions;
    }

    /**
     * This hook is used to display additional information on BO Order View, under Payment block
     *
     * @since PrestaShop 1.7.7 This hook is replaced by displayAdminOrderMainBottom on migrated BO Order View
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminOrderLeft(array $params)
    {
        if (empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int) $params['id_order']);

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayAdminOrderLeft.tpl');
    }

    /**
     * This hook is used to display additional information on BO Order View, under Payment block
     *
     * @since PrestaShop 1.7.7 This hook replace displayAdminOrderLeft on migrated BO Order View
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminOrderMainBottom(array $params)
    {
        if (empty($params['id_order'])) {
            return '';
        }

        $order = new Order((int) $params['id_order']);

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayAdminOrderMainBottom.tpl');
    }

    /**
     * This hook is used to display information in customer account
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayCustomerAccount(array $params)
    {
        $this->context->smarty->assign([
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
            'transactionsLink' => $this->context->link->getModuleLink(
                $this->name,
                'account'
            ),
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayCustomerAccount.tpl');
    }

    /**
     * This hook is used to display additional information on order confirmation page
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayOrderConfirmation(array $params)
    {
		
        if (empty($params['order'])) {
            return '';
        }
		
        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayOrderConfirmation.tpl');
    }

    /**
     * This hook is used to display additional information on FO (Guest Tracking and Account Orders)
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayOrderDetail(array $params)
    {
		
        if (empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayOrderDetail.tpl');
    }

    /**
     * This hook displays form generated by binaries during the checkout
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayPaymentByBinaries(array $params)
    {
        /** @var Cart $cart */
        $cart = $params['cart'];

        if (false === Validate::isLoadedObject($cart) || false === $this->checkCurrency($cart)) {
            return '';
        }

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', ['option' => 'binary'], true),
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayPaymentByBinaries.tpl');
    }

    /**
     * This hook is used to display additional information on bottom of order confirmation page
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayPaymentReturn(array $params)
    {
        if (empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
            'transactionsLink' => $this->context->link->getModuleLink(
                $this->name,
                'account'
            ),
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayPaymentReturn.tpl');
    }

    /**
     * This hook is used to display additional information on Invoice PDF
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayPDFInvoice(array $params)
    {
        if (empty($params['object'])) {
            return '';
        }

        /** @var OrderInvoice $orderInvoice */
        $orderInvoice = $params['object'];

        if (false === Validate::isLoadedObject($orderInvoice)) {
            return '';
        }

        $order = $orderInvoice->getOrder();

        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }

        $transaction = '';

        if ($order->getOrderPaymentCollection()->count()) {
            /** @var OrderPayment $orderPayment */
            $orderPayment = $order->getOrderPaymentCollection()->getFirst();
            $transaction = $orderPayment->transaction_id;
        }

        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'transaction' => $transaction,
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayPDFInvoice.tpl');
    }

    /**
     * Check if currency is allowed in Payment Preferences
     *
     * @param Cart $cart
     *
     * @return bool
     */
    private function checkCurrency(Cart $cart)
    {
        $currency_order = new Currency($cart->id_currency);
        /** @var array $currencies_module */
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (empty($currencies_module)) {
            return false;
        }

        foreach ($currencies_module as $currency_module) {
            if ($currency_order->id == $currency_module['id_currency']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Factory of PaymentOption for Offline Payment
     *
     * @return PaymentOption
     */
    private function getOfflinePaymentOption()
    {
        $offlineOption = new PaymentOption();
        $offlineOption->setModuleName($this->name);
        $offlineOption->setCallToActionText($this->l('Pay offline'));
        $offlineOption->setAction($this->context->link->getModuleLink($this->name, 'validation', ['option' => 'offline'], true));
        $offlineOption->setAdditionalInformation($this->context->smarty->fetch('module:paymentpayee/views/templates/front/paymentOptionOffline.tpl'));
        $offlineOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/offline.png'));

        return $offlineOption;
    }

    /**
     * Factory of PaymentOption for External Payment
     *
     * @return PaymentOption
     */
    private function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption->setModuleName($this->name);

        $default_name = $this->l('Visa - Mastercard - Vipps - Faktura'); 
        
        $payment_name = Configuration::get(PaymentPayee::CONFIG_PAYEE_NAME); 
        if (!empty($payment_name)) $payment_name = $default_name; 

        $subscription_enabled = Configuration::get(PaymentPayee::CONFIG_SUBSCRIPTION_ON);

		//stAn - it is very important to disable subscription when we don't subscribe because the list of payment methods is limited to just credit card when subscrption is requested
		if (Module::isEnabled('wkproductsubscription')) {
        include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';
		$products = $this->context->cart->getProducts();
		
		$hasSubscription = false; 
		$idCart = (int) $this->context->cart->id;
    	foreach ($products as $product) {
			
                        $idAttrib = $product['id_product_attribute'];
                        $idProduct = $product['id_product'];
                        $isCartExist = WkSubscriptionCartProducts::getByIdProductByIdCart(
                            $idCart,
                            $idProduct,
                            $idAttrib,
                            true
                        );
						if (!empty($isCartExist)) {
								$hasSubscription = true; 
							}
		}
		if (empty($hasSubscription)) {
			$subscription_enabled = false; 
		}
        if (!empty($subscription_enabled)) {
            $payment_name_sub = Configuration::get(PaymentPayee::CONFIG_PAYEE_NAME_SUBSCRIPTION); 
            if (!empty($payment_name_sub)) {
                $payment_name = $payment_name_sub; 
            }
        }

		}

        

        $externalOption->setCallToActionText($this->l($payment_name));
        $externalOption->setAction($this->context->link->getModuleLink($this->name, 'external', [], true));
        
        $externalOption->setAdditionalInformation($this->context->smarty->fetch('module:paymentpayee/views/templates/front/paymentOptionExternal.tpl'));
        $externalOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/external.png'));
		
		
		
        return $externalOption;
    }

    /**
     * Factory of PaymentOption for Embedded Payment
     *
     * @return PaymentOption
     */
    private function getEmbeddedPaymentOption()
    {
        $embeddedOption = new PaymentOption();
        $embeddedOption->setModuleName($this->name);
        $embeddedOption->setCallToActionText($this->l('Pay embedded'));
        $embeddedOption->setForm($this->generateEmbeddedForm());
        $embeddedOption->setAdditionalInformation($this->context->smarty->fetch('module:paymentpayee/views/templates/front/paymentOptionEmbedded.tpl'));
        $embeddedOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/embedded.png'));

        return $embeddedOption;
    }

    /**
     * Factory of PaymentOption for binary Payment
     *
     * @return PaymentOption
     */
    private function getBinaryPaymentOption()
    {
        $binaryOption = new PaymentOption();
        $binaryOption->setModuleName($this->name);
        $binaryOption->setCallToActionText($this->l('Pay binary'));
        $binaryOption->setAdditionalInformation($this->context->smarty->fetch('module:paymentpayee/views/templates/front/paymentOptionBinary.tpl'));
        $binaryOption->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/option/binary.png'));
        $binaryOption->setBinary(true);

        return $binaryOption;
    }

    /**
     * Generate a form for Embedded Payment
     *
     * @return string
     */
    private function generateEmbeddedForm()
    {
        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', ['option' => 'embedded'], true),
        ]);

        return $this->context->smarty->fetch('module:paymentpayee/views/templates/front/paymentOptionEmbeddedForm.tpl');
    }

    /**
     * @return bool
     */
    private function installOrderState()
    {
        return $this->createOrderState(
            static::CONFIG_OS_OFFLINE,
            [
                'en' => 'Awaiting payment',
            ],
            '#00ffff',
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            true,
            'awaiting-payment'
        );
    }

    /**
     * Create custom OrderState used for payment
     *
     * @param string $configurationKey Configuration key used to store OrderState identifier
     * @param array $nameByLangIsoCode An array of name for all languages, default is en
     * @param string $color Color of the label
     * @param bool $isLogable consider the associated order as validated
     * @param bool $isPaid set the order as paid
     * @param bool $isInvoice allow a customer to download and view PDF versions of his/her invoices
     * @param bool $isShipped set the order as shipped
     * @param bool $isDelivery show delivery PDF
     * @param bool $isPdfDelivery attach delivery slip PDF to email
     * @param bool $isPdfInvoice attach invoice PDF to email
     * @param bool $isSendEmail send an email to the customer when his/her order status has changed
     * @param string $template Only letters, numbers and underscores are allowed. Email template for both .html and .txt
     * @param bool $isHidden hide this status in all customer orders
     * @param bool $isUnremovable Disallow delete action for this OrderState
     * @param bool $isDeleted Set OrderState deleted
     *
     * @return bool
     */
    private function createOrderState(
        $configurationKey,
        array $nameByLangIsoCode,
        $color,
        $isLogable = false,
        $isPaid = false,
        $isInvoice = false,
        $isShipped = false,
        $isDelivery = false,
        $isPdfDelivery = false,
        $isPdfInvoice = false,
        $isSendEmail = false,
        $template = '',
        $isHidden = false,
        $isUnremovable = true,
        $isDeleted = false
    ) {
        $tabNameByLangId = [];

        foreach ($nameByLangIsoCode as $langIsoCode => $name) {
            foreach (Language::getLanguages(false) as $language) {
                if (Tools::strtolower($language['iso_code']) === $langIsoCode) {
                    $tabNameByLangId[(int) $language['id_lang']] = $name;
                } elseif (isset($nameByLangIsoCode['en'])) {
                    $tabNameByLangId[(int) $language['id_lang']] = $nameByLangIsoCode['en'];
                }
            }
        }

        $orderState = new OrderState();
        $orderState->module_name = $this->name;
        $orderState->name = $tabNameByLangId;
        $orderState->color = $color;
        $orderState->logable = $isLogable;
        $orderState->paid = $isPaid;
        $orderState->invoice = $isInvoice;
        $orderState->shipped = $isShipped;
        $orderState->delivery = $isDelivery;
        $orderState->pdf_delivery = $isPdfDelivery;
        $orderState->pdf_invoice = $isPdfInvoice;
        $orderState->send_email = $isSendEmail;
        $orderState->hidden = $isHidden;
        $orderState->unremovable = $isUnremovable;
        $orderState->template = $template;
        $orderState->deleted = $isDeleted;
        $result = (bool) $orderState->add();

        if (false === $result) {
            $this->_errors[] = sprintf(
                'Failed to create OrderState %s',
                $configurationKey
            );

            return false;
        }

        $result = (bool) Configuration::updateGlobalValue($configurationKey, (int) $orderState->id);

        if (false === $result) {
            $this->_errors[] = sprintf(
                'Failed to save OrderState %s to Configuration',
                $configurationKey
            );

            return false;
        }

        $orderStateImgPath = $this->getLocalPath() . 'views/img/orderstate/' . $configurationKey . '.png';

        if (false === (bool) Tools::file_exists_cache($orderStateImgPath)) {
            $this->_errors[] = sprintf(
                'Failed to find icon file of OrderState %s',
                $configurationKey
            );

            return false;
        }

        if (false === (bool) Tools::copy($orderStateImgPath, _PS_ORDER_STATE_IMG_DIR_ . $orderState->id . '.gif')) {
            $this->_errors[] = sprintf(
                'Failed to copy icon of OrderState %s',
                $configurationKey
            );

            return false;
        }

        return true;
    }

    /**
     * Delete custom OrderState used for payment
     * We mark them as deleted to not break passed Orders
     *
     * @return bool
     */
    private function deleteOrderState()
    {
        $result = true;

        $orderStateCollection = new PrestaShopCollection('OrderState');
        $orderStateCollection->where('module_name', '=', $this->name);
        /** @var OrderState[] $orderStates */
        $orderStates = $orderStateCollection->getAll();

        foreach ($orderStates as $orderState) {
            $orderState->deleted = true;
            $result = $result && (bool) $orderState->save();
        }

        return $result;
    }

    /**
     * Install default module configuration
     *
     * @return bool
     */
    private function installConfiguration()
    {
        return (bool) Configuration::updateGlobalValue(static::CONFIG_PO_OFFLINE_ENABLED, '1')
            && (bool) Configuration::updateGlobalValue(static::CONFIG_PO_EXTERNAL_ENABLED, '1')
            && (bool) Configuration::updateGlobalValue(static::CONFIG_PO_EMBEDDED_ENABLED, '1')
            && (bool) Configuration::updateGlobalValue(static::CONFIG_PO_BINARY_ENABLED, '1');
    }

    /**
     * Uninstall module configuration
     *
     * @return bool
     */
    private function uninstallConfiguration()
    {
        return (bool) Configuration::deleteByName(static::CONFIG_PO_OFFLINE_ENABLED)
            && (bool) Configuration::deleteByName(static::CONFIG_PO_EXTERNAL_ENABLED)
            && (bool) Configuration::deleteByName(static::CONFIG_PO_EMBEDDED_ENABLED)
            && (bool) Configuration::deleteByName(static::CONFIG_PO_BINARY_ENABLED);
    }

    /**
     * Install Tabs
     *
     * @return bool
     */
    public function installTabs()
    {
        if (Tab::getIdFromClassName(static::MODULE_ADMIN_CONTROLLER)) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = static::MODULE_ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = -1;
        $tab->name = array_fill_keys(
            Language::getIDs(false),
            $this->displayName
        );

        return (bool) $tab->add();
    }

    /**
     * Uninstall Tabs
     *
     * @return bool
     */
    public function uninstallTabs()
    {
        $id_tab = (int) Tab::getIdFromClassName(static::MODULE_ADMIN_CONTROLLER);

        if ($id_tab) {
            $tab = new Tab($id_tab);

            return (bool) $tab->delete();
        }

        return true;
    }
}
