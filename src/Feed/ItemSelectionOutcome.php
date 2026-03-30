<?php

declare(strict_types=1);

namespace Podquilt\Feed;

/**
 * Makes item-selection outcomes explicit when a candidate feed item is evaluated.
 */
enum ItemSelectionOutcome: string
{
    case Include = 'include';
    case Exclude = 'exclude';
}
