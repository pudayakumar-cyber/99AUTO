<?php

namespace Tests\Unit;

use App\Http\Middleware\RevalidateMarketingCoupon;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\MarketingCouponEligibility;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class MarketingCouponEligibilityTest extends TestCase
{
    private Capsule $db;
    private MarketingCouponEligibility $eligibility;
    private PromoCode $coupon;
    private User $user;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Requires isolated SQLite.');
        }
        $this->db = new Capsule;
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        $app = $this->db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->db->schema());
        $app->instance('validator', new Factory(new Translator(new ArrayLoader, 'en')));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        (require __DIR__.'/../../database/migrations/2026_10_23_040000_create_stripe_checkout_attempts.php')->up();
        $this->db->schema()->create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->integer('status');
            $table->integer('no_of_times');
            $table->string('type');
            $table->decimal('discount');
        });
        (require __DIR__.'/../../database/migrations/2026_10_23_010000_add_marketing_rules_to_promo_codes.php')->up();
        $this->db->schema()->create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('transaction_number');
            $table->string('order_status')->nullable();
            $table->text('billing_info')->nullable();
        });
        Carbon::setTestNow('2026-10-23 12:00:00');
        $this->eligibility = new MarketingCouponEligibility;
        $this->coupon = PromoCode::create(['status' => 1, 'no_of_times' => 5, 'type' => 'percentage', 'discount' => 10]);
        $this->user = new User;
        $this->user->forceFill(['id' => 21, 'email' => 'CUSTOMER@example.test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Model::unsetConnectionResolver();
        parent::tearDown();
    }

    public function test_existing_unrestricted_coupon_still_works_for_guests(): void
    {
        $this->assertTrue($this->eligibility->allows($this->coupon, null));
        $this->coupon->status = 0;
        $this->assertFalse($this->eligibility->allows($this->coupon, null));
        $this->coupon->status = 1;
        $this->coupon->no_of_times = 0;
        $this->assertFalse($this->eligibility->allows($this->coupon, null));
    }

    public function test_expiry_boundary_and_recipient_binding(): void
    {
        $this->coupon->recipient_email = 'customer@example.test';
        $this->assertFalse($this->eligibility->allows($this->coupon, null));
        $this->assertTrue($this->eligibility->allows($this->coupon, $this->user));
        $this->coupon->expires_at = now();
        $this->assertFalse($this->eligibility->allows($this->coupon, $this->user));
        $this->coupon->expires_at = now()->addSecond();
        $this->assertTrue($this->eligibility->allows($this->coupon, $this->user));
        $this->user->email = 'other@example.test';
        $this->assertFalse($this->eligibility->allows($this->coupon, $this->user));
    }

    public function test_first_order_rule_checks_account_and_guest_history(): void
    {
        $this->coupon->first_order_only = true;
        $this->assertFalse($this->eligibility->allows($this->coupon, null));
        $this->assertTrue($this->eligibility->allows($this->coupon, $this->user));
        $this->db->table('orders')->insert(['user_id' => 21, 'transaction_number' => 'temporary', 'order_status' => 'Pending']);
        $this->assertTrue($this->eligibility->allows($this->coupon, $this->user));
        $this->db->table('orders')->insert(['user_id' => 21, 'transaction_number' => 'ORD-1', 'order_status' => 'Canceled']);
        $this->assertTrue($this->eligibility->allows($this->coupon, $this->user));
        $this->db->table('orders')->insert(['user_id' => 0, 'transaction_number' => 'ORD-2', 'order_status' => 'Delivered',
            'billing_info' => json_encode(['bill_email' => 'Customer@EXAMPLE.test'])]);
        $this->assertFalse($this->eligibility->allows($this->coupon, $this->user));
        $this->db->table('orders')->where('transaction_number', 'ORD-2')->delete();
        $this->db->table('orders')->where('transaction_number', 'ORD-1')->update(['order_status' => 'Pending']);
        $this->assertFalse($this->eligibility->allows($this->coupon, $this->user));
    }

    private function request(string $route): Request
    {
        $request = Request::create('/test', 'POST');
        $request->setRouteResolver(fn () => (new Route('POST', '/test', fn () => null))->name($route));
        $request->setUserResolver(fn () => $this->user);
        $session = new Store('test', new ArraySessionHandler(120));
        $request->setLaravelSession($session);
        $session->put('coupon', ['code' => $this->coupon, 'discount' => 10]);
        $session->put('cart', [['main_price' => 100, 'attribute_price' => 0, 'qty' => 1]]);
        return $request;
    }

    public function test_all_payment_initiation_routes_reject_expired_session_coupons(): void
    {
        foreach (RevalidateMarketingCoupon::ROUTES as $route) {
            $request = $this->request($route);
            $this->coupon->update(['expires_at' => now()]);
            try {
                (new RevalidateMarketingCoupon($this->eligibility))->handle($request, fn () => $this->fail('Payment must not start'));
                $this->fail('Expected coupon validation error');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('coupon', $e->errors());
                $this->assertFalse($request->session()->has('coupon'));
            }
        }
    }

    public function test_changed_discount_requires_review_but_paid_callbacks_are_untouched(): void
    {
        $request = $this->request('front.stripe.create.intent');
        $this->coupon->update(['discount' => 5]);
        try {
            (new RevalidateMarketingCoupon($this->eligibility))->handle($request, fn () => $this->fail('Payment must not start'));
            $this->fail('Expected review');
        } catch (ValidationException $e) {
            $this->assertEquals(5, $request->session()->get('coupon.discount'));
        }
        foreach (['front.stripe.confirm.payment', 'front.stripe.return', 'front.paytm.notify', 'paytab.callback'] as $route) {
            $callback = $this->request($route);
            $this->coupon->update(['expires_at' => now()]);
            $this->assertSame('paid', (new RevalidateMarketingCoupon($this->eligibility))->handle($callback, fn () => 'paid'));
            $this->assertEquals(10, $callback->session()->get('coupon.discount'));
        }
    }

    public function test_valid_coupon_reaches_payment_and_migration_rollback_preserves_legacy_data(): void
    {
        $request = $this->request('front.checkout.submit');
        $this->assertSame('continue', (new RevalidateMarketingCoupon($this->eligibility))->handle($request, fn () => 'continue'));
        $this->assertEquals(10, $request->session()->get('coupon.discount'));
        (require __DIR__.'/../../database/migrations/2026_10_23_010000_add_marketing_rules_to_promo_codes.php')->down();
        $this->assertFalse($this->db->schema()->hasColumn('promo_codes', 'expires_at'));
        $this->assertEquals(10, $this->db->table('promo_codes')->value('discount'));
    }

    public function test_unresolved_stripe_attempt_blocks_other_gateways_but_preserves_reserved_retry(): void
    {
        $this->db->table('stripe_checkout_attempts')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => 21, 'active_user_id' => 21,
            'amount' => 9000, 'currency' => 'cad', 'snapshot_hash' => str_repeat('a', 64), 'snapshot' => 'unused',
        ]);
        $this->coupon->update(['no_of_times' => 0]);
        $middleware = new RevalidateMarketingCoupon($this->eligibility);
        $retry = $this->request('front.stripe.create.intent');
        $this->assertSame('saved-attempt', $middleware->handle($retry, fn () => 'saved-attempt'));
        $this->assertTrue($retry->session()->has('coupon'));
        $callback = $this->request('front.stripe.confirm.payment');
        $this->assertSame('confirm', $middleware->handle($callback, fn () => 'confirm'));
        try {
            $middleware->handle($this->request('front.checkout.submit'), fn () => $this->fail('Another payment started'));
            $this->fail('Unresolved payment ignored');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->errors());
        }
    }
}
