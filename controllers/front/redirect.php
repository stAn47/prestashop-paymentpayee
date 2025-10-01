<?php

class PaymentpayeeRedirectModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;
    private $helper;

    public function __construct()
    {
        parent::__construct();
        $this->ssl = true;
        $this->ajax = true;
        $this->json = true;
        $this->helper = new Helper();

        $this->controllers = [
            'validation'
        ];
    }

    public function initContent()
    {


        /**======= Load Library =========== */
        require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php');

        parent::initContent();

        /** ======== Get Query Parameter =========  */
        $cartId = (int)Tools::getValue('cartid');
        $payee_transaction_id = (int)Tools::getValue('payee_transaction_id');

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


		/** ===== Get Customer ============ */
        $customer = new Customer($this->context->cart->id_customer);


		if ($payee_transaction_id) { // if found transaction id

            $tr = payee::getTransaction($payee_transaction_id);

            if (is_object($tr) && (isset($tr->status)))
			{
                $isPaid = payee::isPaid($tr);

                if ($isPaid) { // if payment success redirect to thank you page.

                    Tools::redirect($this->context->link->getPageLink(
                        'order-confirmation',
                        true,
                        (int) $this->context->language->id,
                        [
                            'id_cart' => (int) $cartId,
                            'id_module' => (int) $this->module->id,
                            'id_order' => (int) $this->module->currentOrder,
                            'key' => $customer->secure_key,
                        ]
                    ));

                    return true;

                    /** ====== get cart values ========== */

                   /* $orderStateId = (int) 2; // Configuration::get(PaymentPayee::CONFIG_OS_OFFLINE);
                    //$demoData->customer_id = $customer->email;
                    $currency_id = (int)$this->context->cart->id_currency;

                    $total_order_amount = $this->context->cart->getOrderTotal(true, Cart::BOTH);
                    $currency = new Currency($this->context->cart->id_currency);
                    $rounded_total_order_amount_float = Tools::convertPrice($total_order_amount, $currency);
                    $order_total_int = round($rounded_total_order_amount_float * 100);


                    $option = Tools::getValue('option');
                    $name = $this->module->displayName;
                    /** Get Cart  */
                    /*$cart = new Cart($cartId);

                    if (!Validate::isLoadedObject($cart)) { // validate cart

                        Tools::redirect($this->context->link->getPageLink(
                            'order',
                            true,
                            (int) $this->context->language->id,
                            [
                                'step' => 1,
                            ]
                        ));
                    }

                    // create new order.
                    $order_id = $this->module->validateOrder(
                        (int) $this->context->cart->id,
                        (int) $orderStateId,
                        (float) $rounded_total_order_amount_float,
                        $name,
                        null,
                        [
                            'transaction_id' => $payee_transaction_id, 'redirect_url'=>'' // Should be retrieved from your Payment response
                        ],
                        (int) $this->context->currency->id,
                        false,
                        $customer->secure_key
                    );

                    if (isset($this->module->currentOrder)) {
                        $order_id = $this->module->currentOrder;
                    }

                    $order = new Order($order_id);   */

                }
            }
        }


        if (false === $this->checkIfContextIsValid() || false === $this->checkIfPaymentOptionIsAvailable()) {

			Tools::redirect($this->context->link->getPageLink(
                'order',
                true,
                (int) $this->context->language->id,
                [
                    'step' => 1,
                ]
            ));
        }

        if (false === Validate::isLoadedObject($customer)) {

			Tools::redirect($this->context->link->getPageLink(
                'order',
                true,
                (int) $this->context->language->id,
                [
                    'step' => 1,
                ]
            ));
        }

        Tools::redirect($this->context->link->getPageLink(
            'order-confirmation',
            true,
            (int) $this->context->language->id,
            [
                'id_cart' => (int) $this->context->cart->id,
                'id_module' => (int) $this->module->id,
                'id_order' => (int) $this->module->currentOrder,
                'key' => $customer->secure_key,
            ]
        ));

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