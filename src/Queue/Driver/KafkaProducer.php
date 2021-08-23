<?php
namespace MultilineQM\Queue\Driver;

use longlang\phpkafka\Producer\Producer;
use longlang\phpkafka\Producer\ProducerConfig;

/**
 * Class KafkaProducer
 * @package MultilineQM\Messaging\Driver
 */
class KafkaProducer implements MessagingDriverInterface
{
    protected $connect = null;

    protected $config = [];

    /**
     * Kafka Producer constructor.
     * @param $host kafka server
     * @param int $port port
     */
    public function __construct($host, $port='9092')
    {

        $this->config = [
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     * Connect to kafka producer
     * @return \Producer
     */
    public function getConnect()
    {
        if (!$this->connect) {
            $this->connect = $this->connection();
        }
        return $this->connect;
    }

    /**
     * Connect to Kafka
     * @return \Producer
     */
    private function connection()
    {
        $config = new ProducerConfig();
        $config->setBootstrapServer($this->config['host'].":".$this->config['port']);
        $config->setUpdateBrokers(true);
        $config->setAcks(-1);
        return new Producer($config);
    }

    /**
     * Close the current connection instance
     * @return mixed|void
     */
    public function close()
    {
        // $this->getConnect()->close();
        $this->connect = null;
    }
}
