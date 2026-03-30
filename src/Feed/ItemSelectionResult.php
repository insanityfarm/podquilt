<?php

declare(strict_types=1);

namespace Podquilt\Feed;

/**
 * Bundles an inclusion decision with the transformed item that should be rendered.
 */
final readonly class ItemSelectionResult
{
    public function __construct(
        public ItemSelectionOutcome $outcome,
        public ?FeedItem $item = null,
    ) {
    }

    public static function include(FeedItem $item): self
    {
        return new self(ItemSelectionOutcome::Include, $item);
    }

    public static function exclude(): self
    {
        return new self(ItemSelectionOutcome::Exclude);
    }
}
