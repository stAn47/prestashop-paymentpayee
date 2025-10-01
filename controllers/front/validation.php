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
 * This Controller receive customer after approval on bank payment page
 */
class PaymentPayeeValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @var PaymentModule
     */
    public $module;

    /**
     * {@inheritdoc}
     */
    public function postProcess()
    {
		
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/configuration.php');
		require_once(_PS_ROOT_DIR_.'/modules/paymentpayee/vendor/payee.php');
		$db = n_db::getDBO(); 
		
		$order_id = (int)Tools::getValue('order_id');
		$payee_transaction_id = (int)Tools::getValue('payee_transaction_id'); 
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
			return $this->error('Security Error: Access key does not match'); 
		}
		if ((int)$res['transaction_id'] === $payee_transaction_id) {
			
			$tr = payee::getTransaction($payee_transaction_id); 
			if (is_object($tr) && (isset($tr->status))) 
			{
				if ((int)$tr->amount !== (int)$get_amount) {
					return $this->error('Error: Amount paid is not same as order amount'); 
				}
				$isPaid = payee::isPaid($tr); 
				if ($isPaid) {
					 
					$order = new Order($order_id);
					if ($order->reference !== $tr->order_reference) {
						
						
						return $this->error('Order reference does not match payment reference'); 
					}
					$this->module->currentOrder = $order_id; 
					$current_order_status_id = $order->getCurrentState();
					$new_order_state = Configuration::get('PS_OS_PAYMENT');
					if ($new_order_state !== $current_order_status_id) {
						$order->setCurrentState($new_order_state);
						$transaction_id = $payee_transaction_id;
						$amount_paid = ((int)$tr->amount) / 100;
						$order->addOrderPayment($amount_paid, null, $transaction_id);
						$order->update();
					}
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
	private function error($msg) {
		$customer = new Customer($this->context->cart->id_customer);
		$this->context->smarty->assign([
            'msg' => $msg,
        ]);
		
        $this->setTemplate('module:paymentpayee/views/templates/front/error.tpl');
    
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

    /**
     * Get OrderState identifier
     *
     * @return int
     */
    private function getOrderState()
    {
        $option = Tools::getValue('option');
        $orderStateId = (int) Configuration::get('PS_OS_ERROR');

        switch ($option) {
            case 'offline':
                $orderStateId = (int) Configuration::get(PaymentPayee::CONFIG_OS_OFFLINE);
                break;
            case 'external':
                $orderStateId = (int) Configuration::get('PS_OS_WS_PAYMENT');
                break;
            case 'iframe':
            case 'embedded':
            case 'binary':
                $orderStateId = (int) Configuration::get('PS_OS_PAYMENT');
                break;
        }

        return $orderStateId;
    }

    /**
     * Get translated Payment Option name
     *
     * @return string
     */
    private function getOptionName()
    {
        $option = Tools::getValue('option');
        $name = $this->module->displayName;

        switch ($option) {
            case 'offline':
                $name = $this->l('Offline');
                break;
            case 'external':
                $name = $this->l('External');
                break;
            case 'iframe':
                $name = $this->l('Iframe');
                break;
            case 'embedded':
                $name = $this->l('Embedded');
                break;
            case 'binary':
                $name = $this->l('Binary');
                break;
        }

        return $name;
    }
}
