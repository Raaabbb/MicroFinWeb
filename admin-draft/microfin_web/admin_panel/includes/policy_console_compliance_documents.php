<?php

require_once __DIR__ . '/policy_console_system_defaults.php';

if (!function_exists('policy_console_compliance_documents_setting_key')) {
    function policy_console_compliance_documents_setting_key(): string
    {
        return 'policy_console_compliance_documents';
    }
}

if (!function_exists('policy_console_compliance_documents_requirement_values')) {
    function policy_console_compliance_documents_requirement_values(): array
    {
        return ['required', 'conditional', 'not_needed'];
    }
}

if (!function_exists('policy_console_compliance_documents_catalog')) {
    function policy_console_compliance_documents_catalog(PDO $pdo): array
    {
        $excludedNames = policy_console_compliance_document_excluded_names();
        $availableNames = [];

        try {
            $statement = $pdo->query("SELECT document_name FROM document_types WHERE is_active = 1 ORDER BY document_name ASC");
            if ($statement instanceof PDOStatement) {
                $availableNames = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
            }
        } catch (Throwable $exception) {
            $availableNames = [];
        }

        $availableNames = array_values(array_filter(
            array_map('strval', $availableNames),
            static function (string $name) use ($excludedNames): bool {
                return $name !== '' && !in_array($name, $excludedNames, true);
            }
        ));

        if ($availableNames === []) {
            foreach (policy_console_compliance_document_categories() as $category) {
                foreach ((array)($category['allowed_document_names'] ?? []) as $documentName) {
                    $name = (string)$documentName;
                    if ($name !== '' && !in_array($name, $excludedNames, true) && !in_array($name, $availableNames, true)) {
                        $availableNames[] = $name;
                    }
                }
            }
        }

        $categories = [];
        $usedNames = [];

        foreach (policy_console_compliance_document_categories() as $category) {
            $options = [];
            foreach ((array)($category['allowed_document_names'] ?? []) as $documentName) {
                $name = (string)$documentName;
                if ($name !== '' && in_array($name, $availableNames, true)) {
                    $options[] = ['document_name' => $name];
                    $usedNames[] = $name;
                }
            }

            $categories[] = [
                'category_key' => (string)$category['category_key'],
                'label' => (string)$category['label'],
                'default_requirement' => (string)$category['default_requirement'],
                'options' => $options,
            ];
        }

        $remainingNames = array_values(array_filter(
            $availableNames,
            static fn(string $name): bool => !in_array($name, $usedNames, true)
        ));

        return $categories;
    }
}

if (!function_exists('policy_console_compliance_documents_defaults')) {
    function policy_console_compliance_documents_defaults(PDO $pdo): array
    {
        return policy_console_compliance_documents_normalize(
            policy_console_compliance_documents_system_defaults(),
            policy_console_compliance_documents_catalog($pdo)
        );
    }
}

if (!function_exists('policy_console_compliance_documents_normalize')) {
    function policy_console_compliance_documents_normalize($payload, array $catalog): array
    {
        $defaults = policy_console_compliance_documents_system_defaults();
        $input = is_array($payload) ? array_replace_recursive($defaults, $payload) : $defaults;

        $normalizeInt = static function ($value, $fallback, int $min = 0, int $max = 3650): int {
            $number = is_numeric($value) ? (float)$value : (float)$fallback;
            return (int)round(min($max, max($min, $number)));
        };

        $inputRows = [];
        if (!empty($input['document_requirements']) && is_array($input['document_requirements'])) {
            foreach ($input['document_requirements'] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $categoryKey = trim((string)($row['category_key'] ?? ''));
                if ($categoryKey !== '') {
                    $inputRows[$categoryKey] = $row;
                }
            }
        }

        $rows = [];
        foreach ($catalog as $category) {
            $categoryKey = (string)$category['category_key'];
            $existingRow = $inputRows[$categoryKey] ?? [];

            $requirement = (string)($existingRow['requirement'] ?? $category['default_requirement'] ?? 'not_needed');
            if (!in_array($requirement, policy_console_compliance_documents_requirement_values(), true)) {
                $requirement = (string)($category['default_requirement'] ?? 'not_needed');
            }

            $acceptedDocumentNames = [];
            if (!empty($existingRow['accepted_document_names']) && is_array($existingRow['accepted_document_names'])) {
                foreach ($existingRow['accepted_document_names'] as $documentName) {
                    $name = trim((string)$documentName);
                    if ($name !== '' && !in_array($name, $acceptedDocumentNames, true)) {
                        $acceptedDocumentNames[] = $name;
                    }
                }
            } elseif (!empty($existingRow['document_options']) && is_array($existingRow['document_options'])) {
                foreach ($existingRow['document_options'] as $option) {
                    if (!is_array($option) || empty($option['is_accepted'])) {
                        continue;
                    }

                    $name = trim((string)($option['document_name'] ?? $option['label'] ?? ''));
                    if ($name !== '' && !in_array($name, $acceptedDocumentNames, true)) {
                        $acceptedDocumentNames[] = $name;
                    }
                }
            }

            $documentOptions = [];
            $normalizedAcceptedNames = [];
            foreach ((array)($category['options'] ?? []) as $option) {
                $documentName = trim((string)($option['document_name'] ?? ''));
                if ($documentName === '') {
                    continue;
                }

                $isAccepted = $requirement === 'not_needed'
                    ? false
                    : ($acceptedDocumentNames === [] || in_array($documentName, $acceptedDocumentNames, true));

                if ($isAccepted) {
                    $normalizedAcceptedNames[] = $documentName;
                }

                $documentOptions[] = [
                    'document_name' => $documentName,
                    'is_accepted' => $isAccepted,
                ];
            }

            $rows[] = [
                'category_key' => $categoryKey,
                'label' => (string)$category['label'],
                'requirement' => $requirement,
                'accepted_document_names' => $normalizedAcceptedNames,
                'document_options' => $documentOptions,
            ];
        }

        return [
            'document_requirements' => $rows,
        ];
    }
}

if (!function_exists('policy_console_compliance_documents_load')) {
    function policy_console_compliance_documents_load(PDO $pdo, string $tenantId): array
    {
        $catalog = policy_console_compliance_documents_catalog($pdo);
        $raw = admin_get_system_setting($pdo, $tenantId, policy_console_compliance_documents_setting_key(), '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return policy_console_compliance_documents_normalize($decoded, $catalog);
            }
        }

        return policy_console_compliance_documents_defaults($pdo);
    }
}

if (!function_exists('policy_console_compliance_documents_build_from_post')) {
    function policy_console_compliance_documents_build_from_post(array $source, PDO $pdo): array
    {
        $catalog = policy_console_compliance_documents_catalog($pdo);
        $selectedDocs = isset($source['pcd_docs']) && is_array($source['pcd_docs'])
            ? $source['pcd_docs']
            : [];

        $documentRequirements = [];
        foreach ($catalog as $category) {
            $categoryKey = (string)$category['category_key'];
            $acceptedNames = isset($selectedDocs[$categoryKey]) && is_array($selectedDocs[$categoryKey])
                ? array_values(array_unique(array_map('strval', $selectedDocs[$categoryKey])))
                : [];

            $documentRequirements[] = [
                'category_key' => $categoryKey,
                'requirement' => 'required', // Always set to required since the column was removed
                'accepted_document_names' => $acceptedNames,
            ];
        }

        $payload = [
            'document_requirements' => $documentRequirements,
        ];

        return policy_console_compliance_documents_normalize($payload, $catalog);
    }
}
