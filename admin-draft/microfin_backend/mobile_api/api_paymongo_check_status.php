<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$sourceId = $data['source_id'] ?? '';

if (empty($sourceId)) {
    echo json_encode(['success' => false, 'message' => 'Missing source_id']);
    exit;
}

$secretKey = microfin_config('PAYMONGO_SECRET_KEY', '');

function microfin_paymongo_get(string $url, string $secretKey): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
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

function microfin_paymongo_map_checkout_status(array $attributes): array
{
    $sessionStatus = strtolower((string) ($attributes['status'] ?? ''));
    $payments = is_array($attributes['payments'] ?? null) ? $attributes['payments'] : [];
    $paymentIntent = is_array($attributes['payment_intent'] ?? null) ? $attributes['payment_intent'] : [];
    $paymentIntentAttributes = is_array($paymentIntent['attributes'] ?? null) ? $paymentIntent['attributes'] : [];
    $paymentIntentStatus = strtolower((string) ($paymentIntentAttributes['status'] ?? $paymentIntent['status'] ?? ''));
    $lastPaymentError = $paymentIntentAttributes['last_payment_error']['message']
        ?? $paymentIntentAttributes['last_payment_error']['detail']
        ?? '';

    $hasFailedPayment = false;
    foreach ($payments as $payment) {
        $paymentStatus = strtolower((string) ($payment['attributes']['status'] ?? $payment['status'] ?? ''));
        if ($paymentStatus === 'paid') {
            return [
                'status' => 'completed',
                'paymongo' => $paymentIntentStatus !== '' ? $paymentIntentStatus : $sessionStatus,
                'error' => '',
            ];
        }

        if ($paymentStatus === 'failed' || $paymentStatus === 'cancelled') {
            $hasFailedPayment = true;
        }
    }

    if ($paymentIntentStatus === 'succeeded') {
        return [
            'status' => 'completed',
            'paymongo' => $paymentIntentStatus,
            'error' => '',
        ];
    }

    if ($sessionStatus === 'expired' || $hasFailedPayment || ($lastPaymentError !== '' && $paymentIntentStatus !== 'processing')) {
        return [
            'status' => 'failed',
            'paymongo' => $paymentIntentStatus !== '' ? $paymentIntentStatus : $sessionStatus,
            'error' => (string) $lastPaymentError,
        ];
    }

    return [
        'status' => 'pending',
        'paymongo' => $paymentIntentStatus !== '' ? $paymentIntentStatus : $sessionStatus,
        'error' => '',
    ];
}

// Simulation Mode (If no keys available)
if (empty($secretKey)) {
    $mockFile = __DIR__ . "/../../.temp_mocks/$sourceId.json";
    if (file_exists($mockFile)) {
        $mockData = json_decode(file_get_contents($mockFile), true);
        if ($mockData['status'] === 'pending' && (time() - $mockData['created_at']) > 5) {
            $mockData['status'] = 'completed';
            file_put_contents($mockFile, json_encode($mockData));
        }
        echo json_encode(['success' => true, 'status' => $mockData['status']]);
    } else {
        echo json_encode(['success' => true, 'status' => 'completed']);
    }
    exit;
}

if (strpos($sourceId, 'cs_') === 0) {
    $apiResponse = microfin_paymongo_get("https://api.paymongo.com/v1/checkout_sessions/$sourceId", $secretKey);
    $responseData = $apiResponse['json'];
    $attributes = $responseData['data']['attributes'] ?? null;

    if ($apiResponse['http_code'] === 200 && is_array($attributes)) {
        $mapped = microfin_paymongo_map_checkout_status($attributes);
        echo json_encode([
            'success' => true,
            'status' => $mapped['status'],
            'paymongo' => $mapped['paymongo'],
            'message' => $mapped['error'],
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Status check failed: ' . ($responseData['errors'][0]['detail'] ?? $apiResponse['curl_error'] ?: 'Unknown error'),
    ]);
    exit;
}

$apiResponse = microfin_paymongo_get("https://api.paymongo.com/v1/sources/$sourceId", $secretKey);
$responseData = $apiResponse['json'];

if ($apiResponse['http_code'] === 200 && isset($responseData['data']['attributes']['status'])) {
    $payMongoStatus = strtolower((string) $responseData['data']['attributes']['status']);
    $mappedStatus = 'pending';

    if ($payMongoStatus === 'chargeable' || $payMongoStatus === 'paid') {
        $mappedStatus = 'completed';
    } elseif ($payMongoStatus === 'cancelled' || $payMongoStatus === 'expired') {
        $mappedStatus = 'failed';
    }

    echo json_encode([
        'success' => true,
        'status' => $mappedStatus,
        'paymongo' => $payMongoStatus,
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Status check failed: ' . ($responseData['errors'][0]['detail'] ?? $apiResponse['curl_error'] ?: 'Unknown error'),
]);
?>
