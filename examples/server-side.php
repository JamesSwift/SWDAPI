<?php

// This example demonstrates how you could make API requests from within 
// your server-side website code.
//
// SWDAPI::request( method, data, authInfo);


//Include source
require "../swdapi-server.php";

//Instantiate the API and load your configuration
$API = new \JamesSwift\SWDAPI\Server("exampleConfig.json");


//Make a basic request without sending any data
$result1 = $API->request("ping");
var_dump($result1);

//Make a request sending an array of data
$result2 = $API->request("plus1", ["number"=>20]);
var_dump($result2);

//Make a request to a secure method
$result3 = $API->request("auth-test"); // <- this will return 403 access denied
var_dump($result3);


// The $API->request() method by default doesn't supply any user credentials.
// Normally each website handles user sign in through their own unqiue system.
// If you want to tell your api methods who is running this request, supply that
// information through the third parameter. You can send additional information 
// though the parameter as well, but make sure you send the credential as "authorizedUser"
// to take advantage of some in-build tools
//
// If you supply "authorizedUser" it is assumed that you have authenticated this user
// with your own system. No other authentication is done.


$result4 = $API->request("auth-test", null, [
    "authorizedUser"=> new \JamesSwift\SWDAPI\Credential("bob")
]);
var_dump($result4);



// SWDAPI also provides access to it's database connection via $API->DB
// This points to an instance of PDO.

$API->connectDB();

foreach ($API->DB->query("show tables") as $row){
    var_dump($row);   
}

