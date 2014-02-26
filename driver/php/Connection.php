<?php

namespace gtsd;

include("Message.php");
include_once("Exception.php");

const MESSAGE_SEPARATOR = "\n\n";

class Connection
{

    const buffer = 4096;

    protected $socket;
    protected $id;
    private $active;
    private $buffer = false;

    public function __construct($server, $port, Agent $agent)
    {
        if (!($this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
            throw new Exception("Could not establish connection on ".$server.":".$port);

        if (!($this->active = @socket_connect($this->socket, $server, $port)))
            throw new Exception("Could not establish connection on ".$server.":".$port);
        
        $this->setNonBlocking();
        
        if (!$this->acknowledge($agent))
            throw new Exception("Could not establish connection");
    }

    public function getId()
    {
        return $this->id;
    }

    public function setNonBlocking()
    {
        socket_set_nonblock($this->socket);
    }

    public function setBlocking()
    {
        socket_set_block($this->socket);
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function acknowledge(Agent $agent)
    {
        if ($agent instanceof Client)
            $agent = 'client';
        elseif ($agent instanceof Worker)
            $agent = 'worker';

        $this->write(new Message(Message::ACKNOWLEDGE, array('type' => $agent)));
        if (($message = $this->read()) instanceof Message && $message->getCode() == Message::CONNECTION_ESTABLISHED)
        {
            $this->id = $message->getBodyData('connection_id');
            return true;
        }
        return false;
    }

    public function read()
    {
        //echo "trying to read connection ".$this->getId()."\n";
        if ($this->isActive())
        {
            while(($pos = strpos($this->buffer, MESSAGE_SEPARATOR)) === false)
                $this->buffer .= socket_read($this->socket, self::buffer*10000, PHP_NORMAL_READ);

            $msgs = explode(MESSAGE_SEPARATOR, $this->buffer);
            $buffer = array_shift($msgs);
            $this->buffer = implode(MESSAGE_SEPARATOR, $msgs);

            if(empty($buffer))
                return null;

            $data = json_decode(trim($buffer), true);
            if ($data === null)
            {
                $this->buffer .= $buffer;
                return null;
            }           

            $message = Message::fromJSON($data);
            
            if (defined("GTSD_DEBUG") && GTSD_DEBUG)
            {
                echo "->\n";
                $message->dump();
                sleep((defined('GTSD_DELAY') ? GTSD_DELAY : 5));
            }
            
            $message->header['connection_id'] = $this->id;
            
            return $message;
        }
        else
        {
            echo "Connection dropped (on read)\n";
            return false;
        }
    }

    public function write(Message $message)
    {
        if ($this->isActive())
        {
            if (defined("GTSD_DEBUG") && GTSD_DEBUG)
            {
                echo "<- ".$this->getId()." \n";
                $message->dump();
                sleep((defined('GTSD_DELAY') ? GTSD_DELAY : 5));
            }

            $buffer = $message.MESSAGE_SEPARATOR;
            $length = strlen($buffer);
        
            $this->setBlocking();
            while (true) {
                $sent = socket_write($this->socket, $buffer, $length);
                    
                if ($sent === false) {
                    break;
                }

                if ($sent < $length) {
                    $buffer = substr($buffer, $sent);
                    $length -= $sent;

                } else {
                    break;
                }
                    
            }
            $this->setNonBlocking();

            return $sent;
        }
        else
        {
            if (defined("GTSD_DEBUG") && GTSD_DEBUG)
                echo "Connection dropped (on write)\n";
            return false;
        }
    }

    public function isActive()
    {
        //return true;
        return (socket_last_error($this->socket) === 0);
    }

}
