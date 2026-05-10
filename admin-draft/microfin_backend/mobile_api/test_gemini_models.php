<?php
$config = require __DIR__ . '/local_config.php';
$apiKey = $config['GEMINI_API_KEY'] ?? '';

$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
$decoded = json_decode($response, true);
if (isset($decoded['models'])) {
    foreach ($decoded['models'] as $model) {
        echo $model['name'] . "\n";
    }
} else {
    echo "No models array found. Response: \n$response";
}
