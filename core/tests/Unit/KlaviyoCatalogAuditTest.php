<?php

namespace Tests\Unit;

use App\Services\KlaviyoCatalogAudit;
use PHPUnit\Framework\TestCase;

class KlaviyoCatalogAuditTest extends TestCase
{
    private function record(string $id = '1'): array
    {
        return ['id' => $id, 'title' => 'Filter', 'description' => 'Oil filter',
            'link' => 'https://99autoparts.ca/product/filter',
            'image_link' => 'https://99autoparts.ca/filter.jpg', 'price' => 10,
            'inventory_quantity' => 0, 'inventory_policy' => 1];
    }

    public function test_counts_exact_encoded_bytes_and_distinguishes_inventory_from_import_failure(): void
    {
        $records = [$this->record(), $this->record('2')];
        $audit = (new KlaviyoCatalogAudit)->inspect($records);
        $this->assertTrue($audit['valid']);
        $this->assertSame(2, $audit['count']);
        $this->assertSame(2, $audit['excluded_from_recommendations']);
        $this->assertSame(strlen(json_encode($records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $audit['bytes']);
    }

    public function test_finds_invalid_records_after_a_valid_first_record(): void
    {
        $second = $this->record('2');
        $second['description'] = '';
        $second['image_link'] = 'http://99autoparts.ca/filter.jpg';
        $audit = (new KlaviyoCatalogAudit)->inspect([$this->record(), $second]);
        $this->assertFalse($audit['valid']);
        $this->assertSame(1, $audit['invalid']);
        $this->assertStringContainsString('Record 2', $audit['examples'][0]);
    }

    public function test_rejects_duplicate_ids_and_bad_utf8(): void
    {
        $bad = $this->record();
        $bad['title'] = chr(255);
        $audit = (new KlaviyoCatalogAudit)->inspect([$this->record(), $bad]);
        $this->assertFalse($audit['valid']);
        $this->assertStringContainsString('duplicate id', $audit['examples'][0]);
        $this->assertStringContainsString('JSON encoding failed', $audit['examples'][0]);
    }

    public function test_rejects_empty_catalog_and_limits_error_examples(): void
    {
        $this->assertFalse((new KlaviyoCatalogAudit)->inspect([])['valid']);
        $audit = (new KlaviyoCatalogAudit)->inspect(array_fill(0, 15, []));
        $this->assertSame(15, $audit['invalid']);
        $this->assertCount(10, $audit['examples']);
    }
}
