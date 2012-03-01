<?php

require_once( dirname(__FILE__) . "/Curl.php" );
require_once( dirname(__FILE__) . "/Cache.php" );

class CurlCache extends Curl {

    var $cache;
	var $log_dir;

    function __construct($cache_dir, $log_dir = false)
    {
        parent::__construct();
		
		$this->log_dir = $log_dir;
		$this->cache_dir = $cache_dir;
		
        if (!file_exists($cache_dir))
            mkdir($cache_dir, 0777);
						
		$this->cache = new Cache($cache_dir);
    }

    function get($url, $options = '', $time2cache = 0)
    {		
        if ($time2cache === 0) {
            $data = parent::get($url, $options);

            if (empty($data))
                $this->log($url, $this->error);

            return $data;
        }
		
		$key = serialize(array_merge(compact('url'), $options));

        if ($data = $this->cache->get($key, $time2cache)) 
		{
            return $data;
        }
        else
        {
            $data = parent::get($url, $options);

            if (empty($data))
                $this->log($url, $this->error);

            $this->cache->set($key, $data);
            return $data;
        }

    }

    function log($url, $reason = '')
    {
		if ($this->log_dir && is_writable($this->log_dir)) {
			$fh = fopen($this->log_dir . '/curlfail.log', 'a');
			fwrite($fh, date('Y-m-d H:i:s') . ": $url : $reason\n");
			fclose($fh);
		}
    }


}


?>