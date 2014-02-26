<?php



define("GTSD_DEBUG", false);
define("GTSD_DELAY", 0);
include("Worker.php");

$pool = array(
    array('host' => 'localhost', 'port' => 8124),
    array('host' => 'localhost', 'port' => 8125)
);

$resize = function($job){
    $workload = json_decode($job->workload, true);
    
    $src = imagecreatefromstring (base64_decode($workload['image']));
    $dest = imagecreatetruecolor($workload['width'], $workload['height']);
    imagecopyresampled ($dest, $src, 0, 0, 0, 0, $workload['width'], $workload['height'], imagesx($src), imagesy($src));
    ob_start();
    imagejpeg($dest);
    return base64_encode(ob_get_clean());
};

$reverse = unction($job){
    $job->setStatus((int)rand(1,99));
    return strrev($job->workload);
};

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


