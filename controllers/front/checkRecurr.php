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
class PaymentPayeeCheckRecurrModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $customer = new Customer(3);
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
        $base_url = Tools::getShopDomainSSL(true) . __PS_BASE_URI__;
		$return_url = $base_url.'module/paymentpayee/redirect?cartid=';
        $demoData = new stdClass();
        $order = new Order((int) 10);
        // dump($order);die;
        $address = new Address($order->id_address_invoice);
        $locale = $this->context->language->locale;
		$locale = str_replace('_', '-', $locale);
        $currency = new Currency($order->id_currency);
		$currency_iso_code = $currency->iso_code;
        $country_iso_code = Country::getIsoById($address->id_country);
        $demoData->currency = strtoupper($currency_iso_code);
        $demoData->language = $locale;
        $demoData->orderid = 10;
        $demoData->amount = $order->total_paid;
        $demoData->return_url = $return_url;
        $demoData->cancel_url = $return_url;
        $demoData->callback_url = $return_url;
        $demoData->tos_url = $return_url;
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
        $demoData->customer_id = $customer->email;
        $jsonObj = self::getPaymentJson(true, $demoData);
        if (!isset($jsonObj->customer)) $jsonObj->customer = new stdClass();

		$remote_paymentRef = 'payex.creditcard';
		//we'll follow dintero syntax here for now:
		//per this - https://docs.dintero.com/docs/checkout/tokenization/
		$jsonObj->customer->tokens = new stdClass();
		$jsonObj->customer->tokens->{$remote_paymentRef} = new stdClass();
		$jsonObj->customer->tokens->{$remote_paymentRef}->recurrence_token = 'a3f9ed18-fd00-4f98-9651-a4692a051dc7';

		if (!isset($jsonObj->payment)) $jsonObj->payment = new stdClass();

		$jsonObj->payment->payment_product_type = $remote_paymentRef;
		$jsonObj->payment->operation = 'recurring_purchase';

		//$jsonObj->payment->operation = 'unscheduled_purchase';
		$jsonObj->order->merchant_reference = 'RECUR-'.time();
        $rep =  payee::sendJson($jsonObj);
        dump($rep);die;
    }

    public static function getPaymentJson($is_live, $demoData) {
		//defines $experimental
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

        /*
        sv-SE, nb-NO, da-DK, en-US or fi-FI
        defined in:
        require(self::$configFile);

        */

        if (!isset($language)) {
            $language = 'nb-NO';
        }


        //is_live=false is used for transaction validation
        $is_live = true;
        $auto_capture = true;
        //stAn - live=false for transction testing, it will be cancelled immidiatelly after creation
        $requestBody = '{
                    "configuration": {
                        "live": '.json_encode((bool)$is_live).',
                        "language": '.json_encode($language).',
                        "auto_capture": '.json_encode((bool)$auto_capture).',
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
                        "email": '.json_encode($bt_obj->email).',
                        "phone_number": '.json_encode($bt_obj->phone_number).'

                    },
                    "order": {
                        "merchant_reference": '.json_encode($demoData->orderid).',
                        "amount": '.(int)$demoData->amount.',
                        "currency": "'.$demoData->currency.'",
                        "vat_amount": 0,
                        "shipping_address": '.json_encode($st_obj).',
                        "billing_address": '.json_encode($bt_obj).',
                        "partial_payment": false,
                        "items": [
                        {
                            "id": '.json_encode($demoData->orderid).',
                            "line_id": "1",
                            "type": "PRODUCT",
                            "description": '.json_encode($demoData->orderid).',
                            "quantity": 1,
                            "amount": '.(int)$demoData->amount.',
                            "vat_amount": 0,
                            "vat": 25
                        }
                        ]
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
        $test = json_decode($requestBody);
        if (empty($test)) {
            echo "json:\n";
            echo $requestBody;
            echo "\njson end\n";
        die('internal json malformatted error - '.json_last_error_msg());
        }
            //notes:
        //customer object is optional but for subscriptin payments you need to provide customer_id
        //

        //experimental features:

            if (!empty($demoData->customer_id))
            {
                $test->customer->customer_id = $demoData->customer_id;
            }

            $test->token_provider->token_types = array();
            if (!empty($demoData->generatePaymentToken)) {
                $test->token_provider->token_types[] = 'payment_token';
            }
            if (!empty($demoData->generateRecurrenceToken)) {
                $test->token_provider->token_types[] = 'recurrence_token';
            }
        return $test;
    }
}