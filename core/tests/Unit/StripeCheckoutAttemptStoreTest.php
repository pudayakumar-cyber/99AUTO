<?php

namespace Tests\Unit;

use App\Services\StripeCheckoutAttemptStore;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class StripeCheckoutAttemptStoreTest extends TestCase
{
    private Manager $db;
    private StripeCheckoutAttemptStore $store;

    protected function setUp(): void
    {
        $this->db = new Manager;
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setAsGlobal();
        $app = $this->db->getContainer();
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->db->schema());
        $app->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        (require __DIR__.'/../../database/migrations/2026_10_23_040000_create_stripe_checkout_attempts.php')->up();
        $this->store = new StripeCheckoutAttemptStore;
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    private function snapshot(): array
    {
        return ['user_id' => 21, 'cart' => '{"10":{"qty":1}}',
            'billing_info' => '{"bill_email":"private@example.test"}', 'transaction_number' => 'temporary'];
    }

    public function test_snapshot_is_encrypted_and_retries_preserve_original_placeholder(): void
    {
        $id = (string) Str::uuid();
        $snapshot = $this->snapshot();
        $this->store->prepare($id, $snapshot, 2891, 'cad');
        $stored = $this->db->table('stripe_checkout_attempts')->first();
        $this->assertStringNotContainsString('private@example.test', $stored->snapshot);
        $retry = $snapshot;
        $retry['transaction_number'] = 'another-placeholder';
        $this->assertSame($snapshot, $this->store->prepare($id, $retry, 2891, 'cad'));
        $this->assertSame(1, $this->db->table('stripe_checkout_attempts')->count());
    }

    public function test_changed_amount_cannot_overwrite_a_prepared_attempt(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        try {
            $this->store->prepare($id, $this->snapshot(), 100, 'cad');
            $this->fail('Changed checkout accepted.');
        } catch (\RuntimeException) {
            $this->assertSame(2891, $this->db->table('stripe_checkout_attempts')->value('amount'));
        }
    }

    public function test_recovery_requires_the_account_owner_and_bound_provider_intent(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $this->store->bind($id, 'pi_test');
        $this->store->bind($id, 'pi_test');
        $this->assertSame($this->snapshot(), $this->store->recover('pi_test', 21));
        $this->assertNull($this->store->recover('pi_test', 22));
        $this->assertNull($this->store->recover('pi_test', 0));
        $this->assertNull($this->store->recover('pi_unknown', 21));
        $this->expectException(\RuntimeException::class);
        $this->store->bind($id, 'pi_other');
    }

    public function test_provider_retry_reuses_saved_request_after_a_lost_response(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $requests = [];
        $create = function ($parameters, $options) use (&$requests) {
            $requests[] = [$parameters, $options];
            if (count($requests) === 1) {
                throw new \RuntimeException('Simulated response loss');
            }
            return (object) array_merge($parameters, ['id' => 'pi_retry']);
        };
        $retrieve = fn () => throw new \RuntimeException('Unexpected retrieve');
        try {
            $this->store->provision($id, $create, $retrieve);
            $this->fail('Expected lost response.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated response loss', $e->getMessage());
        }
        $intent = $this->store->provision($id, $create, $retrieve);
        $this->assertSame($requests[0], $requests[1]);
        $this->assertSame('checkout-'.$id, $requests[0][1]['idempotency_key']);
        $this->assertSame('pi_retry', $intent->id);
        $again = $this->store->provision($id,
            fn () => throw new \RuntimeException('Must not create again'),
            fn ($providerId) => $intent);
        $this->assertSame($intent, $again);
    }

    public function test_unknown_old_attempt_never_creates_a_fresh_payment(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $this->db->table('stripe_checkout_attempts')->where('id', $id)
            ->update(['created_at' => now()->subDays(2)]);
        $calls = 0;
        try {
            $this->store->provision($id, function () use (&$calls) { $calls++; }, fn () => null);
            $this->fail('Old unknown attempt accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('reconciliation', $e->getMessage());
            $this->assertSame(0, $calls);
        }
    }

    public function test_provider_amount_mismatch_does_not_bind_the_intent(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        try {
            $this->store->provision($id,
                fn ($parameters) => (object) array_merge($parameters, ['id' => 'pi_wrong', 'amount' => 100]),
                fn () => null);
            $this->fail('Wrong amount accepted.');
        } catch (\RuntimeException $e) {
            $this->assertNull($this->db->table('stripe_checkout_attempts')->value('provider_intent_id'));
        }
    }
}
