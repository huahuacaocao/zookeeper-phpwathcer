<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\WatcherEvent;

require_once "./config/config.php";

echo "cache starting ...\r\n";
$host = $config['ZOOKEEPER_ADDRESS'];
$root = rtrim($config['ZOOKEEPER_PATH'], '/');
$redisConfig = [
    'REDIS_HOST' => $config['REDIS_HOST'],
    'REDIS_PORT' => $config['REDIS_PORT'],
    'REDIS_DATABASE' => $config['REDIS_DATABASE'],
];

(new WatcherEvent($host, $root, $redisConfig))->run();


while (true) {
    sleep(1);
}
