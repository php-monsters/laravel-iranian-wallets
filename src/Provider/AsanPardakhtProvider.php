<?php

namespace PhpMonsters\LaraWallet\Provider;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use PhpMonsters\LaraWallet\Enums\AsanpardakhtStatusEnum;
use PhpMonsters\Log\Facades\XLog;

/**
 * AsanPardakhtProvider class
 */
class AsanPardakhtProvider extends AbstractProvider
{
    public const POST_METHOD = 'POST';

    protected bool $refundSupport = true;
    protected array $parameters = [];


    /**
     * @return JsonResponse|array
     */
    public function checkWalletBalance(): JsonResponse|array
    {
        try {
            $arrayData = [
                "caurl" => $this->getParameters('callback_url'),
                "mo" => $this->getCellNumber(),
                "hi" => $this->getParameters('host_id'),
                "walet" => 5,
                "htran" => random_int(5000, 50000).time(),
                "hop" => AsanpardakhtStatusEnum::WalletBalanceHop->value,
                "htime" => time(),
                "hkey" => $this->getParameters('api_key')
            ];

            $hostRequest = $this->prepareJsonString($arrayData);

            $hostRequestSign = $this->signRequest($hostRequest);
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());

            $responseJson = json_decode($rawResponse["hresp"], false, 512, JSON_THROW_ON_ERROR);

            $credit = 0;

            if (property_exists($responseJson, 'wball')) {
                $credit = $responseJson->wball / 10;
            }

            return self::generalResponse(
                code: AsanpardakhtStatusEnum::SuccessResponse->value,
                value: $credit,
            );
        } catch (ClientException|\Exception $exception) {
            return $this->getBalanceWalletError($exception);
        }
    }


    /**
     * @param  string  $input
     * @return string
     */
    public function signRequest(string $input): string
    {
        $binary_signature = "";

        openssl_sign(
            $input,
            $binary_signature,
            Storage::disk('private')->get('private.pem'),
            OPENSSL_ALGO_SHA256
        );

        return '1#1#'.base64_encode($binary_signature);
    }


    /**
     * @return JsonResponse|array
     */
    public function payByWallet(): JsonResponse|array
    {
        $responseJson = '';

        try {
            $arrayData = [
                "caurl" => $this->getParameters('callback_url'),
                "pid" => $this->hashParam($this->transaction->id),
                "ao" => $this->getTransaction()->getPayableAmount(),
                "mo" => $this->getCellNumber(),
                "hi" => $this->getParameters('host_id'),
                "walet" => 5,
                "htran" => random_int(5000, 50000).time(),
                "hop" => AsanpardakhtStatusEnum::PayByWalletHop->value,
                "htime" => time(),
                "stime" => time(),
                "hkey" => $this->getParameters('api_key')
            ];

            $hostRequest = $this->prepareJsonString($arrayData);

            $hRequestSign = $this->signRequest($hostRequest);

            $rawResponse = $this->sendInfoToAp($hostRequest, $hRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = $this->getHresponseData($rawResponse["hresp"]);

            if ($responseJson['st'] == AsanpardakhtStatusEnum::SuccessRequest->value) {
                $this->getTransaction()->setCallBackParameters($responseJson);

                return self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }

            if ($responseJson->st == AsanpardakhtStatusEnum::AccessDeniedRequest->value || $responseJson->st == AsanpardakhtStatusEnum::InsufficientInventory) { // access denied : 1332, low credit : 1330
                return self::generalResponse(
                    code: AsanpardakhtStatusEnum::AccessDeniedResponse->value,
                    value: $responseJson->addData->ipgURL,
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
     * @return mixed
     * @throws \JsonException
     */
    public function verifyWalletPaymentResult(): mixed
    {
        $getCallbackParams = $this->getTransaction()->getCallbackParams();

        $arrayData = [
            "ao" => $getCallbackParams['ao'],
            "hi" => $this->getParameters('host_id'),
            "htran" => $getCallbackParams['htran'],
            "hop" => AsanpardakhtStatusEnum::VerifyRequestHop->value,
            "htime" => $getCallbackParams['htime'],
            "stime" => time(),
            "stkn" => $getCallbackParams['stkn'],
            "hkey" => $this->getParameters('api_key')
        ];

        $hostRequest = $this->prepareJsonString($arrayData);

        $hostRequestSign = $this->signRequest($hostRequest);
        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());

            $responseJson = json_decode($rawResponse["hresp"]);

            $result = $responseJson->st;

//----------------------------------successfully verified-------------------------------------
            if ($responseJson->st == AsanpardakhtStatusEnum::SuccessRequest->value or $responseJson->st == AsanpardakhtStatusEnum::TransactionAlreadyBeenVerified->value) {
                $result = self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }
        } catch (ServerException $ex) {
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents());

            $result = self::generalExceptionResponse(
                AsanpardakhtStatusEnum::FailedResponse->value,
                $errorJson
            );
        }

        return $result;
    }


    /**
     * @return mixed
     * @throws \JsonException
     */
    public function settleWalletPaymentResult(): mixed
    {
        $getCallbackParams = $this->getTransaction()->getCallbackParams();

        $arrayData = [
            "ao" => $getCallbackParams['ao'],
            "hi" => $this->getParameters('host_id'),
            "htran" => $getCallbackParams['htran'],
            "hop" => AsanpardakhtStatusEnum::SettleRequestHop->value,
            "htime" => $getCallbackParams['htime'],
            "stime" => time(),
            "stkn" => $getCallbackParams['stkn'],
            "hkey" => $this->getParameters('api_key')
        ];

        $hostRequest = $this->prepareJsonString($arrayData);
        $hostRequestSign = $this->signRequest($hostRequest);

        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = json_decode($rawResponse["hresp"]);
            $result = $responseJson->st;

            //---------------------Successfully verified--------------------------
            if ($responseJson->st == AsanpardakhtStatusEnum::SuccessResponse->value or $responseJson->st == AsanpardakhtStatusEnum::TransactionAlreadyBeenSettled->value) {
                $result = self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }
        } catch (ServerException $ex) {
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents());

            $result = self::generalExceptionResponse(
                AsanpardakhtStatusEnum::FailedResponse->value,
                $errorJson
            );
        }

        return $result;
    }


    /**
     * @param  \Exception  $exception
     * @return JsonResponse
     */
    public function getBalanceWalletError(\Exception $exception): JsonResponse
    {
        if (method_exists($exception, 'getResponse') && !empty($exception->getResponse())) {
            $errorJson = json_decode($exception->getResponse()->getBody()->getContents());
            $errorMsg = $errorJson != null && property_exists(
                $errorJson,
                'description'
            ) ? $errorJson->description : $exception->getMessage();
        } else {
            $errorMsg = $exception->getMessage();
        }

        XLog::emergency('check balance failure'.' '.' message: '.$errorMsg);

        return self::generalExceptionResponse(
            AsanpardakhtStatusEnum::FailedResponse->value,
            'error in wallet service'
        );
    }


    /**
     * @param  array  $data
     * @return string|array|false
     * @throws \JsonException
     */
    public function prepareJsonString(array $data): string|array|false
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param $hresp
     * @return mixed
     * @throws \JsonException
     */
    public function getHresponseData($hresp): mixed
    {
        return json_decode($hresp, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return mixed
     */
    public function reverseWalletPaymentResult(): mixed
    {
        $time = time();

        $arrayData = [
            "caurl" => $this->getParameters('callback_url'),
            "ao" => $this->getTransaction()->getPayableAmount(),
            "mo" => $this->getCellNumber(),
            "hi" => $this->getParameters('host_id'),
            "walet" => 5,
            "htran" => random_int(5000, 50000).time(),
            "hop" => AsanpardakhtStatusEnum::ReverseRequestHop->value,
            "htime" => $time,
            "stime" => $time,
            "hkey" => $this->getParameters('api_key')
        ];


        $hostRequest = $this->prepareJsonString($arrayData);

        $hostRequestSign = $this->signRequest($hostRequest);


        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());
            $responseJson = json_decode($rawResponse["hresp"], true);

            $result = $responseJson['st'];

            //----------------------------------successfully reversed-------------------------------------

            if ($responseJson['st'] == AsanpardakhtStatusEnum::SuccessRequest->value) {
                $this->log('successfully reversed', [], 'info');
                $this->getTransaction()->setCallBackParameters($responseJson);

                $result = self::generalResponse(
                    code: AsanpardakhtStatusEnum::SuccessResponse->value,
                );
            }
        } catch (ServerException $ex) {
            $this->log($ex->getMessage(), [], 'error');
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents());
            $result = [AsanpardakhtStatusEnum::FailedResponse->value, $errorJson];
        }

        return $result;
    }
}