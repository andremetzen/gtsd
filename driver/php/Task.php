<?php

namespace gtsd;

class Task
{
    public $id;
    public $progress = 0;
    public $status = 'created';
    public $function_name;
    public $workload;
    public $result;
    public $connId;
    public $agent;
    
    
    public function __construct($agent, $function_name, $workload)
    {
        $this->agent = $agent;
        $this->function_name = $function_name;
        $this->workload = $workload;
    }
    
    public function updateInfo(Message $message)
    {
        $this->id = $message->getBodyData('jobid');
        
        switch($message->getCode())
        {
            case Message::WORK_STATUS:
                $this->status = $message->getBodyData('status');
                $this->progress = $message->getBodyData('progress');
                break;
            
            case Message::WORK_DONE:
                $this->status = 'finished';
                $this->result = $message->getBodyData('result');
                $this->progress = 100;
                break;
        }
    }
    
    public function setStatus($progress)
    {
        $this->progress = $progress;
        if($this->agent instanceof Worker)
        {
            $this->agent->notifyWorkStatus($this);
        }
    }
}

?>
