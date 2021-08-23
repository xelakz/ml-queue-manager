<?php
namespace MultilineQM\Serialize;
use Opis\Closure\SerializableClosure;

/**
 * Task serialization class
 * Class JobSerialize
 * @package MultilineQM\Serialize
 */
class JobSerialize implements SerializeInterface
{

    /**
     * Serialize the data and return the corresponding string
     * @param $data
     * @return string
     */
    public static function serialize($data): string{
        SerializableClosure::enterContext();
        SerializableClosure::wrapClosures($data);
        $data = \serialize($data);
        SerializableClosure::exitContext();
        return $data;
    }

    /**
     * Deserialize data
     * @param string $string
     * @return mixed
     */
    public static function unSerialize(string $string, array $options = null){
        SerializableClosure::enterContext();
        $data = ($options === null || \PHP_MAJOR_VERSION <7)
            ? \unserialize($string)
            : \unserialize($string, $options);
        SerializableClosure::unwrapClosures($data);
        SerializableClosure::exitContext();
        return $data;
    }
}