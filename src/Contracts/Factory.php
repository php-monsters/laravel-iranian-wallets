<?php

namespace PhpMonsters\LaraWallet\Contracts;

interface Factory
{

    /**
     * @param $driver
     * @return mixed
     */
    public function driver($driver = null): mixed;
}
