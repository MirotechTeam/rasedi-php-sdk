<?php

namespace Rasedi\Sdk;

use RuntimeException;

final class Auth
{
    public function __construct(
        private string $privateKey,
        private string $keyId
    ) {
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    public function setKeyId(string $keyId): void
    {
        $this->keyId = $keyId;
    }

    public function makeSignature(string $method, string $relativeUrl): string
    {
        $raw = sprintf('%s || %s || %s', strtoupper($method), $this->keyId, $relativeUrl);

        $resource = openssl_pkey_get_private($this->privateKey);
        if ($resource === false) {
            throw new RuntimeException('Invalid private key provided');
        }

        $details = openssl_pkey_get_details($resource);
        $algo = OPENSSL_ALGO_SHA256;
        if (isset($details['ed25519'])) {
            $algo = 0;
        }

        $signature = '';
        if (!openssl_sign($raw, $signature, $resource, $algo)) {
            throw new RuntimeException('Failed to sign payload');
        }

        return base64_encode($signature);
    }
}
