<?php


namespace MultilineQM\Serialize;


interface SerializeInterface
{
    /**
     * Serialize the data and return the corresponding string
     * @param $data
     * @return string
     */
    public static function serialize($data): string;

    /**
     * Deserialize data
     * @param string $string
     * @return mixed
     */
    public static function unSerialize(string $string);
}