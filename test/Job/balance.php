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
            'name' => 'balance', //Queue name
        ]
    ]
];
Config::set($config);

$message = json_decode('{"request_uid":"79f86d7c-76e5-47dc-8693-48c6217619d2","request_ts":"1627880877.99249900","command":"balance","sub_command":"scrape","data":{"provider":"pin","username":"PWX2300006"}}', true);
Queue::push('balance', new \MultilineQM\Jobs\Betting($message));
