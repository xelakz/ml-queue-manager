<?php
declare(strict_types=1);
namespace MultilineQM\Scraper\Provider\Pin;

use Ramsey\Uuid\Uuid;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

/**
 * A provider service to download and extract the bet information.
 * The response will be send via messaging bus
*/
class Bet implements ProviderServiceInterface {
  
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
        $command = 'bet';
        $provider = $message["data"]["provider"];
        $username = $message["data"]["username"];
        $marketId = $message["data"]["market_id"];
        $odds = $message["data"]["odds"];
        $sportId = $message["data"]["sport"];
        $stake = $message["data"]["stake"];
        $score = $message["data"]["score"];
        $eventId = $message["data"]["event_id"];

        $betPayload = array("request_uid" => $requestUid,
                "request_ts" => $requestTs,
                "command" => 'bet',
                "sub_command" => 'transform',
                "data" => array(
                        "provider" => $provider,
                        "sport" => $sportId,
                        "market_id" => $marketId,
                        "status" => "received",
                        "odds" => $odds,
                        "stake" => $stake,
                        "to_win" => "",
                        "score" => $score,
                        "bet_id" => "",
                        "reason" => "",
                    ),
                "response_message" => "success: bet request from ML",
            );
        $producer = $this->producer->getConnect();
        $producer->send('PLACED-BET', json_encode($betPayload));

        $betPayload["data"]["status"] = "failed";
        $betPayload["data"]["reason"] = "[Match/Market/Selection] is not open for betting";
        $betPayload["response_message"] = "";

        $isValid = false;
        $toWin = 0;
        $betId = "";
        $betStatus = "failed";
        $reason = "";
        $connect = $this->cache->getConnect();
        $betParamsCache = $connect->get("pin-bet-{$marketId}");
        if (!empty($betParamsCache))
        {
            $params = [];
            $betParamsDict = json_decode($betParamsCache, true);
            if (!empty($betParamsDict))
            {
                $params = array(
                    "lineId" => (integer) $betParamsDict["lineId"],
                    "team" => $betParamsDict["team"],
                    "fillType" => "NORMAL",
                    "uniqueRequestId" => (string) Uuid::uuid4(),
                    "oddsFormat" => "HONGKONG",
                    "winRiskStake" => "RISK",
                    "betType" => $betParamsDict["betType"],
                    "sportId" => 29,
                    "acceptBetterLine" => true,
                    "periodNumber" => (integer) $betParamsDict["periodNumber"],
                    "stake" => (float) $stake,
                    "eventId" => (integer) $betParamsDict["eventId"],
                );

                if (!empty($betParamsDict["altLineId"]))
                {
                    $params["altLineId"] = $betParamsDict["altLineId"];
                }
                else
                {
                    $params["altLineId"] = null;
                }
                if (!empty($betParamsDict["side"]))
                {
                    $params["side"] = $betParamsDict["side"];
                }
                else
                {
                    $params["side"] = null;
                }

                // $params = array("altLineId" => $betParamsDict["altLineId"],
                //     "lineId" => $betParamsDict["lineId"],
                //     "team" => $betParamsDict["team"],
                //     "fillType" => "NORMAL",
                //     "uniqueRequestId" => (string) Uuid::uuid4(),
                //     "oddsFormat" => "HONGKONG",
                //     "winRiskStake" => "RISK",
                //     "betType" => $betParamsDict["betType"],
                //     "sportId" => 29,
                //     "acceptBetterLine" => true,
                //     "periodNumber" => 0,
                //     "stake" => $stake,
                //     "eventId" => $eventId,
                //     "pitcher1MustStart" => true,
                //     "pitcher2MustStart" => true,
                // );
                if (!empty($betParamsDict["side"]))
                {
                    $params["side"] = $betParamsDict["side"];
                }
                else
                {
                    $params["side"] = "";
                }

                OutPut::normal("BET BODY: {$username} -> " . json_encode($params) . PHP_EOL);
                $contents = $this->getContents($params);
                OutPut::normal("BET CONTENT: {$contents}" . PHP_EOL);
                if (!empty($contents)) {
                    /*
                    BET CONTENT: "{\"status\":\"ACCEPTED\",\"errorCode\":null,\"uniqueRequestId\":\"e29976fe-07d7-4fd5-9ba9-7c690bffe8fe\",\"straightBet\":{\"betId\":1797183639,\"uniqueRequestId\":\"e29976fe-07d7-4fd5-9ba9-7c690bffe8fe\",\"wagerNumber\":1,\"placedAt\":\"2021-08-20T06:28:43Z\",\"betStatus\":\"ACCEPTED\",\"betType\":\"SPREAD\",\"win\":70.5,\"risk\":50.0,\"oddsFormat\":\"HONGKONG\",\"updateSequence\":1629440923000,\"sportId\":29,\"sportName\":\"Soccer\",\"leagueId\":2196,\"leagueName\":\"Spain - La Liga\",\"eventId\":1376300406,\"handicap\":0.5,\"price\":1.41,\"teamName\":\"Levante UD\",\"team1\":\"Levante UD\",\"team2\":\"Real Madrid\",\"periodNumber\":0,\"isLive\":\"FALSE\",\"eventStartTime\":\"2021-08-22T20:00:00Z\"}}"
                    */
                    $dataDict = json_decode($contents, true);
                    if (!empty($dataDict)) {
                        if (array_key_exists('code', $dataDict))
                        {
                            if ($dataDict["code"] == "INVALID_CREDENTIALS")
                            {
                                $reason = "INVALID_CREDENTIALS";
                                $cached_active_user = $connect->get("session:pin:active:{$username}");
                                $active_user = json_decode($cached_active_user, true);
                                $active_user["token"] = "";
                                $active_user["cookies"] = "";
                                $connect->set("session:pin:active:{$username}", json_encode($active_payload));
                            }
                            $reason = $dataDict["code"];
                        }
                        elseif (array_key_exists('errorCode', $dataDict))
                        {
                            if (!empty($dataDict['errorCode']))
                            {
                                OutPut::normal("BET CONTENT with errorCode -> reason: "  . $dataDict["errorCode"] . PHP_EOL);
                                $betPayload["data"]["status"] = "failed";
                                $reason = $dataDict["errorCode"];
                            }
                            else
                            {
                                if (array_key_exists('straightBet', $dataDict))
                                {
                                    if (array_key_exists('cancellationReason', $dataDict))
                                    {
                                        $reason = $dataDict["code"];
                                    }
                                    else 
                                    {
                                        if (!empty($dataDict['straightBet']))
                                        {
                                            OutPut::normal("BET CONTENT with straightBet" . PHP_EOL);
                                            if (array_key_exists('betStatus', $dataDict['straightBet']))
                                            {
                                                OutPut::normal("BET CONTENT with betStatus" . PHP_EOL);
                                                switch ($dataDict['straightBet']["betStatus"]){
                                                    case "ACCEPTED":
                                                        $betStatus = "success";
                                                        break;
                                                    case "PENDING_ACCEPTANCE":
                                                        $betStatus = "pending";
                                                        break;
                                                    case "CANCELLED":
                                                        $betStatus = "failed";
                                                        break;
                                                    case "NOT_ACCEPTED":
                                                        $betStatus = "failed";
                                                        break;
                                                }
                                            }
                                            $isValid = true;
                                            $stake = $dataDict['straightBet']["risk"];
                                            $toWin = (string) $dataDict['straightBet']["win"];
                                            $betId = (string) $dataDict['straightBet']["betId"];
                                        }
                                        else
                                        {
                                            OutPut::normal("BET CONTENT straightBet is null" . PHP_EOL);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        OutPut::normal("Market ID [{$marketId}]: cached: $betParamsCache} -> betPayload: " . json_encode($betPayload). " -> content: {$contents}" . PHP_EOL);

        $betPayload["data"]["status"] = $betStatus;
        $betPayload["data"]["stake"] = $stake;
        $betPayload["data"]["to_win"] = (string) $toWin;
        $betPayload["data"]["betId"] = (string) $betId;
        $betPayload["data"]["reason"] = $reason;

        $producer = $this->producer->getConnect();
        $producer->send('PLACED-BET', json_encode($betPayload));
        OutPut::normal("BET sent: " . json_encode($betPayload) . PHP_EOL);
    }

    /**
     * Get response.
     * @param array $params body payload
     * @return string 
     */
    protected function getContents($params) : string
    {
        $response = $this->request->post("/v2/bets/place", json_encode($params));
        // $response = $this->request->post("/v2/bets/straight", json_encode($params));
        try
        {
            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            OutPut::warning("BET Error: " . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage() . PHP_EOL);
        }
    }
}