<?php

if (!function_exists('mf_document_repo_root')) {
    function mf_document_repo_root(): string
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('mf_document_normalize_path')) {
    function mf_document_normalize_path(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('~^[a-z]+://[^/]+/~i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('~\?.*$~', '', $normalized) ?? $normalized;
        $normalized = ltrim($normalized, '/');

        if ($normalized === '' || preg_match('~(^|/)\.\.(/|$)~', $normalized)) {
            return '';
        }

        return $normalized;
    }
}

if (!function_exists('mf_document_candidate_relative_paths')) {
    function mf_document_candidate_relative_paths(string $path): array
    {
        $normalized = mf_document_normalize_path($path);
        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];

        if (str_starts_with($normalized, 'microfin_mobile/uploads/')) {
            $candidates[] = substr($normalized, strlen('microfin_mobile/'));
        }

        if (str_starts_with($normalized, 'microfin_platform/uploads/')) {
            $candidates[] = substr($normalized, strlen('microfin_platform/'));
        }

        if (str_starts_with($normalized, 'uploads/')) {
            $candidates[] = 'microfin_mobile/' . $normalized;
            $candidates[] = 'microfin_platform/' . $normalized;
        }

        return array_values(array_unique(array_filter($candidates, static fn ($item) => is_string($item) && $item !== '')));
    }
}

if (!function_exists('mf_document_resolve_absolute_path')) {
    function mf_document_resolve_absolute_path(string $path): ?string
    {
        $repoRoot = mf_document_repo_root();

        foreach (mf_document_candidate_relative_paths($path) as $relativePath) {
            $absolutePath = $repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($absolutePath)) {
                return $absolutePath;
            }
        }

        return null;
    }
}

if (!function_exists('mf_document_view_url')) {
    function mf_document_view_url(string $path): string
    {
        $normalized = mf_document_normalize_path($path);
        if ($normalized === '') {
            return '';
        }

        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($scriptDir === '') {
            $scriptDir = '/';
        }

        return $scriptDir . '/document_file.php?path=' . rawurlencode($normalized);
    }
}

if (!function_exists('mf_document_attach_url')) {
    function mf_document_attach_url(array $document, string $pathField = 'file_path', string $urlField = 'file_url'): array
    {
        $document[$urlField] = mf_document_view_url((string) ($document[$pathField] ?? ''));
        return $document;
    }
}
