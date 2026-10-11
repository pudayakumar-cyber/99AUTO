<?php

// Only used by the opt-in MySQL concurrency test, never by the application.
require __DIR__.'/../../vendor/autoload.php';

$database = getenv('COUPON_TEST_DATABASE');
if (getenv('COUPON_TEST_MYSQL_ENABLED') !== '1'
    || ! preg_match('/^coupon_usage_test_[a-f0-9]{16}$/D', (string) $database)) {
    throw new RuntimeException('Isolated coupon test database required.');
}
$capsule = new Illuminate\Database\Capsule\Manager;
$capsule->addConnection([
    'driver' => 'mysql', 'host' => '127.0.0.1',
    'port' => getenv('COUPON_TEST_MYSQL_PORT') ?: 33379,
    'database' => $database,
    'username' => getenv('COUPON_TEST_MYSQL_USER') ?: 'root',
    'password' => getenv('COUPON_TEST_MYSQL_PASSWORD') ?: '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$order = App\Models\Order::findOrFail((int) ($argv[1] ?? 0));
echo (new App\Services\CouponUsageService)->record($order) ? '1' : '0';
