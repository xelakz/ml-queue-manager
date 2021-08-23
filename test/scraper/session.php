<?php

use GuzzleHttp\Client;
use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;

use MultilineQM\Scraper\Client\Request;
use MultilineQM\Scraper\ProxyIP;

require_once __DIR__ . '/../../vendor/autoload.php';

$driver = new \MultilineQM\Queue\Driver\Redis(getenv('REDIS_HOST') ? getenv('REDIS_HOST') : '127.0.0.1');


$config = new ProducerConfig();
$config->setBootstrapServer('ml-qm-kafka:9092');
$config->setUpdateBrokers(true);
$config->setAcks(-1);
$producer = new Producer($config);

$proxy = new ProxyIP($driver, 'pin');
$proxy_url = $proxy->getOne();

$credentials =  base64_encode('pwf0426002:mul71l1n3');
$params = [
    'headers' => [
        'Content-Type'=>"application/json",
        'Authorization'=>"Basic {$credentials}",
    ],
    "proxy" => $proxy_url,
];

$client = new Client([
    'base_uri' => 'https://api.ps3838.com',
    'timeout'  => 5.0,
]);

$request = new Request($client, $params);

// $service = new MultilineQM\Scraper\Provider\Isn\Login($request);
// $service = new MultilineQM\Scraper\Provider\Hg\Login($request);
$service = new MultilineQM\Scraper\Provider\Pin\Login($request, $producer, $driver);

$message = ["username"=>"pwf0426002", "password"=>"mul71l1n3", "proxy"=>$proxy_url, "category"=>"minmax"];
$service->process($message);

?>