<?php
$resize = function($job){
    $workload = json_decode($job->workload, true);

    $image = new Imagick();
    $image->readImageBlob(base64_decode($workload['image']));
    $image->resizeImage($workload['width'], $workload['height'],Imagick::FILTER_LANCZOS,1);
    $output = dirname($workload['image']).'/output/'.basename($workload['image']);
    $output = $image->getImageBlob();
    $image->destroy(); 
    return base64_encode($output);
};

$reverse = function($job){
    $job->setStatus((int)rand(1,99));
    return strrev($job->workload);
};