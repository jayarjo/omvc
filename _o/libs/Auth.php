<?php

class Auth {
	
	private static $user;

	function init($user) 
	{
		self::$user = $user;
	}
	
	
	function is($group)
	{
		if (!self::$user) {
			return false;	
		}
		
		return self::$user->is($group);
	}
	
	function id()
	{
		if (!self::$user) {
			return false;	
		}
		
		return self::$user->id();
	}
	
	function name()
	{
		if (!self::$user) {
			return false;	
		}
		
		return self::$user->name();
	}

	function is_authed_on($service)
	{
		if (!self::$user) {
			return false;	
		}
		
		return self::$user->is_authed_on($service);
	}


	function auth_redirect()
	{
		if (self::$user) {
			self::$user->auth_redirect();
		}
	}

	function authed_redirect()
	{
		if (self::$user) {
			self::$user->authed_redirect();
		}
	}
	
}