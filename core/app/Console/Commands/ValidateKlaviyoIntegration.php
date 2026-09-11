<?php

namespace App\Console\Commands;

use App\Services\KlaviyoCatalogService;
use App\Services\KlaviyoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ValidateKlaviyoIntegration extends Command
{
    protected $signature = 'klaviyo:validate
        {--skip-api : Skip the read-only Klaviyo credential request}';

    protected $description = 'Validate Klaviyo production readiness without sending events or subscriptions';

    public function handle(KlaviyoClient $client, KlaviyoCatalogService $catalog): int
    {
        $checks = [];

        $this->check($checks, 'Public API key', trim((string) config('services.klaviyo.public_key')) !== '');
        $this->check($checks, 'Private API key', trim((string) config('services.klaviyo.private_api_key')) !== '');
        $this->check($checks, 'Email list ID', trim((string) config('services.klaviyo.email_list_id')) !== '');
        $this->check($checks, 'SMS list ID', trim((string) config('services.klaviyo.sms_list_id')) !== '');
        $this->check($checks, 'Catalog feed token', trim((string) config('services.klaviyo.catalog_feed_token')) !== '');

        foreach ([
            'marketing_consents',
            'marketing_consent_events',
            'customer_lifecycle_profiles',
            'customer_lifecycle_orders',
        ] as $table) {
            $this->check($checks, 'Database table: '.$table, Schema::hasTable($table));
        }

        $queueConnection = (string) config('queue.default');
        $this->check($checks, 'Queue connection', $queueConnection !== '' && $queueConnection !== 'sync', $queueConnection ?: 'not configured');
        $this->check($checks, 'Klaviyo queue name', trim((string) config('services.klaviyo.queue')) !== '');

        try {
            $sample = $catalog->query()->first();
            $validSample = $sample !== null
                && $catalog->map($sample)['id'] !== ''
                && $catalog->map($sample)['link'] !== '';
            $this->check($checks, 'Catalog sample', $validSample, $sample ? 'product '.$sample->id : 'no active product found');
        } catch (Throwable $exception) {
            $this->check($checks, 'Catalog sample', false, $exception->getMessage());
        }

        if ($this->option('skip-api')) {
            $checks[] = ['API credentials (read-only)', 'SKIPPED', 'Run without --skip-api before activation'];
        } else {
            try {
                $client->validateCredentials();
                $checks[] = ['API credentials (read-only)', 'PASS', 'Klaviyo accepted the private key'];
            } catch (Throwable $exception) {
                $checks[] = ['API credentials (read-only)', 'FAIL', $exception->getMessage()];
            }
        }

        $checks[] = [
            'Outbound delivery',
            config('services.klaviyo.enabled') ? 'ENABLED' : 'DISABLED',
            config('services.klaviyo.enabled') ? 'Live events may be sent' : 'Safe validation mode',
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);

        $failed = collect($checks)->contains(fn (array $check): bool => $check[1] === 'FAIL');

        if ($failed) {
            $this->error('Klaviyo is not ready to enable. Resolve every FAIL result and run this command again.');

            return self::FAILURE;
        }

        $this->info('Klaviyo readiness checks passed. No events, profiles, subscriptions, or messages were sent.');

        return self::SUCCESS;
    }

    private function check(array &$checks, string $name, bool $passed, string $details = ''): void
    {
        $checks[] = [$name, $passed ? 'PASS' : 'FAIL', $details];
    }
}
