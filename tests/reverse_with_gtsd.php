#!/usr/bin/php
<?php

include("../driver/php/Client.php");

$pool = array();

$servers = explode(",", $argv[1]);
foreach($servers as $s)
{
    list($host, $port) = explode(":", $s);
    $pool[] = array('host' => $host, 'port' => $port);
}


try {
    $client = new gtsd\Client($pool);
    $strings = file($argv[2]);
    foreach($strings as $s)
    {
        echo $client->run('reverse', $s)."\n";
    }
    
} catch (gtsd\Exception $e) {
    echo $e->getMessage() . "\n";
}

