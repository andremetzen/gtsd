<?php

namespace gtsd;

class Agent
{
    /**
     *
     * @var ServerPool 
     */
    public $serverpool;
    
    public function __construct($servers)
    {
        $this->serverpool = new ServerPool($servers, $this);
    }
}

?>
