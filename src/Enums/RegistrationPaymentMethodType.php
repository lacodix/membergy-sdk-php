<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Enums;

enum RegistrationPaymentMethodType: string
{
    case DirectDebit = 'directdebit';
    case BankTransfer = 'banktransfer';
}
