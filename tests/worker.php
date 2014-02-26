#!/usr/bin/php
<?php
include("../driver/php/Worker.php");
include("functions.php");

$pool = array();

$servers = explode(",", $argv[1]);
foreach($servers as $s)
{
    list($host, $port) = explode(":", $s);
    $pool[] = array('host' => $host, 'port' => $port);
}

try
{
    $worker = new gtsd\Worker($pool);
    $worker->register("resize", $resize);
    $worker->register('reverse', $reverse);

    while($worker->work())
        echo "Done\n";
}
catch(Exception $e)
{
    echo $e->getMessage()."\n";
}


