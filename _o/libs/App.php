<?php

class App {
			
	static $_models = array();
			
	static $db, $sd, $conf, $params, $ctrl, $act, $ctrl_obj;
	
	static $_hooks = array();

	function __construct(&$conf)
	{			
		self::$conf = &$conf;
		
		// start debugging if requested
		if ($conf['app']['debug'] && self::is_allowed())
		{
			ini_set('display_errors', 'On');
			error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED); 	
		}
		
		// clean up global parameters, strip off magic quotes and such
		self::$params = $params = $this->filter_query_params();
		
		// start session manager
		self::$sd = new SessionData($conf['app']['prefix']);
		
		// connect to database
		self::$db = new DB($conf['db']);
		
		$ctrl = isset($params['ctrl']) ? $params['ctrl'] : $conf['app']['controller'];
		$act = isset($params['act']) ? $params['act'] : $conf['app']['action'];
		
		# try to load specified Controller, and load default on failure	
		if (!$this->load_controller($ctrl, $act))
			if (!$this->load_controller($conf['app']['controller'], $conf['app']['action']))
				die("$ctrl::$act not found. You must define default controller in conf.php.");	
	}
	
	
	/**
	 * Attempts to initialize MVC structure (Controller, it's Models and template engine) 
	 * specified by arguments, and on success populates it with the properties of the current application 
	 * (db, user, conf, etc.)
	 *
	 * @param String $ctrl Controller to load.
	 * @param String $act Action (Method) to call within specified Controller.
	 * @return Boolean True on success and False on failure.
	 */
	protected function load_controller($ctrl, $act)
	{		
		$ctrl_file = self::$conf['dir']['controllers'] . "/$ctrl.php";
		if (!file_exists($ctrl_file))
			return false;
			
		require_once($ctrl_file);
		$ctrl_class = ucfirst($ctrl) . "Controller";
		
		if (is_callable(array($ctrl_class, $act)))
		{		
			self::$ctrl = $ctrl;
			self::$act = $act;
			
			self::$ctrl_obj = $ctrl_obj = new $ctrl_class;
		
			# load models
			$this->load_models($ctrl_obj, $ctrl);
			
			# call _construct on all associated models
			foreach (App::$_models as $model)
				$model->_construct();

			$ctrl_obj->_construct();
						
			# call the action
			View::vars(); // reset view variables
			$ctrl_obj->$act();
			
			return true;
		}
		else
			return false;	
		
	}
	
	
	protected function load_models($obj, $ctrl)
	{	
		if (isset(App::$_models[$ctrl]))
		{
			$obj->$ctrl = App::$_models[$ctrl];
			return;	
		}
		
		$model_file = self::$conf['dir']['models'] . "/{$ctrl}_model.php";
			
		if (file_exists($model_file))	
		{
			require_once($model_file);
			$model_class = ucfirst($ctrl) . "Model";			
		}
		else
			$model_class = "Model";
									
		# store reference to model
		App::$_models[$ctrl] = $obj->$ctrl = new $model_class($ctrl);		
		
		# instantiate all dependencies
		if (!empty($obj->$ctrl->uses))
			foreach ($obj->$ctrl->uses as $dependency)
				$this->load_models($obj->$ctrl, $dependency);		
	}
	
	
	/**
	 * Checks if magic_quotes_gpc is on and if it is recursively strips slashes from $_GET and $_POST arrays.
	 *
	 * @return Array Merged $_GET and $_POST without extra slashes.
	 */
	protected function filter_query_params()
	{
		$input =& array_merge($_GET, $_POST);
		
		if (!isset($_GLOBALS['magic_quotes_gpc']))
			$_GLOBALS['magic_quotes_gpc'] = ini_get("magic_quotes_gpc");
				
		if ($_GLOBALS['magic_quotes_gpc'])
			$input = App::stripslashesdeep($input);
		
		return $input;
	}

	
	
	/**
	 * Outputs all the errors and/or messages accumulated in $errors argument,
	 * if argument not available global $errors object is checked.
	 *
	 * @param Object $errors Error object to parse and output.
	 */
	static function errors($errors = false)
	{
		if (!isError($errors) || $errors->isEmpty())
			$errors = $GLOBALS['errors'];
			
		if (!isError($errors) || $errors->isEmpty())
			$errors = $_SESSION['errors'];
		
		if (!isError($errors) || $errors->isEmpty()) 
			return;
		
		$errors->listAll();
		$errors->clear();
	}
	
	
	/******** HELPERS **********/
		
	/**
	 * Returns an request value by name without magic quoting.
	 *
	 * @param String $name Name of parameter to get.
	 * @param String $default_value Default value to return if value not found.
	 * @return String request value by name without magic quoting or default value.
	 */
	static function get_param($name, $default_value = false) {
		if (!isset($_REQUEST[$name]))
			return $default_value;
	
		if (!isset($_GLOBALS['magic_quotes_gpc']))
			$_GLOBALS['magic_quotes_gpc'] = ini_get("magic_quotes_gpc");
	
		if (isset($_GLOBALS['magic_quotes_gpc'])) {
			if (is_array($_REQUEST[$name])) {
				$newarray = array();
	
				foreach($_REQUEST[$name] as $name => $value)
					$newarray[stripslashes($name)] = stripslashes($value);
	
				return $newarray;
			}
			return stripslashes($_REQUEST[$name]);
		}
	
		return $_REQUEST[$name];
	}
	
	
	/**
	 * Recursively strps off the slashes from the specified argument. 
	 * Is useful when neutralizing magic_quotes_gpc effect.
	 * 
	 * @param Mixed $value to strip off the slashes
	 * @return Mixed value with slashes stripped off.
	 */ 
	static function stripslashesdeep($value)
	{
		if (is_string($value))
		{
			$value = stripslashes($value);
		}
		elseif (is_array($value))
		{
			foreach ($value as $k => $v)
				$value[stripslashes($k)] = App::stripslashesdeep($v);
		}
		return $value;	
	}
	
	
	 /**
     * Generate unique hash
     *
     * @param $chars Number of characters to include into hash
     */
    static function generate_hash($chars = 10) 
	{
        $seq = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        for ($i=0; $i < $chars; $i++) 
		{
			$r = rand(0, strlen($seq));
			$hash.= substr($seq, $r, 1);
        }
        return $hash;
    }
	
	
	static function array_merge_better($defaults, $r)
	{	
		if (!empty($r)) 
		{
			foreach ($r as $key => $value)
			{
				if (is_array($value) && is_array($defaults[$key]))
					$defaults[$key] = array_merge_better($defaults[$key], $value); 
				else 	
					$defaults[$key] = is_array($value) ? $value : trim($value);
			}
		}
		return $defaults;
	}
	
	
	static function baseurl()
	{
		$url = ($_SERVER["HTTPS"] == "on" ? 'https' : 'http') . "://";

		if ($_SERVER["SERVER_PORT"] != "80") 
			$url .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"];
		else 
			$url .= $_SERVER["SERVER_NAME"];
	
		return $url;
	}
	
	
	static function siteurl()
	{
		//return getBaseUrl() . str_replace("?{$_SERVER['QUERY_STRING']}", '', $_SERVER["REQUEST_URI"]);
		//return str_replace("?{$_SERVER['QUERY_STRING']}", '', $_SERVER["REQUEST_URI"]);
		
		return rtrim(self::$conf['app']['siteurl'], '/');
	}
	
	
	static function currenturl()
	{
		return self::baseurl() . $_SERVER["REQUEST_URI"];
	}
	
	
	static function url($ctrl = false, $action = false, $args = array())
	{
		if (!$ctrl)
			$ctrl = self::$conf['app']['controller'];
			
		if (!$action)
			$action = self::$conf['app']['action'];
			
		$url.= self::$conf['app']['permalinks'] ? "/$ctrl/$action?" : "/?ctrl=$ctrl&act=$action&";
		
		if (!empty($args) && is_array($args))
			$url.= http_build_query($args);
	
		return rtrim($url, '/?&');
	}
	
	static function the_url($ctrl = false, $action = false, $args = array())
	{
		echo self::url($ctrl, $action, $args);
	}
	
	
	static function url_enc($ctrl = '', $act = '', $opt_args = array())
	{
		return	str_replace('&', '&amp;', self::url($ctrl, $act, $opt_args));
	}
	
	
	static function the_url_enc($ctrl = '', $act = '', $opt_args = array())
	{
		echo self::url_enc($ctrl, $act, $opt_args);
	}
	
	
	static function is_allowed()
	{		
		foreach (self::$conf['app']['admin'] as $range)
		if (self::ip_in_range(self::get_real_ip(), $range))
			return true;
	
		return false;
	}
	
	
	function the_errors($errors = false)
	{
		if (!$errors) {
			$errors = self::$sd->get('_errors');	
			self::$sd->wipe_var('_errors');
		}
		
		if (!$errors || !Error::isError($errors)) {
			return;	
		}
		
		$errors->list_all();
	}
	
	// credits: http://roshanbh.com.np/2007/12/getting-real-ip-address-in-php.html
	function get_real_ip()
	{
		if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
			return $_SERVER["HTTP_CLIENT_IP"];
		} elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
			return $_SERVER["HTTP_X_FORWARDED_FOR"];
		} else {
			return $_SERVER["REMOTE_ADDR"];
		}
	}
	
	
	static function ip_in_range($ip, $range) 
	{
		if (false === strpos($range, '/'))
		return $ip == $range;
			
		list($range, $netmask) = explode('/', $range);
	
		$range_dec = ip2long($range);
		$ip_dec = ip2long($ip);
		$netmask_dec = bindec( str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0') );
	
		return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec)); 
	}


	static function redirect($url, $status = 302)
	{
		if ($status == 301) 
		{
			$protocol = $_SERVER["SERVER_PROTOCOL"];
			if ( 'HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol )
				$protocol = 'HTTP/1.0';

			$status_header = "$protocol 301 Moved Permanently";
			header($status_header, true, $header);
		}

		header("Location: $url");
		exit;
	}
	
	
	
	/******* APP ********/
	
	static function add_action($tag, $callback, $priority = 10)
	{
		if (!isset(self::$_hooks[$tag]) || !isset(self::$_hooks[$tag][$priority])) 
		{
			self::$_hooks[$tag][$priority] = array();
		}
		
		self::$_hooks[$tag][$priority][] = $callback;
		
		ksort(self::$_hooks[$tag]);	
	}
	
	
	static function do_action($tag)
	{
		$args = func_get_args();
		
		array_shift($args); // get rid of the tag
		
		if (!isset(self::$_hooks[$tag])) {
			return;	
		}
		
		foreach (self::$_hooks[$tag] as $priority => $callbacks) 
		{
			foreach ($callbacks as $callback) 
			{	
				if (is_callable($callback))
					call_user_func_array($callback, $args);
			}
		}
	}
	
	
	static function minify_styles($source = '')
	{
		require_once(self::$conf['dir']['tools'] . '/cssmin.php');	
		return CssMin::minify($source, array
        (
			"remove-empty-blocks"           => true,
			"remove-empty-rulesets"         => true,
			"remove-last-semicolons"        => true,
			"convert-css3-properties"       => true,
			"convert-font-weight-values"    => true, // new in v2.0.2
			"convert-named-color-values"    => true, // new in v2.0.2
			"convert-hsl-color-values"      => true, // new in v2.0.2
			"convert-rgb-color-values"      => true, // new in v2.0.2; was "convert-color-values" in v2.0.1
			"compress-color-values"         => true,
			"compress-unit-values"          => true,
			"emulate-css3-variables"        => true
        ));	
	}
	
	
	static function minify_scripts($source = '')
	{
		//include_once(self::$conf['dir']['tools'] . '/jsmin.php');
		//return JSMin::minify($source);
		
		include_once(self::$conf['dir']['tools'] . '/class.JavaScriptPacker.php' );
		$packer = new JavaScriptPacker($source, 'Normal', true, false);
		return $packer->pack();
	}
	

	
}


?>