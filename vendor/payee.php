<?php

class payee {
	private static $authResponse; 
	
	public static function auth() {
		if (!empty(self::$authResponse)) return self::$authResponse; 
		
		
		$payeeApi = PAYEE_CONFIG_ACCESS_KEY.':'.PAYEE_CONFIG_SECRET_KEY;
		$apiKey = base64_encode($payeeApi);
		
		$request = array(); 
		$request["grant_type"] = "client_credentials"; 
		//stAn - subject to change in future (staging vs live )
		$request["audience"] = "https://api.payee.no/api/v1/accounts/".PAYEE_CONFIG_MERCHANT_NUMBER; 
		$requestJson = json_encode($request);
		$endpoint = PAYEE_ENDPOINT."/accounts/".PAYEE_CONFIG_MERCHANT_NUMBER."/auth/token";
		
		if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
			die('Endpoint URL is not valid: '.$endpoint); 
		}
		
		$headers = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Basic ' . $apiKey
		);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $requestJson);
		curl_setopt($curl, CURLOPT_URL, $endpoint);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);

		$rawResponse = curl_exec($curl); 
	 
		curl_close($curl); 
		$authResponse = json_decode($rawResponse);
		if (empty($rawResponse)) {
			throw new Exception('Error authenticating against payee.no'); 
		}
		if (empty($authResponse) || (!isset($authResponse->access_token))) {
			throw new Exception('Error authenticating against payee.no'); 
		}
		else if (!empty($authResponse->access_token)) {
			self::debug('Access Token OK - '.$authResponse->access_token);
		}
		self::$authResponse = $authResponse; 

		return $authResponse;

	}
	
	//this is going to recur a transaction which had already been done per existing recur token
	//a difference against recurTransaction is that this one allows to set a different description and references compared to recurTransaction
	//and thus a different/new return/notify URL's will be triggered 
	public static function recurPayment($is_live, $rt, $demoData) {
		
		$jsonObj = self::getPaymentJson($is_live, $demoData); 
		if (!isset($jsonObj->customer)) $jsonObj->customer = new stdClass(); 
		
		$remote_paymentRef = 'payex.creditcard'; 
		//we'll follow dintero syntax here for now: 
		//per this - https://docs.dintero.com/docs/checkout/tokenization/
		$jsonObj->customer->tokens = new stdClass(); 
		$jsonObj->customer->tokens->{$remote_paymentRef} = new stdClass(); 
		$jsonObj->customer->tokens->{$remote_paymentRef}->recurrence_token = $rt; 
		
		if (!isset($jsonObj->payment)) $jsonObj->payment = new stdClass(); 
		
		$jsonObj->payment->payment_product_type = $remote_paymentRef;
		$jsonObj->payment->operation = 'recurring_purchase';
		
		//$jsonObj->payment->operation = 'unscheduled_purchase';
		$jsonObj->order->merchant_reference = 'RECUR-'.time(); 
		
		return self::sendJson($jsonObj); 
	} 
	//production ready, is made similar to https://docs.dintero.com/checkout-api.html#operation/checkout_session_post
	
	public static function getPaymentJson($is_live, $demoData) {
		 
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


		$language = 'nb-NO'; 
		if (!empty($demodata->language)) {
			$language = $demodata->language;
		}

		if (!in_array($language, array('sv-SE', 'nb-NO', 'da-DK', 'en-US', 'fi-FI'))) 
		{
			$language = 'nb-NO'; 
		}

	//is_live=false is used for transaction validation
	//$is_live = true; 
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
		$test = json_decode($requestBody); 
		if (empty($test)) {
			self::debug( $requestBody);
			self::debug('internal json malformatted error - '.json_last_error_msg()); 
			throw new Exception('payee.no: internal json malformatted error'); 
		}

		//notes: 
		//customer object is optional but for subscriptin payments you need to provide customer_id
		//

		//experimental features:
		$experimental = true; 

		if ($experimental) { 

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
		}

		return $test; 
	}
	
	
	public static function sendJson($jsonObj) {
		
		$authResponse = self::auth(); 
		
		$checkoutUrl = PAYEE_ENDPOINT."/sessions";
		
		if (!isset($authResponse->access_token)) 
		{
			self::debug(__LINE__.': oauth had failed: '.var_export($authResponse, true)); 
			throw new Exception('payee.no oauth had failed'); 
		}
		
		$requestBody = json_encode($jsonObj, JSON_PRETTY_PRINT);  
		
		$contentLength = strlen($requestBody);
		 
		$token = $authResponse->access_token; 
		
		$headers = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $token
		);
	
		$debug_json = json_encode(json_decode($requestBody), JSON_PRETTY_PRINT);		 
		 
		if (!filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
			throw new Exception('payee.no checkoutUrl URL is not valid: '.$checkoutUrl); 
		}
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $requestBody);
		curl_setopt($curl, CURLOPT_URL, $checkoutUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);
		
		$rawResponse = curl_exec($curl);
		$error = curl_error($curl);     // human-readable message 
		$http = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); // e.g., 200, 404
		curl_close($curl);  
	
		$response = json_decode($rawResponse);		

		if (!empty($response) && (!empty($response->error_description))) {
			return $response; 
		}

		if ((empty($response) || (!isset($response->id)))) {
			var_dump($rawResponse); 
			var_dump($requestBody); 
			var_dump($checkoutUrl); 
			
			$msg = 'Malformatted response from payee.no, http response = '.$http.', error='.$error; 
			die($msg); 
			throw new Exception($msg); 
			self::debug($rawResponse); 
			
		}

		self::debug( 'endpoint '.$checkoutUrl); 
		self::debug('transaction_id: '.$response->id); 
		self::debug('payment_url: '.$response->url); 
		
		return $response;   
		
	}

	public static function getPaymentUrl($is_live) {
		if (empty(self::$configFile)) {
			throw new Exception('Please use payee::setConfig(\'config.php\') to set default customer datas for unit testing.', 500); 
		}
		require(self::$configFile);  
		
		
		$jsonObj = self::getPaymentJson($is_live, $demoData); 
		return self::sendJson($jsonObj); 
	
	}
	
	
	public static function searchTransactions($obj) {
		if (empty(self::$configFile)) {
			throw new Exception('Please use payee::setConfig(\'config.php\') to set default customer datas for unit testing.', 500); 
		}
		require(self::$configFile);  
		$authResponse = self::auth(); 
		$token = $authResponse->access_token; 
		$headers = array(
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $token
		);
		/*
		$obj = new stdClass(); 
		$obj->id = $transaction_id; 
		
		$obj->limit = 0; 
		$obj->limitstart = 0; 
		
		$obj->merchant_reference = 'TEST'; 
		$obj->currency = 'NOK';
		
		$obj->amount = 50; 
		$obj->amount_gte = 0;
		$obj->amount_lte = 0;
		
		$obj->captured_at_gte = date('Y-m-d', 'yesterday'); 
		$obj->captured_at_lte = date('Y-m-d', 'yesterday');; 
		
		$obj->created_at_gte = date('Y-m-d', 'yesterday');
		$obj->created_at_lte = date('Y-m-d', 'yesterday');
		*/
		
		$checkTransactionUrl = $entryPoint."/transactions/?";
		$search = array(); 
		foreach ($obj as $key=>$val) {
			if (is_null($val)) continue; 
			$search[] = urlencode($key).'='.urlencode($val); 
		}
		$checkTransactionUrl .= implode('&', $search); 
		
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		
		curl_setopt($curl, CURLOPT_URL, $checkTransactionUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);

		$rawResponse = curl_exec($curl);
		
		curl_close($curl); 
		
		$testx = json_encode(json_decode($rawResponse), JSON_PRETTY_PRINT); 
		self::debug(__FUNCTION__.': - '.$checkTransactionUrl); 
		
		$ret = json_decode($rawResponse); 
		if (empty($ret)) {
			self::debug($rawResponse); 
		}
		else {
			self::debug(json_encode($ret, JSON_PRETTY_PRINT));
		}
		if (empty($ret)) {
			
			self::debug( 'Error in reply from '.$checkTransactionUrl ); 
			self::debug( $rawResponse ); 
			throw new Exception('Error in reply from '.$checkTransactionUrl); 
		}
		return $ret; 
	}
	public static function isPaid($transaction) {
		 
		$response = $transaction;  

		if (empty($response) || (!is_object($response))) return false; 

		if ($response->status === 'CAPTURED') return true; 

		if ($response->status === 'ABORTED') {
			//self::logData($getParameters["merchant_reference"], $logData); 
			return false; 
		}
	
	
		if ($response->payment_product === 'payex') 
		{
			//for payex it seems to be enough to have just the authorization
			if ($response->status === 'AUTHORIZED') return true; 
			
		}
	
		if ($response->payment_product === 'Invoice') 
		{ 
			//for payex it seems to be enough to have just the authorization
			if ($response->status === 'AUTHORIZATION_COMPLETED') return true; 
			if (stripos($response->status, 'COMPLETED') !== false) return true; 
			
		}

		if ($response->payment_product === 'CreditCard') 
		{  			
			if ($response->status === 'AUTHORIZATION_COMPLETED') return true; 
			if (stripos($response->status, 'COMPLETED') !== false) return true; 
			
		}	

		if ($response->payment_product === 'Vipps') 
		{ 			 
			if ($response->status == 'CAPTURED')  return true; 
			if ($response->status === 'AUTHORIZATION_COMPLETED') return true; 			
		}
 
		return false; 
	
	
	}

	 
	public static function getTransaction($transaction_id) {
		 
		$authResponse = self::auth(); 
		$token = $authResponse->access_token; 
$headers = array(
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
);

		$checkTransactionUrl = PAYEE_ENDPOINT."/transactions/{transaction_id}";
		$checkTransactionUrl = str_replace('{transaction_id}', $transaction_id, $checkTransactionUrl); 
		
		
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		
		curl_setopt($curl, CURLOPT_URL, $checkTransactionUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);

		$rawResponse = curl_exec($curl);
		curl_close($curl); 
		
		$testx = json_encode(json_decode($rawResponse), JSON_PRETTY_PRINT); 
		self::debug(__FUNCTION__.': - '.$checkTransactionUrl); 
		$ret = json_decode($rawResponse); 
		if (empty($ret)) {
			self::debug($rawResponse); 
		}
		else {
			self::debug(json_encode($ret, JSON_PRETTY_PRINT));
		}
		
		return json_decode($rawResponse); 
	}
	public static function deleteToken($transaction_id) {
		$authResponse = self::auth(); 
		$token = $authResponse->access_token; 
$headers = array(
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
);

		//$checkTransactionUrl = PAYEE_ENDPOINT."/transactions/{transaction_id}/cancel";
		$checkTransactionUrl = PAYEE_ENDPOINT."/transactions/{transaction_id}/deletetoken";
		$checkTransactionUrl = str_replace('{transaction_id}', $transaction_id, $checkTransactionUrl); 
		
		
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
		
		curl_setopt($curl, CURLOPT_URL, $checkTransactionUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);

		$rawResponse = curl_exec($curl);
		curl_close($curl); 
		
		$testx = json_encode(json_decode($rawResponse), JSON_PRETTY_PRINT); 
		self::debug(__FUNCTION__.': - '.$checkTransactionUrl); 
		$ret = json_decode($rawResponse); 
		if (empty($ret)) {
			self::debug($rawResponse); 
		}
		else {
			self::debug(json_encode($ret, JSON_PRETTY_PRINT));
		}
		
		return json_decode($rawResponse); 
	}
	public static function verifyTransaction($transaction_id) {
		
		$authResponse = self::auth(); 
		$token = $authResponse->access_token; 
$headers = array(
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
);

		$checkTransactionUrl = PAYEE_ENDPOINT."/transactions/{transaction_id}/verify";
		$checkTransactionUrl = str_replace('{transaction_id}', $transaction_id, $checkTransactionUrl); 
		
		
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		
		curl_setopt($curl, CURLOPT_URL, $checkTransactionUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);

		$rawResponse = curl_exec($curl);
		curl_close($curl); 
		
		$testx = json_encode(json_decode($rawResponse), JSON_PRETTY_PRINT); 
		self::debug(__FUNCTION__.': - '.$checkTransactionUrl); 
		$ret = json_decode($rawResponse); 
		if (empty($ret)) {
			self::debug($rawResponse); 
		}
		else {
			self::debug(json_encode($ret, JSON_PRETTY_PRINT));
		}
		
		return json_decode($rawResponse); 
	}
	private static $configFile = ''; 
	public static function setConfig($fn) {
		if (!file_exists($fn)) {
			throw new Exception('Please use payee::setConfig(\'config.php\') to set default customer datas for unit testing.', 500); 
		}
		self::$configFile = $fn; 
	}
	
	//this recurs already existing transaction which was initially sent to create recur token
	// inputs are needed here, it uses same references as initial (i.e. same order number might be shown to customer as initial order number)
	public static function recurTransaction($transaction_id) {
		if (empty(self::$configFile)) {
			throw new Exception('Please use payee::setConfig(\'config.php\') to set default customer datas for unit testing.', 500); 
		}
		require(self::$configFile);  
		$authResponse = self::auth(); 
		$token = $authResponse->access_token; 
$headers = array(
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
);

		$checkTransactionUrl = $entryPoint."/transactions/{transaction_id}/recur";
		$checkTransactionUrl = str_replace('{transaction_id}', $transaction_id, $checkTransactionUrl); 
		
		
		
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
		
		curl_setopt($curl, CURLOPT_URL, $checkTransactionUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);

		$rawResponse = curl_exec($curl);
		curl_close($curl); 
		
		$testx = json_encode(json_decode($rawResponse), JSON_PRETTY_PRINT); 
		self::debug(__FUNCTION__.': - '.$checkTransactionUrl); 
		$ret = json_decode($rawResponse); 
		if (empty($ret)) {
			self::debug($rawResponse); 
		}
		else {
			self::debug(json_encode($ret, JSON_PRETTY_PRINT));
		}
		
		return json_decode($rawResponse); 
	}
	
	public static function debug($msg) {
		 
	}
	
	
	
	
}
