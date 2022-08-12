<?php

namespace App\Packages\wallet\wallet\src\Provider;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Tartan\Log\Facades\XLog;

/**
 *
 */
class AsanPardakhtProvider extends AbstractProvider
{
    public const POST_METHOD = 'POST';

    protected bool $refundSupport = true;
    protected array $parameters = [];

    /**
     * @return float|int|array
     */
    public function checkWalletBalance(): float|int|array
    {
        try {
            $hostId = $this->getParameters('host_id');

            $hostRequest = "{\\\"caurl\\\":\\\"" . $this->getParameters(
                    'callback_url'
                ) . "\\\",\\\"mo\\\":\\\"" . $this->getCellNumber(
                ) . "\\\",\\\"hi\\\":$hostId,\\\"walet\\\":5,\\\"hop\\\":310,\\\"htime\\\":" . time(
                ) . ",\\\"htran\\\":" . random_int(5000, 50000) . time() . ",\\\"hkey\\\":\\\"" . $this->getParameters(
                    'api_key'
                ) . "\\\"}";

            $hostRequestSign = $this->signRequest($hostRequest);

            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());
            $response = json_decode($rawResponse, true);
            $responseJson = json_decode($response["hresp"]);

            $credit = 0;

            if (property_exists($responseJson, 'wball')) {
                $credit = $responseJson->wball / 10;
            }
            return $credit;
        } catch (ClientException|\Exception $exception) {
            return $this->getBalanceWalletError($exception);
        };
    }


    /**
     * sign request
     *
     * @param string $input
     * @param bool $debug
     *
     * @return string
     */
    public function signRequest(string $input): string
    {
        $binary_signature = "";

        if (config('wallet.asanpardakht.debug')) {
            openssl_sign(
                $input,
                $binary_signature,
                config('wallet.asanpardakht.debug_private_key'),
                OPENSSL_ALGO_SHA256
            );
        } else {
            openssl_sign($input, $binary_signature, config('wallet.asanpardakht.private_key'), OPENSSL_ALGO_SHA256);
        }

        return '1#1#' . base64_encode($binary_signature);
    }


    /**
     * @return array
     */
    public function payByWallet(): array
    {
        $responseJson = '';

        try {
            $hostId = $this->getParameters('host_id');

            $hostRequest = "{\\\"caurl\\\":\\\"" . $this->getParameters(
                    'callback_url'
                ) . "\\\", \\\"pid\\\":\\\"" . $this->hashParam(
                    $this->transaction->id
                ) . "\\\", \\\"ao\\\":" . $this->getTransaction()->getPayableAmount(
                ) . ", \\\"mo\\\":\\\"" . $this->getCellNumber(
                ) . "\\\", \\\"hi\\\":$hostId, \\\"walet\\\":5, \\\"htran\\\":" . random_int(5000, 50000) . time(
                ) . ", \\\"hop\\\":243, \\\"htime\\\":" . time() . ", \\\"stime\\\":" . time(
                ) . ", \\\"hkey\\\":\\\"" . $this->getParameters('api_key') . "\\\"}";

            $hRequestSign = $this->signRequest($hostRequest);

            $rawResponse = $this->sendInfoToAp($hostRequest, $hRequestSign, self::POST_METHOD, $this->getUrl());

            $response = json_decode($rawResponse, true);

            $responseJson = json_decode($response["hresp"], true);

            if ($responseJson['st'] == 0) {
                $this->getTransaction()->setCallBackParameters($responseJson);
                return [10001, ''];
            }

            if ($responseJson->st == 1332 || $responseJson->st == 1330) { // access denied : 1332, low credit : 1330
                return [10000, $responseJson->addData->ipgURL];
            } else {
                return [999, $responseJson->stm];
            }
        } catch (ServerException $exception) {
            if ($responseJson != '') {
                $this->reverseWalletPaymentResult();
            }

            $errorJson = json_decode($exception->getResponse()->getBody()->getContents());

            return [999, $errorJson->description ?? ''];
        } catch (\Exception $exception) {
            return [999, $exception->getMessage()];
        }
    }


    /**
     * @return mixed
     */
    public function reverseWalletPaymentResult(): mixed
    {
        $getCallbackParams = $this->getTransaction()->getCallbackParams();
        $hostId = $this->getParameters('host_id');

        $hostRequest = "{\\\"ao\\\":" . $getCallbackParams['ao'] . ", \\\"hi\\\":$hostId, \\\"htran\\\":" . $getCallbackParams['htran'] . ", \\\"hop\\\":2003, \\\"htime\\\":" . $getCallbackParams['htime'] . ", \\\"stkn\\\":\\\"" . $getCallbackParams['stkn'] . "\\\", \\\"stime\\\":" . time(
            ) . ", \\\"hkey\\\":\\\"" . $this->getParameters('api_key') . "\\\"}";

        $hostRequestSign = $this->signRequest($hostRequest);

        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());

            $response = json_decode($rawResponse, true);
            $responseJson = json_decode($response["hresp"]);
            $result = $responseJson->st;

            //----------------------------------successfully reversed-------------------------------------

            if ($responseJson->st == 0) {
                $this->log('successfully reversed', [], 'info');

                $result = [10001, ''];
            }
        } catch (ServerException $ex) {
            $this->log($ex->getMessage(), [], 'error');
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents());
            $result = [999, $errorJson];
        }

        return $result;
    }


    /**
     * @return mixed
     */
    public function verifyWalletPaymentResult(): mixed
    {
        $getCallbackParams = $this->getTransaction()->getCallbackParams();

        $hostId = $this->getParameters('host_id');
        $apiKey = $this->getParameters('api_key');

        $hostRequest = "{\\\"ao\\\":" . $getCallbackParams['ao'] . ", \\\"hi\\\":$hostId, \\\"htran\\\":" . $getCallbackParams['htran'] . ", \\\"hop\\\":2001, \\\"htime\\\":" . $getCallbackParams['htime'] . ", \\\"stkn\\\":\\\"" . $getCallbackParams['stkn'] . "\\\", \\\"stime\\\":" . time(
            ) . ", \\\"hkey\\\":\\\"" . $apiKey . "\\\"}";

        $hostRequestSign = $this->signRequest($hostRequest);

        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());

            $response = json_decode($rawResponse, true);
            $responseJson = json_decode($response["hresp"]);

            $result = $responseJson->st;

//----------------------------------successfully verified-------------------------------------
            if ($responseJson->st == 0 or $responseJson->st == 2102) {
                $result = [10001, ''];
            }
        } catch (ServerException $ex) {
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents());
            $result = [999, $errorJson];
        }

        return $result;
    }


    /**
     * @return mixed
     */
    public function settleWalletPaymentResult(): mixed
    {
        $getCallbackParams = $this->getTransaction()->getCallbackParams();

        $hostId = $this->getParameters('host_id');
        $apiKey = $this->getParameters('api_key');

        $hostRequest = "{\\\"ao\\\":" . $getCallbackParams['ao'] . ", \\\"hi\\\":$hostId, \\\"htran\\\":" . $getCallbackParams['htran'] . ", \\\"hop\\\":2002, \\\"htime\\\":" . $getCallbackParams['htime'] . ", \\\"stkn\\\":\\\"" . $getCallbackParams['stkn'] . "\\\", \\\"stime\\\":" . time(
            ) . ", \\\"hkey\\\":\\\"" . $apiKey . "\\\"}";
        $hostRequestSign = $this->signRequest($hostRequest);


        try {
            $rawResponse = $this->sendInfoToAp($hostRequest, $hostRequestSign, self::POST_METHOD, $this->getUrl());
            $response = json_decode($rawResponse, true);
            $responseJson = json_decode($response["hresp"]);
            $result = $responseJson->st;

            //---------------------Successfully verified--------------------------
            if ($responseJson->st == 0 or $responseJson->st == 2103) {
                $result = [10001, ''];
            }
        } catch (ServerException $ex) {
            $errorJson = json_decode($ex->getResponse()->getBody()->getContents());
            $result = [999, $errorJson];
        }

        return $result;
    }


    /**
     * @param \Exception $exception
     * @return array
     */
    public function getBalanceWalletError(\Exception $exception): array
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

        XLog::emergency('check balance failure' . ' ' . ' message: ' . $errorMsg);

        return [999, ''];
    }
}
