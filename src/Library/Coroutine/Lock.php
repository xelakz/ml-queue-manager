<?php
namespace MultilineQM\Library\Coroutine;

use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;

/**
 * Coroutine lock
 * Allow multiple locks for the same coroutine
 * lock and unlock must exist in a pair, otherwise it will deadlock
 * Class Lock
 * @package MultilineQM\Library\Coroutine
 */
class Lock
{
    protected $cid = null;//coroutine cid
    protected $waitGroup;

    public function __construct(){
        $this->waitGroup = new WaitGroup();
    }

    /**
     * Lock wait and lock
     * @return bool
     * @throws \Exception
     */
    public function lock(){
        $cid = Coroutine::getCid();
        if($cid == -1){
            throw new \Exception('Please use it in the coroutine environment');
        }
        if($this->cid && $this->cid != $cid){
            $this->waitGroup->wait();
        }
        $this->cid = $cid;
        $this->waitGroup->add();
        return true;
    }

    /**
     * Unlock
     * @return bool
     * @throws \Exception
     */
    public function unLock(){
        if(!$this->cid){
            return true;
        }
        $cid = Coroutine::getCid();
        if($cid == -1){
            throw new \Exception('Please use it in the coroutine environment');
        }
        if($this->cid != $cid){
            return false;
        }
        $this->cid = null;
        $this->waitGroup->done();
        return true;
    }

    /**
     * Perform lock wait
     * @return bool
     * @throws \Exception
     */
    public function wait(){
        $cid = Coroutine::getCid();
        if($cid == -1){
            throw new \Exception('Please use it in the coroutine environment');
        }
        if($this->cid == $cid || !$this->cid){
            return true;
        }
        return $this->waitGroup->wait();
    }

}