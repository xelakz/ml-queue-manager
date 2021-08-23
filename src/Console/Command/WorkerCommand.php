<?php


namespace MultilineQM\Console\Command;

use MultilineQM\Config\BasicsConfig;
use MultilineQM\Config\LogConfig;
use MultilineQM\Config\ProcessConfig;
use MultilineQM\Console\BaseCommand;
use MultilineQM\Log\Log;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Process\ManageProcess;
use Swoole\Event;
use Swoole\Process;
use Swoole\Timer;

class WorkerCommand extends BaseCommand
{

    private $manage;
    private $wait = false;

    static protected $signature=[
        'worker:start'=>'start',
        'worker:stop'=>'stop',
        'worker:restart'=>'restart',
        'worker:reload'=>'reload',
        'worker:status'=>'status',

    ];

    static protected $description = [
        'worker:start'=>'Start with parameter -d background start',
        'worker:stop'=>'stop',
        'worker:restart'=>'Restart with parameter -d start in background',
        'worker:reload'=>'smooth restart',
        'worker:status'=>'View process running status',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->manage = new ManageProcess();
    }

    /**
     * start up
     */
    public function start()
    {
        $this->cmd->option('d')->boolean()->describedAs('Carrying this parameter means starting in daemon mode');
        if ($this->manage->getPid()) {
            Log::error('Started and cannot be restarted');
            exit();
        }
        if($this->cmd['d']){
            OutPut::normal("Start as a daemon process, please go to the log to query detailed information, log directory address:" . realpath(LogConfig::path()) . "\n");
            Process::daemon();
            ProcessConfig::setDaemon(true,false);
        }
        Log::info('Starting, please wait....');
        $this->manage->run();
        $this->wait();
    }

    /**
     * Reboot
     * @throws \Exception
     */
    public function restart()
    {
       $this->stop();
       $this->start();
    }


    /**
     * Get process status information
     * @param $timer
     */
    public function status()
    {
        OutPut::normal("Querying the running status of MultilineQM...\n");
        $pid = $this->manage->getPid();
        if (!$pid) {
            OutPut::normal("The queue is not started\n");
            exit();
        }
        @unlink($this->manage->getStatusFile());
        Process::kill($pid, ProcessConfig::SIG_STATUS);
        Timer::tick(1600, function ($timer) {
            if (file_exists($this->manage->getStatusFile())) {
                Timer::clear($timer);
                $processes = json_decode(file_get_contents($this->manage->getStatusFile()), true);
                $this->manage->outPutStatusInfo($processes);
                @unlink($this->manage->getStatusFile());
            }
        });
        $this->wait();
    }

    /**
     * Stop
     * @param false $is_start
     * @throws \Exception
     */
    public function stop()
    {
        $pid = $this->manage->getPid();
        if($pid){
            OutPut::normal("Stopping....\n");
            Process::kill($pid, ProcessConfig::SIG_STOP);
            while (true){
                usleep(500000);
                if (!$this->manage->getPid()) {
                    OutPut::normal("stopped...\n");
                    return true;
                }
            }
        }
        OutPut::normal('The queue is not started, no need to stop'.PHP_EOL);
        return ;
    }

    /**
     * Graceful restart
     */
    public function reload(){
        $pid = $this->manage->getPid();
        if (!$pid) {
            OutPut::normal('The queue is not started, no smooth restart is required'.PHP_EOL);
            exit();
        }
        Process::kill($pid, ProcessConfig::SIG_RELOAD);
        OutPut::normal("Smooth restart signal sent successfully");
    }

    /**
     *
     */
    private function wait()
    {
        if (!$this->wait) {
            $this->wait = true;
            Event::wait();
        }
    }

}