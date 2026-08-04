<?php

namespace App\Jobs;

use App\Models\ProductUpload;
use App\Services\ItemCsvImporter;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessProductUploadChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    // Waiting for another import to release the write lock is not a failure.
    // Real processing failures remain bounded by maxExceptions.
    public $tries = 0;

    public $maxExceptions = 3;

    protected int $uploadId;

    protected int $startByte;

    protected int $chunkSize;

    public function __construct(int $uploadId, int $startByte, int $chunkSize)
    {
        $this->uploadId = $uploadId;
        $this->startByte = $startByte;
        $this->chunkSize = max(1, $chunkSize);
        $this->onQueue('imports');
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(ItemCsvImporter $importer): void
    {
        $upload = ProductUpload::find($this->uploadId);
        if (! $upload || $upload->status === 'failed') {
            return;
        }

        $lock = Cache::lock('product-import-write-lock', $this->timeout + 60);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $path = storage_path('app/'.$upload->file_path);
            $result = $importer->importChunk($path, $this->startByte, $this->chunkSize);

            $upload->increment('processed_rows', $result['processed']);
            $upload->increment('imported_count', $result['imported']);
            $upload->increment('skipped_count', $result['skipped']);
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        $upload = ProductUpload::find($this->uploadId);
        if (! $upload) {
            return;
        }

        Log::error('Product CSV chunk import failed', [
            'upload_id' => $upload->id,
            'message' => $e->getMessage(),
            'start_byte' => $this->startByte,
        ]);
    }
}
