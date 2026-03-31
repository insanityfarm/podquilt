<?php

declare(strict_types=1);

namespace Podquilt\Runtime;

/**
 * Captures the stable request metadata that the app uses for defaults and logging.
 */
final readonly class RequestContext
{
    public function __construct(
        public string $userAgent,
        public string $remoteAddress,
        public string $serverName,
        public string $requestUri,
        public float $requestStartedAt,
    ) {
    }

    /**
     * Builds a request context from the active PHP globals while keeping CLI execution predictable.
     */
    public static function fromGlobals(): self
    {
        return new self(
            userAgent: self::stringFromServer('HTTP_USER_AGENT', 'Podquilt'),
            remoteAddress: self::stringFromServer('REMOTE_ADDR', '127.0.0.1'),
            serverName: self::stringFromServer('SERVER_NAME', 'localhost'),
            requestUri: self::stringFromServer('REQUEST_URI', '/'),
            requestStartedAt: self::floatFromServer('REQUEST_TIME_FLOAT', microtime(true)),
        );
    }

    /**
     * Builds the default channel link from the server name and request URI.
     */
    public function defaultChannelLink(): string
    {
        return $this->serverName . $this->requestUri;
    }

    private static function stringFromServer(string $key, string $default): string
    {
        $value = $_SERVER[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    private static function floatFromServer(string $key, float $default): float
    {
        $value = $_SERVER[$key] ?? null;

        return is_int($value) || is_float($value) || is_string($value) ? (float) $value : $default;
    }
}
