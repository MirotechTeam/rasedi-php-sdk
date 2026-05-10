<?php

namespace Rasedi\Sdk\Interfaces;

use Rasedi\Sdk\Enum\PaymentStatus;

final class IPaymentHistoryItem
{
    public function __construct(
        public string $referenceCode,
        public PaymentStatus $status,
        public string $amount,
        public ?string $gateway,
        public ?string $paidAt,
        public ?string $payoutAmount,
        public ?string $serviceFeeAmount,
        public ?string $gatewayFeeAmount,
        public ?string $expiresAt
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['referenceCode'],
            PaymentStatus::from($data['status']),
            $data['amount'],
            $data['gateway'] ?? null,
            $data['paidAt'] ?? null,
            $data['payoutAmount'] ?? null,
            $data['serviceFeeAmount'] ?? null,
            $data['gatewayFeeAmount'] ?? null,
            $data['expiresAt'] ?? null
        );
    }
}

final class ICreatePaymentResponseBody
{
    public function __construct(
        public string $referenceCode,
        public string $amount,
        public ?string $paidVia,
        public ?string $paidAt,
        public string $redirectUrl,
        public PaymentStatus $status,
        public ?string $payoutAmount
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['referenceCode'],
            $data['amount'],
            $data['paidVia'] ?? null,
            $data['paidAt'] ?? null,
            $data['redirectUrl'],
            PaymentStatus::from($data['status']),
            $data['payoutAmount'] ?? null
        );
    }
}

final class ICreatePaymentResponse
{
    public function __construct(
        public ICreatePaymentResponseBody $body,
        public array $headers,
        public int $statusCode
    ) {
    }
}

final class IPaymentDetailsResponseBody
{
    /** @param IPaymentHistoryItem[] $history */
    public function __construct(
        public string $referenceCode,
        public string $amount,
        public ?string $paidVia,
        public ?string $paidAt,
        public string $redirectUrl,
        public PaymentStatus $status,
        public ?string $payoutAmount,
        public array $history = []
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $history = [];
        if (isset($data['history']) && is_array($data['history'])) {
            foreach ($data['history'] as $item) {
                $history[] = IPaymentHistoryItem::fromArray($item);
            }
        }

        return new self(
            $data['referenceCode'],
            $data['amount'],
            $data['paidVia'] ?? null,
            $data['paidAt'] ?? null,
            $data['redirectUrl'],
            PaymentStatus::from($data['status']),
            $data['payoutAmount'] ?? null,
            $history
        );
    }
}

final class IPaymentDetailsResponse
{
    public function __construct(
        public IPaymentDetailsResponseBody $body,
        public array $headers,
        public int $statusCode
    ) {
    }
}

final class ICancelPaymentResponseBody
{
    public function __construct(
        public string $referenceCode,
        public string $amount,
        public ?string $paidVia,
        public ?string $paidAt,
        public string $redirectUrl,
        public PaymentStatus $status,
        public ?string $payoutAmount
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['referenceCode'],
            $data['amount'],
            $data['paidVia'] ?? null,
            $data['paidAt'] ?? null,
            $data['redirectUrl'],
            PaymentStatus::from($data['status']),
            $data['payoutAmount'] ?? null
        );
    }
}

final class ICancelPaymentResponse
{
    public function __construct(
        public ICancelPaymentResponseBody $body,
        public array $headers,
        public int $statusCode
    ) {
    }
}

final class IPublicKeyResponseBody
{
    public function __construct(
        public string $id,
        public string $key
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['key']);
    }
}

final class IPublicKeysResponse
{
    /** @param IPublicKeyResponseBody[] $body */
    public function __construct(
        public array $body,
        public array $headers,
        public int $statusCode
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $items = [];
        foreach ($data['body'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = IPublicKeyResponseBody::fromArray($item);
        }

        return new self(
            $items,
            $data['headers'] ?? [],
            $data['statusCode'] ?? 200
        );
    }
}

/** @deprecated Use status checking or webhooks instead. */
final class IVerifyPayload
{
    public function __construct(
        public string $keyId,
        public ?string $content
    ) {
    }
}

/** @deprecated Use status checking instead. */
final class IVerifyPaymentResponseBody
{
    public function __construct(
        public string $referenceCode,
        public PaymentStatus $status,
        public ?string $payoutAmount
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['referenceCode'],
            PaymentStatus::from($data['status']),
            $data['payoutAmount'] ?? null
        );
    }
}

/** @deprecated Use status checking instead. */
final class IVerifyPaymentResponse
{
    public function __construct(
        public IVerifyPaymentResponseBody $body,
        public array $headers,
        public int $statusCode
    ) {
    }
}
