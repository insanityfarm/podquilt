<?php

declare(strict_types=1);

namespace Podquilt\Feed;

use Podquilt\Config\AppConfig;
use Podquilt\Logging\LoggerInterface;
use Podquilt\Runtime\RequestContext;

/**
 * Orchestrates all configured sources and renders the final aggregated RSS feed.
 */
final readonly class PodquiltService
{
    public function __construct(
        private RemoteFeedSourceProcessor $remoteProcessor,
        private FileFeedSourceProcessor $fileProcessor,
        private RssRenderer $renderer,
    ) {
    }

    public function render(
        AppConfig $config,
        RequestContext $requestContext,
        LoggerInterface $logger,
    ): string {
        $items = [];

        foreach ($config->feeds as $source) {
            if (!$source->isEnabled()) {
                continue;
            }

            $items = [
                ...$items,
                ...$this->remoteProcessor->collectItems($source, $logger, $requestContext),
            ];
        }

        foreach ($config->files as $source) {
            if (!$source->isEnabled()) {
                continue;
            }

            $items = [
                ...$items,
                ...$this->fileProcessor->collectItems($source, $logger, $requestContext),
            ];
        }

        usort(
            $items,
            static fn (FeedItem $left, FeedItem $right): int => $right->publishedAt <=> $left->publishedAt,
        );

        return $this->renderer->render($config->channel, $items);
    }
}
