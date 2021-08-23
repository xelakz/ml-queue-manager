<?php
use MultilineQM\Config\Config;
use MultilineQM\Queue\Queue;
require_once __DIR__ . '/vendor/autoload.php';
// Config::set(include(__DIR__ . '/test/Config.php'));
$config = [
    'basics' => [
        'name' => 'ml-queue-1',//When multiple servers start at the same time, you need to set the names separately
        'driver' => new \MultilineQM\Queue\Driver\Redis(getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1'),
    ],
    'queue' => [
        [
            'name' => 'test2',//Queue name
        ],
        [
            'name' => 'test3',//Queue name
        ],
        [
            'name' => 'test4',//Queue name
        ],
        [
            'name' => 'test5',//Queue name
        ],
        [
            'name' => 'test6',//Queue name
        ],
        [
            'name' => 'test7',//Queue name
        ],
        [
            'name' => 'test8',//Queue name
        ],
        [
            'name' => 'test9',//Queue name
        ],
        [
            'name' => 'test10',//Queue name
        ],
        [
            'name' => 'test11',//Queue name
        ],
        [
            'name' => 'test12',//Queue name
        ],
        [
            'name' => 'test13',//Queue name
        ],
        [
            'name' => 'test14',//Queue name
        ],
        [
            'name' => 'test15',//Queue name
        ],
        [
            'name' => 'test16',//Queue name
        ],
        [
            'name' => 'test17',//Queue name
        ],
        [
            'name' => 'test18',//Queue name
        ],
        [
            'name' => 'test19',//Queue name
        ],
        [
            'name' => 'test20',//Queue name
        ]
    ]
];
Config::set($config);

use longlang\phpkafka\Consumer\ConsumeMessage;
use longlang\phpkafka\Consumer\Consumer;
use longlang\phpkafka\Consumer\ConsumerConfig;

$config = new ConsumerConfig();
$config->setBroker('ml-qm-kafka:9092');
$config->setTopic('hg_minmax_req'); // topic
$config->setGroupId('php-ml-qm'); // group ID
$config->setClientId('php-ml-qm'); // client ID. Use different settings for different consumers.
$config->setGroupInstanceId('php-ml-qm'); // group instance ID. Use different settings for different consumers.
$config->setInterval(0.1);
$consumer = new Consumer($config);
while(true) {
    $message = $consumer->consume();
    if($message) {
        // var_dump($message->getKey() . ':' . $message->getValue());
        $msg = json_decode($message->getValue(), true);
        for ($i=2; $i<=20; $i++) {
            Queue::push('test' . $i, new \MultilineQMTest\Job\FailedJobParam($msg));
        }

        $consumer->ack($message); // commit manually
    }
    sleep(1.0);
}
