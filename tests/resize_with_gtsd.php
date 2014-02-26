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

    $dirPath = __DIR__ . '/../testdata/';
	$dir = opendir($dirPath);
	while ($filename = readdir($dir)) {
	    if (!is_dir($dirPath . $filename)) {
	        $imageSize = getimagesize($dirPath . $filename);
	        $workload = json_encode(array('image' => base64_encode(file_get_contents($dirPath . $filename)), 'width' => $imageSize[0]/2, 'height' => $imageSize[1] / 2));
	        $resized = $client->run('resize', $workload);
	    }
	}
    
} catch (gtsd\Exception $e) {
    echo $e->getMessage() . "\n";
}

