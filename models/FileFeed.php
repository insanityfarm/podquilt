<?php

namespace Podquilt;

class FileFeed extends \Podquilt\Feed
{

    public function __construct($sourceFile = null)
    {

        if($sourceFile !== null)
        {
            // convert file to an item node and add it to this feed object
	        $item = $this->initItem($sourceFile);
        }

        return $this;

    }

    protected function initItem($sourceFile)
    {

    	// create the item node (document is just for generating it but will not be returned)
	    $document = new \DOMDocument;
	    $itemNode = $document->createElement('item');

	    // TODO: Add validation that these config keys exist; title and description should be optional

	    // add a title node with the value from config
	    $title = $document->createElement('title', $sourceFile->title);
	    $itemNode->appendChild($title);

	    // add a description node with the value from config
	    $description = $document->createElement('description', $sourceFile->title);
	    $itemNode->appendChild($description);

	    // read the pubDate from config, convert to UTC and add to node in standardized format
	    $pubDateTime = new \DateTime($sourceFile->pubDate, new \DateTimeZone('UTC'));
	    $pubdateValue = $pubDateTime->format('r');
	    $pubDate = $document->createElement('pubDate', $pubdateValue);
	    $itemNode->appendChild($pubDate);

	    // render the URL in an enclosure node
	    $enclosure = $document->createElement('enclosure');
	    $enclosureUrlAttribute = $document->createAttribute('url');
	    $enclosureUrlAttribute->value = $sourceFile->url;
	    $enclosure->appendChild($enclosureUrlAttribute);
	    $itemNode->appendChild($enclosure);

	    $item = new \Podquilt\Item($itemNode, array());
	    $this->addItem($item);
		return $this;

    }

}