<?php
// use DeviceDetector\DeviceDetector;

if ($_SERVER['REQUEST_METHOD'] == 'POST' || $_SERVER['REQUEST_METHOD'] == 'GET') {

    // include_once 'include/class.database.php';
    include_once('include/classlib.php');
    include_once('language/lang_all.php');
    // include_once 'include/class.xun_email.php';
    include_once 'include/class.outgoing_business.php';
    // include_once 'include/class.xun_user.php';
    // include_once 'include/class.xun_erlang.php';
    // include_once 'include/class.xun_crypto.php';
    // include_once 'include/class.xun_livechat.php';
    // include_once 'include/class.xun_marketplace.php';
    // include_once 'include/class.xun_xmpp.php';
    // include_once 'include/class.xun_sms.php';
    // include_once 'include/class.xun_admin.php';
    // include_once 'include/class.post.php';
    // include_once 'include/class.webservice.php';
    include_once 'include/class.outgoing_webservice.php';
    // include_once 'include/class.message.php';
    // include_once 'include/class.setting.php';
    // include_once 'include/class.general.php';
    // include_once 'include/class.log.php';
    // include_once 'include/libphonenumber-for-php-master-v7.0/vendor/autoload.php';
    // include_once 'include/class.language.php';
    // include_once 'include/class.provider.php';
    // include_once 'include/class.ticketing.php';
    // include_once 'include/class.country.php';
    // include_once 'include/class.xun_aws.php';
    // include_once 'include/class.xun_giftcode.php';
    // include_once 'include/class.xun_tree.php';
    // include_once 'include/class.xun_referral.php';
    // include_once 'include/class.xun_currency.php';
    // include_once 'include/class.xun_freecoin_payout.php';
    // include_once 'include/class.xun_company_wallet.php';
    // include_once 'include/class.xun_company_wallet_api.php';
    // include_once 'include/class.push_notification.php';
    // include_once 'include/class.abstract_xun_user.php';
    // include_once 'include/class.xun_user_model.php';
    // include_once 'include/class.xun_user_service.php';
    // include_once 'include/class.xun_business_model.php';
    // include_once 'include/class.xun_business_service.php';
    // include_once 'include/class.xun_livechat_model.php';
    // include_once 'include/class.xun_wallet_transaction_model.php';
    // include_once 'include/class.xun_group_chat.php';
    // include_once 'include/class.xun_payment_gateway_model.php';
    // include_once 'include/class.xun_payment_gateway_service.php';

    // include_once 'include/class.xun_kyc.php';
    // include_once 'include/class.xun_wallet.php';
    // include_once 'include/class.xun_ip.php';
    // include_once 'include/class.xun_announcement.php';

    // include_once 'include/class.binance.php';

    // include_once 'include/class.xun_commission.php';
    // include_once 'include/class.xun_service_charge.php';
    // include_once 'include/class.xun_in_app_notification.php';
    // include_once 'include/class.xun_pay.php';
    // include_once 'include/class.xun_pay_provider.php';
    // include_once 'include/class.reloadly.php';
    // include_once 'include/class.group_chat_model.php';
    // include_once 'include/class.group_chat_service.php';
    // include_once 'include/class.xun_swapcoins.php';
    // include_once 'include/class.xun_pay_model.php';
    // include_once 'include/class.xun_pay_service.php';
    // include_once 'include/class.giftnpay.php';
    // include_once 'include/class.xun_coins.php';
    // include_once 'include/class.account.php';
    // include_once 'include/class.xun_story.php';
    // include_once 'include/class.xun_aws_web_services.php';
    // include_once 'include/class.xun_crowdfunding.php';
    // include_once 'include/class.xun_payment_gateway.php';
    // include_once 'include/class.xun_payment.php';
    // include_once 'include/class.xun_phone_approve.php';
    // include_once 'include/class.xun_phone_approve_service.php';
    // include_once 'include/class.xun_phone_approve_model.php';
    // include_once 'include/class.business_partner.php';
    // include_once 'include/class.xun_reward.php';
    // include_once 'include/class.xun_cashpool.php';
    // include_once 'include/class.cash.php';
    // include_once 'include/class.xun_sales.php';
    // include_once 'include/class.xun_marketer.php';
    // include_once 'include/class.xun_miner_fee.php';
    // include_once 'include/class.xun_business_coin.php';
    // include_once 'include/class.excel.php';
    // include_once 'include/class.xun_reseller.php';  
    // include_once 'include/class.whoisserver.php'; 
    // include_once 'include/class.campaign.php'; 
    // include_once 'include/class.xun_advertisement.php'; 
    // include_once 'include/class.simplex.php';
    // include_once 'include/class.xanpool.php';

    // To load composer for device detector
    // include_once ('device-detector/Spyc.php');
    // include_once ('device-detector/autoload.php');

    $url_string = $_GET["url_string"];
    
    $filtered_json_commands = array("giftnpaycallback");

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $json_data = json_decode(file_get_contents('php://input'), true);

        $command = str_replace("/", "", $url_string);
        
        if ($json_data["command"] && !in_array($command, $filtered_json_commands)) {
            $command = $json_data["command"];
            $params  = $json_data["params"];
        }

    } else {
        $command         = str_replace("/", "", $url_string);
        
        unset($_GET["url_string"]);
        
        $json_data       = $_GET;
    }

  

    $slave_db_connection_commands = array("marketplaceadvertisementlist", "marketplaceadvertisementuserlist", "marketplaceadvertisementuserdetails", "marketplaceadvertisementuserorderlist", "marketplacepayment_methoduserdetails", "marketplacepayment_methoduserlist", "marketplacesettingsummary", "marketplaceadvertisementdetails", "marketplacepayment_methodlistv2", "marketplacecurrencylist", "marketplacexmppmessage", "marketplaceadvertisementorderlist","appcryptoaddressexternallist","appcryptoaddressexternaldetails");

    if(in_array($command, $slave_db_connection_commands)){
        $db = new MysqliDb($config['dBHostSlave'], $config['dBUserSlave'], $config['dBPasswordSlave'], $config['dB']);
    }else{
        $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    }

    $db2 = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $partnerDB = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], "thenuxPartner");

    $currentPath = __DIR__;
    $logPath = $currentPath . '/log/';
    $logBaseName = basename(__FILE__, '.php');
    $path = realpath($logPath);

    // $deviceDetector   = new DeviceDetector();
    // $whoisserver      = new WhoisServer();
    $setting       = new Setting($db);
    $general       = new General($db2, $setting);
    //$log           = new Log();
    $log           = new Log($logPath, $logBaseName);
    $provider      = new Provider($db);
    $message       = new Message($db2, $general, $provider);
    $webservice    = new Webservice($db2, $general, $message);
    $outgoingWebservice = new OutgoingWebservice($db2);
    $language      = new Language($db, $general, $setting);
    $post          = new post();
    $country       = new Country($db, $general);
    $xunEmail      = new XunEmail($db, $post);
    $outgoingBusiness   = new OutgoingBusiness($db, $post, $general, $xunEmail);
    $xunGroupChat   = new xunGroupChat($db, $post, $general, $xunEmail);
    $xunUser       = new XunUser($db, $post, $general, $whoisserver);
    $xunErlang     = new XunErlang($db, $post, $general);
    $xunCrypto     = new XunCrypto($db, $post, $general);
    $xunXmpp       = new XunXmpp($db, $post);
    $xunSms        = new XunSms($db, $post);
    $xunLivechat   = new XunLivechat($db, $post);
    $xunAdmin      = new XunAdmin($db, $setting, $general, $post);
    $xunMarketplace = new XunMarketplace($db, $post, $general);
    $ticket        = new Ticket($db, $general, $setting, $message, $log);
    $xunAws        = new XunAws($db, $setting);
    $xunGiftCode   = new XunGiftCode($db, $post, $general);
    $xunTree       = new XunTree($db, $setting, $general);
    $xunReferral   = new XunReferral($db, $setting, $general, $xunTree);
    $xunCurrency   = new XunCurrency($db);
    $xunCompanyWalletAPI   = new XunCompanyWalletAPI($db, $setting, $general, $post);

    // $binance = new Binance($config['binanceAPIKey'], $config['binanceAPISecret'], $config['binanceAPIURL'], $config['binanceWAPIURL']);

    $xunKYC   = new XunKYC($db, $setting, $general);
    $xunAnnouncement  = new XunAnnouncement($db, $setting, $general);
    $xunServiceCharge  = new XunServiceCharge($db, $setting, $general);
    $account = new Account($db, $setting, $message, $provider, $log);
    $xunPay  = new XunPay($db, $setting, $general, $account);
    
    $giftnpay = new GiftnPay($db, $setting, $post);
    $xunCoins = new XunCoins($db, $setting);
    $xunStory = new XunStory($db, $post, $general, $setting);
    $xunAWSWebservices = new XunAWSWebservices($db);
    $xunCrowdfunding = new XunCrowdfunding($db, $post, $general, $setting);
    $xunPaymentGateway = new XunPaymentGateway($db, $post, $general, $setting, $xunCrypto, $xunCoins);
    $xunPayment = new XunPayment($db, $post, $general, $setting, $xunCrypto, $xunCoins);
    $xunSwapcoins  = new XunSwapcoins($db, $general, $setting, $post, $binance, $account, $xunPaymentGateway);
    $xunPhoneApprove = new XunPhoneApprove($db, $setting, $general, $post, $account);
    $xunBusinessPartner = new XunBusinessPartner($db, $post, $general, $partnerDB, $xunCrypto);
    $xunReward = new XunReward($db, $partnerDB, $post, $general, $setting);
    $cash = new Cash($db, $setting, $message, $provider);
    $xunCashpool = new XunCashpool($db, $general, $setting, $account);
    $xunSales = new XunSales($db, $partnerDB, $post, $general, $setting);
    $xunMarketer = new XunMarketer($db, $setting, $general);
    $xunMinerFee = new XunMinerFee($db, $general, $setting, $log);
    $xunBusinessCoin = new XunBusinessCoin($db, $general, $setting, $log, $xunCrypto, $xunCurrency);
    $excel = new Excel($db, $setting, $message, $log, $general, $cash, $xunAdmin);
    $xunReseller  = new XunReseller($db, $setting, $general, $post);
    $campaign      = new Campaign($db, $setting, $general, $post, $whoisserver);
    $xunAdvestiment = new xunAdvestiment($db, $general, $setting);
    $simplex = new Simplex($db, $general, $setting, $post);
    $xanpool = new Xanpool($db, $general, $setting, $post);


    //   //get ip address
    if (!empty($_SERVER['HTTP_CLIENT_IP'])&& filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])&& filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $rid = $_SERVER["HTTP_RID"];
    $source = $_SERVER["HTTP_SOURCE"];
    $source2 = $_SERVER["HTTP_SOURCE2"];
    $access_token = $_SERVER["HTTP_ACCESS_TOKEN"];
    $user_agent = $_SERVER["HTTP_USER_AGENT"];
    $ip = $_SERVER["HTTP_USER_IP"] ?: $ip;
    $user_id = $_SERVER["HTTP_BUSINESS_ID"];

    if(strtolower($source) == 'erlang'){
        $db->where('username' , $json_data['username']);
        $xun_user = $db->getOne('xun_user');

        $language_id = $xun_user['language'];

        $db->where('id', $language_id);
        $languages = $db->getOne('languages');

    }
    else{
        $languages = $json_data['language'];
    }
   
    $systemLanguage = $languages ? trim($languages['language']) : "english";
    //$systemLanguage = trim($json_data["language"]) ? trim($json_data["language"]) : "english"; // default to english

    // // Set current language. Call $general->getCurrentLanguage() to retrieve the current language
    $general->setCurrentLanguage($systemLanguage);
    // // Include the language file for mapping usage
    include_once 'language/lang_all.php';
    // // Set the translations into general class. Call $general->getTranslations() to retrieve all the translations
    $general->setTranslations($translations);

    $timeStart  = time();
    $tblDate    = date("Ymd");
    $createTime = date("Y-m-d H:i:s");

    //replace all slashes with empty space
    $ws_json_data = $json_data;
    $ws_json_data['ip'] = !empty($ws_json_data['ip']) ? $ws_json_data['ip'] : ($ip ?: '');
    if($command == "appcryptobackuprequest"){
        unset($ws_json_data["encrypted_private_key"]);
    }
    $webserviceID = $webservice->insertWebserviceData($ws_json_data, $tblDate, $createTime, $command);

    $filtered_business_commands = array("businesssignin", "businessregister", "businessforgotpassword", "businessregisterresend-email", "businessverify", "businessmarketget", "businessmarketregister", "businessmarketcode", "utm_tracking", "cryptocurrencypricelist", "bloglist", "blogdetails", "download_link_tracking", "newslist", "newsdetails", "crowdfundingdetails", "crowdfundingsubscribe");
    
    $filtered_erlang_commands = array();
    
    $filtered_crypto_commands = array();
    
    $filtered_non_authenticated_commands = array("businessprofileget", "appbusinesstaglist", "cryptocurrencypricelist");

    $crypto_whitelist_ip_address = $config['cryptoWhitelistIPAddress'];

    //  3rd party APIs
    switch($command){
        case "businessmarketregister":

            $outputArray = $xunBusiness->business_market_register($json_data);

            break;

        case "businessmarketcode":

            $outputArray = $xunBusiness->business_market_code($json_data);

            break;

        case "businessregister":

            $outputArray = $xunBusiness->business_register($json_data);

            break;

        case "businessmarketget":

            $outputArray = $xunBusiness->business_market_get($json_data);

            break;

        case "businesssignin":

            $outputArray = $xunBusiness->business_signin($json_data, $ip);

            break;

        case "businessverify":

            $outputArray = $xunBusiness->business_verify($json_data);

            break;

        case "businessregisterresend-email":

            $outputArray = $xunBusiness->business_register_resend_email($json_data, "business");

            break;

        case "businessmobileverifycodeget":

            $outputArray = $xunBusiness->business_mobile_verifycode_get($json_data);

            break;

        case "businessforgotpassword":

            $outputArray = $xunBusiness->business_forgotpassword($json_data, "business");

            break;

        case "businessprofileget":

            $outputArray = $xunBusiness->business_profile_get($json_data);

            break;

        case "businesssearch":

            $outputArray = $xunBusiness->business_search($json_data);

            break;

        case "cryptocurrencypricelist":
        
            $outputArray = $xunBusiness->cryptocurrency_live_price_listing($json_data);
            
            break; 

        case "appbusinesstaglist":
            
            $outputArray = $xunBusiness->business_tag_list($json_data);

            break;

        // Start Blog Post APIs//
        case "newsdetails":
            
            $outputArray = $xunBusiness->get_news_post_content($json_data);
            
            break; 

        case "newslist":
            
            $outputArray = $xunBusiness->get_news_post_listing($json_data);
        
            break;

        case "bloglist":
            
            $outputArray = $xunBusiness->get_blog_post_listing($json_data);
            
            break; 

        case "articledetails":
            
            $outputArray = $xunBusiness->get_article_post_content($json_data);
            
            break;
        // End Blog Post APIs//

        case "qrdetails":

            $outputArray = $xunBusiness->get_qr_details($json_data);
            
            break;

        //Start WEB Story APIs//
        case "storydetails":
            
            $outputArray = $xunStory->get_story_details_web($json_data);

            break;

        case "webmainstorypage":
            
            $outputArray = $xunStory->web_main_story_page($json_data);

            break;

        case "storycommentlist":

            $outputArray = $xunStory->web_get_comment_list($json_data);

            break;

        case "storytransactionhistory":

            $outputArray = $xunStory->web_get_transaction_history($json_data);

            break;
        
        case "storyaddcomment":
            
            $outputArray = $xunStory->web_add_story_comment($json_data, $user_agent);

            break;

        case "webstorypaymentmethodlistget":

            $outputArray = $xunStory->get_story_payment_method_list($json_data);

            break;

        case "storyshare":

            $outputArray = $xunStory->web_share_story($json_data);

            break;

        case "storytransaction_slipupload_link":

            $outputArray = $xunStory->web_get_donor_transaction_slip_url($json_data);

            break;

        case "storydonate":

            $outputArray = $xunStory->web_donation($json_data, $ip, $user_agent);

            break;
            
        case "webstorycreate":
                
            $outputArray = $xunStory->create_story($json_data, 'web', $ip, $user_agent);

            break;

        case "webstorycreateupdates":

            $outputArray = $xunStory->create_story_updates($json_data, 'web', $user_agent);

            break;
            
        case "webstorymediaupload_link":
                
            $outputArray = $xunStory->request_story_media_upload_link($json_data, 'web');
            
            break;

        case "webstorycreatedetailsget":
                
            $outputArray =  $xunStory->web_get_create_story_details($json_data);
            
            break;

        case "webstorysettingpaymentmethodget":
        
            $outputArray = $xunStory->app_story_setting_get_payment_method($json_data);
            
            break; 

        case "webstorypaymentmethodset":

            $outputArray = $xunStory->set_payment_method($json_data, "web");

            break;
            
        case "webstorypaymentmethoddelete":

            $outputArray = $xunStory->delete_payment_method($json_data, "web");

            break;

        case "webstoryregister":

            $outputArray = $xunStory->web_story_register($json_data, $ip, $user_agent);

            break;

        case "webstoryregisterverify":

            $outputArray = $xunStory->web_story_verify_email($json_data);

            break;

        case "webstoryregisterresend-email":

            $outputArray = $xunStory->web_story_resend_email($json_data);

            break;

        case "webstoryregisterotpget":

            $outputArray = $xunStory->get_story_user_verify_code($json_data);

            break;

        case "webstoryregisterotpverify":
            
            $outputArray = $xunStory->validate_story_user_verify_code($json_data);

            break;

        case "webstoryownerprofileget":
            
            $outputArray = $xunStory->web_owner_get_profile($json_data);

            break;
            
        case "webstoryownerchangepassword":
            
            $outputArray = $xunStory->web_owner_change_password($json_data, $ip, $user_agent);

            break;
                                 
	    case "webstorylogin":
		
	        $outputArray = $xunStory->story_login($json_data, $ip, $user_agent);

            break;

        case "webstoryloginqrtokenrequest":

            $outputArray = $xunStory->request_web_login_token($json_data);

	        break;

        case "webstoryloginqrtokenverify":

            $outputArray = $xunStory->verify_web_login_token($json_data);

            break;
            
        case "webstoryownereditprofile":
        
            $outputArray = $xunStory->owner_edit_profile($json_data, $user_agent);

            break;
            
        case "webstoryownerdeleteaccount":
        
            $outputArray = $xunStory->owner_delete_account($json_data, $user_agent);

            break;
                    
        case "webstorydonationlisting":
        
            $outputArray = $xunStory->story_donation_listing($json_data, 'web');

            break;
                                        
        case "webstorytransactiondetailsget":
        
            $outputArray = $xunStory->get_story_transaction_details($json_data, 'web');

            break;

        case "webstoryownerupdatedonation":
        
            $outputArray = $xunStory->owner_update_donation($json_data, 'web', $ip, $user_agent);

            break;
        
        case "webstorygetstorylist":
        
            $outputArray = $xunStory->get_my_story_list($json_data, 'web');

            break;

        case "webstorydashboard":

            $outputArray = $xunStory->web_dashboard($json_data);

            break;
        
        case "webstoryuseractivitylist":

            $outputArray = $xunStory->web_get_user_activity_list($json_data);

            break;

        case "webstoryqrlogincallback":

            $outputArray = $xunStory->web_story_qr_login_callback($json_data);

            break;

        case "webstorygetloginstatus":
            
            $outputArray = $xunStory->web_get_user_login_status($json_data);

            break;

        case "webstoryforgotpassword":
            
            $outputArray = $xunStory->web_story_forgot_password($json_data, $ip, $user_agent);

            break;

        case "webstorymydonationlist": 
            
            $outputArray = $xunStory->web_get_my_donation_list($json_data);

            break;

        case "webstorymydonationdetails":
        
            $outputArray = $xunStory->web_get_donation_details($json_data);

            break;

        case "webstorymydetails":

            $outputArray = $xunStory->web_my_story_details($json_data);

            break;

        case "webstorycountrylist":
        
            $outputArray = $xunStory->web_get_country_list($json_data);

            break;

        case "parcelhubCheckLabel":

            $outputArray = $outgoingBusiness->parcelhubCheckLabel($json_data);

            break;

    	//End WEB Story APIs//                            
        case "cryptosetcallbackurl":
            
            $outputArray = $xunCrypto->set_callback_url($json_data);
            
            break; 

        case "cryptogetcallbackurl":
            
            $outputArray = $xunCrypto->get_callback_url($json_data);
            
            break; 

        case "cryptogeneratenewaddress":
        
            $outputArray = $xunCrypto->generate_new_address($json_data);
            
            break;

        //  Start crowdfunding API
        case "crowdfundingsubscribe":

            $outputArray = $xunAdmin->subcribe_crowdfunding($json_data);

            break;

        case "crowdfundingdetails":
            
            $outputArray = $xunAdmin->getCrowdfundingIDetails($json_data);
            
            break;

        case "crowdfundingprofitsharing":

            $outputArray = $xunCrowdfunding->get_profit_sharing_details($json_data);

            break;
        //  End crowdfunding API

    // notification
    case "errorNotification":
        $outputArray = $xunAdmin->sendErrorNotification($json_data);
	    break;


	//Third party web login
	case "webloginverify":
	    $outputArray = $xunBusiness->verify_web_login($json_data);
	    break;

	case "appbusinessloginattempt":
            $outputArray = $xunBusiness->app_attempt_login($json_data);
	    break;

        //UTM APIs//

        case "utm_record":

            $outputArray = $xunAdmin->utm_record($params);

            break;

        case "utm_list";

            $outputArray = $xunAdmin->utm_list($params);

            break;

        case "utm_tracking";

            $outputArray = $xunAdmin->utm_tracking($params);

            break;

        case "utm_tracking_list";

            $outputArray = $xunAdmin->utm_tracking_list($params);

            break;
        
        case "download_link_tracking";

            $outputArray = $xunAdmin->save_download_link_tracking($params);

            break;

        //ADMIN APIs//

        case "adminbusinesslivechatsettingget":

            $outputArray = $xunAdmin->get_livechat_setting_admin($json_data);

            break;

        case "adminbusinesslivechatsettingadd":

            $outputArray = $xunAdmin->add_edit_setting_admin($json_data);

            break;

        case "adminbusinessreferrallist":

            $outputArray = $xunAdmin->get_business_listing($json_data);

            break;

        // Start GiftCode APIs//
        case "gift_codepurchase":
            // xun/gift_code/purchase
            $outputArray = $xunGiftCode->purchase_gift_code($json_data);
            
            break; 

        case "gift_codewalletdetails":
            // xun/gift_code/wallet/details
            $outputArray = $xunGiftCode->get_wallet_details($json_data);
            
            break; 

        // giftnpay/callback
        case "giftnpaycallback":
            $outputArray = $giftnpay->giftnpayCallback($json_data);
                
            break; 
        // End Gift code APIs//

        case "businessbroadcast":

            $outputArray = $xunBusiness->business_message_sending($url_string, $json_data);

            break;

        case "livechattranscript":

            $outputArray = $xunLivechat->save_livechat_transcript($url_string, $json_data);

            break;

        case "walletcallbackfreecoin":
        
            $outputArray = $xunCompanyWalletAPI->freecoinWalletServerCallback($json_data);
            
            break;
        
        case "walletcallbackprepaid":
        
            $outputArray = $xunCompanyWalletAPI->prepaidWalletServerCallback($json_data);
            
            break;

        case "walletcallbackprepaidcreate":

            $outputArray = $xunCompanyWalletAPI->createPrepaidWalletCallback($json_data);

            break;

        case "wallettransactionupdate":

            $outputArray = $general->keep_queue_callback($command, $json_data);
            //$outputArray = $xunCompanyWalletAPI->updateWalletTransaction($json_data);

            break;

        case "walletprepaidcreate":

            $outputArray = $xunCompanyWalletAPI->createPrepaidWallet($json_data);

            break;

        //  Start of TTwo Integration //
        case "cryptowalletbalanceget":
            
            $outputArray = $xunCrypto->crypto_get_wallet_balance($json_data);

            break;

        case "cryptoexternaltransfer":

            $outputArray = $xunCrypto->crypto_external_transfer($json_data);

            break;

        case "cryptominerfeeget":

            $outputArray = $xunPaymentGateway->crypto_miner_fee_get($json_data);

            break;
        
        // End of TTwo Integeration //
                            
        case "businesswalletbalanceget":

            $outputArray = $xunCrypto->business_get_wallet_info($json_data);

            break;

        // case "cryptowalletservertokenverify":
    
        //     $outputArray = $xunCrypto->wallet_server_verify_token($json_data);

        //     break;

        // case "cryptoexternalfundoutcallback":

        //     $outputArray = $general->keep_queue_callback($command, $json_data);
        //     // $outputArray = $xunCrypto->crypto_external_fund_out_callback($json_data);

        //     break;

        // case "cryptocallback":

        //     $outputArray = $general->keep_queue_callback($command, $json_data);
        //     //$outputArray = $xunCrypto->save_crypto_callback($url_string, $json_data);
        //     //$outputArray = $xunCrypto->save_crypto_callback($json_data);

        //     break;  
        
        // case "cryptotransactioncallback":
            
        //     $outputArray = $general->keep_queue_callback($command, $json_data); 
        //     //$outputArray = $xunCrypto->transaction_callback($json_data);
            
        //     break; 

        // case "cryptotransactiontokenverify":

        //     $outputArray = $xunCrypto->verify_user_crypto_transaction_token($json_data);

        //     break;

        case "cryptoexternaltransferbybatch":

            $outputArray = $xunCrypto->crypto_external_transfer_by_batch($json_data);

            break;

        // Start Web NuxPay APIs//
        case "merchantregister":

            $outputArray = $xunPaymentGateway->merchant_register($json_data);

            break;
        case "payment_gatewaymerchanttransactionrequest":

            $outputArray = $xunPaymentGateway->merchant_request_transaction($json_data, $source, "", false, $ip);

            break;

        case "thenuxCheckPGAddressBusiness":
            $outputArray = $xunPaymentGateway->thenuxCheckPGAddressBusiness($json_data);
            break;

        case "payment_gatewaymerchanttransactiondetails":

            $outputArray = $xunPaymentGateway->payment_gateway_get_transaction_details($json_data);

            break;
            
        case "payment_gatewaymerchantbuysellpaymentrequest":

            $outputArray = $xunPaymentGateway->create_crypto_payment_request($json_data, "merchant");

            break;

        case "payment_gatewaymerchanttransactionstatus":

            $outputArray = $xunPaymentGateway->payment_gateway_get_transaction_status($json_data);

            break;

        case "payment_gatewaysendfundtransactionstatusget":

            $outputArray = $xunPaymentGateway->get_send_fund_transaction_status($json_data);

            break;

        case "payment_gatewaygetfundoutcoinlisting":
            $outputArray = $xunPaymentGateway->get_fund_out_coin_listing($json_data);
            break;
        
        case "payment_gatewaysetfundoutexternaladdress":
            $outputArray = $xunPaymentGateway->set_fund_out_external_address($json_data);
            break;
        
        case "payment_gatewaysetfundoutexternaladdressV2":
            $outputArray = $xunPaymentGateway->set_fund_out_external_address_v2($json_data);
            break;

        case "payment_gatewaygenerateexternaladdress":
            $outputArray = $xunPaymentGateway->generate_external_address($json_data);
            break;

        case "payment_gatewaygetfundoutlisting":
            $outputArray = $xunPaymentGateway->get_fund_out_listing($json_data);
            break;

	    case "webpayregisterotpget":

            $outputArray = $xunPay->get_pay_user_verify_code($json_data, $ip, $user_agent);

	        break;

        case "webpayresetpasswordotpget":

            $outputArray = $xunPay->reset_password_verifiycode_get($json_data, $ip, $user_agent);

            break;

        case "webpayresetpasswordotpvalidate":

            $outputArray = $xunPay->reset_password_verifiycode_validate($json_data, $ip, $user_agent);

            break;

        case "webpayresetpassword":

            $outputArray = $xunPay->reset_password_merchant($json_data, $ip, $user_agent);

            break;

        case "webpaybinduserotpget":

            $outputArray = $xunPay->get_bind_user_verify_code($json_data, $ip, $user_agent);

            break;

        case "webpaybinduseraccount":

            $outputArray = $xunPay->get_bind_user_account($json_data, $ip, $user_agent);

            break;

        case "webpayregisterotpverify":

            $outputArray = $xunPay->validate_pay_user_verify_code($json_data, $ip, $user_agent);

	        break;

        case "webpaylogin":

            $outputArray = $xunPay->pay_login($json_data, $ip, $user_agent);

            break;

	    case "webpayloginqrtokenrequest":

            $outputArray = $xunStory->request_web_login_token($json_data);

            break;

        case "webpayregister":

            $outputArray = $xunPay->pay_register($json_data, $ip, $user_agent, $rid);

            break;
        
        case "webpayverifycode":

            $outputArray = $xunPay->pay_verify_code($json_data);

            break;
        
        // case "webpayforgotpassword":

        //     $outputArray = $xunBusiness->business_forgotpassword($json_data, "nuxpay");

        //     break;

        case "webpayregisterresend-email":

            $outputArray = $xunBusiness->business_register_resend_email($json_data, "nuxpay");

            break;

        case "webpayhomepage":

            $outputArray = $xunPaymentGateway->nux_pay_homepage($json_data);

            break;

        case "webpaygetcryptopricing":

            $outputArray = $xunPaymentGateway->get_crypto_pricing($json_data);

            break;

        case "webpayresendotp":
            
            $outputArray = $xunPay->get_pay_user_verify_code($json_data, $ip, $user_agent);
            
            break;
        
        case "webpayforgotpassword":
            $outputArray = $xunPaymentGateway->nuxpay_forgot_password($json_data, $source, $xunEmail);

            break;
        case "currencyfiatlist":

            $outputArray = $xunPaymentGateway->get_supported_fiat_currency($json_data);

            break;
        
        case "cryptoconversionrateget":
            
            $outputArray = $xunPaymentGateway->get_crypto_rate($json_data);

            break;
        
        case "businesspgdestinationaddresslistget":

            $outputArray = $xunPaymentGateway->get_pg_destination_address_list($json_data);

            break;

        case "webpayregistered_userupdate":

            $outputArray = $xunBusinessPartner->partner_update_user($json_data, "nuxpay");

            break;

        case "payment_gatewayinvoicepaymentrequestverification":

            $outputArray = $xunPaymentGateway->request_nuxpay_invoice_payment($json_data, $ip, $rid, $source, $user_agent, 'verification', $xunEmail);

            break;

        case "payment_gatewayinvoicepaymentrequest":

            $outputArray = $xunPaymentGateway->request_nuxpay_invoice_payment($json_data, $ip, $rid, $source, $user_agent, 'confirmation', $xunEmail);

            break;

        case "payment_gatewayinvoicedetailsget":

            $outputArray = $xunPaymentGateway->get_nuxpay_invoice_details($json_data);

            break;

        case "payment_gatewayinvoicelistget":

            $outputArray = $xunPaymentGateway->get_nuxpay_invoice_listing($json_data);
            
            break;
        
        case "payment_gatewaypayerdetailset":

            $outputArray = $xunPaymentGateway->set_nuxpay_invoice_listing_payer($json_data);

            break;

        case "payment_gatewaywithdrawalbalanceget":

            $outputArray = $xunPaymentGateway->get_nuxpay_withdrawal_balance($json_data);
    
            break;
    
        case "payment_gatewaywithdrawallistget":
    
            $outputArray = $xunPaymentGateway->get_nuxpay_withdrawal_listing($json_data);
    
            break;

        case "payment_gatewayapiwithdrawallistget":
        
            $outputArray = $xunPaymentGateway->get_nuxpay_api_withdrawal_listing($json_data);    

            break;
        case "payment_gatewayrequestfundwithdrawalcreate":
    
            $outputArray = $xunPaymentGateway->create_nuxpay_invoice_withdrawal($json_data);
    
            break;

        case "payment_gatewaywalletbalanceget":

            $outputArray = $xunPaymentGateway->get_wallet_balance($json_data, $user_id);

            break;

        case "payment_gatewaywithdrawaldetailsget":

            $outputArray = $xunPaymentGateway->get_withdrawal_details($json_data);

            break;

        case "webpayresellerregister":

            $outputArray = $xunReseller->reseller_register($json_data, $source);

            break;

        case "webpayresellerdetailsget":

            $outputArray = $xunPaymentGateway->get_reseller_details($json_data, $source);

            break;

        case "webpaycampaignlongurlget":

            $outputArray = $campaign->get_long_url($json_data, $ip, $user_agent);

            break;

        case "webpayuserfirsttimeinfoupdate":

            $outputArray = $xunUser->update_user_first_time_info($json_data);

            break;

        case "webpayuserfirsttimebusinessupdate":

            $outputArray = $xunUser->update_user_first_time_business($json_data);

            break;

        case "webpayuserfirsttimebusinessupdateskip":

            $outputArray = $xunUser->update_user_first_time_business_skip($json_data);

            break;

        case "webpaylandingpagedetailsget":

            $outputArray = $campaign->get_landing_page_details($json_data);

            break;

        case "requestfundcheckuserexist":

            $outputArray = $xunUser->request_fund_check_user_exist($json_data);

            break;
        
        case "upgradeuseraccounttype":

            $outputArray = $xunUser->upgrade_user_account_type($json_data);

            break;


        case "webpaywalletstatusset":

            $outputArray = $xunPaymentGateway->set_wallet_status($json_data);

            break;

        case "webpaywalletstatusget":

            $outputArray = $xunPaymentGateway->get_wallet_status($json_data);

            break;

        case "setswitchcurrency":

            $outputArray = $xunPaymentGateway->set_switch_currency($json_data);

            break;

        case "webpaynuxpaywalletstatusset":

            $outputArray = $xunPaymentGateway->set_nuxpay_wallet_status($json_data);

            break;

        case "webpaynuxpaywalletstatusget":

            $outputArray = $xunPaymentGateway->get_nuxpay_wallet_status($json_data);

            break;
            
        case "webpayconversionamountget":

            $outputArray = $xunPaymentGateway->get_conversion_amount($json_data);

            break;

        case "webpayreceiptdetailsget":

            $outputArray = $xunPaymentGateway->get_receipt_details($json_data);

            break;
        
        
        case "webpayproviderget":

            $outputArray = $xunPaymentGateway->get_provider_status($json_data);
    
            break;

        case "payment_gatewaybuysellconversionrateget":

            $outputArray = $xunPaymentGateway->get_buy_sell_conversion_rate($json_data);

            break;
            
        case "payment_gatewaybuysellsupportedcoinsget":
            
            $outputArray = $xunPaymentGateway->merchant_get_buy_sell_supported_currencies($json_data);

            break;

        case "payment_gatewaybuysellpaymentrequest":

            $outputArray = $xunPaymentGateway->create_crypto_payment_request($json_data, "");

            break;

        case "webpaybuyselltransactiontokendetailsget":

            $outputArray = $xunPaymentGateway->get_buysell_transaction_token_details($json_data, $ip);

            break;
        
        case "getNuxpayUserWithInternalAddress":

            $outputArray = $xunPaymentGateway->get_nuxpay_user_with_internal_address($json_data);
            
            break;
                
    
        //End Web NuxPay APIs//
        //Start Simplex API//
        case "webpaycryptoquoteget":

            $outputArray = $simplex->get_quote($json_data, $user_id, $ip);

            break;

        case "webpaysimplexpayment":

            $outputArray = $simplex->create_payment_transaction($json_data, $user_id, $ip);

            break;
        // End Simplex APIs//
        // Start Xanpool APIs//
        case "payxanpoolcallback":
            
            $outputArray = $xanpool->xanpool_crypto_callback($json_data);

            break;

        case "webpayxanpoolquoteget":

            $outputArray = $xanpool->estimate_transaction_cost($json_data, $user_id);

            break;

        case "webpayxanpooltransactionupdate":

            $outputArray = $xanpool->update_transaction_data($json_data);

            break;
            
        case "webpayxanpoolpayment":

            $outputArray = $xanpool->create_payment_request($json_data, $user_id);

            break;

        case "webpayxanpoolsellcrypto":

            $outputArray = $xanpool->transfer_sell_crypto($json_data);

            break;

        case "cryptogetwalletsupportedcurrencies":

            $outputArray = $xanpool->get_buy_crypto_supported_currency_wallet($json_data);

            break;
            
        // End Xanpool APIs //
        //  Phone Approve APIs //
        case "businesswallettransaction":
            $outputArray = $xunPhoneApprove->request_business_wallet_transaction($json_data);

            break;

        case "businesswalletrewardsbalanceget":
            $outputArray = $xunBusinessPartner->get_business_partner_rewards_user_info($json_data);

            break;
        // End Phone Approve APIs //
        //  Business Partner APIs //
        case "businessregistered_userupdate":

            $outputArray = $xunBusinessPartner->partner_update_user($json_data);

            break;
        // End Phone Approve APIs //

        case "webcountrylistget":
            
            $outputArray = $xunReward->web_get_country_list($json_data);

            break;

        case "businessbroadcastemployee":

            $outputArray = $xunBusiness->business_send_employee_message($json_data);

            break;

        case "businessrequestmoney":

            $outputArray = $xunBusiness->business_request_money($json_data);

            break;

        case "businesspaymentgatewayfundoutverify":

            $outputArray = $xunCrypto->busines_payment_gateway_verify_fundout($json_data);

            break;

        case "businesspaymentgatewayfundout":

            $outputArray = $xunCrypto->busines_payment_gateway_fundout($json_data);

            break;

        case "businessnuxpayfundout":

            $outputArray = $xunPaymentGateway->nuxpay_business_payment_gateway_fundout($json_data);

            break;

        case "marketplaceescrownotification":
            
            $outputArray = $xunMarketplace->escrow_notification($json_data);

            break;

        case "marketplacexmppmessage":
    
            $outputArray = $xunMarketplace->xmpp_marketplace_chat_room($json_data);

            break; 

        case "marketplaceescrowvalidate":
        
            $outputArray = $xunMarketplace->escrow_validation($json_data);

            break;

        case "businesscoinget":
        
            $outputArray = $xunBusinessCoin->get_business_coin($json_data);

            break;

        case "businesscreditnew":
        
            $outputArray = $xunBusinessCoin->create_business_credit_coin($json_data);

            break;

        case "businesscredittransfer":
        
            $outputArray = $xunBusinessCoin->business_transfer_credit($json_data);

            break;

        case "businesswallettransactionhistory":

            $outputArray = $xunCrypto->business_get_wallet_transaction_history($json_data);

            break;

        case "cryptoconversionget":

            $outputArray = $xunPaymentGateway->get_crypto_conversion_rate($json_data);

            break;  

        default : 

        if(strtolower($source) == 'business'){
            
            if(!in_array($command, $filtered_business_commands)){
                
                $business_id = $_SERVER["HTTP_BUSINESS_ID"]; 
                
                if(!$xunBusiness->validate_access_token($business_id, $access_token)){
                
                    //error access token
                    $notification_message = "Source: ".$source;
                    $notification_message .= "\nCommand: ".$command;
                    $notification_message .= "\nBusiness Id: ".$business_id;
                    $notification_message .= "\nAccess Token: ".$access_token;
                    $notification_message .= "\n\nData In: ".json_encode($json_data);
                    $notification_tag = "Failed validate access token";
                    $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "nuxpay");


                    $outputArray = array('code' => -100, 'message' => "LOGGED OUT", 'message_d' => "Duplicate login is not allowed.", "developer_msg" => "Duplicate login is not allowed.");

                    $dataOut = $outputArray;
                    $status  = $dataOut['status'];

                    $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);

                    echo json_encode($outputArray);

                    exit();

                }
                
            }

            switch($command){
                case "businessmobileverifycodeverify":

                    $outputArray = $xunBusiness->business_mobile_verifycode_verify($json_data);

                    break;

                case "businessapi_keydeleteall":

                    $outputArray = $xunBusiness->delete_all_record($json_data);

                    break;

                case "businessapi_keydelete":

                    $outputArray = $xunBusiness->delete_multiple_record($json_data);

                    break;

                case "businessapi_keygenerate": 

                    $outputArray = $xunBusiness->generate_api_key($json_data);

                    break;

                case "businessapi_keylist":

                    $outputArray = $xunBusiness->api_key_listing($json_data);

                    break;

                case "businessapi_keyupdate":

                    $outputArray = $xunBusiness->update_api_key($json_data);

                    break;

                case "businessfollowcount":

                    $outputArray = $xunBusiness->business_follow_count($json_data);

                    break;

                case "businesscontactgroupedit":

                    $outputArray = $xunBusiness->business_contact_group_edit($json_data);

                    break;

                case "sendSmsMessage":

                    $outputArray = $xunBusiness->sendSmsMessage($params);

                    break;

                case "businessintegrationadd":

                    $outputArray = $xunBusiness->sms_add($json_data);

                    break;

                case "businessintegrationedit":

                    $outputArray = $xunBusiness->sms_edit($json_data);

                    break;

                case "businessintegrationlisting":

                    $outputArray = $xunBusiness->sms_list($json_data);

                    break;

                case "businessintegrationget":

                    $outputArray = $xunBusiness->sms_get($json_data);

                    break;

                case "businessintegrationdelete":

                    $outputArray = $xunBusiness->sms_delete($json_data);

                    break;

                case "businessintegrationdeleteall":

                    $outputArray = $xunBusiness->sms_delete_all($json_data);

                    break;

                case "businessbroadcasthistorylist":

                    $outputArray = $xunBusiness->message_history_listing($json_data);

                    break;

                case "businessbroadcasthistorydetail":

                    $outputArray = $xunBusiness->message_history_detail($json_data);

                    break;

                case "countryphone_codelist":

                    $outputArray = $xunBusiness->country_phone_code_list($json_data);

                    break;

                case "businessbroadcastemployee":

                    $outputArray = $xunBusiness->business_send_employee_message($json_data);

                    break;

                case "businessrequestmoney":

                    $outputArray = $xunBusiness->business_request_money($json_data);

                    break;

                case "businesspaymentgatewayfundoutverify":

                    $outputArray = $xunCrypto->busines_payment_gateway_verify_fundout($json_data);

                    break;

                case "businesspaymentgatewayfundout":

                    $outputArray = $xunCrypto->busines_payment_gateway_fundout($json_data);

                    break;
                    
                case "businesspaymentgatewayaddressvalidate":

                    $outputArray = $xunCrypto->business_crypto_validate_address($json_data);

                    break;

                case "businesswallettransactionhistory":

                    $outputArray = $xunCrypto->business_get_wallet_transaction_history($json_data);

                    break;

                case "businesschangepassword":

                    $outputArray = $xunBusiness->business_changepassword($json_data);

                    break;

                case "businesslist":

                    $outputArray = $xunBusiness->business_list($json_data);

                    break;
                
                case "businessedit":

                    $outputArray = $xunBusiness->business_edit($json_data);

                    break;
                
                case "businessdelete":

                    $outputArray = $xunBusiness->business_delete($json_data);

                    break;

                case "businessprofilepictureupload":

                    $outputArray = $xunBusiness->business_profile_picture_upload($json_data);

                    break;

                case "businessemployeeadd":

                    $outputArray = $xunBusiness->business_employee_add($url_string, $json_data);

                    break;

                case "businessemployeeget":

                    $outputArray = $xunBusiness->business_employee_get($json_data);

                    break;

                case "businessemployeelist":

                    $outputArray = $xunBusiness->business_employee_list($json_data);

                    break;

                case "businessemployeeconfirmedlist":

                    $outputArray = $xunBusiness->business_employee_confirmed_list($json_data);

                    break;

                case "businessemployeeedit":

                    $outputArray = $xunBusiness->business_employee_edit($json_data);

                    break;

                case "businessemployeedelete":

                    $outputArray = $xunBusiness->business_employee_delete($json_data);

                    break;

                case "businessemployeedeleteall":

                    $outputArray = $xunBusiness->business_employee_delete_all($json_data);

                    break;

                case "businesstagadd":

                    $outputArray = $xunBusiness->business_tag_add($json_data);

                    break;
                
                case "businesstagedit":

                    $outputArray = $xunBusiness->business_tag_edit($json_data);

                    break;

                case "businesstaglist":

                    $outputArray = $xunBusiness->business_tag_list($json_data);

                    break;

                case "businesstagget":

                    $outputArray = $xunBusiness->business_tag_get($json_data);

                    break;

                case "businesstagdelete":

                    $outputArray = $xunBusiness->business_tag_delete($json_data);

                    break;

                case "businesstagdeleteall":

                    $outputArray = $xunBusiness->business_tag_delete_all($json_data);

                    break;

                case "businesstagchatadd":

                    $outputArray = $xunBusiness->business_employee_tag_add($json_data);

                    break;

                case "businesstagchatedit":

                    $outputArray = $xunBusiness->business_employee_tag_edit($json_data);

                    break;

                case "businesstagchatlist":

                    $outputArray = $xunBusiness->business_employee_tag_list($json_data);

                    break;
                
                case "businesstagchatget":

                    $outputArray = $xunBusiness->business_employee_tag_get_details($json_data);

                    break;

                case "businesstagchatdelete":

                    $outputArray = $xunBusiness->business_employee_tag_delete($json_data);

                    break;
                
                case "businesstagchatdeleteall":

                    $outputArray = $xunBusiness->business_employee_tag_delete_all($json_data);

                    break;
                
                case "businesspackagesubscription":

                    $outputArray = $xunBusiness->business_package_subscription($json_data);

                    break;
                
                case "businesscontactgroupimport":

                    $outputArray = $xunBusiness->business_contact_group_import($_POST, $_FILES);

                    break;
                
                case "businesscontactgroupeditimport":

                    $outputArray = $xunBusiness->business_contact_group_edit_import($_POST, $_FILES);

                    break;

                case "businesscontactgrouplist":

                    $outputArray = $xunBusiness->business_contact_group_list($json_data);

                    break;
                
                case "businesscontactgroupget":

                    $outputArray = $xunBusiness->get_contact_group_details($json_data);

                    break;

                case "businesscontactgroupdelete":

                    $outputArray = $xunBusiness->delete_contact_group($json_data);

                    break;

                case "businesscontactgroupdeleteall":

                    $outputArray = $xunBusiness->delete_all_contact_group($json_data);

                    break;

                case "businesscontactgroupcontactadd":

                    $outputArray = $xunBusiness->xun_group_contact_add($json_data);

                    break;
                    
                case "businesscontactgroupcontactedit":

                    $outputArray = $xunBusiness->xun_group_contact_edit($json_data);

                    break;

                case "businesscontactgroupcontactdelete":

                    $outputArray = $xunBusiness->xun_group_contact_delete($json_data);

                    break;

                case "businesscontactgroupcontactdeleteall":

                    $outputArray = $xunBusiness->xun_group_contact_delete_all($json_data);

                    break;

                case "businesslivechatscript":

                    $outputArray = $xunBusiness->livechat_get_script($json_data);

                    break;

                case "businesslivechatsettingadd":

                    $outputArray = $xunBusiness->add_edit_setting($json_data);

                    break;
                
                case "businesslivechatsettingget":

                    $outputArray = $xunBusiness->get_livechat_setting($json_data);

                    break;
                        
                //Crypto APIs//

                case "cryptosetdestinationaddress":
                    
                    $outputArray = $xunCrypto->set_destination_address($json_data);
                    
                    break;

                case "cryptosetcallbackurl":
                
                    $outputArray = $xunCrypto->set_callback_url($json_data);
                    
                    break; 

                case "cryptogetcallbackurl":
                    
                    $outputArray = $xunCrypto->get_callback_url($json_data);
                    
                    break; 

                case "cryptogeneratenewaddress":
                
                    $outputArray = $xunCrypto->generate_new_address($json_data, strtolower($source));
                    
                    break;

                case "cryptogetdestinationaddress":
                    
                    $outputArray = $xunCrypto->get_destination_address($json_data);
                    
                    break;
                
                case "cryptogetaddresslist":
                    
                    $outputArray = $xunCrypto->get_address_list($json_data);
                    
                    break;

                case "cryptosetwalletstatus":
                    
                    $outputArray = $xunCrypto->set_wallet_status($json_data);
                    
                    break;

                case "cryptogenerateapikey":
                    
                    $outputArray = $xunCrypto->generate_apikey($json_data);
                    
                    break;
                
                case "cryptodeleteapikey":
                    
                    $outputArray = $xunCrypto->delete_apikey($json_data);
                    
                    break;
                
                case "cryptogetapikeylist":
                    
                    $outputArray = $xunCrypto->get_apikey_list($json_data);
                    
                    break;
                
                case "cryptogetwalletsdestinationaddress":
                    
                    $outputArray = $xunCrypto->get_wallets_destination_address($json_data);
                    
                    break;

                case "cryptogetwallettype":
                    
                    $outputArray = $xunCrypto->get_wallet_type($json_data);
                    
                    break;   

                // case "cryptogettransactionlist":
                    
                //     $outputArray = $xunCrypto->get_transaction_list($json_data);
                    
                //     break;
                    
                case "cryptogetwalletdata":
                    
                    $outputArray = $xunCrypto->get_wallet_data($json_data);
                    
                    break; 
                
                //end crypto api
                
                //  Start rewards API 
                case "businessrewardaddressget":
                    
                    $outputArray = $xunReward->web_get_business_rewards_address($json_data);
                    
                    break; 

                // case "businesstaget":

                //     $outputArray = $xunBusiness->business_tag_detail($json_data);

                //     break;

                //Start Reward api//

                case "businesssendreward":

                    $outputArray = $xunReward->send_reward_v1($json_data);

                    break;

                case "businessimportrewardpoint" :
                    
                    $outputArray = $xunReward->import_reward_point($json_data);

                    break;

                case "businessredeemlist":

                    $outputArray = $xunReward->get_redeem_listing($json_data);

                    break;

                case "businessdashboardstatistics":

                    $outputArray = $xunReward->dashboard_statistic($json_data);

                    break;

                case "businessrewardtransactionlist": 

                    $outputArray = $xunReward->get_reward_transaction_listing($json_data);

                    break;

                case "businesscointransactionlist":
                    
                    $outputArray = $xunReward->get_coin_transaction_listing($json_data);

                    break;

                case "businessrewardfollowcount":
                    
                    $outputArray = $xunReward->reward_follow_count($json_data);

                    break;

                // case "businessrewardsetminmaxqrpayment":
                case "businessrewardsetrewardsetting":
                    
                    $outputArray = $xunReward->set_business_reward_setting($json_data);

                    break;

                case "businessrewardsettingget":
                    
                    $outputArray = $xunReward->get_business_reward_setting($json_data);

                    break;

                case "businessrewardcustomeradd":

                    $outputArray = $xunBusinessPartner->partner_update_user($json_data, "business");
        
                    break;

                case "webbusinessmyfollowers":

                    $outputArray = $xunReward->business_my_followers($json_data);
        
                    break;

                case "businessrewardcustomerlist":

                    $outputArray = $xunReward->get_customer_listing($json_data);
        
                    break;

                case "businesssendcashtoken" : 
                    
                    $outputArray = $xunReward->send_cash_token($json_data);

                    break;

                case "businessimportcashtoken" :

                    $outputArray = $xunReward->import_cash_token($json_data);

                    break;

                case "businesscashtokentransactionlisting" : 

                    $outputArray = $xunReward->cash_token_transaction_listing($json_data);
                    
                    break;

                case "businesscashrewardsettingset":

                    $outputArray = $xunReward->set_cash_reward_setting($json_data);

                    break;

                case "businesscashrewardsettingget":

                    $outputArray = $xunReward->get_cash_reward_setting($json_data);

                    break;
                    
                case "businessrewardcoinimageupload":

                    $outputArray = $xunReward->web_coin_image_upload($json_data);

                    break;

                case "businessrewardwalletbackgroundupload":

                    $outputArray = $xunReward->web_wallet_background_upload($json_data);

                    break;

                case "businessrewardcardfontcolorupdate":

                    $outputArray = $xunReward->web_card_font_color_update($json_data);

                    break;

                case "businessrewardcarddesignupdate":

                    $outputArray = $xunReward->web_card_design_update($json_data);

                    break;

                case "businessrewardcarddesignget":

                    $outputArray = $xunReward->web_get_card_design($json_data);

                    break;

                case "businessrewarddetails":

                    $outputArray = $xunReward->business_reward_details($json_data);
                    
                    break;
                
                case "businessrewarddashboardlisting":

                    $outputArray = $xunReward->business_reward_dashboard_listing($json_data);

                    break;

                case "businessdashboardstatisticsv1":
                    
                    $outputArray = $xunReward->dashboard_statistic_v1($json_data);

                    break;

                case "businessrewardpurchasehistorylist": 

                    $outputArray = $xunReward->customer_purchase_history_listing($json_data);

                    break;
            // End Reward api//

            //Start Cashpool api//
                case "businessbankslipurlget":

                    $outputArray = $xunCashpool->get_bankslip_url($json_data);

                    break;

                case "businesscashpooltopup":

                    $outputArray = $xunCashpool->cashpool_topup($json_data);

                    break;

                case "businesscashpooltopuplisting":
                    
                    $outputArray = $xunCashpool->cashpool_topup_list($json_data);

                    break;

                case "businesscashpooltransactionlisting":

                    $outputArray = $xunCashpool->cashpool_transaction_list($json_data);

                    break;

                case "businessthenuxbankdetailsget":

                    $outputArray = $xunCashpool->get_thenux_bank_details($json_data);

                    break;

                case "businessbankslipurlupdate":

                    $outputArray = $xunCashpool->update_bankslip_url($json_data);

                    break;

            // End Cashpool api//

            // Start Sales api//
                case "businesscustomersalesadd":

                    $outputArray = $xunSales->add_customer_sales($json_data);

                    break;

                case "businessimportcustomersales":

                    $outputArray = $xunSales->import_customer_sales($json_data);

                    break;

                case "businessimportcustomersaleslisting":

                    $outputArray = $xunSales->import_customer_sales_listing($json_data);

                    break;

                case "businessimportcustomersalesdetailslisting":

                    $outputArray = $xunSales->import_customer_sales_details_listing($json_data);

                    break;

                case "businesssalescustomerlisting":

                    $outputArray = $xunSales->get_customer_listing($json_data);

                    break;

                case "businesssaleslistingget" :

                    $outputArray = $xunSales->get_sales_listing($json_data);

                    break;

            // End Sales api//

                default:

                    $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Categorynotfound . ", 'data' => '');
                    break;
            }
            
        }else if(strtolower($source) == 'erlang'){
            
            if(!in_array($command, $filtered_erlang_commands)){
                
                if(!$xunErlang->validate_access_token($access_token)){
                
                    //error access token
                    $notification_message = "Source: ".$source;
                    $notification_message .= "\nCommand: ".$command;
                    $notification_message .= "\nAccess Token: ".$access_token;
                    $notification_message .= "\n\nData In: ".json_encode($json_data);
                    $notification_tag = "Failed validate access token";
                    $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "nuxpay");


                    $outputArray = array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Access Token");

                    $dataOut = $outputArray;
                    $status  = $dataOut['status'];

                    $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);

                    echo json_encode($outputArray);

                    exit();

                }
                
            }

            switch($command){
                //app
                case "registerverifycodeget":

                    $outputArray = $xunUser->register_verifycode_get($json_data);

                    break;

                case "registerverifycodeverify":

                    $outputArray = $xunUser->register_verifycode_verify($json_data);

                    break;

                case "usercontactlistupdate":
                
                    $outputArray = $xunErlang->user_contactlist_update($json_data);
                    
                    break;

                case "businessfollowlistuser":

                    $outputArray = $xunBusiness->business_follow_list_user($json_data);

                    break;

                case "businessblock":

                    $outputArray = $xunBusiness->business_block($json_data);

                    break;

                case "businessunblock":

                    $outputArray = $xunBusiness->business_unblock($json_data);

                    break;

                case "businessblocklist":

                    $outputArray = $xunBusiness->business_block_list($json_data);

                    break;

                case "deviceupdate":

                    $outputArray = $xunBusiness->update_device_information($json_data);

                    break;

                case "usersettingnotificationchatroommessageringtoneupdate":
                
                    $outputArray = $xunErlang->update_chat_room_ringtone($json_data);
                    
                    break;

                case "usersettingnotificationchatroommessagemuteupdate":
                
                    $outputArray = $xunErlang->update_chat_room_mute($json_data);
                    
                    break;

                case "usersettingnotificationchatroomgetall":

                    $outputArray = $xunBusiness->user_setting_notification_chatroom_get_all($json_data);
        
                    break;
        
                case "deleteuser":

                    $outputArray = $xunErlang->delete_user($json_data);

                    break;

                case "usersettingnotificationreset":
                
                    $outputArray = $xunErlang->reset_user_notification_settings($json_data);
                    
                    break;
                
                case "usersettingaccountchange_number":

                    $outputArray = $xunUser->change_number($json_data);

                    break;

                case "usersettingaccountprivacyprofile_photoupdate":
                
                    $outputArray = $xunErlang->update_profile_photo_privacy_setting($json_data);
                    
                    break;

                case "usersettingaccountprivacy":
                
                    $outputArray = $xunErlang->get_privacy_setting($json_data);
                    
                    break;

                case "group_chatcreateprivatev1":
                
                    $outputArray = $xunErlang->group_chat_create($json_data);
                    
                    break;    
                
                case "group_chatparticipantaddv1":
                    
                    $outputArray = $xunErlang->group_chat_add_participant($json_data);
                    
                    break;

                case "group_chatparticipantremove":
                
                    $outputArray = $xunErlang->group_chat_remove_participant($json_data);
                    
                    break;
                
                case "group_chatparticipantleave":
                
                    $outputArray = $xunErlang->group_chat_leave_participant($json_data);
                    
                    break;

                case "group_chatadminadd":
                
                    $outputArray = $xunErlang->group_chat_add_admin($json_data);
                    
                    break;

                case "group_chatuserlist":

                    $outputArray = $xunErlang->group_chat_user_list($json_data);

                    break;
                
                case "group_chatusercommon_groups":
                
                    $outputArray = $xunErlang->group_chat_user_common_groups($json_data);
                    
                    break;

                case "group_chatdetails":
                
                    $outputArray = $xunErlang->group_chat_details($json_data);
                    
                    break;
                
                case "group_chatdescriptionset":
                
                    $outputArray = $xunErlang->update_group_chat_description($json_data);
                    
                    break;
                
                case "group_chatcallback_urlset":
                
                    $outputArray = $xunErlang->update_group_chat_callback_url($json_data);
                    
                    break;

                case "group_chatwelcome_messageset":
                
                    $outputArray = $xunErlang->update_group_chat_welcome_message($json_data);
                    
                    break;

                case "group_chatcommanddetailsget":

                    $outputArray = $xunErlang->group_chat_command_detail($json_data);

                    break;

                case "group_chatcryptohashupdate":

                    $outputArray = $xunErlang->group_chat_update_crypto_hash($json_data);

                    break;

                case "group_chatmsgforward":

                    $outputArray = $xunErlang->group_chat_msg_forward($json_data);
        
                    break;
                
                case "appgroup_chatkeyrevoke":

                    $outputArray = $xunErlang->group_chat_key_revoke($json_data);

                    break;

                case "appgroup_chatinvitekeydetail":

                    $outputArray = $xunErlang->group_chat_key_detail($json_data);

                    break;

                case "group_chatjoininvite":

                    $outputArray = $xunErlang->group_chat_join_invite_link($json_data);

                    break;

                case "encryptionpublic_keyuserupdate":
                
                    $outputArray = $xunErlang->update_user_public_key($json_data);
                    
                    break;
                        
                case "encryptionpublic_keyget":
                    
                    $outputArray = $xunErlang->get_user_public_key($json_data);
                    
                    break;
                                
                case "encryptionencrypted_private_keyuserupdate":
                    
                    $outputArray = $xunErlang->update_user_encrypted_key($json_data);
                    
                    break;
                
                case "encryptionencrypted_private_keyuserget":
                    
                    $outputArray = $xunErlang->get_user_encrypted_key($json_data);
                    
                    break;
            
                case "ticketaccept":

                    $outputArray = $xunErlang->accept_livechat($json_data);

                    break;
                
                case "ticketclose":

                    $outputArray = $xunErlang->close_livechat($json_data);

                    break;

                case "ticketstandup":

                    $outputArray = $xunErlang->standup_livechat($json_data);

                    break;

                case "appbusinesschatroom":

                    $outputArray = $xunErlang->app_business_chatroom($json_data);

                    break;

                case "appbusinesschatroomuserdetails":

                    $outputArray = $xunErlang->app_business_chatroom_user_details($json_data);

                    break;

                case "appbusinesschatroomemployeeall":

                    $outputArray = $xunErlang->app_business_chatroom_employee_all($json_data);

                    break;
                
                case "applivechatgroupcreate":

                    $outputArray = $xunErlang->app_livechat_group_create($json_data);

                    break;
        
                case "applivechatgroupdetails":
                    
                    $outputArray = $xunErlang->app_livechat_group_details($json_data);
                    
                    break;

                case "appbusinesstaglist":
                
                    $outputArray = $xunBusiness->business_tag_list($json_data);

                    break;

                case "appbusinesstagdetail":

                    $outputArray = $xunBusiness->app_business_tag_detail($json_data);

                    break;
                
                case "appbusinesstaguserlist":

                    $outputArray = $xunErlang->business_tag_user_list($json_data);

                    break;
                
                case "appbusinessemployeevcardget":

                    $outputArray = $xunBusiness->app_business_employee_vcard_get($url_string, $json_data);

                    break;
                
                case "appbusinessemployeevcardupdate":

                    $outputArray = $xunBusiness->app_business_employee_vcard_update($url_string, $json_data);

                    break;
                
                case "appbusinessemployeeresponse":

                    $outputArray = $xunBusiness->app_business_employee_response($json_data);

                    break;

                case "appbusinessemployeedetails":

                    $outputArray = $xunErlang->app_get_employee_details($json_data);

                    break;

                case "appcryptoaddressset":

                    $outputArray = $xunBusiness->app_crypto_address_set($json_data);

                    break;

                case "appcryptoaddressget":

                    $outputArray = $xunBusiness->xun_app_crypto_address($json_data);

                    break;
                
                case "appcryptomobileget":

                    $outputArray = $xunBusiness->app_crypto_mobile_get($json_data);

                    break;

                case "appcryptoaddressverify":

                    $outputArray = $xunBusiness->app_crypto_address_verify($json_data);

                    break;
        
                case "appcryptotransaction":
                
                    $outputArray = $xunBusiness->get_app_crypto_transaction_token($json_data);

                    break;
                
                case "appcryptotransactionv2":
                    
                    $outputArray = $xunBusiness->get_app_crypto_transaction_token_v2($json_data);

                    break;
                
                case "appcryptoescrow_agent_addressget":

                    $outputArray = $xunCompanyWalletAPI->getEscrowAgentAddress($json_data);

                    break;

                case "appcryptowallettransactionupdate":
                    
                    $outputArray = $xunErlang->update_wallet_transaction($json_data);

                    break;

                case "appcryptoescrowtransactiondetails":
                    
                    $outputArray = $xunErlang->get_escrow_details($json_data);

                    break;
                
                case "appcryptobusinesssearch":

                    $outputArray = $xunBusiness->business_wallet_search($json_data);

                    break;

                case "appcryptoescrowtransactionreport":
                
                    $outputArray = $xunErlang->escrow_transaction_report_user($json_data);

                    break;

                case "appcryptoescrowtransactionrequest":
                    
                    $outputArray = $xunErlang->escrow_transaction_request_money($json_data);

                    break;

                case "appcryptosigningdetails":

                    $outputArray = $xunErlang->get_fund_transfer_signing_details($json_data);

                    break;

                case "appcryptowallettransactionupdatev2":
                    
                    $outputArray = $xunErlang->update_wallet_transaction_v2($json_data);

                    break;

                case "appcryptoservice_chargedetails":

                    $outputArray = $xunErlang->get_service_charge_details($json_data);

                    break;
                            
                case "appbusinessencryptedwalletkeyget":

                    $outputArray = $xunBusiness->get_business_encrypted_wallet_private_key($json_data);

                    break;
                
                case "appbusinessencryptedwalletkeyupdate":

                    $outputArray = $xunBusiness->update_business_encrypted_wallet_private_key($json_data);

                    break;
        
                case "appsetfiatcurrency":
                    
                    $outputArray = $xunErlang->set_fiat_currency($json_data);

                    break;

                case "appgetfiatcurrencylisting":

                    $outputArray = $xunErlang->get_fiat_currency_listing($json_data);

                    break;

                case "appexternaladdressset":
                
                    $outputArray = $xunErlang->set_external_address($json_data);
                    
                    break;
                
                case "appexternaladdressget":

                    $outputArray = $xunErlang->get_external_address($json_data);

                    break;

                // Start Marketplace APIs//

                case "marketplacepayment_methodlist":
            
                    $outputArray = $xunMarketplace->get_marketplace_payment_method_listing($json_data);
                    
                    break; 

                case "marketplacepayment_methodlistv2":
                
                    $outputArray = $xunMarketplace->get_marketplace_payment_method_listing_v2($json_data);
                    
                    break; 

                case "marketplacepayment_methoduseradd":
                
                    $outputArray = $xunMarketplace->add_user_payment_method($json_data);

                    break; 

                case "marketplacepayment_methoduserdetails":
                
                    $outputArray = $xunMarketplace->get_user_payment_method_details($json_data);

                    break; 
                    
                case "marketplacepayment_methoduserlist":
                
                    $outputArray = $xunMarketplace->get_user_payment_method_listing($json_data);

                    break; 

                case "marketplacepayment_methoduserdelete":
                
                    $outputArray = $xunMarketplace->delete_user_payment_method($json_data);

                    break;
                
                case "marketplacesettingsummary":
            
                    $outputArray = $xunMarketplace->get_user_marketplace_summary($json_data);

                    break;

                case "marketplaceadvertisementplace":
                
                    $outputArray = $xunMarketplace->get_place_advertisement_info($json_data);

                    break; 

                case "marketplaceadvertisementcryptocurrencyprice":
                
                    $outputArray = $xunMarketplace->get_advertisement_cryptocurrency_price($json_data);

                    break; 

                case "marketplaceadvertisementbuyplace":
            
                    $outputArray = $xunMarketplace->place_buy_advertisement($json_data);
                    
                    break; 

                case "marketplaceadvertisementsellplace":
                
                    $outputArray = $xunMarketplace->place_sell_advertisement($json_data);
                    
                    break; 
                
                case "marketplaceadvertisementlist":
                
                    $outputArray = $xunMarketplace->get_advertisement_listing($json_data);

                    break;

                case "marketplaceadvertisementdetails":
                
                    $outputArray = $xunMarketplace->get_advertisement_details($json_data);

                    break; 

                case "marketplaceadvertisementuserlist":
                
                    $outputArray = $xunMarketplace->get_user_advertisement_listing($json_data);

                    break; 
        
                case "marketplaceadvertisementuserdetails":
                
                    $outputArray = $xunMarketplace->get_user_advertisement_details($json_data);

                    break;
                
                case "marketplaceadvertisementuserorderlist":
            
                    $outputArray = $xunMarketplace->get_user_advertisement_order_listing($json_data);

                    break; 

                case "marketplaceadvertisementuserorderdetails":
                
                    $outputArray = $xunMarketplace->get_user_advertisement_order_details($json_data);

                    break;
                
                case "marketplacecurrencylist":
            
                    $outputArray = $xunMarketplace->get_supported_currencies($json_data);

                    break;
                
                case "marketplaceadvertisementcancel":
                
                    $outputArray = $xunMarketplace->cancel_advertisement($json_data);

                    break; 

                case "marketplaceadvertisementorderplace":
                
                    $outputArray = $xunMarketplace->place_order($json_data);

                    break;
                
                case "marketplaceadvertisementorderpaid":
            
                    $outputArray = $xunMarketplace->paid_advertisement_order($json_data);

                    break;
                
                case "marketplaceadvertisementordercancel":
                
                    $outputArray = $xunMarketplace->cancel_advertisement_order($json_data);

                    break; 

                case "marketplaceadvertisementorderextend_time":
                
                    $outputArray = $xunMarketplace->extend_time_advertisement_order($json_data);

                    break;

                case "marketplaceadvertisementorderrelease_coin":
                
                    $outputArray = $xunMarketplace->release_coin_advertisement_order($json_data);

                    break;
                
                case "marketplaceadvertisementorderreport":
                
                    $outputArray = $xunMarketplace->report_user_advertisement_order($json_data);

                    break;
                
                case "marketplaceadvertisementorderremind_seller":
                
                    $outputArray = $xunMarketplace->remind_seller_advertisement_order($json_data);

                    break; 

                case "marketplaceadvertisementorderrate":
                
                    $outputArray = $xunMarketplace->rate_user($json_data);

                    break;
                
                case "marketplaceadvertisementorderlist":
                
                    $outputArray = $xunMarketplace->get_advertisement_order_listing($json_data);

                    break;
                    
                case "marketplaceadvertisementorderdetails":
                
                    $outputArray = $xunMarketplace->get_advertisement_order_details($json_data);

                    break;
                
                case "marketplaceordertransactionuser":
                
                    $outputArray = $xunMarketplace->save_user_transaction_hash($json_data);

                    break;
                

                case "marketplaceordertransactionescrow":
                
                    $outputArray = $xunMarketplace->save_escrow_transaction_hash($json_data);

                    break;

                case "marketplaceescrownotification":
                
                    $outputArray = $xunMarketplace->escrow_notification($json_data);

                    break;

                case "marketplacexmppmessage":
            
                    $outputArray = $xunMarketplace->xmpp_marketplace_chat_room($json_data);

                    break; 

                case "marketplaceescrowvalidate":
                
                    $outputArray = $xunMarketplace->escrow_validation($json_data);

                    break;

                // End Marketplace API//

                case "appcryptobackuprequest":

                    $outputArray = $xunErlang->request_wallet_address_otp($json_data);

                    break;

                case "appcryptobackupverify":

                    $outputArray = $xunErlang->verify_wallet_address_otp($json_data);

                    break;

                case "appcryptobackupstatus":

                    $outputArray = $xunErlang->get_wallet_verification_status($json_data);

                    break;

                case "appmessagearchiveget":

                    $outputArray = $xunErlang->get_chatroom_message_archive($json_data);

                    break;

                case "appgift_codeverify":
                    // xun/app/gift_code/verify
                    $outputArray = $xunGiftCode->app_verify_gift_code($json_data);
                    
                    break; 

                case "appgift_coderedeem":
                    // xun/app/gift_code/verify
                    $outputArray = $xunGiftCode->redeem_gift_code($json_data);
                    
                    break; 

                // Start App Referral APIs //
                case "appuserreferralreferreradd":

                    $outputArray = $xunReferral->add_upline($json_data);
                    
                    break;

                case "appuserreferraldetails":
            
                    $outputArray = $xunReferral->get_user_referral_tree($json_data);
                    
                    break;

                case "appuserreferralsummary":
                
                    $outputArray = $xunReferral->get_referral_summary($json_data);
                    
                    break;

                case "appuserreferralhistorylist":
                
                    $outputArray = $xunReferral->get_referral_transaction_history_listing($json_data);
                    
                    break;

                case "appuserreferralmaster_dealer":
            
                    $outputArray = $xunReferral->get_master_upline_status($json_data);
                    
                    break;

                case "appuserreferralstatus":

                    $outputArray = $xunErlang->get_user_upline_freecoin_payout_status($json_data);

                    break;
                        
                case "appuserreferraldownlineadd":

                    $outputArray = $xunReferral->add_downline($json_data);
                    
                    break;
                        
                // End App Referral APIs //
                    
                case "appuserqr_code":
            
                    $outputArray = $xunUser->get_user_qr_code_string($json_data);
                    
                    break;

                case "appcryptoaddressexternalupdate":

                    $outputArray = $xunErlang->update_user_external_address($json_data);

                    break;

                case "appcryptoaddressexternallist":

                    $outputArray = $xunErlang->get_user_external_address_listing($json_data);

                    break;

                case "appcryptoaddressexternaldetails":

                    $outputArray = $xunErlang->get_user_external_address_details($json_data);

                    break;

                case "appcryptoaddressexternalhistorylist":

                    $outputArray = $xunErlang->get_user_external_address_history_description($json_data);

                    break;

                case "appcryptoaddressinfouser":

                    $outputArray = $xunErlang->get_user_info_by_address($json_data);

                    break;
                
                case "appnotification":
                
                    $outputArray = $xunErlang->apps_notification($json_data);
                    
                    break;

                case "appusercallback_urlupdate":
                
                    $outputArray = $xunErlang->set_wallet_callback_url($json_data);
                    
                    break;

                case "appusercallback_urlget":
            
                    $outputArray = $xunErlang->get_wallet_callback_url($json_data);
                    
                    break;

                case "appcryptolive_price":

                    $outputArray = $xunErlang->get_live_price_listing($json_data);

                    break;
            
                case "appcryptolive_pricev1":

                    $outputArray = $xunErlang->get_live_price_listing_v1($json_data);

                    break;
                
                case "appuserprimary_addressget":
            
                    $outputArray = $xunErlang->get_user_primary_address($json_data);
                    
                    break;

                case "appuserprimary_addressupdate":
                
                    $outputArray = $xunErlang->update_user_primary_address($json_data);
                    
                    break;

                case "appuserprimary_addressupdatev2":
                
                    $outputArray = $xunErlang->update_user_primary_address_v2($json_data);
                    
                    break;

                case "appbusinessprofile_pictureupdate":

                    $outputArray = $xunBusiness->app_business_update_image($json_data);

                    break;

                case "appbusinessregister":

                    $outputArray = $xunBusiness->app_business_register($json_data);

                    break;

                case "appbusinessregisterv1":

                    $outputArray = $xunBusiness->app_business_register_v1($json_data);

                    break;

                case "appbusinessedit":

                    $outputArray = $xunBusiness->app_business_edit_details($json_data);

                    break;

                case "appverifyuserdocumentsubmit":

                    $outputArray = $xunKYC->submit_kyc_document($json_data);
        
                    break;
        
                case "appverifyuserdocumentstatusget":
        
                    $outputArray = $xunKYC->get_kyc_status($json_data);
        
                    break;
        
                case "appverifyuserdocumentupload_linkget":
        
                    $outputArray = $xunKYC->request_kyc_document_upload_link($json_data);
        
                    break;

                case "appuseraccepted_currency":

                    $outputArray = $xunErlang->get_user_accepted_currency($json_data);

                    break;

                case "appusersettingaccepted_currencygetv1":

                    $outputArray = $xunErlang->get_user_accepted_currency_setting_v1($json_data);

                    break;
    
                case "appusersettingaccepted_currencyget":

                    $outputArray = $xunErlang->get_user_accepted_currency_setting($json_data);

                    break;

                case "appusersettingaccepted_currencyupdate":

                    $outputArray = $xunErlang->update_user_accepted_currency_setting($json_data);

                    break;

                case "appusersettingaccepted_currencyratioupdate":

                    $outputArray = $xunErlang->update_accepted_currency_floating_ratio($json_data);

                    break;

                case "appuseraccepted_currencyv1":

                    $outputArray = $xunErlang->get_user_accepted_currency_v1($json_data);

                    break;

                case "appusersettingaccepted_currencyratioset":

                    $outputArray = $xunErlang->set_user_accepted_currency_setting($json_data);

                    break;

                case "appuserprofileget":

                    $outputArray = $xunErlang->get_user_profile($json_data);

                    break;

                case "appbusinesscallback_urlupdate":

                    $outputArray = $xunBusiness->set_business_callback_url($json_data);

                    break;

                case "appbusinesscallback_urlget":

                    $outputArray = $xunBusiness->get_business_callback_url($json_data);

                    break;

                case "appbusinessweblogin":

                    $outputArray = $xunBusiness->business_web_login($json_data, $ip, $user_agent);

                    break;

                case "appqrrequest":

                    $outputArray = $xunErlang->app_qr_request($json_data, $ip, $user_agent);

                    break;
                
                // App Crypto APIs//
                case "apppaymentgatewaycoinlistget":

                    $outputArray = $xunCrypto->get_payment_gateway_coin_list($json_data);

                    break;

                case "apppaymentgatewayapikeyget":

                    $outputArray = $xunCrypto->get_app_apikey_list($json_data);

                    break;

                case "apppaymentgatewayapikeycreate":

                    $outputArray = $xunCrypto->app_generate_apikey($json_data);

                    break;

                case "apppaymentgatewayapikeyremove":

                    $outputArray = $xunCrypto->app_delete_apikey($json_data);

                    break;

                case "apppaymentgatewaycallbackurlget":

                    $outputArray = $xunCrypto->get_app_callback_url($json_data);

                    break;

                case "apppaymentgatewaycallbackurlset":

                    $outputArray = $xunCrypto->set_app_callback_url($json_data);

                    break;

                case "apppaymentgatewaystatus":

                    $outputArray = $xunCrypto->get_app_paymentgateway_status($json_data);

                    break;

                case "apppaymentgatewaydestinationaddressset":

                    $outputArray = $xunCrypto->set_app_destination_address($json_data);

                    break;

                case "apppaymentgatewaydestinationaddressget":

                    $outputArray = $xunCrypto->get_app_destination_address($json_data);

                    break;

                case "apppaymentgatewaystatusupdate":

                    $outputArray = $xunCrypto->set_app_wallet_status($json_data);

                    break;
                
                case "apppaymentgatewaytransactionlistget":

                    $outputArray = $xunCrypto->get_app_transaction_list($json_data);

                    break;

                case "apppaymentgatewaytransactionlistdetail":

                    $outputArray = $xunCrypto->get_app_transaction_detail($json_data);

                    break;

                case "apppaymentgatewayaddresslistget":

                    $outputArray = $xunCrypto->get_app_address_list($json_data);

                    break;

                case "apppaymentgatewayaddresscreate":

                    $outputArray = $xunCrypto->generate_app_new_address($json_data);

                    break;

                //End App Crypto API//

                case "appbusinesstagget":

                    $outputArray = $xunBusiness->get_business_tag_listing($json_data);

                    break;

                case "appbusinessemployeelistget":

                    $outputArray = $xunBusiness->app_business_team_member_list($json_data);

                    break;

                case "appbusinessemployeedetailget":

                    $outputArray = $xunBusiness->app_business_employee_detail($json_data);

                    break;

                case "appbusinessemployeeadd":

                    $outputArray = $xunBusiness->app_business_employee_add($json_data);

                    break;

                case "appbusinessemployeeedit":

                    $outputArray = $xunBusiness->app_business_employee_edit($json_data);

                    break;

                case "appbusinessemployeedelete":

                    $outputArray = $xunBusiness->app_business_employee_delete($json_data);

                    break;

                //APP BUSINESS API
                case "appbusinessapi_keygenerate":

                    $outputArray = $xunBusiness->app_generate_api_key($json_data);

                    break;

                case "appbusinessapi_keyupdate":

                    $outputArray = $xunBusiness->app_update_apikey($json_data);

                    break;

                case "appbusinessapi_keydelete":

                    $outputArray = $xunBusiness->app_delete_apikey($json_data);

                    break;

                case "appbusinessapi_keylist":

                    $outputArray = $xunBusiness->app_api_key_listing($json_data);

                    break;

                case "appbusinessrequestmoneytransactionupdate":

                    $outputArray = $xunErlang->business_request_money_update_tx_hash($json_data);

                    break;

                case "appbusinesscreatedetailsverify": 
                    
                    $outputArray = $xunBusiness->validate_business_details($json_data);

                    break;

                case "appwalletbackgroundurlget":

                    $outputArray = $xunErlang->get_wallet_background_url($json_data);

                    break;

                // START APP PAY APIs //
                case "apppayproducttypelist":

                    $outputArray = $xunPay->get_product_type_listing($json_data);

                    break;

                case "apppayproductlist":

                    $outputArray = $xunPay->get_product_listing($json_data);

                    break;

                case "apppayproductdetail":

                    $outputArray = $xunPay->get_product_details($json_data);

                    break;

                case "apppaymainlist":

                    $outputArray = $xunPay->get_pay_main_page_listing($json_data);

                    break;

                case "apppaytransactionlist":

                    $outputArray = $xunPay->pay_transaction_listing($json_data);

                    break;

                case "apppaytransactiondetail":

                    $outputArray = $xunPay->pay_transaction_detail($json_data);

                    break;

                case "apppayredemptionmainpage":

                    $outputArray = $xunPay->get_redemption_main_page_listing($json_data);

                    break;


                case "apppayredemptionproductlist":

                    $outputArray = $xunPay->redemption_get_product_listing($json_data);

                    break;

                case "apppayredemptionproductdetail":

                    $outputArray = $xunPay->redemption_get_product_details($json_data);

                    break;

                // END APP PAY APIs //

                case "appswapcoinslogin":

                    $outputArray = $xunSwapcoins->swapcoinsLogin($json_data);

                    break;

                //App Story Api//
                case "appstorymediaupload_link":
                    
                    $outputArray = $xunStory->request_story_media_upload_link($json_data, 'app');
                    
                    break;

                case "appstorycreate":
                    
                    $outputArray = $xunStory->create_story($json_data, 'app');

                    break;

                case "appstoryupdatescreate":

                    $outputArray = $xunStory->create_story_updates($json_data, 'app');

                    break;

                case "appstorycreatedetailsget":
                    
                    $outputArray =  $xunStory->get_create_story_details($json_data);
                    
                    break;
                
                case "appstorymylistget":

                    $outputArray = $xunStory->get_my_story_list($json_data, 'app');

                    break;

                case "appstorymydetailsget":
                    
                    $outputArray = $xunStory->get_my_story_details($json_data);

                    break;

                case "appstorylistget":
                    
                    $outputArray = $xunStory->get_story_list($json_data);

                    break;

                case "appstorymaindetailsget":
                    
                    $outputArray = $xunStory->get_main_story_details($json_data);

                    break;
                
                case "appstorydetailsget":

                    $outputArray = $xunStory->get_story_details($json_data);

                    break;

                case "appstorysave":

                    $outputArray = $xunStory->save_story($json_data);

                    break;

                case "appstoryunsave":

                    $outputArray = $xunStory->unsave_story($json_data);

                    break;
                
                case "appstorysavedlistget": 

                    $outputArray = $xunStory->get_saved_story_list($json_data);

                    break;

                case "appstoryaddcomment" : 
                    
                    $outputArray = $xunStory->add_story_comment($json_data);

                    break;

                case "appstoryeditcomment":

                    $outputArray = $xunStory->edit_story_comment($json_data);

                    break;

                case "appstorydeletecomment":

                    $outputArray = $xunStory->delete_story_comment($json_data);

                    break;

                case "appstorycommentlistget":

                    $outputArray = $xunStory->get_story_comment_list($json_data);

                    break;

                case "appstoryfundwithdraw":

                    $outputArray = $xunStory->withdraw_fund($json_data);

                    break;

                case "appstoryfundmanage":

                    $outputArray = $xunStory->get_manage_fund_listing($json_data);

                    break;

                case "appstorynotificationlistget":

                    $outputArray = $xunStory->get_story_notification_list($json_data);

                    break;

                case "appstorymyactivitylistget":
                        
                    $outputArray = $xunStory->get_story_my_activity_list($json_data);

                    break;

                case "appstorybackerslist":

                    $outputArray = $xunStory->get_story_backers_listing($json_data);

                    break;

                case "appstorymytransactionhistory":

                    $outputArray = $xunStory->get_story_transaction_history_listing($json_data);

                    break;

                case "appstorydonatedlistget":

                    $outputArray = $xunStory->get_donated_story_listing($json_data);

                    break;

                case "appstorypaymentmethodset":

                    $outputArray = $xunStory->set_payment_method($json_data, "app");

                    break;

                case "appstorypaymentmethoddetailsget":

                    $outputArray = $xunStory->get_payment_method_details($json_data);

                    break;

                case "appstorywithdrawaldetailsget":
                    
                    $outputArray = $xunStory->get_withdrawal_details($json_data);

                    break;

                case "appstoryshare":
                    
                    $outputArray = $xunStory->app_share_story($json_data);

                    break;
                    

                case "appstorypaymentmethodlistget":
                
                    $outputArray = $xunStory->get_payment_method_listing($json_data);

                    break;

                case "appstorydonationpaymentmethodlistget":

                    $outputArray = $xunStory->app_get_story_payment_method_list($json_data);
        
                    break;

                case "appstorysettingpaymentmethodget":
            
                    $outputArray = $xunStory->app_story_setting_get_payment_method($json_data);
                    
                    break; 

                case "appstorydeletepaymentmethod":
            
                    $outputArray = $xunStory->delete_payment_method($json_data, "app");
                    
                    break; 

                case "appstorydonatefiat":
            
                    $outputArray = $xunStory->app_donation_fiat($json_data);
                    
                    break; 

                
                case "appstorydonationlist":

                    $outputArray = $xunStory->story_donation_listing($json_data, 'app');

                    break;

                case "appstoryownerupdatedonation":

                    $outputArray = $xunStory->owner_update_donation($json_data, 'app');

                    break;

                case "appstorygettransactiondetails":

                    $outputArray = $xunStory->get_story_transaction_details($json_data, 'app');

                    break;
                //End App Story Api

                case "appsystem_settingsget":

                    $outputArray = $xunErlang->get_system_settings($json_data);

                    break;

                case "userincominggrouplivechatmessage":
                
                    $outputArray = $xunErlang->user_incoming_group_livechat_message($json_data);
                    
                    break;

                case "usersettingaccountprivacyvcardpermission":
                    
                    $outputArray = $xunErlang->check_request_vcard_permission($json_data);
                    
                    break;
                    
                case "businessmessageforward":
                    
                    $outputArray = $xunErlang->business_message_forward($json_data);
                    
                    break;

                case "appbusinesschatroomdetails":

                    $outputArray = $xunErlang->get_livechat_details($json_data);

                    break;
                    
                case "livechatusermessage":

                    $outputArray = $xunErlang->user_incoming_livechat_message($json_data);

                    break;

                case "livechatemployeemessage":

                    $outputArray = $xunErlang->employee_incoming_livechat_message($json_data);

                    break;

                case "ticketcloseguest":

                    $outputArray = $xunErlang->close_guest_livechat($json_data);

                    break;
            
                case "businessemployeename":

                    $outputArray = $xunErlang->get_employee_name($json_data);

                    break;
                    
                case "businessemployeedetails":

                    $outputArray = $xunErlang->get_employee_details($json_data);

                    break;
                                            
                case "businesslivechatpromptget":

                    $outputArray = $xunErlang->get_livechat_prompt($json_data);

                    break;

                case "appmessagearchive":

                    $outputArray = $xunErlang->archive_message($json_data);

                    break;
                    
                case "appusernicknameupdate":

                    $outputArray = $xunUser->update_nickname($json_data);

                    break;

                case "appuservcardupdate":

                    $outputArray = $xunUser->update_vcard($json_data);

                    break;
                
                // START announcement API
                        
                case "appannouncementuserget":
                    $outputArray = $xunAnnouncement->get_announcement_for_user($json_data);

                    break;
                    
                case "appannouncementview":
                    $outputArray = $xunAnnouncement->announcement_view($json_data);

                    break;
                    
                // END announcement API
                        
                //  START xmpp message API
                case "userincomingwalletmessage":
                    $outputArray = $xunErlang->user_incoming_wallet_transaction_chatroom_message($json_data);

                    break;
                //  END xmpp message API

                case "usersettingaccountverify":

                    $outputArray = $xunUser->verify_change_number($json_data);

                    break;

                case "getprivacysettings":

                    $outputArray = $xunBusiness->get_privacy_settings($json_data);

                    break;
                
                case "businessfollow":

                    $outputArray = $xunErlang->business_follow($json_data);

                    break;

                case "businessunfollow":

                    $outputArray = $xunErlang->business_unfollow($json_data);

                    break;

                case "businessfollowerlang":

                    $outputArray = $xunErlang->business_follow($json_data);

                    break;

                case "businessunfollowerlang":

                    $outputArray = $xunBusiness->business_unfollow($json_data);

                    break;

                case "appphone_approvaltransactiondetails":

                    $outputArray = $xunPhoneApprove->app_get_request_details($json_data);

                    break;
            
                case "appphone_approvalresponseupdate":

                    $outputArray = $xunPhoneApprove->app_update_request_status($json_data);

                    break;
            
                case "erlangvcardupdate":

                    $outputArray = $xunErlang->update_erlang_vcard($json_data);

                    break;
            
                case "apppaymentaddressget":

                    $outputArray = $xunReward->app_generate_payment_address($json_data);

                    break;

                case "apppaymenttransactionupdate":

                    $outputArray = $xunReward->update_redemption_reference($json_data);

                    break;
    
                case "appcoinsadd":
                    
                    $outputArray = $xunErlang->add_coins($json_data);

                    break;

                case "appuserlanguageset":

                    $outputArray = $xunErlang->set_user_language($json_data);

                    break;

                case "applanguagelistget":

                    $outputArray = $xunErlang->get_language_list($json_data);

                    break;

                //START APP REWARD API

                case "apprewardcoinimageupload":

                    $outputArray = $xunReward->app_coin_image_upload($json_data);

                    break;

                case "apprewardwalletbackgroundupload":

                    $outputArray = $xunReward->app_wallet_background_upload($json_data);

                    break;


                case "apprewardcardfontcolorupdate":

                    $outputArray = $xunReward->app_card_font_color_update($json_data);

                    break;

                case "apprewardcarddesignupdate":

                    $outputArray = $xunReward->app_card_design_update($json_data);

                    break;      

                case "appcredittransfer":

                    $outputArray = $xunBusinessCoin->app_transfer_credit($json_data);

                    break;                    
                //END APP REWARD API
                    
                default:

                    $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Categorynotfound . ", 'data' => '');
                    break;

            }
            
        }else if(strtolower($source) == 'crypto'){
            
            if(!in_array($command, $filtered_crypto_commands)){
                
                if(!in_array($ip, $crypto_whitelist_ip_address) && $config['environment'] != 'dev'){

                    $tag = "IP Not Whitelisted";
                    $content = "URL: ".$_SERVER['HTTP_HOST']."\n";
                    $content .= "Command: " . $command."\n";
                    $content .= "Source: ".$source."\n";
                    $content .= "IP: " . $ip."\n";
                    $content .= "Created At: " .date("Y-m-d H:i:s")."\n";

                    $erlang_params = [];
                    $erlang_params["tag"] = $tag;
                    $erlang_params["message"] = $content;
                    $erlang_params["mobile_list"] = $xun_numbers;
                    $xmpp_result = $general->send_thenux_notification($erlang_params, 'thenux_issues');
                    $outputArray = array('code' => 0, 'message' => "FAILED", 'message_d' => "IP not whitelisted", 'ip'=> $ip);

                    $dataOut = $outputArray;
                    $status  = $dataOut['status'];

                    $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);

                    echo json_encode($outputArray);
                    exit();
                }

                if(!$xunCrypto->validate_access_token($access_token)){
                
                    //error access token
                    $notification_message = "Source: ".$source;
                    $notification_message .= "\nCommand: ".$command;
                    $notification_message .= "\nAccess Token: ".$access_token;
                    $notification_message .= "\n\nData In: ".json_encode($json_data);
                    $notification_tag = "Failed validate access token";
                    $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "nuxpay");


                    $outputArray = array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Access Token");

                    $dataOut = $outputArray;
                    $status  = $dataOut['status'];

                    $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);

                    echo json_encode($outputArray);

                    exit();

                }
                
            }

            switch($command){
                    
                //Crypto APIs//   
                
                case "cryptogetusernamefromaddress":
                    
                    $outputArray = $xunCrypto->get_username_from_address($json_data);
                    
                    break; 

                case "cryptotransactiontokenverify":
                    
                    $outputArray = $xunCrypto->verify_user_crypto_transaction_token($json_data);
                        
                    break; 


                case "cryptocoinnew":

                    $outputArray = $xunCrypto->add_new_coin($json_data);

                    break;

                case "cryptocallback":

                    $outputArray = $general->keep_queue_callback($command, $json_data);
                    //$outputArray = $xunCrypto->save_crypto_callback($json_data);

                    break;

                case "cryptotransactioncallback":

                    $outputArray = $general->keep_queue_callback($command, $json_data); 
                    //$outputArray = $xunCrypto->transaction_callback($json_data);

                    break; 

                case "cryptoservice_chargeget":
                    
                    $outputArray = $xunCrypto->get_service_charge($json_data);
                    
                    break;
                
                case "cryptoupdatetransactionhash":
                    
                    $outputArray = $general->keep_queue_callback($command, $json_data);
                    // $outputArray = $xunCrypto->crypto_update_transaction_hash($json_data);

                    break;

                case "cryptogetdestinationaddress":
                
                    $outputArray = $xunCrypto->get_destination_address($json_data);
                    
                    break;

                case "cryptonewTokenCreationcallback":
                
                    $outputArray = $xunCrypto->new_token_creation_callback($json_data);
                    
                    break;

                case "cryptocustomcointransactiontokenverify":

                    $outputArray = $xunCrypto->custom_coin_verify_transaction_token($json_data);

                    break;

                case "cryptogetminerfeebalance":

                    $outputArray = $xunCrypto->get_miner_fee_balance($json_data);

                    break;

                case "cryptotransactionidreplace":

                    $outputArray = $xunCrypto->replace_transaction_id($json_data);

                break;

                case "cryptopgaddressfundoutcheck":
                    
                    $outputArray = $xunCrypto->check_pg_address_fund_out($json_data);

                    break;

                case "cryptoswaporderstatusupdate":

                    $outputArray = $xunCrypto->crypto_update_order_status($json_data);

                    break;  
                
                //End Crypto APIs//

                //  Start of TTwo Integration //
                    
                case "cryptowalletservertokenverify":
        
                    $outputArray = $xunCrypto->wallet_server_verify_token($json_data);

                    break;

                case "cryptoexternalfundoutcallback":

                    $outputArray = $general->keep_queue_callback($command, $json_data);
                    // $outputArray = $xunCrypto->crypto_external_fund_out_callback($json_data);

                    break;
                
                // End of TTwo Integeration //

                default:

                    $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Categorynotfound . ", 'data' => '');
                    break;

            }
            
        }else if(strtolower($source) == 'wallet'){
            
            if(!in_array($command, $filtered_crypto_commands)){
                
                if(!$xunCrypto->validate_access_token($access_token)){
                
                    //error access token
                    $notification_message = "Source: ".$source;
                    $notification_message .= "\nCommand: ".$command;
                    $notification_message .= "\nAccess Token: ".$access_token;
                    $notification_message .= "\n\nData In: ".json_encode($json_data);
                    $notification_tag = "Failed validate access token";
                    $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "nuxpay");


                    $outputArray = array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Access Token");

                    $dataOut = $outputArray;
                    $status  = $dataOut['status'];

                    $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);

                    echo json_encode($outputArray);

                    exit();

                }
                
            }

            switch($command){
                default:

                $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Categorynotfound . ", 'data' => '');
                break;
            }
            
        }else if(strtolower($source) == 'groupchat'){
            
                
                
            $group_id = $_SERVER["HTTP_GROUP_ID"];
            
            if(!$xunGroupChat->validate_access_token($group_id, $access_token)){
            
                //error access token
                $notification_message = "Source: ".$source;
                $notification_message .= "\nCommand: ".$command;
                $notification_message .= "\nGroup Id: ".$group_id;
                $notification_message .= "\nAccess Token: ".$access_token;
                $notification_message .= "\n\nData In: ".json_encode($json_data);
                $notification_tag = "Failed validate access token";
                $general->send_thenux_notification(array('tag'=>$notification_tag, 'message'=>$notification_message), "nuxpay");


                $outputArray = array('code' => -100, 'message' => "LOGGED OUT", 'message_d' => "Invalid API Key.", "developer_msg" => "Invalid API Key.");

                $status  = "error";

                $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);

                echo json_encode($outputArray);

                exit();

            }
                
            switch($command){

                case "group_chatsendannouncement":
                
                    $outputArray = $xunGroupChat->group_chat_send_announcement($json_data);
                    
                    break;

                default:

                    $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Categorynotfound . ", 'data' => '');
                    break;
            }
                
            
        }else if((strtolower($source) == 'nuxpay' || strtolower($source2) == 'nuxpay') && $source != ''){
        
            switch($command){
                
                // Swapcoins API
                case "swapcoinpreviewget":

                    $outputArray = $xunSwapcoins->getPreviewSwapCoinRate($json_data, strtolower($source));

                    break;

                case "swapcoinsupportedcoinsget":

                    $outputArray = $xunSwapcoins->getSupportedSwapCoinsListing($json_data, strtolower($source));

                    break;

                case "swapcoinestimaterate":

                    $outputArray = $xunSwapcoins->estimateSwapCoinRate($json_data, strtolower($source));

                    break;

                case "swapcoinswap":

                    $outputArray = $xunSwapcoins->swap($json_data, strtolower($source));

                    break;

                case "swapcoinhistoryget":

                    $outputArray = $xunSwapcoins->getSwapHistory($json_data, strtolower($source));

                    break;

                //Crypto APIs//
                case "webpaydestinationaddressset":
                    
                    $outputArray = $xunCrypto->set_destination_address_v2($json_data, strtolower($source));
             
                    break;

                case "webpaygenerateaddress":

                    $outputArray = $xunCrypto->generate_new_address($json_data, strtolower($source), $ip);
                
                    break;

                case "webpaygenerateapi_key":

                    $outputArray = $xunBusiness->generate_api_key($json_data, strtolower($source));

                    break;

                 case "cryptosetdestinationaddress":
                    
                    $outputArray = $xunCrypto->set_destination_address($json_data, strtolower($source));
                    
                    break;

                case "cryptosetcallbackurl":
                
                    $outputArray = $xunCrypto->set_callback_url($json_data);
                    
                    break; 

                case "cryptogetcallbackurl":
                    
                    $outputArray = $xunCrypto->get_callback_url($json_data);
                    
                    break; 
                
                case "cryptogetdeveloperdata":
                    $outputArray = $xunCrypto->get_developer_data($json_data);
                    break;

                case "cryptogetdeveloperiocommandlist":
                    $outputArray = $xunCrypto->get_developer_io_command_list($json_data);
                    break;

                case "cryptogetdeveloperiodata":
                    $outputArray = $xunCrypto->get_developer_io_data($json_data);
                    break;

                case "cryptogeneratenewaddress":
                
                    $outputArray = $xunCrypto->generate_new_address($json_data, strtolower($source));
                    
                    break;

                case "cryptogetdestinationaddress":
                    
                    $outputArray = $xunCrypto->get_destination_address($json_data);
                    
                    break;
                
                case "cryptogetaddresslist":
                    
                    $outputArray = $xunCrypto->get_address_list($json_data);
                    
                    break;

                case "cryptosetwalletstatus":
                    
                    $outputArray = $xunCrypto->set_wallet_status($json_data);
                    
                    break;

                case "cryptogenerateapikey":
                    
                    $outputArray = $xunCrypto->generate_apikey($json_data, strtolower($source));
                    
                    break;
                
                case "cryptodeleteapikey":
                    
                    $outputArray = $xunCrypto->delete_apikey($json_data);
                    
                    break;
                
                case "cryptogetapikeylist":
                    
                    $outputArray = $xunCrypto->get_apikey_list($json_data, 'nuxpay');
                    
                    break;
                
                case "cryptogetwalletsdestinationaddress":
                    
                    $outputArray = $xunCrypto->get_wallets_destination_address($json_data);
                    
                    break;

                case "cryptogetwalletsdestinationaddressv1":

                    $outputArray = $xunCrypto->get_wallets_destination_address_v1($json_data);
                    
                    break;

                case "cryptogetwallettype":
                    
                    $outputArray = $xunCrypto->get_wallet_type($json_data, $user_id);
                    
                    break;   

                case "cryptogettransactionlist":
                    
                    $outputArray = $xunCrypto->get_transaction_list($json_data);
                    
                    break;
            
                case "cryptogettransactionlistv1":
                
                    $outputArray = $xunCrypto->get_transaction_list_v1($json_data);
                    
                    break;

                case "cryptoescrowgettransaction":
                
                    $outputArray = $xunCrypto->get_escrow_transaction($json_data);
                    
                    break;

                case "cryptogetwalletdata":
                    
                    $outputArray = $xunCrypto->get_wallet_data($json_data);
                    
                    break; 
                
                case "cryptosetdestinationaddressv1":
                
                    $outputArray = $xunCrypto->set_destination_address_v1($json_data);

                    break;

                case "cryptodestinationaddressstatusset":
                
                    $outputArray = $xunCrypto->set_destination_address_status($json_data, $user_id);

                    break;

                case "cryptodelegate_addressget":
                
                    $outputArray = $xunCrypto->get_payment_gateway_delegate_address($json_data);

                    break;
                //end crypto api

                case "webpaypgaddresslistget":
                    
                    $outputArray = $xunPaymentGateway->get_pg_address_list($json_data);

                    break;

                case "webpaytransactiongrossvolumeget":

                    $outputArray = $xunPaymentGateway->get_transaction_gross_volume($json_data);

                    break;

                case "webpaytransactionsalesdataget":

                    $outputArray = $xunPaymentGateway->get_transaction_sales_data($json_data);

                    break;

                case "webpaytransactionoverallsalesget":

                    $outputArray = $xunPaymentGateway->get_overall_sales_data($json_data);
                    break;

                case "webpaylatesttransactionget":

                    $outputArray = $xunPaymentGateway->get_latest_transactions($json_data);

                    break;

                case "webpaytransactiondetailsget":

                    $outputArray = $xunPaymentGateway->get_transaction_details($json_data);
                    
                    break;
                
                case "webpaynewslisting":

                    $outputArray = $xunPaymentGateway->get_news_list($json_data);

                    break;
                
                case "getestimatedminerfee":

                    $outputArray = $xunPaymentGateway->get_estimated_miner_fee($json_data, $user_id);

                    break;

                //Start business api//
                case "webpayeditinfo":

                    $outputArray = $xunBusiness->nuxpay_edit($json_data);

                    break;

                case "webpayprofilepictureupload":

                    $outputArray = $xunBusiness->nuxpay_profile_picture_uplaod($json_data);

                    break;

                case "webpaychangepassword":

                    $outputArray = $xunBusiness->nuxpay_changepassword($json_data);

                    break;

                
                case "webpayinvoicetransactionupdate":

                    $outputArray = $xunPaymentGateway->update_invoice_transaction($json_data);

                    break;
                
                case "webpaygetinvoicetransaction":

                    $outputArray = $xunPaymentGateway->get_invoice_transaction($json_data);

                    break;

                case "webpaysendfundverification":

                    $outputArray = $xunPaymentGateway->create_send_fund($json_data, 'verification', $source, $ip);

                    break;

                case "webpaysendfundrequest":

                    $outputArray = $xunPaymentGateway->create_send_fund($json_data, 'confirmation', $source, $ip);

                    break;

                case "webpayredeemcodedetailsget":

                    $outputArray = $xunPaymentGateway->get_redeem_code_details($json_data);

                    break;

                case "webpayredeempin":

                    $outputArray = $xunPaymentGateway->nuxpay_redeem_redemption_pin($json_data, $source, $ip);

                    break;

                case "nuxpayescrowrelease":

                    $outputArray = $xunPaymentGateway->nuxpay_escrow_release($json_data, $source, $ip);

                    break;


                case "nuxpayescrowsendmessage":

                    $outputArray = $xunPaymentGateway->nuxpay_escrow_send_message($json_data, $source);

                    break;

                case "nuxpayescrowgetmessages":

                    $outputArray = $xunPaymentGateway->nuxpay_escrow_get_messages($json_data, $source);

                    break;
                    
                case "webpayredeempinresend":

                    $outputArray = $xunPaymentGateway->resend_redeem_code($json_data, $source, $ip);

                    break;  

                //Advertisement
                case "nuxpayadvestimentcreate":

                     $outputArray = $xunAdvestiment->create_advestiment($json_data, $user_id);
        
                    break;     

                case "nuxpayadvestimentdelete":

                     $outputArray = $xunAdvestiment->delete_advestiment($json_data, $user_id);
        
                    break;     

                case "nuxpayadvestimentupdate":

                     $outputArray = $xunAdvestiment->update_advestiment($json_data, $user_id);
        
                    break;  

                case "nuxpayadvestimentlistingget":

                     $outputArray = $xunAdvestiment->get_advertisement_listing($json_data, $user_id);
        
                    break;  

                case "nuxpayadvestimentdetailget":

                    $outputArray = $xunAdvestiment->get_advertisement_detail($json_data);
        
                    break; 

                case "webgetuserinfo":

                    $outputArray = $xunPaymentGateway->get_user_info($json_data, $source, $ip);

                    break;  

                //buy sell
                case "nuxpaybuysellwallettypeget":

                    $outputArray = $xunAdvestiment->get_buy_sell_wallet_type();

                    break;  

                case "nuxpaybuysellordercreate":

                     $outputArray = $xunAdvestiment->create_buysell_order($json_data, $user_id);
        
                    break;

                case "nuxpaybuysellorderdelete":

                     $outputArray = $xunAdvestiment->delete_buysell($json_data, $user_id);
        
                    break;

                case "nuxpaybuysellorderupdate":

                     $outputArray = $xunAdvestiment->update_buysell($json_data, $user_id);
        
                    break;

                case "nuxpaybuyselllistingget":

                     $outputArray = $xunAdvestiment->get_buysell_listing($json_data, $user_id);
        
                    break;
                    
                case "nuxpaybuyselldetailget":

                     $outputArray = $xunAdvestiment->get_buysell_detail($json_data, $user_id);
        
                    break;
                    
                case "webpaypgaddresswithdraw":

                    $outputArray = $xunPaymentGateway->pg_address_withdrawal($json_data, $user_id, $source, 'confirmation');

                    break;

                case "webpaypgaddresswithdrawverification":

                    $outputArray = $xunPaymentGateway->pg_address_withdrawal($json_data, $user_id, $source, 'verification');

                    break;
                    
                case "cryptoconversionget":

                    $outputArray = $xunPaymentGateway->get_crypto_conversion_rate($json_data);

                    break;  

                case "webpaysendfunddetailsget":

                    $outputArray = $xunPaymentGateway->get_send_fund_details($json_data);

                    break;

                case "webpaypaymentaddressdetailget":

                    $outputArray = $xunPaymentGateway->get_payment_gateway_address_details($json_data);

                    break;

                case "webpaywalletaddresslistget":

                    $outputArray = $xunPaymentGateway->get_wallet_address_list($json_data, $source);
        
                    break;

                case "webpayaddressvalidate":

                    $outputArray = $xunPaymentGateway->nuxpay_validate_address($json_data);
        
                    break;
                
                case "webpaybuycryptosupportedcurrencyget":

                    $outputArray = $xunPaymentGateway->get_buy_crypto_supported_currency($json_data);
        
                    break;

                case "webpaybuycryptohistoryget":

                    $outputArray = $xunPaymentGateway->get_buy_crypto_history($json_data, $user_id);

                    break;

                case "webpaybuycryptosettingget":

                    $outputArray = $xunPaymentGateway->get_buy_crypto_setting($json_data, $user_id);

                    break;
                //End business api//

                case "webpaydeveloperlogcommandlist":

                    $outputArray = $xunBusiness->get_developer_log_command_list($json_data);

                    break;

                case "webpaydeveloperlog":

                    $outputArray = $xunBusiness->get_developer_log($json_data, $user_id);

                    break;

                default:

                    $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Categorynotfound . ", 'data' => '');
                    break;
            }
        } else if(strtolower($source) == 'whitelistserver'){

            switch($command){
                case "businessdetailget":
                    $outputArray = $xunBusiness->get_business_detail($json_data);
                    break;

                case "whitelistforwardbroadcast":
                    $outputArray = $xunBusiness->whitelist_forward_broadcast($json_data);
                    break;

                case "userbcexternaladdressget":
                    $outputArray = $xunBusiness->get_user_bc_external_address_list($json_data);
                    break;

                default:
                    $outputArray = array('status' => "error", 'code' => 1, 'statusMsg' => "Categorynotfound . ", 'data' => '');
                    break;
            }

        } else{
            
            $outputArray = array('code' => 0, 'message' => "FAILED", 'message_d' => "Invalid Source");

            $dataOut = $outputArray;
            $status  = $dataOut['status'];

            $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);

            echo json_encode($outputArray);

            exit();
        
        }

        break;
        
    }

    $completedTime = date("Y-m-d H:i:s");
    $processedTime = time() - $timeStart;

    $dataOut = $outputArray;
    $status  = isset($dataOut['status']) ? $dataOut['status'] : $dataOut["code"];

    if ($command != "getWebservices") {
        $updateWebservice = $webservice->updateWebserviceData($webserviceID, $dataOut, $status, $completedTime, $processedTime, $tblDate, 0);
    }
    echo json_encode($outputArray);

}
