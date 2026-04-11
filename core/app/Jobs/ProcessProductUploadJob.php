<?php

namespace App\Jobs;

use App\Models\ProductUpload;
use App\Services\ItemCsvImporter;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessProductUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 5;

    protected $uploadId;
    private const DEFAULT_CHUNK_SIZE = 500;

    public function __construct($uploadId)
    {
        $this->uploadId = $uploadId;
        $this->onQueue('imports');
    }

    private function chunkSize(): int
    {
        $size = (int) env('PRODUCT_IMPORT_CHUNK_SIZE', self::DEFAULT_CHUNK_SIZE);

        return max(50, min(2000, $size));
    }

    public function handle(): void
    {
        $upload = ProductUpload::find($this->uploadId);

        if (! $upload) {
            return;
        }
        if ($upload->status !== 'pending') {
            return;
        }

        $path = storage_path('app/'.$upload->file_path);

        $upload->update([
            'status' => 'processing',
            'total_rows' => ItemCsvImporter::countDataRows($path),
            'processed_rows' => 0,
            'imported_count' => 0,
            'skipped_count' => 0,
            'error_message' => null,
        ]);

        $chunkSize = $this->chunkSize();
        $offsets = ItemCsvImporter::chunkStartBytes($path, $chunkSize);

        if ($offsets === []) {
            $upload->update(['status' => 'completed']);
            return;
        }

        $jobs = array_map(
            fn (int $offset) => new ProcessProductUploadChunkJob((int) $upload->id, $offset, $chunkSize),
            $offsets
        );

        Bus::batch($jobs)
            ->name('product-upload-'.$upload->id)
            ->allowFailures()
            ->then(function (Batch $batch) use ($upload): void {
                $upload->refresh();
                if ($batch->failedJobs > 0) {
                    $upload->update([
                        'status' => 'failed',
                        'error_message' => __('Some chunks failed. Check failed jobs and retry.'),
                    ]);
                } else {
                    $upload->update(['status' => 'completed']);
                }
            })
            ->catch(function (Batch $batch, Throwable $e) use ($upload): void {
                Log::error('Product CSV batch import failed', [
                    'upload_id' => $upload->id,
                    'batch_id' => $batch->id,
                    'message' => $e->getMessage(),
                ]);
                $upload->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            })
            ->onQueue('imports')
            ->dispatch();
    }

    public function failed(Throwable $e): void
    {
        $upload = ProductUpload::find($this->uploadId);
        if (! $upload) {
            return;
        }

        Log::error('Product CSV import failed', [
            'upload_id' => $upload->id,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $upload->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
