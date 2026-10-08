<?php

namespace Tests\Unit;

use App\Services\KlaviyoQueueReadiness;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class KlaviyoQueueReadinessTest extends TestCase
{
    private Repository $config;
    private $database;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Container;
        Container::setInstance($app);
        $this->config = new Repository;
        $app->instance('config', $this->config);
        $this->database = Mockery::mock();
        $app->instance('db', $this->database);
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_missing_and_discarded_queues_never_pass(): void
    {
        foreach ([null, 'null', 'sync', 'typo', 'custom'] as $driver) {
            $this->config->set('queue.default', 'marketing');
            $this->config->set('queue.connections.marketing', $driver === null ? null : ['driver' => $driver]);
            $this->assertSame('FAIL', (new KlaviyoQueueReadiness)->checks()[0][1]);
        }
    }

    public function test_driver_is_checked_even_when_connection_name_looks_valid(): void
    {
        $this->config->set('queue.default', 'redis');
        $this->config->set('queue.connections.redis', ['driver' => 'sync']);
        $this->assertSame('FAIL', (new KlaviyoQueueReadiness)->checks()[0][1]);
    }

    public function test_valid_remote_drivers_require_no_connection_or_dispatch(): void
    {
        $this->database->shouldReceive('connection')->never();
        foreach (['redis', 'sqs', 'beanstalkd'] as $driver) {
            $this->config->set('queue.default', 'marketing');
            $this->config->set('queue.connections.marketing', ['driver' => $driver]);
            $checks = (new KlaviyoQueueReadiness)->checks();
            $this->assertSame('PASS', $checks[0][1]);
            $this->assertStringContainsString('worker health is not checked', $checks[0][2]);
        }
    }

    public function test_database_queue_uses_its_own_connection_and_table(): void
    {
        $this->databaseConfig();
        $schema = Mockery::mock();
        $this->expectSchema($schema);
        $schema->shouldReceive('hasTable')->once()->with('marketing_jobs')->andReturnTrue();
        $schema->shouldReceive('hasColumns')->once()->with('marketing_jobs',
            ['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])->andReturnTrue();
        $this->assertSame('PASS', (new KlaviyoQueueReadiness)->checks()[1][1]);
    }

    public function test_missing_queue_table_fails_without_dispatching(): void
    {
        $this->databaseConfig();
        $schema = Mockery::mock();
        $this->expectSchema($schema);
        $schema->shouldReceive('hasTable')->with('marketing_jobs')->andReturnFalse();
        $schema->shouldReceive('hasColumns')->never();
        $this->assertSame('FAIL', (new KlaviyoQueueReadiness)->checks()[1][1]);
    }

    public function test_connection_errors_fail_without_printing_credentials(): void
    {
        $this->databaseConfig();
        $this->database->shouldReceive('connection')->with('queue_db')->andThrow(new \RuntimeException('secret-password'));
        $checks = (new KlaviyoQueueReadiness)->checks();
        $this->assertSame('FAIL', $checks[1][1]);
        $this->assertStringNotContainsString('secret-password', json_encode($checks));
    }

    public function test_queue_table_with_missing_columns_fails(): void
    {
        $this->databaseConfig();
        $schema = Mockery::mock();
        $this->expectSchema($schema);
        $schema->shouldReceive('hasTable')->with('marketing_jobs')->andReturnTrue();
        $schema->shouldReceive('hasColumns')->andReturnFalse();
        $this->assertSame('FAIL', (new KlaviyoQueueReadiness)->checks()[1][1]);
    }

    public function test_validate_command_returns_failure_for_null_queue_even_when_other_checks_pass(): void
    {
        foreach (['public_key', 'private_api_key', 'email_list_id', 'catalog_feed_token', 'queue'] as $key) {
            $this->config->set('services.klaviyo.'.$key, 'test-only');
        }
        $this->config->set('queue.default', 'marketing');
        $this->config->set('queue.connections.marketing', ['driver' => 'null']);
        $schema = Mockery::mock();
        $schema->shouldReceive('hasTable')->times(5)->andReturnTrue();
        Container::getInstance()->instance('db.schema', $schema);
        $builder = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $builder->shouldReceive('limit')->once()->with(1)->andReturnSelf();
        $builder->shouldReceive('get')->once()->andReturn(new \Illuminate\Database\Eloquent\Collection([new \App\Models\Item]));
        $catalog = Mockery::mock(\App\Services\KlaviyoCatalogService::class);
        $catalog->shouldReceive('query')->once()->andReturn($builder);
        $catalog->shouldReceive('map')->once()->andReturn([
            'id' => '1', 'title' => 'Filter', 'description' => 'Oil filter',
            'link' => 'https://example.test/product/1', 'image_link' => 'https://example.test/1.jpg',
            'price' => 10, 'inventory_quantity' => 1, 'inventory_policy' => 1,
        ]);
        $client = Mockery::mock(\App\Services\KlaviyoClient::class);
        $client->shouldNotReceive('validateCredentials');
        $command = new \App\Console\Commands\ValidateKlaviyoIntegration;
        $input = new \Symfony\Component\Console\Input\ArrayInput(['--email-only' => true, '--skip-api' => true], $command->getDefinition());
        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setInput($input);
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $output));
        $this->assertSame(1, $command->handle($client, $catalog));
        $display = $output->fetch();
        $this->assertStringContainsString('Queue connection', $display);
        $this->assertStringContainsString('Resolve every FAIL', $display);
        $this->assertStringNotContainsString('Local integration checks passed', $display);
    }

    private function databaseConfig(): void
    {
        $this->config->set('queue.default', 'marketing');
        $this->config->set('queue.connections.marketing', [
            'driver' => 'database', 'connection' => 'queue_db', 'table' => 'marketing_jobs',
        ]);
    }

    private function expectSchema($schema): void
    {
        $connection = Mockery::mock();
        $this->database->shouldReceive('connection')->once()->with('queue_db')->andReturn($connection);
        $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);
    }
}
