<?php

namespace POData\UriProcessor\QueryProcessor\ExpressionParser\Expressions;

use POData\Providers\Metadata\Type\IType;

/**
 * Class ConstantExpression
 * @package POData\UriProcessor\QueryProcessor\ExpressionParser\Expressions
 */
class ConstantExpression extends AbstractExpression
{
    /**
     * The value hold by the expression
     * @var mixed
     */
    protected $value;

    /**
     * Create new inatnce of ConstantExpression.
     * 
     * @param mixed $value The constant value
     * @param IType $type The expression node type
     */
    public function __construct($value, IType $type)
    {
        $this->value = $value;
        $this->nodeType = ExpressionType::CONSTANT;
        $this->type = $type;
    }

    /**
     * Get the value associated with the expression
     * 
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * (non-PHPdoc)
     * 
     * @see library/POData/QueryProcessor/ExpressionParser/Expressions.AbstractExpression::free()
     * 
     * @return void
     */
    public function free()
    {
        unset($this->value);
    }
}