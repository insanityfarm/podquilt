<?php

class Config {

    const DEFAULT_ITEM_LIMIT    = 10;   // don't include more than this many items per feed
    const DEFAULT_ITEM_MAX_AGE  = 14;    // max number of days to retain old items

    public function __construct()
    {
        // load the config data
        $config = $this->loadConfig();

        // map that data to config object properties
        foreach($config as $key => $value)
        {
            $this->$key = $value;
        }

        return $this;
    }

    // load the config data from JSON file into an array
    private function loadConfig()
    {
        $config = array();
        try
        {

            if(!file_exists('config.json'))
            {
	            throw new Exception('Unable to find config.json. Please create a configuration file and try again.');
            }

	        $config = json_decode(file_get_contents('config.json', FILE_USE_INCLUDE_PATH));
	        if(json_last_error() != JSON_ERROR_NONE)
	        {
		        throw new Exception('Unable to read config.json. Please check that is is properly formatted and try again.');
	        }
        }
        catch (Exception $e)
        {
            Error::show($e);
        }
        return $config;
    }

}