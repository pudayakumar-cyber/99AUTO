<?php

namespace Tests\Integration;

use App\Models\Order;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** Opt-in: creates and drops its own uniquely named database on a local test server. */
class CouponUsageMysqlTest extends TestCase
{
    private ?\PDO $server = null;
    private ?string $database = null;
    private Capsule $db;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('COUPON_TEST_MYSQL_ENABLED') !== '1') {
            $this->markTestSkipped('Enable only against a disposable local MySQL server.');
        }
        $config = [
            'driver' => 'mysql', 'host' => '127.0.0.1',
            'port' => (int) (getenv('COUPON_TEST_MYSQL_PORT') ?: 33379),
            'username' => getenv('COUPON_TEST_MYSQL_USER') ?: 'root',
            'password' => getenv('COUPON_TEST_MYSQL_PASSWORD') ?: '',
        ];
        $this->server = new \PDO('mysql:host=127.0.0.1;port='.$config['port'], $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $name = 'coupon_usage_test_'.bin2hex(random_bytes(8));
        $this->server->exec('CREATE DATABASE `'.$name.'`');
        $this->database = $name; // Only a successfully created database may be dropped.
        putenv('COUPON_TEST_DATABASE='.$name);
        $this->db = new Capsule;
        $this->db->addConnection($config + ['database' => $name]);
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        $app = $this->db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->db->schema());
        Facade::setFacadeApplication($app);
        $this->db->schema()->create('orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->text('discount')->nullable();
            $table->string('transaction_number');
            $table->timestamps();
        });
        $this->db->schema()->create('promo_codes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->integer('no_of_times');
        });
        (require __DIR__.'/../../database/migrations/2026_10_08_000000_add_coupon_usage_marker_to_orders.php')->up();
        $this->db->table('promo_codes')->insert(['id' => 1, 'no_of_times' => 10]);
        // Make overlapping requests likely instead of relying on scheduler timing.
        $this->db->getConnection()->unprepared('CREATE TRIGGER test_coupon_delay BEFORE UPDATE ON promo_codes FOR EACH ROW DO SLEEP(0.1)');
    }

    protected function tearDown(): void
    {
        if ($this->server && $this->database) {
            $this->server->exec('DROP DATABASE `'.$this->database.'`');
        }
        putenv('COUPON_TEST_DATABASE');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Order::unsetConnectionResolver();
        parent::tearDown();
    }

    private function order(): int
    {
        return (int) Order::create(['transaction_number' => 'ORD-test',
            'discount' => json_encode(['discount' => 10, 'code' => ['id' => 1]])])->id;
    }

    private function concurrently(array $ids): int
    {
        $workers = [];
        foreach ($ids as $id) {
            $process = proc_open([PHP_BINARY, __DIR__.'/../Support/coupon_usage_worker.php', (string) $id],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $changed = 0;
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            $this->assertSame(0, $status, $error);
            $this->assertContains($output, ['0', '1']);
            $changed += (int) $output;
        }
        return $changed;
    }

    public function test_parallel_retries_of_one_order_only_decrement_once(): void
    {
        $this->assertSame(1, $this->concurrently(array_fill(0, 8, $this->order())));
        $this->assertSame(9, (int) $this->db->table('promo_codes')->value('no_of_times'));
    }

    public function test_parallel_distinct_orders_do_not_lose_decrements(): void
    {
        $ids = array_map(fn () => $this->order(), range(1, 8));
        $this->assertSame(8, $this->concurrently($ids));
        $this->assertSame(2, (int) $this->db->table('promo_codes')->value('no_of_times'));
        $this->assertSame(8, $this->db->table('orders')->whereNotNull('coupon_usage_recorded_at')->count());
    }

    public function test_parallel_orders_never_make_balance_negative(): void
    {
        $this->db->table('promo_codes')->update(['no_of_times' => 2]);
        $ids = array_map(fn () => $this->order(), range(1, 8));
        $this->assertSame(2, $this->concurrently($ids));
        $this->assertSame(0, (int) $this->db->table('promo_codes')->value('no_of_times'));
        $this->assertSame(8, $this->db->table('orders')->whereNotNull('coupon_usage_recorded_at')->count());
    }

    public function test_migration_rejects_nontransactional_tables_before_adding_marker(): void
    {
        $migration = require __DIR__.'/../../database/migrations/2026_10_08_000000_add_coupon_usage_marker_to_orders.php';
        $migration->down();
        $this->db->getConnection()->statement('ALTER TABLE promo_codes ENGINE=MyISAM');
        try {
            $migration->up();
            $this->fail('Migration should reject MyISAM.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('requires InnoDB: promo_codes', $exception->getMessage());
        }
        $this->assertFalse($this->db->schema()->hasColumn('orders', 'coupon_usage_recorded_at'));
    }
}
