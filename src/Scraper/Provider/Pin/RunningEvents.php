<?php

namespace MultilineQM\Scraper\Provider\Pin;

use MultilineQM\OutPut\OutPut;
use MultilineQM\Scraper\ProviderServiceInterface;
use MultilineQM\Scraper\Client\Request;
use MultilineQM\Queue\Driver\{Redis, KafkaProducer};

class RunningEvents implements ProviderServiceInterface
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
                $sportId = 29;
                break;
            default:
                throw new \Exception('PIN-SPORTS: Invalid Sport parameter');
        }
        $contents = $this->getContents();
        if (!empty($contents)) {
            $data_dict = json_decode($contents, true);

            if (empty($data_dict['sports'])) {
                OutPut::normal("PIN RUNNING EVENTS - sports is empty" . PHP_EOL);
                return;
            }

            $cache = $this->cache->getConnect();
            array_map(function ($sport) use ($cache, $sportId) {
                if ($sport['id'] == $sportId) {
                    array_map(function ($league) use ($cache) {
                        $events = $league['events'] ?? [];
                        array_map(function ($event) use ($cache) {
                            $cache->set('running-event-' . $event['id'], json_encode($event), $this->ttl);
                            OutPut::normal("PIN RUNNING EVENTS SET TO REDIS -> running-event-{$event['id']}:" . json_encode($event) . PHP_EOL);
                        }, $events);
                    }, $sport['leagues']);
                }
            }, $data_dict['sports']);
        } else {
            OutPut::normal("PIN RUNNING EVENTS - No running event" . PHP_EOL);
        }
    }

    protected function getContents()
    {
        $url        = "/v2/inrunning";
        $response = $this->request->get($url);
        if (empty($response)) {
            return null;
        }
        return $response->getBody()->getContents();
    }
}