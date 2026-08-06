<?php

namespace Tests\Unit;

use App\Support\GoogleSiteVerification;
use PHPUnit\Framework\TestCase;

class GoogleSiteVerificationTest extends TestCase
{
    public function test_it_extracts_and_removes_a_plain_assignment_from_any_setting(): void
    {
        $result = GoogleSiteVerification::extractAndSanitize([
            'copyright' => '99AutoParts All rights reserved.',
            'adsense' => 'google-site-verification=test_token-123',
        ]);

        $this->assertSame('test_token-123', $result['token']);
        $this->assertSame('', $result['sources']['adsense']);
        $this->assertSame('99AutoParts All rights reserved.', $result['sources']['copyright']);
    }

    public function test_it_extracts_and_removes_a_complete_meta_tag(): void
    {
        $result = GoogleSiteVerification::extractAndSanitize([
            'analytics' => '<meta name="google-site-verification" content="test_token-456"><script>gtag();</script>',
        ]);

        $this->assertSame('test_token-456', $result['token']);
        $this->assertSame('<script>gtag();</script>', $result['sources']['analytics']);
    }
}
