<?php

declare(strict_types=1);

namespace Neos\Fusion\Migrations\FusionPrototype;

/**
 * @Flow\Proxy(false)
 * @internal
 */
class FusionPrototypeTransformer
{
    private function __construct(private readonly string $fileContent)
    {
    }

    public static function forContent(string $fileContent): self
    {
        return new self($fileContent);
    }

    /**
     * Rewrite prototype names form e.g Foo.Bar:Boo to Boo.Bar:Foo
     */
    public function processFusionPrototypeNameReplacements(FusionPrototypeNameReplacement ...$fusionPrototypeNameReplacements): self
    {
        $fileContent = $this->fileContent;

        $comments = [];
        foreach ($fusionPrototypeNameReplacements as $fusionPrototypeNameReplacement) {
            $replacementCount = 0;
            if ($fusionPrototypeNameReplacement->skipPrototypeDefinitions) {
                $pattern = '/(^|[=\s<\/])(' . $fusionPrototypeNameReplacement->oldName . ')([\s{\/>]|$)/';
            } else {
                $pattern = '/(^|[=\s(<\/])(' . $fusionPrototypeNameReplacement->oldName . ')([\s{)\/>]|$)/';
            }
            $replacement = '$1' . $fusionPrototypeNameReplacement->newName . '$3';
            $fileContent = preg_replace($pattern, $replacement, $fileContent, count: $replacementCount);

            if ($replacementCount > 0 && $fusionPrototypeNameReplacement->comment) {
                $comments[] = '// TODO 9.0 migration: ' . $fusionPrototypeNameReplacement->comment;
            }
        }

        if (count($comments) > 0) {
            $fileContent = implode("\n", $comments) . "\n" . $fileContent;
        }

        return new self($fileContent);
    }

    /**
     * Add comment to file if prototype name matches at least once.
     */
    public function processFusionPrototypeNameAddComments(FusionPrototypeNameAddComment ...$fusionPrototypeNameAddComments): self
    {
        $fileContent = $this->fileContent;

        $comments = [];
        foreach ($fusionPrototypeNameAddComments as $fusionPrototypeNameAddComment) {
            $matches = [];
            $pattern = '/(^|[=\s\(<\/])(' . $fusionPrototypeNameAddComment->name . ')([\s\{\)\/>]|$)/';
            preg_match($pattern, $fileContent, $matches);

            if (count($matches) > 0) {
                $comments[] = "// " . $fusionPrototypeNameAddComment->comment;
            }
        }

        if (count($comments) > 0) {
            $fileContent = implode("\n", $comments) . "\n" . $fileContent;
        }

        return new self($fileContent);
    }

    public function getProcessedContent(): string
    {
        return $this->fileContent;
    }
}
