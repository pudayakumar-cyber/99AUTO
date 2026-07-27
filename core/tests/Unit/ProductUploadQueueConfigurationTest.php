<?php

namespace Tests\Unit;

use App\Jobs\ProcessProductUploadChunkJob;
use Tests\TestCase;

class ProductUploadQueueConfigurationTest extends TestCase
{
    public function test_database_reservation_outlasts_the_import_job_timeout(): void
    {
        $job = new ProcessProductUploadChunkJob(1, 0, 500);

        $this->assertGreaterThan(
            $job->timeout,
            config('queue.connections.database.retry_after')
        );
    }

    public function test_lock_contention_has_headroom_without_unlimited_exceptions(): void
    {
        $job = new ProcessProductUploadChunkJob(1, 0, 500);

        $this->assertSame(100, $job->tries);
        $this->assertSame(3, $job->maxExceptions);
    }
}
