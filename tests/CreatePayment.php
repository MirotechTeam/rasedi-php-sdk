<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rasedi\Sdk\Enum\Gateway;
use Rasedi\Sdk\Interfaces\ICreatePayment;
use Rasedi\Sdk\PaymentClient;

$env = loadEnv(__DIR__ . '/../.env');

$privateKey = $env['PRIVATE_KEY'] ?? getenv('PRIVATE_KEY');
$secretKey = $env['SECRET_KEY'] ?? getenv('SECRET_KEY');
$privateKeyFile = $env['PRIVATE_KEY_FILE'] ?? getenv('PRIVATE_KEY_FILE');

if (($privateKey === null || $privateKey === false || $privateKey === '') && is_string($privateKeyFile)) {
    if (is_file($privateKeyFile)) {
        $privateKey = file_get_contents($privateKeyFile);
    } else {
        fwrite(STDERR, "PRIVATE_KEY_FILE is set but file does not exist or is unreadable.\n");
        exit(1);
    }
}

if ($privateKey === false || $secretKey === false || $privateKey === null || $secretKey === null) {
    fwrite(STDERR, "PRIVATE_KEY and SECRET_KEY must be set via .env or environment variables.\n");
    exit(1);
}

$baseUrl = $env['BASE_URL'] ?? 'https://api.rasedi.com';
$amount = $env['PAYMENT_AMOUNT'] ?? '10000';
$redirectUrl = $env['PAYMENT_REDIRECT_URL'] ?? 'https://example.com/return';
$callbackUrl = $env['PAYMENT_CALLBACK_URL'] ?? 'https://example.com/webhook';
$title = $env['PAYMENT_TITLE'] ?? 'Local SDK Payment';
$description = $env['PAYMENT_DESCRIPTION'] ?? 'Created via PHP SDK';

$gatewayValues = array_filter(array_map('trim', explode(',', $env['GATEWAYS'] ?? 'FIB,ZAIN')));
$gateways = array_map(fn (string $value) => Gateway::from($value), $gatewayValues);
if ($gateways === []) {
    $gateways = [Gateway::FIB];
}

$client = new PaymentClient(
    privateKey: $privateKey,
    secretKey: $secretKey,
    baseUrl: $baseUrl
);

$payload = new ICreatePayment(
    amount: (string) $amount,
    gateways: $gateways,
    title: $title,
    description: $description,
    redirectUrl: $redirectUrl,
    callbackUrl: $callbackUrl,
    collectFeeFromCustomer: parseBool($env['COLLECT_FEE_FROM_CUSTOMER'] ?? null, true),
    collectCustomerEmail: parseBool($env['COLLECT_CUSTOMER_EMAIL'] ?? null, true),
    collectCustomerPhoneNumber: parseBool($env['COLLECT_CUSTOMER_PHONE_NUMBER'] ?? null, false)
);

try {
    $response = $client->createPayment($payload);
    echo "Reference code: {$response->body->referenceCode}\n";
    echo "Status: {$response->body->status->value}\n";
    echo "Amount: {$response->body->amount}\n";
    echo "\nFull Response:\n";
    print_r($response);
} catch (Throwable $exception) {
    fwrite(STDERR, "Create payment failed: {$exception->getMessage()}\n");
    if (method_exists($exception, 'getResponse')) {
        $response = $exception->getResponse();
        if ($response !== null) {
            fwrite(STDERR, "Response body: " . $response->getBody() . "\n");
        }
    }
    exit(1);
}

/**
 * Load a dot-env style file without extra dependencies.
 *
 * @param string $path
 * @return array<string, string>
 */
function loadEnv(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $result = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
            continue;
        }

        $value = trim($matches[2]);
        if ($value === '') {
            $result[$matches[1]] = '';
            continue;
        }

        if (($value[0] === '"' && $value[-1] === '"') || ($value[0] === '\'' && $value[-1] === '\'')) {
            $value = substr($value, 1, -1);
        }

        $result[$matches[1]] = stripcslashes($value);
    }

    return $result;
}

function parseBool(mixed $value, bool $default): bool
{
    if ($value === null) {
        return $default;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return $default;
    }

    $trueValues = ['1', 'true', 'yes', 'on'];
    $falseValues = ['0', 'false', 'no', 'off'];

    if (in_array($normalized, $trueValues, true)) {
        return true;
    }

    if (in_array($normalized, $falseValues, true)) {
        return false;
    }

    return $default;
}
