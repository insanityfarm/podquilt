<?php

declare(strict_types=1);

namespace Podquilt\Tests;

use PHPUnit\Framework\TestCase;
use Podquilt\Tools\ContextSystem\ContextSystem;

final class ContextSystemTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $tempRoot) {
            $this->removeDirectory($tempRoot);
        }

        $this->tempRoots = [];
    }

    public function testBuildsGeneratedGlossaryOutputAndDeterministicTaskPackets(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);
        $contextData = $system->loadContextData();
        $changedFiles = $system->writeContextArtifacts($contextData);
        $taskPacket = $system->buildTaskPacket(
            $contextData,
            'config contract authoritative artifact',
            ['config-contract-and-normalization'],
        );
        /** @var array{
         *     matchedSubsystems: list<array{id: string, status: string, score: int, reasons: list<string>}>,
         *     lockedSubsystems: list<string>,
         *     allowLocked: list<string>,
         *     requiredSubsystemRecords: list<string>
         * } $taskPacket
         */

        self::assertSame([], $changedFiles);
        self::assertFileExists($repoRoot . '/glossary/README.md');
        self::assertSame('config-contract-and-normalization', $taskPacket['matchedSubsystems'][0]['id']);
        self::assertContains('config-contract-and-normalization', $taskPacket['lockedSubsystems']);
        self::assertContains('config-contract-and-normalization', $taskPacket['allowLocked']);
        self::assertContains(
            'spec/subsystems/config-contract-and-normalization.json',
            $taskPacket['requiredSubsystemRecords'],
        );
    }

    public function testSkipsGeneratedArtifactsDuringTerminologyChecksAndFlagsDiscouragedProseElsewhere(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);
        $system->writeContextArtifacts($system->loadContextData());

        $system->checkTerminology($system->loadContextData());

        $this->writeFile(
            $repoRoot,
            'docs/context-system/README.md',
            "# Fixture docs\n\nThis " . ('source of ' . 'truth file') . " drifts from the glossary.\n",
        );

        $this->expectExceptionMessage('authoritative artifact');
        $system->checkTerminology($system->loadContextData());
    }

    public function testRejectsBacklogCommentsAndReasonlessPhpstanIgnores(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);

        $this->writeFile(
            $repoRoot,
            'src/Feed/CommentDrift.php',
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace Podquilt\\Feed;\n\n// " . ('TO' . 'DO') . ": remove this drift\n// " . ('@phpstan' . '-ignore-next-line') . "\nfinal class CommentDrift\n{\n}\n",
        );

        $this->expectExceptionMessage('backlog-style comment');
        $system->checkComments($system->loadContextData());
    }

    public function testEnforcesArchitectureRules(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);

        $system->checkArchitecture($system->loadContextData());

        $this->writeFile(
            $repoRoot,
            'src/Feed/BadImport.php',
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace Podquilt\\Feed;\n\nuse Podquilt\\Application\\App;\n\nfinal class BadImport\n{\n    public function __construct(private App \$app)\n    {\n    }\n}\n",
        );

        $this->expectExceptionMessage('arch-001');
        $system->checkArchitecture($system->loadContextData());
    }

    public function testFailsWhenDerivedContextArtifactsAreStale(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);
        $system->writeContextArtifacts($system->loadContextData());

        $this->replaceInFile(
            $repoRoot,
            'glossary/terms.json',
            'current behavior.',
            'current behavior and workflow.',
        );

        $this->expectExceptionMessage('Derived context artifacts are stale');
        $system->checkContextState($system->loadContextData());
    }

    public function testFailsWhenFirstPartyFilesAreUnmapped(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);

        $this->writeFile(
            $repoRoot,
            'src/Unowned.php',
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace Podquilt;\n\nfinal class Unowned\n{\n}\n",
        );

        $this->expectExceptionMessage('Unmapped first-party files detected');
        $system->writeContextArtifacts($system->loadContextData());
    }

    public function testFailsWhenChangedFilesSpillOutsideTheActiveTaskPacket(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);
        $contextData = $system->loadContextData();
        $taskPacket = $system->buildTaskPacket(
            $contextData,
            'config contract authoritative artifact',
            ['config-contract-and-normalization'],
        );

        $system->writeActiveTask($contextData, $taskPacket);
        $this->appendFile(
            $repoRoot,
            'src/Feed/Processor.php',
            "\nfinal class ProcessorTouched\n{\n}\n",
        );
        $system->writeContextArtifacts($system->loadContextData());
        self::assertContains('src/Feed/Processor.php', $this->gitStatus($system));

        $this->expectExceptionMessage('spill outside the active task packet');
        $system->checkContextState($system->loadContextData());
    }

    public function testFailsClosedOnLockedSubsystemEditsWithoutAdrUpdatesAndPassesOnceAdrChangesToo(): void
    {
        $repoRoot = $this->createFixtureRepo();
        $system = new ContextSystem($repoRoot);
        $contextData = $system->loadContextData();
        $taskPacket = $system->buildTaskPacket(
            $contextData,
            'config contract authoritative artifact',
            ['config-contract-and-normalization'],
        );

        $system->writeActiveTask($contextData, $taskPacket);
        $this->appendFile(
            $repoRoot,
            'src/Config/ConfigLoader.php',
            "\nfinal class ConfigLoaderTouched\n{\n}\n",
        );
        $this->replaceInFile(
            $repoRoot,
            'spec/subsystems/config-contract-and-normalization.json',
            'Owns fixture config behavior.',
            'Owns fixture config behavior and drift metadata.',
        );
        $system->writeContextArtifacts($system->loadContextData());
        self::assertContains('src/Config/ConfigLoader.php', $this->gitStatus($system));
        self::assertContains(
            'spec/subsystems/config-contract-and-normalization.json',
            $this->gitStatus($system),
        );

        try {
            $system->checkContextState($system->loadContextData());
            self::fail('Expected a locked-subsystem ADR failure.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('requires an ADR change', $exception->getMessage());
        } finally {
            $this->appendFile(
                $repoRoot,
                'spec/decisions/ADR-0002-locked.md',
                "\nThe locked config seam changed during this test.\n",
            );
        }

        $system->checkContextState($system->loadContextData());
        $reviewPacket = $system->buildReviewPacket($system->loadContextData());
        self::assertStringContainsString('config-001', $reviewPacket['markdown']);
    }

    private function createFixtureRepo(): string
    {
        $repoRoot = sys_get_temp_dir() . '/podquilt-context-' . bin2hex(random_bytes(8));
        mkdir($repoRoot, 0777, true);
        $this->tempRoots[] = $repoRoot;

        $files = [
            'README.md' => "# Fixture repo\n",
            'AGENTS.md' => "# Fixture agents\n",
            '.gitignore' => ".agent-context\nvendor\n",
            'composer.json' => "{\n    \"name\": \"fixture/podquilt\",\n    \"autoload\": {\"psr-4\": {\"Podquilt\\\\\": \"src/\"}}\n}\n",
            'docs/context-system/README.md' => "# Fixture docs\n",
            'glossary/terms.json' => json_encode([
                'version' => 1,
                'terms' => [
                    [
                        'id' => 'authoritative-artifact',
                        'canonical' => 'authoritative artifact',
                        'definition' => 'Checked-in file that defines current behavior.',
                        'kind' => 'governance',
                        'discouraged' => ['source of ' . 'truth file'],
                        'applies_to' => ['docs/context-system/README.md'],
                        'related_terms' => [],
                    ],
                    [
                        'id' => 'config-contract',
                        'canonical' => 'config contract',
                        'definition' => 'Preserved fixture config behavior.',
                        'kind' => 'architecture',
                        'discouraged' => ['config ' . 'shape'],
                        'applies_to' => ['src/Config/ConfigLoader.php'],
                        'related_terms' => ['authoritative-artifact'],
                    ],
                ],
                'examples' => [
                    [
                        'term' => 'authoritative-artifact',
                        'examples' => ['spec/README.md'],
                    ],
                ],
                'usage_rules' => [
                    'Use authoritative artifact for current checked-in workflow or contract files.',
                    'Use config contract for preserved fixture config behavior.',
                ],
                'cross_references' => ['../spec/README.md'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            'spec/README.md' => "# Fixture spec\n",
            'spec/subsystems/index.json' => json_encode([
                'version' => 1,
                'status_vocabulary' => [
                    'locked' => 'Locked subsystem.',
                    'stable' => 'Stable subsystem.',
                    'evolving' => 'Evolving subsystem.',
                ],
                'source_roots' => [
                    'README.md',
                    'AGENTS.md',
                    '.gitignore',
                    'composer.json',
                    'docs',
                    'glossary',
                    'spec',
                    'src',
                ],
                'generated_roots' => ['spec/generated'],
                'allowed_local_outputs' => ['.agent-context'],
                'exempt_paths' => ['glossary/README.md'],
                'architecture_rules' => [
                    [
                        'id' => 'arch-001',
                        'from' => ['src/Feed/**'],
                        'forbidden' => ['src/Application/**'],
                        'allowed' => ['src/Feed/**'],
                        'exceptions' => [],
                        'message' => 'Feed code must not depend on the application layer.',
                    ],
                ],
                'task_state_path' => '.agent-context/active-task.json',
                'task_markdown_path' => '.agent-context/active-task.md',
                'review_packet_path' => '.agent-context/drift-review.md',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            'spec/subsystems/repo-governance-context-system.json' => $this->subsystemJson([
                'id' => 'repo-governance-context-system',
                'title' => 'Repo governance',
                'status' => 'stable',
                'summary' => 'Owns governance docs and tooling.',
                'owned_paths' => ['README.md', 'AGENTS.md', '.gitignore', 'composer.json', 'docs/**', 'glossary/**', 'spec/**'],
                'related_paths' => [],
                'entrypoints' => [['path' => 'spec/README.md', 'symbols' => ['README']]],
                'depends_on' => [],
                'used_by' => [],
                'required_reads' => ['spec' => ['spec/README.md'], 'docs' => ['docs/context-system/README.md'], 'decisions' => ['spec/decisions/ADR-0001-context.md']],
                'glossary_terms' => ['authoritative artifact'],
                'public_contracts' => [['name' => 'governance', 'summary' => 'Keeps authoritative context aligned.']],
                'invariants' => [['id' => 'gov-001', 'statement' => 'Structured context artifacts are authoritative for architecture memory.']],
                'performance_constraints' => ['Context retrieval stays bounded.'],
                'change_policy' => ['default' => 'additive-only', 'requires_adr' => ['changing the governance workflow']],
                'tests' => ['tests/ContextSystemTest.php'],
                'decision_refs' => ['ADR-0001'],
                'history_anchors' => ['introduced_by' => 'fixture', 'major_refactors' => []],
            ]),
            'spec/subsystems/request-entry-and-composition.json' => $this->subsystemJson([
                'id' => 'request-entry-and-composition',
                'title' => 'Request entry',
                'status' => 'stable',
                'summary' => 'Owns application entrypoints.',
                'owned_paths' => ['index.php', 'src/Application/**'],
                'related_paths' => [],
                'entrypoints' => [['path' => 'src/Application/App.php', 'symbols' => ['App']]],
                'depends_on' => ['config-contract-and-normalization'],
                'used_by' => ['repo-governance-context-system'],
                'required_reads' => ['spec' => ['spec/README.md'], 'docs' => [], 'decisions' => []],
                'glossary_terms' => [],
                'public_contracts' => [['name' => 'App', 'summary' => 'Fixture entrypoint.']],
                'invariants' => [['id' => 'entry-001', 'statement' => 'Application entry stays above feed code.']],
                'performance_constraints' => ['Entry code stays thin.'],
                'change_policy' => ['default' => 'additive-only', 'requires_adr' => []],
                'tests' => [],
                'decision_refs' => [],
                'history_anchors' => ['introduced_by' => 'fixture', 'major_refactors' => []],
            ]),
            'spec/subsystems/config-contract-and-normalization.json' => $this->subsystemJson([
                'id' => 'config-contract-and-normalization',
                'title' => 'Config contract',
                'status' => 'locked',
                'summary' => 'Owns fixture config behavior.',
                'owned_paths' => ['src/Config/**', 'spec/subsystems/config-contract-and-normalization.json'],
                'related_paths' => [],
                'entrypoints' => [['path' => 'src/Config/ConfigLoader.php', 'symbols' => ['ConfigLoader']]],
                'depends_on' => [],
                'used_by' => ['request-entry-and-composition'],
                'required_reads' => ['spec' => ['spec/README.md'], 'docs' => [], 'decisions' => ['spec/decisions/ADR-0002-locked.md']],
                'glossary_terms' => ['config contract'],
                'public_contracts' => [['name' => 'ConfigLoader', 'summary' => 'Loads fixture config.']],
                'invariants' => [['id' => 'config-001', 'statement' => 'Fixture config behavior stays stable.']],
                'performance_constraints' => ['Config loading stays cheap.'],
                'change_policy' => ['default' => 'additive-only', 'requires_adr' => ['changing fixture config behavior']],
                'tests' => [],
                'decision_refs' => ['ADR-0002'],
                'history_anchors' => ['introduced_by' => 'fixture', 'major_refactors' => []],
            ]),
            'spec/subsystems/feed-selection-and-replay.json' => $this->subsystemJson([
                'id' => 'feed-selection-and-replay',
                'title' => 'Feed selection',
                'status' => 'stable',
                'summary' => 'Owns feed selection behavior.',
                'owned_paths' => ['src/Feed/**', 'spec/subsystems/feed-selection-and-replay.json'],
                'related_paths' => [],
                'entrypoints' => [['path' => 'src/Feed/Processor.php', 'symbols' => ['Processor']]],
                'depends_on' => [],
                'used_by' => ['request-entry-and-composition'],
                'required_reads' => ['spec' => ['spec/README.md'], 'docs' => [], 'decisions' => []],
                'glossary_terms' => [],
                'public_contracts' => [['name' => 'Processor', 'summary' => 'Processes feeds.']],
                'invariants' => [['id' => 'feed-001', 'statement' => 'Feed code stays below the application layer.']],
                'performance_constraints' => ['Feed processing stays isolated.'],
                'change_policy' => ['default' => 'additive-only', 'requires_adr' => []],
                'tests' => [],
                'decision_refs' => [],
                'history_anchors' => ['introduced_by' => 'fixture', 'major_refactors' => []],
            ]),
            'spec/subsystems/ops-tooling.json' => $this->subsystemJson([
                'id' => 'ops-tooling',
                'title' => 'Ops tooling',
                'status' => 'stable',
                'summary' => 'Owns unrelated reporting helpers.',
                'owned_paths' => ['src/Ops/**', 'spec/subsystems/ops-tooling.json'],
                'related_paths' => [],
                'entrypoints' => [['path' => 'src/Ops/Report.php', 'symbols' => ['Report']]],
                'depends_on' => [],
                'used_by' => [],
                'required_reads' => ['spec' => ['spec/README.md'], 'docs' => [], 'decisions' => ['spec/decisions/ADR-0001-context.md']],
                'glossary_terms' => [],
                'public_contracts' => [['name' => 'Report', 'summary' => 'Produces an unrelated report.']],
                'invariants' => [['id' => 'ops-001', 'statement' => 'Ops tooling stays outside unrelated feature work unless explicitly targeted.']],
                'performance_constraints' => ['Reports stay isolated from unrelated features.'],
                'change_policy' => ['default' => 'additive-only', 'requires_adr' => []],
                'tests' => [],
                'decision_refs' => ['ADR-0001'],
                'history_anchors' => ['introduced_by' => 'fixture', 'major_refactors' => []],
            ]),
            'spec/decisions/ADR-0001-context.md' => "# ADR-0001 Context\n\nFixture ADR.\n",
            'spec/decisions/ADR-0002-locked.md' => "# ADR-0002 Locked\n\nFixture ADR.\n",
            'src/Application/App.php' => "<?php\n\ndeclare(strict_types=1);\n\nnamespace Podquilt\\Application;\n\nuse Podquilt\\Config\\ConfigLoader;\nuse Podquilt\\Feed\\Processor;\n\nfinal class App\n{\n    public function __construct(\n        private ConfigLoader \$configLoader,\n        private Processor \$processor,\n    ) {\n    }\n}\n",
            'src/Config/ConfigLoader.php' => "<?php\n\ndeclare(strict_types=1);\n\nnamespace Podquilt\\Config;\n\nfinal class ConfigLoader\n{\n}\n",
            'src/Feed/Processor.php' => "<?php\n\ndeclare(strict_types=1);\n\nnamespace Podquilt\\Feed;\n\nfinal class Processor\n{\n}\n",
            'src/Ops/Report.php' => "<?php\n\ndeclare(strict_types=1);\n\nnamespace Podquilt\\Ops;\n\nfinal class Report\n{\n}\n",
            'index.php' => "<?php\n\ndeclare(strict_types=1);\n",
        ];

        foreach ($files as $relativePath => $contents) {
            $this->writeFile($repoRoot, $relativePath, $contents);
        }

        $system = new ContextSystem($repoRoot);
        $system->writeContextArtifacts($system->loadContextData());

        $this->runGit($repoRoot, ['init']);
        $this->runGit($repoRoot, ['add', '-A']);
        $this->runGit($repoRoot, [
            '-c',
            'user.name=Fixture',
            '-c',
            'user.email=fixture@example.com',
            'commit',
            '-m',
            'Initial fixture',
        ]);

        return $repoRoot;
    }

    /**
     * @param array<string, mixed> $subsystem
     */
    private function subsystemJson(array $subsystem): string
    {
        return (string) json_encode($subsystem, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function writeFile(string $repoRoot, string $relativePath, string $contents): void
    {
        $absolutePath = $repoRoot . '/' . $relativePath;
        $directory = dirname($absolutePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($absolutePath, $contents);
    }

    private function appendFile(string $repoRoot, string $relativePath, string $contents): void
    {
        file_put_contents($repoRoot . '/' . $relativePath, $contents, FILE_APPEND);
    }

    private function replaceInFile(string $repoRoot, string $relativePath, string $search, string $replace): void
    {
        $absolutePath = $repoRoot . '/' . $relativePath;
        $contents = (string) file_get_contents($absolutePath);
        file_put_contents($absolutePath, str_replace($search, $replace, $contents));
    }

    /**
     * @param list<string> $arguments
     */
    private function runGit(string $repoRoot, array $arguments): void
    {
        $command = 'cd ' . escapeshellarg($repoRoot) . ' && git ' . implode(' ', array_map('escapeshellarg', $arguments));
        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, 'Git command failed: ' . $command);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * @return list<string>
     */
    private function gitStatus(ContextSystem $system): array
    {
        $reflectionMethod = new \ReflectionMethod($system, 'getGitStatus');

        /** @var list<string> $status */
        $status = $reflectionMethod->invoke($system);

        return $status;
    }
}
