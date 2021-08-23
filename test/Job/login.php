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
        'driver' => new \MultilineQM\Queue\Driver\KafkaProducer(getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1'),
    ],
    'queue' => [
        [
            'name' => 'login', //Queue name
        ]
    ]
];
Config::set($config);

$accounts = [
    ['username' => 'PWX2305003',
    'password' => 'pinpass8888',
    'category'  => 'odds'],
    ["username" => 'PWX2305004',
    'password' => 'pinpass8888',
    'category'  => 'odds'],
    ["username" => 'PWX2306000',
    'password' => 'pinpass8888',
    'category'  => 'odds'],
    ["username" => 'PWX2306001',
    'password' => 'pinpass8888',
    'category'  => 'odds'],
    ["username" => 'PWX2303000',
    'password' => '8888passs',
    'category'  => 'odds'],
    ["username" => 'PWX2303001',
    'password' => '8888passs',
    'category'  => 'odds'],
    ["username" => 'PWX2303002',
    'password' => '8888passs',
    'category'  => 'odds'],
    ["username" => 'PWX2301001',
    'password' => 'PWX23JOHN',
    'category'  => 'minmax'],
    ["username" => 'PWX2301000',
    'password' => 'PWX23JOHN',
    'category'  => 'minmax'],
    ["username" => 'PWX2302001',
    'password' => 'PWX23JOHN',
    'category'  => 'minmax'],
    ["username" => 'PWX2302000',
    'password' => 'PWX23JOHN',
    'category'  => 'minmax'],
    ["username" => 'PWX230000Z',
    'password' => 'aaaa8888',
    'category'  => 'minmax'],
    ["username" => 'PWX230000Y',
    'password' => 'aaaa8888',
    'category'  => 'minmax'],
    ["username" => 'PWX230000X',
    'password' => 'aaaa8888',
    'category'  => 'minmax'],
    ["username" => 'PWX230000W',
    'password' => 'aaaa8888',
    'category'  => 'minmax'],
    ["username" => 'PWX2304001',
    'password' => 'aaaa8888',
    'category'  => 'minmax'],
    ["username" => 'PWX2304000',
    'password' => 'aaaa8888',
    'category'  => 'minmax'],
    ["username" => 'HCP00ML003',
    'password' => 'pass8888',
    'category'  => 'bet'],
    ["username" => 'HCP00ML004',
    'password' => 'pass8888',
    'category'  => 'bet'],
    ["username" => 'HCP00ML002',
    'password' => 'pass8888',
    'category'  => 'bet'],
    ["username" => 'HCP00ML001',
    'password' => 'pass8888',
    'category'  => 'bet'],
    ["username" => 'pwf0426005',
    'password' => 'mul71l1n3',
    'category'  => 'bet'],
    ["username" => 'pwf0426001',
    'password' => 'mul71l1n3',
    'category'  => 'bet'],
];
foreach ($accounts as $value) {
    $username = $value['username'];
    $password = $value['password'];
    $category = $value['category'];
    $message = json_decode('{"request_uid":"56e92c9a-24f2-478d-b4f9-9678f23d78c4","request_ts":"1627025816.69943400","command":"session","sub_command":"add","data":{"provider":"pin","username":"' . $username . '","password":"' . $password . '","category":"' . $category. '","usage":"OPEN"}}', true);
    Queue::push('login', new \MultilineQM\Jobs\Login($message));
}
