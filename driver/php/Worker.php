<?php

namespace gtsd;

include("ServerPool.php");
include("Task.php");
include("Agent.php");

class Worker extends Agent
{

    protected $functions = array();
    protected $registered = false;

    public function __construct($servers)
    {
        parent::__construct($servers);

        if(!$this->serverpool->connectAll())
        {
            throw new Exception("Could not connect to any server. Stopping worker");
        }
    }

    protected function registerFunctions()
    {
        if (!$this->registered)
        {
            $this->serverpool->writeAll(new Message(Message::FUNCTION_REGISTER, array('functions' => array_keys($this->functions))));
            $this->registered = true;
        }
    }

    protected function requestWork(Message $wakeMsg = null)
    {
        $msg = new Message(Message::WORK_REQUEST);

        if($wakeMsg === null)
            $this->serverpool->writeAll($msg);
        else
        {
            $this->serverpool->write($msg, $wakeMsg->header['connection_id']);
        }
    }
    
    protected function workComplete($task)
    {
        $this->serverpool->write(new Message(Message::WORK_DONE, array('jobid' => $task->id, 'result' => $task->result)), $task->connId);
    }
    
    public function notifyWorkStatus($task)
    {
        $message = new Message(Message::WORK_STATUS, array('jobid' => $task->id, 'status' => $task->status, 'progress' => $task->progress));
        $this->serverpool->write($message, $task->connId);
    }

    public function register($function, $callback)
    {
        $this->functions[$function] = $callback;
    }

    public function work()
    {
        $this->registerFunctions();
        $this->requestWork();
        
        $message = $this->serverpool->readAll();
            
        while($this->executeMessage($message))
        {
            $message = $this->serverpool->readAll();
            
        }
        
        return true;
    }

    private function executeMessage($message)
    {
        if($message === false || !($message instanceof Message)) return true;
        
        switch ($message->getCode())
        {
            case Message::NO_WORK:
                return true;

            case Message::WAKE:
                $this->requestWork($message);
                return true;

            case Message::RUN_WORK:
                $task = $this->run($message);
                $this->workComplete($task);
                return false;
        }
        
        return true;
        
    }
    
    protected function run($message)
    {
        $task = new Task($this, $message->getBodyData('function_name'), $message->getBodyData('workload'));
        $task->updateInfo($message);
        $task->connId = $message->header['connection_id'];
        $task->status = "working";
        $callback = $this->functions[$task->function_name];

        if (is_string($callback))
            $result = call_user_func($callback, $task);
        else if (is_callable($callback, true))
        {
            if (is_array($callback))
            {
                // an array: 0 - object, 1 - method name
                list($object, $method) = $callback;
                if (is_string($object)) // static method call
                    $result = call_user_func($callback, $task);
                else if (method_exists($object, $method))
                    $result = $object->$method($task);
                else
                    throw new Exception("The $method method does not exist in ".get_class($object));
            }
            else // PHP 5.3: anonymous function
                $result = call_user_func($callback, $task);
        }

        $task->result = $result;
        return $task;
    }


}

?>
