<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rasedi\Sdk\PaymentClient;

// Helper to load env (same as CreatePayment.php)
function loadEnv(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $result = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) continue;
        $value = trim($matches[2]);
        if ($value === '') { $result[$matches[1]] = ''; continue; }
        if (($value[0] === '"' && $value[-1] === '"') || ($value[0] === '\'' && $value[-1] === '\'')) {
            $value = substr($value, 1, -1);
        }
        $result[$matches[1]] = stripcslashes($value);
    }
    return $result;
}

// 1. Load configuration
$env = loadEnv(__DIR__ . '/../.env');

$privateKey = $env['PRIVATE_KEY'] ?? getenv('PRIVATE_KEY');
$secretKey = $env['SECRET_KEY'] ?? getenv('SECRET_KEY');
$baseUrl = $env['BASE_URL'] ?? 'https://api.rasedi.com';

// Handle inline vs file private key
if (!str_contains($privateKey, 'BEGIN PRIVATE KEY')) {
    // If it doesn't look like a key content, check if it's a file path or defined via PRIVATE_KEY_FILE
    $privateKeyFile = $env['PRIVATE_KEY_FILE'] ?? getenv('PRIVATE_KEY_FILE');
    if ($privateKeyFile && is_file($privateKeyFile)) {
        $privateKey = file_get_contents($privateKeyFile);
    }
}

// 2. Validate args
if ($argc < 2) {
    echo "Usage: php tests/CheckStatus.php <reference_code>\n";
    exit(1);
}
$referenceCode = $argv[1];

// 3. Init client
$client = new PaymentClient(
    privateKey: $privateKey,
    secretKey: $secretKey,
    baseUrl: $baseUrl
);

// 4. Execute
try {
    echo "Checking status for: $referenceCode\n";
    $response = $client->getPaymentByReferenceCode($referenceCode);
    
    echo "Status: " . $response->body->status->value . "\n";
    echo "Amount: " . $response->body->amount . "\n";
    
    echo "\nFull Response:\n";
    print_r($response);

} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    if (method_exists($e, 'getResponse')) {
        $resp = $e->getResponse();
        if ($resp) {
            echo "Response Body: " . $resp->getBody() . "\n";
        }
    }
    exit(1);
}
