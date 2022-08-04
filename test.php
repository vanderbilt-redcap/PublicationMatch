<?php
if(!defined("SUPER_USER") || !SUPER_USER) {
	die();
}
$source_name = "mcguffk";
$source_type = "vunet";
echo "Source: ".$source_name;

$return_data = $module->sendAPIRequest($source_type, $source_name);

$return_data = json_decode($return_data,true);

echo "<br /><pre>";
var_dump($return_data);
echo "</pre><br />";