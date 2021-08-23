<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

/**
 * A provider service to download and extract the settlement information.
 * The response will be send via messaging bus
*/
class Settlement implements ProviderServiceInterface {
  
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

        $this->producer =$producer;
        
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
        $username = $message["data"]["username"];
        $provider = $message["data"]["provider"];

        $today = new \DateTime();
        $toDate = $today->format('Y-m-d\TH:i:s\Z');
        $fromDate = $today->modify("-1 days")->format('Y-m-d\T00:00:01\Z');

        $contents = $this->getContents($fromDate, $toDate);
        if (!empty($contents))
        {
            $dataDict = json_decode($contents, true);

            $data = [];
            if (array_key_exists('straightBets', $dataDict)) {
                $data = array();
                $ctr = 1;
                foreach ($dataDict["straightBets"] as $row)
                {
                    $score = "{$row["team1Score"]} - {$row["team2Score"]}";
                    $reason = $this->getReason($row);
                    $sport = $this->getSport($row);

                    $bet = array("provider" => "pin",
                            "sport" => $sport,
                            "id" => $ctr,
                            "username" => $username,
                            "status" => $row["betStatus"],
                            "odds" => (string) $row["handicap"],
                            "score" => $score,
                            "stake" => (string) $row["risk"],
                            "profit_loss" => (string) $row["winLoss"],
                            "bet_id" => (string) $row["betId"],
                            "reason" => $reason
                        );

                    array_push($data, $bet);
                    $ctr++;
                }
            }
            $message = json_encode(
                array("request_uid" => $requestUid,
                    "request_ts" => $requestTs,
                    "command" => 'settlement',
                    "sub_command" => 'transform',
                    "data" => $data,
                    )
                );
            $producer = $this->producer->getConnect();
            $producer->send('SCRAPING-SETTLEMENTS', $message);
            OutPut::normal("SETTLEMENT sent: " . $message . PHP_EOL);
        }
        else
        {
            throw new \Exception('PIN-SETTLEMENT: Response content is empty');
        }
    }

    /**
     * Get response.
     *
     * @return string 
     */
    protected function getContents($fromDate, $toDate) : string
    {
        $response = $this->request->get("/v3/bets?betlist=SETTLED&fromDate={$fromDate}&toDate={$toDate}");
        return $response->getBody()->getContents();
    }

    /**
     * Get concat cancellation message
     *
     */
    private function getReason($row) : string
    {
        $res = '';
        if (array_key_exists('cancellationReason', $row))
        {
            foreach($row["cancellationReason"] as $k => $v)
            {  
                $res .= "{$v} ";
            }
        }
        return $res;
    }

    /**
     * Get/tranform the provider sport id.
     *
     */
    private function getSport($row) : integer
    {
        $res = 1;
        if ($row["sportId"] == 29)
        {
            $res = 1;
        }
        return $res;
    }
}