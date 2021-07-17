<?php

namespace Podquilt;

class Feed
{

	public $items = array();
	public $source = null;

	protected $_itemLimit;
	protected $_itemMaxAge;
	protected $_itemMaxAgeDate;
	protected $_isReplayEnabled;
	protected $_replayQueue;
	protected $_replaySchedule;
	protected $_replayStartDate;
	protected $_replayOriginalStartDate;

    public function __construct(\Podquilt\App $app, $source = null)
    {
    	$this->app = $app;
	    if($source)
	    {
		    // make the source feed or file available for this instance
		    $this->source = $source;

		    // retrieve all items from the source feed or file and add them to this feed object
		    $this->_initItems();
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

    protected function _initItems()
    {

        // only proceed if the the limit is a positive number
        if($this->_getItemLimit() > 0)
        {
            // if there's a URL for the source feed, attempt to fetch its items
            if(array_key_exists('url', $this->source))
            {

                // get a DOMDocument object to retrieve item nodes from
                $document = $this->_getDocumentFromUrl($this->source->url);

                // find all item nodes in the document
                $itemNodes = $document->getElementsByTagName('item');
	            $this->app->log->write('Beginning to parse feed: ' . $this->source->url, Log::LOG_LEVEL_INFO);

                // loop through the item nodes, adding them to this feed
                $index = 1; // set an incrementing loop index to limit the number of items
                foreach($itemNodes as $itemNode)
                {
                    if($index > $this->_getItemLimit())
                    {
                        break;
                    }

                    // create an item object for this item node
                    $item = new \Podquilt\Item($itemNode, $this->source);

                    // determine if item should be included in generated feed
                    if($this->_checkIfItemIsWanted($item))
                    {
                        // add the item to this feed
	                    $this->addItem($item);
                        // increment the index
                        $index++;
                    }

                }

	            $this->app->log->write('Parsing complete. ' . ($index - 1) . ' items were retrieved from feed.', Log::LOG_LEVEL_INFO);

            }
        }

        return $this;

    }

    // fetch an XML file by URL and parse it into a DOMDocument object
    protected function _getDocumentFromUrl($url)
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
            $contents = $this->_requestUrl($url);
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
        else
        {
			$this->app->log->write('Invalid URL for feed: ' . $url, Log::LOG_LEVEL_WARN);
        }
        return $document;
    }

    protected function _requestUrl($url)
    {
    	// build a complete HTTP request for curl (some servers don't play nicely without certain headers)
	    $headers = [
		    'User-Agent: ' . (array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : 'Podquilt ' . Podquilt::VERSION),
		    'Accept: */*'
	    ];
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	    $response = curl_exec($ch);
	    // log non-success responses as info or warning, depending on whether code was an error (e.g., 400 or higher)
	    if(($responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE)) !== 200)
	    {
	    	$logLevel = $responseCode < 400 ? Log::LOG_LEVEL_INFO : Log::LOG_LEVEL_WARN;
		    $this->app->log->write('Request for ' . $url . ' returned code ' . $responseCode . '.', $logLevel);
	    }
	    curl_close($ch);
	    return $response;
    }

    // TODO: Any way to make _getItemLimit() and _getItemMaxAge() more DRY? They're basically the same

    protected function _getItemLimit()
    {
    	if(!$this->_itemLimit)
    	{
		    // fetch the default item limit from config and put it in an array by itself
		    $itemLimits = array(\Podquilt\Config::DEFAULT_ITEM_LIMIT);
		    if(array_key_exists('item_limit', $this->source))
		    {
		    	// if this feed has its own item limit, add that to the array as well
			    $itemLimits[] = (int) $this->source->item_limit;
		    }
		    // whichever number is smaller takes precedence
		    $this->_itemLimit = min($itemLimits);
	    }
	    return $this->_itemLimit;
    }

	protected function _getItemMaxAge()
	{
		if(!$this->_itemMaxAge)
		{
			// fetch the default item max age from config and put it in an array by itself
			$itemMaxAges = array(\Podquilt\Config::DEFAULT_ITEM_MAX_AGE);
			if(array_key_exists('item_max_age', $this->source))
			{
				// if this feed has its own item max age, add that to the array as well
				$itemMaxAges[] = (int) $this->source->item_max_age;
			}
			// whichever number is smaller takes precedence
			$this->_itemMaxAge = min($itemMaxAges);
		}
		return $this->_itemMaxAge;
	}

	protected function _getItemMaxAgeDate()
	{
		if(!$this->_itemMaxAgeDate)
		{
			// get a DateTime object for the max age relative to the current time
			$this->_itemMaxAgeDate = new \DateTime($this->_getItemMaxAge() . ' days ago', new \DateTimeZone('UTC'));
		}
		return $this->_itemMaxAgeDate;
	}

    protected function _getIsReplayEnabled()
    {
        if(!isset($this->_isReplayEnabled))
        {
            $this->_isReplayEnabled = false;
            if(!array_key_exists('replay', $this->source) || !is_object($this->source->replay))
            {
                return $this->_isReplayEnabled;
            }
            if($this->_getReplaySchedule() === false)
            {
                $this->app->log->write('The feed\'s replay schedule is an improperly formatted cron expression.', Log::LOG_LEVEL_ERROR);
                return $this->_isReplayEnabled;
            }
            if($this->_getReplayStartDate() === '')
            {
                $this->app->log->write('The feed\'s replay start date is an improperly formatted timestamp.', Log::LOG_LEVEL_ERROR);
                return $this->_isReplayEnabled;
            }
            if($this->_getReplayOriginalStartDate() === '')
            {
                $this->app->log->write('The feed\'s replay original start date is an improperly formatted timestamp.', Log::LOG_LEVEL_ERROR);
                return $this->_isReplayEnabled;
            }
            $this->_isReplayEnabled = true;
        }
        return $this->_isReplayEnabled;
    }

    protected function _getReplaySchedule()
    {
        if(!$this->_replaySchedule)
        {
            $this->_replaySchedule = false;
            if(array_key_exists('schedule', $this->source->replay))
            {
                if(\Cron\CronExpression::isValidExpression($this->source->replay->schedule))
                {
                    $this->_replaySchedule = new \Cron\CronExpression($this->source->replay->schedule);
                }
            }
        }
        return $this->_replaySchedule;
    }

    protected function _getReplayStartDate()
    {
        if(!$this->_replayStartDate)
        {
            $this->_replayStartDate = '';
            if(array_key_exists('replayStartDate', $this->source->replay))
            {
                $this->_replayStartDate = \DateTime::createFromFormat(\DateTime::RSS, $this->source->replay->replayStartDate);
            }
        }
        return $this->_replayStartDate;
    }

    protected function _getReplayOriginalStartDate()
    {
        if(!$this->_replayOriginalStartDate)
        {
            $this->_replayOriginalStartDate = '';
            if(array_key_exists('originalStartDate', $this->source->replay))
            {
                $this->_replayOriginalStartDate = \DateTime::createFromFormat(\DateTime::RSS, $this->source->replay->originalStartDate);
            }
        }
        return $this->_replayOriginalStartDate;
    }

    protected function _getReplayQueue()
    {
        if(!$this->_replayQueue)
        {
            $this->_replayQueue = [];
            if($this->_getIsReplayEnabled())
            {
                $nextDate = $this->_getReplayStartDate();
                while($this->app->now > $nextDate = $this->_getReplaySchedule()->getNextRunDate($nextDate))
                {
                    $this->_replayQueue[] = $nextDate; 
                }
            }
        }
        return $this->_replayQueue;
    }

    protected function _checkIfItemIsWanted($item)
    {
        // TODO: Allow global filters to be applied to all feeds alongside individual feed filters
        // apply filter(s) if any are defined for this feed
        if(array_key_exists('filter', $this->source) && is_object($this->source->filter))
        {
            // loop through all of the filters for this feed, only proceeding if this item matches all
            foreach($this->source->filter as $node => $pattern)
            {
                // only evaluate regex against nodes that actually exist on the item
                if(array_key_exists($node, $item) && preg_match('/'.$pattern.'/i', $item->$node) === 0)
                {
                    return false;
                }
            }
        }
        // skip item if it is older than the max age allowed or scheduled for future publication
        if($item->pubDate < $this->_getItemMaxAgeDate() || $item->pubDate > $this->app->now)
        {
            return false;
        }
        return true;
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