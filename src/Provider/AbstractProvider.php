<?php

namespace PhpMonsters\LaraWallet\Provider;

use App\Containers\AppSection\Transaction\Models\Transaction;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use PhpMonsters\LaraWallet\Facades\Wallet;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

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
    const CONNECTION_TIME_OUT = 5;


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
    protected $transaction;


    /**
     * @param array $configs
     * @param string $environment
     * @param $transaction
     * @param $mobileNumber
     */
    public function __construct(
        array $configs,
        string $environment,
        $transaction = null,
        $mobileNumber
    ) {
        $this->environment = $environment;
        $this->setParameters($configs);
        $this->setCellNumber($mobileNumber);
        $this->setTransaction($transaction);
        $this->setUrl(config('wallet.asanpardakht.url'));
    }


    /**
     * @param $transaction
     * @return void
     */
    public function setTransaction($transaction = null): void
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
     * @return mixed
     */
    public function sendInfoToAp(string $hostRequest, string $hostRequestSign, $method, $url): mixed
    {
        $rawResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->withBody(
            '{"hreq":"' . str_replace(
                ['"{', '}"'],
                ["{", "}"],
                json_encode($hostRequest, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK),
            ) . '","hsign":"' . $hostRequestSign . '","ver":"1.9.2"}',
            'application/json'
        )->$method(
            $url
        );

        return json_decode($rawResponse->body(), true);
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


    public function getTransaction()
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

        Wallet::log($message, $params, $level);
    }

    /**
     * @param $message
     * @param int $statusCode
     * @param int|null $errorCode
     * @return JsonResponse
     */
    public static function generalExceptionResponse(
        int $errorCode = null,
        $message = null,
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
    ): JsonResponse {
        return response()->json([
            "code" => $errorCode,
            "message" => $message,
            "x_track_id" => resolve(env('XLOG_TRACK_ID_KEY', 'xTrackId')),
        ], $statusCode);
    }


    /**
     * @param int $code
     * @param string|null $value
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function generalResponse(
        int $code = 0,
        string $value = null,
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        return response()->json([
            "code" => $code,
            "value" => $value,
            "x_track_id" => resolve(env('XLOG_TRACK_ID_KEY', 'xTrackId')),
        ], $statusCode);
    }

    /**
     * @param $view
     * @param $withErrors
     * @return Factory|View|Application
     */
    public static function generalViewErrorResponse($view, $withErrors): Factory|View|Application
    {
        return view($view)->withErrors([$withErrors]);
    }

}
