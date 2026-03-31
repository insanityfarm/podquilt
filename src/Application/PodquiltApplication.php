<?php

declare(strict_types=1);

namespace Podquilt\Application;

use Podquilt\Config\ConfigLoader;
use Podquilt\Feed\PodquiltService;
use Podquilt\Logging\LoggerFactory;
use Podquilt\Logging\LogLevel;
use Podquilt\Runtime\RequestContext;

/**
 * Coordinates configuration, logging, and feed rendering for a single request.
 */
final readonly class PodquiltApplication
{
    public function __construct(
        private ConfigLoader $configLoader,
        private LoggerFactory $loggerFactory,
        private PodquiltService $service,
        private string $projectRoot,
    ) {
    }

    public function render(string $configPath, RequestContext $requestContext): string
    {
        $config = $this->configLoader->load($configPath, $requestContext);
        $logger = $this->loggerFactory->create($config->logs, $this->projectRoot);

        $logger->log(
            LogLevel::Info,
            sprintf(
                'Processing new request from `%s` at %s…',
                $requestContext->userAgent,
                $requestContext->remoteAddress,
            ),
        );

        foreach ($config->warnings as $warning) {
            $logger->log(LogLevel::Warning, $warning);
        }

        $xml = $this->service->render($config, $requestContext, $logger);

        $logger->log(
            LogLevel::Info,
            sprintf(
                'Process complete. Total execution time: %dms',
                (int) round((microtime(true) - $requestContext->requestStartedAt) * 1000),
            ),
        );

        return $xml;
    }
}
