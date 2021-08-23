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
use MultilineQM\Scraper\QueueSession;

class Session extends \MultilineQM\Job
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
        OutPut::normal("Start processing SESSION: " . json_encode($this->message) . PHP_EOL);

        $request_uid = $this->message["request_uid"];
        $request_ts = $this->message["request_ts"];
        $command = $this->message["command"];
        $sub_command = $this->message["sub_command"];
        $provider = $this->message["data"]["provider"];
        $username = $this->message["data"]["username"];
        $password = '';

        $continue = true;
        if (empty($request_uid) || empty($request_ts) || empty($provider) || ($command!='session'))
        {
            OutPut::warning("SESSION: Invalid payload." . PHP_EOL);
            $continue = false;
        }

        $connect = $this->cache->getConnect();
        if (!$connect) 
        {
            OutPut::warning("SESSION: Unable to connect to redis." . PHP_EOL);
            $continue = false;
        }

        $proxy_url = "";
        if ($sub_command == "stop")
        {
            $proxy_url = "";
            $continue = true;
            $cacheActiveUser = $connect->get("session:{$provider}:active:{$username}");
            if (!empty($cacheActiveUser))
            {
                $userDict = json_decode($cacheActiveUser, true);
                if (!empty($userDict))
                {
                    if (empty($userDict["token"]))
                    {
                        OutPut::normal("{$username} already inactive." . PHP_EOL);
                        $continue = false;
                    }
                }
            }
        }
        elseif ($sub_command == "add")
        {
            $password = $this->message["data"]["password"];

            $cacheActiveUser = $connect->get("session:{$provider}:active:{$username}");
            if (!empty($cacheActiveUser))
            {
                $userDict = json_decode($cacheActiveUser, true);
                if (!empty($userDict))
                {
                    if (!empty($userDict["token"]))
                    {
                        OutPut::normal("{$username} already active." . PHP_EOL);
                        $continue = false;
                    }
                }
            }
            if ($continue)
            {
                $proxy = new ProxyIP($this->cache, $provider);
                $proxy_url = $proxy->getOne();
                if (empty($proxy_url)) 
                {
                    OutPut::normal("SESSION: Proxy is empty." . PHP_EOL);
                    $continue = false;
                }
            }
        }
        elseif ($sub_command == "category")
        {
            $category = $this->message["data"]["category"];

            $proxy_url = "";
            $continue = true;
            $cacheActiveUser = $connect->get("session:{$provider}:active:{$username}");
            $userDict = json_decode($cacheActiveUser, true);
            if (!empty($userDict))
            {
                if (!empty($userDict["token"]))
                {
                    if ($userDict["category"] == $category)
                    {
                        $continue = false;
                    }
                }
            }
        }
        else
        {
            $continue = false;
            OutPut::warning("SESSION: Invaluid sub_command: {$sub_command}." . PHP_EOL);
        }

        if ($continue)
        {
            $base_params = [];
            $header_params = [];
            switch ($provider) {
                case 'pin':
                    $client = null;

                    $request = null;
                    break;
                default:
                    $params = new \MultilineQM\Scraper\Provider\Pin\Params($username, $password, $proxy_url);
                    $base_params = $params->getBaseParams();
                    $header_params = $params->getRequestParams();

                    $client = new Client($base_params);

                    $request = new Request($client, $header_params);
                    break;
            }

            $service = $this->getService($request, $provider, $this->producer, $this->cache, $sub_command);

            $this->message["data"]["proxy"] = $proxy_url;
            $service->process($this->message);
        }

        \MultilineQM\Jobs\Helper::showExecutionTime("SESSION", $start_time, json_encode($this->message));
    }

    private function getService($request, $provider, $producer, $cache, $sub_command)
    {
        $service = null;
        try {
            switch ($provider) {
                case 'pin':
                    if ($sub_command == 'add')
                    {
                        $service = new \MultilineQM\Scraper\Provider\Pin\Login($request, $producer, $cache);
                    }
                    elseif ($sub_command == 'stop')
                    {
                        $service = new \MultilineQM\Scraper\Provider\Pin\Logout($request, $producer, $cache);
                    }
                    elseif ($sub_command == 'category')
                    {
                        $service = new \MultilineQM\Scraper\Provider\Pin\Category($request, $producer, $cache);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
        return $service;
    }
}