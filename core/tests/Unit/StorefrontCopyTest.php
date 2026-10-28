<?php

namespace Tests\Unit;

use App\Support\StorefrontCopy;
use PHPUnit\Framework\TestCase;

class StorefrontCopyTest extends TestCase
{
    public function test_it_corrects_the_verified_service_copy_typo(): void
    {
        $this->assertSame(
            'We possess SSL / Secure Certificate',
            StorefrontCopy::serviceDetails('We posess SSL / Secure Certificate')
        );
        $this->assertSame('Possess a valid certificate', StorefrontCopy::serviceDetails('Posess a valid certificate'));
    }

    public function test_it_preserves_other_service_copy_and_shipping_amounts(): void
    {
        $details = 'Free shipping on orders over CAD150.00';

        $this->assertSame($details, StorefrontCopy::serviceDetails($details));
        $this->assertSame('', StorefrontCopy::serviceDetails(null));
    }

    public function test_it_corrects_only_the_english_useful_links_fallback(): void
    {
        $this->assertSame('Useful Links', StorefrontCopy::usefulLinksLabel('Usefull Links'));
        $this->assertSame('روابط مفيدة', StorefrontCopy::usefulLinksLabel('روابط مفيدة'));
    }
}
