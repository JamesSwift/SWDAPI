<?php

//Include code
require "server/SWDAPI.php";

//Instantiate the API and load your configuration
$API = new \JamesSwift\SWDAPI\SWDAPI("exampleConfig.json");

//Respond to a request made over http
$request = $API->listen();
$request->sendHttpResponse();

