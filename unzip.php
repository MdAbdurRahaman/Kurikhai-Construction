<?php
/**
 * Automated Deployment Extractor for Tabeeb Contractor
 */
header('Content-Type: application/json');

$zipFile = __DIR__ . '/website-deploy.zip';

if (!file_exists($zipFile)) {
    echo json_encode([
        'status' => 'skipped',
        'message' => 'website-deploy.zip not found (already extracted)'
    ]);
    exit(0);
}

$zip = new ZipArchive();
$res = $zip->open($zipFile);

if ($res === TRUE) {
    $zip->extractTo(__DIR__);
    $zip->close();
    @unlink($zipFile);
    echo json_encode([
        'status' => 'success',
        'message' => 'Website files extracted successfully!'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to extract zip file. Code: ' . $res
    ]);
}
