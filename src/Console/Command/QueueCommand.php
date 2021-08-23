<?php

namespace MultilineQM\Console\Command;

use MultilineQM\Config\ProcessConfig;
use MultilineQM\Config\QueueConfig;
use MultilineQM\Console\BaseCommand;
use MultilineQM\OutPut\OutPut;
use MultilineQM\Queue\Queue;

class QueueCommand extends BaseCommand
{
    static protected $signature=[
      'queue:clean'=>'clean',
      'queue:status'=>'status',
      'queue:failed'=>'failedList',
    ];

    static protected $description = [
        'queue:clean'=>'Empty the contents of the queue --queue test Empty the specified queue:test',
        'queue:status'=>'View queue information --queue test 指定队列:test',
        'queue:failed'=>'Details of print failure information must specify the queue --queue test 指定队列:test'
    ];

    /**
     * Empty the queue
     */
    public function clean(){
        $this->cmd->option('queue')->describedAs('Set the corresponding queue');
        if($this->cmd['queue']){
            OutPut::info('Empty the queue: '.$this->cmd['queue'].PHP_EOL);
            \MultilineQM\Queue\Queue::cleanJob($this->cmd['queue']);
        }else{
            foreach (QueueConfig::queues() as $key=>$value){
                OutPut::info('Empty the queue: '.$key.PHP_EOL);
                \MultilineQM\Queue\Queue::cleanJob($key);
            }
        }
        return true;
    }

    /**
     * Get queue information
     */
    public function status(){
        $this->cmd->option('queue')->describedAs('Set the corresponding queue');
        if($this->cmd['queue']){
           $queues = [$this->cmd['queue']=>''];
        }else{
            $queues = QueueConfig::queues();
        }
        OutPut::normal("------Queue------Total number------To be executed------In execution------Failed------Completed------".PHP_EOL);
        foreach ($queues as $key=>$value){
            OutPut::normal('   ');
            OutPut::normal($key, 10);
            OutPut::normal(Queue::getCount($key,'all'),12);
            OutPut::normal(Queue::getCount($key,'waiting'),12);
            OutPut::normal(Queue::getCount($key,'working'),12);
            OutPut::normal(Queue::getCount($key,'failed'),12);
            OutPut::normal(Queue::getCount($key,'over'),12);
            OutPut::normal("   ".PHP_EOL);
        }
    }

    public function failedList(){
        $this->cmd->option('queue')->require(true)->describedAs('Set the corresponding queue');
        var_dump(\MultilineQM\Queue\Queue::failedList($this->cmd['queue']));
    }

}