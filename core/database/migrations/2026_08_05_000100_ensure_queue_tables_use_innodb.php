<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['jobs', 'job_batches', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table) || $this->engineFor($table) === 'innodb') {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }

    public function down(): void
    {
        // Queue tables must not be reverted to a non-transactional engine.
    }

    private function engineFor(string $table): string
    {
        $engine = DB::table('information_schema.tables')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->value('ENGINE');

        return strtolower((string) $engine);
    }
};
