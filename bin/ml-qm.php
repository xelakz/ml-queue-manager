<?php
define('MULTILINE_QUEUE_CLI', true);

use MultilineQM\Config\Config;

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
            'name' => 'login', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'leagues', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'events', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'runningevents', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'odds', //Queue name
            'worker_number' => 10, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'oddssince', //Queue name
            'worker_number' => 10, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'bet', //Queue name
            'worker_number' => 10, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'minmax', //Queue name
            'worker_number' => 10, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'balance', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'openorder', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'settlement', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'heartbeat', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'sessionsync', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'sessionstatus', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'session', //Queue name
            'worker_number' => 30, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
        [
            'name' => 'maintenance', //Queue name
            'worker_number' => 4, //The current number of queue worker processes
            'memory_limit' => 0, //The maximum used memory of the current queue worker process, if exceeded, restart. unit MB
        ],
    ]
];
Config::set($config);
(new \MultilineQM\Console\Application())->run();