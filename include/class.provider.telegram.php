<?php
/**
 * @author TtwoWeb Sdn Bhd.
 * This file is contains the Database functionality for Reseller.
 * Date  19/05/2018.
 **/

class Telegram {

    function __construct() {
        // $this->db      = $db;
        // $this->general = $general;
        // $this->setting = $setting;
    }

    public function telegramBotCallback($params){

        $db = MysqliDb::getInstance();

        $chat_id = $params['chat_id'];
        $type    = $params['type'];
        $content = $params['content'];

        if($type == 'bot_command'){
            if(stripos($content, '/start ') !== false){
                $token = str_replace('/start ',"",$content);

                $db->where('token', $token);
                $checkExist = $db->getOne('oc_telegram_bot');
                $db->where('id', $checkExist['user_id']);
                        $domain = $db->getValue('client', 'domain');

                if($checkExist){
                    $db->where('token', $token);
                    $db->update('oc_telegram_bot', array('chat_id' => $chat_id, 'status' => 'active'));

                    if($checkExist['user_type'] == 'member'){
                        //get client username
                        $db->where('id', $checkExist['user_id']);
                        $client_username = $db->getValue('client', 'username');
                        
                        //trigger notification to user to indicate bot linked successfully
                        $find = array("%%username%%");
                        $replace = array($client_username['username']);
                        $referenceID = General::generateReferenceID('telegram');
                        $notifyRes = Telegram::createMemberTelegramNotification('10013', NULL, NULL, $find, $replace,"","",$chat_id); 

                        //update notification telegram bot status to active
                        $updateData = array(
                            "status" => "activate",
                            "updated_at" => date("Y-m-d H:i:s"),
                        );
                        $db->where('activity',"Telegram Bot");
                        $db->where('client_id',$checkExist['user_id']);
                        $db->update("notification", $updateData);
                    }
                    else{
                        //insert message code for admin to receive respective notfication
                        if($checkExist['chat_type'] == 'general'){
                            $db->where('module', 'One Cover Admin');
                            $messageCode = $db->getValue('message_code', 'code', null);

                            $notificationCode = '10027';
                        }
                        else if($checkExist['chat_type'] == 'alert'){
                            $db->where('module', 'One Cover Admin');
                            $messageCode = $db->getValue('message_code', 'code', null);

                            $notificationCode = '10028';
                        }

                        $db->where('recipient', $chat_id);
                        $existingRes = $db->getValue('message_assigned', 'code', null);

                        foreach($messageCode as $value){

                            if(in_array($value, $existingRes)) continue;
                            // if($value == $existingRes) continue;

                            $insertData = array(
                                'code' => $value,
                                'recipient' => $chat_id,
                                'type' => 'telegram'
                            );

                            $insertList[] = $insertData;
                        }

                        if($insertList){
                            $db->insertMulti('message_assigned', $insertList);
                        }

                        //get admin username
                        $db->where('id', $checkExist['user_id']);
                        $admin_username = $db->getValue('admin', 'username');

                        //trigger notification to user to indicate bot linked successfully
                        $find = array("%%name%%");
                        $replace = array($admin_username);
                        $referenceID = General::generateReferenceID('telegram');
                        $notifyRes = Telegram::createAdminTelegramNotification($notificationCode, NULL, NULL, $find, $replace,"","",$checkExist['chat_type'],$chat_id,$domain); 
                    }
                    

                    return array('code' => '0', 'status' => "ok", 'statusMsg' => 'Completed successfully.', 'data' => $notifyRes);  
                } 
                else{
                    return array('code' => 1, 'status' => "error", 'statusMsg' => 'Invalid token.', 'data' => '');      
                } 
            }else{
                if($chat_id ="-661242099"){
                    if(stripos($content, '/Test') !== false){
                        $notificationCode = '10027';
                        $find = array("%%name%%");
                        $replace = array("Test");
                        $referenceID = General::generateReferenceID('telegram');
                        $notifyRes = Telegram::TelegramBotSendMessageInGroup($notificationCode, NULL, NULL, $find, $replace,"","","telegram",$chat_id); 

                        
                        //Test work
                        // $telegramParams['url']     = "https://api.telegram.org/bot2107807294:AAFoGi15z1HOJDVDf68VnKuGzl47YNMT19w/sendMessage";
                        // $telegramParams['chat_id'] = "-661242099";
                        // $telegramParams['content'] = "Testing";

                        // $response = Telegram::sendTelegramNotification($telegramParams);
                    }
                }
            }
        }
        else{

            // $db->where('chat_id', $chat_id);
            // $client_id = $db->getValue('oc_telegram_bot', 'client_id');

            // $insertMessageInData = array(
            //     'content' => $content,
            //     'sender'  => ''
            // );
        }

        return array('code' => 0, 'status' => "ok", 'statusMsg' => 'Completed successfully.', 'data' => '');      
    }

    public function sendTelegramNotification($params){

        $db = MysqliDb::getInstance();

        $URL     = $params['url'];
        $chat_id = $params['chat_id'];
        $content = $params['content'];

        $data = [
            'chat_id'   => $chat_id,//'1835591156',
            'text'      => $content,//'Hello worfnkengkengknekgeld!'
        ];

        $URL .= "?".http_build_query($data);

        // ##### GET METHOD #####
        $curl=curl_init($URL);
                        
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);  // for debug
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT ,120);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120); //timeout in seconds
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, 0);

        $response = curl_exec($curl);

        if(curl_errno($curl)){
            return array('code' => 1, 'status' => "error", 'statusMsg' => '', 'curl_error_no' => curl_errno($curl), 'curl_error' => curl_error($curl));      
        }     

        /* get http status code*/
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return $response;
    }

    public function createMemberTelegramNotification($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $recipient = "")
    {
        
        $db = MysqliDb::getInstance();
        // $provider = self::provider;
        if(!$messageCode) return false;
        if(!$scheduledAt)
            $scheduledAt = date('Y-m-d H:i:s');

        $db->where('code', $messageCode);
        $msgCodeResult = $db->getOne("message_code", "title AS subject, content");

        // Get provider details for mapping purpose
        $db->where('company', 'telegram');
        $providerID = $db->getValue('provider', 'id');
        
        if ($msgCodeResult){

            $sentHistoryTable = 'sent_history_'.date('Ymd');
            
            $check = $db->tableExists($sentHistoryTable);

            if(!$check) {
                $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
            }

            $insertData['recipient'] = $recipient;
            $insertData['type'] = 'telegram';
            
            if (isset($subject) && !empty($subject))
            {
                $insertData["subject"] = $subject;
            }
            else
            {
                $insertData["subject"] = $msgCodeResult["subject"];
            }
            
            if(!empty($content) && isset($content))
            {
                $insertData["content"] = $content;
            }
            else
            {
                if (count($find) > 0 && count($replace) > 0)
                {
                    // Use find and replace to replace contents
                    $insertData["content"] = str_replace($find, $replace, $msgCodeResult["content"]);
                }
                else
                {
                    $insertData["content"] = $msgCodeResult["content"];
                }
            }

            // Map to get the provider_id
            $insertData["provider_id"] = $providerID;
            $insertData["created_at"] = date("Y-m-d H:i:s");
            $insertData['scheduled_at'] = $scheduledAt;

            $sentID = $db->insert($sentHistoryTable, $insertData);
            if(!$sentID)
                return false;
            
            // Set the priority to 1
            $insertData["priority"] = $priority;
            
            // Set the data for message_out table
            $insertData["sent_history_id"] = $sentID;
            $insertData["sent_history_table"] = $sentHistoryTable;

            $msgID = $db->insert('message_out', $insertData);
            if(!$msgID)
                return false;

            unset($insertData);
        }
        else
        {
            return false;
        }

        return true;
    }

    public function createAdminTelegramNotification($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = '', $chat_id)
    {
        $db = MysqliDb::getInstance();
        // $provider = self::provider;
        
        if(!$messageCode) return false;
        if(!$scheduledAt)
            $scheduledAt = date('Y-m-d H:i:s');

        $db->resetState();
        if($chat_id) $db->where('recipient', $chat_id);
        $db->where('code', $messageCode);
        $msgAssignedResult = $db->get("message_assigned", null, "recipient, type"); // get group id

        $db->where('code', $messageCode);
        $msgCodeResult = $db->getOne("message_code", "title AS subject, content");

        // Get provider details for mapping purpose
        if($type == 'general'){
            $db->where('company', 'telegramAdminGeneral');
            $providerID = $db->getValue('provider', 'id');
        }
        else if($type == 'alert'){
            $db->where('company', 'telegramAdminAlert');
            $providerID = $db->getValue('provider', 'id');
        }
        else if($type == 'telegramAdminGroup'){
            $db->where('company', 'GoTasty.net');
            $providerID = $db->getValue('provider', 'id');
        }
        else if($type == 'telegramAdminGroupWL'){
            $db->where('company', 'telegramAdminGroupWL');
            $providerID = $db->getValue('provider', 'id');
        }
        
        if ($msgCodeResult){
            
            $sentHistoryTable = 'sent_history_'.date('Ymd');
            
            $check = $db->tableExists($sentHistoryTable);

            if(!$check) {
                $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
            }
            foreach ($msgAssignedResult as $key => $val)
            {
                
                $insertData['recipient'] = $val['recipient'];
                $insertData['type'] = $val['type'];
                
                if (isset($subject) && !empty($subject))
                {
                    $insertData["subject"] = $subject;
                }
                else
                {
                    $insertData["subject"] = $msgCodeResult["subject"];
                }
                
                if(!empty($content) && isset($content))
                {
                    $insertData["content"] = $content;
                }
                else
                {
                    if (count($find) > 0 && count($replace) > 0)
                    {
                        // Use find and replace to replace contents
                        $insertData["content"] = str_replace($find, $replace, $msgCodeResult["content"]);
                    }
                    else
                    {
                        $insertData["content"] = $msgCodeResult["content"];
                    }
                }

                // Map to get the provider_id
                $insertData["provider_id"] = $providerID;
                $insertData["created_at"] = date("Y-m-d H:i:s");
                $insertData['scheduled_at'] = $scheduledAt;
                
                $sentID = $db->insert($sentHistoryTable, $insertData);
                if(!$sentID)
                    return false;
                
                // Set the priority to 1
                $insertData["priority"] = $priority;
                
                // Set the data for message_out table
                $insertData["sent_history_id"] = $sentID;
                $insertData["sent_history_table"] = $sentHistoryTable;
                
                $msgID = $db->insert('message_out', $insertData);
                if(!$msgID)
                    return false;

                unset($insertData);
            }
        }
        else
        {
            return false;
        }

        return true;
    }

    function activateTelegramNotification($params, $user_id) {

        $db      = MysqliDb::getInstance();

        //Language Translations.
        $language       = General::$currentLanguage;
        $translations   = General::$translations;

        $user_type = $params['user_type'] ? $params['user_type'] : "member";
        $notification_type = $params['notification_type'] ? $params['notification_type'] : "general";

        if($user_type == 'member'){
            $db->where('id', $user_id);
            $result = $db->getOne('client');
        }
        else if($user_type == 'admin'){
            $db->where('id', $user_id);
            $result = $db->getOne('admin');
        }

        if($result){

            $db->where('user_id', $user_id);
            $db->where('user_type', $user_type);
            $db->where('chat_type', $notification_type);
            $token = $db->getValue('oc_telegram_bot', 'token');

            //if not exist, will insert new bot
            if(!$token){
                $string_list = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

                while(true){
                    $token =  substr(str_shuffle($string_list), 0, 6);

                    $db->where('token', $token);
                    $checkExist = $db->getOne('oc_telegram_bot');

                    if(!$checkExist) break;
                }

                $insertData = array(
                    'user_id'       => $user_id,
                    'chat_id'       => '',
                    'token'         => $token,
                    'status'        => 'pending',
                    'user_type'     => $user_type,
                    'chat_type'     => $notification_type,
                    'created_at'    => date("Y-m-d H:i:s"),
                    'updated_at'    => date("Y-m-d H:i:s"),
                );

                $insertRes = $db->insert('oc_telegram_bot', $insertData);

                if(!$insertRes){
                    // return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00003"][$language], 'data' => ''); //Failed to activate telegram notification.
                    return array('code' => 1, 'status' => "error", 'statusMsg' => "Failed to activate telegram notification.", 'data' => ''); //Failed to activate telegram notification.
                }
            }

            if($user_type == 'member'){
                $db->where('name', 'newTelegramChatURL');
                $url = $db->getValue('provider_setting', 'value');

                $db->where('id', $user_id);
                $clientUsername = $db->getValue('client', 'username');

                // insert activity log
                $activityData = array('username' => $clientUsername);
                $activityRes  = Activity::insertActivity('Activate Telegram Notification', 'T00009', 'L00009', $activityData, $user_id, $user_id, 'Member');
            }
            else if($user_type == 'admin'){
                if($notification_type == 'general'){
                    $db->where('name', 'newAdminGeneralTelegramChatURL');
                    $url = $db->getValue('provider_setting', 'value');
                }
                else if($notification_type == 'alert'){
                    $db->where('name', 'newAdminAlertTelegramChatURL');
                    $url = $db->getValue('provider_setting', 'value');
                }

                $db->where('id', $user_id);
                $adminUsername = $db->getValue('admin', 'username');

                // insert activity log
                $activityData = array('username' => $adminUsername);
                $activityRes  = Activity::insertActivity('Activate Telegram Notification', 'T00009', 'L00009', $activityData, $user_id, $user_id, 'Admin');
            }

            $data['redirectUrl'] = $url.'?start='.$token;

            
            // Failed to insert activity
            if(!$activityRes){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity.", 'data' => "");
            }

            return array('code' => 0, 'status' => "ok", 'statusMsg' => $translations["B00001"][$language], 'data' => $data); //Completed successfully
            
        }
        else{
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00004"][$language], 'data' => ''); //Invalid user.
        }
    }

    function deactivateTelegramNotification($params, $user_id) {

        $db      = MysqliDb::getInstance();

        //Language Translations.
        $language       = General::$currentLanguage;
        $translations   = General::$translations;

        $user_type = $params['user_type'] ? $params['user_type'] : "member";
        $notification_type = $params['notification_type'] ? $params['notification_type'] : "general";

        $db->where('user_id', $user_id);
        $db->where('user_type', $user_type);
        $db->where('chat_type', $notification_type);
        $result = $db->getOne('oc_telegram_bot');

        if($result){
            
            $db->where('user_id', $user_id);
            $db->where('user_type', $user_type);
            $db->where('chat_type', $notification_type);
            $db->update('oc_telegram_bot', array('status' => 'inactive'));

            $updateData = array(
                "status" => "deactive",
                "updated_at" => date("Y-m-d H:i:s"),
            );
            $db->where('activity',"Telegram Bot");
            $db->where('client_id',$user_id);
            $db->update("notification", $updateData);

            if($user_type == "member"){
                $db->where('id', $user_id);
                $clientUsername = $db->getValue('client', 'username');
                
                // insert activity log
                $activityData = array('username' => $clientUsername);
                $activityRes  = Activity::insertActivity('Deactivate Telegram Notification', 'T00010', 'L00010', $activityData, $client_id, $client_id, 'Member');

            }
            else 
                if($user_type == "admin"){
                    $db->where('id', $user_id);
                    $adminUsername = $db->getValue('admin', 'username');
                    
                    // insert activity log
                    $activityData = array('username' => $adminUsername);
                    $activityRes  = Activity::insertActivity('Deactivate Telegram Notification', 'T00010', 'L00010', $activityData, $user_id, $user_id, 'Admin');
                    
                }

            // Failed to insert activity
            if(!$activityRes){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity.", 'data' => "");
            }
            
            return array('code' => 0, 'status' => "ok", 'statusMsg' => $translations["B00003"][$language], 'data' => ''); //Telegram notification deactivate successfully.

        }
        else{
            return array('code' => 1, 'status' => "error", 'statusMsg' => $translations["E00006"][$language], 'data' => ''); //Bot not exist.
        }
    }
    
    public function TelegramBotSendMessageInGroup($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = '', $chat_id){
        $db = MysqliDb::getInstance();
        // $provider = self::provider;
        
        if(!$messageCode) return false;
        if(!$scheduledAt)
            $scheduledAt = date('Y-m-d H:i:s');

        $db->resetState();
        if($chat_id) $db->where('recipient', $chat_id);
        $db->where('code', $messageCode);
        $msgAssignedResult = $db->get("message_assigned", null, "recipient, type");

        $db->where('code', $messageCode);
        $msgCodeResult = $db->getOne("message_code", "title AS subject, content");

        // Get provider details for mapping purpose
        if($type == 'telegram'){
            $db->where('company', 'telegramAdminGeneral');
            $providerID = $db->getValue('provider', 'id');
        }
        
        if ($msgCodeResult){

            $sentHistoryTable = 'sent_history_'.date('Ymd');
            
            $check = $db->tableExists($sentHistoryTable);

            if(!$check) {
                $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
            }

            foreach ($msgAssignedResult as $key => $val)
            {
                $insertData['recipient'] = $val['recipient'];
                $insertData['type'] = $val['type'];
                
                if (isset($subject) && !empty($subject))
                {
                    $insertData["subject"] = $subject;
                }
                else
                {
                    $insertData["subject"] = $msgCodeResult["subject"];
                }
                
                if(!empty($content) && isset($content))
                {
                    $insertData["content"] = $content;
                }
                else
                {
                    if (count($find) > 0 && count($replace) > 0)
                    {
                        // Use find and replace to replace contents
                        $insertData["content"] = str_replace($find, $replace, $msgCodeResult["content"]);
                    }
                    else
                    {
                        $insertData["content"] = $msgCodeResult["content"];
                    }
                }

                // Map to get the provider_id
                $insertData["provider_id"] = $providerID;
                $insertData["created_at"] = date("Y-m-d H:i:s");
                $insertData['scheduled_at'] = $scheduledAt;

                $sentID = $db->insert($sentHistoryTable, $insertData);
                if(!$sentID)
                    return false;
                
                // Set the priority to 1
                $insertData["priority"] = $priority;
                
                // Set the data for message_out table
                $insertData["sent_history_id"] = $sentID;
                $insertData["sent_history_table"] = $sentHistoryTable;

                $msgID = $db->insert('message_out', $insertData);
                if(!$msgID)
                    return false;

                unset($insertData);
            }
        }
        else
        {
            return false;
        }

        return true;    

    }
}
?>
