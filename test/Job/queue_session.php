<?php
use MultilineQM\Config\Config;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Scraper\QueueSession;

require_once __DIR__ . '/../../vendor/autoload.php';
$config = [
    'basics' => [
        'name' => 'ml-queue-1',//When multiple servers start at the same time, you need to set the names separately
        'driver' => new \MultilineQM\Queue\Driver\Redis(getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1'),
    ],
    'queue' => [
        [
            'name' => 'login', //Queue name
        ]
    ]
];
Config::set($config);

$queue_session = new QueueSession(BasicsConfig::driver(), 'pin', '');

$task = array(
        "request_uid"=>$message["request_uid"],
        "request_ts"=>$message["request_ts"],
        "command"=>$message["command"],
        "sub_command"=>$message["sub_command"],
    );
$selected_session = $queue_session->getAvailableSession('odds', $task);
var_dump($selected_session);

