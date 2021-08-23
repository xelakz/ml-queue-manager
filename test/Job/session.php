<?php
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\MessagingProducerConfig;

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
            'name' => 'sessionsync', //Queue name
        ],
        [
            'name' => 'sessionstatus', //Queue name
        ]
    ]
];
Config::set($config);

$producer = MessagingProducerConfig::driver();

// $message = json_decode('{"provider":"pin", "command":"sync"}', true);
// Queue::push('sessionsync', new \MultilineQM\Jobs\SessionSync($message));

$message = json_decode('{"provider":"pin", "command":"status"}', true);
Queue::push('sessionstatus', new \MultilineQM\Jobs\SessionSync($message));