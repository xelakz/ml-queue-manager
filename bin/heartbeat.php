<?php
declare(strict_types=1);
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\MessagingProducerConfig;

require_once __DIR__ . '/../vendor/autoload.php';
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
            'name' => 'heartbeat', //Queue name
        ],
        [
            'name' => 'sessionsync', //Queue name
        ],
        [
            'name' => 'maintenance', //Queue name
        ],
        [
            'name' => 'sessionstatus', //Queue name
        ]
    ]
];
Config::set($config);

Co\run(function()
{
    $producer = MessagingProducerConfig::driver();
    $cache    = BasicsConfig::driver();

    go(function() use($producer, $cache)
    {
        $message = json_decode('{"provider":"pin"}', true);
        while (true) {
            Queue::push('maintenance', new \MultilineQM\Jobs\Maintenance($message, $producer, $cache));
            Co::sleep(60); // 1 min
        }
    });

    go(function() use($producer, $cache)
    {
        $message = json_decode('{"provider":"pin", "command":"status"}', true);
        while (true) {
            Queue::push('sessionstatus', new \MultilineQM\Jobs\SessionSync($message, $producer, $cache));
            Co::sleep(60 * 5); // 5 mins
        }
    });

    go(function() use($producer, $cache)
    {
        $message = json_decode('{"provider":"pin"}', true);
        while (true) {
            Queue::push('heartbeat', new \MultilineQM\Jobs\Heartbeat($message, $producer, $cache));
            Co::sleep(60 * 10); // 10 mins
        }
    });

    go(function() use($producer, $cache)
    {
        $message = json_decode('{"provider":"pin", "command":"sync"}', true);
        while (true) {
            Queue::push('sessionsync', new \MultilineQM\Jobs\SessionSync($message, $producer, $cache));
            Co::sleep(60 * 10); // 10 mins
        }
    });
});