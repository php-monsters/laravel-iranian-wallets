<?php

namespace PhpMonsters\LaraWallet\Provider;

use App\Ship\Enums\LogType;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use JsonException;
use PhpMonsters\LaraWallet\Enums\AsanpardakhtStatusEnum;
use PhpMonsters\LaraWallet\Exception;
use PhpMonsters\Log\Facades\XLog;
use RuntimeException;

/**
 * AsanPardakhtProvider class
 */
class AsanPardakhtProvider extends AbstractProvider
{
    public const POST_METHOD = 'POST';

    protected bool $refundSupport = true;

    protected array $parameters = [];

    /**
     * @throws Exception|JsonException
     */
    public function checkWalletBalance(): JsonResponse|array
    {
        try {
            $hostRequest = $this->prepareJsonString([
                'caurl' => $this->getParameters('callback_url'),
                'mo' => $this->getCellNumber(),
                'hi' => $this->getParameters('host_id'),
                'walet' => 5,
                'htran' => $this->getWalletTransactionId(),
                'hop' => AsanpardakhtStatusEnum::WalletBalanceHop->value,
                'htime' => time(),
                'hkey' => $this->getParameters('api_key'),
            ]);

            $rawResponse = $this->sendInfoToAp(
                $hostRequest,
                $this->signRequest($hostRequest),
                self::POST_METHOD,
                $this->getUrl()
            );
            $responseJson = json_decode($rawResponse['hresp'], false, 512, JSON_THROW_ON_ERROR);

            if ($responseJson->st !== 1100) {
                $credit = 0;

                if (property_exists($responseJson, 'wball')) {
                    $credit = $responseJson->wball;
                }

                return self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                    value: $credit,
                );
            }

            XLog::emergency('asan pardakht wallet service check balance failure', $rawResponse);

            throw new RuntimeException();
        } catch (ClientException|\Exception $exception) {
            $exceptionMessage = $this->getBalanceWalletError($exception);
            throw new Exception(
                (json_decode($exceptionMessage->content(), false, 512, JSON_THROW_ON_ERROR))->message,
                $exceptionMessage->getStatusCode());
        }
    }

    /**
     * @throws JsonException
     */
    public function prepareJsonString(array $data): string|array|false
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function signRequest(string $input): string
    {
        $binary_signature = '';

        openssl_sign(
            $input,
            $binary_signature,
            Storage::disk('private')->get('APWalletPrivate.pem'),
            OPENSSL_ALGO_SHA256
        );

        return '1#1#'.base64_encode($binary_signature);
    }

    public function getBalanceWalletError(\Exception $exception): JsonResponse
    {
        if (method_exists($exception, 'getResponse') && ! empty($exception->getResponse())) {
            $errorJson = json_decode($exception->getResponse()->getBody()->getContents());
            $errorMsg = ! is_null($errorJson) && property_exists(
                $errorJson,
                'description'
            ) ? $errorJson->description : $exception->getMessage();
        } else {
            $errorMsg = $exception->getMessage();
        }

        XLog::emergency('wallet service check balance failure'.' '.' message: '.$errorMsg);

        return self::generalExceptionResponse(
            AsanpardakhtStatusEnum::FailedResponse->value,
            'error in wallet service'
        );
    }

    /**
     * @throws JsonException
     */
    public function payByWallet(): JsonResponse|array
    {
        $responseJson = '';

        try {
            $hostRequest = $this->prepareJsonString([
                'caurl' => $this->getParameters('callback_url'),
                'pid' => $this->hashParam($this->getTransaction()->id),
                'ao' => $this->getTransaction()->getPayableAmount(),
                'mo' => $this->getCellNumber(),
                'hi' => $this->getParameters('host_id'),
                'walet' => 5,
                'htran' => $this->getWalletTransactionId(),
                'hop' => AsanpardakhtStatusEnum::PayByWalletHop->value,
                'htime' => time(),
                'stime' => time(),
                'hkey' => $this->getParameters('api_key'),
            ]);

            $hRequestSign = $this->signRequest($hostRequest);
            $rawResponse = $this->sendInfoToAp($hostRequest, $hRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = $this->getHresponseData($rawResponse['hresp']);

            if ($responseJson['st'] == AsanpardakhtStatusEnum::SuccessRequest->value) {
                $this->getTransaction()->setCallBackParameters($responseJson);

                return self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }

            return self::generalResponse(
                code: AsanpardakhtStatusEnum::FailedResponse->value,
                value: $responseJson->stm,
            );
        } catch (ServerException $exception) {
            if ($responseJson != '') {
                $this->reverseWalletPaymentResult();
            }

            $errorJson = json_decode($exception->getResponse()->getBody()->getContents());

            return self::generalExceptionResponse(
                AsanpardakhtStatusEnum::FailedResponse->value,
                $errorJson->description ?? ''
            );
        } catch (\Exception $exception) {
            return self::generalExceptionResponse(
                AsanpardakhtStatusEnum::FailedResponse->value,
                $exception->getMessage()
            );
        }
    }

    /**
     * @throws JsonException
     */
    public function getHresponseData($hresp): mixed
    {
        return json_decode($hresp, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    public function reverseWalletPaymentResult(): mixed
    {
        $time = time();

        $hostRequest = $this->prepareJsonString([
            'caurl' => $this->getParameters('callback_url'),
            'ao' => $this->getTransaction()->getPayableAmount(),
            'mo' => $this->getCellNumber(),
            'hi' => $this->getParameters('host_id'),
            'walet' => 5,
            'htran' => $this->getWalletTransactionId(),
            'hop' => AsanpardakhtStatusEnum::ReverseRequestHop->value,
            'htime' => $time,
            'stime' => $time,
            'hkey' => $this->getParameters('api_key'),
        ]);

        $hostRequestSign = $this->signRequest($hostRequest);

        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = json_decode($rawResponse['hresp'], true, 512, JSON_THROW_ON_ERROR);

            $result = $responseJson['st'];

            //----------------------------------successfully reversed-------------------------------------

            if ($responseJson['st'] === AsanpardakhtStatusEnum::SuccessRequest->value) {
                $this->log('successfully reversed', [], LogType::INFO->value);
                $this->getTransaction()->setCallBackParameters($responseJson);

                $result = self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }
        } catch (ServerException $ex) {
            $this->log($ex->getMessage(), [], 'error');
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            $result = [AsanpardakhtStatusEnum::FailedResponse->value, $errorJson];
        }

        return $result;
    }

    public function walletCharge(): JsonResponse|array
    {
        try {
            $hostRequest = $this->prepareJsonString([
                'caurl' => $this->getTransaction()->callback_url,
                'ao' => $this->getTransaction()->amount,
                'mo' => $this->getCellNumber(),
                'hi' => $this->getParameters('host_id'),
                'walet' => 5,
                'htran' => $this->getWalletTransactionId(),
                'hop' => AsanpardakhtStatusEnum::ChargeWallet->value,
                'htime' => time(),
                'stime' => time(),
                'hkey' => $this->getParameters('api_key'),
            ]);

            $hRequestSign = $this->signRequest($hostRequest);
            $rawResponse = $this->sendInfoToAp($hostRequest, $hRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = $this->getHresponseData($rawResponse['hresp']);

            if ($responseJson['st'] == AsanpardakhtStatusEnum::SuccessRequest->value) {
                return self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                    value: $responseJson['addData']['ipgURL'],
                );
            }

            return [
                AsanpardakhtStatusEnum::FailedResponse->value,
                '',
            ];
        } catch (\Exception $exception) {
            return self::generalExceptionResponse(
                AsanpardakhtStatusEnum::FailedResponse->value,
                $exception->getMessage()
            );
        }
    }

    /**
     * @throws JsonException
     */
    public function verifyWalletPaymentResult(): mixed
    {
        $getCallbackParams = $this->getTransaction()->getCallbackParams();

        $hostRequest = $this->prepareJsonString([
            'ao' => $getCallbackParams['ao'],
            'hi' => $this->getParameters('host_id'),
            'htran' => $getCallbackParams['htran'],
            'hop' => AsanpardakhtStatusEnum::VerifyRequestHop->value,
            'htime' => $getCallbackParams['htime'],
            'stime' => time(),
            'stkn' => $getCallbackParams['stkn'],
            'hkey' => $this->getParameters('api_key'),
        ]);

        $hostRequestSign = $this->signRequest($hostRequest);
        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());

            $responseJson = json_decode($rawResponse['hresp'], false, 512, JSON_THROW_ON_ERROR);

            $result = $responseJson->st;

            //----------------------------------successfully verified-------------------------------------
            if ($responseJson->st == AsanpardakhtStatusEnum::SuccessRequest->value || $responseJson->st == AsanpardakhtStatusEnum::TransactionAlreadyBeenVerified->value) {
                $result = self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }
        } catch (ServerException $ex) {
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

            $result = self::generalExceptionResponse(
                AsanpardakhtStatusEnum::FailedResponse->value,
                $errorJson
            );
        }

        return $result;
    }

    /**
     * @throws JsonException
     */
    public function settleWalletPaymentResult(): mixed
    {
        $getCallbackParams = $this->getTransaction()->getCallbackParams();

        $arrayData = [
            'ao' => $getCallbackParams['ao'],
            'hi' => $this->getParameters('host_id'),
            'htran' => $getCallbackParams['htran'],
            'hop' => AsanpardakhtStatusEnum::SettleRequestHop->value,
            'htime' => $getCallbackParams['htime'],
            'stime' => time(),
            'stkn' => $getCallbackParams['stkn'],
            'hkey' => $this->getParameters('api_key'),
        ];

        $hostRequest = $this->prepareJsonString($arrayData);
        $hostRequestSign = $this->signRequest($hostRequest);

        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = json_decode($rawResponse['hresp']);
            $result = $responseJson->st;

            //---------------------Successfully verified--------------------------
            if ($responseJson->st == AsanpardakhtStatusEnum::SuccessResponse->value || $responseJson->st == AsanpardakhtStatusEnum::TransactionAlreadyBeenSettled->value) {
                $result = self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }
        } catch (ServerException $ex) {
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

            $result = self::generalExceptionResponse(
                AsanpardakhtStatusEnum::FailedResponse->value,
                $errorJson
            );
        }

        return $result;
    }

    /**
     * @throws JsonException
     */
    public function refundWalletPaymentResult(): mixed
    {
        $hostRequest = $this->prepareJsonString([
            'ao' => $this->getTransaction()->getPayableAmount(),
            'mo' => $this->getCellNumber(),
            'hi' => $this->getParameters('host_id'),
            'walet' => 5,
            'htran' => $this->getTransaction()->getCallbackParams()['htran'],
            'hop' => AsanpardakhtStatusEnum::RefundRequestHop->value,
            'htime' => $this->getTransaction()->getCallbackParams()['htime'],
            'stime' => time(),
            'stkn' => $this->getTransaction()->getCallbackParams()['stkn'],
            'hkey' => $this->getParameters('api_key'),
        ]);

        $hostRequestSign = $this->signRequest($hostRequest);

        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = json_decode($rawResponse['hresp'], true);

            $result = $responseJson['st'];

            //----------------------------------successfully reversed-------------------------------------
            if ($responseJson['st'] == AsanpardakhtStatusEnum::SuccessRequest->value) {
                $this->log('successfully reversed', [], LogType::INFO->value);

                $result = self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                    result: $responseJson,
                );
            } else {
                $result = self::generalResponse(
                    code: $result,
                );
            }
        } catch (ServerException $ex) {
            $this->log($ex->getMessage(), [], 'error');
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
            $result = [
                AsanpardakhtStatusEnum::FailedResponse->value,
                $errorJson,
            ];
        }

        return $result;
    }
}
