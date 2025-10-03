<?php

class AdminSubscriptionController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true; // Use PrestaShop BO styling
    }

    public function initContent()
    {
        parent::initContent();

        $id_order = (int) Tools::getValue('id_order');
        $action   = Tools::getValue('action');
        
        require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
	    require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php');
        
        $locale = $this->context->language->locale;
	    $locale = str_replace('_', '-', $locale);
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
         $order = new Order((int) $id_order);
      
        if (false === Validate::isLoadedObject($order) || $order->module !== 'paymentpayee') {
            return '';
        }
        $row = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'wk_subscription_orders WHERE id_order = '.(int) $id_order);

       
            // Example: run some custom test logic
            
            
            

            
// Example usage:
if ($row) {
    

   
    if (n_mini::tableExists('#__order_payeedata')) { 
         $tr_row = Db::getInstance()->getRow('
    SELECT *
    FROM '._DB_PREFIX_.'order_payeedata
    WHERE order_id = '.(int) $order->id_cart
    );

    $WkProductSubscriptionGlobal = new WkProductSubscriptionGlobal();
    $idSubscription = (int)$row['id_subscription']; 
    $subscriptionData = $WkProductSubscriptionGlobal->getSubscriptionDetails((int) $idSubscription);
    if (!empty($subscriptionData['payment_response'])) { 
    $token = $subscriptionData['payment_response']; 
    if (strpos($subscriptionData['payment_response'], '{') === 0) {
        $tk = json_decode($subscriptionData['payment_response']); 
        if (!empty($tk) && (!empty($tk->token))) {
            $token = $tk->token; 
        }
    }
    if (empty($token)) {
        $token = $tr_row['recurrence_token']; 
    }
    
    

    
        
    $transaction_id = $tr_row['transaction_id']; 
    if ($action === 'cancel') {
    $test = payee::deleteToken($transaction_id); 
    if (empty($test->error_description)) {
        if ($test->status === 'OK') {
            $this->confirmations[] = $this->l('Subscription token removed');
        }
    }
    else {
        $this->confirmations[] = $this->l($test->error_description);
    }
    }
    if ($action === 'testSubscription') {
    $test = payee::verifyTransaction($transaction_id); 
    if (!empty($test) && (is_object($test)) && (!empty($test->state))) {
        $this->confirmations[] = $this->l('Verify transaction result = '.$test->state);
    }
    else {
        $this->confirmations[] = $this->l('Verify transaction result NOT OK = '.var_export($test, true));
    }

    

   
    
    }
            
        }
    }
}
        if ($action === 'recurNow') {
            if (Module::isEnabled('wkproductsubscription')) {
                include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';
            }
             $module = Module::getInstanceByName('paymentpayee');
             $module->checkUpgrade(); 
             
             $idOrder = 0; 
             $idCart = $order->id_cart; 
             
             require _PS_MODULE_DIR_.'paymentpayee/controllers/front/wkcron.php';

        // Get the module instance (dispatcher also does this)
        $wkproductsubscription = Module::getInstanceByName('wkproductsubscription');
        $_GET['fc'] = 'module';
        $_GET['module'] = 'wkproductsubscription';
        $_GET['controller'] = 'cron';
        // Use the exact controller class name from cron.php:
        $controller = new PaymentPayeeWkcronModuleFrontController();
        $controller->module  = $wkproductsubscription;
        $controller->context = Context::getContext();
        $new_idCart = $controller->createSubscriberCart($subscriptionData);
             $error = ''; 
             $params = ['subscriptionData' => $subscriptionData, 'context' => $this->context, 'id_cart'=>$new_idCart, 'idOrder'=>&$idOrder, 'classRef'=>$controller, 'error'=>&$error];
             Hook::exec('WkCreateSubscriptionOrderForPayee', $params);
             if (!empty($error)) {
                    $this->confirmations[] = $this->l('Recur Error: '.$error);
                }
             if (class_exists('WkSubscriberScheduleModel'))
             if (!empty($idOrder)) {
                            $id_subscription = (int) $subscriptionData['id_subscription'];
                            if (!empty($subscriptionData['order_date'])) { 
                                $order_date = date('Y-m-d', strtotime($subscriptionData['order_date']));
                            }
                            if (empty($order_date)) $order_date = date('Y-m-d'); 
                            $next_order_delivery_date = date('Y-m-d', strtotime($subscriptionData['next_order_delivery_date']));
                            $subObj = new WkSubscriberOrderModel();
                            $subObj->id_order = (int) $idOrder;
                            $subObj->id_cart = (int) $idCart;
                            $subObj->id_shop = (int) $subscriptionData['id_shop'];
                            $subObj->id_shop_group = (int) Shop::getContextShopGroupID();
                            $subObj->id_subscription = (int) $id_subscription;
                            $idSchedule = (int) $subscriptionData['schedule']['id_wk_subscription_schedule'];
                            $subObj->id_schedule = (int) $idSchedule;
                            $objGlobal = new WkProductSubscriptionGlobal();
                            if ($subObj->save()) {
                                //++$total_order_create;
                                // Update status in order schedule table
                                $scheudleObj = new WkSubscriberScheduleModel((int) $idSchedule);
                                
                                $scheudleObj->id_subscription = (int) $id_subscription;
                                $scheudleObj->order_date = $order_date;
                                $scheudleObj->delivery_date = $next_order_delivery_date;
                                $scheudleObj->is_order_created = 0;
                                $scheudleObj->is_email_send = $objGlobal->sendPreOrderMail((int) $subsData['id_subscription']);
                                $scheudleObj->active = 1;
                                $scheudleObj->save();
                                $this->confirmations[] = $this->l('Recur Now executed successfully - new Order ID = '.$idOrder);
                            }
             }

             
             /*
             $json = $this->buildDemoDataFromOrder($id_order); 
    

    debug_zval_dump($json); 
     var_dump($token); 
    var_dump($subscriptionData['payment_response']); 
    var_dump($tr_row['recurrence_token']); 
    $ret = payee::recurPayment($is_live, $token, $json); 
   
    var_dump($ret); die(); 
            // Example: trigger a recurrence
            PrestaShopLogger::addLog("Recur Now triggered for order ID: $id_order");
            $this->confirmations[] = $this->l('Recur Now executed successfully.');
        
                */
            }
        $this->setTemplate('subscription_result.tpl');

    }

    public function buildDemoDataFromOrder($order_id)
{
    $demoData = new stdClass();

    // Load order
    $order = new Order((int)$order_id);
    if (!Validate::isLoadedObject($order)) {
        throw new PrestaShopException("Order not found: $order_id");
    }

    // Customer + currency
    $customer = new Customer((int)$order->id_customer);
    $currency = new Currency((int)$order->id_currency);

    $demoData->currency = strtoupper($currency->iso_code);
    $demoData->language = $this->context->language->iso_code; // or derive from order->id_lang
    $demoData->email    = $customer->email;

    // Invoice address
    $address = new Address((int)$order->id_address_invoice);
    $country_iso_code = Country::getIsoById((int)$address->id_country);

    $demoData->zip         = $address->postcode;
    $demoData->country     = $country_iso_code;
    $demoData->street      = $address->address1;
    $demoData->name        = $address->firstname.' '.$address->lastname;
    $demoData->city        = $address->city;
    $demoData->company_name= $address->company;
    $demoData->phoneNumber = $address->phone;

    // Environment data
    $demoData->customer_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $demoData->user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Subscription check (if wkproductsubscription is installed)
    //not used on recur: 
    /*
    $subscription_enabled = Configuration::get(PaymentPayee::CONFIG_SUBSCRIPTION_ON);
    if (Module::isEnabled('wkproductsubscription')) {
        include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';

        $products = $order->getProducts(); // from Order instead of Cart
        $hasSubscription = false;

        foreach ($products as $product) {
            $idAttrib  = $product['product_attribute_id'];
            $idProduct = $product['product_id'];
            $idCart    = (int)$order->id_cart;

            $isCartExist = WkSubscriptionCartProducts::getByIdProductByIdCart(
                $idCart,
                $idProduct,
                $idAttrib,
                true
            );

            if (!empty($isCartExist)) {
                $hasSubscription = true;
                break;
            }
        }

        if (empty($hasSubscription)) {
            $subscription_enabled = false;
        }
    }

    if ($subscription_enabled) {
        $demoData->generatePaymentToken    = true;
        $demoData->generateRecurrenceToken = true;
        // $demoData->generateUnscheduledToken = true;
    }
    */
    // Payment values
    $demoData->customer_id = $customer->email;

    $total_order_amount = $order->getOrdersTotalPaid(); // safer for Order
    $currency           = new Currency($order->id_currency);
    $rounded_total      = Tools::convertPrice($total_order_amount, $currency);
    $order_total_int    = round($rounded_total * 100);

    // URLs
    $base_url   = Tools::getShopDomainSsl(true).__PS_BASE_URI__;
    $order_link = $this->context->link->getPageLink(
        'order',
        true,
        (int)$this->context->language->id,
        ['step' => 1]
    );

    $demoData->return_url   = $base_url.'module/paymentpayee/redirect?orderid='.$order->id;
    $demoData->callback_url = $base_url.'module/paymentpayee/notify?orderid='.$order->id;
    $demoData->cancel_url   = $order_link;
    $demoData->auto_capture = true;
    $demoData->amount       = $order_total_int;

    // Misc
    $root = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://')
        .Tools::getShopDomainSsl().__PS_BASE_URI__;
    $demoData->tos_url  = $root;
    $demoData->orderid  = $order->reference; // better than cart->id+timestamp

    return $demoData;
}

}