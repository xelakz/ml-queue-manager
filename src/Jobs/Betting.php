<?php
namespace MultilineQM\Jobs;

use GuzzleHttp\Client;

use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Scraper\ProxyIP;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\MessagingProducerConfig;
use MultilineQM\Scraper\QueueSession;

class Betting extends \MultilineQM\Job
{
    private $cache;
    private $producer;

    public function __construct($message)
    {
        $this->message = $message;

        $this->cache = BasicsConfig::driver();
        $this->producer = MessagingProducerConfig::driver();

        $this->command = $this->message["command"];
    }

    public function handle()
    {
        $start_time = microtime(true);
        OutPut::normal("Start processing " . strtoupper($this->command) . ": " . json_encode($this->message) . PHP_EOL);

        $request_uid = $this->message["request_uid"];
        $request_ts = $this->message["request_ts"];
        $command = $this->message["command"];
        $provider = $this->message["data"]["provider"];
        $username = $this->message["data"]["username"];

        $continue = true;
        if (empty($request_uid) || empty($request_ts) || empty($provider) || (!in_array($command, ["bet", "balance", "orders", "settlement"])))
        {
            OutPut::normal(strtoupper($this->command) . ": Invalid payload." . PHP_EOL);
            $continue = false;
        }

        $connect = $this->cache->getConnect();
        if (!$connect) 
        {
            OutPut::normal(strtoupper($this->command) . ": Unable to connect to redis." . PHP_EOL);
            $continue = false;
        }

        $queue_session = new QueueSession($this->cache, 'pin', $username);
        $selected_session = $queue_session->getAvailableSession('bet', []);
        if (empty($selected_session))
        {
            OutPut::normal(strtoupper($this->command) . ": Unable to continue. Session is empty" . PHP_EOL);
            $continue = false;
        }

        if ($continue)
        {
            $username = $selected_session["username"];
            $queue_session->removeAssigned($username);

            $password = $selected_session["password"];
            $proxy_url = $selected_session["proxy"];

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
            
            $service->process($this->message);
        }

        \MultilineQM\Jobs\Helper::showExecutionTime(strtoupper($this->command), $start_time, json_encode($this->message));
    }

    private function getService($request, $provider, $producer, $cache)
    {
        $service = null;
        try {
            switch ($provider) {
                case 'pin':
                    if ($this->command == 'bet') {
                        $service = new \MultilineQM\Scraper\Provider\Pin\Bet($request, $producer, $cache);
                    }
                    elseif ($this->command == 'balance') {
                        $service = new \MultilineQM\Scraper\Provider\Pin\Balance($request, $producer, $cache);
                    }
                    elseif ($this->command == 'orders') {
                        $service = new \MultilineQM\Scraper\Provider\Pin\OpenOrders($request, $producer, $cache);
                    }
                    elseif ($this->command == 'settlement') {
                        $service = new \MultilineQM\Scraper\Provider\Pin\Settlement($request, $producer, $cache);
                    }
                    break;
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
        return $service;
    }
}