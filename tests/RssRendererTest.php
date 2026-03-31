<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use PHPUnit\Framework\TestCase;
use Podquilt\Config\ChannelConfig;
use Podquilt\Feed\FeedItem;
use Podquilt\Feed\RssRenderer;

final class RssRendererTest extends TestCase
{
    public function testRenderKeepsRootAttributesLimitedToVersionWhilePreservingNamespacedItems(): void
    {
        $item = FeedItem::fromXmlFragment(<<<'XML'
<item xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
  <title>Episode ’ One</title>
  <guid>episode-guid</guid>
  <pubDate>Mon, 01 Apr 2024 12:00:00 +0000</pubDate>
  <itunes:episodeType>full</itunes:episodeType>
</item>
XML);

        self::assertNotNull($item);

        $xml = (new RssRenderer())->render(
            new ChannelConfig(
                title: 'Titlé',
                link: 'https://example.test/feed?a=1&b=2',
                description: 'Desc — line',
            ),
            [$item],
        );

        self::assertSame(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Titlé</title>
    <link>https://example.test/feed?a=1&amp;b=2</link>
    <description>Desc — line</description>
    <item xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
  <title>Episode ’ One</title>
  <guid>episode-guid</guid>
  <pubDate>Mon, 01 Apr 2024 12:00:00 +0000</pubDate>
  <itunes:episodeType>full</itunes:episodeType>
</item>
  </channel>
</rss>
XML . "\n",
            $xml,
        );
        self::assertStringContainsString("<rss version=\"2.0\">\n", $xml);
        self::assertStringNotContainsString('<rss version="2.0" ', $xml);
        self::assertStringContainsString(
            '<item xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">',
            $xml,
        );
    }
}
