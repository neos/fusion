<?php
declare(strict_types=1);

namespace Neos\Fusion\Migrations\Helper;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 * @internal
 */
class RegexCommentTemplatePair
{
    public function __construct(
        public readonly string $regex,
        public readonly string $template,
    ) {
    }
}
