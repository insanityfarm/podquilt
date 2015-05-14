<?php

class Feed {

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
        usort($items, array("Feed", "comparePubDates"));
        
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
    
    private function initItems($sourceFeed)
    {
        
        // set the item limit and max age for this source feed
        $limit = Config::DEFAULT_ITEM_LIMIT;
        if(array_key_exists('item_limit', $sourceFeed))
        {
            $limit = (int) $sourceFeed->item_limit;
        }
        $maxAge = Config::DEFAULT_ITEM_MAX_AGE;
        if(array_key_exists('item_max_age', $sourceFeed))
        {
            $maxAge = (int) $sourceFeed->item_max_age;
        }
        
        // get a DateTime object for the max age relative to the current time
        $maxAgeDate = new DateTime($maxAge . ' days ago', new DateTimeZone('UTC'));
    
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
        
                    // create an item object for each item node
                    $item = new Item($itemNode);

                    // prepend to the item title if needed
                    if(array_key_exists('prepend', $sourceFeed))
                    {
                        $item->prependTitle($sourceFeed->prepend);
                    }
                    
                    // only proceed if the item is newer than the max age allowed
                    if($item->pubDate >= $maxAgeDate)
                    {
                        // add the item to this feed
                        $this->addItem($item);
                    }
                    
                    // increment the index
                    $index++;
                
                }
            
            }
        }
        
        return $this;
        
    }
    
    // fetch an XML file by URL and parse it into a DOMDocument object
    private function getDocumentFromUrl($url)
    {
    
        $document = new DOMDocument;
        if(!filter_var($url, FILTER_VALIDATE_URL) === false)
        {
            // retrieve the remote XML
            $contents = file_get_contents($url);
            $document->loadXML($contents);
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