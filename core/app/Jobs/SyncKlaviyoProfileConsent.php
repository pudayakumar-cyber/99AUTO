<?php

namespace App\Jobs;

use App\Models\MarketingConsent;
use App\Services\KlaviyoClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncKlaviyoProfileConsent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(public int $consentId)
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

        $consent = MarketingConsent::with('user')->find($this->consentId);

        if (! $consent) {
            return;
        }

        $listId = trim((string) config('services.klaviyo.'.$consent->channel.'_list_id'));
        if ($listId === '') {
            throw new RuntimeException('A Klaviyo list ID is required for '.$consent->channel.' consent synchronization.');
        }

        $profile = $consent->channel === 'email'
            ? ['email' => $consent->identity]
            : ['phone_number' => $consent->identity];

        if ($consent->user) {
            $profile += [
                'external_id' => (string) $consent->user->id,
                'first_name' => $consent->user->first_name,
                'last_name' => $consent->user->last_name,
            ];
        }

        $client->upsertProfile($profile, ['consent_source' => $consent->source]);

        if ($consent->status === 'subscribed') {
            $client->subscribeProfile($consent->channel, $profile, $listId, $consent->source);
        } else {
            $client->unsubscribeProfile($consent->channel, $profile, $listId);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Klaviyo consent synchronization failed.', [
            'consent_id' => $this->consentId,
            'exception' => $exception::class,
        ]);
    }
}
