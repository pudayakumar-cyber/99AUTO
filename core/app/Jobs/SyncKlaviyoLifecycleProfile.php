<?php

namespace App\Jobs;

use App\Models\CustomerLifecycleProfile;
use App\Services\KlaviyoClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncKlaviyoLifecycleProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $profileId)
    {
        $this->onQueue((string) config('services.klaviyo.queue', 'default'));
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(KlaviyoClient $client): void
    {
        if (! $client->enabled()) {
            return;
        }

        $lifecycle = CustomerLifecycleProfile::with(['user.state'])->find($this->profileId);

        if (! $lifecycle) {
            return;
        }

        $user = $lifecycle->user;
        $client->upsertProfile(array_filter([
            'email' => $lifecycle->email,
            'phone_number' => $lifecycle->phone,
            'external_id' => $lifecycle->user_id ? (string) $lifecycle->user_id : null,
            'first_name' => $lifecycle->first_name ?: $user?->first_name,
            'last_name' => $lifecycle->last_name ?: $user?->last_name,
            'location' => array_filter([
                'address1' => $lifecycle->address1,
                'address2' => $lifecycle->address2,
                'city' => $lifecycle->city,
                'region' => $lifecycle->region,
                'country' => $lifecycle->country,
                'zip' => $lifecycle->postal_code,
            ]),
        ]), array_filter([
            'primary_vehicle_year' => $lifecycle->primary_vehicle_year,
            'primary_vehicle_make' => $lifecycle->primary_vehicle_make,
            'primary_vehicle_model' => $lifecycle->primary_vehicle_model,
            'buyer_type' => $lifecycle->buyer_type,
            'lifecycle_status' => $lifecycle->lifecycle_status,
            'last_purchase_category' => $lifecycle->last_purchase_category,
            'last_purchase_at' => $lifecycle->last_purchase_at?->toAtomString(),
            'next_maintenance_due_date' => $lifecycle->next_maintenance_due_date?->format('Y-m-d'),
            'referral_code' => $lifecycle->referral_code,
            'referral_status' => $lifecycle->referral_status,
            'trade_review_status' => $lifecycle->trade_review_status,
            'total_orders' => $lifecycle->total_orders,
        ], static fn ($value): bool => $value !== null && $value !== ''));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Klaviyo lifecycle profile synchronization failed.', [
            'profile_id' => $this->profileId,
            'exception' => $exception::class,
        ]);
    }
}
