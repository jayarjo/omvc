<?php

/**
 *
 * @author Davit Barbakadze
 */

class Cipher {
    
    private $securekey, $iv, $size;

    function __construct($textkey)
    {
        $this->securekey = hash('md5',$textkey);
        $this->size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
        // $this->iv = mcrypt_create_iv($this->size, MCRYPT_DEV_RANDOM);
        $this->iv = mcrypt_create_iv($this->size, MCRYPT_RAND);
    }

    function encrypt($input)
    {
        return base64_encode(mcrypt_encrypt(MCRYPT_BLOWFISH, $this->securekey, $input, MCRYPT_MODE_ECB, $this->iv));
    }

    function decrypt($input)
    {
        return trim(mcrypt_decrypt(MCRYPT_BLOWFISH, $this->securekey, base64_decode($input), MCRYPT_MODE_ECB, $this->iv));
    }
}

?>