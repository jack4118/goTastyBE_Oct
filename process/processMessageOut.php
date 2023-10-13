<?php

    /**
     * Script to process all the messages in message out table and send to recipient based on the type
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.phpmailer.php');
    include($currentPath.'/../include/class.smtp.php');
    include($currentPath.'/../include/class.pop3.php');
    include($currentPath.'/../include/class.notification.php');
    include($currentPath.'/../include/class.process.php');
    include($currentPath.'/../include/class.provider.php');
    include($currentPath.'/../include/class.message.php');
    include($currentPath.'/../include/class.log.php');

    //echo $config['dB'];
    //return $config['dBUser'];
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $pdb = new MysqliDb($config['dBHost'], $config['processUser'], $config['processPassword'], $config['dB']);
    $log = new Log($logPath, $logBaseName);
    $setting = new Setting($db);
    $mail = new PHPMailer();
    $notification = new Notification($mail);
    $provider = new Provider($db);
    $message = new Message($db, '', $provider);
    $process = new Process($pdb, $setting, $log);
    
    
    $processName = "";
    if(strlen($argv[1]) > 0)  
    $processName = $argv[1];
    $limit = (strlen($argv[2]) > 0) ? $argv[2] : 5; // Limit records
    $sleepTime = (strlen($argv[3]) > 0) ? $argv[3] : 2; // Sleep time

    while(1)
    {
            
        unset($processEnable);

        $processEnable = $setting->systemSetting['processOutGoingEnableFlag'];

        $log->write(date("Y-m-d H:i:s")." Enable Flag is: ".$processEnable.".\n");

        // 1. CHECK PROCESS ENABLE
        if($processEnable == 1)
        {

            //2. CHECK IN THE PROCESS - insert on duplicate key update to system_status
            $process->checkin('');
            
            // 3.GET PROVIDER DETAILS - for API calls 
            $providerArray = $provider->getProvider();

            // 4. check message_out, get asinged or assign  
            #getAssignedMessages       
            $results = $message->getAssignedMessages($processName, $limit);
                
            if(count($results) > 0)
            {
                // Send the notifications - to already assigned messages

                $log->write(date("Y-m-d H:i:s")." Retrieved ".count($results). " assigned messages.\n");
                    
                foreach($results as $result)
                {
                    //unset($fileArray);
                    $error = $result['error_count'];
                    $to = $result['recipient'];
                    $subject = $result['subject'];
                    $text = $result['content'];
                    $data = "";
                    $errorData = "";
                    $sent = 0;
                    $sentID = $result['sent_history_id'];
                    $sentHistoryTable = $result['sent_history_table'];
                    $providerDetails = $providerArray[$result["provider_id"]];
                    echo "\n";
                    echo "providerArray: ";
                    echo $providerArray;
                    echo "\n";

                    echo 'resultCHecking:';
                    echo strtolower($result['type']);

                    switch ($result['type']){
                        case 'email':
	
                            $db->where("h.id", $sentID);
                            $db->where("m.deleted", 0);
                            $db->where("m.url", "", "!=");
                            $db->join("career_media m", "m.id=h.reference_id", "INNER");
                            $attachmentDetail = $db->getOne($sentHistoryTable." h", "m.name, m.url");
                                
                            $fileArray = array();
                            if($attachmentDetail) {
                                //print_r($attachmentDetail);
                                $attachment_bin=file_get_contents($attachmentDetail["url"]);
                                $attachment_name=$attachmentDetail["name"];
                                $fileArray[] = array("file"=>$attachment_name, "bin"=>$attachment_bin);
                            }

                                // SMTP
                                // Send the email and check for errors
                            $response = $notification->sendEmailsUsingSMTP($to, $subject, $text, $providerArray[$result['type']], $fileArray);
                                
                            if ($response){
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else{
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'], 'processor' => $processName, 'error_code' => '', 'error_description' => $response);
                            }
                                
                            break;
                            
                        case 'mail':
                            
                            // IsMail
                            // Send the email and check for errors
                            $response = $notification->sendEmailsUsingSendmail($to, $subject, $text, $providerArray[$result['type']]);
                            
                            if ($response)
                            {
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'], 'processor' => $processName, 'error_code' => '', 'error_description' => "Mail sending failed.");
                            }
                            
                            break;
                            
                        case 'phone':
                            
                            
                            //$xml = simplexml_load_string($response);
                            // $msgCode = (string)$xml->statusCode;
                            // $msg = (string)$xml->statusMsg;
                            // Send the sms and check for errors
                            $response = $notification->sendSMS($to, $text,$providerArray[$result['type']]);
                            $msgCode = $response['code'];
                            // $msgCode = 0;
                            
                            //if success sent
                            if ($msgCode ==  0)
                            {
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'],'processor' => $processName, 'error_code' => $msgCode, 'error_description' => $msg);
                            }
                            
                            break;

                        case 'telegram':
                            $log->write(date("Y-m-d H:i:s")." Subject : ".$result['subject']."\n");
                            $log->write(date("Y-m-d H:i:s")." Provider ID : ".$result['provider_id']."\n");

                            $db->where('id',$result['provider_id']);
                            $URL = $db->getValue('provider','url1');

                            $telegramParams['url']     = $URL;
                            $telegramParams['chat_id'] = $to;
                            $telegramParams['content'] = $text;

                            $log->write(date("Y-m-d H:i:s")." Telegram Params URL : ".$telegramParams['url']."\n");
                            $log->write(date("Y-m-d H:i:s")." Telegram Params Recipient : ".$telegramParams['chat_id']."\n");
                            $log->write(date("Y-m-d H:i:s")." Telegram Params Content : ".$telegramParams['content']."\n");

                            $response = $notification->sendTelegramNotification($telegramParams);
                            $response = json_decode($response, true);

                            $status = $response['ok'];
                            
                            if ($status !== 1){
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"), 'response' => json_encode($response['result']));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('provider_id' => $providerDetails['id'], 'type' => $type, 'message_id' => $assignedMsgRow['id'], 'error_code' => $status , 'error_description' => json_encode($response['result']));
                                $log->write(date("Y-m-d H:i:s")." Provider (".$providerDetails['id'].") ".$providerDetails['company']." case has failed.\n");

                            }
                            break;


                        case 'stockWarningTelegram':
                        case 'saleOrderTelegramNotification':
                        case 'wfNotification':
                        case 'telegramadmingeneral':
                        case 'telegramadminalert':
                        case 'telegramadmingroup':
                        case 'telegramadmingroupwl':
                        case 'GoTastyMemberEnquiry':
                            $log->write(date("Y-m-d H:i:s")." Provider : ".$providerDetails['company']."\n");

                            if(strtolower($result['type']) == 'telegram'){
                                $db->where('name', 'sendTelegramNotificationURL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }

                            else  if(strtolower($result['type']) == 'stockwarningtelegram'){
                                $db->where('name', 'sendLowStockTelegramNotificationURL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }
                            else  if(strtolower($result['type']) == 'wfnotification'){
                                $db->where('name', 'wishlistFavouriteTelegramNotificationURL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }
                            else  if(strtolower($result['type']) == 'saleordertelegramnotification'){
                                $db->where('name', 'saleOrderTelegramNotificationURL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }
                            else if(strtolower($providerDetails['company']) == 'telegramadmingeneral'){
                                $db->where('name', 'sendAdminGeneralTelegramNotificationURL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }
                            else if(strtolower($providerDetails['company']) == 'telegramadminalert'){
                                $db->where('name', 'sendAdminAlertTelegramNotificationURL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }
                            else if(strtolower($providerDetails['company']) == 'telegramadmingroup'){
                                $db->where('name', 'sendTelegramAdminGroupNotificationURL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }
                            else if(strtolower($providerDetails['company']) == 'telegramadmingroupwl'){
                                $db->where('name', 'sendTelegramAdminGroupNotificationURLWL');
                                $URL = $db->getValue('provider_setting', 'value');
                            }
                            else if(strtolower($result['type']) == 'gotastymemberenquiry'){
                                $db->where('name', 'sendClientEnquiryTelegramNotification');
                                $URL = $db->getValue('provider_setting', 'value');
                            }

                            $telegramParams['url']     = $URL;
                            $telegramParams['chat_id'] = $to;
                            $telegramParams['content'] = $text;

                            $log->write(date("Y-m-d H:i:s")." Telegram Params URL : ".$telegramParams['url']."\n");
                            $log->write(date("Y-m-d H:i:s")." Telegram Params Recipient : ".$telegramParams['chat_id']."\n");
                            $log->write(date("Y-m-d H:i:s")." Telegram Params Content : ".$telegramParams['content']."\n");

                            $response = $notification->sendTelegramNotification($telegramParams);
                            $response = json_decode($response, true);

                            $status = $response['ok'];
                            
                            if ($status !== 1){
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"), 'response' => json_encode($response['result']));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('provider_id' => $providerDetails['id'], 'type' => $type, 'message_id' => $assignedMsgRow['id'], 'error_code' => $status , 'error_description' => json_encode($response['result']));
                                $log->write(date("Y-m-d H:i:s")." Provider (".$providerDetails['id'].") ".$providerDetails['company']." case has failed.\n");

                            }
                            break;
                            
                        case 'xun':
                        case 'xun2':
                            
                            $xunNumber = array();
                            
                            $setNumber = '+'.$to;
                            
                            array_push($xunNumber, $setNumber);
                            $response = $notification->sendXun($xunNumber, $text, $subject, $providerArray[$result['type']]);
                            
                            $code = $response['code'];
                            $xunMsg = $response['message_d'];
                            
                            if ($code == 1)
                            {
                                $sent = 1;
                                $data = array('sent' => $sent, 'sent_at' => date("Y-m-d H:i:s"));
                            }
                            else
                            {
                                $sent = 0;
                                $errorData = array('message_id' => $result['id'],'processor' => $processName, 'error_code' => $code , 'error_description' => $xunMsg);
                            }
                            
                            break;
                    
                    
                    }

                    $log->write(date("Y-m-d H:i:s")." $processName ".$result['type']." ".($sent == 1 ? "successfully" : "failed to")." sent to ".$to." \n");

                    $message->updateMessages($sent, $result['id'], $data, $errorData, $error, $sentID, $sentHistoryTable);

                }


            }
            else
            {
                
                $log->write(date("Y-m-d H:i:s")." Attempting to assign up to $limit record(s).\n");
                
                //Assign messges to the current process, update the processor = $processName
                $assignedCount = $message->assignMessages($processName, $limit);
                
                $log->write(date("Y-m-d H:i:s")." Assigned $assignedCount messages.\n");
                
            }

            $log->write(date("Y-m-d H:i:s")." The process is going to sleep for: ". $sleepTime. "second(s)\n");
            
            sleep($sleepTime);
                
        }
        else
        {
            
            $log->write(date("Y-m-d H:i:s")." Process :".$processName ." has been disabled. Do nothing.\n");
            
            sleep($sleepTime);
        }


    }

?>
