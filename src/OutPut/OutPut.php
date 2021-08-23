<?php

namespace MultilineQM\OutPut;

/**
 * Command line output
 * Class OutPut
 * @package MultilineQM\OutPut
 */
class OutPut
{
    static private $outFile = null;

    /**
     * Set output file
     * @param $outFile
     */
    public static function setOutFile($outFile = null){
        self::$outFile = $outFile;
    }

    /**
     * Normal output
     * @param string $content
     * @param int $len
     */
    public static function normal(string $content, $len = 0)
    {
        FormatOutput::setContent(self::center($content, $len))->setOutFile(self::$outFile)->outPut();
    }

    /**
     * Information output
     * @param string $content
     * @param int $len
     */
    public static function info(string $content, $len = 0)
    {
        FormatOutput::setContent(self::center($content, $len))->setOutFile(self::$outFile)->color(0, 255, 0)->outPut();
    }

    /**
     * Warning output
     * @param string $content
     * @param int $len
     */
    public static function warning(string $content, $len = 0)
    {
        FormatOutput::setContent(self::center($content, $len))->setOutFile(self::$outFile)->color(255, 230, 0)->outPut();
    }

    /**
     * Error output
     * @param string $content
     * @param int $len
     */
    public static function error(string $content, $len = 0)
    {
        FormatOutput::setContent(self::center($content, $len))->setOutFile(self::$outFile)->color(255, 0, 0)->outPut();
    }

    /**
     * The content is centered (fill with spaces on both sides)
     * @param string $content
     * @param int $len needs to be an even number
     * @return string
     */
    public static function center(string $content, $len = 0)
    {
        $newStr = preg_replace('/[^\x{4e00}-\x{9fa5}]/u', '', $content);
        $mbLen = mb_strlen($newStr,"utf-8");
        $strLen = mb_strlen($content)+$mbLen ;
        if($len > 0 && $strLen %2 !=0){
            $content =' '.$content;
            $strLen ++;
        }
        $n = ($len - $strLen) / 2;
        if ($strLen < $len) {
            for ($i = 0; $i < $n; $i++) {
                $content = ' ' . $content . ' ';
            }
        }
        return $content;
    }

}