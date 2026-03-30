<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Podquilt\Config\FileSourceConfig;
use Podquilt\Feed\FileFeedSourceProcessor;
use Podquilt\Runtime\RequestContext;
use Podquilt\Runtime\UriFactory;
use Podquilt\Tests\Support\BufferedLogger;
use Podquilt\Tests\Support\FrozenClock;

final class FileFeedSourceProcessorTest extends TestCase
{
    public function testCreatesSyntheticItemsThatRespectDateWindow(): void
    {
        $processor = new FileFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $logger = new BufferedLogger();
        $items = $processor->collectItems(
            new FileSourceConfig(
                url: 'https://example.test/audio.mp3',
                title: 'Local Episode',
                pubDate: 'Sat, 30 Mar 2024 12:00:00 +0000',
                description: 'Local file-backed episode.',
                disabled: null,
            ),
            $logger,
            new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0),
        );

        self::assertCount(1, $items);
        self::assertSame('Local Episode', $items[0]->fieldValue('title'));
        self::assertNotNull($items[0]->fieldValue('guid'));
    }

    public function testSkipsFutureAndOldFileItems(): void
    {
        $processor = new FileFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $logger = new BufferedLogger();

        self::assertSame([], $processor->collectItems(
            new FileSourceConfig(
                url: 'https://example.test/future.mp3',
                title: 'Future Episode',
                pubDate: 'Tue, 02 Apr 2024 12:00:00 +0000',
                description: 'Future file-backed episode.',
                disabled: null,
            ),
            $logger,
            new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0),
        ));

        self::assertSame([], $processor->collectItems(
            new FileSourceConfig(
                url: 'https://example.test/old.mp3',
                title: 'Old Episode',
                pubDate: 'Fri, 01 Mar 2024 12:00:00 +0000',
                description: 'Old file-backed episode.',
                disabled: null,
            ),
            $logger,
            new RequestContext('PHPUnit', '127.0.0.1', 'example.test', '/feed', 1000.0),
        ));
    }
}
