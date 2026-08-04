<?php

namespace Tests\Unit;

use App\Services\ItemCsvImporter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ItemCsvImporterSlugTest extends TestCase
{
    public function test_it_adds_the_identifier_to_generic_product_titles(): void
    {
        $this->assertSame(
            'control-arm-with-ball-joint-72-ck80539',
            $this->slugBase('Control Arm With Ball Joint', '72-CK80539')
        );
    }

    public function test_it_does_not_duplicate_an_identifier_already_in_the_title(): void
    {
        $this->assertSame(
            'kyb-234054-front-gas-charged-strut',
            $this->slugBase('KYB - 234054 - Front Gas Charged Strut', '234054')
        );
    }

    private function slugBase(string $title, ?string $identifier): string
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'itemSlugBase');
        $method->setAccessible(true);

        return $method->invoke(new ItemCsvImporter, $title, $identifier);
    }
}
