<?php
declare(strict_types=1);

namespace Neos\Fusion\Migrations\Helper;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 * @internal
 */
class PrecedingFusionFileComment
{
    public string $text = '';

    public function __construct(
        public readonly int $lineNumberOfMatch,
        public readonly string $template
    ) {
    }
}
