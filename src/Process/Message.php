<?php

namespace MultilineQM\Process;

use MultilineQM\Exception\MessageException;
use \MultilineQM\Serialize\JsonSerialize;
use MultilineQM\Serialize\PhpSerialize;

/**
 * Inter-process communication message class
 * Class Message
 * @package MultilineQM\Socket
 */
class Message
{
    protected $type;

    protected $data;

    protected $msg;

    protected $pid;

    /**
     * Message constructor.
     * @param string $type message type
     * @param null|array $data message data
     * @param string $msg information
     * @param null $pid message clientPid
     */
    public function __construct(string $type, $data = null, $msg = '', $pid = null)
    {
        $this->type = $type;
        $this->data = $data;
        $this->msg = $msg;
        $this->pid = $pid ?: getmypid();
    }

    public function __call($name, $arg)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }
        return null;
    }

    public function serialize(): string
    {
        $str = PhpSerialize::serialize(['type' => $this->type, 'pid' => $this->pid, 'data' => $this->data, 'msg' => $this->msg]);
        return pack('N', strlen($str)) . $str;
    }

    public static function unSerialize(string $string)
    {
//        $pack = unpack('N', substr($string, 0, 4));
//        $len = $pack[1];//Length bytes of data sent by the client
        $string = substr($string, 4);
//        if ($len != strlen($string)) throw new MessageException('Wrong message format:'. $string);
        $data = PhpSerialize::unSerialize($string);
        if (is_array($data) && isset($data['type']) && !empty($data['pid'])) {
            return new self($data['type'], isset($data['data'])? $data['data']: null, isset($data['msg'])? $data['msg' ]:'', $data['pid']);
        }
        throw new MessageException('Wrong message format:'. $string);
    }

    /**
     * Get the message communication protocol
     * @return array
     */
    public static function protocolOptions(): array
    {
        return [
            'open_length_check' => true,
            'package_max_length' => 2 * 1024 * 1024,
            'package_length_type' =>'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
        ];
    }

    /**
     * Get the array
     * @return array
     */
    public function toArray(){
        return ['type'=>$this->type,'data'=>$this->data,'msg'=>$this->msg,'pid'=>$this->pid];
    }
}