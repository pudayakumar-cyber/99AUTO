<?php

namespace App\Console\Commands;

use App\Jobs\ConvertWebpToJpg;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ConvertWebpImagesCommand extends Command
{
    protected $signature = 'images:convert-webp
                            {--disk=public : Filesystem disk (default: public)}
                            {--path=images : Root path on that disk to scan recursively}
                            {--limit=0 : Max WebP files to queue (0 = no limit)}
                            {--dry-run : Only list how many files would be queued}
                            {--sync : Run the first conversion synchronously (smoke test)}';

    protected $description = 'Queue jobs to convert WebP files under storage to JPEG (run php artisan queue:work).';

    public function handle(): int
    {
        $diskName = (string) $this->option('disk');
        $root = trim((string) $this->option('path'), '/');
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $sync = (bool) $this->option('sync');

        $disk = Storage::disk($diskName);
        if (! $disk->exists($root) && $root !== '') {
            $this->error("Path [{$root}] does not exist on disk [{$diskName}].");

            return self::FAILURE;
        }

        $files = $root === ''
            ? $disk->allFiles()
            : $disk->allFiles($root);

        $webpFiles = [];
        foreach ($files as $file) {
            if (preg_match('/\.webp$/i', $file)) {
                $webpFiles[] = $file;
            }
        }

        $total = count($webpFiles);
        if ($limit > 0 && $total > $limit) {
            $webpFiles = array_slice($webpFiles, 0, $limit);
        }

        $queued = count($webpFiles);
        $this->info("Found {$total} WebP file(s) under [{$diskName}:{$root}]; queueing {$queued} job(s).");

        if ($dryRun) {
            $this->warn('Dry run: no jobs dispatched.');

            return self::SUCCESS;
        }

        if ($sync && $queued > 0) {
            $first = $webpFiles[0];
            $this->info("Sync test: converting [{$first}] …");
            ConvertWebpToJpg::dispatchSync($first);
            array_shift($webpFiles);
            $this->info('Sync test done. Dispatching remaining jobs…');
        }

        foreach ($webpFiles as $relativePath) {
            ConvertWebpToJpg::dispatch($relativePath);
        }

        $this->info('Done. Start a worker: php artisan queue:work');

        return self::SUCCESS;
    }
}
