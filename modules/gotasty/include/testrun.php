<?php


    // retrieve bot api
    $URL1 = 'https://api.telegram.org/bot6084688226:AAHxUHgbmfHG9XAcOJEXSJIpYu0PdKDM37w';

    $URL = $URL1 . '/sendMessage';

    // $chat_id = $config['telegramGroup'];
    $chat_id = '-1001926795126';
    
    $content = 'hi';
    $data = [
        'chat_id'   => $chat_id,
        'text'      => $content,
    ];
    // error_log(print_r($content, true));
    $URL .= "?".http_build_query($data)."&parse_mode=Markdown";

    // ##### GET METHOD #####
    $curl=curl_init($URL);

    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_VERBOSE, 0);  // for debug
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,120);
    curl_setopt($curl, CURLOPT_TIMEOUT, 120); //timeout in seconds
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, 0);

    $response = curl_exec($curl);

    /* get http status code*/
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if(curl_errno($curl)){
        return array('code' => 1, 'status' => "error", 'statusMsg' => '', 'http_code' => $httpCode, 'curl_error_no' => curl_errno($curl), 'curl_error' => curl_error($curl));
    }

    curl_close($curl);

    // return $response;


?>