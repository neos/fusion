<?php

namespace Neos\Fusion\Core\ObjectTreeParser\Ast;

class EelExpressionValueAst extends PathValueAst
{
    /**
     * @var string
     */
    protected $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
