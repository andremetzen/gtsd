<?php

namespace gtsd;

include("Connection.php");
include_once("Exception.php");

class ServerPool {

    protected $servers = array();
    protected $conns = array();
    protected $conn_by_id = array();

    /**
     *
     * @var Agent
     */
    protected $agent;

    public function __construct($servers, Agent $agent = null) {
        $this->servers = $servers;
        $this->agent = $agent;
    }

    public function connect($server) {
        try {
            $conn = new Connection($server["host"], $server["port"], $this->agent);
            return $this->conn_by_id[$conn->getId()] = $conn;
        } catch (\Exception $e) {
            echo $e->getMessage() . "\n";
            return false;
        }
    }

    public function connectAll() {
        foreach ($this->servers as $k => $s) {
            if ($conn = $this->connect($s)) {
                $this->conns[$k] = $conn;
            }
        }

        return !empty($this->conns);
    }

    private function getAliveServerKey() {
        $key = null;
        $servers = $this->servers;
        $servers_with_key = array();

        foreach ($this->servers as $k => $s) {
            $s['key'] = $k;
            $servers_with_key[] = $s;
        }

        do {
            shuffle($servers_with_key);
            $key = $servers_with_key[0]['key'];
            if (isset($servers[$key]['alive']) && !$servers[$key]['alive']) {
                $key = null;
                unset($servers[$key]);
            }
        } while ($key === null || empty($servers));

        return $key;
    }

    public function suffleConnection() {

        $key = $this->getAliveServerKey();
        if ($key === null) {
            return false;
        }

        if (isset($this->conns[$key]) && $this->conns[$key] instanceof Connection && $this->conns[$key]->isActive()) {
            $this->conns[$key];
            $this->setLastConn($key);
        } else {
            if (!($conn = $this->connect($this->servers[$key]))) {
                $this->servers[$key]['alive'] = false;
                return $this->suffleConnection();
            }

            $this->conns[$key] = $conn;
        }

        $this->setLastConn($key);
        return true;
    }

    private function setLastConn($key) {
        reset($this->conns);
        while (key($this->conns) != $key)
            next($this->conns);
    }

    /**
     *
     * @return Connection
     */
    public function getLastConn() {
        return current($this->conns);
    }

    /**
     *
     * @return Message
     */
    public function read() {
        if ($this->getLastConn())
            return $this->getLastConn()->read();
        else
            return false;
    }

    public function write(Message $message, $toId = null) {
        if ($toId !== null)
            $conn = $this->conn_by_id[$toId];
        else
            $conn = $this->getLastConn();

        return $conn->write($message);
    }
    
    public function whoIsReady() {
        $sockets = array();
        foreach ($this->conns as $c)
            $sockets[] = $c->getSocket();

        $write = NULL;
        $except = NULL;

        $selected = socket_select($sockets, $write, $except, null);

        if ($selected === false) {
            echo "socket_select() failed, reason: " . socket_strerror(socket_last_error()) . "\n";
        } else {
            //if(count($sockets) > 1)
            //    var_dump($sockets);
            
            return reset($sockets);
        }
    }

    protected function getConnectionBySocket($socket)
    {
        foreach($this->conns as $c)
            if($c->getSocket() == $socket)
                return $c;
    }
    
    public function readAll() {
        $select = $this->whoIsReady();
        $conn = $this->getConnectionBySocket($select);
        return $conn->read();
    }

    public function writeAll(Message $message) {
        foreach ($this->conns as $c) {
            $c->write($message);
        }

        return count($this->conns);
    }

}
