<?php

namespace Rasedi\Sdk\Enum;

enum PaymentStatus: string
{
    case TIMED_OUT = 'TIMED_OUT';
    case PENDING = 'PENDING';
    case PAID = 'PAID';
    case CANCELED = 'CANCELED';
    case FAILED = 'FAILED';
}
