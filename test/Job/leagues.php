<?php
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
use MultilineQM\Config\BasicsConfig;

require_once __DIR__ . '/../../vendor/autoload.php';
$config = [
    'basics' => [
        'name' => 'ml-queue-1',//When multiple servers start at the same time, you need to set the names separately
        'driver' => new \MultilineQM\Queue\Driver\Redis(getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1'),
    ],
    'kafkaproducer' => [
        'driver' => new \MultilineQM\Queue\Driver\KafkaProducer(getenv('KAFKA_HOST') ? getenv('KAFKA_HOST') : '127.0.0.1', getenv('KAFKA_PORT') ? getenv('KAFKA_PORT') : '9092'),
    ],
    'queue' => [
        [
            'name' => 'leagues', //Queue name
        ]
    ]
];
Config::set($config);

$message = json_decode('{"request_uid":"449a4109-64a6-41d8-82df-4a7f0b88cf1b","request_ts":"1628003188.17423800","command":"odd","sub_command":"scrape","data":{"provider":"pin","schedule":"inplay","sport":"1"}}', true);
Queue::push('leagues', new \MultilineQM\Jobs\Leagues($message));
