<?php

/**
 * @@author Davit Barbakadze
 */

class Curl {


    /**
     * Connection Timeout
     * @var int
     */
    public $timeout = 50; //secs

    public $url;

    public $params = array();

	public $errno;
    public $error;

    public $referer = '';
	
	public $fail_onerror = false;
	

    function __construct()
    {

    }

    function fetch($url, $filename, $dir)
    {
        if (!$filename)
            $filename = uniqid();

        if (!is_writable($dir))
            return new Error('error', "$dir is not writable. Check permissions and try again.");

        set_time_limit(0);
        $this->timeout = 0;

        $filename = rtrim($dir, '/') . '/' . $filename;

        $fp = fopen( $filename, 'w' );

        $results = $this->request($url, 'get', '', '', '', $fp);
        if (!$results)
            return new Error('error', $this->error);

        return $filename;
    }

    function get($url, $options = '', $username = '', $password = '')
    {
        return $this->request($url, 'get', $options, $username, $password);
    }
	
	function post($url, $options = '')
	{
		return $this->request($url, 'post', $options);
	}

    function get_webpage_title($url)
    {
        if (!$result = $this->get($url))
            return '';

        return preg_match('|<title>([\s\S]*?)</title>|', $result, $matches) ? $matches[1] : '';
    }

    function request($url, $method = 'get', $options = '', $username = '', $password = '', $fp = false)
    {
        $ch = curl_init();

        $options = !empty($options) && is_array($options) ? http_build_query($options) : $options;

        if (strtolower($method) == 'post') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options);
        } else {
            $url = rtrim($url, '/');
            if (!empty($options))
                $url.= '?' . $options;
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $this->url = $url;

        if (!empty($username) && !empty($password)) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC|CURLAUTH_NTLM);
            curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        }

        if ($fp !== false) {
            curl_setopt($ch, CURLOPT_FILE, $fp);
        } else {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           // curl_setopt($ch, CURLOPT_BINARYTRANSFER, 0);
        }

        if (!empty($this->referer))
            curl_setopt($ch, CURLOPT_REFERER, $this->referer);

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, $this->fail_onerror);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // ignore ssl certificate check

        // additional params if any
        if (!empty($this->params)) {
        	foreach ($this->params as $key => $value) {
        		curl_setopt($ch, $key, $value);
        	}
        }

        $result = curl_exec($ch);
        
		$this->error = curl_error($ch);
		$this->errno = curl_errno($ch);
        
		if (!empty($this->error))
		{
            $result = false;
		}
			
			
        curl_close($ch);

        return $result;
    }
	
	function async($url, $params)
	{
		$parts = parse_url($url);
		$post_string = http_build_query($params);	
	
		$fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80);
	
		$out = "POST ".$parts['path']." HTTP/1.1\r\n";
		$out.= "Host: ".$parts['host']."\r\n";
		$out.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out.= "Content-Length: ".strlen($post_string)."\r\n";
		$out.= "Connection: Close\r\n\r\n";
		if (isset($post_string)) $out.= $post_string;
	
		fwrite($fp, $out);
		fclose($fp);
	}
	
	
	static function get_status_header_desc( $code ) 
	{
		$code = absint( $code );
	
		$desc = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
	
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			226 => 'IM Used',
	
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Reserved',
			307 => 'Temporary Redirect',
	
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',
	
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			510 => 'Not Extended'
		);
	
		return isset($desc[$code]) ? $desc[$code] : '';
	}
}

?>