<?php

namespace PhpMonsters\LaraWallet\Contracts;

interface Transaction
{
    /**
     * return the callback url of the transaction process
     */
    public function getCallbackUrl(): string;

    /**
     * set gateway token of transaction
     *
     *
     * @return mixed
     */
    public function setGatewayToken(string $token, bool $save = true): bool;

    /**
     * set reference ID of transaction
     *
     *
     * @return mixed
     */
    public function setReferenceId(string $referenceId, bool $save = true): bool;

    public function getGatewayOrderId(): int;

    public function isReadyForTokenRequest(): bool;

    public function isReadyForVerify(): bool;

    public function isReadyForInquiry(): bool;

    public function isReadyForSettle(): bool;

    public function isReadyForRefund(): bool;

    public function setVerified(bool $save = true): bool;

    public function setSettled(bool $save = true): bool;

    public function setAccomplished(bool $save = true): bool;

    public function setRefunded(bool $save = true): bool;

    public function getPayableAmount(): int;

    public function setCardNumber(string $cardNumber, bool $save = false): bool;

    public function setCallBackParameters(array $parameters, bool $save = true): bool;

    public function addExtra(string $key, $value, bool $save = true): bool;

    public function getWalletTransactionId(): int;
}
