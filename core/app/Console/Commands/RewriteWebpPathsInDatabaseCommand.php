<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites stored filenames from .webp to .jpg (run after images:convert-webp + queue worker).
 */
class RewriteWebpPathsInDatabaseCommand extends Command
{
    protected $signature = 'images:rewrite-webp-paths-in-db
                            {--dry-run : Show counts only, no UPDATE}';

    protected $description = 'Replace .webp with .jpg in items.photo, items.thumbnail, and galleries.photo';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $itemsPhoto = DB::table('items')->where('photo', 'like', '%.webp')->count();
        $itemsThumb = DB::table('items')->where('thumbnail', 'like', '%.webp')->count();
        $galleries = DB::table('galleries')->where('photo', 'like', '%.webp')->count();

        $this->table(
            ['Table.column', 'Rows with .webp'],
            [
                ['items.photo', $itemsPhoto],
                ['items.thumbnail', $itemsThumb],
                ['galleries.photo', $galleries],
            ]
        );

        if ($dryRun) {
            $this->warn('Dry run: no changes written.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            DB::statement(
                "UPDATE items SET photo = REPLACE(photo, '.webp', '.jpg'), thumbnail = REPLACE(thumbnail, '.webp', '.jpg') WHERE photo LIKE '%.webp' OR thumbnail LIKE '%.webp'"
            );
            DB::statement(
                "UPDATE galleries SET photo = REPLACE(photo, '.webp', '.jpg') WHERE photo LIKE '%.webp'"
            );
        });

        $this->info('Database paths updated.');

        return self::SUCCESS;
    }
}
