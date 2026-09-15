<?php

namespace Tests\Unit;

use App\Http\Requests\PromoCodeRequest;
use App\Repositories\Front\CartRepository;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

class CouponValidationTest extends TestCase
{
    public function test_discount_never_exceeds_subtotal_or_increases_it(): void
    {
        $repository = new CartRepository();
        $cases = [
            [15, 'amount', 10, 10, 0],
            [15, 'amount', 40, 15, 25],
            [10, 'percentage', 40, 4, 36],
            [150, 'percentage', 40, 40, 0],
            [-10, 'amount', 40, 0, 40],
            [-10, 'percentage', 40, 0, 40],
            [15, 'amount', 0, 0, 0],
            [15, 'amount', -10, 0, 0],
            [15, 'unknown', 40, 0, 40],
            ['bad', 'amount', 40, 0, 40],
            [INF, 'amount', 40, 0, 40],
            [15, 'amount', NAN, 0, 0],
            ['10', 'percentage', '99.90', 9.99, 89.91],
        ];
        foreach ($cases as [$discount, $type, $price, $sub, $total]) {
            $actual = $repository->getDiscount($discount, $type, $price);
            $this->assertEqualsWithDelta($sub, $actual['sub'], 0.000001);
            $this->assertEqualsWithDelta($total, $actual['total'], 0.000001);
        }
    }

    public function test_admin_coupon_rules_accept_valid_and_reject_invalid_discounts(): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        $cases = [
            [['type' => 'percentage', 'discount' => 10, 'no_of_times' => 1], true],
            [['type' => 'percentage', 'discount' => 100, 'no_of_times' => 0], true],
            [['type' => 'amount', 'discount' => 150, 'no_of_times' => 5], true],
            [['type' => 'percentage', 'discount' => 101, 'no_of_times' => 1], false],
            [['type' => 'amount', 'discount' => -1, 'no_of_times' => 1], false],
            [['type' => 'percentage', 'discount' => -1, 'no_of_times' => 1], false],
            [['type' => 'amount', 'discount' => 15, 'no_of_times' => -1], false],
            [['type' => 'amount', 'discount' => 15, 'no_of_times' => 1.5], false],
            [['type' => 'other', 'discount' => 15, 'no_of_times' => 1], false],
            [['discount' => 15, 'no_of_times' => 1], false],
        ];
        foreach ($cases as [$input, $expected]) {
            $request = PromoCodeRequest::create('/', 'POST', $input);
            // Exercise real field rules without the unrelated unique-code DB lookup.
            $rules = array_intersect_key($request->rules(), array_flip(['type', 'discount', 'no_of_times']));
            $this->assertSame($expected, $factory->make($input, $rules)->passes());
        }
    }
}
