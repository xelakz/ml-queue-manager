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
class Logout implements ProviderServiceInterface {
  
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
        $provider = $message["data"]["provider"];
        $username = $message["data"]["username"];
        $proxy = $message["data"]["proxy"];

        $continue = true;

        $connect = $this->cache->getConnect();

        // $connect->del("session:pin:assigned:{$username}");

        $cache_active_user = $connect->get("session:{$provider}:active:{$username}");
        $user_dict = json_decode($cache_active_user, true);
        if (!empty($user_dict))
        {
            $cached = array(
                "username"=>$username,
                "password"=>$user_dict["password"],
                "token"=>"",
                "cookies"=>"",
                "proxy"=>$user_dict["proxy"],
                "category"=>$user_dict["category"],
                "ver"=>$user_dict["ver"],
                "usage"=>$user_dict["usage"],
            );
        }
        $connect->set("session:pin:active:{$username}", json_encode($cached));

        // $connect->del("session:pin:details:{$username}");

        $connect->srem("session:pin:assignedproxyurl", $proxy);

        // $connect->srem("session:pin:users", $username);
    }
}