<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Houses the historical Podquilt defaults that still shape feed selection behavior.
 */
final class SourceDefaults
{
    public const ITEM_LIMIT = 10;
    public const ITEM_MAX_AGE_DAYS = 14;

    private function __construct()
    {
    }
}
