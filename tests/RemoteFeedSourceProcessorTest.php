<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Podquilt\Config\FeedSourceConfig;
use Podquilt\Config\ReplayConfig;
use Podquilt\Feed\RemoteFeedSourceProcessor;
use Podquilt\Http\FetchResult;
use Podquilt\Logging\LogLevel;
use Podquilt\Runtime\RequestContext;
use Podquilt\Runtime\UriFactory;
use Podquilt\Tests\Support\BufferedLogger;
use Podquilt\Tests\Support\FixturePath;
use Podquilt\Tests\Support\FrozenClock;

final class RemoteFeedSourceProcessorTest extends TestCase
{
    public function testCollectItemsFromFetchResultAppliesLimitsFiltersPrependAndDateRules(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $source = new FeedSourceConfig(
            url: 'https://example.test/primary.xml',
            prepend: 'Primary: ',
            itemLimit: 3,
            itemMaxAgeDays: 14,
            filterPatterns: ['itunes:episodeType' => '^((?!trailer).)*$'],
            replay: null,
            disabled: null,
        );
        $preparedFeed = $processor->prepareFeed(0, $source, $this->requestContext());
        $items = $processor->collectItemsFromFetchResult(
            $preparedFeed,
            new FetchResult(
                200,
                (string) file_get_contents(FixturePath::for('feeds/primary.xml')),
            ),
        );
        $logger = $this->flushLogs($preparedFeed);

        self::assertCount(2, $items);
        self::assertSame('Primary: Newest Episode', $items[0]->fieldValue('title'));
        self::assertSame(hash('sha256', 'newest-guid'), $items[0]->fieldValue('guid'));
        self::assertSame(
            hash('sha256', 'Primary: Missing Guid EpisodeMissing guid description.'),
            $items[1]->fieldValue('guid'),
        );
        self::assertSame([], $logger->messagesForLevel(LogLevel::Warning));
    }

    public function testCollectItemsFromFetchResultPrependsDirectTitleAndItunesTitleWhenPresent(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $source = new FeedSourceConfig(
            url: 'https://example.test/prepend-itunes.xml',
            prepend: 'Prefix: ',
            itemLimit: 1,
            itemMaxAgeDays: 14,
            filterPatterns: [],
            replay: null,
            disabled: null,
        );
        $preparedFeed = $processor->prepareFeed(0, $source, $this->requestContext());
        $items = $processor->collectItemsFromFetchResult(
            $preparedFeed,
            new FetchResult(
                200,
                (string) file_get_contents(FixturePath::for('feeds/prepend-itunes.xml')),
            ),
        );
        $logger = $this->flushLogs($preparedFeed);

        self::assertCount(1, $items);
        self::assertSame('Prefix: Visible Title', $items[0]->fieldValue('title'));
        self::assertSame('Prefix: Player Title', $items[0]->fieldValue('itunes:title'));
        self::assertSame([], $logger->messagesForLevel(LogLevel::Warning));
    }

    public function testLogsInvalidUrlsWithoutAttemptingNetworkFetch(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $preparedFeed = $processor->prepareFeed(
            0,
            new FeedSourceConfig(
                url: 'not-a-url',
                prepend: null,
                itemLimit: null,
                itemMaxAgeDays: null,
                filterPatterns: [],
                replay: null,
                disabled: null,
            ),
            $this->requestContext(),
        );
        $logger = $this->flushLogs($preparedFeed);

        self::assertNull($preparedFeed->request);
        self::assertSame(
            ['Invalid URL for feed: not-a-url'],
            $logger->messagesForLevel(LogLevel::Warning),
        );
    }

    public function testLogsNonSuccessResponsesAndStillParsesBodies(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $preparedFeed = $processor->prepareFeed(
            0,
            new FeedSourceConfig(
                url: 'https://example.test/secondary.xml',
                prepend: null,
                itemLimit: 1,
                itemMaxAgeDays: 14,
                filterPatterns: [],
                replay: null,
                disabled: null,
            ),
            $this->requestContext(),
        );
        $items = $processor->collectItemsFromFetchResult(
            $preparedFeed,
            new FetchResult(
                404,
                (string) file_get_contents(FixturePath::for('feeds/secondary.xml')),
            ),
        );
        $logger = $this->flushLogs($preparedFeed);

        self::assertCount(1, $items);
        self::assertSame(
            ['Request for https://example.test/secondary.xml returned code 404.'],
            $logger->messagesForLevel(LogLevel::Warning),
        );
    }

    public function testPublicationWindowIncludesBothBoundariesAndStillExcludesOldAndFutureItems(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $preparedFeed = $processor->prepareFeed(
            0,
            new FeedSourceConfig(
                url: 'https://example.test/window.xml',
                prepend: null,
                itemLimit: 10,
                itemMaxAgeDays: 14,
                filterPatterns: [],
                replay: null,
                disabled: null,
            ),
            $this->requestContext(),
        );
        $items = $processor->collectItemsFromFetchResult(
            $preparedFeed,
            new FetchResult(
                200,
                <<<'XML'
<rss version="2.0">
  <channel>
    <item>
      <title>At Cutoff</title>
      <pubDate>Mon, 18 Mar 2024 12:00:00 +0000</pubDate>
    </item>
    <item>
      <title>At Now</title>
      <pubDate>Mon, 01 Apr 2024 12:00:00 +0000</pubDate>
    </item>
    <item>
      <title>Too Old</title>
      <pubDate>Sun, 17 Mar 2024 12:00:00 +0000</pubDate>
    </item>
    <item>
      <title>Future</title>
      <pubDate>Tue, 02 Apr 2024 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML,
            ),
        );

        self::assertCount(2, $items);
        self::assertSame('At Cutoff', $items[0]->fieldValue('title'));
        self::assertSame('At Now', $items[1]->fieldValue('title'));
    }

    public function testReplaySchedulingPreservesExistingSelectionSemantics(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-02-06T00:00:00+00:00')),
            new UriFactory(),
        );

        $preparedFeed = $processor->prepareFeed(
            0,
            new FeedSourceConfig(
                url: 'https://example.test/replay.xml',
                prepend: null,
                itemLimit: 2,
                itemMaxAgeDays: 14,
                filterPatterns: [],
                replay: new ReplayConfig(
                    schedule: '0 0 * * *',
                    replayStartDate: 'Thu, 01 Feb 2024 00:00:00 +0000',
                    originalStartDate: 'Thu, 01 Feb 2024 00:00:00 +0000',
                ),
                disabled: null,
            ),
            $this->requestContext(),
        );
        $items = $processor->collectItemsFromFetchResult(
            $preparedFeed,
            new FetchResult(
                200,
                (string) file_get_contents(FixturePath::for('feeds/replay.xml')),
            ),
        );

        self::assertCount(2, $items);
        self::assertSame('Sat, 03 Feb 2024 00:00:00 +0000', $items[0]->fieldValue('pubDate'));
        self::assertSame('Sun, 04 Feb 2024 00:00:00 +0000', $items[1]->fieldValue('pubDate'));
    }

    private function requestContext(): RequestContext
    {
        return new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0);
    }

    private function flushLogs(\Podquilt\Feed\PreparedRemoteFeed $preparedFeed): BufferedLogger
    {
        $logger = new BufferedLogger();
        $preparedFeed->flushLogsTo($logger);

        return $logger;
    }
}
