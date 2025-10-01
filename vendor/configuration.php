<?php

if (!defined('_DB_PREFIX_')) die(); 

$dbj_host = _DB_SERVER_; 
$dbj_user = _DB_USER_;
$dbj_pwd = _DB_PASSWD_;
$dbj_database = _DB_NAME_;
$dbj_prefix = _DB_PREFIX_; 
	

if (!defined('CONFIGURATION')) 
define('CONFIGURATION', __DIR__.DIRECTORY_SEPARATOR.'configuration.php'); 

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR); 
	
	
	
	
}


if (!defined('CLASSES')) 
define('CLASSES', __DIR__.DIRECTORY_SEPARATOR.'classes'); 

if (!defined('SCHEMAS')) 
define('SCHEMAS', __DIR__.DIRECTORY_SEPARATOR.'schemas'); 


if (!defined('DATADIR')) {
	define('DATADIR', __DIR__.DIRECTORY_SEPARATOR.'data'); 
}


if (!defined('SEP'))
define('SEP', '~>~'); 

if (!defined('MYROOT')) {
	define('MYROOT', __DIR__); 
}




 if (!defined('AUTOLOADREGISTERED')) {
define('AUTOLOADREGISTERED', true); 
spl_autoload_register(function ($class_name) {
	if (file_exists(CLASSES.DIRECTORY_SEPARATOR.$class_name.'.php')) {
		
		require(CLASSES.DIRECTORY_SEPARATOR.$class_name.'.php');
		 
		
	}
});
 }
	if (file_exists(__DIR__.DIRECTORY_SEPARATOR.'defines.php')) {
		require(__DIR__.DIRECTORY_SEPARATOR.'defines.php'); 
	}






