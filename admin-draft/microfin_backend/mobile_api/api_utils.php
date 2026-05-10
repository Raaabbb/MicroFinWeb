<?php

function microfin_api_bootstrap(): void
{
    header('Content-Type: application/json');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        http_response_code(200);
        exit;
    }
}

function microfin_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        microfin_json_response(['success' => false, 'message' => 'Invalid Request'], 405);
    }
}

function microfin_read_json_input(): array
{
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput ?: '[]', true);

    return is_array($decoded) ? $decoded : [];
}

function microfin_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function microfin_clean_string($value): string
{
    return trim((string) ($value ?? ''));
}
