<?php

function method_test($data, $authInfo){
    
    return new \JamesSwift\SWDAPI\Response(
        200, 
        "Welcome back ".$authInfo['authorizedUser']
    );
    
}

function method_plus1($data, $authInfo){
    
    $num = $data['number'] + 1;
    
    return new \JamesSwift\SWDAPI\Response(
        200, 
        "The number is $num"
    );
    
}