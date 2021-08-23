<?php

namespace MultilineQM\Jobs;

use GuzzleHttp\Client;
use MultilineQM\Job;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Config\{BasicsConfig, MessagingProducerConfig};
use MultilineQM\Scraper\Provider\Pin\OddsSince as PinOddsSince;
use MultilineQM\Scraper\Provider\Pin\Params as PinParams;
use MultilineQM\Scraper\QueueSession;

class OddsSince extends Job
{
    private $cache;
    private $producer;

    public function __construct($message)
    {
        /**
         * Expected message payload
         * {"request_uid":"6c2234a0-9c64-4d61-af5e-d06e23f4fcc4","request_ts":"1627068447.06725000","command":"odd","sub_command":"scrape","data":{"provider":"pin","schedule":"inplay","sport":"1"}}
         */
        $this->message = $message;

        $this->cache    = BasicsConfig::driver();
        $this->producer = MessagingProducerConfig::driver();
    }

    public function handle()
    {
        $start_time = microtime(true);
        try {
            OutPut::normal('Start processing ODDS SINCE: ' . json_encode($this->message) . PHP_EOL);

            $request_uid = $this->message["request_uid"];
            $request_ts  = $this->message["request_ts"];
            $provider    = $this->message["data"]["provider"];
            $schedule    = $this->message["data"]["schedule"] ?? '';
            $sport       = $this->message["data"]["sport"] ?? '';
            $leagueIds   = !empty($this->message["data"]["leagueIds"]) ?? null;
            $eventIds    = !empty($this->message["data"]["eventIds"]) ?? null;

            $continue = true;
            if (empty($request_uid) || empty($request_ts) || empty($provider) || empty($schedule) || empty($sport) || is_null($eventIds) || is_null($leagueIds)) {
                OutPut::normal("ODDS SINCE: Invalid payload." . PHP_EOL);
                $continue = false;
            }

            $connect = $this->cache->getConnect();
            if (!$connect) {
                OutPut::normal("Unable to connect to redis." . PHP_EOL);
                $continue = false;
            }

            $queue_session    = new QueueSession($this->cache, 'pin', '');
            $selected_session = $queue_session->getAvailableSession('odds', []);
            if (empty($selected_session)) {
                OutPut::normal("Unable to continue. Session is empty" . PHP_EOL);
                $continue = false;
            }

            if ($continue) {
                $username  = $selected_session["username"];
                $password  = $selected_session["password"];
                $proxy_url = $selected_session["proxy"];

                $base_params   = [];
                $header_params = [];
                switch ($provider) {
                    case 'pin':
                        $params        = new PinParams($username, $password, $proxy_url);
                        $base_params   = $params->getBaseParams();
                        $header_params = $params->getRequestParams();
                        break;
                }

                $client = new Client($base_params);

                $request = new Request($client, $header_params);

                $service = $this->getService($request, $provider, $this->producer, $this->cache);

                $service->process($this->message);
            }
        } catch (\Exception $e) {
            OutPut::normal("Error: " . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL);
            return false;
        }
        Helper::showExecutionTime('ODDS:', $start_time, json_encode($this->message));
    }

    private function getService($request, $provider, $producer, $cache)
    {
        $service = null;
        switch ($provider) {
            case 'pin':
                $service = new PinOddsSince($request, $producer, $cache);
                break;
        }
        return $service;
    }
}