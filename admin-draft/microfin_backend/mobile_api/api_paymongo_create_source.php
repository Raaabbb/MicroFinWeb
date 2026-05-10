<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$amount = (float)($data['amount'] ?? 0);
$method = strtolower(trim((string)($data['payment_method'] ?? 'gcash')));
$userId = (int)($data['user_id'] ?? 0);
$tenantId = trim((string)($data['tenant_id'] ?? ''));
$loanId = (int)($data['loan_id'] ?? 0);

function microfin_paymongo_resolve_method(string $method): array
{
    if ($method === 'paymaya' || $method === 'maya') {
        return [
            'display_name' => 'PayMaya',
            'paymongo_method' => 'paymaya',
            'flow' => 'checkout',
        ];
    }

    if ($method === 'gcash') {
        return [
            'display_name' => 'GCash',
            'paymongo_method' => 'gcash',
            'flow' => 'source',
        ];
    }

    return [];
}

function microfin_paymongo_post(string $url, string $secretKey, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($secretKey . ':'),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'raw_body' => (string) $response,
        'json' => json_decode((string) $response, true) ?? [],
        'curl_error' => $curlError,
    ];
}

$methodConfig = microfin_paymongo_resolve_method($method);
if ($amount <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount.']);
    exit;
}

if ($methodConfig === []) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Unsupported payment method for PayMongo checkout.']);
    exit;
}

// Amount must be in centavos (multiply by 100)
$amountInCents = (int)round($amount * 100);

$secretKey = microfin_config('PAYMONGO_SECRET_KEY', '');

// If NO key is provided in Railway variables, fallback to simulation mode so app doesn't crash
if (empty($secretKey)) {
    $sourceId = ($methodConfig['flow'] === 'checkout' ? 'cs_mock_' : 'src_mock_') . time() . '_' . rand(1000, 9999);
    $mockDir = __DIR__ . '/../../.temp_mocks';
    if (!is_dir($mockDir)) { mkdir($mockDir, 0777, true); }
    file_put_contents("$mockDir/$sourceId.json", json_encode(['status' => 'pending', 'created_at' => time()]));
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $host . dirname($_SERVER['PHP_SELF']);
    $checkoutUrl = "$baseUrl/api_paymongo_mock_portal.php?source=$sourceId&amount=$amount&method={$methodConfig['display_name']}";

    echo json_encode([
        'success' => true,
        'source_id' => $sourceId,
        'checkout_url' => $checkoutUrl,
        'mode' => 'simulated',
        'paymongo_flow' => $methodConfig['flow'],
    ]);
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$baseUrl = $scheme . '://' . $host . dirname($_SERVER['PHP_SELF']);
$successUrl = "$baseUrl/api_paymongo_mock_portal.php?status=success&amount=$amount&method={$methodConfig['display_name']}";
$failedUrl  = "$baseUrl/api_paymongo_mock_portal.php?status=failed&amount=$amount&method={$methodConfig['display_name']}";

if ($methodConfig['flow'] === 'checkout') {
    $referenceNumber = 'LN' . ($loanId > 0 ? $loanId : '0') . '-' . time();
    $description = 'Loan payment';
    if ($loanId > 0) {
        $description .= ' for loan #' . $loanId;
    }

    $payload = [
        'data' => [
            'attributes' => [
                'cancel_url' => $failedUrl,
                'success_url' => $successUrl,
                'description' => $description,
                'line_items' => [[
                    'currency' => 'PHP',
                    'amount' => $amountInCents,
                    'name' => 'Loan Payment',
                    'quantity' => 1,
                    'description' => $description,
                ]],
                'payment_method_types' => [$methodConfig['paymongo_method']],
                'reference_number' => $referenceNumber,
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'loan_id' => (string) $loanId,
                    'user_id' => (string) $userId,
                    'payment_method' => $methodConfig['display_name'],
                ],
            ],
        ],
    ];

    $apiResponse = microfin_paymongo_post('https://api.paymongo.com/v1/checkout_sessions', $secretKey, $payload);
    $responseData = $apiResponse['json'];

    if (in_array($apiResponse['http_code'], [200, 201], true)
        && isset($responseData['data']['id'], $responseData['data']['attributes']['checkout_url'])) {
        echo json_encode([
            'success' => true,
            'source_id' => $responseData['data']['id'],
            'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            'mode' => 'live',
            'paymongo_flow' => 'checkout',
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'PayMongo error: ' . ($responseData['errors'][0]['detail'] ?? $apiResponse['curl_error'] ?: 'Unknown error'),
        'raw' => $responseData,
    ]);
    exit;
}

$payload = [
    'data' => [
        'attributes' => [
            'amount' => $amountInCents,
            'redirect' => [
                'success' => $successUrl,
                'failed' => $failedUrl,
            ],
            'type' => $methodConfig['paymongo_method'],
            'currency' => 'PHP',
        ],
    ],
];

$apiResponse = microfin_paymongo_post('https://api.paymongo.com/v1/sources', $secretKey, $payload);
$responseData = $apiResponse['json'];

if (in_array($apiResponse['http_code'], [200, 201], true)
    && isset($responseData['data']['id'], $responseData['data']['attributes']['redirect']['checkout_url'])) {
    echo json_encode([
        'success' => true,
        'source_id' => $responseData['data']['id'],
        'checkout_url' => $responseData['data']['attributes']['redirect']['checkout_url'],
        'mode' => 'live',
        'paymongo_flow' => 'source',
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'PayMongo error: ' . ($responseData['errors'][0]['detail'] ?? $apiResponse['curl_error'] ?: 'Unknown error'),
    'raw' => $responseData,
]);
?>
