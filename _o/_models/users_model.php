<?php

class UsersModel extends Model {
	
	public $uses = array('userkeys');
	
	protected $cookie, $cipher, $track_users;	
	
	function _construct() 
	{
		$this->track_users = App::$conf['app']['track_users'];
		$this->cookie = App::$conf['app']['prefix'] . 'cookie';
		$this->cipher = new Cipher(App::$conf['app']['secret']);
		
		Auth::init($this);
						
		# if user is authorized, bypass the rest
		if ($this->is_authorized()) {
			return;
		}
					
		# identify user with cookie
        if (!$this->auth_cookie()) 
		{
			# user seems to be visiting website first time, register him/her
			$user = array(
				'uniqid' => uniqid($this->prefix, true),
				'password' => sha1(App::generate_hash(9)),
				'ip' => App::get_real_ip()
			);
			$id = $this->add($user);
			if (is_numeric($id))			
				$this->authorize($user['uniqid'], $user['password'], true, true);
		}
	}
	
	
	function id()
    {
        return $this->sd->get('id', '_auth');
    }
    

    function name()
    {
        return $this->sd->get('username', '_auth');
    }
    
	
    function role()
    {
		return $this->sd->get('role', '_auth');
    }


    function ip()
    {
		$ip = $this->sd->get('ip', '_auth');
		
		if (!is_array($ip))
			$ip = array($ip);

		return $ip;
    }


	
	
	function info($key)
	{
		return $this->sd->get($key, '_auth');
	}
	

    function is_authorized()
    {
        return is_numeric($this->id());
    }

	
    function is_authed_on($service)
    {
    	if ($keys = $this->info('keys')) {
    		if (isset($keys[$service])) {
    			return $keys[$service][0]['key'];
    		}
    	}
    	return false;
    }


	
    function is($role)
    {
		return $this->is_authorized() && $role === $this->role();
    }


    function auth_redirect()
    {
    	$this->sd->set('_redirect', App::currenturl());
    	header("Location: " . App::url('users', 'login'));
    	exit;
    }


    function authed_redirect()
    {
    	if ($redirect = $this->sd->get('_redirect')) {
			$this->sd->wipe_var('_redirect');
		} else {
			$redirect = App::url();
		}
		header("Location: $redirect");
		exit;		
    }
	
	
	 /**
     * Authorize the user with $username/$uniqid and $password, populate session with user information
     * and update access information 
     *
     * @global object $db
     * @param string $username
     * @param string $password
	 * @param bool $remeber
     * @param bool $password_encrypted 
     * @return mixed Returns user object on success and false otherwise.
     */
    function authorize($username, $password, $remember = false, $password_encrypted = false)
    {
        $db = &$this->db;
		
		if (!$password_encrypted)
			$password = sha1($password);

		if (!$user = $this->get_row(array(
			'OR' => array(
				'username' => $username,
				'uniqid' => $username
			),
			'AND' => array(
				'password' => $password
			)
		))) 
			return false;
			
		$this->deauthorize();	
		$this->set_session_variables($user);
		$this->update_access_info();
		
		if ($remember)
			$this->set_auth_cookie($username, $password);
		
        return $user;
    }
	
	
	/**
     * Deathorize user and clean session of corresponding info 
     */
    function deauthorize()
    {
		# delete remember me cookie if set
		$this->delete_auth_cookie();

        $this->sd->wipe_group('_auth');
    }
	
	
	
	function login($params)
	{						
		foreach (array('username', 'password') as $required) {
			if (empty($params[$required])) {
				return new Error('error', ucfirst($required) . " cannot be empty.");
			}
		}
		
		if (!$this->authorize($params['username'], $params['password'], isset($params['rememberme']))) {
			return new Error('error', 'Username or Password wrong.');	
		}
		
		return true;
	}
	
	
	
	function register($params)
	{						
		foreach (array('username', 'password', 'email') as $required) {
			if (empty($params[$required])) {
				return new Error('error', ucfirst($required) . " cannot be empty.");
			}
		}
	
		if (!filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
			return new Error('error', "Email in wrong format.");
		}
		
		$params['password'] = sha1($params['password']);
				
		if ($user_id = Auth::id()) {
			$result = $this->edit($params, array('id' => $user_id));
		} else {
			$result = $this->add($params);
		}	
					
		return $result;
	}
	
	
	function merge($user_id, $merge_user_id = false)
	{
		if (!$merge_user_id)
			$merge_user_id = $this->id();
			
		if (!is_numeric($user_id) || !is_numeric($merge_user_id))
			return false;
			
		$user = $this->get_row(array('id' => $user_id));
		$merge_user = $this->get_row(array('id' => $merge_user_id));
		
		if (!$user || !$merge_user) 
			return false;
		
		# we do not want to overwrite these	
		unset($merge_user['id'], $merge_user['password'], $merge_user['uniqid'], $merge_user['username']);
		
		# update user with merged info		
		$this->edit(array_merge($user, $merge_user));	
		
		# now we need to take care of the keys
		if (in_array('userkeys', $this->uses))
		{
			$userkeys = $this->userkeys->get($user_id);
			$merge_userkeys = $this->userkeys->get($merge_user_id);
			
			foreach ($merge_userkeys as $service => $key)
			{
				if (!isset($userkeys[$service]))
				{
					$key['user_id'] = $user_id;
					$this->userkeys->edit($key);
				}
				else
					$this->userkeys->delete($key['id']);	
			}
			# clean up if needed
			$this->delete($merge_user_id);
		}
		return true;
	}
		
	
	protected function auth_cookie()
    {
        if (!isset($_COOKIE[$this->cookie]))
			return false;

        list($uniqid, $password) = explode('::',  $this->cipher->decrypt($_COOKIE[$this->cookie]));		
        $this->authorize($uniqid, $password, true, true);
		return true;
    }


	function set_auth_cookie($uniqid, $password)
	{		
		# if user tracking system set, keep cookie for a year, otherwise for a week
		$duration = $this->track_users ? 365 : 7;
	
		setcookie($this->cookie, $this->cipher->encrypt("$uniqid::$password"), time() + 3600 * 24 * 7, '/');
	}


	function delete_auth_cookie()
	{
		if (!empty($_COOKIE[$this->cookie]))
			setcookie($this->cookie, '', time() - 3600 * 24 * 7, '/');
	}
	
	
	/**
      * Populate session with user specific information
      *
      * @param $user Reference to associative array holding user information
      */
     function set_session_variables(&$user)
     {
		 $this->sd->wipe_group('_auth'); // make sure we are setting this from fresh
		 $this->sd->add_group('_auth');
		 
		 unset($user['password']);

		 $user['ip'] = explode(':', $user['ip']);
		 //$user['options']   = stripslashes($user['options']);

		 $this->sd->set_vars($user, '_auth');
		 
		 // set user keys
		 if (in_array('userkeys', $this->uses))
		 	$this->sd->set('keys', $this->userkeys->get($user['id']), '_auth');	 
     }
	 
	 
	 /**
	  * Reset user session variables
	  */
	 function reset_session_variables($user_id = false)
	 {
		 if (!$user_id)
		 	$user_id = $this->id();
		 
		 if (is_numeric($user_id))
			 $this->set_session_variables($this->get_row(array('id' => $user_id)));
	 }
	 

     /**
      * Registers IPs (currently last 10) and sets last visit datetime
      */
     protected function update_access_info()
     {
         $ip = $this->ip();
		 $current_ip = App::get_real_ip();

		 $max2store = 10;
		 if (sizeof($ip) > $max2store)
			 $ip =& array_splice($ip, sizeof($ip) - $max2store);

		$input = array(
			'id' => $this->id()
		);

		if (!in_array($current_ip, $ip))
		{
			$ip[] = $current_ip;
			$input['ip'] = join(':', $ip);
		}		
		$this->edit($input);
     }
	 
	 
	function mail($to, $subject, $message)
	{
		if ($this->use_phpmailer) 
		{
			global $conf;
			
			require("{$conf['dir']['tools']}phpmailer/class.phpmailer.php");
			
			$mail = new PHPMailer();
			
			$mail->IsSMTP();             // set mailer to use SMTP
			$mail->Host = "";  
			$mail->SMTPAuth = true;     // turn on SMTP authentication
			$mail->Username = "";  // SMTP username
			$mail->Password = ""; // SMTP password
			
			$mail->From = "";
			$mail->FromName = "";
			$mail->AddAddress($to);
			
			$mail->WordWrap = 100;                                // set word wrap to 50 characters
			
			$mail->Subject = $subject;
			$mail->Body    = $message;
			
			if(!$mail->Send())
			   return new Error('error', $mail->ErrorInfo);
		
			return true;	
		}
		else
			return @mail($to, $subject, $message);
		
	}
	
	
	
	public $table_sql = "(
		  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		  `uniqid` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
		  `username` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
		  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `email` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
		  `role` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'user',
		  `realname` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `age` smallint(5) unsigned NOT NULL,
		  `gender` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
		  `ip` text COLLATE utf8_unicode_ci NOT NULL,
		  `created` datetime NOT NULL,
		  `modified` datetime NOT NULL,
		  PRIMARY KEY (`id`),
		  UNIQUE KEY `uniqid` (`uniqid`),
		  UNIQUE KEY `username` (`username`),
		  UNIQUE KEY `email` (`email`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
	";
	
}

?>