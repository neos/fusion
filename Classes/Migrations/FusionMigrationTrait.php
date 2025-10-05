<?php

declare(strict_types=1);

namespace Neos\Fusion\Migrations;

use Neos\Flow\Core\Migrations\AbstractMigration;
use Neos\Fusion\Migrations\Helper\RegexCommentTemplatePair;

trait FusionMigrationTrait
{
    /**
     * @var array<string,string>
     */
    private array $eelReplacementOperations = [];

    /**
     * @var array<RegexCommentTemplatePair>
     */
    private array $regexConditionalCommentsOperations = [];

    final public function replaceEelExpression(string $pregSearch, string $pregReplace): void
    {
        $this->eelReplacementOperations[$pregSearch] = $pregReplace;
    }

    final public function addCommentsIfRegexMatches(string $regex, string $comment): void
    {
        $this->regexConditionalCommentsOperations[] = new RegexCommentTemplatePair($regex, $comment);
    }

    final protected function applyEelFusionOperations(): void
    {
        $filePaths = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->targetPackageData['path']),
            ),
            '/\.fusion$/',
            \RecursiveRegexIterator::GET_MATCH,
        );

        foreach ($filePaths as $filePath => $_) {
            $originalContents = file_get_contents($filePath);

            $eelTransformer = EelExpressionTransformer::parse($originalContents);
            $eelTransformer = $eelTransformer->process(function (string $expression) {
                $newExpression = preg_replace(array_keys($this->eelReplacementOperations), array_values($this->eelReplacementOperations), $expression);
                if ($newExpression === null) {
                    throw new \RuntimeException(sprintf('Malformed preg replacement for expression %s', $expression), 1759641629);
                }
                return $newExpression;
            });

            $eelTransformer = $eelTransformer->addCommentsIfRegexesMatch($this->regexConditionalCommentsOperations);

            $newContents = $eelTransformer->getProcessedContent();

            if ($originalContents !== $newContents) {
                file_put_contents($filePath, $newContents);
            }
        }
    }

    public function execute()
    {
        /** Que into flow's {@see AbstractMigration::execute()} - there is no other extension point */
        parent::execute();
        $this->applyEelFusionOperations();
    }
}
