<?php

declare(strict_types=1);

namespace Podquilt\Config;

use JsonException;
use Podquilt\Logging\LogLevel;
use Podquilt\Runtime\RequestContext;

/**
 * Loads config.json into typed immutable DTOs.
 */
final readonly class ConfigLoader
{
    public function load(string $path, RequestContext $requestContext): AppConfig
    {
        if (!file_exists($path)) {
            throw new ConfigException('Unable to find config.json. Please create a configuration file and try again.');
        }

        try {
            $payload = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );
        } catch (JsonException) {
            throw new ConfigException('Unable to read config.json. Please check that is is properly formatted and try again.');
        }

        if (!is_array($payload)) {
            throw new ConfigException('Unable to read config.json. Please check that is is properly formatted and try again.');
        }

        $warnings = [];

        return new AppConfig(
            channel: $this->loadChannel($payload['channel'] ?? null, $requestContext),
            feeds: $this->loadFeeds($payload['feeds'] ?? []),
            files: $this->loadFiles($payload['files'] ?? []),
            logs: $this->loadLogs($payload['logs'] ?? null),
            http: $this->loadHttp($payload['http'] ?? null, $warnings),
            warnings: $warnings,
        );
    }

    private function loadChannel(mixed $value, RequestContext $requestContext): ChannelConfig
    {
        $channel = is_array($value) ? $value : [];

        return new ChannelConfig(
            title: $this->stringOrDefault($channel['title'] ?? null, 'Podquilt'),
            link: $this->stringOrDefault($channel['link'] ?? null, $requestContext->defaultChannelLink()),
            description: $this->stringOrDefault($channel['description'] ?? null, 'Your description here.'),
        );
    }

    /**
     * @return list<FeedSourceConfig>
     */
    private function loadFeeds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $feeds = [];

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $feeds[] = new FeedSourceConfig(
                url: $this->stringOrDefault($entry['url'] ?? null, ''),
                prepend: $this->nullableString($entry['prepend'] ?? null),
                itemLimit: $this->nullableInt($entry['item_limit'] ?? null),
                itemMaxAgeDays: $this->nullableInt($entry['item_max_age'] ?? null),
                filterPatterns: $this->loadFilterPatterns($entry['filter'] ?? null),
                replay: $this->loadReplay($entry['replay'] ?? null),
                disabled: $entry['disabled'] ?? null,
            );
        }

        return $feeds;
    }

    /**
     * @return list<FileSourceConfig>
     */
    private function loadFiles(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $files = [];

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $files[] = new FileSourceConfig(
                url: $this->stringOrDefault($entry['url'] ?? null, ''),
                title: $this->stringOrDefault($entry['title'] ?? null, ''),
                pubDate: $this->stringOrDefault($entry['pubDate'] ?? null, ''),
                description: $this->stringOrDefault($entry['description'] ?? null, ''),
                disabled: $entry['disabled'] ?? null,
            );
        }

        return $files;
    }

    private function loadLogs(mixed $value): LogConfig
    {
        $logs = is_array($value) ? $value : [];
        $defaults = LogConfig::defaults();
        $configuredLevel = $logs['level'] ?? null;

        return new LogConfig(
            enabled: array_key_exists('enabled', $logs) ? (bool) $logs['enabled'] : $defaults->enabled,
            level: is_scalar($configuredLevel) ? LogLevel::fromInt((int) $configuredLevel) : $defaults->level,
            path: $this->stringOrDefault($logs['path'] ?? null, $defaults->path),
        );
    }

    /**
     * @param list<string> $warnings
     */
    private function loadHttp(mixed $value, array &$warnings): HttpConfig
    {
        $http = is_array($value) ? $value : [];
        $defaults = HttpConfig::defaults();
        $configuredConcurrency = $http['max_concurrent_requests'] ?? null;

        if ($configuredConcurrency === null) {
            return $defaults;
        }

        if (!is_scalar($configuredConcurrency)) {
            $warnings[] = sprintf(
                'Invalid http.max_concurrent_requests value. Using default of %d.',
                HttpConfig::DEFAULT_MAX_CONCURRENT_REQUESTS,
            );

            return $defaults;
        }

        $maxConcurrentRequests = (int) $configuredConcurrency;

        if ($maxConcurrentRequests <= 0) {
            $warnings[] = sprintf(
                'Invalid http.max_concurrent_requests value. Using default of %d.',
                HttpConfig::DEFAULT_MAX_CONCURRENT_REQUESTS,
            );

            return $defaults;
        }

        return new HttpConfig($maxConcurrentRequests);
    }

    /**
     * @return array<string, string>
     */
    private function loadFilterPatterns(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $patterns = [];

        foreach ($value as $node => $pattern) {
            if (!is_string($node) || !is_scalar($pattern)) {
                continue;
            }

            $patterns[$node] = (string) $pattern;
        }

        return $patterns;
    }

    private function loadReplay(mixed $value): ?ReplayConfig
    {
        if (!is_array($value)) {
            return null;
        }

        return new ReplayConfig(
            schedule: $this->nullableString($value['schedule'] ?? null),
            replayStartDate: $this->nullableString($value['replayStartDate'] ?? null),
            originalStartDate: $this->nullableString($value['originalStartDate'] ?? null),
        );
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (!is_int($value) && !is_string($value) && !is_float($value)) {
            return null;
        }

        return (int) $value;
    }
}
