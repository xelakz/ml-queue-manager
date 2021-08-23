<?php
require_once __DIR__ . '/vendor/autoload.php';

use MultilineQM\{Config\Config, OutPut\OutPut, Queue\Queue};
use longlang\phpkafka\Consumer\{Consumer, ConsumerConfig};

Co\run(function () {
    try {
        $defaultConfig = [
            'basics'        => [
                'name'   => 'ml-queue-1',//When multiple servers start at the same time, you need to set the names separately
                'driver' => new \MultilineQM\Queue\Driver\Redis(getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1'),
            ],
            'kafkaproducer' => [
                'path'   => '/tmp',
                'driver' => new \MultilineQM\Queue\Driver\KafkaProducer(getenv('KAFKA_HOST') ? getenv('KAFKA_HOST') : '127.0.0.1'),
            ],
            'queue'         => [
                [
                    'name'    => 'leagues',//Queue name
                    'pin_req' => \MultilineQM\Jobs\Leagues::class,
                ],
                [
                    'name'           => 'minmax',//Queue name
                    'pin_minmax_req' => \MultilineQM\Jobs\MinMax::class,
                ],
                [
                    'name'        => 'bet',//Queue name
                    'pin_bet_req' => \MultilineQM\Jobs\Bet::class
                ],
                [
                    'name'              => 'order',//Queue name
                    'pin_openorder_req' => \MultilineQM\Jobs\OpenOrder::class
                ],
                [
                    'name'               => 'settlement',//Queue name
                    'pin_settlement_req' => \MultilineQM\Jobs\Settlement::class
                ],
                [
                    'name'            => 'balance',//Queue name
                    'pin_balance_req' => \MultilineQM\Jobs\Balance::class
                ],
                [
                    'name'            => 'session',//Queue name
                    'pin_session_req' => \MultilineQM\Jobs\Session::class
                ],
            ]
        ];
        Config::set($defaultConfig);

        $config = new ConsumerConfig();
        $config->setBroker(getenv('KAFKA_HOST') . ':9092');
        $config->setTopic([
            'pin_req',
            //    'pin_minmax_req',
            //    'pin_bet_req',
            //    'pin_openorder_req',
            //    'pin_settlement_req',
            //    'pin_balance_req',
            //    'pin_session_req'
        ]); // topic
        $config->setGroupId('php-ml-qm'); // group ID
        $config->setClientId('php-ml-qm'); // client ID. Use different settings for different consumers.
        $config->setGroupInstanceId('php-ml-qm'); // group instance ID. Use different settings for different consumers.
        $config->setInterval(0.1);
        $consumer = new Consumer($config);

        while (true) {
            $message = $consumer->consume();
            if ($message) {
                $msg = json_decode($message->getValue(), true);
                OutPut::normal(json_encode($msg) . PHP_EOL);
                foreach ($defaultConfig['queue'] as $key => $queue) {
                    if (!empty($defaultConfig['queue'][$key][$message->getTopic()])) {
                        go(function () use ($queue, $defaultConfig, $key, $message, $msg) {
                            Queue::push($queue['name'], new $defaultConfig['queue'][$key][$message->getTopic()]($msg));
                        });
                        $consumer->ack($message); // commit manually
                    }
                }
            }
            usleep(50000);
        }
    } catch (\Exception $e) {
        OutPut::normal("Error: " . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL);
    }
});
