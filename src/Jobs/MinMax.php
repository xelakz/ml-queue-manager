<?php
namespace MultilineQM\Jobs;

use GuzzleHttp\Client;
use Exception;

use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Scraper\ProxyIP;
use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\MessagingProducerConfig;
use MultilineQM\Scraper\QueueSession;

class MinMax extends \MultilineQM\Job
{
    private $cache;
    private $producer;

    public function __construct($message)
    {
        /**
         * Expected message payload
         * {"request_uid":"762cda42-3193-47e5-874f-9716fb13b6fa","request_ts":"1628061453.98684800","sub_command":"scrape","command":"minmax","data":{"provider":"pin","market_id":"REC4960445","sport":1,"schedule":"inplay","event_id":"4960443","odds":"1.53","memUID":"b881fb6b730aeea8016319dab7ad1e59","counter":1}}
         */
        $this->message = $message;

        $this->cache = BasicsConfig::driver();
        $this->producer = MessagingProducerConfig::driver();
    }

    public function handle()
    {
        $start_time = microtime(true);
        OutPut::normal('Start processing MINMAX: ' . json_encode($this->message) . PHP_EOL);

        $request_uid = $this->message["request_uid"];
        $request_ts  = $this->message["request_ts"];
        $command     = $this->message["command"];
        $provider    = $this->message["data"]["provider"];
        $sport_id    = $this->message["data"]["sport"];
        $market_id   = $this->message["data"]["market_id"];
        $schedule    = $this->message["data"]["schedule"];
        $event_id    = $this->message["data"]["event_id"];
        $odds        = $this->message["data"]["odds"];

        $continue = true;
        if (empty($request_uid) || empty($request_ts) || empty($provider) || ($command!='minmax')) {
            OutPut::normal("MINMAX: Invalid payload." . PHP_EOL);
            $continue = false;
        }

        $connect = $this->cache->getConnect();
        if (!$connect) {
            OutPut::normal("Unable to connect to redis." . PHP_EOL);
            $continue = false;
        }

        $cached_payload = [
            "request_uid" => $request_uid,
            "request_ts"  => $request_ts,
            "command"     => 'minmax',
            "sub_command" => 'transform',
            "data"        => [
                "provider"  => $provider,
                "sport"     => $sport_id,
                "market_id" => $market_id,
                "odds"      => "",
                "points"    => "",
                "minimum"   => "",
                "maximum"   => "",
                "timestamp" => (string) time(),
            ],
            "message"          => "onqueue",
            "response_message" => "preparing to process from provider",
        ];

        $proceed_publish = false;
        $process         = false;

        // pull from cache
        $minmax_cache = $connect->get("minmax-{$market_id}");
        $minmax_dict  = json_decode($minmax_cache, true);

        if (!empty($minmax_dict)) {
            try {
                $refresh_interval = ($schedule == 'inplay') ? 5 : (($schedule == 'today') ? 10 : 30);
                $data = [
                    "provider"  => $provider,
                    "sport"     => (integer) $minmax_dict['data']['sport'],
                    "market_id" => (string) $minmax_dict['data']['market_id'],
                    "odds"      => (string) $minmax_dict['data']['odds'],
                    "points"    => (string) $minmax_dict['data']['points'],
                    "minimum"   => (string) $minmax_dict['data']['minimum'],
                    "maximum"   => (string) $minmax_dict['data']['maximum'],
                    "timestamp" => (string) $minmax_dict['data']['timestamp'],
                ];

                $cached_payload['data'] = $data;
                $cached_payload['message'] = $minmax_dict['message'];
                if (($minmax_dict['data']['minimum'] && $minmax_dict['data']['maximum']) || ($minmax_dict['message'])) {
                    $proceed_publish = true;
                    if ($minmax_dict['message'] == "onqueue") {
                        $minmax_dict['message'] = '';
                    }

                    $cached_payload['response_message'] = 'sending from cache';
                    if ((time() - ((float) $minmax_dict['data']['timestamp'])) >= $refresh_interval) {
                        $process = true;
                    }

                    if (empty($minmax_dict['data']['minimum']) && empty($minmax_dict['data']['maximum'])) {
                        $minmax_dict['message'] = "";
                        $process = true;
                    }

                    if ($minmax_dict['data']['minimum']=="0.0" || $minmax_dict['data']['maximum']=="0.0") {
                        $minmax_dict['message'] = "";
                        $process = true;
                    }

                    if (($minmax_dict['message'] == 'onqueue') && ((time() - ((float) $minmax_dict['data']['timestamp'])) <= 1)) {
                        $proceed_publish = false;
                    }
                }
            } catch (\Exception $exception) {
                $proceed_publish = true;
                $process = true;

                Log::warning($e);
            }
        } else{
            $proceed_publish = true;
            $process = true;
            $connect->del("minmax-onqueue-{$market_id}");
        }

        if ($proceed_publish) {
            $producer = $this->producer->getConnect();
            $producer->send('MINMAX-ODDS', json_encode($cached_payload));
        }

        if ($process) {
            $message = $cached_payload;

            $queue_session    = new QueueSession($this->cache, 'pin', '');
            $selected_session = $queue_session->getAvailableSession('minmax', []);
            if (empty($selected_session))
            {
                OutPut::normal("MINMAX: Unable to continue. Session is empty" . PHP_EOL);
                $continue = false;
            }
            if ($connect->get("minmax-onqueue-{$market_id}"))
            {
                OutPut::normal("MINMAX: marketid {$market_id} still onqueue" . PHP_EOL);
                $continue = false;
            }
            if ($continue)
            {
                OutPut::normal("MINMAX: Preparing to extract minmax of Market ID [{$market_id}]" . PHP_EOL);
                $connect->set("minmax-onqueue-{$market_id}", json_encode($cached_payload));

                $username = $selected_session["username"];
                $queue_session->removeAssigned($username);

                $password  = $selected_session["password"];
                $proxy_url = $selected_session["proxy"];

                $base_params   = [];
                $header_params = [];

                switch ($provider) {
                    case 'pin':
                        $params        = new \MultilineQM\Scraper\Provider\Pin\Params($username, $password, $proxy_url);
                        $base_params   = $params->getBaseParams();
                        $header_params = $params->getRequestParams();

                        break;
                }

                $client  = new Client($base_params);
                $request = new Request($client, $header_params);
                $service = $this->getService($request, $provider, $this->producer, $this->cache);

                $service->process($this->message);

                $connect->del("minmax-onqueue-{$market_id}");
            }
        }

        \MultilineQM\Jobs\Helper::showExecutionTime('MINMAX', $start_time, json_encode($this->message));
    }

    private function getService($request, $provider, $producer, $cache)
    {
        $service = null;
        try {
            switch ($provider) {
                case 'pin':
                    $service = new \MultilineQM\Scraper\Provider\Pin\MinMax($request, $producer, $cache);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error($e);
        }
        return $service;
    }
}