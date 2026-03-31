<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Podquilt\Config\FileSourceConfig;
use Podquilt\Feed\FileFeedSourceProcessor;
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
        );

        self::assertCount(1, $items);
        self::assertSame('Local Episode', $items[0]->fieldValue('title'));
        self::assertNotNull($items[0]->fieldValue('guid'));
    }

    public function testIncludesItemsAtPublicationWindowBoundaries(): void
    {
        $processor = new FileFeedSourceProcessor(
            new FrozenClock(new DateTimeImmutable('2024-04-01T12:00:00+00:00')),
            new UriFactory(),
        );

        $logger = new BufferedLogger();
        $cutoffItems = $processor->collectItems(
            new FileSourceConfig(
                url: 'https://example.test/cutoff.mp3',
                title: 'Cutoff Episode',
                pubDate: 'Mon, 18 Mar 2024 12:00:00 +0000',
                description: 'Exactly on the oldest allowed boundary.',
                disabled: null,
            ),
            $logger,
        );
        $currentItems = $processor->collectItems(
            new FileSourceConfig(
                url: 'https://example.test/current.mp3',
                title: 'Current Episode',
                pubDate: 'Mon, 01 Apr 2024 12:00:00 +0000',
                description: 'Exactly on the newest allowed boundary.',
                disabled: null,
            ),
            $logger,
        );

        self::assertCount(1, $cutoffItems);
        self::assertSame('Cutoff Episode', $cutoffItems[0]->fieldValue('title'));
        self::assertCount(1, $currentItems);
        self::assertSame('Current Episode', $currentItems[0]->fieldValue('title'));
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
        ));
    }
}
