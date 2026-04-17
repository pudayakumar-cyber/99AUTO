<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EShipperService
{
    public function isEnabled()
    {
        return (bool) config('services.eshipper.enabled');
    }

    public function canCreateShipments()
    {
        return (bool) config('services.eshipper.allow_live_shipments', false);
    }

    public function getQuotes(array $payload)
    {
        $response = $this->client()
            ->withToken($this->authenticate())
            ->acceptJson()
            ->post('/api/v3/quote?limit=' . (int) config('services.eshipper.top_rates_limit', 4), $payload);

        if ($response->failed()) {
            Log::warning('eShipper quote request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Unable to fetch shipping rates right now.');
        }

        return $response->json();
    }

    public function authenticate()
    {
        $response = $this->client()
            ->acceptJson()
            ->post('/api/v2/authenticate', [
                'principal' => config('services.eshipper.username'),
                'credential' => config('services.eshipper.password'),
            ]);

        if ($response->failed()) {
            Log::warning('eShipper authentication failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Unable to authenticate shipping provider.');
        }

        $data = $response->json();
        $token = $data['token'] ?? $data['accessToken'] ?? $data['jwt'] ?? null;

        if (!$token) {
            throw new RuntimeException('Shipping provider token missing from authentication response.');
        }

        return $token;
    }

    public function createShipment(array $payload, $rateId)
    {
        if (!$this->canCreateShipments()) {
            throw new RuntimeException('Live shipment creation is disabled.');
        }

        $response = $this->client()
            ->withToken($this->authenticate())
            ->acceptJson()
            ->post('/api/v3/ship/' . rawurlencode((string) $rateId), $payload);

        if ($response->failed()) {
            Log::warning('eShipper shipment creation failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Unable to create shipment right now.');
        }

        return $response->json();
    }

    public function getShipmentLabel($shipmentId)
    {
        $response = $this->client()
            ->withToken($this->authenticate())
            ->get('/api/v2/ship/' . rawurlencode((string) $shipmentId) . '/label');

        if ($response->failed()) {
            Log::warning('eShipper label request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Unable to fetch shipment label right now.');
        }

        return $response;
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout((int) config('services.eshipper.timeout', 20))
            ->connectTimeout((int) config('services.eshipper.connect_timeout', 10));
    }

    protected function baseUrl()
    {
        $mode = config('services.eshipper.mode', 'live');

        if ($mode === 'sandbox') {
            return rtrim((string) config('services.eshipper.sandbox_url'), '/');
        }

        return rtrim((string) config('services.eshipper.live_url'), '/');
    }
}
