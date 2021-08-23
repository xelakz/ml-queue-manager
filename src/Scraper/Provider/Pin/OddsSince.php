<?php

namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\Redis;
use MultilineQM\Queue\Driver\KafkaProducer;
use MultilineQM\OutPut\OutPut;

class OddsSince implements ProviderServiceInterface
{

    private $request;
    private $producer;
    private $cache;
    private $message;
    private $ttl;

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

        $this->message = $message;
        $eventIds      = $message['data']['eventIds'];
        $leaguesIds    = $message['data']['leagueIds'];
        $cache         = $this->cache->getConnect();

        if (empty($eventIds)) {
            OutPut::normal("PIN ODDS: Empty events - Nothing to scrape" . PHP_EOL);
            return;
        }

        switch ($message["data"]["sport"]) {
            case '1':
                $sportId = 29;
                break;
        }

        $lastCalledOdds = false;
        if ($cache->get('last-call-odds')) {
            $lastCalledOdds = $cache->get('last-call-odds');
        }
        $contents = $this->getContents($sportId, $eventIds, $lastCalledOdds);
        if (!empty($contents)) {
            $data_dict = json_decode($contents, true);

            if (empty($data_dict['leagues'])) {
                OutPut::normal("PIN EVENT ODDS SINCE - leagues is empty" . PHP_EOL);
                return;
            }

            $cache->set('last-call-odds', $data_dict['last']);
            array_map(function ($league) use ($leaguesIds, $cache, &$eventData) {
                if (in_array($league['id'], $leaguesIds)) {
                    array_map(function ($event) use (
                        $leaguesIds,
                        $league,
                        $cache
                    ) {
                        $event['league_id'] = $league['id'];
                        $cache->set('event-odds-since-' . $this->getEventId($event['id']), json_encode($event), $this->ttl);
                        OutPut::normal("PIN EVENT ODDS SINCE SET TO REDIS -> event-odds-since-" . $this->getEventId($event['id']) . ":" . json_encode($event) . PHP_EOL);
                    }, $league['events']);
                }
            }, $data_dict['leagues']);
        } else {
            throw new \Exception('PIN-ODDS: Response content is empty');
        }
    }

    protected function getContents($sportId, $eventIds, $since = false)
    {
        $eventList = implode(',', $eventIds);
        $url       = "/v3/odds?sportId={$sportId}&oddsFormat=HongKong&isLive=0&eventIds=" . $eventList;
        $url .= "&since=" . $since;

        OutPut::normal($url . PHP_EOL);
        $response = $this->request->get($url);
        if (empty($response)) {
            return null;
        }
        return $response->getBody()->getContents();
    }

    private function getEventId($eventId)
    {
        $cache               = $this->cache->getConnect();
        $cachedParentEventId = $cache->get('parent-event-' . $eventId);

        if (!empty($cachedParentEvent)) {
            $eventId = $cachedParentEventId;
        }

        return $eventId;
    }
}