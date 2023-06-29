<?php

namespace PhpMonsters\LaraWallet\Provider;

use App\Containers\AppSection\Transaction\Models\Transaction;
use App\Containers\AppSection\Wallet\Models\WalletProvider;
use App\Ship\Models\Serial;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use PhpMonsters\LaraWallet\Facades\LaraWallet;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AbstractProvider
 *
 * @author    Maryam Nabiyan
 *
 * @version   v1.0
 */
abstract class AbstractProvider extends WalletProvider
{
    const CONNECTION_TIME_OUT = 5;

    protected string $cellNumber;

    /**
     * @var string|mixed
     */
    protected string $environment;

    protected string $url;

    /**
     * @var Transaction
     */
    protected $transaction;

    public function __construct(
        array $configs,
        string $environment,
        //todo adds type of transaction
        $transaction,
        $mobileNumber
    ) {
        $this->environment = $environment;
        $this->setParameters($configs);
        $this->setCellNumber($mobileNumber);
        $this->setTransaction($transaction);
        $this->setUrl(config('wallet.asanpardakht.url'));
    }

    public function setTransaction($transaction = null): void
    {
        $this->transaction = $transaction;
    }

    public function setCellNumber($cellNumber): void
    {
        $this->cellNumber = $cellNumber;
    }

    public function getCellNumber(): string
    {
        return $this->cellNumber;
    }

    public function hashParam(string $data): bool|string
    {
        return hash('md5', $data, false);
    }

    public function sendInfoToAp(string $hostRequest, string $hostRequestSign, $method, $url): mixed
    {
        $rawResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->withBody(
            '{"hreq":"'.str_replace(
                ['"{', '}"'],
                ['{', '}'],
                json_encode($hostRequest, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK),
            ).'","hsign":"'.$hostRequestSign.'","ver":"1.9.2"}',
            'application/json'
        )->$method(
            $url
        );

        return json_decode($rawResponse->body(), true);
    }

    /**
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
     * @param  mixed|string  $environment
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

    public function setUrl(string $url): AbstractProvider
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    protected function log(string $message, array $params = [], string $level = 'debug'): void
    {
        $reflect = new ReflectionClass($this);
        $provider = strtolower(str_replace('Provider', '', $reflect->getShortName()));

        $message = $provider.': '.$message;

        LaraWallet::log($message, $params, $level);
    }

    public static function generalExceptionResponse(
        int $errorCode = null,
        $message = null,
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
    ): JsonResponse {
        return response()->json([
            'code' => $errorCode,
            'message' => $message,
            'x_track_id' => resolve(env('XLOG_TRACK_ID_KEY', 'xTrackId')),
        ], $statusCode);
    }

    public static function generalResponse(
        int $code = 0,
        string $value = null,
        array $result = null,
        int $statusCode = Response::HTTP_OK,
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'value' => $value,
            'result' => $result,
            'x_track_id' => resolve(env('XLOG_TRACK_ID_KEY', 'xTrackId')),
        ], $statusCode);
    }

    public static function generalViewErrorResponse($view, $withErrors): Factory|View|Application
    {
        return view($view)->withErrors([$withErrors]);
    }

    protected function getWalletTransactionId(string $seq = 'ap_trans_id'): int
    {
        return Serial::getNextVal($seq);
    }
}
