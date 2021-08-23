<?php
namespace MultilineQM\Jobs;

use ReflectionMethod;
use MultilineQM\OutPut\OutPut;

/**
 * Tool function class
 * Class Helper
 * @package MultilineQM\Jobs
 */
class Helper
{

    /**
     * Output execution time
     */
    static function showExecutionTime($prefix, $start_time=0, string $message)
    {
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time);
        OutPut::normal("End processing {$prefix}: execution time[{$execution_time}(s)] -> {$message}". PHP_EOL);
    }

    static function numberSign($number)
    {
        if ($number > 0) {
            return '+';
        } elseif ($number == 0) {
            return '';
        } else {
            return '-';
        }
    }

    /**
     * Get milliseconds.
     * @return mixed|void
     */
    static function getMilliseconds()
    {
        $mt = explode(' ', microtime());
        return bcadd($mt[1], $mt[0], 8);
    }

    /**
     * format to correct point
     * @param $number
     * @param int $decimals
     * @return mixed|string
     */
    function oddsPointPrecision($number)
    {
        if (is_int($number)) {
            return $number . ".0";
        } else {
            return $number;
        }
    }
}