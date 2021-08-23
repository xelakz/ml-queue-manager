<?php


namespace MultilineQM\Library\Traits;


use MultilineQM\Exception\ConfigException;

trait Config
{
    private static $is_set = false;

    public function __call($name, $arg)
    {
        return $this->{$name};
    }

    public static function __callStatic($name, $arg)
    {
        return self::${$name};
    }

    protected static function checkSet($name ='')
    {
        if ($name) {
            if (!is_null(self::$$name)) {
                throw new ConfigException('The configuration can only be initialized once');
            }
        } else {
            if (self::$is_set) {
                throw new ConfigException('The configuration can only be initialized once');
            }
            self::$is_set = true;
        }

    }

}