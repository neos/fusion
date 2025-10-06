<?php

declare(strict_types=1);

namespace Neos\Fusion\Migrations\FusionPrototype;

class FusionPrototypeNameAddComment
{
    public function __construct(
        public readonly string $name,
        public readonly string $comment,
    ) {
    }
}
