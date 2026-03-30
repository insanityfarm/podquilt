<?php

declare(strict_types=1);

namespace Podquilt\Config;

/**
 * Represents the supported RSS channel metadata keys in the public config schema.
 */
final readonly class ChannelConfig
{
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
    ) {
    }

    /**
     * Returns the ordered channel element map used by the RSS renderer.
     *
     * @return array<string, string>
     */
    public function toElementMap(): array
    {
        return [
            'title' => $this->title,
            'link' => $this->link,
            'description' => $this->description,
        ];
    }
}
