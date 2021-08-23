<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use Symfony\Component\DomCrawler\Crawler;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

/**
 * A provider service to download and extract the balance information.
 * The response will be send via messaging bus
*/
class Maintenance implements ProviderServiceInterface {
  
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

        $connect = $this->cache->getConnect();

        $payload = array("command" => "maintenance",
                "sub_command" => "transform",
                "data" => [
                    "provider" => "pin", 
                    "is_undermaintenance" => false
                ]
            );
        $contents = $this->getContents();
        if (!empty($contents))
        {
            $crawler = new Crawler($contents);
            $items = $crawler->filterXPath('//li[contains(@class, "list-group-item")]');
            if ($items) {
                foreach($items as $index => $node)
                {
                    if ($index == 1)
                    {
                        if (strpos($node->nodeValue, 'Operational') === false)
                        {
                            $payload["data"]["is_undermaintenance"] = true;
                        }
                        break;
                    }
                }
            }
            $producer = $this->producer->getConnect();
            $producer->send('PROVIDER-MAINTENANCE', json_encode($payload));
            OutPut::normal("MAINTENANCE sent: " . json_encode($payload) . PHP_EOL);
        }
        else
        {
            throw new \Exception('PIN-MAINTENANCE: Response content is empty');
        }

    }

    /**
     * Get response.
     *
     * @return string 
     */
    protected function getContents() : string
    {
        $response = $this->request->get("/");
        return $response->getBody()->getContents();
    }
}