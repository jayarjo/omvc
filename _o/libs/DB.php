<?php

/*
 * 
 * @author Davit Barbakadze
 */

class DB {

	public $prefix, $debug = false;

    private $db, $data, $thread_id, $insert_id, $affected_rows = 0;
	

    function __construct($args)
    {
		$this->db = mysql_connect($args['host'], $args['user'], $args['pass']) 
			or die("Connection cannot be established!");
			
        mysql_select_db($args['name'], $this->db) 
			or die("Database cannot be selected!");

        $this->prefix = $args['prefix'];

		// declare tables internally
		$this->tables = !isset($args['tables']) || empty($args['tables']) ? $this->tables() : $args['tables'];
	    
		foreach ($this->tables as $t) {
			if (!isset($this->$t)) $this->$t = $this->prefix . $t;
		}
    }


    /**
     * Universal query function. Can be used to SELECT, INSERT, UPDATE, DELETE
     *
     * @param   string $query   Query to process
     * @param   boolean $debug  Whether to printout corresponding query
     *
     * @return  mixed           Depends on query type, for SELECT it will be associative array of matched rows,
     *                          for UPDATE - number of affected rows, for INSERT - last insert id
     * @return  boolean         false on FAIL
     */
    function sql($query, $params = array())
    {
		//echo "$query\n";
		
		$result = false;

        $defaults = array(
			'type'	=> 'ASSOC',	// possible values ASSOC, NUM, BOTH, OBJECT
            'debug'     => false,
            'buffered'  => true
        );
        extract(array_merge($defaults, $params));

        if ($debug)
            echo $query . '<br />';

        $query_type = substr($query, 0, strpos($query, ' '));

        $this->data = !$buffered && $query_type == 'SELECT' 
			? mysql_unbuffered_query($query, $this->db) 
			: mysql_query($query, $this->db);

        if (!$this->data)
            return false;

        $this->thread_id = mysql_thread_id($this->db);

        switch ($query_type):
            case 'SHOW':
            case 'SELECT':
                if ($buffered) {
                    while ($row = $this->fetch_result($type))
                        $result[] = $row;
                } else {
                    $result = true;
                }
                break;

            case 'DELETE':
            case 'UPDATE':
                $result = $this->affected_rows = mysql_affected_rows();
                break;

            case 'INSERT':
				$this->affected_rows = mysql_affected_rows();
                $result = $this->insert_id = mysql_insert_id();
                break;

			case 'LOCK':
			case 'UNLOCK':
				$result = $this->data ? true : false;
				break;

			default:
				$result = $this->data;

        endswitch;

        if ($buffered && $query_type == 'SELECT')
            mysql_free_result($this->data);
            
        return $result;
    }


    private function fetch_result($type = 'ASSOC')
    {
		if (!in_array($type, array('ASSOC', 'NUM', 'BOTH', 'OBJECT')))
			return false;

		$type = strtoupper($type);
		return $type == 'OBJECT' ? mysql_fetch_object($this->data) : mysql_fetch_array($this->data, constant("MYSQL_$type"));
    }


    function next_row()
    {
        return mysql_fetch_assoc( $this->data );
    }

    function free()
    {
        mysql_free_result( $this->data );
    }


	function lock($table)
	{
		if (isset($this->$table))
			return $this->sql("LOCK TABLES $table");
	}


	function unlock($table)
	{
		return $this->sql("UNLOCK TABLES $table");
	}


    /**
     * Inserts Assoc. $array into $table
     *
     * @param   string $table   Table to insert into
     * @param   array $array    Associative array of fields and values
     *
     * @return  int             Last Insert ID
     * @see     sql($query)
     */
    function insert($table, $array)
    {
        $array = $this->escape($array);

        return $this->sql("INSERT INTO `$table` (`".join("`,`", array_keys($array))."`) VALUES (".join(",", array_values($array)).")");
    }


	function bulk_insert($table, $array, $ignore_duplicates = 'IGNORE')
	{
		$array = $this->escape($array);

		$sql = "INSERT $ignore_duplicates INTO `$table` (`".join("`,`", $array['fields'])."`) VALUES";

		foreach ($array['values'] as $values)
			$sql.= "(" . join(",", $values) . "),";

		$sql = rtrim($sql, ',');
        return $this->sql($sql);
	}

    /**
     * Updates $table with Assoc. $array, taking into account $where clasuses
     *
     * @param   string $table   Table to insert into
     * @param   array $array    Associative array of fields and values
     * @param   array $where    Assoc. array representing members of WHERE clause
     *
     * @return  int             Affected Rows
     * @see     sql($query)
     */
    function update($table, $array, $where)
    {
        $array = $this->escape($array);

        $where_query = '';
        if (sizeof($where)) {
            $where_query .= " WHERE";
            foreach ($where as $key => $value) {
                    $where_query.= " `$key` = $value AND";
            }
            $where_query = rtrim($where_query, 'AND');
        }

        $query = "UPDATE `$table` SET";
        foreach ($array as $key => $value) {
            $query.= " `$key` = $value,";
        }
        $query = rtrim($query, ',');

        $query.= $where_query;

        return $this->sql($query);
    }

    /**
     * Returns a single row
     *
     * @param   string $query   SQL query
     * @return  array           Array representing specific row
     */
    function row($query, $params = array())
    {
        if ( !preg_match("|LIMIT\s+0\s*,\s*1\s*$|i", $query) )
            $query .= " LIMIT 0,1";

        $data = $this->sql($query, $params);
        if ($data)
            $data = $data[0];
        return $data;
    }

    /**
     * Returns a single column as enumerated array
     *
     * @param   string $query   SQL query
     * @return  array           Array representing specific column
     */
    function col($query, $params = array())
    {
        $col = array();
        $data = $this->sql($query, $params);
        if (!$data)
            return $data;

        foreach ($data as $item)
            $col[] = current($item);

        return $col;
    }


	function pair($query, $params = array())
	{
		$pair = array();

		$params['type'] = 'NUM';

        $data = $this->sql($query, $params);
        if (!$data)
            return $data;

        foreach ($data as $item)
            $pair[$item[0]] = $item[1];

        return $pair;
	}


     /**
     * Returns a single item
     *
     * @param   string $query   SQL query
     * @return  string          Single item
     */
    function item($query, $params = array())
    {
        $data = $this->row($query, $params);
        if ($data)
            $data = current($data);
        return $data;
    }
	
	
	
	function tables($prefixed = true)
	{
		if (!$tmp_tables = $this->col("SHOW TABLES"))
			return array();

		$tables = array();
		
		if (!empty($this->prefix) && $prefixed) // make sure that only prefixed tables make through
		{
			foreach ($tmp_tables as $i => $t)
			{
				if (substr($t, 0, strlen($this->prefix)) === $this->prefix)
					$tables[] = trim(substr($t, strlen($this->prefix)));
			}
			return $tables;
		}
		return $tmp_tables;
	}
	
	
	function fields($table, $full = false)
	{
		// prefix if necessary
		if (substr($table, 0, strlen($this->prefix)) !== $this->prefix) {
			$table = $this->prefix . $table;
        }
				
		if (!$data = $this->sql("SHOW " . ($full ? "FULL ": '') . "FIELDS FROM `{$this->escape($table, false)}`")) {
			return false;
        }
			
		// re-order by field name
		$fields = array();
		foreach ($data as $field) {
			$fields[$field['Field']] = $field;
        }
			
		return $fields;
	}
	
	

    /**
     * Escaper
     *
     * @param   string|array    $arg Argument to escape
     *
     * @return  string|array    Escaped result
     */
    function escape($arg, $quotes = true)
    {
        if ( is_array($arg) ) {
            foreach ($arg as $key => $value) {
                $return[ is_numeric($key) ? $key : addslashes(trim($key)) ] = $this->escape($value);
            }
            return $return;
        }
		
		if (!is_numeric($arg))
		{
			$arg = addslashes(trim($arg));
			if ($quotes)
				$arg = "'".$arg."'"; 	
		}
		return $arg;
    }


}

?>
