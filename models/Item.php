<?php

class Item {

    // TODO: Add a config setting so this can be an overridable default instead of hard-coded rule
    const GUID_HASH_ALGORITHM = 'sha256';

    public function __construct(DOMElement $itemNode, $sourceFeed)
    {

        // attach the DOMElement object to the item object
        $this->node = $itemNode;

        // set the source feed for easy retrieval later
        $this->sourceFeed = $sourceFeed;

        // convert the XML <item /> node's children into properties of this object
        foreach($itemNode->childNodes as $node)
        {
            $nodeName = $node->nodeName;
            $this->$nodeName = $node->nodeValue;
        }

        $this->transform();
        return $this;

    }

    public function setGuid()
    {
        // replace item GUID with a hash of its original value or generate one if none exists
        // this may improve compatibility between some particularly formatted feeds and some podcatchers
        $guidNodes = $this->node->getElementsByTagName('guid');
        if($guidNodes->length)
        {
            foreach($guidNodes as $guidNode)
            {
                $oldGuid = $guidNode->nodeValue;
                $guidNode->nodeValue = hash(self::GUID_HASH_ALGORITHM, $oldGuid);
            }
        }
        else
        {
            // this item doesn't have a GUID, so create a new one by hashing its title and description
            $titleNodes = $this->node->getElementsByTagName('title');
            $descriptionNodes = $this->node->getElementsByTagName('description');
            $stringToHash = '';
            if($titleNodes->length)
            {
                foreach($titleNodes as $titleNode)
                {
                    $stringToHash .= $titleNode->nodeValue;
                }
            }
            if($descriptionNodes->length)
            {
                foreach($descriptionNodes as $descriptionNode)
                {
                    $stringToHash .= $descriptionNode->nodeValue;
                }
            }
            $guid = hash(self::GUID_HASH_ALGORITHM, $stringToHash);
            $document = $this->node->ownerDocument;
            $guidNode = $document->createElement('guid', $guid);
            $document->importNode($guidNode);
            $this->node->appendChild($guidNode);
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

    // logic for transforming some nodes before adding them to output feed
    public function transform()
    {

        // prepend to the item title if needed
        if(array_key_exists('prepend', $this->sourceFeed))
        {
            $this->prependTitle($this->sourceFeed->prepend);
        }

        // set GUID for the item
        $this->setGuid();

        // convert the item's pubDate string to a PHP DateTime object
        if(array_key_exists('pubDate', $this))
        {
            $pubDateString = $this->pubDate;
            $pubDate = DateTime::createFromFormat(DateTime::RSS, $pubDateString);
            $this->pubDate = $pubDate;
        }

        return $this;
    }

}