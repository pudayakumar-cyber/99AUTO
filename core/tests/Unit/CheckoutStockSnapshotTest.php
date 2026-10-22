<?php

namespace Tests\Unit;

use App\Helpers\PriceHelper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

class CheckoutStockSnapshotTest extends MarketingOfferIssuerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->schema()->create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_type');
            $table->integer('stock');
            $table->timestamps();
        });
        $this->db->schema()->create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->string('stock');
        });
        $this->db->table('items')->insert([
            ['id' => 1, 'item_type' => 'normal', 'stock' => 10],
            ['id' => 2, 'item_type' => 'normal', 'stock' => 20],
        ]);
        $this->db->table('attribute_options')->insert(['id' => 1, 'stock' => '10']);
        $session = new Store('stock-test', new ArraySessionHandler(120));
        $session->put('cart', [2 => ['qty' => 4, 'options_id' => []]]);
        $this->db->getContainer()->instance('session', $session);
    }

    public function test_snapshot_decrements_paid_items_even_when_session_cart_changed(): void
    {
        PriceHelper::stockDecrese([1 => ['qty' => 2, 'options_id' => [1]]]);
        $this->assertEquals(8, $this->db->table('items')->where('id', 1)->value('stock'));
        $this->assertEquals(20, $this->db->table('items')->where('id', 2)->value('stock'));
        $this->assertSame('8', $this->db->table('attribute_options')->value('stock'));
    }

    public function test_legacy_call_still_uses_session_cart(): void
    {
        PriceHelper::stockDecrese();
        $this->assertEquals(10, $this->db->table('items')->where('id', 1)->value('stock'));
        $this->assertEquals(16, $this->db->table('items')->where('id', 2)->value('stock'));
    }

    public function test_empty_explicit_snapshot_does_not_fall_back_to_new_cart(): void
    {
        PriceHelper::stockDecrese([]);
        $this->assertEquals(20, $this->db->table('items')->where('id', 2)->value('stock'));
    }
}
