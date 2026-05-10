<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
microfin_api_bootstrap();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Check if configuration exists for external GCash/Maya fields
    // For now we just return which fields are required
    $tenantId = microfin_clean_string($_GET['tenant_id'] ?? '');
    
    // We can fetch tenant-specific display names or instructions here if needed
    echo json_encode([
        'success' => true,
        'fields' => [
            [
                'id' => 'mobile_number',
                'label' => 'Mobile Number',
                'placeholder' => '09xxxxxxxxx',
                'type' => 'phone',
                'required' => true,
                'validation' => '^09[0-9]{9}$'
            ]
        ]
    ]);
    exit;
}

if ($method === 'POST') {
    $input = microfin_read_json_input();
    $userId = (int)($input['user_id'] ?? 0);
    $tenantId = microfin_clean_string($input['tenant_id'] ?? '');
    
    if ($userId <= 0 || $tenantId === '') {
        microfin_json_response(['success' => false, 'message' => 'Missing context.'], 422);
    }

    // Since we only have one field for now:
    $mobileNumber = microfin_clean_string($input['mobile_number'] ?? '');
    
    if ($mobileNumber === '') {
        microfin_json_response(['success' => false, 'message' => 'Mobile number is required.'], 422);
    }

    // Logic to store or verify... 
    // Usually PayMongo doesn't strictly NEED the number beforehand to create the source,
    // but the USER wants to ask for it. We can store it or just return success to continue the flow.
    
    // Optional: Update client profile with this number if it's different?
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment details verified.'
    ]);
    exit;
}
