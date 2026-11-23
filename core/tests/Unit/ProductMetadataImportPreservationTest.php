<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Services\ItemCsvImporter;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductMetadataImportPreservationTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite is required for isolated import tests.');
        }
        $app = new Container;
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
        $this->database = new Capsule($app);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $app->instance('db', $this->database->getDatabaseManager());
        $app->instance('db.schema', $this->database->schema());
        $app->instance('config', new Repository($app->make('config')->toArray()));
        $this->database->schema()->create('items', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('meta_description')->nullable();
            $table->integer('stock')->default(0);
            $table->timestamps();
        });
        (require __DIR__.'/../../database/migrations/2026_11_18_000001_add_custom_product_metadata_to_items.php')->up();
        DB::table('items')->insert([
            'id' => 9,
            'name' => 'Existing listing',
            'meta_description' => 'Existing imported description',
            'seo_title' => 'Reviewed custom title',
            'seo_meta_description' => 'Reviewed custom description',
            'stock' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        if (isset($this->database)) {
            $this->database->getDatabaseManager()->disconnect();
        }
        parent::tearDown();
    }

    public function test_actual_csv_scalar_import_keeps_both_custom_fields_when_legacy_meta_changes(): void
    {
        $method = new ReflectionMethod(ItemCsvImporter::class, 'syncExistingItem');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke(new ItemCsvImporter, 9, [
            'meta description' => 'Fresh importer legacy meta',
            'stock' => '14',
        ], 'Updated listing'));

        $row = DB::table('items')->where('id', 9)->first();
        $this->assertSame('Updated listing', $row->name);
        $this->assertSame('Fresh importer legacy meta', $row->meta_description);
        $this->assertSame(14, $row->stock);
        $this->assertSame('Reviewed custom title', $row->seo_title);
        $this->assertSame('Reviewed custom description', $row->seo_meta_description);
    }

    public function test_standard_item_mass_assignment_cannot_bypass_dedicated_metadata_validation(): void
    {
        $item = Item::findOrFail(9);
        $item->update([
            'name' => 'Edited listing',
            'seo_title' => 'Unvalidated replacement',
            'seo_meta_description' => 'Unvalidated replacement description',
        ]);
        $row = DB::table('items')->where('id', 9)->first();
        $this->assertSame('Edited listing', $row->name);
        $this->assertSame('Reviewed custom title', $row->seo_title);
        $this->assertSame('Reviewed custom description', $row->seo_meta_description);
    }
}
