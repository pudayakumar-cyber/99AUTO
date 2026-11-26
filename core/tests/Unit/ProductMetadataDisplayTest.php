<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Item;
use App\Support\ProductMetaDescription;
use App\Support\ProductMetaTitle;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Factory;
use PHPUnit\Framework\TestCase;

class ProductMetadataDisplayTest extends TestCase
{
    private function product(array $overrides = []): Item
    {
        $item = new Item;
        $item->setRawAttributes(array_merge([
            'name' => 'Brake Pads',
            'product_part_number' => 'BP-7',
            'meta_description' => 'Existing authored brake copy',
            'sort_details' => 'Short fallback',
            'details' => 'Details fallback',
        ], $overrides));
        $item->setRelation('brand', new Brand(['name' => 'Bosch']));
        foreach (['category', 'subcategory', 'childcategory'] as $relation) {
            $item->setRelation($relation, null);
        }

        return $item;
    }

    public function test_title_only_override_does_not_change_description(): void
    {
        $item = $this->product(['seo_title' => '  Handwritten A & B Title  ']);
        $this->assertSame('Handwritten A & B Title', ProductMetaTitle::for($item));
        $this->assertSame('Bosch - BP-7 - Brake Pads. Existing authored brake copy', ProductMetaDescription::for($item));
    }

    public function test_description_only_override_does_not_change_title(): void
    {
        $item = $this->product(['seo_meta_description' => '  Handwritten & complete description.  ']);
        $this->assertSame('Brake Pads - BP-7 | 99 Auto Parts', ProductMetaTitle::for($item));
        $this->assertSame('Handwritten & complete description.', ProductMetaDescription::for($item));
    }

    public function test_both_custom_fields_remain_independent_and_are_not_generated_or_truncated(): void
    {
        $title = 'Manual & accurate '.str_repeat('T', 75);
        $description = 'Manually authored & sourced '.str_repeat('D', 175);
        $item = $this->product(['seo_title' => "  {$title}  ", 'seo_meta_description' => "\n{$description}\t"]);
        $this->assertSame($title, ProductMetaTitle::for($item));
        $this->assertSame($description, ProductMetaDescription::for($item));
        $this->assertGreaterThan(60, mb_strlen(ProductMetaTitle::for($item)));
        $this->assertGreaterThan(160, mb_strlen(ProductMetaDescription::for($item)));
    }

    public function test_whitespace_only_custom_fields_fall_back_to_existing_authored_description(): void
    {
        $item = $this->product(['seo_title' => " \t\n ", 'seo_meta_description' => " \n\t "]);
        $this->assertSame('Brake Pads - BP-7 | 99 Auto Parts', ProductMetaTitle::for($item));
        $this->assertSame('Bosch - BP-7 - Brake Pads. Existing authored brake copy', ProductMetaDescription::for($item));
    }

    public function test_absent_custom_fields_keep_baseline_outputs_byte_identical(): void
    {
        $item = $this->product();
        $this->assertSame('Brake Pads - BP-7 | 99 Auto Parts', ProductMetaTitle::for($item));
        $this->assertSame('Bosch - BP-7 - Brake Pads. Existing authored brake copy', ProductMetaDescription::for($item));
        $this->assertArrayNotHasKey('seo_title', $item->getAttributes());
        $this->assertArrayNotHasKey('seo_meta_description', $item->getAttributes());

        $item = $this->product(['meta_description' => null, 'sort_details' => '<b>Short & existing</b>   fallback']);
        $this->assertSame('Bosch - BP-7 - Brake Pads. Short & existing fallback', ProductMetaDescription::for($item));
        $item = $this->product(['meta_description' => null, 'sort_details' => null, 'details' => 'Details fallback']);
        $this->assertSame('Bosch - BP-7 - Brake Pads. Details fallback', ProductMetaDescription::for($item));
    }

    private function renderedProductHead(Item $item): string
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root.'/resources/views/front/catalog/product.blade.php');
        $layout = file_get_contents($root.'/resources/views/master/front.blade.php');
        $titleStart = strpos($source, "@section('title')");
        $titleEnd = strpos($source, '@endsection', $titleStart) + strlen('@endsection');
        $initStart = strpos($source, '@php', $titleEnd);
        $initEnd = strpos($source, '@endphp', $initStart) + strlen('@endphp');
        $metaStart = strpos($source, "@section('meta')", $initEnd);
        $metaEnd = strpos($source, '@endsection', $metaStart) + strlen('@endsection');
        $layoutTitle = '<title>@yield(\'title\')</title>';
        $this->assertStringContainsString($layoutTitle, $layout);
        $this->assertStringContainsString("@yield('meta')", $layout);

        // Compile the real product title, initialization and metadata sections;
        // include the real product-layout title/yield markup, without booting Laravel.
        $blade = substr($source, $titleStart, $titleEnd - $titleStart)
            .substr($source, $initStart, $initEnd - $initStart)
            .substr($source, $metaStart, $metaEnd - $metaStart)
            .$layoutTitle."\n@yield('meta')";
        $filesystem = new Filesystem;
        $compiled = (new BladeCompiler($filesystem, sys_get_temp_dir()))->compileString($blade);
        $compiledFile = tempnam(sys_get_temp_dir(), 'product-meta-');
        file_put_contents($compiledFile, $compiled);

        $previousContainer = Container::getInstance();
        $container = new Container;
        $request = Request::create('https://example.test/product/brake-pads');
        $container->instance('request', $request);
        $container->instance('url', new UrlGenerator(new RouteCollection, $request));
        $container->alias('url', \Illuminate\Contracts\Routing\UrlGenerator::class);
        Container::setInstance($container);
        $__env = new Factory(new EngineResolver, new FileViewFinder($filesystem, [$root.'/resources/views']), new Dispatcher($container));
        $setting = (object) ['title' => 'Example Store'];
        ob_start();
        try {
            include $compiledFile;

            return ob_get_contents();
        } finally {
            ob_end_clean();
            unlink($compiledFile);
            Container::setInstance($previousContainer);
        }
    }

    public function test_blade_uses_existing_baseline_when_custom_fields_are_absent(): void
    {
        $html = $this->renderedProductHead($this->product());
        $this->assertMatchesRegularExpression('~<title>\s*Brake Pads - BP-7 \| 99 Auto Parts\s*</title>~', $html);
        $this->assertStringContainsString('<meta name="description" content="Bosch - BP-7 - Brake Pads. Existing authored brake copy">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Bosch - BP-7 - Brake Pads. Existing authored brake copy">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="Brake Pads - BP-7 | 99 Auto Parts">', $html);
    }

    public function test_blade_escapes_literal_custom_html_in_title_and_all_description_meta_tags(): void
    {
        $item = $this->product([
            'seo_title' => '  A & B "<great>" title  ',
            'seo_meta_description' => '  Use A & B "<safe>" today  ',
        ]);
        $html = $this->renderedProductHead($item);
        $this->assertMatchesRegularExpression('~<title>\s*A &amp; B &quot;&lt;great&gt;&quot; title\s*</title>~', $html);
        foreach (['title', 'twitter:title'] as $tag) {
            $this->assertStringContainsString('<meta name="'.$tag.'" content="A &amp; B &quot;&lt;great&gt;&quot; title">', $html);
        }
        $this->assertStringContainsString('<meta property="og:title" content="A &amp; B &quot;&lt;great&gt;&quot; title">', $html);
        foreach (['description', 'twitter:description'] as $tag) {
            $this->assertStringContainsString('<meta name="'.$tag.'" content="Use A &amp; B &quot;&lt;safe&gt;&quot; today">', $html);
        }
        $this->assertStringContainsString('<meta property="og:description" content="Use A &amp; B &quot;&lt;safe&gt;&quot; today">', $html);
        $this->assertStringNotContainsString('<great>', $html);
        $this->assertStringNotContainsString('<safe>', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_migration_adds_nullable_manual_fields_without_touching_legacy_description_and_rolls_back_only_new_fields(): void
    {
        $db = new Capsule;
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setAsGlobal();
        $db->bootEloquent();
        $app = $db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $db->getDatabaseManager());
        $app->bind('db.schema', fn () => $db->schema());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        try {
            $db->schema()->create('items', function (Blueprint $table): void {
                $table->id();
                $table->text('meta_description')->nullable();
            });
            $db->table('items')->insert(['id' => 17, 'meta_description' => 'Existing authored & literal']);

            $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_18_000001_add_custom_product_metadata_to_items.php';
            $migration->up();

            $columns = collect($db->getConnection()->select('PRAGMA table_info(items)'))->keyBy('name');
            $this->assertSame('varchar', strtolower($columns['seo_title']->type));
            $this->assertSame('text', strtolower($columns['seo_meta_description']->type));
            $migrationSource = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_11_18_000001_add_custom_product_metadata_to_items.php');
            $this->assertStringContainsString("string('seo_title', 255)", $migrationSource);
            $this->assertStringContainsString("text('seo_meta_description')", $migrationSource);
            $this->assertSame(0, $columns['seo_title']->notnull);
            $this->assertSame(0, $columns['seo_meta_description']->notnull);
            $this->assertNull($db->table('items')->where('id', 17)->value('seo_title'));
            $this->assertNull($db->table('items')->where('id', 17)->value('seo_meta_description'));
            $db->table('items')->insert([
                'id' => 18,
                'seo_title' => 'Manual title & literal',
                'seo_meta_description' => 'Manual description & literal',
            ]);
            $this->assertSame('Manual title & literal', $db->table('items')->where('id', 18)->value('seo_title'));
            $this->assertSame('Manual description & literal', $db->table('items')->where('id', 18)->value('seo_meta_description'));
            $this->assertNull($db->table('items')->where('id', 18)->value('meta_description'));
            $this->assertSame('Existing authored & literal', $db->table('items')->where('id', 17)->value('meta_description'));

            $migration->down();
            $this->assertFalse($db->schema()->hasColumn('items', 'seo_title'));
            $this->assertFalse($db->schema()->hasColumn('items', 'seo_meta_description'));
            $this->assertTrue($db->schema()->hasColumn('items', 'meta_description'));
            $this->assertSame('Existing authored & literal', $db->table('items')->where('id', 17)->value('meta_description'));
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
            Container::setInstance(null);
            Model::unsetConnectionResolver();
        }
    }
}
