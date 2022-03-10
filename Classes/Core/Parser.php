<?php
declare(strict_types=1);

namespace Neos\Fusion\Core;

/*
 * This file is part of the Neos.Fusion package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Fusion;
use Neos\Fusion\Core\ObjectTreeParser\Ast\FusionFile;
use Neos\Fusion\Core\ObjectTreeParser\FilePatternResolver;
use Neos\Fusion\Core\ObjectTreeParser\Lexer;
use Neos\Fusion\Core\ObjectTreeParser\ObjectTree;
use Neos\Fusion\Core\ObjectTreeParser\ObjectTreeAstVisitor;
use Neos\Fusion\Core\ObjectTreeParser\PredictiveParser;
use Neos\Flow\Annotations as Flow;

/**
 * The Fusion Parser
 *
 * @api
 */
class Parser implements ParserInterface
{
    /**
     * Reserved parse tree keys for internal usage.
     *
     * @deprecated use ParserInterface::RESERVED_PARSE_TREE_KEYS
     */
    public static array $reservedParseTreeKeys = ParserInterface::RESERVED_PARSE_TREE_KEYS;

    /**
     * @Flow\Inject
     * @var DslFactory
     */
    protected $dslFactory;

    protected PredictiveParser $predictiveParser;

    /**
     * Parses the given Fusion source code and returns an object tree
     * as the result.
     *
     * @param string $sourceCode The Fusion source code to parse
     * @param string|null $contextPathAndFilename An optional path and filename to use as a prefix for inclusion of further Fusion files
     * @param array $objectTreeUntilNow Used internally for keeping track of the built object tree
     * @return array A Fusion object tree, generated from the source code
     * @throws Fusion\Exception
     * @api
     */
    public function parse(string $sourceCode, ?string $contextPathAndFilename = null, array $objectTreeUntilNow = []): array
    {
        $fusionFile = $this->getFusionFile($sourceCode, $contextPathAndFilename);

        $objectTree = new ObjectTree();
        $objectTree->setObjectTree($objectTreeUntilNow);

        $objectTree = $this->getObjectTreeAstVisitor($objectTree)->visitFusionFile($fusionFile);

        $objectTree->buildPrototypeHierarchy();
        return $objectTree->getObjectTree();
    }

    public function injectPredictiveParser(PredictiveParser $predictiveParser)
    {
        $this->predictiveParser = $predictiveParser;
    }

    protected function handleFileInclude(ObjectTree $objectTree, string $filePattern, ?string $contextPathAndFilename): void
    {
        $filesToInclude = FilePatternResolver::resolveFilesByPattern($filePattern, $contextPathAndFilename, '.fusion');
        foreach ($filesToInclude as $file) {
            if (is_readable($file) === false) {
                throw new Fusion\Exception("Could not read file '$file' of pattern '$filePattern'.", 1347977017);
            }
            // Check if not trying to recursively include the current file via globbing
            if ($contextPathAndFilename === null
                || stat($contextPathAndFilename) !== stat($file)) {

                $fusionFile = $this->getFusionFile(file_get_contents($file), $file);
                $this->getObjectTreeAstVisitor($objectTree)->visitFusionFile($fusionFile);
            }
        }
    }

    protected function handleDslTranspile(string $identifier, string $code)
    {
        $dslObject = $this->dslFactory->create($identifier);

        $transpiledFusion = $dslObject->transpile($code);

        $lexer = new Lexer('value = ' . $transpiledFusion);
        $fusionFile = $this->predictiveParser->parse($lexer);

        $objectTree = $this->getObjectTreeAstVisitor(new ObjectTree())->visitFusionFile($fusionFile);

        $temporaryAst = $objectTree->getObjectTree();

        $dslValue = $temporaryAst['value'];
        return $dslValue;
    }

    protected function getObjectTreeAstVisitor(ObjectTree $objectTree): ObjectTreeAstVisitor
    {
        return new ObjectTreeAstVisitor(
            $objectTree,
            fn (...$args) => $this->handleFileInclude(...$args),
            fn (...$args) => $this->handleDslTranspile(...$args),
        );
    }

    protected function getFusionFile(string $sourceCode, ?string $contextPathAndFilename): FusionFile
    {
        $lexer = new Lexer($sourceCode);
        $fusionFile = $this->predictiveParser->parse($lexer, $contextPathAndFilename);
        return $fusionFile;
    }
}
