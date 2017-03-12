<?php

namespace Podquilt;

class Feed
{

    public function __construct($sourceFeed = null)
    {

        if($sourceFeed !== null)
        {
            // retrieve all items from a source feed and add them to this feed object
            $this->initItems($sourceFeed);
        }

        return $this;

    }

    public function addItem(Item $item)
    {
        // update the array in the items property
        $this->items[] = $item;
    }

    public function sortItemsByPubDate($dir = 'desc')
    {
        $items = $this->items;

        // perform the sort
        usort($items, array("\\Podquilt\\Feed", "comparePubDates"));

        // sorted items are in descending order by default, reverse array for ascending order
        if($dir !== 'desc')
        {
            $items = array_reverse($items);
        }

        $this->items = $items;
        return $this;
    }

    // cast all of this feed's item nodes in an array
    public function getItemNodes()
    {

        // an array to hold the items as we cast them all to arrays
        $itemNodes = array();

        // loop through items in this feed, adding their nodes to the itemNodes array
        foreach($this->items as $item)
        {
            $itemNodes[] = $item->node;
        }

        return $itemNodes;
    }

	public function toXml($channelConfig = array())
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

		// use channel data to generate channel contents
		if(is_array($channelConfig))
		{
			foreach ($channelConfig as $key => $value)
			{
				$channelNode = $document->createElement($key, $value);
				$channel->appendChild($channelNode);
			}
		}

		// get nodes from all items in the output feed
		$itemNodes = $this->getItemNodes();

		// import the item nodes to the document and append it to the channel node
		foreach($itemNodes as $itemNode)
		{
			$importedNode = $document->importNode($itemNode, true);
			$channel->appendChild($importedNode);
		}

		return $document->saveXml();
	}

    protected function initItems($sourceFeed)
    {

        // set the item limit and max age for this source feed
        $limit = \Podquilt\Config::DEFAULT_ITEM_LIMIT;
        if(array_key_exists('item_limit', $sourceFeed))
        {
            $limit = (int) $sourceFeed->item_limit;
        }
        $maxAge = \Podquilt\Config::DEFAULT_ITEM_MAX_AGE;
        if(array_key_exists('item_max_age', $sourceFeed))
        {
            $maxAge = (int) $sourceFeed->item_max_age;
        }

        // get a DateTime object for the max age relative to the current time
        $maxAgeDate = new \DateTime($maxAge . ' days ago', new \DateTimeZone('UTC'));

        // only proceed if the the limit is a positive number
        if($limit > 0)
        {
            // if there's a URL for the source feed, attempt to fetch its items
            if(array_key_exists('url', $sourceFeed))
            {

                // get a DOMDocument object to retrieve item nodes from
                $document = $this->getDocumentFromUrl($sourceFeed->url);

                // find all item nodes in the document
                $itemNodes = $document->getElementsByTagName('item');

                // loop through the item nodes, adding them to this feed
                $index = 1; // set an incrementing loop index to limit the number of items
                foreach($itemNodes as $itemNode){

                    if($index > $limit)
                    {
                        break;
                    }

	                // increment the index
	                $index++;

                    // TODO: Implement filtering of feed items matching criteria specified in config file

                    // create an item object for each item node
                    $item = new \Podquilt\Item($itemNode, $sourceFeed);

                    // skip item if it is older than the max age allowed
                    if($item->pubDate < $maxAgeDate)
                    {
                        continue;
                    }

	                // add the item to this feed
	                $this->addItem($item);

                }

            }
        }

        return $this;

    }

    // fetch an XML file by URL and parse it into a DOMDocument object
    protected function getDocumentFromUrl($url)
    {

        $document = new \DOMDocument;

        // set a few options to make XML parsing problems as silent as possible
        // TODO: Debug the real reason warnings are sometimes being thrown here, instead of just suppressing them
        libxml_use_internal_errors(true);
        $document->recover = true;
        $document->strictErrorChecking = false;

        if(!filter_var($url, FILTER_VALIDATE_URL) === false)
        {
            // retrieve the remote XML
            $contents = @file_get_contents($url);
            if($contents)
            {
                // trim the contents for a document encapsulated in <xml></xml> nodes
                $trimmedContents = substr($contents, 0, strpos($contents, "</xml>"));
                if($trimmedContents === false)
                {
                    // if that didn't work, try the same thing for <rss></rss> nodes
                    $trimmedContents = substr($contents, 0, strpos($contents, "</rss>"));
                }
                // figure out whether to use trimmed contents or the raw data
                $contents = $trimmedContents ? $trimmedContents : $contents;
                // load the XML with options for handling very large (>10MB) content and suppressing warnings
                // TODO: See todo above, about replacing kludgy warning suppression with real error handling
                $document->loadXML($contents, LIBXML_PARSEHUGE & LIBXML_NOWARNING & LIBXML_NOERROR);
            }
        }
        return $document;

    }

    static function comparePubDates($a, $b)
    {
        if($a->pubDate > $b->pubDate)
        {
            return -1;
        }
        elseif($a->pubDate < $b->pubDate)
        {
            return 1;
        }
        return 0;
    }

}