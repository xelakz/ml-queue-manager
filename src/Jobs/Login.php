<?php
namespace MultilineQM\Jobs;

use GuzzleHttp\Client;


use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Scraper\ProxyIP;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\MessagingProducerConfig;
use MultilineQM\Jobs\Helper;

class Login extends \MultilineQM\Job
{
    private $cache;
    private $producer;

    public function __construct($message)
    {
        /**
         * Expected mesage payload
         * {"request_uid":"f60095b4-973f-43e9-89fb-202050fd7ff3","request_ts":"1627011446.46516900","command":"session","sub_command":"category","data":{"provider":"hg","username":"uatmm2","category":"minmax"}}
         */
        $this->message = $message;

        $this->cache = BasicsConfig::driver();
        $this->producer = MessagingProducerConfig::driver();
    }

    public function handle()
    {
        $start_time = microtime(true);
        OutPut::normal('Start processing LOGIN: ' . json_encode($this->message) . PHP_EOL);

        $request_uid = $this->message["request_uid"];
        $request_ts = $this->message["request_ts"];
        $command = $this->message["command"];
        $provider = $this->message["data"]["provider"];
        $username = $this->message["data"]["username"];
        $password = $this->message["data"]["password"];

        $continue = true;
        if (empty($request_uid) || empty($request_ts) || empty($username) || empty($password) || ($command!='session'))
        {
            OutPut::normal("Login: Invalid payload." . PHP_EOL);
            $continue = false;
        }

        $connect = $this->cache->getConnect();
        if (!$connect) 
        {
            OutPut::normal("Unable to connect to redis." . PHP_EOL);
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

            $this->message["data"]["proxy"] = $proxy_url;
            $service->process($this->message);
        }

        \MultilineQM\Jobs\Helper::showExecutionTime('LOGIN:', $start_time, json_encode($this->message));
    }

    private function getService($request, $provider, $producer, $cache)
    {
        $service = null;
        try {
            switch ($provider) {
                case 'pin':
                    $service = new \MultilineQM\Scraper\Provider\Pin\Login($request, $producer, $cache);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
        return $service;
    }
}