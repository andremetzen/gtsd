#!/usr/bin/php
<?php

function random_string($size)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randstring = '';
    for ($i = 0; $i < $size; $i++) {
        $randstring .= $characters[rand(0, strlen($characters)-1)];
    }
    return $randstring;
}

$num_strings = 1000;
$length_strings = array(10, 100, 1000, 100000);



foreach($length_strings as $length)
{
	$output = "";

	for($i = 0; $i<$num_strings; $i++)
	{
		$output .= random_string($length)."\n";
	}

	file_put_contents("strings_".$length.'.txt', $output);
}