<?php

namespace App\Support;

class ShippingNotificationDetails
{
    public static function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'tracking_carrier' => ['nullable', 'string', 'max:150'],
            'tracking_url' => ['nullable', 'url:https', 'max:2048'],
            'estimated_delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'out_for_delivery' => ['sometimes', 'boolean'],
        ];
    }

    public static function fromInput(array $input, array $previous, bool $newTrackingNumber): array
    {
        $details = $newTrackingNumber ? [] : $previous;
        foreach (['tracking_carrier', 'tracking_url', 'estimated_delivery_date'] as $key) {
            if (array_key_exists($key, $input)) {
                $details[$key] = trim((string) $input[$key]) ?: null;
            }
        }
        if (array_key_exists('out_for_delivery', $input)) {
            $confirmed = (bool) $input['out_for_delivery'];
            $details['out_for_delivery_at'] = $confirmed
                ? ($details['out_for_delivery_at'] ?? now()->toAtomString()) : null;
        }

        return $details;
    }

    public static function eventProperties(array $details): array
    {
        $url = trim((string) ($details['tracking_url'] ?? ''));
        // Treat older/imported records as untrusted too. Never emit executable URLs.
        $safeUrl = filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && parse_url($url, PHP_URL_USER) === null;

        return array_filter([
            'Carrier' => $details['tracking_carrier'] ?? null,
            'TrackingURL' => $safeUrl ? $url : null,
            'EstimatedDeliveryDate' => $details['estimated_delivery_date'] ?? null,
            'OutForDeliveryAt' => $details['out_for_delivery_at'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
