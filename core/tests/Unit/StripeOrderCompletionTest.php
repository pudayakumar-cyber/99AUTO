<?php

namespace Tests\Unit;

use App\Services\StripeCheckoutAttemptStore;
use App\Services\StripeOrderCompletion;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class StripeOrderCompletionTest extends TestCase
{
    private Manager $db;
    private StripeCheckoutAttemptStore $attempts;
    private StripeOrderCompletion $completion;

    protected function setUp(): void
    {
        $this->db = new Manager;
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        $app = $this->db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->db->schema());
        $app->instance('encrypter', new Encrypter(random_bytes(32), 'AES-256-CBC'));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        (require __DIR__.'/../../database/migrations/2026_10_23_040000_create_stripe_checkout_attempts.php')->up();
        $schema = $this->db->schema();
        $schema->create('orders', function ($t) {
            $t->id(); $t->integer('user_id');
            foreach (['cart','billing_info','discount','txnid','transaction_number','currency_sign','payment_status','order_status'] as $field) { $t->text($field)->nullable(); }
            $t->float('currency_value'); $t->timestamp('coupon_usage_recorded_at')->nullable(); $t->timestamps();
        });
        $schema->create('items', function ($t) { $t->id(); $t->string('item_type'); $t->integer('stock'); $t->timestamps(); });
        $schema->create('attribute_options', function ($t) { $t->id(); $t->string('stock'); $t->timestamps(); });
        $schema->create('transactions', function ($t) {
            $t->id(); $t->integer('order_id'); $t->string('txn_id'); $t->string('user_email');
            $t->float('amount'); $t->string('currency_sign'); $t->float('currency_value'); $t->timestamps();
        });
        $schema->create('track_orders', function ($t) { $t->id(); $t->integer('order_id'); $t->string('title'); $t->timestamps(); });
        $schema->create('notifications', function ($t) { $t->id(); $t->integer('order_id'); $t->timestamps(); });
        $this->db->table('items')->insert(['id' => 10, 'item_type' => 'normal', 'stock' => 9]);
        $this->db->table('attribute_options')->insert(['id' => 5, 'stock' => '7']);
        $this->attempts = new StripeCheckoutAttemptStore;
        $this->completion = new StripeOrderCompletion($this->attempts);
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(null);
        \Stripe\Stripe::setApiKey(null);
        Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); Container::setInstance(null);
        parent::tearDown();
    }

    private function intent(): object
    {
        $id = (string) Str::uuid();
        $this->attempts->prepare($id, [
            'user_id' => 21, 'cart' => json_encode(['10-5' => ['qty' => 2, 'item_type' => 'normal', 'options_id' => [5]]]),
            'billing_info' => '{"bill_email":"buyer@example.test"}', 'discount' => '[]',
            'transaction_number' => 'temporary', 'currency_sign' => 'CAD', 'currency_value' => 1,
            'order_status' => 'Pending',
        ], 5782, 'cad');
        return $this->attempts->provision($id, fn ($p) => (object) array_merge($p, ['id' => 'pi_test', 'status' => 'succeeded', 'amount_received' => 5782]), fn () => null);
    }

    public function test_success_and_repeated_confirmation_apply_all_local_effects_once(): void
    {
        $intent = $this->intent();
        $order = $this->completion->complete($intent);
        $this->assertSame($order->id, $this->completion->complete($intent)->id);
        foreach (['orders', 'transactions', 'track_orders', 'notifications'] as $table) {
            $this->assertSame(1, $this->db->table($table)->count());
        }
        $this->assertSame(7, $this->db->table('items')->value('stock'));
        $this->assertSame('5', $this->db->table('attribute_options')->value('stock'));
        $this->assertEquals(57.82, $this->db->table('transactions')->value('amount'));
        $this->assertSame('buyer@example.test', $this->db->table('transactions')->value('user_email'));
        $this->assertSame(4, $this->db->table('stripe_checkout_effects')->where('status', 'pending')->count());
        $this->assertSame('Paid', $order->payment_status);
        $this->assertNull($this->attempts->activeAttempt(21));
    }

    public function test_mid_finalization_failure_rolls_back_order_stock_and_transaction(): void
    {
        $intent = $this->intent();
        $this->db->table('attribute_options')->delete();
        try { $this->completion->complete($intent); $this->fail('Missing option accepted'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {}
        foreach (['orders', 'transactions', 'track_orders', 'notifications', 'stripe_checkout_effects'] as $table) {
            $this->assertSame(0, $this->db->table($table)->count());
        }
        $this->assertSame(9, $this->db->table('items')->value('stock'));
        $this->assertNull($this->db->table('stripe_checkout_attempts')->value('order_id'));
        $this->db->table('attribute_options')->insert(['id' => 5, 'stock' => '7']);
        $this->assertSame('Paid', $this->completion->complete($intent)->payment_status);
    }

    public function test_notification_replays_and_uncertain_failures_do_not_resend(): void
    {
        $order = $this->completion->complete($this->intent());
        $this->db->schema()->create('settings', function ($t) { $t->id(); $t->string('title'); });
        $this->db->table('settings')->insert(['title' => 'Test']);
        $this->db->table('stripe_checkout_effects')->where('topic', '!=', 'email')->update(['status' => 'done']);
        $mailer = new class extends \App\Helpers\EmailHelper {
            public int $calls = 0;
            public bool $fail = true;
            public function __construct() {}
            public function sendTemplateMail(array $data, $mode = null, bool $throwOnFailure = false) {
                $this->calls++;
                if ($this->fail) { throw new \RuntimeException('Uncertain delivery'); }
                return true;
            }
        };
        Container::getInstance()->instance(\App\Helpers\EmailHelper::class, $mailer);
        Container::getInstance()->instance('log', new \Psr\Log\NullLogger);
        $effects = new \App\Services\StripeCheckoutEffects;
        $effects->process($order->id);
        $effects->process($order->id);
        $this->assertSame(1, $mailer->calls);
        $this->assertSame('review', $this->db->table('stripe_checkout_effects')->where('topic', 'email')->value('status'));
        $this->assertSame('Paid', $order->fresh()->payment_status);
        // Simulate a support-approved retry after checking the provider delivery log.
        $this->db->table('stripe_checkout_effects')->where('topic', 'email')->update(['status' => 'pending']);
        $mailer->fail = false;
        $effects->process($order->id);
        $effects->process($order->id);
        $this->assertSame(2, $mailer->calls);
        $this->assertSame('done', $this->db->table('stripe_checkout_effects')->where('topic', 'email')->value('status'));
    }

    public function test_background_recovery_waits_for_success_and_completes_without_a_session(): void
    {
        $intent = $this->intent();
        $this->db->schema()->create('payment_settings', function ($t) { $t->id(); $t->string('unique_keyword'); $t->text('information'); });
        $this->db->table('payment_settings')->insert(['unique_keyword' => 'stripe', 'information' => '{"secret":"sk_test_fake_only"}']);
        $client = new class($intent) implements \Stripe\HttpClient\ClientInterface {
            public string $status = 'processing';
            public int $calls = 0;
            public function __construct(private object $intent) {}
            public function request($method, $url, $headers, $params, $hasFile) {
                $this->calls++;
                return [json_encode(array_merge((array) $this->intent, ['object' => 'payment_intent', 'status' => $this->status])), 200, []];
            }
        };
        \Stripe\ApiRequestor::setHttpClient($client);
        $command = new class extends \App\Console\Commands\ReconcileStripeCheckouts {
            public bool $apply = false;
            public function option($key = null) { return $this->apply; }
            public function line($string, $style = null, $verbosity = null) {}
        };
        $effects = new class extends \App\Services\StripeCheckoutEffects {
            public array $orders = [];
            public function process(int $orderId): void { $this->orders[] = $orderId; }
        };
        $this->assertSame(0, $command->handle($this->attempts, $this->completion, $effects));
        $this->assertSame(0, $client->calls);
        $command->apply = true;
        $this->assertSame(0, $command->handle($this->attempts, $this->completion, $effects));
        $this->assertSame(0, $this->db->table('orders')->count());
        $this->assertSame([], $effects->orders);
        $client->status = 'succeeded';
        $this->assertSame(0, $command->handle($this->attempts, $this->completion, $effects));
        $this->assertSame(1, $this->db->table('orders')->count());
        $this->assertCount(1, $effects->orders);
        $this->assertSame(0, $command->handle($this->attempts, $this->completion, $effects));
        $this->assertSame(1, $this->db->table('orders')->count());
        $this->assertSame(7, $this->db->table('items')->value('stock'));
    }
}
