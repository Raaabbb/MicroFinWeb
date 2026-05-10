<?php
$config = require __DIR__ . '/local_config.php';
$apiKey = $config['GEMINI_API_KEY'] ?? '';
echo "Key: " . substr($apiKey, 0, 5) . "...\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-latest:generateContent?key=" . $apiKey;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Hello, respond with OK']
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if(curl_errno($ch)) {
    echo "Curl error: " . curl_error($ch) . "\n";
}
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
