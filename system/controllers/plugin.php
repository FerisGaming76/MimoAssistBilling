<?php
/**
 *  PHP Mikrotik Billing (https://github.com/MimoAssistBilling/)
 *  by https://t.me/mimoassist
 **/

if(function_exists($routes[1])){
    call_user_func($routes[1]);
}else{
    r2(U.'dashboard', 'e', 'Function not found');
}