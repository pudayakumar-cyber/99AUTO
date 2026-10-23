<?php

namespace Tests\Unit;

use App\Support\MarketingIdentity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MarketingIdentityTest extends TestCase
{
    public function test_it_normalizes_email_addresses(): void
    {
        $this->assertSame('customer@example.com', MarketingIdentity::email(' Customer@Example.COM '));
    }

    public function test_it_normalizes_north_american_phone_numbers_to_e164(): void
    {
        $this->assertSame('+14165551234', MarketingIdentity::phone('(416) 555-1234'));
        $this->assertSame('+14165551234', MarketingIdentity::phone('+1 416 555 1234'));
    }

    public function test_it_rejects_invalid_phone_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarketingIdentity::phone('123');
    }

    public function test_it_rejects_a_zero_country_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarketingIdentity::phone('+03328324800');
    }

    public function test_it_rejects_a_zero_country_code_after_international_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MarketingIdentity::phone('0003328324800');
    }

    public function test_it_preserves_valid_international_numbers(): void
    {
        $this->assertSame('+923328324800', MarketingIdentity::phone('+92 332 8324800'));
        $this->assertSame('+14165551234', MarketingIdentity::phone('0014165551234'));
        $this->assertSame('+14165551234', MarketingIdentity::phone('14165551234'));
    }
}
