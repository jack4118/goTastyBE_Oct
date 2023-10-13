<?php

include_once('include/classlib.php');
include_once('language/lang_all.php');


// $userID = '1010382';
// $userType = 'Member';

$params['name'] = 'chee lam';
$params['emailAddress'] = 'abc123@gmail.com';
$params['phone'] = '60167169981';
$params['dialingArea'] = '60';
$params['companyName'] = 'Company name testing';
$params['streetNo'] = 'Taman ABAB 0101';
$params['city'] = 'johor bahru';
$params['zipCode'] = '81333';
$params['country'] = 'Malaysia';
$params['state'] = 'Johor';
$params['package'] = 'testing package';

$outputArray = Client::guestOwnerVerification($params);

echo json_encode($outputArray)."\n";
?>