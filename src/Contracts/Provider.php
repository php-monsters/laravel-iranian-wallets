<?php

namespace PhpMonsters\LaraWallet\Contracts;

interface Provider
{
    /**
     * Determines whether the provider supports reverse transaction
     *
     * @return bool
     */
    public function refundSupport(): bool;

    /**
     * @param array $parameters operation parameters
     *
     * @return Provider
     */
    public function setParameters(array $parameters = []): Provider;

    /**
     * @param string|null $key
     *
     * @param null $default
     *
     * @return mixed
     */
    public function getParameters(string $key = null, $default = null): mixed;

    /**
     * return rendered goto gate form
     *
     * @return string
     */
    public function getForm(): string;


    /**
     * @return array
     */
    public function getFormParameters(): array;


    /**
     * @return Transaction
     */
    public function getTransaction(): Transaction;

    /**
     * verify transaction
     *
     * @return bool
     */
    public function verifyTransaction(): bool;


    /**
     * @return bool
     */
    public function settleTransaction(): bool;


    /**
     * @return bool
     */
    public function refundTransaction(): bool;


    /**
     * @return string
     */
    public function getGatewayReferenceId(): string;


    /**
     * @param string $action
     * @return string
     */
    public function getUrlFor(string $action): string;


    /**
     * @return bool
     */
    public function canContinueWithCallbackParameters(): bool;


    /**
     * @param array $parameters
     * @return void
     */
    public function checkRequiredActionParameters(array $parameters): void;
}
