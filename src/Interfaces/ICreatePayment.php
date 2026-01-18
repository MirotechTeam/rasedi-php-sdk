<?php

namespace Rasedi\Sdk\Interfaces;

use Rasedi\Sdk\Enum\Gateway;

final class ICreatePayment
{
    /** @param Gateway[] $gateways */
    public function __construct(
        public string $amount,
        public array $gateways,
        public string $title,
        public string $description,
        public string $redirectUrl,
        public string $callbackUrl,
        public bool $collectFeeFromCustomer,
        public bool $collectCustomerEmail,
        public bool $collectCustomerPhoneNumber
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'gateways' => array_map(fn (Gateway $gateway) => $gateway->value, $this->gateways),
            'title' => $this->title,
            'description' => $this->description,
            'redirectUrl' => $this->redirectUrl,
            'callbackUrl' => $this->callbackUrl,
            'collectFeeFromCustomer' => $this->collectFeeFromCustomer,
            'collectCustomerEmail' => $this->collectCustomerEmail,
            'collectCustomerPhoneNumber' => $this->collectCustomerPhoneNumber,
        ];
    }
}
