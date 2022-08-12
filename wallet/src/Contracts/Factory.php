<?php

namespace App\Packages\wallet\wallet\src\Contracts;

interface Factory
{
    /**
     * Get a Shetab provider instance.
     *
     * @param string|null $driver
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function driver($driver = null);
}
