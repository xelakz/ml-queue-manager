<?php

namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Jobs\Helper;
use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;
use Carbon\Carbon;

class Odds implements ProviderServiceInterface
{

    private $request;
    private $producer;
    private $cache;
    private $redis;
    private $message;
    private $ttl;
    private $marketPrefix = [
        'moneyline' => [
            'FT' => [
                'home' => 'MFH',
                'away' => 'MFA',
                'draw' => 'MFD'
            ],
            'HT' => [
                'home' => 'MHH',
                'away' => 'MHA',
                'draw' => 'MHD'
            ]
        ],
        'hdp'       => [
            'main'  => [
                'FT' => [
                    'home' => 'MSFH',
                    'away' => 'MSFA',
                ],
                'HT' => [
                    'home' => 'MSHH',
                    'away' => 'MSHA'
                ]
            ],
            'other' => [
                'FT' => [
                    'home' => 'OSFH',
                    'away' => 'OSFA',
                ],
                'HT' => [
                    'home' => 'OSHH',
                    'away' => 'OSHA'
                ]
            ],
        ],
        'ou'        => [
            'main'  => [
                'FT' => [
                    'over'  => 'MTFH',
                    'under' => 'MTFA',
                ],
                'HT' => [
                    'over'  => 'MTHH',
                    'under' => 'MTHA'
                ]
            ],
            'other' => [
                'FT' => [
                    'over'  => 'OTFH',
                    'under' => 'OTFA',
                ],
                'HT' => [
                    'over'  => 'OTHH',
                    'under' => 'OTHA'
                ]
            ],
        ]
    ];

    public function __construct(Request $request, KafkaProducer $producer, Redis $cache)
    {
        $this->request  = $request;
        $this->producer = $producer;
        $this->cache    = $cache;
        $this->redis    = $this->cache->getConnect();
    }

    public function process($message)
    {
        $cache    = $this->redis;
        $producer = $this->producer->getConnect();

        $cache->incr('league-req-id-count-' . $message['request_uid']);
        $cacheLeagueReqIdCount = $cache->get('league-req-id-count-' . $message['request_uid']);

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

        $this->message = $message;
        $eventIds      = $message['data']['eventIds'];
        $leaguesIds    = $message['data']['leagueIds'];

        if (empty($eventIds)) {
            OutPut::normal("PIN ODDS: Empty events - Nothing to scrape" . PHP_EOL);
            return;
        }

        switch ($message["data"]["sport"]) {
            case '1':
                $sportId = 29;
                break;
        }

        $contents = $this->getContents($sportId, $eventIds);
        if (!empty($contents)) {
            $data_dict = json_decode($contents, true);
            $cache->set('last-call-odds', $data_dict['last']);

            if (empty($data_dict['leagues'])) {
                OutPut::normal("PIN EVENT ODDS SINCE - leagues is empty" . PHP_EOL);
                return;
            }

            $eventData  = [];
            $leagueData = [];
            array_map(function ($league) use ($leaguesIds, $message, $cache, $producer, &$eventData, &$leagueData) {
                if (in_array($league['id'], $leaguesIds)) {
                    $leagueData[] = $league['id'];
                    array_map(function ($event) use (
                        $leaguesIds,
                        $message,
                        $league,
                        $cache,
                        $producer,
                        &$eventData
                    ) {
                        $event['league_id'] = $league['id'];
                        $cache->set('event-odds-' . $this->getEventId($event['id']), json_encode($event), $this->ttl);
                        $eventData[] = $this->getEventId($event['id']);
                        OutPut::normal("PIN EVENT ODDS SET TO REDIS -> event-odds-" . $this->getEventId($event['id']) . ":" . json_encode($event) . PHP_EOL);
                    }, $league['events']);
                }
            }, $data_dict['leagues']);

            $activeEventIds = [
                'early'  => [],
                'today'  => [],
                'inplay' => []
            ];
            foreach ($eventData as $eventId) {
                $eventOddsCacheJson = $cache->get('event-odds-' . $this->getEventId($eventId));
                if (!$eventOddsCacheJson) {
                    continue;
                }
                $event            = json_decode($eventOddsCacheJson, true);
                $cachedLeagueJson = $cache->get('league-' . $event['league_id']);
                $cachedLeague     = json_decode($cachedLeagueJson, true);
                if (!$cachedLeague) {
                    continue;
                }
                $cachedEventJson = $cache->get('event-' . $this->getEventId($event['id']));
                $cachedEvent     = json_decode($cachedEventJson, true);
                if (!$cachedEvent) {
                    continue;
                }

                $referenceSchedule = Carbon::createFromFormat("Y-m-d\TH:i:s\Z", $cachedEvent['starts']);

                $runningtime = '';
                if ($referenceSchedule->diffInDays(Carbon::now()) > 0) {
                    $schedule = 'early';
                } else {
                    $cachedRunningEventJson = $cache->get('running-event-' . $this->getEventId($event['id']));
                    if (empty($cachedRunningEventJson)) {
                        $schedule    = $message['data']['schedule'];
                        $runningtime = '';
                    } else {
                        $schedule           = 'inplay';
                        $cachedRunningEvent = json_decode($cachedRunningEventJson, true);
                        switch ((string) $cachedRunningEvent['state']) {
                            case '1':
                                $period = '1H';
                                break;
                            case '2':
                                $period = 'HT';
                                break;
                            case '3':
                                $period = '2H';
                                break;
                            default:
                                $period = '';
                                break;
                        }
                        $runningtime = $period . ' ' . $cachedRunningEvent['elapsed'];
                    }
                }

                $eventMarkets = [];

                $this->overwritePeriodsWithSince($event);
                $this->spreadMarkets($event, $eventMarkets, $event['league_id']);
                $this->moneylineMarkets($event, $eventMarkets, $event['league_id']);
                $this->totalsMarkets($event, $eventMarkets, $event['league_id']);

                $this->fillMissingMarket4Payload($eventMarkets);


                $payload = [
                    'request_uid' => $message['request_uid'],
                    'request_ts'  => $message['request_ts'],
                    'command'     => 'odd',
                    'sub_command' => 'transform',
                    'data'        => [
                        'provider'          => $message['data']['provider'],
                        'schedule'          => $schedule,
                        'sport'             => $message['data']['sport'],
                        'leagueName'        => $cachedLeague['name'],
                        'homeTeam'          => $cachedEvent['home'],
                        'awayTeam'          => $cachedEvent['away'],
                        'referenceSchedule' => $referenceSchedule->format("Y-m-d\TH:i:s.v"),
                        'runningtime'       => $runningtime,
                        'home_score'        => empty($event["homeScore"]) ? "0" : (string) $event["homeScore"],
                        'away_score'        => empty($event["awayScore"]) ? "0" : (string) $event["awayScore"],
                        'home_redcard'      => empty($event["homeRedCards"]) ? "0" : (string) $event["homeRedCards"],
                        'away_redcard'      => empty($event["awayRedCards"]) ? "0" : (string) $event["awayRedCards"],
                        'events'            => $eventMarkets
                    ]
                ];
                OutPut::normal(json_encode($payload) . PHP_EOL);

                $activeEventIds[$schedule][] = $eventId;

                if ($schedule == $message['data']['schedule']) {
                    $producer->send('SCRAPING-ODDS', json_encode($payload));
                }
            }

            $leaguesIds = array_merge((array) json_decode($cache->get('leagues-req-id-' . $message['request_uid']),
                true), $leagueData);
            $cache->set('leagues-req-id-' . $message['request_uid'], json_encode($leaguesIds), 600);

            $cachedEventIds = $cache->get('events-req-id-' . $message['request_uid']) ?? [];
            $eventIds       = array_merge((array) json_decode($cachedEventIds, true),
                $activeEventIds[$message['data']['schedule']]);
            $cache->set('events-req-id-' . $message['request_uid'], json_encode($eventIds), 600);
            $this->returnProcess($cacheLeagueReqIdCount, $cache, $message, $producer, $eventIds, $leaguesIds);
        } else {
            $this->returnProcess($cacheLeagueReqIdCount, $cache, $message, $producer, $eventIds, $leaguesIds);
            throw new \Exception('PIN-ODDS: Response content is empty');
        }
    }

    private function returnProcess($cacheLeagueReqIdCount, $cache, $message, $producer, $eventIds, $leaguesIds)
    {
        if ($cacheLeagueReqIdCount == $cache->get('league-req-id-total-' . $message['request_uid'])) {
            $cache->set('league-req-id-count-' . $message['request_uid'], 0, 60);
            $payload = [
                'request_uid' => $message['request_uid'],
                'request_ts'  => $message['request_ts'],
                'command'     => 'event',
                'sub_command' => 'transform',
                'data'        => [
                    'provider'  => $message['data']['provider'],
                    'schedule'  => $message['data']['schedule'],
                    'sport'     => $message['data']['sport'],
                    'event_ids' => $eventIds
                ]
            ];

            $producer->send('SCRAPING-PROVIDER-EVENTS', json_encode($payload));

            $payload = [
                'request_uid' => $message['request_uid'],
                'request_ts'  => $message['request_ts'],
                'command'     => 'league',
                'sub_command' => 'transform',
                'data'        => [
                    'provider' => $message['data']['provider'],
                    'schedule' => $message['data']['schedule'],
                    'sport'    => $message['data']['sport'],
                    'leagues'  => $leaguesIds
                ]
            ];
            $producer->send('SCRAPING-PROVIDER-LEAGUES', json_encode($payload));
        }
    }

    protected function getContents($sportId, $eventIds)
    {
        $eventList = implode(',', $eventIds);
        $url       = "/v3/odds?sportId={$sportId}&oddsFormat=HongKong&isLive=0&eventIds=" . $eventList;
        OutPut::normal($url . PHP_EOL);
        $response = $this->request->get($url);
        return $response->getBody()->getContents();
    }

    private function overwritePeriodsWithSince(&$event)
    {
        $eventWithSinceJson = $this->redis->get('event-odds-since-' . $this->getEventId($event['id']));
        if (!empty($eventWithSinceJson)) {
            if (!empty($eventWithSince['periods'][0])) {
                $event['periods'][0] = $eventWithSince['periods'][0];
            }

            if (!empty($eventWithSince['periods'][1])) {
                $event['periods'][1] = $eventWithSince['periods'][1];
            }

            OutPut::normal("Event With Since - " . json_encode($event) . PHP_EOL);
        }
    }

    private function spreadMarkets($event, &$eventMarkets, $leagueId)
    {
        foreach ($event['periods'] as $periodKey => $period) {
            if (empty($period['spreads'])) {
                continue;
            }

            $timePeriod = $periodKey == 0 ? 'FTH' : 'HTH';
            $oddType    = $periodKey == 0 ? 'HDP' : 'HT HDP';

            $eventId = $this->getEventId($event['id']);

            foreach ($period['spreads'] as $key => $spread) {
                if (empty($eventMarkets[$key]['eventId'])) {
                    $eventMarkets[$key]['eventId']     = (string) (empty($key) ? $eventId : $eventId . $key);
                    $eventMarkets[$key]['market_type'] = empty($key) ? 1 : 2;
                }

                $marketType = empty($spread['altLineId']) ? 'main' : 'other';
                if (strpos($timePeriod, 'FT') === false) {
                    $prefix = $this->marketPrefix['hdp'][$marketType]['HT'];
                } else {
                    $prefix = $this->marketPrefix['hdp'][$marketType]['FT'];
                }

                $eventMarkets[$key]['market_odds'][] = [
                    'oddsType'        => $oddType,
                    'marketSelection' => [
                        [
                            'market_id' => $prefix['home'] . ($key == 0 ? $period['lineId'] : $spread['altLineId']),
                            'indicator' => 'Home',
                            'odds'      => $spread['home'] ? number_format($spread['home'], 2) : '',
                            'points'    => Helper::numberSign($spread['hdp']) . (Helper::oddsPointPrecision(abs($spread['hdp'])))
                        ],
                        [
                            'market_id' => $prefix['away'] . ($key == 0 ? $period['lineId'] : $spread['altLineId']),
                            'indicator' => 'Away',
                            'odds'      => $spread['away'] ? number_format($spread['away'], 2) : '',
                            'points'    => Helper::numberSign($spread['hdp'] * -1) . (Helper::oddsPointPrecision(abs($spread['hdp'])))
                        ]
                    ]
                ];

                $homeMinMax = [
                    'leagueId'  => $leagueId,
                    'eventId'   => $eventId,
                    'odds'      => $spread['home'],
                    'sportId'   => $this->message['data']['sport'],
                    'points'    => $spread['hdp'],
                    'periodNum' => $periodKey,
                    'betType'   => 'SPREAD',
                    'team'      => 'TEAM1',
                    'side'      => ''
                ];
                $this->redis->set('pin-minmax-' . ($prefix['home'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])),
                    json_encode($homeMinMax), $this->ttl);
                OutPut::normal("PIN MINMAX SET TO REDIS -> pin-minmax-" . ($prefix['home'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])) . ":" . json_encode($homeMinMax) . PHP_EOL);

                $awayMinMax = [
                    'leagueId'  => $leagueId,
                    'eventId'   => $eventId,
                    'odds'      => $spread['away'],
                    'sportId'   => $this->message['data']['sport'],
                    'points'    => $spread['hdp'],
                    'periodNum' => $periodKey,
                    'betType'   => 'SPREAD',
                    'team'      => 'TEAM2',
                    'side'      => ''
                ];
                $this->redis->set('pin-minmax-' . ($prefix['away'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])),
                    json_encode($awayMinMax), $this->ttl);
                OutPut::normal("PIN MINMAX SET TO REDIS -> pin-minmax-" . ($prefix['away'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])) . ":" . json_encode($awayMinMax) . PHP_EOL);

                $homeBet = [
                    'altLineId'    => $spread['altLineId'] ?? '',
                    'lineId'       => $period['lineId'],
                    'team'         => 'TEAM1',
                    'side'         => '',
                    'betType'      => 'SPREAD',
                    'sportId'      => $this->message['data']['sport'],
                    'periodNumber' => $periodKey,
                    'eventId'   => $eventId,
                ];
                $this->redis->set('pin-bet-' . ($prefix['home'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])),
                    json_encode($homeBet), $this->ttl);
                OutPut::normal("PIN BET SET TO REDIS -> pin-bet-" . ($prefix['home'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])) . ":" . json_encode($homeBet) . PHP_EOL);

                $awayBet = [
                    'altLineId'    => $spread['altLineId'] ?? '',
                    'lineId'       => $period['lineId'],
                    'team'         => 'TEAM2',
                    'side'         => '',
                    'betType'      => 'SPREAD',
                    'sportId'      => $this->message['data']['sport'],
                    'periodNumber' => $periodKey,
                    'eventId'   => $eventId,
                ];
                $this->redis->set('pin-bet-' . ($prefix['away'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])),
                    json_encode($awayBet), $this->ttl);
                OutPut::normal("PIN BET SET TO REDIS -> pin-bet-" . ($prefix['away'] . ($key == 0 ? $period['lineId'] : $spread['altLineId'])) . ":" . json_encode($awayBet) . PHP_EOL);
            };
        }
    }

    private function moneylineMarkets($event, &$eventMarkets, $leagueId)
    {
        foreach ($event['periods'] as $periodKey => $period) {
            if (empty($period['moneyline'])) {
                continue;
            }

            $timePeriod = $periodKey == 0 ? 'FTM' : 'HTM';
            $oddType    = $periodKey == 0 ? '1X2' : 'HT 1X2';
            $moneyline  = $period['moneyline'];
            $key        = 0;
            $eventId    = $this->getEventId($event['id']);

            if (empty($eventMarkets[$key]['eventId'])) {
                $eventMarkets[$key]['eventId']     = (string) $eventId;
                $eventMarkets[$key]['market_type'] = empty($key) ? 1 : 2;
            }

            if (strpos($timePeriod, 'FT') === false) {
                $prefix = $this->marketPrefix['moneyline']['HT'];
            } else {
                $prefix = $this->marketPrefix['moneyline']['FT'];
            }

            $eventMarkets[$key]['market_odds'][] = [
                'oddsType'        => $oddType,
                'marketSelection' => [
                    [
                        'market_id' => $prefix['home'] . ($key == 0 ? $period['lineId'] : $moneyline['altLineId']),
                        'indicator' => 'Home',
                        'odds'      => $moneyline['home'] ? number_format($moneyline['home'], 2) : ''
                    ],
                    [
                        'market_id' => $prefix['away'] . ($key == 0 ? $period['lineId'] : $moneyline['altLineId']),
                        'indicator' => 'Away',
                        'odds'      => $moneyline['away'] ? number_format($moneyline['away'], 2) : ''
                    ],
                    [
                        'market_id' => $prefix['draw'] . ($key == 0 ? $period['lineId'] : $moneyline['altLineId']),
                        'indicator' => 'Draw',
                        'odds'      => $moneyline['draw'] ? number_format($moneyline['draw'], 2) : ''
                    ]
                ]
            ];

            $homeMinMax = [
                'leagueId'  => $leagueId,
                'eventId'   => $eventId,
                'odds'      => $moneyline['home'],
                'sportId'   => $this->message['data']['sport'],
                'points'    => '',
                'periodNum' => $periodKey,
                'betType'   => 'MONEYLINE',
                'team'      => 'TEAM1',
                'side'      => ''
            ];
            $this->redis->set('pin-minmax-' . ($prefix['home'] . $period['lineId']),
                json_encode($homeMinMax), $this->ttl);
            OutPut::normal("PIN MINMAX SET TO REDIS -> pin-minmax-" . ($prefix['home'] . $period['lineId']) . ":" . json_encode($homeMinMax) . PHP_EOL);

            $awayMinMax = [
                'leagueId'  => $leagueId,
                'eventId'   => $eventId,
                'odds'      => $moneyline['away'],
                'sportId'   => $this->message['data']['sport'],
                'points'    => '',
                'periodNum' => $periodKey,
                'betType'   => 'MONEYLINE',
                'team'      => 'TEAM1',
                'side'      => ''
            ];
            $this->redis->set('pin-minmax-' . ($prefix['away'] . $period['lineId']),
                json_encode($awayMinMax), $this->ttl);
            OutPut::normal("PIN MINMAX SET TO REDIS -> pin-minmax-" . ($prefix['away'] . $period['lineId']) . ":" . json_encode($awayMinMax) . PHP_EOL);

            $drawMinMax = [
                'leagueId'  => $leagueId,
                'eventId'   => $eventId,
                'odds'      => $moneyline['draw'],
                'sportId'   => $this->message['data']['sport'],
                'points'    => '',
                'periodNum' => $periodKey,
                'betType'   => 'MONEYLINE',
                'team'      => 'DRAW',
                'side'      => ''
            ];
            $this->redis->set('pin-minmax-' . ($prefix['draw'] . $period['lineId']),
                json_encode($drawMinMax), $this->ttl);
            OutPut::normal("PIN MINMAX SET TO REDIS -> pin-minmax-" . ($prefix['draw'] . $period['lineId']) . ":" . json_encode($drawMinMax) . PHP_EOL);

            $homeBet = [
                'altLineId'    => '',
                'lineId'       => $period['lineId'],
                'team'         => 'TEAM1',
                'side'         => '',
                'betType'      => 'MONEYLINE',
                'sportId'      => $this->message['data']['sport'],
                'periodNumber' => $periodKey,
                'eventId'   => $eventId,
            ];
            $this->redis->set('pin-bet-' . ($prefix['home'] . $period['lineId']), json_encode($homeBet),
                $this->ttl);
            OutPut::normal("PIN BET SET TO REDIS -> pin-bet-" . ($prefix['home'] . $period['lineId']) . ":" . json_encode($homeBet) . PHP_EOL);

            $awayBet = [
                'altLineId'    => '',
                'lineId'       => $period['lineId'],
                'team'         => 'TEAM2',
                'side'         => '',
                'betType'      => 'MONEYLINE',
                'sportId'      => $this->message['data']['sport'],
                'periodNumber' => $periodKey,
                'eventId'   => $eventId,
            ];
            $this->redis->set('pin-bet-' . ($prefix['away'] . $period['lineId']), json_encode($awayBet),
                $this->ttl);
            OutPut::normal("PIN BET SET TO REDIS -> pin-bet-" . ($prefix['away'] . $period['lineId']) . ":" . json_encode($awayBet) . PHP_EOL);

            $drawBet = [
                'altLineId'    => '',
                'lineId'       => $period['lineId'],
                'team'         => 'DRAW',
                'side'         => '',
                'betType'      => 'MONEYLINE',
                'sportId'      => $this->message['data']['sport'],
                'periodNumber' => $periodKey,
                'eventId'   => $eventId,
            ];
            $this->redis->set('pin-bet-' . ($prefix['draw'] . $period['lineId']), json_encode($drawBet),
                $this->ttl);
            OutPut::normal("PIN BET SET TO REDIS -> pin-bet-" . ($prefix['draw'] . $period['lineId']) . ":" . json_encode($drawBet) . PHP_EOL);
        }
    }

    private function totalsMarkets($event, &$eventMarkets, $leagueId)
    {
        foreach ($event['periods'] as $periodKey => $period) {
            if (empty($period['totals'])) {
                continue;
            }
            $timePeriod = $periodKey == 0 ? 'FTO' : 'HTO';
            $oddType    = $periodKey == 0 ? 'OU' : 'HT OU';

            $eventId = $this->getEventId($event['id']);

            foreach ($period['totals'] as $key => $total) {
                if (empty($eventMarkets[$key]['eventId'])) {
                    $eventMarkets[$key]['eventId']     = (string) (empty($key) ? $eventId : $eventId . $key);
                    $eventMarkets[$key]['market_type'] = empty($key) ? 1 : 2;
                }

                $marketType = empty($spread['altLineId']) ? 'main' : 'other';
                if (strpos($timePeriod, 'FT') === false) {
                    $prefix = $this->marketPrefix['ou'][$marketType]['HT'];
                } else {
                    $prefix = $this->marketPrefix['ou'][$marketType]['FT'];
                }

                $eventMarkets[$key]['market_odds'][] = [
                    'oddsType'        => $oddType,
                    'marketSelection' => [
                        [
                            'market_id' => $prefix['over'] . $period['lineId'],
                            'indicator' => 'Home',
                            'odds'      => $total['over'] ? number_format($total['over'], 2) : '',
                            'points'    => 'O ' . $total['points']
                        ],
                        [
                            'market_id' => $prefix['under'] . $period['lineId'],
                            'indicator' => 'Away',
                            'odds'      => $total['under'] ? number_format($total['under'], 2) : '',
                            'points'    => 'U ' . $total['points']
                        ]
                    ]
                ];

                $homeMinMax = [
                    'leagueId'  => $leagueId,
                    'eventId'   => $eventId,
                    'odds'      => $total['over'],
                    'sportId'   => $this->message['data']['sport'],
                    'points'    => $total['points'],
                    'periodNum' => $periodKey,
                    'betType'   => 'TOTAL_POINTS',
                    'team'      => 'TEAM1',
                    'side'      => 'OVER'
                ];
                $this->redis->set('pin-minmax-' . ($prefix['over'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])),
                    json_encode($homeMinMax), $this->ttl);
                OutPut::normal("PIN MINMAX SET TO REDIS -> pin-minmax-" . ($prefix['over'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])) . ":" . json_encode($homeMinMax) . PHP_EOL);

                $awayMinMax = [
                    'leagueId'  => $leagueId,
                    'eventId'   => $eventId,
                    'odds'      => $total['under'],
                    'sportId'   => $this->message['data']['sport'],
                    'points'    => $total['points'],
                    'periodNum' => $periodKey,
                    'betType'   => 'TOTAL_POINTS',
                    'team'      => 'TEAM1',
                    'side'      => 'UNDER'
                ];
                $this->redis->set('pin-minmax-' . ($prefix['under'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])),
                    json_encode($awayMinMax), $this->ttl);
                OutPut::normal("PIN MINMAX SET TO REDIS -> pin-minmax-" . ($prefix['under'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])) . ":" . json_encode($awayMinMax) . PHP_EOL);

                $overBet = [
                    'altLineId'    => $total['altLineId'] ?? '',
                    'lineId'       => $period['lineId'],
                    'team'         => 'TEAM1',
                    'side'         => 'OVER',
                    'betType'      => 'TOTAL_POINTS',
                    'sportId'      => $this->message['data']['sport'],
                    'periodNumber' => $periodKey,
                    'eventId'   => $eventId,
                ];
                $this->redis->set('pin-bet-' . ($prefix['over'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])),
                    json_encode($overBet), $this->ttl);
                OutPut::normal("PIN BET SET TO REDIS -> pin-bet-" . ($prefix['over'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])) . ":" . json_encode($overBet) . PHP_EOL);

                $underBet = [
                    'altLineId'    => $total['altLineId'] ?? '',
                    'lineId'       => $period['lineId'],
                    'team'         => 'TEAM2',
                    'side'         => 'UNDER',
                    'betType'      => 'TOTAL_POINTS',
                    'sportId'      => $this->message['data']['sport'],
                    'periodNumber' => $periodKey,
                    'eventId'   => $eventId,
                ];
                $this->redis->set('pin-bet-' . ($prefix['under'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])),
                    json_encode($underBet), $this->ttl);
                OutPut::normal("PIN BET SET TO REDIS -> pin-bet-" . ($prefix['under'] . ($key == 0 ? $period['lineId'] : $total['altLineId'])) . ":" . json_encode($underBet) . PHP_EOL);
            };
        }
    }

    private function fillMissingMarket4Payload(&$eventMarkets)
    {
        foreach ($eventMarkets as $key => $eventMarket) {
            $allMarketTypes = ['1X2', 'HDP', 'OU', 'OE', 'HT 1X2', 'HT HDP', 'HT OU'];
            $haveMarkets    = [];
            foreach ($eventMarket['market_odds'] as $marketOdds) {
                if (in_array($marketOdds['oddsType'], $allMarketTypes)) {
                    $haveMarkets[] = $marketOdds['oddsType'];
                }
            }

            foreach ($allMarketTypes as $allMarketType) {
                if (!in_array($allMarketType, $haveMarkets)) {

                    $marketSelection = [
                        [
                            'market_id' => '',
                            'indicator' => 'Home',
                            'odds'      => ''
                        ],
                        [
                            'market_id' => '',
                            'indicator' => 'Away',
                            'odds'      => ''
                        ]
                    ];
                    if (in_array($allMarketType, ['HDP', 'HT HDP', 'OU', 'HT OU'])) {
                        $marketSelection[0]['points'] = '';
                        $marketSelection[1]['points'] = '';
                    }

                    if (in_array($allMarketType, ['1X2', 'HT 1X2'])) {
                        $marketSelection[2] = [
                            'market_id' => '',
                            'indicator' => 'Draw',
                            'odds'      => ''
                        ];
                    }

                    $eventMarkets[$key]['market_odds'][] = [
                        'oddsType'        => $allMarketType,
                        'marketSelection' => $marketSelection
                    ];
                }
            }
        }
    }

    private function getEventId($eventId)
    {
        $cache               = $this->redis;
        $cachedParentEventId = $cache->get('parent-event-' . $eventId);

        if (!empty($cachedParentEvent)) {
            $eventId = $cachedParentEventId;
        }

        return $eventId;
    }
}