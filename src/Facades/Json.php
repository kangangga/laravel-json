<?php

namespace Kangangga\Json\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Kangangga\Json\Json
 */
class Json extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Kangangga\Json\Json::class;
    }
}
