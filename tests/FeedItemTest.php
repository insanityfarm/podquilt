<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use DateTimeImmutable;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use Podquilt\Feed\FeedItem;

final class FeedItemTest extends TestCase
{
    public function testHashesExistingGuidAndGeneratesMissingGuid(): void
    {
        $itemWithGuid = FeedItem::fromDomElement($this->createItemElement(
            title: 'Guided Item',
            description: 'Guided description.',
            pubDate: 'Mon, 01 Apr 2024 12:00:00 +0000',
            guid: 'guided-guid',
        ));

        $itemWithoutGuid = FeedItem::fromDomElement($this->createItemElement(
            title: 'Unguided Item',
            description: 'Unguided description.',
            pubDate: 'Mon, 01 Apr 2024 12:00:00 +0000',
        ));

        self::assertNotNull($itemWithGuid);
        self::assertNotNull($itemWithoutGuid);

        self::assertSame(
            hash('sha256', 'guided-guid'),
            $itemWithGuid->withHashedGuid()->fieldValue('guid'),
        );

        self::assertSame(
            hash('sha256', 'Unguided ItemUnguided description.'),
            $itemWithoutGuid->withHashedGuid()->fieldValue('guid'),
        );
    }

    public function testPrependedTitleAndUpdatedPublicationDateRefreshFieldState(): void
    {
        $item = FeedItem::fromDomElement($this->createItemElement(
            title: 'Original Title',
            description: 'Original description.',
            pubDate: 'Mon, 01 Apr 2024 12:00:00 +0000',
        ));

        self::assertNotNull($item);

        $updated = $item
            ->withPrependedTitle('Prefix: ')
            ->withPublishedAt(new DateTimeImmutable('2024-04-02T12:00:00+00:00'));

        self::assertSame('Prefix: Original Title', $updated->fieldValue('title'));
        self::assertSame('Tue, 02 Apr 2024 12:00:00 +0000', $updated->fieldValue('pubDate'));
    }

    private function createItemElement(
        string $title,
        string $description,
        string $pubDate,
        ?string $guid = null,
    ): \DOMElement {
        $document = new DOMDocument('1.0', 'UTF-8');
        $item = $document->createElement('item');
        $document->appendChild($item);

        $item->appendChild($document->createElement('title', $title));
        $item->appendChild($document->createElement('description', $description));
        $item->appendChild($document->createElement('pubDate', $pubDate));

        if ($guid !== null) {
            $item->appendChild($document->createElement('guid', $guid));
        }

        return $item;
    }
}
