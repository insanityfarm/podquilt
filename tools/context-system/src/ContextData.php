<?php

declare(strict_types=1);

namespace Podquilt\Tools\ContextSystem;

/**
 * @param array<string, mixed> $glossary
 * @param array<string, mixed> $subsystemIndex
 * @param list<array<string, mixed>> $subsystems
 * @param list<string> $decisionPaths
 */
final readonly class ContextData
{
    public function __construct(
        public string $repoRoot,
        public array $glossary,
        public array $subsystemIndex,
        public array $subsystems,
        public array $decisionPaths,
    ) {
    }
}
