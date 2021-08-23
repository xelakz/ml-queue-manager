<?php
namespace MultilineQM\OutPut;

/**
 * Command line output character formatting class
 * Class FormatOutput
 * @package MultilineQM\OutPut
 */
class FormatOutput
{
    private $label ='';
    private $content;
    private $outFile = null;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Set output to file/direct output
     * @param null $outFile file address/null (direct output)
     */
    public function setOutFile($outFile = null){
        $this->outFile = $outFile;
        return $this;
    }

    /**
     * Set bold/enhance
     */
    public function strong()
    {
        $this->label.= '1;';
        return $this;
    }

    /**
     * Set italics (not widely supported. It is sometimes regarded as reversed display.)
     */
    public function italic()
    {
        $this->label.= '3;';
        return $this;
    }

    /**
     * Underline
     */
    public function overline(){
        $this->label.= '53;';
        return $this;
    }

    /**
     * Underline (not widely supported)
     */
    public function lineThrough()
    {
        $this->label.= '9;';
        return $this;
    }

    /**
     * Underscore
     */
    public function underline()
    {
        $this->label.= '4;';
        return $this;
    }

    /**
     * Slow flashing (less than 150 times per minute)
     */
    public function slowBlink()
    {
        $this->label.= '5;';
        return $this;
    }

    /**
     * Fast flashing (not widely supported)
     */
    public function fastBlink()
    {
        $this->label.= '6;';
        return $this;
    }

    /**
     * Set font color/foreground color (rgb color value is white by default)
     * @param int $r red 0-255
     * @param int $g green 0-255
     * @param int $b blue 0-255
     */
    public function color(int $r = 255, int $g = 255, int $b = 255)
    {
        $this->label.= "38;2;$r;$g;$b;";
        return $this;
    }

    /**
     * Set the background scenery (rgb color value is black by default)
     * @param int $r red 0-255
     * @param int $g green 0-255
     * @param int $b blue 0-255
     */
    public function backgroundColor(int $r = 0, int $g = 0, int $b = 0)
    {
        $this->label.= "48;2;$r;$g;$b;";
        return $this;

    }

    /**
     * Output content
     */
    public function outPut(){
        if($this->outFile){
            file_put_contents($this->outFile,$this->getFormatContent(),FILE_APPEND | LOCK_EX);
        }else{
            echo $this->getFormatContent();
        }
    }

    /**
     * Get the formatted output content
     * @return string
     */
    public function getFormatContent(): string
    {
        $this->label = rtrim($this->label,';');
        return "\e[{$this->label}m{$this->content}\e[0m";
    }

    /**
     * Statically call quick setting content
     * @param string $content
     * @return FormatOutput
     */
    public static function setContent(string $content)
    {
        return new self($content);
    }
}