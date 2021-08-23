<?php


namespace MultilineQM\Server;


use MultilineQM\Exception\ClientException;
use MultilineQM\Exception\MessageException;
use MultilineQM\Library\Helper;
use MultilineQM\Library\Traits\Client;
use MultilineQM\Log\Log;
use MultilineQM\Process\MasterProcess;
use MultilineQM\Process\Message;

/**
 * Connection connection class between the master process and the worker process
 * Class MasterConnection
 * @package MultilineQM\Server
 */
class MasterConnection
{
    use Client;
    protected $process;
    protected $connection;
    protected $socket;
    public $pid;//The corresponding pid of the current connection

    /**
     * MasterConnection constructor.
     * @param \Swoole\Coroutine\Server\Connection $connection worker Connection process connection
     * @param MasterProcess $process
     */
    public function __construct(\Swoole\Coroutine\Server\Connection $connection, MasterProcess $process){
        $this->connection = $connection;
        $this->process = $process;
        $this->socket = $connection->exportSocket();
    }


    /**
     * Receive messages and process
     * @param float $timeout timeout
     * @return mixed
     * @throws \MultilineQM\Exception\MessageException
     */
    public function recvAndExec(float $timeout = -1)
    {
        try {
            $message = $this->recv($timeout);
            if($message) {
                Log::debug('Received message', $message->toArray());
                $data = $message->data();
                is_null($data) && $data = [];
                $params = is_array($data)? $data: [$data];
                $params['pid'] = $message->pid();
                $params['connection'] = $this;
                return call_user_func_array([$this->process, $message->type()], Helper::getMethodParams($params, $this->process, $message->type()));
            }
        }catch (MessageException $e){
            Log::error('Message parsing failed:'.$e->getCode().'|'.$e->getMessage(),$e->getTrace());
        }
        return false;
    }


    /**
     * Export the corresponding \Swoole\Coroutine\Server\Connection
     * @return \Swoole\Coroutine\Server\Connection
     */
    public function exportConnection(){
        return $this->connection;
    }
}