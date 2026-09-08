<?php

namespace App\Console\Commands;

use App\Models\CustomerLifecycleProfile;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerLifecycleService;
use Illuminate\Console\Command;

class SyncKlaviyoLifecycle extends Command
{
    protected $signature = 'klaviyo:sync-lifecycle
        {--user= : Only rebuild one registered user ID}
        {--chunk=200 : Records processed per database chunk}
        {--dry-run : Report eligible records without changing data or queuing jobs}';

    protected $description = 'Build lifecycle properties from historical customers and orders, then sync Klaviyo profiles';

    public function handle(CustomerLifecycleService $lifecycle): int
    {
        $chunk = max(10, min(1000, (int) $this->option('chunk')));
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        $orders = Order::query()
            ->where('transaction_number', 'like', 'ORD-%')
            ->where('order_status', '!=', 'Canceled')
            ->when($userId, fn ($query) => $query->where('user_id', $userId));
        $users = User::query()->when($userId, fn ($query) => $query->whereKey($userId));

        if ($this->option('dry-run')) {
            $this->info('Eligible orders: '.$orders->count());
            $this->info('Eligible registered users: '.$users->count());

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($orders->count());
        $orders->orderBy('id')->chunkById($chunk, function ($records) use ($lifecycle, $bar): void {
            foreach ($records as $order) {
                $lifecycle->recordOrder($order, false);
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine(2);

        $users->orderBy('id')->chunkById($chunk, function ($records) use ($lifecycle): void {
            foreach ($records as $user) {
                $lifecycle->ensureUserProfile($user, false);
            }
        });

        CustomerLifecycleProfile::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->chunkById($chunk, function ($profiles) use ($lifecycle): void {
                foreach ($profiles as $profile) {
                    $lifecycle->sync($profile);
                }
            });

        $this->info('Lifecycle data rebuilt. Klaviyo profile jobs were queued when the integration is enabled.');

        return self::SUCCESS;
    }
}
