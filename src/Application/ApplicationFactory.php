<?php

declare(strict_types=1);

namespace Podquilt\Application;

use Podquilt\Config\ConfigLoader;
use Podquilt\Feed\FileFeedSourceProcessor;
use Podquilt\Feed\PodquiltService;
use Podquilt\Feed\RemoteFeedSourceProcessor;
use Podquilt\Feed\RssRenderer;
use Podquilt\Http\SymfonyFeedFetcher;
use Podquilt\Logging\LoggerFactory;
use Podquilt\Runtime\SystemClock;
use Podquilt\Runtime\UriFactory;

/**
 * Builds the production dependency graph used by the HTTP entrypoint.
 */
final readonly class ApplicationFactory
{
    public static function create(string $projectRoot): PodquiltApplication
    {
        $clock = new SystemClock();
        $uriFactory = new UriFactory();
        $feedFetcher = SymfonyFeedFetcher::createDefault();
        $remoteProcessor = new RemoteFeedSourceProcessor($clock, $uriFactory, $feedFetcher);
        $fileProcessor = new FileFeedSourceProcessor($clock, $uriFactory);

        return new PodquiltApplication(
            configLoader: new ConfigLoader(),
            loggerFactory: new LoggerFactory(),
            service: new PodquiltService(
                remoteProcessor: $remoteProcessor,
                fileProcessor: $fileProcessor,
                renderer: new RssRenderer(),
            ),
            projectRoot: $projectRoot,
        );
    }
}
