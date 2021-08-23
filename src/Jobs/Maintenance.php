<?php
namespace MultilineQM\Jobs;

use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Scraper\ProxyIP;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\Scraper\QueueMAINTENANCE;

class Maintenance extends \MultilineQM\Job
{
    private $cache;
    private $producer;

    public function __construct($message, KafkaProducer $producer, Redis $cache)
    {
        $this->message = $message;

        $this->cache = $cache;

        $this->producer = $producer;
    }

    public function handle()
    {
        $start_time = microtime(true);
        OutPut::normal("Start processing MAINTENANCE: " . json_encode($this->message) . PHP_EOL);

        $provider = $this->message["provider"];

        $continue = true;
        if (empty($provider))
        {
            OutPut::normal("MAINTENANCE: Invalid payload." . PHP_EOL);
            $continue = false;
        }

        $connect = $this->cache->getConnect();
        if (!$connect) 
        {
            OutPut::normal( "MAINTENANCE: Unable to connect to redis." . PHP_EOL);
            $continue = false;
        }

        $proxy = new ProxyIP($this->cache, $provider);
        $proxy_url = $proxy->getOne();
        if (empty($proxy_url)) 
        {
            OutPut::normal("Proxy is empty." . PHP_EOL);
            $continue = false;
        }

        if ($continue)
        {
            $base_params = [];
            $header_params = [];
            switch ($provider) {
                case 'pin':
                    $params = new \MultilineQM\Scraper\Provider\Pin\Params("", "", $proxy_url);
                    $base_params = [
                        'base_uri' => 'https://status.pinnacle.com',
                        'timeout'  => 5.0,
                    ];
                    $header_params = [
                        'headers' => [
                            'Content-Type'=>"application/html",
                        ]
                    ];
                    break;
            }

            $client = new Client($base_params);

            $request = new Request($client, $header_params);

            $service = $this->getService($request, $provider, $this->producer, $this->cache);

            $service->process($this->message);
        }

        \MultilineQM\Jobs\Helper::showExecutionTime("MAINTENANCE", $start_time, json_encode($this->message));
    }

    private function getService($request, $provider, $producer, $cache)
    {
        $service = null;
        try {
            switch ($provider) {
                case 'pin':
                    $service = new \MultilineQM\Scraper\Provider\Pin\Maintenance($request, $producer, $cache);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
        return $service;
    }
}