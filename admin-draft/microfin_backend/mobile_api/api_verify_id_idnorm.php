<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey && file_exists(__DIR__ . '/local_config.php')) {
    $config = require __DIR__ . '/local_config.php';
    $apiKey = $config['GEMINI_API_KEY'] ?? '';
}

if ($apiKey === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Gemini API not configured. Please add GEMINI_API_KEY to local_config.php.',
        'requires_manual_entry' => true,
    ]);
    exit;
}

$imageField = isset($_FILES['front_image']) ? 'front_image' : (isset($_FILES['file']) ? 'file' : null);
if (!$imageField) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No ID image was provided for scanning.']);
    exit;
}

$imagePath = $_FILES[$imageField]['tmp_name'];
$imageType = $_FILES[$imageField]['type'];
$imageData = base64_encode(file_get_contents($imagePath));

$prompt = <<<PROMPT
Analyze this image of a Philippine Identity Document (ID).
Extract the following information and return it in valid RAW JSON format only.
Do not include any markdown formatting like ```json.
If a piece of information is missing or unreadable, return null for that field.

Required fields:
1. "full_name": The complete name of the person.
2. "document_number": The unique ID or Card number.
3. "date_of_birth": The birth date normalized to YYYY-MM-DD.
4. "gender": "Male" or "Female".
5. "address_street": Street address.
6. "address_barangay": Barangay.
7. "address_city": City or Municipality.
8. "address_province": Province.
9. "address_postal_code": 4-digit postal code.
10. "id_expiry": Expiry date normalized to YYYY-MM-DD (if visible).

Note: For address, split the full address string into its specific components (street, barangay, city, province, postal_code).
PROMPT;

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                [
                    'inline_data' => [
                        'mime_type' => $imageType,
                        'data' => $imageData
                    ]
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'response_mime_type' => 'application/json'
    ]
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-latest:generateContent?key=" . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP dev

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode([
        'success' => false,
        'message' => 'AI Verification failed (Gate 1). Error Code: ' . $httpCode,
        'requires_manual_entry' => true,
    ]);
    exit;
}

$decoded = json_decode($response, true);
$rawText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Gemini might return text inside markdown even with response_mime_type in some versions
$jsonClean = trim(str_replace(['```json', '```'], '', $rawText));
$extracted = json_decode($jsonClean, true);

if (!$extracted) {
    echo json_encode([
        'success' => false,
        'message' => 'AI was unable to parse the ID details clearly. Please enter manually.',
        'requires_manual_entry' => true,
        'debug' => $rawText
    ]);
    exit;
}

echo json_encode(array_merge(['success' => true], $extracted));
