<?php

namespace Podquilt;

// Main Podquilt application class

require_once('models/App.php');

class Podquilt extends \Podquilt\App
{

    public function __construct()
    {
        parent::__construct();
        $this->feeds = $this->getFeeds();
        $this->aggregateFeedItems();
        return $this;
    }
    
    public function aggregateFeedItems()
    {
        // create output feed
        $this->output = new \Podquilt\Feed;
        
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
        $channelConfig = get_object_vars($this->getConfig('channel'));

        return $this->output->toXml($channelConfig);

    }
    
    protected function getFeeds()
    {

        $feeds = array();
        $sourceFeeds = $this->getConfig('feeds');
	    $sourceFiles = $this->getConfig('files');
        
        foreach($sourceFeeds as $sourceFeed)
        {
        	// skip feeds flagged as disabled
	        if($this->isEnabled($sourceFeed))
	        {
		        $feeds[] = new \Podquilt\Feed($sourceFeed);;
	        }
        }

        if($sourceFiles)
        {
	        foreach($sourceFiles as $file)
	        {
	        	if($this->isEnabled($file))
		        {
			        $feeds[] = new \Podquilt\FileFeed($file);
		        }
	        }
        }

        return $feeds;

    }

	protected function isEnabled($data)
	{
		// return true unless object contains a property "disabled" with a (string) value of "true"
		return !(property_exists($data, 'disabled') && $data->disabled === 'true');
	}

}