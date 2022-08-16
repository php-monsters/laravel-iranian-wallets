<?php

namespace PhpMonsters\LaraWallet\Facades;

use Illuminate\Support\Facades\Facade;
use PhpMonsters\LaraWallet\Contracts\Factory;

/**
 *
 * @method static log(string $message, array $params, string $level)
 */
class Wallet extends Facade
{


    /**
     * @return string
     */
    public static function getFacadeAccessor(): string
    {
        return Factory::class;
    }

}
