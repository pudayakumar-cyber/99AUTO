<?php

namespace App\Console\Commands;

use App\Services\KlaviyoCatalogService;
use App\Services\KlaviyoCatalogAudit;
use App\Services\KlaviyoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ValidateKlaviyoIntegration extends Command
{
    protected $signature = 'klaviyo:validate
        {--skip-api : Skip the read-only Klaviyo credential request}
        {--email-only : Check email prerequisites without requiring an SMS list}
        {--full-catalog : Validate all catalog records and total JSON size, not just a sample}';

    protected $description = 'Validate Klaviyo production readiness without sending events or subscriptions';

    public function handle(KlaviyoClient $client, KlaviyoCatalogService $catalog): int
    {
        $checks = [];

        $this->check($checks, 'Public API key', trim((string) config('services.klaviyo.public_key')) !== '');
        $this->check($checks, 'Private API key', trim((string) config('services.klaviyo.private_api_key')) !== '');
        $this->check($checks, 'Email list ID', trim((string) config('services.klaviyo.email_list_id')) !== '');
        if (! $this->option('email-only')) {
            $this->check($checks, 'SMS list ID', trim((string) config('services.klaviyo.sms_list_id')) !== '');
        }
        $this->check($checks, 'Catalog feed token', trim((string) config('services.klaviyo.catalog_feed_token')) !== '');

        foreach ([
            'cart_recovery_links',
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
            $fullCatalog = (bool) $this->option('full-catalog');
            $items = $fullCatalog ? $catalog->query()->lazyById(500) : $catalog->query()->limit(1)->get();
            $records = (function () use ($items, $catalog) {
                foreach ($items as $item) {
                    yield $catalog->map($item);
                }
            })();
            $audit = (new KlaviyoCatalogAudit)->inspect($records);
            $this->check($checks, $fullCatalog ? 'Full catalog' : 'Catalog sample', $audit['valid'],
                $audit['count'].' records; '.$audit['invalid'].' invalid; '.$audit['bytes'].' JSON bytes');
            foreach ($audit['examples'] as $example) {
                $checks[] = ['Catalog record', 'FAIL', $example];
            }
            if ($audit['bytes'] > 50000000) {
                $checks[] = ['Catalog size', $audit['bytes'] > 100000000 ? 'FAIL' : 'WARN', 'Split feed sources; recommended below 50 MB, maximum 100 MB'];
            }
            if ($audit['excluded_from_recommendations'] > 0) {
                $checks[] = ['Inventory visibility', 'INFO', $audit['excluded_from_recommendations'].' records excluded from recommendations by stock policy'];
            }
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

        $this->info('Local integration checks passed. No events, profiles, subscriptions, or messages were sent.');
        $this->warn('This does not verify write scopes, worker health, live feed download, Klaviyo ingestion, flow settings, or email delivery.');
        if ($this->option('skip-api')) {
            $this->warn('API access was not checked.');
        }
        if (! $this->option('full-catalog')) {
            $this->warn('Only one catalog record was checked. Use --full-catalog to check every record and the feed size.');
        }

        return self::SUCCESS;
    }

    private function check(array &$checks, string $name, bool $passed, string $details = ''): void
    {
        $checks[] = [$name, $passed ? 'PASS' : 'FAIL', $details];
    }
}
