<?php

namespace PhpMonsters\LaraWallet\Contracts;

interface Transaction
{
    /**
     * return the callback url of the transaction process
     *
     * @return string
     */
    public function getCallbackUrl(): string;

    /**
     * set gateway token of transaction
     *
     * @param string $token
     * @param bool $save
     *
     * @return mixed
     */
    public function setGatewayToken(string $token, bool $save = true): bool;

    /**
     * set reference ID of transaction
     *
     * @param string $referenceId
     * @param bool $save
     *
     * @return mixed
     */
    public function setReferenceId(string $referenceId, bool $save = true): bool;


    /**
     * @return int
     */
    public function getGatewayOrderId(): int;


    /**
     * @return bool
     */
    public function isReadyForTokenRequest(): bool;


    /**
     * @return bool
     */
    public function isReadyForVerify(): bool;


    /**
     * @return bool
     */
    public function isReadyForInquiry(): bool;


    /**
     * @return bool
     */
    public function isReadyForSettle(): bool;


    /**
     * @return bool
     */
    public function isReadyForRefund(): bool;


    /**
     * @param bool $save
     * @return bool
     */
    public function setVerified(bool $save = true): bool;


    /**
     * @param bool $save
     * @return bool
     */
    public function setSettled(bool $save = true): bool;


    /**
     * @param bool $save
     * @return bool
     */
    public function setAccomplished(bool $save = true): bool;


    /**
     * @param bool $save
     * @return bool
     */
    public function setRefunded(bool $save = true): bool;


    /**
     * @return int
     */
    public function getPayableAmount(): int;


    /**
     * @param string $cardNumber
     * @param bool $save
     * @return bool
     */
    public function setCardNumber(string $cardNumber, bool $save = false): bool;


    /**
     * @param array $parameters
     * @param bool $save
     * @return bool
     */
    public function setCallBackParameters(array $parameters, bool $save = true): bool;


    /**
     * @param string $key
     * @param $value
     * @param bool $save
     * @return bool
     */
    public function addExtra(string $key, $value, bool $save = true): bool;
}
