<?php

namespace Podquilt;

// Main Podquilt application class

require_once('models/App.php');

class Podquilt extends \Podquilt\App {

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
    
        // start building a new document
        $document = new \DOMDocument;
        
        // create <rss><channel /></rss> nodes and append them to the document root
        $rss = $document->createElement('rss');
        $channel = $document->createElement('channel');
        $rssVersionAttribute = $document->createAttribute('version');
        $rssVersionAttribute->value = '2.0';
        $document->appendChild($rss);
        $rss->appendChild($rssVersionAttribute);
        $rss->appendChild($channel);
        
        // get channel data from config
        $channelConfig = get_object_vars($this->getConfig('channel'));
        
        // use channel data to generate channel contents
        foreach($channelConfig as $key => $value)
        {
            $channelNode = $document->createElement($key, $value);
            $channel->appendChild($channelNode);
        }
        
        // get nodes from all items in the output feed
        $itemNodes = $this->output->getItemNodes();
        
        // import the item nodes to the document and append it to the channel node
        foreach($itemNodes as $itemNode)
        {
            $importedNode = $document->importNode($itemNode, true);
            $channel->appendChild($importedNode);
        }
        
        return $document->saveXml();
    }
    
    private function getFeeds()
    {
        $feeds = array();
        $sourceFeeds = $this->getConfig('feeds');
        
        foreach($sourceFeeds as $sourceFeed)
        {
        	if(property_exists($sourceFeed, 'disabled') && $sourceFeed->disabled === 'true')
	        {
		        continue;
	        }
	        $feed = new \Podquilt\Feed($sourceFeed);
	        $feeds[] = $feed;
        }
        return $feeds;
    }

}