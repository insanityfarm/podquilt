<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Podquilt\Config\AppConfig;
use Podquilt\Config\ChannelConfig;
use Podquilt\Config\FeedSourceConfig;
use Podquilt\Config\HttpConfig;
use Podquilt\Config\LogConfig;
use Podquilt\Feed\FileFeedSourceProcessor;
use Podquilt\Feed\PodquiltService;
use Podquilt\Feed\RemoteFeedSourceProcessor;
use Podquilt\Feed\RssRenderer;
use Podquilt\Http\FetchResult;
use Podquilt\Logging\LogLevel;
use Podquilt\Runtime\RequestContext;
use Podquilt\Runtime\UriFactory;
use Podquilt\Tests\Support\BufferedLogger;
use Podquilt\Tests\Support\FixtureFeedFetcher;
use Podquilt\Tests\Support\FixturePath;
use Podquilt\Tests\Support\FrozenClock;

final class PodquiltServiceTest extends TestCase
{
    public function testPreservesFeedOrderInLogsWhilePassingConfiguredConcurrencyLimit(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00'));
        $feedFetcher = new FixtureFeedFetcher(
            responses: [
                'https://example.test/primary.xml' => new FetchResult(
                    404,
                    (string) file_get_contents(FixturePath::for('feeds/primary.xml')),
                ),
                'https://example.test/secondary.xml' => new FetchResult(
                    404,
                    (string) file_get_contents(FixturePath::for('feeds/secondary.xml')),
                ),
            ],
            resultUrlOrder: [
                'https://example.test/secondary.xml',
                'https://example.test/primary.xml',
            ],
        );

        $service = $this->createService($clock, $feedFetcher);
        $logger = new BufferedLogger();

        $service->render(
            new AppConfig(
                channel: new ChannelConfig('Merged Feed', 'https://example.test/podquilt', 'Merged feed description.'),
                feeds: [
                    new FeedSourceConfig(
                        url: 'https://example.test/primary.xml',
                        prepend: null,
                        itemLimit: 1,
                        itemMaxAgeDays: 14,
                        filterPatterns: [],
                        replay: null,
                        disabled: null,
                    ),
                    new FeedSourceConfig(
                        url: 'https://example.test/secondary.xml',
                        prepend: null,
                        itemLimit: 1,
                        itemMaxAgeDays: 14,
                        filterPatterns: [],
                        replay: null,
                        disabled: null,
                    ),
                ],
                files: [],
                logs: LogConfig::defaults(),
                http: new HttpConfig(2),
            ),
            $this->requestContext(),
            $logger,
        );

        self::assertSame(2, $feedFetcher->lastMaxConcurrentRequests());
        self::assertSame(
            [
                'Request for https://example.test/primary.xml returned code 404.',
                'Beginning to parse feed: https://example.test/primary.xml',
                'Parsing complete. 1 items were retrieved from feed.',
                'Request for https://example.test/secondary.xml returned code 404.',
                'Beginning to parse feed: https://example.test/secondary.xml',
                'Parsing complete. 1 items were retrieved from feed.',
            ],
            array_map(
                static fn (array $entry): string => $entry['message'],
                $logger->entries(),
            ),
        );
    }

    public function testContinuesRenderingWhenOneRemoteFeedFails(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00'));
        $feedFetcher = new FixtureFeedFetcher([
            'https://example.test/primary.xml' => new FetchResult(
                200,
                (string) file_get_contents(FixturePath::for('feeds/primary.xml')),
            ),
            'https://example.test/secondary.xml' => new FetchResult(
                0,
                '',
                'timed out',
            ),
        ]);

        $service = $this->createService($clock, $feedFetcher);
        $logger = new BufferedLogger();
        $xml = $service->render(
            new AppConfig(
                channel: new ChannelConfig('Merged Feed', 'https://example.test/podquilt', 'Merged feed description.'),
                feeds: [
                    new FeedSourceConfig(
                        url: 'https://example.test/primary.xml',
                        prepend: null,
                        itemLimit: 1,
                        itemMaxAgeDays: 14,
                        filterPatterns: [],
                        replay: null,
                        disabled: null,
                    ),
                    new FeedSourceConfig(
                        url: 'https://example.test/secondary.xml',
                        prepend: null,
                        itemLimit: 1,
                        itemMaxAgeDays: 14,
                        filterPatterns: [],
                        replay: null,
                        disabled: null,
                    ),
                ],
                files: [],
                logs: LogConfig::defaults(),
                http: HttpConfig::defaults(),
            ),
            $this->requestContext(),
            $logger,
        );

        self::assertStringContainsString('<title>Newest Episode</title>', $xml);
        self::assertSame(
            ['Request for https://example.test/secondary.xml failed: timed out'],
            $logger->messagesForLevel(LogLevel::Warning),
        );
    }

    public function testRenderedOutputPrefixesDirectTitleAndItunesTitleWithoutRootNamespaceHoisting(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00'));
        $feedFetcher = new FixtureFeedFetcher([
            'https://example.test/prepend-itunes.xml' => new FetchResult(
                200,
                (string) file_get_contents(FixturePath::for('feeds/prepend-itunes.xml')),
            ),
        ]);

        $service = $this->createService($clock, $feedFetcher);
        $xml = $service->render(
            new AppConfig(
                channel: new ChannelConfig('Merged Feed', 'https://example.test/podquilt', 'Merged feed description.'),
                feeds: [
                    new FeedSourceConfig(
                        url: 'https://example.test/prepend-itunes.xml',
                        prepend: 'Prefix: ',
                        itemLimit: 1,
                        itemMaxAgeDays: 14,
                        filterPatterns: [],
                        replay: null,
                        disabled: null,
                    ),
                ],
                files: [],
                logs: LogConfig::defaults(),
                http: HttpConfig::defaults(),
            ),
            $this->requestContext(),
            new BufferedLogger(),
        );

        self::assertStringContainsString("<rss version=\"2.0\">\n", $xml);
        self::assertStringNotContainsString('<rss version="2.0" xmlns:', $xml);
        self::assertStringContainsString('<title>Prefix: Visible Title</title>', $xml);
        self::assertStringContainsString('<itunes:title>Prefix: Player Title</itunes:title>', $xml);
    }

    public function testLogsSyntheticTransportFailureWhenFetcherOmitsPreparedResult(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00'));
        $feedFetcher = new FixtureFeedFetcher(
            responses: [
                'https://example.test/primary.xml' => new FetchResult(
                    200,
                    (string) file_get_contents(FixturePath::for('feeds/primary.xml')),
                ),
            ],
            omittedResultUrls: ['https://example.test/primary.xml'],
        );

        $service = $this->createService($clock, $feedFetcher);
        $logger = new BufferedLogger();

        $service->render(
            new AppConfig(
                channel: new ChannelConfig('Merged Feed', 'https://example.test/podquilt', 'Merged feed description.'),
                feeds: [
                    new FeedSourceConfig(
                        url: 'https://example.test/primary.xml',
                        prepend: null,
                        itemLimit: 1,
                        itemMaxAgeDays: 14,
                        filterPatterns: [],
                        replay: null,
                        disabled: null,
                    ),
                ],
                files: [],
                logs: LogConfig::defaults(),
                http: HttpConfig::defaults(),
            ),
            $this->requestContext(),
            $logger,
        );

        self::assertSame(
            [
                'Request for https://example.test/primary.xml failed: The HTTP fetcher did not return a result for https://example.test/primary.xml.',
            ],
            $logger->messagesForLevel(LogLevel::Warning),
        );
    }

    private function createService(FrozenClock $clock, FixtureFeedFetcher $feedFetcher): PodquiltService
    {
        return new PodquiltService(
            new RemoteFeedSourceProcessor($clock, new UriFactory()),
            new FileFeedSourceProcessor($clock, new UriFactory()),
            $feedFetcher,
            new RssRenderer(),
        );
    }

    private function requestContext(): RequestContext
    {
        return new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0);
    }
}
