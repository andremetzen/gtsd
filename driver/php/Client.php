<?php

namespace gtsd;

include("ServerPool.php");
include("Task.php");
include("Agent.php");
include_once("Exception.php");

class Client extends Agent {
    public $callbacks = array();
    public $tasks = array();
    
    public function run($function, $workload, $options = array())
    {
        $default = array(
            'priority' => 0,
            'async' => false,
        );
        
        foreach($default as $key=>$value)
            if(!isset($options[$key])) $options[$key] = $value;
    
    
        $message = new Message(Message::RUN_WORK, array(
            'function_name' => $function,
            'workload' => $workload,
            'priority' => $options['priority'], // Validar essas informações
            'async' => $options['async'],
        ));  
        
        if(!$this->serverpool->suffleConnection())
            throw new Exception("Could not send the request to server");
            
        $this->serverpool->write($message);
        $message = $this->serverpool->read();
        
        if(!($message instanceof Message))
        {
            //var_dump($message);
            throw new Exception("Could not read the message from the server");
        }
        
        $this->addTask($function, $workload, $message);
        $this->runCallback('create', $message);
        
        if(!$options['async'])
        {
            while($response = $this->serverpool->read())
            {
                $task = $this->getTask($response);
                
                if($task instanceof Task)
                    $task->updateInfo($response);
                
                if($response->getCode() == Message::WORK_DONE)
                {
                    $this->runCallback('finish', $response);
                    $this->delTask($response->getBodyData('jobid'));
                    return $response->getBodyData('result');
                }
                elseif($response->getCode() == Message::WORK_STATUS)
                {
                    $this->runCallback('status', $response);
                }
            }
        }
    }
    
    protected function runCallback($type, $message)
    {
        if(isset($this->callbacks[$type]) && is_callable($this->callbacks[$type]))
        {
            $task = $this->getTask($message);
            $callback = $this->callbacks[$type];

            if(is_string($callback))
                call_user_func($callback,$task);
            else if(is_callable($callback,true))
            {
                if(is_array($callback))
                {
                    // an array: 0 - object, 1 - method name
                    list($object,$method)=$callback;
                    if(is_string($object))	// static method call
                        call_user_func($callback,$task);
                    else if(method_exists($object,$method))
                        $object->$method($task);
                    else
                        throw new Exception("The $method method does not exist in ".get_class($object));
                }
                else // PHP 5.3: anonymous function
                    call_user_func($callback,$task);
            }
        }
    }
    
    public function onStatus($callback)
    {
        $this->callbacks['status'] = $callback;
    }
    
    public function onCreate($callback)
    {
        $this->callbacks['create'] = $callback;
    }
    
    public function onFinish($callback)
    {
        $this->callbacks['finish'] = $callback;
    }
    
    protected function addTask($function_name, $workload, Message $message)
    {
        $task = new Task($this, $function_name, $workload);
        
        if($message->getCode() == Message::WORK_CREATED)
        {
            $task->id = $message->getBodyData('jobid');
            $this->tasks[$task->id] = $task;
        }
    }
    
    protected function getTask($jobid)
    {
        if($jobid instanceof Message)
        {
            $jobid = $jobid->getBodyData('jobid');
        }
        
        
        return (isset($this->tasks[$jobid])) ? $this->tasks[$jobid] : null;
    }
    
    protected function delTask($jobid)
    {
        unset($this->tasks[$jobid]);
    }
        
              
}
