<?php

namespace App\Console\Commands;

use App\Models\Gallery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class RepairProductGalleryImages extends Command
{
    protected $signature = 'products:repair-gallery-images
        {--item= : Limit the scan to one item ID}
        {--apply : Rename valid images and update gallery records}';

    protected $description = 'Detect gallery files with unsafe extensions and rename valid image data safely';

    public function handle(): int
    {
        $query = Gallery::query()->orderBy('id');
        if ($this->option('item')) {
            $query->where('item_id', (int) $this->option('item'));
        }

        $apply = (bool) $this->option('apply');
        $changed = 0;
        $invalid = 0;

        $query->eachById(function (Gallery $gallery) use ($apply, &$changed, &$invalid): void {
            $filename = basename(trim((string) $gallery->photo));
            $paths = array_values(array_unique([
                Storage::disk('public')->path('images/'.$filename),
                public_path('storage/images/'.$filename),
            ]));
            $source = collect($paths)->first(fn ($path) => is_file($path));

            if (! $source) {
                $invalid++;
                $this->warn("Gallery {$gallery->id}: file not found ({$filename})");

                return;
            }

            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/avif' => 'avif',
                default => null,
            };

            if ($extension === null) {
                $invalid++;
                $this->warn("Gallery {$gallery->id}: invalid content type {$mimeType} ({$filename})");

                return;
            }

            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === $extension) {
                return;
            }

            $newFilename = pathinfo($filename, PATHINFO_FILENAME).'.'.$extension;
            if ($apply) {
                foreach ($paths as $path) {
                    if (! is_file($path)) {
                        continue;
                    }

                    $target = dirname($path).DIRECTORY_SEPARATOR.$newFilename;
                    if (is_file($target)) {
                        $newFilename = pathinfo($filename, PATHINFO_FILENAME).'-'.$gallery->id.'.'.$extension;
                        $target = dirname($path).DIRECTORY_SEPARATOR.$newFilename;
                    }
                    File::move($path, $target);
                }

                $gallery->update(['photo' => $newFilename]);
            }

            $changed++;
            $action = $apply ? 'renamed' : 'would rename';
            $this->line("Gallery {$gallery->id}: {$action} {$filename} -> {$newFilename}");
        });

        $mode = $apply ? 'Applied' : 'Dry run';
        $this->info("{$mode}: {$changed} repairable, {$invalid} invalid or missing.");

        return self::SUCCESS;
    }
}
