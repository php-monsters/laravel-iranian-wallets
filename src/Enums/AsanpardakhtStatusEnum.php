<?php

namespace PhpMonsters\LaraWallet\Enums;

/**
 *
 */
enum AsanpardakhtStatusEnum: int
{
    //----------------------generate by Asanpardakht-------------------

    case SuccessRequest = 0;
    case InvalidMobile = 1103;
    case RequestUnClear = 1001;
    case Failed = 1002;
    case DuplicateTransaction = 1098;
    case InvalidRequest = 1100;
    case TransactionNotFound = 1111;
    case TransactionInquiry = 2250;
    case InsufficientInventory = 1330;
    case TransactionAlreadyBeenVerified = 2102;
    case TransactionAlreadyBeenSettled = 2103;
    case TransactionAlreadyBeenReversed = 2104;
    case TransactionNotBeenVerified = 2106;
    case AccessDeniedRequest = 1332;
    case WalletBalanceHop = 310;
    case PayByWalletHop = 243;
    case ReverseRequestHop = 244;
    case VerifyRequestHop = 2001;
    case SettleRequestHop = 2002;


    //----------------------generate by code-------------------
    case SuccessResponse = 10001;
    case AccessDeniedResponse = 10000;
    case FailedResponse = 999;

}
