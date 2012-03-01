<?php

class Model {
		
	public $uses = array('users');
	
	public $table, $table_sql;
	
	public $db, $conf, $params;
	
	public $table_schema;
	
	function __construct($model = false) 
	{
		$this->db = App::$db;
		$this->sd = App::$sd;
		$this->params = App::$params;
		
		// model should be passed in, 'cause there is no other way to find it, if instance is created out of Model class
		if (!$model)
			$model = substr_replace(strtolower(get_class($this)), '', -strlen('model'));	
	
		# check if model associated with the table
		if (in_array($model, $this->db->tables) || $this->table_sql)
		{
			$this->table = $this->db->prefix . $model;
						
			# retrieve model table schema
			if (!$this->table_schema = $this->sd->get($this->table, '_schemas'))
			{
				if (!$this->table_schema = $this->db->fields($this->table, true))
				{
					# if table sql available try to create it
					if ($this->table_sql)
						$this->db->sql("CREATE TABLE IF NOT EXISTS $this->table $this->table_sql");
					
					# try one more time	
					if (!$this->table_schema = $this->db->fields($this->table, true))	
						die(ucfirst($model) . " not associated with table.");
				}
					
				$this->sd->set($this->table, $this->table_schema, '_schemas');
			}
		}
	}
	
	
	function _construct() {}
	
	
	function exists($id)
    {
    	if (!is_numeric($id)) 
			return false;
		
		return $this->get_row(array('id' => $id));
    }
    
    
    function get($where = array(), $args = array())
    {
		$defaults = array(
			'page' => 0,
			'num' => 30,
			'fields' => '*',
			'order' => 'DESC'
		);
		extract(array_merge($defaults, $args));
		
		if (!is_array($fields) && $fields != '*') {
			$fields = preg_split('|\s*,\s*|', $fields);
		}

		if (is_array($fields)) {
			$fields = "`" . join('`, `', $fields) . "`";
		}

		$where = $this->where('AND', $where);
		$query = "SELECT $fields FROM `$this->table`" . (!empty($where) ? " WHERE $where" : '');
		
		if ($num == 1) 
		{			
			return (sizeof($fields) == 1 && $fields[0] !== '*') ?  $this->db->item($query) : $this->db->row($query);
		}
		elseif (is_numeric($num)) 
		{
			$start = $page * $num;
			$query.= " LIMIT $start, $num";	
		}		
		return $this->db->sql($query);
    }
	
	
	function get_row($where)
	{
		return $this->get($where, array('num' => 1));
	}
	
	
	function get_item($field, $where)
	{
		return $this->get($where, array('num' => 1, 'fields' => array($field)));
	}
	
	
	private function where($op, $where)
	{
		$array = array();
		
		if (!is_array($where) || !sizeof($where)) 
			return '';
	
		foreach ($where as $key => $value) 
		{		
			if (in_array(strtoupper($key), array('AND', 'OR')) && is_array($value))
			{
				$array[] = $this->where($key, $value);
				continue;
			}
			$array[] = "`$key` = {$this->db->escape($value)}";
		}
		return " (".join(" $op ", $array).")";
	}
	
    
    function add($params) 
    {
    	$now = time();
        
        $defaults = array(
        	'created' => date('Y-m-d H:i:s', $now),
            'modified' => date('Y-m-d H:i:s', $now)
		);
        $params = array_merge($defaults, $params);
    
        $result = $this->validate($params);
        if (Error::isError($result)) 
            return $result;
       
        return $this->db->insert($this->table, $params);       
    }
    
    
    function edit($params, $where = array())
    {
		$defaults = array(
            'modified' => date('Y-m-d H:i:s')
		);
        $params = array_merge($defaults, $params);
		        
        $result = $this->validate($params) && $this->validate($where);
        if (Error::isError($result)) 
            return $result;
			
		$id = $params['id'];
		unset($params['id']);
        
        if ($this->db->update($this->table, $params, $where))
            return $id;
            
		return new Error('error', "Row wasn't edited.");
    }
    
    
    function delete($id)
    {
    	if (!$this->exists($id))
        	return new Error('error', "No such item in the $this->table table.");
            
    	return $this->db->sql("DELETE FROM {$this->table} WHERE id = $id");
    }   
    
    
    
    protected function validate(&$params)
    {
    	foreach ($params as $key => $value) :
					
			# silently unset fields that do not match schema
			if (!isset($this->table_schema[$key])) 
			{
				unset($params[$key]);
				continue;
			}
			
			# normalize type
			if (!preg_match('|(^\w+)(?(?=\()\(([^()]+)\))(?(?=\s)\s+(\w+$))|', $this->table_schema[$key]['type'], $type))
				continue;
				
			# check if value exceeds the specified width
			if (isset($type[2]) && is_numeric($type[2]))
				if (strlen($value) > $type[2])
					return new Error('error', "`$key` expected to be no more than $type[2] characters wide.");
					
			
			# if field is unique or primary key and we are inserting, check for duplicates
			if (!isset($params['id']) && in_array($this->table_schema[$key]['Key'], array('PRI', 'UNI')))
				if ($this->get_row(array($key => $value)))
					return new Error('error', "Such `$key` already exists.");
					
			
			switch (strtolower($type[1]))
			{
				case 'int':
				case 'tinyint':
					if (!is_int($value))
						return new Error('error', "`$key` expected to be $type[1].");
					continue 2;
				
				case 'text':
				case 'varchar':
					$params[$key] = $this->db->escape($value);
					continue 2;
					
				case 'date':
				case 'datetime':
					if (!preg_match('|\d{4}-\d{2}-\d{2}(?(?=\s)\s\d{2}:\d{2}:\d{2}$)|', $value))
						return new Error('error', "`$key` expected to be $type[1].");
					continue 2;
			}
			
		endforeach;
    } 
    
	
}

?>