<?php

namespace App\Packages\wallet\wallet\src\Provider;

use App\Containers\AppSection\Transaction\Models\Transaction;
use Asanpay\Shaparak\Facades\Shaparak;
use ReflectionClass;

/**
 * Class AbstractProvider
 *
 * @author    Maryam Nabiyan
 * @package   Wallet
 * @package   Wallet\Wallet
 * @version   v1.0
 */
abstract class AbstractProvider
{

    /**
     * @var string
     */
    protected string $cellNumber;

    /**
     * @var string|mixed
     */
    protected string $environment;

    /**
     * @var string
     */
    protected string $url;

    /**
     * @var Transaction
     */
    protected Transaction $transaction;


    /**
     * @param array $configs
     * @param string $environment
     * @param Transaction|null $transaction
     */
    public function __construct(
        array $configs,
        string $environment,
        Transaction $transaction = null,
    ) {
        $this->environment = $environment;
        $this->setParameters($configs);
        $this->setCellNumber($transaction->mobile_number);
        $this->setTransaction($transaction);
        $this->setUrl(config('wallet.asanpardakht.url'));
    }

    /**
     * @param $transaction
     * @return void
     */
    public function setTransaction($transaction): void
    {
        $this->transaction = $transaction;
    }

    /**
     * @param $cellNumber
     * @return void
     */
    public function setCellNumber($cellNumber): void
    {
        $this->cellNumber = $cellNumber;
    }

    /**
     * @return string
     */
    public function getCellNumber(): string
    {
        return $this->cellNumber;
    }

    /**
     * @param string $data
     * @return bool|string
     */
    public function hashParam(string $data): bool|string
    {
        return hash('md5', $data, false);
    }


    /**
     * @param string $hostRequest
     * @param string $hostRequestSign
     * @param $method
     * @param $url
     * @return string|bool
     */
    public function sendInfoToAp(string $hostRequest, string $hostRequestSign, $method, $url): string|bool
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => '{"hreq":"' . $hostRequest . '","hsign":"' . $hostRequestSign . '","ver":"1.9.2"}',
            CURLOPT_HTTPHEADER => array(
                "accept: */*",
                "accept-language: en-US,en;q=0.8",
                "content-type: application/json",
            ),
        ));

        return curl_exec($curl);
    }


    /**
     * @param array $parameters
     * @return $this
     */
    public function setParameters(array $parameters = []): static
    {
        $parameters = array_change_key_case($parameters, CASE_LOWER);

        $parameters = array_map('trim', $parameters);
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }


    /**
     * @param string|null $key
     * @param $default
     * @return mixed|null
     */
    public function getParameters(string $key = null, $default = null): mixed
    {
        if (is_null($key)) {
            return $this->parameters;
        }

        $key = strtolower($key);
        return $this->parameters[$key] ?? $default;
    }

    /**
     * @param mixed|string $environment
     * @return AbstractProvider
     */
    public function setEnvironment(mixed $environment): AbstractProvider
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @return Transaction
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    /**
     * @param string $url
     * @return AbstractProvider
     */
    public function setUrl(string $url): AbstractProvider
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $message
     * @param array $params
     * @param string $level
     * @return void
     */
    protected function log(string $message, array $params = [], string $level = 'debug'): void
    {
        $reflect = new ReflectionClass($this);
        $provider = strtolower(str_replace('Provider', '', $reflect->getShortName()));

        $message = $provider . ": " . $message;

        Shaparak::log($message, $params, $level);
    }

}
