<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class KlaviyoClient
{
    public function enabled(): bool
    {
        return (bool) config('services.klaviyo.enabled')
            && trim((string) config('services.klaviyo.private_api_key')) !== '';
    }

    public function trackEvent(
        string $metricName,
        array $profile,
        array $properties = [],
        ?string $uniqueId = null,
        ?DateTimeInterface $occurredAt = null,
        ?float $value = null
    ): void {
        $this->ensureEnabled();
        $this->validateMetricName($metricName);
        $this->validateProfile($profile);

        $attributes = [
            'properties' => $properties,
            'metric' => [
                'data' => [
                    'type' => 'metric',
                    'attributes' => ['name' => trim($metricName)],
                ],
            ],
            'profile' => [
                'data' => [
                    'type' => 'profile',
                    'attributes' => $this->withoutEmptyValues($profile),
                ],
            ],
        ];

        if ($uniqueId !== null && trim($uniqueId) !== '') {
            $attributes['unique_id'] = trim($uniqueId);
        }

        if ($occurredAt !== null) {
            $attributes['time'] = $occurredAt->format(DateTimeInterface::ATOM);
        }

        if ($value !== null) {
            $attributes['value'] = $value;
        }

        $this->request()->post('/api/events/', [
            'data' => [
                'type' => 'event',
                'attributes' => $attributes,
            ],
        ])->throw();
    }

    public function upsertProfile(array $attributes, array $properties = []): void
    {
        $this->ensureEnabled();
        $this->validateProfile($attributes);

        $profileAttributes = $this->withoutEmptyValues($attributes);

        if ($properties !== []) {
            $profileAttributes['properties'] = $this->withoutEmptyValues($properties);
        }

        $this->request()->post('/api/profile-import/', [
            'data' => [
                'type' => 'profile',
                'attributes' => $profileAttributes,
            ],
        ])->throw();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.klaviyo.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Klaviyo-API-Key '.config('services.klaviyo.private_api_key'),
                'revision' => config('services.klaviyo.revision'),
            ])
            ->connectTimeout((int) config('services.klaviyo.connect_timeout'))
            ->timeout((int) config('services.klaviyo.timeout'));
    }

    private function ensureEnabled(): void
    {
        if (! $this->enabled()) {
            throw new InvalidArgumentException('Klaviyo is disabled or its private API key is missing.');
        }
    }

    private function validateProfile(array $profile): void
    {
        $identifiers = Arr::only($profile, ['email', 'phone_number', 'external_id']);

        if ($this->withoutEmptyValues($identifiers) === []) {
            throw new InvalidArgumentException(
                'A Klaviyo profile requires an email, phone_number, or external_id identifier.'
            );
        }
    }

    private function validateMetricName(string $metricName): void
    {
        if (trim($metricName) === '') {
            throw new InvalidArgumentException('A Klaviyo event requires a metric name.');
        }
    }

    private function withoutEmptyValues(array $values): array
    {
        return array_filter($values, static function ($value): bool {
            return $value !== null && $value !== '' && $value !== [];
        });
    }
}
