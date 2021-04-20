<?php


namespace POData\Providers\Metadata\Type;

/**
 * Class DateTime
 * @package POData\Providers\Metadata\Type
 */
class DateTimeTz extends DateTime
{
    /**
     * Gets the type code
     * Note: implementation of IType::getTypeCode
     *
     * @return TypeCode
     */
    public function getTypeCode()
    {
        return TypeCode::DATETIMETZ;
    }
}
