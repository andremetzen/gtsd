<?php

namespace gtsd;

class Message {
    public $header;
    public $body;
    
    const ACKNOWLEDGE = 1;
    const CONNECTION_ESTABLISHED = 2;
    const FUNCTION_REGISTER = 3;
    const WORK_REQUEST = 4;
    const NO_WORK = 5;
    const WAKE = 6;
    const RUN_WORK = 7;
    const WORK_CREATED = 8;
    const WORK_RUNNING = 9;
    const WORK_STATUS = 10;
    const WORK_DONE = 11;
    
    public function __construct($code, $body = array(), $hostname = null)
    {
        if($hostname === null) $hostname = gethostname();
        
        $this->header = array('origin' => $hostname, 'code' => $code);
        $this->body = $body;
    }
    
    public function getCode()
    {
        return $this->header['code'];
    }
    
    public function getOrigin()
    {
        return $this->header['origin'];
    }
    
    public function getBody()
    {
        return $this->body;
    }
    
    public function getBodyData($data)
    {
        if(isset($this->body[$data]))
            return $this->body[$data];
        else
            return null;
    }
    
    public function __toString()
    {
        return json_encode(array('header' => $this->header, 'body' => $this->body));
    }
    
    public function dump()
    {
        echo "======================================================================\n";
        $content = "Code: ".$this->header['code']." | Origin: ".$this->header['origin'];
        echo "| ".$content;
        for($i=strlen($content)+3; $i<70; $i++) echo " ";
        echo "|\n";
        echo "======================================================================\n";
        foreach($this->body as $k=>$v)
        { 
            $cv = (is_array($v)) ? str_replace("\n", " ", var_export($v, 1)) : $v;
            $content = $k.": ".$cv;
            echo "| ".$content;
            for($i=strlen($content)+3; $i<70; $i++) echo " ";
            echo "|\n";
        }
        echo "======================================================================\n\n";
        
    }
    
    public static function fromJSON($data)
    {
        return new Message($data['header']['code'], $data['body'], $data['header']['origin']);
    }
}
