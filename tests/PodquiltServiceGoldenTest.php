<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Podquilt\Config\AppConfig;
use Podquilt\Config\ChannelConfig;
use Podquilt\Config\FeedSourceConfig;
use Podquilt\Config\FileSourceConfig;
use Podquilt\Config\LogConfig;
use Podquilt\Feed\FileFeedSourceProcessor;
use Podquilt\Feed\PodquiltService;
use Podquilt\Feed\RemoteFeedSourceProcessor;
use Podquilt\Feed\RssRenderer;
use Podquilt\Http\FetchResult;
use Podquilt\Runtime\RequestContext;
use Podquilt\Runtime\UriFactory;
use Podquilt\Tests\Support\BufferedLogger;
use Podquilt\Tests\Support\FixtureFeedFetcher;
use Podquilt\Tests\Support\FixturePath;
use Podquilt\Tests\Support\FrozenClock;

final class PodquiltServiceGoldenTest extends TestCase
{
    public function testRenderedFeedMatchesGoldenSnapshot(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00'));
        $service = new PodquiltService(
            new RemoteFeedSourceProcessor(
                $clock,
                new UriFactory(),
                new FixtureFeedFetcher([
                    'https://example.test/primary.xml' => new FetchResult(
                        200,
                        (string) file_get_contents(FixturePath::for('feeds/primary.xml')),
                    ),
                    'https://example.test/secondary.xml' => new FetchResult(
                        200,
                        (string) file_get_contents(FixturePath::for('feeds/secondary.xml')),
                    ),
                ]),
            ),
            new FileFeedSourceProcessor($clock, new UriFactory()),
            new RssRenderer(),
        );

        $config = new AppConfig(
            channel: new ChannelConfig(
                title: 'Merged Feed',
                link: 'https://example.test/podquilt',
                description: 'Fixture-generated merged feed.',
            ),
            feeds: [
                new FeedSourceConfig(
                    url: 'https://example.test/primary.xml',
                    prepend: 'Primary: ',
                    itemLimit: 3,
                    itemMaxAgeDays: 14,
                    filterPatterns: ['itunes:episodeType' => '^((?!trailer).)*$'],
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
            files: [
                new FileSourceConfig(
                    url: 'https://example.test/audio.mp3',
                    title: 'Local Episode',
                    pubDate: 'Sat, 30 Mar 2024 12:00:00 +0000',
                    description: 'Local file-backed episode.',
                    disabled: null,
                ),
            ],
            logs: LogConfig::defaults(),
        );

        $xml = $service->render(
            $config,
            new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0),
            new BufferedLogger(),
        );

        self::assertSame(
            (string) file_get_contents(FixturePath::for('expected/aggregated-feed.xml')),
            $xml,
        );
    }
}
