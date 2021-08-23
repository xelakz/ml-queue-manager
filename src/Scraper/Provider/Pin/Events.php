<?php

namespace MultilineQM\Scraper\Provider\Pin;

use Carbon\Carbon;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\{Redis, KafkaProducer};

class Events implements ProviderServiceInterface
{

    private $request;
    private $producer;
    private $cache;
    private $ttl;

    private $filteredTeams = [
        "No. of Corners",
        "No. of Bookings",
        "To Qualify",
        "(Corners)",
        "Winner",
        "PK(Handicap)",
        "PK(Over/Under)",
        "games (e.g",
        "Days (",
        "Game",
        "Corners",
        "borders",
        "To Win Final",
        "To Finish 3rd",
        "To Advance",
        "(n)",
        "Home Team",
        "Away Team",
        "To Win",
        "TEST"
    ];

    public function __construct(Request $request, KafkaProducer $producer, Redis $cache)
    {
        $this->request  = $request;
        $this->producer = $producer;
        $this->cache    = $cache;
    }

    public function process($message)
    {
        switch ($message["data"]['schedule']) {
            case 'inplay':
                $ttl = 14400; // 4 hrs
                break;
            case 'today':
                $ttl = 43200; // 12 hrs
                break;
            case 'early':
            default:
                $ttl = 172800; // 48 hrs
                break;
        }
        $this->ttl = $ttl;

        $leaguesIds = $message['data']['leagueIds'];

        if (empty($leaguesIds)) {
            OutPut::normal("PIN EVENTS: Empty leagues - Nothing to scrape" . PHP_EOL);
            return;
        }

        switch ($message["data"]["sport"]) {
            case '1':
                $sportId = 29;
                break;
            default:
                throw new \Exception('PIN-SPORTS: Invalid Sport parameter');
        }

        $contents = $this->getContents($sportId, $leaguesIds, true);
        if (!empty($contents)) {
            $producer = $this->producer->getConnect();
            $data_dict = json_decode($contents, true);

            if (empty($data_dict['league'])) {
                OutPut::normal("PIN EVENTS - leagues is empty" . PHP_EOL);
                return;
            }
            $cache = $this->cache->getConnect();

            $eventIdsData = [
                'early'  => [],
                'today'  => [],
                'inplay' => []
            ];
            $leagueIdsData = $leaguesIds;
            array_map(function ($league) use ($leaguesIds, $message, $cache, $producer, &$eventIdsData) {
                $cache->set('league-' . $league['id'], json_encode($league), $this->ttl);
                OutPut::normal("PIN LEAGUES SET TO REDIS -> league-{$league['id']}:" . json_encode($league) . PHP_EOL);

                if (in_array($league['id'], $leaguesIds)) {
                    $leagueId = $league['id'];
                    $events = $league['events'] ?? [];
                    array_map(function ($event) use ($cache, $message, $leagueId, &$eventIdsData) {
                        foreach ($this->filteredTeams as $disregard) {
                            if (strpos($event['home'], $disregard) !== false) {
                                return;
                            }
                        }

                        foreach ($this->filteredTeams as $disregard) {
                            if (strpos($event['away'], $disregard) !== false) {
                                return;
                            }
                        }

                        $referenceSchedule = Carbon::createFromFormat("Y-m-d\TH:i:s\Z", $event['starts']);
                        $schedule = 'inplay';
                        if ($referenceSchedule->diffInDays(Carbon::now()) > 0) {
                            $schedule = 'early';
                        } else {
                            $cachedRunningEventJson = $cache->get('running-event-' . $event['id']);
                            if (empty($cachedRunningEventJson)) {
                                if (!empty($event['parentId'])) {
                                    $cachedRunningEventJson = $cache->get('running-event-' . $event['parentId']);
                                    if (empty($cachedRunningEventJson)) {
                                        if ($referenceSchedule->gte(Carbon::now())) {
                                            $schedule = 'today';
                                        }
                                    }
                                } else {
                                    if ($referenceSchedule->gte(Carbon::now())) {
                                        $schedule = 'today';
                                    }
                                }
                            }
                        }

                        if ($schedule != $message['data']['schedule']) {
                            return;
                        }

                        if (!empty($event['parentId'])) {
                            $eventIdsData[$schedule][] = $event['parentId'];
                            $cache->set('parent-event-' . $event['id'], $event['parentId'], $this->ttl);
                            OutPut::normal("PIN PARENT EVENTS SET TO REDIS -> parent-event-{$event['id']}:" . $event['parentId'] . PHP_EOL);
                        } else {
                            $eventIdsData[$schedule][] = $event['id'];
                            $cache->set('event-' . $event['id'], json_encode($event), $this->ttl);
                            OutPut::normal("PIN EVENTS SET TO REDIS -> event-{$event['id']}:" . json_encode($event) . PHP_EOL);
                        }
                        $event['leagueId'] = $leagueId;
                    }, $events);
                }
            }, $data_dict['league']);

            $payload = [
                'request_uid' => $message['request_uid'],
                'request_ts'  => $message['request_ts'],
                'sub_command' => 'scrape',
                'data'        => [
                    'provider'  => $message['data']['provider'],
                    'schedule'  => $message['data']['schedule'],
                    'sport'     => $message['data']['sport'],
                    'leagueIds' => (array) $leagueIdsData,
                    'eventIds'  => $eventIdsData[$message['data']['schedule']]
                ]
            ];
            $producer->send('pin_odds', json_encode($payload));
            $producer->send('pin_odds_since', json_encode($payload));

        } else {
            throw new \Exception('PIN-EVENTS: Response content is empty');
        }
    }

    protected function getContents($sportId, $leaguesIds, $since = false)
    {
        $leagueList = implode(',', $leaguesIds);
        $url        = "/v3/fixtures?sportId={$sportId}&isLive=0&leagueIds=" . $leagueList;
        if ($since) {
            $url .= "&since=" . time();
        }
        $response = $this->request->get($url);
        if (empty($response)) {
            return null;
        }
        return $response->getBody()->getContents();
    }
}