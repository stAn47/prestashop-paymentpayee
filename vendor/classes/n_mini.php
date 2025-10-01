<?php

/*
*
* @copyright Copyright (C) 2007 - 2013 RuposTel - All rights reserved.
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* One Page checkout is free software released under GNU/GPL and uses code from VirtueMart
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* 
*/



class n_mini
{
  // moved from opc loaders so we do not load loader when not needed
	static $modelCache; 
	static $cache; 
 public static function calculateSlug($product_name, $id=0) {
		
		$product_name = trim($product_name);
		$product_name = n_mini::remove_accent($product_name); 
		$product_name = strtolower($product_name); 
		$product_name = str_replace(array('  '), array(' '), $product_name); 
		//$product_name = str_replace(' ', '_', $product_name); 
		$product_name = preg_replace('/[^a-zA-Z0-9]+/', '_', $product_name);
		$product_name = trim($product_name, '_');
		if (!empty($id)) {
		$product_name = strtolower($product_name.'-'.$id); 
		}
		//$unique = $item->vm_product['product_sku']; 
		//$t1 = urlencode($unique); 
		return $product_name; 
	}
	
	public static function toUTC($date='now') {
		$was_default = date_default_timezone_get(); 
		date_default_timezone_set('Europe/Berlin');
		$dateOrig = $date; 
		if ($date !== 'now') {
			
		$x = strtotime($date);
		$date = date ("Y-m-d H:i:s", $x);
		}	
		
		$utc_date = new DateTime($date); 
		
		
		$utc_date->setTimeZone(new DateTimeZone('UTC'));
		$ret_dat = $utc_date->format('Y-m-d G:i');
		date_default_timezone_set($was_default); 
		return $ret_dat; 
	}
	
	public static function makeSafe($path)
	{
		$regex = array('#[^A-Za-z0-9_\\\/\(\)\[\]\{\}\#\$\^\+\.\'~`!@&=;,-]#');

		return preg_replace($regex, '', $path);
	}
	public static function fetchUrl($url, $XPost='', $username='', $password='', $compress=false, $fileuploadpath=array(), $opts=array())
	{
		$msg = 'OPC Notice: Curl to URL: '.$url; 
		if (!is_array($XPost)) $msg .= ' data length '.strlen($XPost).' bytes';
		//error_log( $msg ); 
		$starttime = microtime(true); 
		
		if (!function_exists('curl_init'))
		{
			return file_get_contents($url); 
		}
		
		
		
		$ch = curl_init(); 
		
		//	 curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($ch, CURLOPT_URL,$url); // set url to post to
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable
		
		if ((!empty($username)) && (!empty($password)))
		curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
		
		if (defined('CURLOPT_FOLLOWLOCATION'))
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 8000); // times out after 4s
		
		if (!empty($XPost))
		curl_setopt($ch, CURLOPT_POST, 1); 
		else
		curl_setopt($ch, CURLOPT_POST, 0); 
		
		
		if ((!empty($fileuploadpath)) && (!empty($XPost)) && (is_array($XPost))) {
			if (function_exists('CurlFile')) {
				$XPost['xml_post_name'] = new CurlFile($fileuploadpath['filepath'], $fileuploadpath['mime'], 'xml_post_name');
			}
			else {
				if (function_exists('curl_file_create')) {
					$XPost['xml_post_name'] = curl_file_create($fileuploadpath['filepath'], $fileuploadpath['mime'], 'xml_post_name');
				}
			}
		}
		
		
		if (!empty($XPost))
		curl_setopt($ch, CURLOPT_POSTFIELDS, $XPost); // add POST fields
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.34 Safari/537.36');
		
		if ((!isset($opts[CURLOPT_COOKIEFILE])) && (!isset($opts[CURLOPT_COOKIEJAR]))) {
		$cookie = DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'curl_cookies_'.uniqid(rand()).'.txt';
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
		}
		
		

		if (!empty($compress)) {
			curl_setopt($ch, CURLOPT_ENCODING , "gzip");
		}
		
		//curl_setopt($ch, CURLOPT_ENCODING , "gzip");
		
		
		foreach ($opts as $k => $v) {
			curl_setopt($ch, $k, $v); 
		}
		
		
		$result = curl_exec($ch);   
		
   if (!empty($opts[CURLINFO_HEADER_OUT]))
		{
			
			$outHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);
			
			$result = $outHeaders."\n".$result;
		}
		
		$endtime = microtime(true); 
		$dur = $endtime - $starttime; 
		
		
		//error_log('OPC Curl took '.number_format($dur, 4, '.', ' ').' s'); 
		
		
		
		if ( curl_errno($ch) ) {    

			
			$err = 'ERROR -> ' . curl_errno($ch) . ': ' . curl_error($ch); 
			n_log::notice($err, 'CURL');
			
			
			
			@curl_close($ch);
			if (!empty($cookie))
			if (!isset($opts[CURLOPT_COOKIEFILE])) {
			if (file_exists($cookie)) {
				@unlink($cookie); 
			}
			}
			return false; 
		} else {
			
			
			
			$returnCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			n_log::notice($url.' -> '.$returnCode, 'CURL');
			switch($returnCode){
			case 404:
				
				
				@curl_close($ch);
				if (!empty($cookie))
				if (!isset($opts[CURLOPT_COOKIEFILE])) {
				if (file_exists($cookie)) {
					@unlink($cookie); 
				}
				}
				return false; 
				break;
			case 200:
				break;
			default:
							
				
				@curl_close($ch);
					if (!empty($cookie))
					if (!isset($opts[CURLOPT_COOKIEFILE])) {
					if (file_exists($cookie)) {
						@unlink($cookie); 
					}
					}
				return false; 
				break;
			}
		}
		
		
		
		@curl_close($ch);
					if (!empty($cookie))
					if (!isset($opts[CURLOPT_COOKIEFILE])) {
					if (file_exists($cookie)) {
						@unlink($cookie); 
					}
					}

		return $result;   
		
		

	}
	
 public static function extExists($ext) {
	 static $c; 
	 if (isset($c[$ext])) return $c[$ext]; 
   $db = c_db::getDBO(); 
   $q = "select * from #__extensions where element = '".$db->escape($ext)."' limit 0,1"; 
   $db->setQuery($q); 
   $r = $db->loadAssoc(); 
   if (empty($r)) return false; 
   $c[$ext] = (array)$r; 
   return $c[$ext]; 
 }
 
 //sorts array by value keeping the index
 public static function sortTxtArray($arr) {
		$copy = $arr; 
		usort($copy, array('n_mini', "sort_txt"));
		
		$ret = array(); 
		//for ($i=0; $i<count($copy); $i++)
		foreach ($copy as $i=>$val3)
		{
		foreach ($arr as $key=>$val) 
		{
			 $val2 = $copy[$i]; 
			 if ($val2 === $val) {
				 $ret[$key] = $val; 
			 }
		 }
		}
		
		$arr = $ret; 
		return $ret; 
 }
 
 public static function sort_txt($a, $b) {
	 $c = new Collator('sk_SK');
	 $t1 = $c->compare($a, $b); 
	 return $t1; 
	 
 }
 
 //if str1 contains strings str2
 public static function contains($str1, $str2, $word='{word}', &$ign=array()) {
	 $str1 = n_mini::remove_accent($str1); 
	 $needle = str_replace('{word}', $str2, $word); 
	 $needle = n_mini::remove_accent($needle); 
	 $str1 = mb_strtolower($str1); 
	 $needle = mb_strtolower($needle); 
	 if (stripos($str1, $needle)!==false) return true; 
	 
	 $str2 = n_mini::remove_accent($str2); 
	 $str2 = mb_strtolower($str2); 
	 
	 $str2 = trim($str2); 
	 $str1 = trim($str1); 
	 $needle = trim($needle); 
	 
	 $synonyms = n_schema::getSchema('x_synonyms'); 
	
	 n_log::notice('str1: '.$str1.' str2:'.$needle); 
	
	 foreach ($synonyms as $key=>$words) {
		 
		 if (isset($ign[$key])) continue; 
		 if (in_array($str2, $words)) {
			 
			
			$ign[$key] = true; 
			 
			 foreach ($words as $syn) {
			   $ret = self::contains($str1, $syn, $word, $ign); 
			   if ($ret !== false) return $ret; 
			 }
		 }
	 }
	 
	 return false; 
 }
 
 
 
 public static function setVMLANG() {
	 
	if (!defined('VMLANG')) define('VMLANG', 'sk_sk'); 
		
 }
 // gets rid of any DB references or DB objects, all objects are converted to stdClass
 public static function toObject(&$product, $return=false, $recursion=0) {
    
	
	
	if (is_object($product)) {
	 $copy = new stdClass(); 
	 $attribs = get_object_vars($product); 
	 $isO = true; 
	}
	elseif (is_array($product)) {
		  $copy = array(); 
		  $isO = false; 
		  $attribs = array_keys($product); 
		  $copy2 = array(); 
		  foreach ($attribs as $zza=>$kka) {
		       if (strpos($kka, "\0")===0) continue;
			   $copy2[$kka] = $product[$kka]; 
		  }
		  $attribs = $copy2; 
		}
		
	if (!empty($attribs))
    foreach ($attribs as $k=> $v) {
		
		if (strpos($k, "\0")===0) continue;
		if ($isO) {
	      $copy->{$k} = $v; 	
		}
		else
		{
			$copy[$k] = $v; 
		}
		if (empty($v)) continue; 
		//if ($recursion < 5)
		if ((is_object($v)) && (!($v instanceof stdClass))) {
		   $recursion++; 
		   if ($isO) {
		     n_mini::toObject($copy->{$k}, $recursion); 
		   }
		   else
		   {
			   n_mini::toObject($copy[$k], $recursion); 
		   }
		}
		else
		{
			
			if (is_array($v)) {
			   $recursion++; 
			   if ($isO) {
		        n_mini::toObject($copy->{$k}, $recursion); 
			   }
			   else
			   {
				   n_mini::toObject($copy[$k], $recursion); 
			   }
			}
		}
		/*
		if (is_array($v)) {
		
		  $keys = array_keys($v); 
	  
		  foreach ($keys as $kk2=>$z2) {
		     if (strpos($z2, "\0")===0) continue;
			 $copy->{$k}[$z2] = $v[$z2]; 
			 if ((is_object($v[$z2])) && (!($v[$z2] instanceof stdClass))) {
				$recursion++; 
			    n_mini::toObject($copy->{$k}[$z2]); 
			 }
			 else
			 if (is_array($v[$z2])) {
			    $recursion++; 
			    n_mini::toObject($copy->{$k}[$z2]); 
			 }
			 
		  }
		}
		*/
		
		
	}
	$recursion--;
	if (empty($copy)) return; 
	if (empty($return)) {
	$product = $copy; 
	}
	else return $copy; 
 }
 //http://php.net/manual/en/function.preg-replace.php
public static function remove_accent($str) 
{ 
  $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ'); 
  $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'); 
  return str_replace($a, $b, $str); 
} 

public static function post_slug($str) 
{ 
  return strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'), 
  array('', '-', ''), self::remove_accent($str))); 
} 
 
 public static function isMysql($ver, $operator='>=') {
   $db = c_db::getDBO(); 
   $q = 'SELECT @@version as version'; 
   $db->setQuery($q); 
   $version = $db->loadResult(); 
   
   if (stripos($version, '-')) {
     $versionA = explode('-', $version); 
	 if (count($versionA)>1) $version = $versionA[0]; 
   }
   return version_compare($version, $ver, $operator); 
   
 }
 public static function parseCommas($str, $sep=',', $cast=false)
 {
	  if (empty($str)) return array(); 
	  $e = explode($sep, $str); 
	  
	  
	  $ea = array(); 
	  if (count($e)>0) {
	    foreach ($e as $c) {
		  $c = trim($c); 
		  if ($c === '0') {
		   $ea[0] = 0; 
		   continue; 
		  }
		  if ($cast) {
		  $c = (int)$c; 
		  }
		  if (empty($c)) continue; 
		  $ea[$c] = $c; 
		}
	  }
	  else
	  {
		  $c = trim( $str ); 
		  if ($c === '0') {
		   $ea[0] = 0; 
		  }
		  if ($cast) {
		   $c = (int)$c; 
		  }
		  if (!empty($c)) $ea[$c] = $c; 
	  }
	  return $ea; 
 }
 
 public static function insertArray($table, &$fields, $def=array())
 {
	 return c_db::insertArray($table, $fields, $def); 
	 
	
	  
 }
 public static function toMSsqlTime($timestamp=null) {
	 if (is_null($timestamp)) $timestamp = time(); 
	 if ($timestamp === 0) return '1900-01-01 00:00:01.0'; 
	 $d = date("Y-m-d H:i:s", $timestamp); //datetime NOT NULL DEFAULT '0000-00-00 00:00:00' 
	 $d .= '.000'; 
	 return $d; 
 }
 public static function toMysqlTime($timestamp=null) {
	 if (is_null($timestamp)) $timestamp = time(); 
	 if ($timestamp === 0) return '0000-00-00 00:00:00'; 
	 return date("Y-m-d H:i:s", $timestamp); //datetime NOT NULL DEFAULT '0000-00-00 00:00:00' 
 }
 public static function removeEnters(&$metadesc, $resplaceWith='') {
	 $metadesc = str_replace("\r\r\n", $resplaceWith, $metadesc); 
	 $metadesc = str_replace("\r\n", $resplaceWith, $metadesc); 
	 $metadesc = str_replace("\n", $resplaceWith, $metadesc);
	 
	 $metadesc = str_replace('\r\r\n', $resplaceWith, $metadesc); 
	 $metadesc = str_replace('\r\n', $resplaceWith, $metadesc); 
	 $metadesc = str_replace('\n', $resplaceWith, $metadesc);
 }
 
 
 
 public static function getPrimary($table) {
	  if (!self::tableExists($table)) return array(); 
   $db = c_db::getDBO();
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) { 
   $table = str_replace('#__', $prefix, $table); 
   
   }
   if (isset(n_mini::$cache['primary_'.$table])) return n_mini::$cache['primary_'.$table]; 
   // here we load a first row of a table to get columns
   
   $q = 'SHOW COLUMNS FROM '.$table; 
   $db->setQuery($q); 
   $res = $db->loadAssocList(); 
  
   $new = '';
   if (!empty($res)) {
    foreach ($res as $k=>$v)
	{
		$field = (string)$v['Field']; 
		$auto = (string)$v['Extra']; 
		$key = (string)$v['Key']; 
		if (($key === 'PRI') || (stripos($auto, 'auto_increment')!==false)) {
			$new = $field; 
			break; 
		}
		
	}
	n_mini::$cache['primary_'.$table] = $new; 
	return $new; 
   }
   n_mini::$cache['primary_'.$table] = '';
   return array(); 
 }
 
 public static function getUnique($table) {
	  if (!self::tableExists($table)) return array(); 
   $db = c_db::getDBO();
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) { 
   $table = str_replace('#__', $prefix, $table); 
   
   }
   if (isset(n_mini::$cache['unique_'.$table])) return n_mini::$cache['unique_'.$table]; 
   // here we load a first row of a table to get columns
   
   $q = 'SHOW COLUMNS FROM '.$table; 
   $db->setQuery($q); 
   $res = $db->loadAssocList(); 
  
   $new = '';
   if (!empty($res)) {
    foreach ($res as $k=>$v)
	{
		$field = (string)$v['Field']; 
		$auto = (string)$v['Extra']; 
		$key = (string)$v['Key']; 
		if (($key === 'PRI') && (stripos($auto, 'auto_increment')!==false)) {
			if (empty($new))
			$new = $field; 
		
		}
		if ($key === 'UNI') {
			
			$new = $field; 
		}
		
	}
	n_mini::$cache['unique_'.$table] = $new; 
	return $new; 
   }
   n_mini::$cache['unique_'.$table] = '';
   return array(); 
 }
 
 
 public static function getColumns($table, $withSchema=false) {
   if (!self::tableExists($table)) return array(); 
   $db = c_db::getDBO();
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) { 
   $table = str_replace('#__', $prefix, $table); 
   
   }
   
    if ($withSchema) {
	 $suffix = '_ws'; 
   }
   else {
	$suffix = ''; 
   }
   
   if (isset(n_mini::$cache['columns_'.$suffix.$table])) return n_mini::$cache['columns_'.$suffix.$table]; 
   // here we load a first row of a table to get columns
   
   $q = 'SHOW COLUMNS FROM '.$table; 
   $db->setQuery($q); 
   $res = $db->loadAssocList(); 
  
   $new = array(); 
   if (!empty($res)) {
    foreach ($res as $k=>$v)
	{
		if ($withSchema) {
			foreach ($v as $k2=>$v2) {
				if (isset($v2)) {
					$v[strtolower($k2)] = $v2; 
				}
				else {
					$v[strtolower($k2)] = null; 
				}
			}				
			$new[$v['Field']] = $v; 
		}
		else {
			$new[$v['Field']] = $v['Field']; 
		}
	}
	n_mini::$cache['columns_'.$suffix.$table] = $new; 
	return $new; 
   }
   n_mini::$cache['columns_'.$suffix.$table] = array(); 
   return array(); 
   
   
 }
 
   
   
	
	
	

   static function clearTableExistsCache()
   {
    n_mini::$cache = array(); 
	static::$cache = array(); 
   }
   
   // -1 for a DB error, true for has index and false for does not have index
   public static function hasIndex($table, $column, $isunique=false)
   {
	   
	   
	    $db = c_db::getDBO(); 
		$prefix = $db->getPrefix();
		if (strpos($table, '#__') === 0) { 
	    $table = str_replace('#__', '', $table); 
		$table = str_replace($prefix, '', $table); 
		$table = $db->getPrefix().$table; 
		}
		
		
		
		
	    if (!n_mini::tableExists($table)) return -1; 
	    $q = "SHOW INDEX FROM `".$table."`"; 
		try
		{
		 $db->setQuery($q); 
		 $r = $db->loadAssocList(); 
		}
		catch (Exception $e)
		{
			
			
			return -1; 
		}
		
		
		
		if (empty($r)) return false; 
		
		$composite = array(); 

		$toreturn = -1; 
		
		
		
		
		foreach ($r as $k=>$row)
		{
		n_mini::toUpperKeys($row); 
		
		/*
		if ((!empty($row['NON_UNIQUE'])) && (!empty($isunique))) {
			
			
			continue; 
		}
		*/
		
		if (isset($row['KEY_NAME'])) {
		  if (empty($composite[$row['KEY_NAME']])) $composite[$row['KEY_NAME']] = array(); 
		  $composite[$row['KEY_NAME']][] = $row['COLUMN_NAME']; 
		}
		
		
		
		/*
		foreach ($row as $kn=>$data)
		{
			$kk = strtolower($kn); 
			
			if (($kk === 'key_name') || ($kk === 'column_name'))
			{
				
				
				$dt = strtolower($data); 
				$c = strtolower($column); 
				if ($dt === $c) $toreturn = true; 
				if ($dt === $c.'_index') $toreturn = true; 
			}
		}
		*/
		}
		
		
		
		if (!is_array($column)) {
			
		  $c = strtolower($column);
		foreach ($composite as $z=>$r2) {
		  $first = $r2[0]; 
		  $first = strtolower($first); 

		  if ($first === $c) {
			 
			  return true; 
		  }
		  
		  if ($first === $c.'_index') return true; 
		  
		 //echo 'first: '.$first."<br />\n";
		 //echo 'c: '.$c."<br />\n";
		  
		}
		
		 
		
		}
		else
		{
			foreach ($composite as $z=>$r2) {
				$diff = array_diff($column, $r2); 
				if (empty($diff)) {
					return true; 
				}
				
				
			}
		}
		
		
		return false; 
	   
   }
   public static function toUpperKeys(&$arr) {
	   $arr2 = array(); 
	   foreach ($arr as $k=>$v)
	   {
		   if (is_string($k)) {
		     $arr2[strtoupper($k)] = $v; 
		   }
		   else
		   {
			   $arr2[$k] = $v; 
		   }
	   }
	   $arr = $arr2; 
   
   }
   static function addIndex($table, $cols=array(), $isUnique=false, $index_name='')
   {
	    if (empty($cols)) return; 
	    $db = c_db::getDBO(); 
		$prefix = $db->getPrefix();
		if (strpos($table, '#__') === 0) { 
	    $table = str_replace('#__', '', $table); 
		$table = str_replace($prefix, '', $table); 
		$table = $db->getPrefix().$table; 
		}
		if (!n_mini::tableExists($table)) return; 
		if (empty($index_name)) {
			$name = reset($cols); 
			$name .= '_'.md5(json_encode($cols)); 
			$name .= '_index'; 
		}
		else {
			$name = $index_name;
		}
		
		$def = c_db::getColumns($table); 
		if (isset(c_db::$cache['columndef_'.$table])) {
		$colsdef = c_db::$cache['columndef_'.$table];
		}
		else {
			$colsdef = array(); 
		}
		
		
		
		foreach ($cols as $k=>$v)
		{
			if (!is_numeric($k)) { $name = $k; }
			if (isset($v)) {
				$cols[$k] = '`'.$db->escape($v).'`'; 
			}
			elseif (is_null($v)) {
				$cols[$k] = 'NULL'; 
			}
			if (!isset($colsdef[$v])) {
				n_log::warning('Trying to create a key on non existent column: '.$table.'.'.$v.' for index '.$name); 
				return; 
			}
			if (strpos(strtolower($colsdef[$v]['Type']), 'text') !== false) {
				$cols[$k] .= '(128)'; 
			}
			elseif (strpos(strtolower($colsdef[$v]['Type']), 'blob') !== false) {
				$cols[$k] .= '(128)'; 
			}
			
		}
		$cols = implode(', ', $cols); 
		if ($isUnique) {
		 //ALTER TABLE `vepao_virtuemart_products` ADD UNIQUE `product_sku` (`product_sku`);
		 $q = "ALTER TABLE  `".$table."` ADD UNIQUE  `".$db->escape($name)."` (  ".$cols." ) "; 
		}
		else {
		 $q = "ALTER TABLE  `".$table."` ADD INDEX  `".$db->escape($name)."` (  ".$cols." ) "; 
		}
		try {
		 $db->setQuery($q); 
		 $db->query(); 
		 
		 self::clearTableExistsCache(); 
		}
		catch (Exception $e)
		{
		   
		   
		}
   }
   public static function getCountryByID($id, $what = 'country_name' ) {
		static $c; 
		if (isset($c[$id.'_'.$what])) return $c[$id.'_'.$what]; 
		
		if (!class_exists('ShopFunctions'))
		   require(JPATH_ADMINISTRATOR .DIRECTORY_SEPARATOR. 'components'.DIRECTORY_SEPARATOR.'com_virtuemart' .DIRECTORY_SEPARATOR. 'helpers' .DIRECTORY_SEPARATOR. 'shopfunctions.php');
	   
		$ret = (string)shopFunctions::getCountryByID($id, $what); 
	    $c[$id.'_'.$what] = $ret; 
		return $ret; 
	}
	
   static function tableExists($table)
  {
   
   
   $db = c_db::getDBO();
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) { 
   $table = str_replace('#__', $prefix, $table); 
   
   }
   
   
   
   if (isset(n_mini::$cache[$table])) return n_mini::$cache[$table]; 
   
   $q = 'select * from '.$table.' where 1 limit 0,1';
   // stAn, it's much faster to do a positive select then to do a show tables like...
    /*
	if(version_compare(JVERSION,'3.0.0','ge')) 
	{
	try
    {
		$db->setQuery($q); 
		$res = $db->loadResult(); 
		
		if (!empty($res))
		{
			n_mini::$cache[$table] = true; 
			return true;
		}
		$er = $db->getErrorMsg(); 
		if (empty($er))
		{
			n_mini::$cache[$table] = true; 
			return true;
		}
		
		
    } catch (Exception $e)
	{
		  $e = (string)$e; 
	}
	}
    */	
   
   $q = "SHOW TABLES LIKE '".$table."'";
	   $db->setQuery($q);
	   $r = $db->loadResult();
	   
	   if (empty(n_mini::$cache)) n_mini::$cache = array(); 
	   
	   if (!empty($r)) 
	    {
		n_mini::$cache[$table] = true; 
		return true;
		}
		n_mini::$cache[$table] = false; 
   return false;
  }

    
	 
	 public static function slash($string, $insingle = true)
	 {
	    $string = str_replace("\r\r\n", " ", $string); 
   $string = str_replace("\r\n", " ", $string); 
   $string = str_replace("\n", " ", $string); 
   $string = (string)$string; 
   if ($insingle)
    {
	 $string = addslashes($string); 
     $string = str_replace('/"', '"', $string); 
	 return $string; 
	}
	else
	{
	  $string = addslashes($string); 
	  $string = str_replace("/'", "'", $string); 
	  return $string; 
	}
	 
	 }
	 
	 
	 /**
 * strposall
 *
 * Find all occurrences of a needle in a haystack
 *
 * @param string $haystack
 * @param string $needle
 * @return array or false
 */
public static $_cachesearch; 
public static function strposall($haystack,$needle, $offset = 0){
   
    $input = md5($haystack.' '.$needle.' '.$offset); 
	if (empty(self::$_cachesearch)) self::$_cachesearch = array(); 
	if (isset(self::$_cachesearch[$input])) return self::$_cachesearch[$input]; 
	
    $s=$offset;
    $i=0;
    
	if (empty($needle)) {
		self::$_cachesearch[$input] = false; 
		return false; 
	}
	
	if (empty($haystack)) {
		self::$_cachesearch[$input] = false; 
		return false; 
	}
	
    while (is_integer($i)){
       
        $i = stripos($haystack,$needle,$s);
       
        if (is_integer($i)) {
            $aStrPos[] = $i;
            $s = $i+strlen($needle);
			
        }
    }
    if (isset($aStrPos)) {
		self::$_cachesearch[$input] = $aStrPos; 
        return $aStrPos;
    }
    else {
		self::$_cachesearch[$input] = false; 
        return false;
    }
}
  
 public static function askapache_curl_multi( &$args = array() ) {
  

  // save the start time
  $started = time();

  $defaults = array(
    'urls'               => array(), // array containing all the urls to fetch
    'batch'              => 1000,      // fetch this many urls concurrently (don't do more than 200 if using savedir)
    'max_time'           => ( 60 * 6 ), // maximum time allowed to complete all requests.  5 minutes
    'max_request_time'   => 240, // maximum time an individual request will last before being closed. 2 minutes
    'max_connect_time'   => 0, // The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
    'max_redirs'         => 2, // Number of redirects allowed
    'user_agent'         => 'AskApache;', // user-agent
    'headers'            => array( 'Accept-Encoding: none' ),  // array of http headers, such as array( 'Cookie: thiscookie', 'Accept-Encoding: none' )
    'logfile'            => '',
    'debug'              => false,
    'save'               => false,
    'savedir'            => '',
    'savelog'            => '',
	'cookie'			 => ''
  );
  $args = array_merge( $defaults, $args );

  $urls = $batch = $user_agent = $headers = $logfile = $debug = $save = $savedir = $savelog = $cookie = null;
  $max_time = $max_request_time = $max_connect_time = $max_redirs = null;
  extract( $args, EXTR_IF_EXISTS );
  
  

  // Do not abort script execution if a client disconnects
  //ignore_user_abort( true );

  // Set the number of seconds a script is allowed to run.  Restarts the timeout counter from zero.
  //set_time_limit( $max_time );

  $fplog = $fpsavelog = null;

  if ( $debug ) $fplog = fopen( $logfile, 'a');
  
  
  // setup saving
  if ( $save ) {
    
    if ( empty( $savedir ) ) {
      $save = false;
    } else {
      $savedir = rtrim( $savedir, '/' ) . '/';
  
      if ( ! is_dir( $savedir ) ) {
        $save = false;
      } else {
        // set savelog containing the mapping of urls to files
        if ( empty( $savelog ) ) $savelog = $savedir . '__' . date( 'Y-m-d' ) . '_urls-to-files-map.log';
  
        // open save log
        $fpsavelog = fopen( $savelog, 'a');
        
        if ( ! is_resource( $fpsavelog ) ) $save = false;
      }
    }
  }

  // can't follow redirects when open_basedir is in effect
  if ( strlen( ini_get( 'open_basedir' ) ) > 0 ) $max_redirs = 0;

  $total_urls = count( $urls );

  foreach ( array_chunk( $urls, $batch, true ) as $the_urls ) {
    $con = $fps = $chinfo = array();
    $url_count = count( $the_urls );
    $runtime = ( time() - $started );
    
    n_log::notice( "BATCH: {$batch} total_urls: {$total_urls}" );

    if ( $runtime > $max_time ) {
      n_log::notice( ' !!' . " ({$runtime} > {$max_time}) runtime: {$runtime} batch: {$batch} url_count: {$url_count}" );
	  
      return; 
    }

    $mh = curl_multi_init(); // create a 'multi handle'

    curl_multi_setopt( $mh, CURLMOPT_MAXCONNECTS, 20 ); // maximum amount of simultaneously open connections that libcurl may cache. D10.
    curl_multi_setopt( $mh, CURLMOPT_PIPELINING, 1 ); //  Pipelining as far as possible for this handle. if you add a second request that can use an already existing connection, 2nd request will be "piped"

    foreach ( $the_urls as $i => $url ) {
      $url = str_replace(' ', '%20', $url); 
      $con[ $i ] = curl_init( $url );
      
      // skip bad urls
      if ( ! is_resource( $con[ $i ] ) ) {
        n_log::notice( "ERROR!! SKIPPED: {$url}" );
        continue;
      }
      

      // TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly.
      curl_setopt( $con[ $i ], CURLOPT_RETURNTRANSFER, 1 );

      // binary transfer mode
      curl_setopt( $con[ $i ], CURLOPT_BINARYTRANSFER, 1 );
      curl_setopt( $con[ $i ], CURLOPT_SSL_VERIFYPEER, 0 );

      if ( $save ) {
        // TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly.
        curl_setopt( $con[ $i ], CURLOPT_RETURNTRANSFER, 0 );

        $filename = $i . '_' . md5( $url ) . '.file';
        $fps[ $i ] = fopen( $savedir . $filename, 'wb' );
        
        // skip error opening handler to file
        if ( ! is_resource( $fps[ $i ] ) ) {
          n_log::notice( 'ERROR!! SAVING FILE TO: ' . $savedir . $filename . " !! SKIPPED: {$url}" );
          continue;
        }

        // save the filename mapping
        fwrite( $fpsavelog, $filename . ' ' . trim( $url ) . "\n" );
        
        // have curl save the file
        curl_setopt( $con[ $i ], CURLOPT_FILE, $fps[ $i ] );
      }

      // The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
      curl_setopt( $con[ $i ], CURLOPT_CONNECTTIMEOUT, $max_connect_time );
      
      // maximum time in seconds that you allow the libcurl transfer operation to take
      curl_setopt( $con[ $i ], CURLOPT_TIMEOUT, $max_request_time );

      // allow following redirects
      if ( $max_redirs > 0 ) curl_setopt( $con[ $i ], CURLOPT_FOLLOWLOCATION, 1 );

      // Number of redirects allowed
      curl_setopt( $con[ $i ], CURLOPT_MAXREDIRS, $max_redirs );
      
      // TRUE to fail verbosely if the HTTP code returned is greater than or equal to 400. default return the page ignoring the code.
      curl_setopt( $con[ $i ], CURLOPT_FAILONERROR, 1 );

      // Do not output verbose information.
      curl_setopt( $con[ $i ], CURLOPT_VERBOSE, 0 );

      if ( $debug && is_resource( $fplog ) ) {

        // TRUE to output verbose information. Writes output to STDERR, or the file specified using CURLOPT_STDERR.
        curl_setopt( $con[ $i ], CURLOPT_VERBOSE, 1 );
        
        // An alternative location to output errors to instead of STDERR. 
        curl_setopt( $con[ $i ], CURLOPT_STDERR, $fplog );

        //curl_setopt( $con[ $i ], CURLINFO_HEADER_OUT, 1);
      }

      // A parameter set to 1 tells the library to include the header in the body output.
      curl_setopt( $con[ $i ], CURLOPT_HEADER, 0 );
      
	  if (!empty($cookie)) {
	  //n_log::notice( 'using cookie... '); 
	  curl_setopt( $con[ $i ], CURLOPT_COOKIEJAR, $cookie );
	  curl_setopt( $con[ $i ], CURLOPT_COOKIEFILE, $cookie );
	  }
	  else {
		  //n_log::notice( 'not using cookie... '); 
	  }
	  
	  curl_setopt ($con[$i], CURLOPT_REFERER, $i); 
	  
      // TRUE to ignore any cURL function that causes a signal sent to the PHP.
      // curl_setopt( $con[ $i ], CURLOPT_NOSIGNAL, 1 );

      // Ignore the Content-Length header.
      // curl_setopt( $con[ $i ], CURLOPT_IGNORE_CONTENT_LENGTH, 1 );
      
      curl_setopt( $con[ $i ], CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
      
      // TRUE to exclude the body from the output. Request method is then set to HEAD.
      // curl_setopt( $con[ $i ], CURLOPT_NOBODY, 1 );
      
      // A custom request method to use instead of "GET" or "HEAD" when doing a HTTP request.
      // curl_setopt( $con[ $i ], CURLOPT_CUSTOMREQUEST, 'GET' );

      // The User-Agent header
      if ( ! empty( $user_agent ) ) curl_setopt( $con[ $i ], CURLOPT_USERAGENT, $user_agent );
      
      // Additional headers to send
      if ( count( $headers ) > 0 ) curl_setopt( $con[ $i ], CURLOPT_HTTPHEADER, $headers );

      curl_multi_add_handle( $mh, $con[ $i ] ); // add the easy handle to the multi handle 'multi stack' $mh
    }

    $still_running = null;
	/*
    do {
      //usleep( 50000 );
      //usleep( 50000 );
      $status = curl_multi_exec( $mh, $still_running );
    } while ( $still_running > 0 ); // Processes each of the handles in the stack.
	*/
	do {
        $mrc = curl_multi_exec($mh, $active);
    } 
    while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    foreach ( $the_urls as $i => $url ) {
      if ( ! is_resource( $con[ $i ] ) ) {
        n_log::notice( var_export(array( 'url' => $url, 'chinfo' => $chinfo, 'curl_errno' => curl_errno( $con[ $i ] ), 'curl_error' => curl_error( $con[ $i ] ) )), true );
        continue;
      }

      $code = curl_getinfo( $con[ $i ], CURLINFO_HTTP_CODE );
      $rcount = curl_getinfo( $con[ $i ], CURLINFO_REDIRECT_COUNT );
      $size = curl_getinfo( $con[ $i ], CURLINFO_SIZE_DOWNLOAD );
      //$info = curl_getinfo( $con[ $i ] ); ISCLOG::epx($info);

	  
	  
        $html = curl_multi_getcontent($con[ $i ]); // get the content
                // do what you want with the HTML
		n_log::notice('CURL '.$code.' '.$url); 
		
		if (false)
	  if ($code != 200) {
		  echo $args['urls'][$i]; 
		  $e = curl_error  ($con [$i] ); 
		  
		  var_dump($code); 
		  $chinfo = curl_getinfo( $con[ $i ] );
		  var_dump($e); 
			var_dump($chinfo); die(); 
		}
		
		
		if ($code == 200) {
          $args['urls'][$i] = $html; 
		} 
		else {
			$args['urls'][$i] = ''; 
		}
    
		
	  
      //if ( $code != 200 || $rcount > $max_redirs || curl_errno( $con[ $i ] ) ) {
      if ( $rcount > $max_redirs || curl_errno( $con[ $i ] ) || $size <= 0 ) {
        $chinfo = curl_getinfo( $con[ $i ] );
		
		
        n_log::notice( curl_error( $con[ $i ] ) );
        //sleep( 2 );
        if ( $save ) {
          if ( is_resource( $fps[ $i ] ) ) fclose( $fps[ $i ] );
          if ( is_file(  $savedir . $i . '_' . md5( $url ) . '.file' ) ) unlink( $savedir . $i . '_' . md5( $url ) . '.file' );
        }
      }
      
      curl_multi_remove_handle( $mh, $con[ $i ] ); // remove handle from 'multi stack' $mh
      curl_close( $con[ $i ] ); // close the individual handle
    }

    curl_multi_close( $mh ); // close the multi stack
    

    // close the save file handlers
    if ( $save ) {
      foreach ( $fps as $fp ) {
        if ( is_resource( $fp ) ) fclose( $fp );
      }
    }

    //n_log::notice( "BATCH: {$batch} total_urls: {$total_urls}" );

  } // end foreach ( array_chunk( $the_urls, $batch_size, true ) as $urls ) {

  if ( is_resource( $fplog ) ) fclose( $fplog ); // close the logfile
  if ( is_resource( $fpsavelog ) ) fclose( $fpsavelog ); // close the logfile
  
  n_log::notice( "\nCOMPLETED IN: " . ( time() - $started ) . " SECONDS\n");
  
  n_log::notice( $savedir );
}


public static function getIntoFormat($ai, $format, $created_on)
	{
	   	$ai = (int)$ai; 
		$ai = (string)$ai; 
		$format = (string)$format; 
		$zero = (string)'0'; 
		// to get the common types
		$zero = $zero[0]; 
		
		$ail = strlen($ai)-1; 
		
		$YYYY = (string)date('Y', $created_on); 
		
		
		
		$yl = 3; 
		
		$mm = date('m', $created_on); 
		$mm = (string)$mm; 
		
		$dd = date('d', $created_on); 
		$dd = (string)$dd; 
		
		// lengths of other stuff minus one:  
		$all = array(); 
		$all_data = array(); 
		
		$all['m'] = 1; 
		$all['d'] = 1; 
		
		$txt = '-'; 
		$txt = $txt[0]; 
		
		$delay = false; 
		
		if (!empty(self::$debug))
		{
			echo "<br />\nAI:".$ai."<br />\n"; 
			
		}
		
		
		for ($i=(strlen($format) -1);  $i>=0; $i--)
		{
			
			
			
			
			$c = (string)$format[$i]; 
			
			if (($delay) && ($c !== '{')) continue; 
			if (($delay) && ($c === '{')) 
				{
					$delay = false; 
					continue; 
				} 
			
			if ($c === 'n')
			{
				
				
			   if ($ail >= 0)
				$format[$i] = $ai[$ail];
			  else
			  {
				  $format[$i] = $zero;
			  }
			  $ail--; 
			  
			  
			}
			else
			if (($c === 'Y') || ($c==='y'))
			{
				if ($yl >= 0)
				{
				$format[$i] = $YYYY[$yl]; 
				}
				else
				{
					$format[$i] = $zero; 
				}
				$yl--; 
			}
			else
			if (($c === 'm') || ($c==='M'))
			{
				if ($all['m'] >= 0)
			    $format[$i] = $mm[$all['m']];
				else			
					$format[$i] = $zero;
				$all['m']--; 
			
			}
			else
			if (($c === 'd') || ($c === 'D'))
			{
				if ($all['d'] >= 0)
			    $format[$i] = $dd[$all['d']];
				else			
					$format[$i] = $zero;
				$all['d']--; 
			
			}
			else
			if ($c === '}')
			{
				$delay = true; 
			}
			else
			if ($c === '{')
			{
				$delay = false; 
			}
			else if ($c === 'Q') {
				$format[$i] = chr(rand(65,90));
			}
			else if ($c === 'R') {
				$format[$i] = rand(0,9);
			}
			else if ($c === 'q') {
				$format[$i] = chr(rand(97,122));
			}
			else
			{
				
				
				
				if (!isset($all[$c]))
				{
					$all_data[$c] = @date($c, $created_on); 
					
					if (empty($all_data[$c])) continue; 
					
					$all[$c] = strlen($all_data[$c]) -1; 
					
				}
				
					if ($all[$c] >= 0)
					$format[$i] = $all_data[$c][$all[$c]]; 
				    else
				    {
					 
					 $format[$i] = $txt; 
				    }
					$all[$c]--; 

			}
			
			
			
			
		}
		
		
		
		$s = array('{', '}'); 
		$r = array('', ''); 
		$format = str_replace($s, $r, $format); 
		
		
		return $format; 
	}
	
	//cols: array or key
	public static function getHistoryRow($table, $cols=array(), $obj=null) {
			$db = c_db::getDBO(); 
			if (strpos($table, '#__') !== 0) $table = '#__'.$table; 
			
			$obj = (object)$obj; 
			
			/*

		$q = 'select '; 
		$qs = array(); 
		$cx = array(); 
		if (is_array($cols)) {
			foreach ($cols as $c) {
				$qs[] = '`'.$db->escape($c).'`'; 
				$last = '`'.$db->escape($c).'`'; 
				$cx[] = 'count('.$last.') > 1'; 
			}
		}
		else {
			$qs[] = '`'.$db->escape($cols).'`'; 
			$last = '`'.$db->escape($cols).'`';
			$cx[] = 'count('.$cols.') > 1'; 			
		}
		$q .= ' * '; //implode(',', $qs); 
		
		$q .= ' from `'.$db->escape($table).'`'; 
		$q .= ' GROUP BY '.implode(',', $qs); 
		$q .= ' having '.implode(' or ', $cx); 
		$db->setQuery($q); 
		$res = $db->loadAssocList(); 
		if (!empty($res)) {
			debug_zval_dump($res); die(); 
		}
		*/
			
			if (!n_mini::hasIndex($table, $cols)) {
				
				
				n_mini::addIndex($table, $cols, false); 
				
				
			}
			
			$q = 'select * from `'.$db->escape($table).'` where '; 
			$w = array(); 
			if (is_array($cols)) {
				foreach ($cols as $val) {
					$w[] = '`'.$db->escape($val).'` = \''.$db->escape($obj->{$val}).'\''; 
				}
			}
			else {
				$w[] = '`'.$db->escape($cols).'` = \''.$db->escape($obj->{$cols}).'\''; 
			}
			$q .= implode(' and ', $w); 
			
			if (!empty($obj->modified_on)) {
				$q .= ' order by `modified_on` desc '; 
			}
			elseif (!empty($obj->created_on)) {
				$q .= ' order by `created_on` desc '; 
			}
			
			
			$q .= ' limit 1'; 
			$db->setQuery($q); 
			$res = $db->loadAssoc(); 
			
			
			return (array)$res; 
	}
 
}