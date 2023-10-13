<?php

    class Client {
        
        function __construct() {
            
        }

        ## Telegram Notification ##
        public function sendTelegramMessage($messageCodes, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = ''){

            //DB init
            $db = MysqliDb::getInstance();

            //Makes sure message code exists
            if(!$messageCodes) return false;

            //Initialize scheduled at
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');

            //In case there is condition for multiple recipient
            if (is_array($messageCodes)){

                foreach ($messageCodes as $messageCode){
                    //Get chat ID based on message code
                    $db->where('code', $messageCode);
                    $db->where('type', 'telegram');
                    $telegramSenderDetails = $db->getOne("message_assigned","recipient, provider_id");
    
                    //Get message format based on message code
                    $db->where('code', $messageCode);
                    $msgCodeResult = $db->getOne("message_code", "title AS subject, content");
                    
                    $recipient = $telegramSenderDetails['recipient'];
                    $providerID = $telegramSenderDetails['provider_id'];
    
                    if ($recipient && $msgCodeResult && $providerID){
    
                        //Check if sent_history table of the day exists, else create
                        $sentHistoryTable = 'sent_history_'.date('Ymd');
                        $check = $db->tableExists($sentHistoryTable);
                        if(!$check) {
                            $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
                        }
    
                        
                        
                        if (isset($subject) && !empty($subject)){
                            $insertData["subject"] = $subject;
                            }
                        else{
                            $insertData["subject"] = $msgCodeResult["subject"];
                            }
                            
                        if(!empty($content) && isset($content)){
                            $insertData["content"] = $content;
                        }
                        else{
                            if (count($find) > 0 && count($replace) > 0){
                                // Use find and replace to replace contents
                                $insertData["content"] = str_replace($find, $replace, $msgCodeResult["content"]);
                                }
                            else{
                                $insertData["content"] = $msgCodeResult["content"];
                            }
                        }
    
                        // Map to get the provider_id
                        $insertData['recipient'] = $recipient;
                        $insertData['type'] = 'telegram';
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
    
                    }else{
                        return false;
                    }
                }

            }else{
                //Not multiple recipient

                //Get chat ID based on message code
                $db->where('code', $messageCodes);
                $db->where('type', 'telegram');
                $telegramSenderDetails = $db->getOne("message_assigned","recipient, provider_id");

                //Get message format based on message code
                $db->where('code', $messageCodes);
                $msgCodeResult = $db->getOne("message_code", "title AS subject, content");
                
                $recipient = $telegramSenderDetails['recipient'];
                $providerID = $telegramSenderDetails['provider_id'];

                if ($recipient && $msgCodeResult && $providerID){

                    //Check if sent_history table of the day exists, else create
                    $sentHistoryTable = 'sent_history_'.date('Ymd');
                    $check = $db->tableExists($sentHistoryTable);
                    if(!$check) {
                        $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
                    }

                    
                    
                    if (isset($subject) && !empty($subject)){
                        $insertData["subject"] = $subject;
                        }
                    else{
                        $insertData["subject"] = $msgCodeResult["subject"];
                        }
                        
                    if(!empty($content) && isset($content)){
                        $insertData["content"] = $content;
                    }
                    else{
                        if (count($find) > 0 && count($replace) > 0){
                            // Use find and replace to replace contents
                            $insertData["content"] = str_replace($find, $replace, $msgCodeResult["content"]);
                            }
                        else{
                            $insertData["content"] = $msgCodeResult["content"];
                        }
                    }

                    // Map to get the provider_id
                    $insertData['recipient'] = $recipient;
                    $insertData['type'] = 'telegram';
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

                }else{
                    return false;
                }
            }
            
            
            
        }

        function updateClientData($params,$tableName,$updatedColumn){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $db->userID;
            $updatedValue = trim($params["updatedValue"]);

            if(!$clientID){
                $clientID = trim($params["clientID"]);
            }

            if(!$clientID){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
            }

            if(!$updatedValue){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
            }

            switch ($updatedColumn) {
                case 'freezed':
                    if($updatedValue > 1){
                        return array("status" => "error", "code" => 2, "statusMsg" => "Invalid Value.", "data" => "");
                    }else{
                        //authorized - 1 
                        $updatedData[$updatedColumn] = $updatedValue == 1 ? 0 : 1;
                    }

                    break;
                
                default:
                    return array("status" => "error", "code" => 2, "statusMsg" => "Invalid column", "data" => "");
                    break;
            }

            switch ($tableName) {
                case 'client':
                    $db->where("id",$clientID);
                    break;
                
                default:
                    return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
                    break;
            }
            
            $copyDb = $db->copy();
            $dataRow = $db->getOne($tableName, "id, ".$updatedColumn);
            if(empty($dataRow)){
                return array("status" => "error", "code" => 2, "statusMsg" => $translations["E00227"][$language], "data" => "");
            }

            $copyDb->update($tableName,$updatedData);

            return array("status" => "ok", "code" => 0, "statusMsg" => "Updated successfully.", "data" => "");
        }

        public function setlowStockQuantity($params){
            $db = MysqliDb::getInstance();
            $quantity=$params['quantity'];
            $db->where('id','356');
            $db->update('system_settings',array("value"=>$quantity));
            return array("status" => "ok", "code" => 0, "statusMsg" => "Updated successfully.", "data" => "");
        }

        public function createAdminTelegramNotification($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = '', $chat_id)
        {
            $db = MysqliDb::getInstance();
            // $provider = self::provider;
            
            if(!$messageCode) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');

            $db->resetState();
            // if($chat_id) $db->where('recipient', $chat_id);
            // $db->where('code', $messageCode);
            // $msgAssignedResult = $db->get("message_assigned", null, "recipient, type"); // get group id
            // $msgAssignedResult[0]['recipient'] = '-1001926795126';
            // $msgAssignedResult[0]['type'] = 'telegram';

            $db->where('name', $chat_id);
            $db->where('type', 'telegram');
            $msgAssignedResult = $db->get("system_settings", null, "value, type"); // get group id

            

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
                    
                    $insertData['recipient'] = $val['value'];
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

        public function  createStockWarningTelegramNotification($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = '', $chat_id)
        {
            
            $db = MysqliDb::getInstance();
            if(!$messageCode) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');

            $db->resetState();
            $msgAssignedResult[0]['recipient'] = '-1001932862151';
            $msgAssignedResult[0]['type'] = 'stockWarningTelegram';

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
                $db->where('company', 'GoTastyStockNotice');
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


        public function sendWFNotification($clientID,$wfID,$action,$type)
        {

            $db = MysqliDb::getInstance();
            $client_id=$clientID;
            // $client_id='1000748';
            // $action='wishlist';
            //    $type='add';
          // $id =$wfID;
           $msgpackData = msgpack::msgpack_unpack(file_get_contents('php://input'));
           $ipaddress=$msgpackData['ip'];
            $nexline='';
           $db->where('id',$client_id);
           $clientInfo= $db->getOne('client',null,'name,username');

          if($action=='wishlist'){
            $nexline="Wishlist";

            $db->where('pw.client_id',$client_id);
            $db->where('pw.deleted',0);
            $db->orderby('pw.id','ASC');
            $db->join('product p','p.id= pw.product_id' ,'LEFT');
            $db->join('client c','c.id=pw.client_id','LEFT');
            $db->groupby('p.name');
            $productWishlist=$db->get('product_wishlist pw',null,'c.name as clientName,c.username as clientPhone,p.barcode as skuCode,p.name as productName,COUNT(pw.product_id) as quantity');          
            
            //   if(!$clientInfo)
            //   $clientInfo['name']='Public';
              $result['clientName']=$clientInfo['name'];
              $result['clientPhone']=$clientInfo['username']? $clientInfo['username']:'-';
              $result['ip']= $ipaddress;
              $result['action']=$type." ".$nexline;
              $result['wishlistProduct']="\n";
              $result['time']=date('Y-m-d H:i:s');
              
              
    
              foreach($productWishlist as  $value){
                
                $result['wishlistProduct'].= $value['productName']." X ".$value['quantity']."\n". "SKUCode: ".$value['skuCode']."\n\n";
               
              }
          
    
              $find = array("%%name%%","%%phoneNum%%","%%ip%%","%%userAction%%", "%%productList%%","%%datetime%%");
              $replace = array($result['clientName'],
                                $result['clientPhone'],
                                $result['ip'],
                                $result['action'],
                                $result['wishlistProduct'],
                                $result['time']
                            );
          }
          else if($action=='favourite'){
            $nexline="Favourite List";


            $db->where('pf.deleted',0);
            $db->where('pf.client_id',$client_id);
            $db->orderBy('pf.id','ASC');
            $db->join('product p', 'p.id= pf.product_id ', 'LEFT');
            // $db->join('product_media pm', 'pm.reference_id = pf.product_id', 'LEFT');
           $getProductDetails = $db->get('product_favorite pf', null, 'p.name as productName, p.barcode as skuCode');          
          // return array("code" => 0, "status" => "ok", "statusMsg" => $db->getlastquery(), 'data' =>$getProductDetails);

            //   if(!$clientInfo)
            //   $clientInfo['name']='Public';
              $result['clientName']=$clientInfo['name'];
              $result['clientPhone']=$clientInfo['username']? $clientInfo['username']:'-';
              $result['ip']= $ipaddress;
              $result['action']=$type." ".$nexline;
              $result['favouriteProduct']="\n";
              $result['time']=date('Y-m-d H:i:s');
              
    
    
              foreach($getProductDetails as  $value){
                
                $result['favouriteProduct'].="Product Name :". $value['productName']."\n". "SKUCode: ".$value['skuCode']."\n\n";
              }
    
            
              $find = array("%%name%%","%%phoneNum%%","%%ip%%","%%userAction%%", "%%productList%%","%%datetime%%");
              $replace = array($result['clientName'],
                                $result['clientPhone'],
                                $result['ip'],
                                $result['action'],
                                $result['favouriteProduct'],
                                $result['time']
                            );
          
             }


          $outputArray = Client::sendTelegramMessage('10018',NULL,NULL,$find,$replace,"","","GoTastyWishListFavouriteList");
          return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' =>$productWishlist);

        }        

        public function createWFTelegramNotification($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = '', $chat_id)
        {
            $db = MysqliDb::getInstance();
            // $provider = self::provider;
            if(!$messageCode) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');

            $db->resetState();

            $db->where('type','telegram');
            $db->where('name','telegramWishFavourite');
            $recipient = $db->getValue("system_settings","value");
            
            $db->where('code', $messageCode);
            $msgCodeResult = $db->getOne("message_code", "title AS subject, content");

            // Get provider details for mapping purpose
            $db->where('company', 'GoTastyWishListFavouriteList');
            $providerID = $db->getValue('provider', 'id');
            
            if ($msgCodeResult && $recipient && $providerID){
                
                $sentHistoryTable = 'sent_history_'.date('Ymd');
                
                $check = $db->tableExists($sentHistoryTable);

                if(!$check) {
                    $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
                }
                    
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
                $insertData['recipient'] = $recipient;
                $insertData['type'] = 'wfNotification';
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

        public function createSaleOrderTelegramNotification($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = '', $chat_id)
        {
            $db = MysqliDb::getInstance();
            // $provider = self::provider;
            if(!$messageCode) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');


            $db->resetState();
            $db->where('name','telegramSalesOrder');
            $db->where('type','telegram');
            $recipient=$db->getValue("system_settings","value");   

            $db->where('code', $messageCode);
            $msgCodeResult = $db->getOne("message_code", "title AS subject, content");

            // Get provider details for mapping purpose
            $db->where('company', 'GoTastySalesOrder');
            $providerID = $db->getValue('provider', 'id');
            
            
            if ($msgCodeResult){
                
                $sentHistoryTable = 'sent_history_'.date('Ymd');
                
                $check = $db->tableExists($sentHistoryTable);

                if(!$check) {
                    $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
                }
                    
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
                $insertData['recipient'] = $recipient;
                $insertData['type'] = 'saleOrderTelegramNotification';
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

   
        public function sendSalesOrderNotification($clientID,$saleStatus,$action)
        {

            $db = MysqliDb::getInstance();
            $client_id=$clientID;
            $saleId =$saleStatus;
            //$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $msgpackData = msgpack::msgpack_unpack(file_get_contents('php://input'));
            //  $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ipaddress=$msgpackData['ip'];

            $nexline='';
            //   $saleId= '530';
            $db->where('id',$client_id);
            $clientInfo= $db->getOne('client',null,'name,username');




          if($action=='add'||$action=='inc'){
            $nexline="Add to cart";
          }
          else if($action=='update'){
            $nexline="Update";
          }
          else if($action=='delete'||$action=='dec'){
            $nexline="Remove";
          }
    

          $db->where('id',$saleId);
          $saleName=  $db->getOne('sale_order',null,'so_no,created_at');
          $db->where('sale_id',$saleId);
          $db->where('deleted',0);
          $itemforSo=$db->get('sale_order_detail',null,'item_name,quantity');
          

          if(!$clientInfo)
          $clientInfo['name']='Public';
          $result['clientName']=$clientInfo['name'];
          $result['clientPhone']=$clientInfo['username']? $clientInfo['username']:'-';
          $result['ip']= $ipaddress;
          $result['action']=$nexline;
          $result['salesName']=$saleName['so_no']."\n Product List :\n";
          $result['time']=$saleName['created_at'];
          
          


          foreach($itemforSo as  $value){
            
            $result['salesName'].= $value['item_name']." X ".$value['quantity']."\n";
            
          }

          $find = array("%%name%%","%%phoneNum%%","%%ip%%","%%userAction%%", "%%soName%%","%%datetime%%");
          $replace = array($result['clientName'],
                            $result['clientPhone'],
                            $result['ip'],
                            $result['action'],
                            $result['salesName'],
                            $result['time']
                        );
          $outputArray = Client::sendTelegramMessage('10016',NULL,NULL,$find,$replace,"","","GoTastySalesOrder");
          return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' =>$result);

        }
     
        public function sendTelegramNotification($content){
            global $config;
            $db = MysqliDb::getInstance();
          
            // retrieve bot api
            $db->where('company', $config['companyName']);
            $db->where('name','telegram');
            $db->where('type', 'notification');
            $URL1 = $db->getOne('provider',null,'url1');
           
            $URL = $URL1['url1'] . '/sendMessage';

            $chat_id = $config['telegramGroup'];
            //return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' =>$chat_id);
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
        }

        function getCreditDisplay(){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $creditList=$db->get('credit',null,'name,translation_code');
            foreach ($creditList as $key => $value) {
                $creditListReturn[$value['name']]=$translations[$value['translation_code']][$language];
            }
            return $creditListReturn;
        }

        function create_passlib_pbkdf2($algo, $password, $salt, $iterations){
          $hash = hash_pbkdf2($algo, $password, base64_decode(str_replace(".", "+", $salt)), $iterations, 64, true);
          return sprintf("\$pbkdf2-%s\$%d\$%s\$%s", $algo, $iterations, $salt, str_replace("+", ".", rtrim(base64_encode($hash), '=')));
        }



        function verify_passlib_pbkdf2($password, $passlib_hash){
            if (empty($password) || empty($passlib_hash)) return false;

            $parts = explode('$', $passlib_hash);
            if (!array_key_exists(4, $parts)) return false;
            $t = explode('-', $parts[1]);
            if (!array_key_exists(1, $t)) return false;

            $algo = $t[1];
            $iterations = (int) $parts[2];
            $salt = $parts[3];
            $orghash = $parts[4];

            $hash = Self::create_passlib_pbkdf2($algo, $password, $salt, $iterations);
            return $passlib_hash === $hash;
        }

        
        public function getPortfolioDetail($portfolioId) {
            $db = MysqliDb::getInstance();

            $db->where("id", $portfolioId);
            $portfolioDetail = $db->getOne("mlm_client_portfolio", "product_id, product_price, bonus_value, portfolio_type, belong_id, batch_id");

            return $portfolioDetail;
        }

        public function getDownLineList($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $searchData     = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $seeAll = $params['seeAll'] ? $params['seeAll'] : 0;


            $userID = $db->userID;
            $site = $db->userType;

            if($site == 'Member'){
                $clientID = $userID;
            }else{
                $clientID = trim($params['clientID']);
            }
           
            $db->where('id', $clientID);
            $member = $db->getOne("client", 'dial_code, phone');
            $phoneNumber = $member['dial_code'] . $member['phone'];
         
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) 
                {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch ($dataName) 
                    {
                        case 'name':
                            $db->where('name', "%" . $dataValue . "%", 'LIKE');
                        break;

                        case 'phone':
                            $db->where('username',"%" . $dataValue . "%", 'LIKE');
                        break;

                        case 'email':
                            $db->where('email',"%" . $dataValue . "%", 'LIKE');
                        break;
                    }
                    unset($dateFrom);
                    unset($dateTo);
                    unset($dataName);
                    unset($dataValue);    
                }
            }
            $db->where('sponsor_id', $phoneNumber);
            $copydb =$db->copy();
            $getDownLineClient = $db->get('client',$limit,'id,name,username,email');
            
            $totalRecord = $copydb->getValue('client', 'count(name)');
            $data['pageNumber'] = $pageNumber;
               
            if($seeAll == "1") 
            {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
             } 
             else 
             {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
             }
             $data['totalRecord'] = $totalRecord;

            if (!empty($getDownLineClient)) 
            {
            foreach ($getDownLineClient as $key => $value)
            {
                $db->where('client_id', $clientID);
                $db->where('from_id', $value['id']);
                $db->where('paid', 1);
                $getDownLineStatus = $db->getValue('mlm_bonus_direct_sponsor','paid');

                if($getDownLineStatus)
                {
                    $downlineStatus = 1;
                }else
                {
                    $downlineStatus = 0;
                }

                $data['downLineList'][$key]['name'] = $value['name'];
                $data['downLineList'][$key]['phone'] = $value['username'];
                $data['downLineList'][$key]['email'] = $value['email']?  $value['email'] : "-";
                $data['downLineList'][$key]['isEligible'] = $downlineStatus;
            }
            if($params['type'] =="export")
            {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $finalResult['downLineList']=$data['downLineList'];
            $finalResult['totalPage']=$data['totalPage'];
            $finalResult['pageNumber']=$data['pageNumber'];
            $finalResult['totalRecord']=$data['totalRecord'];
            $finalResult{'numRecord'}=$data['numRecord'];      

            return array("code" => 0, "status" => "ok", "statusMsg" => "", 'data' => $finalResult);
        } 
            return array("status"=> "ok", 'code' => 0, 'statusMsg' => "No Result", 'data' => $finalResult);
        }


        public function getCustomerReviewDetailAdmin($params)
        {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];
            $reviewID       =$params['reviewID'];
            $db->where('pr.deleted',0);
            $db->where('pr.id',$reviewID);
            $db->join('client c','c.id=pr.client_id','LEFT');
            $db->join('product p','p.id=pr.product_id','LEFT');

            $result = $db->get('product_review pr',null,'p.name as productName,
                                pr.msg as customerReview,pr.created_by as createdBy, pr.rating as rating,
                                pr.created_ts as createdDate, pr.is_anonymous as is_anonymousStatus,
                                pr.status as status,pr.approved_by as approveBy,
                                pr.approved_ts as approvedDated');
          //  return array('status' => "ok", 'code' => 0, 'statusMsg' => $db->getlastquery(), 'data' => $result);
            if (empty($result))
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => '', 'data' => "");
            }
            else
            {
                foreach($result as $row)
                {
                    $reviewDetail['productName']        = $row['productName'];
                    $reviewDetail['createdBy']          = $row['createdBy'];
                    $reviewDetail['createdDate']        = $row['createdDate'];
                    $reviewDetail['status']             = $row['status'];
                    $reviewDetail['approveBy']          = $row['approveBy'];
                    $reviewDetail['is_anonymousStatus'] = $row['is_anonymousStatus'];
                    $reviewDetail['approvedDated']      = $row['approvedDated'];
                    $reviewDetail['customerReview']     = $row['customerReview'];
                    $reviewDetail['rating']             = $row['rating'];
                    $reviewList[] = $reviewDetail;
                }
                
                return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' => $reviewList);
            }

        }
        public function getCustomerReviewAdmin($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $seeAll = $params['seeAll'] ? $params['seeAll'] : 0;

           
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) 
                {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch ($dataName) {

                        case 'productName':
                            $db->where('p.name', "%" . $dataValue . "%", 'LIKE');
                        break;

                        case 'status':
                            $db->where('pr.status', "%" . $dataValue . "%", 'LIKE');
                        break;

                        case 'customerName':
                            $db->where('c.name',"%" . $dataValue . "%", 'LIKE');
                        break;

                        case 'rating':
                            $db->where('pr.rating',  $dataValue);
                        break;

                        
                    }
                    // unset($dateFrom);
                    // unset($dateTo);
                    unset($dataName);
                    unset($dataValue);
                }
            }
            $db->where('pr.deleted',0);
            $db->join('client c','c.id=pr.client_id','LEFT');
            $db->join('product p','p.id=pr.product_id','LEFT');
            $copydb= $db->copy();
            $result = $db->get('product_review pr',$limit,'pr.id as reviewID,p.name as productName
                                ,c.name as customerName,pr.rating,pr.created_ts as createdDate,
                                pr.status as status');
            $totalRecord = $copydb->getValue('product_review pr', 'count(pr.id)');
            $data['pageNumber'] = $pageNumber;
                    
            if($seeAll == "1") 
            {
               $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
             } 
             else 
             {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
             }
             $data['totalRecord'] = $totalRecord;

             
            if (!empty($result)) 
            {
                foreach($result as $value)
                {
                    $review['reviewID']                  =$value['reviewID'];
                    $review['productName']              =$value['productName'];
                    $review['created_by']              = $value['customerName'] ? $value['customerName'] : "-";
                    $review['customerName']          = $value['customerName']? $value['customerName'] : "-";;    
                    $review['createdDate']              = $value['createdDate'] ? $value['createdDate'] : "-";            
                    $review['status']                   =$value['status']? $value['status'] : "-";
                    $review['rating']                =$value['rating']? $value['rating'] :"-";
                    $reviewList[] = $review;
                }

                $finalResult['reviewList']=$reviewList;
                $finalResult['totalPage']=$data['totalPage'];
                $finalResult['pageNumber']=$data['pageNumber'];
                $finalResult['totalRecord']=$data['totalRecord'];
                $finalResult{'numRecord'}=$data['numRecord'];
                return array("status"=> "ok", 'code' => 0, 'statusMsg' => "", 'data' => $finalResult);
            
            }
            return array("status"=> "ok", 'code' => 0, 'statusMsg' => "No Result", 'data' => '');
        }

        public function updateCustomerReview($params,$username){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];
            $reviewID       =$params['reviewID'];
            $action         =$params['action'];
            $db->where('id',$reviewID);
            



            if($action=='approve')
            {
                $fields = array('is_approve','status','approved_by', 'approved_ts','deleted','updated_ts');
                $values = array(1, 'Approved',$username,date("Y-m-d H:i:s"),0,null);
                $result=$db->update('product_review', array_combine($fields, $values));
                return array("status"=> "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
     
            }

            else if($action=='reject')
            {
                $fields = array('status','is_approve', 'approved_ts');
                $values = array('Rejected',0,null);
                $result= $db->update('product_review', array_combine($fields, $values));
                return array("status"=> "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
    
            }
                return array("status"=> "error", 'code' => 1, 'statusMsg' => "Error Occured", 'data' => "");
        }

        public function getWishListAdmin($params){
      
         
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $sortData       = $params['sortData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

           $limit          = General::getLimit($pageNumber);
           $seeAll = $params['seeAll'] ? $params['seeAll'] : 0;
           $layer          = $params['layer']; 
           $productId        = $params['productId'];
  
           if (!$layer) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Product Layer", 'data' => "");

           if ($layer == 1) {
               
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch ($dataName) {

                        case 'productName':
                            $db->where('p.name', "%" . $dataValue . "%", 'LIKE');
                        break;

                        case 'barcode':
                            $db->where('p.barcode', "%" . $dataValue . "%", 'LIKE');
                        break;
                    }

                    unset($dataName);
                    unset($dataValue);
                }
            }

            $sortOrder = "ASC";
            $sortField = 'p.name';

            // Means the sort params is there
            if (!empty($sortData)) { 
                if($sortData['field']) {
                    $sortField = $sortData['field'];
                }
                        
                if($sortData['order'] == 'DESC')
                    $sortOrder = 'DESC';
            }

            // Sorting while switch case matched
            if ($sortField && $sortOrder) {
                $data['sortBy'] = array('field' => $sortField, 'order' => $sortOrder);
                $db->orderBy($sortField, $sortOrder);
            }

            $rule = array(
                array('col' => 'pw.deleted', 'val' => '0')
            );

            foreach($rule as $v){
                $db->where($v['col'], $v['val']);
            }
            
            // $db->where('pw.deleted',0);
            // $db->orderby('pw.id','ASC');
            $db->join('product p','p.id= pw.product_id' ,'LEFT');
            $copydb=$db->copy();
            $db->groupby('p.name');
            $copydbForExcel=$db->copy();
            $result=$db->get('product_wishlist pw',$limit,'p.id,p.barcode,p.name,COUNT(pw.product_id)');
            $totalRecord = $copydb->getValue('product_wishlist pw', 'count(pw.id)');
            $data['pageNumber'] = $pageNumber;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } 
            else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['totalRecord'] = $totalRecord;

            $productInvExcel = $copydbForExcel->get('product_wishlist pw',null,'p.barcode as barcode,p.name as name,COUNT(pw.product_id) as quantity');
            foreach($productInvExcel as $excelDetail)
            {
                $newExcelDetail['barcode'] = $excelDetail['barcode'];
                $newExcelDetail['name'] = $excelDetail['name'];
                $newExcelDetail['quantity'] = $excelDetail['quantity'];
                $excelListing[] = $newExcelDetail;
            }
            $data['productInvExcel'] = $excelListing;
            if($params['type'] == "export")
            {
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
           
            }
        }

        else if($layer == 2)
        {
            if (!$productId) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Product ID", 'data' => $productId);

            
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'customerName':
                            $db->where('c.name', "%" . $dataValue . "%", 'LIKE');
                            break;

                        case 'mobileNumber':
                            $db->where('c.username', "%" . $dataValue . "%", 'LIKE');
                            break;

            
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $sortOrder = "DESC";
            $sortField = 'pw.created_ts';

            // Means the sort params is there
            if (!empty($sortData)) { 
                if($sortData['field']) {
                    $sortField = $sortData['field'];
                }
                        
                if($sortData['order'] == 'ASC')
                    $sortOrder = 'ASC';
            }

            // Sorting while switch case matched
            if ($sortField && $sortOrder) {
                $data['sortBy'] = array('field' => $sortField, 'order' => $sortOrder);
                $db->orderBy($sortField, $sortOrder);
            }

            // $rule = array(
            //     array('col' => 'pw.deleted', 'val' => '0'),
            //     array('col' => 'pw.product_id', 'val' => $product_id)
            // );

            // foreach($rule as $v){
            //     $db->where($v['col'], $v['val']);
            // }

            $db->where('pw.deleted',0);
            // $db->orderby('pw.id','ASC');
            $db->where('pw.product_id',$productId);
            $db->join('product p','p.id=pw.product_id');
            $db->join('client c','c.id= pw.client_id' ,'LEFT');
            $copydb=$db->copy();
            $copyCustomerdbForExcel=$db->copy();
            $result=$db->get('product_wishlist pw',$limit,'p.name as productName,c.name as customerName,c.username as mobileNumber,pw.created_ts as Date');
            $totalRecord = $copydb->getValue('product_wishlist pw', 'count(pw.id)');
            $data['pageNumber'] = $pageNumber;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } 
            else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['totalRecord'] = $totalRecord;

            $productInvExcel = $copyCustomerdbForExcel->get('product_wishlist pw',null,'p.name as productName,c.name as customerName,c.username as mobileNumber,pw.created_ts as Date');
            foreach($productInvExcel as $excelDetail)
            {
                $newExcelDetail['productName'] = $excelDetail['productName'];
                $newExcelDetail['customerName'] = $excelDetail['customerName'];
                $newExcelDetail['mobileNumber'] = $excelDetail['mobileNumber'];
                $newExcelDetail['Date'] = $excelDetail['Date'];
                $excelListing[] = $newExcelDetail;
            }
            $data['productInvExcel'] = $excelListing;
            if($params['type'] == "export")
            {

                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
           
            }



        }

        if (!empty($result))
        { 
            foreach($result as $value) {
                $product['id']                = $value['id'];
                $product['name']              = $value['name'] ? $value['name'] : "-";
                $product['barcode']           = $value['barcode'] ? $value['barcode'] : "-";
                $product['quantity']          = $value['COUNT(pw.product_id)'] ? $value['COUNT(pw.product_id)'] : "0";
                $product['customerName']      = $value['customerName']? $value['customerName']  : "-";
                $product['phone']             = $value['mobileNumber']? $value['mobileNumber']: "-";
                $product['productName']       = $value['productName']? $value['productName']: "-";
                $product['Date']              = $value['Date']? $value['Date']: "-";
                $productList[] = $product;
            }
            $finalResult['wishList']=$productList;
            $finalResult['totalPage']=$data['totalPage'];
            $finalResult['pageNumber']=$data['pageNumber'];
            $finalResult['totalRecord']=$data['totalRecord'];
            $finalResult{'numRecord'}=$data['numRecord'];
            $finalResult{'sortBy'}=$data['sortBy'];
           
            return array("status"=> "ok", 'code' => 0, 'statusMsg' => "", 'data' => $finalResult);
        }
        else {
            $data['$productList']      = '';
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Record Found", 'data' => $data);
        }
       
     
   
        }

        public function addGTBanner($params){
            
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $db->where('type','Upload Setting');
            $uploadSetting = $db->map('name')->get('system_settings',null,'name,value,reference');

            ##### variable #####
            $campaignName = $params['campaignName'];
            $fromDate = $params['fromDate'];
            $toDate = $params['toDate'];
            $priority = $params['priority'];
            $bannerPage = $params['bannerPage'];
            $uploadData = $params['uploadData'];
            $from_date = strtotime($fromDate);
            $to_date = strtotime($toDate);


            if(!$campaignName) {
                $errorFieldArr[] = array(
                    'id'  => 'nameError',
                    'msg' => 'Please enter campaign name' 
                );
            }

            if (!$fromDate || $fromDate <= 0 || !$toDate || $toDate <= 0) {
                $errorFieldArr[] = array(
                    'id'  => 'dateError',
                    'msg' => 'Please enter schedule date'
                );
            }

            if($from_date > $to_date){
                $errorFieldArr[] = array(
                    'id'  => 'dateError',
                    'msg' => 'Start date should not be later than end date' 
                );
            }

            if(!$priority) {
                $errorFieldArr[] = array(
                    'id'  => 'priorityError',
                    'msg' => 'Please enter priority' 
                );
            }

            if(!$bannerPage) {
                $errorFieldArr[] = array(
                    'id'  => 'pageError',
                    'msg' => 'Please select banner page' 
                );
            }

            if(!$uploadData){
                $errorFieldArr[] = array(
                    'id'  => 'imgError',
                    'msg' => "Please upload image and select language"
                );
            }

            foreach ($uploadData as $lang => $imageData) {
                $validImageSet  = $uploadSetting['validImageType'];
                $validImageType = explode("#", $validImageSet['value']);
                $validImageSize = $validImageSet['reference'];
                $sizeMB         = $validImageSize / 1024 / 1024;

                if(!$imageData['imgData'] || !$imageData['imgName']){
                    $errorFieldArr[] = array(
                        'id'  => 'imgError',
                        'msg' => "Please upload image" 
                    );
                }

                if($imageData["imgFlag"]) {
                    if(!in_array($imageData["imgType"], $validImageType)) {
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                        );
                    }

                    if(!$imageData['imgSize'] || $imageData['imgSize'] > $validImageSize){
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                        );
                    }
                }

                if(!$imageData['languageType']){
                    $errorFieldArr[] = array(
                        'id'  => "imgLanguageError",
                        'msg' => $translations["E00602"][$language] /* Please Select Language. */
                    );
                }
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01098"][$language] /* Required fields cannot be left blank. */, 'data' => $data);
            }
            ############### Finish Checking ###############

            $insertBanner = array(
                "name" => $campaignName,
                "priority" => $priority,
                "banner_page" => $bannerPage,
                "from_date" => date("Y-m-d", $from_date),
                "to_date" => date("Y-m-d", $to_date),
                "updated_at" => date("Y-m-d H:i:s"),
                "created_at"   => date("Y-m-d H:i:s")
            );
            $banner = $db->insert('gt_banner', $insertBanner);

            foreach($uploadData as $key => $val) {
                $imgSrc = $val['imgData'];
                $uploadParams['imgSrc'] = $imgSrc;
                $uploadRes = aws::awsUploadImage($uploadParams);
                $imgName = $val['imgName'];

                if($uploadRes['status'] == 'ok') {
                    $imgUrl = $uploadRes['imageUrl'];

                    $insertBannerImage = array(
                        "banner_id" => $banner,
                        "img_name" => $imgName,
                        "url" => $imgUrl,
                        "language" => $val['languageType'],
                        "created_at" => date("Y-m-d H:i:s"),
                        "updated_at" => date("Y-m-d H:i:s")
                    );
                    $bannerImage = $db->insert('gt_banner_image', $insertBannerImage);

                } else {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                }
                unset($key); unset($value);
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Banner added successfully', 'data' => ''); 
        }

        public function getGTBanner($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateFormat = Setting::$systemSetting['systemDateFormat'];
            $today = date('Y-m-d');

            $bannerPage = $params["bannerPage"];

            if(!$bannerPage){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Banner page not found", 'data' => "");
            }

            $db->where('banner_page', $bannerPage);
            $db->where('deleted', '0');
            $db->where('to_date', $today, ">=");
            $db->where('from_date', $today, "<=");
            $db->orderBy("priority","Desc");
            $banner = $db->get('gt_banner', null, 'id, priority,from_date,to_date');

            if(!$banner){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Banner not found", 'data' => "");
            }

            $id = array_column($banner, 'id');
            $db->where('banner_id', $id, 'IN');
            $db->where('language', $language);
            $db->where('deleted', '0');
            $bannerImage = $db->map('banner_id')->get('gt_banner_image', null, 'banner_id, url');  

            if(!$bannerImage){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Banner image not found", 'data' => "");
            }

            foreach($banner as $value){
                if($bannerImage[$value['id']]){
                    $imgUrl[] =    $bannerImage[$value['id']];
                } 
            }
            
            $data['imgUrl'] = $imgUrl;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getGTBannerData($params){

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateFormat = Setting::$systemSetting['systemDateFormat'];

            $id = $params["id"];

            if(!$id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid ID", 'data' => "");
            }

            $db->where('id', $id);
            $bannerData = $db->getOne('gt_banner', 'id, name, priority, banner_page, from_date, to_date');

            if(!$bannerData){
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid banner id', 'data' => '');
            }

            $bannerData['from_date'] = date("Y-m-d", strtotime($bannerData['from_date']));
            $bannerData['to_date'] = date("Y-m-d", strtotime($bannerData['to_date']));

            //check if valid
            $db->where('disabled', 0);
            $systemLanguages = $db->get('languages', NULL, 'language, language_code');

            $db->where('banner_id' , $id);
            $db->where('deleted', '0');
            $bannerImgData = $db->get('gt_banner_image', null, 'id, img_name, url, language');

            if(!empty($bannerData) && !empty($bannerImgData)) {

                $data['bannerData'] = $bannerData;
                $data['imgData'] = $bannerImgData;
                $data['systemLanguages'] = $systemLanguages;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00655"][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function getGTBannerList($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);
            $searchData = $params['searchData'];
            $sortData = $params['sortData'];

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'campaignName':
                            $db->where('name', "%".$dataValue."%", "LIKE");
                            $res = $db->getValue("gt_banner", "id", null);
                            if(!empty($res)){
                                $rule[] = array('col' => 'id', 'val' => $res, 'operator' => 'IN');
                            }else{
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00002"][$language], 'data'=>""); // No record found.                   
                            }
                            break;

                        case 'priority':
                            $rule[] = array('col' => 'priority', 'val' => $dataValue, 'operator' => '=');
                            break;

                        case 'bannerPage':
                            $db->where('banner_page', "%".$dataValue."%", "LIKE");
                            $res = $db->getValue("gt_banner", "id", null);
                            if(!empty($res)){
                                $rule[] = array('col' => 'id', 'val' => $res, 'operator' => 'IN');
                            }else{
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00002"][$language], 'data'=>""); // No record found.                   
                            }
                            break;

                        case 'scheduleDate':
                            if($v['tsFrom']!== "" && $v['tsTo']!== ""){
                                $rule[] = array('col' => 'date(from_date)', 'val' => date("Y-m-d",$v['tsFrom']), 'operator' => '>=');
                                $rule[] = array('col' => 'date(to_date)', 'val' => date("Y-m-d",$v['tsTo']), 'operator' => '<=');
                            }else{
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A0009"][$language], 'data'=>''); // Please enter date range.
                            }
                            
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $sortOrder = "DESC";
            $sortField = 'id';

            // Means the sort params is there
            if (!empty($sortData)) { 
                if($sortData['field']) {
                    $sortField = $sortData['field'];
                }
                        
                if($sortData['order'] == 'ASC')
                    $sortOrder = 'ASC';
            }

            // Sorting while switch case matched
            if ($sortField && $sortOrder) {
                $data['sortBy'] = array('field' => $sortField, 'order' => $sortOrder);
                $db->orderBy($sortField, $sortOrder);
            }

            foreach ($rule as $key => $val) {
                $db->where($val["col"], $val["val"], $val["operator"]);
            }

            $db->where('deleted', '0');
            $copyDb = $db->copy();
            $result = $db->get('gt_banner', $limit, 'id, name, priority, banner_page, from_date, to_date');

            if(!empty($result)){
                foreach($result as $value) {
                
                    $record['id'] = $value['id'];
                    $record['fromDate'] = $value['from_date'];
                    $record['toDate'] = $value['to_date'];
                    $record['name'] = $value['name'];
                    $record['priority'] = $value['priority'];
                    $record['banner_page'] = $value['banner_page'];
                    $recordList[] = $record;
                }

                $totalRecords = $copyDb->getValue('gt_banner', 'count(id)');
                $data['record'] = $recordList;
                $data['totalPage'] = ceil($totalRecords/$limit[1]);
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecords;
                $data['numRecord'] = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }else{
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00655"][$language] /* No Results Found. */, 'data' => $data);
            }
        }

        public function editGTBanner($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTime = date("Y-m-d H:i:s");

            $db->where('type','Upload Setting');
            $uploadSetting = $db->map('name')->get('system_settings',null,'name,value,reference');

            ##### variable #####
            $id = $params["id"];
            $campaignName = $params['campaignName'];
            $fromDate = $params['fromDate'];
            $toDate = $params['toDate'];
            $priority = $params['priority'];
            $bannerPage = $params['bannerPage'];
            $imgUrl = $params['imgUrl'];
            $uploadData = $params['uploadData'];
            $from_date = strtotime($fromDate);
            $to_date = strtotime($toDate);
            $deleted = $params['deleted'];

            if(!$campaignName) {
                $errorFieldArr[] = array(
                    'id'  => 'nameError',
                    'msg' => 'Please enter campaign name'
                );
            }

            if (!$fromDate || $fromDate <= 0 || !$toDate || $toDate <= 0) {
                $errorFieldArr[] = array(
                    'id'  => 'dateError',
                    'msg' => 'Please enter schedule date'
                );
            }

            if($from_date > $to_date){
                $errorFieldArr[] = array(
                    'id'  => 'dateError',
                    'msg' => 'Start date should not be later than end date' 
                );
            }

            if(!$priority) {
                $errorFieldArr[] = array(
                    'id'  => 'priorityError',
                    'msg' => 'Please enter priority'
                );
            }

            if(!$bannerPage) {
                $errorFieldArr[] = array(
                    'id'  => 'pageError',
                    'msg' => 'Please select banner page'
                );
            }

            foreach ($uploadData as $lang => $imageData) {
                if($imageData['imgData']){
                    $validImageSet  = $uploadSetting['validImageType'];
                    $validImageType = explode("#", $validImageSet['value']);
                    $validImageSize = $validImageSet['reference'];
                    $sizeMB         = $validImageSize / 1024 / 1024;

                    if($imageData["imgFlag"]) {
                        if(!in_array($imageData["imgType"], $validImageType)) {
                            $errorFieldArr[] = array(
                                'id'  => "imgTypeError",
                                'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                            );
                        }

                        if(!$imageData['imgSize'] || $imageData['imgSize'] > $validImageSize){
                            $errorFieldArr[] = array(
                                'id'  => "imgTypeError",
                                'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                            );
                        }
                    }

                    if(!$imageData['languageType']){
                        $errorFieldArr[] = array(
                            'id'  => "imgLanguageError",
                            'msg' => 'Please select a language'
                        );
                    }
                }
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01098"][$language] /* Required fields cannot be left blank. */, 'data' => $data);
            }
            ############### Finish Checking ###############

            $updateBanner = array(
                "name" => $campaignName,
                "priority" => $priority,
                "banner_page" => $bannerPage,
                "from_date" => date("Y-m-d", $from_date),
                "to_date" => date("Y-m-d", $to_date),
                "updated_at" => date("Y-m-d H:i:s")
            );
            $db->where('id', $id);
            $banner = $db->update('gt_banner', $updateBanner);

            if($deleted){
                $deletedImg = array("deleted" => '1');
                $db->where('id',$deleted);
                $db->update('gt_banner_image', $deletedImg);
            }

            foreach($uploadData as $key => $val) {
                $imgSrc = $val['imgData'];
                $uploadParams['imgSrc'] = $imgSrc;
                $uploadRes = aws::awsUploadImage($uploadParams);
                $imgName = $val['imgName'];

                if($uploadRes['status'] == 'ok') {
                    $imgUrl = $uploadRes['imageUrl'];
                    $updateBannerImage = array(
                        "banner_id" => $id,
                        "img_name" => $imgName,
                        "language" => $val['languageType'],
                        "updated_at" => date("Y-m-d H:i:s")
                    );
                    
                    if (!empty($imgSrc)) {
                        $updateBannerImage["url"] = $imgUrl;
                    }

                    $db->where('banner_id', $id);
                    $db->where('language', $val['languageType']);
                    $db->where('deleted', 0);
                    $languages = $db->getValue('gt_banner_image', 'id', null);

                    if($languages){
                        $db->where('banner_id', $id);
                        $db->where('deleted', 0);
                        $db->where('language', $val['languageType']);
                        $bannerImage = $db->update('gt_banner_image', $updateBannerImage);
                    }else{
                        $bannerImage = $db->insert('gt_banner_image', $updateBannerImage);
                    }
                    
                } else {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                }
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Banner added successfully', 'data' => $bannerImage); 
        }

        public function deleteGTBanner($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $id = $params["id"];

            if(empty($id)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01098"][$language] /* Required fields cannot be left blank. */, 'data' => "");
            }

            $deleteBanner = array('deleted'=> "1");
            $db->where('id', $id);
            $db->update('gt_banner', $deleteBanner);

            $db->where('banner_id', $id);
            $deleteBannerImage = array('deleted' => "1");
            $db->update('gt_banner_image', $deleteBannerImage);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A01591"][$language] /* Successfully Deleted Banner. */, 'data' => "");
        }

        public function memberLogin($msgpackData) {
            $db = MysqliDb::getInstance();

            $language = General::$currentLanguage;
            $translations = General::$translations;
            $passwordEncryption = Setting::getMemberPasswordEncryption();
            $autoLoginExpiryDay    = Setting::$systemSetting["autoLoginExpiryDay"];
            $defTimeOut = Setting::$systemSetting["memberTimeout"];

            $params = $msgpackData['params'];
            $ip = $msgpackData['ip'];

            $id = trim($params['id']);
            $username = trim($params['username']);
            $password = trim($params['password']);
            $loginFromID = trim($params['login_user_id']);
            // $params['loginBy'] = 'email';
            $isAutoLogin = trim($params['isAutoLogin']);
            $marcaje = trim($params['marcaje']);
            $marcajeTK = $params['marcajeTK'];
            $dateTime = date('Y-m-d H:i:s');
            // Admin Credential
            $adminID = $params['adminID'];
            $adminSession = $params['adminSession'];
            $ip_info = General::ip_info($ip);

            $bkend_token_temp   = trim($params['bkend_token']);

           

            if($username == '60'){
                // return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00305"][$language] /* Please fill in mobile number */, 'data' => "");
                $returnData['field'][] = array('id' => 'usernameError', 'msg' => $translations["E00305"][$language]/* Please fill in mobile number */);
                // return array("status" => "error", "code" => 1, "statusMsg" => $translations["E00305"][$language], "data" => $returnData);
            }

            if(empty($password) && !$adminSession){
                $errMsg = $translations['E00306'][$language]/* Please fill in password */;
                $returnData['field'][] = array('id' => 'passwordError', 'msg' => $errMsg);
                // return array("status" => "error", "code" => 1, "statusMsg" => $errMsg, "data" => $returnData);
            }

            // $data['field'] = $errorFieldArr;
            // if($errorFieldArr) {
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
            // }

            if($username && $username != '60'){
                $phone = $username;
                $mobileNumberCheck = General::mobileNumberInfo($phone, "MY");
                if($mobileNumberCheck['isValid'] != 1){
                    $returnData['field'][] = array('id' => 'usernameError', 'msg' => $translations["E01093"][$language] /* Invalid mobile number format */);
                }
                $username = $mobileNumberCheck['phone'];
            }

            if($returnData){
                $fields = $returnData['field'];

                $db->where('username', $username);
                $clientName = $db->getValue('client', 'name');

                foreach ($fields as $key => $value) {
                    $newKey = $key + 1;
                    $newErrorArr[$newKey] = $newKey . '.' . $value['msg'];
                }

                $errorString = implode("\n", $newErrorArr);
                
                // for ($i = 1; $i < count($fields); $i++) {
                $find = array("%%name%%", "%%phone%%", "%%ip%%", "%%country%%", "%%datetime%%", "%%issueDesc%%");
                $replace = array($clientName, $username, $ip, $ip_info['country'], $dateTime, $errorString);
                $outputArray = Client::sendTelegramMessage('10020', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                // }
                return array("status" => "error", "code" => 1, "statusMsg" => $translations["E00130"][$language], "data" => $returnData);
            }

            $db->where('username', $username);
            $clientName = $db->getValue('client', 'name');
            
            switch ($params['loginBy']) {
                case 'phone':
                    $db->where('concat(dial_code, phone)', str_replace('+', '', $username));
                    $db->where('type', 'Client');
                    $fieldName = "Phone Number ";
                    break;

                // case 'username':
                //     // $db->where("main_id","0");
                //     $db->where('username', $username);
                //     $fieldName = "Username ";
                //     break;

                // case 'mainAcc':
                //     $db->where('username', $username);
                //     $db->where("main_id", "0", "!=");

                //     $copyDb = $db->copy();
                //     $mainID = $copyDb->getValue('client', 'main_id');
                //     if($mainID != $loginFromID){
                //         return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => "");
                //     }
                //     $fieldName = "Username ";
                //     $loginFromMainAcc = 1;
                //     break;

                // case 'backAcc':
                //     $copyDb = $db->copy();

                //     $db->where('username', $username);
                //     $db->where("main_id", "0");

                //     $copyDb->where("main_id", $id);                    
                //     $subIDAry = $copyDb->getValue('client', 'id', null);
                //     if(!in_array($loginFromID, $subIDAry)){
                //         return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => "");
                //     }
                //     $fieldName = "Username ";
                //     break;

                // case 'email':
                //     $db->where('email', $username);
                //     $db->orwhere('username', $username);
                //     $fieldName = "Email ";
                //     break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01178"][$language] /* Invalid Login Type. */,'data' => "");
                    break;
            }
            $result = $db->get('client');

            //for admin login from admin site to member site
            if (!empty($adminID)) {
                $db->where("id", $adminID);
                $db->where('session_id',$adminSession);
                $adminResult = $db->getOne('admin');

                if($adminResult){
                    $loginFromAdmin = 1;
                }else{
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => "Admin does not exist",'data' => "");
                }
            }
            
            // return array("status" => "error", "code" => 1, "statusMsg" => "", "data" => $adminResult);
            //return array("status" => "error", "code" => 1, "statusMsg" => "", "data" => $db->getLastQuery());


            if(empty($result)){
                $returnData['field'][] = array('id' => 'usernameError', 'msg' => $translations["E01277"][$language]/* Mobile number no associate with any registered account. Please sign up for a new account */);

                $find = array("%%name%%", "%%phone%%", "%%ip%%", "%%country%%", "%%datetime%%", "%%issueDesc%%");
                $replace = array($clientName, $username, $ip, $ip_info['country'], $dateTime, $translations["E01277"][$language]);
                $outputArray = Client::sendTelegramMessage('10020', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
          
                return array("status" => "error", "code" => 1, "statusMsg" => $translations["E01277"][$language], "data" => $returnData);
            }

            if (!$result[0]['username']) {
                $data['incompleteProfile'] = 1;
            }
            
            $clientId = $result[0]["id"];

            //if doesn't have id means it is not login from admin site
            // return array("status" => "error", "code" => 21, "statusMsg" => "testing", "data" => Self::verify_passlib_pbkdf2($password,$result[0]['password']));
            if (!$loginFromAdmin) {

                // this is verification method using pbkdf2_sha512

                //return array("status" => "error", "code" => 21, "statusMsg" => $passwordEncryption, "data" => Self::verify_passlib_pbkdf2($password,$result[0]['password']));
                if ($result[0]['encryption_method'] == "pbkdf2_sha512"){

                    // We need to verify hash password by using this function
                    if (!Self::verify_passlib_pbkdf2($password,$result[0]['password'])){


                        // return array("status" => "error", "code" => 21, "statusMsg" => $errMsg, "data" => Client::verify_passlib_pbkdf2($result[0]['password'] , $password));

                        // $db->where('name', 'memberFailLogin');
                        // $failLimit = $db->getValue('system_settings', 'value');

                        // $failTime = $result[0]['fail_login'] + 1;
                        // $updateData["fail_login"] = $failTime;
                        // $remainTime = $failLimit - $failTime;

                        // if($failTime >= $failLimit || $remainTime == 0) {
                        //     if($result[0]['terminated'] != 1){
                        //         $updateData["suspended"] = 1;
                        //         $errMsg = $translations["E00471"][$language];
                        //     }else{
                        //         $errMsg = $translations["E00473"][$language];
                        //     }
                        // }else{
                        //      // $errMsg = $translations["E01093"][$language];
                        //     $errMsg = $translations["E00818"][$language];
                        //     $errMsg = str_replace("%%count%%", $remainTime, $errMsg);
                        // }
                        
                        // $db->where("id", $clientId);
                        // $db->update('client', $updateData);

                        $errMsg = $translations['E01278'][$language]/*The password you entered is incorrect.*/;
                        $returnData['field'][] = array('id' => 'passwordError', 'msg' => $errMsg);

                        if($returnData){
                            $find = array("%%name%%", "%%phone%%", "%%ip%%", "%%country%%", "%%datetime%%", "%%issueDesc%%");
                            $replace = array($clientName, $username, $ip, $ip_info['country'], $dateTime, $errMsg);
                            $outputArray = Client::sendTelegramMessage('10020', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                        }
                    
                        return array("status" => "error", "code" => 1, "statusMsg" => $errMsg, "data" => $returnData);
                    }

                }else {
              //  if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if (!password_verify($password, $result[0]['password'])){
                        // $db->where('name', 'memberFailLogin');
                        // $failLimit = $db->getValue('system_settings', 'value');

                        // $failTime = $result[0]['fail_login'] + 1;
                        // $updateData["fail_login"] = $failTime;
                        // $remainTime = $failLimit - $failTime;

                        // if($failTime >= $failLimit || $remainTime == 0) {
                        //     if($result[0]['terminated'] != 1){
                        //         $updateData["suspended"] = 1;
                        //         $errMsg = $translations["E00471"][$language];
                        //     }else{
                        //         $errMsg = $translations["E00473"][$language];
                        //     }
                        // }else{
                        //      // $errMsg = $translations["E01093"][$language];
                        //     $errMsg = $translations["E00818"][$language];
                        //     $errMsg = str_replace("%%count%%", $remainTime, $errMsg);
                        // }
                        
                        // $db->where("id", $clientId);
                        // $db->update('client', $updateData);

                        $errMsg = $translations['E01278'][$language]/*The password you entered is incorrect*/;
                        $returnData['field'][] = array('id' => 'passwordError', 'msg' => $errMsg);
                        if($returnData){
                            $find = array("%%name%%", "%%phone%%", "%%ip%%", "%%country%%", "%%datetime%%", "%%issueDesc%%");
                            $replace = array($clientName, $username, $ip, $ip_info['country'], $dateTime, $errMsg);
                            $outputArray = Client::sendTelegramMessage('10020', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                        }
                        return array("status" => "error", "code" => 1, "statusMsg" => $errMsg, "data" => $returnData);
                    }
                }
            }

            //IF Member is registered under this country, prevent login
            $db->where('registered_block_login','1');
            $disabledLoginCountries=$db->map('id')->arrayBuilder()->get('country',null,'id');

            if (in_array($result[0]['country_id'], $disabledLoginCountries)){
                $find = array("%%name%%", "%%phone%%", "%%ip%%", "%%country%%", "%%datetime%%", "%%issueDesc%%");
                $replace = array($clientName, $username, $ip, $ip_info['country'], $dateTime, $translations["E00754"][$language] /* Invalid Login */);
                $outputArray = Client::sendTelegramMessage('10020', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00754"][$language] /* Invalid Login */, 'data' => '');
            }


            $id = $result[0]['id'];
            $turnOffPopUpMemo = $result[0]['turnOffPopUpMemo'];
            if($result[0]['disabled'] == 1) {
                // Return error if account is disabled
                $statusErrMsg = $translations["E00754"][$language]; /*Your account is disabled.*/
            }

            // if($result[0]['activated'] == 0) {
            //     // Return error if account is not activated
            //     $data["resendVerifiedEmail"] = 1;
            //     $statusErrMsg = $translations["E00783"][$language]; /*Your email is not verified!<br/>  Please verify your email address.*/
            // }

            if($result[0]['suspended'] == 1) {
                // Return error if account is suspended
                $statusErrMsg = $translations["E00471"][$language]; /*Your account is suspended.*/
            }

            if($result[0]['freezed'] == 1) {
                // Return error if account is freezed
                $statusErrMsg = $translations["E00472"][$language]; /*Your account is freezed.*/
            }

            if($result[0]['terminated'] == 1) {
               // Return error if account is terminated
                $statusErrMsg = $translations["E00473"][$language]; /*Your account is terminated.*/
            }

            if($statusErrMsg){
                if($marcaje && $marcajeTK){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => "", 'data' => "");
                }else{
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $statusErrMsg, 'data' => $data);
                }
            }

            // Checking if client's countryIP is allowed to login
            $returnData=Self::countryIPBlock($ip,$id);
            if($returnData['status']='error'){
                return $returnData;
            }

            $sessionID = md5($result[0]['username'] . time());
            
            $fields = array('session_id', 'last_login', 'updated_at', 'last_login_ip', 'main_login');
            $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"), $ip, $loginFromMainAcc);
            $db->where('id', $id);
            $db->update('client', array_combine($fields, $values));

            //Insert Session ID
            $sessionData = User::insertSessionData($clientId,$sessionID,$dateTime,$timeOut,$isAutoLogin);

            //get client blocked rights
            $column = array(
                "(SELECT name FROM mlm_client_rights WHERE id = mlm_client_blocked_rights.rights_id) AS blocked_rights"
            );
            $db->where('client_id', $id);
            $result2 = $db->get("mlm_client_blocked_rights", NULL, $column);

            $blockedRights = array();
            foreach ($result2 as $row){
                $blockedRights[] = $row['blocked_rights'];
            }
            $db->where('id', $id);
            $clientDetail = $db->get('client', null, 'name, username,type, concat(dial_code, phone) as phone');
            foreach ($clientDetail as $row) {
                $name = $row['name'];
                $username = $row['username'];
                $type = $row['type'];
                $phone = $row['phone'];
            }

            $find = array("%%name%%", "%%phone%%", "%%ip%%", "%%country%%", "%%datetime%%");
            $replace = array($name, $username, $ip, $ip_info['country'], $dateTime);
            $outputArray = Client::sendTelegramMessage('10007', NULL, NULL, $find, $replace,"","","telegramAdminGroup");

            // $content = '*Login Message* '."\n\n".'Member ID: '.$member_id."\n".'Type: '.$type."\n".'Phone Number: '.$phone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
            // Client::sendTelegramNotification($content);
            //$memo = Bulletin::getPopUpMemo($id, $turnOffPopUpMemo);
            
            $member['memo'] = $memo;
            $member['timeOutFlag'] = Setting::getMemberTimeout();
            $member['userID'] = $id;
            $member['memberID'] = $result[0]['member_id'];
            $member['name'] = $result[0]['name'];
            $member['username'] = $result[0]['username'];
            $member['userEmail'] = $result[0]['email'];
            $member['userRoleID'] = $result[0]['role_id'];
            $member["countryID"] = $result[0]["country_id"];
            $member['sessionID'] = $sessionID;
            $member['pagingCount'] = Setting::getMemberPageLimit();
            $member['decimalPlaces'] = Setting::getInternalDecimalFormat();
            $member['blockedRights'] = $blockedRights;

            $data['userDetails'] = $member;

            //check for authorized and kyc
            //ald authorized = 0;
            $isFreezed = 1;
            $db->where("id",$id);
            $isFreezed = $db->getValue("client","freezed");
            
            $data["isAuthorized"] = $isFreezed == 1 ? 0 : 1;

            //check phone edited
            $data['isEditMobile'] = $result[0]['phone'] ? 1 : 0;

            $kycStatus = "New";
            //client kyc status
            $db->where("client_id",$id);
            $db->orderBy("created_at","DESC");
            $kycRes = $db->get("mlm_kyc",1,"status");
            foreach($kycRes as $kycRow){
                $kycStatus = $kycRow["status"];
            }
            $data["memberKycStatus"] = $kycStatus;

            $db->where("disabled","0");
            $db->orderBy("priority","ASC");
            $bonusReportAry = $db->get("mlm_bonus",null, "name, language_code as languageCode");
            $data["bonusReport"] = $bonusReportAry;

            /* get user's inbox message */
            $inboxSubQuery = $db->subQuery();
            $inboxSubQuery->where("`creator_id`", $id);
            $inboxSubQuery->orWhere("`receiver_id`", $id);
            $inboxSubQuery->get("`mlm_ticket`", null, "`id`");
            $db->where("`ticket_id`", $inboxSubQuery, "IN");
            $db->where("`read`", 0);
            $db->where("`sender_id`", $id, "!=");
            $inboxUnreadMessage = $db->getValue("`mlm_ticket_details`", "COUNT(*)");
            $data['inboxUnreadMessage'] = $inboxUnreadMessage;

            // $db->where('status','Active');
            // $packageArr = $db->get('mlm_product',null,'id,name,translation_code');
            // foreach ($packageArr as &$packageRow) {
            //     $packageRow['display'] = $translations[$packageRow['translation_code']][$language];
            // }
            // $data['packageArr'] = $packageArr;

            $db->orderBy('priority','ASC');
            $rankRes = $db->get('rank',null,'id,type,translation_code');
            foreach ($rankRes as $rankRow) {
                $rankData['id'] = $rankRow['id'];
                $rankData['display'] = $translations[$rankRow['translation_code']][$language];
                $rankList[$rankRow['type']][] = $rankData;
            }
            $data['rankList'] = $rankList;

            $db->where("id", $clientId);
            $db->update('client', array("fail_login" => "0"));

            if(empty($bkend_token_temp))
            {
                $db->orderBy('id', 'desc');
                $db->where('client_id', $clientId);
                $tokenExist = $db->getOne('guest_token');

                if(!$tokenExist)
                {
                    $autoLoginTokenRes = General::generateAutoLoginToken($dateTime, $timeOut);
                    if(!$dateTime) $dateTime = date('Y-m-d H:i:s');
                    $wbToken = $autoLoginTokenRes['wbToken'];
                    $bkendToken = $autoLoginTokenRes['bkendToken'];
                    $expiredTS  = $autoLoginTokenRes['expiredTS'];

                    // insert into guest_token table
                    $insertData = array(
                        'token'         => $bkendToken,
                        'client_id'     => $clientId,
                        'sale_id'       => '',
                        'created_at'    => $todayDate,
                    );
                    $insertGuestToken = $db->insert('guest_token', $insertData);
                    $data['bkend_token'] = $bkendToken;
                }
                else if(!empty($tokenExist['sale_id']))
                {
                    $db->where('id', $tokenExist['sale_id']);
                    $saleOrderStatus = $db->getOne('sale_order');
                    if($saleOrderStatus)
                    {
                        if(strtolower($saleOrderStatus['status']) != 'draft')
                        {
                            // insert into guest_token table
                            $updateData = array(
                                'token'         => $tokenExist['token'],
                                'client_id'     => $clientId,
                                'sale_id'       => '',
                                'created_at'    => $todayDate,
                            );
                            $db->where('client_id', $id);
                            $updateGuestToken = $db->update('guest_token', $updateData);
                            $data['bkend_token'] = $tokenExist['token'];
                        }
                        else if(strtolower($saleOrderStatus['status']) == 'draft')
                        {
                            $updateData = array(
                                'token'         => $tokenExist['token'],
                                'sale_id'       => $tokenExist['sale_id'],
                                'client_id'     => $clientId,
                                'created_at'    => $todayDate,
                            );
                            $db->where('client_id', $clientId);
                            $updateGuestToken = $db->update('guest_token', $updateData);
                            $data['bkend_token'] = $tokenExist['token'];
                        }
                    }
                }
                else
                {
                    if($tokenExist)
                    {
                        $updateData = array(
                            'token'         => $tokenExist['token'],
                            'client_id'     => $id,
                            'sale_id'       => '',
                            'created_at'    => $todayDate,
                        );
                        $db->where('client_id', $id);
                        $updateGuestToken = $db->update('guest_token', $updateData);
                        $data['bkend_token'] = $tokenExist['token'];
                    }
                    else if (!$tokenExist)
                    {
                        $updateData = array(
                            'token'         => $bkendToken,
                            'client_id'     => $id,
                            'sale_id'       => '',
                            'created_at'    => $todayDate,
                        );
                        $db->where('client_id', $id);
                        $updateGuestToken = $db->update('guest_token', $updateData);
                        $data['bkend_token'] = $bkendToken;
                    }
                }
            }
            else
            {
                $db->orderBy('id', 'desc');
                $db->where('client_id', $clientId);
                $existToken = $db->getOne('guest_token');

                if($existToken)
                {
                    $db->where('id', $existToken['sale_id']);
                    $saleOrderStatus = $db->getOne('sale_order');
                    if(strtolower($saleOrderStatus['status']) != 'draft')
                    {
                        $updateData = array(
                            'sale_id' => '',
                        );
                        $db->where('token', $existToken['token']);
                        $db->update('guest_token', $updateData);
                    }
                    else
                    {
                        $bkendToken = $existToken['token'];
                        $data['bkend_token'] = $bkendToken;
                    }
                }

                if(empty($existToken))
                {
                    $db->where('client_id', $clientId);
                    $existToken = $db->getOne('guest_token');
                    if($existToken)
                    {
                        $db->where('id', $existToken['sale_id']);
                        $saleOrderStatus = $db->getOne('sale_order');
                        if(strtolower($saleOrderStatus) != 'draft')
                        {
                            $autoLoginTokenRes = General::generateAutoLoginToken($dateTime, $timeOut);
                            if(!$dateTime) $dateTime = date('Y-m-d H:i:s');
                            $wbToken = $autoLoginTokenRes['wbToken'];
                            $bkendToken = $autoLoginTokenRes['bkendToken'];
                            $expiredTS  = $autoLoginTokenRes['expiredTS'];
                        }
                        else
                        {
                            if($existToken)
                            {
                                $bkendToken = $existToken['token'];
                            }
                        }
                    }
                    else
                    {
                        $autoLoginTokenRes = General::generateAutoLoginToken($dateTime, $timeOut);
                        if(!$dateTime) $dateTime = date('Y-m-d H:i:s');
                        $wbToken = $autoLoginTokenRes['wbToken'];
                        $bkendToken = $autoLoginTokenRes['bkendToken'];
                        $expiredTS  = $autoLoginTokenRes['expiredTS'];
                    }
    
                    // insert into guest_token table
                    $insertData = array(
                        'token'         => $bkendToken,
                        'client_id'     => $id,
                        'created_at'    => $todayDate,
                    );
                    $insertGuestToken = $db->insert('guest_token', $insertData);
                    $data['bkend_token'] = $bkendToken;
                }
            }

            if(!empty($bkend_token_temp))
            {
                // combine cart action
                $db->where('token', $bkendToken);
                $db->where('disabled', '0');
                $clientShoppingCart = $db->get('shopping_cart');

                $db->where('token', $bkend_token_temp);
                $db->where('disabled', '0');
                $guestShoppingCart = $db->get('shopping_cart');

                foreach($guestShoppingCart as $combineCart)
                {
                    unset($result);
                    unset($dataIn);
                    $dataIn['clientID'] = $clientId;
                    $dataIn['packageID'] = $combineCart['product_id'];
                    $dataIn['quantity'] = $combineCart['quantity'];
                    $dataIn['type'] = 'add';
                    $dataIn['product_template'] = '';
                    $dataIn['bkend_token'] = $bkendToken;
                    $result = Inventory::addShoppingCart($dataIn);
                }
            }

            $data['marcaje']            = $sessionData['marcaje'];
            $data['marcajeTK']          = $sessionData['marcajeTK'];
            $data['expiredTS']          = $sessionData['expiredTS']?($sessionData['expiredTS'] + $defTimeOut):"";

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
        
        public function getValidCreditType() {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $creditID = $db->subQuery();
            $creditID->where('name', 'isWallet');
            $creditID->where('value', 1);
            $creditID->getValue('credit_setting', 'credit_id', null);

            $db->where('id', $creditID, 'IN');
            $creditName = $db->getValue("credit", "name", null);

            if(empty($creditName))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

            return $creditName;
        }

        public function getViewMemberDetails($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $clientID = $params['clientID'];

            $db->join("country c", "m.country_id=c.id", "LEFT");
            $db->join("client s", "m.sponsor_id=s.id", "LEFT");
            $db->where("m.id", $clientID);
            // $member = $db->getOne("client m", "m.name, m.email, m.phone, m.dial_code, m.address, c.name AS country, m.disabled, m.suspended, m.freezed, s.username as sponsorUsername, s.name as sponsorName, s.dial_code as sponsorDialCode, s.phone AS sponsorPhone");
            $member = $db->getOne("client m", "m.name, m.email, m.phone, m.dial_code, m.address, c.name AS country, m.disabled, m.suspended, m.freezed, m.sponsor_id as sponsorId");
            $db->where("disabled",0);
            $db->where("address_type","billing");
            $db->where("client_id",$clientID);
            $billingRes = $db->getOne("address","name,email,phone,address,state_id,district_id,sub_district_id,post_code,city,country_id,remarks");

            unset($districtIDAry,$subDistrictIDAry,$postCodeIDAry,$cityIDAry,$stateIDAry,$countryIDAry);

            $districtIDAry[$billingRes["district_id"]] = $billingRes["district_id"];
            $subDistrictIDAry[$billingRes["sub_district_id"]] = $billingRes["sub_district_id"];
            $postCodeIDAry[$billingRes["post_code"]] = $billingRes["post_code"];
            $cityIDAry[$billingRes["city"]] = $billingRes["city"];
            $stateIDAry[$billingRes["state_id"]] = $billingRes["state_id"];
            $countryIDAry[$billingRes["country_id"]] = $billingRes["country_id"];

            if($districtIDAry){
                $db->where("id",$districtIDAry,"IN");
                $districtRes = $db->map("id")->get("county",null,"id,name");
            }

            if($subDistrictIDAry){
                $db->where("id",$subDistrictIDAry,"IN");
                $subDistrictRes = $db->map("id")->get("sub_county",null,"id,name");
            }

            if($postCodeIDAry){
                $db->where("id",$postCodeIDAry,"IN");
                $postCodeRes = $db->map("id")->get("zip_code",null,"id,name");
            }

            if($cityIDAry){
                $db->where("id",$cityIDAry,"IN");
                $cityRes = $db->map("id")->get("city",null,"id,name");
            }

            if($stateIDAry){
                $db->where("id",$stateIDAry,"IN");
                $stateRes = $db->map("id")->get("state",null,"id,name");
            }

            if($countryIDAry){
                $db->where("id",$countryIDAry,"IN");
                $countryRes = $db->map("id")->get("country",null,"id,name,translation_code,country_code");
            }

            unset($billingInfo);
            $billingInfo["name"] = $billingRes["name"];
            $billingInfo["email"] = $billingRes["email"];
            // $billingInfo["dialingArea"] = $countryRes[$billingRes["country_id"]]["country_code"];
            $billingInfo['dialingArea'] = $member['dial_code'];
            $billingInfo["phone"] = $billingRes["phone"];
            $billingInfo["address"] = $billingRes["address"];
            $billingInfo["remarks"] = $billingRes["remarks"];
            $billingInfo["country"] = $translations[$countryRes[$billingRes["country_id"]]["translation_code"]][$language] ? $translations[$countryRes[$billingRes["country_id"]]["translation_code"]][$language] : $countryRes[$billingRes["country_id"]]["name"];
            $billingInfo["state"] = $stateRes[$billingRes["state_id"]];
            // $billingInfo["city"] = $cityRes[$billingRes["city"]];
            $billingInfo["city"] = $billingRes["city"];
            $billingInfo["district"] = $districtRes[$billingRes["district_id"]];
            $billingInfo["subDistrict"] = $subDistrictRes[$billingRes["sub_district_id"]];
            // $billingInfo["postalCode"] = $postCodeRes[$billingRes["post_code"]];
            $billingInfo["postalCode"] = $billingRes["post_code"];

            unset($sponsorInfo);
            // get sponsor user detail
            if($member['sponsorId'] != '0')
            {   
                $db->where('concat(dial_code, phone)', $member['sponsorId']);
                $db->where('type', 'Client');
                $sponsorDetail = $db->getOne('client');
            }
            
            if($sponsorDetail)
            {
                $sponsorInfo["sponsorName"] = $sponsorDetail['name'];
                $sponsorInfo["sponsorDialCode"] = $sponsorDetail['dial_code'];
                $sponsorInfo["sponsorPhone"] = $sponsorDetail['phone'];
            }
            
            if(!$sponsorDetail)
            {
                $sponsorInfo["sponsorName"] = '-';
                $sponsorInfo["sponsorDialCode"] = '-';
                $sponsorInfo["sponsorPhone"] = '-';
            }

            $data['member'] = $member;
            $data["billingInfo"] = $billingInfo;
            $data["sponsorInfo"] = $sponsorInfo;
            if(empty($member))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00279"][$language] /* No result found */, 'data' => "");
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        // -- Registration Start -- // -- need tune
        public function updateClientRank($clientId, $productId) {
            $db = MysqliDb::getInstance();

            $productAry = Product::getProductList();
            foreach ($productAry['data'] as $productKey => $productDetail) {
                $bonusValueAry[$productKey] = $productDetail['bonusValue'];
            }

            $db->where('client_id', $clientId);
            $db->where('name', "package");
            $result = $db->getOne('client_setting', 'id, name, value, reference');

            if(empty($result)) {
                $insertData = array(
                                        "name" => "package",
                                        "value" => $productAry['data'][$productId]['name'],
                                        "reference" => $productId,
                                        "client_id" => $clientId
                                    );
                $db->insert("client_setting", $insertData);
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
            } 

            $previousBonusValue = $result['value'];
            if($bonusValueAry[$productId]['value'] > $bonusValueAry[$result["reference"]]['value']) {
                $updateData = array(
                                        'value' => $productAry[$productId]['name'], 
                                        'reference' => $productAry[$productId]['id']
                                    );
                $db->where('id', $result['id']);
                $db->update('client_setting', $updateData);
            }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function insertMaxCap($clientId, $productId, $belongId, $batchId, $bonusValue, $portfolioId) {
            $db = MysqliDb::getInstance();

            $db->where("product_id", $productId);
            $db->where("name", "maxCapMultiplier");
            $maxCapMultiplier = $db->getValue("mlm_product_setting", "value");

            $db->where("client_id", $clientId);
            $db->where("name", "maxCapMultiplier");
            $savedMaxCapMultiplier = $db->getValue("client_setting", "value");

            if(empty($savedMaxCapMultiplier)) {
                $insertData = array("name" => "maxCapMultiplier", 
                                    "value" => $maxCapMultiplier, 
                                    "client_id" => $clientId);
                $db->insert("client_setting", $insertData);
                $savedMaxCapMultiplier = $maxCapMultiplier;
            } elseif($maxCapMultiplier > $savedMaxCapMultiplier) {
                $updateData = array("value" => $maxCapMultiplier);
                $db->where("client_id", $clientId);
                $db->where("name", "maxCapMultiplier");
                $db->update("client_setting", $updateData);
                $savedMaxCapMultiplier = $maxCapMultiplier;
            }

            $maxCapRecievable = $bonusValue * ($savedMaxCapMultiplier / 100);

            $db->where("username", "creditSales");
            $internalId = $db->getValue("client", "id");
            Cash::insertTAccount($internalId, $clientId, "maxCap", $maxCapRecievable, "maxCap", $belongId, "", $db->now(), $batchId, $clientId, "", $portfolioId, $savedMaxCapMultiplier);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getRegistrationDetails($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $childAgeOption = Setting::$systemSetting['childAgeOption'];

            $clientID = $db->userID;
            $site = $db->userType;

            if($site == "Admin"){
                $clientID = $params["clientID"];
            }

            // if($clientID || $site == "Admin"){
            //     $productReturn = Product::getProductList();
            //     $productData = $productReturn["data"];
            //     foreach ($productData as $productID => $productRow) {

            //         $productRow["bonusValue"] = $productRow["bonusValue"]["value"];

            //         if($productRow["isRegisterPackage"]["value"] == "1" || $productRow["isBundlePackage"]["value"] == "1"){
            //             $validProductList[] = $productRow;
            //         }
            //     }

            //     $productData = $validProductList;

            //     $walletList = Cash::walletDisplaySetting($clientID);
            //     foreach ($walletList as $creditType => $walletData) {
            //         $validCreditType[$creditType] = $creditType;
            //         $creditDisplay[$creditType] = $walletData["translation_code"];
            //     }

            //     $registerType = "Package Register";
            //     $db->where("status","Active");
            //     $db->where("payment_type",$registerType);
            //     $res = $db->get("mlm_payment_method", null, "credit_type AS creditType,min_percentage AS minPercentage,max_percentage AS maxPercentage, group_type AS groupType");
            //     foreach($res AS $row){

            //         if($validCreditType[$row["creditType"]]){

            //             $row['creditDisplay'] = $creditDisplay[$row['creditType']];
            //             $row['balance'] = Cash::getBalance($clientID,$row['creditType']);
                        
            //             if(!$row['groupType']){
            //                 $paymentData[$row['creditType']] = $row;
            //             }else{
            //                 $paymentData[$row['groupType']][$row['creditType']] = $row;
            //             }
            //         }
            //     }

            //     $data['credit'] = $paymentData;
            // }

            // return bank account based on country
            // get bank list
            $db->where('status', "Active");
            $db->orderBy('name', "ASC");
            $bankDetail  = $db->get("mlm_bank ", null, "id, country_id, name, translation_code");
            if (empty($bankDetail))
                $bankDetail = '';

            foreach($bankDetail AS &$bankData){
                $bankData['display'] = $translations[$bankData['translation_code']][$language] ? $translations[$bankData['translation_code']][$language] : $bankData["name"];
            }

            foreach($bankDetail as $bankValue) {
                $bankListData[$bankValue['country_id']][] = $bankValue;

            }
            // end of return bank account based on country

            $countryParams = array("pagination" => "No");
            $resultCountryList = Country::getCountriesList($countryParams);
            if (!$resultCountryList) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00281"][$language] /* No result found */, 'data' => "");
            }
            
            // $countryList    = $resultCountryList['data']['countriesList'];
            // $cityList       = Country::getCity();
            // $countyList     = Country::getCounty();
            // $subCountyList     = Country::getSubCounty();
            // $postalCodeList = Country::getPostalCode();
            // $resultStateList = Country::getState();
            // $stateList[]     = $resultStateList;

            $childAgeOption = explode('#', $childAgeOption);
            foreach ($childAgeOption as $childAgeValue) {
                $childAgeData['value'] = $childAgeValue;

                if(is_numeric($childAgeValue)){
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00481'][$language])/*%%childAgeValue%% years old and above*/;
                }else{
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00482'][$language])/*%%childAgeValue%% years old*/;
                }
                $childAgeOptionArr[] = $childAgeData;
            }

            $data["childAgeOption"] = $childAgeOptionArr;
            // foreach ($resultStateList as $stateRow) {
            //     if($stateRow['country_id'] == 129){
            //         $stateList[] = $stateRow;
            //     }
            // }
            

            $data["productData"]       = $productData;
            // $data['countriesList']     = $countryList;
            // $data['stateList']         = $stateList;
            // $data['cityList']          = $cityList;
            // $data['countyList']        = $countyList;
            // $data['subCountyList']     = $subCountyList;
            // $data['postalCode']        = $postalCodeList; 
            $data['placementPosition'] = $position;
            $data['pacDetails']        = $pacDetail;
            $data['bankDetails']      = $bankListData; 

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function verifyTransactionPassword($clientID, $transactionPassword){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            //get the stored password type.
            $passwordEncryption = Setting::getMemberPasswordEncryption();

            $db->where('id', $clientID);
            if($passwordEncryption == "bcrypt") {
                // Bcrypt encryption
                // Hash can only be checked from the raw values
            }
            else if ($passwordEncryption == "mysql") {
                // Mysql DB encryption
                $db->where('transaction_password', $db->encrypt($transactionPassword));
            }
            else {
                // No encryption
                $db->where('transaction_password', $transactionPassword);
            }
            $result = $db->getValue('client', 'transaction_password');

            if(empty($result)){
                 $returnData['field'][] = array('id' => 'transactionPasswordError', 'msg' => $translations["E00282"][$language]);
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00282"][$language] /* Invalid transaction password */, 'data' => $returnData);
            }
            
            if($passwordEncryption == "bcrypt") {
                // We need to verify hash password by using this function
                if(!password_verify($transactionPassword, $result)){
                    $returnData['field'][] = array('id' => 'transactionPasswordError', 'msg' => $translations["E00282"][$language]);
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00282"][$language] /* Invalid transaction password */, 'data' => $returnData);
                }
                    
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getRegistrationPaymentDetails($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $sponsorUsername  = $params['sponsorUsername'];
            $codeNum          = $params['codeNum'];

            // Get latest unit price
            $unitPrice = General::getLatestUnitPrice();
            // Get valid credit type 
            $creditName = General::getValidCreditType();
            // Get decimal Placse
            $decimalPlaces = Setting::getSystemDecimalPlaces();

            if (empty($sponsorUsername)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00283"][$language] /* Sponsor no found */, 'data'=> "");
            } else {
                $db->where("username", $sponsorUsername);
                $sponsorID = $db->getValue("client", "id");
            }
            if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
            } else {
                // p is mlm_product table, s is mlm_product_setting table
                $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                $db->where("s.name","bonusValue");
                $db->where("p.status", "Active");
                $db->where("p.category","Package");
                $db->where("p.id", $codeNum);
                $copyDb        = $db->copy();
                $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                if (empty($resultPackage)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Sponsor no found */, 'data'=> "");
                }
            }

            foreach ($creditName as $value) {
                // Get min/max payment method
                $paymentMethod = Product::getMinMaxPaymentMethod(number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", ""), $value, "Registration");

                if($paymentMethod[$value]){
                    $balance[] = array("name" => $value, "value" => Cash::getClientCacheBalance($sponsorID, $value), "payment" => $paymentMethod[$value]);
                }
            }
            
            $data['sponsorID']              = $sponsorID;
            $data['balance']                = $balance;
            $data['resultPackage']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, ".", "");
            $data['resultPackage']['name']  = $resultPackage['name'];
            $data['resultPackage']['value'] = $resultPackage['value'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getRegistrationPackageDetails($params) {
            $db = MysqliDb::getInstance();

            $language         = General::$currentLanguage;
            $translations     = General::$translations;

            $type             = $params['type'];
            $codeNum          = $params['codeNum'];
            $status           = $params['status'];
            $sponsorUsername  = $params['sponsorUsername'];
            
            // Get latest unit price
            $unitPrice = General::getLatestUnitPrice();
            // Get valid credit type 
            $creditName = Self::getValidCreditType();
            // Get decimal place
            $decimalPlaces = Setting::getSystemDecimalPlaces();
            
            if(empty($sponsorUsername))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00283"][$language] /* Sponsor no found */, 'data'=> "");
            else {
                $db->where("username", $sponsorUsername);
                $sponsorID = $db->getValue("client", "id");
            }
            
            foreach($creditName as $value) {
                $credit[] = array("name" => $value, "value" => Cash::getClientCacheBalance($sponsorID, $value));
            }

            $data['credit'] = $credit;

            if ($type == 'package') {
                if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                } else {
                    // p is mlm_product table, s is mlm_product_setting table
                    $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                    $db->where("s.name","bonusValue");
                    $db->where("p.status", "Active");
                    $db->where("p.category","Package");
                    $db->where("p.id", $codeNum);
                    $copyDb        = $db->copy();
                    $resultPackage = $db->getOne("mlm_product p", "p.price, p.name, s.value");
                    if (empty($resultPackage)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                    }
                    $data['result']['price'] = number_format($resultPackage['price'] * $unitPrice, $decimalPlaces, '.', '');
                    $data['result']['name']  = $resultPackage['name'];
                    $data['result']['value'] = $resultPackage['value'];
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            } elseif ($type == 'pin') {
                if (empty($codeNum)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00287"][$language] /* Pin no found */, 'data'=> "");
                } else {
                    // a is mlm_product table, c is mlm_pin table
                    $db->join("mlm_product a", "a.id = c.product_id", "LEFT");
                    $db->where("c.code", $codeNum);
                    $db->where('c.status', $status);
                    $copyDb     = $db->copy();
                    $resultPin  = $db->get("mlm_pin c", 1,  "c.code, a.name, c.bonus_value as bonusValue");
                    if (empty($resultPin)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00287"][$language] /* Pin no found */, 'data'=> "");
                    }
                    $data['result'] = $resultPin;
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            } elseif ($type == 'free') {
                if (empty($codeNum)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                } else {
                    // p is mlm_product table, s is mlm_product_setting table
                    $db->join("mlm_product_setting s", "p.id = s.product_id", "LEFT");
                    $db->where("s.name","bonusValue");
                    $db->where("p.status", "Active");
                    $db->where("p.category","Package");
                    $db->where("p.id", $codeNum);
                    $copyDb        = $db->copy();
                    $resultPackage = $db->get("mlm_product p", NULL, "p.name");
                    if (empty($resultPackage)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00284"][$language] /* Package no found */, 'data'=> "");
                    }
                    $data['result'] = $resultPackage;
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
                }
            }
        }
        // -- Registration End -- //

        public function verifyPayment($params) {
            $db = MysqliDb::getInstance();

            $language            = General::$currentLanguage;
            $translations        = General::$translations;

            $clientId            = $params['clientId'];
            $packageId           = $params['packageId'];
            $tPassword           = trim($params['tPassword']);
            $creditData          = $params['creditData'];

            // Get password encryption type
            $passwordEncryption  = Setting::getMemberPasswordEncryption();
            // Get latest unit price
            $unitPrice = General::getLatestUnitPrice();
            
            //checking client ID
            if (empty($clientId)) {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data' => '');
            } else {
                $db->where("id", $clientId);
                $id = $db->getValue("client", "id");

                if (empty($id)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data' => '');
                }
            }
            //checking package ID
            if (empty($packageId)) {
                $errorFieldArr[] = array(
                                            'id'    => 'packageIdError',
                                            'msg'   => $translations["E00339"][$language] /* Invalid package */
                                        );
            }else {
                $db->where("id", $packageId);
                $db->where("category", 'Package');
                $checkingPackageId = $db->getOne("mlm_product", "price, status");
                $price             = $checkingPackageId['price'] * $unitPrice;
                $status            = $checkingPackageId['status'];

                if (empty($checkingPackageId)) {
                    $errorFieldArr[] = array(
                                                'id'    => 'packageIdError',
                                                'msg'   => $translations["E00339"][$language] /* Invalid package */
                                            );
                } else {
                    if ($status != 'Active') {
                        $errorFieldArr[] = array(
                                                    'id'    => 'packageIdError',
                                                    'msg'   => $translations["E00339"][$language] /* Invalid package */
                                                );
                    }
                }
            }
            // checking credit type and amount
            if (empty($creditData)) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00340"][$language] /* Please enter an amount. */
                                        );
            }
            $totalAmount = 0;
            foreach ($creditData as $value) {
                $balance = Cash::getClientCacheBalance($id, $value['creditType']);
                if (!is_numeric($value['paymentAmount']) || $value['paymentAmount'] < 0) {
                    $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $translations["E00330"][$language] /* Amount is required or invalid */
                                            );
                } else {
                    if ($value['paymentAmount'] > $balance){
                        $errorFieldArr[] = array(
                                                    'id'    => $value['creditType'].'Error',
                                                    'msg'   => $translations["E00266"][$language] /* Insufficient credit. */
                                                );
                    }

                    $minMaxResult = Product::checkMinMaxPayment($price, $value['paymentAmount'], $value['creditType'], "Registration");
                    if($minMaxResult["status"] != "ok"){
                        $errorFieldArr[] = array(
                                                'id'    => $value['creditType'].'Error',
                                                'msg'   => $minMaxResult["statusMsg"]
                                            );
                    }

                    $totalAmount = $totalAmount + $value['paymentAmount'];
                    //matching amount with price 
                    
                }
            }

            if ($totalAmount == 0) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00343"][$language] /* Please enter an amount. */
                                        );
            }

            if ($totalAmount < $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00266"][$language] /* Insufficient credit. */
                                        );
            }
            if ($totalAmount > $price) {
                $errorFieldArr[] = array(
                                            'id'    => 'totalError',
                                            'msg'   => $translations["E00344"][$language] /* Credit total does not match with total cost. */
                                        );
            }      
            //checking transaction password
            if (Cash::$creatorType){
                if (empty($tPassword)) {
                    $errorFieldArr[] = array(
                                                'id'    => 'tPasswordError',
                                                'msg'   => $translations["E00128"][$language] /* Please enter transaction password. */
                                            );
                } else {
                    $result = Self::verifyTransactionPassword($clientId, $tPassword);
                    if($result['status'] != "ok") {
                        $errorFieldArr[] = array(
                                                    'id'  => 'tPasswordError',
                                                    'msg' => $translations["E01278"][$language] /* The password you entered is incorrect. */
                                                );
                    }
                }
            }
            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        } 

        public function getCreditTransactionList($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $decimalPlaces  = Setting::getSystemDecimalPlaces();

            $creditType   = $params['creditType'];
            $searchData   = $params['searchData'];
            $seeAll         = $params['seeAll'];

            $usernameSearchType = $params["usernameSearchType"];

            $db->where('name', $creditType);
            $creditCode = $db->getValue('credit', 'translation_code');

            // $data['creditHeader'] = $translations[$creditCode][$language];
            $data['creditType'] = $creditType;

            $db->where('type', $creditType);
            $creditCode = $db->getOne('credit', 'translation_code, code');
            $data['creditHeader'] = $translations[$creditCode['translation_code']][$language];
            
            if(!$creditCode){
                $db->where('name', $creditType);
                $creditCode = $db->getOne('credit', 'admin_translation_code, code');
                $data['creditHeader'] = $translations[$creditCode['admin_translation_code']][$language];
            }

            if($db->userType == 'Admin'){
                $data['creditHeader'] = $data['creditHeader']." - ";
            }

            $creditArr    = Cash::$paymentCredit;
            $creditsType   = $creditArr[$creditType];

            //Get the limit.
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);

    		$adminLeaderAry = Setting::getAdminLeaderAry();

            // Means the search params is there
            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);
                            // $downlines[] = $clientID;

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $db->where('client_id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $db->where('username',$dataValue);
                            $mainLeaderID  = $db->getValue('client','id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, 'IN');

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'fullName':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%".$dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;

                        case 'userName':
                            if ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;
                            
                        case 'memberId':
                            $clientMemberID = $db->subQuery();
                            $clientMemberID->where('member_id', $dataValue);
                            $clientMemberID->getOne('client','id');
                            $db->where('client_id', $clientMemberID);  
                            break;
                            
                        case 'transactionType':
                            $db->where('subject', $dataValue);
                            break;
                            
                        case 'toFromId':
                            $fromUsernameID = $db->subQuery();
                            $fromUsernameID->where('username', $dataValue);
                            $fromUsernameID->getOne('client', "id");
                            $db->where('from_id', $fromUsernameID);
                            $db->orwhere('to_id', $toUsernameID);
                            break;

                        case 'phone':
                            $clientPhoneID = $db->subQuery();
                            $clientPhoneID->where('phone', $dataValue);
                            $clientPhoneID->getOne('client', "id");
                            $db->where('client_id', $clientPhoneID);
                            break;
                            
                        case 'searchDate':
                            $columnName = 'created_at';
                            $dateFrom   = trim($v['tsFrom']);
                            $dateTo     = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'leaderUsername':

                            break;

                        case 'mainLeaderUsername':

                            break;

                        case 'sponsorID':
                            $sq = $db->subQuery();  
                            $ssq = $sq->subQuery();
                            $ssq->where('member_id', $dataValue);
                            $ssq->get('client', null, 'id');
                            $sq->where('sponsor_id',$ssq);
                            $sq->get('client',null,'id');
                            $db->where('client_id',$sq,'IN');
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if (empty($creditType)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00350"][$language] /* Please select a credit type */, 'data' => "");
            } else {
                    $db->where('type', $creditsType ,"IN");
            }

            if($adminLeaderAry){
            	$db->where('client_id', $adminLeaderAry, 'IN');
            }
            $copyDb = $db->copy();
            $db->orderBy('created_at', "DESC");

            $getUsername = "(SELECT username FROM client WHERE client.id=client_id) AS username";
            $getName     = "(SELECT name FROM client WHERE client.id=client_id) AS name";
            $getMemberID = "(SELECT member_id FROM client WHERE client.id=client_id) AS memberID";
            $getSponsorID = "(SELECT member_id FROM client WHERE id = (SELECT sponsor_id FROM client R WHERE R.id = credit_transaction.client_id)) AS sponsorID";

            $totalRecord = $copyDb->getValue("credit_transaction", "count(*)");

            if($seeAll == "1"){
                $limit = array(0, $totalRecord);
            }

            // $db->groupBy("transaction_id");
            $db->groupBy("group_id");
            $db->groupBy("subject");
            $result = $db->get("credit_transaction", $limit, $getUsername.','.$getMemberID.','.$getName.", client_id, subject, from_id, to_id, SUM(amount) as amount, remark, batch_id, creator_id, creator_type, created_at, type, ".$getSponsorID);

            if (empty($result)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00146"][$language] /* No result found. */, 'data'=> $data);

            unset($clientID);

            $clientUsernameMap = $db->map('id')->get('client', NULL, 'id, username');

            foreach($result as $value) {

            	if(!$startingDate) $startingDate = $value['created_at'];

                if($value['creator_type'] == 'SuperAdmin'){

                    $superAdminID[] = $value['creator_id'];
                }
                else if($value['creator_type'] == 'Admin'){
                    $adminID[] = $value['creator_id'];

                }
                else if ($value['creator_type'] == 'Member'){
                    $clientID[] = $value['creator_id'];

                }

                unset($eachBal);
                if(!$balance[$value['client_id']]){
                	$eachBal = Cash::getBalance($value['client_id'], $creditType, $startingDate);
                	$balance[$value['client_id']] = $eachBal;
                }

                $clientIDs[] = $value['client_id'];

                if($value['subject'] == "Transfer In" || $value['subject'] == "Transfer Out")
                    $batch[] = $value['batch_id'];
            }
            if(!empty($superAdminID)) {
                $db->where('id', $superAdminID, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($adminID)) {
                $db->where('id', $adminID, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $value) {
                   $usernameList['Admin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($clientID)) {
                $db->where('id', $clientID, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }
            $usernameList['System']['0'] = "System";
            
            if(!empty($batch)) {
                $db->where('batch_id', $batch, 'IN');
                $db->where('subject', array("Transfer In", "Transfer Out"), 'IN');
                $getUsername = "(SELECT username FROM client WHERE client.id=client_id) AS username";
                $batchDetail = $db->get('credit_transaction', null, 'subject, batch_id, '.$getUsername);
            }
            if(!empty($batchDetail)) {
                foreach($batchDetail as $value) {
                    $batchUsername[$value['batch_id']][$value['subject']] = $value['username'];
                }
            }

            foreach($result as $value) {
                $transactionSubject             = $value['subject'];
                $transaction['created_at']  = General::formatDateTimeToString($value['created_at'], "d/m/Y H:i:s");

                $transaction['username']    = $value['username'];
                $transaction['memberID']    = $value['memberID'];
                $transaction['clientID']    = $value['client_id'];
                $transaction['sponsorID']   = $value['sponsorID'];
                $clientID=$transaction['clientID'];
                if(!$clientData[$clientID]['mainLeaderUsername']){//Saving to Array, Client's mainLeaderUsername so not to go searching again for each loop
                    $clientData[$clientID]['mainLeaderUsername'] = Tree::getMainLeaderUsername($transaction)? : '-' ;
                }
                $transaction['mainLeaderUsername']=$clientData[$clientID]['mainLeaderUsername'];
                // $mainLeaderUsername = Tree::getMainLeaderUsername($transaction);
                // $transaction['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";
                $transaction['name']        = $value['name'];
                $transaction['subject']     = $value['subject'];
                if($value['subject'] == "Transfer Out") {
                    $transaction['to_from'] = $batchUsername[$value['batch_id']]["Transfer In"] ? $batchUsername[$value['batch_id']]["Transfer In"] : "-";
                }
                else if($value['subject'] == "Transfer In") {
                    $transaction['to_from'] = $batchUsername[$value['batch_id']]["Transfer Out"] ? $batchUsername[$value['batch_id']]["Transfer Out"] : "-";
                }
                else if($value['from_id'] == "9")
                    $transaction['to_from'] = "bonusPayout";
                else
                    $transaction['to_from'] = "-";

                if($value['from_id'] >= 1000000) {
                    $transaction['credit_in'] = "-";
                    $transaction['credit_out'] = number_format($value['amount'], $decimalPlaces, '.', '');
                    $transaction['balance'] = number_format($balance[$value['client_id']], $decimalPlaces, '.', '');
                    $balance[$value['client_id']] += $value['amount'];
                }
                else {
                    $transaction['credit_in'] = number_format($value['amount'], $decimalPlaces, '.', '');
                    $transaction['credit_out'] = "-";
                    $transaction['balance'] = number_format($balance[$value['client_id']], $decimalPlaces, '.', '');
                    $balance[$value['client_id']] -= $value['amount'];
                }

                $dateTimeStr = $value['created_at'];
                $dateTimeAry = explode(' ', $dateTimeStr);
                $dateAry = explode('-', $dateTimeAry[0]);
                $timeAry = explode(':', $dateTimeAry[1]);

                $startTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':00';
                $endTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':59';

                // $db->where('created_on', $startTimeStr, '>=');
                // $db->where('created_on', $endTimeStr, '<=');
                // $db->where('type', $creditType);
                // $currentRate = $db->getValue('mlm_coin_rate', 'rate');

                // $transaction['coinRate'] = $currentRate;

                $transaction['creator_id'] = $usernameList[$value['creator_type']][$value['creator_id']];
                $transaction['remark'] = $value['remark'] ? $value['remark'] : "-";
                $transaction['type'] = $value['type'] ? $translations[$creditLanguageCodeArray[$value['type']]][$language] : "-";

                if($creditType == 'maxCap'){
                    $db->where('batch_id', $value['batch_id']);
                    $tempClientID = $db->getValue('mlm_client_portfolio', 'client_id');
                    $transaction['to_from'] = $clientUsernameMap[$tempClientID] ? $clientUsernameMap[$tempClientID] : '-';
                } elseif (in_array($transactionSubject, array('Credit Reentry', 'Credit Register', 'Diamond Reentry', 'Diamond Register'))) {
                    $db->where('batch_id', $value['batch_id']);
                    $tempClientID = $db->getValue('mlm_client_portfolio', 'client_id');
                    $transaction['to_from'] = $clientUsernameMap[$tempClientID] ? $clientUsernameMap[$tempClientID] : '-';
                }

                $transactionList[] = $transaction;
                unset($transaction);
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            // This is to get the transaction type(as subject) for the search select option
            $db->groupBy('subject');
            $resultType = $db->get('credit_transaction', null, 'subject');
            if (empty($resultType)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00352"][$language] /* Failed to get commands for search option */, 'data' => '');
            }
            foreach($resultType as $value) {
                $searchBarData['type'] = $value['subject'];

                $searchBarDataList[] = $searchBarData;
            }

            // $totalRecord = $copyDb->getValue("credit_transaction", "count(*)");

            // remove duplicate transaction type. Then sort it alphabetically
            $searchBarDataList = array_map("unserialize", array_unique(array_map("serialize", $searchBarDataList)));
            sort($searchBarDataList);

            $data['transactionList'] = $transactionList;
            $data['transactionType'] = $searchBarDataList;
            $data['totalPage']       = ceil($totalRecord/$limit[1]);
            $data['pageNumber']      = $pageNumber;
            $data['totalRecord']     = $totalRecord;
            $data['numRecord']       = $limit[1];
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);  
        }

        public function getTreePlacementPositionAvailability($uplineID, $position) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($uplineID) || empty($position))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty */, 'data' => "");

            $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];

            if($position < 1 || $position > $maxPlacementPositions)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00359"][$language] /* Invalid placement. */, 'data' => "");

            $db->where('upline_id', $uplineID);
            $db->where('client_position', $position);
            $result = $db->getOne('tree_placement', 'id');

            if($db->count > 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00360"][$language] /* Position has been taken. */, 'data' => "");
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getTreePlacementPositionValidity($sponsorID, $uplineID, $clientID="") {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($sponsorID) || empty($uplineID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $db->where('client_id', $uplineID);
            $result = $db->getValue('tree_placement', 'trace_key');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language], 'data' => "");

            $traceKey = str_replace(array("-1>","-1<","-1|","-1"), "/", $result);
            $traceKey = array_filter(explode("/", $traceKey), 'strlen');

            $uplineLevel = array_search($uplineID, $traceKey);
            $sponsorLevel = array_search($sponsorID, $traceKey);

            if(!empty($clientID)) {
                if(strlen(array_search($clientID, $traceKey)) > 0)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            }

            if(strlen($sponsorLevel) <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            else if($sponsorLevel > $uplineLevel)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");
            else
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getUpline($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language], 'data' => "");

            $getUsername = '(SELECT username FROM client WHERE client.id=upline_id) AS username';
            $getID = '(SELECT id FROM client WHERE client.id=upline_id) AS id';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=upline_id) AS created_at';
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_sponsor', $getUsername.','.$getID.','.$getCreatedAt);

            // if(empty($result))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client */, 'data' => "");

            foreach($result as $key => $value) {
                if($key == 'created_at'){
                    $sponsorUpline[$key] = $value ? date($dateTimeFormat, strtotime($value)) : "-";
                }else{
                    $sponsorUpline[$key] = $value ? $value : "-";
                }
            }

            unset($result);

            $getUsername = '(SELECT username FROM client WHERE client.id=upline_id) AS username';
            $getID = '(SELECT id FROM client WHERE client.id=upline_id) AS id';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=upline_id) AS created_at';
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_placement', $getUsername.','.$getID.','.$getCreatedAt);

            // if(empty($result))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid client.", 'data' => "");

            foreach($result as $key => $value) {
                $placementUpline[$key] = $value ? $value : "-";
            }

            $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            $data['placementUpline'] = $placementUpline;
            $data['sponsorUpline'] = $sponsorUpline;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getTreeSponsor($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $clientID = $params['clientID'];
            $site = $db->userType;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00363"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00364"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_sponsor', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00365"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->getOne('client', 'id, username, name AS fullName, created_at, activated, disabled, suspended, freezed,`terminated`');

            if(empty($sponsor))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");
            
            $getUsername = '(SELECT username FROM client WHERE client.id=client_id) AS username';
            $getCreatedAt = '(SELECT created_at FROM client WHERE client.id=client_id) AS created_at';
            $getName = '(SELECT name FROM client WHERE client.id=client_id) AS fullName';
            $getActivated = '(SELECT activated FROM client WHERE client.id=client_id) AS activated';
            $getDisabled = '(SELECT disabled FROM client WHERE client.id=client_id) AS disabled';
            $getSuspended = '(SELECT suspended FROM client WHERE client.id=client_id) AS suspended';
            $getFreezed = '(SELECT freezed FROM client WHERE client.id=client_id) AS freezed';
            $getTerminated = '(SELECT `terminated` FROM client WHERE client.id=client_id) AS `terminated`';

            $db->where('upline_id', $sponsor['id']);
            $downlines = $db->get('tree_sponsor', null, 'client_id,'.$getUsername.','.$getCreatedAt.','.$getName.','.$getActivated.','.$getDisabled.','.$getSuspended.','.$getFreezed.','.$getTerminated);

            $db->where('client_id', $params['realClientID']);
            $searchLevel = $db->getValue('tree_sponsor', 'level');

            $db->where('client_id', $params['clientID']);
            $searchSponsorLevel = $db->getValue('tree_sponsor', 'level');
            $finalSponsorLevel = $searchSponsorLevel - $searchLevel;
            
            $allDownlines = Tree::getSponsorTreeDownlines($sponsor['id'],false);
            foreach ($allDownlines as $value) {
               $allDownlinesArray[] = $value;
            }

            //find the level of all downlines_id
            $db->where('upline_id', $sponsor['id']);
            $sponsorLevel = $db->map("client_id")->get("tree_sponsor", null, "client_id, level");

            $db->where('client_id', $params['realClientID']);
            $targetLevel = $db->getValue('tree_sponsor', 'level');

            foreach ($downlines as $value1) {
                $downlineArry[] = $value1["client_id"];
            }
            $clientIDArr = $downlineArry;
            $clientIDArr[] = $clientID;

            foreach($clientIDArr as $clientIDRow){
                $downline[$clientIDRow] = Tree::getSponsorTreeDownlines($clientIDRow, false);

                if($downline[$clientIDRow]){
                    $db->where('client_id',$downline[$clientIDRow],"IN");
                    $downlineSales[$clientIDRow] = $db->getValue('mlm_bonus_in','SUM(bonus_value)');
                }
            }

            //Get Client Sales
            $db->where('client_id',$clientIDArr,"IN");
            $db->groupBy('client_id');
            $clientSalesData = $db->map('client_id')->get('mlm_bonus_in',null,'client_id, SUM(bonus_value) as bonus_value');

            $clientRankArr = Bonus::getClientRank("Bonus Tier",$clientIDArr,"","rankDisplay");
            $rankIDAry = $db->map("id")->get("rank", null, "id, translation_code as langCode");

            foreach ($downlineArry as $value2) {
                $allDownlinesResult = Tree::getSponsorTreeDownlines($value2,false);
                if (empty($allDownlinesResult)) continue;
                foreach ($allDownlinesResult as $value3) {
                    $allDownlinesResultArray[$value2][] = $value3;
                }
            }
            if(empty($downlines))
                $downlines = array();
  
            if($params['realClientID']) {
                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');

                if($clientTraceKey) {
                    $traceKey = array_filter(explode("/", $clientTraceKey), 'strlen');

                    $realClientKey = array_search($params['realClientID'], $traceKey);
                    $clientKey = array_search($params['clientID'], $traceKey);

                    for($i=$realClientKey; $i <= $clientKey; $i++) { 
                        $breadcrumbTemp[] = $traceKey[$i];
                    }

                    $db->where('id', $breadcrumbTemp, 'IN');
                    $result = $db->get('client', null, 'id, username');

                    if($result) {
                        foreach($breadcrumbTemp as $value) {
                            $arrayKey = array_search($value, array_column($result, 'id'));
                            $breadcrumb[] = $result[$arrayKey];
                        }
                    }
                } 
            }

            if($sponsor['terminated'] == 1) {
                $sponsor["rankDisplay"] = "Terminated";
            }else{
                $sponsor["rankDisplay"] = $clientRankArr[$clientID]["rank_id"] ? $translations[$rankIDAry[$clientRankArr[$clientID]["rank_id"]]][$language] : "-";
            }

            $db->where("client_id",$clientID);
            $traceKey = $db->getValue("tree_sponsor","trace_key");

            // $sponsor['community'] = ($clientSalesData[$clientID]['downline'] ? $clientSalesData[$clientID]['downline'] : "0");
            $sponsor["ownSales"] = ($clientSalesData[$clientID] ? Setting::setDecimal($clientSalesData[$clientID]): "0");
            $sponsor["pgpSales"] = $downlineSales[$clientID] ? : 0;

            if($sponsor['created_at'] != '0000-00-00 00:00:00'){
                $sponsor['created_at'] = (date("Y-m-d H:i:s", strtotime($sponsor['created_at'])) < "2022-09-01 00:00:00") ? date('Y-m-d', strtotime('2022-09-01 00:00:00')) : date('Y-m-d', strtotime($sponsor['created_at']));
            }else{
                $sponsor['created_at'] = '-';
            }


            unset($salesRes);
            unset($communityRankID);
            unset($rankID);
            unset($totalDownline);
            
            if($downlines){
                foreach ($downlines as $k => $v) {
                    $downlineIDAry[$v["client_id"]] = $v["client_id"];
                }

                $db->where("client_id", $downlineIDAry, "IN");
                $db->where("status", "Active");
                $downlineProductIDAry = $db->map("client_id")->get("mlm_client_portfolio", null, "client_id, product_id");
               
                $db->where("name","totalDownline");
            	$db->where("client_id", $downlineIDAry, "IN");
            	$totalDownlineArr = $db->map("client_id")->get("client_setting",NULL,"client_id,value");

                foreach ($downlines as $k => &$v) {
                	$donwlineID = $v['client_id'];
                    if($v['terminated'] == 1) {
                        $v["rankDisplay"] = "Terminated";
                    }else{
                        $v["rankDisplay"] = $clientRankArr[$donwlineID]["rank_id"] ? $translations[$rankIDAry[$clientRankArr[$donwlineID]["rank_id"]]][$language] : "-";
                    }
                    
    	            // $totalDownline = $totalDownlineArr[$donwlineID];
    	            // $v['community'] = ($clientSalesData[$donwlineID]['downline'] ? $clientSalesData[$donwlineID]['downline'] : "0");
                    $v["ownSales"] = ($clientSalesData[$donwlineID] ? $clientSalesData[$donwlineID] : "0");
                    $v["pgpSales"] = $downlineSales[$donwlineID] ? : 0;

                    if($v['created_at'] != '0000-00-00 00:00:00'){
                        $v['created_at'] = (date("Y-m-d H:i:s", strtotime($v['created_at'])) < "2022-09-01 00:00:00") ? date('Y-m-d', strtotime('2022-09-01 00:00:00')) : date('Y-m-d', strtotime($v['created_at']));
                    }else{
                        $v['created_at'] = '-';
                    }

    	            unset($downlineIDArr);
                    $v['downlines'] = $sponsorLevel[$value1["client_id"]] - $targetLevel;
                }
            }
            if($site == "Admin"){
                $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
                $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            }
            $data['breadcrumb'] = $breadcrumb;
            $data['sponsor'] = $sponsor;
            $data['downlinesLevel'] = $downlines;
            $data['uplineLevel'] = $finalSponsorLevel;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getTreePlacement($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $currentDate = date('Y-m-d');

            $site = $db->userType;

            $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];
            for ($i=1; $i<=$maxPlacementPositions; $i++) {
                $clientSettingName[] = "Placement Total $i";
                $clientSettingName[] = "Placement CF Total $i";
            }

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00367"][$language] /* Failed to load view. */, 'data' => "");

            $db->where('client_id', $params['clientID']);
            $clientTraceKey = $db->getValue('tree_placement', 'trace_key');
            if(empty($clientTraceKey))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00367"][$language] /* Failed to load view. */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00368"][$language] /* Invalid username. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_placement', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00369"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->get('client', 1, 'id AS client_id, username, name, member_id, created_at');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");

            $db->where('trace_key','%'.$params['clientID'].'%','LIKE');
            $placementDownlines = $db->get('tree_placement',null,'client_id');
            foreach($placementDownlines as $placementDownlinesRow){
                $placementDownlineIDAry[$placementDownlinesRow['client_id']] = $placementDownlinesRow['client_id'];
            }

            unset($bonusInAry, $salesAmountData);
            $db->where("DATE(created_at)", $currentDate);
            $db->where('client_id',$placementDownlineIDAry,'IN');
            $db->groupBy("client_id");
            $bonusInAry = $db->map("client_id")->get("mlm_bonus_in", null, "client_id, sum(bonus_value) as bonus_value");

            if($bonusInAry){
                foreach($bonusInAry as $clientID => $bonusValue){
                    unset($uplineData);
                    $uplineData = Bonus::getPlacementTreeUplines($clientID,false);
                    if(empty($uplineData)) continue;
                    $downlinePosition = 0;
                    $downlineID = 0;

                    foreach ($uplineData as $uplineRow) {
                        $uplineID = $uplineRow["client_id"];
                        if($uplineID != $clientID){
                            $salesAmountData[$uplineID][$downlinePosition] += $bonusValue;
                        }
                        $downlineID = $uplineID;
                        $downlinePosition = $uplineRow["client_position"];
                    }
                }
            }

            $depthLevel = $params['depthLevel'] ? $params['depthLevel'] : 3;
            $upline = $sponsor;
            $sponsorDownlines = array();
            for($i = 0; $i < $depthLevel; $i++) {
                $nextGenUpline = array();
                foreach($upline as $value) {
                    $colUsername = '(SELECT c.username FROM client c WHERE c.id=t.client_id) AS username';
                    $colCreatedAt = '(SELECT c.created_at FROM client c WHERE c.id=t.client_id) AS created_at';
                    $colMemberID = '(SELECT c.member_id FROM client c WHERE c.id=t.client_id) AS member_id';
                    $colName = '(SELECT c.name FROM client c WHERE c.id=t.client_id) AS name';
                    $db->where('upline_id', $value['client_id']);
                    $downlines = $db->get('tree_placement t', null, 't.client_id, '.$colUsername.', '.$colMemberID.', '.$colName.', upline_id, client_position,'.$colCreatedAt);

                    if($db->count <= 0)
                        continue;

                    $nextGenUpline = array_merge($nextGenUpline, $downlines);
                    $sponsorDownlines = array_merge($sponsorDownlines, $downlines);
                }
                $upline = $nextGenUpline;
                unset($nextGenUpline);
            }

            foreach($sponsor as $sponsors) {
                // Get the placement total
                if (count($clientSettingName) > 0) {
                    $db->where("name",$clientSettingName,"IN");
                    $db->where("client_id",$sponsors["client_id"]);
                    $bvRes = $db->get("client_setting", null, "name, value");
                    foreach ($bvRes as $bvRow) {
                        $clientSetting[$bvRow["name"]] = $bvRow["value"];
                    }

                    $sponsors['created_at'] = (date("Y-m-d H:i:s", strtotime($sponsors['created_at'])) < "2022-09-01 00:00:00") ? "2022-09-01" : date("Y-m-d", strtotime($sponsors['created_at']));

                    unset($cfAmount);
                    $db->where('client_id', $sponsors["client_id"]);
                    $db->orderBy('bonus_date','DESC');
                    $cfAmount = $db->getOne('mlm_bonus_couple','remaining_dvp_1, remaining_dvp_2');


                    for ($i=1; $i<=$maxPlacementPositions; $i++) {
                        // $sponsors['placementTotal_'.$i] = $clientSetting["Placement Total $i"]? $clientSetting["Placement Total $i"] : 0;
                        // $sponsors['placementCFTotal_'.$i] = $clientSetting["Placement CF Total $i"]? $clientSetting["Placement CF Total $i"] : 0;
                        // $sponsors['placementRRTotal_'.$i] = $clientSetting["Placement RR Total $i"]? $clientSetting["Placement RR Total $i"] : 0;
                        $sponsors['DVP_'.$i] = $salesAmountData[$sponsors["client_id"]][$i] ? $salesAmountData[$sponsors["client_id"]][$i] : 0;
                        $sponsors['remainingDVP_'.$i] = $cfAmount["remaining_dvp_".$i.""]? $cfAmount["remaining_dvp_".$i.""] : 0;
                    }
                    unset($clientSetting);

                    $sponsorRow[] = $sponsors;
                }
            }

            unset($downlines);
            foreach ($sponsorDownlines as $sponsorDownlinesRow) {

                if($sponsorDownlinesRow["client_position"]) {
                    if($maxPlacementPositions == 2)
                        $sponsorDownlinesRow['placement'] = $sponsorDownlinesRow["client_position"] == 1 ? "Left" : "Right";
                    else if($maxPlacementPositions == 3) {
                        if($sponsorDownlinesRow["client_position"] == 1)
                            $sponsorDownlinesRow['placement'] = "Left";
                        else if($sponsorDownlinesRow["client_position"] == 2)
                            $sponsorDownlinesRow['placement'] = "Middle";
                        else if($sponsorDownlinesRow["client_position"] == 3)
                            $sponsorDownlinesRow['placement'] = "Right";
                    }
                }

                $db->where("name",$clientSettingName,"IN");
                $db->where("client_id",$sponsorDownlinesRow["client_id"]);
                $bvRes = $db->get("client_setting", null, "name, value");
                foreach ($bvRes as $bvRow) {
                    $clientSetting[$bvRow["name"]] = $bvRow["value"];
                }

                $sponsorDownlinesRow['created_at'] = (date("Y-m-d H:i:s", strtotime($sponsorDownlinesRow['created_at'])) < "2022-09-01 00:00:00") ? "2022-09-01" : date("Y-m-d", strtotime($sponsorDownlinesRow['created_at']));

                unset($cfAmountPlacement);
                $db->where('client_id', $sponsorDownlinesRow["client_id"]);
                $db->orderBy('bonus_date','DESC');
                $cfAmountPlacement = $db->getOne('mlm_bonus_couple','remaining_dvp_1, remaining_dvp_2');

                for ($i=1; $i<=$maxPlacementPositions; $i++) {
                    // $sponsorDownlinesRow['placementTotal_'.$i] = $clientSetting["Placement Total $i"]? $clientSetting["Placement Total $i"] : 0;
                    // $sponsorDownlinesRow['placementCFTotal_'.$i] = $clientSetting["Placement CF Total $i"]? $clientSetting["Placement CF Total $i"] : 0;
                    // $sponsorDownlinesRow['placementRRTotal_'.$i] = $clientSetting["Placement RR Total $i"]? $clientSetting["Placement RR Total $i"] : 0;
                    $sponsorDownlinesRow['DVP_'.$i] = $salesAmountData[$sponsorDownlinesRow["client_id"]][$i] ? $salesAmountData[$sponsorDownlinesRow["client_id"]][$i] : 0;
                    $sponsorDownlinesRow['remainingDVP_'.$i] = $cfAmountPlacement["remaining_dvp_".$i.""] ? $cfAmountPlacement["remaining_dvp_".$i.""] : 0;
                }
                unset($clientSetting);

                $downlines[] = $sponsorDownlinesRow;

            }
            // foreach($sponsorDownlines as $array) {
            //     foreach($array as $k => $v) {
            //         if($k == "client_position") {
            //             if($maxPlacementPositions == 2)
            //                 $col['placement'] = $v == 1 ? "Left" : "Right";
            //             else if($maxPlacementPositions == 3) {
            //                 if($v == 1)
            //                     $col['placement'] = "Left";
            //                 else if($v == 2)
            //                     $col['placement'] = "Middle";
            //                 else if($v == 3)
            //                     $col['placement'] = "Right";
            //             }
            //         }


            //         $col[$k] = $v;
            //     }
            //     $downlines[] = $col;
            // }

            if(empty($downlines))
                $downlines = array();

            if($params['realClientID']) {
                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_placement', 'trace_key');

                if($clientTraceKey) {
                    $traceKey = str_replace(array("-1>","-1<","-1|","-1","<",">"), "/", $clientTraceKey);
                    $traceKey = array_filter(explode("/", $traceKey), 'strlen');

                    $realClientKey = array_search($params['realClientID'], $traceKey);
                    $clientKey = array_search($params['clientID'], $traceKey);

                    for($i=$realClientKey; $i <= $clientKey; $i++) { 
                        $breadcrumbTemp[] = $traceKey[$i];
                    }

                    $db->where('id', $breadcrumbTemp, 'IN');
                    $result = $db->get('client', null, 'id, username');

                    if($result) {
                        foreach($breadcrumbTemp as $value) {
                            $arrayKey = array_search($value, array_column($result, 'id'));
                            $breadcrumb[] = $result[$arrayKey];
                        }
                    }
                } 
            }

            if($site == "Admin"){
                $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
                $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            }
            $data['breadcrumb'] = $breadcrumb;
            $data['sponsor'] = $sponsorRow;
            $data['downlines'] = $downlines;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPlacementTreeVerticalView($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = trim($params["clientID"]);
            $targetID = trim($params["targetID"]);
            $viewType = trim($params["viewType"]);

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00371"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $clientID);
                $clientTraceKey = $db->getValue('tree_placement', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00372"][$language] /* Failed to load view. */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_placement', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00373"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $clientID = trim($childID);
                    $targetID = trim($childID);
                }
            }

            $maxPlacementPositions = Setting::$systemSetting["maxPlacementPositions"];

            if(strlen($clientID) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "clientID");
            if(!$viewType)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00375"][$language] /* Select view type */, 'data' => array('field' => "targetID"));


            for($i=1; $i<=$maxPlacementPositions; $i++) {
                $clientSettingName[] = "'Placement Total $i'";
                $clientSettingName[] = "'Placement CF Total $i'";
            }

            $db->where("id", $targetID);
            // $db->where("type", "Member");
            $result = $db->getOne("client", "id");
            if(!$result)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00149"][$language] /* Client not found */, 'data' => array('field' => "targetID"));


            $db->where("client_id", $targetID);
            $targetClient = $db->getOne("tree_placement", "level, trace_key");            

            $filterTraceKey = strstr($targetClient["trace_key"], $clientID);

            $targetTraceKey = preg_split('/(?<=[0-9])(?=[<|>]+)/i', $filterTraceKey);


            foreach ($targetTraceKey as $key => $val) {
                if(!is_numeric($val[0])){
                    $targetUplinesID[] = explode("-", substr($val, 1))[0];

                }else{
                    $targetUplinesID[] = explode("-", $val)[0];
                }
            }

            $db->where("client_id" , $targetUplinesID, "IN");
            $targetUplinesAry = $db->get("tree_placement", null, "client_id,client_position,level,trace_key");
            
            $db->where("id" , $targetUplinesID, "IN");
            $targetUplinesClient = $db->map ('id')->ObjectBuilder()->get("client", null, "id, username, name, created_at");
            
            foreach ($targetUplinesAry as $key => $upline) {
                $uplineID = $upline['client_id'];
                $username = $targetUplinesClient[$uplineID]->username;
                $name = $targetUplinesClient[$uplineID]->name;
                $createdAt = $targetUplinesClient[$uplineID]->created_at;

                $tree['attr']['ID'] = $uplineID;
                $tree['attr']['name'] = $name;
                $tree['attr']['username'] = $username;
                // Build the level from clientID to targetID
                $data['treeLink'][] = $tree;

                if($uplineID == $targetID) {

                    $data['target']['attr']['id'] = $uplineID;
                    $data['target']['attr']['username'] = $username;
                    $data['target']['attr']['name'] = $name;
                    $data['target']['attr']['createdAt'] = date("d/m/Y", strtotime($createdAt));

                    $targetLevel = $upline["level"];
                }
            }

            $depthRule = "1";
            if($viewType == "Horizontal") $depthRule = "3";

            $db->where("level", $targetClient["level"], ">");
            $db->where("level", $targetClient["level"]+$depthRule, "<=");
            $db->where("trace_key", $targetClient["trace_key"]."%", "LIKE");
            $targetDownlinesAry = $db->get("tree_placement", null," client_id,client_unit,client_position,level,trace_key");

            if(count($targetDownlinesAry) == 0) return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);

            foreach ($targetDownlinesAry as $key => $val) $targetDownlinesIDAry[] = $val["client_id"];
            $db->where("id", $targetDownlinesIDAry, "in");
            $targetDownlinesClient = $db->map('id')->ObjectBuilder()->get("client",null,"id,username,name,created_at,disabled,suspended,freezed");
            
            foreach ($targetDownlinesAry as $key => $targetDownline) {
                $depth = $targetDownline["level"] - $targetLevel;
                $downlineID = $targetDownline['client_id'];
                $username = $targetDownlinesClient[$downlineID]->username;
                $name = $targetDownlinesClient[$downlineID]->name;
                $createdAt = $targetDownlinesClient[$downlineID]->created_at;
                $disabled = $targetDownlinesClient[$downlineID]->disabled;
                $suspended = $targetDownlinesClient[$downlineID]->suspended;
                $freezed = $targetDownlinesClient[$downlineID]->freezed;

                $downline['attr']['id'] = $downlineID;
                $downline['attr']['username'] = $username;
                $downline['attr']['name'] = $name;
                $downline['attr']['position'] = $targetDownline["client_position"];

                $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];
                if($maxPlacementPositions == 2)
                    $downline['attr']['position'] = $downline['attr']['position'] == 1 ? "Left" : "Right";
                else if($maxPlacementPositions == 3) {
                    if($downline['attr']['position'] == 1)
                        $downline['attr']['position'] = "Left";
                    else if($downline['attr']['position'] == 2)
                        $downline['attr']['position'] = "Middle";
                    else if($downline['attr']['position'] == 3)
                        $downline['attr']['position'] = "Right";
                }
                $downline['attr']['depth'] = $depth;
                $downline['attr']['createdAt'] = date("d/m/Y", strtotime($createdAt));
                $downline['attr']['downlineCount'] = count(Self::getPlacementTreeDownlines($downlineID, false));
                $downline['attr']['disabled'] = $disabled==0 ? "No" : "Yes";
                $downline['attr']['suspended'] = $suspended==0 ? "No" : "Yes";
                $downline['attr']['freezed'] = $freezed==0 ? "No" : "Yes";

                $data['downline'][] = $downline;
                unset($downline);

                //get placement total in client setting                
            }

            $data['targetID'] = ($clientID == $targetID) ? "" : $targetID;
            
            // $data['generatePlacementBonusType'] = Setting::$internalSetting['generatePlacementBonusType'];
            // $data['placementLRDecimalType'] = Setting::$internalSetting['placementLRDecimalType'];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPlacementTreeDownlines($clientID, $includeSelf = true) {
            $db = MysqliDb::getInstance();   

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_placement", "trace_key");

            $db->where("trace_key", $result["trace_key"]."%", "LIKE");
            $result = $db->get("tree_placement", null, "client_id");

            foreach ($result as $key => $val) $downlineIDArray[$val["client_id"]] = $val["client_id"];

            if(!$includeSelf) unset($downlineIDArray[$clientID]);

            return $downlineIDArray;
        }

        public function getSponsorTreeTextView($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00376"][$language] /* Failed to load view */, 'data' => "");

            if($params['username']) {
                $db->where('username', $params['username']);
                $childID = $db->getValue('client', 'id');
                if(empty($childID))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00377"][$language] /* Invalid username. */, 'data' => "");

                $db->where('client_id', $params['clientID']);
                $clientTraceKey = $db->getValue('tree_sponsor', 'trace_key');
                if(empty($clientTraceKey))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00376"][$language] /* Failed to load view */, 'data' => "");

                $db->where('trace_key', $clientTraceKey."%", 'LIKE');
                $db->where('client_id', $childID);
                $isDownline = $db->getValue('tree_sponsor', 'id');
                if(empty($isDownline)) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00378"][$language] /* Invalid downline */, 'data' => "");
                } else {
                    $params['clientID'] = $childID;
                }
            }

            $db->where('id', $params['clientID']);
            $sponsor = $db->get('client', 1, 'id AS client_id, username,member_id, created_at');

            if($db->count <= 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00379"][$language] /* Invalid client */, 'data' => "");

            $depthLevel = 30;
            $upline = $sponsor;
            $sponsorDownlines = array();

            for($i = 0; $i < $depthLevel; $i++) {
                $nextGenUpline = array();
                foreach($upline as $value) {
                    $colUsername = '(SELECT c.username FROM client c WHERE c.id=t.client_id) AS username';
                    $colCreatedAt = '(SELECT c.created_at FROM client c WHERE c.id=t.client_id) AS created_at';
                    $colMemberID = '(SELECT c.member_id FROM client c WHERE c.id=t.client_id) AS member_id';
                    $uplineUsername = '(SELECT c.username FROM client c WHERE c.id=t.upline_id) AS uplineUsername';
                    $db->where('upline_id', $value['client_id']);
                    $downlines = $db->get('tree_sponsor t', null, 't.client_id, '.$colUsername.', upline_id,'.$colCreatedAt.', '.$colMemberID.', '.$uplineUsername);

                    if($db->count <= 0)
                        continue;
                    $nextGenUpline = array_merge($nextGenUpline, $downlines);

                    // $sponsorDownlines = array_merge($sponsorDownlines, $downlines);
                }
                $upline = $nextGenUpline;

                unset($nextGenUpline);
                if(!$upline) continue;
                $downlinesLevel[$i] = $upline;
            }

            foreach ($downlinesLevel as $key => $downData) {
                foreach ($downData as $downValue ) {
                       $downlineDataAry[$downValue["client_id"]] = $downValue["client_id"];
                
                }
            }

            $db->where('status','Active');
            $productRes = $db->get('mlm_product',null,'id,name');
            foreach ($productRes as $pKey => $productRow) {
                $productIDAry[$productRow['id']] = $productRow['id'];
                $productNameAry[$productRow['id']] = $productRow['name'];
            }

            if($productIDAry){
                $db->where('module_id',$productIDAry,"IN");
                $db->where('module','mlm_product');
                $db->where('language',$language);
                $productNameDisplayAry = $db->map('module_id')->get('inv_language',null,'module_id,content');
            }
            
            $allDownlines = Tree::getSponsorTreeDownlines($params['clientID'],false);
            foreach ($allDownlines as $value) {
               $allDownlinesArray[] = $value;
            }

            if(!empty($allDownlinesArray)){
                $db->where("product_id",$productIDAry,'IN');
                $db->where("client_id",$allDownlinesArray,'IN');
                $db->groupBy("product_id");
                $portfolioRes = $db->get("mlm_client_portfolio",null,'client_id,sum(product_price) as total, product_id');

                foreach ($portfolioRes as $portfolioValue) {
                    $pData["name"]  = $productNameAry[$portfolioValue['product_id']];
                    $pData["total"]  = $portfolioValue['total'];

                    $portfolioData[] = $pData;

                }
            }

            //Logic not same, hide it.
            /*$db->where("status","Active");
            $db->orderBy("created_at","ASC");
            $allPortfolioRes = $db->get("mlm_client_portfolio",null,'client_id, product_id');
            foreach ($allPortfolioRes as $allPortfolioData) {
                $clientPortfolioDataAry[$allPortfolioData['client_id']] = $allPortfolioData['product_id'];
            }*/

            foreach ($downlinesLevel as $level => &$firstRow) {
                foreach ($firstRow as &$downlinesLevelData) {
                    $insideClientID = $downlinesLevelData['client_id'];
                    /*$packageDisplay = $productNameDisplayAry[$clientPortfolioDataAry[$insideClientID]];
                    $downlinesLevelData['packageDisplay'] = $packageDisplay?$packageDisplay:"-";*/
                    $downlinesLevelData['created_at'] = $downlinesLevelData['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($downlinesLevelData['created_at'])) : "-";;

                }
            }


            $data['downlines'] = $downlinesLevel;
            $data['portfolio'] = $portfolioData;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function changeSponsor($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            if(empty($params['clientID']) || empty($params['uplineUsername'])) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");
            }

            $clientID       = $params['clientID'];
            $uplineUsername = $params['uplineUsername'];

            // Get sponsor by username
            $db->where('username',$uplineUsername);
            $uplineID = $db->getValue('client','id');

			if(empty($uplineID)) {
				return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid new sponsor id.", 'data' => "");
			}

            $db->where("client_id",$clientID);
            $uplineSponsorID = $db->getOne("tree_sponsor","upline_id");


            $db->where("client_id",$uplineSponsorID['upline_id']);
            $uplineSponsorIDTraceKey = $db->getOne("tree_sponsor","trace_key,upline_id");

            $db->where("trace_key","%".$uplineID."%","LIKE");
            $isUnderSponsorID = $db->get("tree_sponsor",null,"client_id");

            foreach ($isUnderSponsorID as $key => $value) {
                $isUnderSponsorIDAry[] = $value['client_id'];
            }

            if(!in_array($uplineID, $isUnderSponsorIDAry)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "The Upline Username Should be Same Tree with The username", 'data' => "");
            }

            $db->where("client_id",$clientID);
            $uplineOctID = $db->getOne("tree_placement","upline_id,trace_key");

            $db->where("client_id", $uplineOctID['upline_id']);
            $traceKey = $db->getValue("tree_placement", "trace_key");
            $traceKeys = str_replace(array("<", "|", ">", "-1"), array("/", "/", "/", ""), $traceKey);

            $uplineOctIDArray = array_filter(explode("/", $traceKeys), 'strlen');

            $uplineOctIDArray = array_slice($uplineOctIDArray,0);

            if(!in_array($uplineID, $uplineOctIDArray)) {
                 return array('status' => "error", 'code' => 1, 'statusMsg' => "The Upline Username is in different  Placement Tree ", 'data' => "");
            }

            $targetSponsor = Tree::getSponsorByUsername($uplineUsername);
            if(!$targetSponsor) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00573"][$language], 'data' => "");

            } else {
                $targetSponsorTraceKey = explode("/", $targetSponsor["trace_key"]);
                foreach ($targetSponsorTraceKey as $val) {
                    $targetSponsorUplinesID[$val] = $val;
                }

            }

            //get current client's sponsor ID
            $db->where("id", $clientID);
            $client = $db->getOne("client", "sponsor_id, username");
            $oldSponsorID = $client["sponsor_id"];
            $username = $client["username"];

            // If is the same sponsor, skip it
            if($targetSponsor["id"] == $oldSponsorID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00574"][$language], 'data' => "");

            }

            // If is ownself, skip it
            $db->where('username', $uplineUsername);
            $uplineID = $db->getValue('client', 'id');
            if($uplineID == $clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00575"][$language], 'data' => "");

            }

            $db->where("client_id", $clientID);
            $result = $db->getOne("tree_sponsor", "trace_key");
            $clientTraceKey = $result["trace_key"];

            if(!$clientTraceKey) {
                // Skip if encounter error
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language], 'data' => "");

            }

            // Compare level, cannot change to a lower level sponsor in the same tree
            if($targetSponsorUplinesID[$clientID]) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00577"][$language], 'data' => "");

            }

            // Remove sales from old sponsor upline
            $db->where('trace_key', $clientTraceKey.'/%', 'like');
            $downlineIDArray = $db->map('client_id')->get('tree_sponsor',null,'client_id');

            $removeGroupSalesRes = Subscribe::updateSponsorGroupSales($clientID,'decrease',$downlineIDArray);
            if ($removeGroupSalesRes['status'] != 'ok') {
                return $removeGroupSalesRes;
            }


            //lock the table prevent others access this table while running function
            //  $db->setLockMethod("WRITE")->lock("tree_sponsor");
            $db->where('client_id', $uplineID);
            $upline = $db->getOne('tree_sponsor', 'level, trace_key', 1);

            if($db->count <= 0) {
               // $db->unlock();
               return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $uplineLevel = $upline['level'];
            $traceKey = $upline['trace_key'];

            $db->where('client_id', $clientID);
            $client = $db->getOne('tree_sponsor', 'id, level, trace_key');

            if($db->count <= 0) {
              // $db->unlock();
               return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00383"][$language] /* Invalid sponsor */, 'data' => "");
            }

            $db->rawQuery("UPDATE tree_sponsor SET upline_id = '".$uplineID."', level = '".($uplineLevel + 1)."', trace_key = '".($traceKey.'/'.$clientID)."' WHERE id = '".$client['id']."' ");

            $db->where('trace_key', $client['trace_key'].'/%', 'like');
            $downlines = $db->get('tree_sponsor', null, 'id, client_id, level, trace_key');

            $levelDiscrepancy = (($uplineLevel - $client['level']) + 1);

            foreach($downlines as $value) {
                $array = explode($clientID.'/', $value['trace_key']);

                $result = $db->rawQuery("UPDATE tree_sponsor SET level = '".($levelDiscrepancy + $value['level'])."', trace_key = '".($traceKey.'/'.$clientID.'/'.$array[1])."' WHERE id = '".$value['id']."' ");

            }

            $db->where('id', $clientID);
            $db->update('client', array('sponsor_id' => $uplineID));

            Leader::insertMainLeaderSetting($clientID, $uplineID);

            // insert activity log
            $titleCode    = 'T00009';
            $activityCode = 'L00009';
            $transferType = 'Change Sponsor';
            $activityData = array('user' => $username,'oldSponsorID' => $oldSponsorID,'newSponsorID' => $uplineID);

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes) {
              // $db->unlock();
               return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");
            }

            $db->where("client_id",$oldSponsorID);
            $traceKey = $db->getValue("tree_sponsor", "trace_key");
            $currentSponsorUpline = explode("/", $traceKey);
            foreach($currentSponsorUpline AS $id){
                $idArr[] = $id;
            }

            $totalDownline = 1;
            $totalDownline += COUNT($downlines);
            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalDownline");
                $db->update("client_setting",array("value"=>$db->dec($totalDownline)));
            }
            unset($idArr);

            $db->where("client_id",$targetSponsor["id"]);
            $traceKey = $db->getValue("tree_sponsor","trace_key");
            $currentSponsorUpline = explode("/", $traceKey);
            foreach($currentSponsorUpline AS $id){
                $idArr[] = $id;
            }

            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalDownline");
                $db->update("client_setting",array("value"=>$db->inc($totalDownline)));
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00386"][$language], 'data' => "");
        
        }

        public function changePlacement($params) {

            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['clientID']) || empty($params['uplineUsername']) || empty($params['position']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $clientID = $params['clientID'];
            $uplineUsername = $params['uplineUsername'];
            $position = $params['position'];

            $db->where('id', $clientID);
            $clientUsername = $db->getOne('client', 'username');

            if($uplineUsername == $clientUsername['username']){
                $errorFieldArr[] = array(
                    'id'  => 'uplineUsernameError',
                    'msg' => "Upline username cannot same with Client Username"
                );
            }
            $data['field'] = $errorFieldArr;
             if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
            }

            $db->where('username', $uplineUsername);
            $uplineID = $db->getValue('client', 'id');

             if(empty($uplineID)) {
				return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid new placement id.", 'data' => "");
                //$errorFieldArr[] = array(
                //                            'id'  => 'uplineUsernameError',
                //                            'msg' => $translations["E00382"][$language] /* Username does not exist. */
                //                        );
            }

            $downlineAry=Tree::getPlacementTreeDownlines($clientID,false);

            $db->where("client_id",$clientID);
            $uplineSponsorID = $db->getValue("tree_sponsor","upline_id");

            $db->where("client_id",$uplineSponsorID);
            $uplineOctTraceKey = $db->getValue("tree_placement", "trace_key");

            $db->where('trace_key', $uplineOctTraceKey.'%', 'like');
            $availableChangeOctClient = $db->get("tree_placement",null,"client_id");

            foreach ($availableChangeOctClient as $client) {
                $availableChangeOctClientAry[] = $client['client_id'];
            }
          
            if(!in_array($uplineID, $availableChangeOctClientAry)) {
                 return array('status' => "error", 'code' => 1, 'statusMsg' => "The Upline Username cannot High than the Sponsor Username", 'data' => "");
            }

            if(in_array($uplineID, $downlineAry)){
                $errorFieldArr[] = array(
                    'id'  => 'uplineUsernameError',
                    'msg' => "upline  username  exists  in Client downlines" /* Username does not exist. */
                );

            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
            }

            $db->where("upline_id", $uplineID);
            $db->where("client_position", $position);
            $checkUplineAvailabilePosition = $db->getOne('tree_placement', "client_position, level, trace_key");
            
            if(!empty($checkUplineAvailabilePosition)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00386"][$language] /* Invalid placement. */, 'data' => "");
            }

            $db->where("client_id", $clientID);
            $clientOldData= $db->getOne('tree_placement', "client_position, level, trace_key");


            $db->where("client_id", $uplineID);
            $uplineData= $db->getOne('tree_placement', "client_position, level, trace_key");

            $currentlevel = $uplineData['level'] +1;
            $uplinePosition = $uplineData['client_position'];

            if($position==1) {
                $traceKey = $uplineData["trace_key"]."<".$clientID."-1";
            } else {
                $traceKey = $uplineData["trace_key"].">".$clientID."-1"; 
            }
            
            if($currentlevel==0) {
                $clientUnit = 0;
                $uplineUnit = 0;
            } else if($currentlevel==1) {
                $clientUnit = 1;
                $uplineUnit = 0;
            } else {
                $clientUnit = 1;
                $uplineUnit = 1;
            }


            // update treeoctopus table columns
            $updateData = array (
                'client_unit'       => $clientUnit,
                'client_position'   => $position,
                'upline_id'         => $uplineID,
                'level'             => $currentlevel,
                'trace_key'         => $traceKey
            );
            $db->where('client_id', $clientID);
            $db->update('tree_placement', $updateData);


            $updateUplineData = array(
                'upline_unit'       => $uplineUnit,
                'upline_position'   => $uplinePosition
            );
            $db->where("upline_id", $uplineID);
            $db->update("tree_placement", $updateUplineData);


            $updateClientTable = array (
                'placement_id'         => $uplineID,
                'placement_position'   => $position,
            );
            $db->where('id', $clientID);
            $db->update('client', $updateClientTable);


            $db->where('upline_id', $clientID);
            $db->orderBy('id','ASC');
            $downlines = $db->get('tree_placement', null,"client_position, level, trace_key");

            if(!empty($downlines)){

                foreach ($downlines as $row) {
                    $db->where('trace_key', $row['trace_key'].'%', 'like');
                    $db->orderBy('id','ASC');
                    $getalldownlines= $db->get('tree_placement', null);

                       foreach($getalldownlines as $value) {

                        $downlineTraceKeys=str_replace($clientOldData['trace_key'],"", $value['trace_key']);
                        $downlineRealTraceKey =$traceKey.$downlineTraceKeys;
                        $downlinesLevels = count(explode(",", str_replace(array("<",">","|"), ",", $downlineRealTraceKey)))-1;

                        $updateDataDownline = array (
                            'trace_key'         => $downlineRealTraceKey,
                            'level'             => $downlinesLevels,
                        );

                        $db->where('client_id', $value['client_id']);
                        $db->update('tree_placement', $updateDataDownline);
                    }
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $updateData);

        }

        public function getSponsor($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");
            $getClientName = "(SELECT name FROM client WHERE client.id=client_id) AS client_name";
            $getClientUsername = "(SELECT username FROM client WHERE client.id=client_id) AS client_username";
            $getClientMemberID = "(SELECT member_id FROM client WHERE client.id=client_id) AS member_id";
            $getUplineName = "(SELECT name FROM client WHERE client.id=upline_id) AS upline_name";
            $getUplineUsername = "(SELECT username FROM client WHERE client.id=upline_id) AS upline_username";
            $getUplineMemberID = "(SELECT member_id FROM client WHERE client.id=upline_id) AS upline_member_id";
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_sponsor', 'client_id, upline_id,'.$getClientName.','.$getClientUsername.','.$getClientMemberID.','.$getUplineName.','.$getUplineUsername.','.$getUplineMemberID);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");

            foreach($result as $key => $value) {
                if(empty($value))
                    $value = "-";
                $data[$key] = $value;
            }

            $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getPlacement($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            if(empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");
            $getClientName = "(SELECT name FROM client WHERE client.id=client_id) AS client_name";
            $getClientUsername = "(SELECT username FROM client WHERE client.id=client_id) AS client_username";
            $getClientMemberID = "(SELECT member_id FROM client WHERE client.id=client_id) AS member_id";
            $getUplineName = "(SELECT name FROM client WHERE client.id=upline_id) AS upline_name";
            $getUplineUsername = "(SELECT username FROM client WHERE client.id=upline_id) AS upline_username";
            $getUplineMemberID = "(SELECT member_id FROM client WHERE client.id=upline_id) AS upline_member_id";
            $db->where('client_id', $params['clientID']);
            $result = $db->getOne('tree_placement', 'client_id, upline_id, client_position, '.$getClientName.','.$getClientUsername.','.$getUplineName.','.$getUplineUsername.','.$getUplineMemberID.','.$getClientMemberID);

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00361"][$language] /* Invalid client. */, 'data' => "");

            $maxPlacementPositions = Setting::$systemSetting['maxPlacementPositions'];

            foreach($result as $key => $value) {
                if($key == "client_position"){
                    if($maxPlacementPositions == 2)
                        $value = $value == 1 ? "Left" : "Right";
                    else if($maxPlacementPositions == 3) {
                        if($value == 1)
                            $value = "Left";
                        else if($value == 2)
                            $value = "Middle";
                        else if($value == 3)
                            $value = "Right";
                    }
                }
                if(empty($value))
                    $value = "-";
                $data[$key] = $value;
            }

            $memberDetails = Self::getCustomerServiceMemberDetails($params['clientID']);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getCustomerServiceMemberDetails($clientID="", $params="") {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            if(empty($clientID) && empty($params))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $clientID = $clientID ? $clientID : $params['clientID'];
            $db->where('id', $clientID);
            $getClientUnitTier = "(SELECT value FROM client_setting WHERE name='tierValue' AND client_id=client.id) AS unit_tier";
            $getClientSponsorBonusPercentage = "(SELECT value FROM client_setting WHERE type='Bonus Percentage' AND name='sponsorBonus' AND client_id=client.id) AS sponsor_bonus_percentage";
            $getClientPairingBonusPercentage = "(SELECT value FROM client_setting WHERE type='Bonus Percentage' AND name='pairingBonus' AND client_id=client.id) AS pairing_bonus_percentage";
            $result = $db->getOne('client', 'id, username, member_id, name, '.$getClientUnitTier.','.$getClientSponsorBonusPercentage.','.$getClientPairingBonusPercentage);
            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            foreach($result as $key => $value) {
                $memberDetails[$key] = $value ? $value : "0";
            }

            // get MT4 account no.
            $db->where('client_id',$clientID);
            $db->where('name','quantumAccDisplay');
            $quantumAcc = $db->getValue('client_setting','value');
            $memberDetails["quantumAcc"] = $quantumAcc?:'-'; 

            $data['memberDetails'] = $memberDetails;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function getInboxUnreadMessage($userID,$site){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientID = $userID ? $userID : $db->userID;
            $site = $site ? $site : $db->userType;

            /* get user's inbox message */
            if($site == "Member"){
                /*$inboxSubQuery = $db->subQuery();
                $inboxSubQuery->where("`creator_id`", $clientID);
                $inboxSubQuery->orWhere("`receiver_id`", $clientID);
                $inboxSubQuery->get("`mlm_ticket`", null, "`id`");*/

                $db->where("creator_id", $clientID);
                $db->orWhere("receiver_id", $clientID);
                $ticketRes = $db->get("mlm_ticket", null, "id, type");
                foreach ($ticketRes as $ticketRow) {
                    $unreadTicketAry[$ticketRow["id"]] = $ticketRow["type"];
                    $ticketIDAry[] = $ticketRow["id"];
                }
            }else{
                /*$inboxSubQuery = $db->subQuery();
                $inboxSubQuery->where("`type`", "support");
                $inboxSubQuery->where("`status`", "Closed", "!=");
                $inboxSubQuery->get("`mlm_ticket`", null, "`id`");*/

                $db->where("status",  "Closed", "!=");
                $ticketRes = $db->get("mlm_ticket", null, "id, type");
                foreach ($ticketRes as $ticketRow) {
                    $unreadTicketAry[$ticketRow["id"]] = $ticketRow["type"];
                    $ticketIDAry[] = $ticketRow["id"];
                }

            }

            if($ticketIDAry){
                $db->where("`ticket_id`", $ticketIDAry, "IN");
                $db->where("`sender_id`", $clientID, "!=");
                $db->where("`read`", 0);
                $db->groupBy("ticket_id");
                $inboxUnreadMessageRes = $db->get("`mlm_ticket_details`", null, "ticket_id, COUNT(*) as unreadCount");
                foreach($inboxUnreadMessageRes as $inboxUnreadMessageRow){
                    $inboxUnreadMessage[$unreadTicketAry[$inboxUnreadMessageRow["ticket_id"]]] += $inboxUnreadMessageRow["unreadCount"];
                    // $inboxUnreadMessage[$inboxUnreadMessageRow["type"]] = $inboxUnreadMessageRow["unreadCount"];
                }
            }

            

            $data['inboxUnreadMessage'] = $inboxUnreadMessage;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function memberChangePassword($params) {
            $db = MysqliDb::getInstance();
            $language            = General::$currentLanguage;
            $translations        = General::$translations;

            $memberId = $db->userID;

            // $memberId            = $params['memberId'];
            $passwordCode        = $params['passwordCode'];
            $currentPassword     = $params['currentPassword'];
            $verificationCode    = $params['verificationCode'];
            $newPassword         = $params['newPassword'];
            $newPasswordConfirm  = $params['newPasswordConfirm'];

            // get password length
            $maxPass  = Setting::$systemSetting['maxPasswordLength'];
            $minPass  = Setting::$systemSetting['minPasswordLength'];
            $maxTPass = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minTPass = Setting::$systemSetting['minTransactionPasswordLength'];
            // Get password encryption type
            $passwordEncryption  = Setting::getMemberPasswordEncryption();
            $browserInfo = General::getBrowserInfo();
            $ip = $browserInfo["ip"]?$browserInfo["ip"]:"Unknown";
            $ipInfo = General::ip_info($ip);

            if (empty($memberId))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if (empty($passwordCode)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00444"][$language] /* Password type not found. */, 'data'=> "");

            } else {
                if ($passwordCode == 1) {
                    $passwordType = "password";

                } else if ($passwordCode == 2) {
                    $passwordType = "transaction_password";

                } else {
                   return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00444"][$language] /* Password type not found. */, 'data'=> "");
                }
            }
            // get error msg type
            if ($passwordType == "password") {
                $idName        = 'Password';
                $msgFieldB     = $translations["A00120"][$language];
                $msgFieldS     = $translations["A00120"][$language];
                $maxLength     = $maxPass;
                $minLenght     = $minPass;

            } else if ($passwordType == "transaction_password") {
                $idName        = 'TPassword';
                $msgFieldB     = $translations["A01190"][$language];
                $msgFieldS     = $translations["A01190"][$language];
                $maxLength     = $maxTPass;
                $minLenght     = $minTPass;

            }
            if (empty($newPasswordConfirm)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."ConfirmError",
                                            // 'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00445"][$language])
                                            'msg' => $translations["E01247"][$language] /* Please fill in confirm new password */ 
                                        );

            } else {
                if ($newPasswordConfirm != $newPassword) 
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."ConfirmError",
                                                'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00446"][$language]) 
                                            );
            }

            // Retrieve the encrypted password based on settings
            $newEncryptedPassword = Setting::getEncryptedPassword($newPassword);
            // Retrieve the encrypted currentPassword based on settings
            $encryptedCurrentPassword = Setting::getEncryptedPassword($currentPassword);

            $db->where('id', $memberId);
            $result = $db->getOne('client', $passwordType);
            if (empty($result)) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if ($passwordType == "password"){
                if (empty($currentPassword)) {
                    $errorFieldArr[] = array(
                                                'id'  => "current".$idName."Error",
                                                'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00448"][$language]) 
                                            );
                } else {
                    $db->where('id', $memberId);
                    $getCurrentEncryptionMethodAndPass = $db->getOne('client', 'password,encryption_method');
                    // Check password encryption
                    if ($passwordEncryption == "bcrypt") {
                        if($getCurrentEncryptionMethodAndPass['encryption_method'] == "pbkdf2_sha512")
                        {
                            if (!Self::verify_passlib_pbkdf2($currentPassword,$getCurrentEncryptionMethodAndPass['password']))
                            {
                                $errorFieldArr[] = array(
                                    'id'  => "current".$idName."Error",
                                    'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                );
                            }
                        }
                        else
                        {
                            // We need to verify hash password by using this function
                            if(!password_verify($currentPassword, $result[$passwordType])) {
                                $errorFieldArr[] = array(
                                                            'id'  => "current".$idName."Error",
                                                            'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                        );
                            }
                        }
                    } else {
                        if ($encryptedCurrentPassword != $result[$passwordType]) {
                            $errorFieldArr[] = array(
                                                        'id'  => "current".$idName."Error",
                                                        'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                    );
                        }
                    }
                }
            }elseif ($passwordType == "transaction_password"){
                if (empty($currentPassword)) {
                    // if(empty($verificationCode)){
                        $errorFieldArr[] = array(
                                                    'id'  => "current".$idName."Error",
                                                    'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00448"][$language]) 
                                                );
                } else {
                    // Check password encryption
                    if ($passwordEncryption == "bcrypt") {
                        // We need to verify hash password by using this function
                        if(!password_verify($currentPassword, $result[$passwordType])) {
                            // if(empty($verificationCode)){

                                $errorFieldArr[] = array(
                                                            'id'  => "current".$idName."Error",
                                                            'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                        );
                        }
                    } else {
                        if ($encryptedCurrentPassword != $result[$passwordType]) {
                            // if(empty($verificationCode)){
                                $errorFieldArr[] = array(
                                                            'id'  => "current".$idName."Error",
                                                            'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00449"][$language]) 
                                                        );
                        }
                    }
                }
            }
            if (empty($newPassword)) {
                
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' =>  $translations["E01248"][$language]/* Please fill in new password */ 
                                        );
            } else {
                if (strlen($newPassword) < $minLenght || strlen($newPassword) > $maxLength) {
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."Error",
                                                'msg' => str_replace(array("%%min%%","%%max%%","%%password%%"), array($minLenght,$maxLength,$msgFieldB), $translations["E00451"][$language])  /*  Password length should within 8 - 20 characters  */ 
                                            );
                }
                // else if(!ctype_alnum($newPassword) || !preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $newPassword)){

                //     $errorFieldArr[] = array(
                //         'id'  => "new".$idName."Error",
                //         'msg' => $translations["M03926"][$language] /* Need at least one lowercase letter and one digit */
                //     );

                // }
                else {
                    //checking new password no match with current password
                    if ($passwordEncryption == "bcrypt") {
                        // We need to verify hash password by using this function
                        if(password_verify($newPassword, $result[$passwordType])) {
                            $errorFieldArr[] = array(
                                                        'id'  => "new".$idName."Error",
                                                        'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00453"][$language]) 
                                                    );
                        }
                    } else {
                        if ($newEncryptedPassword == $result[$passwordType]) {
                            $errorFieldArr[] = array(
                                                        'id'  => "new".$idName."Error",
                                                        'msg' => str_replace('%%password%%', $msgFieldS, $translations["E00453"][$language]) 
                                                    );
                        }  
                    }
                }
            }
            $data['field'] = $errorFieldArr;
            if($errorFieldArr){

                $db->where('id', $memberId);
                $phone = $db->getValue('client', 'username');

                $db->where('id', $memberId);
                $clientName = $db->getValue('client', 'name');

                foreach ($errorFieldArr as $key => $value) {
                    $newKey = $key + 1;
                    $newErrorArr[$newKey] = $newKey . '.' . $value['msg'];
                }

                $errorString = implode("\n", $newErrorArr);

                // for ($i = 0; $i < count($errorFieldArr); $i++) {
                    $find = array("%%phoneNumber%%", "%%name%%", "%%ip%%", "%%country%%", "%%dateTime%%","%%issueDesc%%");
                    $replace = array($phone, $clientName, $ip, $ipInfo['country'], $dateTime, $errorString);
                    $outputArray = Client::sendTelegramMessage('10021', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                // }
                
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);
            }

            $updateData = array($passwordType => $newEncryptedPassword,
                                'encryption_method' => 'bcrypt');
            $db->where('id', $memberId);
            $updateResult = $db->update('client', $updateData);
            if($updateResult)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");

            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00455"][$language] /* Update failed. */, 'data' => "");
        }

        public function memberResetPassword($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            // $username           = $params['username'];
            $password           = trim($params['password']);
            $retypePassword     = trim($params['retypePassword']);
            $verificationCode   = trim($params['verificationCode']);
            $otpType            = $params['otpType'];
            // $step               = $params['step'];
            // $phoneNumber        = $params['phoneNumber'];
            // $dialCode           = $params['dialCode'];
            //$email = trim($params['email']);
            $phone = trim($params['phone']);

            $maxPass  = Setting::$systemSetting['maxPasswordLength'];
            $minPass  = Setting::$systemSetting['minPasswordLength'];
            $dateTime           = date("Y-m-d H:i:s");
            $browserInfo = General::getBrowserInfo();
            $ip = $browserInfo["ip"]?$browserInfo["ip"]:"Unknown";
            $ipInfo = General::ip_info($ip);


            // if($otpType == 'email') {
            //     if(!$email){
            //         $errorFieldArr[] = array(
            //             'id'  => 'emailError',
            //             'msg' => $translations["E00318"][$language] /* Please fill in email */
            //         );
            //     } else {
            //         $db->where('email', $email);
            //         $client = $db->getOne('client', 'id, register_method');

            //         if(!$client) {
            //             $errorFieldArr[] = array(
            //                 'id'  => 'emailError',
            //                 'msg' => $translations["E00679"][$language] /* Invalid User. */
            //             );
            //         }
            //     }
            // } else if($otpType == 'phone') {
            //     if(!$dialCode || !$phoneNumber){
            //         $errorFieldArr[] = array(
            //             'id'  => 'phoneError',
            //             'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
            //         );
            //     } else {
            //         $db->where('dial_code',$dialCode);
            //         $db->where('phone',$phoneNumber);

            //         $client = $db->getOne('client', 'id, register_method');

            //         if(!$client) {
            //             $errorFieldArr[] = array(
            //                 'id'  => 'phoneError',
            //                 'msg' => $translations["E00679"][$language] /* Invalid User. */
            //             );
            //         }
            //     }
            // }

            // Check Register Method
            // if($client) {
            //     if($client['register_method'] != $otpType) {
            //         return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00922"][$language] /* Invalid Reset Password Method. */, 'data' => '');
            //     }
            // }

            // if(!$username){
            //     $errorFieldArr[] = array(
            //                                 'id'  => 'usernameError',
            //                                 'msg' => $translations["E00227"][$language] /* Invalid username */
            //                             );
            // }else{
            //     // $db->where('id', $clientID);
            //     $db->where('username', $username);
            //     $clientData = $db->getOne('client', 'id, email');
            //     if(!$clientData || !$clientData['id'] || !$clientData['email']){
            //         return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /* Invalid User */, 'data' => '');
            //     }
            //     $clientID    = $clientData['id'];
            //     $clientEmail = $clientData['email'];
            // }

            if($phone != '60' && $phone){
                $db->where('concat(dial_code, phone)', $phone);
                $db->orWhere('member_id', $phone);
                $clientID = $db->getOne('client', 'id, email, username, concat(dial_code, phone) as phone');

                if(!$clientID){
                    $find = array("%%phoneNumber%%", "%%name%%", "%%ip%%", "%%country%%", "%%dateTime%%","%%issueDesc%%");
                    $replace = array($phone, $clientName, $ip, $ipInfo['country'], $dateTime, $translations["E01253"][$language]/* This mobile number has already been sign up */);
                    $outputArray = Client::sendTelegramMessage('10021', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                   
                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01102"][$language], 'data' => $db->getLastQuery());
                    $errorFieldArr[] = array(
                        'id'  => 'phoneError',
                        'msg' => $translations["E01277"][$language] /* Mobile number no associate with any registered account. Please sign up for a new account */
                    );
                }
            }
            

            if($phone == '60')
            {
                $errorFieldArr[] = array(
                    'id'  => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
                );
            }

            if($phone && $phone != '60'){
                $mobileNumberCheck = General::mobileNumberInfo($phone, "MY");
                if($mobileNumberCheck['isValid'] != 1){
                    $errorFieldArr[] = array(
                        'id'  => 'phoneError',
                        'msg' => $translations["E01093"][$language] /* Invalid mobile number format */
                    );
                }
                $phone = $mobileNumberCheck['phone'];
            }

            if(!$verificationCode)
            {
                $errorFieldArr[] = array(
                    'id'  => 'otpError',
                    'msg' => $translations["E01238"][$language] /* Please fill in OTP code */
                );
            }

            if(!$password){
                $errorFieldArr[] = array(
                    'id'  => 'passwordError',
                    'msg' => $translations["E01248"][$language] /* Please fill in new password. */
                );
            }
            else if(strlen($password) < $minPass || strlen($password) > $maxPass){
                $errorFieldArr[] = array(
                    'id'  => 'passwordError',
                    'msg' => $translations["E00810"][$language] /* Your password must be between 8 and 20 characters long */
                );
            }

            // else if(!ctype_alnum($password)){
            //     $errorFieldArr[] = array(
            //         'id'  => 'passwordError',
            //         'msg' => $translations["E00810"][$language] /* Your password must be between 8 and 20 characters long and can only contain letters and numbers */
            //     );
            // }

    
            // else if(!preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $password)){
            //     $errorFieldArr[] = array(
            //         'id'  => 'passwordError',
            //         'msg' => $translations["E00810"][$language] /* Your password must be between 8 and 20 characters long and can only contain letters and numbers */
            //     );
            // }
            if(!$retypePassword){
                $errorFieldArr[] = array(
                    'id'  => 'checkPasswordError',
                    'msg' => $translations["E01247"][$language] /* Please fill in confirm new password. */
                );
            }
            else if($password != $retypePassword){
                $errorFieldArr[] = array(
                    'id'  => 'checkPasswordError',
                    'msg' => $translations["M01051"][$language] /* The passwords you entered do not match. Please retype your password. */
                );
            }

            // if(!in_array($otpType, array('email', 'phone'))){
            //     $errorFieldArr[] = array(
            //         'id'  => 'otpTypeError',
            //         'msg' => 'Invalid OTP type'
            //     );   
            // }

            // if(!$verificationCode){
            //     $errorFieldArr[] = array(
            //         'id'  => 'verificationCodeError',
            //         'msg' => $verifyCode['statusMsg'] /* Invalid OTP code. */
            //     );      
            // }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                $db->where('username', $phone);
                $clientName = $db->getValue('client', 'name');

                foreach ($errorFieldArr as $key => $value) {
                    $newKey = $key + 1;
                    $newErrorArr[$newKey] = $newKey . '.' . $value['msg'];
                }

                $errorString = implode("\n", $newErrorArr);


                // for ($i = 0; $i < count($errorFieldArr); $i++) {
                    $find = array("%%phoneNumber%%", "%%name%%", "%%ip%%", "%%country%%", "%%dateTime%%","%%issueDesc%%");
                    $replace = array($phone, $clientName, $ip, $ipInfo['country'], $dateTime, $errorString);
                    $outputArray = Client::sendTelegramMessage('10021', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                // } 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            if($phone){
                $db->where('username', $phone);
                $clientName = $db->getValue('client', 'name');
            }

            // $verifyCode = Otp::verifyOTPCode($clientID, $otpType, "resetPassword", $verificationCode);
            // if($verifyCode["status"] != "ok") {
            //     $errorFieldArr[] = array(
            //         'id'  => 'verificationCodeError',
            //         'msg' => $verifyCode['statusMsg'] /* Invalid OTP code. */
            //     );
            // } else {
            //     $otpID = $verifyCode['data'];
            // }

            // if($errorFieldArr) {
            //     $data['field'] = $errorFieldArr;
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            // }
            $verifyCode = Otp::verifyOTPCode($clientID,$otpType,"resetPassword",$verificationCode,$phone);
                    
            if($verifyCode["status"] != "error")
            {
                $db->where('phone_number',$phone);
                $db->where('status','Sent');
                $db->where('msg_type','OTP Code');
                $db->where('verification_type','resetPassword##phone');
                $db->where('code',$verificationCode);
                $fields = array("status");
                $values = array("Verified");
                $arrayData = array_combine($fields, $values);
                $row = $db->update("sms_integration", $arrayData);
            }
            else
            {
                $find = array("%%phoneNumber%%", "%%name%%", "%%ip%%", "%%country%%", "%%dateTime%%","%%issueDesc%%");
                $replace = array($phone, $clientName, $ip, $ipInfo['country'], $dateTime, $translations["E00864"][$language]);
                $outputArray = Client::sendTelegramMessage('10021', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
           
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00864"][$language]/* Invalid OTP code. */, 'data' => $verifyCode);
            }

            $params['step'] = 3;
            
            if(empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
                );
            }
            
            if(!$verificationCode){
                $errorFieldArr[] = array(
                    'id'  => 'verificationCodeError',
                    'msg' => $translations["E01238"][$language] /* Please fill in OTP code */
                );      
            }
            
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }
            
            
            $db->where('concat(dial_code, phone)', $phone);
            $db->orWhere('member_id', $phone);
            $clientID = $db->getOne('client', 'id, email, username, concat(dial_code, phone) as phone');
            
            if(!$clientID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $db->getLastQuery());
            }
            
            if($clientID)
            {
                $verificationRes['status'] = 'ok';
            }
            if($verificationRes['status'] != 'ok') return $verificationRes;

            $db->where('phone_number',$phone);
            $db->where('status','Verified');
            $db->where('msg_type','OTP Code');
            $db->where('verification_type','resetPassword##phone');
            $db->where('code',$verificationCode);
            $fields = array("status");
            $values = array("Success");
            $arrayData = array_combine($fields, $values);
            $row = $db->update("sms_integration", $arrayData);

            $otpID = $verificationRes['data'];

            $db->where('concat(dial_code, phone)', $phone);
            $db->orWhere('member_id', $phone);
            $clientID = $db->getValue('client', 'id');

            $db->where('client_id', $clientID);
            $db->where('name', 'hasChangedPassword');
            $dbChangedPassword = $db->copy();
            $hasChangedPassword = $db->get('client_setting');

            if ($hasChangedPassword) {
                $dbChangedPassword->update('client_setting', array('value'=>'1'));
            } else {
                $insertData = array(
                    "name"           => 'hasChangedPassword',
                    'value'          => '1',                                            
                    'client_id'      => $clientID,                                            
                 );

                $db->insert('client_setting', $insertData);
            }

            $db->where('ID', $clientID);
            $updateData = array(
                'password'          => Setting::getEncryptedPassword($password),
                'encryption_method' => 'bcrypt'
            );
            $db->update('client', $updateData);

            // verify user is Guest or Client
            $db->where('concat(dial_code, phone)', $phone);
            $db->where('type','Guest');
            $GuestAcc = $db->getOne('client');
            if($GuestAcc)
            {
                $password = Setting::getEncryptedPassword($password);
                $updateData = array(
                    'name'          => $GuestAcc['name'],
                    'password'      => $password,
                    'type'          => 'Client',
                    'activated'     => '1',
                    'fail_login'    => '0',
                    'sponsor_id'    => $sponsorID,
                    'updated_at'    => date("Y-m-d H:i:s"),
                );
    
                //update Guest to Client Account
                $db->where('concat(dial_code, phone)',$dialingArea.$phone);
                $db->where('type','Guest');
                $result = $db->update('client',$updateData);
            }

            if($otpID){
                $db->where('id', $otpID, 'IN');
                $db->update('sms_integration', array('expired_at' => $db->now()));
            }
            $db->where('id',$clientID);
            $username = $db->getValue('client','name');
            $find = array("%%phoneNumber%%", "%%name%%", "%%ip%%", "%%country%%", "%%dateTime%%");
            $replace = array($phone, $username, $ip, $ipInfo['country'], $dateTime);
            $outputArray = Client::sendTelegramMessage('10008', NULL, NULL, $find, $replace,"","","telegramAdminGroup");

            // $content = '*Reset Password Message* '."\n\n".'Client ID: '.$clientID."\n".'Phone Number: '.$phone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
            // Client::sendTelegramNotification($content);
            $db->where('phone_number',$phone);
            $db->where('status','Verified');
            $db->where('msg_type','OTP Code');
            $db->where('verification_type','resetPassword##phone');
            $db->where('code',$verificationCode);
            $fields = array("status");
            $values = array("Success");
            $arrayData = array_combine($fields, $values);
            $row = $db->update("sms_integration", $arrayData);
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00530"][$language] /* Successfully reset password */, 'data' => "");
        }

        public function addTransactionPassword($params, $userID) {
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            // $registerType       = trim($params['registerType']);
            $tPassword          = trim($params['tPassword']);
            $checkTPassword     = trim($params['checkTPassword']);

            $maxTPass = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minTPass = Setting::$systemSetting['minTransactionPasswordLength'];
            
            //checking transaction password
            if (empty($tPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'addTPasswordError',
                                            'msg'   => $translations["E00310"][$language] /* Please fill in transaction password */
                                        );
            } else {
                if (strlen($tPassword)<$minTPass || strlen($tPassword)>$maxTPass) {
                    $errorFieldArr[] = array(
                                                'id'  => 'addTPasswordError',
                                                'msg' => $translations["E00311"][$language] /* Transaction password cannot be less than */ . $minTPass . $translations["E00312"][$language] /*  or more than  */ . $maxTPass . '.'
                                            );
                }
            }
            //checking re-type transaction password
            if (empty($checkTPassword)) {
                $errorFieldArr[] = array(
                                            'id'    => 'checkAddTPasswordError',
                                            'msg'   => $translations["E00310"][$language] /* Please fill in transaction password */
                                        );
            } else {
                if ($checkTPassword != $tPassword) {
                    $errorFieldArr[] = array(
                                                'id'  => 'checkAddTPasswordError',
                                                'msg' => $translations["E00313"][$language] /* Transaction password not match */
                                            );
                }
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;

                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>$data);
            }

            $tPassword = Setting::getEncryptedPassword($tPassword);

            $updateData = array('transaction_password' => $tPassword);
            $db->where('id', $userID);
            $db->update('client', $updateData);
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function memberResetTransactionPassword($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $memberId           = $params['memberId'];
            $username           = $params['username'];
            $tPassword           = $params['tPassword'];
            $retypeTPassword     = $params['retypeTPassword'];
            $verificationCode   = $params['verificationCode'];
            // $phoneNumber     = $params['phoneNumber'];
            // $dialCode            = $params['dialCode'];

            // if(!$username)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language], 'data' => array('field'=> 'username'));
            if(!$tPassword)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00310"][$language], 'data' => array('field'=> 'tPassword'));
            if(!$retypeTPassword)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00310"][$language], 'data' => array('field'=> 'retypeTPassword'));
            if($tPassword != $retypeTPassword)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00313"][$language], 'data' => array('field'=> 'retypeTPassword'));

            $maxPass  = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minPass  = Setting::$systemSetting['minTransactionPasswordLength'];
            
            if(strlen($tPassword) < $minPass || strlen($tPassword) > $maxPass)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00193"][$language], 'data' => array('field'=> 'tPassword'));

            if(!ctype_alnum($tPassword)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00193"][$language], 'data' => array('field'=> 'tPassword'));
            }

            if(!preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $tPassword))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00193"][$language], 'data' => array('field'=> 'tPassword'));

            $db->where('concat(dial_code,phone)', $username);
            $db->orWhere('id', $memberId);

            $clientID = $db->getvalue("client","ID");
            if(!$clientID)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language], 'data' => "");

            $db->where('receiver_id', $clientID);
            $db->orderBy('ID', 'DESC');
            $row = $db->getone("sms_integration", "msg");
            $msg = $row['msg'];

            $msg = explode(' ', $msg);

            $db->where('id', $clientID);
            $dialCode = $db->getvalue("client", "dial_code");

            // preg_match_all('!\d+!', $verificationCode, $matches);
            if($dialCode != 212){
                if(!in_array($verificationCode, $msg) || empty($verificationCode)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00997"][$language], 'data' => "");
                }
            }else{
            	if($verificationCode != 12345){
            		return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M00997"][$language], 'data' => "");
            	}
            }

            $db->where('ID', $clientID);
            $updateData = array('transaction_password' => Setting::getEncryptedPassword($tPassword));
            $db->update('client', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00168"][$language], 'data' => "");
        } 

        public function addKYC($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $maxAccPP = Setting::$systemSetting['maxAccPerPhone'];

            $name = trim($params["name"]);
            $nric = trim($params["nric"]);
            $documentType = trim($params["documentType"]);
            $country = trim($params["country"]);
            $address = trim($params["address"]);
            $phone   = trim($params["phone"]);

            // $step = trim($params["step"]);
            // $imageData = $params['imageData'];
            // $selfImageData = $params['selfImageData'];

            $clientID = $db->userID;

            if(empty($clientID)) {
                $clientID = $params['clientID'];
            }

            $db->where("id", $country);
            $dialCode = $db->getValue("country", "country_code");
            if (empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in phone number */
                );
            } else {
                if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00858"][$language] /* Only number is allowed */
                    );
                }

                // check max account per phone
                $db->where("dial_code", $dialCode);
                $db->where("phone", $phone);
                $totalAccThisPhone = $db->getValue("mlm_kyc", "COUNT(*)");
                if (!empty($totalAccThisPhone)) {
                    if($totalAccThisPhone>=$maxAccPP){
                        $errorFieldArr[] = array(
                            'id' => 'phoneError',
                            'msg' => $translations["E00994"][$language] /* Maximum account for this phone has reached. */
                        );
                    }
                }
            }

            

            $status = "Waiting Approval";

            $todayDate = date("Y-m-d H:i:s");

            $maxFName = Setting::$systemSetting['maxFullnameLength'];
            $minFName = Setting::$systemSetting['minFullnameLength'];
            $minNricLength = Setting::$systemSetting['Min NRIC Length'];
            $maxNricLength = Setting::$systemSetting['Max NRIC Length'];

            if(empty($clientID)) {
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Member.", 'data' => "");
            }

            if(empty($name)) {
                $errorFieldArr[] = array(
                    'id'    => 'nameError',
                    'msg'   => $translations["E00296"][$language] /* Please insert full name */
                );
            } else {
                if (strlen($name) < $minFName || strlen($name) > $maxFName) {
                    $errorFieldArr[] = array(
                        'id'    => 'nameError',
                        'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                    );
                }
            }

            if (empty($country) || !ctype_digit($country)) {
                $errorFieldArr[] = array(
                    'id' => 'countryError',
                    'msg' => $translations["E00303"][$language] /* Please select country */
                );
            }

            if(empty($address)) {
                $errorFieldArr[] = array(
                    'id'    => 'addressError',
                    'msg'   => $translations["E00943"][$language] /* Please Insert Address */
                );
            }

            $validDocumentType = array(
                "nric" => 2,
                "passport" => 1
            );

            if(empty($documentType)) {
                $errorFieldArr[] = array(
                    'id'    => 'documentTypeError',
                    'msg'   => $translations["E00769"][$language] /* Please select document type. */
                );
            }else{
                if(!$validDocumentType[$documentType]) {
                    $errorFieldArr[] = array(
                        'id'    => 'documentTypeError',
                        'msg'   => $translations["E00770"][$language]
                    );
                }
            }

            $documentTypeDisplayAry = array(
                "nric" => $translations["B00252"][$language],
                "passport" => $translations["B00253"][$language],
            );

            if($validDocumentType[$documentType]) {
                if(empty($nric)) {
                    $errorMsg = str_replace("%%documentType%%", $documentTypeDisplayAry[$documentType], $translations["E00767"][$language]);
                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errorMsg /* Please insert %%documentType%% number. */
                    );
                } else {
                    if(strlen($nric) < $minNricLength || strlen($nric) > $maxNricLength){
                        $errorMsg = str_replace(array("%%min%%","%%max%%","%%documentType%%"), array($minNricLength,$maxNricLength,$documentTypeDisplayAry[$documentType]), $translations["E00771"][$language]);
                        $errorFieldArr[] = array(
                            'id'    => 'nricError',
                            'msg'   => $errorMsg,
                        );
                    }else{
                        //check image
                        // $imageCount = 0;
                        // if(count($imageData) != $validDocumentType[$documentType]){
                        //     $errorFieldArr[] = array(
                        //                             'id'    => 'imageFile1Error',
                        //                             'msg'   => $translations["E00775"][$language]
                        //                         );
                        // }
                    }
                }
            }

            // if(empty($selfImageData)) {
            //     $errorFieldArr[] = array(
            //         'id'    => 'selfImageError',
            //         'msg'   => $translations["E00775"][$language]
            //     );
            // } else {
            //     if (empty($selfImageData['imageType']) || empty($selfImageData['imageName'])) {
            //         $errorFieldArr[] = array(
            //             'id'    => 'selfImageError',
            //             'msg'   => $translations["E00775"][$language]
            //         );
            //     } else {
            //         $explodeMime = explode("/", $selfImageData['imageType']);
            //         $fileType    = $explodeMime[0];

            //         if($fileType != "image"){
            //             $errorFieldArr[] = array(
            //                 'id'    => 'selfImageError',
            //                 'msg'   => $translations["E00777"][$language]
            //             );
            //         }
            //     }
            // }
            
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // check nric, kyc not allow multiple same nric
            $db->where("type", $documentType);
            $db->where('status', array('Approved','Waiting Approval'), 'IN');
            $db->where("nric", $nric);
            $nricCountRes = $db->get("mlm_kyc", null, "dial_code, phone");
            if($nricCountRes) {
                foreach ($nricCountRes as $key => $value) {
                    if($value['dial_code'] == $dialCode && $value['phone'] == $phone){
                        continue;
                    }
                    if($documentType == 'passport') {
                        $errMsg = $translations["E00881"][$language];
                    } else if($documentType == 'nric') {
                        $errMsg = $translations["E00772"][$language];
                    }

                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errMsg
                    );
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                }
            }
            
            $db->where("client_id", $clientID);
            $db->where("status", array("Waiting Approval","Approved"), "IN");
            $approvedKycCount = $db->getValue("mlm_kyc", "count(id)");
            if($approvedKycCount > 0){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00779"][$language] /* Your KYC is already approved. */, 'data'=> $data);
            }

            $insertData = array(
                "client_id" => $clientID,
                "name" => $name,
                "nric" => $nric,
                "type" => $documentType,
                "dial_code" => $dialCode,
                "phone" => $phone,
                "address" => $address,
                "country_id" => $country,
                "status" => $status,
                "created_at" => $todayDate,
                "updated_at" => $todayDate,
            );

            $db->insert("mlm_kyc",$insertData);

            // if($step == 2){
            //     $groupCode = General::generateUniqueChar("mlm_kyc",array("image_1","image_2","self_image"));

            //     //insertData
            //     foreach ($imageData as $key => $uploadData) {
            //         $key += 1;
            //         unset($insertData);
            //         $fileType = end(explode(".", $uploadData["imageName"]));
            //         $uploadFileName = time()."_".General::generateUniqueChar("mlm_kyc",array("image_1","image_2"))."_".$groupCode.".".$fileType;

            //         $imageAry['image_'.$key] = $uploadFileName;
            //         $returnData['imageName'][] = $uploadFileName;
            //     }

            //     unset($fileType);
            //     unset($uploadFileName);
            //     //Self Image Data
            //     $fileType = end(explode(".", $selfImageData["imageName"]));
            //     $uploadFileName = time()."_".General::generateUniqueChar("mlm_kyc",array("image_1","image_2","self_image"))."_".$groupCode.".".$fileType;

            //     $selfImage = $uploadFileName;

            //     if($selfImage){
            //         $returnData['imageName'][] = $selfImage;
            //     }

            //     $insertData = array(
            //                             "client_id" => $clientID,
            //                             "name" => $name,
            //                             "nric" => $nric,
            //                             "type" => $documentType,
            //                             "image_1" => $imageAry["image_1"],
            //                             "image_2" => $imageAry["image_2"],
            //                             "self_image" => $selfImage,
            //                             "status" => $status,
            //                             "created_at" => $todayDate,
            //                             "updated_at" => $todayDate,
            //                         );

            //     $db->insert("mlm_kyc",$insertData);

            //     $returnData["doRegion"]     = Setting::$configArray["doRegion"];
            //     $returnData["doEndpoint"]   = Setting::$configArray["doEndpoint"];
            //     $returnData["doAccessKey"]  = Setting::$configArray["doApiKey"];
            //     $returnData["doSecretKey"]  = Setting::$configArray["doSecretKey"];
            //     $returnData["doBucketName"] = Setting::$configArray["doBucketName"]."/kyc";
            //     $returnData["doProjectName"]= Setting::$configArray["doProjectName"];
            //     $returnData["doFolderName"] = Setting::$configArray["doFolderName"];

            //     return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00251"][$language], 'data'=> $returnData);
            // }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00251"][$language] /* Successfully Submitted KYC. */, 'data'=> "");
        }

        public function adminEditKYC($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $maxAccPP = Setting::$systemSetting['maxAccPerPhone'];

            $kycID  = trim($params["kycID"]);
            $name   = trim($params["name"]);
            $nric   = trim($params["nric"]);
            $documentType   = trim($params["documentType"]);
            $country        = trim($params["country"]);
            $address        = trim($params["address"]);
            $phone          = trim($params["phone"]);

            // $step = trim($params["step"]);
            // $imageData = $params['imageData'];
            // $selfImageData = $params['selfImageData'];

            $userID = $db->userID;
            $site = $db->userType;
            if($site!="Admin"){
                return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Action.", 'data' => "");
            }

            $db->where("id", $country);
            $dialCode = $db->getValue("country", "country_code");

            if (empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in phone number */
                );
            } else {
                if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00858"][$language] /* Only number is allowed */
                    );
                }

                // check max account per phone
                $db->where("dial_code", $dialCode);
                $db->where("phone", $phone);
                $totalAccThisPhone = $db->getValue("mlm_kyc", "COUNT(*)");
                if (!empty($totalAccThisPhone)) {
                    if($totalAccThisPhone>=$maxAccPP){
                        $errorFieldArr[] = array(
                            'id' => 'phoneError',
                            'msg' => $translations["E00994"][$language] /* Maximum account for this phone has reached. */
                        );
                    }
                }
            }

            $status = "Approved";

            $todayDate = date("Y-m-d H:i:s");

            $maxFName = Setting::$systemSetting['maxFullnameLength'];
            $minFName = Setting::$systemSetting['minFullnameLength'];
            $minNricLength = Setting::$systemSetting['Min NRIC Length'];
            $maxNricLength = Setting::$systemSetting['Max NRIC Length'];

            if(empty($name)) {
                $errorFieldArr[] = array(
                    'id'    => 'nameError',
                    'msg'   => $translations["E00296"][$language] /* Please insert full name */
                );
            } else {
                if (strlen($name) < $minFName || strlen($name) > $maxFName) {
                    $errorFieldArr[] = array(
                        'id'    => 'nameError',
                        'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                    );
                }
            }

            if (empty($country) || !ctype_digit($country)) {
                $errorFieldArr[] = array(
                    'id' => 'countryError',
                    'msg' => $translations["E00303"][$language] /* Please select country */
                );
            }

            if(empty($address)) {
                $errorFieldArr[] = array(
                    'id'    => 'addressError',
                    'msg'   => $translations["E00943"][$language] /* Please Insert Address */
                );
            }

            $validDocumentType = array(
                "nric" => 2,
                "passport" => 1
            );

            if(empty($documentType)) {
                $errorFieldArr[] = array(
                    'id'    => 'documentTypeError',
                    'msg'   => $translations["E00769"][$language] /* Please select document type. */
                );
            }else{
                if(!$validDocumentType[$documentType]) {
                    $errorFieldArr[] = array(
                        'id'    => 'documentTypeError',
                        'msg'   => $translations["E00770"][$language]
                    );
                }
            }

            $documentTypeDisplayAry = array(
                "nric" => $translations["B00252"][$language],
                "passport" => $translations["B00253"][$language],
            );

            if($validDocumentType[$documentType]) {
                if(empty($nric)) {
                    $errorMsg = str_replace("%%documentType%%", $documentTypeDisplayAry[$documentType], $translations["E00767"][$language]);
                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errorMsg /* Please insert %%documentType%% number. */
                    );
                } else {
                    if(strlen($nric) < $minNricLength || strlen($nric) > $maxNricLength){
                        $errorMsg = str_replace(array("%%min%%","%%max%%","%%documentType%%"), array($minNricLength,$maxNricLength,$documentTypeDisplayAry[$documentType]), $translations["E00771"][$language]);
                        $errorFieldArr[] = array(
                            'id'    => 'nricError',
                            'msg'   => $errorMsg,
                        );
                    }else{
                    }
                }
            }
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // check nric, kyc not allow multiple same nric
            $db->where("type", $documentType);
            $db->where('status', array('Approved','Waiting Approval'), 'IN');
            $db->where("nric", $nric);
            $nricCountRes = $db->get("mlm_kyc", null, "dial_code, phone");
            if($nricCountRes) {
                foreach ($nricCountRes as $key => $value) {
                    if($value['dial_code'] == $dialCode && $value['phone'] == $phone){
                        continue;
                    }
                    if($documentType == 'passport') {
                        $errMsg = $translations["E00881"][$language];
                    } else if($documentType == 'nric') {
                        $errMsg = $translations["E00772"][$language];
                    }

                    $errorFieldArr[] = array(
                        'id'    => 'nricError',
                        'msg'   => $errMsg
                    );
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                }
            }

            $db->where("id", $kycID);
            $kycCount = $db->getValue("mlm_kyc", "client_id");
            if(!$kycCount){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00996"][$language] /* KYC not found. */, 'data'=> $data);
            }

            $updateData = array(
                // "client_id" => $clientID,
                "name" => $name,
                "nric" => $nric,
                "type" => $documentType,
                "dial_code" => $dialCode,
                "phone" => $phone,
                "address" => $address,
                "country_id" => $country,
                "status" => $status,
                "created_at" => $todayDate,
                "updated_at" => $todayDate,
                "approved_at" => $todayDate,
                "updater_id" => $userID,
            );

            $db->where("id", $kycID);
            $db->update("mlm_kyc",$updateData);

            $db->where("id", $kycCount);
            $db->update("client", array("name" => $name, "dial_code" => $dialCode,"phone" => $phone));
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00251"][$language] /* Successfully Submitted KYC. */, 'data'=> "");
        }

        public function getKYCDetails($params){
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $params['clientID'];

            $db->where('id', $clientID);
            $member = $db->getOne('client', 'member_id, passport, identity_number');
            if(!$member){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Member", 'data' => "");
            }

            $memberID = $member['member_id'];
            $passport = $member['passport'];
            $identityNumber = $member['identity_number'];
            $idType = $identityNumber > 0 ? "nric" : "passport";

            $temp['Bank Account Cover']['record'] = 0;
            $temp['NPWP Verification']['record'] = 0;
            $temp['ID Verification']['record'] = 0;


            $db->where("client_id", $clientID);
            $db->orderBy("id", 'ASC');
            $kycAry = $db->get("mlm_kyc", NULL, "id, doc_type, remark, status");

            if (empty($kycAry)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
            }


            foreach ($kycAry as $kyc) {
                $kycIDAry[$kyc['doc_type']] = $kyc['id'];
                $kycIDStatus[$kyc['id']]['status'] = $kyc['status'];
                $kycIDStatus[$kyc['id']]['remark'] = $kyc['remark'];
            }

            if($kycIDAry){
                $db->where('kyc_id', array_values($kycIDAry), 'IN');
                $kycDetails = $db->get('mlm_kyc_detail', null, "id, kyc_id, name, value, description");
            }

            foreach ($kycIDAry as $docType => $kycID) {
                if($docType == 'ID Verification'){
                    $temp[$docType]['idType']  = General::getTranslationByName($idType);
                }
                foreach($kycDetails as $value){     
                    if($value['kyc_id'] == $kycID){ 
                        $temp[$docType][$value['name']] = $value['value'];
                        if($value['name'] == 'Address'){   
                           $temp[$docType][$value['name']] = $value['description'];
                        }
                    }
                }
                $temp[$docType]['kycID']  = $kycID;
                $temp[$docType]['status'] = $kycIDStatus[$kycID]['status'];
                $temp[$docType]['remark'] = $kycIDStatus[$kycID]['remark'];
                $temp[$docType]['memberID'] = $memberID;
                $temp[$docType]['record'] = 1;
            }

            $db->where('kyc_id', array_values($kycIDAry), 'IN');
            $db->where('name', 'notificationCount');
            $db->update('mlm_kyc_detail', array('value' => '0'));

            $data = $temp;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getKYCDataByID($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $kycID = trim($params['kycID']);
            $statusDisplayAry = array(
                "Waiting Approval" => $translations["B00254"][$language],
                "New" => $translations["B00255"][$language],
                "Approved" => $translations["B00256"][$language],
                "Rejected" => $translations["B00259"][$language],
            );

            $genderDisplayAry = array(
                "male" => $translations["B00257"][$language],
                "female" => $translations["B00258"][$language],
            );

            $documentTypeDisplayAry = array(
                "nric" => $translations["B00252"][$language],
                "passport" => $translations["B00253"][$language],
            );

            if(empty($kycID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid KYC", 'data' => "");

            $db->where("id",$kycID);
            $kycRow = $db->getOne('mlm_kyc', 'id, client_id, name, phone, address, nric, country_id, type, image_1 as image1, image_2 as image2, self_image as selfImage, status, created_at, updated_at, approved_at, updated_at, updater_id, remark');

            if(empty($kycRow)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid KYC", 'data' => "");
            }

            if($kycRow["client_id"]){
                $db->where("id",$kycRow["client_id"]);
                $clientRow = $db->getOne("client","id, member_id, username, name");
                $kycData["memberID"] = $clientRow["member_id"];
                $kycData["username"] = $clientRow["username"];
                $kycData["fullName"] = $clientRow["name"];
            }

            if($kycRow["updater_id"]){
                $db->where("id",$kycRow["updater_id"]);
                $adminRow = $db->getOne("admin","id, username");
                $kycData["updaterUsername"] = $adminRow["username"];
            }

            if($kycRow["image1"]){
                $kycData["imageData1"] = $kycRow["image1"];
            }

            if($kycRow["image2"]){
                $kycData["imageData2"] = $kycRow["image2"];
            }

            if($kycRow["selfImage"]){
                $kycData["selfImage"] = $kycRow["selfImage"];
            }

            if($kycRow["country_id"]){
                $db->where("id",$kycRow["country_id"]);
                $countryRow = $db->getOne("country","id,name,translation_code");
                $kycData["countryDisplay"] = $translations[$countryRow["translation_code"]][$language];
            }

            $kycData["accountHolderName"] = $kycRow["name"];
            $kycData["address"] = $kycRow["address"];
            $kycData["nric"] = $kycRow["nric"];
            $kycData["documentTypeDisplay"] = $documentTypeDisplayAry[$kycRow["type"]];
            $kycData["status"] = $statusDisplayAry[$kycRow["status"]];
            $kycData["createdAt"] = $kycRow["created_at"];
            $kycData["updatedAt"] = $kycRow["updated_at"];
            $kycData["approvedAt"] = strtotime($kycRow["approved_at"]) > 0 ? $kycRow["approved_at"] : "-";
            $kycData["remark"] = $kycRow["remark"];

            $data["kycData"] = $kycData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function updateKYC($params) { 
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $adminID = $db->userID;

            $kycIDAry = $params['kycIDAry'];
            $remark = trim($params['remark']);
            $status = $params['status'];

            $validStatusAry = array("Waiting Approval", "Approved", "Rejected");

            $todayDate = date("Y-m-d H:i:s");

            if(empty($kycIDAry))
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Nothing was updated', 'data' => "");

            if(!in_array($status, $validStatusAry))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language], 'data' => "");

            foreach($kycIDAry as $kycID){
                $db->where("id",$kycID);
                $kycRow = $db->getOne("mlm_kyc","id, status, client_id, doc_type");

                if(empty($kycRow)){
                    continue;
                }

                if($kycRow["status"] == "Rejected" || $kycRow["status"] == "Approved"){
                    continue;
                }

                if($kycRow["status"] == $status){
                    continue;
                }

                $updateData["status"] = $status;
                $updateData["updated_at"] = $todayDate;
                $updateData["updater_id"] = $adminID;
                $updateData["remark"] = $remark;

                $db->where("id",$kycID);
                $db->update("mlm_kyc",$updateData);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'KYC Status Updated', 'data' => "");
        }

        public function getKYCListing($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $userID = $db->userID;
            $site = $db->userType;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'] ? $params['seeAll'] : 0;
            $limit = General::getLimit($pageNumber);
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if(count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {

                        case "mainLeaderUsername":
                            $db->where('username', $dataValue);
                            $mainLeaderID  = $db->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 

                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataType = trim($v['dataType']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'username': 
                            if ($dataType == "like") {
                                $db->where('username', '%'.$dataValue.'%', 'LIKE');
                            } else {
                                $db->where('username', $dataValue);
                            }
                            break;

                        case 'fullName':
                            if ($dataType == "like") {
                                $db->where('name', '%'.$dataValue.'%', 'LIKE');
                            } else {
                                $db->where('name', $dataValue);
                            }
                            break;

                        case 'memberID':
                            $db->where("member_id", $dataValue);
                            break;

                        case 'createdAt':
                            // Set db column here
                            $columnName = 'date(created_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }

                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                            }
                            $sq = $db->subQuery();
                            $sq->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            $sq->where($columnName, date('Y-m-d', $dateTo), '<=');
                            $sq->get('mlm_kyc', NULL, 'client_id');
                            $db->where('id', $sq, 'IN');
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            // Set db column here
                            $columnName = 'date(updated_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                            }

                            $maxSQ = $db->subQuery();
                            $maxSQ->groupBy('client_id');
                            $maxSQ->groupBy('doc_type');
                            $maxSQ->get('mlm_kyc', NULL, 'MAX(id)');

                            $sq = $db->subQuery();
                            $sq->where('id', $maxSQ, 'IN');
                            $sq->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            $sq->where($columnName, date('Y-m-d', $dateTo), '<=');
                            $sq->get('mlm_kyc', NULL, 'client_id');
                            $db->where('id', $sq, 'IN');
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'status':
                            $statusValue = $dataValue;
                            break;

                        case 'docType':
                            $docTypeValue = $dataValue;
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($statusValue || $docTypeValue){
                $maxSQ = $db->subQuery();
                $maxSQ->groupBy('client_id');
                $maxSQ->groupBy('doc_type');
                $maxSQ->get('mlm_kyc', NULL, 'MAX(id)');

                if($statusValue && $docTypeValue){
                    if($docTypeValue == 'Email Verification'){
                        $nameSq = $db->subQuery();
                        $nameSq->where('id', $maxSQ, 'IN');
                        if($statusValue == 'Approved'){
                            $nameSq->where("email_verified", '1');
                        }else if($statusValue == 'Waiting Approval'){
                            $nameSq->where("email_verified", '0');
                        }else{
                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No results found */, 'data' => "");
                        }
                        $nameSq->get('client_detail', NULL, 'client_id');
                        $db->where('id', $nameSq, 'IN');
                    }else{
                        $sq = $db->subQuery();
                        $sq->where('id', $maxSQ, 'IN');
                        $sq->where("doc_type", $docTypeValue);
                        $sq->where("status", $statusValue);
                        $sq->get('mlm_kyc', NULL, 'client_id'); 
                        $db->where('id', $sq, 'IN');
                    }
                }elseif($statusValue){
                    $sq = $db->subQuery();
                    $sq->where('id', $maxSQ, 'IN');
                    $sq->where("status", $statusValue);
                    $sq->get('mlm_kyc', NULL, 'client_id');
                    $db->where('id', $sq, 'IN');
                }elseif($docTypeValue){
                    $sq = $db->subQuery();
                    $sq->where('id', $maxSQ, 'IN');
                    if($docTypeValue == 'Email Verification'){
                        $sq->where("email_verified", '1');
                        $sq->get('client_detail', NULL, 'client_id');                                
                    } else {
                        $sq->where("doc_type", $docTypeValue);
                        $sq->get('mlm_kyc', NULL, 'client_id');
                    }
                    $db->where('id', $sq, 'IN');
                }
            }
            $db->where('type', 'Client');
            $copyDb = $db->copy();
            $db->orderBy('kyc_status', 'DESC');
            $kycClientRes = $db->get('client', $limit, 'id, (SELECT status FROM mlm_kyc WHERE client.id = mlm_kyc.client_id ORDER BY FIELD(status, "Waiting Approval", "Rejected", "Approved") LIMIT 1) AS kyc_status');
            if(!$kycClientRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No results found */, 'data' => "");
            }
            foreach($kycClientRes as $kyc){
                $clientIDAry[$kyc["id"]] = $kyc["id"];
            }

            if($clientIDAry){
                $db->where("id", $clientIDAry, "IN");
                $clientDataAry = $db->map("id")->get("client", null, "id, username, name, member_id");

                $db->where("client_id", $clientIDAry, "IN");
                $clientEmailVerified = $db->map("client_id")->get("client_detail", null, "client_id, email_verified");

                $db->where("client_id", $clientIDAry, "IN");
                $db->orderBy("created_at","ASC");
                $kycDocAry = $db->get("mlm_kyc", NULL, "id, client_id, doc_type, status, updated_at, updater_id, created_at");
                foreach ($kycDocAry as $kycDoc) {
                    $allClientKYCIDs[$kycDoc['id']] = $kycDoc['client_id'];
                    $updaterIDAry[$kyc["updater_id"]] = $kyc["updater_id"];
                    $clientKYCDoc[$kycDoc['client_id']][$kycDoc['doc_type']] = $kycDoc['status'];
                    $kycUpdateDetails[$kycDoc['client_id']]['updated_at'] = $kycDoc['updated_at'];
                    $kycUpdateDetails[$kycDoc['client_id']]['updater_id'] = $kycDoc['updater_id'];
                    if(!$kycUpdateDetails[$kycDoc['client_id']]['created_at']){
                        $kycUpdateDetails[$kycDoc['client_id']]['created_at'] = $kycDoc['created_at'];
                    }
                }

                if($allClientKYCIDs){
                    $db->where('kyc_id', array_keys($allClientKYCIDs), 'IN');
                    $db->where('name', 'notificationCount');
                    $notifyCount = $db->get('mlm_kyc_detail', NULL, 'kyc_id, value');
                    foreach ($notifyCount as $kycNofity) {
                        $clientIDNotify[$allClientKYCIDs[$kycNofity['kyc_id']]] += $kycNofity['value'];
                    }
                }
            }

            if($updaterIDAry) {
                $db->where("id", $updaterIDAry, "IN");
                $adminDataAry = $db->map("id")->get("admin", null, "id, username");
            }

            foreach($kycClientRes as $kycRow) {
                unset($temp);

                $temp["ID Verification"]     = '-';
                $temp["Bank Account Cover"]  = '-';
                $temp["NPWP Verification"]   = '-';
                $memberKYCDoc = $clientKYCDoc[$kycRow["id"]];
                foreach ($memberKYCDoc as $docType => $verifyStatus) {
                    $temp[$docType] = $verifyStatus;
                }

                $temp["clientID"] = $kycRow["id"];
                $temp["memberID"] = $clientDataAry[$kycRow["id"]]["member_id"];
                $temp["username"] = $clientDataAry[$kycRow["id"]]["username"];
                $temp["name"]     = $clientDataAry[$kycRow["id"]]["name"];
                $temp["emailVerified"] = $clientEmailVerified[$kycRow["id"]] ? 'Approved' : '-';

                $updateAt       = $kycUpdateDetails[$kycRow["id"]]['updated_at'];
                $updatedID      = $kycUpdateDetails[$kycRow["id"]]['updater_id'];
                $createdAt      = $kycUpdateDetails[$kycRow["id"]]['created_at'];
                if($updateAt){
                    $temp["updatedAt"] = $updateAt != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($updateAt)) : "-";    
                } else {
                    $temp["updatedAt"] = '-';
                }

                if($createdAt){
                    $temp["createdAt"] = $createdAt != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($createdAt)) : "-";
                } else {
                    $temp["createdAt"] = '-';
                }
                
                $temp["updaterID"] = $adminDataAry[$updatedID] ?: '-';
                $temp["unreadCount"] = $clientIDNotify[$kycRow['id']] > 0 ? $clientIDNotify[$kycRow['id']] : '0';
                
                $kycList[] = $temp;
            }
            
            $totalRecord = $copyDb->getValue('client', 'count(id)');
            $data['kycList'] = $kycList;
            $data['pageNumber'] = $pageNumber;

            if($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } else {
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['totalRecord'] = $totalRecord;

            $db->where('admin_id',$userID);
            $db->where('type', "kyc");
            $db->update('admin_notification',array("notification_count" => 0));

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getImageByID($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $imageID = $params['imageID'];

            $db->where('id', $imageID);
            $imageBase64 = $db->getValue('uploads', 'data');
            $data['imageBase64'] = $imageBase64;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        // --- Bank Start --// -- need tune

        public function getAvailableCreditWalletAddress($site){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $cryptoCreditList = Wallet::getCryptoCredit(false, false);
            // $acceptCoinType = json_decode(Setting::$systemSetting['acceptCoinType']);
            $data['creditList'] = $cryptoCreditList;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function addBankAccountDetailVerification($params) {
            $db = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;
            $clientID = $db->userID;
            $site = $db->userType;
            $dateTime = date("Y-m-d H:i:s");

            if(!$clientID || $site == 'Admin'){
                $clientID      = $params['clientID'];
            }

            $accountHolder = $params['accountHolder'];
            $bankID        = $params['bankID'];
            $accountNo     = $params['accountNo'];
            $province      = $params['province'];
            $bankCity      = $params['bankCity'];
            $branch        = $params['branch'];
            $tPassword     = $params['tPassword'];

            if (empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00461"][$language] /* Member not found. */, 'data'=> "");

            if (empty($accountHolder))
                $errorFieldArr[] = array(
                                            'id'  => "accHolderNameError",
                                            'msg' => $translations["E00462"][$language] /* Please enter account holder name. */
                                        );
            if (empty($bankID)){
                $errorFieldArr[] = array(
                                            'id'  => "bankTypeError",
                                            'msg' => $translations["E00463"][$language] /* Please enter a bank. */
                                        );
            }else{
                $db->where('id',$bankID);
                $db->where('status','Active');
                $checkBank = $db->getValue('mlm_bank','id');
                if (empty($checkBank))
                    $errorFieldArr[] = array(
                                            'id'  => "bankTypeError",
                                            'msg' => $translations["E00463"][$language] /* Please enter a bank. */
                                        );
            }

            if (empty($accountNo))
                $errorFieldArr[] = array(
                                            'id'  => "accountNoError",
                                            'msg' => $translations["E00464"][$language] /* Please enter account number. */
                                        );
            // if (empty($province))
            //     $errorFieldArr[] = array(
            //                                 'id'  => "provinceError",
            //                                 'msg' => $translations["E00465"][$language] /* Please enter province. */
            //                             );

            if (empty($bankCity))
                $errorFieldArr[] = array(
                                            'id'  => "bankCityError",
                                            'msg' => $translations["E01033"][$language] /* Please enter province. */
                                        );
            if (empty($branch))
                $errorFieldArr[] = array(
                                            'id'  => "branchError",
                                            'msg' => $translations["E00466"][$language] /* Please enter branch. */
                                        );
            /*This project did not check Transaction Password*/
            // if($site != 'Admin'){
            //     /* check transaction password */
            //     if (empty($tPassword)){
            //         $errorFieldArr[] = array(
            //                                     'id'  => "tPasswordError",
            //                                     'msg' => $translations["E00128"][$language] /* Please enter transaction password. */
            //                                 );

            //     } else {
            //         $tPasswordResult = Self::verifyTransactionPassword($clientID, $tPassword);
            //         if($tPasswordResult['status'] != "ok") {
            //             $errorFieldArr[] = array(
            //                                         'id'  => 'tPasswordError',
            //                                         'msg' => $translations["E00468"][$language] /* Invalid password. */
            //                                     );
            //         }
            //     }
            //     /* END check transaction password */
            // }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => '');
        }

        public function addBankAccountDetail($params) {
            $db = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;
            $clientID = $db->userID;
            $site = $db->userType;
            $dateTime = date("Y-m-d H:i:s");

            if(!$clientID || $site == 'Admin'){
                $clientID      = $params['clientID'];
            }

            $accountHolder = $params['accountHolder'];
            $bankID        = $params['bankID'];
            $accountNo     = $params['accountNo'];
            $province      = $params['province'];
            $bankCity      = $params['bankCity'];
            $branch        = $params['branch'];
            $tPassword     = $params['tPassword'];

            $validationResult = self::addBankAccountDetailVerification($params);

            if(strtolower($validationResult['status']) != 'ok'){
                return $validationResult;
            }

            //one bank one active account
            $db->where('bank_id',$bankID);
            $db->where('client_id',$clientID);
            $db->where('status','Active');
            $checkID = $db->getValue('mlm_client_bank','id');
            if ($checkID){
                $db->where('id', $checkID);
                $db->update('mlm_client_bank', array('status' => 'Inactive'));
            }

            $insertClientBankData = array(
                                        "client_id"      => $clientID,
                                        "bank_id"        => $bankID,
                                        "account_no"     => $accountNo,
                                        "account_holder" => $accountHolder,
                                        "created_at"     => $dateTime,
                                        "status"         => 'Active',
                                        "province"       => $province,
                                        "bank_city"      => $bankCity,
                                        "branch"         => $branch

                                     );

            $insertClientBankResult  = $db->insert('mlm_client_bank', $insertClientBankData);
            // Failed to insert client bank account
            if (!$insertClientBankResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00470"][$language] /* Failed to add bank account. */, 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00168"][$language] /* Update successful */, 'data' => $data);
        }

        public function addWalletAddress($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            $userID = $db->userID;
            $site = $db->userType;

            $creditType          = trim($params['creditType']);
            $walletAddress       = trim($params['walletAddress']);
            $transactionPassword = trim($params['transactionPassword']);

            //check transaction password
            if(!$transactionPassword)
                return array("status" => "error", "code" => 1, "statusMsg" => $translations['E00128'][$language] /* Please enter transaction password */ , "data" => "");

            $result = self::verifyTransactionPassword($userID,$transactionPassword);
            if($result['status'] != 'ok'){
                $errorFieldArr= $result['data']['field'];
            }
            
            //check coin types
            $acceptCoinType = Wallet::getCryptoCredit(true, false);
            if(!trim($creditType) || !array_key_exists($creditType, $acceptCoinType)) {
                $errorFieldArr[] = array(
                    'id' => 'creditTypeError',
                    'msg' => $translations['E00747'][$language] /* Please choose a crypto currency */,
                );
            }

            $coinsWithTagAry = array('ripple','eos');
            if (in_array($creditType, $coinsWithTagAry)){
                // new coins that require tag
                $tag = trim($params['tag']);
                if ($tag) {
                    $walletAddress = $walletAddress . ":::ucl:::" . $tag;
                } else {
                    $errorFieldArr[] = array(
                        'id' => 'tagError',
                        'msg' => $translations["E00218"][$language]
                    );
                }
            }

            //check wallet address
            if(!$walletAddress || $walletAddress == "") {
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01941"][$language]/* Wallet Address cannot be empty. */
                );
            } else if (strlen($walletAddress) < 30) {
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                );
            } elseif ($creditType == "tether" && $walletAddress[0] != "0") {
                /* if tether address must start from 0 */
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                );
            } elseif ($creditType == "tronUSDT" && $walletAddress[0] != "T") {
                $errorFieldArr[] = array(
                    'id' => 'walletAddressError',
                    'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                );
            }

            // $db->where('info',$walletAddress);
            // $db->where('credit_type',$creditType);
            // $db->where('status','Active');
            // $isExist = $db->has('mlm_client_wallet_address');
            // if ($isExist) {
            //     $errorFieldArr[] = array(
            //         'id' => 'walletAddressError',
            //         'msg' => $translations["E00912"][$language] /* This wallet address is already occupied. */
            //     );
            // }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            // $db->where('client_id', $userID);
            // $db->where('credit_type', $creditType);
            // $db->where('status', 'Active');
            // $getExistingWalletAddress = $db->get('mlm_client_wallet_address', NULL, 'id');
            // if($getExistingWalletAddress){
            //     $db->where('client_id', $userID);
            //     $db->where('credit_type', $creditType);
            //     $db->where('status', 'Active');
            //     $db->update('mlm_client_wallet_address', array('status' => 'Inactive'));
            // }

            $db->where('id', $userID);
            $username = $db->getValue('client', 'username');
            $insertData = array(
                "id"                => $db->getNewID(),
                "client_id"         => $userID,
                "credit_type"       => $creditType,
                "info"              => $walletAddress,
                // "type"              => $walletType,
                // "wallet_provider"   => $walletProvider,
                "created_at"        => date("Y-m-d H:i:s"),
                "status"            => 'Active',
                "updater_id"        => $userID,
                "updater_username"  => $username,
            );
            $recordID = $db->insert("mlm_client_wallet_address", $insertData);
            
            // Failed to insert client bank account
            if (!$recordID)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00817"][$language] /* Failed to add wallet address. */, 'data' => "");

            $queueData['checkedIDs'] = array($recordID);
            $queueData['status']       = 'WhiteList';
            $queueData['address']    = $walletAddress;

            $insertQueue = array(
                "queue_type" => "autoWhitelistWalletAddress",
                "client_id"  => $userID,
                "data"       => json_encode($queueData),
                "created_at" => date('Y-m-d H:i:s'),
                "processed"  => 0,
            );
            $db->insert('queue',$insertQueue);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00599"][$language], 'data' => $data);
        }

        public function getAllBankAccountDetail($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            // get bank list
            $db->where('status', "Active");
            $bankDetail   = $db->get("mlm_bank ", $limit, "id, name, translation_code");
            if (empty($bankDetail))
                $bankDetail = '';

            foreach($bankDetail AS &$bankData){
                $bankData['display'] = $translations[$bankData['translation_code']][$language];
            }

            $data['bankDetails']      = $bankDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getMemberBankList($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = $params['clientID'];
            $creditType = $params['creditType'];

            if(empty($clientID) || empty($creditType))
                return array('status' => "error", 'code' => 0, 'statusMsg' => $translations["E00391"][$language] /* Failed to load bank list */, 'data' => "");

            $countryID = $db->subQuery();
            $countryID->where('id', $clientID);
            $countryID->get('client', null, 'country_id');

            $db->where('id', $countryID);
            $country = $db->getOne('country', 'id, name');

            $bankIDs = $db->subQuery();
            $bankIDs->where('country_id', $country['id']);
            $bankIDs->get('mlm_bank', null, 'id');

            $db->where('client_id', $clientID);
            $db->where('bank_id', $bankIDs, 'IN');
            $db->where('status', "Active");
            $getBankName = "(SELECT name FROM mlm_bank WHERE mlm_bank.id=bank_id) AS bank_name";
            $banks = $db->get('mlm_client_bank', null, 'bank_id, '.$getBankName.', account_no, account_holder, province, branch');

            $balance = Cash::getBalance($clientID, $creditType);

            $data['balance'] = $balance;
            $data['clientBankList'] = $banks;
            $data['country'] = $country;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getWalletAddressListing($params,$clientID){

            $db = MysqliDb::getInstance();
            
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit           = General::getLimit($pageNumber);

            $site = $db->userType;

            // Means the search params is there
            if (count($searchData) > 0) {

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'leaderUsername':

                            $clientID = $db->subQuery();
                            $clientID->where('username', $dataValue);
                            $clientID->getOne('client', "id");

                            $downlines = Tree::getSponsorTreeDownlines($clientID);
                            // $downlines[] = $clientID;

                            if (empty($downlines))
                                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

                            $db->where('client_id', $downlines, "IN");

                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }

                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'username':
                            $sq = $db->subQuery();
                            if ($dataType == "like") {
                                $sq->where("username", "%".$dataValue."%", "LIKE");
                            }else{
                                $sq->where("username", $dataValue);
                            }
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN"); 
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "IN");
                            break;

                        case 'creditType':
                            $db->where('credit_type', $dataValue);
                            break;

                        case 'walletType':
                            $db->where('type', $dataValue); //deposit / withdrawal
                            break;

                        case 'whiteList':

                            if (strtolower($dataValue) =="yes"){
                                $dataValue = 1;
                                $db->where("isWhitelisted",$dataValue);
                            }else if(strtolower($dataValue) == "no"){
                                $dataValue = 0;
                                $db->where("isWhitelisted",$dataValue);
                            }
                
                             break;

                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }
            else{
                //If no searchData, show only Active
                // $db->where('status','Active');
            }
            
            // $db->where('status','Active');
            if($site == 'Member') $db->where('client_id',$clientID);
            $dbSearch=$db->copy();
            $db->orderBy('status', 'ASC');
            $db->orderBy('created_at', 'DESC');
            $result=$db->get('mlm_client_wallet_address',$limit,'id,client_id,credit_type,info,type,wallet_provider,created_at,status, isWhitelisted, error_msg');
            // $creditListDisplay=Self::getCreditDisplay();
            $cryptoCreditListDisplay = Wallet::getCryptoCredit(true);
            foreach ($result as $res) {
                $clientIDAry[$res['client_id']] = $res['client_id'];
            }

            if($clientIDAry){
                $db->where('id', $clientIDAry, 'IN');
                $clientDataAry = $db->map('id')->get('client', NULL, 'id, member_id, username, name');
            }

            foreach ($result as $key => $row) {

                if($row["status"] == "Active") $row["statusDisplay"] = $translations["A00372"][$language];
                else if($row["status"] == "Inactive") $row["statusDisplay"] = $translations["M00330"][$language];
                else $row["statusDisplay"] = "-";

                // $row["creditTypeDisplay"] = $creditListDisplay[$row['credit_type']];
                $row["creditTypeDisplay"] = $cryptoCreditListDisplay[$row['credit_type']];

                // if($row["creditType"] == "bitcoin") $row["creditTypeDisplay"] = $translations["M01898"][$language];
                // else if($row["creditType"] == "ETH") $row["creditTypeDisplay"] = $translations["M01899"][$language];
                // else if($row["creditType"] == "USDT") $row["creditTypeDisplay"] = $translations["M01900"][$language];
                // else $row["statusDisplay"] = "-"; 
                if(!$row['wallet_provider'])
                    $row['wallet_provider'] = '-';

                $row['created_at'] = date("d/m/Y H:i:s", strtotime($row['created_at']));

                if($site == 'Admin'){
                    $row['fullname'] = $clientDataAry[$row['client_id']]['name'] ?: "-";
                    $row['username'] = $clientDataAry[$row['client_id']]['username'] ?: "-";
                    $row['memberID'] = $clientDataAry[$row['client_id']]['member_id'] ?: "-";
                    $row['isWhitelisted'] = $row['isWhitelisted'] ? "Yes" : "No";

                    if($row['isWhitelisted'] == "Yes"){
                        unset($row['error_msg']);
                    }

                } else {
                    unset($row['isWhitelisted']);
                    unset($row['error_msg']);
                }

                $data["dataList"][] = $row;      
            }

            $totalRecord=$dbSearch->getValue('mlm_client_wallet_address','COUNT(id)');
            if ($totalRecord==0){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00733'][$language], 'data' => '');
            }
            $data["totalRecord"] = $totalRecord;
            $data["pageNumber"] = $pageNumber;
            $data['numRecord']   = $limit[1];
            $data["totalPage"] = ceil($totalRecord/$limit[1]);
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function inactiveWalletAddress($params,$clientID){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $addressID = (string)$params['addressID'];
            // $type = (string)$params[''];

            // Set the person who record the changes
            // Client::creatorID = $clientID;
            // Client::creatorUsername = Client::clientData['username'];
            // Client::creatorType = $source;

            $db->where('client_id',$clientID);
            $db->where('id',$addressID);
            $db->where('status','Active');
            $updatedb=$db->copy();
            $verify=$db->getOne('mlm_client_wallet_address',null,'info, credit_type');
            if ($verify){
                $status = "Inactive";
                $updateData = array('status' => "$status");
                $updatedb->update("mlm_client_wallet_address",$updateData);

                $updateData = array("value" => "0", "type"=>"crypto", "reference"=>$creditType);
                // $res = self::updateAutoWithdrawalStatus($updateData,$clientID);

                // Insert activity log
                Activity::insertActivity("Update Wallet Address Status", "Wallet Address Listing",$status,$verify, $clientID);

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00271'][$language]/* Canceled Successfully */, 'data' => $resData);
            }
            else{
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['B00272'][$language]/* Cannot cancel this wallet address */, 'data' => "");
            }
        }

        public function inactiveBankAccount($params,$clientID){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $ID = (string)$params['checkedIDs'];

            $db->where('client_id',$clientID);
            $db->where('id',$ID);
            $db->where('status','Active');
            $updatedb=$db->copy();
            $verify=$db->getOne('mlm_client_bank',null,'account_no, bank_id');
            if ($verify){
                $status = "Inactive";
                $updateData = array('status' => "$status");
                $updatedb->update("mlm_client_bank",$updateData);

                $updateData = array("value" => "0", "type"=>"bank", "reference"=>"0");
                // $res = self::updateAutoWithdrawalStatus($updateData,$clientID);

                // Insert activity log
                Activity::insertActivity("Update Bank Account Status", "Bank Account",$status,$verify, $clientID);

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00414'][$language]/* You successfully deactivate a Bank Account. */, 'data' => $resData);
            }
            else{
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['B00415'][$language]/* Fail to cancel bank account */, 'data' => "");
            }
        }

        public function getWithdrawalListing($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $clientID = $db->userID;

            // $creditType = $params['creditType'];
            // $clientID = $params['clientID'];
            $searchData = $params['searchData'];

            if(empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00221"][$language] /* Required fields cannot be empty. */, 'data' => "");

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = General::getLimit($pageNumber);
            
            $res = $db->get("credit",NULL,"type,translation_code");
            foreach($res AS $row){
            	$creditDisplayList[$row['type']] = $translations[$row['translation_code']][$language];
            }
            // $creditDisplayList=Self::getCreditDisplay();
            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'creditType':
                            $db->where('credit_type', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'withdrawalType':
                            if($dataValue != "all"){
                                $db->where('withdrawal_type', $dataValue);
                                break;
                            }

                        case 'cryptoType':
                            if($dataValue != "all"){
                                $db->where('crypto_type', $dataValue);
                                break;
                            }

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);

                                if($dateTo == $dateFrom)
                                    $dateTo += 86399;
                                $db->where('created_at', date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            // $db->where('credit_type', $creditType);
            $db->where('client_id', $clientID);
            // $getCountryName = "(SELECT name FROM country WHERE country.id=(SELECT country_id FROM mlm_bank WHERE mlm_bank.id=bank_id)) AS country_name";
            // $getAccountHolderName = "(SELECT account_holder FROM mlm_client_bank WHERE mlm_client_bank.client_id=mlm_withdrawal.client_id AND mlm_client_bank.bank_id=mlm_withdrawal.bank_id AND mlm_client_bank.account_no=mlm_withdrawal.account_no) AS account_holder";
            // $getBankName = "(SELECT name FROM mlm_bank WHERE mlm_bank.id=bank_id) AS bank_name";
            // $getProvince = "(SELECT province FROM mlm_client_bank WHERE mlm_client_bank.client_id=mlm_withdrawal.client_id AND mlm_client_bank.bank_id=mlm_withdrawal.bank_id AND mlm_client_bank.account_no=mlm_withdrawal.account_no) AS province";
            // $getCredit = "(SELECT content FROM language_translation WHERE code = (SELECT translation_code FROM credit WHERE name = credit_type) AND language = '".$language."') AS creditDisplay";
            $copyDb = $db->copy();
            $db->orderBy("created_at", "DESC");
            /*
               $column = 'id,
               status,
               (SELECT name FROM country WHERE id = (SELECT country_id FROM client WHERE id = client_id)) AS country,
               (SELECT username FROM client WHERE id = client_id) AS username ,
               (SELECT CONCAT(dial_code, "", phone) FROM client WHERE id = client_id) AS phone,
               (SELECT name FROM client WHERE id = client_id) AS name,
               receivable_amount, 
               charges,  
               amount, 
               walletAddress,
               created_at,
               credit_type'; 
            */
            $column = '
                id,
                amount,
                status,
                remark,
                created_at,
                estimated_date,
                bank_id,
                (SELECT translation_code FROM mlm_bank WHERE id = bank_id) AS bank_name,
                branch,
                bank_city,
                account_no,
                approved_at,
                credit_type,
                crypto_type,
                receivable_amount,
                charges,
                currency_rate,
                converted_amount,
                withdrawal_type,
                walletAddress,
                ref_id
                ';
            $result = $db->get('mlm_withdrawal', $limit, $column);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00150"][$language] /* No results found */, 'data' => "");
            $totalWithdrawal = 0;
            $totalFee = 0;
            $totalDeductible = 0;

            $cryptoCreditListDisplay = Wallet::getCryptoCredit(true);

            $db->where('client_id', $clientID);
            $clientBankAry = $db->map('id')->get("mlm_client_bank", NULL, "id, account_holder");

            foreach($result as $row) {
                foreach($row as $key => $value) {
                    $withdrawal[$key] = $value ? $value : "-";
                }

                $withdrawal['amount'] = Setting::setDecimal($row['amount'], $row['credit_type']);
                $withdrawal['charges'] = Setting::setDecimal($row['charges'], $row['credit_type']);
                $withdrawal['receivable_amount'] = Setting::setDecimal($row['receivable_amount'], $row['credit_type']);
                $withdrawal['currency_rate'] = Setting::setDecimal($row['currency_rate'], $row['credit_type']);
                $withdrawal['converted_amount'] = Setting::setDecimal($row['converted_amount'], $row['credit_type']);

                if($withdrawal['receivable_amount']) $totalWithdrawal += $row['receivable_amount'];
                if($withdrawal['charges']) $totalFee += $row['charges'];
                if($withdrawal['amount']) $totalDeductible += $row['amount'];

                if ($row['approved_at'] == '0000-00-00 00:00:00') $withdrawal['approved_at'] = "-";
                else $withdrawal['approved_at'] = date("d/m/Y H:i:s", strtotime($row['approved_at']));

                $withdrawal['created_at'] = date("d/m/Y H:i:s", strtotime($row['created_at']));

                $withdrawal['crypto_type'] = $row['crypto_type']?$cryptoCreditListDisplay[$row['crypto_type']]:"-";
                $withdrawal['bank_name'] = $row['bank_name']?$translations[$row['bank_name']][$language]:"-";
                $withdrawal['branch'] = $row['branch']? : "-";
                $withdrawal['bank_city'] = $row['bank_city']? :"-";

                $withdrawal['accountHolder'] = $clientBankAry[$row['ref_id']] ? $clientBankAry[$row['ref_id']] : "-";

                $withdrawal['creditDisplay']=$creditDisplayList[$withdrawal['credit_type']];
                switch($row['withdrawal_type']){
                    case "bank":
                        $withdrawal['withdrawal_type'] = General::getTranslationByName($row['withdrawal_type']);
                    break;
                    case "crypto":
                        $withdrawal['withdrawal_type'] = General::getTranslationByName($row['withdrawal_type']);
                    break;
                    default:
                    break;
                }

                switch($row['status']){
                    case "Waiting Approval":
                        $withdrawal['status'] = $translations["M00652"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Approve":
                        $withdrawal['status'] = $translations["M00498"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Reject":
                        $withdrawal['status'] = $translations["A01187"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Cancel":
                        $withdrawal['status'] = $translations["A00660"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    case "Pending":
                        $withdrawal['status'] = $translations["M00500"][$language];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                    default:
                        $withdrawal['status'] = $row['status'];
                        $withdrawal['statusValue'] = $row['status'];
                    break;
                }

                $withdrawalListing[] = $withdrawal;
            }

            $totalRecord = $copyDb->getValue("mlm_withdrawal", "COUNT(*)");
            $data['withdrawalListing'] = $withdrawalListing;
            $data['totalWithdrawal'] = $totalWithdrawal;
            $data['totalFee'] = $totalFee;
            $data['totalDeductible'] = $totalDeductible;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function getBankAccountList($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            
            $searchData   = $params['searchData'];
            $pageNumber   = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $usernameSearchType = $params["usernameSearchType"];
            
            //Get the limit.
            $limit        = General::getLimit($pageNumber);
            $adminLeaderAry = Setting::getAdminLeaderAry();
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $db->where('name','isAutoWithdrawal');
            $db->where('value','1');
            $db->where('type','bank');
            $autoBankIdRes = $db->get('client_setting',null,'reference,client_id');
            foreach ($autoBankIdRes as $autoBankIdKey => $autoBankIdValue) {
                $autoBankIdAry[$autoBankIdValue['client_id']] = $autoBankIdValue['reference'];
            }

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'username':
                            // $clientID = $db->subQuery();
                            // $clientID->where('username', $dataValue);
                            // $clientID->getOne("client", "id");
                            // $db->where("client_id", $clientID); 
                            if ($dataType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;

                        case 'name':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%" . $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;

                        case 'accHolderName':
                            $db->where('account_holder', $dataValue);
                            break;
                            
                        case 'typeBank':
                            $langCode = $db->subQuery();
                            $langCode->where('content', $dataValue);
                            $langCode->where('site', "System");
                            $langCode->getOne('language_translation', "code");
                            $bankID = $db->subQuery();
                            $bankID->where('translation_code', $langCode);
                            $bankID->getOne('mlm_bank', "id");
                            $db->where('bank_id', $bankID);  
                            break;
                            
                        case 'status':
                            if ($dataValue == 0) {
                                $db->where('status', "Active");
                            } elseif ($dataValue == 1) {
                               $db->where('status', "Inactive");
                            }
                            break;
                            
                        case 'branch':
                            $db->where('branch', $dataValue);
                            break;
                            
                        case 'province':
                            $db->where('province', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            if (Cash::$creatorType == "Member"){
                // $memberID = $params['memberId'];
                $clientID=Cash::$creatorID;

                $db->where('id', $clientID);
                $memberDetail = $db->getOne('client', "name, username");
                $clientDetail['id'] = $clientID;
                $clientDetail['name'] = $memberDetail['name'];
                $clientDetail['username'] = $memberDetail['username'];
                $data['clientDetails'] = $clientDetail;

                $db->where("id", $clientID);
                $countryID = $db->getValue("client", "country_id");
                $db->where("id",$countryID);
                $countryName = $db->getValue("country", "name");
                if ($countryName!="China" && $countryName!="Thailand") {
                    $data['invalidAddBank'] = 1;
                }

                $db->where('client_id', $clientID);

            }
            
            if($adminLeaderAry){
                $db->where('client_id', $adminLeaderAry, 'IN');
            }

            $db->where("status", array('Deleted'), "NOT IN");
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");

            $getUsername  = '(SELECT username FROM client WHERE mlm_client_bank.client_id = client.id) as username';
            $getMemberID  = '(SELECT member_id FROM client WHERE mlm_client_bank.client_id = client.id) as member_id';
            $getFullName  = '(SELECT name FROM client WHERE mlm_client_bank.client_id = client.id) as fullName';
            $getBankName  = '(SELECT name FROM mlm_bank WHERE mlm_client_bank.bank_id = mlm_bank.id) as bank_name';
            $getBankName  = '(SELECT translation_code FROM mlm_bank WHERE mlm_client_bank.bank_id = mlm_bank.id) as langCode';

            $result = $db->get("mlm_client_bank ", $limit, $getUsername. "," .$getMemberID. "," .$getFullName. "," .$getBankName. ", id, client_id, account_no, account_holder as accountHolder, province, branch, status, bank_id, created_at");

            $totalRecord = $copyDb->getValue ("mlm_client_bank", "count(*)");

            if(!empty($result)) {
                foreach($result as $value) {
                    $bankAcc['id']            = $value['id'];

                    if($autoBankIdAry[$value['client_id']] == $value['bank_id'] && $value['status'] == 'Active'){
                        $bankAcc['isAutoWithdrawalBank'] = $translations['A00768'][$language];
                    }else{
                        $bankAcc['isAutoWithdrawalBank'] = $translations['A00605'][$language];
                    }

                    $bankAcc['createdAt']     = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";
                    $bankAcc['bankName']      = $translations[$value['langCode']][$language];
                    $bankAcc['memberID']      = $value['member_id'];
                    $bankAcc['fullName']      = $value['fullName'];
                    if(Cash::$creatorType){
                        $bankAcc['username']      = $value['username'];
                    }
                    $bankAcc['accountHolder'] = $value['accountHolder'];
                    $bankAcc['accountNo']     = $value['account_no'];
                    $bankAcc['province']      = $value['province'];
                    $bankAcc['branch']        = $value['branch'];
                    $bankAcc['status']        = $value['status'] == "Active" ? $translations["A00372"][$language] : $translations["A00373"][$language];
                    $bankAcc['statusDisplay'] = General::getTranslationByName($value['status']);

                    $bankAccList[] = $bankAcc;
                }

            $data['bankAccList'] = $bankAccList ? $bankAccList : "";
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00151"][$language] /* No results found */, 'data' => $data);
            }
        }

        public function updateBankAccStatus($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            if(empty($params['checkedIDs']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00395"][$language] /* No check box selected. */, 'data' => "");

            if(empty($params['status']) || ($params['status'] != "Inactive" && $params['status'] != "Deleted"))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid status. */, 'data' => "");

            $form = array(
                'status' => $params['status']
            );
            $db->where('id', $params['checkedIDs'], 'in');
            $db->update('mlm_client_bank', $form);

            // get admin username
            $adminID = Cash::$creatorID;
            $db->where("id", $adminID);
            $adminUsername = $db->getValue("admin", "username");

            // get member username
            $db->where('id', $params['checkedIDs'], 'in');
            $allClientUsernameRes = $db->get('mlm_client_bank', null, "(SELECT username FROM client WHERE id = client_id) AS username");

            foreach ($allClientUsernameRes as $key => $value) {
                $tempClientUsernameList[] = $value['username'];
            }

            $clientUsername = implode(",", $tempClientUsernameList);
            $activityData = array('admin' => $adminUsername,'client'=>$clientUsername);
            $activityRes = Activity::insertActivity('Update Bank Account Status', 'T00017', 'L00028', $activityData);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity." /* $translations["E00144"][$language] */, 'data' => "");
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function updateWalletAddressStatus($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $checkedIDs = $params['checkedIDs'];

            if(empty($params['checkedIDs']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00395"][$language] /* No check box selected. */, 'data' => "");

            switch ($params['status']) {
                case 'Inactive':
                case 'Deleted':
                    $form = array(
                        'status' => $params['status']
                    );
                    $db->where('id', $params['checkedIDs'], 'in');
                    $db->update('mlm_client_wallet_address', $form);

                    $activityTitle = 'Update Wallet Address Status';
                    $activityDescriptionLang = 'L00029';
                    $activityTitleLang = 'T00018';
                    break;

                case 'WhiteList':
                    $db->where('id', $checkedIDs, "IN");
                    $walletAddressAry = $db->get('mlm_client_wallet_address',null, 'id, status, credit_type, info, isWhitelisted');

                    foreach ($walletAddressAry as $walletAddres) {  
                        if($walletAddres['status'] == "Inactive"){
                            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Inactive Wallet Address Cannot Select for Whitelist', 'data' => "");
                        }

                        if($walletAddres['isWhitelisted'] == 1){
                            return array('status' => "error", 'code' => 1, 'statusMsg' => 'Selected Addresses have Whiteliested Address ', 'data' => "");
                        }

                        if($walletAddres['status'] == "Active" && $walletAddres['isWhitelisted'] == 0) {
                            $address['address']     = $walletAddres['info'];
                            $address['wallet_type'] = CryptoPG::getCryptoConverter($walletAddres['credit_type'])['theNuxPrefix'];
                        }

                        
                        $addressInfo[$walletAddres['info']] = $address;
                    }

                    //send request to the Nux Pay
                    $postParams = array(
                                        "account_id"=> Setting::$configArray['theNuxWalletBusinessID'],
                                        "api_key"    => Setting::$configArray['nuxPayWhiteListApiKey'],
                                        "address"    => array_values($addressInfo),
                                    );

                    $postParams = json_encode($postParams);
                    $url = Setting::$configArray['nuxPayAPIDomain']."/whitelist/address/multi";

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams); // $response->setBody()
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    $jsonResponse = curl_exec($ch);
                    $whitelistResponse = json_decode($jsonResponse, 1);

                    if($whitelistResponse['message'] == "Success"){
                        $whitelistData = $whitelistResponse['data'];

                        foreach ($whitelistData as $whitelist) {                            
                            if($whitelist['status'] == "Success") {
                                $successWhitelist[$whitelist['address']] = $whitelist['address'];
                            } else {
                                if($whitelist['reason'] == 'Duplicate address detected'){
                                    $updateAddress['isWhitelisted'] = 1;
                                }
                                $updateAddress['error_msg'] = $whitelist['reason'];

                                $db->where('info', $whitelist['address']);
                                $db->where('status', 'Active');
                                $db->update('mlm_client_wallet_address', array('error_msg' => $whitelist['reason']));
                            }
                        }

                        if($successWhitelist){
                            $db->where('info', $successWhitelist, 'IN');
                            $db->where('status', 'Active');
                            $db->update('mlm_client_wallet_address', array('isWhitelisted' => '1'));    
                        }
                    } else {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to WhiteList." , 'data' => $whitelistResponse);
                    }

                    $activityTitle = 'WhiteList Wallet Address';
                    $activityDescriptionLang = 'L00068';
                    $activityTitleLang = 'T00048';
                    break;

                default:
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid status. */, 'data' => "");
                    break;
            }

            // get admin username
            $adminID = Cash::$creatorID;
            if($adminID){
                $db->where("id", $adminID);
                $adminUsername = $db->getValue("admin", "username");

                // get member username
                $db->where('id', $params['checkedIDs'], 'in');
                $allClientUsernameRes = $db->get('mlm_client_wallet_address', null, "(SELECT username FROM client WHERE id = client_id) AS username");

                foreach ($allClientUsernameRes as $key => $value) {
                    $tempClientUsernameList[] = $value['username'];
                }

                $clientUsername = implode(",", $tempClientUsernameList);
                $activityData = array('admin' => $adminUsername,'client'=>$clientUsername);
                $activityRes = Activity::insertActivity($activityTitle, $activityTitleLang, $activityDescriptionLang, $activityData, $adminID);
                // Failed to insert activity
                if(!$activityRes)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity." /* $translations["E00144"][$language] */, 'data' => "");
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getBankAccountDetail($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;
            $site = $db->userType;
            $clientID = $db->userID;

            if($site == 'Admin'){
                $clientID     = $params['clientID'];
            }

            if (empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00460"][$language] /* Member not found. */, 'data'=> "");

            // get member name, username, country_id
            $db->where('id', $clientID);
            $memberDetail = $db->getOne('client', "name, username, country_id");
            if (empty($memberDetail))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00460"][$language] /* Member not found. */, 'data'=> "");

            $countryCode  = $memberDetail['country_id'];

            $db->where('client_id',$clientID);
            $db->where('status','Active');
            $userBankDetail = $db->getOne('mlm_client_bank','bank_id,account_no,account_holder,branch,bank_city');

            // get bank list
            $db->where('country_id', $countryCode);
            $db->where('status', "Active");
            $db->orderBy('name', "ASC");
            $bankDetail   = $db->get("mlm_bank ", $limit, "id, name, translation_code");
            if (empty($bankDetail))
                $bankDetail = '';

            foreach($bankDetail AS &$bankData){
                $bankData['display'] = $translations[$bankData['translation_code']][$language] ? $translations[$bankData['translation_code']][$language] : $bankData["name"];
            }

            $clientDetail['id']       = $clientID;
            $clientDetail['name']     = $memberDetail['name'];
            $clientDetail['username'] = $memberDetail['username'];
            $clientDetail['hasBank']  = $userBankDetail?1:0;
            $clientDetail['bankID']   = $userBankDetail['bank_id'];
            $clientDetail['accountNo']= $userBankDetail['account_no'];
            $clientDetail['accountHolder']= $userBankDetail['account_holder'];
            $clientDetail['branch']   = $userBankDetail['branch'];
            $clientDetail['bank_city']= $userBankDetail['bank_city'];
            $data['clientDetails']    = $clientDetail;
            $data['bankDetails']      = $bankDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getWithdrawalOptionOfMember($clientID){
            $db = MysqliDb::getInstance();

            $db->where("client_id", $clientID);
            $db->where("name","isAutoWithdrawal");
            $db->where("value",1);
            $isAutoWithdrawalType = $db->getValue('client_setting','type');

            $db->where("id", $clientID);
            $countryID = $db->getValue("client", "country_id");
            $db->where("id",$countryID);
            $countryName = $db->getValue("country", "name");

            if($isAutoWithdrawalType == 'bank'){
                return array("bank");

            }else if($isAutoWithdrawalType == 'crypto'){

                return array("crypto");

            }

            // if($countryName!="China") return array("crypto");

            return array("bank","crypto");
        }

        public function updateAutoWithdrawalStatus($params,$clientID){
            $db = MysqliDb::getInstance();

            $type = $params["type"];
            $reference = $params["reference"];
            $value = $params["value"];

            if(!$type && $type != 0) return false;
            if(!$reference && $reference != 0) return false;
            if(!$value && $value != 0) return false;

            $db->where("client_id", $clientID);
            $db->where("name","isAutoWithdrawal");
            $id = $db->getValue("client_setting","id");

            if($id){

                $updateData = array("value"=>$value,"type"=>$type,"reference"=>$reference);
                $db->where("id",$id);
                $db->update("client_setting",$updateData);

            }else{
                $insertData = array(
                                        "name"=>"isAutoWithdrawal",
                                        "client_id"=>$clientID,
                                        "value"=>$value,
                                        "type"=>$type,
                                        "reference"=>$reference,
                                    );
                $db->insert("client_setting",$insertData);
            }

            return true;
        }
        
        // --- Bank End --//
 
        function adminSearchDownline($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $clientID = trim($params["clientID"]);
            $targetUsername = trim($params["targetUsername"]);

            $db->where("username", $targetUsername);
            $targetClientID = $db->getValue("client", "id");

            if (empty($targetClientID)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data' => '');
            }

            $db->where("client_id", $clientID);
            $clientOwnLevel = $db->getValue("tree_sponsor", "level");

            $downline = Tree::getSponsorDownlineByClientID($clientID);

            if(!in_array($targetClientID, $downline)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00378"][$language] /* Client not found. */, 'data' => '');
            }

            $db->where("client_id", $targetClientID);
            $result = $db->get("tree_sponsor", null, "level,trace_key");

            foreach ($result as $key => $value) {
                $targetClientTraceKey = $value["trace_key"];
                $targetClientOwnLevel = $value["level"];
            }

            $clientArray = explode("/", $targetClientTraceKey);

            foreach ($clientArray as $value1) {
                $db->where("client_id", $value1);
                $level = $db->getValue("tree_sponsor", "level");
                $clientIDwithLevel[$value1] = $level;

            }

            foreach ($clientIDwithLevel as $cID => $kLvl) {
               // if($clientIDwithLevel[$cID] < $clientOwnLevel) continue;
               // if($clientIDwithLevel[$cID] > $targetClientOwnLevel) continue;
               $downlinesData['client_id'] = $cID;
               $downlineClientIDArray[] = $downlinesData;
               $allDownlinesArray[] = $cID;
            }

            $db->where("type", 'Client');
            $allClient = $db->get("client", NULL, "username, id,member_id");

            foreach ($allClient as $key => $value) {
                $allClientArray[$value['id']] = $value['username'];
                $allClientMemberIDArray[$value['id']] = $value['member_id'];

            }

            foreach ($allDownlinesArray as $value) {
                $downlineLineData['username'] = $allClientArray[$value];
                $downlineLineData['level'] = $clientIDwithLevel[$value];
                $downlineLineData['clientID'] = $value;
                $downlineLineData['memberID'] = $allClientMemberIDArray[$value];

                $displayClientArray[] = $downlineLineData;

            }
             // foreach ($allDownlinesArray as $value2) {
                    $allDownlinesResult = Tree::getSponsorTreeDownlines($targetClientID,false);
                    // if (empty($allDownlinesResult)) continue;
                    foreach ($allDownlinesResult as $value3) {
                        $allDownlinesResultArray[$targetClientID][] = $value3;
                    }

            // }
            if (!empty($allDownlinesResultArray)) {

                foreach ($allDownlinesResultArray as $key1 => $value4) {
                            
                    $db->where("portfolio_type",'Package Re-entry');
                    $db->where("client_id" , $allDownlinesResultArray[$key1], "IN");
                    $tsAResult = $db->getValue("mlm_client_portfolio","SUM(bonus_value)");
                    $tsA[$key1] = $tsAResult > 0 ? number_format($tsAResult,2,".","") : number_format(0,2,".","");
                }

                foreach ($allDownlinesResultArray as $key1 => $value4) {

                    $dateToday = date("Y-m-d");
                            
                    $db->where("portfolio_type",'Package Re-entry');
                    $db->where("client_id" , $allDownlinesResultArray[$key1], "IN");
                    $db->where("created_at", $dateToday."%", "LIKE");
                    $dsAResult = $db->getValue("mlm_client_portfolio","SUM(bonus_value)");
                    $dsA[$key1] = $dsAResult > 0 ? number_format($dsAResult,2,".","") : number_format(0,2,".","");
                }
            }

            $db->where("portfolio_type",'Package Re-entry');
            $db->groupBy('client_id');
            $result = $db->get('mlm_client_portfolio portfolio', NULL, 'client_id AS clientID, SUM(bonus_value) AS amount');
            foreach ($result as $value) {
                $totalArray[$value["clientID"]] = $value["amount"];
            }

            if($downlineClientIDArray){

                foreach ($downlineClientIDArray as $k => &$v) {
                    $v['username'] = $allClientArray[$v['client_id']];
                    $v['ownSalesAmount'] = number_format($totalArray[$v['client_id']]?:0,2,".","");
                    $v['totalSalesAmount'] = number_format($tsA[$v['client_id']]?:0,2,".","");
                    $v['dailySalesAmount'] = number_format($dsA[$v['client_id']]?:0,2,".","");
                }
            }


            // foreach ($displayArray as $key => $value) {
            //     $displayClientArray[$allClientArray[$key]] = $value;
            // }
            $memberDetails = Self::getCustomerServiceMemberDetails($targetClientID);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];
            $data["treeLink"] = $displayClientArray;
            $data["downlines"] = $downlineClientIDArray;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        function countryIPBlock($ip,$clientID){
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $excludedCheckIP=array('127.0.0.1');

            if (!$clientID){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid clientID', 'data' => '');
            }

            if (!$ip){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid IP address', 'data' => '');
            }
            // Grab recorded IP address's country info
            if (!in_array($ip, $excludedCheckIP)){
                $db->where('ip',$ip);
                $IPRecord=$db->getOne('mlm_Ip_Country_Code','ip,countryCode');
                if (!$IPRecord){
                    $returnData=General::ip_info($ip);
                    $country_code=$returnData['country_code'];
                    $db->rawQuery("INSERT INTO `mlm_Ip_Country_Code` (`ip`, `countryCode`, `source`, `createdOn`) SELECT '$ip', '$country_code', '', '".date('Y-m-d H:i:s')."' ");

                    $db->where('ip',$ip);
                    $IPRecord=$db->getOne('mlm_Ip_Country_Code','ip,countryCode');
                }

                //IF country is disabled then stop from login
                // $disabledLoginCountries=json_decode(Setting::$systemSetting['blockMemberLoginByCountryIP']);

                // $db->where('IP_block_login','1');
                // $disabledLoginCountries=$db->map('iso_code2')->arrayBuilder()->get('country',null,'iso_code2');


                $db->where('blocked','1');
                $db->where('client_id',$clientID);
                $disabledLoginCountries=$db->map('country_code')->arrayBuilder()->get('client_country_ip_block',null,'country_code');

                if (in_array($IPRecord['countryCode'], $disabledLoginCountries)){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00754"][$language] /* Invalid Login */, 'data' => '');
                }

            }
            return true;
        }

        function terminatePortfolio($clientID, $params){
            $db = MysqliDb::getInstance();
            $cash = $this->cash;
            $currentTime = date('Y-m-d H:i:s');
            $portfolioId = $params['portfolioIDAry'];

            $totalPortfolioBV = 0;
            $totalPayableAmount = 0;

            $db->where('username','payout');
            $internalID = $db->getValue('client','id');

            $db->where('status','Active');
            $db->where('client_id',$clientID);
            $db->where('id',$portfolioId,'IN');
            $db->where('portfolio_type',array('Credit Reentry','Credit Register'),'IN');
            $portfolioRes = $db->get('mlm_client_portfolio',null,'bonus_value,id,promoBV');
            foreach ($portfolioRes as $portfolioKey => $portfolioValue) {
                $portfolioDetail[] = $portfolioValue;
            }

            if(empty($portfolioDetail)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No portfolio able to redeem.", 'data'=> "");
            }

            foreach ($portfolioDetail as $portfolioKey => $portfolioValue) {

                $db->where('portfolio_id',$portfolioValue['id']);
                // $db->where('paid','1');
                $rebateBonusRes = $db->get('mlm_bonus_rebate',null,'payable_amount');
                foreach ($rebateBonusRes as $rebateBonusKey => $rebateBonusValue) {
                    $totalPayableAmount += $rebateBonusValue['payable_amount'];
                }

                $totalRedeemAmount = $portfolioValue['bonus_value'] - $totalPayableAmount;

                if($totalRedeemAmount < 0){

                    $totalRedeemAmount = 0;

                }

                $batchID = $db->getNewID();

                $cash->insertTAccount($internalID, $clientID, 'voucherRedeem', $totalRedeemAmount, 'Early Redemption', $batchID, '', $currentTime, $batchID, $clientID, "",$portfolioValue['id']);

                $updateData = array(
                    "status" => "Redeemed",
                    "redeemed_at" => $currentTime,
                );

                $db->where('id',$portfolioValue['id']);
                $db->update('mlm_client_portfolio',$updateData);
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Redeem Successfully.', 'data'=> '');
        }

        function updateTotalDownline($clientID){
        	$db = MysqliDb::getInstance();

        	$db->where("client_id",$clientID);
        	$traceKey = $db->getValue("tree_sponsor","trace_key");

        	$uplineArray = explode("/", $traceKey);
        	foreach($uplineArray AS $id){
        		if($id == $clientID) continue;
        		$idArr[] = $id;
        	}

        	$insertArr = array(
									"client_id" => $clientID,
									"name" => "totalDownline",
									"value" => 0,
								);
			$db->insert("client_setting",$insertArr);

        	if($idArr){
        		$db->where("client_id",$idArr,"IN");
        		$db->where("name","totalDownline");
        		$db->update("client_setting",array("value"=>$db->inc(1)));

        		$db->where("client_id",$idArr,"IN");
        		$db->where("name","totalDownline");
        		$clientIDArr = $db->getValue("client_setting","client_id",NULL);

        		$diffArr = array_diff($idArr, $clientIDArr);
        		if(!$clientIDArr && !$diffArr) $diffArr = $idArr;

        		if(COUNT($diffArr) > 0){
        			foreach($diffArr AS $client){
        				$insertArr = array(
        										"client_id" => $client,
        										"name" => "totalDownline",
        										"value" => 1,
        									);
        				$db->insert("client_setting",$insertArr);
        			}
        		}
        	}
        	return true;
        }

        function updateTotalIntroducee($clientID){
            $db = MysqliDb::getInstance();

            $db->where("client_id",$clientID);
            $traceKey = $db->getValue("tree_introducer","trace_key");

            $uplineArray = explode("/", $traceKey);
            foreach($uplineArray AS $id){
                if($id == $clientID) continue;
                $idArr[] = $id;
            }

            $insertArr = array(
                                    "client_id" => $clientID,
                                    "name" => "totalIntroducee",
                                    "value" => 0,
                                );
            $db->insert("client_setting",$insertArr);

            if($idArr){
                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalIntroducee");
                $db->update("client_setting",array("value"=>$db->inc(1)));

                $db->where("client_id",$idArr,"IN");
                $db->where("name","totalIntroducee");
                $clientIDArr = $db->getValue("client_setting","client_id",NULL);

                $diffArr = array_diff($idArr, $clientIDArr);
                if(!$clientIDArr && !$diffArr) $diffArr = $idArr;

                if(COUNT($diffArr) > 0){
                    foreach($diffArr AS $client){
                        $insertArr = array(
                                                "client_id" => $client,
                                                "name" => "totalIntroducee",
                                                "value" => 1,
                                            );
                        $db->insert("client_setting",$insertArr);
                    }
                }
            }
            return true;
        }

        public function memberGetMemoList() {
            $db = MysqliDb::getInstance();

            $id = $db->userID;

            if($id){
                $db->where("id", $id);
                $turnOffPopUpMemo = $db->getValue('client', 'turnOffPopUpMemo');
            }
            $memo = Bulletin::getPopUpMemo($id, $turnOffPopUpMemo);
            $data['memo'] = $memo;

            //get client blocked rights
            $blockedRights = array();
            if($id){
                $column = array(
                    "(SELECT name FROM mlm_client_rights WHERE id = mlm_client_blocked_rights.rights_id) AS blocked_rights"
                );
                $db->where('client_id', $id);
                $result2 = $db->get("mlm_client_blocked_rights", NULL, $column);

                foreach ($result2 as $row){
                    $blockedRights[] = $row['blocked_rights'];
                }
            }
            $data['blockedRights'] = $blockedRights;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getKYCDetailsNew($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientId   = $db->userID;
            $verifyType = $params['verifyType'];
            $db->where('id',$clientId);
            $clientDetails = $db->getOne('client','name, identity_number, passport');

            if(empty($clientDetails)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00105'][$language] /* Invalid User */, 'data' => '');
            }

            $fullName   = $clientDetails['name'];
            if(!empty($clientDetails['identity_number'])){
                $idNumber   = $clientDetails['identity_number'];
                $idType     = 'KTP';
            }else if(!empty($clientDetails['passport'])){
                $idNumber   = $clientDetails['passport'];
                $idType     = 'Passport';
            }

            $db->where('address_type', 'billing');
            $db->where('client_id',$clientId);
            $clientAddress = $db->getOne('address','address, (SELECT name FROM county WHERE id = district_id) AS district, (SELECT name FROM sub_county WHERE id = sub_district_id) AS subDistrict, (SELECT name FROM zip_code WHERE id = post_code) AS zipCode, (SELECT name FROM city WHERE id = city) AS city, (SELECT name FROM state WHERE id = state_id) AS province, (SELECT translation_code FROM country WHERE id = address.country_id) AS countryName');

            if($clientAddress['countryName']){
                $clientAddress['countryName'] = $translations[$clientAddress['countryName']]['english'];;    
            }
            $address = join(', ',$clientAddress);

            $db->where('status','Active');
            $db->where('client_id',$clientId);
            $bankDetail = $db->getOne('mlm_client_bank','bank_id, account_no, account_holder');

            if(!$bankDetail){
                $bankName = "";
            }else{
                $db->where('id', $bankDetail['bank_id']);
                $bankName = $db->getValue('mlm_bank','name');
            }
            $bankAccNo  = $bankDetail['account_no'];
            $bankAccHold= $bankDetail['account_holder'];

            $db->where('client_id',$clientId);
            $npwp = $db->getValue('client_detail','tax_number');

            if($verifyType == 'idVerify'){
                $db->where('doc_type', 'ID Verification');
            }else if($verifyType == 'bankAccVerify'){
                $db->where('doc_type', 'Bank Account Cover');
            }else if($verifyType == 'NPWPVerify'){
                $db->where('doc_type', 'NPWP Verification');
            }

            $db->where('client_id',$clientId);
            $db->orderBy("created_at","DESC");
            $remarkRes = $db->getOne('mlm_kyc','remark, status');

            switch($verifyType){
                case 'idVerify':
                    $data['fullName'] = $fullName;
                    $data['idNum']    = $idNumber;
                    $data['idType']   = $idType;
                    $data['address']  = $address;
                    $data['remarkDetail']  = $remarkRes;
                    break;

                case 'bankAccVerify':
                    $data['bankName']       = $bankName;
                    $data['bankAccNo']      = $bankAccNo;
                    $data['bankAccHolder']  = $bankAccHold;
                    $data['remarkDetail']  = $remarkRes;
                    break;

                case 'NPWPVerify':
                    $data['fullName'] = $fullName;
                    $data['npwpNum']  = $npwp;
                    $data['remarkDetail']  = $remarkRes;
                    break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01061'][$language]/* Invalid verify type */, 'data' => '');
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addKYCValidation($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientId   = $db->userID;
            $verifyType = $params['verifyType'];
            $imgName    = $params['imgName'];

            $db->where('id',$clientId);
            $clientDetails = $db->getOne('client','name, identity_number, passport');
            
            $db->where('address_type', 'billing');
            $db->where('client_id',$clientId);
            $clientAddress = $db->getOne('address','address, (SELECT name FROM county WHERE id = district_id) AS district, (SELECT name FROM sub_county WHERE id = sub_district_id) AS subDistrict, (SELECT name FROM city WHERE id = city) AS city, (SELECT name FROM zip_code WHERE id = post_code) AS zipCode, (SELECT name FROM state WHERE id = state_id) AS province, (SELECT translation_code FROM country WHERE id = address.country_id) AS countryName');

            if($clientAddress['countryName']){
                $clientAddress['countryName'] = $translations[$clientAddress['countryName']]['english'];;    
            }
            
            $fullName   = $clientDetails['name'];
            if(!empty($clientDetails['identity_number'])){
                $idNumber   = $clientDetails['identity_number'];
            }else if(!empty($clientDetails['passport'])){
                $idNumber   = $clientDetails['passport'];
            }
            $address = join(', ',$clientAddress);

            $db->where('status','Active');
            $db->where('client_id',$clientId);
            $bankDetail = $db->getOne('mlm_client_bank','bank_id, account_no, account_holder');
            if($bankDetail){
                $db->where('id', $bankDetail['bank_id']);
                $bankName = $db->getValue('mlm_bank','name');
            }else{
                $bankName = "";
            }
            $bankAccNo  = $bankDetail['account_no'];
            $bankAccHold= $bankDetail['account_holder'];

            $db->where('client_id',$clientId);
            $npwp = $db->getValue('client_detail','tax_number');

            switch($verifyType){
                case 'idVerify':
                    if(!$fullName){
                        $errorFieldArr[] = array(
                            'id'    => 'usernameError',
                            'msg'   => $translations['E01062'][$language] /* Username not found */
                        );
                    }elseif(!$idNumber){
                        $errorFieldArr[] = array(
                            'id'    => 'idError',
                            'msg'   => $translations['E01063'][$language] /* Id not found */
                        );
                    }elseif(!$address){
                        $errorFieldArr[] = array(
                            'id'    => 'addressError',
                            'msg'   => $translations['E01064'][$language] /* Address not found */
                        );
                    }
                    $docType = 'ID Verification';
                    break;

                case 'bankAccVerify':
                    if(!$bankName){
                        $errorFieldArr[] = array(
                            'id'    => 'bankError',
                            'msg'   => $translations['E01065'][$language] /* Bank not found */
                        );
                    }elseif(!$bankAccNo){
                        $errorFieldArr[] = array(
                            'id'    => 'bankAccError',
                            'msg'   => $translations['E01066'][$language] /* Bank account not found */
                        );
                    }elseif(!$bankAccHold){
                        $errorFieldArr[] = array(
                            'id'    => 'bankAccHolderError',
                            'msg'   => $translations['E01067'][$language] /* Bank account holder not found */
                        );
                    }
                    $docType = 'Bank Account Cover';
                    break;

                case 'NPWPVerify':
                    if(!$fullName){
                        $errorFieldArr[] = array(
                            'id'    => 'usernameError',
                            'msg'   => $translations['E01062'][$language] /* Username not found */
                        );
                    }elseif(!$npwp){
                        $errorFieldArr[] = array(
                            'id'    => 'npwpError',
                            'msg'   => $translations['E01068'][$language] /* NPWP not found */
                        );
                    }
                    $docType = 'NPWP Verification';
                    break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01060'][$language]/* Invalid verify type */, 'data' => '');
            }

            if(!$imgName){
                $errorFieldArr[] = array(
                    'id'    => 'uploadError',
                    'msg'   => $translations['E01069'][$language] /* Please upload image */
                );
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['E01070'][$language] /* Required place cannot be empty */, 'data'=>$data);
            }

            $db->where('doc_type', $docType);
            $db->where('client_id',$clientId);
            $db->where('status', array('Waiting Approval','Success'),'IN');
            $checkValid = $db->getValue('mlm_kyc','status');

            if($checkValid){
                switch($checkValid){
                    case 'Success':
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01071'][$language] /* You have done verified your KYC */, 'data' => '');
                        break;

                    default:
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01071'][$language] /*Your verification is waiting to be approved */, 'data' => '');
                        break;
                }
            }


            $data['imgName']    = $imgName;
            $data['fullName']   = $fullName;
            $data['idNumber']   = $idNumber;
            $data['address']    = $address;
            $data['bankName']   = $bankName;
            $data['bankAccNo']  = $bankAccNo;
            $data['bankAccHold']= $bankAccHold;
            $data['npwp']       = $npwp;

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addKYCConfirmation($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientId   = $db->userID;
            $verifyType = $params['verifyType'];
            $created_at = date('Y-m-d H:i:s');

            $db->where('client_id',$clientId);
            $getCountryId = $db->getValue('address','country_id');

            $result = self::addKYCValidation($params);
            if($result['status'] != 'ok'){
                return $result;
            }
            
            $imgName    = $result['data']['imgName'];
            $fullName   = $result['data']['fullName'];
            $idNumber   = $result['data']['idNumber'];
            $address    = $result['data']['address'];
            $bankName   = $result['data']['bankName'];
            $bankAccNo  = $result['data']['bankAccNo'];
            $bankAccHold= $result['data']['bankAccHold'];
            $npwp       = $result['data']['npwp'];
            
            $groupCode = General::generateUniqueChar("mlm_kyc_detail",'value');
            $fileType = end(explode(".",$imgName));
            $imgName = time()."_".General::generateUniqueChar("mlm_kyc_detail","value")."_".$groupCode.".".$fileType;

            switch($verifyType){
                case 'idVerify':
                    $docType = 'ID Verification';
                    $data = array(  
                                    "Full Name"         => $fullName,
                                    "Identity Number"   => $idNumber,
                                    "Address"           => $address,
                                    "Image Name 1"      => $imgName
                                );
                    break;

                case 'bankAccVerify':
                    $docType = 'Bank Account Cover';
                    $data = array(  
                                    "Bank Name"             => $bankName,
                                    "Bank Account Number"   => $bankAccNo,
                                    "Bank Account Holder"   => $bankAccHold,
                                    "Image Name 1"          => $imgName
                                );
                    break;

                case 'NPWPVerify':
                    $docType = 'NPWP Verification';
                    $data = array(  
                                    "Full Name"         => $fullName,
                                    "NPWP Number"       => $npwp,
                                    "Image Name 1"      => $imgName
                                );
                    break;

                default:
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01060'][$language]/* Invalid verify type */, 'data' => '');
            }

            $insertKYCData = array(
                                    "client_id" => $clientId,
                                    "country_id"=> $getCountryId,
                                    "doc_type"  => $docType,
                                    "status"    => "Waiting Approval",
                                    "created_at"=> $created_at
                                  );
            $kyc = $db->insert('mlm_kyc',$insertKYCData);

            foreach($data as $name=>$value){
                if($name == 'Address'){
                    $insertKYCDetail = array(
                                             "kyc_id"       => $kyc,
                                             "name"         => $name,
                                             "type"         => "basic",
                                             "description"  => $value
                                            );
                }else{
                    $insertKYCDetail = array(
                                             "kyc_id"       => $kyc,
                                             "name"         => $name,
                                             "type"         => "basic",
                                             "value"        => $value
                                            );
                }
                $kycDetail = $db->insert('mlm_kyc_detail',$insertKYCDetail);
            }

            /* Notification Part */
            General::insertNotification("kyc");
            $insertNotification = array(
                "kyc_id"       => $kyc,
                "name"         => 'notificationCount',
                "type"         => 'basic',
                "value"        => 1
            );
            $db->insert('mlm_kyc_detail', $insertNotification);
            
            $returnData['imgName']      = $imgName;
            $returnData["doRegion"]     = Setting::$configArray["doRegion"];
            $returnData["doEndpoint"]   = Setting::$configArray["doEndpoint"];
            $returnData["doAccessKey"]  = Setting::$configArray["doApiKey"];
            $returnData["doSecretKey"]  = Setting::$configArray["doSecretKey"];
            $returnData["doBucketName"] = Setting::$configArray["doBucketName"]."/kyc";
            $returnData["doProjectName"]= Setting::$configArray["doProjectName"];
            $returnData["doFolderName"] = Setting::$configArray["doFolderName"];

            if(!$kyc || !$kycDetail){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01073'][$language] /* Please try again later */, 'data' => '');
            }else{
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $returnData);
            }
        }

        public function checkMemberKYCStatus($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $checkKYCFlag = Setting::$systemSetting['checkKYCFlag'];
            $dateTime = date("Y-m-d H:i:s");

            $clientID = $db->userID;
            $site = $db->userType;

            if($checkKYCFlag != 1) return false;

            // $kycDocAry = array("ID Verification","Bank Account Cover","NPWP Verification");
            $kycDocAry = array("ID Verification");
            if($params['type'] == 'purchase') $kycDocAry = array("ID Verification");

            $db->where("client_id",$clientID);
            $db->where("doc_type",$kycDocAry,"IN");
            $db->where("status",array("Approved"),"IN");
            $memberKYC = $db->map("doc_type")->get("mlm_kyc",null,"doc_type");

            unset($nonApprovedKYC);

            foreach($kycDocAry as $kycDoc){
                if(!$memberKYC[$kycDoc]) $nonApprovedKYC[$kycDoc] = $kycDoc;
            }

            return $nonApprovedKYC;
        }

        public function accountOwnerVerification($params, $type) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $identityType = trim($params['identityType']);
            $identityNumber = trim($params['identityNumber']);
            $name = trim($params['name']);
            $dob = trim($params['dob']);
            $step = trim($params['step']);
            $phone = trim($params['phone']);
            $verificationCode = $params['verificationCode'];
            $dialCode = $params['dialCode'];
            $number = $params['number'];
            $type = $type ? $type : $params['type'];
            $dateTime = date("Y-m-d H:i:s");
            $browserInfo = General::getBrowserInfo();
            $ip = $browserInfo["ip"]?$browserInfo["ip"]:"Unknown";
            $ipInfo = General::ip_info($ip);

            if(!$step) $step = 1;

            if($type == 'resetPassword'){
                if(empty($phone)) {
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
                    );
                }
            }

            if($type == 'resetPassword' && $step > 1){
                if(!$verificationCode){
                    $errorFieldArr[] = array(
                        'id'  => 'verificationCodeError',
                        'msg' => $translations["M01050"][$language] /* Please insert otp code */
                    );      
                }
            }

            if($phone == '60')
            {
                $errorFieldArr[] = array(
                    'id'  => 'phoneError',
                    'msg' => $translations["E00305"][$language] /* Please fill in mobile number */
                );
            }

            if($phone && $phone != '60'){
                $mobileNumberCheck = General::mobileNumberInfo($phone, "MY");
                if($mobileNumberCheck['isValid'] != 1){
                    $errorFieldArr[] = array(
                        'id'  => 'phoneError',
                        'msg' => $translations["E01093"][$language] /* Invalid phone number */
                    );
                }
            }

            if($type == 'resetPassword'){
                $messageType = 'Reset Password';
            }else{
                $messageType = strtoupper(substr($type, 0, 1)) . substr($type, 1);
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;

                foreach ($errorFieldArr as $key => $value) {
                    $newKey = $key + 1;
                    $newErrorArr[$newKey] = $newKey . '.' . $value['msg'];
                }

                $errorString = implode("\n", $newErrorArr);

                // for ($i = 0; $i < count($errorFieldArr); $i++) {
                    $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                    $replace = array($phone, $ip, $messageType,$ipInfo['country'], $dateTime, $errorString);
                    $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                // }
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            if($type == 'resetPassword'){
                $db->where('concat(dial_code, phone)', $phone);
                $db->orWhere('member_id', $phone);
                $clientID = $db->getOne('client', 'id, email, username, concat(dial_code, phone) as phone');

                if(!$clientID){
                    $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                    $replace = array($phone, $ip, $messageType,$ipInfo['country'], $dateTime, $translations["E01253"][$language]);
                    $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");

                    $errorFieldArr[] = array(
                        'id'  => 'phoneError',
                        'msg' => $translations["E01277"][$language] /* Mobile number no associate with any registered account. Please sign up for a new account */
                    );

                    if($errorFieldArr){
                        $data['field'] = $errorFieldArr;
        
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }
               
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $db->getLastQuery());
                }
            }

            if($type == 'register'){
                $db->where('concat(dial_code, phone)', $phone);
                $db->orWhere('member_id', $phone);
                $clientID = $db->getOne('client', 'id, email, username, concat(dial_code, phone) as phone,type');

                if($clientID && $clientID["type"] == "Client"){
                    $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                    $replace = array($phone, $ip, $messageType,$ipInfo['country'], $dateTime, $translations["E01252"][$language]);
                    $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
               
                    $errorFieldArr[] = array(
                        'id'  => 'phoneError',
                        'msg' => $translations["E01279"][$language] /* Mobile number already associate with a registered account. Please sign in instead. */
                    );

                    if($errorFieldArr){
                        $data['field'] = $errorFieldArr;
        
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
                    }
               
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $db->getLastQuery());
               
                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $db->getLastQuery());
                }
            }

            $data['loginID'] = $clientID['username'];
            $data['phone'] = $clientID['phone'];

            if($type == 'resetPassword'){

                // if($step == 1){
                    $otpParams['phone'] = $clientID['phone'];
                    $otpParams['sendType'] = 'phone';
                    $otpParams['type'] = $type;

                    $db->where('name', 'otpReqPerDay');
                    $otpLimit = $db->getValue('system_settings', 'value');

                    //check the last verified result
                    $db->where('phone_number', $phone);
                    $db->where('created_on', date("Y-m-d 00:00:00"), '>=');
                    $db->where('msg_type', 'OTP Code');
                    $db->where('status', 'Success');
                    $db->where('verification_type', 'resetPassword##phone');
                    $last_verified_res = $db->getValue('sms_integration', 'max(created_on)');

                    if($last_verified_res){
                        $start_check_time = $last_verified_res;
                    }else{
                        $start_check_time = date("Y-m-d 00:00:00");
                    }

                    //check if have otp still not expired yet
                    $db->where('phone_number', $phone);
                    $db->where('created_on', $start_check_time, '>=');
                    $db->where('msg_type', 'OTP Code');
                    $db->where('status', 'Sent');
                    $db->where('verification_type', 'resetPassword##phone');
                    $otp_req_count = $db->getValue('sms_integration', 'count(id)');
                    // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01249"][$language] /* Please wait the resend OTP cooldown */, 'data' => $otpLimit);

                    if($otp_req_count >= $otpLimit){

                        $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                        $replace = array($clientID['phone'], $ip, $messageType,$ipInfo['country'], $dateTime, $translations["E01250"][$language]);
                        $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                  
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01250"][$language] /* You have reach daily otp request limit. Please contact customer for assistance. */, 'data' => $otp_req_count);
                    }


                    $db->orderby('created_on');
                    $db->where('phone_number',$phone);
                    $db->where('status','Sent');
                    $db->where('verification_type','resetPassword##phone');
                    $availableOTP = $db->getOne('sms_integration','created_on');
                    $availableOTP = $availableOTP['created_on'];

                    $currentDateTime = time();

                    $availableOTP = strtotime($availableOTP);

                    $diff_minutes = ($currentDateTime - $availableOTP) / 60;
                    if ($diff_minutes >= 3) {
                        $otpRes = Otp::sendOTPCode($otpParams);
                    } 
                    else 
                    {
                        $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                        $replace = array($phone, $ip, $messageType,$ipInfo['country'], $dateTime, $translations["E01249"][$language]);
                        $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                  
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01249"][$language] /* Please wait the resend OTP cooldown */, 'data' => "");
                    }
                    //return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E01102"][$language] /* Sorry, we could not find the correct memberID with these information. Please try again. */, 'data' => $clientID['phone']);

                    // $otpRes = Otp::sendOTPCode($otpParams);
                    $find = array("%%otpParams%%", "%%otpRes%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%");
                    $replace = array($otpParams['phone'], $otpRes['data']['otpCode'], $ip, 'Reset Password',$ipInfo['country'], $dateTime);
                    $outputArray = Client::sendTelegramMessage('10009', NULL, NULL, $find, $replace,"","","telegramAdminGroup");

                    // $content = '*OTP Request* '."\n\n".'Phone : '.$otpParams['phone']."\n".'Send Type: phone'."\n".'OTP Code: '.$otpRes['data']['otpCode']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                    // Client::sendTelegramNotification($content);

                    return $otpRes;
                // }

            }else {



                // if ($step == 1){
                    $otpParams['phone'] = $number;
                    $otpParams['sendType'] = 'phone';
                    $otpParams['type'] = $type;
                    $otpParams['dialCode'] = $dialCode;

                    $db->where('name', 'otpReqPerDay');
                    $otpLimit = $db->getValue('system_settings', 'value');

                    //check the last verified result
                    $db->where('phone_number', $phone);
                    $db->where('created_on', date("Y-m-d 00:00:00"), '>=');
                    $db->where('msg_type', 'OTP Code');
                    $db->where('status', 'Success');
                    $db->where('verification_type', 'register##phone');
                    $last_verified_res = $db->getValue('sms_integration', 'max(created_on)');

                    if($last_verified_res){
                        $start_check_time = $last_verified_res;
                    }else{
                        $start_check_time = date("Y-m-d 00:00:00");
                    }

                    //check if have otp still not expired yet
                    $db->where('phone_number', $phone);
                    $db->where('created_on', $start_check_time, '>=');
                    $db->where('msg_type', 'OTP Code');
                    $db->where('status', 'Sent');
                    $db->where('verification_type', 'register##phone');
                    $otp_req_count = $db->getValue('sms_integration', 'count(id)');

                    if($otp_req_count >= $otpLimit){
                        $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                        $replace = array($phone, $ip, $messageType,$ipInfo['country'], $dateTime, $translations["E01250"][$language]);
                        $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                  
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01250"][$language] /* You have reach daily otp request limit. Please contact customer for assistance. */, 'data' => $otp_req_count);
                    }



                    $db->orderby('created_on');
                    $db->where('phone_number',$dialCode.$number);
                    $db->where('status','Sent');
                    $db->where('verification_type','register##phone');
                    $availableOTP = $db->getOne('sms_integration','created_on');
                    $availableOTP = $availableOTP['created_on'];

                    $currentDateTime = time();

                    $availableOTP = strtotime($availableOTP);

                    $diff_minutes = ($currentDateTime - $availableOTP) / 60;
                    if ($diff_minutes >= 3) {
                        $otpRes = Otp::sendOTPCode($otpParams);
                    } 
                    else 
                    {
                        $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                        $replace = array($phone, $ip, $messageType,$ipInfo['country'], $dateTime, $translations["E01249"][$language]);
                        $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                  
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01249"][$language] /* Please wait the resend OTP cooldown */, 'data' => "");
                    }

                    if($otpRes)
                    {
                        $db->where('phone_number', $dialCode.$number);
                        $getOtpCode = $db->getOne('sms_integration','code');
                        foreach ($getOtpCode as $row) {
                            $OtpCode = $row['code'];
                        }
                        // make sure OTP is not empty
                        if(empty($otpRes['otpCode']))
                        {
                            $code = $otpRes['data']['otpCode'];
                        }
                        else
                        {
                            $code = $otpRes['otpCode'];
                        }
                        $find = array("%%otpParams%%", "%%otpRes%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%");
                        $replace = array('+60'.$otpParams['phone'], $otpRes['data']['otpCode'], $ip, $type,$ipInfo['country'], $dateTime);
                        $outputArray = Client::sendTelegramMessage('10009', NULL, NULL, $find, $replace,"","","telegramAdminGroup");

                        // $content = '*OTP Request* '."\n\n".'Phone : '.$otpParams['phone']."\n".'Send Type: phone'."\n".'OTP Code: '.$otpRes['data']['otpCode']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                        // Client::sendTelegramNotification($content);
                    }
                    return $otpRes;
                // }

            }

            // verify OTP code
            $verifyCode = Otp::verifyOTPCode($clientID,'phone',$type,$verificationCode,$phone);
            if($verifyCode['status'] == 'error')
            {
                $find = array("%%otpParams%%", "%%ip%%",  "%%type%%","%%country%%", "%%dateTime%%", "%%issueDesc%%");
                $replace = array($phone, $ip, $messageType,$ipInfo['country'], $dateTime, $translations["E00864"][$language]);
                $outputArray = Client::sendTelegramMessage('10022', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
          
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00864"][$language], 'data' => "");
            }
            else
            {
                $db->where('phone_number',$phone);
                $db->where('status','Sent');
                $db->where('msg_type','OTP Code');
                $db->where('verification_type','resetPassword##phone');
                $db->where('code',$verificationCode);
                $fields = array("status");
                $values = array("Verified");
                $arrayData = array_combine($fields, $values);
                $row = $db->update("sms_integration", $arrayData);
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function guestOwnerVerification($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $name               = trim($params['name']);
            $emailAddress       = trim($params['emailAddress']);
            $dialingArea        = trim($params['dialingArea']);
            $phone              = trim($params['phone']);
            $companyName        = trim($params['companyName']);
            $address            = $params['streetNo'];
            $addressline2       = $params['address2'];
            $city               = $params['city'];
            $zipCode            = $params['zipCode'];
            $state              = $params['state'];
            $country            = $params['country'];
            $package            = $params['package'];
            $ShipToSameAddress  = $params['guestShipToSameAddress'];

            $name2              = trim($params['name2']);
            $emailAddress2      = trim($params['emailAddress2']);
            $dialingArea2       = trim($params['dialingArea2']);
            $phone2             = trim($params['phone2']);
            $companyName2       = trim($params['companyName2']);
            $address2           = $params['streetNo2'];
            $addressline22      = $params['address22'];
            $city2              = $params['city2'];
            $zipCode2           = $params['zipCode2'];
            $state2             = $params['state2'];
            $country2           = $params['country2'];
            $deliveryMethod     = trim($params['deliveryMethod']);
            $saleID             = trim($params['saleID']);
            $promoCode          = trim($params['promo_code']);
            $is_gift            = $params['is_gift'];

            // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);


            // bkend_token
            $bkendToken         = $params['bkend_token'];

            $db->orderBy('id', 'DESC');
            $db->where('token', $bkendToken);
            $saleID = $db->getOne('guest_token');

            if(!empty($saleID['sale_id']))
            {
                $saleID = $saleID['sale_id'];
                $db->where('id', $saleID);
                $saleOrder = $db->getOne('sale_order');

                $purchaseAmount = $saleOrder['payment_amount'];
            }

            if(empty($name)) {
                $errorFieldArr[] = array(
                    'id' => 'nameError',
                    'msg' => $translations["E00635"][$language] /* Please Enter Name. */ 
                );
            }

            if(empty($dialingArea)) {
                $errorFieldArr[] = array(
                    'id' => 'dialCodeError',
                    'msg' => $translations["E01084"][$language] /* Please fill in valid dial code */
                );
            }

            if(empty($phone)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["M02436"][$language] /* Enter your phone number */
                );
            }

            if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
                $errorFieldArr[] = array(
                    'id' => 'phoneError',
                    'msg' => $translations["E00858"][$language] /* Only number is allowed */
                );
            }

            if($phone){
                $phoneCheck = self::modifyPhoneNumber($phone);
                $mobileNumberCheck = General::mobileNumberInfo($phoneCheck, "MY");
                if($mobileNumberCheck['isValid'] != 1){
                    $errorFieldArr[] = array(
                        'id' => 'phoneError',
                        'msg' => $translations["E01093"][$language] /* Invalid mobile number */
                    );
                }
            }

            if($emailAddress){
                if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                    $errorFieldArr[] = array(
                        'id' => 'emailAddressError',
                        'msg' => $translations["E00121"][$language] /* Invalid email format. */
                    );
                }
            }

            if(empty($address)) {
                $errorFieldArr[] = array(
                    'id' => 'addressError',
                    'msg' => $translations["M03152"][$language] /* Enter your address */
                );
            }

            if(empty($addressline2)) {
                $errorFieldArr[] = array(
                    'id' => 'address2Error',
                    'msg' => $translations["E01273"][$language] /* Enter your address line 2 */
                );
            }

            if(empty($city)) {
                $errorFieldArr[] = array(
                    'id' => 'cityError',
                    'msg' => $translations["M03157"][$language] /* Enter your city */
                );
            }

            if(empty($zipCode)) {
                $errorFieldArr[] = array(
                    'id' => 'zipCodeError',
                    'msg' => $translations["E01030"][$language] /* Please Insert Zip Code */
                );
            }


            if($ShipToSameAddress != 1 || strtolower($deliveryMethod) != 'pickup')
            {

                if(empty($name2)) {
                    $errorFieldArr[] = array(
                        'id' => 'name2Error',
                        'msg' => $translations["E00635"][$language] /* Please Enter Name. */ 
                    );
                }

                if(empty($address2)) {
                    $errorFieldArr[] = array(
                        'id' => 'streetNo2Error',
                        'msg' => $translations["M03152"][$language] /* Enter your address */
                    );
                }

                if(empty($addressline22)) {
                    $errorFieldArr[] = array(
                        'id' => 'address2Error',
                        'msg' => $translations["E01273"][$language] /* Enter your address line 2 */
                    );
                }
    
                if(empty($city2)) {
                    $errorFieldArr[] = array(
                        'id' => 'city2Error',
                        'msg' => $translations["M03157"][$language] /* Enter your city */
                    );
                }
    
                if(empty($zipCode2)) {
                    $errorFieldArr[] = array(
                        'id' => 'zipCode2Error',
                        'msg' => $translations["E01030"][$language] /* Please Insert Zip Code */
                    );
                }

                if(empty($phone2)) {
                    $errorFieldArr[] = array(
                        'id' => 'phone2Error',
                        'msg' => $translations["M02436"][$language] /* Enter your phone number */
                    );
                }

                if(!empty($phone2))
                {
                    if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
                        $errorFieldArr[] = array(
                            'id' => 'phone2Error',
                            'msg' => $translations["E00858"][$language] /* Only number is allowed */
                        );
                    }
                }

                if($phone2){
                    $phoneCheck2 = self::modifyPhoneNumber($phone2);
                    $mobileNumberCheck = General::mobileNumberInfo($phoneCheck2, "MY");
                    if($mobileNumberCheck['isValid'] != 1){
                        $errorFieldArr[] = array(
                            'id' => 'phone2Error',
                            'msg' => $translations["E01093"][$language] /* Invalid mobile number */
                        );
                    }
                    $validPhone2 = $mobileNumberCheck['phone'];
                }

                if($state2 == 'Select State/Province') {
                    $errorFieldArr[] = array(
                        'id' => 'state2Error',
                        'msg' => $translations["M03427"][$language] /* Please Select State. */ 
                    );
                }
            
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // check is the user exist or not
            $db->where('concat(dial_code,phone)',$dialingArea.$phone);
            $userExist = $db->get('client',null,'id, username, name, email, dial_code, phone, address, country_id, state_id, city_id');

            if($userExist)
            {
                foreach($userExist as $userRow)
                {
                    $clientID = $userRow['id'];
                }

                $db->where('name',$country);
                $countryID = $db->get('country',null,'id');
                $countryID = $countryID[0]['id'];
                $db->where('name',$state);
                $stateID = $db->get('state',null,'id');
                $stateID = $stateID[0]['id'];
                $db->where('name',$city);
                $db->where('country_id',$countryID);
                $cityID = $db->get('city',null,'id');
                $cityID = $cityID[0]['id'];

                $data = array(
                    // "name"          => $name,
                    // "email"         => $emailAddress,
                    "address"       => $address,
                    "country_id"    => $countryID,
                    "state_id"      => $stateID,
                    "city_id"       => $cityID,
                    "updated_at"    => date("Y-m-d H:i:s"),
                );
                $db->where('concat(dial_code,phone)',$dialingArea.$phone);
                // update client table details
                $updateUser = $db->update('client',$data);
                if(!$updateUser)
                {
                    $find = array("%%phoneNumber%%", "%%address%%", "%%countryID%%", "%%stateID%%", "%%cityID%%", "%%currentDate%%", "%%currentTime%%");
                    $replace = array($dialingArea.$phone, $address, $countryID, $stateID, $cityID, date('Y-m-d'), date('H:i:s'));
                    $outputArray = Client::sendTelegramMessage('10010', NULL, NULL, $find, $replace,"","","telegramAdminGroup");

                    // $content = '*Failed to Update User Existing profile* '."\n\n"."Client Phone No: ".$dialingArea.$phone."\n"."Address: ".$address."\n"."Country ID: ".$countryID."\n"."State ID: ".$stateID."\n"."City ID: ".$cityID."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                    // Client::sendTelegramNotification($content);
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01180"][$language] /* Failed to update user existing profile. */, 'data' => '');
                }

                // update guest_token
                $db->where('token', $bkendToken);
                $tokenDetails = $db->getOne('guest_token');
                // remove the guest token
                $db->where('token', $bkendToken);
                $db->delete('guest_token');

                $updateData = array(
                    'token'   => $bkendToken,
                    'sale_id' => $tokenDetails['sale_id'],
                );

                $db->where('token', $bkendToken);
                $saleID = $db->getOne('guest_token', 'sale_id');
                if($saleID)
                {
                    $saleID = $saleID['sale_id'];
                }

                $db->where('deleted', 0);
                $db->where("sale_id", $saleID);
                $order_detail = $db->getOne("sale_order_detail");

                foreach($order_detail as $detailChecking)
                {
                    $db->where('product_id', $detailChecking['product_id']);
                    $db->where('status', 'Active');
                    $availableStock = $db->getValue('stock', 'count(*)') ?? 0;

                    $db->where('sod.sale_id', $saleID);
                    $db->where("so.status", array("Pending","Pending Payment Approve", "Paid"),"IN");
                    $db->where("sod.deleted", 0);
                    $db->where("sod.product_id", $product_id);
                    $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                    $lockStock = $db->getValue("sale_order_detail sod", "SUM(sod.quantity)") ?? 0;

                    $result = intval($availableStock) - intval($quantity) - intval($lockStock);

                    if(intval($result) < 0)
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03005"][$language] /* Out Of Stock */, 'data' => $dataOut['data']);
                    }
                }

                // get the clientID
                $db->where('concat(dial_code,phone)',$dialingArea.$phone);
                $clientUpdateID = $db->getOne('client');
                if($clientUpdateID)
                {
                    $clientUpdateID = $clientUpdateID['id'];
                }
                
                // search the guest_token to check is there have existing client_id
                $db->where('client_id', $clientUpdateID);
                $existToken = $db->get('guest_token');

                if($existToken)
                {
                    $db->where('client_id', $clientUpdateID);
                    $db->update('guest_token', $updateData);
                }
                else
                {
                    $insertData = array(
                        'token'      => $bkendToken,
                        'sale_id'    => $tokenDetails['sale_id'],
                        'client_id'  => $clientUpdateID,
                        'created_at' => date("Y-m-d H:i:s"),
                    );
                    $db->insert('guest_token', $insertData);
                }
                // return array("code" => 110, "status" => "ok", "clientUpdateID" => $clientUpdateID);
            }
            else
            {
                // $clientID = $db->getNewID();
                $memberID = Subscribe::generateMemberID();
                $dateTime = $db->now();
                $db->where('name',$country);
                $countryID = $db->get('country',null,'id');
                $countryID = $countryID[0]['id'];
                $db->where('name',$state);
                $stateID = $db->get('state',null,'id');
                $stateID = $stateID[0]['id'];
                $db->where('name',$city);
                $db->where('country_id',$countryID);
                $cityID = $db->get('city',null,'id');
                $cityID = $cityID[0]['id'];

                $insertClientData = array(
                    // "id" => $clientID,
                    "member_id" => $memberID,
                    "email" => $emailAddress,
                    "name" => $name,
                    "username" => $dialingArea.$phone, 
                    "dial_code" => $dialingArea,
                    "phone" => $phone,
                    "address" => $address,
                    // "address_2" => $addressline2,
                    "state_id" => $stateID,
                    "city_id" => $cityID,
                    "country_id" => $countryID,
                    "type" => "Guest",
                    "created_at" => $dateTime,
                );
                try
                {
                    $createGuest = $db->insert('client',$insertClientData);
                    $find = array("%%memberID%%", "%%name%%", "%%phoneNumber%%", "%%currentDate%%", "%%currentTime%%");
                    $replace = array($memberID, $name, $dialingArea.$phone, date('Y-m-d'), date('H:i:s'));
                    $outputArray = Client::sendTelegramMessage('10012', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                }
                catch(Exception $e)
                {
                    $find = array("%%memberID%%", "%%name%%", "%%phoneNumber%%", "%%currentDate%%", "%%currentTime%%");
                    $replace = array($memberID, $name, $dialingArea.$phone, date('Y-m-d'), date('H:i:s'));
                    $outputArray = Client::sendTelegramMessage('10011', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01182"][$language] /* Failed to create new client. */ , 'data' => '');
                }
                // update client id for guest_token table
                $updateData = array(
                    'client_id' => $createGuest,
                );

                try
                {
                    $db->where('token', $bkendToken);
                    $db->update('guest_token', $updateData);
                }
                catch(Exception $e)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00131"][$language] /* Update failed. */, 'data' => "");
                }

                // get User Client Id (Guest)
                $db->where('concat(dial_code,phone)', $dialingArea.$phone);
                $db->where('type', 'Guest');
                $clientID = $db->getOne('client');
                $clientID = $clientID['id']; 
            }
            // convert country name and state name into id
            $db->where('name', $country);
            $countryId = $db->getOne('country');

            $db->where('name', $country2);
            $countryId2 = $db->getOne('country');

            $db->where('name', $state);
            $stateId = $db->getOne('state');

            $db->where('name', $state2);
            $stateId2 = $db->getOne('state');

            $db->where('id',$clientID);
            $clientDetails = $db->getOne('client');

            $addressChecking['clientID']            = $clientID;
            $addressChecking['billingName']         = $clientDetails['name'];
            $addressChecking['billingPhone']        = $clientDetails['dial_code'].$clientDetails['phone'];
            $addressChecking['billingAddress']      = $address;
            $addressChecking['billingAddressLine2'] = $addressline2;
            $addressChecking['billingPostCode']     = $zipCode;
            $addressChecking['billingCity']         = $city;
            $addressChecking['billingState']        = $stateId['id'];
            $addressChecking['billingCountry']      = $countryId['id'];

            $checkAddress = Admin::checkAddressDuplication($addressChecking);

            $addressChecking2['clientID']            = $clientID;
            $addressChecking2['billingName']         = $name2;
            $addressChecking2['billingPhone']        = $validPhone2;
            $addressChecking2['billingAddress']      = $address2;
            $addressChecking2['billingAddressLine2'] = $addressline22;
            $addressChecking2['billingPostCode']     = $zipCode2;
            $addressChecking2['billingCity']         = $city2;
            $addressChecking2['billingState']        = $stateId2['id'];
            $addressChecking2['billingCountry']      = $countryId2['id'];

            $checkAddress2 = Admin::checkAddressDuplication($addressChecking2);

            // check and insert address
            $db->where('id',$clientID);
            $clientDetails = $db->getOne('client');
            // insert address Table
            // Billing address
            $data = array(
                "client_id" => $clientID,
                "name" => $clientDetails['name'],
                "email" => $emailAddress,
                "phone" => $clientDetails['phone'],
                "address" => $address,
                "address_2" => $addressline2,
                "post_code" => $zipCode,
                "city" => $city,
                "state_id" => $stateId['id'],
                "country_id" => $countryId['id'],
                "address_type" => 'billing',
                "remarks" => $companyName,
                "created_at" => $db->now(),
            );
            // Shipping address
            $data2 = array(
                "client_id" => $clientID,
                "name" => $name2,
                "email" => $emailAddress2,
                "phone" => $validPhone2,
                "address" => $address2,
                "address_2" => $addressline22,
                "post_code" => $zipCode2,
                "city" => $city2,
                "state_id" => $stateId2['id'],
                "country_id" => $countryId2['id'],
                "address_type" => 'shipping',
                "remarks" => $companyName2,
                "created_at" => $db->now(),
            );
            if($checkAddress['status'] != 'error'){
                $insertAddress1 = $db->insert('address',$data);
                if(!$insertAddress1)
                {
                    $content = '*Failed to insert new company address Message* '."\n\n".'client ID: '.$clientID."\n"."Name: ".$clientDetails['name']."\n"."Email: ".$clientDetails['email']."\n".'Type: Guest'."\n".'Phone Number: +'.$clientDetails['phone']."\n"."Address: ".$clientDetails['address']."\n"."Post Code: ".$zipCode."\n"."City: ".$city."\n"."State id: ".$clientDetails['state_id']."\n"."Country id: ".$clientDetails['country_id']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                    Client::sendTelegramNotification($content);
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01181"][$language] /* Failed to insert company address. */, 'data' => '');
                }
            }else{
                $insertAddress1 = $checkAddress['data'];
            }
            
            if($checkAddress2['status'] != 'error'){
                $insertAddress2 = $db->insert('address',$data2);
                if(!$insertAddress2)
                {
                    $content = '*Failed to insert new company address Message* '."\n\n".'client ID: '.$clientID."\n"."Name: ".$clientDetails['name']."\n"."Email: ".$clientDetails['email']."\n".'Type: Guest'."\n".'Phone Number: +'.$clientDetails['phone']."\n"."Address: ".$clientDetails['address']."\n"."Post Code: ".$zipCode."\n"."City: ".$city."\n"."State id: ".$clientDetails['state_id']."\n"."Country id: ".$clientDetails['country_id']."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
                    Client::sendTelegramNotification($content);
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01181"][$language] /* Failed to insert company address. */, 'data' => '');
                }
            }else{
                $insertAddress2 = $checkAddress2['data'];
            }

            if($ShipToSameAddress == '1')
            {
                $ShippingId = $insertAddress1;
            }
            else
            {
                $ShippingId = $insertAddress2;
            }
            $BillingId  = $insertAddress1;

            if($checkAddress['status'] == 'error'){
                if(!$BillingId){
                    $db->where('client_id', $addressChecking['clientID'] );
                    $db->where('name', $addressChecking['billingName']);
                    $db->where('phone', $addressChecking['billingPhone'] );
                    $db->where('address', $addressChecking['billingAddress'] );
                    $db->where('address_2', $addressChecking['billingAddressLine2'] );
                    $db->where('post_code', $addressChecking['billingPostCode'] );
                    $db->where('city', $addressChecking['billingCity'] );
                    $db->where('state_id', $addressChecking['billingState'] );
                    $db->where('country_id', $addressChecking['billingCountry'] );
                    $BillingId = $db->getValue('address', 'id');
                }
            }

            unset($params);
            $params['clientID'] = $clientID;
            $params['package'] = $package;
            $params['bkend_token'] = $bkendToken;
            $params['promo_code'] = $promoCode;

            // add client id for existing shopping cart
            $updateData = array(
                'client_id'  => $clientID,
                'updated_at' => $todayDate,
            );
            $db->where('sale_id', $saleID);
            $shoppingCart = $db->update('shopping_cart', $updateData);

            $updateInventory = Inventory::updateShoppingCart($params);
            if($updateInventory['status'] == 'error')
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01183"][$language] /* Failed to update shopping cart. */, 'data' => $updateInventory['statusMsg']);
            }

            // $saleParams['clientID'] = $clientID;
            // $InsertSO = Inventory::InsertSO($saleParams);

            unset($params);
            $params['quantityOfReward']     = '0';
            $params['isRedeemReward']       = '0';
            $params['redeemAmount']         = '0';
            $params['memberPointDeduct']    = '0';
            $params['billing_address']      = $BillingId;
            $params['shipping_address']     = $ShippingId;
            $params['purchase_amount']      = $purchaseAmount;
            $params['clientID']             = $clientID;
            $params['deliveryMethod']      = $deliveryMethod;
            $params['bkend_token']          = $bkendToken;
            $params['is_gift']              = $is_gift;

            $addNewPayment = Cash::addNewPayment($params, $clientID); // addNewPayment

            if($addNewPayment['status'] == 'error')
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $addNewPayment['statusMsg'] /* Failed to add new payment. */, 'data' => $addNewPayment, 'clientID' => $clientID, 'updateInventory' => $updateInventory,"verifyOwner" =>$deliveryMethod);
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $addNewPayment['statusMsg'], 'data' => $addNewPayment['data'], 'InsertSO' => $InsertSO);
        }

        public function getState($params)
        {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $country_name = trim($params['countryName']);
            $country_id   = trim($params['countryId']);
            $state_id     = trim($params['stateId']);
            $state_name   = trim($params['stateName']);

            if(empty($country_id))
            {
                // get all country
                $countryList = $db->get('country',null,'id, name');

                // get all state
                $stateList = $db->get('state',null,'id, name');
            }  
            if(!empty($country_id))
            {
                if(empty($country_name))
                {
                    // get the country name
                    $db->where('id',$country_id);
                    $country_name = $db->getOne('country','name');
                    $country_name = $country_name['name'];
                }
                if(empty($state_id))
                {
                    // $stateList = $db->get('state',null,'id, name');
                    $db->where('country_id',$country_id);
                    $state_id = $db->getOne('state','id');
                    $state_id = $state_id['id'];
                }
                else if (!empty($state_id))
                {
                    if(empty($state_name));
                    {
                        // get the state name
                        $db->where('id',$state_id);
                        $db->where('country_id',$country_id);
                        $state_name = $db->getOne('state','name');
                        $state_name = $state_name['name'];
                    }
                    
                }
                // get the country id and name
                $db->where('id',$country_id);
                $countryList = $db->getOne('country','id, name');

                // get the state id and name
                $db->where('country_id',$country_id);
                $stateList = $db->get('state',null,'id, name');
            }
            
            $data['state'] = $stateList;
            $data['country'] = $countryList;
            return array("code" => 0, "status" => "ok", "statusMsg" => '', "data" => $data);
        }

        public function getProductListMember($params)
        {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            
            $userID = $db->userID;
            $site = $db->userType;

            $categories = trim($params['categories']);
            $searchForm = trim($params['searchData']);

            if(empty($categories))
            {
                $db->where('deleted', '0');
                $db->where('is_published', '1');
                $db->where('is_archive', '0');
                if(!empty($searchForm)){
                    // if filter with category and with search data
                    $db->where('name', "%".$searchForm."%", 'LIKE');
                }
                $productList = $db->get('product',null, 'id, sale_price, name, product_type, barcode as skuCode, ignore_stock_count');
                $db->where('name', 'percentage');
                $db->where('type', 'marginPercen');
                $margin_percen = $db->getOne('system_settings','value');
                $margin_percen = $margin_percen['value'];
                foreach($productList as $productInvRow)
                {
                    $productDetail['id']                = $productInvRow['id'];
                    $productDetail['skuCode']           = $productInvRow['skuCode'];
                    $productDetail['name']              = $productInvRow['name'];
                    $productDetail['product_type']       = $productInvRow['product_type'];
                    // $productDetail['marginPercen']      = $margin_percen;
                    $productDetail['categ_id']           = $productInvRow['categ_id'];
                    $productDetail['sale_price']         = $productInvRow['sale_price'];
                    $productDetail['ignore_stock_count'] = $productInvRow['ignore_stock_count'];

                    $db->where('reference_id', $productInvRow['id']);
                    $db->where('type', 'Image');
                    $productImage = $db->getOne('product_media', 'url');

                    if($productImage) {
                        $productDetail['image']             = $productImage['url'];
                    } else {
                        $productDetail['image']             = '';
                    }

                    $productInvList[] = $productDetail;
                }
                $data['productInventory'] = $productInvList;
                return array("code" => 0, "status" => "ok", "statusMsg" => '', "data" => $data);
            }
            $db->where('name',$categories);
            $productID = $db->get('product_category',null,'id');

            if(!$productID)
            {
                // probably not searching category, try search product name
                $searchTerm = '%'.$categories.'%';
                $productList = $db->rawQuery("SELECT * FROM product WHERE name LIKE '".$searchTerm."' AND deleted = 0");
                if(!$productList)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00279"][$language] /* No result found */, 'data' => "");
                }
                $productID = $productList;
                // return array("code" => 333, "status" => "ok", "productID" => $productID);
            }

            // $productID = '["'.$productID[0]['id'].'"]';

            // $db->where('categ_id',$productID);
            // $productList = $db->get('product',null, 'id, cost, margin_percen, name, product_type, barcode as skuCode');
            foreach($productList as $productInvRow)
            {
                $productDetail['id']                = $productInvRow['id'];
                $productDetail['skuCode']           = $productInvRow['skuCode'];
                $productDetail['name']              = $productInvRow['name'];
                $productDetail['product_type']       = $productInvRow['product_type'];
                $productDetail['categ_id']          = $productInvRow['categ_id'];
                $productDetail['sale_price']         = $productInvRow['sale_price'];
                $productDetail['ignore_stock_count'] = $productInvRow['ignore_stock_count'];

                $productInvList[] = $productDetail;
            }
            $data['productInventory'] = $productInvList;
            return array("code" => 0, "status" => "ok", "statusMsg" => '', "data" => $data);
        }

        public function getCategoryInventoryMember($params) {
            $db                 = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            $dateTimeFormat     = Setting::$systemSetting['systemDateTimeFormat'];


            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            if(!$seeAll) $limit = General::getLimit($pageNumber);
            $searchForm = trim($params['searchData']);
            $searchMenu = $params['filter'];
            $categories = $params['categories'];
            $language   = $params['language'];
            $dateTime   = date("Y-m-d H:i:s");
            $dummyvalue = 0;
            $userID     = $db->userID;

            if($language == 'chineseSimplified' || $language == 'chineseTraditional') $language = 'chinese';

            // $productList = Client::getProductListMember($params);

            if(!empty($categories)){
                $db->where('id', $categories);
                $db->where('deleted', '0');
                $categID = $db->getValue('product_category', 'id');

                if($categID){
                    $rule[] = array('col' => 'categ_id', 'val' => '%"'.$categID.'"%', 'operator' => 'LIKE');
                }
                else{
                    return array("code" => 1, "status" => "error", "statusMsg" => 'Product Not Found', "data" => $data);
                }
                
            }

            if($searchForm){

                $db->where('content', "%".$searchForm."%", 'LIKE');
                $db->where('module', array('product','package'), 'IN');
                $db->where('type', 'name');
                $searchingList = $db->getValue('inv_language', 'module_id', null);
                
                if($searchingList){
                    $rule[] = array('col' => 'id', 'val' => $searchingList, 'operator' => 'IN');
                }
                else{
                    $error = 'error';
                }
            }

            if($searchMenu == '1'){
                $db->orderBy('sale_price','ASC');
            }else if($searchMenu == '2'){
                $db->orderBy('sale_price','DESC');
            }else if($searchMenu == '3'){
                $db->orderBy('name','ASC');
            }else if($searchMenu == '4'){
                $db->orderBy('name','DESC');
            }

            foreach ($rule as $key => $val) {
                $db->where($val["col"], $val["val"], $val["operator"]);
            }

            $db->where('deleted', '0');
            $db->where('is_published', '1');
            $db->where('is_archive', '0');
            $productList = $db->get('product', null, 'id, name, product_type, description, note, barcode, expired_day, cost, sale_price, vendor_id, categ_id, cooking_suggestion, cooking_time, delivery_method, sales_count, view_count,ignore_stock_count');
            $totalRecord = $db->count;

            if($error && $error == 'error'){
                $productList = '';
            }

            foreach($productList as $productInvRow)
            {
                $db->where('module', array('product','package'), 'IN');
                $db->where('module_id', $productInvRow['id']);
                $db->where('type', 'name');
                $db->where('language', $language);
                $getInvLan = $db->getOne('inv_language', 'module,module_id,type,language,content');

                $productDetail['name']              = $getInvLan['content'] ?: $productInvRow['name'];
                $productDetail['id']                = $productInvRow['id'];
                $productDetail['categ_id']          = $productInvRow['categ_id'];
                $productDetail['skuCode']           = $productInvRow['barcode'];
                $productDetail['productType']       = $productInvRow['product_type'];

                $db->where('disabled', '0');
                $db->where(" '".$dateTime."' BETWEEN start_date AND end_date");
                // $db->where("(start_date >= ? AND end_date >= ?)", [$dateTime, $dateTime]);
                $db->where('product_id', $productInvRow['id']);
                $pricelist_detail = $db->getOne('pricelist_detail');
                unset($latestPrice);
                if(strtolower($pricelist_detail['discount_type']) == 'percentage')
                {
                    $latestPrice = floatval($productInvRow['sale_price']) - (floatval($productInvRow['sale_price']) * (floatval($pricelist_detail['discount']) / 100));
                }
                if(strtolower($pricelist_detail['discount_type']) == 'fixed')
                {
                    $latestPrice = floatval($productInvRow['sale_price']) - floatval($pricelist_detail['discount']);
                }

                $productDetail['salePrice']         = $productInvRow['sale_price'];
                $productDetail['latestPrice']       = $latestPrice ?: 0;

                $db->where('deleted', '0');
                $db->where('reference_id', $productInvRow['id']);
                $db->where('type', 'coverImg');
                $productImage = $db->getOne('product_media', 'url');

                $productDetail['image']             = $productImage['url'] ?: ""; 

                if(strtolower($productInvRow['product_type']) != 'package')
                {
                    $dataIn['product_id'] = $productInvRow['id'];
                    $dataIn['quantity'] = 0;
                    $dataIn['purchaseProduct'] = $dataIn;
                    $dataOut = Inventory::checkStockQuantity($dataIn);
                }
                else
                {
                    $dataIn['product_id'] = $productInvRow['id'];
                    $dataIn['quantity'] = 0;
                    $dataOut = Inventory::checkStockQuantity($dataIn);
                    $dataOut['data'] = $dataOut['data'];
                }
                
                if($dataOut['data'][0]['finalAmount'] > 0 ){
                    $productDetail['stockQuantity'] = $dataOut['data'][0]['finalAmount'];
                    $productDetail['availability'] = 'yes';
                }
                else if($dataOut['data'] && $dataOut['status'] != 'error'){
                    $productDetail['stockQuantity'] = $dataOut['data'];
                    $productDetail['availability'] = 'yes';
                }
                else{
                    if($productInvRow['ignore_stock_count'] == '1'){
                        $productDetail['availability'] = 'yes';
                        $productDetail['stockQuantity']= 9999;
                    }
                    else{
                        $productDetail['stockQuantity'] = 0;
                        $productDetail['availability'] = 'no';
                    }
                }


                if($userID)
                {
                    $db->where('product_id', $productInvRow['id']);
                    $db->where('client_id', $userID);
                    $db->where('deleted', '0');
                    $getFavouriteList = $db->getOne('product_favorite');
                    if($getFavouriteList)
                    {
                        $productDetail['favourite'] = 1;
                        $productDetail['fav_id'] = $getFavouriteList['id'];
                    }
                    else
                    {
                        $productDetail['favourite'] = 0;
                    }
    
                    $db->where('product_id', $productInvRow['id']);
                    $db->where('client_id', $userID);
                    $db->where('deleted', '0');
                    $getWishList = $db->getOne('product_wishlist');
                    
                    if($getWishList)
                    {
                        $productDetail['wishlist'] = 1;
                        $productDetail['wishlist_id'] = $getWishList['id'];
                    }
                    else
                    {
                        $productDetail['wishlist'] = 0;
                    }
                }
                else
                {
                    $productDetail['favourite'] = 0;
                    $productDetail['wishlist'] = 0;
                }
                
                $productInvList[] = $productDetail;
            }
            $productList = $productInvList;

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            $db->where('deleted', '0');
            $categoryList = $db->get('product_category');
            $categoryArray = array(); 

            $categoryDetails = []; 
            $processedIDs = [];

            foreach ($categoryList as $categoryDetail) 
            {
                if($categoryDetail['parent_id'] != '0')
                {
                    // check the parent_id is active or inactive
                    $db->where('id', $categoryDetail['parent_id']);
                    $parentStatus = $db->getOne('product_category');

                    if($parentStatus['deleted'] == 1) // inactive
                    {
                        $inactiveParent[] = $parentStatus;
                    }
                }

                $parentMatch = false; 

                foreach ($inactiveParent as $inactiveCategory) {
                    if ($categoryDetail['parent_id'] == $inactiveCategory['id']) {
                        $parentMatch = true;
                        $matchParentInactive[] = $inactiveCategory;
                        break;  
                    }
                }

                if(!$parentMatch) 
                {
                    $db->where('module', 'category');
                    $db->where('module_id',$categoryDetail['id']);
                    $db->where('type', 'name');
                    $db->where('language', $language);
                    $getCategoryOthLan = $db->getOne('inv_language',null, 'module,module_id,type,language,content');

                    if($getCategoryOthLan['content']){
                        $categoryDetails['name'] = $getCategoryOthLan['content'];
                    }else{
                        $categoryDetails['name'] = $categoryDetail['name'];
                    }

                    $categoryDetails['id'] = $categoryDetail['id'];
                    $categoryDetails['parent_id'] = $categoryDetail['parent_id'];
                    $categoryDetails['description'] = $categoryDetail['description'];
                    $categoryDetails['deleted'] = $categoryDetail['deleted'];
                    $categoryDetails['created_at'] = $categoryDetail['created_at'];
                    $categoryDetails['updated_at'] = $categoryDetail['updated_at'];

                    if ($categoryDetail['parent_id'] == '0') {
                        $categoryArray[] = $categoryDetails;
                    } else {
                        $isAdded = false;
                        foreach ($categoryArray as &$category) {
                            if ($category['id'] === $categoryDetail['parent_id']) {
                                if (!isset($category['subCategory'])) {
                                    $category['subCategory'] = array();
                                }
                                $category['subCategory'][] = $categoryDetails;
                                $isAdded = true;
                                break;
                            }else {
                                if (isset($category['subCategory'])) {
                                    foreach ($category['subCategory'] as &$subCategory) {
                                        if ($subCategory['id'] === $categoryDetail['parent_id']) {
                                        // if ($subCategory['id'] === $categoryArray['parent_id']) {
                                            if (!isset($subCategory['subCategory'])) {
                                                $subCategory['subCategory'] = array();
                                            }
                                            $subCategory['subCategory'][] = $categoryDetails;
                                            $isAdded = true;
                                            break 2; 
                                        }
                                    }
                                }
                            }
                        }
                        if (!$isAdded) {
                            $categoryArray[] = $categoryDetails;
                        }
                    }
                }
            }

            foreach ($categoryArray as $categoryDetail) {
                $parentId = $categoryDetail['parent_id'];

                if (!isset($categoryMap[$parentId])) {
                    $categoryMap[$parentId] = [];
                }
            
                // Check if the category already exists in the categoryMap
                $existingCategory = false;
                foreach ($categoryMap[$parentId] as $existing) {
                    if ($existing['id'] === $categoryDetail['id']) {
                        $existingCategory = true;
                        break;
                    }
                }
            
                if (!$existingCategory) {
                    $categoryMap[$parentId][] = $categoryDetail;
                }
            }
            $parentId = 0;
            foreach ($categoryMap[$parentId] as $category) {
                $categoryId = $category['id'];
                if (isset($categoryMap[$categoryId])) {
                    // Check if the current category's parent_id matches its id
                    foreach ($categoryMap[$categoryId] as $subcategory) {
                        if ($subcategory['parent_id'] === $categoryId) {
                            $category['subCategory'][] = $subcategory;
                            unset($categoryMap[$categoryId]);
                        }
                    }
                }
                if ($category['parent_id'] === $parentId) {
                    $sortedCategories[] = $category;
                }
                if (isset($categoryMap[$category['id']])) {
                    $subcategories = $categoryMap[$category['id']];
                    while (!empty($subcategories)) {
                        $subcategory = array_shift($subcategories);
                        if ($subcategory['parent_id'] === $category['id']) {
                            $sortedCategories[] = $subcategory;
                        }
                        if (isset($categoryMap[$subcategory['id']])) {
                            $subcategories = array_merge($subcategories, $categoryMap[$subcategory['id']]);
                        }
                    }
                }
            }
            // $data["categoryList"]    = $categoryArray;
            $data["categoryList"]    = $sortedCategories;
            // $data["subCategoryList"] = $subCategoryList;
            if($productList == '')
            {
                $data['productInventory'] = $productList['data']['productInventory'];
            }
            else
            {
                $data['productInventory'] = $productList;
            }

            $data['pageNumber']       = $pageNumber;
            $data['totalRecord']      = $totalRecord;
            if($seeAll) {
                $data['totalPage']    = 1;
                $data['numRecord']    = $totalRecord;
            } else {
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['numRecord']    = $limit[1];

            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00114"][$language] /* Search Sucecssful */, 'data'=> $data);
        }
        
        public function clientPurchaseHistory($params)
        {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $ClientID = $db->userID;
            $site = $db->userType;

            if(empty($ClientID))
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00257"][$language] /* User id is invalid */, 'data' => '');
            }
            $db->orderBy('id','Desc');
            $db->where('client_id', $ClientID);
            $db->where('status', array('Draft'), 'NOT IN');
            // $db->join('payment_gateway_details pg','s.id = pg.purchase_id','LEFT');
            
            try{
                $result = $db->get('sale_order',null,'id, so_no, updated_at as payment_date, (payment_amount - discount_amount - redeem_amount + shipping_fee) as purchase_amount, status');
            } catch(Exception $e)
            {
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01185"][$language] /* Failed to execute query */, 'data' => $db->getlastquery());
            }

            if(!$result)
            {
                return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["M03624"][$language] /* No Result Found */, 'data' => '');
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $result);
        }

        public function updateSOStatus($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $ClientID = $db->userID;
            // $site = $db->userType;
            $status = $params["status"];
            $fromPage = $params["fromPage"];
            $id = $params["id"];
            $todayDate = date("Y-m-d H:i:s");


            if(empty($ClientID)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00257"][$language] /* User id is invalid */, 'data' => '');
            }

            if($fromPage == 'paymentListing'){
                $updateData = array(
                    'status'  => $status,
                    'updated_at' => $todayDate,
                );
                $db->where("id", $id);
                $db->where("status", 'Failed');
                $db->update('sale_order', $updateData);

                $db->where("sale_id", $id);
                $db->where("client_id", $ClientID);
                $db->where("item_name", 'Redeemed Points');
                $pointDetail = $db->getOne('sale_order_detail');

                if($pointDetail){
                    unset($dataIn);
                    $dataIn['id']               = $ClientID;
                    $dataIn['creditType']       = 'bonusDef';
                    $dataIn['adjustmentType']   = 'Adjustment In';
                    $dataIn['adjustmentAmount'] = $pointDetail['quantity'];
                    $dataIn['remark']           = 'Refund Points';
                    // $dataIn['isMember']         = '1';
                    $creditOutput = Wallet::creditAdjustment($dataIn);
                }
            }



            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => '');
        }

        public function updateGuestToken($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $ClientID = $db->userID;
            $saleID = $params["id"];
            $bkend_token = $params["bkend_token"];
            $todayDate = date("Y-m-d H:i:s");

            if(empty($ClientID)){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00257"][$language] /* User id is invalid */, 'data' => '');
            }

            // Previous Function
            // $updateData = array(
            //     'sale_id'  => $saleID,
            // );
            // $db->where("client_id", $ClientID);
            // $db->update('guest_token', $updateData);

            // $updateData2 = array(
            //     'disabled'  => 1,
            //     'updated_at'  => $todayDate,
            // );
            // $db->where("client_id", $ClientID);
            // $db->where("disabled", 0);
            // $db->update('shopping_cart', $updateData2);

            // New Function
            $updateSaleID = array(
                'sale_id'  => '',
            );
            $db->where("client_id", $ClientID);
            $db->update('guest_token', $updateSaleID);

            $updateShoppingCart = array(
                'disabled'  => 1,
                'updated_at'  => $todayDate,
            );
            $db->where("client_id", $ClientID);
            $db->where("disabled", 0);
            $db->update('shopping_cart', $updateShoppingCart);

            $db->where("sale_id", $saleID);
            $db->where("type", array("stock", ""), 'IN');
            $db->where("deleted", 0);
            $getPreviousOrder = $db->get('sale_order_detail',null, 'product_id, quantity, product_template_id');

            $db->where("client_id", $ClientID);
            $guestToken = $db->getValue('guest_token', 'token');

            // add to shopping cart
            foreach ($getPreviousOrder as $value) {
                $cartParams['packageID'] = $value['product_id'];
                $cartParams['quantity'] = $value['quantity'];
                $cartParams['product_template'] = $value['product_template_id'];
                $cartParams['clientID'] = $ClientID;
                $cartParams['bkend_token'] = $bkend_token;
                $cartParams['type'] = 'add';
                $cartParams['step'] = 'reorder';
                $addCartResult = Inventory::addShoppingCart($cartParams);

                if(!$addCartResult){
                    return array("code" => 1, "status" => "error", "statusMsg" => 'Failed2' , 'data' => $addCartResult['statusMsg']);
                }
                
            }

            return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["A00684"][$language] /* Update Successful */, 'data' => $bkend_token);
        }

        public function sendClientEnquiryTelegramNotification($messageCode, $content = NULL, $subject = NULL, $find = array(), $replace = array(), $scheduledAt = '', $priority = 1, $type = '', $chat_id)
        {
            $db = MysqliDb::getInstance();
            // $provider = self::provider;
            
            if(!$messageCode) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');

            $db->resetState();
            

            //start
            $db->where('name','telegramMemberEnquiry');
            $db->where('type','telegram');
            $telegramGroupID = $db->getValue('system_settings','value');
            //end

            $db->where('code', $messageCode);
            $msgCodeResult = $db->getOne("message_code", "title AS subject, content");

            // Get provider details for mapping purpose
            $db->where('company', 'GoTastyMemberEnquiry');
            $providerID = $db->getValue('provider', 'id');
            
            
            if ($msgCodeResult && $telegramGroupID && $providerID){
                
                $sentHistoryTable = 'sent_history_'.date('Ymd');
                
                $check = $db->tableExists($sentHistoryTable);

                if(!$check) {
                    $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
                }
                    
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
                $insertData['recipient'] = $telegramGroupID;
                $insertData['type'] = 'GoTastyMemberEnquiry';
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

        public function submitContactUs($params){

            global $config;
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $browserInfo    = General::getBrowserInfo();
            $browser        = $browserInfo['browser']?$browserInfo['browser']:"Unknown";
            $browserVer     = $browserInfo['browser_version']?$browserInfo['browser_version']:"Unknown";
            $osPlatform     = $browserInfo['os_platform']?$browserInfo['os_platform']:"Unknown";
            $device         = $browserInfo["device"]?$browserInfo["device"]:"Unknown";
            $ipAddress      = $browserInfo["ip"]?$browserInfo["ip"]:"Unknown";
           
            $location       = General::ip_info($ipAddress);
            $country        = $location['country'];

            $client_name    = trim($params['client_name']);
            $client_email   = trim($params['client_email']);
            $page_url       = $params['page_url'];
            $action_type    = $params['action_type'];
            $type           = $params['type'] ?: 'info';
            $phone          = $params['phone'] ?: '-';
            $description    = $params['description'] ?: '-';
            $message        = trim($params['message']);
            $uploadImage    = $params['uploadImage'];
            $dateTime       = date("Y-m-d H:i:s");

            if(!$client_name){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03789"][$language] /* Name cannot be empty */, 'data' => '');
            }

            if(!$client_email){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03790"][$language] /* Email cannot be empty */ , 'data' => '');
            }

            if (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00121"][$language] /* Invalid email format */, 'data' => '');
            }

            // if(!$page_url){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Page URL have issues.", 'data' => '');
            // }

            if($type == 'info'){
                $mail           = new PHPMailer();
                $referenceID    = General::generateReferenceID('gmailSMTP');

                $find = array(
                    "%%clientName%%", "%%clientEmail%%", "%%page_url%%", "%%country%%", "%%ipAddress%%", "%%browser%%", "%%device%%", "%%action_type%%"
                );

                $replace = array(
                    $client_name, $client_email, $page_url, $country, $ipAddress, $browser, $device, $action_type
                );

                if($config['environment'] == "prod"){
                    $notify_recipient = "-1001439171852";
                }else{
                    $notify_recipient = "-1001439171852";
                }

                // Inform admin about user creation of contrcat
                // $referenceID = General::generateReferenceID('telegram');
                $notifyRes = Telegram::createCXProfitTelegramNotification('10016', NULL, NULL, $find, $replace,"","",$notify_recipient, 'telegramEncryptedLandingTrack');
                if(!$notifyRes){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to send telegram notification enquiry", 'data' => "");
                }

                // Send email to user
                $notifyRes = Message::createCustomizeMessageOut('888', NULL, NULL, $find, $replace, "", "", $client_email, "gmailSMTP");
                if(!$notifyRes){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to submit enquiry", 'data' => "");
                }
            }else if($type == 'enquiry'){
                if(!$message)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M04097"][$language] /* Message cannot be empty */ , 'data' => '');
                }
                $insertData = array(
                    'client_name'   => $client_name,
                    'client_email'  => $client_email,
                    'message'   => $message,
                    'type'          => $type,
                    'browser'       => $browser,
                    'country'       => $country,
                    'device'        => $device,
                    'ip_address'    => $ipAddress,
                    'created_at'    => $dateTime,
                );
                $getStarted = $db->insert('goTasty_enquiry', $insertData);

                $find        = array("%%name%%", "%%email%%", "%%message%%");
                $replace     = array($client_name, $client_email, $message);
                // $referenceID = General::generateReferenceID('telegram');
                $notifyRes   = Client::sendTelegramMessage('10017', NULL, NULL, $find, $replace,"","","GoTastyMemberEnquiry");

                // Send email to client
                $outputEmail = Message::createMessageOut('10029', $content, $eventSection, '', '', '' ,'', $client_email);

                // send email to internal
                $find        = array("%%client_name%%", "%%client_email%%", "%%message%%");
                $replace     = array($client_name, $client_email, $message);
                $outputEmail2 = Message::createMessageOut('10031', $content, $eventSection, $find, $replace, '' ,'');
            }
            else if($type == 'career'){
                if($phone){
                    $mobileNumberCheck = General::mobileNumberInfo($phone, "MY");
                    if($mobileNumberCheck['isValid'] != 1){
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00942"][$language] /* Invalid phone number */, 'data' => $mobileNumberCheck);
                    }
                    $phone = $mobileNumberCheck['phone'];
                }
                $insertData = array(
                    'client_name'   => $client_name,
                    'client_email'  => $client_email,
                    'phone_number'  => $phone,
                    'message'   => $message,
                    'type'          => $type,
                    'browser'       => $browser,
                    'country'       => $country,
                    'device'        => $device,
                    'ip_address'    => $ipAddress,
                    'created_at'    => $dateTime,
                );
                $id = $db->insert('goTasty_enquiry', $insertData);

		    $mediaReferenceID = 0;

                if(!empty($uploadImage)) {
                    $db->where('reference_id', $id);
                    $db->where('deleted', '0');
                    $db->where('type', 'Image');
                    $updateArray = array(
                        'deleted' => '1'
                    );
                    $db->update('career_media', $updateArray);
                    foreach($uploadImage as $key => $val) {
                        $imgSrc = json_decode($val['imgData'], true);
                        $uploadParams['imgSrc'] = $imgSrc;
                        $uploadRes = aws::awsUploadImage($uploadParams);
    
                        if($uploadRes['status'] == 'ok') {
                            $imageUrl = $uploadRes['imageUrl'];
    
                            // insert product_media
                            $insertImage = array(
                                "type" => "Image",
                                "name" => $val['imgName'],
                                "url" => $imageUrl,
                                "reference_id" => $id,
                                "created_at"   => $dateTime
                            );

                            $insertInvImage = $db->insert('career_media', $insertImage);
			    $mediaReferenceID = $insertInvImage;

                            if(!$insertInvImage) {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                            }
                        } else {
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                        }
                    }
                }
                else if(empty($uploadImage))
                {
                    $db->where('reference_id', $id);
                    $db->where('deleted', '0');
                    $db->where('type', 'Image');
                    $updateArray = array(
                        'deleted' => '1'
                    );
                    $db->update('career_media', $updateArray);
                }
                $find        = array("%%name%%", "%%email%%", "%%phone%%");
                $replace     = array($client_name, $client_email, $phone);
                $notifyRes   = Client::sendTelegramMessage('10019', NULL, NULL, $find, $replace,"","","GoTastyMemberEnquiry");

                 // Send email to client
                $outputEmail = Message::createMessageOut('10028', $content, $eventSection, '', '', '' ,'', $client_email);

                // send email to internal
                $find        = array("%%client_name%%", "%%client_email%%", "%%phone%%");
                $replace     = array($client_name, $client_email, $phone);
                $outputEmail2 = Message::createMessageOut('10030', $content, $eventSection, $find, $replace, '' ,'', '', $mediaReferenceID);
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid enquiry type.", 'data' => "");
            }

            // Insert activity log
            $activityData = array('username' => $client_name);
            $activityRes  = Activity::insertActivity('Enquiry', 'T00036', 'L00036', $activityData, "", "", "");
            // Failed to insert activity
            if(!$activityRes){
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity.", 'data' => "");
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["M04036"][$language] /* Thank you for contacting us, our support will reply to you within 1 working day */, 'data'=> "");
        }

        function modifyPhoneNumber($phone) {
            if (substr($phone, 0, 2) === '60') {
                return $phone;
            } elseif (substr($phone, 0, 1) === '0') {
                return '60' . substr($phone, 1);
            } else {
                return '60' . $phone;
            }
        }

        public function parcelhubCheckLabel($params, $command){
            // Checkpoint
            global $config;
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tblDate     = date("Ymd");
            $createTime  = date("Y-m-d H:i:s");

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'parcelLabel');
            $db->where('name', 'parcel');
            $providerId = $db->getOne('provider');

            if($providerId)
            {
                $db->where('provider_id', $providerId['id']);
                $providerSetting = $db->getOne('provider_setting');
            }

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'createParcel');
            $db->where('name', 'parcel');
            $bearerID = $db->getValue('provider', 'id');

            if($bearerID)
            {
                $db->where('provider_id', $bearerID);
                $db->where('name', 'bearer');
                $providerSettingBearer = $db->getValue('provider_setting', 'value');
            }

            $webserviceID = OutgoingWebservice::insertOutgoingWebserviceData($params, $tblDate, $createTime, $providerSetting['value']);

            $params = array(
                'hawb_no' => $params['hawb_no']
            );
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $providerSetting['value'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: '.$providerSettingBearer
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            if($response)
            {
                $status = 'ok';
            }
            else
            {
                $status = 'error';
            }

            OutgoingWebservice::updateOutgoingWebserviceData($webserviceID, base64_encode($response), $status, date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());


            if($response)
            {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '' , 'data' => base64_encode($response));
            }
        }

        public function parcelhubCreateParcel($params){
            global $config;
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tblDate     = date("Ymd");
            $createTime  = date("Y-m-d H:i:s");

            $saleID       = $params['sale_id'];
            $deliveryOrderNo = $params['delivery_order_no'];
            $parcelWeight = $params['parcel_weight'];
            $parcelHeight = $params['parcel_height'];
            $parcelWidth  = $params['parcel_width'];
            $parcelLength = $params['parcel_length'];

            $shipper_name = $params['shipper_name'];
            $shipper_address_line_1 = $params['shipper_address_line_1'];
            $shipper_address_line_2 = $params['shipper_address_line_2'];
            $shipper_city = $params['shipper_city'];
            $shipper_postcode = $params['shipper_postcode'];
            $shipper_state = $params['shipper_state'];
            $shipper_country_code = $params['shipper_country_code'];
            $shipper_tel = $params['shipper_tel'];

            $receiver_contact_person = $params['receiver_contact_person'];
            $receiver_address_line_1 = $params['receiver_address_line_1'];
            $receiver_address_line_2 = $params['receiver_address_line_2'];
            $receiver_city = $params['receiver_city'];
            $receiver_postcode = $params['receiver_postcode'];
            $receiver_state = $params['receiver_state'];
            $receiver_country_code = $params['receiver_country_code'];
            $receiver_tel = $params['receiver_tel'];
            $description = $params['remark'];
            $package_type = $params['package_type'];
            $pickup_type = $params['pickup_type'];
            $parcels = $params['parcels'];
            $receiver_name = $params['receiver_name'];
            $courier_name = $params['courier_name'];

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'createParcel');
            $db->where('name', 'parcel');
            $providerId = $db->getOne('provider');

            if($providerId)
            {
                $db->where('provider_id', $providerId['id']);
                $db->where('name','parcelhub');
                $providerSetting = $db->getOne('provider_setting');
            }

            if(!$shipper_name)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01258"][$language] /* Receiver / Company Name cannot be empty */, 'data'=> "");
            }
            if(!$receiver_tel)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01259"][$language] /* Receiver Phone Number cannot be empty */, 'data'=> "");
            }
            if(!$receiver_address_line_1)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01260"][$language] /* Receiver Address Line 1 cannot be empty */, 'data'=> "");
            }
            if(!$receiver_address_line_2)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01261"][$language] /* Receiver Address Line 2 cannot be empty */, 'data'=> "");
            }
            if(!$receiver_city)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01262"][$language] /* Receiver City cannot be empty */, 'data'=> "");
            }
            if(!$receiver_postcode)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01263"][$language] /* Receiver Post Code cannot be empty */, 'data'=> "");
            }
            if(!$receiver_state)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01264"][$language] /* Receiver State cannot be empty */, 'data'=> "");
            }
            if(!$receiver_country_code)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01265"][$language] /* Receiver Country Code cannot be empty */, 'data'=> "");
            }
            if(!$parcelWeight)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01254"][$language] /* Parcel Weight cannot be empty */, 'data'=> "");
            }
            if(!$parcelHeight)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01255"][$language] /* Parcel Height cannot be empty */, 'data'=> "");
            }
            if(!$parcelWidth)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01256"][$language] /* Parcel Width cannot be empty */, 'data'=> "");
            }
            if(!$parcelLength)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01257"][$language] /* Parcel Length cannot be empty */, 'data'=> "");
            }
            if(!$description)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01282"][$language] /* Remarks cannot be empty */, 'data'=> "");
            }

            $webserviceID = OutgoingWebservice::insertOutgoingWebserviceData($params, $tblDate, $createTime, $providerSetting['value']);

            $db->where('sale_id', $saleID);
            $db->where('deleted', '0');
            $saleOrderDetail = $db->get('sale_order_detail');

            $db->where('id', $saleID);
            $saleOrder = $db->getOne('sale_order');

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'createParcel');
            $db->where('name', 'parcel');
            $bearerID = $db->getValue('provider', 'id');

            if($bearerID)
            {
                $db->where('provider_id', $bearerID);
                $db->where('name', 'bearer');
                $providerSettingBearer = $db->getValue('provider_setting', 'value');
            }


            $parcels = array(
                array(
                    'description'    => 'SO No : ' . $saleOrder['so_no'],
                    'category'       => 'Food',
                    'weight'         => $parcelWeight,
                    'height'         => $parcelHeight,
                    'width'          => $parcelWidth,
                    'length'         => $parcelLength,
                    'declared_value' => $saleOrder['payment_amount'],
                    'currency_code'  => 'MYR'
                )
            );

            $params['parcels'] = json_encode($parcels);

            $params = array(
                'shipper_name' => $shipper_name,
                'shipper_address_line_1' => $shipper_address_line_1,
                'shipper_address_line_2' => $shipper_address_line_2,
                'shipper_city' => $shipper_city,
                'shipper_postcode' => $shipper_postcode,
                'shipper_state' => $shipper_state,
                'shipper_country_code' => $shipper_country_code,
                'shipper_tel' => $shipper_tel,
                'receiver_contact_person' => $receiver_contact_person,
                'receiver_address_line_1' => $receiver_address_line_1,
                'receiver_address_line_2' => $receiver_address_line_2,
                'receiver_city' => $receiver_city,
                'receiver_postcode' => $receiver_postcode,
                'receiver_state' => $receiver_state,
                'receiver_country_code' => $receiver_country_code,
                'receiver_tel' => $receiver_tel,
                'description' => $description,
                'package_type' => $package_type,
                'pickup_type' => $pickup_type,
                'parcels' => $params['parcels'],
                'receiver_name' => $receiver_name,
                'courier_name' => $courier_name
            );

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $providerSetting['value'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: '.$providerSettingBearer
            ),
            ));

            $response = curl_exec($curl);

            OutgoingWebservice::updateOutgoingWebserviceData($webserviceID, $response, $response['status'], date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            curl_close($curl);

            $cleanData = json_decode($response, true);
            if($cleanData)
            {
                $status = $cleanData['status'];
                $hawbNo = $cleanData['hawb_no'];
                $courier = $cleanData['courier'];
                $message = $cleanData['message'];
            }

            if($status = 1)
            {
                $status = 'ok';
            }
            else
            {
                $status = 'error';
            }

            if($message == 'Shipment created')
            {
                $db->where('b.deleted', 0);
                $db->where('status', 'Order Processing');
                $db->where('a.id', $saleID);
                $db->join('sale_order_detail b', 'a.id = b.sale_id', 'LEFT');
                $getSaleList = $db->get('sale_order a', null, 'a.id, b.product_id, b.item_name');
    
                if(!$getSaleList) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01187"][$language] /* Sale order doest not exits */, 'data'=> "");
                }

                unset($dataIn);
                $dataIn['hawb_no'] = $hawbNo;
    
                $trackingResult = Client::parcelhubCheckTrack($dataIn);

                if($courier_name == 'Whallo X')
                {
                    $courier_name = 'Parcelhub';
                }
                else if($courier_name == 'Whallo Cold')
                {
                    $courier_name = 'Whallo';
                }

                $updateData = array(
                    'delivery_partner' => $courier_name,
                    'tracking_number' => $hawbNo,
                    'status' => 'Pending for Pickup',
                    'updated_at' => $createTime,
                );
            
                $db->where('so_id', $saleID);
                $db->where('delivery_order_no', $deliveryOrderNo);
                $delivery_order = $db->update('inv_delivery_order', $updateData);

                $db->where('so_id', $saleID);
                $deliveryOrder = $db->get('inv_delivery_order');
                $validDelivery = array();
                $notCompleteDO = array();
                foreach($deliveryOrder as $deliveryDetail)
                {
                    if($deliveryDetail['status'] != 'Pending for Pickup' && $deliveryDetail['status'] != 'Cancelled')
                    {
                        $validDelivery[] = $deliveryDetail;
                    }
                }
                if(count($validDelivery) == 0)
                {
                    $db->where('do.so_id', $saleID);
                    $db->join('inv_delivery_order_detail dod', 'dod.inv_delivery_order_id = do.id', 'INNER');
                    $deliveryOrderList = $db->get('inv_delivery_order do', null, 'do.status');

                    foreach($deliveryOrderList as $detailCheck)
                    {
                        if(strtolower($detailCheck['status']) != 'pending for pickup' && strtolower($detailCheck['status']) != 'cancelled')
                        {
                            $notCompleteDO[] = $detailCheck;
                        }
                    }

                    $db->where('so_no', $saleOrder['so_no']);
                    $db->where('deleted', '0');
                    $saleOrderDetail = $db->get('sale_order_item');

                    $db->where('so_id', $saleID);
                    $db->where('status', 'sold');
                    $stockListDetail = $db->get('stock');
                    $data['stockListDetail'] =$stockListDetail;
                    $data['expandedSaleOrderDetail'] =$saleOrderDetail;
                    $data['notCompleteDO'] =$notCompleteDO;

                    // return array('status' => "error", 'code' => 110, 'statusMsg' => 'testing' , 'data' => $data);

                    if((count($stockListDetail) == count($saleOrderDetail)) && (count($notCompleteDO) == 0))
                    {
                        $updateData = array(
                            "tracking_id"           => $so_tracking,
                            "status"                => "Packed",
                            "updated_at"            => $createTime,
                        );
            
                        $db->where('id', $saleID);
                        $result = $db->update('sale_order', $updateData);
                    }
                }
    
                $data['Status'] = $status;
                $data['hawbNo'] = $hawbNo;
                $data['courier'] = $courier;
                $data['message'] = $message;
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '' , 'data' => $data);
            }
            else
            {
                $data['Status'] = $status;
                $data['hawbNo'] = $hawbNo;
                $data['courier'] = $courier;
                $data['message'] = $message;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $message , 'data' => $data);
            }
        }

        public function parcelhubCheckTrack($params, $command){
            global $config;
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tblDate     = date("Ymd");
            $createTime  = date("Y-m-d H:i:s");

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'parcelTrack');
            $db->where('name', 'parcel');
            $providerId = $db->getOne('provider');

            if($providerId)
            {
                $db->where('provider_id', $providerId['id']);
                $providerSetting = $db->getOne('provider_setting');
            }

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'createParcel');
            $db->where('name', 'parcel');
            $bearerID = $db->getValue('provider', 'id');

            if($bearerID)
            {
                $db->where('provider_id', $bearerID);
                $db->where('name', 'bearer');
                $providerSettingBearer = $db->getValue('provider_setting', 'value');
            }

            $webserviceID = OutgoingWebservice::insertOutgoingWebserviceData($params, $tblDate, $createTime, $providerSetting['value']);

            $params = array(
                'hawb_no' => $params['hawb_no']
            );
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $providerSetting['value'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: '.$providerSettingBearer
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            OutgoingWebservice::updateOutgoingWebserviceData($webserviceID, json_encode($response), json_encode($response["status"]), date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            $array = json_decode($response, true);

            if (is_array($array) && count($array) > 0) {
                $data["Status"] = $array[0]['status'];
                $data["Location"] = $array[0]['location'];
                $data["StatusUpdatedAt"] = $array[0]['status_updated_at'];
                $data["DisposeCode"] = $array[0]['dispose_code'];
                $data["Recipient"] = $array[0]['recipient'];
                $data["CreatedAt"] = $array[0]['created_at'];
                $data["UpdatedAt"] = $array[0]['updated_at'];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '' , 'data' => $data);
        }

        public function cancelParcelhub($params){
            $db                     = MysqliDb::getInstance();
            $language               = General::$currentLanguage;
            $translations           = General::$translations;
            $dateTime               = date("Y-m-d H:i:s");
            $userID                 = $db->userID;

            global $config;
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tblDate     = date("Ymd");
            $createTime  = date("Y-m-d H:i:s");

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'parcelCancel');
            $db->where('name', 'parcel');
            $providerId = $db->getOne('provider');

            if($providerId)
            {
                $db->where('provider_id', $providerId['id']);
                $providerSetting = $db->getOne('provider_setting');
            }

            $db->where('company', 'parcelhub');
            $db->where('deleted', '0');
            $db->where('type', 'createParcel');
            $db->where('name', 'parcel');
            $bearerID = $db->getValue('provider', 'id');

            if($bearerID)
            {
                $db->where('provider_id', $bearerID);
                $db->where('name', 'bearer');
                $providerSettingBearer = $db->getValue('provider_setting', 'value');
            }

            $webserviceID = OutgoingWebservice::insertOutgoingWebserviceData($params, $tblDate, $createTime, $providerSetting['value']);

            $params = array(
                'shipment_id' => $params['shipment_id'],
                'saleID'      => $params['saleID'],
            );
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $providerSetting['value'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: '.$providerSettingBearer
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            OutgoingWebservice::updateOutgoingWebserviceData($webserviceID, json_encode($response), json_encode($response["status"]), date("Y-m-d H:i:s"), 0, $tblDate, $db->getQueryNumber());

            $array = json_decode($response, true);
            if($array)
            {
                $status = $array['status'];
                $hawbNo = $array['hawb_no'];
                $courier = $array['courier'];
                $message = $array['message'];
                $errors = $array['errors'];
            }

            if($status == 1)
            {
                $status = 'ok';
            }
            else
            {
                $status = 'error';
            }
            $status = 'ok';

            if($status === 'ok')
            {
                # cancel DO
                $updateData = array(
                    'status'    => 'Cancelled',
                    'updater_id'=> $userID,
                    'updated_at'=> $dateTime,
                );
                $db->where('tracking_number', $params['shipment_id']);
                $db->update('inv_delivery_order', $updateData);

                # change back stock status to active
                $db->where('ido.tracking_number', $params['shipment_id']);
                $db->where('dod.disabled', '0');
                $db->join('inv_delivery_order_detail dod', 'dod.inv_delivery_order_id = ido.id', 'LEFT');
                $deliveryOrderDetail = $db->get('inv_delivery_order ido');
                
                foreach($deliveryOrderDetail as $detailStock)
                {
                    $updateData = array(
                        'status' => 'Active',
                        'so_id'  => '',
                        'updated_at' => $dateTime
                    );
                    $db->where('serial_number', $detailStock['serial_number']);
                    $db->update('stock', $updateData);

                    # update Sale order item table
                    $updateData = array(
                        'serial_number' => '',
                        'do_no'         => '',
                        'updated_by'    => $userID,
                        'updated_at'    => $dateTime,
                    );
                    $db->where('deleted', 0);
                    $db->where('serial_number', $detailStock['serial_number']);
                    $db->update('sale_order_item', $updateData);
                }

                # check current SO status
                $db->where('id', $params['saleID']);
                $saleOrderInfo = $db->getOne('sale_order');
                if(!$saleOrderInfo)
                {
                    return array('status'=>'error', 'code'=> 1, 'statusMsg'=> $translations["E01187"][$language] /* Sale order doest not exist */ , 'data'=>'');
                }

                if(strtolower($saleOrderInfo['status']) == 'packed')
                {
                    $updateData = array(
                        'status'        => 'Order Processing',
                        'updated_at'    => $dateTime,
                    );
                    $db->where('id', $params['saleID']);
                    $db->update('sale_order', $updateData);
                }
            }
            else
            {
                return array('status'=>'error', 'code'=> 1, 'statusMsg'=> $message . '<br>' . $errors['shipment_id'] , 'data'=>$array);
            }
            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $message , 'data'=>$array);
        }

        public function createEmailNotification($messageCode, $find = array(), $replace = array(), $recipient = null)
        {
            $content = NULL;
            $subject = NULL;
            $scheduledAt = '';
            $priority = 1;

            if($recipient == null){
                return false;
            }

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            if(!$messageCode) return false;
            if(!$scheduledAt)
                $scheduledAt = date('Y-m-d H:i:s');
    
            $db->where('code', $messageCode);
            $emailCodeResult = $db->getOne("message_code", "title AS subject, content");
    
            // Get provider details for mapping purpose
            // $db->where('company', 'gmail smtp');
            $db->where('name', ' email');
            $providerID = $db->getValue('provider', 'id');

            if ($emailCodeResult){
    
                $sentHistoryTable = 'sent_history_'.date('Ymd');
                
                $check = $db->tableExists($sentHistoryTable);
    
                if(!$check) {
                    $db->rawQuery('CREATE TABLE IF NOT EXISTS '.$sentHistoryTable.' LIKE sent_history');
                }
    
                $insertData['recipient'] = $recipient;
                $insertData['type'] = 'email';
                
                if (isset($subject) && !empty($subject))
                {
                    $insertData["subject"] = $subject;
                }
                else
                {
                    $insertData["subject"] = $emailCodeResult["subject"];
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
                        $insertData["content"] = str_replace($find, $replace, $emailCodeResult["content"]);
                    }
                    else
                    {
                        $insertData["content"] = $emailCodeResult["content"];
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
        
    }
?>
