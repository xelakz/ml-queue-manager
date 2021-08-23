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
            'name' => 'minmax', //Queue name
        ]
    ]
];
Config::set($config);


$message = json_decode('{"request_uid":"00914096-c1f0-4a4a-92ea-6affbdf26eae","request_ts":"1628132151.38800300","sub_command":"scrape","command":"minmax","data":{"provider":"pin","market_id":"OSFH20758559717","sport":1,"schedule":"inplay","event_id":"1374119302","odds":"1.15","memUID":"9b669490c0c413721b62fd37921332b3","counter":1}}', true);
Queue::push('minmax', new \MultilineQM\Jobs\MinMax($message));
