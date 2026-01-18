# Rasedi PHP SDK

Unofficial PHP SDK for integrating with Rasedi Payment Services. This library provides a simple, strongly-typed interface for creating payments, checking status, cancelling transitions, and verifying webhooks.

## Requirements

- PHP >= 8.1
- `guzzlehttp/guzzle` ^7.7
- `ext-openssl`
- `ext-json`

## Installation

Install via Composer:

```bash
composer require rasedi/php-sdk
```

## Configuration

You need your **Private Key** (PEM format) and **Secret Key** provided by the Rasedi dashboard.

### Environment Variables

It is recommended to load credentials from environment variables or a `.env` file.

```dotenv
# .env
PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMC4CAQAwBQYDK2Vw...if4+rx\n-----END PRIVATE KEY-----"
SECRET_KEY="live_..."
BASE_URL="https://api.pallawan.com"
```

## Usage

### 1. Initialize the Client

```php
use Rasedi\Sdk\PaymentClient;

$privateKey = "your_private_key_content"; // Or getenv('PRIVATE_KEY')
$secretKey = 'your_secret_key';

$client = new PaymentClient(
    privateKey: $privateKey,
    secretKey: $secretKey,
    baseUrl: 'https://api.pallawan.com' // Optional, defaults to Rasedi API
);
```

### 2. Create a Payment

```php
use Rasedi\Sdk\Interfaces\ICreatePayment;
use Rasedi\Sdk\Enum\Gateway;

$payload = new ICreatePayment(
    amount: '10000', // Amount in smallest unit (e.g., cents/dinars)
    gateways: [Gateway::FIB, Gateway::ZAIN],
    title: 'Order #1234',
    description: 'Payment for digital goods',
    redirectUrl: 'https://your-site.com/return',
    callbackUrl: 'https://your-site.com/webhook',
    collectFeeFromCustomer: true,
    collectCustomerEmail: true,
    collectCustomerPhoneNumber: false
);

try {
    $response = $client->createPayment($payload);
    
    echo "Payment Created:\n";
    echo "Reference: " . $response->body->referenceCode . "\n";
    echo "Redirect URL: " . $response->body->redirectUrl . "\n";
    
    // Redirect user to $response->body->redirectUrl
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### 3. Get Payment Details

Retrieve the current status of a payment using its reference code.

```php
$referenceCode = 'cf002d99-40e0-4dd3-9dd7-19e78333739f';
$details = $client->getPaymentByReferenceCode($referenceCode);

echo "Status: " . $details->body->status->value . "\n"; // e.g., PENDING, SUCCESS, FAILED
```

### 4. Cancel a Payment

```php
$referenceCode = 'cf002d99-40e0-4dd3-9dd7-19e78333739f';
$cancelResponse = $client->cancelPayment($referenceCode);

echo "New Status: " . $cancelResponse->body->status->value . "\n"; // CANCELLED
```

### 5. Verify Webhook Signature

The SDK can verify the `X-Signature` or payload signature sent by Rasedi webhooks to ensure authenticity.

```php
use Rasedi\Sdk\Interfaces\IVerifyPayload;

// Assuming you receive the payload as a JSON object or array
$payloadData = [
    'keyId' => '...',
    'content' => '...' // JWT-like signed content
];

try {
    // This will fetch public keys automatically if needed and verify the signature
    $verification = $client->verify($payloadData);
    
    // $verification->body contains the decoded payment update info
    $param = $verification->body;
    
    echo "Verified Update for: " . $param['referenceCode'] . "\n";
    echo "New Status: " . $param['status'] . "\n";
    
} catch (\Exception $e) {
    // Signature verification failed
    http_response_code(400);
    echo "Invalid signature";
}
```

## Enum Reference

### Gateway
- `Gateway::FIB` - First Iraqi Bank
- `Gateway::ZAIN` - ZainCash

### PaymentStatus
- `PENDING`
- `SUCCESS`
- `FAILED`
- `CANCELLED`
- `EXPIRED`

## License

MIT
