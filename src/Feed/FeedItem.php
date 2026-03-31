<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/**
 * Immutable representation of a single RSS item together with the DOM needed for final rendering.
 */
final readonly class FeedItem
{
    private const GUID_HASH_ALGORITHM = 'sha256';

    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public DOMDocument $document,
        public array $fields,
        public DateTimeImmutable $publishedAt,
    ) {
    }

    /**
     * Builds a feed item from an existing RSS <item> node.
     */
    public static function fromDomElement(DOMElement $itemElement): ?self
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($itemElement, true));
        $fields = self::extractFields(self::rootElement($document));
        $publishedAt = self::parsePublicationDate($fields['pubDate'] ?? null);

        if ($publishedAt === null) {
            return null;
        }

        return new self($document, $fields, $publishedAt);
    }

    public static function fromXmlFragment(string $xml): ?self
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->recover = true;
        $document->strictErrorChecking = false;

        libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_PARSEHUGE | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();

        if ($loaded !== true) {
            return null;
        }

        $root = $document->documentElement;

        if (!$root instanceof DOMElement || $root->nodeName !== 'item') {
            return null;
        }

        return self::fromDomElement($root);
    }

    /**
     * Builds a feed item from synthetic config-backed file source values.
     */
    public static function fromSyntheticValues(
        string $title,
        string $description,
        DateTimeImmutable $publishedAt,
        string $url,
    ): self {
        $document = new DOMDocument('1.0', 'UTF-8');
        $item = $document->createElement('item');
        $document->appendChild($item);

        $item->appendChild(self::createTextElement($document, 'title', $title));
        $item->appendChild(self::createTextElement($document, 'description', $description));
        $item->appendChild(
            self::createTextElement($document, 'pubDate', $publishedAt->format(DateTimeInterface::RSS)),
        );

        $enclosure = $document->createElement('enclosure');
        $enclosure->setAttribute('url', $url);
        $item->appendChild($enclosure);

        return new self(
            document: $document,
            fields: self::extractFields(self::rootElement($document)),
            publishedAt: $publishedAt,
        );
    }

    public function node(): DOMElement
    {
        $node = $this->document->documentElement;

        if (!$node instanceof DOMElement) {
            throw new RuntimeException('Feed items must always carry a document element.');
        }

        return $node;
    }

    public function xml(): string
    {
        return (string) $this->document->saveXML($this->node());
    }

    public function fieldValue(string $nodeName): ?string
    {
        return $this->fields[$nodeName] ?? null;
    }

    #[\NoDiscard]
    public function withPrependedTitle(string $prefix): self
    {
        $document = $this->duplicateDocument();
        $root = self::rootElement($document);
        $mutated = false;

        foreach (['title', 'itunes:title'] as $nodeName) {
            $titleNode = self::directChildElement($root, $nodeName);

            if ($titleNode === null) {
                continue;
            }

            $titleNode->nodeValue = $prefix . $titleNode->nodeValue;
            $mutated = true;
        }

        if (!$mutated) {
            return $this;
        }

        return clone($this, [
            'document' => $document,
            'fields' => self::extractFields($root),
        ]);
    }

    #[\NoDiscard]
    public function withHashedGuid(): self
    {
        $document = $this->duplicateDocument();
        $root = self::rootElement($document);
        $guidNode = self::directChildElement($root, 'guid');

        if ($guidNode !== null) {
            $guidNode->nodeValue = hash(self::GUID_HASH_ALGORITHM, (string) $guidNode->nodeValue);
        } else {
            $guidSource = ($this->fieldValue('title') ?? '') . ($this->fieldValue('description') ?? '');
            $root->appendChild(
                self::createTextElement($document, 'guid', hash(self::GUID_HASH_ALGORITHM, $guidSource)),
            );
        }

        return clone($this, [
            'document' => $document,
            'fields' => self::extractFields(self::rootElement($document)),
        ]);
    }

    #[\NoDiscard]
    public function withPublishedAt(DateTimeImmutable $publishedAt): self
    {
        $document = $this->duplicateDocument();
        $root = self::rootElement($document);
        $pubDateNode = self::directChildElement($root, 'pubDate');
        $formatted = $publishedAt->format(DateTimeInterface::RSS);

        if ($pubDateNode !== null) {
            $pubDateNode->nodeValue = $formatted;
        } else {
            $root->appendChild(self::createTextElement($document, 'pubDate', $formatted));
        }

        return clone($this, [
            'document' => $document,
            'fields' => self::extractFields($root),
            'publishedAt' => $publishedAt,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function extractFields(DOMElement $itemElement): array
    {
        $fields = [];

        foreach ($itemElement->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            $fields[$childNode->nodeName] = (string) $childNode->nodeValue;
        }

        return $fields;
    }

    private static function parsePublicationDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $publishedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::RSS, $value);

        return $publishedAt ?: null;
    }

    private function duplicateDocument(): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->importNode($this->node(), true));

        return $document;
    }

    private static function rootElement(DOMDocument $document): DOMElement
    {
        $root = $document->documentElement;

        if (!$root instanceof DOMElement) {
            throw new RuntimeException('Feed item documents must contain a root <item> element.');
        }

        return $root;
    }

    private static function directChildElement(DOMElement $parent, string $nodeName): ?DOMElement
    {
        foreach ($parent->childNodes as $childNode) {
            if (
                $childNode instanceof DOMElement
                && $childNode->parentNode instanceof DOMNode
                && $childNode->parentNode->isSameNode($parent)
                && $childNode->nodeName === $nodeName
            ) {
                return $childNode;
            }
        }

        return null;
    }

    private static function createTextElement(DOMDocument $document, string $nodeName, string $value): DOMElement
    {
        $element = $document->createElement($nodeName);
        $element->appendChild($document->createTextNode($value));

        return $element;
    }
}
