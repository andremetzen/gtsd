<?php

define("GTSD_DEBUG", false);
define("GTSD_DELAY", 0);
include("Client.php");

$pool = array(
    array('host' => 'localhost', 'port' => 8124),
    array('host' => 'localhost', 'port' => 8125)
);


try {
    $client = new gtsd\Client($pool);

    /*$dirPath = __DIR__ . '/../../testdata/';
    $dir = opendir($dirPath);
    while ($filename = readdir($dir)) {
        if (!is_dir($dirPath . $filename)) {
            $imageSize = getimagesize($dirPath . $filename);
            $source = base64_encode(file_get_contents($dirPath . $filename));
            $workload = json_encode(array('image' => $source, 'width' => 300, 'height' => ($imageSize[1] / $imageSize[0]) * 300));
            $resized = $client->run('resize', $workload);
            file_put_contents($dirPath . 'output/' . $filename, base64_decode($resized));
        }
    }*/

    $client->onStatus(function($job){
        echo $job->id. ' at '.$job->progress."\n";
    });
    
    for($i = 0; $i < 50; $i++)
    {
        echo $client->run('reverse', 'mensagem-'.$i)."\n";
    }
    
} catch (gtsd\Exception $e) {
    echo $e->getMessage() . "\n";
}

echo "\n";

