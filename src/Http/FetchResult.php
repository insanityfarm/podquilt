<?php

declare(strict_types=1);

namespace Podquilt\Http;

/**
 * Captures the response status and body from a feed fetch attempt.
 */
final readonly class FetchResult
{
    public function __construct(
        public int $statusCode,
        public string $content,
        public ?string $transportError = null,
    ) {
    }
}
