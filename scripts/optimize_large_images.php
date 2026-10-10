<?php
/**
 * Optimize Heavy Images (Poster & Hero Asset) - Tabeeb Contractor
 * Generates lightweight, crisp WebP and optimized JPEG versions.
 */

$imgDir = dirname(__DIR__) . '/images';

// 1. Optimize tabeeb-poster.jpg (2.72 MiB)
$posterSource = $imgDir . '/tabeeb-poster.jpg';
if (file_exists($posterSource)) {
    echo "Processing $posterSource (" . round(filesize($posterSource)/1024/1024, 2) . " MB)...\n";
    $img = imagecreatefromjpeg($posterSource);
    if ($img) {
        $w = imagesx($img);
        $h = imagesy($img);
        // Resize down to standard high-res web max width 1600 if larger
        if ($w > 1600) {
            $newW = 1600;
            $newH = (int)round(($h / $w) * $newW);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }
        imagewebp($img, $imgDir . '/tabeeb-poster.webp', 82);
        imagejpeg($img, $imgDir . '/tabeeb-poster-opt.jpg', 84);
        imagedestroy($img);
        echo "Created tabeeb-poster.webp (" . round(filesize($imgDir . '/tabeeb-poster.webp')/1024, 1) . " KB)\n";
        echo "Created tabeeb-poster-opt.jpg (" . round(filesize($imgDir . '/tabeeb-poster-opt.jpg')/1024, 1) . " KB)\n";
    }
}

// 2. Optimize hero-bg.png (2.35 MiB)
$heroSource = $imgDir . '/hero-bg.png';
if (file_exists($heroSource)) {
    echo "Processing $heroSource (" . round(filesize($heroSource)/1024/1024, 2) . " MB)...\n";
    $img = imagecreatefrompng($heroSource);
    if ($img) {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > 1920) {
            $newW = 1920;
            $newH = (int)round(($h / $w) * $newW);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }
        imagewebp($img, $imgDir . '/hero-bg.webp', 82);
        imagedestroy($img);
        echo "Created hero-bg.webp (" . round(filesize($imgDir . '/hero-bg.webp')/1024, 1) . " KB)\n";
    }
}

echo "=== Optimization Complete ===\n";
