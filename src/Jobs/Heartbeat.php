<?php
declare(strict_types=1);
namespace MultilineQM\Jobs;

use GuzzleHttp\Client;

use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Scraper\ProxyIP;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\Scraper\QueueSession;

class Heartbeat extends \MultilineQM\Job
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
        OutPut::normal('Start processing HEARTBEAT: ' . json_encode($this->message) . PHP_EOL);

        $provider = $this->message["provider"];

        $connect = $this->cache->getConnect();
        if (!$connect) 
        {
            OutPut::normal("Unable to connect to redis." . PHP_EOL);
            $continue = false;
        }

        $queue_session = new QueueSession($this->cache, 'pin', '');
        $userDetails = $queue_session->getAllUserDetails();
        foreach($userDetails as $detail)
        {
            $username = $detail["username"];
            $password = $detail["password"];
            $proxy_url = $detail["proxy"];
            OutPut::warning("HEARTBEAT {$username} -> {$proxy_url}" . PHP_EOL);

            $base_params = [];
            $header_params = [];
            switch ($provider) {
                case 'pin':
                    $params = new \MultilineQM\Scraper\Provider\Pin\Params($username, $password, $proxy_url);
                    $base_params = $params->getBaseParams();
                    $header_params = $params->getRequestParams();
                    break;
            }

            $client = new Client($base_params);

            $request = new Request($client, $header_params);

            $service = $this->getService($request, $provider, $this->producer, $this->cache);

            $this->message["username"] = $username;
            $service->process($this->message);
        }

        \MultilineQM\Jobs\Helper::showExecutionTime('HEARTBEAT', $start_time, json_encode($this->message));
    }

    private function getService($request, $provider, $producer, $cache)
    {
        $service = null;
        try {
            switch ($provider) {
                case 'pin':
                    $service = new \MultilineQM\Scraper\Provider\Pin\Heartbeat($request, $producer, $cache);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
        return $service;
    }
}