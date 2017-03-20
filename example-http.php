<?php

//Include code
require "SWDAPI.php";

//Instantiate the API and load your configuration
$API = new \JamesSwift\SWDAPI\SWDAPI("exampleConfig.json");


// Optionally setup your own security method
// SWDAPI provides it's own method for a client to authenticate
// but if you ignore it you can write your own here.
//
// It should return an array of security infomartion. This
// will be passed to any called method as the second parameter
// It can contain any data you want, but for convience try to 
// store the user id in "authorizedUser", as SWDAPI provides 
// filtering around this value.
//
/*
$API->registerSecurityFallback(function(){
    
    return [
        "authorizedUser"=>$_SESSION['userid'];
    ];
    
});
*/

//Respond to a request made over http
$request = $API->listen();
$request->sendHttpResponse();

