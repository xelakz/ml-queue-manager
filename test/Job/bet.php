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
            'name' => 'bet', //Queue name
        ]
    ]
];
Config::set($config);


$message = json_decode('{"request_uid":"75cd905f-ed55-4063-9a9a-cdfdeac8652a-2978","request_ts":"1628685240.28899600","sub_command":"place","command":"bet","data":{"provider":"pin","sport":1,"stake":"50","odds":"1.37","market_id":"OSFH20767632396","event_id":1377254148,"score":"","username":"pwf0426001"}}', true);
Queue::push('bet', new \MultilineQM\Jobs\Betting($message));
