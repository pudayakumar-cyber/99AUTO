<?php

namespace App\Console\Commands;

use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ScaleRecentItemPricesCommand extends Command
{
    protected $signature = 'items:scale-recent-prices
                            {--hours=4 : Only items created in the last N hours}
                            {--since= : Instead of --hours, only items with created_at >= this datetime (e.g. 2026-04-06 14:00:00)}
                            {--factor=0.74 : Multiply discount_price and previous_price by this (or divide when --undo)}
                            {--undo : Reverse one application: divide prices by --factor (e.g. after running scale twice)}
                            {--dry-run : Show how many rows would change, do not save}';

    protected $description = 'Multiply or undo discount_price and previous_price for recently created items';

    public function handle(): int
    {
        $factor = (float) $this->option('factor');
        $dryRun = (bool) $this->option('dry-run');
        $undo = (bool) $this->option('undo');

        if ($factor <= 0) {
            $this->error('Factor must be greater than zero.');

            return self::FAILURE;
        }

        $sinceOpt = $this->option('since');
        if ($sinceOpt) {
            try {
                $since = Carbon::parse($sinceOpt);
            } catch (\Throwable $e) {
                $this->error('Invalid --since datetime: '.$sinceOpt);

                return self::FAILURE;
            }
            $this->info("Window: created_at >= {$since->toDateTimeString()} (--since)");
        } else {
            $hours = max(0, (int) $this->option('hours'));
            $since = now()->subHours($hours);
            $this->info("Window: created_at >= {$since->toDateTimeString()} (last {$hours}h)");
        }

        $query = Item::query()->where('created_at', '>=', $since);
        $count = (clone $query)->count();

        $op = $undo ? 'Divide by' : 'Multiply by';
        $this->info("{$op} factor: {$factor}");
        $this->info("Matching items: {$count}");

        if ($count === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes saved.');

            return self::SUCCESS;
        }

        $updated = 0;

        $query->orderBy('id')->chunkById(200, function ($items) use ($factor, $undo, &$updated) {
            foreach ($items as $item) {
                $dirty = false;

                if ($item->discount_price !== null && $item->discount_price !== '') {
                    $v = (float) $item->discount_price;
                    $item->discount_price = $undo
                        ? round($v / $factor, 2)
                        : round($v * $factor, 2);
                    $dirty = true;
                }

                if ($item->previous_price !== null && $item->previous_price !== '') {
                    $v = (float) $item->previous_price;
                    $item->previous_price = $undo
                        ? round($v / $factor, 2)
                        : round($v * $factor, 2);
                    $dirty = true;
                }

                if ($dirty) {
                    $item->save();
                    $updated++;
                }
            }
        });

        $this->info("Updated {$updated} item(s).");

        return self::SUCCESS;
    }
}
