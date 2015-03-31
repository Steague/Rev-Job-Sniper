<?php

require('../vendor/mycurl.php');

class revMyCurl extends mycurl
{
    public function loginToRev()
    {
        $config = include('config.php');

        $login = new revMyCurl($config['revLoginUrl']);
            $login->setPost(array(
            "email"      => $config['revEmail'],
            "password"   => $config['revPassword'],
            "rememberMe" => "true"
        ));
        $login->setIncludeHeader(true);
        $login->createCurl();
        return $this;
    }

    public function __tostring()
    {
        if (!is_string($this->_webpage))
        {
            $rev = rev::getInstance();
            $rev->revlog($this->_webpage);
            return "";
        }
        return $this->_webpage;
    }
}
