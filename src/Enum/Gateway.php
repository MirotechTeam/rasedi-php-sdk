<?php

namespace Rasedi\Sdk\Enum;

enum Gateway: string
{
    case FIB = 'FIB';
    case ZAIN = 'ZAIN';
    case ASIA_PAY = 'ASIA_PAY';
    case FAST_PAY = 'FAST_PAY';
    case NASS_WALLET = 'NASS_WALLET';
    case CREDIT_CARD = 'CREDIT_CARD';
}
