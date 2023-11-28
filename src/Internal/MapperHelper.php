<?php

namespace Mention\Retry\Internal;

use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;

/** @internal */
final class MapperHelper
{
    public static function mapper(): TreeMapper
    {
        return (new MapperBuilder())
            ->allowSuperfluousKeys()
            ->allowPermissiveTypes()
            ->enableFlexibleCasting()
            ->mapper()
        ;
    }
}
