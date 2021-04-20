<?php


namespace POData\Providers\Metadata\Type;

/**
 * Class Date
 * @package POData\Providers\Metadata\Type
 */
class Date extends DateTime
{
    /**
     * Gets the type code
     * Note: implementation of IType::getTypeCode
     *
     * @return TypeCode
     */
    public function getTypeCode()
    {
        return TypeCode::DATE;
    }

    /**
     * Gets full name of this type in EDM namespace
     * Note: implementation of IType::getFullTypeName
     * 
     * @return string
     */
    public function getFullTypeName()
    {
        return 'Edm.Date';
    }
}
