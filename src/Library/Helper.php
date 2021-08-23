<?php
namespace MultilineQM\Library;

use ReflectionMethod;

/**
 * Tool function class
 * Class Helper
 * @package MultilineQM\Library
 */
class Helper
{

    /**
     * Return the available parameters of the corresponding method
     * @param array $params The associative array of the incoming parameters
     * @param string|object $objectOrMethod Classname, object
     * (instance of the class) that contains the method or class name and
     * method name delimited by ::.
     * @param string|null $method Name of the method if the first argument is a
     * classname or an object.
     * @return array
     * @throws \ArgumentCountError|\ReflectionException
     */
    static function getMethodParams(array $params,$objectOrMethod,$method = null): array
    {
        $newParams = [];
        $reflectionMethod = new ReflectionMethod($objectOrMethod,$method);
        foreach ($reflectionMethod->getParameters() as $reflectionParameter){
            if(array_key_exists($reflectionParameter->getName(),$params)){
                $newParams[] = $params[$reflectionParameter->getName()];
            }elseif ($reflectionParameter->isDefaultValueAvailable()){
                $newParams[] = $reflectionParameter->getDefaultValue();
            }else{
                throw new \ArgumentCountError("Missing necessary parameters: params:".json_encode($params,JSON_UNESCAPED_UNICODE));
            }
        }
        return $newParams;
    }

    /**
     * Convert seconds to days, hours, minutes, and seconds
     * @param int $seconds
     * @return string
     */
    static function humanSeconds(int $seconds){
        $day = $seconds> 86400? floor($seconds / 86400): 0;
        $seconds -= $day * 86400;
        $hour = $seconds> 3600? floor($seconds / 3600): 0;
        $seconds -= $hour * 3600;
        $minute = $seconds> 60? floor($seconds / 60): 0;
        $seconds -= $minute * 60;
        $second = $seconds;
        $dayText = $day? $day.'day':'';
        $hourText = $hour? $hour.'hour':'';
        $minuteText = $minute? $minute.'minute':'';
        $date = $dayText. $hourText. $minuteText. $second.'seconds';
        return $date;
    }

}