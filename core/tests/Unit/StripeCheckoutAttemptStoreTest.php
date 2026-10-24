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

    public function test_finalization_replays_return_same_order_without_repeating_database_effects(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $intent = $this->store->provision($id,
            fn ($parameters) => (object) array_merge($parameters, ['id' => 'pi_final', 'status' => 'succeeded', 'amount_received' => 2891]), fn () => null);
        $calls = 0;
        $create = function ($snapshot) use (&$calls) {
            $calls++;
            $this->assertSame($this->snapshot(), $snapshot);
            return 42;
        };
        $this->assertSame(42, $this->store->finalize($intent, $create));
        $this->assertSame(42, $this->store->finalize($intent, $create));
        $this->assertSame(1, $calls);
        $this->assertNotNull($this->db->table('stripe_checkout_attempts')->value('finalized_at'));
    }

    public function test_finalization_rolls_back_database_effects_and_can_retry(): void
    {
        $this->db->schema()->create('test_effects', fn ($table) => $table->integer('order_id'));
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $intent = $this->store->provision($id,
            fn ($parameters) => (object) array_merge($parameters, ['id' => 'pi_atomic', 'status' => 'succeeded', 'amount_received' => 2891]), fn () => null);
        try {
            $this->store->finalize($intent, function () {
                $this->db->table('test_effects')->insert(['order_id' => 42]);
                throw new \RuntimeException('Stock failure');
            });
            $this->fail('Failure was ignored');
        } catch (\RuntimeException $e) {
            $this->assertSame('Stock failure', $e->getMessage());
        }
        $this->assertSame(0, $this->db->table('test_effects')->count());
        $this->assertNull($this->db->table('stripe_checkout_attempts')->value('order_id'));
        $this->assertSame(43, $this->store->finalize($intent, fn () => 43));
    }

    public function test_pending_and_wrong_currency_payments_never_finalize(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $intent = $this->store->provision($id,
            fn ($parameters) => (object) array_merge($parameters, ['id' => 'pi_pending', 'status' => 'processing']), fn () => null);
        foreach (['processing', 'requires_capture', 'succeeded'] as $status) {
            $intent->status = $status;
            if ($status === 'succeeded') { $intent->currency = 'usd'; }
            try {
                $this->store->finalize($intent, fn () => $this->fail('Must not create order'));
                $this->fail('Invalid payment accepted');
            } catch (\RuntimeException) {
                $this->assertNull($this->db->table('stripe_checkout_attempts')->value('order_id'));
            }
        }
    }

    public function test_same_account_cannot_start_a_second_unresolved_attempt(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $this->assertSame($id, $this->store->activeAttempt(21));
        try {
            $this->store->prepare((string) Str::uuid(), $this->snapshot(), 2891, 'cad');
            $this->fail('Second active attempt accepted');
        } catch (\RuntimeException) {
            $this->assertSame(1, $this->db->table('stripe_checkout_attempts')->count());
        }
        $this->assertNull($this->store->activeAttempt(0));
    }

    public function test_only_provider_confirmed_cancellation_releases_the_active_account_guard(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $intent = $this->store->provision($id,
            fn ($p) => (object) array_merge($p, ['id' => 'pi_cancel', 'status' => 'processing']), fn () => null);
        try { $this->store->recordCancellation($intent); $this->fail('Pending payment released'); }
        catch (\RuntimeException) { $this->assertSame($id, $this->store->activeAttempt(21)); }
        $intent->status = 'canceled';
        $this->store->recordCancellation($intent);
        $this->assertNull($this->store->activeAttempt(21));
        $this->assertTrue($this->store->closed($id));
        $this->assertTrue($this->store->canceledIntent('pi_cancel', 21));
        $this->assertFalse($this->store->canceledIntent('pi_cancel', 22));
        $this->assertNull($this->db->table('stripe_checkout_attempts')->value('order_id'));
    }

    public function test_partial_capture_never_creates_a_fully_paid_order(): void
    {
        $id = (string) Str::uuid();
        $this->store->prepare($id, $this->snapshot(), 2891, 'cad');
        $intent = $this->store->provision($id,
            fn ($p) => (object) array_merge($p, ['id' => 'pi_partial', 'status' => 'succeeded', 'amount_received' => 100]), fn () => null);
        $this->expectException(\RuntimeException::class);
        $this->store->finalize($intent, fn () => $this->fail('Partial capture accepted'));
    }
}
