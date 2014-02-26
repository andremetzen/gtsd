#!/usr/bin/php
<?php

include("../driver/php/Worker.php");

$pool = array();

$servers = explode(",", $argv[1]);
foreach($servers as $s)
{
    list($host, $port) = explode(":", $s);
    $pool[] = array('host' => $host, 'port' => $port);
}

$resize = function($job){
    $workload = json_decode($job->workload, true);

    $image = new Imagick();
    $image->readImageBlob(base64_decode($workload['image']));
    $image->resizeImage($workload['width'], $workload['height'],Imagick::FILTER_LANCZOS,1);
    $output = $image->getImageBlob();
    $image->destroy(); 
    
    return base64_encode($output);
};

try
{
    $worker = new gtsd\Worker($pool);
    $worker->register("resize", $resize);
    while($worker->work())
        echo $argv[2].": job done\n";
}
catch(Exception $e)
{
    echo "Error: ".$e->getMessage()."\n";
}


