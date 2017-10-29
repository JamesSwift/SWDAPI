<?php

function method_ping($data, $authInfo){
    
    return new \JamesSwift\SWDAPI\Response(
        200, 
        "pong"
    );
    
}

function method_authtest($data, $authInfo){
    
    return new \JamesSwift\SWDAPI\Response(
        200, 
        "Welcome back ".$authInfo->authorizedUser
    );
    
}

function method_plus1($data, $authInfo){

    $num = $data['number'] + 1;
    
    return new \JamesSwift\SWDAPI\Response(
        200, 
        $num
    );
    
}