<?php

declare(strict_types=1);

use Podquilt\Application\ApplicationFactory;
use Podquilt\Runtime\RequestContext;

require __DIR__ . '/vendor/autoload.php';

$requestContext = RequestContext::fromGlobals();
$application = ApplicationFactory::create(__DIR__);

try {
    header('Content-Type: application/rss+xml; charset=utf-8');
    header('Content-Disposition: inline');
    header('Cache-Control: no-cache');

    echo $application->render(__DIR__ . '/config.json', $requestContext);
} catch (Throwable $throwable) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    echo '<pre>' . htmlspecialchars((string) $throwable, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
}
