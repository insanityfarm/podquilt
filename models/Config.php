<?php

namespace Podquilt;

class Config
{

	// TODO: Actually use these consts consistently through the app...

    const DEFAULT_ITEM_LIMIT    = 10;   // don't include more than this many items per feed
    const DEFAULT_ITEM_MAX_AGE  = 14;   // max number of days to retain old items

	const CONFIG_KEY_CHANNEL                            = 'channel';
	const CONFIG_KEY_CHANNEL_DESCRIPTION                = 'description';
	const CONFIG_KEY_CHANNEL_LINK                       = 'link';
	const CONFIG_KEY_CHANNEL_TITLE                      = 'title';

	const CONFIG_KEY_FEEDS                              = 'feeds';
	const CONFIG_KEY_FEEDS_DISABLED                     = 'disabled';
	const CONFIG_KEY_FEEDS_FILTER                       = 'filter';
	const CONFIG_KEY_FEEDS_PREPEND                      = 'prepend';
	const CONFIG_KEY_FEEDS_REPLAY                       = 'replay';
	const CONFIG_KEY_FEEDS_REPLAY_ORIGINALSTARTDATE     = 'originalStartDate';
	const CONFIG_KEY_FEEDS_REPLAY_REPLAYSTARTDATE       = 'replayStartDate';
	const CONFIG_KEY_FEEDS_REPLAY_SCHEDULE              = 'schedule';
	const CONFIG_KEY_FEEDS_URL                          = 'url';

	const CONFIG_KEY_FILES                              = 'files';
	const CONFIG_KEY_FILES_DESCRIPTION                  = 'description';
	const CONFIG_KEY_FILES_PUBDATE                      = 'pubDate';
	const CONFIG_KEY_FILES_TITLE                        = 'title';
	const CONFIG_KEY_FILES_URL                          = 'url';

	const CONFIG_KEY_LOGS                               = 'logs';
	const CONFIG_KEY_LOGS_ENABLED                       = 'enabled';
	const CONFIG_KEY_LOGS_LEVEL                         = 'level';
	const CONFIG_KEY_LOGS_PATH                          = 'path';

	protected $_data;
	protected $_defaults;

    public function __construct(\Podquilt\App $app)
    {

    	$this->_defaults = (object) [
		    self::CONFIG_KEY_CHANNEL => (object) [
			    self::CONFIG_KEY_CHANNEL_DESCRIPTION => 'Your description here.',
			    self::CONFIG_KEY_CHANNEL_LINK => $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'],
			    self::CONFIG_KEY_CHANNEL_TITLE => 'Podquilt'
		    ],
		    self::CONFIG_KEY_FEEDS => (object) [],
		    self::CONFIG_KEY_FILES => (object) [],
		    self::CONFIG_KEY_LOGS => (object) [
		    	self::CONFIG_KEY_LOGS_ENABLED => true,
			    self::CONFIG_KEY_LOGS_LEVEL => \Podquilt\Log::LOG_LEVEL_ERROR,
			    self::CONFIG_KEY_LOGS_PATH => 'logs/podquilt.log'
		    ]
	    ];

    	// load the config data
	    $this->app = $app;
        $app->config = $this->_loadData();
        return $this;
    }

    // load the config data from JSON file into an array
    protected function _loadData()
    {
        try
        {
            if(!file_exists('config.json'))
            {
	            throw new \Exception('Unable to find config.json. Please create a configuration file and try again.');
            }
	        $config = json_decode(
	        	file_get_contents(
	        		'config.json',
			        FILE_USE_INCLUDE_PATH
		        )
	        );
	        if(json_last_error() != JSON_ERROR_NONE)
	        {
		        throw new \Exception('Unable to read config.json. Please check that is is properly formatted and try again.');
	        }
        }
        catch (\Exception $e)
        {
        	// problem parsing the JSON, so force default config for error handling (and logging)
        	$this->app->config = $this->_defaults;
	        new \Podquilt\Log($this->app);
            $this->app->error->show($e);
        }

	    // merge configuration and defaults into single array
	    $config = $this->_recursiveConfigMerge($this->_defaults, $config);

        return $config;
    }

    // recursively merge settings from config.json into the array of defaults
    protected function _recursiveConfigMerge($default = array(), $config = array())
    {
	    if(is_object($default))
	    {
	    	foreach($default as $key => $value)
		    {
		    	if(is_object($config) && property_exists($config, $key))
			    {
			    	$default->$key = is_object($config->$key) ? $this->_recursiveConfigMerge($value, $config->$key) : $config->$key;
			    	// if the default for this key is empty, the configured value supersedes it
			    	if(is_object($default->$key) && empty($default->$key))
				    {
				    	$default->$key = $config->$key;
				    }
			    }
		    }
	    }
	    return $default;
    }

}