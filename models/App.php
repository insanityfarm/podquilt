<?php

// Base app class, provides general functionality and loads other classes
namespace Podquilt;

require_once('models/Config.php');
require_once('models/Log.php');
require_once('models/Error.php');
require_once('models/Feed.php');
require_once('models/FileFeed.php');
require_once('models/Item.php');

class App
{

    public function __construct()
    {
        date_default_timezone_set('UTC');
	    new \Podquilt\Error($this);
        new \Podquilt\Config($this);
        new \Podquilt\Log($this);
	    $this->log->write('Processing new request from ' . $_SERVER['REMOTE_ADDR'] . '...', Log::LOG_LEVEL_INFO);
        return $this;
    }

}