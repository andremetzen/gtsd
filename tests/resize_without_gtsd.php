#!/usr/bin/php
<?php

include("functions.php");
include("../driver/php/Client.php");

$dirPath = __DIR__ . '/../testdata/';
$dir = opendir($dirPath);
while ($filename = readdir($dir)) {
    if (!is_dir($dirPath . $filename)) {
        $imageSize = getimagesize($dirPath . $filename);
        $workload = json_encode(array('image' => base64_encode(file_get_contents($dirPath . $filename)), 'width' => $imageSize[0]/2, 'height' => $imageSize[1] / 2));
        $task = new gtsd\Task("agent-test", "resize", $workload);
		$resize($task);
    }
}