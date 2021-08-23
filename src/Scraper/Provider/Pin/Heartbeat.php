<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Log\Log;

class Heartbeat implements ProviderServiceInterface {
  
    private $request=null;
    private $producer=null;
    private $cache=null;

    public function __construct(Request $request, KafkaProducer $producer, Redis $cache)
    {
        $this->request = $request;
        $this->producer = $producer;
        $this->cache = $cache;
    }

    public function process($message)
    {
        $username = $message["username"];

        $connect = $this->cache->getConnect();

        $contents = $this->getContents();
        if (!empty($contents)) {
            $data_dict = json_decode($contents, true);
            if (array_key_exists('code', $data_dict))
            {
                if ($data_dict["code"] == "INVALID_CREDENTIALS")
                {
                    $msg = $data_dict["message"];
                    OutPut::normal("HEARTBEAT PIN user: {$username} -> msg: {$msg}" . PHP_EOL);

                    /**
                     * Disable the account
                     * "code": "INVALID_CREDENTIALS", "message": "Invalid username and password combination"
                     */
                    if ($msg == "Invalid username and password combination")
                    {
                        $cached_active_user = $connect->get("session:pin:active:{$username}");
                        $active_user = json_decode($cached_active_user, true);
                        $active_user["token"] = "";
                        $active_user["cookies"] = "";
                        $connect->set("session:pin:active:{$username}", json_encode($active_user));
                        OutPut::normal("HEARTBEAT PIN user: {$username} is inactive -> error msg: {$msg}" . PHP_EOL);
                    }
                }
            }
            elseif (array_key_exists('status', $data_dict))
            {
                if ($data_dict["status"] != "ALL_BETTING_ENABLED")
                {
                    $status = $data_dict["status"];
                    OutPut::normal("HEARTBEAT PIN user: {$username} status: {$status}" . PHP_EOL);
                }
                else {
                    OutPut::normal("HEARTBEAT PIN user: {$username} is active" . PHP_EOL);
                }
            }
        }
    }

    protected function getContents()
    {
        $response = $this->request->get("/v1/bets/betting-status");
        if (empty($response)) {
            return null;
        }
        return $response->getBody()->getContents();
    }
}