<?php
    
    class Notification
    {
        
        function __construct($mail)
        {
            $this->mail = $mail;
        }
        
        function sendEmailsUsingSendmail($to, $subject, $content, $providerInfo)
        {
            
            $mail = $this->mail;
            
            $mail->isMail();
            $mail->Subject = $subject;
            $mail->addAddress($to);
            $mail->msgHTML($content);
            
            // Set sender information
            $mail->From = $providerInfo['username'];
            $mail->FromName = $providerInfo['company'];
            
            if ($this->emailReply) $mail->addReplyTo($this->emailReply, $this->emailReply);
            
            $mailsender = $mail->send();
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            
            return $mailsender;
            
        }
        
        function sendEmailsUsingSMTP($to, $subject, $content, $providerInfo, $fileAry)
        {
            $mail = $this->mail;
            
            $mail->isSMTP();
            $mail->Subject = $subject;
            $mail->addAddress($to);
            $mail->msgHTML($content);
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Authentication section
            $mail->Username = $providerInfo['username'];
            $mail->Password = $providerInfo['password'];
            
            // Set sender information
            $mail->From = $providerInfo['username'];
            $mail->FromName = $providerInfo['company'];
            
            if ($this->emailReply) $mail->addReplyTo($this->emailReply, $this->emailReply);

	    foreach($fileAry as $row){
	        $mail->AddStringAttachment($row['bin'], $row['file']);
	    }
                   
            // $mail->Sender = "";
            $mailsender = $mail->send();
            $mail->clearAttachments();
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            
            return $mailsender? true : $mail->ErrorInfo;
            
        }
        
        
        function sendSMS($recipient, $text, $providerInfo)
        {   

            // $post_data = array (
            //                                 'email' => $providerInfo['username'],
            //                                 'key' => $providerInfo['api_key'],
            //                                 'recipient' => $recipient,
            //                                 'message' => $text,
            //                             );
            $post_data = array (
                                        'apiKey' => $providerInfo['api_key'],
                                        'recipients' => $recipient,
                                        'messageContent' => $text,
                                        );
            $post_data = json_encode($post_data, true);
            echo "Currently trying to send sms : ";
            echo "\n";
            echo $providerInfo['username'];
            echo "\n";
            echo $text;
            echo "\n";
            echo $recipient;
            echo "\n";
            $URL = $providerInfo['url1'];
            echo $URL;
            echo "\n";
            //$URL = $providerInfo['url1']."email=".$providerInfo['username']."&key=".$providerInfo['api_key']."&recipient=".$recipient."&message=".urlencode($text);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $URL);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            
            $response = curl_exec($ch);
            echo "response";
            echo $response;
            echo '\n';
            curl_close($ch);
            
            return $response;
        }
        
        function sendXun($xunNumber, $message, $subject, $providerInfo)
        {
            global $db, $msgpack;
            
            $url = $providerInfo['url1'];
            $fields = array("api_key" => $providerInfo['api_key'],
                            "business_id" => $providerInfo['username'],
                            "message" => $message,
                            "tag" => $subject,
                            "mobile_list" => $xunNumber
                            );
            
            $dataString = json_encode($fields);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                                   'Content-Type: application/json',
                                                   'Content-Length: ' . strlen($dataString))
                       );
        
            $response = curl_exec($ch);
            curl_close($ch);
            
            return json_decode($response, true);
        }

        public function sendTelegramNotification($params){
            global $config;
            $db = MysqliDb::getInstance();

            // retrieve bot api
            $chat_id = $params['chat_id'];
            $content = $params['content'];

            $URL1['url1'] = $params['url'];

            $URL = $URL1['url1'] . '/sendMessage';
            echo "\n";
            echo "URL1 testing = ";
            echo $URL;
            echo "\n";
            echo "recipient = ";
            echo $chat_id;
            echo "\n";
            echo "content = ";
            echo $content;
            
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
    
            return $response;
        }
        
    }
    
    ?>
