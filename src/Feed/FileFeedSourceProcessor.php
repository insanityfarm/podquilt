<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Podquilt\Config\FileSourceConfig;
use Podquilt\Config\SourceDefaults;
use Podquilt\Logging\LoggerInterface;
use Podquilt\Logging\LogLevel;
use Podquilt\Runtime\ClockInterface;
use Podquilt\Runtime\RequestContext;
use Podquilt\Runtime\UriFactory;

/**
 * Turns config-defined file items into synthetic RSS items that can be merged with remote feeds.
 */
final readonly class FileFeedSourceProcessor
{
    public function __construct(
        private ClockInterface $clock,
        private UriFactory $uriFactory,
    ) {
    }

    /**
     * @return list<FeedItem>
     */
    public function collectItems(
        FileSourceConfig $source,
        LoggerInterface $logger,
        RequestContext $requestContext,
    ): array {
        $uri = $this->uriFactory->parseAbsoluteUri($source->url);

        if ($uri === null) {
            $logger->log(LogLevel::Warning, 'Invalid URL for file source: ' . $source->url);

            return [];
        }

        $publishedAt = DateTimeImmutable::createFromFormat(
            DateTimeInterface::RSS,
            $source->pubDate,
            new DateTimeZone('UTC'),
        );

        if ($publishedAt === false) {
            $logger->log(LogLevel::Error, 'The file source pubDate is an improperly formatted timestamp.');

            return [];
        }

        $publicationCutoff = $this->clock->now()->modify(sprintf('-%d days', SourceDefaults::ITEM_MAX_AGE_DAYS));

        if ($publishedAt <= $publicationCutoff || $publishedAt >= $this->clock->now()) {
            return [];
        }

        return [
            FeedItem::fromSyntheticValues(
                title: $source->title,
                description: $source->description,
                publishedAt: $publishedAt,
                url: $uri->toString(),
            )->withHashedGuid(),
        ];
    }
}
