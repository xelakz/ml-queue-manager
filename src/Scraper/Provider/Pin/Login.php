<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

/**
 * A provider service to login to provider site.
 * Account provider information will be save and cache and will be use for session management
*/
class Login implements ProviderServiceInterface {
  
    /**
     * @var MultilineQM\Scraper\Client\Request
     */
    private $request = null;

    /**
     * @var MultilineQM\Queue\Driver\KafkaProducer
     */
    private $producer = null;

    /**
     * @var MultilineQM\Queue\Driver\Redis
     */
    private $cache = null;

    public function __construct(Request $request, KafkaProducer $producer, Redis $cache)
    {
        $this->request = $request;

        $this->producer = $producer;
        
        $this->cache = $cache;
    }

    /**
     * This is to process and extract.
     *
     * @param string $message payload
     * @throws \Exception
     */
    public function process($message) : void
    {
        $username = $message["data"]["username"];
        $password = $message["data"]["password"];
        $category = $message["data"]["category"];
        $proxy = $message["data"]["proxy"];
        $usage = $message["data"]["usage"];

        $continue = true;

        $credentials = base64_encode("{$username}:{$password}");

        $connect = $this->cache->getConnect();

        // check if the account is already active
        $activeuser_cache = $connect->get("session:pin:active:{$username}");
        if ($activeuser_cache)
        {
            OutPut::normal("Username: {$username} already active." . PHP_EOL);
            $continue = false;
        }

        if ($continue)
        {
            $connect->del("session:pin:assigned:{$username}");

            $active_payload = array("username" => $username,
                    "password" => $password,
                    "token" => $credentials,
                    "cookies" => $credentials,
                    "proxy" => $proxy,
                    "category" => $category,
                    "provider" => "pin",
                    "usage" => $usage,
                    "ver" => "");
            $connect->set("session:pin:active:{$username}", json_encode($active_payload));

            $details_payload = array("username" => $username,
                    "password" => $password,
                    "category" => $category,
                    "provider" => "pin",
                    "last_login" => strtotime("now"),
                );
            $connect->set("session:pin:details:{$username}", json_encode($details_payload));

            $connect->sadd("session:pin:assignedproxyurl", $proxy);

            $connect->sadd("session:pin:users", $username);

            $connect->lpush("session:pin:queue:{$category}", $username);
        }
    }
}