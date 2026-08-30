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
}
