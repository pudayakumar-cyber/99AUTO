<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OptimizePublicStorageImages extends Command
{
    protected $signature = 'images:optimize-public-storage
        {--apply : Write optimized files. Without this option the command only reports candidates.}
        {--dir= : Directory to scan. Defaults to public/storage/images.}
        {--min-bytes=500000 : Only process files larger than this size.}
        {--max-width=1600 : Resize images wider than this width.}
        {--quality=82 : JPEG/WebP quality, from 1 to 100.}';

    protected $description = 'Report or optimize oversized public storage images with backups.';

    public function handle(): int
    {
        if (!extension_loaded('gd')) {
            $this->error('The PHP GD extension is required.');
            return self::FAILURE;
        }

        $dir = $this->option('dir') ?: public_path('storage/images');
        $dir = rtrim((string) $dir, DIRECTORY_SEPARATOR);

        if (!is_dir($dir)) {
            $this->error("Directory not found: {$dir}");
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $minBytes = max(1, (int) $this->option('min-bytes'));
        $maxWidth = max(320, (int) $this->option('max-width'));
        $quality = min(100, max(1, (int) $this->option('quality')));

        $files = collect(File::files($dir))
            ->filter(fn ($file) => $file->getSize() >= $minBytes)
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true))
            ->sortByDesc(fn ($file) => $file->getSize());

        if ($files->isEmpty()) {
            $this->info('No oversized images found.');
            return self::SUCCESS;
        }

        $rows = [];
        $optimized = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $path = $file->getPathname();
            $extension = strtolower($file->getExtension());
            $before = $file->getSize();
            $imageInfo = @getimagesize($path);

            if (!$imageInfo) {
                $skipped++;
                continue;
            }

            [$width, $height] = $imageInfo;
            $status = 'candidate';

            if ($extension === 'gif') {
                $status = 'manual-gif';
                $skipped++;
            } elseif ($apply) {
                $backupPath = $path . '.perf-bak';
                if (!file_exists($backupPath)) {
                    File::copy($path, $backupPath);
                }

                $this->optimizeImage($path, $extension, $width, $height, $maxWidth, $quality);
                clearstatcache(true, $path);
                $after = filesize($path) ?: $before;
                $status = $after < $before ? 'optimized' : 'kept';
                $optimized += $after < $before ? 1 : 0;
            }

            $rows[] = [
                'file' => $file->getFilename(),
                'type' => $extension,
                'dimensions' => "{$width}x{$height}",
                'before' => $this->formatBytes($before),
                'after' => $apply && $extension !== 'gif' ? $this->formatBytes(filesize($path) ?: $before) : '-',
                'status' => $status,
            ];
        }

        $this->table(['File', 'Type', 'Dimensions', 'Before', 'After', 'Status'], $rows);

        if (!$apply) {
            $this->warn('Dry run only. Re-run with --apply to optimize JPEG/PNG/WebP files.');
            $this->warn('GIF files are reported as manual-gif and are not modified automatically.');
        } else {
            $this->info("Optimized {$optimized} image(s), skipped {$skipped} image(s). Backups use .perf-bak suffix.");
        }

        return self::SUCCESS;
    }

    private function optimizeImage(string $path, string $extension, int $width, int $height, int $maxWidth, int $quality): void
    {
        $source = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (!$source) {
            return;
        }

        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round($height * ($targetWidth / $width));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        match ($extension) {
            'jpg', 'jpeg' => imagejpeg($canvas, $path, $quality),
            'png' => imagepng($canvas, $path, 9),
            'webp' => imagewebp($canvas, $path, $quality),
        };

        imagedestroy($source);
        imagedestroy($canvas);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
