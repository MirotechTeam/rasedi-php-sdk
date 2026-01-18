<?php

namespace Rasedi\Sdk;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Rasedi\Sdk\Interfaces\ICancelPaymentResponse;
use Rasedi\Sdk\Interfaces\ICancelPaymentResponseBody;
use Rasedi\Sdk\Interfaces\ICreatePayment;
use Rasedi\Sdk\Interfaces\ICreatePaymentResponse;
use Rasedi\Sdk\Interfaces\ICreatePaymentResponseBody;
use Rasedi\Sdk\Interfaces\HttpResponse;
use Rasedi\Sdk\Interfaces\IPaymentDetailsResponse;
use Rasedi\Sdk\Interfaces\IPaymentDetailsResponseBody;
use Rasedi\Sdk\Interfaces\IVerifyPayload;
use RuntimeException;

final class PaymentClient
{
    private readonly Auth $authenticator;
    private readonly ClientInterface $httpClient;
    private readonly string $baseUrl;

    private int $upstreamVersion = 1;
    private bool $isTest = true;
    /** @var array<int, array{id: string, key: string}> */
    private array $publicKeys = [];

    public function __construct(
        string $privateKey,
        string $secretKey,
        ?string $baseUrl = null,
        ?ClientInterface $httpClient = null
    ) {
        $this->authenticator = new Auth($privateKey, $secretKey);
        $this->isTest = str_contains($secretKey, 'test');
        $this->baseUrl = $baseUrl ? $this->trimBaseUrl($baseUrl) : Constant::API_BASE_URL;
        $this->httpClient = $httpClient ?? new Client(['timeout' => 10.0]);
    }

    private function buildRelativeUrl(string $path): string
    {
        $mode = $this->isTest ? 'test' : 'live';

        return sprintf('/v%d/payment/rest/%s%s', $this->upstreamVersion, $mode, $path);
    }

    /**
     * Generic HTTP helper that signs every request before sending it.
     *
     * @return array<string, mixed>
     */
    private function call(string $path, string $method, ?string $body = null): array
    {
        $relativeUrl = $this->buildRelativeUrl($path);
        $versionedUrl = $this->baseUrl . $relativeUrl;
        $signature = $this->authenticator->makeSignature($method, $relativeUrl);

        $options = [
            'headers' => [
                'x-signature' => $signature,
                'x-id' => $this->authenticator->getKeyId(),
                'Content-Type' => 'application/json',
            ],
            'http_errors' => true,
        ];

        if ($body !== null) {
            $options['body'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $versionedUrl, $options);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to call Rasedi API: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }

        return $this->buildResponseArray($response);
    }

    /**
     * Normalize response to array-based structure for downstream consumers.
     */
    private function buildResponseArray(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode > 209) {
            throw new RuntimeException('Unexpected status: ' . $statusCode);
        }

        $content = (string) $response->getBody();

        try {
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $parsed = $content;
        }

        return [
            'body' => $parsed,
            'headers' => $this->normalizeHeaders($response->getHeaders()),
            'statusCode' => $statusCode,
        ];
    }

    /** Ensure we always return string values for headers. */
    private function normalizeHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $values) {
            $result[$name] = implode(',', $values);
        }

        return $result;
    }

    private function trimBaseUrl(string $hostName): string
    {
        $hostName = trim($hostName);
        if ($hostName === '') {
            return Constant::API_BASE_URL;
        }

        if (!str_starts_with($hostName, 'https://')) {
            if (str_starts_with($hostName, 'http://')) {
                $hostName = 'https://' . substr($hostName, 7);
            } else {
                $hostName = 'https://' . $hostName;
            }
        }

        return rtrim($hostName, '/');
    }

    public function getPublicKeys(): HttpResponse
    {
        $response = $this->call('/get-public-keys', 'GET');
        $body = is_array($response['body']) ? $response['body'] : [];

        return new HttpResponse(
            $body,
            $response['headers'],
            $response['statusCode']
        );
    }

    public function getPaymentByReferenceCode(string $referenceCode): IPaymentDetailsResponse
    {
        $response = $this->call('/status/' . $referenceCode, 'GET');
        $body = $this->ensureArrayPayload($response['body']);

        return new IPaymentDetailsResponse(
            IPaymentDetailsResponseBody::fromArray($body),
            $response['headers'],
            $response['statusCode']
        );
    }

    public function createPayment(ICreatePayment $payload): ICreatePaymentResponse
    {
        try {
            $json = json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to serialize create-payment payload', $exception->getCode(), $exception);
        }

        $response = $this->call('/create', 'POST', $json);
        $body = $this->ensureArrayPayload($response['body']);

        return new ICreatePaymentResponse(
            ICreatePaymentResponseBody::fromArray($body),
            $response['headers'],
            $response['statusCode']
        );
    }

    public function cancelPayment(string $referenceCode): ICancelPaymentResponse
    {
        $response = $this->call('/cancel/' . $referenceCode, 'PATCH');
        $body = $this->ensureArrayPayload($response['body']);

        return new ICancelPaymentResponse(
            ICancelPaymentResponseBody::fromArray($body),
            $response['headers'],
            $response['statusCode']
        );
    }

    /**
     * Verify a webhook-style payload using the cached public keys.
     *
     * @param array<string, mixed>|IVerifyPayload $payload
     */
    public function verify(array|IVerifyPayload $payload): HttpResponse
    {
        if (empty($this->publicKeys)) {
            $publicResponse = $this->getPublicKeys();
            $this->publicKeys = is_array($publicResponse->body) ? $publicResponse->body : [];
        }

        if ($payload instanceof IVerifyPayload) {
            $keyId = $payload->keyId;
            $content = $payload->content;
        } else {
            $keyId = $payload['keyId'] ?? null;
            $content = $payload['content'] ?? null;
        }

        if ($keyId === null) {
            throw new RuntimeException('Internal server error: keyId missing');
        }

        if ($content === null) {
            throw new RuntimeException('Internal server error: empty content');
        }

        $targetKey = $this->findPublicKey($keyId);
        if ($targetKey === null) {
            $publicResponse = $this->getPublicKeys();
            $this->publicKeys = is_array($publicResponse->body) ? $publicResponse->body : [];
            $targetKey = $this->findPublicKey($keyId);

            if ($targetKey === null) {
                throw new RuntimeException('Internal server error: public key not found');
            }
        }

        $payloadParts = explode('.', $content);
        if (count($payloadParts) !== 3) {
            throw new RuntimeException('Invalid token format');
        }

        [$header, $body, $signature] = $payloadParts;
        $signedData = $header . '.' . $body;
        $decodedSignature = $this->base64UrlDecode($signature);

        $publicKey = $targetKey['key'] ?? null;
        if ($publicKey === null) {
            throw new RuntimeException('Public key missing');
        }

        $resource = openssl_pkey_get_public($publicKey);
        if ($resource === false) {
            throw new RuntimeException('Unable to load public key');
        }

        $details = openssl_pkey_get_details($resource);
        $keySizeBytes = (int) ceil(($details['bits'] ?? 0) / 8);

        $derSignature = $this->rawSignatureToDer($decodedSignature, $keySizeBytes);
        $verified = openssl_verify($signedData, $derSignature, $resource, OPENSSL_ALGO_SHA512);

        if ($verified !== 1) {
            throw new RuntimeException('Signature verification failed');
        }

        try {
            $decodedBody = json_decode($this->base64UrlDecode($body), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to decode payload body', $exception->getCode(), $exception);
        }

        return new HttpResponse($decodedBody, [], 200);
    }

    /**
     * Search the cached public keys by id.
     *
     * @param string $keyId
     * @return array{id: string, key: string}|null
     */
    private function findPublicKey(string $keyId): ?array
    {
        foreach ($this->publicKeys as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['id'] ?? '') === $keyId) {
                return $entry;
            }
        }

        return null;
    }

    private function ensureArrayPayload(mixed $body): array
    {
        if (!is_array($body)) {
            throw new RuntimeException('Unexpected response payload');
        }

        return $body;
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder > 0) {
            $input .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 payload');
        }

        return $decoded;
    }

    private function rawSignatureToDer(string $rawSignature, int $keySizeBytes): string
    {
        if ($keySizeBytes <= 0) {
            throw new RuntimeException('Unable to determine EC key size');
        }

        if (strlen($rawSignature) !== $keySizeBytes * 2) {
            throw new RuntimeException('Invalid signature length for ES algorithm');
        }

        $r = substr($rawSignature, 0, $keySizeBytes);
        $s = substr($rawSignature, $keySizeBytes);

        $r = $this->addLeadingZero($r);
        $s = $this->addLeadingZero($s);

        $sequenceLength = strlen($r) + strlen($s) + 4;

        return chr(0x30)
            . chr($sequenceLength)
            . chr(0x02)
            . chr(strlen($r))
            . $r
            . chr(0x02)
            . chr(strlen($s))
            . $s;
    }

    private function addLeadingZero(string $value): string
    {
        if ($value === '') {
            return "\x00";
        }

        if (ord($value[0]) & 0x80) {
            return "\x00" . $value;
        }

        $trimmed = ltrim($value, "\x00");

        return $trimmed === '' ? "\x00" : $trimmed;
    }
}
