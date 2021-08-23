<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

/**
 * A provider service to download and extract the balance information.
 * The response will be send via messaging bus
*/
class Balance implements ProviderServiceInterface {
  
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
        $requestUid = $message["request_uid"];
        $requestTs = $message["request_ts"];
        $command = 'balance';
        $subCommand = 'transform';
        $provider = $message["data"]["provider"];
        $username = $message["data"]["username"];

        $connect = $this->cache->getConnect();

        $contents = $this->getContents();
        if (!empty($contents))
        {
            $dataDict = json_decode($contents, true);

            $message = json_encode(
                array("request_uid" => $requestUid,
                    "request_ts" => $requestTs,
                    "command" => 'balance',
                    "sub_command" => 'transform',
                    "data" => array(
                            "provider" => $provider,
                            "username" => $username,
                            "available_balance" => (string) $dataDict["availableBalance"],
                            "currency" => $dataDict["currency"],
                        ),
                    )
                );

            $producer = $this->producer->getConnect();
            $producer->send('BALANCE', $message);
            OutPut::normal("BALANCE sent: " . $message . PHP_EOL);
        }
        else
        {
            throw new \Exception('PIN-BALANCE: Response content is empty');
        }
    }

    /**
     * Get response.
     *
     * @return string 
     */
    protected function getContents() : string
    {
        $response = $this->request->get("/v1/client/balance");
        return $response->getBody()->getContents();
    }
}