<?php

require "server/SWDAPI.php";

$API = new \JamesSwift\SWDAPI\SWDAPI(__DIR__."/exampleConfig.json");

$response = $API->request("test");

var_dump($response);

var_dump(get_class_methods ($API));;