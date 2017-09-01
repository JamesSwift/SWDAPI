<?php

//Include code
require "SWDAPI.php";

//Instantiate the API and load your configuration
$API = new \JamesSwift\SWDAPI\SWDAPI("exampleConfig.json");

//Optionally define a function to verify user-pass login requests
//
// If the $user and $pass that are passed in are correct, the function
// should return a string with the user id. This may be the same as the
// $user variable passed in, or it may be different (such as a numeric UID).
// Note that it must be of type string, or it will be assumed the login failed.
// This returned value will be stored and supplied to to called methods as
// part of the array in the second parameter: 'authorizedUser'
//
// If the credentials don't match the function should return false
//
// This function isn't called on every request, rather only on requests to 
// "swdapi/getAuthToken".
// If the login is successfull, a token is sent to the client which will then be
// pased back on each subsequent request.

$API->registerCredentialVerifier(function($user, $pass){
    
    //Obviously this would normally be a lookup of a database, but for simplicity...
    if ($user==="test" && $pass==="password"){
        return "test";
    }
    
    //Didn't match
    return false;
});


// Optionally setup your own security method
//
// SWDAPI provides it's own method (above) for a client to authenticate,
// but if you are using the API on a website which has a session based 
// authentication system already in place, it may be easier to use this
// technique.
//
// It function should return an array of security information. This
// will be passed to any called method as the second parameter
// It can contain any data you want, but for convience try to 
// store the user id in "authorizedUser", as SWDAPI provides 
// filtering around this value.
//
// Note this will only be called if authentication via the built in 
// method doesn't succeed (or isn't set up).

/*
$API->registerSecurityFallback(function(){
    
    session_start();
    return [
        "authorizedUser"=>$_SESSION['userid'];
    ];
    
});
*/

//Respond to a request made over http
$request = $API->listen();
$request->sendHttpResponse();

