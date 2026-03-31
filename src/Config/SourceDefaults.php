<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Defines the default feed selection limits used by Podquilt.
 */
final class SourceDefaults
{
    public const ITEM_LIMIT = 10;
    public const ITEM_MAX_AGE_DAYS = 14;

    private function __construct()
    {
    }
}
