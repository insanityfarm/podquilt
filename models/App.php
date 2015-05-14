<?php

// Base app class, provides general functionality and loads other classes

require_once('models/Error.php');
require_once('models/Config.php');
require_once('models/Feed.php');
require_once('models/Item.php');

class App {

    public function __construct()
    {
        $this->config = new Config;
        return $this;
    }
    
    public function hasConfig($key)
    {
        $keyExists  = array_key_exists($key, $this->config);
        return $keyExists;
    }
    
    public function getConfig($key = null)
    {
        $config = null;
        if(empty($key))
        {
            $config = $this->config;
        }
        else
        {
            if($this->hasConfig($key))
            {
                $config = $this->config->$key;
            }
        }
        return $config;
    }

}