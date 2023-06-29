<?php

namespace PhpMonsters\LaraWallet\Contracts;

interface Provider
{
    /**
     * Determines whether the provider supports reverse transaction
     */
    public function refundSupport(): bool;

    /**
     * @param  array  $parameters operation parameters
     */
    public function setParameters(array $parameters = []): Provider;

    /**
     * @param  null  $default
     */
    public function getParameters(string $key = null, $default = null): mixed;

    /**
     * return rendered goto gate form
     */
    public function getForm(): string;

    public function getFormParameters(): array;

    public function getTransaction(): Transaction;

    /**
     * verify transaction
     */
    public function verifyTransaction(): bool;

    public function settleTransaction(): bool;

    public function refundTransaction(): bool;

    public function getGatewayReferenceId(): string;

    public function getUrlFor(string $action): string;

    public function canContinueWithCallbackParameters(): bool;

    public function checkRequiredActionParameters(array $parameters): void;
}
