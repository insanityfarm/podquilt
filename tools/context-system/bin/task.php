<?php

declare(strict_types=1);

use Podquilt\Tools\ContextSystem\ContextSystem;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$system = new ContextSystem(dirname(__DIR__, 3));
/** @var list<string> $argvValues */
$argvValues = $_SERVER['argv'] ?? [];
$arguments = ContextSystem::parseArgs(array_slice($argvValues, 1));
$task = trim(implode(' ', $arguments['positionals']));

if ($task === '') {
    throw new RuntimeException('Usage: composer context:task -- "<task>" [--allow-locked=id1,id2]');
}

$allowLocked = array_values(
    array_filter(
        array_map(
            static fn (string $entry): string => trim($entry),
            explode(',', $arguments['flags']['allow-locked'] ?? ''),
        ),
        static fn (string $entry): bool => $entry !== '',
    ),
);

$contextData = $system->loadContextData();
$taskPacket = $system->buildTaskPacket($contextData, $task, $allowLocked);
$system->writeActiveTask($contextData, $taskPacket);

echo $system->formatTaskSummary($taskPacket) . PHP_EOL;
