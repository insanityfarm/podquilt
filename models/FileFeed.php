<?php

namespace Podquilt;

class FileFeed extends \Podquilt\Feed
{

    protected function _initItems()
    {

	    // TODO: Add validation that these source file keys exist; title and description should be optional

    	// parse the source file's pubDate first to validate if it should be included
	    $pubDateTime = new \DateTime($this->source->pubDate, new \DateTimeZone('UTC'));
	    if($pubDateTime > $this->_getItemMaxAgeDate())
	    {

		    // create the item node (document is just for generating it but will not be returned)
		    $document = new \DOMDocument;
		    $itemNode = $document->createElement('item');

		    // add a title node with the value from config
		    $title = $document->createElement('title', $this->source->title);
		    $itemNode->appendChild($title);

		    // add a description node with the value from config
		    $description = $document->createElement('description', $this->source->title);
		    $itemNode->appendChild($description);

		    // read the pubDate from config, convert to UTC and add to node in standardized format
		    $pubdateValue = $pubDateTime->format('r');
		    $pubDate = $document->createElement('pubDate', $pubdateValue);
		    $itemNode->appendChild($pubDate);

		    // render the URL in an enclosure node
		    $enclosure = $document->createElement('enclosure');
		    $enclosureUrlAttribute = $document->createAttribute('url');
		    $enclosureUrlAttribute->value = $this->source->url;
		    $enclosure->appendChild($enclosureUrlAttribute);
		    $itemNode->appendChild($enclosure);

		    $item = new \Podquilt\Item($itemNode, array());
		    $this->addItem($item);

	    }

		return $this;

    }

}