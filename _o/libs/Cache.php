<?php

class Cache {
	
	public $dir;

    function __construct($dir)
    {
        $this->dir = $dir;
		
		if (!file_exists($this-dir))
			@mkdir($this->dir, 0777, true);
    }
	
	
	public function get_name($key)
	{
		if (is_array($key))
			$key = serialize($key);
		
		return sha1($key);
	}
	

    public function get($key, $expiration = 3600, $key_encoded = false)
    {

        if ( !is_dir($this->dir) || !is_writable($this->dir))
            return false;

        if (!$key_encoded)
			$key = $this->get_name($key);	

        $cache_path = "{$this->dir}/$key"; 
		
        if (!@file_exists($cache_path))
        	return false;

        if (filemtime($cache_path) < (time() - $expiration))
        {
           $this->clear($key, $key_encoded);
           return false;
        }

        if (!$fp = @fopen($cache_path, 'rb'))
        	return false;

        flock($fp, LOCK_SH);

        $cache = '';

        if (filesize($cache_path) > 0)
		{
            $cache = fread($fp, filesize($cache_path));
			if ($this->is_serialized($cache))
				$cache = @unserialize($cache);
		}
        else
            $cache = null;

        flock($fp, LOCK_UN);
        fclose($fp);

        return $cache;
    }
	

    public function set($key, $data, $key_encoded = false)
    {

        if ( !is_dir($this->dir) || !is_writable($this->dir))
            return false;
			
		if (!$key_encoded)
			$key = $this->get_name($key);	

        $cache_path = "{$this->dir}/$key"; 

        if ( ! $fp = fopen($cache_path, 'wb'))
            return false;

        if (flock($fp, LOCK_EX))
        {
            fwrite($fp, !is_string($data) ? serialize($data) : $data);
            flock($fp, LOCK_UN);
        }
        else
            return false;
			
        fclose($fp);
        @chmod($cache_path, 0777);
       
	   return true;
    }
	

    public function clear($key, $key_encoded = false)
    {
        if (!$key_encoded)
			$key = $this->get_name($key);	

        $cache_path = "{$this->dir}/$key"; 

        if (file_exists($cache_path))
        {
            unlink($cache_path);
            return true;
        }

        return false;
    }
	
	/**
	 * @credits: WordPress 
	 *
	 *	Check value to find if it was serialized.
	 *
	 * If $data is not an string, then returned value will always be false.
	 * Serialized data is always a string.
	 *
	 * @since 2.0.5
	 *
	 * @param mixed $data Value to check to see if was serialized.
	 * @return bool False if not serialized and true if it was.
	 */
	private function is_serialized( $data ) {
		// if it isn't a string, it isn't serialized
		if ( !is_string( $data ) )
			return false;
		$data = trim( $data );
		if ( 'N;' == $data )
			return true;
		if ( !preg_match( '/^([adObis]):/', $data, $badions ) )
			return false;
		switch ( $badions[1] ) {
			case 'a' :
			case 'O' :
			case 's' :
				if ( preg_match( "/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data ) )
					return true;
				break;
			case 'b' :
			case 'i' :
			case 'd' :
				if ( preg_match( "/^{$badions[1]}:[0-9.E-]+;\$/", $data ) )
					return true;
				break;
		}
		return false;
	}
}


?>