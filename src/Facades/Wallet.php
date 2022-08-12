<?php

namespace App\Packages\wallet\wallet\src\Facades;

use App\Packages\wallet\wallet\src\Contracts\Factory;
use Illuminate\Support\Facades\Facade;

/**
 *
 */
class Wallet extends Facade
{

    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    public static function getFacadeAccessor(): string
    {
        return Factory::class;
    }

}
