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

/**
 * This Controller simulate an external payment gateway
 */
class PaymentPayeeExternalModuleFrontController extends ModuleFrontController
{
    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
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
		
    }
	
	private function createOrder() { 
		 
        $customer = new Customer($this->context->cart->id_customer);
		

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
		
		$cart = $this->context->cart; 
		
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php'); 
		
		$obj = new stdClass(); 
		
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
		
		$demoData = new stdClass(); 

		$currency = new Currency($cart->id_currency);
		$currency_iso_code = $currency->iso_code;
		
		$demoData->currency = strtoupper($currency_iso_code); 
		
		$address = new Address($cart->id_address_invoice);
		$country_iso_code = Country::getIsoById($address->id_country);
		$demoData->language = $locale; 
		$demoData->email = $customer->email; 
		$demoData->zip = $address->postcode; 	
		$demoData->country = $country_iso_code; 
		$demoData->street = $address->address1; 
		$demoData->name = $address->firstname.' '.$address->lastname; 
		$demoData->city = $address->city; 
		$demoData->company_name = $address->company;  
		$demoData->phoneNumber = $address->phone;   
		
		
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

		}
		

	

		

		//experimental features for subscription: 
		if ($subscription_enabled) { 
			$demoData->generatePaymentToken = true; 
			$demoData->generateRecurrenceToken = true; 
			//$demoData->generateUnscheduledToken = true; 
		}

		//not required - will be used for subscription later on: 
		$demoData->customer_id = $customer->email; 
		$currency_id = (int)$this->context->cart->id_currency; 
		$total_order_amount = $this->context->cart->getOrderTotal(true, Cart::BOTH);
		$currency = new Currency($this->context->cart->id_currency);
		$rounded_total_order_amount_float = Tools::convertPrice($total_order_amount, $currency);
		$order_total_int = round($rounded_total_order_amount_float * 100); 
		
		
		$cart_link = $this->context->link->getPageLink(
                'order',
                true,
                (int) $this->context->language->id,
                [
                    'step' => 1,
                ]
            ); 
		
		//validate data first:
		$base_url = Tools::getShopDomainSSL(true) . __PS_BASE_URI__;
		$return_url = $base_url.'module/paymentpayee/redirect?cartid='.$cart->id;
		$cancel_url = $cart_link;			
		$callback_url = $base_url.'module/paymentpayee/notify?cartid='.$cart->id;
		
		$demoData->return_url = $return_url; 
		$demoData->callback_url = $callback_url;
		$demoData->auto_capture = true; 
		$demoData->cancel_url = $cancel_url;
		$demoData->amount = $order_total_int; 
		$root = $base_url = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . Tools::getShopDomainSsl().__PS_BASE_URI__;
		
		$demoData->tos_url = $root; 
		$order_reference = $cart->id.'-'.date('Ymdhis'); 

		$demoData->orderid = $order_reference; 		
		
		$jsonObj = payee::getPaymentJson($is_live, $demoData); 
		
		$rep =  payee::sendJson($jsonObj); 
		 
		if (!empty($rep->error_description)) {
			
			$this->errors[] = $rep->error_message;
			$this->context->controller->errors[] = $this->l($rep->error_message); 
			
			return Tools::redirect($cart_link); 
		}
				
		$option = Tools::getValue('option');
        $name = $this->module->displayName;
		$orderStateId = (int) 2; //(int) Configuration::get(PaymentPayee::CONFIG_OS_OFFLINE);

		/*$order_id = $this->module->validateOrder(
            (int) $this->context->cart->id,
            (int) $orderStateId,
            (float) $rounded_total_order_amount_float,
            $name,
            null,
            [
                'transaction_id' => 0, 'redirect_url'=>'' // Should be retrieved from your Payment response
            ],
            (int) $this->context->currency->id,
            false,
            $customer->secure_key
        );
		if (isset($this->module->currentOrder)) {
			$order_id = $this->module->currentOrder;
		}
		$order = new Order($order_id);
		$return_url = $this->context->link->getModuleLink($this->module->name, 'validation', ['option' => 'external', 'order_id'=>$order_id,'total'=>$order_total_int], true);
		$cancel_url = $this->context->link->getModuleLink($this->module->name, 'cancel', ['option' => 'external', 'order_id'=>$order_id,'total'=>$order_total_int], true);
		*/
		
		
		$demoData->return_url = $return_url; 
		$demoData->callback_url = $callback_url;
		$demoData->auto_capture = true; 
		$demoData->cancel_url = $cancel_url;
		$demoData->amount = $order_total_int; 
		$root = $base_url = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . Tools::getShopDomainSsl().__PS_BASE_URI__;
		
		$demoData->tos_url = $root; 
		/*$order_reference = $order->reference;
		$demoData->orderid = $order_reference; */		
		 
		$jsonObj = payee::getPaymentJson($is_live, $demoData); 
		$rep =  payee::sendJson($jsonObj); 
		
		$transaction_id = $rep->id; 
		$url = $rep->url; 
		$this->url = $url; 
		
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
		$test = n_mini::tableExists('#__order_payeedata'); 
		$arr = array(); 
		$arr['id'] = 'NULL'; 
		$arr['order_id'] = (int)$cart->id; 
		$arr['transaction_id'] = $transaction_id; 
		$arr['recurrence_token'] = 'NULL'; 
		$arr['is_live'] = (int)$is_live; 
		$arr['public_key'] = PAYEE_CONFIG_ACCESS_KEY; 
		$arr['data_sent'] = json_encode($jsonObj); 
		//$arr['last_data'] = 0; 
		//$arr['created_on'] = 0; 
		//$arr['status'] = 0; 
		$arr['currency_id'] = $currency_id; 
		$arr['amount'] = $order_total_int; 
		$arr['redirect_url'] = $url; 
		try { 
			n_mini::insertArray('#__order_payeedata', $arr); 
		}
			catch(Exception $e) {
		} 
		
	}
	
	var $url = '';
	var $sendJson = ''; 
	
    /**
     * {@inheritdoc}
     */
    public function initContent()
    {
        parent::initContent();
		
		$this->createOrder(); 
		
		Tools::redirect($this->url); 
		
        $this->context->smarty->assign([
            'action' => $this->url,
        ]);
		
        $this->setTemplate('module:paymentpayee/views/templates/front/external.tpl');
    }

    /**
     * Check if the context is valid
     *
     * @return bool
     */
    private function checkIfContextIsValid()
    {
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
        if (!Configuration::get(PaymentPayee::CONFIG_PO_EXTERNAL_ENABLED)) {
            return false;
        }

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
