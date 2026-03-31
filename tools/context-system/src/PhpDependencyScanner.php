<?php

declare(strict_types=1);

namespace Podquilt\Tools\ContextSystem;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RuntimeException;

final readonly class PhpDependencyScanner
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @param list<string> $phpFiles
     * @return array<string, list<string>>
     */
    public function scan(string $repoRoot, array $phpFiles): array
    {
        $symbolMap = $this->buildSymbolMap($repoRoot, $phpFiles);
        $importsByFile = [];

        foreach ($phpFiles as $relativePath) {
            $importsByFile[$relativePath] = $this->collectImportsForFile(
                $repoRoot,
                $relativePath,
                $symbolMap,
            );
        }

        return $importsByFile;
    }

    /**
     * @param list<string> $phpFiles
     * @return array<string, string>
     */
    private function buildSymbolMap(string $repoRoot, array $phpFiles): array
    {
        $finder = new NodeFinder();
        $symbolMap = [];

        foreach ($phpFiles as $relativePath) {
            $nodes = $this->parseAndResolve($repoRoot, $relativePath);

            foreach ($finder->findInstanceOf($nodes, ClassLike::class) as $classLike) {
                $namespacedName = $classLike->namespacedName ?? null;

                if (!$namespacedName instanceof Name) {
                    continue;
                }

                $symbolMap[$namespacedName->toString()] = $relativePath;
            }
        }

        return $symbolMap;
    }

    /**
     * @param array<string, string> $symbolMap
     * @return list<string>
     */
    private function collectImportsForFile(
        string $repoRoot,
        string $relativePath,
        array $symbolMap,
    ): array {
        $nodes = $this->parseAndResolve($repoRoot, $relativePath);
        $finder = new NodeFinder();
        $imports = [];

        foreach ($finder->findInstanceOf($nodes, Name::class) as $nameNode) {
            if (!$this->isRelevantName($nameNode)) {
                continue;
            }

            $resolvedName = $this->resolveName($nameNode);

            if ($resolvedName === null || !array_key_exists($resolvedName, $symbolMap)) {
                continue;
            }

            $importPath = $symbolMap[$resolvedName];

            if ($importPath === $relativePath) {
                continue;
            }

            $imports[$importPath] = true;
        }

        ksort($imports);

        return array_keys($imports);
    }

    /**
     * @return list<Node>
     */
    private function parseAndResolve(string $repoRoot, string $relativePath): array
    {
        $absolutePath = $repoRoot . DIRECTORY_SEPARATOR . $relativePath;
        $source = file_get_contents($absolutePath);

        if ($source === false) {
            throw new RuntimeException('Unable to read PHP source: ' . $relativePath);
        }

        $nodes = $this->parser->parse($source);

        if ($nodes === null) {
            throw new RuntimeException('Unable to parse PHP source: ' . $relativePath);
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor(new NameResolver());

        /** @var list<Node> $resolvedNodes */
        $resolvedNodes = $traverser->traverse($nodes);

        return $resolvedNodes;
    }

    private function isRelevantName(Name $nameNode): bool
    {
        $parent = $nameNode->getAttribute('parent');

        if (!$parent instanceof Node) {
            return false;
        }

        if ($parent instanceof Namespace_ || $parent instanceof UseUse || $parent instanceof GroupUse) {
            return false;
        }

        return true;
    }

    private function resolveName(Name $nameNode): ?string
    {
        if ($nameNode instanceof Name\FullyQualified) {
            return $nameNode->toString();
        }

        $resolvedName = $nameNode->getAttribute('resolvedName');

        return $resolvedName instanceof Name ? $resolvedName->toString() : null;
    }
}
