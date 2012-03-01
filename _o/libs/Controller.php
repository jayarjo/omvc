<?php

class Controller {
	
	private $scripts = array(), $styles = array(), $scripts_footer = array();
	
	public $db, $conf, $params;
	
	public $tpl, $tpl_path;
	
	
	function __construct()
	{
		$this->db = App::$db;
		$this->sd = App::$sd;
		$this->params = App::$params;	
		
		$this->tpl_path =  App::$conf['dir']['views'] . '/' . App::$ctrl;
		$this->tpl = $this->tpl_path . '/' . App::$act . '.php';
		
		App::add_action('the_header', array($this, 'print_header_scripts_styles'));
		App::add_action('the_footer', array($this, 'print_footer_scripts'));
		
		$this->add_style('bootstrap.min');
		$this->add_style('style');
		
		$this->add_script('jquery');
		$this->add_script('bootstrap.min');

		$this->add_script('script', true);
	}
	
	
	function _construct() {}
	
	
	function index()
	{
		include($this->tpl);	
	}
		
	
	protected function add_script($name, $in_footer = false)
	{	
		if (strpos($name, '.js') !== false) {
			$path = App::$conf['app']['root'] . "/js/" . preg_replace('|\.js([\s\S]+)$|', '.js', $name);
		} else {
			$name .= '.js'; 
			$path = App::$conf['app']['root'] . "/js/$name";
		}
			
		if (!file_exists($path))
			return false;
		
		$url = App::$conf['app']['siteurl'] . "/js/$name";
		
		if ($in_footer)	
			$this->scripts_footer[$name] = compact('url', 'path');
		else
			$this->scripts[$name] = compact('url', 'path');
			
		return true;
	}
	
	
	protected function add_style($name)
	{
		if (strpos($name, '.css', strlen($name) - 3) !== false)
			$name = substr_replace($name, '', -4);
			
		$path = App::$conf['app']['root'] . "/css/$name.css";
		if (!file_exists($path))
			return false;
		
		$url = App::$conf['app']['siteurl'] . "/css/$name.css";
			
		$this->styles[$name] = compact('url', 'path');
		return true;
	}
	
	
	function print_header_scripts_styles()
	{		
		$this->output('script', $this->scripts);
		$this->output('style', $this->styles);
	}
	
	
	function print_footer_scripts()
	{
		$this->output('script', $this->scripts_footer);
	}
	
	
	
	private function output($type, &$files)
	{
		if (!sizeof($files)) return;
				
		if ($type === 'script')
		{
			$ext = ".js";
			$tpl_str = '<script type="text/javascript" src="%URL%"></script>'."\n";
			$minifier = "App::minify_scripts";
		}
		else
		{
			$ext = ".css";
			$tpl_str = '<link rel="stylesheet" type="text/css" href="%URL%" />' . "\n";
			$minifier = "App::minify_styles";
		}
		
		// if debug is disabled, concatenate and minify
		if (!App::$conf['app']['debug'] && is_writable(App::$conf['dir']['files'])) 
		{
			if (!file_exists(App::$conf['dir']['files'] . '/min'))
				mkdir(App::$conf['dir']['files'] . '/min', 0777, true);
			
			$cache_time = 60 * 60 * 24;
			$cacher = new Cache(App::$conf['dir']['files'] . '/min');	
					
			$cache_name = $cacher->get_name(join('::', array_keys($files))) . $ext;
			if (!$cacher->get($cache_name, $cache_time, true))
			{
				$source = '';
				foreach ($files as $file)
				{
					$source.= file_get_contents($file['path']) . "\n";
					
					// change relative image urls for styles to absolute ones
					if ($type !== 'script')
						$source = str_replace('url(../img/', 'url('.App::$conf['app']['siteurl'].'/img/', $source);					
				}
				// minify
				$cacher->set($cache_name, call_user_func($minifier, $source), true);
			}
			$url = App::$conf['url']['files'] . '/min/' . $cache_name;
			echo str_replace('%URL%', $url, $tpl_str);	
		}
		else // simply output
		{
			foreach ($files as $file)
				echo str_replace('%URL%', $file['url'], $tpl_str);	
		}
		
		$files = array(); // reset
	}
		
}


?>