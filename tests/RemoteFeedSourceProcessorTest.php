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
use Podquilt\Tests\Support\FixtureFeedFetcher;
use Podquilt\Tests\Support\FixturePath;
use Podquilt\Tests\Support\FrozenClock;

final class RemoteFeedSourceProcessorTest extends TestCase
{
    public function testCollectItemsAppliesLimitsFiltersPrependAndDateRules(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
            new FixtureFeedFetcher([
                'https://example.test/primary.xml' => new FetchResult(
                    200,
                    (string) file_get_contents(FixturePath::for('feeds/primary.xml')),
                ),
            ]),
        );

        $logger = new BufferedLogger();
        $items = $processor->collectItems(
            new FeedSourceConfig(
                url: 'https://example.test/primary.xml',
                prepend: 'Primary: ',
                itemLimit: 3,
                itemMaxAgeDays: 14,
                filterPatterns: ['itunes:episodeType' => '^((?!trailer).)*$'],
                replay: null,
                disabled: null,
            ),
            $logger,
            $this->requestContext(),
        );

        self::assertCount(2, $items);
        self::assertSame('Primary: Newest Episode', $items[0]->fieldValue('title'));
        self::assertSame(hash('sha256', 'newest-guid'), $items[0]->fieldValue('guid'));
        self::assertSame(
            hash('sha256', 'Primary: Missing Guid EpisodeMissing guid description.'),
            $items[1]->fieldValue('guid'),
        );
        self::assertSame([], $logger->messagesForLevel(LogLevel::Warning));
    }

    public function testLogsInvalidUrlsWithoutAttemptingNetworkFetch(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
            new FixtureFeedFetcher([]),
        );

        $logger = new BufferedLogger();
        $items = $processor->collectItems(
            new FeedSourceConfig(
                url: 'not-a-url',
                prepend: null,
                itemLimit: null,
                itemMaxAgeDays: null,
                filterPatterns: [],
                replay: null,
                disabled: null,
            ),
            $logger,
            $this->requestContext(),
        );

        self::assertSame([], $items);
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
            new FixtureFeedFetcher([
                'https://example.test/secondary.xml' => new FetchResult(
                    404,
                    (string) file_get_contents(FixturePath::for('feeds/secondary.xml')),
                ),
            ]),
        );

        $logger = new BufferedLogger();
        $items = $processor->collectItems(
            new FeedSourceConfig(
                url: 'https://example.test/secondary.xml',
                prepend: null,
                itemLimit: 1,
                itemMaxAgeDays: 14,
                filterPatterns: [],
                replay: null,
                disabled: null,
            ),
            $logger,
            $this->requestContext(),
        );

        self::assertCount(1, $items);
        self::assertSame(
            ['Request for https://example.test/secondary.xml returned code 404.'],
            $logger->messagesForLevel(LogLevel::Warning),
        );
    }

    public function testReplaySchedulingPreservesExistingSelectionSemantics(): void
    {
        $processor = new RemoteFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-02-06T00:00:00+00:00')),
            new UriFactory(),
            new FixtureFeedFetcher([
                'https://example.test/replay.xml' => new FetchResult(
                    200,
                    (string) file_get_contents(FixturePath::for('feeds/replay.xml')),
                ),
            ]),
        );

        $logger = new BufferedLogger();
        $items = $processor->collectItems(
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
            $logger,
            $this->requestContext(),
        );

        self::assertCount(2, $items);
        self::assertSame('Sat, 03 Feb 2024 00:00:00 +0000', $items[0]->fieldValue('pubDate'));
        self::assertSame('Sun, 04 Feb 2024 00:00:00 +0000', $items[1]->fieldValue('pubDate'));
    }

    private function requestContext(): RequestContext
    {
        return new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0);
    }
}
