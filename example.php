<?php

require "server/SWDAPI.php";

$API = new \JamesSwift\SWDAPI\SWDAPI(__DIR__."/exampleConfig.json");

var_dump($API->request("test"));

var_dump($API->request("plus1", ["number"=>20]));

var_dump($API->request("test", null, ["authorizedUser"=>"fred"]));


