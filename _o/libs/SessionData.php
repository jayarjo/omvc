<?php

/**
 * 
 * @@author Davit Barbakadze
 */

class SessionData {

    public $prefix;

    function __construct($prefix = '')
    {
        $this->prefix = $prefix;

        if (session_id() === '') {
            session_start();
        }

        $this->add_group('default');
    }
    

    function wipe()
    {
        session_destroy(); 
		session_commit();
        session_start();
    }


    function add_group($group, $max_items = 0)
    {
        if (empty($group) || !is_string($group))
            return false;

		if (!$this->get_group($group))
		{
			$_SESSION[$this->prefix . $group] = array();
			$_SESSION[$this->prefix . $group]['_max_items'] = $max_items;
		}
    }


    function get_group($group = 'default')
    {
        if (!isset($_SESSION[$this->prefix . $group]))
			return false;
			
		$obj = $_SESSION[$this->prefix . $group];
		unset($obj['_max_items']);
			
		return $obj;
    }


    function wipe_group($group)
    {
        unset($_SESSION[$this->prefix . $group]);
    }


    function set($key, $value, $group = 'default')
    {
		if (!$this->get_group($group))
			$this->add_group($group);
		
		$max_items = $_SESSION[$this->prefix . $group]['_max_items'];
		
		// if number of items in the group should be limited (_max_items > 0), remove first valid item
        if ($max_items && sizeof($this->get_group($group)) > $max_items)
        {
			$this->wipe_var(key($this->get_group($group)), $group);
        }

        $_SESSION[$this->prefix . $group][$key] = $value;
    }

    /**
     * Sets a bunch of variables at once for a given group.
     * Doesn' take into account _max_items identifier!
     *
     * @param array $vars
     * @param string $group
     */
    function set_vars($vars = array(), $group = 'default')
    {
        foreach ($vars as $key => $value)
            $_SESSION[$this->prefix . $group][$key] = $value;
    }
    

    function is_set($key, $group = 'default')
    {
        return isset($_SESSION[$this->prefix . $group][$key]);
    }


    function get($key, $group = 'default')
    {
		if ($key === '_max_items') 
			return false;
			
		if (!$this->get_group($group))
			return false;
		
        return isset($_SESSION[$this->prefix . $group][$key]) ? $_SESSION[$this->prefix . $group][$key] : false;
    }


    function wipe_var($key, $group = 'default')
    {
        unset($_SESSION[$this->prefix . $group][$key]);
    }


	/**
	 * Completely destroys session, deletes all files, cookie, data and starts new, using specified session ID\\id
	 *
	 * @param string $id Session id to use
	 */
	function restart($id)
	{
		if ("" == session_id())
			session_start();

		// purge session data
		$_SESSION = array();

		// delete cookie
		if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}
		session_destroy(); // deletes files but not data and not cookie
		session_commit(); // ends the current session and stores session data

		session_id($id);
		session_start();
	}



}

?>