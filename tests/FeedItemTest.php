<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Podquilt\Feed\FeedItem;

final class FeedItemTest extends TestCase
{
    public function testHashesExistingGuidAndGeneratesMissingGuidUsingDirectChildrenOnly(): void
    {
        $itemWithGuid = $this->itemFromXml(<<<'XML'
<item>
  <title>Guided Item</title>
  <description><![CDATA[<guid>nested-guid</guid>]]></description>
  <guid isPermaLink="false">guided-guid</guid>
  <pubDate>Mon, 01 Apr 2024 12:00:00 +0000</pubDate>
</item>
XML);
        $itemWithoutGuid = $this->itemFromXml(<<<'XML'
<item>
  <title>Unguided Item</title>
  <description>Unguided description.</description>
  <pubDate>Mon, 01 Apr 2024 12:00:00 +0000</pubDate>
</item>
XML);

        $updatedWithGuid = $itemWithGuid->withHashedGuid();
        $updatedWithoutGuid = $itemWithoutGuid->withHashedGuid();

        self::assertSame(hash('sha256', 'guided-guid'), $updatedWithGuid->fieldValue('guid'));
        self::assertStringContainsString(
            '<guid isPermaLink="false">' . hash('sha256', 'guided-guid') . '</guid>',
            $updatedWithGuid->xml(),
        );
        self::assertSame('<guid>nested-guid</guid>', $updatedWithGuid->fieldValue('description'));

        self::assertSame(
            hash('sha256', 'Unguided ItemUnguided description.'),
            $updatedWithoutGuid->fieldValue('guid'),
        );
        self::assertStringContainsString(
            '<guid>' . hash('sha256', 'Unguided ItemUnguided description.') . '</guid>',
            $updatedWithoutGuid->xml(),
        );
    }

    public function testPrependedTitleUpdatesDirectTitleAndItunesTitleWithoutTouchingOtherTitleLikeNodes(): void
    {
        $item = $this->itemFromXml(<<<'XML'
<item xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:media="http://search.yahoo.com/mrss/">
  <title>Original Title</title>
  <itunes:title>Player Title</itunes:title>
  <media:title>Media Title</media:title>
  <description>Original description.</description>
  <content:encoded><![CDATA[<title>Nested Title</title>]]></content:encoded>
  <pubDate>Mon, 01 Apr 2024 12:00:00 +0000</pubDate>
</item>
XML);

        $updated = $item
            ->withPrependedTitle('Prefix: ')
            ->withPublishedAt(new DateTimeImmutable('2024-04-02T12:00:00+00:00'));

        self::assertSame('Prefix: Original Title', $updated->fieldValue('title'));
        self::assertSame('Prefix: Player Title', $updated->fieldValue('itunes:title'));
        self::assertSame('Media Title', $updated->fieldValue('media:title'));
        self::assertSame('Tue, 02 Apr 2024 12:00:00 +0000', $updated->fieldValue('pubDate'));
        self::assertSame('<title>Nested Title</title>', $updated->fieldValue('content:encoded'));
        self::assertStringContainsString('<title>Prefix: Original Title</title>', $updated->xml());
        self::assertStringContainsString('<itunes:title>Prefix: Player Title</itunes:title>', $updated->xml());
        self::assertStringContainsString('<media:title>Media Title</media:title>', $updated->xml());
        self::assertStringContainsString(
            '<content:encoded><![CDATA[<title>Nested Title</title>]]></content:encoded>',
            $updated->xml(),
        );
    }

    public function testPrependedTitleStillAppliesWhenOnlyItunesTitleExists(): void
    {
        $item = $this->itemFromXml(<<<'XML'
<item xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
  <itunes:title>Player Title</itunes:title>
  <description>Original description.</description>
  <pubDate>Mon, 01 Apr 2024 12:00:00 +0000</pubDate>
</item>
XML);

        $updated = $item->withPrependedTitle('Prefix: ');

        self::assertNull($updated->fieldValue('title'));
        self::assertSame('Prefix: Player Title', $updated->fieldValue('itunes:title'));
        self::assertStringContainsString('<itunes:title>Prefix: Player Title</itunes:title>', $updated->xml());
    }

    private function itemFromXml(string $xml): FeedItem
    {
        $item = FeedItem::fromXmlFragment($xml);

        self::assertNotNull($item);

        return $item;
    }
}
