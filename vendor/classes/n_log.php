<?php
class n_log {
	public static function _($data) {
		
		if (isset(n_log::$errorlevel))
		if ((n_log::$errorlevel !== n_log::ALL)) return; 
		
		if (!is_string($data)) $data = var_export($data, true);
        $data = 'LOG:'.$data; 		
		$path = MYROOT.DS.'logs'.DS.'log.txt'; 
		$data = $data."\n"; 
		if (!self::$writelog) return; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		error_log($data); 
	}
	const NONE = 0; 
	const ALL = 1;
	const NOTICE = 10;
	const QUERY = 15; 
	const DEBUG = 20;
	const WARNING = 30;
	const ERROR = 50;
	const SYNC = 35;
	
	static $errorlevel; 
	static $writelog; 
	static $msgs; 
	static $throw_exception = true; 
	
	public static function setErrorLevel($level, $isDefault=false) {
		
	  if (($isDefault) && (isset(n_log::$errorlovel))) {
	     return; 
	  }
	  
	  ini_set('error_reporting', E_ALL); 
	  ini_set('error_log', '/home/payee/logs/checkout_payee_no.php.error.log'); 
	  ini_set("log_errors", 1);
	  
	  n_log::$errorlevel = $level; 
	  self::$writelog = true; 
	  
	  
	  //$x = debug_backtrace(); 
	  //foreach ($x as $l) echo $l['file'].' '.$l['line']."<br />\n";
	  n_log::debug('n_log error level: '.$level); 
	}
	
	public static function getMsgs() {
		if (!empty(self::$msgs)) return self::$msgs; 
		return array(); 
	}
	public static function log($area, $data='') {
		if (!file_exists(MYROOT.DS.'logs')) {
			mkdir(MYROOT.DS.'logs', 0700, true); 
		}
		$dt = date('Y_m_d'); 
		$dir = MYROOT.DS.'logs'.DIRECTORY_SEPARATOR.$area.DIRECTORY_SEPARATOR.$dt; 
		if (!file_exists($dir)) {
			mkdir($dir, 0700, true); 
		}
		$ms = microtime(true); 
		$ms = $ms * 1000; 
		$ms = round($ms, 0); 
		$ms = (int)$ms; 
		
		if (!file_exists($dir.DIRECTORY_SEPARATOR.'.htaccess')) {
			$ht = 'require all denied'; 
			file_put_contents($dir.DIRECTORY_SEPARATOR.'.htaccess', $ht); 
		}
		if (!file_exists($dir.DIRECTORY_SEPARATOR.'index.php')) {
			$ht = 'error_log(\'direct access not allowed\');'; 
			file_put_contents($dir.DIRECTORY_SEPARATOR.'index.php', $ht); 
		}
		
				$i = 32; 
				$cstrong = true; 
				$bytes = openssl_random_pseudo_bytes($i, $cstrong);
				$hex1   = bin2hex($bytes);
		
		$path = $dir.DIRECTORY_SEPARATOR.$ms.'_'.$hex1.'.log'; 
		$path_tmp = $dir.DIRECTORY_SEPARATOR.$ms.'_'.$hex1.'.log.tmp'; 
		
		$path_debug = $dir.DIRECTORY_SEPARATOR.$ms.'_'.$hex1.'.debug.log'; 
		$path_debug_tmp = $dir.DIRECTORY_SEPARATOR.$ms.'_'.$hex1.'.debug.log.tmp'; 
		if (!empty($data)) {
		if (is_object($data)) {
				$data = json_encode($data, JSON_PRETTY_PRINT); 
		}
		else		
		if (!is_string($data)) {
			$data = var_export($path_tmp, true); 
		}
		file_put_contents($path_tmp, $data); 
		rename($path_tmp, $path); 
		}
		$obj = new stdClass(); 
		$obj->SERVER = $_SERVER; 
		if (isset($_POST) && (!empty($_POST))) {
			$obj->POST = $_POST; 
		}
		if (isset($_GET) && (!empty($_GET))) {
			$obj->GET = $_GET; 
		}
		if (isset($_COOKIE) && (!empty($_COOKIE))) {
			$obj->COOKIES = $_COOKIE; 
		}
		
		if (function_exists('getallheaders')) {
			$obj->HEADERS = getallheaders(); 
		}
		$obj->RAW_INPUT = file_get_contents('php://input'); 
		
		$debug = json_encode($obj, JSON_PRETTY_PRINT); 
		file_put_contents($path_debug_tmp, $debug); 
		rename($path_debug_tmp, $path_debug); 
		
		
	}
	public static function manual($errorMsg='', $value='', $item=null, $extra='') {
		if (php_sapi_name() !== 'cli') return; 
		$line = '"'.str_replace('"', '\"', $errorMsg).'";'; 
		$line .= '"'.str_replace('"', '\"', $value).'";'; 
		
		if (empty($item)) {
			$item = new stdClass(); 
			$item->vm_product = array(); $item->vm_product['product_sku'] = ''; 
			$item->vm_custom = array(); $item->vm_custom['customfield_value'] = '';
			$item->extra = array(); $item->extra['_SKUstary_eshop'] = '';
			$item->vm_lang = array(); $item->vm_lang['product_name'] = ''; 
		}
		$sku = $item->vm_product['product_sku']; 
		$skup = ''; 
		
		if (stripos($item->vm_product['product_sku'], ':')!==false) {
			$a = explode(':', $item->vm_product['product_sku']); 
			$sku = $a[1]; 
			$skup = $a[0]; 
		}
		$line .= '"'.$skup.'";'; 
		$line .= '"'.$sku.'";'; 
		
		$line .= '"'.str_replace('"', '\"', $item->vm_custom['customfield_value']).'";'; 
		$line .= '"'.str_replace('"', '\"', $item->vm_lang['product_name']).'";'; 
		$line .= '"'.str_replace('"', '\"', $item->extra['_SKUstary_eshop']).'";'; 
		
		$line .= '"'.str_replace('"', '\"', $extra).'";'; 
				
		
		//self::$msgs[$line] = $line; 
		if (php_sapi_name() !== 'cli') 
		if (is_string($line))
		self::$msgs['notice'][md5($line)] = $line; 
		register_shutdown_function(array('n_log', 'writemanual')); 
		
	}
	public static function writemanual() {
		static $run; 
		if (!empty($run)) return; 
		$run = true; 
		
		if (empty(self::$msgs)) return; 
		$data =  'MANUALNE:'."\n"; 
		foreach (self::$msgs as $area=>$datas) {
			foreach ($datas as $line) {
				$data .= $area.':'.$line; 
			}
		}
		
		$path = MYROOT.DS.'logs'.DS.'manual.txt'; 
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		error_log($data); 
	}
	
	public static function red($data) {
		if (php_sapi_name() !== 'cli') 
		if (is_string($data))
		self::$msgs['notice'][md5($data)] = $data; 
		
		if (isset(n_log::$errorlevel))
		if ((n_log::$errorlevel > n_log::NOTICE)) return; 
	
		if (!is_string($data)) $data = var_export($data, true); 
		$data =  'NOTICE:'.$data; 
		if (php_sapi_name() === 'cli') {
		 echo "\033[31m ".$data.chr(27) . "[0m"."\n"; 
		 flush(); 
		}
		
		if (!self::$writelog) {
			
			return; 
		}
		$path = ini_get('error_log'); 
		//$path = MYROOT.DS.'logs'.DS.'notice.txt'; 
		if (!empty($path)) {
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		error_log($data); 
		}
	}
	
	
	public static function sendmail($data, $extra=array(), $to='') {
		return; 
		
		if (php_sapi_name() !== 'cli') 
		if (is_string($data))
		self::$msgs['notice'][md5($data)] = $data; 
		
		
	
		if (!is_string($data)) $data = '<pre>'.var_export($data, true).'</pre>'; 
		
		if (php_sapi_name() === 'cli') {
		 echo "\033[31m ".$data.chr(27) . "[0m"."\n"; 
		 flush(); 
		}
		
		
		$path = MYROOT.DS.'logs'.DS.'mail.txt'; 
		$dataw = $data."\n".var_export($extra, true)."\n"; 
		
		$phptime = time(); $dataw = date("Y-m-d H:i:s", $phptime).' '.$data;
		if (!file_exists($path)) {
			//file_put_contents($path, $dataw); 
			error_log($dataw); 
		}
		else {
			//file_put_contents($path, $dataw, FILE_APPEND); 
			error_log($dataw); 
		}
		
		n_mail::sendmail($data, $extra, $to); 
		
	}
	
	public static function notice($data) {
		if (php_sapi_name() !== 'cli') 		
		if (is_string($data))
		self::$msgs['notice'][md5($data)] = $data; 
		
		if (isset(n_log::$errorlevel))
		if ((n_log::$errorlevel > n_log::NOTICE)) return; 
		
		
		
		if (!is_string($data)) $data = var_export($data, true); 
		$data =  'NOTICE:'.$data; 
		if (php_sapi_name() === 'cli') {
		echo $data."\n"; 
		flush(); 
		}
		
		if (!self::$writelog) {
			
			return; 
		}
		$path = MYROOT.DS.'logs'.DS.'notice.txt'; 
		$path = ini_get('error_log'); 
		if (!empty($path)) {
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		//error_log($data); 
		}
	}
	public static function printError($e='', $debug='') {
		
		$error = error_get_last();
		if (php_sapi_name() !== 'cli') {
			header("HTTP/1.0 400 Not Found");
		}
		$obj = new stdClass(); 
		if (is_string($e)) {
			$obj->error = 'Internal error'; 
			if (defined('ENV_DEVELOPMENT') && (ENV_DEVELOPMENT === true)) 
			{
				$obj->message = $debug;
			}					 
			//$obj->error = $e; 
			
		}
		else {
			$obj->error = 'api_not_available'; 
		}
	    $obj->error_description = 'Error'; 
		
		$x = debug_backtrace(); 
		$trace = array(); 
		$trace[] = 'API Error'; 
		
		if (!empty($error)) {
		foreach ($error as $k=>$v) {
			$trace[] = $k.':'.$v; 
		}
		}
		
		foreach ($x as $l) {
			if (isset($l['file'])) {
				$trace[] = @$l['file'].':'.@$l['line']; 
			}
		}
		
		if (defined('ISDEBUG') && ISDEBUG) {
		
		$obj->trace = $trace; 
		$obj->e = $e; 
		$obj->debug = $debug; 
		$msg = implode("\n", $trace); 
		}
		//n_log::warning($msg); 
		if (!empty($e) && (is_object($e))) {
			if (method_exists($e, 'getMessage')) {
				n_log::warning($e->getMessage()); 
			}
		}
		//if ($_SERVER['REMOTE_ADDR'] === '176.116.104.134') 
		if (false)
		{
			
		}
		
		echo json_encode($obj); 
		
		$obj->e = $e;
		if (isset($trace)) {
		$obj->trace = $trace;
		}		
		//$obj->e = $e->getMessage(); 
		error_log(var_export($obj, true)); 
		
		
		die(); 
	}
	public static function error($data) {
		
		if (is_string($data)) {
			$msg = $data; 
		}
		else {
			$msg = ''; 
		}
		
		if (!is_string($data)) $data = var_export($data, true); 
		$data =  'ERROR:'.$data; 
		//if (php_sapi_name() === 'cli') 
		if (php_sapi_name() === 'cli') 
		{
		$x = debug_backtrace(); 
		foreach ($x as $l) {
			if (isset($l['file'])) {
			$data .= $l['file'];
			}
			if (isset($l['line'])) {
		    $data .= ' '.$l['line']; 
			}
			$data .= "\n";
		}
		{
		
		fwrite(STDERR, $data);
		flush(); 
		}
		}
		
		if (php_sapi_name() !== 'cli') 
		{
		if (is_string($data))
		self::$msgs['error'][md5($data)] = $data; 
		}
		else {
			fwrite(STDERR, $data);
		}
		
		
		
		$path = MYROOT.DS.'logs'.DS.'error.txt'; 
		if (!is_dir(MYROOT.DS.'logs')) {
			mkdir(MYROOT.DS.'logs'); 
		}
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		
		$x = debug_backtrace(); 
		foreach ($x as $l) {
			if (isset($l['file'])) {
			$data .= $l['file'];
			}
			if (isset($l['line'])) {
		    $data .= ' '.$l['line']; 
			}
			$data .= "\n";
		}
		
		if (!file_exists($path)) {
			//file_put_contents($path, $data); 
			error_log($data); 
		}
		else {
		 //file_put_contents($path, $data, FILE_APPEND); 
		 error_log($data); 
		}
		if (!empty(self::$throw_exception)) {
			
			throw new Exception($data); 
		}
		else {
		
		
			
			
		
		}
		$msg = 'Internal error'; 
		self::printError($msg, $data); 
		
	}
	
	
	public static function exception($data) {
		
		n_mail::sendmail("Exception", $data); 
		
		if (!is_string($data)) $data = var_export($data, true); 
		$data =  'ERROR:'.$data; 
		//if (php_sapi_name() === 'cli') 
		$x = debug_backtrace(); 
		foreach ($x as $l) {
			if (isset($l['file'])) {
			$data .= $l['file'];
			}
			if (isset($l['line'])) {
		    $data .= ' '.$l['line']; 
			}
			$data .= "\n";
		}
		{
		echo $data."\n"; 
		flush(); 
		}
		
		if (php_sapi_name() !== 'cli') 
		{
		if (is_string($data))
		self::$msgs['error'][md5($data)] = $data; 
		}
		else {
			fwrite(STDERR, $data);
		}
		
		
		
		$path = MYROOT.DS.'logs'.DS.'error.txt'; 
		if (!is_dir(MYROOT.DS.'logs')) {
			mkdir(MYROOT.DS.'logs'); 
		}
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		if (!file_exists($path)) {
			//file_put_contents($path, $data); 
			error_log($data); 
		}
		else {
			error_log($data); 
		 //file_put_contents($path, $data, FILE_APPEND); 
		}
		
			throw new Exception($data); 
		
	}
	
	public static function helioserror($data) {
		
		if (!is_string($data)) $data = var_export($data, true); 
		$data =  'ERROR:'.$data; 
		//if (php_sapi_name() === 'cli') 
		$x = debug_backtrace(); 
		foreach ($x as $l) $data .= $l['file'].' '.$l['line']."\n"; 
		{
		echo '<span style="color:red;">'.$data."</span>\n"; 
		
		}
		
		if (php_sapi_name() !== 'cli') 
		if (is_string($data))
		self::$msgs['helioserror'][md5($data)] = $data; 
		
		
		
		$path = MYROOT.DS.'logs'.DS.'helioserror.txt'; 
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		error_log($data); 
		
	}
	
	public static function debug($data) {
		if (isset(n_log::$errorlevel))
		if ((n_log::$errorlevel > n_log::DEBUG)) return; 
		//if (DISPLAY_ERRORS == 1)
		//echo $data."\n"; 
		if (is_string($data))
		self::$msgs['debug'][md5($data)] = $data; 	
	
		if (!self::$writelog) return; 
	    if (!is_string($data)) $data = var_export($data, true); 
		$path = MYROOT.DS.'logs'.DS.'debug.txt'; 
		$path = ini_get('error_log'); 
		if (!empty($path)) {
		$data =  'DEBUG:'.$data; 
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		error_log($data); 
		}
	}
	
	public static function warning($data) {
		
		//if (is_string($data))
		//self::$msgs[$data] = $data; 
		if (php_sapi_name() !== 'cli') 
		{
		if (is_string($data))
		self::$msgs['warning'][md5($data)] = $data; 	
		}
		

		if (isset(n_log::$errorlevel))
		if ((n_log::$errorlevel > n_log::WARNING)) return; 
		if (!is_string($data)) $data = var_export($data, true); 
		$data =  'WARNING:'.$data; 
		if (php_sapi_name() === 'cli') {
		
		fwrite(STDERR, $data."\n");
		
		echo $data."\n"; 
		flush(); 
		}
		if (!self::$writelog) {
			//if (is_string($data))
			//self::$msgs[$data] = $data; 
			return; 
		}
		$path = MYROOT.DS.'logs'.DS.'warning.txt'; 
		$path = ini_get('error_log'); 
		if (!empty($path)) {
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		error_log($data); 
		}
	}
	
	public static function query($class, $data, $print=false) {
		
		if (isset(n_log::$errorlevel))
		if ((n_log::$errorlevel > n_log::QUERY)) return; 
		$class = get_class($class); 
		if (!is_string($data)) $data = var_export($data, true); 
		$data =  '---------- QUERY: '."\n\n".$data."\n\nEND QUERY----------\n\n"; 
		
		if ($print) {
			echo $data; 
			flush(); 
		}
		//echo $data."\n"; 
		if (!self::$writelog) return; 
		if (!empty($path)) {
		$path = MYROOT.DS.'logs'.DS.'db_'.$class.'.txt'; 
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		//error_log($data); 
		}
		
	}
	
	public static function sync($data) {
		if (isset(n_log::$errorlevel))
		if ((n_log::$errorlevel > n_log::SYNC)) return; 
	
		$data =  'SYNC:'.$data; 
		if (php_sapi_name() === 'cli') {
		echo $data."\n"; 
		flush(); 
		}
		if (!self::$writelog) return; 
		$path = ini_get('error_log'); 
		if (!empty($path)) {
		//$path = MYROOT.DS.'logs'.DS.'sync.txt'; 
		$data = $data."\n"; 
		$phptime = time(); $data = date("Y-m-d H:i:s", $phptime).' '.$data;
		//file_put_contents($path, $data, FILE_APPEND); 
		error_log($data); 
		}
	}
	//http://php.net/manual/en/function.set-error-handler.php
	public static function mapErrorCode($code) {
    $error = $log = null;
    switch ($code) {
        case E_PARSE:
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $error = 'Fatal Error';
            $log = LOG_ERR;
            break;
        case E_WARNING:
        case E_USER_WARNING:
        case E_COMPILE_WARNING:
        case E_RECOVERABLE_ERROR:
            $error = 'Warning';
            $log = LOG_WARNING;
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $error = 'Notice';
            $log = LOG_NOTICE;
            break;
        case E_STRICT:
            $error = 'Strict';
            $log = LOG_NOTICE;
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $error = 'Deprecated';
            $log = LOG_NOTICE;
            break;
        default :
		    $error = $code; 
            break;
    }
       //return array('error'=>$error, 'log'=>$log);
	   return $error;
	}
	
}

//default error log: 
n_log::setErrorLevel(n_log::ERROR, true); 



function n_log_fatal_handler() {
  $errfile = "unknown file";
  $errstr  = "shutdown";
  $errno   = E_CORE_ERROR;
  $errline = 0;

  $error = error_get_last();

  if( $error !== NULL) {
    $errno   = $error["type"];
    $errfile = $error["file"];
    $errline = $error["line"];
    $errstr  = $error["message"];
    $types = array(E_ERROR,  E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR); 
	if (!in_array($errno, $types)) return;
	$dates = date('c'); 
	$dataMsg = $errno.' '.$errstr.' in file: '.$errfile.' line: '.$errline." timestamp: ".$dates;
	{
		$em = ':'.n_log::mapErrorCode($errno); 
	    
		n_log::warning($em.$dataMsg);
		if (php_sapi_name() === 'cli') {
			n_log::notice('END: n_log_fatal_handler'); 
			return true; 
		}
		die(1); 
	}
    
  }
}

function n_log_exceptions_error_handler($severity, $message, $filename, $lineno) {
	$severity = n_log::mapErrorCode($severity); 
	if ($severity === 'Fatal Error') {
	  n_log::error($severity.': '.$message.' @ '.$filename.':'.$lineno); 
	}
	else {
		n_log::warning($severity.': '.$message.' @ '.$filename.':'.$lineno); 
	}
	return true; 
}
function n_log_exceptions_error_handler2($ex) {
		
		$msg = $ex->getMessage(); 
		
		$code = 0; 
		$file = ''; 
		$line = 'unknown'; 
		$trace = ''; 
		if (method_exists($ex, 'getCode'))
		$code = $ex->getCode(); 
		if (method_exists($ex, 'getFile'))
		$file = $ex->getFile(); 
		if (method_exists($ex, 'getLine'))
		$line = $ex->getLine(); 
		
		$trace .= $file.':'.$line."\n"; 
		
		if (method_exists($ex, 'getTraceAsString'))
		$trace .= $ex->getTraceAsString()."\n"; 
		
		
		
		if ((empty($code)) || ($code == E_WARNING) || ($code == 1054) || ($code == 1142)) {
			
			$code = E_ERROR; 
		}
	
		$severity = n_log::mapErrorCode($code); 
		
		if ($severity === 'Fatal Error') {
			n_log::error($severity.': '.$msg.' @ '.$trace); 
		}
		else {
			n_log::warning($severity.': '.$msg.' @ '.$file.':'.$line); 
		}
	    
		
		return true; 
	
	}


register_shutdown_function( "n_log_fatal_handler" );
set_error_handler('n_log_exceptions_error_handler'); 
set_exception_handler('n_log_exceptions_error_handler2'); 


