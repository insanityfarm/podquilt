<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use DOMDocument;
use DOMElement;
use Podquilt\Config\ChannelConfig;

/**
 * Renders the final aggregated RSS document while preserving namespaced child nodes from source items.
 */
final readonly class RssRenderer
{
    /**
     * @param list<FeedItem> $items
     */
    public function render(ChannelConfig $channelConfig, array $items): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $rss = $document->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $document->appendChild($rss);

        $channel = $document->createElement('channel');
        $rss->appendChild($channel);

        foreach ($channelConfig->toElementMap() as $key => $value) {
            $channel->appendChild($this->createTextElement($document, $key, $value));
        }

        foreach ($items as $item) {
            $channel->appendChild($document->importNode($item->node(), true));
        }

        return (string) $document->saveXML();
    }

    private function createTextElement(DOMDocument $document, string $nodeName, string $value): DOMElement
    {
        $element = $document->createElement($nodeName);
        $element->appendChild($document->createTextNode($value));

        return $element;
    }
}
