<?php

class View {

	public static $vars;

	static function vars($key = array(), $ent = true)
	{
		if (is_array($key)) {
			self::$vars = $key;
			return;
		}

		if (isset(self::$vars[$key])) {
			return $ent && is_string(self::$vars[$key]) ? htmlentities(self::$vars[$key]) : self::$vars[$key];
		}
	}
	
	/**
	 * Adds no cache headers to HTTP response.
	 */
	static function no_cache_headers() 
	{
		// Date in the past
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	
		// always modified
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	
		// HTTP/1.1
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
	
		// HTTP/1.0
		header("Pragma: no-cache");
	}
	
	
	static function file_headers($name, $type, $size)
	{
		if (ini_get('zlib.output_compression'))
			ini_set('zlib.output_compression', 'Off');  //for IE
		
		header('Pragma: public');
		header('Expires: 0');  // no cache
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');//IE
		
		header('Last-Modified: '.date('j/n/Y h:i A'));
		header('Cache-Control: private',false);
		
		//header("Content-type: application/force-download");
		header("Content-Transfer-Encoding: binary");
		header("Content-type: $type" );
		header('Content-Disposition: attachment; filename="'.$name.'"');
		header('Content-Length: ' . $size);
		header('Connection: close');
	}
	
	
	static function send_as_js($output, $no_cache = false)
	{
		if (!$no_cache)
			self::no_cache_headers();
			
		header('Content-type: application/javascript'); 
		echo $output;
	}
	
	
	static function send_as_json($array, $send_headers = false)
	{
		if ($send_headers)
			header("Content-type: application/json");
			
		echo json_encode($array);
	}
	
	
	static function send_as_xml($array, $send_headers = true, $root = 'response')
	{
		if ($send_headers)
			header('Content-Type: text/xml');
	
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><$root>";
		echo self::xml_body($array);
		echo "</$root>";
	}
	
	private static function xml_body($array)
	{
		if (is_array($array)) :
			foreach ($array as $tag => $value)
			{
				if (is_array($value))
					return "<$tag>" . self::xml_body($value) . "</$tag>";
				else
					return "<$tag>$value</$tag>";
			}
		else :
			return "<result>$array</result>";
		endif;
	}
	
	
	static function send_as_file($name, $type, $output)
	{		
		self::file_headers($name, $type, strlen($output));
		echo $output;
	}
	
	
	static function send_file($path, $type, $name = false)
	{
		if (!file_exists($path) || is_dir($path))
			return false;
		
		if (!$name)	
			$name = pathinfo($path, PATHINFO_BASENAME);
		$size = filesize($path);
		
		@ob_end_clean(); //turn off output buffering to decrease cpu usage
		
		self::file_headers($name, $type, $size);
		
		$chunksize = 1*(1024*1024);
		
		$handle = fopen($path, 'rb');
		if ($handle === false)
			die;
		
		while (!feof($handle))
		{
		   $buffer = fread($handle, $chunksize);
		   echo $buffer;
		   flush();
		}
		fclose($handle);
	}
	
	
	
	static function trim_text($text, $length = 55)
	{
		$words = preg_split("/[\n\r\t ]+/", $text, $length + 1, PREG_SPLIT_NO_EMPTY);
		if ( count($words) > $length ) {
			array_pop($words);
			$text = implode(' ', $words) . '...';
		} else {
			$text = implode(' ', $words);
		}	
		return $text;
	}
	
	
	static function strip_whitespace($html, $echo = true)
	{
		$stripped = preg_replace( "/(?:(?<=\>)|(?<=\/\>))(\s+)(?=\<\/?)/", "", preg_replace('|<!--[^>]*-->|', '', $html));
		$stripped = str_replace(array("\r\n", "\n", "\r"), "", $stripped);
	
		if ($echo)
			echo $stripped;
		else
			return $stripped;
	}
	
	
	
	
}

?>