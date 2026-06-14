<?php
// PHP script to safely resize and compress the main website logo
// to prevent loading a giant 2.17 MB image file in the header.

$logoDir = __DIR__ . '/public/storage/images/';
$settingsFile = __DIR__ . '/bootstrap/app.php';

if (!file_exists($settingsFile)) {
    // If run from outside 'core' directory, adjust paths
    $logoDir = __DIR__ . '/core/public/storage/images/';
}

// Check database for active logo filename
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;

$setting = Setting::first();
if (!$setting || !$setting->logo) {
    die("Error: Could not retrieve logo filename from database settings.\n");
}

$logoFile = $setting->logo;
$logoPath = $logoDir . $logoFile;

if (!file_exists($logoPath)) {
    die("Error: Logo file not found at: " . $logoPath . "\n");
}

echo "Found active logo: " . $logoFile . "\n";
echo "Original File Size: " . round(filesize($logoPath) / (1024 * 1024), 2) . " MB\n";

$info = getimagesize($logoPath);
if (!$info) {
    die("Error: Could not read image details.\n");
}

$origWidth = $info[0];
$origHeight = $info[1];
echo "Original Dimensions: " . $origWidth . "x" . $origHeight . "\n";

// Target width for high-density Retina rendering is 450px
$targetWidth = 450;
if ($origWidth <= $targetWidth) {
    die("Info: Logo is already small (width: " . $origWidth . "px). No resizing needed.\n");
}

$targetHeight = round(($origHeight / $origWidth) * $targetWidth);
echo "Target Dimensions: " . $targetWidth . "x" . $targetHeight . "\n";

// Backup original logo
$backupPath = $logoPath . '.bak';
if (!file_exists($backupPath)) {
    if (copy($logoPath, $backupPath)) {
        echo "Successfully backed up original logo to: " . basename($backupPath) . "\n";
    } else {
        die("Error: Could not create backup copy of the logo.\n");
    }
} else {
    echo "Backup already exists: " . basename($backupPath) . "\n";
}

// Load and resize using GD
$srcImg = imagecreatefrompng($logoPath);
if (!$srcImg) {
    // Try other formats if not a true PNG
    $srcImg = imagecreatefromstring(file_get_contents($logoPath));
}

if (!$srcImg) {
    die("Error: Failed to load image from file.\n");
}

$destImg = imagecreatetruecolor($targetWidth, $targetHeight);

// Maintain transparency for PNG
imagealphablending($destImg, false);
imagesavealpha($destImg, true);
$transparent = imagecolorallocatealpha($destImg, 255, 255, 255, 127);
imagefilledrectangle($destImg, 0, 0, $targetWidth, $targetHeight, $transparent);

// Resize
if (imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight)) {
    // Save compressed PNG (compression level 9 is highest)
    if (imagepng($destImg, $logoPath, 9)) {
        clearstatcache();
        echo "Success! Resized and compressed logo saved.\n";
        echo "New File Size: " . round(filesize($logoPath) / 1024, 2) . " KB\n";
    } else {
        echo "Error: Failed to save resized image.\n";
    }
} else {
    echo "Error: Failed to resample image.\n";
}

imagedestroy($srcImg);
imagedestroy($destImg);
