<?php

use MultilineQM\{Config\Config, OutPut\OutPut, Queue\Queue};
use longlang\phpkafka\Consumer\{Consumer, ConsumerConfig};
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
            'name' => 'leagues',//Queue name
            'pin_req' => \MultilineQM\Jobs\Leagues::class,
        ],
        [
            'name' => 'events',//Queue name
            'pin_events' => \MultilineQM\Jobs\Events::class,
        ],
        [
            'name' => 'runningevents',//Queue name
            'pin_running_events' => \MultilineQM\Jobs\RunningEvents::class,
        ],
        [
            'name' => 'odds',//Queue name
            'pin_odds' => \MultilineQM\Jobs\Odds::class,
        ],
        [
            'name' => 'oddssince',//Queue name
            'pin_odds_since' => \MultilineQM\Jobs\OddsSince::class,
        ],
        [
            'name' => 'minmax',//Queue name
            'pin_minmax_req' => \MultilineQM\Jobs\MinMax::class,
        ],
        [
            'name' => 'bet',//Queue name
            'pin_bet_req' => \MultilineQM\Jobs\Betting::class
        ],
        [
            'name' => 'order',//Queue name
            'pin_openorder_req' => \MultilineQM\Jobs\Betting::class
        ],
        [
            'name' => 'settlement',//Queue name
            'pin_settlement_req' => \MultilineQM\Jobs\Betting::class
        ],
        [
            'name' => 'balance',//Queue name
            'pin_balance_req' => \MultilineQM\Jobs\Betting::class
        ],
        [
            'name' => 'session',//Queue name
            'pin_session_req' => \MultilineQM\Jobs\Session::class
        ],
    ]
];

Co\run(function () use($config) {
    try {
        Config::set($config);
        
        $cache    = BasicsConfig::driver();
        $producer = MessagingProducerConfig::driver();

        $consumer_config = new ConsumerConfig();
        $consumer_config->setBroker(getenv('KAFKA_HOST') . ':9092');
        $topics = array('pin_req',
            'pin_events',
            'pin_running_events',
            'pin_odds',
            'pin_odds_since',
            'pin_minmax_req',
            'pin_bet_req',
            'pin_openorder_req',
            'pin_settlement_req',
            'pin_balance_req',
            'pin_session_req'
        );
        $consumer_config->setTopic($topics); // topic
        $consumer_config->setGroupId('ml-qm'); // group ID
        $consumer_config->setClientId('ml-qm'); // client ID. Use different settings for different consumers.
        $consumer_config->setGroupInstanceId('ml-qm'); // group instance ID. Use different settings for different consumers.
        $consumer_config->setInterval(0.1);
        $consumer = new Consumer($consumer_config);

        while (true) {
            $message = $consumer->consume();
            if ($message) {
                $msg = json_decode($message->getValue(), true);
                OutPut::normal(json_encode($msg) . PHP_EOL);
                foreach ($config['queue'] as $key => $queue) {
                    if (!empty($config['queue'][$key][$message->getTopic()])) {
                        go(function () use ($queue, $config, $key, $message, $msg, $producer, $cache) {
                            if ($queue['name'] == "session")
                            {
                                Queue::push($queue['name'], new $config['queue'][$key][$message->getTopic()]($msg, $producer, $cache));
                            }
                            else
                            {
                                Queue::push($queue['name'], new $config['queue'][$key][$message->getTopic()]($msg));
                            }
                        });
                    }
                }
                $consumer->ack($message); // commit manually
            }
            usleep(50000);
        }
    } catch (\Exception $e) {
        OutPut::normal("Error: " . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL);
    }

});