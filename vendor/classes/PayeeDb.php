<?php 
//generic wrapper for all DBs including Mysql inserts and selects

class PayeeDb
{

 
 var $host; 
 
 var $username; 
 var $pwd; 
 var $database;
 var $prefix; 
 var $db;
 var $configloaded; 
 var $mthis;
 var $lastQuery; 
 
 static $cache; 
 
  function __construct()
 {

  
     
  
 }
 
 
 function __destruct() {
	 //static::$db = null; 
	 $this->db = null; 
 }
 function setQuery($query) {

   $this->lastQuery = $query; 
 }
 
 function getPrefix() {
	 return $this->prefix; 
 }
 
 protected static function getDBO() {
	 throw new Exception('Do not use db class directly'); 
	 
 }
 
 function setConfig($host, $username, $pwd, $database, $prefix) {
	 
	{
    $this->host = $host; 
    $this->username = $username; 
    $this->pwd = $pwd; 
    $this->database = $database; 
	$this->configloaded = true; 
	$this->prefix = $prefix; 
	$this->db = new mysqli($this->host, $this->username, $this->pwd, $this->database);
	}
 }
 
 function affected_rows() {
	 return $this->db->affected_rows; 
 }
 
 function getFieldType($table, $field) {
	 
	  static $cache; 
	  if (!isset($cache[$table])) {
	  $db = static::getDBO(); 
	  $q = "select * from information_schema.columns where table_name = '".$table."'";
	  
	  $db->setQuery($q); 
	  $row = $db->loadAssocList(); 
	  $cache[$table] = $row;
	  }
	  foreach ($cache[$table] as $f) {
		  if ($f['COLUMN_NAME'] === $field) {
			  $type = $f['DATA_TYPE']; 
			  $x = strpos($type, '('); 
			  if ($x !== false) {
				  $type = substr($type, 0, $x); 
				  
			  }
			  $type = strtolower($type); 
			  //echo $type."\n"; 
			  return $type; 
		  }
	  }
	  
	  n_log::error('Field not found '.$field.' in table '.$table); 
	  
	 
 }
 
 function escape($x)
 {
	 
  if (is_array($x)) {
	  if (empty($x)) $x = ''; 
	  if (!empty($x)) {
	  $Zx = debug_backtrace(); 
	  $msg = 'Chyba - neplatny format pre escape '."<br />\n"; 
	  $msg .= 'Hodnota: '.var_export($x, true); 
	  foreach ($Zx as $l) $msg .= $l['file'].' '.$l['line']."<br />\n"; 
	  n_log::error($msg); 
	  }
	  
  }
  return $this->db->real_escape_string($x);
 }
 
 function table(&$q) {
	 if (empty($this->prefix)) return; 
	 $q = str_replace('#__', $this->prefix, $q); 
 }
 
 function loadObject() {
	 $ret = $this->loadAssoc(); 
	 if (empty($ret)) return new stdClass(); 
	 $r = new stdClass(); 
	 foreach ($ret as $k=>$v) {
		 $r->{$k} = $v; 
	 }
	 return $r; 
 }
 
 function loadAssoc($q='')
 {
  if (empty($q)) $q = $this->lastQuery; 
  if (empty($q)) { n_log::error('EMPTY QUERY !'); return; }
	 
  $res = array(); 
  $this->table($q); 
  
  
  try {
  $this->db->query("set names 'utf8mb4'"); 
  } catch(Exception $e) {
	n_log::query($this, $q); 
	
	
  }
  
  n_log::query($this, $q); 
  try {
	$rz = $this->db->query($q);
  }
  catch(Exception $e) {
	n_log::query($this, $q); 
	n_log::error($this->db->error.':'.$q);
	return null; 
  }
  
  n_log::debug($q); 
  
  if (!empty($this->db->error))
  n_log::error($this->db->error.':'.$q);
  if (!empty($rz))
  $res = $rz->fetch_assoc();

  
  if (!empty($this->db->error))
  n_log::error($this->db->error.':'.$q);

  if (!empty($res))
  $rz->close();
  return $res;
 }
 function getErrorMsg() {
	 if (isset($this->db->error)) return $this->db->error;
	 return ''; 
 }
 function loadResult($q='')
 {
  if (empty($q)) $q = $this->lastQuery; 
  if (empty($q)) { n_log::error('Empty query !'); return; }
	 
  $res = array(); 
  $this->table($q); 
   n_log::query($this, $q); 
  $this->db->query("set names 'utf8mb4'"); 
  
  try {
  $rz = $this->db->query($q);
  }
  catch(Exception $e) {
	n_log::query($this, $q); 
	n_log::error($this->db->error.':'.$q);
	return null; 
  }
  
 
  n_log::debug($q); 
  
  if (!empty($this->db->error))
  n_log::error($this->db->error.':'.$q);
  if (!empty($rz))
  $res = $rz->fetch_assoc();
  
  
  if (!empty($res)) {
	  
		  $res = reset($res); 
	
	 
  }
  
  
  if (!empty($this->db->error))
  n_log::error($this->db->error.':'.$q);

  if (!empty($res))
  $rz->close();
  return $res;
 }
 
 
 
 
 function insertLargeArray($arr, $table) {
    $this->table($table); 
	
	$db = static::getDBO(); 
    
	
	$q = 'insert into '.$table; 
	
	$first = reset($arr); 
	
	$keys = array_keys($first); 
	foreach ($keys as $k=>$kk) {
		$keys[$k] = '`'.$db->escape($kk).'`'; 
	}
	
	if (empty($arr)) return; 
	if (empty($keys)) return; 
	
	$lines = array(); 
	$lines_inserted = 0; 
	
	$all_total = count($arr); 
	
    foreach ($arr as $i => $row) {
	  foreach ($row as $kx => $vx) {
		  $row[$kx] = "'".$db->escape($vx)."'"; 
		  
	  }
	  $line = '('.implode(',', $row).')'; 
	  $lines[] = $line; 
	  
	  $lines_inserted++; 
	  
	  if ((count($lines) > 1000) || ($lines_inserted===$all_total)) {
		  $db->query("set names 'utf8mb4'"); 
		  
		  $sql = $q.' ('.implode(',', $keys).' ) values ';
		  
		  $sql .= implode(',', $lines); 
		  
		  $db->setQuery($sql); 
		  $db->query(); 
		  $lines = array(); 
	  }
	  
	}
	
    
 
 }
 
 public function cols($table, $alias, $skip=array() ) {
	 
	 if (!empty(n_globals::$fromlive)) $fromlive = n_globals::$fromlive; 
	 else $fromlive = false; 
	 
	 
	 
	 
	 $this->table($table); 
	 
	 
	 
	 $toi = array(); 
		
		$schema_file = SCHEMAS.DIRECTORY_SEPARATOR.'ms_'.$table.'_definition.php';
			$data = array(); 
			
			
			
		  if (file_exists($schema_file)) {
			  
			
			  
			  include($schema_file); 
			  $coldef = $data['coldef']; 
			  $toi = array(); 
			  foreach ($coldef as $col=>$type) {
				  if (in_array($col, $skip)) continue; 
				  
				  $col = str_replace($table, $alias, $col); 
				  if (strpos($col, '(')===false) {
					  if ($fromlive) {
						  $as = ''; 
						  if (strpos($col, ' ') !== false) {
							  $ea = explode(' ', $col); 
							  $col = reset($ea); 
							  $as = ' AS ['.end($ea).']'; 
						  }
						  
						$toi[$col] = $alias.'.['.$col.']'.$as; 
					  }
					  else {
						  $toi[$col] = '`'.$alias.'`.`'.$col.'`'; 
					  }
				  }
				  else {
					  $toi[$col] = $col;
				  }
			  }
			  
		  }
		  
		  if (empty($toi)) return $alias.'.*'; 
		  
		  return implode(',', $toi);
		  
 }
 
 
 function loadAssocList($q='')
 {
  if (empty($q)) $q = $this->lastQuery; 
  if (empty($q)) { n_log::error('Empty query !'); return; }
  
  $this->table($q); 
  $this->db->query("set names 'utf8mb4'"); 
   n_log::query($this, $q); 
   try {
	$re = $this->db->query($q);
   } catch(Exception $e) {
	n_log::query($this, $q); 
	n_log::error($this->db->error.':'.$q);
	return null; 
  }
  
  if (!empty($this->db->error))
  n_log::error($this->db->error.':'.$q);
  
  $rea = array();
  if (!empty($re))
  while ($r = $re->fetch_assoc())
  {
	  
   $rea[]=$r;
  }
  if (!empty($re))
  $re->close();

  return $rea;
 }
 
 
 function loadObjectList($q='')
 {
  if (empty($q)) $q = $this->lastQuery; 
  if (empty($q)) { n_log::error('Empty query !'); return; }
  
  $this->table($q); 
  $this->db->query("set names 'utf8mb4'"); 
   n_log::query($this, $q); 
   
   try {
  $re = $this->db->query($q);
   } catch(Exception $e) {
	n_log::query($this, $q); 
	n_log::error($this->db->error.':'.$q);
	return null; 
  }
  
  if (!empty($this->db->error))
  n_log::error($this->db->error.':'.$q);
  
  $rea = array();
  if (!empty($re))
  while ($r = $re->fetch_object())
  {
	  
   $rea[]=$r;
  }
  if (!empty($re))
  $re->close();

  return $rea;
 }
 
 
 function close(&$ref) {
	 if (method_exists($ref, 'close')) $ref->close(); 
 }
 
 function ref_loadAssocList(&$ref) {
	 if (!empty($ref)) {
	 return $ref->fetch_assoc(); 
	 }
	 
		 return null; 
 }
 
 function &query_ref($q) {
  if (empty($q)) $q = $this->lastQuery; 
  if (empty($q)) { n_log::error('Empty query !'); $ret = null; return $ret; }
  $this->table($q); 
  try {
  $r = $this->db->query($q);
  } catch(Exception $e) {
	n_log::query($this, $q); 
	n_log::error($this->db->error.':'.$q);
	return null; 
  }
  
  n_log::query($this, $q); 
  if (!empty($this->db->error))
  n_log::error($this->db->error.':'.$q);
  return $r; 
 }
 
 function &execute($q='') {
	 return $this->query($q); 
 }
 
 function &query($q='')
 {
  if (empty($q)) $q = $this->lastQuery; 
  if (empty($q)) { n_log::error('Empty query !'); $ret = null; return $ret; }
  $this->table($q); 
  try {
  $r = $this->db->real_query($q);
  }
  catch(Exception $e) {
	n_log::query($this, $q); 
	n_log::error($this->db->error.':'.$q);
	return null; 
  }
  
  if (!empty($this->db->error)) {
	  
	n_log::error($this->db->error.':'.$q);
  }
  return $r; 
 }
 
 

public function insertid() {
	return $this->db->insert_id; 
}

/*database functions from mini.php*/

 public static function getPrimary($table) {
	 
	
	 
	  if (!static::tableExists($table)) return array(); 
   $db = static::getDBO();
   
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) {
   $table = str_replace('#__', $prefix, $table); 
   
   }
  
   $test = array('virtuemart_products', 'virtuemart_products_sk_sk', 'virtuemart_products_en_gb'); 
   if (in_array($table, $test)) return 'virtuemart_product_id'; 
   
   $test = array('virtuemart_categories', 'virtuemart_categories_en_gb', 'virtuemart_categories_sk_sk'); 
   if (in_array($table, $test)) return 'virtuemart_category_id'; 
   
   
   
   if (isset(static::$cache['primary_'.$table])) return static::$cache['primary_'.$table]; 
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
	static::$cache['primary_'.$table] = $new; 
	return $new; 
   }
   static::$cache['primary_'.$table] = '';
   return array(); 
 }
 
 public static function getUnique($table) {
	  if (!static::tableExists($table)) return array(); 
   $db = static::getDBO();
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) {
   $table = str_replace('#__', $prefix, $table); 
   
    
   }
	 
   if (isset(static::$cache['unique_'.$table])) return static::$cache['unique_'.$table]; 
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
	static::$cache['unique_'.$table] = $new; 
	return $new; 
   }
   static::$cache['unique_'.$table] = '';
   return array(); 
 }
 
 
 public static function getUniques($table) {
	  if (!static::tableExists($table)) return array(); 
   $db = static::getDBO();
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) {
   $table = str_replace('#__', $prefix, $table); 
   
   
   }
   if (isset(static::$cache['uniques'.$table])) return static::$cache['uniques'.$table]; 
   // here we load a first row of a table to get columns
   
   $q = 'SHOW COLUMNS FROM '.$table; 
   $q = 'SHOW INDEX FROM '.$table; 
   $db->setQuery($q); 
   $res = $db->loadAssocList(); 
  
  
  
   $allkeys = array(); 
   
   $new = '';
   if (!empty($res)) {
    foreach ($res as $k=>$v)
	{
		if (!empty($v['Non_unique'])) continue; 
		
		
		
		$keyname = (string)$v['Key_name']; 
		if (empty($allkeys[$keyname])) $allkeys[$keyname] = array(); 
		$colname = $v['Column_name']; 
		$allkeys[$keyname][$colname] = $colname; 
		
		
	}
	static::$cache['uniques_'.$table] = $allkeys; 
	return $allkeys; 
   }
   
   return array(); 
 }
 
 
 public static function getColumns($table) {
	 
   if (!static::tableExists($table)) {
	   n_log::warning('table does not exists : '.$table); 
	   return array(); 
   }
   
   $db = static::getDBO();
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) {
	$table = str_replace('#__', $prefix, $table); 
	
   }
	 
   if (isset(static::$cache['columns_'.$table])) return static::$cache['columns_'.$table]; 
   // here we load a first row of a table to get columns
   
   
   
   $q = 'SHOW COLUMNS FROM '.$table; 
   $db->setQuery($q); 
   $res = $db->loadAssocList(); 
  
  
  
   $new = array(); 
   if (!empty($res)) {
    foreach ($res as $k=>$v)
	{
		
		$new[$v['Field']] = $v['Field']; 
		static::$cache['columndef_'.$table][$v['Field']] = $v; 
	}
	static::$cache['columns_'.$table] = $new; 
	
	
	//n_log::notice('Got table '.$table.' definition: '.implode(',', $new)); 
	
	return $new; 
   }
   static::$cache['columns_'.$table] = array(); 
   return array(); 
   
   
 }
 
   
   
	
	
	

   static function clearTableExistsCache()
   {
    static::$cache = array(); 
   }
   
   // -1 for a DB error, true for has index and false for does not have index
   public static function hasIndex($table, $column)
   {
	   
	   
	    $db = static::getDBO(); 
		$prefix = $db->getPrefix();
		if (strpos($table, '#__') === 0) {
	    $table = str_replace('#__', $prefix, $table); 
		
		}
	    if (!static::tableExists($table)) return -1; 
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
		static::toUpperKeys($row); 
		if (isset($row['KEY_NAME'])) {
		  if (empty($composite[$row['KEY_NAME']])) $composite[$row['KEY_NAME']] = array(); 
		  $composite[$row['KEY_NAME']][] = $row['COLUMN_NAME']; 
		}
		
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
				$ok = false; 
				foreach ($column as $c) {
				   $rx = $r2; 
				   if (!in_array($c, $r2)) {
					   $ok = false; 
					   continue; 
				   }
				   $ok = true; 
				}
				
				if ($ok) return true; 
				
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
   static function addIndex($table, $cols=array())
   {
	    if (empty($cols)) return; 
	    $db = static::getDBO(); 
		$prefix = $db->getPrefix();
		if (strpos($table, '#__') === 0) {
	    $table = str_replace('#__', $prefix, $table); 
		
		}
		if (!static::tableExists($table)) return; 
		
		$name = reset($cols); 
		$name .= '_index'; 
		foreach ($cols as $k=>$v)
		{
			if (!is_numeric($k)) { $name = $k; }
			$cols[$k] = '`'.$db->escape($v).'`'; 
		}
		$cols = implode(', ', $cols); 
		
		$q = "ALTER TABLE  `".$table."` ADD INDEX  `".$db->escape($name)."` (  ".$cols." ) "; 
		try {
		 $db->setQuery($q); 
		 $db->query(); 
		}
		catch (Exception $e)
		{
		   
		   
		}
   }
   static function tableExists($table)
  {
   
   
   $db = static::getDBO();
  
   
   $prefix = $db->getPrefix();
   if (strpos($table, '#__') === 0) {
   $table = str_replace('#__', $prefix, $table); 
   
   }
   
   
   
   if (isset(static::$cache[$table])) return static::$cache[$table]; 
   
   $q = 'select * from '.$table.' where 1 limit 0,1';
   // stAn, it's much faster to do a positive select then to do a show tables like...
    
   
   $q = "SHOW TABLES LIKE '".$table."'";
	   $db->setQuery($q);
	   $r = $db->loadResult();
	   
	   
	   
	   if (empty(static::$cache)) static::$cache = array(); 
	   
	   if (!empty($r)) 
	    {
		static::$cache[$table] = true; 
		return true;
		}
		static::$cache[$table] = false; 
   return false;
  }
  public static function toDelUnique() {
	  return false; 
  }
  public static function runDeleteUnique($primary2_val, $primary_col) {
  
  }
  
  public static function replaceIntoFrom($table1, $table2, &$data) {
	  $colnames = array(); 
	  foreach ($data as $ind=>$row) {
		  foreach ($row as $colname => $y) {
			  $colnames[$colname] = '`'.$colname.'`'; 
			 
		  }
		   break; 
	  }
	  $db = static::getDBO(); 
	  $q = 'replace into `'.$table1.'` select '.implode(',', $colnames).' from `'.$table2.'`'; 
	  $db->setQuery($q); 
	  $db->query($q); 
	  
  }
  
  public static function insertTempArrayData($data,$likeTable='', $merge=false) {
	   n_log::notice('insertTempArrayData like '.$likeTable); 
	   $db = static::getDBO(); 
	   $table = uniqid(); 
	  $key = 'tmp_'.$table; 
	  if (!isset(static::$temp_tables[$key])) {
	  $table_name = $key.'_'.rand(); 
	  
	  $table_name2 = '2_'.$key.'_'.rand(); 
	  
	  $schema = array(); 
	  $values = array(); 
	  foreach ($data as $rn=>$rows) {
		  foreach ($rows as $key_name => $value) {
			  if (empty($schema[$key_name])) $schema[$key_name] = new stdClass(); 
			  if (is_int($value)) {
					$schema[$key_name]->type = 'INT(1)'; 
					$values[$rn][$key_name] = $value; 
			  }
			  
			  if (empty($schema[$key_name]->is_null)) {
						$schema[$key_name]->is_null = ' NOT NULL '; 
					}
			  
			  if (is_null($value)) {
				  $schema[$key_name]->is_null = ' NULL '; 
				  $values[$rn][$key_name] = 'NULL';
			  }
			  if (is_string($value)) {
				  if (strlen($value)>255) {
					  $schema[$key_name]->type = 'TEXT'; 
				  }
				  else
				  if (empty($schema[$key_name]->type))
				  if (strlen($value)<=255) {
					  $schema[$key_name]->type = 'VARCHAR(255)'; 
				  }
				  
			  }
			  if (empty($schema[$key_name]->type)) {
				  $schema[$key_name]->type = ' VARCHAR(255) '; 
			  }
			  if (!isset($values[$rn][$key_name])) {
			  $values[$rn][$key_name] = "'".$db->escape($value)."'"; 
			  }
		  }
	  }
	  $qia = array(); 
	  $cols = array(); 
	  
	  if (empty($schema)) {
		  n_log::notice('No data to be inserted in temporary table'); 
		  return ''; 
	  }
	  $primary_key = ''; 
	  foreach ($schema as $key_n => $type_data) {
		  $cols[] = '`'.$key_n.'`'; 
		  $qia[] = ' `'.$key_n.'` '.$type_data->type.' '.$type_data->is_null; 
	  }
	  $qi = ' ('.implode(',', $qia).') '; 
	  
	  if (empty($likeTable)) {
	   $q = 'CREATE TEMPORARY TABLE `'.$table_name.'` '.$qi; //AS SELECT `ID` FROM '.$table; 
	  }
	  else {
		  if ($merge) {
		  $primary_key = static::getPrimary($likeTable); 
		  if (!empty($primary_key)) {
		    $q = 'CREATE TEMPORARY TABLE `'.$table_name2.'` '.$qi; //AS SELECT `ID` FROM '.$table; 
			n_log::notice($q); 
			$db->setQuery($q); 
			$db->query(); 
		  }
		  }
		  $q = 'CREATE TEMPORARY TABLE `'.$table_name.'` AS SELECT * FROM '.$likeTable; 
	  }
	  
	  n_log::notice($q); 
	  $db->setQuery($q); 
	  $db->query(); 
	  
	   
	  
	  static::$temp_tables[$key] = $table_name; 
	 }
	 else {
		 $table_name = static::$temp_tables[$key];
	 }
	 
	 $row = array(); 
	 foreach ($values as $rx=>$rd) {
	   $row[$rx] = '( '.implode(', ', $rd).')'; 	 
	 }
	 $all_rows = implode(', ', $row); 
	 
	 
	 if (!empty($primary_key)) {
	 //original data into temp: 
	  $q = 'insert into `'.$table_name.'` select * from `'.$likeTable.'`';
	  n_log::warning($q); 
	 $db->setQuery($q); 
	 $db->query(); 
	 
	 //updated data with limited columns into temp:
	 $q = 'insert into `'.$table_name2.'` ('.implode(', ', $cols).') values '.$all_rows; 
	  n_log::warning('inserting many rows '.count($row)); 
	 $db->setQuery($q); 
	 $db->query(); 
	 
	 $q = 'update `'.$table_name.'` INNER JOIN `'.$table_name2.'` ON `'.$table_name.'`.`'.$primary_key.'` = `'.$table_name2.'`.`'.$primary_key.'` SET '; 
	 foreach ($cols as $col) {
		 $qu[] = '`'.$table_name.'`.'.$col.' = `'.$table_name2.'`.'.$col.''; 
	 }
	 $q .= implode(',', $qu); 
	 n_log::warning($q); 
	 $db->setQuery($q); 
	 $db->query(); 
	 }
	 else {
		  //updated data with limited columns into temp:
	 $q = 'insert into `'.$table_name.'` ('.implode(', ', $cols).') values '.$all_rows; 
	 $db->setQuery($q); 
	 $db->query(); 
	 }
	 
	 //n_log::notice($q); 
	 return $table_name; 
	  
  }
  
  static $temp_tables;
  
  public static function insertArray($table, &$fields, $def=array(), $allowIgnore=false)
 {
	 
	 
	 $primary = $primary_col = static::getPrimary($table); 
	 $primary_id = 'NULL'; 
	 if ((!empty($primary)) && (isset($fields[$primary]) && ($fields[$primary] !== 'NULL'))) {
		  if (strpos($primary, '_id') !== false) {
		   $primary_id = (int)$fields[$primary]; 
		  }
		  else {
			  $primary_id = $fields[$primary]; 
		  }
     }
	 
	 
	
	 
	 $unique_val = ''; 
	 $unique_col = static::getUnique($table); 
	 if ((!empty($unique_col)) && (isset($fields[$unique_col]) && ($fields[$unique_col] !== 'NULL'))) {
	  $unique_val = $fields[$unique_col]; 
	 }
	 $dbvv = static::getDBO(); 
	 // check for other unique keys before insert
	  
	 if ((!empty($primary_col)) && (!empty($unique_col)))
	 if ($unique_col !== $primary_col) {
		 
		 
		 $q = 'select * from '.$table.' where '.$unique_col." = '".$dbvv->escape($unique_val)."'"; 
		 $dbvv->setQuery($q); 
		 $res = $dbvv->loadAssoc(); 
		 
		 if (!empty($res)) {
			 n_log::notice('DB we found already existing Unique key '.$table.'.'.$unique_col.' = '.$unique_val); 
			
			if ($res[$primary_col] === "0") {
				$q = 'delete from '.$table.' where '.$primary_col.' = 0'; 
				$dbvv->setQuery($q); 
				$dbvv->query(); 
			 }
			 else {
				foreach ($res as $rcol => $rval) {
					if (!empty($fields[$rcol]))
					if ($fields[$rcol] === 'NULL') $fields[$rcol] = $rval; 
				}
			 }
			
			 if ($table === '#__virtuemart_products') {
			 //var_dump($fields); 
			 //var_dump($res); 
			 
			 
			 
			}
			
		 }
		 
		
		 
		 $z = static::toDelUnique(); 
		 if ($z === true) {
		 if (!empty($res)) {
		 
		 if (strpos($primary, '_id') !== false) {
		  $primary2_val = (int)$res[$primary]; 
		 }
		 else {
			 $primary2_val = $res[$primary]; 
		 }
		 // virtuemart_product_id is not euqal to the current select: 
		 if ($primary2_val !== $primary_id) {
			 static::runDeleteUnique($primary2_val, $primary_col); 
		 }
		 
		 
		
		 
		
		 }
		 }
	 }
	 
	 
	 
	  $onlyfor = array('#__virtuemart_category_categories', '#__virtuemart_product_medias', 'parovanie', 'First_Customers_Import'); 
	  $uniques = static::getUniques($table); 
	  $p_test = ''; 
	  if (empty($res))
	  if (count($uniques) === 2) {
		  foreach ($uniques as $k => $va) {
			  if ($k==='PRIMARY') {
				  $p_key = reset($va); 
			  }
			  else {
				  if (count($va) === 1) {
				    $p_test = reset($va); 
				  }
			  }
		  }
		  if (!empty($p_test))
		  if (empty($fields[$p_key]) || ($fields[$p_key] === 'NULL')) {
			  if ((!empty($fields[$p_test])) && ($fields[$p_test] !== 'NULL')) {
				  $primary = $p_test; 
				  $primary_col = $p_test; 
				  $primary_id = $fields[$p_test]; 
			  }
		  }
		  
		  
	  }
	  //if ($table === '#__virtuemart_category_categories') 	 
	  if (empty($primary_id) || ($primary_id === 'NULL'))
	  if (in_array($table, $onlyfor))
	  {
	 
	  
	   
	    foreach ($uniques as $keys) {
			if (count($keys)>1) {
				$test = array(); 
				$cols = array(); 
				foreach ($keys as $ucol) {
					if (isset($fields[$ucol])) {
					  $test[] = "'".$dbvv->escape($fields[$ucol])."'"; 
					  $cols[] = '`'.$dbvv->escape($ucol).'`'; 
					}
					else {
						$test[] = 'DEFAULT'; 
						$cols[] = '`'.$dbvv->escape($ucol).'`'; 
					}
				}
				$q = 'select * from `'.$table.'` where ';
				$w = array(); 
				foreach ($cols as $i => $k) {
					$w[] = $k.'='.$test[$i]; 
				}
				$q .= implode(' and ', $w); 
				$dbvv->setQuery($q); 
				$res = $dbvv->loadAssoc(); 
				if (!empty($res)) {
					//the data already exists, update primary: 
					if (!empty($primary_col)) {
						if (!isset($res[$primary_col])) {
						}
					 $fields[$primary_col] = $res[$primary_col]; 
					 if (!isset($res[$primary_col])) {
						 n_log::error('error 60...'); 
					 }
					 n_log::notice('trying to insert duplicate data, updating primary ID'); 
					}
					else {
						n_log::error('trying to insert duplicate data without a primary key'); 
					}
				}
			}
			else {
				
				$test_key = reset($keys); 
				if ($fields[$test_key] !== 'NULL') {
				$q = 'select * from `'.$table.'` where `'.$test_key.'` = \''.$dbvv->escape($fields[$test_key]).'\'';
				
				$dbvv->setQuery($q); 
				$res = $dbvv->loadAssoc(); 
				
				if ($fields[$primary_col] === 'NULL') 
				{
					$fields[$primary_col] = $res[$primary_col]; 
				}
				
				
				}
				
				
			}
		}
	 }
	 
	 
	 
	  
	 if (empty($def)) {
		 $def = static::getColumns($table); 
		 
		 
	 }
	  
	 
	 
	 foreach ($fields as $k=>$v)
	 {
		 if (!isset($def[$k])) {
			 n_log::notice('Unsetting column, Found extra column for '.$table.'.'.$k); 
			 unset($fields[$k]); 
		 }
		 else
		 if (is_null($fields[$k])) {
			 //if we are just updating, don't overwrite with null values !
			 n_log::notice('DB unsetting null values '.$table.'.'.$k); 
			 unset($fields[$k]); 
		 }
		 
	 }
	 
	 if (empty($fields)) {
		 
		 
		 n_log::warning('attempt to insert empty values to '.$table); 
		 return false; 
	 }
	 
	 
	 
	 if ((!empty($primary_col)) && (!empty($primary_id)))
	 if (static::compareEqual($fields, $table, $primary_col, $primary_id)) {
		 //if we are updating/inserting exactly same data... 
		 // n_log::notice('skipping insert update...'); 
		 return false; 
	 }
	 
	 if (isset($def['modified_on'])) {
		 $fields['modified_on'] = 'UTC_TIMESTAMP'; 
	 }
	 
	
	 $q = 'insert into `'.$table.'` (';
	 $qu = 'update `'.$table.'` set '; 
	 $keys = ''; 
	 $vals = ''; 
	 $i = 0; 
	 $c = count($fields); 
	 $quq = array(); 
	 foreach ($fields as $key=>$val)
	 {
	  $vxval = $val; 
		 
	  $keys .= '`'.$key.'`'; 
	  $i++;
	  
	  if ($val === 'NULL')
	   $val = 'NULL'; 
      elseif ($val === 'NOW()') {
		  $val = 'NOW()'; 
	  }
	  elseif ($val === 'UTC_TIMESTAMP') {
		$val = 'UTC_TIMESTAMP';
	  }
	  elseif ($val === 'UTC_TIMESTAMP()') {
		$val = 'UTC_TIMESTAMP()'; 
	  }
	  else {
		$type = $dbvv->getFieldType($table, $key); 
	   switch ($type) {
		   case 'bit':  
		     $val = "b'".$dbvv->escape($val)."'";    
			 break; 
			case 'int': 
			case 'bigint':
			case 'tinyint':
			case 'smallint':
			case 'mediumint':
			case 'boolean':
			case 'serial':
		     $val = (int)$val;    
			 break; 
		   default: 
			$val = "'".$dbvv->escape($val)."'";    
			break; 
	   }
	   
	  }
	  
	  $vals .= $val; 
	  
	  if ($i < $c) { 
	   $keys .= ', ';
	   $vals .= ', ';
	   }
	   
	   $arr = array('NULL', 'NOW()', 'DEFAULT', 'UTC_TIMESTAMP', 'UTC_TIMESTAMP()'); 
	   if ($key !== $primary) {
	    if (!in_array($val, $arr)) {
		  //$quq[] = ' `'.$key."`='".$dbvv->escape($val)."'"; 
		  $quq[] = ' `'.$key."` = ".$val; 
		}
		else {
			$quq[] = ' `'.$key."` = ".$val; 
		}
		
	   }
	  
	 }
	 $q .= $keys.') values ('.$vals.') ';
	 $q .= ' ON DUPLICATE KEY UPDATE '; 
	 $u = false; 
	 foreach ($fields as $key=>$val)
	 {
	  if ($u) $q .= ','; 
	  $q .= '`'.$key.'` = '; 
	  
	  if ($val === 'val:NULL') $val = 'NULL'; 
	  else
	  if ($val === 'NULL')
	   $val = 'NULL'; 
      elseif ($val === 'NOW()') {
		  $val = 'NOW()'; 
	  }
	  elseif ($val === 'UTC_TIMESTAMP') {
		$val = 'UTC_TIMESTAMP';
	  }
	  elseif ($val === 'UTC_TIMESTAMP()') {
		$val = 'UTC_TIMESTAMP()'; 
	  }
	  else 
	   $val = "'".$dbvv->escape($val)."'"; 
	  
	  
	  $q .= $val; 
	  $u = true; 
	 }
	 
	 
	 n_log::debug($q); 
	 
	 if ((!empty($primary)) && ((!empty($primary_id)) && ($primary_id !== 'NULL'))) {
	   $qu .= ' '.implode(', ', $quq). ' where `'.$primary.'` = '.(int)$primary_id; 
	   
	   
	   n_log::debug('UPDATETEST: '.$qu); 
	 }
	 
	
	 
	 //quick compare: 
	 if ((!empty($primary)) && (!empty($primary_id)) && ($primary_id !== 'NULL')) {
	   $qx = 'select * from '.$table.' where '.$primary.' = \''.$dbvv->escape($primary_id).'\'';
	   $dbvv->setQuery($qx); 
	   $res = $hasResult = $dbvv->loadAssoc(); 
	   $fieldscopy = $fields; 
	   if (!empty($res))
	   foreach ($fieldscopy as $k=>$v) {
		   if ($res[$k] === $fields[$k]) {
			   unset($fieldscopy[$k]); 
		   }
		   else break; 
	   }
	  
	   if (empty($fieldscopy)) {
		    n_log::notice('skipping insert...'); 
		   
		   if ($primary_id !== 'NULL')
		   $fields[$primary] = $primary_id; 
		   //return $fields; 
		   return false; 
	   }
	   
	 }
	 $ret = false; 
	 if (((!empty($hasResult)) && ((!empty($primary)) && ((!empty($primary_id)) && ($primary_id !== 'NULL')))) ) {
		 
		 $dbvv->setQuery($qu); 
	     $dbvv->query();
		 n_log::red('Executed update...'); 
		 n_log::query($dbvv, $qu); 
		
		 $ret = true; 
		 
	 }
	 else {
		$dbvv->setQuery($q); 
		$dbvv->query();
		n_log::red('Executed insert... ');  
		n_log::query($dbvv, $q); 
		
		$ret = true; 
		
	 }
	 
	
	 
	 
	 
	 if ((!empty($primary)) && ((empty($primary_id)) || ($primary_id === 'NULL'))) {
	   $last_ins_id = $dbvv->insertid();  
	   if (!empty($last_ins_id)) {
	   //if this is an "insert .. on duplicate key..", this returns zero
	   $primary_id = $last_ins_id;
	   if (!empty($primary_id))
	   $fields[$primary] = $primary_id; 
   
	      $ret = true; 
	   }
	   else {
		   
		   //pozor ak je primary_id NULL ale existuje aj iny unique column tak last_ins_id je NULA
		   $ret = false; 
	   }
	 }
	 
	 
	 
	 
	 if (empty($allowIgnore))
	 if (empty($fields[$primary])) {
		
		 
		 $dbg = 'No primary key ! '.__FILE__.' '.__LINE__.' query '.$q; 
		 n_resp::error('Internal Error', 500, $dbg); 
	 }
	 
	 
	 return $ret; 
	 
	  
 }
  
  public static function escapeKey($key) {
	 $db = static::getDBO(); 
	 return '`'.$db->escape($key).'`'; 
  }
  
  public static function compareEqual($fields, $table, $primary_col, $primary_id) {
	  if ((empty($primary_col)) || (empty($primary_id))) return false; 
	  
	  $keys = array_keys($fields); 
	  $db = static::getDBO(); 
	  foreach ($keys as $kk=>$vv) {
		 $keys[$kk] = static::escapeKey($vv); 
	  }
	  $q = 'select '.implode(',', $keys).' from '.$table.' where '.$primary_col.' = '.(int)$primary_id;
	  
	  $db->setQuery($q); 
	  $res = $db->loadAssoc(); 
	  if (empty($res)) return false; 
	  foreach ($res as $ind=>$k) {
		 if ($res[$ind] == $fields[$ind]) {
			 unset($fields[$ind]); 
			 continue; 
		 }
		 if (isset($fields[$ind]) && (isset($res[$ind]))) {
			if (trim($res[$ind]) == trim($fields[$ind])) unset($fields[$ind]); 
		 }
	  }
	  
	  if (empty($fields)) return true; 
	  foreach ($fields as $xk=>$vk) {
		  if (is_null($vk) && (empty($res[$xk]))) unset($fields[$xk]); 
		  if (($vk === 'NULL') && (empty($res[$xk]))) unset($fields[$xk]); 
		  if ($xk === 'created_on') unset($fields[$xk]); 
		  if ($xk === 'modified_on') unset($fields[$xk]); 
		  if ($xk === 'modified_by') unset($fields[$xk]); 
		  
	  }
	  
	  if (empty($fields)) return true; 
	  
	  $orig = array(); 
	  foreach ($fields as $k=>$z) {
		  
		  $orig[$k] = $res[$k]; 
	  }
	  n_log::notice('compareEqual found a difference: '.var_export($fields, true).' vs '.var_export($orig, true)); 
	  return false; 
	  
	   
  }
  
/*end mini.php*/

 public static function nullToEmpty(&$data) {
	 foreach ($data as &$val) {
		 if ($val === 'NULL') $val = ''; 
		 if ($val === NULL) $val = ''; 
	 }
 }	 

  
	function generateTableName($q)
	{
		if (empty($this->prefix)) {
			return $q;
		}

		$q = str_replace('#__', $this->prefix, $q);
		return $q;
	}


}
