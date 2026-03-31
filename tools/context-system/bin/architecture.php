<?php

declare(strict_types=1);

use Podquilt\Tools\ContextSystem\ContextSystem;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$system = new ContextSystem(dirname(__DIR__, 3));
$contextData = $system->loadContextData();
$system->checkArchitecture($contextData);

echo "Architecture checks passed.\n";
