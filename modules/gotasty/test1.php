<?php

include_once('include/classlib.php');
include_once('language/lang_all.php');


// $userID = '1010382';
// $userType = 'Member';

$params['phone'] = '60167778888';
$params['type'] = 'register';
$params['dialCode'] = '60';
$params['number'] = '167778888';

$outputArray = Client::clientPurchaseHistory($params);

echo json_encode($outputArray)."\n";
?>