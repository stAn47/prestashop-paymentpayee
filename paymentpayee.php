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
        'WkCreateSubscriptionOrderForPayee',
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
        $id_order = (int)$params['id_order']; 
        if (false === Validate::isLoadedObject($order) || $order->module !== $this->name) {
            return '';
        }
        
        require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
        if (n_mini::tableExists('#__order_payeedata')) { 
         $row = Db::getInstance()->getRow('
    SELECT *
    FROM '._DB_PREFIX_.'order_payeedata
    WHERE order_id = '.(int) $order->id_cart
);
   
         require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php');

         $is_live = Configuration::get(PaymentPayee::CONFIG_LIVE);
		 if (!defined('PAYEE_CONFIG_ACCESS_KEY')) 
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
        $tr = false; 
        if (!empty($row) && (!empty($row['transaction_id']))) {
         $transactionId = $row['transaction_id']; 
         $tr = payee::getTransaction($transactionId);
        }
         if (!empty($tr)) {
            if (!empty($tr->recurrence_token) && (empty($row['recurrence_token']))) {
                $row['recurrence_token'] = $tr->recurrence_token;
                n_mini::insertArray('#__order_payeedata', $row); 
                
                foreach ($order->getProducts() as $product) {
                                $this->saveRecurringToken(
                                    $tr->recurrence_token,
                                    $order,
                                    $product,
                                    $order->id_cart
                                );
                            }
            
            
            }



         }
    }


        $isSubscription = false; 
        if (Module::isEnabled('wkproductsubscription')) {
            include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';
            $this->createAdminTab(); 
            $isSubscription = (bool) WkProductSubscriptionGlobal::isSubscriptionOrder((int) $id_order);
            /*$isSubscription = (bool) Db::getInstance()->getValue('
    SELECT COUNT(*) 
    FROM '._DB_PREFIX_.'wk_subscription_orders
    WHERE id_order = '.(int) $id_order
);
            */
        }
        $status = false; 
        $details = ''; 
        if (!empty($tr)) {
            $status = $tr->status; 
            $details = json_encode($tr, JSON_PRETTY_PRINT); 
            if (empty($tr->recurrence_token)) $isSubscription = false; 
        }
      
        if (empty($details)) $isSubscription = false; 
        
        $this->context->smarty->assign([
            'moduleName' => $this->name,
            'moduleDisplayName' => $this->displayName,
            'moduleLogoSrc' => $this->getPathUri() . 'logo.png',
            'issubscription' => $isSubscription,
            'id_order'=> $id_order,
            'link' => $this->context->link,
            'status'=>$status,
            'details' => $details,
            'transaction_id' => $transactionId,
        ]);

        $html = $this->context->smarty->fetch('module:paymentpayee/views/templates/hook/displayAdminOrderMainBottom.tpl');
        //$html .= '<details><summary>Transaction Details ('.$tr->status.')</summary><pre>'.json_encode($tr, JSON_PRETTY_PRINT).'</pre></details>'; 
        //$html .= 's='.var_export($isSubscription, true); 
        
        return $html; 
    }

    public function checkUpgrade() {
         $this->createAdminTab(); 
         foreach (PaymentPayee::HOOKS as $hook) {
                if (!$this->isRegisteredInHook($hook)) {
                    $this->registerHook($hook);
                }
             }
    }
    //stAn - if WK doesn't update their plugin we might need to add hook here
    public function validateOrderRemoved(
    $id_cart,
    $id_order_state,
    $amount_paid,
    $payment_method = 'Unknown',
    $message = null,
    $extra_vars = [],
    $currency_special = null,
    $dont_touch_amount = false,
    $secure_key = false,
    Shop $shop = null
) {
    $original_order_status = $id_order_state; 
    // Example: force order to start as "Awaiting payment" instead of whatever was passed
    if ($this->isAutoSubscription($id_cart)) {
        $id_order_state = Configuration::get('PS_OS_AWAITING_PAYMENT');
    }
    
    parent::validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method,
        $message,
        $extra_vars,
        $currency_special,
        $dont_touch_amount,
        $secure_key,
        $shop
    );
     if ($this->isAutoSubscription($id_cart)) {
        
     }
   
    // At this point, the order exists with the altered status
    }

    private function isAutoSubscription($cart_id) {
        $x = debug_backtrace(); 
        foreach ($x as $l) {
            if (isset($l['file'])) {
                //called from cron: 
                if (strpos($l['file'], 'wkproductsubscription') !== false) {
                    return true; 
                } 
            }
        }
        return false; 
    }

    private static $alreadyExecuted = array();
    public function hookWkCreateSubscriptionOrderForPayee(array $params) {
        $x = debug_backtrace();
        $t = array(); 
        foreach ($x as $l) {
            $t[] = @$l['file'].':'.@$l['line']; 
        }
        $bck = implode("\n", $t); 
        $msg = 'Subscription invocation with data:'."\n".var_export($params, true)."\n".' Backtrace: '.$bck; 
        //PrestaShopLogger::addLog($msg); 
        $subscriptionData = $params['subscriptionData']; 
        $context = $params['context']; 
        $id_cart = (int)$params['id_cart']; 
        
        //stAn - hook might be registered to FE and BE
        if (isset(self::$alreadyExecuted[$id_cart])) return; 
        self::$alreadyExecuted[$id_cart] = true; 

        $ref = $params['classRef']; 
       
        $module_name = $subscriptionData['payment_module'];
        if (empty($subscriptionData['payment_response'])) return false; 
        $tk = json_decode($subscriptionData['payment_response']);
        if (empty($tk)) return false; 
        if (empty($tk->token)) return; 
        $token = $tk->token; 

        if ($module_name !== 'paymentpayee') return null; 

        if (Validate::isModuleName($module_name)) {

            $this->module = Module::getInstanceByName('wkproductsubscription');

            $payment_module = Module::getInstanceByName('paymentpayee');
            require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php');
            require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
            $is_live = Configuration::get($payment_module::CONFIG_LIVE);
            if (!defined('PAYEE_CONFIG_ACCESS_KEY'))
            if (!empty($is_live)) {
                define('PAYEE_CONFIG_ACCESS_KEY', Configuration::get($payment_module::CONFIG_LIVE_ACCESS_KEY));
                define('PAYEE_CONFIG_SECRET_KEY', Configuration::get($payment_module::CONFIG_LIVE_SECRET_KEY));
                define('PAYEE_CONFIG_MERCHANT_NUMBER', Configuration::get($payment_module::CONFIG_LIVE_MERCHANT_NUMBER));
                define('PAYEE_ENDPOINT', "https://checkout.payee.no/api/v1");
                $is_live = true;
            }
            else {
                define('PAYEE_CONFIG_ACCESS_KEY', Configuration::get($payment_module::CONFIG_STAGING_ACCESS_KEY));
                define('PAYEE_CONFIG_SECRET_KEY',  Configuration::get($payment_module::CONFIG_STAGING_SECRET_KEY));
                define('PAYEE_CONFIG_MERCHANT_NUMBER', Configuration::get($payment_module::CONFIG_STAGING_MERCHANT_NUMBER));
                define('PAYEE_ENDPOINT', "https://test.checkout.payee.no/api/v1");
                $is_live = false;
            }
            $cart = new Cart((int) $id_cart);
             
            if ((float) $cart->getOrderTotal(true, Cart::BOTH) != (float) $subscriptionData['raw_total_amount']) {
                $orderObj = new Order((int) $subscriptionData['first_order_id']);
                $ref->createSpcificProductPrice(
                    $id_cart,
                    $subscriptionData['id_product'],
                    $subscriptionData['id_product_attribute'],
                    $subscriptionData['raw_base_price'] + $orderObj->total_shipping_tax_excl
                );
                $ref->updateFreeShipping($id_cart);
            }

            Context::getContext()->currency = new Currency((int) $cart->id_currency);
            Context::getContext()->customer = new Customer((int) $cart->id_customer);
            $address = new Address($cart->id_address_delivery);
            Context::getContext()->country = new Country((int) $address->id_country);
            Context::getContext()->cart = $cart;

            $currency_id = (int)$cart->id_currency; 
            // get cron set order status for new order
            $current_state = (int) Configuration::get('WK_SUBSCRIPTION_CRON_ORDER_STATUS');

            $total_order_amount = $cart->getOrderTotal(true, Cart::BOTH);
            $address = new Address($cart->id_address_invoice);
            $country_iso_code = Country::getIsoById($address->id_country);
            $customer = new Customer((int) $cart->id_customer);
            $language = new Language((int) $cart->id_lang);
            //validate data first:
            $base_url = Tools::getShopDomainSSL(true) . __PS_BASE_URI__;
            $return_url = $base_url.'module/paymentpayee/redirect?cartid='.$cart->id;
            $callback_url = $base_url.'module/paymentpayee/notify?cartid='.$cart->id;
            $link = new Link();
            $cart_link = $link->getPageLink(
                'order',
                true,
                (int) $cart->id_lang,
                [
                    'step' => 1,
                ]
            );
            $demoData = new stdClass();
            $cancel_url = $cart_link;
            $demoData->cancel_url = $cancel_url;
            $locale = $language->locale;

            $locale = str_replace('_', '-', $locale);
            $currency = new Currency($cart->id_currency);
            $rounded_total_order_amount_float = Tools::convertPrice($total_order_amount, $currency);
            $order_total_int = round($rounded_total_order_amount_float * 100);
            $demoData->amount = $order_total_int;
            $currency_iso_code = $currency->iso_code;
            $demoData->currency = strtoupper($currency_iso_code);
            $demoData->language = $locale;
            $demoData->email = $customer->email;
            $demoData->zip = $address->postcode;
            $demoData->country = $country_iso_code;
            $demoData->street = $address->address1;
            $demoData->name = $address->firstname.' '.$address->lastname;
            $demoData->city = $address->city;
            $demoData->company_name = $address->company;
            $demoData->phoneNumber = $address->phone;
            $order_reference = $cart->id.'-'.date('Ymdhis').'-RECUR';
            $demoData->orderid = $order_reference;
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $demoData->customer_ip = $_SERVER['REMOTE_ADDR'];
            }
            else {
                $demoData->customer_ip = '';
            }
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $demoData->user_agent = $_SERVER['HTTP_USER_AGENT'];
            }
            else {
                $demoData->user_agent = '';
            }
            $language = 'nb-NO';
            if (!empty($demodata->language)) {
                $language = $demodata->language;
            }

            if (!in_array($language, array('sv-SE', 'nb-NO', 'da-DK', 'en-US', 'fi-FI')))
            {
                $language = 'nb-NO';
            }


            $demoData->return_url = $return_url;
            $demoData->callback_url = $callback_url;
            $root = $base_url = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . Tools::getShopDomainSsl().__PS_BASE_URI__;
            $demoData->tos_url = $root;
            $demoData->customer_id = $customer->email;
            $st_obj = new stdClass();
            $bt_obj = new stdClass();
            $st_obj->phone_number = $demoData->phoneNumber;
            $bt_obj->phone_number = $demoData->phoneNumber;
            $st_obj->business_name = $demoData->company_name;
            $bt_obj->business_name = $demoData->company_name;
            $st_obj->name = $demoData->name;
            $bt_obj->name = $demoData->name;
            $st_obj->street = $demoData->street;
            $bt_obj->street = $demoData->street;
            $st_obj->postal_code = $demoData->zip;
            $bt_obj->postal_code = $demoData->zip;
            $st_obj->city = $demoData->city;
            $bt_obj->city = $demoData->city;

            $st_obj->country = $demoData->country;
            $bt_obj->country = $demoData->country;
            $st_obj->email = $demoData->email;
            $bt_obj->email = $demoData->email;
            $requestBody = '{
                "configuration": {
                    "live": '.json_encode((bool)$is_live).',
                    "language": '.json_encode($language).',
                    "auto_capture": '.json_encode((bool)true).',
                    "default_payment_type": "payex.creditcard",
                    "payex": {
                        "creditcard": {
                            "enabled": true
                        }
                    },
                    "vipps": {
                        "enabled": true
                    },
                    "collector": {
                        "type": "payment_type",
                        "invoice": {
                            "enabled": true,
                            "type": "payment_product_type"
                        }
                    }
                },
                "customer": {
                    "customer_id": '.json_encode($demoData->customer_id).',
                    "email": '.json_encode($demoData->email).',
                    "phone_number": '.json_encode($demoData->phoneNumber).'

                },
                "order": {
                    "merchant_reference": '.json_encode($demoData->orderid).',
                    "amount": '.(int)$demoData->amount.',
                    "currency": "'.$demoData->currency.'",
                    "vat_amount": 0,
                    "shipping_address": '.json_encode($st_obj).',
                    "billing_address": '.json_encode($bt_obj).',
                    "partial_payment": false,
                    "items": [ ]
                },
                "url": {
                    "return_url": '.json_encode($demoData->return_url).',
                    "cancel_url": '.json_encode($demoData->cancel_url).',
                    "callback_url": '.json_encode($demoData->callback_url).',
                    "tos_url": '.json_encode($demoData->tos_url).'

                },
                "customer_ip": '.json_encode($demoData->customer_ip).',
                "user_agent": '.json_encode($demoData->user_agent).',
                "token_provider": {
                    "payment_product_type": "payex.creditcard",
                    "token_types": []

                },
                "payment": {
                    "payment_product_type": "payex.creditcard",
                    "operation": "purchase"
                }

            }';
            $jsonObj = json_decode($requestBody);
            
            PrestaShopLogger::addLog('PaymentPayee: hookWkCreateSubscriptionOrderForPayee executed with subscription datas: '.var_export($subscriptionData, true).' Backtrace:'.$bck); 
            if (empty($jsonObj)) {
                return false;
            }


            if (!isset($jsonObj->customer)) $jsonObj->customer = new stdClass();
            $remote_paymentRef = 'payex.creditcard';
            //we'll follow dintero syntax here for now:
            //per this - https://docs.dintero.com/docs/checkout/tokenization/
            $jsonObj->customer->tokens = new stdClass();
            $jsonObj->customer->tokens->{$remote_paymentRef} = new stdClass();
            
            $jsonObj->customer->tokens->{$remote_paymentRef}->recurrence_token = $token;
            if (!isset($jsonObj->payment)) $jsonObj->payment = new stdClass();
            $jsonObj->payment->payment_product_type = $remote_paymentRef;
            $jsonObj->payment->operation = 'unscheduled_purchase'; // To perform transactions without involving the customer, using a recurrence token, use recurring_purchase
            // To perform transactions without involving the customer, MIT (merchant initiated) transactions use unscheduled_purchase
            $jsonObj->order->merchant_reference = $cart->id.date('Ymdhis'); 
            $rep =  payee::sendJson($jsonObj);
            
            if (!empty($rep->error_description)) {
                $error = $rep->error_description; 
                $params['error'] = $error; 
                return; 
            }
            if (empty($rep->error_description)) {
                $payee_transaction_id = $rep->id;
                $tr = payee::getTransaction($rep->id);

               
                if (is_object($tr) && (isset($tr->status)))
                {
                     $arr = array(); 
		             $arr['id'] = 'NULL'; 
		             $arr['order_id'] = (int)$id_cart; 
		             $arr['transaction_id'] = $tr->id; 
		             $arr['recurrence_token'] = 'NULL'; 
                     if (!empty($tr->recurrence_token)) {
                        $arr['recurrence_token'] = $tr->recurrence_token; 
                    }
                    
		            $arr['is_live'] = (int)$is_live; 
		            $arr['public_key'] = PAYEE_CONFIG_ACCESS_KEY; 
		            $arr['data_sent'] = json_encode($jsonObj); 
		            $arr['last_data'] = json_encode($tr); 
		            
                    if (empty($arr['created_on'])) $arr['created_on'] = 'UTC_TIMESTAMP'; 
		            $arr['status'] = $tr->status; 
		            $arr['currency_id'] = $currency_id; 
		            $arr['amount'] = $order_total_int; 
                    $url = ''; 
                    if (!empty($tr->url)) $url = $tr->url; 
		            $arr['redirect_url'] = $url; 
		try { 
			n_mini::insertArray('#__order_payeedata', $arr); 
		}
			catch(Exception $e) {
                echo $e->getMessage(); die(); 
		} 
        

                    $isPaid = payee::isPaid($tr);
                    $payee_transaction_id = $tr->id; 
                    if ($isPaid) { // if payment success create new order now.

                        $current_state = (int) Configuration::get('WK_SUBSCRIPTION_CRON_ORDER_STATUS');

                        $payment_module->validateOrder(
                            (int) $cart->id,
                            (int) $current_state,
                            $cart->getOrderTotal(true, Cart::BOTH),
                            $payment_module->displayName.' (Subscription)',
                            $this->module->l('Subscription order -- CRON:', 'cron'),
                            [ 'transaction_id' => $payee_transaction_id],
                            null,
                            false,
                            $cart->secure_key
                        );

                        if ($payment_module->currentOrder) {
                            $idOrder = $payment_module->currentOrder;
                            $params['idOrder'] = $idOrder; 
                            return $idOrder; 
                        } else {
                            return false;
                        }

                    }
                    else {
                         $current_state = Configuration::get('PS_OS_ERROR'); 
                         $payment_module->validateOrder(
                            (int) $cart->id,
                            (int) $current_state,
                            $cart->getOrderTotal(true, Cart::BOTH),
                            $payment_module->displayName,
                            $this->module->l('Subscription order -- CRON:', 'cron'),
                            [ 'transaction_id' => $payee_transaction_id],
                            null,
                            false,
                            $cart->secure_key
                        );
                    }
                }
            }
            
        }
        return false;
    }
    public function saveRecurringToken($token, $cart, $product, $idCart)
    {
        $isCartExist = WkSubscriptionCartProducts::getByIdProductByIdCart($cart->id, $product['id_product'], $product['id_product_attribute'], true);
        if ($isCartExist && WkProductSubscriptionModel::checkIfSubscriptionProduct($product['id_product'])) {
             $objTokenSubs = new WkPayeeCustomerSubscription();
            $objTokenSubs->id_cart = $idCart;
            $objTokenSubs->id_product = $product['id_product'];
            $objTokenSubs->attribute_id = $product['id_product_attribute'];
            $objTokenSubs->id_customer = $cart->id_customer;
            $objTokenSubs->token = $token;
            $objTokenSubs->save();
        }
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

        $t1 = (bool) $tab->add();

        $tab = new Tab();
        $tab->class_name = 'AdminSubscription';
        $tab->module = $this->name;
        $tab->id_parent = 0; // No menu, hidden controller
        foreach (Language::getLanguages() as $lang) {
            $tab->name[$lang['id_lang']] = 'Subscription Controller';
        }
        $t2 = (bool)$tab->add();
        return $t1 && $t2; 

    }


    private function createAdminTab()
{
    $className = 'AdminSubscription';

    // Check if tab already exists
    $idTab = (int) Tab::getIdFromClassName($className);

    if ($idTab > 0) {
        // Tab already exists, nothing to do
        return true;
    }

    // Otherwise create it
    $tab = new Tab();
    $tab->class_name = $className;
    $tab->module = $this->name;
    $tab->id_parent = 0; // no parent = hidden tab
    foreach (Language::getLanguages(true) as $lang) {
        $tab->name[$lang['id_lang']] = 'Subscription Controller';
    }

    return $tab->add();
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
