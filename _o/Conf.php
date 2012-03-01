<?php

class Conf {
	
	public static $conf;
	
	function init() 
	{
		$conf['spec'] = array(
			'allow_register' => true,
		);

		$conf['github'] = array(
			'client_id' => '89cea2a8d57b2e4e8844',
			'secret' => '74ad7e0c9f5f04400145bf7018f36785ec0cc676',
			'api_url' => 'https://api.github.com',
			'auth_url' => 'https://github.com/login/oauth/authorize',
			'token_url' => 'https://github.com/login/oauth/access_token'
		);
		
		$conf['app'] = array(
			'siteurl' => '', 
			'secret' => '&xQwQ*NhB+ !0<PmJswIfP#O;M+#C`Bl>(ciy5:I,P3D_;=O|y~3PZQ=ZGG]&Pf',
			'track_users' => true,
			'prefix' => 'b_',
			'controller' => 'projects',
			'action' => 'index',
			'permalinks' => false,
			'debug' => true,
			'admin' => array('127.0.0.1'),
		);
		
		$conf['app']['core'] = dirname(__FILE__);
		
		// if document root empty, figure it out
		if (trim($conf['app']['root']) === '') {
			$conf['app']['root'] = substr($conf['app']['core'], 0, strrpos($conf['app']['core'], '/'));
		}
				
		$conf['db'] = array(
			'host' => 'localhost',
			'name' => 'builder',
			'user' => 'root',
			'pass' => 'root',
			'prefix' => $conf['app']['prefix'],
			'engine' => 'mysql',
			'encoding' => 'utf-8',
			'tables' => array()
		);
		
		
		$conf['dir'] = array(
			'views' => $conf['app']['core'] . '/_views',
			'models' => $conf['app']['core'] . '/_models',
			'controllers' => $conf['app']['core'] . '/_controllers',
			'libs' => $conf['app']['core'] . '/libs',
			'tools' => $conf['app']['core'] . '/tools',
			'tmp' => $conf['app']['core'] . '/tmp',
			'files' => $conf['app']['root'] . '/files'
		);
		
		$conf['dir']['cache'] = $conf['dir']['tmp'] . '/cache';
		
		
		$conf['url'] = array(
			'files' => $conf['app']['siteurl'] . '/files'
		);
	
		self::$conf = &$conf;	
	}
	
	
	public static function __callStatic($name, $extension)
	{
		if (!isset(self::$conf[$name])) {
			return null;	
		}
		
		if (empty($extension)) {
			return self::$conf[$name];	
		}
		
		if (isset(self::$conf[$name][$extension[0]])) {
			return self::$conf[$name][$extension[0]];	
		}
	}
	
}

Conf::init();
