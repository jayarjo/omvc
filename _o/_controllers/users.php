<?php

class UsersController extends Controller {

	
	function index()
	{
		
		
	}
	
	
	function login() 
	{
		if ($this->users->is_authorized()) {
			$this->users->authed_redirect();
		}

		if (isset($this->params['nonce'])) 
		{
			$result = $this->users->login($this->params);
			if (!Error::isError($result)) {
				$this->users->authed_redirect();
			}
			
		}
		
		include($this->tpl);	
	}
	
	
	function logout()
	{
		$this->users->deauthorize();
		
		$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : App::url();
		header("Location: $redirect");
		exit;
	}
	
	
	function register() 
	{
		if ($this->users->is_authorized()) {
			$this->users->authed_redirect();
		}
		
		if (isset($this->params['nonce'])) 
		{
			$this->params['role'] = 'admin'; // we register admins here
						
			$result = $this->users->register($this->params);
			if (!Error::isError($result)) {
				$this->sd->set('_errors', new Error('success', "You were successfully registered."));
				header("Location: " . App::url('users', 'login'));
				exit;		
			} 	
		}
		
		include($this->tpl);	
	}
	
}


?>