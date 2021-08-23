<?php

namespace MultilineQM\Library\Traits;

use MultilineQM\Exception\ClientException;
use MultilineQM\Exception\MessageException;
use MultilineQM\Library\Helper;
use MultilineQM\Log\Log;
use MultilineQM\Process\Message;
use MultilineQM\Library\Coroutine\Lock;

trait Client
{
    protected $socket = null;
    protected $lock = null;

    /**
     * Acquire the current client coroutine lock
     * @return Lock
     */
    public function getLock(): Lock
    {
        if (!$this->lock) {
            $this->lock = new Lock();
        }
        return $this->lock;
    }

    /**
     * Return the connection status of the Client
     * @return mixed
     */
    public function isConnected(): bool
    {
        return $this->socket->checkLiveness();
    }

    /**
     * Export the current \Swoole\Coroutine\Socket object
     * @return mixed|\Swoole\Coroutine\Socket|null
     */
    public function exportSocket(): \Swoole\Coroutine\Socket
    {
        return $this->socket;
    }

    /**
     * Send a message
     * Send data as completely as possible, until all data is successfully sent or an error is encountered.
     * When the send system call returns the error EAGAIN, the bottom layer will automatically monitor the writable event and suspend the current coroutine.
     * @param $type
     * @param null $data
     * @param string $msg
     * @return mixed
     */
    public function send(string $type, $data = null, string $msg =''): bool
    {
        $str = (new Message($type, $data, $msg))->serialize();
        //Add a coroutine lock when sending data to prevent the coroutine from suspending send conflicts when the buffer is full
        if(\Swoole\Coroutine::getCid() != -1){
            $this->getLock()->lock();
            $len = $this->socket->sendAll($str);
            $this->getLock()->unLock();
        }else{
            $len = $this->socket->sendAll($str);
        }
        return strlen($str) === $len;
    }

    /**
     * Receive the complete message package and return the corresponding message object
     * @param float $timeout
     * @return Message|null
     * @throws \MultilineQM\Exception\MessageException
     */
    public function recv(float $timeout = -1)
    {
        $data = $this->socket->recvPacket($timeout);
        //An error occurs or the peer closes the connection, the local end also needs to be closed
        if (!$data) {
            // You can handle it according to the business logic and error code, for example:
            // If the timeout, the connection will not be closed, otherwise the connection will be closed directly
            if ($this->socket->errCode !== SOCKET_ETIMEDOUT) {
                $this->close();
                throw new ClientException('Connection has been closed', SOCKET_ECONNREFUSED);
            }
            return $data;
        }
        return Message::unSerialize($data);
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
            if($message){
                Log::debug('Received message',$message->toArray());
                $data = $message->data();
                is_null($data) && $data = [];
                $params = is_array($data)? $data: [$data];
                $params['pid'] = $message->pid();
                return call_user_func_array([$this->process, $message->type()], Helper::getMethodParams($params,$this->process,$message->type()));
            }
        }catch (MessageException $e){
            Log::error('Message parsing failed:'.$e->getCode().'|'.$e->getMessage(),$e->getTrace());
        }catch (\Throwable $e){
            if(method_exists($this->process,'exceptionHandler')){
                $this->process->exceptionHandler($e);
            }else{
                throw $e;
            }
        }
        return false;
    }

    /**
     * Close connection
     * @return mixed
     */
    public function close(): bool
    {
        return $this->socket->close();
    }

    /**
     * The peek method is only used to peek at the data in the kernel socket buffer area without offset. After using peek, you can still read this part of the data by calling recv
     * The peek method is non-blocking, it will return immediately. When there is data in the socket buffer, the data content will be returned. Return false when the buffer area is empty, and set $client->errCode
     * The connection has been closed peek will return an empty string
     * @param int $length
     * @return mixed
     */
    public function peek(int $length = 65535)
    {
        return $this->socket->peek($length);
    }

    /**
     * Set protocol parameters (to deal with sticky packet problems)
     * @return bool
     */
    public function setProtocol(): bool
    {
        return $this->socket->setProtocol(Message::protocolOptions());
    }}