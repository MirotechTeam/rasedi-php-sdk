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
        $isEd25519 = isset($details['ed25519']) || 
                     ($details['type'] === OPENSSL_KEYTYPE_EC && empty($details['ec'])) ||
                     ($details['type'] === -1 && $details['bits'] === 256);

        if ($isEd25519) {
            $algo = 0;
        }

        $signature = '';
        if (!@openssl_sign($raw, $signature, $resource, $algo)) {
            // Fallback for PHP versions (like 8.2) where openssl_sign fails with Ed25519
            if ($isEd25519 && extension_loaded('sodium')) {
                return $this->signWithSodium($raw);
            }
            throw new RuntimeException('Failed to sign payload');
        }

        return base64_encode($signature);
    }

    private function signWithSodium(string $data): string
    {
        $pem = trim(str_replace(['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n", "\r", "\\n"], '', $this->privateKey));
        $decoded = base64_decode($pem);
        if ($decoded === false || strlen($decoded) < 32) {
            throw new RuntimeException('Invalid private key format for sodium fallback');
        }

        // Extract 32-byte seed from PKCS#8 Ed25519.
        // Standard header is 16 bytes: 30 2e 02 01 00 30 05 06 03 2b 65 70 04 22 04 20
        $seed = substr($decoded, -32);

        try {
            $keyPair = sodium_crypto_sign_seed_keypair($seed);
            $secretKey = sodium_crypto_sign_secretkey($keyPair);
            $signature = sodium_crypto_sign_detached($data, $secretKey);

            return base64_encode($signature);
        } catch (\Throwable $e) {
            throw new RuntimeException('Sodium signing failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
