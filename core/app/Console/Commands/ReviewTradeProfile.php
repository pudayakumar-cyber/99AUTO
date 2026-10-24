<?php

namespace App\Console\Commands;

use App\Models\CustomerLifecycleProfile;
use App\Services\TradeReviewService;
use Illuminate\Console\Command;

class ReviewTradeProfile extends Command
{
    protected $signature = 'marketing:review-trade {profile : Lifecycle profile ID} {decision : approved or rejected}
        {--expected=pending : Previously reviewed status} {--reviewer= : Operator identifier}
        {--reference= : Business verification case reference, no document contents} {--apply : Persist the reviewed decision}';
    protected $description = 'Preview or record an operator trade review; does not grant prices or subscribe customers';

    public function handle(TradeReviewService $service): int
    {
        if (!ctype_digit((string) $this->argument('profile')) || (int) $this->argument('profile') < 1
            || !in_array($this->argument('decision'), ['approved', 'rejected'], true)) {
            $this->error('Provide a positive lifecycle profile ID and approved or rejected.');
            return self::FAILURE;
        }
        try {
            $profile = CustomerLifecycleProfile::findOrFail((int) $this->argument('profile'));
            if (!$this->option('apply')) {
                $this->table(['Profile', 'Customer', 'Current status', 'Proposed status'], [[
                    $profile->id, $profile->user_id, $profile->trade_review_status, $this->argument('decision'),
                ]]);
                $this->info('Preview only. No changes or sync. Verify business evidence before using --apply.');
                return self::SUCCESS;
            }
            $service->decide($profile->id, $this->argument('decision'), $this->option('expected'),
                (string) $this->option('reviewer'), (string) $this->option('reference'));
            $this->info('Decision recorded; profile sync requested when Klaviyo is enabled. No subscription or pricing changes.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Review could not complete. Check the profile, current status, review reference and migration. Retry the same reference after a dispatch failure.');
            return self::FAILURE;
        }
    }
}
