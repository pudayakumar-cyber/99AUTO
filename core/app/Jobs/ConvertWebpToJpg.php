<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Throwable;

/**
 * Converts a single WebP file under the public disk to JPEG (keeps WebP unless IMAGE_CONVERT_DELETE_WEBP=true).
 */
class ConvertWebpToJpg implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        public string $relativePath
    ) {
        $queue = env('IMAGE_CONVERT_QUEUE');
        if ($queue !== null && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(): void
    {
        $relativePath = ltrim($this->relativePath, '/');
        if ($relativePath === '' || ! preg_match('/\.webp$/i', $relativePath)) {
            return;
        }

        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_file($fullPath)) {
            Log::warning('ConvertWebpToJpg: source file missing', ['path' => $relativePath]);

            return;
        }

        $newRelativePath = preg_replace('/\.webp$/i', '.jpg', $relativePath);
        if ($newRelativePath === $relativePath) {
            return;
        }

        $quality = (int) env('IMAGE_CONVERT_JPG_QUALITY', 90);
        $quality = max(1, min(100, $quality));

        try {
            $image = Image::make($fullPath);
            $image->encode('jpg', $quality);
            $binary = $image->encoded;
        } catch (Throwable $e) {
            Log::error('ConvertWebpToJpg: encode failed', [
                'path' => $relativePath,
                'message' => $e->getMessage(),
            ]);
            $this->fail($e);

            return;
        }

        if ($binary === null || $binary === '') {
            Log::error('ConvertWebpToJpg: empty output', ['path' => $relativePath]);
            $this->fail(new \RuntimeException('Empty JPEG output'));

            return;
        }

        Storage::disk('public')->put($newRelativePath, $binary);
        $this->mirrorToPublicStorage($newRelativePath, $binary);

        if (filter_var(env('IMAGE_CONVERT_DELETE_WEBP', false), FILTER_VALIDATE_BOOLEAN)) {
            Storage::disk('public')->delete($relativePath);
            $legacy = public_path('storage/'.$relativePath);
            if (is_file($legacy)) {
                @unlink($legacy);
            }
        }
    }

    private function mirrorToPublicStorage(string $relativePath, string $contents): void
    {
        $target = public_path('storage/'.$relativePath);
        $dir = dirname($target);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        File::put($target, $contents);
    }
}
