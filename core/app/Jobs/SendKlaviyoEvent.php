<?php

namespace App\Jobs;

use App\Services\KlaviyoClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendKlaviyoEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 30;

    public function __construct(
        public string $metricName,
        public array $profile,
        public array $properties = [],
        public ?string $uniqueId = null,
        public ?string $occurredAt = null,
        public ?float $value = null
    ) {
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

        $client->trackEvent(
            $this->metricName,
            $this->profile,
            $this->properties,
            $this->uniqueId,
            $this->occurredAt ? new \DateTimeImmutable($this->occurredAt) : null,
            $this->value
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Klaviyo event delivery failed.', [
            'metric' => $this->metricName,
            'unique_id' => $this->uniqueId,
            'exception' => $exception::class,
        ]);
    }
}
