<?php

class Item {

    public function __construct(DOMElement $itemNode)
    {

        // attach the DOMElement object to the item object
        $this->node = $itemNode;

        // convert the XML <item /> node's children into properties of this object
        foreach($itemNode->childNodes as $node)
        {
            $nodeName = $node->nodeName;
            $this->$nodeName = $node->nodeValue;
        }

        // convert the item's pubDate string to a PHP DateTime object
        if(array_key_exists('pubDate', $this))
        {
            $pubDateString = $this->pubDate;
            $pubDate = DateTime::createFromFormat(DateTime::RSS, $pubDateString);
            $this->pubDate = $pubDate;
        }

        return $this;

    }

    public function prependTitle($prependText)
    {
        $titleNodes = $this->node->getElementsByTagName('title');
        foreach($titleNodes as $titleNode)
        {
            $oldTitle = $titleNode->nodeValue;
            $titleNode->nodeValue = htmlspecialchars($prependText . $oldTitle);
        }
        return $this;
    }

}