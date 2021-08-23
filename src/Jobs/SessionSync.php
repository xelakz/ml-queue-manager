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

class SessionSync extends \MultilineQM\Job
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

        $provider = $this->message["provider"];
        $command = $this->message["command"];
        OutPut::normal("Start processing SESSIONSYNC: {$command}" . json_encode($this->message) . PHP_EOL);

        $continue = true;
        $connect = $this->cache->getConnect();
        if (!$connect) 
        {
            OutPut::normal("SESSIONSYNC: {$command}: Unable to connect to redis." . PHP_EOL);
            $continue = false;
        }

        if ($continue)
        {
            $payload = array("request_uid" => (string) Uuid::uuid4(),
                "request_ts" => Helper::getMilliseconds(),
                "command" => 'session',
                "sub_command" => 'sync',
                "data" => array(
                        "provider" => $provider,
                    ),
                );

            if ($command == "status")
            {
                // {"request_uid": "6726ab26-96a8-4f12-b8bf-281865e41ec7", "request_ts": "1629382465.15591800", "command": "session", "sub_command": "transform", "data": {"provider": "hg", "active_sessions": [{"command": "", "username": "devmbet0", "password": "pass8888", "token": "2h96g634m24936321l155451b0", "cookies": "2h96g634m24936321l155451b0", "proxy": "http://lum-customer-c_a3c9770d-zone-devtaiwan-ip-178.171.122.176:gv=vrx=ul4~2@zproxy.lum-superproxy.io:22225", "category": "bet", "ver": "2021-08-19-01en10f6b-5ec9-ad58-0817-769ae3216be7", "usage": "OPEN"}], "inactive_sessions": []}}
                $payload["sub_command"] = "transform";
                $payload["data"]["active_sessions"] = [];
                $payload["data"]["inactive_sessions"] = [];

                $cacheUserMembers = $connect->smembers("session:{$provider}:users");
                foreach ($cacheUserMembers as $username)
                {
                    $cacheActiveUser = $connect->get("session:{$provider}:active:{$username}");
                    if (empty($cacheActiveUser))
                    {
                        continue;
                    }
                    $userDict = json_decode($cacheActiveUser, true);
                    if (!empty($userDict))
                    {
                        if (!empty($userDict["token"]))
                        {
                            array_push($payload["data"]["active_sessions"], $userDict);
                        }
                        else
                        {
                            array_push($payload["data"]["inactive_sessions"], $userDict);
                        }
                    }
                }
            }
            else {
                // {"request_uid": "a2af6478-97bb-43cb-a30f-bee495e97f09", "request_ts": "1627639835.9697127", "command": "session", "sub_command": "sync", "data": {"provider": "hg"}}
            }
            $producer = $this->producer->getConnect();
            $producer->send('SESSIONS', json_encode($payload));
            OutPut::normal("SESSIONSYNC: {$command}: sent." . json_encode($payload) . PHP_EOL);
        }

        \MultilineQM\Jobs\Helper::showExecutionTime("SESSIONSYNC {$command}", $start_time, json_encode($this->message));
    }
}