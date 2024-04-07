<?php
/**
 *  PHP Mikrotik Billing (https://github.com/MimoAssistBilling/)
 *  by https://t.me/mimoassist
 **/

if(Admin::getID()){
    r2(U.'dashboard');
}if(User::getID()){
    r2(U.'home');
}else{
    r2(U.'login');
}
