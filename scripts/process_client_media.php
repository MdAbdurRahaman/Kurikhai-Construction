<?php
/**
 * Client Media Processing Script - Tabeeb Contractor
 * 
 * Safely extracts authentic, high-quality responsive web assets from client-supplied originals:
 * 1. Full Project Board (1536x1024) -> Web-optimized full board viewer
 * 2. Before column crop -> serangoon-before.jpg/webp
 * 3. Work in Progress column crop -> serangoon-in-progress.jpg/webp
 * 4. Completed column crop -> serangoon-completed.jpg/webp
 * 5. Completed detail card thumbnail -> serangoon-featured-thumb.jpg/webp
 * 6. Renovation Stages Collage (1200x1700) -> Web-optimized composite preserving blurs
 * 7. Project Board Video (15s MP4) -> Web-ready video asset with poster
 */

$mediaDir = dirname(__DIR__) . '/images/client-media';
$outDir   = dirname(__DIR__) . '/images/projects';

if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$boardFile   = $mediaDir . '/WhatsApp Image 2026-10-09 at 7.11.04 PM.jpeg';
$collageFile = $mediaDir . '/WhatsApp Image 2026-10-09 at 8.44.11 PM.jpeg';
$videoFile   = $mediaDir . '/WhatsApp Video 2026-10-09 at 7.11.03 PM.mp4';

echo "=== Processing Client Media Assets ===\n";

if (!file_exists($boardFile)) {
    die("Error: Project board original not found at $boardFile\n");
}
if (!file_exists($collageFile)) {
    die("Error: Collage original not found at $collageFile\n");
}

// 1. Load Board Image
$boardImg = imagecreatefromjpeg($boardFile);
if (!$boardImg) {
    die("Error: Could not load project board JPEG.\n");
}
$bWidth  = imagesx($boardImg);
$bHeight = imagesy($boardImg);
echo "Loaded Board Original: {$bWidth}x{$bHeight}\n";

// Helper to save JPG & WebP
function saveOptimizedImages($resource, string $destBase): void {
    imagejpeg($resource, $destBase . '.jpg', 88);
    if (function_exists('imagewebp')) {
        imagewebp($resource, $destBase . '.webp', 85);
    }
}

// A. Full Board
saveOptimizedImages($boardImg, $outDir . '/serangoon-board-full');
echo "Saved: serangoon-board-full.jpg & .webp\n";

// B. Column Coordinates from 1536x1024 board:
// Header is y=0 to ~210. Columns start at y=210 and end at ~900.
// Column 1 (Before): x=15 to 510, y=210 to 900
// Column 2 (Work in Progress): x=515 to 1015, y=210 to 900
// Column 3 (Completed): x=1020 to 1520, y=210 to 900

$colY = 210;
$colH = 690;

// Column 1: Before
$beforeImg = imagecrop($boardImg, ['x' => 18, 'y' => $colY, 'width' => 490, 'height' => $colH]);
if ($beforeImg) {
    saveOptimizedImages($beforeImg, $outDir . '/serangoon-before');
    imagedestroy($beforeImg);
    echo "Saved: serangoon-before.jpg & .webp\n";
}

// Column 2: Work in Progress
$wipImg = imagecrop($boardImg, ['x' => 522, 'y' => $colY, 'width' => 490, 'height' => $colH]);
if ($wipImg) {
    saveOptimizedImages($wipImg, $outDir . '/serangoon-in-progress');
    imagedestroy($wipImg);
    echo "Saved: serangoon-in-progress.jpg & .webp\n";
}

// Column 3: Completed
$completedImg = imagecrop($boardImg, ['x' => 1026, 'y' => $colY, 'width' => 490, 'height' => $colH]);
if ($completedImg) {
    saveOptimizedImages($completedImg, $outDir . '/serangoon-completed');
    imagedestroy($completedImg);
    echo "Saved: serangoon-completed.jpg & .webp\n";
}

// Featured Thumbnail: Lower panel of Completed column (finished tiled room with wood doors)
// From x=1026, y=575, width=490, height=325
$thumbImg = imagecrop($boardImg, ['x' => 1026, 'y' => 575, 'width' => 490, 'height' => 325]);
if ($thumbImg) {
    saveOptimizedImages($thumbImg, $outDir . '/serangoon-featured-thumb');
    imagedestroy($thumbImg);
    echo "Saved: serangoon-featured-thumb.jpg & .webp\n";
}

imagedestroy($boardImg);

// 2. Load Collage Image (1200x1700)
$collageImg = imagecreatefromjpeg($collageFile);
if ($collageImg) {
    $cWidth  = imagesx($collageImg);
    $cHeight = imagesy($collageImg);
    echo "Loaded Collage Original: {$cWidth}x{$cHeight}\n";
    saveOptimizedImages($collageImg, $outDir . '/renovation-stages-collage');
    imagedestroy($collageImg);
    echo "Saved: renovation-stages-collage.jpg & .webp (Preserving all blurs)\n";
}

// 3. Project Video Asset
if (file_exists($videoFile)) {
    copy($videoFile, $outDir . '/serangoon-board-video.mp4');
    echo "Copied Video: serangoon-board-video.mp4\n";
}

echo "=== Client Media Processing Complete ===\n";
