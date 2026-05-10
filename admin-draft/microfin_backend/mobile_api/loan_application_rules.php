<?php

function microfin_loan_rules_active_loan_statuses(): array
{
    return ['Active', 'Overdue', 'Restructured'];
}

function microfin_loan_rules_open_application_statuses(): array
{
    return [
        'Submitted',
        'Pending',
        'Pending Review',
        'Under Review',
        'Document Verification',
        'Credit Investigation',
        'For Approval',
        'Approved',
    ];
}

function microfin_loan_rules_bind_params(mysqli_stmt $stmt, string $types, array $params): void
{
    $bindValues = [$types];
    foreach ($params as $index => $value) {
        $bindValues[] = &$params[$index];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}

function microfin_loan_rules_default_summary(string $tenantId = ''): array
{
    return [
        'client_found' => false,
        'client_id' => 0,
        'tenant_id' => $tenantId,
        'verification_status' => 'Unverified',
        'credit_limit' => 0.0,
        'used_credit' => 0.0,
        'remaining_credit' => 0.0,
        'active_loans' => [],
        'open_applications' => [],
        'occupied_products' => [],
        'occupied_product_ids' => [],
        'occupied_product_count' => 0,
    ];
}

function microfin_find_client_loan_profile(mysqli $conn, int $userId, string $tenantId): ?array
{
    if ($userId <= 0 || $tenantId === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            client_id,
            user_id,
            tenant_id,
            document_verification_status,
            COALESCE(monthly_income, 0) AS monthly_income,
            COALESCE(credit_limit, 0) AS credit_limit,
            policy_metadata
        FROM clients
        WHERE user_id = ?
          AND tenant_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('is', $userId, $tenantId);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$client) {
        return null;
    }

    $client['client_id'] = (int) ($client['client_id'] ?? 0);
    $client['user_id'] = (int) ($client['user_id'] ?? 0);
    $client['tenant_id'] = trim((string) ($client['tenant_id'] ?? ''));
    $client['document_verification_status'] = trim((string) ($client['document_verification_status'] ?? 'Unverified'));
    $client['credit_limit'] = (float) ($client['credit_limit'] ?? 0);
    $policyMetadata = json_decode($client['policy_metadata'] ?? '{}', true) ?: [];
    $potentialLimit = (float) ($policyMetadata['potential_limit'] ?? 0);
    if ($client['credit_limit'] <= 0 && $potentialLimit > 0) {
        $client['credit_limit'] = $potentialLimit;
    }

    return $client;
}

function microfin_build_client_loan_application_summary(mysqli $conn, array $clientProfile): array
{
    $clientId = (int) ($clientProfile['client_id'] ?? 0);
    $tenantId = trim((string) ($clientProfile['tenant_id'] ?? ''));

    if ($clientId <= 0 || $tenantId === '') {
        return microfin_loan_rules_default_summary($tenantId);
    }

    $summary = [
        'client_found' => true,
        'client_id' => $clientId,
        'tenant_id' => $tenantId,
        'verification_status' => trim((string) ($clientProfile['document_verification_status'] ?? 'Unverified')) ?: 'Unverified',
        'monthly_income' => (float) ($clientProfile['monthly_income'] ?? 0),
        'credit_limit' => (float) ($clientProfile['credit_limit'] ?? 0),
        'used_credit' => 0.0,
        'remaining_credit' => 0.0,
        'active_loans' => [],
        'open_applications' => [],
        'occupied_products' => [],
        'occupied_product_ids' => [],
        'occupied_product_count' => 0,
    ];

    $occupiedProductIds = [];
    $occupiedProducts = [];
    $usedCredit = 0.0;

    $loanStatuses = microfin_loan_rules_active_loan_statuses();
    $loanPlaceholders = implode(', ', array_fill(0, count($loanStatuses), '?'));
    $loanSql = "
        SELECT
            l.loan_id,
            l.application_id,
            l.product_id,
            l.loan_status,
            COALESCE(l.principal_amount, 0) AS exposure_amount,
            COALESCE(lp.product_name, 'Loan Product') AS product_name
        FROM loans l
        LEFT JOIN loan_products lp
            ON lp.product_id = l.product_id
        WHERE l.client_id = ?
          AND l.tenant_id = ?
          AND l.loan_status IN ($loanPlaceholders)
        ORDER BY l.created_at DESC, l.loan_id DESC
    ";

    $loanStmt = $conn->prepare($loanSql);
    if ($loanStmt) {
        $loanParams = array_merge([$clientId, $tenantId], $loanStatuses);
        microfin_loan_rules_bind_params($loanStmt, 'is' . str_repeat('s', count($loanStatuses)), $loanParams);
        $loanStmt->execute();
        $loanResult = $loanStmt->get_result();

        while ($row = $loanResult->fetch_assoc()) {
            $row['loan_id'] = (int) ($row['loan_id'] ?? 0);
            $row['application_id'] = (int) ($row['application_id'] ?? 0);
            $row['product_id'] = (int) ($row['product_id'] ?? 0);
            $row['exposure_amount'] = (float) ($row['exposure_amount'] ?? 0);
            $summary['active_loans'][] = $row;
            $usedCredit += $row['exposure_amount'];

            if ($row['product_id'] > 0) {
                $occupiedProductIds[$row['product_id']] = true;
                $occupiedProducts[$row['product_id']] = [
                    'blocking_type' => 'active',
                    'blocking_status' => trim((string) ($row['loan_status'] ?? 'Active')) ?: 'Active',
                    'message' => 'You already have an active loan for this product.',
                ];
            }
        }

        $loanStmt->close();
    }

    $applicationStatuses = microfin_loan_rules_open_application_statuses();
    $applicationPlaceholders = implode(', ', array_fill(0, count($applicationStatuses), '?'));
    $applicationSql = "
        SELECT
            la.application_id,
            la.product_id,
            la.application_status,
            COALESCE(la.requested_amount, 0) AS exposure_amount,
            COALESCE(lp.product_name, 'Loan Product') AS product_name
        FROM loan_applications la
        LEFT JOIN loan_products lp
            ON lp.product_id = la.product_id
        LEFT JOIN loans linked_loan
            ON linked_loan.application_id = la.application_id
           AND linked_loan.tenant_id = la.tenant_id
        WHERE la.client_id = ?
          AND la.tenant_id = ?
          AND la.application_status IN ($applicationPlaceholders)
          AND linked_loan.loan_id IS NULL
        ORDER BY la.submitted_date DESC, la.application_id DESC
    ";

    $applicationStmt = $conn->prepare($applicationSql);
    if ($applicationStmt) {
        $applicationParams = array_merge([$clientId, $tenantId], $applicationStatuses);
        microfin_loan_rules_bind_params($applicationStmt, 'is' . str_repeat('s', count($applicationStatuses)), $applicationParams);
        $applicationStmt->execute();
        $applicationResult = $applicationStmt->get_result();

        while ($row = $applicationResult->fetch_assoc()) {
            $row['application_id'] = (int) ($row['application_id'] ?? 0);
            $row['product_id'] = (int) ($row['product_id'] ?? 0);
            $row['exposure_amount'] = (float) ($row['exposure_amount'] ?? 0);
            $summary['open_applications'][] = $row;
            $usedCredit += $row['exposure_amount'];

            if ($row['product_id'] > 0) {
                $occupiedProductIds[$row['product_id']] = true;
                if (!isset($occupiedProducts[$row['product_id']])) {
                    $occupiedProducts[$row['product_id']] = [
                        'blocking_type' => 'pending',
                        'blocking_status' => trim((string) ($row['application_status'] ?? 'Pending')) ?: 'Pending',
                        'message' => 'You already have a pending application for this product.',
                    ];
                }
            }
        }

        $applicationStmt->close();
    }

    $summary['used_credit'] = round($usedCredit, 2);
    $summary['remaining_credit'] = round(max(0, $summary['credit_limit'] - $usedCredit), 2);
    $summary['occupied_products'] = $occupiedProducts;
    $summary['occupied_product_ids'] = array_values(array_map('intval', array_keys($occupiedProductIds)));
    $summary['occupied_product_count'] = count($summary['occupied_product_ids']);

    return $summary;
}

function microfin_annotate_loan_products(array $products, array $summary): array
{
    $creditLimit = (float) ($summary['credit_limit'] ?? 0);
    $remainingCredit = (float) ($summary['remaining_credit'] ?? 0);
    $occupiedLookup = [];
    $occupiedProducts = is_array($summary['occupied_products'] ?? null)
        ? $summary['occupied_products']
        : [];

    foreach (($summary['occupied_product_ids'] ?? []) as $productId) {
        $productId = (int) $productId;
        if ($productId > 0) {
            $occupiedLookup[$productId] = true;
        }
    }

    foreach ($products as &$row) {
        $productId = (int) ($row['product_id'] ?? $row['id'] ?? 0);
        $minAmount = (float) ($row['min_amount'] ?? $row['min'] ?? 0);
        $maxAmount = (float) ($row['max_amount'] ?? $row['max'] ?? 0);
        $effectiveMax = $remainingCredit;

        if ($maxAmount > 0 && ($effectiveMax <= 0 || $maxAmount < $effectiveMax)) {
            $effectiveMax = $maxAmount;
        }

        $isOccupied = $productId > 0 && isset($occupiedLookup[$productId]);
        $occupiedState = $productId > 0 && isset($occupiedProducts[$productId]) && is_array($occupiedProducts[$productId])
            ? $occupiedProducts[$productId]
            : [];
        $isAvailable = true;
        $availabilityReason = '';
        $occupiedByType = trim((string) ($occupiedState['blocking_type'] ?? ''));
        $occupiedByStatus = trim((string) ($occupiedState['blocking_status'] ?? ''));

        if ($creditLimit <= 0) {
            $isAvailable = false;
            $availabilityReason = 'No credit limit is available for your account yet.';
        } elseif ($remainingCredit <= 0) {
            $isAvailable = false;
            $availabilityReason = 'Your current loans and pending applications already use your full credit limit.';
        } elseif ($isOccupied) {
            $isAvailable = false;
            if ($occupiedByType === 'active') {
                $availabilityReason = 'You already have an active loan for this product.';
            } elseif ($occupiedByType === 'pending') {
                $availabilityReason = 'You already have a pending application for this product.';
            } else {
                $availabilityReason = 'You already have an active loan or pending application for this product.';
            }
        } elseif ($minAmount > 0 && $effectiveMax > 0 && $effectiveMax < $minAmount) {
            $isAvailable = false;
            $availabilityReason = 'Your remaining limit is below this product minimum amount.';
        }

        $row['occupied_by_existing'] = $isOccupied;
        $row['occupied_by_type'] = $occupiedByType;
        $row['occupied_by_status'] = $occupiedByStatus;
        $row['is_available'] = $isAvailable;
        $row['availability_reason'] = $availabilityReason;
        $row['effective_max_amount'] = round(max(0, $effectiveMax), 2);
        $row['effective_min_amount'] = round($minAmount, 2);
    }
    unset($row);

    return $products;
}

function microfin_build_loan_access_state(array $products, array $summary): array
{
    $creditLimit = (float) ($summary['credit_limit'] ?? 0);
    $remainingCredit = (float) ($summary['remaining_credit'] ?? 0);
    $availableProducts = array_values(array_filter($products, static function ($product): bool {
        return !empty($product['is_available']);
    }));

    $state = [
        'show_notice' => false,
        'title' => '',
        'message' => '',
        'criteria' => [],
        'all_products_occupied' => false,
        'has_available_product' => !empty($availableProducts),
    ];

    if ($creditLimit <= 0) {
        $state['show_notice'] = true;
        $state['title'] = 'No available limit yet';
        $state['message'] = 'Your account currently has a credit limit of zero, so no loan product can move forward yet.';
        $state['criteria'] = [
            'A credit limit must first be assigned to your account.',
            'Once a limit is available, you can apply only within that remaining amount.',
        ];

        return $state;
    }

    if ($remainingCredit <= 0) {
        $state['show_notice'] = true;
        $state['title'] = 'Limit reached for now';
        $state['message'] = 'Your active loans and pending applications already use your full available credit limit.';
        $state['criteria'] = [
            'Active loans and pending applications share the same credit limit.',
            'Finish or clear an existing loan/application to reopen available limit.',
        ];

        return $state;
    }

    if (!empty($products) && empty($availableProducts)) {
        $state['show_notice'] = true;
        $state['title'] = 'All products are currently occupied';
        $state['message'] = 'You already have an active loan or pending application in every available product right now.';
        $state['criteria'] = [
            'Only one active loan or pending application is allowed per product.',
            'Complete or clear one product cycle before applying to that same product again.',
        ];
        $state['all_products_occupied'] = true;
        return $state;
    }

    $multipleLoansEnabled = !empty($summary['rules']['multiple_active_loans_enabled']);
    $hasExistingLoan = (float)($summary['used_credit'] ?? 0) > 0 || (int)($summary['occupied_product_count'] ?? 0) > 0;

    if (!$multipleLoansEnabled && $hasExistingLoan) {
        $state['show_notice'] = true;
        $state['title'] = 'Multiple loans not allowed';
        $state['message'] = 'Your tenant policy currently strictly restricts you to one active loan or application at a time.';
        $state['criteria'] = [
            'Clear your existing active loan or cancel pending applications first.',
            'You cannot hold concurrent loans across different products under this policy.',
        ];
        $state['all_products_occupied'] = true; 
        
        foreach ($products as &$product) {
            $product['is_available'] = false;
            $product['availability_reason'] = 'Multiple active loans are not allowed.';
        }
        unset($product);
    }

    return $state;
}

