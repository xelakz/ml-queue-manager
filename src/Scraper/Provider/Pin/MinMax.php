<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

/**
 * A provider service to download and extract the updated minimum and maximum market information.
 * The response will be send via messaging bus
*/
class MinMax implements ProviderServiceInterface {
  
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
        $requestUid    = $message["request_uid"];
        $requestTs     = $message["request_ts"];
        $provider      = $message["data"]["provider"];
        $marketId      = $message["data"]["market_id"];
        $eventId       = $message["data"]["event_id"];
        $sportId       = $message["data"]["sport"];
        $minmaxPayload = [
            "request_uid" => $requestUid,
            "request_ts"  => $requestTs,
            "command"     => 'minmax',
            "sub_command" => 'transform',
            "data"        => [
                "provider"  => $provider,
                "sport"     => $sportId,
                "market_id" => $marketId,
                "odds"      => "",
                "points"    => "",
                "minimum"   => "",
                "maximum"   => "",
                "timestamp" => (string) time(),
            ],
            "message"          => "onqueue",
            "response_message" => "preparing to process from provider",
        ];

        $producer = $this->producer->getConnect();
        $producer->send('MINMAX-ODDS', json_encode($minmaxPayload));

        $minmaxPayload["message"]          = "[Match/Market/Selection] is not open for betting";
        $minmaxPayload["response_message"] = "";

        $connect           = $this->cache->getConnect();
        $minmaxParamsCache = $connect->get(strtolower($provider) . "-minmax-{$marketId}");

        if (!empty($minmaxParamsCache)) {
            $minmaxParamsDict = json_decode($minmaxParamsCache, true);

            if (!empty($minmaxParamsDict)) {
                $leagueId   = $minmaxParamsDict["leagueId"];
                $eventId    = $minmaxParamsDict["eventId"];
                $odds       = $minmaxParamsDict["odds"];
                $sportId    = $minmaxParamsDict["sportId"];
                $points     = $minmaxParamsDict["points"];
                $period_num = $minmaxParamsDict["periodNum"];
                $bet_type   = $minmaxParamsDict["betType"];
                $team       = $minmaxParamsDict["team"];
                $side       = $minmaxParamsDict["side"];

                $isValid  = false;
                $contents = $this->getContents($leagueId, $points, $sportId, $eventId, $period_num, $bet_type, $team, $side);

                if (!empty($contents)) {
                    $dataDict = json_decode($contents, true);

                    if (array_key_exists('code', $dataDict)) {
                        if ($dataDict["code"] == "INVALID_REQUEST_DATA") {
                            $minmaxPayload["message"] = $dataDict["message"];

                            OutPut::normal("MINMAX PIN: " . $dataDict . PHP_EOL);
                        }
                    } else {
                        // Check if market type is SPREADS
                        if ($this->getPartFromMarketIdPrefix($marketId, 2) == "S") {                            
                            // Check if request is for HOME or AWAY
                            if ($this->getPartFromMarketIdPrefix($marketId, 4) == "A") {
                                $points *= -1;
                            }

                            $points = number_format($points, 2, '.', '');

                            if ($points[strlen($points) - 1] == "0") {
                                $points = substr($points, 0, strlen($points) - 1);
                            }

                            if ($points > 0) {
                                $points = "+" . $points;
                            }
                        }

                        $minmaxPayload["data"]["odds"]     = (string) $dataDict["price"];
                        $minmaxPayload["data"]["points"]   = (string) $points;
                        $minmaxPayload["data"]["minimum"]  = (string) $dataDict["minRiskStake"];
                        $minmaxPayload["data"]["maximum"]  = (string) $dataDict["maxRiskStake"];
                        $minmaxPayload["message"]          = "";
                        $minmaxPayload["response_message"] = "";

                        $isValid = true;
                    }
                } else {
                    OutPut::normal('PIN-MINMAX: Response content is empty' . PHP_EOL);
                }
        
                if ($isValid) {
                    $connect->set("minmax-{$marketId}", json_encode($minmaxPayload));
                }
            }
        } else {
            $minmaxPayload["response_message"] = "";

            OutPut::normal("Market ID [{$marketId}]: [Match/Market/Selection] is not open for betting" . PHP_EOL);
        }

        $producer = $this->producer->getConnect();
        $producer->send('MINMAX-ODDS', json_encode($minmaxPayload));

        OutPut::normal("MINMAX sent: " . json_encode($minmaxPayload) . PHP_EOL);
    }

    /**
     * Get response.
     *
     * @return string 
     */
    protected function getContents($leagueId, $points, $sportId, $eventId, $period_num, $bet_type, $team, $side) : string
    {
        $sportId  = ($sportId == 1) ? 29 : 1;
        $response = $this->request->get("/v2/line?leagueId={$leagueId}&handicap={$points}&oddsFormat=HongKong&sportId={$sportId}&eventId={$eventId}&periodNumber={$period_num}&betType={$bet_type}&team={$team}&side={$side}");

        return $response->getBody()->getContents();
    }

    /**
     * Get Market Flag from Market ID.
     * 
     * @param  string  $marketId
     * @return string
     */
    protected function getPartFromMarketIdPrefix($marketId, $count) : string
    {
        $chars  = preg_replace('/[0-9]+/', '', $marketId);
        $len    = strlen($chars);

        if ($len >= $count) {
            return strtoupper($chars[$count - 1]);
        } else {
            return strtoupper($chars[$len - 1]);
        }
    }
}