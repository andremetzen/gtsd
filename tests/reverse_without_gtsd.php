#!/usr/bin/php
<?php

include("functions.php");
include("../driver/php/Client.php");

$strings = file($argv[1]);
foreach($strings as $s)
{
	$task = new gtsd\Task("agent-test", "reverse", $s);
	echo $reverse($task)."\n";
}
