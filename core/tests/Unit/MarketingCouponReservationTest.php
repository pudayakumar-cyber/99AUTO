<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Services\CouponUsageService;
use App\Services\MarketingCouponReservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;

class MarketingCouponReservationTest extends MarketingOfferIssuerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->db->getContainer();
        $app->instance('validator', new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en')));
        (require __DIR__.'/../../database/migrations/2026_10_23_030000_create_marketing_coupon_reservations.php')->up();
        $this->db->schema()->table('orders', function (Blueprint $table) {
            $table->text('discount')->nullable();
            $table->timestamp('coupon_usage_recorded_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_stale_second_attempt_cannot_reserve_the_same_offer(): void
    {
        $coupon = $this->issuer->issue(21, 'cart', 'cart-1', CarbonImmutable::now()->addDay());
        $reservations = new MarketingCouponReservation;
        $token = $reservations->reserve($coupon, User::findOrFail(21));
        $this->assertSame(64, strlen($token));
        $this->assertSame(0, $coupon->fresh()->no_of_times);
        try {
            $reservations->reserve($coupon, User::findOrFail(21));
            $this->fail('A stale request must not get a second reservation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('coupon', $e->errors());
        }
        $this->assertSame(1, $this->db->table('marketing_coupon_reservations')->count());
        // Even a later admin balance change cannot bypass an existing reservation.
        $coupon->update(['no_of_times' => 1]);
        $this->expectException(ValidationException::class);
        $reservations->reserve($coupon, User::findOrFail(21));
    }

    public function test_stripe_attempt_reserves_once_and_reuses_encrypted_token_on_retry(): void
    {
        $this->db->getContainer()->instance('encrypter', new \Illuminate\Encryption\Encrypter(random_bytes(32), 'AES-256-CBC'));
        (require __DIR__.'/../../database/migrations/2026_10_23_040000_create_stripe_checkout_attempts.php')->up();
        $coupon = $this->issuer->issue(21, 'cart', 'cart-attempt', CarbonImmutable::now()->addDay());
        $snapshot = ['user_id' => 21, 'cart' => '{"10":{"qty":1}}', 'transaction_number' => 'temporary',
            'discount' => json_encode(['code' => $coupon->toArray(), 'discount' => 10])];
        $store = new \App\Services\StripeCheckoutAttemptStore;
        $id = (string) \Illuminate\Support\Str::uuid();
        $saved = $store->prepare($id, $snapshot, 9000, 'cad');
        $token = json_decode($saved['discount'], true)['reservation_token'];
        $this->assertSame(64, strlen($token));
        $this->assertSame($saved, $store->prepare($id, $snapshot, 9000, 'cad'));
        $this->assertSame(1, $this->db->table('marketing_coupon_reservations')->count());
        $this->assertSame(0, $coupon->fresh()->no_of_times);
        $this->assertStringNotContainsString($token, $this->db->table('stripe_checkout_attempts')->value('snapshot'));
    }

    public function test_acceptance_finalizes_once_without_decrementing_again(): void
    {
        $coupon = $this->issuer->issue(21, 'cart', 'cart-1', CarbonImmutable::now()->addDay());
        $reservations = new MarketingCouponReservation;
        $token = $reservations->reserve($coupon, User::findOrFail(21));
        $order = Order::create(['user_id' => 21, 'transaction_number' => 'ORD-test', 'order_status' => 'Pending',
            'discount' => json_encode(['code' => ['id' => $coupon->id], 'reservation_token' => $token])]);
        $service = new CouponUsageService;
        $this->assertTrue($service->record($order));
        $this->assertFalse($service->record($order));
        $this->assertSame(0, $coupon->fresh()->no_of_times);
        $this->assertTrue($reservations->completed($token));
        $other = Order::create(['user_id' => 21, 'transaction_number' => 'ORD-other', 'order_status' => 'Pending', 'discount' => $order->discount]);
        $this->assertFalse($service->record($other));
        $this->assertEquals($order->id, $this->db->table('marketing_coupon_reservations')->value('order_id'));
    }

    public function test_database_failure_rolls_back_both_balance_and_reservation(): void
    {
        $coupon = $this->issuer->issue(21, 'cart', 'cart-1', CarbonImmutable::now()->addDay());
        $this->db->getConnection()->unprepared("CREATE TRIGGER reject_coupon BEFORE UPDATE ON promo_codes BEGIN SELECT RAISE(ABORT, 'reservation test'); END");
        try {
            (new MarketingCouponReservation)->reserve($coupon, User::findOrFail(21));
            $this->fail('Expected database failure');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('reservation test', $e->getMessage());
        }
        $this->assertSame(0, $this->db->table('marketing_coupon_reservations')->count());
        $this->assertSame(1, $coupon->fresh()->no_of_times);
    }

    public function test_wrong_customer_cannot_finalize_a_reserved_offer(): void
    {
        $coupon = $this->issuer->issue(21, 'cart', 'cart-1', CarbonImmutable::now()->addDay());
        $reservations = new MarketingCouponReservation;
        $token = $reservations->reserve($coupon, User::findOrFail(21));
        $order = Order::create(['user_id' => 22, 'transaction_number' => 'ORD-wrong', 'order_status' => 'Pending',
            'discount' => json_encode(['code' => ['id' => $coupon->id], 'reservation_token' => $token])]);
        $this->assertFalse((new CouponUsageService)->record($order));
        $this->assertFalse($reservations->completed($token));
        $this->assertNull($order->fresh()->coupon_usage_recorded_at);
    }

    public function test_wrong_customer_and_expired_snapshot_do_not_consume_an_offer(): void
    {
        $coupon = $this->issuer->issue(21, 'cart', 'cart-1', CarbonImmutable::now()->addDay());
        $reservations = new MarketingCouponReservation;
        try {
            $reservations->reserve($coupon, User::findOrFail(22));
            $this->fail('Wrong customer must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('coupon', $e->errors());
        }
        $coupon->update(['expires_at' => now()->subMinute()]);
        try {
            $reservations->reserve($coupon, User::findOrFail(21));
            $this->fail('Expired offer must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('coupon', $e->errors());
        }
        $this->assertSame(0, $this->db->table('marketing_coupon_reservations')->count());
        $this->assertSame(1, $coupon->fresh()->no_of_times);
    }

    public function test_timeout_does_not_release_offer_and_late_acceptance_can_finalize(): void
    {
        $coupon = $this->issuer->issue(21, 'cart', 'cart-1', CarbonImmutable::now()->addDay());
        $reservations = new MarketingCouponReservation;
        $token = $reservations->reserve($coupon, User::findOrFail(21));
        \Carbon\Carbon::setTestNow('2026-11-01 12:00:00');
        $this->assertFalse($reservations->completed($token));
        $this->assertSame(0, $coupon->fresh()->no_of_times);
        $order = Order::create(['user_id' => 21, 'transaction_number' => 'ORD-late', 'order_status' => 'Pending',
            'discount' => json_encode(['code' => ['id' => $coupon->id], 'reservation_token' => $token])]);
        $this->assertTrue((new CouponUsageService)->record($order));
        $this->assertTrue($reservations->completed($token));
    }
}
