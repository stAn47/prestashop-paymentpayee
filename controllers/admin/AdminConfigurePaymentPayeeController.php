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
class AdminConfigurePaymentPayeeController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->className = 'Configuration';
        $this->table = 'configuration';

        parent::__construct();

        if (empty(Currency::checkPaymentCurrencies($this->module->id))) {
            $this->warnings[] = $this->l('No currency has been set for this module.');
        }
		
		//stAn - maybe we should add order status configs here
        $this->fields_options = [
            $this->module->name => [
                'fields' => [
				   /*
                    PaymentPayee::CONFIG_PO_OFFLINE_ENABLED => [
                        'type' => 'bool',
                        'title' => $this->l('Allow to pay with offline method'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'required' => false,
                    ],
                    PaymentPayee::CONFIG_PO_EXTERNAL_ENABLED => [
                        'type' => 'bool',
                        'title' => $this->l('Allow to pay with external method'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'required' => false,
                    ],
                    PaymentPayee::CONFIG_PO_EMBEDDED_ENABLED => [
                        'type' => 'bool',
                        'title' => $this->l('Allow to pay with embedded method'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'required' => false,
                    ],
                    PaymentPayee::CONFIG_PO_BINARY_ENABLED => [
                        'type' => 'bool',
                        'title' => $this->l('Allow to pay with binary method'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'required' => false,
                    ],
					*/
					PaymentPayee::CONFIG_LIVE => [
                        'type' => 'bool',
                        'title' => $this->l('Live Mode'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'required' => false,
                    ],
					PaymentPayee::CONFIG_SUBSCRIPTION_ON => [
                        'type' => 'bool',
                        'title' => $this->l('Enable Subscriptions'),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                        'required' => false,
                    ],

                    PaymentPayee::CONFIG_PAYEE_NAME => [
                        'type' => 'text',
                        'title' => $this->l('Payment method name shown at checokout'),
                        'validation' => 'isCleanHtml',
                        'required' => false,
                        'default'=>'Visa - Mastercard - Vipps - Faktura',
                    ],
                    PaymentPayee::CONFIG_PAYEE_NAME_SUBSCRIPTION => [
                        'type' => 'text',
                        'title' => $this->l('Payment method name shown at checokout (subscription)'),
                        'validation' => 'isCleanHtml',
                        'required' => false,
                        'default'=>'Visa - Mastercard',
                    ],
					PaymentPayee::CONFIG_LIVE_ACCESS_KEY => [
                        'type' => 'text',
                        'title' => $this->l('Payee Live Access Key ID'),
                       
                        'required' => false,
                    ],
					PaymentPayee::CONFIG_LIVE_SECRET_KEY => [
                        'type' => 'text',
                        'title' => $this->l('Payee Live Secret Key'),
                        
                        'required' => false,
                    ],
					PaymentPayee::CONFIG_LIVE_MERCHANT_NUMBER => [
                        'type' => 'text',
                        'title' => $this->l('Payee Merchant Number'),
                        
                        'required' => false,
                    ],
					PaymentPayee::CONFIG_STAGING_ACCESS_KEY => [
                        'type' => 'text',
                        'title' => $this->l('Payee Staging Access Key ID'),
                        
                        'required' => false,
                    ],
					PaymentPayee::CONFIG_STAGING_SECRET_KEY => [
                        'type' => 'text',
                        'title' => $this->l('Payee Staging Secret Key'),
                        
                        'required' => false,
                    ],
					PaymentPayee::CONFIG_STAGING_MERCHANT_NUMBER => [
                        'type' => 'text',
                        'title' => $this->l('Payee Staging Merchant Number'),
                        
                        'required' => false,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }
}
