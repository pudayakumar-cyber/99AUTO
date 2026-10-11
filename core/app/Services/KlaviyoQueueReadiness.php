<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use Throwable;

class KlaviyoQueueReadiness
{
    /** Inspect configuration/schema only; never dispatch a probe job. */
    public function checks(): array
    {
        $name = (string) config('queue.default');
        $configuration = config('queue.connections.'.$name);
        $driver = is_array($configuration) ? ($configuration['driver'] ?? null) : null;
        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            return [['Queue connection', 'FAIL', 'Configure a supported asynchronous queue; sync, null, missing and unknown drivers are not validated for delivery.']];
        }

        $checks = [['Queue connection', 'PASS', $name.' ('.$driver.'); worker health is not checked']];
        if ($driver === 'database') {
            $table = (string) ($configuration['table'] ?? 'jobs');
            try {
                $schema = Schema::connection($configuration['connection'] ?? null);
                $valid = $table !== '' && $schema->hasTable($table)
                    && $schema->hasColumns($table, ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at']);
                $checks[] = ['Queue database table', $valid ? 'PASS' : 'FAIL',
                    $valid ? 'Configured jobs table and required columns exist' : 'Configured jobs table or required columns are missing'];
            } catch (Throwable $exception) {
                // Connection exceptions can contain credentials or host details.
                $checks[] = ['Queue database table', 'FAIL', 'Could not inspect the configured queue database; check its connection and read permissions.'];
            }
        }

        return $checks;
    }
}
