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

        foreach ($this->collectNamespaces($items) as $prefix => $namespaceUri) {
            $rss->setAttribute('xmlns:' . $prefix, $namespaceUri);
        }

        $channel = $document->createElement('channel');
        $rss->appendChild($channel);

        foreach ($channelConfig->toElementMap() as $key => $value) {
            $channel->appendChild($document->createElement($key, $value));
        }

        foreach ($items as $item) {
            $channel->appendChild($document->importNode($item->node(), true));
        }

        return (string) $document->saveXML();
    }

    /**
     * @param list<FeedItem> $items
     * @return array<string, string>
     */
    private function collectNamespaces(array $items): array
    {
        $namespaces = [];

        foreach ($items as $item) {
            $this->collectNamespacesFromElement($item->node(), $namespaces);
        }

        ksort($namespaces);

        return $namespaces;
    }

    /**
     * @param array<string, string> $namespaces
     */
    private function collectNamespacesFromElement(DOMElement $element, array &$namespaces): void
    {
        $prefix = $element->prefix;

        if ($prefix !== '') {
            $namespaceUri = (string) $element->namespaceURI;

            if ($namespaceUri !== '') {
                $namespaces[$prefix] = $namespaceUri;
            }
        }

        foreach ($element->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $this->collectNamespacesFromElement($childNode, $namespaces);
            }
        }
    }
}
