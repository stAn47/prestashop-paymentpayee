<?php

class PaymentpayeeNotifyModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        /**======= Load Library =========== */
        require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php');

        parent::initContent();

        /** ========= Check transaction payment status ================= */
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

        // Collect notification data (POST/GET values)
        $cartId = Tools::getValue('cartid');
        $transactionId = Tools::getValue('payee_transaction_id');
        $merchantReference = Tools::getValue('payee_merchant_reference');

        // Check if cart ID exists
        if (!$cartId) {
            PrestaShopLogger::addLog('Missing cart ID for order placement', 3);
            die('Missing cart ID');
        }

        // Load the cart by ID
        $cart = new Cart($cartId);
        if (!Validate::isLoadedObject($cart)) {
            PrestaShopLogger::addLog('Invalid cart ID: ' . $cartId, 3);
            die('Invalid cart ID');
        }

        // Ensure the cart has products
        if ($cart->nbProducts() <= 0) {
            PrestaShopLogger::addLog('Cart is empty for cart ID: ' . $cartId, 3);
            die('Cart is empty');
        }

        // Retrieve the customer associated with the cart
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            PrestaShopLogger::addLog('Invalid customer for cart ID: ' . $cartId, 3);
            die('Invalid customer');
        }

        // Get the payment module (assuming this is a payment controller)
        $paymentModule = Module::getInstanceByName('paymentpayee');

        if ($transactionId) { // if found transaction id

            $tr = payee::getTransaction($transactionId);

            if (is_object($tr) && (isset($tr->status)))
			{

                $isPaid = payee::isPaid($tr);

                if ($isPaid) { // if payment success create new order now.
 
                    // Set order details
                    $totalAmount = $cart->getOrderTotal(true, Cart::BOTH);
                    $paymentStatus = Configuration::get('PS_OS_PAYMENT');
                    if (Module::isEnabled('wkproductsubscription')) {
                        include_once _PS_MODULE_DIR_ . 'wkproductsubscription/classes/WkSubscriptionRequired.php';
                        if (!empty($tr->recurrence_token)) {
                            foreach ($cart->getProducts() as $product) {
                                $this->saveRecurringToken(
                                    $tr->recurrence_token,
                                    $cart,
                                    $product,
                                    $cartId
                                );
                            }
                        }
                    }
                    
                    
                    // Use the validateOrder method to create an order
                    $paymentModule->validateOrder(
                        (int) $cart->id,               // Cart ID
                        (int) $paymentStatus,          // Order status
                        (float) $totalAmount,          // Total amount
                        $this->l('Payment Payee.no'),         // Payment method (displayed to customer)
                        null,                          // Optional message
                        ['transaction_id' => $transactionId], // Additional data (e.g., transaction ID)
                        (int) $cart->id_currency,      // Currency ID
                        false,                         // No need to secure key
                        $customer->secure_key          // Customer's secure key
                    );

                }

            }

        }

        // Custom log file (optional)
        /*$logFile = _PS_ROOT_DIR_.'/var/logs/custom_notification_log.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ').print_r($cartId, true).PHP_EOL, FILE_APPEND);
        */

        header('HTTP/1.1 200 OK');
        echo 'OK'; 
        exit;

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
     * Check if the context is valid
     *
     * @return bool
     */
    private function checkIfContextIsValid()
    {
		return true;

        return true === Validate::isLoadedObject($this->context->cart)
            && true === Validate::isUnsignedInt($this->context->cart->id_customer)
            && true === Validate::isUnsignedInt($this->context->cart->id_address_delivery)
            && true === Validate::isUnsignedInt($this->context->cart->id_address_invoice);
    }

    /**
     * Check that this payment option is still available in case the customer changed
     * his address just before the end of the checkout process
     *
     * @return bool
     */
    private function checkIfPaymentOptionIsAvailable()
    {
        $modules = Module::getPaymentModules();

        if (empty($modules)) {
            return false;
        }

        foreach ($modules as $module) {

            if (isset($module['name']) && $this->module->name === $module['name']) {
                return true;
            }
        }

        return false;
    }


}
