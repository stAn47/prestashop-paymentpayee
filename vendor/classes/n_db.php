<?php
 
class n_db extends PayeeDb
{


 public static function runDeleteUnique($primary2_val, $primary_col) {
	  if ($primary_col === 'virtuemart_product_id') {
			 // clear old data in non product tables: 
			 if (!n_products::productExists($primary2_val)) {
			  
			  
			  n_products::removeDuplicates($primary2_val); 
			  n_products::runRemoveDuplicates(); 
			  
			  
			  
			 }
		 }
	 
 }

 public static function escapeKey($key) {
	 $db = static::getDBO(); 
	 return '`'.$db->escape($key).'`'; 
 }

 public static function toCheckUnique() {
	  return true; 
  }
  function __destruct() {
	 //static::$db = null; 
	 $this->db = null; 
 }

 static function &getDBO()
 {
	 
  static $mthis; 
  if (isset($mthis)) {
	  if (!empty($mthis->db)) {
		  if (!$mthis->db->ping()) {
			  require(CONFIGURATION);
			  
			  if (empty($dbj_user)) { $dbj_user = null; }
			  if (empty($dbj_host)) { $dbj_host = null; }
			  if (empty($dbj_pwd)) { $dbj_pwd = null; }
			  if (empty($dbj_database)) { $dbj_database = null; }
			  if (empty($dbj_prefix)) { $dbj_prefix = null; }
			  
			  $mthis->setConfig($dbj_host, $dbj_user, $dbj_pwd, $dbj_database, $dbj_prefix); 
		  }
	  }
	  return $mthis; 
  }
  
  
	require(CONFIGURATION); 
	$mthis = new n_db();
	
	if (empty($dbj_user)) { $dbj_user = null; }
	if (empty($dbj_host)) { $dbj_host = null; }
	if (empty($dbj_pwd)) { $dbj_pwd = null; }
	if (empty($dbj_database)) { $dbj_database = null; }
	if (empty($dbj_prefix)) { $dbj_prefix = null; }
	
	$mthis->setConfig($dbj_host, $dbj_user, $dbj_pwd, $dbj_database, $dbj_prefix); 
	  
 
  
  
  
  
  return $mthis;
 }
 
 
   function syncedID($key, $id) {
	   $db = static::getDBO(); 
	   $tableName = $key.'_temp'; 
	   static $data; 
	   
	   if (empty($data[$tableName])) {
	     $q = 'CREATE TEMPORARY TABLE IF NOT EXISTS `'.$tableName.'` '; 
	    //$q .= ' ENGINE InnoDB '; 
	    $q .= ' (`id` INT NOT NULL)'; 
	    $db->setQuery($q); 
	    $db->query(); 
	   $data[$tableName] = true; 
	   }
	   
	   $q = 'insert into '.$tableName.' (`id`) values ('.(int)$id.')'; 
	   $db->setQuery($q); 
	   $db->query(); 
	   
   }
   
    function syncedIDs($key, $ids) {
	   n_log::sync('Inserting temp records '.count($ids)); 
	   if (empty($ids)) {
		   n_log::error('Empty ids to compare and remove...'); 
	   }
	   $db = static::getDBO(); 
	   $tableName = $key.'_temp'; 
	   static $data; 
	   
	   if (empty($data[$tableName])) {
	   $q = 'CREATE TEMPORARY TABLE IF NOT EXISTS `'.$tableName.'` '; 
	   //$q .= ' ENGINE InnoDB '; 
	   $q .= ' (`id` INT NOT NULL)'; 
	   $db->setQuery($q); 
	   $db->query(); 
	   $data[$tableName] = true; 
	   }
	   
	   $toIns = array(); 
	   foreach ($ids as $id) {
		   $toIns[] = '('.(int)$id.')'; 
	   }
	   $q = 'insert into `'.$tableName.'` (`id`) values '.implode(',', $toIns); 
	   $db->setQuery($q); 
	   $db->query(); 
	   
   }
   
   function deleteMissing($from_table, $from_table_key, $key, $ids=array()) {
	   if (!empty($ids)) {
		   
		   self::syncedIDs($key, $ids); 
	   }
	   
	   n_log::sync('Deleting missing records in '.$from_table); 
	   $db = static::getDBO(); 
	   $tableName = $key.'_temp'; 
	   $q = 'delete from `'.$from_table.'` where `'.$from_table_key.'` NOT IN (select `id` from `'.$tableName.'`)'; 
	   $db->setQuery($q); 
	   $db->query(); 
	   
	   $q = 'SELECT ROW_COUNT()'; 
	   $db->setQuery($q); 
	   $rows = $db->loadResult(); 
	    n_log::sync('Deleted '.$rows.' rows'); 
	   
	   $q = 'delete from `'.$tableName.'` where 1=1'; 
	   $db->setQuery($q); 
	   $db->query(); 
	   
	   $q = 'drop table `'.$tableName.'`'; 
	   $db->setQuery($q); 
	   $db->query(); 
	   
   }

 
 function mergeDataIntoTable($table, $data, $unique=array(), $ign=array()) {
		$errMsg = ''; 
		try {
		$db = n_db::getDBO();  
		$tt = str_replace('#__', '', $table); 
		$temp_table = '#__temp_'.$tt.'_'.rand(); 
		$q = 'create temporary table `'.$db->escape($temp_table).'` like `'.$db->escape($table).'`';
		$db->setQuery($q); 
		$db->execute(); 
		if (!empty($ign)) {
			foreach ($ign as $col) {
				
				$q = 'SHOW COLUMNS FROM `'.$db->escape($temp_table).'` LIKE \''.$db->escape($col).'\''; 
				$db->setQuery($q); 
				$res = $db->loadAssoc(); 
				if (!empty($res)) {
				
				$q = 'ALTER TABLE `'.$db->escape($temp_table).'` DROP COLUMN `'.$db->escape($col).'`'; 
				$db->setQuery($q); 
				$db->execute(); 
				}
			}
		}		 
		}
		catch( Exception $e) {
			$errMsg = (string)$e; 
			return $errMsg; 
		}
		try {
		$qa = array(); 
		$c = 0; 
		$hasError = false;
		$rownames = array(); 
		$toUpdate = array(); 
		$qdirect = array(); 
			$qs = array(); 
			$existing = array(); 
			
			if (!empty($unique))
			{
						$q = 'select ';
						
						foreach ($unique as $uk) {
						   //$qw[] = '`'.$db->escape($uk).'` = \''.$db->escape($obj->$uk).'\'';
						   $qs[] = '`'.$db->escape($uk).'`';
						}
						$q .= implode(',', $qs).' from `'.$db->escape($table).'` where 1=1'; 
						//$q .= implode(' and ', $qw).' limit 1'; 
						
						$db->setQuery($q); 
						$res = $db->loadAssocList(); 
						
						foreach ($res as $row) {
							$key = ''; 
							foreach ($unique as $u) {
							$key .= $row[$u];
							$key .= '_'; 
							}
							$existing[$key] = true; 
						}
						
						
					}
		
		n_log::notice( 'Loaded '.$table.' '.count($existing).' items');  
		
		
		foreach ($data as $obj) {
			if (!empty($unique)) {
		 $key = ''; 
			 foreach ($unique as $u) {
				 if (is_object($obj)) {
				 $key .= $obj->{$u};
				 $key .= '_'; 
				 }
				 elseif (is_array($obj)) {
					 $key .= $obj[$u];
					$key .= '_'; 
				 }
			 }
			 
					if (isset($existing[$key])) {
							//update
							$toUpdateFromTemp = true; 
							
						}
						else {
							//insert directly
							$toUpdateFromTemp = false; 
						}
			}
			foreach ($obj as $col_name => $val) {
				
				if (in_array($col_name, $ign)) {
					continue; 
				}
				
				$rowNames[$col_name] = "`".$db->escape($col_name)."`"; 
				//$toIns[$rowName] = "'".$db->escape($val)."'"; 
				$toIns[$col_name] = $this->transform($col_name, $val); 
				if (!empty($unique)) {
					if (!in_array($col_name, $unique)) {
					  $toUpdate[$col_name] = '`'.$db->escape($table).'`.`'.$db->escape($col_name).'`=`'.$db->escape($temp_table).'`.`'.$db->escape($col_name).'`';
					  //$toUpdate[$col_name] = ' '.$db->escape($table).'.'.$db->escape($col_name).' = '.$db->escape($temp_table).'.'.$db->escape($col_name).'  ';
					}
					 
				}
				else {
				 $toUpdate[$col_name] = '`'.$db->escape($table).'`.`'.$db->escape($col_name).'`=`'.$db->escape($temp_table).'`.`'.$db->escape($col_name).'`';
				 //$toUpdate[$col_name] = ' '.$db->escape($table).'.'.$db->escape($col_name).' = '.$db->escape($temp_table).'.'.$db->escape($col_name).' ';
				}
			} 
			
			if (count($toIns)>0) {
			 if (empty($c)) {
				 
				 $v2 = array(); 
				 foreach ($toIns as $coln => $v) {
					 $v2[] = $coln; 
				 }
				 $c = count($toIns); 
			 }
			 if ($c !== count($toIns)) {
				 $hasError = true; 
				 $errMsg = 'Column count is not equal on all rows'; 
				 $v1 = array(); 
				 foreach ($toIns as $coln => $v) {
					 $v1[] = $coln; 
				 }
				 $errMsg .= var_export($v1, true).' vs '.var_export($v2, true).' for data '.var_export($toIns, true); 
				 
			 }
			 
			 $key = ''; 
			 foreach ($unique as $u) {
				 if (is_object($obj)) {
				 $key .= $obj->{$u};
				 $key .= '_'; 
				 }
				 elseif (is_array($obj)) {
					 $key .= $obj[$u];
					$key .= '_'; 
				 }
			 }
			 
			 
			 if ($toUpdateFromTemp) {
			  if (!empty($key)) {
			   $qa[$key] = '('.implode(',', $toIns).')'; 
			  }
			  else { 
			  $qa[] = '('.implode(',', $toIns).')'; 
			  }
			 
			 }
			 else {
				 $qdirect[$key] = '('.implode(',', $toIns).')'; 
			 }
			 
			 $qu = implode(', ', $toUpdate); 
			} 
			
		}
		 
		
		if (!empty($qa)) {
			
			
							$qhead = $q = "SHOW VARIABLES LIKE 'max_allowed_packet';"; 
$db->setQuery($q); 
$bytesR = $db->loadAssoc(); 

if (isset($bytesR['Value'])) $bytes = (int)$bytesR['Value']; 
if (empty($bytes)) $bytes = 1024*1024*15; 
$bytes = $bytes * 0.9;
			
			$full_qa = implode(',', $qa);
			$bytesq = strlen($full_qa); 
			$force_sing = false; 
			if ($bytesq > $bytes) {
				$force_sing = 1; 
			}
			//shoud be removed:
			if (count($qa) > 1000) {
				$force_sing = 1; 
			}
			
			
			
			if ($force_sing) {
				
				
				
			foreach ($qa as $toi) {
				$qhead = $q = 'insert into `'.$db->escape($temp_table).'` ('.implode(',', $rowNames).') '; 
				$q .= ' VALUES '.$toi; 
				$db->setQuery($q); 
				$db->execute(); 
			}
			}
			else {
				
				

				
		  $qhead = $q = 'insert into `'.$db->escape($temp_table).'` ('.implode(',', $rowNames).') '; 
		  
		     n_log::notice( 'QUERY: '.$q.' ... '.count($qa).' items'); 
		  
		  $q .= ' VALUES '.$full_qa;
		  
		  $db->setQuery($q); 
		  $db->execute(); 
			}
		  
		 if (empty($unique)) {
		  $qhead =  $q = 'insert into `'.$db->escape($table).'` select * from `'.$db->escape($temp_table).'` 
			on duplicate key update '.$qu;
		 }
			else {
		  
		  $qw = array(); 
		  $qhead = $q = 'update `'.$db->escape($table).'`, `'.$db->escape($temp_table).'` set '.$qu.' where '; 
		  foreach ($unique as $uk) {
						   $qw[] = '`'.$db->escape($table).'`.`'.$db->escape($uk).'` = `'.$db->escape($temp_table).'`.`'.$db->escape($uk).'`';
						}
						$q .= implode(' and ', $qw); 
			}
		  
		   
		  $db->setQuery($q); 
		  $db->execute(); 
		  
		  
		  $qhead = $q = 'drop table `'.$db->escape($temp_table).'`';
		  
		     n_log::notice( 'QUERY: '.$q); 
		   
		  $db->setQuery($q); 
		  $db->execute(); 
		  
		}
		if (!empty($qdirect)) {
			$q = 'insert into `'.$db->escape($table).'` ('.implode(',', $rowNames).') '; 
			
		      n_log::notice( 'QUERY: '.$q.' ... '.count($qdirect).' items'); 
		    
			$q .= ' VALUES '.implode(',', $qdirect); 
			$db->setQuery($q); 
		    $db->execute(); 
		  
		}
		
		
		}
		catch (Exception $e) {
			
			  n_log::error( 'ERROR QUERY: '.$q); 
			  n_log::error((string)$e); 
			  die(1); 
			
			$errMsg = (string)$e; 
			$q = 'drop table `'.$db->escape($temp_table).'`';
		    $db->setQuery($q); 
		    $db->execute(); 
			
			if (class_exists('n_log')) {
			$out = var_export($rowNames, true); 
			
			n_log::notice('columns: '.$out); 
			if (isset($v2)) {
			 n_log::notice('toIns '.var_export($v2, true)); 
			}
			if (isset($v1)) {
			 n_log::notice('last toIns '.var_export($v1, true)); 
			}
			}
		}
		
		return $errMsg; 
		
	}
	
	function transform($rowName, $val) {
		$db = n_db::getDBO(); 
		switch($rowName) {
			case 'product_available_date': 
			if (empty($val)) return "'0000-00-00 00:00:00'";
			$time = strtotime($val); 
			$mysql = date("Y-m-d H:i:s", $time);
			return "'".$db->escape($mysql)."'"; 
			break;
			case 'product_in_stock':
			case 'product_ordered':
			case 'low_stock_notification':
			case 'virtuemart_product_id':
			return (int)$val; 
			break;
			default: 
			 return "'".$db->escape($val)."'"; 
		}
	}
 
 
 

 
}
