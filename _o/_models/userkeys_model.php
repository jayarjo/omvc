<?php

class UserkeysModel extends Model {
	
	
	function get($user_id = false)
	{
		if (!$user_id)
			$user_id = $this->users->id();
			
		$keys = array();
		
		if ($data = parent::get(array('user_id' => $user_id)))
		{
			# re-order by services
			foreach ($data as $key) 
			{
				if (isset($keys[$key['service']]))
					$keys[$key['service']][] = $key;
				else
					$keys[$key['service']] = array($key);	
			}	
		}
		return $keys;
	}
	
	
	function delete_by_user_id($user_id)
	{
		if (!is_numeric($user_id))
			return false;
		
		return $this->db->sql("DELETE FROM $this->table WHERE user_id = $user_id");	
	}
	
	
	
	public $table_sql = "(
		  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		  `user_id` int(10) unsigned NOT NULL,
		  `service` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `service_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
		  `key` text COLLATE utf8_unicode_ci NOT NULL,
		  `created` datetime NOT NULL,
		  `modified` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
	";	
	
}


?>