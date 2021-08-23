<?php


namespace MultilineQM\Config;

use MultilineQM\Queue\Driver\MessagingDriverInterface;
use MultilineQM\Library\Traits\Config;

/**
 * @method static \MultilineQM\Messaging\Driver\MessagingDriverInterface driver() Get the messaging driver
 * Class MessagingProducerConfig
 * @package MultilineQM\Config
 */
class MessagingProducerConfig implements ConfigInterface
{
    use Config;

    private static $driver;

    public static function set(MessagingDriverInterface $driver)
    {
        self::checkSet();

        if(is_string($driver) && class_exists($driver)){
            $driver = new $driver;
        }
        if(!$driver instanceof MessagingDriverInterface){
            throw new \Exception('kafka producer driver must be MultilineQM\Messaging\Driver\MessagingDriverInterface realization of');
        }
        self::$driver = $driver;
    }
}