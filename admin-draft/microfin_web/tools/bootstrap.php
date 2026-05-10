<?php

function mf_platform_root(): string
{
    return dirname(__DIR__);
}

function mf_platform_path(string $relativePath = ''): string
{
    $normalized = trim(str_replace(['/', chr(92)], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

    if ($normalized === '') {
        return mf_platform_root();
    }

    return mf_platform_root() . DIRECTORY_SEPARATOR . $normalized;
}

function mf_exports_path(string $fileName = ''): string
{
    $exportsDir = mf_platform_path('storage/exports');
    if (!is_dir($exportsDir)) {
        mkdir($exportsDir, 0777, true);
    }

    $normalized = trim(str_replace(['/', chr(92)], DIRECTORY_SEPARATOR, $fileName), DIRECTORY_SEPARATOR);
    if ($normalized === '') {
        return $exportsDir;
    }

    return $exportsDir . DIRECTORY_SEPARATOR . $normalized;
}
