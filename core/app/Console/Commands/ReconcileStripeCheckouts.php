<?php

namespace App\Console\Commands;

use App\Models\PaymentSetting;
use App\Services\StripeCheckoutAttemptStore;
use App\Services\StripeOrderCompletion;
use App\Services\StripeCheckoutEffects;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileStripeCheckouts extends Command
{
    protected $signature = 'stripe:reconcile-checkouts {--apply : Finalize successful saved attempts and process pending notifications}';
    protected $description = 'Recover saved Stripe checkouts; preview only unless --apply is supplied';

    public function handle(StripeCheckoutAttemptStore $attempts, StripeOrderCompletion $completion, StripeCheckoutEffects $effects): int
    {
        if (!$this->option('apply')) {
            $this->info('Unfinalized attempts: '.DB::table('stripe_checkout_attempts')->whereNull('order_id')->whereNull('canceled_at')->count());
            $this->info('Pending effects: '.DB::table('stripe_checkout_effects')->where('status', 'pending')->count());
            $this->info('Effects requiring inspection: '.DB::table('stripe_checkout_effects')->whereIn('status', ['claimed', 'review'])->count());
            return self::SUCCESS;
        }
        $settings = PaymentSetting::whereUniqueKeyword('stripe')->firstOrFail()->convertJsonData();
        \Stripe\Stripe::setApiKey($settings['secret']);
        $failed = false;
        DB::table('stripe_checkout_attempts')->whereNull('order_id')->whereNull('canceled_at')->chunkById(100,
            function ($rows) use ($attempts, $completion, &$failed) {
                foreach ($rows as $row) {
                    try {
                        // Lost create responses are replayed with the original idempotency key.
                        $intent = $attempts->provision($row->id,
                            fn ($parameters, $options) => \Stripe\PaymentIntent::create($parameters, $options),
                            fn ($id) => \Stripe\PaymentIntent::retrieve($id));
                        if ($intent->status === 'succeeded') {
                            $completion->complete($intent);
                        } elseif ($intent->status === 'canceled') {
                            $attempts->recordCancellation($intent);
                        } else {
                            $this->line($row->id.': '.$intent->status.' (no paid order created)');
                        }
                    } catch (\Throwable $e) {
                        $failed = true;
                        $this->error($row->id.': reconciliation requires review');
                        \Illuminate\Support\Facades\Log::warning('Stripe checkout reconciliation failed.', ['attempt_id' => $row->id, 'exception' => $e::class]);
                    }
                }
            });
        foreach (DB::table('stripe_checkout_effects')->where('status', 'pending')->distinct()->pluck('order_id') as $id) {
            $effects->process((int) $id);
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
