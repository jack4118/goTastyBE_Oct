<?php

/**
 * Script to send Low Stock Warning at specific time 
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


$db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
$pdb = new MysqliDb($config['dBHost'], $config['processUser'], $config['processPassword'], $config['dB']);
$log = new Log($logPath, $logBaseName);
$setting = new Setting($db);
$mail = new PHPMailer();
$notification = new Notification($mail);
$provider = new Provider($db);
$message = new Message($db, '', $provider);
$process = new Process($pdb, $setting, $log);

$processName = "A";
$sleepTime =5;
$limit = 1;


unset($processEnable);

$processEnable = $setting->systemSetting['processOutGoingEnableFlag'];
$log->write(date("Y-m-d H:i:s")." Enable Flag is: ".$processEnable.".\n");

$db->where('name','lowStockThreshold');
$lowStockQuantity=$db->getValue('system_settings','value');
$log->write(date("Y-m-d H:i:s")." Low Stock Threhold: ".$lowStockQuantity.".\n");

if($processEnable == 1)
{
    $db->orderby('id','asc');
    $db->where('product_type', "package", "not LIKE");
    $productList=$db->get('product',null,'id,name,barcode');

    $db->where('code', '10015');
    $msgCodeResult = $db->getOne("message_code", "title AS subject, content");

    foreach($productList as $key => $value) {
         unset($dataIn);
         $dataIn['db'] =$db;
         $dataIn['product_id'] =  $value['id'];
         $dataIn['quantity'] = 0;
         $dataIn['purchaseProduct'] = $dataIn;
         $dataOut = checkStockQuantity($dataIn);

        if($dataOut)
            {
        if($dataOut['data'][0]['finalAmount']){
            $productDetail['stockQuantity']      = $dataOut['data'][0]['finalAmount'];

        }
        else if($dataOut['data']){
            $productDetail['stockQuantity']      = $dataOut['data'];
        }
        else if($dataOut['data'] == ''  || $dataOut['data'][0]['finalAmount'] == ''){
            $productDetail['stockQuantity']      = 0;
        }
        else if(!$dataOut['data']){
            $productDetail['stockQuantity']      = 0;
        }

            }
        else
            {
            $productDetail['stockQuantity']      = 0;
            }

        if($productDetail['stockQuantity'] <= $lowStockQuantity){
                    //  $productDetail['id']=$value['id'];
                        $productDetail['name']=$value['name'];
                        $productDetail['skuCode']=$value['barcode'];
                        $productDetailList[]=$productDetail;
            }
    }
    if($productDetailList){
        $finalResult=array();
        foreach($productDetailList as $key => $value){
                $find = array("%%productName%%", "%%stock%%", "%%skucode%%");
                $replace = array($value['name'], $value['stockQuantity'],$value['skuCode']);
            //$finalResult .=  str_replace($find, $replace, $msgCodeResult["content"]."\n");
            $finalResult[]=  str_replace($find, $replace, $msgCodeResult["content"]."\n"); //
        }
        $total = count($finalResult);  // Get the total number of items
        $log->write(date("Y-m-d H:i:s")." Total low stock: ".$total.".\n");
        $itemsPerMessage = 30;  // Number of items to print per loop

        for ($i = 0; $i < $total; $i += $itemsPerMessage) {
            $chunk[] = array_slice($finalResult, $i, $itemsPerMessage);  // Get a chunk of items to print
        }

        for($i=0;$i<count($chunk);$i++){

            for($j=0;$j<count($chunk[$i]);$j++){
                $resultString .= $chunk[$i][$j];
            }

            $outputArray = createStockWarningTelegramNotification($db,NULL,"*Low Stock Notification*\n".$resultString."\nCreated Time : ".date("Y-m-d H:i:s"),$msgCodeResult['subject'],"","","telegramAdminGroup","");
            $resultString = "";
        }

    }

    $log->write(date("Y-m-d H:i:s")." The process is going to sleep for: ". $sleepTime. "second(s)\n");
    sleep($sleepTime);

}
else
{
    $log->write(date("Y-m-d H:i:s")." Process :".$processName ." has been disabled. Do nothing.\n");
}



function checkStockQuantity($params)
    {
        $db2                     = $params['db'];
        //$language               = General::$currentLanguage;
       // $translations           = General::$translations;
        $dateTime               = date("Y-m-d H:i:s");

        $purchaseProduct = $params['purchaseProduct'];
        $product_id = $params['product_id'];
        $quantity = $params['quantity'];
        $text = $params['text'];

        $db2->where("id", $product_id);
        $product_type = $db2->getValue("product", "product_type");

        if(strtolower($product_type)=="package" && !$text) {
            $productQuantity = $quantity;
            if($productQuantity == 0){
                $productQuantity = 1;
            }
            $db2->where('package_id', $product_id);
            $productListArr = $db2->get('package_item',null, 'product_id,quantity');


            $sums = [];
            foreach ($productListArr as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                if (!isset($sums[$productId])) {
                    $sums[$productId] = 0;
                }

                $sums[$productId] += $quantity;
            }

            $result = [];
            foreach ($sums as $productId => $totalQuantity) {
                $result[] = [
                    'product_id' => $productId,
                    'quantity' => $totalQuantity * $productQuantity,
                ];
            }


            foreach ($result as $key => $value) {
                $db2->where('product_id', $value['product_id']);
                $availableStockPackage = $db2->getValue('stock', 'count(*)') ?? 0;

                $newArr[$key]['product_id'] = $result[$key]['product_id'];
                $newArr[$key]['quantity'] = $availableStockPackage;
            }

         
                $result2 = [];
                
                foreach ($array2 as $index => $item2) {
                    $productId = $item2['product_id'];
                    $quantity2 = $item2['quantity'];
                    
                    $item1 = $array1[$index] ?? ['quantity' => 0];
                    $quantity1 = $item1['quantity'];
                    
                    $result2[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity2 - $quantity1,
                    ];
                }
                
            foreach ($result2 as $item) {
                $quantity = $item['quantity'];
                
                if ($quantity < 0) {
                    return array('status'=>'error', 'code'=>1, 'statusMsg'=> $translations["M03692"][$language] /* Successfully */ , 'data'=> '');
                }
            }

            

            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["M03692"][$language] /* Successfully */ , 'data'=> '1');
        }


        $db2->where('product_id', $product_id);
        $db2->where('status', 'Active');
        $availableStock = $db2->getValue('stock', 'count(*)') ?? 0;


        $db2->where('product_id', $product_id);
        $db2->where('deleted', 0);
        $wishListStock = $db2->getValue('product_wishlist', 'count(*)') ?? 0;

        $db2->where("so.status", array("Pending","Pending Payment Approve", "Paid"),"IN");
        $db2->where("sod.deleted", 0);
        $db2->where("sod.product_id", $product_id);
        $db2->join("sale_order so", "sod.sale_id=so.id", "INNER");
        $lockStock = $db2->getValue("sale_order_detail sod", "SUM(sod.quantity)") ?? 0;	

        $result = intval($availableStock) - intval($quantity) - intval($lockStock);
        if($result < 0)
        {
            $noStockDetails['product_id'] = $product_id;
            $noStockDetails['availableStock'] = $availableStock;
            $noStockDetails['lockStock'] = $lockStock;
            $noStockDetails['wishListStock'] = $wishListStock;
            $noStockDetails['quantity'] = $quantity;
            $noStockDetails['finalAmount'] = $result;

            $outOfStock[] = $noStockDetails;
        }           

        if (!empty($outOfStock)){
            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03454"][$language] /* Out of stock */, 'data' => $outOfStock);
        } else {
            return array('status'=>'ok', 'code'=>0, 'statusMsg'=> $translations["M03692"][$language] /* Successfully */ , 'data'=> $result);
        }
    }


function  createStockWarningTelegramNotification($db, $content=NULL,$fr, $subject, $scheduledAt = '', $priority = 1, $type = '', $chat_id)
{

        if(!$scheduledAt)
            $scheduledAt = date('Y-m-d H:i:s');

        $db->resetState();

        $db->where('type','telegram');
        $db->where('name','telegramLowStockWarning');
        $recipient=$db->getValue("system_settings","value");

        $db->where('company', 'GoTastyStockNotice');
        $providerID = $db->getValue('provider', 'id');
    
    
    if ($providerID && $recipient){

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
                $insertData["content"] = $fr;
            }

        // Map to get the provider_id
        $insertData['recipient'] = $recipient;
        $insertData['type'] = 'stockWarningTelegram';
        $insertData["provider_id"] = $providerID;
        $insertData["created_at"] = date("Y-m-d H:i:s");//
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

   // return true;
}
?>
