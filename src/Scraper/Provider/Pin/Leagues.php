<?php

namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\{Redis, KafkaProducer};

class Leagues implements ProviderServiceInterface
{
    private $request;
    private $producer;
    private $cache;
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

        switch ($message["data"]["sport"]) {
            case '1':
                $sportid = 29;
                break;
            default:
                throw new \Exception('PIN-SPORTS: Invalid Sport parameter');
        }
        $contents = $this->getContents($sportid);

        if (!empty($contents)) {

            $data_dict = json_decode($contents, true);

            if (empty($data_dict['leagues'])) {
                OutPut::normal("PIN LEAGUES - leagues is empty" . PHP_EOL);
                return;
            }
            $leagueIds   = array_column($data_dict['leagues'], 'id') ?? [];

            $cache = $this->cache->getConnect();
            $producer = $this->producer->getConnect();
            array_map(function ($league) use ($cache) {
                $cache->set('league-' . $league['id'], json_encode($league), $this->ttl);
            }, $data_dict['leagues']);

            $leagueChunks = array_chunk($leagueIds, 500) ?? [];
            $count = 0;
            array_map(function ($leagueIds) use ($message, $producer, &$count) {
                $count++;
                $payload = [
                    'request_uid' => $message['request_uid'],
                    'request_ts'  => $message['request_ts'],
                    'sub_command' => 'scrape',
                    'data'        => [
                        'provider'  => $message['data']['provider'],
                        'schedule'  => $message['data']['schedule'],
                        'sport'     => $message['data']['sport'],
                        'leagueIds' => $leagueIds
                    ]
                ];
                $producer->send('pin_events', json_encode($payload));
            }, $leagueChunks);
            $cache->set('league-req-id-total-' . $message['request_uid'], $count, 60);
            $cache->set('league-req-id-count-' . $message['request_uid'], 0, 60);
            $cache->set('leagues-req-id-' . $message['request_uid'], 0, 60);
            $cache->set('events-req-id-' . $message['request_uid'], 0, 60);

            $payload = [
                'request_uid' => $message['request_uid'],
                'request_ts'  => $message['request_ts'],
                'sub_command' => 'scrape',
                'data'        => [
                    'provider'  => $message['data']['provider'],
                    'schedule'  => $message['data']['schedule'],
                    'sport'     => $message['data']['sport'],
                    'leagueIds' => []
                ]
            ];
            $producer->send('pin_running_events', json_encode($payload));
        } else {
            throw new \Exception('PIN-LEAGUES: Response content is empty');
        }

    }

    protected function getContents($sportid = 29)
    {
        $response = $this->request->get("/v3/leagues?sportId={$sportid}");
        if (empty($response)) {
            return null;
        }
        return $response->getBody()->getContents();
    }
}