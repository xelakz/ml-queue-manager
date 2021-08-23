<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

/**
 * A provider service to update category of session account.
 * Account provider information will be save and cache and will be use for session management
*/
class Category implements ProviderServiceInterface {
  
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
        $category = $message["data"]["category"];

        $connect = $this->cache->getConnect();

        $cacheActiveUser = $connect->get("session:{$provider}:active:{$username}");
        if (!empty($cacheActiveUser))
        {
            $userDict = json_decode($cacheActiveUser, true);
            if (!empty($userDict))
            {
                if (!empty($userDict["token"]))
                {
                    if ($userDict["category"] != $category)
                    {
                        $newPayload = array(
                            "username"=>$this->username,
                            "password"=>$userDict["password"],
                            "token"=>$userDict["token"],
                            "cookies"=>$userDict["cookies"],
                            "proxy"=>$userDict["proxy"],
                            "category"=>$category,
                            "ver"=>$userDict["ver"],
                            "usage"=>$userDict["usage"],
                        );
                        $connect->set("session:{$provider}:active:{$username}", json_encode($newPayload));

                        $cacheActiveUserDetail = $connect->get("session:{$provider}:details:{$username}");
                        $userDict = json_decode($cacheActiveUserDetail, true);
                        if (!empty($userDict))
                        {
                            $newPayload = array(
                                "username"=>$username,
                                "password"=>$userDict["password"],
                                "category"=>$category,
                                "last_login"=>$userDict["last_login"],
                            );
                            $connect->set("session:{$provider}:details:{$username}", json_encode($newPayload));
                        }
                    }
                }
            }
        }
    }
}