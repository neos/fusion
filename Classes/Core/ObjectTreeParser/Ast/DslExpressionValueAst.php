<?php

namespace Neos\Fusion\Core\ObjectTreeParser\Ast;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class DslExpressionValueAst extends PathValueAst
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $code;

    public function __construct(string $identifier, string $code)
    {
        $this->identifier = $identifier;
        $this->code = $code;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
