<?php


namespace MultilineQM\Config;


interface ConfigInterface
{
    /**
     * Obtain configuration variables statically
     * @param $name
     * @param $arg
     * @return mixed
     */
    public static function __callStatic($name,$arg);
}