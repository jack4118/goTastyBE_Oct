<?php

$db = MysqliDb::getInstance();

$URL = 'https://api.telegram.org/bot5821386642:AAHFonqAkMHSGwtmW1N4W8Slqq3oPF2nJmI/sendMessage';
$chat_id = '-1001932862151';
$content = 'testing';

$data = [
    'chat_id' => $chat_id,
    'text' => $content,
];

$curl = curl_init($URL);
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    echo 'Curl error: ' . curl_error($curl);
} else {
    echo 'Response: ' . $response;
}

curl_close($curl);

?>