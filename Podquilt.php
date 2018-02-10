<?php

namespace Podquilt;

// Main Podquilt application class

require_once('models/App.php');

class Podquilt extends \Podquilt\App
{

	const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct();
        $this->feeds = $this->_getFeeds();
        $this->aggregateFeedItems();
        return $this;
    }
    
    public function aggregateFeedItems()
    {
        // create output feed
        $this->output = new \Podquilt\Feed($this);
        
        // loop through all feeds, adding all items to the output feed
        foreach($this->feeds as $feed)
        {
            if(array_key_exists('items', $feed))
            {
                foreach($feed->items as $item)
                {
                    $this->output->addItem($item);
                }
            }
        }
        
        return $this;
    }
    
    public function sortItemsByPubDate($dir = 'desc')
    {
        $this->output->sortItemsByPubDate($dir);
        return $this;
    }
    
    public function toXml()
    {

        // get channel data from config
        $channelConfig = get_object_vars($this->config->channel);

        return $this->output->toXml($channelConfig);

    }

    public function logExecutionTime()
    {
    	$executionTime = round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000);
    	$logMessage = "Process complete. Total execution time: " . $executionTime . "ms\n";
    	$this->log->write($logMessage, Log::LOG_LEVEL_INFO);
    }
    
    protected function _getFeeds()
    {

        $feeds = array();
        $sourceFeeds = $this->config->feeds;
	    $sourceFiles = $this->config->files;
        
        foreach($sourceFeeds as $sourceFeed)
        {
        	// skip feeds flagged as disabled
	        if($this->_isEnabled($sourceFeed))
	        {
		        $feeds[] = new \Podquilt\Feed($this, $sourceFeed);
	        }
        }

        if($sourceFiles)
        {
	        foreach($sourceFiles as $file)
	        {
	        	if($this->_isEnabled($file))
		        {
			        $feeds[] = new \Podquilt\FileFeed($this, $file);
		        }
	        }
        }

        return $feeds;

    }

	protected function _isEnabled($data)
	{
		// return true unless object contains a property "disabled" with a (string) value of "true"
		return !(property_exists($data, 'disabled') && $data->disabled === 'true');
	}

}