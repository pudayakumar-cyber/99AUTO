<?php
// PHP CLI script to compress active images larger than 1MB in storage/images.
// Usage: php core/compress_images.php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;

$setting = Setting::first();
$faviconFilename = $setting ? $setting->favicon : '';

$imageDir = __DIR__ . '/public/storage/images/';
if (!is_dir($imageDir)) {
    die("Error: Image directory not found at: {$imageDir}\n");
}

echo "Scanning for images > 1MB in: {$imageDir}...\n\n";

$directory = new RecursiveDirectoryIterator($imageDir);
$iterator = new RecursiveIteratorIterator($directory);
$files = [];

foreach ($iterator as $info) {
    if ($info->isFile()) {
        $filename = $info->getFilename();
        // Ignore backups and non-image files
        if (strpos($filename, '.bak') !== false) {
            continue;
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            $filesize = $info->getSize();
            if ($filesize > 1024 * 1024) { // > 1MB
                $files[] = [
                    'path' => $info->getPathname(),
                    'name' => $filename,
                    'size' => $filesize
                ];
            }
        }
    }
}

echo "Found " . count($files) . " files larger than 1MB.\n\n";

foreach ($files as $file) {
    $filePath = $file['path'];
    $fileName = $file['name'];
    $originalSize = $file['size'];
    
    echo "Processing: {$fileName} (" . round($originalSize / (1024 * 1024), 2) . " MB)\n";
    
    // Check if it is the favicon
    $isFavicon = ($fileName === $faviconFilename || $fileName === '1629651232pre.png');
    
    $imgDetails = @getimagesize($filePath);
    if (!$imgDetails) {
        echo "  [SKIP] Could not read image details (unsupported or corrupted).\n\n";
        continue;
    }
    
    $origWidth = $imgDetails[0];
    $origHeight = $imgDetails[1];
    $mime = $imgDetails['mime'];
    echo "  Original dimensions: {$origWidth}x{$origHeight} ({$mime})\n";
    
    // Determine target dimensions
    if ($isFavicon) {
        $targetWidth = 64;
        $targetHeight = 64;
        echo "  [FAVICON DETECTED] Target dimensions forced to {$targetWidth}x{$targetHeight}\n";
    } else {
        $maxDimension = 1920;
        if ($origWidth > $maxDimension) {
            $targetWidth = $maxDimension;
            $targetHeight = round(($origHeight / $origWidth) * $targetWidth);
            echo "  Resizing from {$origWidth}x{$origHeight} to {$targetWidth}x{$targetHeight}...\n";
        } else {
            $targetWidth = $origWidth;
            $targetHeight = $origHeight;
            echo "  Dimensions within limit. Compressing only...\n";
        }
    }
    
    // Create backup
    $backupPath = $filePath . '.bak';
    if (!file_exists($backupPath)) {
        if (copy($filePath, $backupPath)) {
            echo "  Backup created: " . basename($backupPath) . "\n";
        } else {
            echo "  [ERROR] Could not create backup copy. Skipping.\n\n";
            continue;
        }
    } else {
        echo "  Backup already exists.\n";
    }
    
    // Load image based on mime type
    $srcImg = null;
    if ($mime === 'image/jpeg') {
        $srcImg = @imagecreatefromjpeg($filePath);
    } elseif ($mime === 'image/png') {
        $srcImg = @imagecreatefrompng($filePath);
    } elseif ($mime === 'image/gif') {
        // Skip animated gifs to avoid breaking animation
        echo "  [SKIP] Skipping GIF file to preserve animation/quality.\n\n";
        continue;
    }
    
    if (!$srcImg) {
        echo "  [ERROR] Failed to load image resource via GD. Skipping.\n\n";
        continue;
    }
    
    // Resample
    $destImg = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($mime === 'image/png') {
        imagealphablending($destImg, false);
        imagesavealpha($destImg, true);
        $transparent = imagecolorallocatealpha($destImg, 255, 255, 255, 127);
        imagefilledrectangle($destImg, 0, 0, $targetWidth, $targetHeight, $transparent);
    }
    
    if (imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight)) {
        $saved = false;
        if ($mime === 'image/jpeg') {
            $saved = imagejpeg($destImg, $filePath, 82); // 82 is highly optimized quality
        } elseif ($mime === 'image/png') {
            $saved = imagepng($destImg, $filePath, 9); // Max compression
        }
        
        if ($saved) {
            clearstatcache();
            $newSize = filesize($filePath);
            $reduction = round((($originalSize - $newSize) / $originalSize) * 100, 2);
            echo "  [SUCCESS] Saved! New size: " . round($newSize / 1024, 2) . " KB (Reduced by {$reduction}%)\n\n";
        } else {
            echo "  [ERROR] Failed to save compressed image.\n\n";
        }
    } else {
        echo "  [ERROR] Failed to resample image.\n\n";
    }
    
    imagedestroy($srcImg);
    imagedestroy($destImg);
}

echo "Image processing completed!\n";
