<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Database functionality for Admins.
     * Date  11/07/2017.
     **/
    
    class Admin {

        function __construct() {
            // $this->cash    = Client::validation->bonus->cash;
            // $this->invoice = Client::validation->invoice;
            // $this->product = Client::validation->product;
            // $this->country = Client::validation->country;
            // $this->client  = $client;
            // $this->otp     = Client::validation->bonus->otp;
            // $this->tree    = Client::validation->bonus->tree;
            // $this->bonusReport = $bonusReport;
            // $this->wallet  = Client::validation->bonus->cash->wallet;
            // $this->message = Client::validation->bonus->otp->message;
        }

        public function adminLogin($params) {

            $db = MysqliDb::getInstance();

            //Language Translations.
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTime        = date('Y-m-d H:i:s');

            // Get the stored password type.
            $passwordEncryption = Setting::getAdminPasswordEncryption();

            $username = trim($params['username']);
            $password = trim($params['password']);

            $db->where('username', $username);
            if($passwordEncryption == "bcrypt") {
                // Bcrypt encryption
                // Hash can only be checked from the raw values
            }
            else if ($passwordEncryption == "mysql") {
                // Mysql DB encryption
                $db->where('password', $db->encrypt($password));
            }
            else {
                // No encryption
                $db->where('password', $password);
            }
            $result = $db->get('admin');

            if (!empty($result)) {
                if($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if(!password_verify($password, $result[0]['password']))
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00101"][$language] /* Invalid Login */, 'data' => $data);
                }

                if($result[0]['disabled'] == 1) {
                    // Return error if account is disabled
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00102"][$language] /* Your account is disabled. */, 'data' => '');
                }
                //get Master Admin
                $db->where('name','Master Admin');
                $masterAdminRoleId = $db->getValue('roles','id');

                $id = $result[0]['id'];
                $role_id = $result[0]["role_id"];
                $admin['withdrawalRecordNotification'] = $result[0]['withdrawal_record_notification'];
                // Join the permissions table
                $db->where('a.site', 'Admin');
                if($role_id == $masterAdminRoleId){
                    $db->where('a.master_disabled', 0);
                }else{
                    $db->where('a.disabled', 0);
                }
                $db->where('a.type', 'Page', '!=');
                if ($result[0]["role_id"] != 1 && $result[0]["role_id"] != $masterAdminRoleId) {
                    $db->where('b.disabled', 0);
                    $db->where('b.role_id', $result[0]['role_id']);
                    $db->join('roles_permission b', 'b.permission_id=a.id', 'LEFT');
                }

                $db->orderBy('level', "asc");
                $res = $db->get('permissions a', null, 'a.id, a.name, a.type, a.parent_id, a.file_path, a.priority, a.icon_class_name, a.translation_code, a.reference_id,a.reference_table, a.last_line');
                foreach ($res as $array) {

                    // For frontend, if permission has lastLine, add a line below as separator
                    if ($array['last_line']){
                        $array['lastLine']=true;
                    }

                    if($array["reference_table"]){
                        switch($array["reference_table"]){
                            case "mlm_bonus":
                                $db->where('disabled',0);
                                $db->where('id',$array["reference_id"]);
                                $res2 = $db->getONE('mlm_bonus',"id");
                                // $data['test'] = $res2;
                                if($res2){
                                    if (!empty($array["translation_code"])){
                                        $array["name"] = $translations[$array["translation_code"]][$language];
                                    }
                                    $data['permissions'][] = $array;
                                }
                                break;
                            case "credit":
                                $db->where('id', $array["reference_id"]);
                                $res2 = $db->getONE('credit',"admin_translation_code");
                                if($res2){
                                    
                                    $array["name"] = $translations[$res2["admin_translation_code"]][$language];
                                    $data['permissions'][] = $array;
                                }

                                break;
                            default:
                                $data['permissions'][] = $array;
                                
                                break;
                        }
                    }else{
                        if (!empty($array["translation_code"])){
                            $array["name"] = $translations[$array["translation_code"]][$language];
                        }
                        $data['permissions'][] = $array;
                    }
                    unset($array['lastLine']);
                }

                unset($array);

                $sessionID = md5($result[0]['username'] . time());

                $fields = array('session_id', 'last_login', 'updated_at');
                $values = array($sessionID, date("Y-m-d H:i:s"), date("Y-m-d H:i:s"));

                $db->where('id', $id);
                $db->update('admin', array_combine($fields, $values));

                //Insert Session ID
                User::insertSessionData($id,$sessionID,$dateTime);

                if($role_id != $masterAdminRoleId){
                    // This is to get the Pages from the permissions table
                    $ids = $db->subQuery();
                    $ids->where('disabled', 0);
                    $ids->get('roles_permission', null, 'permission_id');

                    $db->where('id', $ids, 'in');
                    $db->where('disabled', 0);
                }else{
                    $db->where('master_disabled', 0);
                }
                $db->where('type', 'Page');
                $db->where('site', 'Admin');
                $pageResults = $db->get('permissions');
                foreach ($pageResults as $array) {
                    $data['pages'][] = $array;
                }

                // This is to get the hidden submenu from the permissions table
                $db->where('type', 'Hidden');
                $db->where('site', 'Admin');
                if($role_id == $masterAdminRoleId){
                    $db->where('master_disabled', 0);
                }else{
                    $db->where('disabled', 0);
                }
                $hiddenResults = $db->get('permissions');
                foreach ($hiddenResults as $array){
                    $data['hidden'][] = $array;
                }

                $admin['userID']                = $id;
                $admin['username']              = $result[0]['name'];
                $admin['userEmail']             = $result[0]['email'];
                $admin['userRoleID']            = $result[0]['role_id'];
                
                /* handle agent login - START */ 
                // get role name
                $db->where('id', $result[0]['role_id']);
                $admin['userRoleName'] = $db->getValue('roles', 'name');
                // if role is agent, get agent clientID
                if($admin['userRoleName'] == "Agent"){
                    $db->where("admin_id", $id);
                    $agentID = $db->getValue("admin_agent", "leader_id");

                }else{
                    // set director as agent
                    $db->where("username", "director");
                    $agentID = $db->getValue("client", "id");
                }
                /* handle agent login - END (can improve in future :D )*/ 

                $admin['userAgentID']           = $agentID;
                $admin['sessionID']             = $sessionID;
                $admin['timeOutFlag']           = Setting::getAdminTimeOut();
                $admin['pagingCount']           = Setting::getAdminPageLimit();
                $admin['decimalPlaces']         = Setting::getSystemDecimalPlaces();

                $data['userDetails'] = $admin;

                // Get product list
                /*$productList = Product::getProductList();
                $data['productList'] = $productList['data'];*/

                // Get member status for filter
                $db->where('name','memberStatus');
                $res = $db->getValue('system_settings','value');

                $statusList = json_decode($res);
                $memberStatusList = array();
                foreach ($statusList as $status => $translationCode) {
                    $memberStatusList[$status] = $translations[$translationCode][$language];
                }

                $data['memberStatusList'] = $memberStatusList;

                $db->where('name', 'treasureRobotTypes');
                $robotType = $db->getOne('system_settings', 'value');
                $robotTypes = explode('#', $robotType['value']);
                $data['robotTypes'] = $robotTypes;

                // Get Pin Type
                $db->where('name','pinType');
                $res = $db->getValue('system_settings','value');

                $pinTypeList = array();
                foreach (json_decode($res) as $pinType => $translationCode) {
                    $pinTypeList[$pinType] = $translations[$translationCode][$language];
                }

                $data['pinTypeList'] = $pinTypeList;

                /* get user's inbox message */
                // $inboxSubQuery = $db->subQuery();
                // $inboxSubQuery->where("`type`", "support");
                // $inboxSubQuery->where("`status`", "Closed", "!=");
                // $inboxSubQuery->get("`mlm_ticket`", null, "`id`");
                // $db->where("`ticket_id`", $inboxSubQuery, "IN");
                // $db->where("`read`", 0);
                // $db->where("`sender_id`", $id, "!=");
                // $inboxUnreadMessage = $db->getValue("`mlm_ticket_details`", "COUNT(*)");
                // $data['inboxUnreadMessage'] = $inboxUnreadMessage;

				$inbox = Client::getInboxUnreadMessage($id,"Admin");
                $data['inboxUnreadMessage'] = $inbox["data"]["inboxUnreadMessage"];
                // $unread = Self::getWithdrawalUnreadCount($id);
                // $data['inboxUnreadMessage'] = $unread['data']['inboxUnreadMessage'];

                $countryParams = array("pagination" => "No");
                $resultCountryList = Country::getCountriesList($countryParams);
                $data['countryList'] = $resultCountryList['data']['countriesList'];

                // Custom Country List
                $deliveryCountryList = Country::getCustomCountryList(array('type' => 'delivery'));
                $data['deliveryCountryList'] = $deliveryCountryList['data']['countryList'];

                $db->where('disabled', 0);
                $availableCategory = $db->get('inv_category', null, 'id, name');

                foreach ($availableCategory as $value) {
                    $categoryIDAry[$value['id']] = $value['id'];
                }

                if($categoryIDAry) {
                    $db->where('module_id', $categoryIDAry, 'IN');
                    $db->where('language', $language);
                    $db->where('module', 'inv_category');
                    $db->where('type', 'name');
                    $categoryLang = $db->map('module_id')->get('inv_language', null, 'module_id, content');
                }

                foreach ($availableCategory as $value) {
                    $value['categoryDisplay'] = $categoryLang[$value['id']];

                    $categoryList[] = $value;
                }

                $data['categoryList'] = $categoryList;

                $db->where('type', 'Client');
                $db->where('activated', array(1,2),"IN");
                $db->where('disabled', 0);
                $memberCount = $db->getValue('client', 'count(*)');

                $data['memberCount'] = $memberCount;

                $db->where('name','Supplier');
                $db->where('site','Admin');
                $supplierRoleID = $db->getValue('roles','id');

                $db->where('role_id',$supplierRoleID);
                $data['supplierIDArr'] = $db->get('admin',null,'id,username');

                $db->groupBy('subject');
                $invTrxnSubRes = $db->get('inv_product_transaction',null,'subject');
                foreach ($invTrxnSubRes as $invTrxnSubRow) {
                    $invTrxnSubArr['value'] = $invTrxnSubRow['subject'];
                    $invTrxnSubArr['display'] = General::getTranslationByName($invTrxnSubRow['subject']);
                    $data['invTrxnSubArr'][] = $invTrxnSubArr;
                }

                //Get Language
                $db->where("disabled", 0);
                $availableLanguages = $db->get("languages", NULL, "id, language, language_code");

                foreach ($availableLanguages as $value) {
                    $row = array(
                        "languageType" => $value['language'],
                        "languageDisplay" => $translations[$value['language_code']][$language]
                    );

                    $languageList[] = $row;
                }

                $data['languageList'] = $languageList;

                $categoryTypeAry = array('package', 'product');
                foreach ($categoryTypeAry as $categoryRow) {
                    $category['type'] = $categoryRow;
                    $category['display'] = General::getTranslationByName($categoryRow);

                    $categoryType[] = $category;
                }

                $data['categoryType'] = $categoryType;
                
                $db->where('status', 'Active');
                $supplier = $db->get('inv_supplier',null,'id, name, code');

                foreach($supplier as $value){
                    $supplierDetail['id'] = $value['id'];
                    $supplierDetail['name'] = $value['name'];
                    $supplierDetail['code'] = $value['code'];
                    $supplierDetailAry[] = $supplierDetail;
                }

                $data['supplier'] = $supplierDetailAry;
                
                //Get Product Category List
                /*$db->groupBy('category');
                $productCategoryRes = $db-> get('mlm_product',null,'category, id, name');
               

                foreach ($productCategoryRes as $productCategoryRow) {
                    $productCategoryArr['value'] = $productCategoryRow['category'];
                    $productCategoryArr['display'] = General::getTranslationByName($productCategoryRow['category']);
                    $data['productCategoryArr'][] = $productCategoryArr;
                }*/

                $data['creditList'] = $db->get('credit', null, 'name, type, code, admin_translation_code');

                //Get Rank List
                $db->orderBy('priority','ASC');
                $rankRes = $db->get('rank',null,'id,type,translation_code');
                foreach ($rankRes as $rankRow) {
                    $rankData['id'] = $rankRow['id'];
                    $rankData['display'] = $translations[$rankRow['translation_code']][$language];
                    $rankList[$rankRow['type']][] = $rankData;
                }
                $data['rankList'] = $rankList;

                // GET Activity Log Monthly Filter
                $activityTableRes = $db->rawQuery('SHOW TABLES LIKE "activity_log_%"');
                foreach ($activityTableRes as $tableDetail) {
                    foreach ($tableDetail as $activityTableRow) {
                        $logDate = str_replace("activity_log_", "", $activityTableRow);

                        $activityLogDateArr[$logDate]['value'] = $logDate;
                        $activityLogDateArr[$logDate]['Display'] = date("Y F",strtotime($logDate."01"));
                    }
                }
                krsort($activityLogDateArr);
                $data['activityLogDateArr'] = $activityLogDateArr;

                $adminRoleList = Setting::$systemSetting['InvEditableRoles'];
                $adminRolesListAry = explode("#", $adminRoleList);

                $db->where('id', $result[0]['id']);
                $db->where('role_id', $adminRolesListAry, 'IN');
                $getUserID = $db->getValue('admin','id');

                $data['invEditable'] = $getUserID ? '1':'0';

                $db->where("type", "Transaction Type");
                $db->where("disabled", "0");
                $typeRes = $db->get("type_mapping", null, "name, translation_code");

                foreach($typeRes as &$typeRow) {
                    $typeRow["display"] = $translations[$typeRow["translation_code"]][$language]; 
                    $typeList[] = $typeRow;
                }
                $data["transactionTypeList"] = $typeList;

                $db->where('name', 'nicepaySetting');
                $nicepaySetting = $db->getOne('system_settings', 'value, type, description');
                $nicepayBankAry = json_decode($nicepaySetting['description'], true);
                foreach ($nicepayBankAry as $npBankCode => $npBankName) {
                    $tempBank['code'] = $npBankCode;
                    $tempBank['name'] = $npBankName;
                    $data["paymentGatewayBankAry"][] = $tempBank;
                }

                $db->groupBy('status');
                $paymentGatewayStatusAry = $db->get('mlm_pending_payment', NULL, 'status');
                foreach ($paymentGatewayStatusAry as $statusValue) {
                    $tempStatus['value'] = $statusValue['status'];
                    $tempStatus['display'] = General::getTranslationByName("PG ".$statusValue['status']);
                    $data["paymentGatewayStatusAry"][] = $tempStatus;
                }                

                $promoCodeSystemSetting = Admin::promoCodeSystemSetting();
                $data['promoCode']['typePurpose'] = $promoCodeSystemSetting['typePurposeAry'];
                $data['promoCode']['status'] = $promoCodeSystemSetting['statusAry'];

                return array('status' => 'ok', 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }
            else
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00103"][$language] /* Invalid Login */, 'data' => "");
        }

        public function getAdminList($params) {
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData      = $params['inputData'];
            $sortData       = $params['sortData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $roleID = $db->subQuery();
            $roleID->where('name', 'Master Admin');
            $roleID->getOne('roles', "id");
            $db->where('role_id',$roleID);
            $masterAdminID = $db->get('admin',null,'id');
            foreach ($masterAdminID as $masterAdminIDKey => $masterAdminIDValue) {
                $masterAdminIDAry[] = $masterAdminIDValue['id'];
            }

            //Get the limit.
            $limit= General::getLimit($pageNumber);
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        case 'adminFullName':
                            $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            break;

                        case 'adminUsername':
                            $db->where('username', "%" . $dataValue . "%", 'LIKE');
                            break;
                        case 'adminEmail':
                            $db->where('email', "%" . $dataValue . "%", 'LIKE');
                            break;

                        case 'adminCreatedAt':
                            $db->where('created_at', "%" . $dataValue . "%", 'LIKE');
                            break;
                            
                        case 'adminDisabled':
                            if ($dataType == "like") {
                                $db->where('disabled', "%" . $dataValue . "%", 'LIKE');
                            }else{
                                $db->where('disabled', $dataValue);
                            }
                                
                            break;
                        
                            
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $sortOrder = "DESC";
            $sortFilter = 'adminCreatedAt';
            $sortField = 'created_at';

            // Means the sort params is there
            if (!empty($sortData)) { 
                if($sortData['field']) {
                    $sortFilter = $sortData['field'];

                    switch($sortFilter) {
                        case 'adminFullName':
                            $sortField = 'name';
                            break;
                        case 'adminUsername':
                            $sortField = 'username';
                            break;
                        case 'adminEmail':
                            $sortField = 'email';
                            break;
                        case 'adminCreatedAt':
                            $sortField = 'created_at';
                            break;  
                        case 'adminDisabled':
                            $sortField = 'disabled';
                            break;
                        case 'adminLastLogin':
                            $sortField = 'last_login    ';
                            break;                   
                    }
                }

                if($sortData['order'] == 'ASC')
                    $sortOrder = 'ASC';
            }

            $data['sortBy'] = array('field' => $sortFilter, 'order' => $sortOrder);
            
            $db->where('deleted', '0');
            if($masterAdminIDAry) $db->where("id", $masterAdminIDAry, "NOT IN");
            $copyDb = $db->copy();
            $db->orderBy($sortField, $sortOrder);
            $result = $db->get("admin", $limit, "(SELECT name FROM roles WHERE admin.role_id = roles.id) as roleName, id, username, name, email, disabled, created_at, last_login");
            
            // print_r($result);
            $totalRecord = $copyDb->getValue ("admin", "count(*)");

            if (!empty($result)) {

                $admin_ids = array_column($result, 'id');

                $db->where('user_id', $admin_ids, 'IN');
                $db->where('module', 'warehouse');
                $db->where('action', 'filter');
                $permission_res = $db->get('action_permission', null, 'user_id, reference_id');

                $warehouse_maps = $db->map('id')->get('warehouse', null, 'id, warehouse_location');

                foreach($permission_res as $value2){
                    $permission_map[$value2['user_id']][] = $warehouse_maps[$value2['reference_id']];
                }

                foreach($result as $value) {
                    $admin['id']           = $value['id'];
                    $admin['username']     = $value['username'];
                    $admin['name']         = $value['name'];
                    $admin['email']        = $value['email'];
                    $admin['roleName']     = $value['roleName'];
                    $admin['warehouse_location'] = $permission_map[$value['id']] ? implode(', ', $permission_map[$value['id']]) : "-";

                    $admin['disabled']     = ($value['disabled'] == 1)? 'Inactive':'Active';
                    $admin['createdAt']    = $value['created_at'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i:s', strtotime($value['created_at'])) : "-";
                    $admin['lastLogin']    = $value['last_login'] != '0000-00-00 00:00:00' ? date('Y-m-d H:i:s', strtotime($value['last_login'])) : "-";
            
                    $adminList[] = $admin;
                }
            
                $data['adminList']   = $adminList;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];
                
            
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }
            
            else
            {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function getAdminDetails($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00104"][$language] /* Please Select Admin */, 'data'=> '');

            $db->where('id', $id);
            $result = $db->getOne("admin", "id, username, name, email, disabled as status"); //, role_id as roleID

            if (empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");

            foreach ($result as $key => $value) {
                $adminDetail[$key] = $value;
            }

            $data['adminDetail'] = $adminDetail;

            $db->where('admin_id', $id);
            $leaderID = $db->getValue('admin_agent', 'leader_id');

            if($leaderID){
            	$db->where('id', $leaderID);
            	$leaderUsername = $db->getValue('client', 'username');
            	$data['adminDetail']['leaderUsername'] = $leaderUsername;
            }

            # get warehouse permission
            $db->where('user_id', $id);
            $db->where('active', '1');
            $db->where('module', 'warehouse');
            $db->where('action', 'filter');
            $warehousePermission = $db->getOne('action_permission');

            $db->where('id', $warehousePermission['reference_id']);
            $warehouseSelected = $db->getOne('warehouse');
            $data['adminWarehouse'] = $warehouseSelected;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addAdmins($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            //Check the stored password type.
            $passwordFlag = Setting::$systemSetting['passwordVerification'];
            $userId      = $db->userID;

            $email        = trim($params['email']);
            $fullName     = trim($params['fullName']);
            $username     = trim($params['username']);
            $password     = trim($params['password']);
            $leaderUsername = trim($params['leaderUsername']);
            $roleID       = trim($params['roleID']);
            $status       = trim($params['status']);
            $warehouse_id = ($params['warehouseID']);

            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00106"][$language] /* Please Enter Full Name */, 'data'=>"");

            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00107"][$language] /* Please Enter Username */, 'data'=>"");

            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00108"][$language] /* Please Enter Email */, 'data'=>"");

            if(strlen($roleID) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Please Select a Role */, 'data'=>"");

            $db->where("id", $roleID);
            $roleName = $db->getValue("roles", "name");


            if($leaderUsername){
                $db->where('username', $leaderUsername);
                $leaderData = $db->getOne("client", "id, password");
                if(empty($leaderData)){
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00594"][$language] /* Leader Not Found */, 'data'=>"");
                }
            }

            if(strlen($password) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00109"][$language] /* Please Enter Password */, 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00110"][$language] /* Please Choose a Status */, 'data'=>"");

            $db->where('email', $email);
            $result = $db->get('admin');
            if (!empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00111"][$language] /* Email Already Used */, 'data'=>"");

            $db->where('username', $username);
            $result = $db->get('admin');
            if (!empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00692"][$language] /* Username Already Used */, 'data'=>"");

            // Retrieve the encrypted password based on settings
            $password = Setting::getEncryptedPassword($password);

            $adminID = $db->getNewID();
            $fields = array("id", "email", "password", "username","name", "created_at", "role_id", "disabled", "updated_at");
            $values = array($adminID, $email, $password, $username, $fullName, date("Y-m-d H:i:s"), $roleID, $status, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            try{
                $result = $db->insert("admin", $arrayData);
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00112"][$language] /* Failed to add new user */, 'data'=>"");
            }

            if($leaderUsername){
                $insert = array(
                	"leader_id" => $leaderData['id'], 
                	"leader_username" => $leaderUsername, 
                	"admin_id" => $adminID, 
                	"created_at" => date("Y-m-d H:i:s")
                );
                $result2 = $db->insert("admin_agent", $insert);
            }

            if (!empty($warehouse_id))
            {
                $insertData = array(
                    'module'    => 'warehouse',
                    'action'    => 'filter',
                    'user_id'   => $result,
                    'granted_by'=> $userId,
                    'active'    => '1',
                    'reference_table' => 'warehouse',
                    'reference_id' => $warehouse_id,
                    'reference_column' => 'warehouse_location',
                	"created_at" => date("Y-m-d H:i:s")
                );
                $db->insert('action_permission', $insertData);
            }
            else
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01227"][$language] /* Please select a warehouse */, 'data'=>"");
            }

            if($roleName == 'Supplier'){
                $db->where('role_id',$roleID);
                $dataOut['supplierIDArr'] = $db->get('admin',null,'id,username');
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */, 'data'=>$dataOut);
        }

        public function editAdmins($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id       = trim($params['id']);
            $email    = trim($params['email']);
            $fullName = trim($params['fullName']);
            $username = trim($params['username']);
            $leaderUsername = trim($params['leaderUsername']);
            $roleID   = trim($params['roleID']);
            $status   = trim($params['status']);
            $password = trim($params['password']);
            $warehouse_id = ($params['warehouseID']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00113"][$language] /* Admin ID does not exist */, 'data'=>"");

            if(strlen($email) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00114"][$language] /* Please Enter Email */, 'data'=>"");

            if(strlen($fullName) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00115"][$language] /* Please Enter Full Name */, 'data'=>"");

            if(strlen($username) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00116"][$language] /* Please Enter Username */, 'data'=>"");

            // if(strlen($roleID) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Please Select a Role */, 'data'=>"");

            // $db->where('id', $roleID);
            // $result = $db->getOne('roles');
            // if (empty($result))
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations[""][$language]/* Invalid Admin Role */, 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00117"][$language] /* Please Select a Status */, 'data'=>"");

	    $db->where('username', $username);
	    $db->where('id',$id,'!=');
            $usernameResult = $db->get('admin');
            if (!empty($usernameResult)) return array('status' => "error", 'code' => 1, 'statusMsg' => "username already exists", 'data'=>"");
            
	    $db->where('id', $id);
            $result = $db->getOne('admin');

            if (!empty($result)) {
                $fields    = array("email", "username", "name", "role_id", "disabled", "updated_at");
                $values    = array($email, $username, $fullName, $roleID, $status, date("Y-m-d H:i:s"));

                if (strlen($password) != 0) {
                    array_push($fields, "password");
                    array_push($values, Setting::getEncryptedPassword($password));
                }

                if($leaderUsername){
                	$db->where('admin_id', $id);
                	$dbLeaderUsername = $db->getValue('admin_agent', 'id');

                	$db->where('username', $leaderUsername);
                	$leaderID = $db->getValue('client', 'id');

                	if(empty($dbLeaderUsername)){
                		$insert = array(
    		            	"leader_id" => $leaderID, 
    		            	"leader_username" => $leaderUsername, 
    		            	"admin_id" => $id, 
    		            	"created_at" => date("Y-m-d H:i:s")
    		            );
    		            $result2 = $db->insert("admin_agent", $insert);
    		            
                	}else{
                		$update = array(
    		            	"leader_id" => $leaderID, 
    		            	"leader_username" => $leaderUsername, 
    		            	"created_at" => date("Y-m-d H:i:s")
                		);
                		$db->where('admin_id', $id);
                		$result2 = $db->update("admin_agent", $update);
                	}
                    
    	        }

                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("admin", $arrayData);

                if($warehouse_id)
                {
                    # check got existing or not
                    $db->where('module', 'warehouse');
                    $db->where('action', 'filter');
                    $db->where('user_id', $id);
                    $db->where('active', '1');
                    $warehousePermission = $db->getOne('action_permission');

                    if($warehousePermission)
                    {
                        $updateData = array(
                            'reference_id' => $warehouse_id,
                            'updated_at'   => date("Y-m-d H:i:s"),
                        );
                        $db->where('module', 'warehouse');
                        $db->where('action', 'filter');
                        $db->where('user_id', $id);
                        $db->where('active', '1');
                        $db->update('action_permission', $updateData);
                    }
                    else
                    {
                        $insertData = array(
                            'module'    => 'warehouse',
                            'action'    => 'filter',
                            'user_id'   => $result,
                            'granted_by'=> $userId,
                            'active'    => '1',
                            'reference_table' => 'warehouse',
                            'reference_id' => $warehouse_id,
                            'reference_column' => 'warehouse_location',
                            "created_at" => date("Y-m-d H:i:s")
                        );
                        $db->insert('action_permission', $insertData);
                    }
                }
                else
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01227"][$language] /* Please select a warehouse */, 'data'=>"");
                }

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => "");

            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00118"][$language] /* Invalid Admin */, 'data'=>"");
            }
        }

        public function getMemberList($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $seeAll         = $params['seeAll'];

            $dateTime = date('Y-m-d H:i:s');

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit              = General::getLimit($pageNumber);
            $searchData         = $params['searchData'];
            $sortData           = $params['sortData'];
            
    		// $adminLeaderAry = Setting::getAdminLeaderAry();

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            // Means the search params is there
            $cpDb = $db->copy();
            $tempCopy = $db->copy();
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

                            $db->where('id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
                
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);
                    switch($dataName) {

                        case 'memberID':
                            $db->where('member_id', $dataValue);
                            break;

                        case 'email':
                             switch ($dataType) {
                                case 'like':
                                    $db->where("email", "%" . $dataValue . "%", "LIKE");
                                    break;
                                
                                case 'match':
                                    $db->where("email", $dataValue);
                                    break;
                            }
                            break;


                        case 'clientType':
                            if($dataType == "clientType"){
                                $db->where("type", $dataValue);
                            }
                            break;
   
                        case 'phone':
                            if($dataType == "text"){
                                $db->having('mobileNumber', "%" . $dataValue . "%", 'LIKE');
                            }
                            break;

                        case 'name':
                            if($dataType == "like"){
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            }else{
                                $db->where('name', $dataValue);
                            }
                            break;

                        case 'countryID':
                            $db->where('country_id', $dataValue); 
                            break;

                        case 'sponsor':
                            $sponsorID = $db->subQuery();
                            $sponsorID->where('username', $dataValue);
                            $sponsorID->getOne('client', "id");
                            $db->where('sponsor_id', $sponsorID);
                            break;

                        case 'status':
                            if ($dataValue == "suspended") {
                                $db->where("suspended", 1);
                            } elseif ($dataValue == "terminated") {
                                $db->where("`terminated`", 1);
                            } elseif ($dataValue == "disabled") {
                                $db->where("`disabled`", 1);
                            } else{
                                $db->where("activated",1);
                                $db->where("suspended",0);
                                $db->where("`terminated`",0);
                                $db->where("disabled",0);
                            }

                            break;
                        
                        case 'phone':
                            $db->where('phone', $dataValue);
                            break;

                        case 'sponsorID':
                            $sq = $db->subQuery();
                            $sq->where('member_id', $dataValue);
                            $sq->get('client', null, 'id');
                            $db->where('sponsor_id', $sq);
                            break;

                        case 'username':
                            $db->where('username', $dataValue);
                            break;

                        case 'date':
                            $db->where('updated_at', "%" . $dataValue . "%", 'LIKE');
                            break;

                        case 'rank':
                            $sq = $db->subQuery();
                            $tempCopy->where('name', 'rankDisplay');
                            $tempCopy->orderBy('created_at', 'DESC');
                            $tempCopy->groupBy("client_id");
                            $tempCopy->groupBy("type");
                            $tempRes = $tempCopy->get('client_rank', NULL,'client_id, MAX(id) as id, created_at');

                            foreach ($tempRes as $row) {
                                $maxID[] = $row['id'];
                            }

                            $sq->where("id", $maxID, "IN");
                            $sq->where("rank_id", $dataValue);
                            $sq->getValue('client_rank', 'client_id',null);

                            $db->where('id', $sq, "IN");
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            // if($adminLeaderAry){
            // 	$db->where('id', $adminLeaderAry, 'IN');
            // }
            //try this if face query performance issues
            //$sub = $db->subQuery();
            //$sub->where('ag.admin_id', $userID);
            //$sub->join('admin_agent ag' , "ts.trace_key like CONCAT('%', ag.leader_id, '%')", 'INNER' );
            //$sub->get('tree_sponsor ts',null,'ts.client_id');

            //if($sub) $db->where('id',$sub,'IN');
            
            $getCountryName = "(SELECT name FROM country WHERE country.id=country_id) AS country_name";
            $getSaleOrderDate = "(SELECT updated_at FROM sale_order WHERE client.id=client_id) AS update_at";
            // $getCountryName = "(SELECT amount FROM client WHERE client.id) AS country_name";
            $getSponsorUsername = "(SELECT username FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_username";
            $getSponsorMemberID = "(SELECT member_id FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_id";
            // $getSponsorMemberID = "(SELECT amount FROM credit_transaction INNER JOIN client WHERE credit_transaction.data = client.member_id) AS sponsor_id";
            // $getSponsorMemberID = "(SELECT * FROM credit_transaction INNER JOIN client WHERE credit_transaction.data = client.member_id) AS sponsor_id";
            $getSponsorName = "(SELECT name FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_name";

            ///$balance =Cash::getBalance($accountID, $creditType);

            // $db->where('type', "Client");
            $db->where('type', ['Client', 'Guest'], 'IN');
            if ($params['pageType'] == "lockAccount"){
                $db->where('disabled', "0");
            }
            $copyDb = $db->copy();
            // $totalRecords = $copyDb->getValue("client", "count(*)");
            // $totalRecords = $copyDb->get('client', $limit, 'id, member_id, name, username, '.$getCountryName.','.$getSponsorUsername.','.$getSponsorMemberID.','.$getSponsorName.', activated, disabled, suspended, freezed, 
            // `terminated`, last_login, last_login_ip, created_at, email,CONCAT(dial_code, phone) as mobileNumber, type');

            $totalRecords = count($copyDb->get('client ', null, 'name, CONCAT(dial_code, phone) as mobileNumber'));
            // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => $copyDb->getLastQuery());


            if($seeAll == "1"){
                $limit = array(0, $totalRecords);
            }

            $sortOrder = "ASC";
            $sortField = 'name';

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
            
            // $db->join('sale_order so','client.id= client_id' ,'LEFT');
            // $db->orderBy("created_at","DESC");
            $result = $db->get('client', $limit, 'id, member_id, name, username, '.$getCountryName.','.$getSponsorUsername.','.$getSponsorMemberID.','.$getSponsorName.', activated, disabled, suspended, freezed, 
                `terminated`, last_login, last_login_ip, created_at, email,CONCAT(dial_code, phone) as mobileNumber, type');   
            
                // $client['LastPurchaseDate'] = $result['LastPurchaseDate'];

 
                // return array('status' => "error", 'code' => 1, 'statusMsg' =>$translations["E00716"][$language], 'data' => $result);


                if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00105'][$language] /* No Results Found. */, 'data' => "");

            // first day of this month
            $firstDayOfTheMonth = date('Y-m-d H:i:s', strtotime('-1 second', strtotime(date('Y-m-01'))));

            $rankData = $db->map('id')->get('rank', null, 'id, name, translation_code');

            foreach ($result as $clientDetailsRow) {
                $clientIDAry[] = $clientDetailsRow['id'];
            }

            $db->where('client_id',$clientIDAry,"IN");
            $traceKeyArr = $db->map('client_id')->get('tree_sponsor',null,'client_id,trace_key');

            //$clientRankArr = Bonus::getClientRank("Bonus Tier", "", $dateTime, 'rankDisplay', "");

            $db->where('client_id', $clientIDAry, 'IN');
            $db->where('name','rankDisplay');
            $db->groupBy('client_id');
            $clientRankDetail = $db->get('client_rank', null, 'client_id, max(id) as maxid');

            $clientRankArr = array();
            foreach($clientRankDetail as $key => $value) {
                 $db->where('id', $value['maxid']);
                 $thisRankId = $db->getValue("client_rank", "rank_id");
                 $clientRankArr[$value['client_id']]['rank_id'] = $thisRankId;
            }

            $directorRankID = 4;
            foreach ($result as $row) {
                $sponsorTreeTrace = explode("/", $traceKeyArr[$row['id']]);
                krsort($sponsorTreeTrace);

                foreach ($sponsorTreeTrace as $uplineID) {
                    if($clientRankArr[$uplineID]['rank_id'] >= $directorRankID && ($uplineID != $row['id'])){
                        $nearDirector[$row['id']] = $uplineID;
                        break;
                    }
                }
            }

            if($nearDirector){
                $db->where('id',$nearDirector,"IN");
                $nearDirectorData = $db->map('id')->get('client',null,'id,name');
            }

            $db->where('client_id', $clientIDAry, "IN");
            $clientSalesRes = $db->map('client_id')->get('client_sales', null, 'id, client_id, activated, own_sales, group_sales, pgp_sales, active_leg');

            if($clientIDAry){
                $db->where('client_id', $clientIDAry, "IN");
                $db->where('address_type', 'billing');
                $city = $db->map('client_id')->get('address', null, 'client_id, city');

                foreach($clientIDAry as $clientIDRow){
                    $db->where('client_id', $clientIDRow);
                    $pvp[$clientIDRow] = $db->getValue('mlm_bonus_in', 'SUM(bonus_value)');

                    $db->where('trace_key','%'.$clientIDRow.'%','LIKE');
                    $db->where('client_id',$clientIDRow, '!=');
                    $downlineIDArr[$clientIDRow] = $db->map('client_id')->get('tree_placement',null,'client_id');

                    if ($downlineIDArr[$clientIDRow]) {
                        $db->where('client_id',$downlineIDArr[$clientIDRow],'IN');
                        $dvp[$clientIDRow] = $db->getValue('mlm_bonus_in','SUM(bonus_value)');
                    }
                }
            }

            if($city){
                $db->where('id', $city, "IN");
                $cityName = $db->map('id')->get('city',  NULL, 'id, name');
            }

            foreach($result as $value) {
                $client['clientID'] = $value['id'];
                $mainLeaderUsername = Tree::getMainLeaderUsername($client);
                $client['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                unset($rankID);    
                $rankID = $clientRankArr[$client['clientID']]['rank_id'];
                $client['rank'] = $translations[$rankData[$rankID]['translation_code']][$language]?:$rankData[$rankID]['name']?:'-';
               

                // $db->where('client_id', $client['clientID']);
                // $db->where('status', 'Paid');
                // $db->orderBy('updated_at', 'DESC');
                // $client['LastPurchaseDate'] = $db->getValue('sale_order', 'MAX(updated_at)');
                $db->where('client_id',$value['id']);
                $db->where('status', 'Paid');
                $db->orderBy('updated_at', 'DESC');
                $result = $db->getOne('sale_order', 'MAX(updated_at)');
                $client['LastPurchaseDate'] = $result['LastPurchaseDate'];

 
                // if($clientSalesRes[$value['id']]['activated']){
                //     $client['status'] = General::getTranslationByName('Active');
                // }else{
                //     $client['status'] = General::getTranslationByName('Disable');
                // }

                $statusesToInclude = array('Paid', 'Out For Delivery', 'Packed', 'Delivered', 'Order Processing');

                $db->where('client_id', $value['id']);
                $db->where('status', $statusesToInclude, 'IN');
                $db->orderBy('updated_at', 'DESC');
                $lastPurchase = $db->getOne('sale_order', 'updated_at');

                if($lastPurchase){
                    $lastPurchase = $lastPurchase['updated_at'];
                    $client['LastPurchaseDate'] = $lastPurchase;
                }else{
                    $client['LastPurchaseDate'] = '-';
                }

                if ($value['activated'] == 1) {
                    $client['status'] = $translations['A00372'][$language];
                } else {
                    $client['status'] = $translations['A00373'][$language];
                }

                if ($value['disabled'] == 1) {
                    $client['status'] = $translations['A00104'][$language];
                } elseif ($value['suspended'] == 1) {
                    $client['status'] = $translations['A00156'][$language];
                } elseif ($value['freezed'] == 1) {
                    $client['status'] = $translations['A00176'][$language];
                } elseif ($value['terminated'] == 1) {
                    $client['status'] = $translations['A01131'][$language];
                }
                // $client['balance'] = Cash::getBalance($value['id'], 'mfizCredit'); //balance in
                // $client['balance'] = Cash::getBalance($value['id'], 'bonusCredit');
                $client['balance'] = Cash::getBalance($value['id'], 'gotastyCredit');
                $client['balance_to_point'] = floor($client['balance']); // balance to credit point
                $client['point_to_balance'] = floor($client['balance_to_point'] * 0.005);
                $client['memberID'] = $value['member_id'];
                $client['name'] = $value['name'];
                $client['username'] = $value['username'];
                $client['email'] = $value['email'];
                $client['sponsorName'] = $value['sponsor_name'] ?: "-";
                $client['sponsorUsername'] = $value['sponsor_username'] ?: "-";
                $client['sponsorMemberID'] = $value['sponsor_id'] ?:"-";
                $client['country'] = $value['country_name'] ? $value['country_name'] : "-";
                $client['city']    = $cityName[$city[$value['id']]]? $cityName[$city[$value['id']]] : "-";

                if($value['type'] == 'Client'){
                    $userType = 'Customer';
                }else{
                    $userType = $value['type'];
                }
                $client['type'] = $userType;
                $client['phone'] = $value['mobileNumber'];



                $client['lastLogin'] = $value['last_login'] == "0000-00-00 00:00:00" ? "-" : date($dateTimeFormat,strtotime($value['last_login']));
                $client['lastLoginIp'] = $value['last_login_ip'] ?: "-";
                $client['createdAt'] = date($dateTimeFormat,strtotime($value['created_at']));
                // $client['pvp']  = Setting::setDecimal($clientSalesRes[$value['id']]['own_sales']);
                $client['pvp'] = $pvp[$value['id']] ? Setting::setDecimal($pvp[$value['id']]) : Setting::setDecimal(0);
                $client['pgp']  = Setting::setDecimal(($clientSalesRes[$value['id']]['own_sales'] + $clientSalesRes[$value['id']]['pgp_sales']));
                // $client['dvp']  = Setting::setDecimal(($clientSalesRes[$value['id']]['own_sales'] + $clientSalesRes[$value['id']]['group_sales']));
                $client['dvp'] = $dvp[$value['id']] ? Setting::setDecimal($dvp[$value['id']]) : Setting::setDecimal(0);
                $client['activeLeg']  = $clientSalesRes[$value['id']]['active_leg'];
                $client['nearDirector'] = $nearDirectorData[$nearDirector[$value['id']]]?:"-";

                $clientList[] = $client;
                // return $clientList;
            }

            $data['memberList']  = $clientList;
            $data['totalPage']   = ceil($totalRecords/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord']   = $limit[1];
            $data['countryList'] = $db->get('country', null, 'id, name');
            // return $data;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getMainLeaderList() {

            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $db->where('name', "isLeader");
            $db->where('value', "mainLeader");
            $mainLeaderID = $db->get("client_setting", NULL, "client_id");

            foreach ($mainLeaderID as $key => $value) {

                $db->where('id', $value["client_id"]);
                $mainLeaderUsername = $db->get('client', NULL,'username');

                foreach ($mainLeaderUsername as $key1 => $value1) {
                    // $mainLeaderArray[$value["client_id"]] = $value1["username"];
                    $mainLeaderIDArray[] = $value["client_id"];
                }
            }

            return $mainLeaderIDArray;
        }

        public function getMemberDetails($params) {
            $db = MysqliDb::getInstance();

            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            
            $userID = $db->userID;
            $site = $db->userType;

            if($site == 'Member'){
                $clientID = $userID;
            }else{
                $clientID = trim($params['clientID']);
            }

            $db->where('id', $clientID);
            $member = $db->getOne("client", 'name, username, member_id, email, dial_code, phone, address, country_id, dob, weChat, whatsApp, state_id, activated, disabled, suspended, freezed, turnOffPopUpMemo, passport, identity_number, main_id, sponsor_id,`terminated`, `created_at`');

            $phoneNumber = $member['dial_code'] . $member['phone'];

            $db->where('client_id', $clientID);
            $detailInfo = $db->getOne('client_detail', 'gender, martial_status, num_of_child, child_age, tax_number');

            $db->where('id', $member['sponsor_id']);
            $sponsorMemberID = $db->getValue('client', 'id');

            $db->where('id', $member['country_id']);
            $memberCountryTranslation = $db->getOne('country', 'country_code,translation_code');

            $member['fullname'] = $member['name'];
            $member['username'] = $member['username'];
            $member['email'] = $member['email'];
            $member["dialingArea"] = $memberCountryTranslation["country_code"];
            $member['phoneNumber'] = $member['dial_code'].$member['phone'];
            $member['dob'] = $member['dob'];
            $member['gender'] = General::getTranslationByName($detailInfo['gender']);
            $member["countryID"] = $member["country_id"];
            $member['country'] = $translations[$memberCountryTranslation["translation_code"]][$language];
            $member['sponsorID'] = $sponsorMemberID;
            $member['joinedAt'] = $member['created_at'];

            $newestID = $db->subQuery();
            $newestID->where('client_id', $clientID);
            $newestID->groupBy('doc_type');
            $newestID->get('mlm_kyc', null, 'MAX(id)');
            $db->where('id', $newestID, 'IN');
            $db->where('client_id',$clientID);
            $checkKYCDetails = $db->map('doc_type')->get('mlm_kyc',null,'doc_type, id, status');

            $credit = 'bonusDef';
            $db->where('client_id', $userID);
            $db->where('type', $credit);
            $db->groupBy("group_id");
            $db->orderBy("created_at", "DESC");
            $db->orderBy("id", "DESC");
            $result = $db->get("credit_transaction", $limit, "client_id, subject, from_id, to_id, SUM(amount) AS amount, remark, batch_id, creator_id, creator_type, created_at, portfolio_id, belong_id, type, coin_rate, data");

            foreach($result as $value) {
                $transactionSubject             = $bonusPayoutSubjectAry[$value['subject']] ? $bonusPayoutSubjectAry[$value['subject']] : General::getTranslationByName($value['subject']);
                $transaction['created_at']      = General::formatDateTimeToString($value['created_at'], "d/m/Y H:i:s");

                $transacDay = date('d', strtotime($value['created_at']));

                if($transacDay > 1 && $transacDay <= 16){
                    $transaction['fromToDate'] = date('16/m/Y', strtotime($value['created_at']));
                }else if($transacDay == 1){
                    $transaction['fromToDate'] = date('01/m/Y', strtotime($value['created_at']));
                }else{
                    $transaction['fromToDate'] = date('01/m/Y', strtotime($value['created_at']." +1 month"));
                }

                $transaction['subject'] = $transactionSubject ? $transactionSubject : $value['subject'] ;   

                if($value['subject'] == "Transfer Out") {
                    $transaction['to_from']     = $batchUsername[$value['batch_id']]["Transfer In"] ? $batchUsername[$value['batch_id']]["Transfer In"] : "-";
                }
                else if($value['subject'] == "Transfer In") {
                    $transaction['to_from']     = $batchUsername[$value['batch_id']]["Transfer Out"] ? $batchUsername[$value['batch_id']]["Transfer Out"] : "-";
                }
                else if($value['subject'] == "Convert Credit") {
                    $transaction['to_from']     = $belongCreditType[$value['belong_id']][$value['subject']] ? $belongCreditType[$value['belong_id']][$value['subject']] : "-";
                }else if($value['subject'] == "Bonus Package Reentry") {
                    $transaction['to_from'] = $bonusReentryPortfolio[$value["portfolio_id"]] ? $bonusReentryPortfolio[$value["portfolio_id"]] : "-";
                }
                else if($value['from_id'] == "9")
                    $transaction['to_from']     = $translations["B00224"][$language];
                else
                    $transaction['to_from']     = "-";

                $dateTimeStr = $value['created_at'];
                $dateTimeAry = explode(' ', $dateTimeStr);
                $dateAry = explode('-', $dateTimeAry[0]);
                $timeAry = explode(':', $dateTimeAry[1]);

                $startTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':00';
                $endTimeStr = $dateAry[0].'-'.$dateAry[1].'-'.$dateAry[2].' '.$timeAry[0].':'.$timeAry[1].':59';

                $db->where("name", $creditType);
                $db->orWhere("type", $creditType);
                $decimal = $db->getValue("credit", "dcm");

                $db->where('created_on', $startTimeStr, '>=');
                $db->where('created_on', $endTimeStr, '<=');
                $db->where('type', $creditType);
                $currentRate = $db->getValue('mlm_coin_rate', 'rate');
                if(!$currentRate) $currentRate = '-';
                if($value['from_id'] >= "1000000") {
                    $transaction['credit_in'] = "-";
                    $transaction['credit_out'] = Setting::setDecimal($value['amount'], $creditType);
                    $transaction['coin_rate'] = $value['coin_rate']==0 ? "-" : $value['coin_rate'];
                    $transaction['balance'] = Setting::setDecimal($currentBalance, $creditType);
                    $currentBalance += Setting::setDecimal($value['amount'],$creditType);
                }
                else {
                    $transaction['credit_in'] = Setting::setDecimal($value['amount'], $creditType);
                    $transaction['credit_out'] = "-";
                    $transaction['coin_rate'] = $value['coin_rate']==0 ? "-" : $value['coin_rate'];
                    $transaction['balance'] = Setting::setDecimal($currentBalance, $creditType);
                    $currentBalance -= Setting::setDecimal($value['amount'],$creditType);
                }

                if($value['from_id'] == "9" || $value['subject'] == "Package Cashback" || $value['creator_type'] == "System"){
                    $transaction['creator_id']  = General::getTranslationByName("System");
                }else{
                    $transaction['creator_id']  = $usernameList[$value['creator_type']][$value['creator_id']];
                }

                $transaction['remark']      = $value['remark'] ? $value['remark'] : "-";

                if($portfolio == 1) $transaction["portfolio_id"] = $value["portfolio_id"];

                $transaction['maxCapMultiplier'] = ($value["data"] ? $value["data"] : 0.00);

                $transactionList[] = $transaction;
                unset($transaction);
            }

            foreach($checkKYCDetails as $value){
                if($value['status'] == 'Waiting Approval'){
                    $waitingID[] = $value['id'];
                }
            }

            if($waitingID){
                $db->where('kyc_id', $waitingID, 'IN');
                $db->where('name', 'Image Name 1');
                $getKYCImageName = $db->map('kyc_id')->get('mlm_kyc_detail', null, 'kyc_id, value');
            }

            $db->where('email_verified','1');
            $db->where('client_id',$clientID);
            $checkEmailValidate = $db->has('client_detail');

            $member['emailVerify'] = $checkEmailValidate ? 1 : 0;

            $kyc = array('IDVerification','BankAccountCover','NPWPVerification');

            if(!empty($checkEmailValidate)){
                foreach($checkKYCDetails as $key=>$value){
                    $key = str_replace(" ","",$key);

                    $member[$key] = $value['status'];
                    if($value['status'] == 'Waiting Approval'){
                        $member[$key.'ImageName'] = $getKYCImageName[$value['id']];
                    }
                    unset($kyc[array_search($key, $kyc)]);
                }

                if(!empty($kyc)){
                    foreach($kyc as $remaining){
                        $member[$remaining] = 0;
                    }
                }
            }else{
                foreach($kyc as $key){
                    $member[$key] = 0;
                }
            }

            /*$countryParams = array('pagination' => 'No');
            $countryList = Country::getCountriesList($countryParams);
            if($countryList['status'] == 'ok')
                $data['countryList'] = $countryList['data']['countriesList'];

            $resultStateList = Country::getState();
            $data['stateList'] = $resultStateList;

            foreach ($data['countryList'] as $key => $countryValue) {
                 
                    if($countryValue["id"]==$member["country_id"]){
                        $member["countryDisplay"] = $countryValue["display"];
                        break;
                    }

            }*/   

            $data['member'] = $member;
            if(empty($member))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations['B00106'][$language] /* No Results Found. */, 'data' => "");
            $memberDetails = Client::getCustomerServiceMemberDetails($clientID);
            $data['memberDetails'] = $memberDetails['data']['memberDetails'];

            // $db->where('client_id', $clientID);
            // $detailInfo = $db->getOne('client_detail', 'gender, martial_status, num_of_child, tax_number');

            $db->where('client_id', $clientID);
            $db->where('status', 'Active');
            $db->orderBy('created_at', 'DESC');
            $bankRes = $db->getOne('mlm_client_bank', 'bank_id, account_no, branch, account_holder, bank_city');

            // credentials
            $credentials['fullName'] = $member['name'];
            $credentials['gender'] = General::getTranslationByName($detailInfo['gender']);
            $credentials['email'] = $member['email'];
            $credentials['phone'] = $member['phone'];
            $credentials['countryID'] = $member['country_id'];
            $credentials['passport'] = $member['passport'];
            $credentials['identityNumber'] = $member['identity_number'];
            $credentials['dob'] = $member['dob'];

            // additionalInfo
            $additionalInfo['martialStatus'] = $detailInfo['martial_status'];
            $additionalInfo['childNumber'] = $detailInfo['num_of_child'];
            
            $additionalInfo['childAge'] = $detailInfo['child_age'];
            if(empty($detailInfo['child_age']) && $detailInfo['child_age'] !="0")
                $additionalInfo['childAge'] = '-';
            
            $additionalInfo['taxNumber'] = $detailInfo['tax_number'];            

            $childAgeOption = explode('#', Setting::$systemSetting['childAgeOption']);
            foreach ($childAgeOption as $childAgeValue) {
                $childAgeData['value'] = $childAgeValue;

                if(is_numeric($childAgeValue)){
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00481'][$language])/*%%childAgeValue%% years old and above*/;
                }else{
                    $childAgeData['display'] = str_replace("%%childAgeValue%%", $childAgeValue, $translations['B00482'][$language])/*%%childAgeValue%% years old*/;
                }
                $childAgeOptionArr[] = $childAgeData;
            }

             $additionalInfo['childAgeOption'] = $childAgeOptionArr;

            if($member['activated']){
                $member['status'] = 'active';
            }
            if($member['suspended']){
                $member['status'] = 'suspended';
            }else if($member['freezed']){
                $member['status'] = 'freezed';
            }else if($member['terminated']){
                $member['status'] = 'terminated';
            }

            $additionalInfo['status'] = $member['status'];
            $additionalInfo['memberID'] = $member['member_id'];
            $additionalInfo['sponsorID'] = $sponsorMemberID;

            // bankInfo
            $bankInfo['bankID'] = $bankRes['bank_id'];
            $bankInfo['accountNo'] = $bankRes['account_no'];
            $bankInfo['branch'] = $bankRes['branch'];
            $bankInfo['accountHolder'] = $bankRes['account_holder'];
            $bankInfo['bankCity'] = $bankRes['bank_city'];

            $db->where('disabled', '0');
            $db->where('address_type', 'billing');
            $db->where('client_id', $clientID);
            $billingRes = $db->getOne('address', 'name, email, phone, address, state_id, district_id, sub_district_id, post_code, city, remarks, country_id');

            $billingInfo['name'] = $billingRes['name'];
            $billingInfo['email'] = $billingRes['email'];
            $billingInfo['phone'] = $billingRes['phone'];
            $billingInfo['address'] = $billingRes['address'];
            $billingInfo['remarks'] = $billingRes['remarks'];
            $billingInfo['countryId'] = $billingRes['country_id'];

            $db->where('disabled', '0');
            $db->where('client_id', $clientID);
            $db->orderBy('created_at', 'DESC');
            $deliveryRes = $db->getOne('address', 'client_id, name, email, phone, address, state_id, district_id, sub_district_id, post_code, city, remarks, country_id');

            $deliveryInfo['name'] = $deliveryRes['name'];
            $deliveryInfo['email'] = $deliveryRes['email'];
            $deliveryInfo['phone'] = $deliveryRes['phone'];
            $deliveryInfo['address'] = $deliveryRes['address'];
            $deliveryInfo['remarks'] = $deliveryRes['remarks'];
            $deliveryInfo['country_id'] = $deliveryRes['country_id'];

            $db->where("id", array($billingInfo["countryId"]?:"", $deliveryInfo["country_id"]?:"", $credentials["countryID"]?:""), "IN");
            $countryCode = $db->map("id")->get("country", null, "id, country_code");

            $db->where("id", array($billingRes["district_id"]?:"", $deliveryRes["district_id"]?:"",), "IN");
            $countyDisplay = $db->map("id")->get("county", null, "id,name,translation_code");

            $db->where("id", array($billingRes["sub_district_id"]?:"", $deliveryRes["sub_district_id"]?:""), "IN");
            $subCountyDisplay = $db->map("id")->get("sub_county", null, "id,name,translation_code");

            $db->where("id", array($billingRes["post_code"]?:"", $deliveryRes["post_code"]?:""), "IN");
            $zipCodeDisplay = $db->map("id")->get("zip_code", null, "id,name,translation_code");

            $db->where("id", array($billingRes["city"]?:"", $deliveryRes["city"]?:""), "IN");
            $cityDisplay = $db->map("id")->get("city", null, "id,name,translation_code");

            $db->where("id", array($billingRes["state_id"]?:"", $deliveryRes["state_id"]?:""), "IN");
            $stateDisplay = $db->map("id")->get("state", null, "id,name,translation_code");

            $billingInfo['dialingArea'] = $countryCode[$billingRes['country_id']];
            $deliveryInfo['dialingArea'] = $countryCode[$deliveryRes['country_id']];
            $credentials['dialingArea'] = $countryCode[$member['country_id']];

            $billingInfo["districtID"] = $billingRes["district_id"];
            $billingInfo["district"] = $translations[$countyDisplay[$billingRes["district_id"]]["translation_code"]][$language] ? $translations[$countyDisplay[$billingRes["district_id"]]["translation_code"]][$language] : $countyDisplay[$billingRes["district_id"]]["name"];

            $billingInfo["subDistrictID"] = $billingRes["sub_district_id"];
            $billingInfo["subDistrict"] = $translations[$subCountyDisplay[$billingRes["sub_district_id"]]["translation_code"]][$language] ? $translations[$subCountyDisplay[$billingRes["sub_district_id"]]["translation_code"]][$language] : $subCountyDisplay[$billingRes["sub_district_id"]]["name"];

            $billingInfo["postCodeID"] = $billingRes["post_code"];
            $billingInfo["postCode"] = $translations[$zipCodeDisplay[$billingRes["post_code"]]["translation_code"]][$language] ? $translations[$zipCodeDisplay[$billingRes["post_code"]]["translation_code"]][$language] : $zipCodeDisplay[$billingRes["post_code"]]["name"];

            $billingInfo["cityID"] = $billingRes["city"];
            $billingInfo["city"] = $translations[$cityDisplay[$billingRes["city"]]["translation_code"]][$language] ? $translations[$cityDisplay[$billingRes["city"]]["translation_code"]][$language] : $cityDisplay[$billingRes["city"]]["name"];

            $billingInfo["stateID"] = $billingRes["state_id"];
            $billingInfo["state"] = $translations[$stateDisplay[$billingRes["state_id"]]["translation_code"]][$language] ? $translations[$stateDisplay[$billingRes["state_id"]]["translation_code"]][$language] : $stateDisplay[$billingRes["state_id"]]["name"];

            $deliveryInfo["districtID"] = $deliveryRes["district_id"];
            $deliveryInfo["district"] = $translations[$countyDisplay[$deliveryRes["district_id"]]["translation_code"]][$language] ? $translations[$countyDisplay[$deliveryRes["district_id"]]["translation_code"]][$language] : $countyDisplay[$deliveryRes["district_id"]]["name"];

            $deliveryInfo["subDistrictID"] = $deliveryRes["sub_district_id"];
            $deliveryInfo["sub_district"] = $translations[$subCountyDisplay[$deliveryRes["sub_district_id"]]["translation_code"]][$language] ? $translations[$subCountyDisplay[$deliveryRes["sub_district_id"]]["translation_code"]][$language] : $subCountyDisplay[$deliveryRes["sub_district_id"]]["name"];

            $deliveryInfo["post_code"] = $deliveryRes["post_code"];
            $deliveryInfo["post_code"] = $translations[$zipCodeDisplay[$deliveryRes["post_code"]]["translation_code"]][$language] ? $translations[$zipCodeDisplay[$deliveryRes["post_code"]]["translation_code"]][$language] : $zipCodeDisplay[$deliveryRes["post_code"]]["name"];

            $deliveryInfo["cityID"] = $deliveryRes["city"];
            $deliveryInfo["city"] = $translations[$cityDisplay[$deliveryRes["city"]]["translation_code"]][$language] ? $translations[$cityDisplay[$deliveryRes["city"]]["translation_code"]][$language] : $cityDisplay[$deliveryRes["city"]]["name"];

            $deliveryInfo["stateID"] = $deliveryRes["state_id"];
            $deliveryInfo["state"] = $translations[$stateDisplay[$deliveryRes["state_id"]]["translation_code"]][$language] ? $translations[$stateDisplay[$deliveryRes["state_id"]]["translation_code"]][$language] : $stateDisplay[$deliveryRes["state_id"]]["name"];
            $currentBalance = Cash::getBalance($userID, $credit, date("Y-m-d H:i:s", $date));
            $currentBalance = floor($currentBalance);

            $db->where('disabled', 0);
            $db->where('client_id', $clientID);
            $shippingAddressList = $db->get('address', null,'id, client_id, name, email, phone, address, address_2, state_id, district_id, sub_district_id, post_code, city, remarks, country_id, address_type');

            $db->where('status', 'Active');
            $countryList = $db->get('country',null,'name');

            $db->where('status', 'Active');
            $countryID = $db->getValue('country','id');

            $db->where('country_id', $countryID);
            $stateList = $db->get('state',null,'name');

            $db->where('sponsor_id', $phoneNumber);
            $getDownLineClient = $db->get('client',null,'id,name,username');

            foreach ($getDownLineClient as $key => $value){
                $db->where('client_id', $clientID);
                $db->where('from_id', $value['id']);
                $db->where('paid', 1);
                $getDownLineStatus = $db->getValue('mlm_bonus_direct_sponsor','paid');

                if($getDownLineStatus){
                    $downlineStatus = 1;
                }else{
                    $downlineStatus = 0;
                }

                $data['downLineList'][$key]['name'] = $value['name'];
                $data['downLineList'][$key]['phone'] = $value['username'];
                $data['downLineList'][$key]['status'] = $downlineStatus;
            }


            $data['credentials'] = $credentials;
            $data['shippingAddress'] = $shippingAddressList;
            $data['additionalInfo'] = $additionalInfo;
            $data['bankInfo'] = $bankInfo;
            $data['billingInfo'] = $billingInfo;
            $data['deliveryInfo'] = $deliveryInfo;
            $data['userPointBalance'] = $currentBalance;
            $data['transactionList'] = $transactionList;
            $data['countryID'] = $countryID;
            $data['stateList'] = $stateList;
            $data['identityType'] = $member['identity_number']?'nric':'passport';

            $db->where('status', 'Active');
            $bankListRes = $db->get('mlm_bank', null, 'country_id, (SELECT name FROM country where id = country_id) as countryName, id, name, translation_code');
            foreach ($bankListRes as $bankListRow) {
                $bankList[$bankListRow['countryName']][] = $bankListRow;
            }
            $data['bankList'] = $bankList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function editMemberDetails($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            //get max and min full name length
            $maxFName       = Setting::$systemSetting['maxFullnameLength'];
            $minFName       = Setting::$systemSetting['minFullnameLength'];
            $maxUName       = Setting::$systemSetting['maxUsernameLength'];
            $minUName       = Setting::$systemSetting['minUsernameLength'];
            $maxPass        = Setting::$systemSetting['maxPasswordLength'];
            $minPass        = Setting::$systemSetting['minPasswordLength'];
            $maxTPass       = Setting::$systemSetting['maxTransactionPasswordLength'];
            $minTPass       = Setting::$systemSetting['minTransactionPasswordLength'];

            $martialStatusArr = array("single","married","widowed","divorced","separated");
            $genderArr = array("male", "female");

            // $status         = trim($params['status']);
            $clientID           = $params['clientID'];
            $fullName           = trim($params['fullName']);
            $username           = trim($params['username']);
            $number             = trim($params['number']);
            $dialCode           = trim($params['dialCode']);
            $password           = trim($params['password']);
            $confirmPassword    = trim($params['confirmPassword']);
            $dateOfBirth        = trim($params['dob']);
            $address            = trim($params['address']);
            $email              = trim($params['email']);
            $addressArr         = $params['shipping'];

            if($phone == null || $phone == '')
            {
                $phone = $dialCode.$number;
            }

            // additional param
            // $martialStatus = trim($params['martialStatus']);
            // $childNumber   = trim($params['childNumber']);
            // $childAge      = $params['childAge'];
            // $taxNumber     = trim($params['taxNumber']);

            // bank param
            $bankInfo['clientID']     = trim($params['clientID']);
            $bankInfo['bankID']     = trim($params['bankID']);
            $bankInfo['accountNo']  = trim($params['accountNo']);
            $bankInfo['branch']     = trim($params['branch']);
            $bankInfo['bankCity']   = trim($params['bankCity']);
            $bankInfo['accountHolder'] = trim($params['accountHolder']);

            // billing addr param
            $billingInfo['addressType'] = "billing";
            $billingInfo['clientID']    = trim($params['clientID']);
            $billingInfo['fullname']  = trim($params['billingName']);
            // $billingInfo['first_name']  = trim($params['billingFirstName']);
            // $billingInfo['last_name']   = trim($params['billingLastName']);
            $billingInfo['dialingArea'] = trim($params['billingDialingArea']);
            $billingInfo['phone']       = trim($params['billingPhone']);
            $billingInfo['email']       = trim($params['billingEmail']);
            $billingInfo['address']     = trim($params['billingAddress']);
            $billingInfo['address_2']     = trim($params['billingAddress2']);
            $billingInfo['districtID'] = trim($params['billingDistrict']);
            $billingInfo['subDistrictID'] = trim($params['billingSubDistrict']);
            $billingInfo['cityID'] = trim($params['billingCity']);
            $billingInfo['postalCodeID'] = trim($params['billingPostalCode']);
            $billingInfo['stateID'] = trim($params['billingState']);
            $billingInfo['countryID']   = trim($params['billingCountryID']);

            $deliveryInfo['addressType'] = "delivery";
            $deliveryInfo['clientID']    = trim($params['clientID']);
            $deliveryInfo['fullname']  = trim($params['deliveryName']);
            // $deliveryInfo['first_name']  = trim($params['deliveryFirstName']);
            // $deliveryInfo['last_name']   = trim($params['deliveryLastName']);
            $deliveryInfo['dialingArea'] = trim($params['deliveryDialingArea']);
            $deliveryInfo['phone']       = trim($params['deliveryPhone']);
            $deliveryInfo['email']       = trim($params['deliveryEmail']);
            $deliveryInfo['address']     = trim($params['deliveryAddress']);
            $deliveryInfo['address2']     = trim($params['deliveryAddress2']);
            $deliveryInfo['districtID'] = trim($params['deliveryDistrict']);
            $deliveryInfo['subDistrictID'] = trim($params['deliverySubDistrict']);
            $deliveryInfo['cityID'] = trim($params['deliveryCity']);
            $deliveryInfo['postalCodeID'] = trim($params['deliveryPostalCode']);
            $deliveryInfo['stateID'] = trim($params['deliveryState']);
            $deliveryInfo['countryID']   = trim($params['deliveryCountryID']);

            //checking client ID
            if(empty($clientID))
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Client not found', 'data' => '');

            // //checking KYC validate
            // // Checking for email verification
            // $db->where('email_verified','1');
            // $db->where('client_id',$clientID);
            // $checkEmailValidate = $db->has('client_detail');

            // Checking for Success verification
            // $newestID = $db->subQuery();
            // $newestID->where('client_id', $clientID);
            // $newestID->groupBy('doc_type');
            // $newestID->get('mlm_kyc', null, 'MAX(id)');
            // $db->where('id', $newestID, 'IN');
            // $db->where('status', 'Approved');
            // $getSuccessKYCRes = $db->map('doc_type')->get('mlm_kyc',null,'doc_type');

            // Get member original details
            // $db->where('id', $clientID);
            // $oriDetailRes = $db->getOne('client', 'name, email, country_id, identity_number, passport');
            // $db->where('client_id',$clientID);
            // $oriNPWPRes = $db->getOne('client_detail','tax_number');

            // ===== CREDENTIALS START =====
            // Validate fullName
            // return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Client not found', 'data' => $fullName);
            if(empty($fullName)) {
                $errorFieldArr[] = array(
                    'id'    => 'nameError',
                    'msg'   => $translations["E00296"][$language] /* Please insert full name */
                );
            } else {
                // if(in_array('ID Verification',$getSuccessKYCRes) && $fullName != $oriDetailRes['name']){
                //     $errorFieldArr[] = array(
                //         'id'    => 'nameError',
                //         'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
                //     );
                // } else 
                if (strlen($fullName) < $minFName || strlen($fullName) > $maxFName) {
                    $errorFieldArr[] = array(
                        'id'    => 'nameError',
                        'msg'   => $translations["E00297"][$language] /* Full name cannot be less than  */ . $minFName . $translations["E00298"][$language] /*  or more than  */ . $maxFName . '.'
                    );
                }
            }

            // Validate Gender
            // if(empty($gender) || (!in_array($gender, $genderArr))){
            //     $errorFieldArr[] = array(
            //         'id' => 'genderError',
            //         'msg' => $translations["E00766"][$language] /* Invalid gender */
            //     );
            // } 

            // Valid email
            // if (empty($email)) {
            //     $errorFieldArr[] = array(
            //         'id' => 'emailError',
            //         'msg' => $translations["E00318"][$language] /* Please fill in email */
            //     );
            // }
             if(!empty($email)) {
                if ($email) {
                    // if($checkEmailValidate && $email != $oriDetailRes['email']){
                    //     $errorFieldArr[] = array(
                    //         'id' => 'emailError',
                    //         'msg' => $translations["E01107"][$language] /* Email had been validated. Unable to edit. */
                    //     );
                    // }else 
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorFieldArr[] = array(
                            'id' => 'emailError',
                            'msg' => $translations["E00319"][$language] /* Invalid email format. */
                        );
                    }else{
                        $db->where('email', $email);
                        $isOccupied = $db->getOne('client', 'id');
                        if ($isOccupied && $isOccupied['id'] != $clientID) {
                            $errorFieldArr[] = array(
                                'id'  => 'emailError',
                                'msg' => $translations['E00748'][$language] /* Email Already Used */
                            );
                        }
                    }
                }
            }

            if (!empty($fullName)) {
                // Regular expression pattern to match only alphabets, spaces, and hyphens
                $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';

                if (preg_match($pattern, $fullName)) {
                    // The full name is valid (no special characters)
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Full name should not contain special characters.', 'data' => '');
                }
            } else {
                // The full name parameter is empty
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00657"][$language]/*Please Enter Full Name.*/, 'data' => '');
            }
            
           

            if (!empty($email)) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00121'][$language] /* Invalid email format. */, 'data' => '');
                } 
            } 



            // Validate country
            // if(in_array('ID Verification',$getSuccessKYCRes) && $country != $oriDetailRes['country_id']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'countryIDError',
            //             'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
            //         );
            // }else if(!is_numeric($country) || empty($country)) {
            //     $errorFieldArr[] = array(
            //         'id'  => "countryIDError",
            //         'msg' => $translations['E00947'][$language]
            //     );
            // }else{
            //     $db->where('id', $country);
            //     $dialingArea = $db->getValue('country', 'country_code');
            //     if(!$dialingArea){
            //         $errorFieldArr[] = array(
            //             'id'  => "countryIDError",
            //             'msg' => $translations['E00947'][$language]
            //         );
            //     }
            // }

            // Validate phone
            // if (empty($phone)) {
            //     $errorFieldArr[] = array(
            //         'id' => 'phoneError',
            //         'msg' => $translations["E00305"][$language] /* Please fill in phone number */
            //     );
            // } else {
            //     if (!preg_match('/^[0-9]*$/', $phone, $matches)) {
            //         $errorFieldArr[] = array(
            //             'id' => 'phoneError',
            //             'msg' => $translations["E00858"][$language] /* Only number is allowed */
            //         );
            //     }
            // }

            // Validate Date of Birth
            // if (!is_numeric($dateOfBirth)){
            //     $errorFieldArr[] = array(
            //         'id' => 'dateOfBirthError',
            //         'msg' => $translations["E00156"][$language] /* Invalid date. */
            //     );
            // }

            // if($dateOfBirth){
            //     // check Date of Birth, min 18 years old
            //     $ts1 = date("Y-m-d", $dateOfBirth); 
            //     $tempDob = date("Y-m-d", strtotime('-18 year', strtotime("now")));
            //     $ts2 = $tempDob;
            //     if($ts1 > $ts2){
            //         $errorFieldArr[] = array(
            //             'id' => 'dateOfBirthError',
            //             'msg' => $translations["E01053"][$language] /* You must be 18 and above to register. */
            //         );
            //     }    
            // }

            // Validate identity
            // if(in_array('ID Verification',$getSuccessKYCRes) && ($passport != $oriDetailRes['passport'] || $identityNumber != $oriDetailRes['identity_number'])){
            //     if($oriDetailRes['passport'] && $identityType != 'passport'){
            //         $errorFieldArr[] = array(
            //             'id'    => 'identityTypeError',
            //             'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
            //         );
            //     }else if($oriDetailRes['identity_number'] && $identityType != 'nric'){
            //         $errorFieldArr[] = array(
            //             'id'    => 'identityTypeError',
            //             'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
            //         );
            //     }
            //     $errorFieldArr[] = array(
            //         'id'    => 'identityNumberError',
            //         'msg'   => $translations["E01108"][$language] /* ID had been validated. Unable to edit */
            //     );
            // } else {
            // if($identityType == "nric"){
            //     if(empty($identityNumber)){
            //         $errorFieldArr[] = array(
            //             'id' => 'identityNumberError',
            //             'msg' => $translations["E01040"][$language] /* Please Insert Identity Number */
            //         );
            //     }
            // } else if ($identityType == "passport"){
            //     if(empty($passport)){
            //         $errorFieldArr[] = array(
            //             'id' => 'identityNumberError',
            //             'msg' => $translations["E01042"][$language] /* Please Insert Passport Number */
            //         );
            //     }
            // }else{
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00218"][$language], 'data' => "");
            // }
            
            // ===== CREDENTIALS END =====

            // ===== ADDITIONAL INFO START =====

            // Validate Marital status
            // if(empty($martialStatus) || (!in_array($martialStatus, $martialStatusArr))){
            //     $errorFieldArr[] = array(
            //         'id' => 'martialStatusError',
            //         'msg' => $translations["E01037"][$language] /* Please Select Marital Status */
            //     );
            // }

            // if(!is_numeric($childNumber) || $childNumber < 0){
            //     $errorFieldArr[] = array(
            //         'id' => 'childNumberError',
            //         'msg' => $translations["E01038"][$language] /* Please Insert Child Number */
            //     );
            // }

            // if($childNumber > 0){
            //     $childAgeOption = explode('#', Setting::$systemSetting['childAgeOption']);
            //     // childAge
            //     if(!is_array($childAge)){
            //         $errorFieldArr[] = array(
            //             'id' => 'childAgeError',
            //             'msg' => $translations["E01111"][$language] /* Invalid Age. */
            //         );
            //     }else if(count($childAge) != $childNumber){
            //         $errorFieldArr[] = array(
            //             'id' => 'childAgeError',
            //             'msg' => $translations["E01112"][$language] /* Total count of age not match. */
            //         );
            //     }else{
            //         foreach ($childAge as $childAgeRow) {
            //             if(!$childAgeOption[$childAgeRow]){
            //                 $errorFieldArr[] = array(
            //                     'id' => 'childAgeError',
            //                     'msg' => $translations["E01111"][$language] /* Invalid Age. */
            //                 );
            //                 break;
            //             }
            //         }
            //     }
            // }

            // if(empty($taxNumber)){
            //     // $errorFieldArr[] = array(
            //     //     'id' => 'taxNumberError',
            //     //     'msg' => $translations["E01039"][$language] /* Please Insert Tax Number */
            //     // );
            // }else if(in_array('NPWP Verification', $getSuccessKYCRes) && $taxNumber != $oriNPWPRes['tax_number']){
            //     $errorFieldArr[] = array(
            //         'id'    => 'taxNumberError',
            //         'msg'   => $translations["E01109"][$language] /* NPWP had been validated. Unable to edit */
            //     );
            // }
            // ===== ADDITIONAL INFO END =====

            // ===== BANK INFO START =====
            // $db->where('status', 'Active');
            // $db->where('client_id', $clientID);
            // $db->orderBy('created_at', 'DESC');
            // $curBankRes = $db->getOne('mlm_client_bank', 'bank_id, account_no, branch, bank_city, account_holder');

            // if(in_array('Bank Account Cover', $getSuccessKYCRes)){
            //     if($bankInfo['bankID'] != $curBankRes['bank_id']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankIDError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['accountNo'] != $curBankRes['account_no']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankAccError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['branch'] != $curBankRes['branch']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankBranchError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['bankCity'] != $curBankRes['bank_city']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankCityError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            //     if($bankInfo['accountHolder'] != $curBankRes['account_holder']){
            //         $errorFieldArr[] = array(
            //             'id'    => 'bankAccHolderError',
            //             'msg'   => $translations["E01110"][$language] /* Bank had been validated. Unable to edit */
            //         );
            //     }
            // }else 
            // if($curBankRes &&
            //     ( $bankInfo['bankID'] != $curBankRes['bank_id']
            //         || $bankInfo['accountNo'] != $curBankRes['account_no']
            //         || $bankInfo['branch'] != $curBankRes['branch']
            //         || $bankInfo['bankCity'] != $curBankRes['bank_city']
            //         || $bankInfo['accountHolder'] != $curBankRes['account_holder'] )
            // ){
            //     // add bank flag
            //     $addBankFlag = true;
            // }else if(!$curBankRes && $bankInfo['bankID']){
            //     $addBankFlag = true;
            // }

            // if($addBankFlag){
            //     $bankValidation = Client::addBankAccountDetailVerification($bankInfo);
            //     if(strtolower($bankValidation['status']) != 'ok'){
            //         return $bankValidation;
            //     }
            // }
            // ===== BANK INFO END =====

            // ===== BILLING INFO START =====
            // $db->where('disabled', '0');
            // $db->where('address_type', 'billing');
            // $db->where('client_id', $clientID);
            // $curBillingRes = $db->getOne('address', 'id, name, email, phone, address, state_id, district_id, sub_district_id, remarks, city_id, post_code_id, country_id');
            // // $billingInfo['dialingArea'] != $curBillingRes['']

            // if($curBillingRes &&
            //     ( $billingInfo['fullname'] != $curBillingRes['name']
            //         || $billingInfo['phone'] != $curBillingRes['phone']
            //         || $billingInfo['email'] != $curBillingRes['email']
            //         || $billingInfo['address'] != $curBillingRes['address']
            //         || $billingInfo['districtID'] != $curBillingRes['district_id']
            //         || $billingInfo['subDistrictID'] != $curBillingRes['sub_district_id']
            //         || $billingInfo['cityID'] != $curBillingRes['city_id']
            //         || $billingInfo['postalCodeID'] != $curBillingRes['post_code_id']
            //         || $billingInfo['stateID'] != $curBillingRes['state_id']
            //         || $billingInfo['countryID'] != $curBillingRes['country_id'] )
            // ){
            //     // add billing address flag
            //     $addBillingAddrFlag = true;

            // }else if(!$curBillingRes && $billingInfo['fullname']){
            //     $addBillingAddrFlag = true;
            // }

            // if($addBillingAddrFlag){
            //     $errorFieldArrBilling = Inventory::verifyAddress($billingInfo);
            //     if($errorFieldArrBilling){
            //         foreach ($errorFieldArrBilling as $key => &$value) {
            //             $value['id'] = $value['id']."Billing";
            //         }
            //         $data['field'] = $errorFieldArrBilling;
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            //     }
            // }
            // ===== BILLING INFO END =====

            // ===== DELIVERY INFO START =====


            // $db->where('disabled', '0');
            // $db->where('client_id', $clientID);
            // $curDeliveryRes = $db->get('address', 'id, name, email, phone, address, state_id, district_id, sub_district_id, remarks, city, post_code, country_id');
            // $deliveryInfo['dialingArea'] != $curDeliveryRes['']
            $dateTime = date('Y-m-d H:i:s');

            $db->where('disabled', '0');
            $db->where('client_id', $clientID);
            $getNonDisabledAddr = $db->getValue("address", 'id',null);
            // return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Full name should not contain special characters.', 'data' => $getNonDisabledAddr);

            $addressArr = self::filterAndRemoveDuplicateShippingAddresses($addressArr);

            if($addressArr){
                $disUser = array(
                    "disabled"               => 1,
                    "updated_at"         => $dateTime,
                );
                $db->where('disabled', '0');
                $db->where('client_id', $clientID);
                $disabledAddress = $db->update("address", $disUser);
            }
            

            if($getNonDisabledAddr){
                if($addressArr){
                    foreach($addressArr as $details)
                    {
                        if($details['address_type'] = 'shipping'){
                            $addressType = 1;
                        }else{
                            $addressType = 0;
                        }
    
                        $db->where('status', 'Active');
                        $db->where('name', $details['countryName']);
                        $countryID = $db->getValue("country", "id");
    
                        $db->where('disabled', 0);
                        $db->where('country_id', $countryID);
                        $db->where('name', $details['stateName']);
                        $stateID = $db->getValue("state", "id");

                        if (!$stateID) {
                           $updateUsr = array(
                                "disabled"               => 0,
                                "updated_at"         => $dateTime,
                            );
                
                            $db->where('disabled', '1');
                            $db->where('id', $getNonDisabledAddr, 'IN');
                            $disabledAddress = $db->update("address", $updateUsr);
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address State Required', 'data' => '');
                        }
    
                        if (!empty($details['name'])) {
                            // Regular expression pattern to match only alphabets, spaces, and hyphens
                            $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
            
                            if (preg_match($pattern, $details['name'])) {
                                // The full name is valid (no special characters)
                                $updateUsr = array(
                                    "disabled"               => 0,
                                    "updated_at"         => $dateTime,
                                );
                    
                                $db->where('disabled', '1');
                                $db->where('id', $getNonDisabledAddr, 'IN');
                                $disabledAddress = $db->update("address", $updateUsr);
                    
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Full name should not contain special characters.', 'data' => '');
                            }
                        } else {
                            // The full name parameter is empty
                            $updateUsr = array(
                                "disabled"               => 0,
                                "updated_at"         => $dateTime,
                            );
                            $db->where('disabled', '1');
                            $db->where('id', $getNonDisabledAddr, 'IN');
                            $disabledAddress = $db->update("address", $updateUsr);
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address name required', 'data' => '');
                        }


                        if (empty($details['address'])) {
                            
                            $updateUsr = array(
                                "disabled"               => 0,
                                "updated_at"         => $dateTime,
                            );
                
                            $db->where('disabled', '1');
                            $db->where('id', $getNonDisabledAddr, 'IN');
                            $disabledAddress = $db->update("address", $updateUsr);
                
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Line 1 Required', 'data' => '');
                            
                        } 

                        if (empty($details['address2'])) {
                            
                            $updateUsr = array(
                                "disabled"               => 0,
                                "updated_at"         => $dateTime,
                            );
                
                            $db->where('disabled', '1');
                            $db->where('id', $getNonDisabledAddr, 'IN');
                            $disabledAddress = $db->update("address", $updateUsr);
                
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Line 2 Required', 'data' => '');
                            
                        } 

                        if (empty($details['post_code'])) {
                            
                            $updateUsr = array(
                                "disabled"               => 0,
                                "updated_at"         => $dateTime,
                            );
                
                            $db->where('disabled', '1');
                            $db->where('id', $getNonDisabledAddr, 'IN');
                            $disabledAddress = $db->update("address", $updateUsr);
                
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Post Code Required', 'data' => '');
                        } 

                        if($details['post_code']){
                            $pattern = '/^[0-9]{5}$/'; // Malaysian postcode format (5 digits)
                            if (!preg_match($pattern, $details['post_code'])) {

                                $updateUsr = array(
                                    "disabled"               => 0,
                                    "updated_at"         => $dateTime,
                                );
                    
                                $db->where('disabled', '1');
                                $db->where('id', $getNonDisabledAddr, 'IN');
                                $disabledAddress = $db->update("address", $updateUsr);

                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Incorrect Address Post Code Format', 'data' => '');
                            } 

                            $countPostcode = preg_replace('/\D/', '', $details['post_code']);
    
                            if (!strlen($countPostcode) === 5) {
                                $updateUsr = array(
                                    "disabled"               => 0,
                                    "updated_at"         => $dateTime,
                                );
                    
                                $db->where('disabled', '1');
                                $db->where('id', $getNonDisabledAddr, 'IN');
                                $disabledAddress = $db->update("address", $updateUsr);
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Address Post Code Format', 'data' => '');
                            } 
                        }

                        if (empty($details['city'])) {
                            
                            $updateUsr = array(
                                "disabled"               => 0,
                                "updated_at"         => $dateTime,
                            );
                
                            $db->where('disabled', '1');
                            $db->where('id', $getNonDisabledAddr, 'IN');
                            $disabledAddress = $db->update("address", $updateUsr);
                
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address City Required', 'data' => '');
                            
                        } 

                        if (empty($details['phone'])) {
                            
                            $updateUsr = array(
                                "disabled"               => 0,
                                "updated_at"         => $dateTime,
                            );
                
                            $db->where('disabled', '1');
                            $db->where('id', $getNonDisabledAddr, 'IN');
                            $disabledAddress = $db->update("address", $updateUsr);
                
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Contact Required', 'data' => '');
                            
                        } 

                        if (!empty($details['email'])) {
                            if (!filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
                                $updateUsr = array(
                                    "disabled"               => 0,
                                    "updated_at"         => $dateTime,
                                );
                    
                                $db->where('disabled', '1');
                                $db->where('id', $getNonDisabledAddr, 'IN');
                                $disabledAddress = $db->update("address", $updateUsr);
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Address Email format.', 'data' => '');
                            } 
                        } 
    
                        if($details['phone']){
                            $phone = $details['phone'];
        
                            $mobileNumberCheck = General::mobileNumberInfo($details['phone'], "MY");
                            if($mobileNumberCheck['isValid'] != 1){
                                $updateUsr = array(
                                    "disabled"               => 0,
                                    "updated_at"         => $dateTime,
                                );
                    
                                $db->where('disabled', '1');
                                $db->where('id', $getNonDisabledAddr, 'IN');
                                $disabledAddress = $db->update("address", $updateUsr);
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Address Phone Format', 'data' => $mobileNumberCheck);
                            }
    
                            $validPhoneNumber = $mobileNumberCheck['phone'];
                        }
            
                            // insert new data
                            $insertData = array(
                                'type'              => $addressType,
                                'client_id'         => $clientID,
                                'name'              => $details['name'],
                                'email'             => $details['email'],
                                'phone'             => $validPhoneNumber,
                                'address'           => $details['address'],
                                'address_2'         => $details['address2'],
                                'district_id'       => $details['district_id'],
                                'sub_district_id'   => $details['sub_district_id'],
                                'post_code'         => $details['post_code'],
                                'city'              => $details['city'],
                                'state_id'          => $stateID,
                                'country_id'        => $countryID,
                                'address_type'      => $details['address_type'],
                                'remarks'           => $details['remarks'],
                                'created_at'        => $dateTime,
                                'disabled'          => 0,
                            );
                            $db->insert('address', $insertData);
                    }
                }
            }
            else{
                if($addressArr){
                    foreach($addressArr as $details)
                    {
                        if($details['address_type'] = 'shipping'){
                            $addressType = 1;
                        }else{
                            $addressType = 0;
                        }
    
                        $db->where('status', 'Active');
                        $db->where('name', $details['countryName']);
                        $countryID = $db->getValue("country", "id");
    
                        $db->where('disabled', 0);
                        $db->where('country_id', $countryID);
                        $db->where('name', $details['stateName']);
                        $stateID = $db->getValue("state", "id");


                        if (!$stateID) {
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address State Required', 'data' => '');
                        }
    
                        if (!empty($details['name'])) {
                            // Regular expression pattern to match only alphabets, spaces, and hyphens
                            $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
            
                            if (preg_match($pattern, $details['name'])) {
                                // The full name is valid (no special characters)
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Full name should not contain special characters.', 'data' => '');
                            }
                        } else {
                            // The full name parameter is empty
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address name required', 'data' => '');
                        }


                        if (empty($details['address'])) {
                        
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Line 1 Required', 'data' => '');
                        } 
                        if (empty($details['address2'])) {
                        
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Line 2 Required', 'data' => '');
                        } 

                        if (empty($details['post_code'])) {
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Post Code Required', 'data' => '');
                        } 

                        if($details['post_code']){
                            $pattern = '/^[0-9]{5}$/'; // Malaysian postcode format (5 digits)
                            if (!preg_match($pattern, $details['post_code'])) {
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Address Post Code Format', 'data' => '');
                            } 

                            $countPostcode = preg_replace('/\D/', '', $details['post_code']);
    
                            if (!strlen($countPostcode) === 5) {
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Address Post Code Format', 'data' => '');
                            } 
                        }

                        if (empty($details['city'])) {
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address City Required', 'data' => '');
                        } 

                        if (empty($details['phone'])) {
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Address Contact Required', 'data' => '');
                        } 
            
                        if (!empty($details['email'])) {
                            if (!filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Address Email format.', 'data' => '');
                            } 
                        } 
    
                        if($details['phone']){
                            $phone = $details['phone'];
        
                            $mobileNumberCheck = General::mobileNumberInfo($details['phone'], "MY");
                            if($mobileNumberCheck['isValid'] != 1){
                                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Address Phone Format', 'data' => $mobileNumberCheck);
                            }
                            $validPhoneNumber = $mobileNumberCheck['phone'];
                        }
            
                            // insert new data
                            $insertData = array(
                                'type'              => $addressType,
                                'client_id'         => $clientID,
                                'name'              => $details['name'],
                                'email'             => $details['email'],
                                'phone'             => $validPhoneNumber,
                                'address'           => $details['address'],
                                'address_2'         => $details['address2'],
                                'district_id'       => $details['district_id'],
                                'sub_district_id'   => $details['sub_district_id'],
                                'post_code'         => $details['post_code'],
                                'city'              => $details['city'],
                                'state_id'          => $stateID,
                                'country_id'        => $countryID,
                                'address_type'      => $details['address_type'],
                                'remarks'           => $details['remarks'],
                                'created_at'        => $dateTime,
                                'disabled'          => 0,
                            );
                            $db->insert('address', $insertData);
                    }
                }
            }
            

            // if($curDeliveryRes &&
            //     ( $deliveryInfo['fullname'] != $curDeliveryRes['name']
            //         || $deliveryInfo['phone'] != $curDeliveryRes['phone']
            //         || $deliveryInfo['email'] != $curDeliveryRes['email']
            //         || $deliveryInfo['address'] != $curDeliveryRes['address']
            //         || $deliveryInfo['districtID'] != $curDeliveryRes['district_id']
            //         || $deliveryInfo['subDistrictID'] != $curDeliveryRes['sub_district_id']
            //         || $deliveryInfo['cityID'] != $curDeliveryRes['city']
            //         || $deliveryInfo['postalCodeID'] != $curDeliveryRes['post_code']
            //         || $deliveryInfo['stateID'] != $curDeliveryRes['state_id']
            //         || $deliveryInfo['countryID'] != $curDeliveryRes['country_id'] )
            // ){
            //     // add billing address flag
            //     $addDeliveryAddrFlag = true;

            // }else if(!$curDeliveryRes && $deliveryInfo['fullname']){
            //     $addDeliveryAddrFlag = true;
            // }

            // if($addDeliveryAddrFlag){
            //     $errorFieldArrDelivery = Inventory::verifyAddress($deliveryInfo);
            //     if($errorFieldArrDelivery){
            //         foreach ($errorFieldArrDelivery as $key => &$value) {
            //             $value['id'] = $value['id']."Delivery";
            //         }
            //         $data['field'] = $errorFieldArrDelivery;
            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            //     }
            // }
            // ===== DELIVERY INFO END =====

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            
            // ===== VERIFICATION END =====

            // get old data
            $db->where("id", $clientID);
            $clientOldData = $db->getOne("client", "name, email, phone, address, country_id, disabled, suspended, freezed, `terminated`,turnOffPopUpMemo");

            // if (Cash::$creatorType == "Admin") {

                $updateData["name"] = $fullName;
                $updateData["email"] = $email;
                // $updateData["password"] = $password;
                // $updateData["dob"] = $dateOfBirth;
                // $updateData["address"] = $address;

               if($status == "active"){
                    $updateData["activated"] = 1;
                    $updateData["suspended"] = 0;
                    $updateData["freezed"] = 0;
                    $updateData["terminated"]  = 0;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;

                }elseif($status == "suspended"){
                    $updateData["suspended"] = 1;
                    $updateData["freezed"] = 0;
                    $updateData["terminated"]  = 0;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;

                }elseif($status == "freezed"){
                    $updateData["suspended"] = 0;
                    $updateData["freezed"] = 1;
                    $updateData["terminated"]  = 0;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;

                }elseif($status == "terminated"){
                    $updateData["suspended"] = 0;
                    $updateData["freezed"] = 0;
                    $updateData["terminated"]  = 1;
                    $updateData["disabled"] = 0;
                    $updateData["fail_login"] = 0;
                    $isTerminated = 1;
                }

                $db->where('id', $clientID);
                $updateResult = $db->update('client', $updateData);
            // }

            //Insert Terminate time for Rerun bonus module
            $db->where('client_id',$clientID);
            $db->where('name','terminatedAt');
            $terminateStgID = $db->getValue('client_setting','id');
            switch ($isTerminated) {
                case '1':
                    if(!$terminateStgID){
                        unset($insertData);
                        $insertData = array(
                            "name" => 'terminatedAt',
                            "value"=> $dateTime,
                            "client_id"=>$clientID
                        );
                        $db->insert('client_setting',$insertData);
                    }
                    break;
                
                case '0':
                    if($terminateStgID){
                        $db->where('id',$terminateStgID);
                        $db->delete('client_setting');
                    }
                    break;
            }

            $db->where('id', $clientID);
            $clientUsername = $db->getValue("client", "username");
            $userData = array("user" => $clientUsername);

            $db->where("client_id", $clientID);
            $clientOldData2 = $db->getOne("client_detail", "gender, martial_status, num_of_child, tax_number");

            if (Cash::$creatorType == "Admin") {

                unset($updateData2);
                $updateData2["gender"] = $gender;
                $updateData2["martial_status"] = $martialStatus;
                $updateData2["num_of_child"] = $childNumber;
                $updateData2["child_age"] = $childNumber>0?implode("#", $childAge):'';
                $updateData2["tax_number"] = $taxNumber;

                $db->where('client_id', $clientID);
                $updateResult = $db->update('client_detail', $updateData2);
            }

            // insert activity log
            $changedDataArray = array_diff_assoc($updateData, $clientOldData); // get what is changed
            $changedDataArray2 = array_diff_assoc($updateData2, $clientOldData2); // get what is changed
            if(count($changedDataArray) > 0 || count($changedDataArray2) > 0){
                $activityData = array_merge($userData, $changedDataArray, $changedDataArray2);
                $activityRes = Activity::insertActivity('Edit Member Details', 'T00015', 'L00015', $activityData, $clientID);
                // Failed to insert activity
                if(!$activityRes)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to insert activity." /* $translations["E00144"][$language] */, 'data' => "");
            }

            if($addBankFlag){
                $bankValidation = Client::addBankAccountDetail($bankInfo);
            }

            if($addBillingAddrFlag){
                if($curBillingRes) {
                    $billingInfo['id'] = $curBillingRes['id'];
                    $errorFieldArrBilling = Inventory::manageAddress($billingInfo, 'edit');
                }
                else {
                    $errorFieldArrBilling = Inventory::manageAddress($billingInfo, 'add');
                }
            }

            if($addDeliveryAddrFlag){
                if($curDeliveryRes) {
                    $deliveryInfo['id'] = $curDeliveryRes['id'];
                    $errorFieldArrBilling = Inventory::manageAddress($deliveryInfo, 'edit');
                }
                else {
                    $errorFieldArrBilling = Inventory::manageAddress($deliveryInfo, 'add');
                }
            }

            if($updateResult)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M02477"][$language] /* Successfully Updated" */, 'data' => "");

            return array('status' => "error", 'code' => 1, 'statusMsg' => "Update failed" /*  $translations["E00131"][$language]*/, 'data' =>"");
        }

        public function changeMemberPassword($params) {
            $db = MysqliDb::getInstance();

            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $memberID     = $params['clientID'];
            $newPassword  = $params['newPassword'];
            $confirmNewPassword  = $params['confirmNewPassword'];
            $passwordCode = $params['passwordType'];

            $minPass      = Setting::$systemSetting['minPasswordLength'];
            // Get password encryption type
            $passwordEncryption  = Setting::getMemberPasswordEncryption();

            if (empty($memberID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00132"][$language] /* Member not found */, 'data'=> "");

            // checking client
            $db->where('id', $memberID);
            $clientDetails = $db->getValue('client', 'username');
            if(empty($clientDetails))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00133"][$language] /* Member not found */, 'data' => "");

            $memberId      = $memberID;
            $username    = $clientDetails;

            if (empty($passwordCode)) {
                $errorFieldArr[] = array(
                                            'id'  => 'passwordTypeError',
                                            'msg' => $translations["E00134"][$language] /* Please select a password type */
                                        );
            } else {
                if ($passwordCode == 1) {
                    $passwordType  = "password";
                } else if ($passwordCode == 2) {
                    $passwordType  = "transaction_password";
                } else {
                    $errorFieldArr[] = array(
                                                'id'  => 'passwordTypeError',
                                                'msg' => $translations["E00135"][$language] /* Invalid password type */
                                            );
                }
            }
       
            // get error msg type
            if ($passwordType == "password") {
                $idName        = 'Password';
                $msgFieldB     = 'Password';
                $msgFieldS     = 'password';
                $titleCode     = 'T00013';
                $activityCode  = 'L00013';
                $transferType  = 'Reset Password';
                $maxLength     = $maxPass;
                $minLength     = $minPass;
            } else if ($passwordType == "transaction_password") {
                $idName        = 'TPassword';
                $msgFieldB     = 'Transaction password';
                $msgFieldS     = 'transaction password';
                $titleCode     = 'T00014';
                $activityCode  = 'L00014';
                $transferType  = 'Reset Transaction Password';
                $maxLength     = $maxTPass;
                $minLength     = $minTPass;
            }
            if (empty($newPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' =>  $translations["E00136"][$language] /* Please enter new */ . " " . $msgFieldS . "."
                                        );
            } elseif (strlen($newPassword)<$minPass) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $translations["E00451"][$language] /* Password length should within 8 - 20 characters */
                                        );
            }

             //checking re-type password
            if (empty($confirmNewPassword)) {
                $errorFieldArr[] = array(
                    'id' => 'newConfirmPasswordError',
                    'msg' =>  $translations["E00136"][$language] /* Please enter new */ . " " . $msgFieldS . "."
                );
            } else if ($confirmNewPassword != $newPassword){
                // if ($confirmNewPassword != $newPassword) {
                    $errorFieldArr[] = array(
                        'id' => 'newConfirmPasswordError',
                        'msg' => $translations["E00309"][$language] /* Password not match */
                    );
                // }
            }
            // elseif (strlen($confirmNewPassword)<$minPass) {
            //     $errorFieldArr[] = array(
            //                                 'id'  => "newConfirmPasswordError",
            //                                 'msg' => $translations["E00451"][$language] /* Password length should within 8 - 20 characters */
            //                             );
            // }
            // Retrieve the encrypted password based on settings
            $newEncryptedPassword = Setting::getEncryptedPassword($newPassword);
            $db->where('id', $memberId);
            $result = $db->getOne('client', $passwordType);
            // if (empty($result[$passwordType])) 
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00139"][$language] /* Member not found */, 'data'=> "");

            if($result){
                if ($passwordEncryption == "bcrypt") {
                    // We need to verify hash password by using this function
                    if(password_verify($newPassword, $result[$passwordType])) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => $translations["E00140"][$language] /* Please enter different */ . " $msgFieldS."
                                                );
                    }
                } else {
                    if ($newEncryptedPassword == $result[$passwordType]) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => $translations["E00140"][$language] /* Please enter different */ . " $msgFieldS."
                                                );
                    }
                }
            }
            
            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data'=>$data);

            $updateData = array($passwordType => $newEncryptedPassword,"encryption_method" => "bcrypt");
            $db->where('id', $memberId);
            $updateResult = $db->update('client', $updateData);
            if(!$updateResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00143"][$language] /* Update failed */, 'data' => "");

            // insert activity log
            $activityData = array('user' => $username);

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $memberId);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getRankMaintain($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;


            $tableName      = "mlm_bonus";
            $column         = array(

                "mlm_bonus.name AS mlm_bonus_name",
                "mlm_bonus.language_code AS languageCode"
            );

            $db->where("mlm_bonus.allow_rank_maintain", "1");
            $db->where("mlm_bonus.disabled", "0");
            $result = $db->get($tableName, NULL, $column);

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00107"][$language] /* No Results Found. */, 'data'=>"");

            foreach ($result as $value) {
                $bonusSelection[$value['mlm_bonus_name']] = $translations[$value['languageCode']][$language];
                $bonusRankAry[] = $value['mlm_bonus_name']."Percentage";
            }
            $data["bonusRankAry"] = $bonusRankAry;
            
            $clientID = $params['clientID'];
            if ($clientID) {
                // Get member details
                $db->where('id',$clientID);
                $memberDetails = $db->getOne('client','id AS clientID,member_id,username,name, email');

                //get Rank setting
                $column = array(
                    'rank_id',
                    'name',
                    'value',
                    '(SELECT name From rank where id = rank_id) AS rank_name',
                    '(SELECT translation_code From rank where id = rank_id) AS rank_lang_code',
                );
                $bonusRankSettingRes = $db->get('rank_setting',null,$column);
                foreach ($bonusRankSettingRes as $bonusRankSettingRes => $bonusRankSettingValue) {

                    unset($rankData);
                    $rankData['rank_id'] = $bonusRankSettingValue['rank_id'];
                    $rankData['rank_name'] = $bonusRankSettingValue['rank_name'];
                    $rankData['value'] = $bonusRankSettingValue['value'];
                    $rankData['rank_display'] = $translations[$bonusRankSettingValue['rank_lang_code']][$language];

                    $rankDisplay[$bonusRankSettingValue['rank_id']] = $translations[$bonusRankSettingValue['rank_lang_code']][$language];

                    $rankSettingData[$bonusRankSettingValue['name']][$bonusRankSettingValue['rank_id']] = $rankData;
                }

                $bonusNameRes = $db->get('mlm_bonus',null,'name'); 
                foreach ($bonusNameRes as $bonusNameKey => $bonusNameValue) {

                    $settingName = $bonusNameValue['name'].'Percentage';
                    if($rankSettingData[$settingName]){
                        $rankSettingAry[$settingName] = $rankSettingData[$settingName];

                    }
                }

                foreach ($bonusRankAry as $bonusName) {
                    $memberDetails["System"][$bonusName] = 0;
                    $memberDetails["Admin"][$bonusName] = 0;
                }

                $db->where('client_id',$clientID);
                $db->where('name',$bonusRankAry,'IN');
                $db->orderBy('created_at','ASC');
                $bonusPercentage = $db->get('client_rank',null,'name,value,type,updated_at,rank_id');

                foreach ($bonusPercentage as $bonusPercentageKey => $bonusPercentageValue) {

                    $memberDetails[$bonusPercentageValue['type']][$bonusPercentageValue['name']] = $rankDisplay[$bonusPercentageValue['rank_id']];//$bonusPercentageValue['value'];
                    $memberDetails['updated_at'] = $bonusPercentageValue['updated_at'] > 0 ? $bonusPercentageValue['updated_at'] : "-";
                }

                foreach ($bonusSelection as $key => $value) {
                    $clientBonusPercentage[$key]["display"] = $value;
                    $clientBonusPercentage[$key]["percentage"] = $bonusPercentage[$key] ? $bonusPercentage[$key] : 0;
                }
            }

            $data['memberDetails'] = $memberDetails;
            $data['clientBonusPercentage'] = $clientBonusPercentage;
            $data['rankSettingAry'] = $rankSettingAry;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function updateRankMaintain($params,$userID,$site) {

            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            $bonusName   = $params['bonusName'];
            $rank_id    = $params['rank_id'];
            $clientID    = trim($params['clientID']);


            if (empty($clientID) || !is_numeric($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language] /* Client not found. */, 'data'=>"");
            
            // Check client
            $db->where('id', $clientID);
            $username = $db->getValue('client', 'username');
            if(!$username)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00146"][$language] /* Client not found. */, 'data'=>"");

            if (empty($bonusName) || empty($rank_id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00147"][$language] /* Invalid rank. */, 'data'=> "");

            if($bonusName != 'maxCap'){
                $db->where('name',$bonusName);
                $bonusNameLangCode = $db->getValue('mlm_bonus','language_code');
                $bonusNameDisplay = $translations[$bonusNameLangCode][$language];


                $bonusName = $bonusName."Percentage";
            }else{

                $bonusNameDisplay = $translations['A00549'][$language];
            }
            

            $column = array(
                "rank_id",
                "value",
                "(SELECT type FROM rank where id = rank_id) AS rank_type"
            );
            $db->where('name', $bonusName);
            $db->where('rank_id', $rank_id);
            $rank_setting_res = $db->get("rank_setting", 1,$column);
            foreach ($rank_setting_res as $rank_setting_key => $rank_setting_value) {
                $percentage = $rank_setting_value['value'];
                $rank_type = $rank_setting_value['rank_type'];
            }

            $isSet = '';
            $db->where('client_id',$clientID);
            $db->where('name',$bonusName);
            $db->where('type',$site);
            $db->orderBy('created_at', 'DESC');
            $copyDb = $db->copy();
            $isSetBeforePercentage = $db->getValue('client_rank','value');
/*
            if ($isSetBeforePercentage >= $percentage) {
                
                return array('status'=>'error','code'=>2,'statusMsg'=>'Failed to update rank.','data'=>'');

            } else {*/
                $insertData = array(
                    'client_id' => $clientID,
                    'name' => $bonusName,
                    'value' => $percentage,
                    'rank_type'  => $rank_type,
                    'rank_id'    => $rank_id,
                    'created_at' => $db->now(),
                    'updated_at' => $db->now(),
                    'updated_by' => $userID,
                    'type' => $site
                );

                $isSet = $db->insert('client_rank',$insertData);
            /*}*/

            if (!$isSet) {
                return array('status'=>'error','code'=>2,'statusMsg'=>'Failed to update rank.','data'=>'');
            }

            // insert activity log
            $titleCode      = 'T00008';
            $activityCode   = 'L00025';
            $transferType   = 'Change Rank';
            $activityData   = array(
                'user' => $username,
                'bonusName'  => $bonusNameDisplay,
                'old'  => $isSetBeforePercentage?$isSetBeforePercentage:0,
                'new'  => $percentage
            );

            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00109"][$language] /* Successfully update clienk rank. */, 'data'=> '');
        }

        public function getPortfolioList($params, $site, $userID, $specialFilterArray = 0) {
            $db            = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData    = $params['searchData'];
            $pageNumber    = trim($params['pageNumber']) ? trim($params['pageNumber']) : 1;
            $seeAll        = trim($params['seeAll']);
            $currentTime   = time();
            $dateTime      = date("Y-m-d H:i:s");
            $limit         = $seeAll == 1 ? NULL : General::getLimit($pageNumber);

            $usernameSearchType = strtolower(trim($params["usernameSearchType"]));

            $decimalPlaces  = Setting::getInternalDecimalFormat();
            $adminLeaderAry = Setting::getAdminLeaderAry();

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

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

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
                        case 'portfolioType':
                            $db->where("portfolio_type", $dataValue);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;

                        case 'fullName':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%" .  $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;
                            
                        case 'username':
                            $sq = $db->subQuery();
                            if ($usernameSearchType == "like") $sq->where("username", '%'.$dataValue.'%', 'LIKE');
                            else $sq->where("username", $dataValue);

                            $sq->get("client", NULL, "id");
                            $db->where('client_id', $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;
                        
                        case 'countryName':
                            $sq = $db->subQuery();
                            $sq->where("country_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;
                            
                        case 'entryDate':
                            // Set db column here
                            $columnName = 'date(created_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'refNo':
                            $db->where('reference_no', $dataValue);
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            if($site == 'Member') $db->where("client_id", $userID);

            $copyDB = $db->copy();
            $db->orderBy('id', 'DESC');
            $portfolioRes = $db->get('mlm_client_portfolio', $limit, 'id, client_id, product_price, status, product_id, bonus_value, batch_id');

            if(!$portfolioRes){
                if(!$data) $data = "";
                return array("status" => 'ok', "code" => 0, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => $data);
            }

            foreach ($portfolioRes as $portfolioResult) {
                $clientIDAry[$portfolioResult['client_id']]   = $portfolioResult['client_id'];
                $productIDAry[$portfolioResult['product_id']] = $portfolioResult['product_id'];
                $batchIDAry[$portfolioResult['batch_id']] = $portfolioResult['batch_id'];
            }

            if($clientIDAry){
                $db->where('id', $clientIDAry, "IN");
                $clientData = $db->map('id')-> get('client', NULL, 'id, username, member_id, email');
            }

            if($productIDAry){
                $db->where('module_id', $productIDAry, "IN");
                $db->where('module', 'mlm_product');
                $db->where('type', 'name');
                $db->where('language', $language);
                $prodData = $db->map('module_id')-> get('inv_language', NULL, 'module_id, content');
            }

            if($batchIDAry){
                $db->where('batch_id', $batchIDAry, "IN");
                $batchIDData = $db->map('batch_id')-> get('inv_order', NULL, 'batch_id, reference_number');
            }

            foreach ($portfolioRes as $portfolioResult) {

                $portfolio['id']            = $portfolioResult['id'];
                $portfolio['username']      = $clientData[$portfolioResult['client_id']]['username'];
                $portfolio['memberID']      = $clientData[$portfolioResult['client_id']]['member_id'];
                $portfolio['email']         = $clientData[$portfolioResult['client_id']]['email'];
                $portfolio['packageName']   = $prodData[$portfolioResult['product_id']];
                $portfolio['referenceNo']   = $batchIDData[$portfolioResult['batch_id']];
                $portfolio['productPrice']  = $portfolioResult['product_price'];
                $portfolio['bonusValue']    = $portfolioResult['bonus_value'];
                $portfolio['status']        = General::getTranslationByName($portfolioResult['status']);

                $portfolioList[] = $portfolio;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully exported", 'data' => $data);
            }

            $totalRecordRes = $copyDB->getValue('mlm_client_portfolio','count(*)', null);
            $totalRecord    = $copyDB->count;
            $data['portfolioList']  = $portfolioList;
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }
            $data['seeAll']         = $seeAll;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00547'][$language] /* Successfully retrieved */, 'data' => $data);
        }

        public function getPortfolioListOld($params, $site, $userID, $specialFilterArray = 0) {
            $db            = MysqliDb::getInstance();
            $language      = General::$currentLanguage;
            $translations  = General::$translations;

            $productID     = trim($params['productID']);
            $searchData    = $params['searchData'];
            $pageNumber    = trim($params['pageNumber']) ? trim($params['pageNumber']) : 1;
            $seeAll        = trim($params['seeAll']);
            $currentTime   = time();

            $limit         = $seeAll == 1 ? NULL : General::getLimit($pageNumber);

            $usernameSearchType = strtolower(trim($params["usernameSearchType"]));

            $decimalPlaces  = Setting::getInternalDecimalFormat();
            $adminLeaderAry = Setting::getAdminLeaderAry();

            $firstGameTime   = Setting::$systemSetting['firstGameTime'];  /* 11:30:00 */
            $secondGameTime  = Setting::$systemSetting['secondGameTime']; /* 16:30:00 */

            $firstGameDateTime  = date('Y-m-d H:i:s', strtotime($firstGameTime));  /*Y-m-d 11:30:00*/
            $secondGameDateTime = date('Y-m-d H:i:s', strtotime($secondGameTime)); /*Y-m-d 16:30:00*/

            if($site == 'Member'){
                $db->where('name', array('firstGameTime', 'secondGameTime'), 'IN');
                $gameTimeSetting = $db->get('system_settings');

                foreach ($gameTimeSetting as $timeSetting) {
                    if ($timeSetting['name'] == 'firstGameTime') {
                        $gameOneStartTime = date('Y-m-d H:i:s', strtotime($timeSetting['value']));
                        $gameOneEndTime   = date('Y-m-d H:i:s', strtotime($timeSetting['reference']));
                    }

                    if ($timeSetting['name'] == 'secondGameTime') {
                        $gameTwoStartTime = date('Y-m-d H:i:s', strtotime($timeSetting['value']));
                        $gameTwoEndTime   = date('Y-m-d H:i:s', strtotime($timeSetting['reference']));
                    }
                }

                if((strtotime($gameOneStartTime) <= $currentTime && strtotime($gameOneEndTime) > $currentTime) || (strtotime($gameTwoStartTime) <= $currentTime && strtotime($gameTwoEndTime) > $currentTime)) {

                    // In Game
                    if(strtotime($gameOneStartTime) <= $currentTime && strtotime($gameOneEndTime) > $currentTime){
                        $remaining['startTime'] = 0;
                        $remaining['drawTime']  = strtotime($gameOneEndTime) - $currentTime;
                    } else if(strtotime($gameTwoStartTime) <= $currentTime && strtotime($gameTwoEndTime) > $currentTime){
                        $remaining['startTime'] = 0;
                        $remaining['drawTime']  = strtotime($gameTwoEndTime) - $currentTime;
                    } 
                } else{
                    // Not In Game
                    if(strtotime($gameOneStartTime) >= $currentTime){
                        $remaining['startTime'] = strtotime($gameOneStartTime) - $currentTime;
                        $remaining['drawTime']  = 0;
                    } elseif(strtotime($gameTwoStartTime) >= $currentTime) {
                        $remaining['startTime'] = strtotime($gameTwoStartTime) - $currentTime;
                        $remaining['drawTime']  = 0;
                    } else {
                        $remaining['startTime'] = strtotime($gameOneStartTime. "+ 1 day") - $currentTime;
                        $remaining['drawTime']  = 0;
                    }
                }
                $data['remaining'] = $remaining;
            }

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

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }


                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = strtolower(trim($v['dataType']));
                        
                    switch($dataName) {
                        case 'portfolioType':
                            $db->where("portfolio_type", $dataValue);
                            break;

                        case 'type':
                            $sq = $db->subQuery();
                            $sq->where("name", $dataValue);
                            $sq->getOne("mlm_product", "id");
                            $db->where("product_id", $sq);
                            break;

                        case 'memberID':
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;

                        case 'fullName':
                            if ($dataType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("name", "%" .  $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            } else {
                                $sq = $db->subQuery();
                                $sq->where("name", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            }
                            break;
                            
                        case 'username':
                            $sq = $db->subQuery();
                            if ($usernameSearchType == "like") $sq->where("username", '%'.$dataValue.'%', 'LIKE');
                            else $sq->where("username", $dataValue);

                            $sq->get("client", NULL, "id");
                            $db->where('client_id', $sq);
                            break;

                        case 'phone':
                            $sq = $db->subQuery();
                            $sq->where("phone", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);
                            break;
                        
                        case 'countryName':
                            $sq = $db->subQuery();
                            $sq->where("country_id", $dataValue);
                            $sq->get("client", NULL, "id");
                            $db->where("client_id", $sq, "in");
                            break;
                            
                        case 'entryDate':
                            // Set db column here
                            $columnName = 'date(created_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                            
                        case 'maturityDate':
                            // Set db column here
                            $columnName = 'date(expire_at)';
                                
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00162"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00163"][$language] /* Invalid date. */, 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00164"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'refNo':
                            $db->where('reference_no', $dataValue);
                            break;

                        case 'clientId':
                            $db->where('client_id', $$dataValue);
                            break;

                        case 'productType':
                            if ($dataValue) $db->where('product_id',$dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                    unset($dataType);
                }
            }

            if ($specialFilterArray){
                foreach ($specialFilterArray as $columnName => $columnField) {
                    switch ($columnName) {
                        case 'productName':
                            $sq = $db->subQuery();
                            $sq->where("name", $columnField);
                            $sq->getOne("mlm_product", "id");
                            $db->where("product_id", $sq);
                            break;
                        
                        default:
                            $db->where($columnName,$columnField);
                            break;
                    }
                    
                }
            }

            if($productID) $db->where("product_id", $productID);

            if($adminLeaderAry) $db->where('client_id', $adminLeaderAry, 'IN');

            if($site == 'Member') $db->where("client_id", $userID);

            $column = array(
                "id", "client_id", "created_at", "product_price", "expire_at",
                "status", "product_id", "bonus_value as amount", "belong_id", "creator_id",
                "(SELECT `sponsor_id` FROM `client` WHERE `client`.`id` = `mlm_client_portfolio`.`client_id`) AS sponsor_id",
            );

            $copyDB = $db->copy();
            $db->orderBy('id', 'DESC');
            $portfolioRes = $db->get('mlm_client_portfolio', $limit, $column);

            if(!$portfolioRes)
                return array("status" => 'ok', "code" => 0, "statusMsg" => $translations['B00101'][$language] /* No Results Found */, "data" => '');
            
            foreach ($portfolioRes as $value) {
                $clientIDAry[$value['client_id']]   = $value['client_id'];
                $clientIDAry[$value['sponsor_id']]  = $value['sponsor_id'];
                $clientIDAry[$value['creator_id']]  = $value['creator_id'];
                $productIDAry[$value['product_id']] = $value['product_id'];
                $belongIDAry[$value['belong_id']]   = $value['belong_id'];

                if($site == 'Member') $portfolioIDAry[$value['id']]   = $value['id'];
            }

            if($clientIDAry){
                $db->where('type', 'Client');
                $db->where('id', $clientIDAry, 'IN');
                $clientData = $db->map('id')->get('client', NULL, 'id, username');

                $db->where('name', 'enabledAutoJoin');
                $db->where('type', 'joinGameSetting');
                $db->where('client_id', $clientIDAry, 'IN');
                $autoJoinGameData = $db->map('client_id')->get('client_setting', NULL, 'client_id, value');
            }

            if($currentTime >= strtotime($firstGameDateTime)){
                $previousGameTime = $firstGameDateTime;
            }else if($currentTime >= strtotime($secondGameDateTime)){
                $previousGameTime = $secondGameDateTime;
            }else{
                $pastDayGameDateTime = date('Y-m-d H:i:s', strtotime('-1 day', strtotime($secondGameTime))); /*Y-m-d(-1) 16:30:00*/
                $previousGameTime = $pastDayGameDateTime;
            }

            if($previousGameTime){
                $db->where('start_date', $previousGameTime);
                $previousGameIDAry = $db->getValue('game', 'id', NULL);
            }

            if($portfolioIDAry){
                if($previousGameIDAry) $db->where('game_id', $previousGameIDAry, 'IN');
                $db->where('portfolio_id', $portfolioIDAry, 'IN');
                $getGameDetail = $db->map('portfolio_id')->get('game_detail', NULL, 'portfolio_id, id, game_id');

                if($getGameDetail){
                    foreach ($getGameDetail as $gameRow) {
                        $gameIDAry[$gameRow['game_id']] = $gameRow['game_id'];
                    }
                }

                if($gameIDAry){
                    $db->where('id', $gameIDAry, 'IN');
                    // $db->where('status', array('await', 'closed'), 'IN');
                    $db->orderBy('end_date', 'DESC');
                    $db->orderBy('id', 'DESC');
                    $db->groupBy('product_id');
                    $gameData = $db->map('id')->get('game', NULL, 'id, product_id, created_at as startTime, end_date as endTime, status');
                }
            }

            if($productIDAry){
                $db->where('id', $productIDAry, 'IN');
                $productData = $db->map('id')->get('mlm_product', NULL, 'id, translation_code');
            }

            unset($value);
            foreach ($portfolioRes as $value) {
                $value['bonusEarned']     = 0;
                $value['entryDate']       = $value['created_at'];
                $value['portfolioID']     = $value['id'];
                $value['username']        = $clientData[$value['client_id']]  ? $clientData[$value['client_id']]  : '-';
                $value['sponsorUsername'] = $clientData[$value['sponsor_id']] ? $clientData[$value['sponsor_id']] : '-';
                $value['creatorUsername'] = $clientData[$value['creator_id']] ? $clientData[$value['creator_id']] : '-';

                $productDisplay          = $translations[$productData[$value['product_id']]][$language];
                $value['packageDisplay'] = $productDisplay ? $productDisplay : '-';

                $value['statusDisplay'] = General::getTranslationByName($value['status']);

                $value['maturityDate'] = $value["expire_at"] > 0 ? date("Y-m-d",strtotime($value["expire_at"])) : "-";

                if($site == 'Member'){
                    $value['hasJoined']         = '0';
                    $value['gameStartTime']     = '-';
                    $value['gameCloseTime']     = '-';
                    $value['gameStatus']        = '-';
                    $value['gameStatusDisplay'] = '-';
                    $value['isMatured']         = '0';
                    $value['currentGameID']     = '-';
                    $value['disabledAutoJoin']  = $autoJoinGameData[$value['client_id']] == '1' ? '0' : '1';

                    /*Initially the common varable for both portfolio status*/
                    switch ($value['status']) {
                        case 'Active':
                            if($getGameDetail[$value['id']]){
                                $value['hasJoined']     = '1';
                                $value['currentGameID'] = $getGameDetail[$value['id']]['game_id'];
                            }

                            $value['gameStatus']    = $gameData[$getGameDetail[$value['id']]['game_id']]['status'];
                            $value['gameStatus']    = $value['gameStatus'] ? $value['gameStatus'] : '-';

                            $gameStatusDisplay          = General::getTranslationByName($value['gameStatus']);
                            $gameStatusDisplay          = $gameStatusDisplay ? $gameStatusDisplay : '-';
                            $value['gameStatusDisplay'] = $gameStatusDisplay ? $gameStatusDisplay : $value['gameStatus'];

                            $value['gameStartTime'] = $remaining['startTime'] ? $remaining['startTime'] : '-';
                            $value['gameCloseTime'] = $remaining['drawTime'] ? $remaining['drawTime'] : '-';

                            // return;
                            // if(in_array($value['gameStatus'], array('await', 'closed'))){
                            //     $gameEndTime = $gameData[$getGameDetail[$value['id']]['game_id']]['endTime'];

                            //     $value['gameCloseTime'] = strtotime($gameEndTime) - $currentTime;
                            //     $value['gameCloseTime'] = $value['gameCloseTime'] <= 0 ? '-' : $value['gameCloseTime'];
                            // }else{
                            //     if($currentTime < strtotime($firstGameDateTime)){
                            //         $value['gameStartTime'] = strtotime($firstGameDateTime);
                            //     }else if($currentTime < strtotime($secondGameDateTime)){
                            //         $value['gameStartTime'] = strtotime($secondGameDateTime);
                            //     }else{
                            //         $value['gameStartTime'] = strtotime('+1 day', strtotime($firstGameDateTime));
                            //     }

                            //     if($value['gameStartTime'] != '-'){
                            //         $value['gameStartTime'] = $value['gameStartTime'] - $currentTime;
                            //     }
                            // }
                            break;

                        case 'Matured':
                            $value['hasJoined'] = '1';
                            $value['isMatured'] = '1';
                            break;
                    }
                }

                unset($value['id']);
                unset($value['client_id']);
                unset($value['sponsor_id']);
                unset($value['creator_id']);
                unset($value['product_id']);
                unset($value['expire_at']);

                $portfolioList[] = $value;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Successfully exported", 'data' => $data);
            }

            $totalRecord            = $copyDB->getValue('mlm_client_portfolio', 'COUNT(id)');
            $data['portfolioList']  = $portfolioList;
            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage']  = 1;
                $data['numRecord']  = $totalRecord;
            }else{
                $data['totalPage']  = ceil($totalRecord/$limit[1]);
                $data['numRecord']  = $limit[1];
            }
            $data['grandTotal']     = $grandTotal;
            $data['seeAll']         = $seeAll;

            if($site != 'Member'){
                $data['countryList'] = $db->get('country', null, 'id, name');
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00547'][$language] /* Successfully retrieved */, 'data' => $data);
        }

       //  public function getPortfolioList1($params, $site, $userID,$specialFilterArray=0) {

       //      $db = MysqliDb::getInstance();

       //      $language       = General::$currentLanguage;
       //      $translations   = General::$translations;
            
       //      $productID     = $params['productID'];
       //      $searchData     = $params['searchData'];
       //      $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
       //      $seeAll         = $params['seeAll'];
       //      $limit          = General::getLimit($pageNumber);
       //      $decimalPlaces  = Setting::getInternalDecimalFormat();

       //      $usernameSearchType = strtolower(trim($params["usernameSearchType"]));

       //      // Get member leader username
       //      $leaderUsernameArray = $db->map('client_id')->get('tree_sponsor',null,'client_id,(SELECT client.username FROM client WHERE client.id = tree_sponsor.upline_id) AS username');

       //      $tableName      = "mlm_client_portfolio";
       //      $column         = array(

       //          "id",
       //          "client_id",
       //          "reference_no",
       //          "created_at",
       //          "(SELECT username FROM client WHERE id = client_id) AS username",
       //          "(SELECT name FROM client WHERE id = client_id) AS fullname",
       //          "(SELECT identity_number FROM client WHERE id = client_id) AS identity_number",
       //          "(SELECT email FROM client WHERE id = client_id) AS email",
       //          "(SELECT member_id FROM client WHERE id = client_id) AS memberID",
       //          "(SELECT name FROM country WHERE id = (SELECT country_id FROM client WHERE id = client_id)) AS country",
       //          "(SELECT username FROM client WHERE id = (SELECT sponsor_id FROM client WHERE id = client_id)) AS sponsorUsername",
       //          "status",
       //          // "day_left",
       //          "(product_price) AS product_price",
       //          "expire_at",
       //          "product_id",
       //          "portfolio_type",
       //          "bonus_value AS amount",
       //          "max_cap",
       //          "(SELECT username FROM client WHERE id = creator_id) AS creatorUsername",
       //          "(SELECT mlm_pin.code FROM mlm_pin WHERE mlm_pin.belong_id = mlm_client_portfolio.belong_id) AS pinCode",
       //          "pairing_cap",
       //          // "rebateLock",
       //          // "rebateWithholdingCredit",
       //      );

       //      // Get user name


       //      $adminLeaderAry = Setting::getAdminLeaderAry();

       //      // Means the search params is there
       //      $cpDb = $db->copy();
       //      if (count($searchData) > 0) {
       //          foreach ($searchData as $k => $v) {
       //              $dataName = trim($v['dataName']);
       //              $dataValue = trim($v['dataValue']);

       //              switch($dataName) {
       //                  case 'leaderUsername':

       //                      $clientID = $db->subQuery();
       //                      $clientID->where('username', $dataValue);
       //                      $clientID->getOne('client', "id");

       //                      $downlines = Tree::getSponsorTreeDownlines($clientID);
       //                      // $downlines[] = $clientID;

       //                      if (empty($downlines))
       //                          return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");

       //                      $db->where('client_id', $downlines, "IN");

       //                      break;

       //                  case 'mainLeaderUsername':

       //                      $cpDb->where('username', $dataValue);
                            // $mainLeaderID  = $cpDb->getValue('client', 'id');
                            // $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            // if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            // $db->where('client_id', $mainDownlines, "IN");

       //                      break;
       //              }
       //              unset($dataName);
       //              unset($dataValue);
       //          }


       //          foreach ($searchData as $k => $v) {
       //              $dataName = trim($v['dataName']);
       //              $dataValue = trim($v['dataValue']);
       //              // $dataType = strtolower(trim($v['dataType']));
                        
       //              switch($dataName) {
       //                  case 'portfolioType':
                         
       //                      $db->where("portfolio_type", $dataValue);
                                
       //                      break;

       //                  case 'type':
       //                      $sq = $db->subQuery();
       //                      $sq->where("name", $dataValue);
       //                      $sq->getOne("mlm_product", "id");
       //                      $db->where("product_id", $sq);
                                
       //                      break;

       //                  case 'memberID':
       //                      $sq = $db->subQuery();
       //                      $sq->where("member_id", $dataValue);
       //                      $sq->get("client", NULL, "id");
       //                      $db->where("client_id", $sq, "in");
                                
       //                      break;

       //                  case 'fullName':
       //                      $sq = $db->subQuery();
       //                      $sq->where("name", $dataValue);
       //                      $sq->get("client", NULL, "id");
       //                      $db->where("client_id", $sq, "in");
                                
       //                      break;
                            
       //                  case 'username':
       //                      //If like, else defaults to '='
       //                      if ($usernameSearchType == "like") {
       //                          $dataValue="%$dataValue%";
       //                          $sq = $db->subQuery();
       //                          $sq->where("username", $dataValue,'like');
       //                          $sq->get("client", NULL, "id");
       //                          $db->where('client_id', $sq,'IN');
       //                      }else {
       //                          $sq = $db->subQuery();
       //                          $sq->where("username", $dataValue);
       //                          $sq->get("client", NULL, "id");
       //                          $db->where('client_id', $sq);
       //                      }
       //                      break;

       //                  case 'phone':
       //                      $sq = $db->subQuery();
       //                      $sq->where("phone", $dataValue);
       //                      $sq->getOne("client", "id");
       //                      $db->where("client_id", $sq);
       //                      break;
                        
       //                  case 'countryName':
       //                      $sq = $db->subQuery();
       //                      $sq->where("country_id", $dataValue);
       //                      $sq->get("client", NULL, "id");
       //                      $db->where("client_id", $sq, "in");
       //                      break;
                            
       //                  case 'entryDate':
       //                      // Set db column here
       //                      $columnName = 'date(created_at)';
                                
       //                      $dateFrom = trim($v['tsFrom']);
       //                      $dateTo = trim($v['tsTo']);
       //                      if(strlen($dateFrom) > 0) {
       //                          if($dateFrom < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
       //                      }
       //                      if(strlen($dateTo) > 0) {
       //                          if($dateTo < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          if($dateTo < $dateFrom)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
       //                          // $dateTo += 86399;
       //                          $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
       //                      }
                                
       //                      unset($dateFrom);
       //                      unset($dateTo);
       //                      unset($columnName);
       //                      break;
                            
       //                  case 'maturityDate':
       //                      // Set db column here
       //                      $columnName = 'date(expire_at)';
                                
       //                      $dateFrom = trim($v['tsFrom']);
       //                      $dateTo = trim($v['tsTo']);
       //                      if(strlen($dateFrom) > 0) {
       //                          if($dateFrom < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00162"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
       //                      }
       //                      if(strlen($dateTo) > 0) {
       //                          if($dateTo < 0)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00163"][$language] /* Invalid date. */, 'data'=>"");
                                    
       //                          if($dateTo < $dateFrom)
       //                              return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00164"][$language] /* Date from cannot be later than date to. */, 'data'=>$data);
       //                          // $dateTo += 86399;
       //                          $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
       //                      }
                                
       //                      unset($dateFrom);
       //                      unset($dateTo);
       //                      unset($columnName);
       //                      break;

       //                  case 'leaderUsername':
       //                      // do nothing =D

       //                      break;

       //                  case 'mainLeaderUsername':
       //                      // do nothing =D

       //                      break;

       //                  case 'status':
       //                      $db->where('status', $dataValue);
       //                      break;

       //                  case 'refNo':
       //                      $db->where('reference_no', $dataValue);
       //                      break;

       //                  case 'clientId':
       //                      $clientId = $dataValue;
       //                      $db->where('client_id', $clientId);
       //                      break;

       //                  case 'productType':
       //                      if ($dataValue) {
       //                          $db->where('product_id',$dataValue);
       //                      }
       //                      break;

       //                  default:
       //                      $db->where($dataName, $dataValue);

       //              }
       //              unset($dataName);
       //              unset($dataValue);
       //              unset($dataType);
       //          }
       //      }

       //      if($site == 'Member'){
       //          $clientID = $userID; 
       //          $db->where("client_id", $clientID);
       //      }

       //      if ($specialFilterArray){
       //          foreach ($specialFilterArray as $columnName => $columnField) {
       //              switch ($columnName) {
       //                  case 'productName':
       //                      $sq = $db->subQuery();
       //                      $sq->where("name", $columnField);
       //                      $sq->getOne("mlm_product", "id");
       //                      $db->where("product_id", $sq);
       //                      break;
                        
       //                  default:
       //                      $db->where($columnName,$columnField);
       //                      break;
       //              }
                    
       //          }
       //      }

       //      $copyDb = $db->copy();
       //      $totalRecord = $copyDb->getValue($tableName, "count(*)");

       //      if($seeAll == "1"){
       //          $limit = array(0, $totalRecord);
       //      } 
       //      if (!empty($productID)){
       //          $db->where("product_id", $productID);
       //      }

       //      if($adminLeaderAry){
    			// $db->where('client_id', $adminLeaderAry, 'IN');
       //      }

       //      $db->orderBy("id", "DESC");
       //      $portfolioList = $db->get($tableName, $limit, $column);
            
       //      if (empty($portfolioList))
       //          return array('status' => "ok", 'code' => 0, 'statusMsg' => 'No Results Found', 'data' => "");

       //      $productList = Product::getProductList();
       //      $date1 = new DateTime(date("Y-m-d"));

       //      // $countRecord = 0;
       //      $grandTotal = 0;

       //      foreach ($portfolioList as $portfolio) {
             
       //          $portfolioListing['clientID']               = $portfolio['client_id'];
       //          $portfolioListing['memberID']               = $portfolio['memberID'];
       //          $portfolioListing['identity_number']        = $portfolio['identity_number'] ? $portfolio['identity_number'] : '-';
       //          $portfolioListing['email']                  = $portfolio['email'] ? $portfolio['email'] : '-';

       //          $mainLeaderUsername = Tree::getMainLeaderUsername($portfolioListing);
       //          $portfolioListing['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

       //          $portfolioListing['portfolioID']            = $portfolio['id']?:'-';
       //          $portfolioListing['reference_no']           = $portfolio['reference_no']?:'-';
       //          $portfolioListing['createdAt']              = General::formatDateTimeToString($portfolio['created_at'])?:'-';
       //          $portfolioListing['username']               = $portfolio['username']?:'-';
       //          $portfolioListing['fullname']               = $portfolio['fullname']?:'-';
       //          $portfolioListing['country']                = $portfolio['country']?:'-';
       //          $portfolioListing['sponsorUsername']        = $portfolio['sponsorUsername']?:'-';
       //          $portfolioListing['creatorUsername']        = $portfolio['creatorUsername']?:'-';
       //          $portfolioListing['pinCode']                = $portfolio['pinCode']?:'-';
       //          $portfolioListing['bonusValue']             = Setting::setDecimal($portfolio['amount']);
       //          $portfolioListing['leaderUsername']         = $leaderUsernameArray[$portfolio['client_id']]?:'-';
       //          $portfolioListing['pairingCap']             = Setting::setDecimal($portfolio['pairing_cap']);

       //          $portfolioListing['rebateLock']             = $portfolio['rebateLock'] ?  $translations["A01273"][$language]:$translations["A01274"][$language];
       //          $portfolioListing['rebateWithholdingCredit']= $portfolio['rebateWithholdingCredit']? $translations["C00014"][$language]:$translations["C00009"][$language];//

       //          if($portfolio['status'] == "Purchased"){
       //              $portfolio['status'] = "Active";
       //          } 

       //          $portfolioListing['status']                 = $portfolio['status'] ? $portfolio['status'] : '-';

       //          $portfolioListing['packageConvertible']     = '0';
       //          if($portfolioListing['status'] == 'Active'){
       //          $portfolioListing['statusDisplay']          = $translations["M00329"][$language];
       //          }
       //          if($portfolioListing['status'] == 'Inactive'){
       //              $productName=$productList['data'][$portfolio['product_id']]['name'];
       //              if($productName=='hedging'||$productName=='newHedging'){
       //                  $portfolioListing['packageConvertible']= '1';
       //              }


       //          $portfolioListing['statusDisplay']          = $translations["M00330"][$language];
       //          }
       //          if($portfolioListing['status'] == 'Terminated'){
       //          $portfolioListing['statusDisplay']          = $translations["M01655"][$language];
       //          }
       //          if($portfolioListing['status'] == 'Redeemed'){
       //          $portfolioListing['statusDisplay']          = $translations["M02051"][$language];
       //          }
       //          // $portfolioListing['expireAt']               = General::formatDateTimeToString($portfolio['expire_at'])?:'-';
       //          $portfolioListing['product_translate_code'] = $productList['data'][$portfolio['product_id']]['translation_code'];
       //          $portfolioListing['product_name']          = $translations[$productList['data'][$portfolio['product_id']]['translation_code']][$language];
       //          $portfolioListing['VRPercentage']           = $productList['data'][$portfolio['product_id']]['vestingReceivable']['value'];
       //          $portfolioListing['vestingReceivableValue'] = $portfolioListing['VRPercentage'] * $portfolio['product_price'] / 100;
       //          $portfolioListing['productPrice']           = number_format($portfolio['product_price'], $decimalPlaces, '.', '');
       //          $portfolioListing['portfolioType']          = $portfolio['portfolio_type'];

       //          $portfolioListing['amount']          = number_format($portfolio['amount'], $decimalPlaces, '.', '');
       //          $portfolioListing['amountDisplay'] = 0;
       //          $portfolioListing['amountNBVDisplay'] = 0;
       //          $portfolioListing['max_cap']          = number_format($portfolio['max_cap'], $decimalPlaces, '.', '');

       //          if($portfolio['portfolio_type'] == 'Package Re-entry'){
       //             // $portfolioListing['portfolioTypeDisplay'] = $translations["T00012"][$language];
       //             $portfolioListing['portfolioTypeDisplay'] = 'Investment';
       //             $portfolioListing['amountDisplay'] = $portfolio['amount'];
       //          }elseif($portfolio['portfolio_type'] == 'freeWithRebate'){
       //             $portfolioListing['portfolioTypeDisplay'] = 'Non-BV Rebate';
       //             $portfolioListing['amountNBVDisplay'] = $portfolio['amount'];
       //          }elseif($portfolio['portfolio_type'] == 'noRebate'){
       //             $portfolioListing['portfolioTypeDisplay'] = 'Non-BV';
       //             $portfolioListing['amountNBVDisplay'] = $portfolio['amount'];
       //          }

       //          switch ($portfolio['portfolio_type']) {
       //              case 'Credit Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01421'][$language];
       //                  break;

       //              case 'Diamond Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01422'][$language];
       //                  break;

       //              case 'Credit Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01425'][$language];
       //                  break;

       //              case 'Diamond Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01426'][$language];
       //                  break;

       //              case 'NBV Credit Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01423'][$language];
       //                  break;

       //              case 'NBVR Credit Register':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01424'][$language];
       //                  break;

       //              case 'NBV Credit Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01427'][$language];
       //                  break;

       //              case 'NBVR Credit Reentry':
       //                  $portfolioListing['portfolioTypeDisplay'] = $translations['A01428'][$language];
       //                  break;
                    
       //              default:
       //                  $portfolioListing['portfolioTypeDisplay'] = $portfolio['portfolio_type'];
       //                  break;
       //          }

       //          $portfolioListing['maturityDate']           = $portfolio["expire_at"]>0?date("Y-m-d",strtotime($portfolio["expire_at"])):"-";

       //          $portfolioMaturityDate = date("Y-m-d",strtotime($portfolio["expire_at"]));

       //          $date2 = new DateTime($portfolioMaturityDate);
       //          if( $date2 < $date1){
       //              $portfolioListing['countDownDays']  = "0";
       //          } else {

       //          $interval = $date1->diff($date2);
              
       //          $portfolioListing['countDownDays']  = $interval->days;

       //          } 

       //          if ($site == 'Admin') {
       //              // unset($portfolioListing['portfolioID']);
       //              // unset($portfolioListing['productPrice']);
       //              unset($portfolioListing['product_translate_code']);
       //          }
                
       //          $portfolioPageListing[] = $portfolioListing;
       //          $grandTotal += ($portfolio['amount']?number_format($portfolio['amount'], $decimalPlaces, '.', ''):'0');
       //          // $countRecord++;
       //      }

       //      $memberDetails = Client::getCustomerServiceMemberDetails($clientId);
       //      $data['memberDetails'] = $memberDetails['data']['memberDetails'];

       //      $data['portfolioPageListing']       = $portfolioPageListing;

       //      if($params['type'] == "export"){
       //          $params['command'] = __FUNCTION__;
       //          $data = Excel::insertExportData($params);
       //          return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
       //      }

       //      $data['pageNumber']                 = $pageNumber;
       //      $data['totalRecord']                = $totalRecord;
       //      if($seeAll == "1"){
       //          $data['totalPage']              = 1;
       //          $data['numRecord']              = $totalRecord;
       //      }else{
       //          $data['totalPage']              = ceil($totalRecord/$limit[1]);
       //          $data['numRecord']              = $limit[1];
       //      }
       //      $data['grandTotal'] = $grandTotal;
       //      $data['seeAll'] = $seeAll;
       //      if($site != 'Member'){
       //          $data['countryList'] = $db->get('country', null, 'id, name');
       //      }

       //      return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Portfolio List successfully retrieved', 'data' => $data);
       //  }

        public function getProductDetail($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_product";
            $searchData     = $params['searchData'];
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $column         = array(

                "mlm_product.name AS product_name",
                "mlm_product.code",
                "mlm_product.category",
                "mlm_product.price",
                "mlm_product.status",
                "mlm_product.translation_code",
                "mlm_product.active_at",
                "mlm_product.expire_at",
                "mlm_product_setting.name AS setting_name",
                "mlm_product_setting.value"

            );

            if (count($searchData) > 0) {
                foreach ($searchData as $array) {
                    foreach ($array as $key => $value) {
                        if ($key == 'dataName') {
                            $dbColumn = $tableName . "." .$value;
                        } else if ($key == 'dataValue') {
                            foreach ($value as $innerVal) {
                                $db->where($dbColumn, $innerVal);
                            }
                        }
                    }
                }
            }

            $copyDb = $db->copy();
            $db->join("mlm_product_setting", "mlm_product_setting.product_id = mlm_product.id", "LEFT");
            $totalRecord = $copyDb->getValue($tableName, "count(*)");
            $productDetail = $db->get($tableName, null, $column);

            $newProductDetail       = array();
            $productArray           = array();
            $newKey                 = -1;
            foreach($productDetail as $productDetailKey => $product){

                if (!in_array($product["product_name"], $productArray)) {

                    ++$newKey;
                    $newProductDetail[$newKey]["product_name"]       = $product["product_name"];
                    $newProductDetail[$newKey]["code"]               = $product["code"];
                    $newProductDetail[$newKey]["category"]           = $product["category"];
                    $newProductDetail[$newKey]["price"]              = $product["price"];
                    $newProductDetail[$newKey]["status"]             = $product["status"];
                    $newProductDetail[$newKey]["translation_code"]   = $product["translation_code"];
                    $newProductDetail[$newKey]["active_at"]          = $product["active_at"];
                    $newProductDetail[$newKey]["expire_at"]          = $product["expire_at"];
                }
                $newProductDetail[$newKey][$product["setting_name"]] = $product["value"];
                $productArray [] = $product["product_name"];

            }

            $data['productDetail']          = $newProductDetail;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00116"][$language] /* Successfully retrieved product detail */, 'data' => $data);
        }

        public function getActivityLogList($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $searchData     = $params['searchData'];
            $memberId       = $params['memberId'] ? $params['memberId'] : "";
            $dateToday      = date("Ym");

            $usernameSearchType = $params["usernameSearchType"];

            //Get the limit.
            $limit = General::getLimit($pageNumber);

    		$adminLeaderAry = Setting::getAdminLeaderAry();

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach($searchData as $k => $v){
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch ($dataName) {
                        case 'creatorUsername':
                            if ($dataValue == "Public Registration") {
                                $db->where('creator_id', "0");
                            } else{
                                $db->where('username', $dataValue);
                                $searchID = $db->getValue('admin',"id");

                                if (empty($searchID)){
                                    $db->where('username', $dataValue);
                                    $searchID = $db->getValue('client', "id");
                                }

                                $db->where('creator_id', $searchID);
                            }
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
                            if ($usernameSearchType == "match") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue);
                                $sq->getOne("client", "id");
                                $db->where("client_id", $sq);
                            } elseif ($usernameSearchType == "like") {
                                $sq = $db->subQuery();
                                $sq->where("username", $dataValue . "%", "LIKE");
                                $sq->get("client", NULL, "id");
                                $db->where("client_id", $sq, "IN"); 
                            }
                            break;

                        case 'clientId':
                            // $db->where('client_id', $dataValue);
                            $sq = $db->subQuery();
                            $sq->where("member_id", $dataValue);
                            $sq->getOne("client", "id");
                            $db->where("client_id", $sq);  
                            break;
                                
                        case 'activityType':
                            $db->where('title', $dataValue);
                            break;

                        case 'searchDate':
                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00166"][$language] /* Please specify a date */, 'data'=>"");
                                
                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                
                            $db->where("DATE(created_at)",date('Y-m-d',$dataValue));
                            break;
                                
                        case 'searchTime':
                            // Set db column here
                            $columnName = 'created_at';

                            if(strlen($dataValue) == 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00168"][$language] /* Please specify a date */, 'data'=>"");

                            if($dataValue < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00169"][$language] /* Invalid date. */, 'data'=>"");

                            $dataValue = date('Y-m-d', $dataValue);

                            $dateFrom = trim($v['timeFrom']);
                            $dateTo = trim($v['timeTo']);
                            if(strlen($dateFrom) > 0) {
                                $dateFrom = strtotime($dataValue.' '.$dateFrom);
                            if($dateFrom < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00170"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                $dateTo = strtotime($dataValue.' '.$dateTo);
                            if($dateTo < 0)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00171"][$language] /* Invalid date. */, 'data'=>"");

                            if($dateTo < $dateFrom)
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00172"][$language] /* Time from cannot be later than time to */, 'data'=>$data);

                                $db->where($columnName, date('Y-m-d H:i:s', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'searchMonth':
                            $dateToday = $dataValue;
                            break;

                        case 'fullname':
                            if($dataType == "like"){
                                $fullname = $db->subQuery();
                                $fullname->where('name',  "%" .  $dataValue . "%", "LIKE");
                                $fullname->get('client', NULL, "id");
                                $db->where("client_id", $fullname,"IN");
                            }else{
                                $fullname = $db->subQuery();
                                $fullname->where('name', $dataValue);
                                $fullname->getOne('client', "id");
                                $db->where("client_id", $fullname);

                            }
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if (!empty($memberId))
                $db->where("a.client_id", $memberId);

    		if($adminLeaderAry)$db->where('a.client_id', $adminLeaderAry, 'IN');

            $db->where("title", "noRebate", "!=");
            $db->orderBy("created_at", "DESC");
            $copyDb = $db->copy();

            $getAdminId        = '(SELECT id FROM admin WHERE a.creator_id = admin.id) as adminId';
            $getMemberId       = '(SELECT member_id FROM client WHERE a.client_id = client.id) as memberId';
            $getAdminUsername  = '(SELECT username FROM admin WHERE a.creator_id = admin.id) as adminUsername';
            $getMemberUsername = '(SELECT username FROM client WHERE a.creator_id = client.id) as clientUsername';
            // specially for public registration
            $getClientUsername = '(SELECT username FROM client WHERE a.client_id = client.id) as getClientUsername';
            $getClientName     = '(SELECT name FROM client WHERE a.client_id = client.id) as getClientName';

            try {
                $result = $db->get('activity_log_'.$dateToday." a", $limit, $getMemberUsername. "," .$getAdminUsername. "," .$getClientUsername. "," .$getMemberId. "," .$getAdminId. ", " .$getClientName. ", client_id, title, translation_code, data, creator_id, creator_type, created_at");
            }
            catch (Exception $e) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00117"][$language] /* No Results Found. */, 'data' => "");
            }

            $creditRes = $db->get('credit', null, 'name, admin_translation_code');
            foreach($creditRes AS $data){
                $creditAry[$data['name']] =  $translations[$data['admin_translation_code']][$language];
            }

            if ($result) {
                foreach($result as $value) {

                    $activity['activityType'] = $value['title'];
                    $translationCode          = $value['translation_code'];
                    $activityData             = (array) json_decode($value['data'], true);

                    $db->where('code', $translationCode);
                    $db->where('language',$language);
                    $content     = $db->getValue('language_translation', 'content');

                    foreach($activityData as $key => $val) {
                        if($key=="client_id"){
                            $db->where("id", $val);
                            $val = $db->getValue("client", "username");
                            $key = "user";
                        }

                        $oriKeyWord = '%%'.$key.'%%';
                        if($key == 'credit'){
                            $val = $creditAry[$val];
                        }
                        $content = str_replace($oriKeyWord, $val, $content);                       
                    }
                    //pieces chop content where ' %%' is at.
                    //pieces2 chop pieces from array position [1] onwards where '%%' is at.
                    //pieces3 chop pieces at array position [0] only where '%%' is at.
                    //pieces3 is using to detect if %% is the first word.
                    $pieces = explode(" %%", $content);

                    if(isset($pieces[1])) {
                        $pieces3 = explode("%%", $pieces[0]);
                        if(isset($pieces3[1]))
                            $piecesList[] = $pieces3[1];

                        foreach(array_slice($pieces, 1) as $val) {
                            $pieces2 = explode("%%", $val);
                            $piecesList[] = $pieces2[0];
                        }
                                
                        foreach($piecesList as $key) {
                            $oriKeyWord = '%%'.$key.'%%';
                            $content = str_replace($oriKeyWord, '', $content);                       
                        }
                    }

                    $activity['description'] = $content;
                    $activity['created_at']  = General::formatDateTimeToString($value['created_at'], "d/m/Y h:i:s A");

                    if ($value['creator_type'] == "Admin")
                        $activity['doneBy']  = $value['adminUsername']?:"-";
                    else if ($value['creator_type'] == "Member")
                        $activity['doneBy']  = $value['clientUsername']?:"-";
                    else
                        $activity['doneBy']  = "-";

                    $activity['memberID']    = $value['memberId']?:"-";
                    $activity['fullname']    = $value['getClientName']?:"-";
                    $activity['username']    = $value['getClientUsername']?:"-";


                    if ( $value['creator_type'] == "Member" && empty($value['clientUsername'])){
                         $activity['doneBy']  = "Public Registration";
                         $activity['username'] = $value['getClientUsername']?:"-";
                    }

                    $activityList[]          = $activity;
                }

                // This is to get the title for the search select option
                $db->where("title", "noRebate", "!=");
                $dropDownResult = $db->get('activity_log_'.$dateToday, null, "title");
                if(empty($dropDownResult))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00173"][$language] /* Failed to get title for search option */, 'data' => '');
                    
                foreach($dropDownResult as $value) {
                    $searchBarData['activityType'] = $value['title'];
                    $searchBarDataList[]           = $searchBarData;
                }

                $totalRecord = $copyDb->getValue ('activity_log_'.$dateToday . " a", "count(id)");

                // remove duplicate command. Then sort it alphabetically
                $searchBarDataList = array_map("unserialize", array_unique(array_map("serialize", $searchBarDataList)));
                sort($searchBarDataList);

                $data['activityLogList']  = $activityList;
                $data['activityTypeList'] = $searchBarDataList;
                $data['totalPage']        = ceil($totalRecord/$limit[1]);
                $data['pageNumber']       = $pageNumber;
                $data['totalRecord']      = $totalRecord;
                $data['numRecord']        = $limit[1];
                        
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00117"][$language] /* No Results Found. */, 'data'=> "");
            }
        }

        public function getLanguageTranslationList($params) {
            $db = MysqliDb::getInstance();
                
            $pageNumber  = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit       = General::getLimit($pageNumber);

            $searchData  = json_decode($languageCodeParams['searchData']);
            if (count($searchData) > 0) {
                foreach ($searchData as $array) {                  
                    foreach ($array as $key => $value) {
                        if ($key == 'dataName') {
                            $dbColumn = $value;
                        } else if ($key == 'dataValue') {
                            foreach ($value as $innerVal) {
                                $db->where($dbColumn, $innerVal);
                            }
                        }
                    }
                }
            }
            $copyDb = $db->copy();
            $db->orderBy("id", "DESC");
            $result = $db->get("language_translation", $limit);

            $totalRecord = $copyDb->getValue ("language_translation", "count(id)");
                foreach($result as $value) {
                    $language['id']           = $value['id'];
                    $language['contentCode']  = $value['code'];
                    $language['language']     = $value['language'];
                    $language['module']       = $value['module'];
                    $language['site']         = $value['site'];
                    $language['category']     = $value['type'];
                    $language['content']      = $value['content'];

                    $languageList[] = $language;
                        
                }


                    $data['languageCodeList'] = $languageList;
                    $data['totalPage']        = ceil($totalRecord/$limit[1]);
                    $data['pageNumber']       = $pageNumber;
                    $data['totalRecord']      = $totalRecord;
                    $data['numRecord']        = $limit[1];
                    
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function getLanguageTranslationData($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00174"][$language] /* Please Select A Language Code */, 'data'=> '');
                
            $db->where('id', $id);
            $result = $db->getOne("language_translation");

            if (!empty($result)) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $result);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00175"][$language] /* Invalid Language */, 'data'=>"");
            }
        }

        public function editLanguageTranslationData($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $id           = trim($params['id']);
            $contentCode  = trim($params['contentCode']);
            $module       = trim($params['module']);
            $language     = trim($params['language']);
            $site         = trim($params['site']);
            $category     = trim($params['category']);
            $content      = trim($params['content']);

            if(strlen($contentCode) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00176"][$language] /* Please Enter Language Name. */, 'data' => "");

            if(strlen($language) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00177"][$language] /* Please Enter Language Code. */, 'data' => "");

            $updatedAt = $db->now();

            $fields    = array("code", "module", "language", "site", "type", "content", "updated_at");
            $values    = array($contentCode, $module, $language, $site, $category, $content, $updatedAt);
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result    = $db->update("language_translation", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00119"][$language] /* Permission Successfully Updated. */);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00178"][$language] /* Invalid Permission. */, 'data' => "");
            }
        }

        public function getExchangeRateList($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_currency_exchange_rate";
            $joinTable      = "country";
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();


            $db->where("status", "Active");
            $countryRes = $db->get("country", null, "id, name, translation_code, currency_code");

            foreach ($countryRes as $countryRow) {
                if($countryRow['currency_code']){
                    $countryIDArr[$countryRow['id']] = $countryRow['id'];
                    $countryData[$countryRow['id']] = $countryRow;
                }
            }

            $data['activeCountry'] = $countryData;

            $column = array(

                $tableName . ".id",
                $tableName . ".currency_code",
                $tableName . ".exchange_rate",
                $tableName . ".buy_rate",
                $tableName . ".country_id",
            );

            $db->where("country_id", $countryIDArr, "IN");
            $db->orderBy('priority','ASC');
            
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue($tableName, "count(*)");
            $exchangeRateList = $db->get($tableName, $limit, $column);

            if (empty($exchangeRateList))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00120"][$language] /* No Result Found. */, 'data'=> $data);

            foreach ($exchangeRateList as $exchangeRate) {

                if (Cash::$creatorType == "Admin") {
                    if (!empty($exchangeRate['id']))
                        $exchangeRateListing['id']              = $exchangeRate['id'];
                    else
                        $exchangeRateListing['id']              = "-";
                }

                // if (!empty($exchangeRate['name']))
                //     $exchangeRateListing['name']                = $exchangeRate['name'];
                // else
                //     $exchangeRateListing['name']                = "-";
                $translateCode = $countryData[$exchangeRate['country_id']]['translation_code'];
                $exchangeRateListing['display_name'] = $translations[$translateCode][$language];
                $exchangeRateListing['countryID'] = $exchangeRate['country_id'];

                if (!empty($exchangeRate['currency_code']))
                    $exchangeRateListing['currencyCode']        = $exchangeRate['currency_code'];
                else
                    $exchangeRateListing['currencyCode']        = "-";

                if (!empty($exchangeRate['exchange_rate']))
                    $exchangeRateListing['exchangeRate']        = number_format($exchangeRate['exchange_rate'], $decimalPlaces, '.', '');
                else
                    $exchangeRateListing['exchangeRate']        = "-";

                if (!empty($exchangeRate['buy_rate']))
                    $exchangeRateListing['buyRate']        = number_format($exchangeRate['buy_rate'], $decimalPlaces, '.', '');
                else
                    $exchangeRateListing['buyRate']        = "-";

                $exchangeRatePageListing[] = $exchangeRateListing;
            }

            $data['exchangeRatePageListing']    = $exchangeRatePageListing;
            $data['totalPage']                  = ceil($totalRecord/$limit[1]);
            $data['pageNumber']                 = $pageNumber;
            $data['totalRecord']                = $totalRecord;
            $data['numRecord']                  = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00121"][$language] /* Exchange rate list successfully retrieved. */, 'data'=> $data);
        }

        public function editExchangeRate($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_currency_exchange_rate";
            $exchangeRate   = trim($params['exchangeRate']);
            $buyRate        = trim($params['buyRate']);
            $userID         = $db->userID;
            $site           = $db->userType;
            $now            = date("Y-m-d H:i:s");
            $countryID      = trim($params['countryID']);

            if (empty($exchangeRate) || !is_numeric($exchangeRate) || $exchangeRate < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            if (empty($buyRate) || !is_numeric($buyRate) || $buyRate < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            if (empty($countryID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            if($site != "Admin"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            }

            $db->where('id', $userID);
            $adminUsername = $db->getValue("admin", "username");

            $db->where("id", $countryID);
            $db->where("status", "Active");
            $validCountry = $db->getOne("country", "id, currency_code, translation_code");
            if (empty($validCountry) || !$validCountry['currency_code'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $db->where("country_id", $validCountry['id']);
            $rateData = $db->getOne("mlm_currency_exchange_rate");

            if($rateData){
                unset($updateData);
                $updateData = array(
                    "exchange_rate" => $exchangeRate,
                    "buy_rate" => $buyRate,
                    "updated_at" => $now,
                );

                $db->where("country_id", $validCountry['id']);
                if (!$db->update($tableName, $updateData))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00180"][$language] /* Failed to update data. */, 'data'=> "");    
            }else{
                $maxPriority = $db->getValue("mlm_currency_exchange_rate", 'MAX(priority)', NULL);
                $maxPriority = $maxPriority[0];
                // insert
                unset($insertData);
                $insertData = array(
                    "country_id"    => $validCountry['id'],
                    "currency_code" => $validCountry['currency_code'],
                    "exchange_rate" => $exchangeRate,
                    "buy_rate"      => $buyRate,
                    "created_at"    => $now,
                    "updated_at"    => $now,
                    "status"        => "Active",
                    "priority"      => $maxPriority + 1,
                );
                if (!$db->insert("mlm_currency_exchange_rate", $insertData))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00180"][$language] /* Failed to update data. */, 'data'=> "");    
            }
            
            $countryID = $validCountry['id'];
            $currencyCode = $validCountry['currency_code'];
            $countryDisplay = $translations[$validCountry['translation_code']][$language];

            unset($insertData);
            $insertData = array(
                "country_id"    => $rateData['country_id'],
                "currency_code" => $rateData['currency_code'],
                "exchange_rate" => $exchangeRate,
                "buy_rate"      => $buyRate,
                "creator_id"    => $userID,
                "created_at"    => $now,
            );
            $db->insert("mlm_currency_exchange_rate_history", $insertData);

            // insert activity log
            $title   = 'Update Exchange Rate';
            $titleCode      = 'T00061'; // Update Exchange Rate
            $activityCode   = 'L00083'; // %%admin%% updated country: %%country%%, currency code : %%currencyCode%%, exchange rate to : %%exRate%%, buy rate to : %%buyRate%%.
            $activityData   = array(
                'admin'     => $adminUsername,
                'country'   => $countryDisplay,
                'currencyCode' => $currencyCode,
                'exRate'    => $exchangeRate,
                'buyRate'   => $buyRate,
            );

            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00122"][$language] /* Successfully update exchange rate. */, 'data'=> "");
        }

        public function getUnitPriceList($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_unit_price";
            $offsetSecs     = trim($params['offsetSecs']);
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $column         = array(

                "id",
                "unit_price",
                "(SELECT name FROM admin WHERE id = creator_id) AS creator_name",
                "created_at"
            );

            $db->orderBy("created_at", "DESC");
            $copyDb = $db->copy();
            $unitPriceList = $db->get($tableName, null, $column);
            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if (empty($unitPriceList))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00123"][$language] /* No Result Found. */, 'data'=> "");

            foreach ($unitPriceList as $unitPrice) {

                if (!empty($unitPrice['id']))
                    $unitPriceListing['id']                     = $unitPrice['id'];
                else
                    $unitPriceListing['id']                     = "-";

                if (!empty($unitPrice['unit_price']))
                    $unitPriceListing['unitPrice']              = number_format($unitPrice['unit_price'], $decimalPlaces, '.', '');
                else
                    $unitPriceListing['unitPrice']              = "-";

                if (!empty($unitPrice['created_at']))
                    $unitPriceListing['createdAt']              = General::formatDateTimeString($offsetSecs, $unitPrice['created_at'], $format = "d/m/Y h:i:s A");
                else
                    $unitPriceListing['createdAt']              = "-";

                if (!empty($unitPrice['creator_name']))
                    $unitPriceListing['creatorName']            = $unitPrice['creator_name'];
                else
                    $unitPriceListing['creatorName']            = "-";

                $unitPricePageListing[] = $unitPriceListing;
            }


            $data['unitPricePageListing']           = $unitPricePageListing;
            $data['totalPage']                      = ceil($totalRecord/$limit[1]);
            $data['pageNumber']                     = $pageNumber;
            $data['totalRecord']                    = $totalRecord;
            $data['numRecord']                      = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00124"][$language] /* Unit price list successfully retrieved */, 'data'=> $data);
        }

        public function addUnitPrice($params) {

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_unit_price";
            $unitPrice      = trim($params['unitPrice']);
            $creatorId      = trim($params['creatorId']);
            $type           = $params['type'] ? trim($params['type']) : "purchase";
            $activedDate    = $params['actived_date'] ? trim($params['actived_date']) : $db->now();

            if (empty($unitPrice) || !is_numeric($unitPrice) || $unitPrice < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Successfully insert unit price */, 'data'=> "");

            if (empty($creatorId) || !is_numeric($creatorId) || $creatorId < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00182"][$language] /* Successfully insert unit price */, 'data'=> "");

            $insertData     = array(

                "unit_price"        => $unitPrice,
                "type"              => $type,
                "creator_id"        => $creatorId,
                "creator_type"      => "Admin",
                "created_at"        => $db->now(),
                "actived_date"      => $activedDate 
            );

            $id = $db->insert($tableName, $insertData);

            if(empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00183"][$language] /* Failed to insert unit price */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00125"][$language] /* Successfully insert unit price */, 'data'=> "");
        }

        public function getMemberAccList($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            if(empty($params['creditType']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");

            $creditType = $params['creditType'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $usernameSearchType = $params["usernameSearchType"];

            $creditID = $db->subQuery();
            $creditID->where("name", $creditType);
            $creditID->get("credit", null, "id");
            $db->where("credit_id", $creditID, "in");
            $db->where("name", "isWallet");
            $result = $db->getOne("credit_setting", "value, admin");

            if(!$result['value'] && !$result['admin'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00196"][$language] /* Invalid credit type */, 'data' => "");
            unset($result);

            //Get the limit.
            $limit      = General::getLimit($pageNumber);
            $searchData = $params['searchData'];
            
    		// $adminLeaderAry = Setting::getAdminLeaderAry();

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

                            $db->where('id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

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
                        case 'name':
                            if ($dataType == "like") {
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            } else {
                                $db->where('name', $dataValue);
                            }
                                
                            break;
                            
                        case 'username':
                            if ($usernameSearchType == "like") {
                                $db->where("username", $dataValue . "%", "LIKE");
                            } else {
                                $db->where("username", $dataValue);
                            }
                                
                            break;

                        case 'memberID':
                            $db->where('member_id', $dataValue);
                            break;

                        case 'email':
                            $db->where('email', $dataValue);
                            break;
                            
                        case 'countryID':
                            $db->where('country_id', $dataValue); 
                            break;
                            
                        case 'disabled':
                            $db->where('disabled', $dataValue);
                                
                            break;

                        case 'phone':
                            $db->where('phone', $dataValue);
                            break;

                        case 'leaderUsername':

                            break;

                        case 'mainLeaderUsername':

                            break; 
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $getCountryName = "(SELECT name FROM country WHERE country.id=country_id) AS country_name";
            $getSponsorUsername = "(SELECT username FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_username";
            
            // if($adminLeaderAry) $db->where('id', $adminLeaderAry, 'IN');

            $db->where("type", "Client");
            $copyDb = $db->copy();

            $db->orderBy("id", "DESC");

            $result = $db->get("client", $limit, 'id, member_id, username, name, '.$getCountryName.','.$getSponsorUsername.', email, disabled');

            $totalRecords = $copyDb->getValue("client", "count(*)");

            if (!empty($result)) {
                foreach($result as $value) {
                    $client['clientID']           = $value['id'];
                    $client['member_id']    = $value['member_id'];
                    $client['username']     = $value['username'];
                    $client['name']         = $value['name'];

                    $client['sponsorUsername'] = $value['sponsor_username'] ? $value['sponsor_username'] : "-";

                    $mainLeaderUsername = Tree::getMainLeaderUsername($client);
                    $client['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                    $client['country']         = $value['country_name'] ? $value['country_name'] : "-";

                    $client['email']        = $value['email'];
                    $client['disabled']     = ($value['disabled'] == 1)? "Disabled":"Active";

                    $clientList[] = $client;
                }

                $data['memberList']  = $clientList;
                $data['totalPage']   = ceil($totalRecords/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecords;
                $data['numRecord']   = $limit[1];
                $data['countryList'] = $db->get('country', null, 'id, name');

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00131"][$language] /* No Results Found */, 'data'=>"");
            }
        }

        public function getMemberBalance($params) {
            $db = MysqliDb::getInstance();

            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $clientID       = $params['id'];
            $creditType     = $params['creditType'];

            if(empty($creditType))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type. */, 'data' => "");

            $adminID=Cash::$creatorID;
            //Get admin's roleID
            $db->where("id", $adminID);
            $roleID = $db->getValue("admin","role_id");

            $db->where('id',$roleID);
            $roleName = $db->getValue('roles','name');


            $permissionsID = $db->subQuery();
            $permissionsID->where("role_id",$roleID);
            $permissionsID->where("disabled",0);
            $permissionsID->get('roles_permission',null,'permission_id');
            $db->where('id',$permissionsID,'IN');
            $permissionsRes=$db->get('permissions',null,'name');
            foreach ($permissionsRes as $key => $value) {
                $permissionsArray[]=$value['name'];
            }

            $creditID = $db->subQuery();
            $creditID->where("name", $creditType);
            $creditID->get("credit", null, "id");
            $db->where("credit_id", $creditID, "in");
            $result = $db->get("credit_setting", null, "name,".strtolower($db->userType)." AS permission");

            if(empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00208"][$language] /* Invalid credit type. */, 'data' => "");

            foreach($result as $value) {
                $permissions[$value['name']] = $value['permission'];

                if($roleName != 'Master Admin'){
                    if (!in_array($creditType.' Withdrawal', $permissionsArray)){
                        $permissions['isWithdrawable']=0;
                    }
                    if (!in_array($creditType.' Adjustment', $permissionsArray)) {
                        $permissions['isAdjustable']=0;
                    }
                    if (!in_array($creditType.' Transfer', $permissionsArray)) {
                    // if (!in_array($creditType.' Transfer', $permissionsRes)) {
                        $permissions['isTransferable']=0;
                    }
                }
            }
            $data['permissions'] = $permissions;
            unset($result);

            $data['balance']        = Cash::getClientCacheBalance($clientID, $creditType);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00134"][$language] /* Successfully get detail */, 'data'=>$data);
        }

        public function getMemberLoginDetail($params){
            global $config;
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $id             = $params['id'];
            $adminID        = $params['adminID'];
            $adminSession   = $params['adminSession'];
            // $url            = $config['loginToMemberURL'];

            // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00275"][$language] /* User is invalid */, 'data'=> $params);


            $tableName      = "client";
            $column         = Array(
                "id",
                "username",
                "dial_code",
                "phone",
                "type"
            );

            $db->where("id", $id);
            $result = $db->getOne($tableName, $column);
            // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00275"][$language] /* User is invalid */, 'data'=> $result['type']);

            if (empty($result))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00275"][$language] /* User is invalid */, 'data'=> "");

            if ($result['type'] != 'Client')
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'User is not a Client', 'data'=> "");


            $dataOut['id'] = $result['id'];
            $dataOut['username'] = $result['username'];
            $dataOut['url'] = Setting::$configArray["loginToMemberURL"];
            $dataOut['adminID'] = $adminID;
            $dataOut['adminSession'] = $adminSession;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $dataOut);
        }

        public function getWhoIsOnlineList($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $adminTimeOut   = Setting::getAdminTimeOut();
            $memberTimeOut  = Setting::getMemberTimeout();
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $data           = array();
            $currentTime    = time();
            $tableName      = "admin";
            $column         = array(
                "username",
                "name",
                "last_login",
                "last_activity"
            );

            $seeAll         = $params['seeAll'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = $seeAll ? null : General::getLimit($pageNumber);

            $adminLastActivity  = $currentTime - $memberTimeOut;
            $memberLastActivity = $currentTime - $adminTimeOut;
            $adminLeaderAry = Setting::getAdminLeaderAry();
            $condition = $adminLeaderAry ? "and id in (".implode(",", $adminLeaderAry).") " : "";

            $result = $db->rawQuery("select username, name, last_login, last_activity, 'client' as type from client where last_activity >= '".date("Y-m-d H:i:s", $memberLastActivity)."'".$condition." and type = 'Client' union all select username,name,last_login,last_activity, 'admin' as type from admin where last_activity >= '".date("Y-m-d H:i:s" , $adminLastActivity)."' and role_id not in (select id from roles where name = 'Master Admin') limit ".$limit[0].",".$limit[1]);
            if(!$result){
                $data['totalUserOnline'] = 0;
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00717"][$language] /* No User Is Online. */, 'data'=> $data);
            }

            unset($count);
            foreach($result as $row){
                    $client['username']     = $row['username'];
                    $client['fullname']     = $row['name'];
                    $client['last_login']   = strtotime($row['last_login']) >0 ? date($dateTimeFormat, strtotime($row['last_login'])) : "-";

                    $count++;
                    $onlineUserList[]       = $client;
            }

            //total client online
            $db->where('last_activity', date("Y-m-d H:i:s", $memberLastActivity),'>=');
            if($adminLeaderAry){
                $db->where('id', $adminLeaderAry, 'IN');
            }
            $db->where('type', 'Client');
            $totalClientOnline = $db->getValue('client','count(*)');

            //total admin online
            $sq = $db->subQuery();
            $sq->where('name','Master Admin');
            $sq->get('roles', null, 'id');
            $db->where('role_id', $sq, 'NOT IN');
            $db->where('last_activity', date("Y-m-d H:i:s", $adminLastActivity),'>=');
            $totalAdminOnline = $db->getValue('admin','count(*)');

            $totalRecord = $totalClientOnline + $totalAdminOnline;

            $data['onlineUserList']     = $onlineUserList;
            $data['totalUserOnline']    = $totalRecord ?  $totalRecord : 0;

            $data['pageNumber']     = $pageNumber;
            $data['totalRecord']    = $totalRecord;
            $data['totalPage']      = ceil($totalRecord/$limit[1]);
            $data['numRecord']      = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getClientRightsList($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName  = "mlm_client_rights";

            if(!$params['pageType']){
                $clientId   = trim($params['clientId']);
                $column     = array(
                    "id",
                    "name",
                    "parent_id",
                    "translation_code",
                    "(SELECT count(*) FROM mlm_client_blocked_rights WHERE client_id = " . $clientId . " AND rights_id = " . $tableName . ".id) AS blocked"
                );

                if (empty($clientId))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00119"][$language], 'data'=> "");
            }else{
                $column     = array(
                    "id",
                    "name",
                    "parent_id",
                    "translation_code",
                );
            }
            
            if($params['pageType']!="batchUnlock"){
                $db->where('status','on');
                $db->orderBy("priority",'ASC');
                $result = $db->get($tableName, NULL, $column);
            }else{
                $db->where('parent_id','0','!=');
                $db->groupBy('parent_id');
                $parentId = $db->get($tableName, NULL, 'parent_id');

                foreach($parentId as $key => $id){
                    $parentIdU[] = $id['parent_id'];
                }

                if($parentIdU) $db->where('id', $parentIdU, 'NOT IN');
                $db->where('status','on');
                $db->orderBy('parent_id','ASC');
                $db->orderBy('priority','ASC');
                $result = $db->get($tableName, NULL, $column);

                array_push($column,'credit_id');

                $credit = $db->subQuery();
                $credit->where('credit_id','0','!=');
                $credit->get($tableName, NULL, 'credit_id');
                $db->where('id',$credit,'IN');
                $creditMenu = $db->map('id')->get('credit', NULL, 'id, translation_code');

                if($parentIdU){
                    $db->where('id',$parentIdU,'IN');
                    $db->where('parent_id','0');
                    $parentMainMenu = $db->get($tableName, NULL, $column);
                    foreach($parentMainMenu as $key=>$value){
                        $parentMainMenuU[] = $value['id'];
                        $parentName[$value['id']] = $value['credit_id'] != 0 ? $translations[$creditMenu[$value['credit_id']]][$language] : $value['name'];
                    }

                    $db->where('id',$parentIdU,'IN');
                    $db->where('parent_id',$parentIdU,'IN');
                    $parentSubMenu = $db->get($tableName, NULL, $column);
                    foreach($parentSubMenu as $key=>$value){
                        $parentSubId[] = $value['id'];
                        $parentSubMenuU[$value['id']] = $value['parent_id'];
                        $parentSubName[$value['id']] = $value['credit_id'] != 0 ? $translations[$creditMenu[$value['credit_id']]][$language] : $value['name'];
                    }
                }
            }

            if (empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00275"][$language], 'data'=> "");

            foreach ($result as &$resultRow) {
                if($params['pageType'] == "batchUnlock" && $parentIdU){
                    if(in_array($resultRow['parent_id'], $parentMainMenuU)){

                        $resultRow['display'] = $translations[$resultRow["translation_code"]][$language]?$parentName[$resultRow['parent_id']]." - ".$translations[$resultRow["translation_code"]][$language]:$resultRow['name'];

                    }elseif(in_array($resultRow['parent_id'], $parentSubId)){

                        $resultRow['display'] = $translations[$resultRow["translation_code"]][$language]?$parentName[$parentSubMenuU[$resultRow['parent_id']]]." - ".$parentSubName[$resultRow['parent_id']]." - ".$translations[$resultRow["translation_code"]][$language]:$resultRow['name']; 

                    }else{

                        $resultRow["display"] = $translations[$resultRow["translation_code"]][$language]?$translations[$resultRow["translation_code"]][$language]:$resultRow['name'];   
                    
                    }
                }else{
                    $resultRow["display"] = $translations[$resultRow["translation_code"]][$language]?$translations[$resultRow["translation_code"]][$language]:$resultRow['name'];
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $result);
        }

        public function lockAccount($params){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $tableName      = "mlm_client_blocked_rights";
            $clientID       = trim($params['clientId']);
            $blockedList    = $params['blockedList'];

            foreach($blockedList as $rights){

                if ($rights['blocked'] == "1"){

                    $db->where("id", $rights['rightsId']);
                    $rightName = $db->getValue("mlm_client_rights","name");

                    $db->where('rights_id', $rights['rightsId']);
                    $db->where('client_id', $clientID);
                    $check = $db->getOne('mlm_client_blocked_rights', 'id');

                    if(!$check){

                        $insertData = array(
                                "client_id" => $clientID,
                                "rights_id" => $rights['rightsId'],
                                "rights_name" => $rightName,
                                "created_at" => $db->now()
                            );
                            if (!$db->insert($tableName, $insertData))
                                return array('status' => "error", 'code' => 1, 'statusMsg' => "Failed to update account rights", 'data' => "");

                    }
                    // $db->rawQuery("INSERT INTO " . $tableName . " (client_id, rights_id, created_at)
                    //                SELECT * FROM (SELECT " . $clientId . ", " . $rights['rightsId'] . ", NOW()) AS tmp
                    //                WHERE NOT EXISTS (
                    //                SELECT client_id FROM " . $tableName . " WHERE client_id = " . $clientId . " AND rights_id = " . $rights['rightsId'] . ")
                    //                LIMIT 1");
                }
                else if ($rights['blocked'] == "0"){

                    $db->where("client_id", $clientID);
                    $db->where("rights_id", $rights['rightsId']);

                    if (!$db->delete($tableName))
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00720"][$language], 'data'=> "");

                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> "");
        }

        public function leaderLockAccount($params) {

            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $seeAll = $params['seeAll'];
            $lineType = $params['lineType'];
            $type = $params['type'];
            $filterOutLeaderUsername = $params['filterOutLeaderUsername'];

            // get rank display
            $rankRes = $db->get("rank", null, "id,translation_code");
            foreach ($rankRes as $rankRow) {
                $rankDisplay[$rankRow['id']] = $translations[$rankRow['translation_code']][$language];
            }

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            if($params['step'] == 1){
                $limit = General::getLimit($pageNumber);
            }
            $searchData = $params['searchData'];

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

                            $leaderUsernameSearch=$downlines;
                            // $db->where('id', $downlines, "IN");

                            break;

                        case 'mainLeaderUsername':

                            $cpDb->where('username', $dataValue);
                            $mainLeaderID  = $cpDb->getValue('client', 'id');
                            $mainDownlines = Leader::getLeaderDownlines($mainLeaderID); 
                            
                            if(empty($mainDownlines)) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
                            $db->where('client_id', $mainDownlines, "IN");

                            break;

                            case 'country':

                                $clientIDAry = $db->subQuery();
                                $clientIDAry->where('country_id',$dataValue);
                                $clientIDAry->get('client', null, 'id');
                                $db->where('id', $clientIDAry, "IN");

                                break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            if ($leaderUsernameSearch){
                $db->where('id', $leaderUsernameSearch, "IN");
            }            
            if ($mainLeaderSearch){
                $db->where('id', $mainLeaderSearch, "IN");
            }
                $getCountryName = "(SELECT name FROM country WHERE country.id=country_id) AS country_name";
                $getSponsorUsername = "(SELECT username FROM client sponsor WHERE sponsor.id=client.sponsor_id) AS sponsor_username";
                $getRankID = "(SELECT value FROM client_setting WHERE client_setting.client_id=client.id AND name='leadershipRank') AS rank_id";
                $db->where('type', "Client");
                $db->where('disabled', "0");
                $copyDb = $db->copy();
                $totalRecords = $copyDb->getValue("client", "count(*)");

                if ($seeAll == "1") {
                    $limit = array(0, $totalRecords);
                }
                $db->orderBy("created_at", "DESC");
                $result = $db->get('client', $limit, 'id, member_id, username, name, ' . $getCountryName . ',' . $getSponsorUsername . ',' . $getRankID . ', disabled, suspended, freezed, last_login, last_login_ip, created_at');                

                if (empty($result))
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00105'][$language] /* No Results Found. */, 'data' => "");

                foreach ($result as $value) {

                    // if(in_array($filterDownlines,$value['id'])){

                    //     continue;
                    // }
                    $test[] = in_array($value['id'], $filterDownlines);



                    $client['clientID'] = $value['id'];
                    $client['member_id'] = $value['member_id'];
                    $client['username'] = $value['username'];
                    $client['name'] = $value['name'];
                    $client['sponsorUsername'] = $value['sponsor_username'] ? $value['sponsor_username'] : "-";
                    $mainLeaderUsername = Tree::getMainLeaderUsername($client);
                    $client['mainLeaderUsername'] = $mainLeaderUsername ? $mainLeaderUsername : "-";

                    $client['rankDisplay'] = $rankDisplay[$value['rank_id']] ? $rankDisplay[$value['rank_id']] : $rankDisplay[1];
                    $client['country'] = $value['country_name'] ? $value['country_name'] : "-";
                    $client['disabled'] = $value['disabled'] == 1 ? "Yes" : "No";
                    $client['suspended'] = $value['suspended'] == 1 ? "Yes" : "No";
                    $client['freezed'] = $value['freezed'] == 1 ? "Yes" : "No";
                    $client['lastLogin'] = $value['last_login'] == "0000-00-00 00:00:00" ? "-" : $value['last_login'];
                    $client['lastLoginIp'] = $value['last_login_ip'];
                    $client['createdAt'] = $value['created_at'];

                    $clientList[] = $client;
                }

            if($params['step'] == 1){
                $data['memberList'] = $clientList;
                $data['totalPage'] = ceil($totalRecords / $limit[1]);
                $data['pageNumber'] = $pageNumber;
                $data['totalRecord'] = $totalRecords;
                $data['numRecord'] = $limit[1];
                // $data['countryList'] = $db->get('country', null, 'id, name');

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);

            }else if($params['step'] == 2){


                foreach ($clientList as $key => $value) {
                    $client_id = $value['clientID'];

                    $insertParams = array('clientId' => $client_id,
                                          'blockedList' => $params['blockedList']);

                    $returnResult = Self::lockAccount($insertParams);
                    if($returnResult['status'] != 'ok'){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => $returnResult['statusMsg'], 'data' => '');
                    }
                }

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => '');
            }
        }

        public function getPaymentMethodList($params){
            
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $tableName      = "mlm_payment_method";

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;

            // Get the limit.
            $limit              = General::getLimit($pageNumber);
            $searchData         = $params['inputData'];
            
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'paymentType':
                            if($dataValue != "all"){
                                $db->where('payment_type', $dataValue);
                            }
                            break;
                            
                        case 'status':
                            if($dataValue != ""){
                                $db->where('status', $dataValue);
                            }   
                            break;
                            
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("ID", "ASC");
            $result = $db->get($tableName, $limit, "ID, credit_type, status, min_percentage, max_percentage, payment_type");

            $totalRecord = $copyDb->getValue($tableName, "count(*)");

            if (!empty($result)) {
                foreach($result as $value) {
                    $temp['ID']             = $value['ID'];
                    $temp['paymentType']    = $value['payment_type'];
                    $temp['creditType']     = $value['credit_type'];
                    $temp['minPercentage']  = $value['min_percentage'];
                    $temp['maxPercentage']  = $value['max_percentage'];
                    $temp['status']         = $value['status'];
                    // $temp['createdAt']      = $value['created_at'];

                    $paymentSetting[] = $temp;
                }

                // $totalRecords = $copyDb->getValue($tableName, "count(*)");
                $data['settingList']  = $paymentSetting;
                $data['totalPage']   = ceil($totalRecord/$limit[1]);
                $data['pageNumber']  = $pageNumber;
                $data['totalRecord'] = $totalRecord;
                $data['numRecord']   = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            }

            else
            {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function getPaymentMethodDetails($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00104"][$language] /* Please Select Payment Setting */, 'data'=> '');

            $db->where('ID', $id);
            $result = $db->getOne("mlm_payment_method", "ID, status, credit_type, min_percentage, max_percentage, payment_type"); //, role_id as roleID

            if (empty($result)) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid Setting. */, 'data'=>"");

            foreach ($result as $key => $value) {
                $settingDetail[$key] = $value;
            }

            $data['settingDetail'] = $settingDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editPaymentMethod($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id       = trim($params['id']);
            $credit_type    = trim($params['credit_type']);
            $payment_type = trim($params['payment_type']);
            $min_percentage = trim($params['min_percentage']);
            $max_percentage   = trim($params['max_percentage']);
            $status   = trim($params['status']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00514"][$language] /* method ID does not exist */, 'data'=>"");

            if(strlen($credit_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00510"][$language] /* Credit cannot be empty */, 'data'=>"");

            if(strlen($payment_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00509"][$language] /* Payment Type cannot be empty */, 'data'=>"");

            if(strlen($min_percentage) == 0 || !is_numeric($min_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00511"][$language]/* Please Enter Min Percentage */, 'data'=>"");

            if(strlen($max_percentage) == 0 || !is_numeric($max_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00512"][$language] /* Please Enter Max Percentage */, 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00117"][$language] /* Please Select a Status */, 'data'=>"");

            $db->where('id', $id);
            $result = $db->getOne('mlm_payment_method');

            if (!empty($result)) {
                $fields    = array("credit_type", "status", "min_percentage", "max_percentage", "payment_type");
                $values    = array($credit_type, $status, $min_percentage, $max_percentage, $payment_type);

                $arrayData = array_combine($fields, $values);
                $db->where('id', $id);
                $db->update("mlm_payment_method", $arrayData);

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00179"][$language] /* Admin Profile Successfully Updated */, 'data' => "");

            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00118"][$language] /* Invalid Admin */, 'data'=>"");
            }
        }

        public function deletePaymentMethod($params){
            $db = MysqliDb::getInstance();

            $id = trim($params['id']);

            if(strlen($id) == 0)
                return array('status' => "error", 'code' => '1', 'statusMsg' => $translations["E00721"][$language], 'data'=>"");
            
            $db->where('id', $id);
            $result = $db->get('mlm_payment_method', 1);
            
            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('mlm_payment_method');
                
                if($result) {
                    return Self::getPaymentMethodList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00571"][$language], 'data' => '');
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00723"][$language], 'data'=>"");
            }
        }

        public function getPaymentSettingDetails() {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $creditID = $db->subQuery();
            $creditID->where('name', 'isWallet');
            $creditID->where('value', 1);
            $creditID->getValue('credit_setting', 'credit_id', null);

            $db->where('id', $creditID, 'IN');
            $creditResult = $db->getValue("credit", "name", null);

            if(empty($creditResult)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00195"][$language] /* Invalid credit type */, 'data' => "");
            }

            $data["creditData"] = $creditResult;

            $db->where('payment', 1);
            $paymentTypeResult = $db->getValue("mlm_modules", "name", null);

            if(empty($paymentTypeResult)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00724"][$language] /* Invalid modules */, 'data' => "");
            }

            $data["paymentType"] = $paymentTypeResult;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function addPaymentMethod($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $payment_type   = trim($params['paymentType']);
            $credit_type    = trim($params['creditType']);
            $min_percentage = trim($params['minPercentage']);
            $max_percentage = trim($params['maxPercentage']);
            $status         = trim($params['status']);

            if(strlen($payment_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00509"][$language] /* Payment type cannot be empty */, 'data'=>"");

            if(strlen($credit_type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00510"][$language] /* Credit type cannot be empty */, 'data'=>"");

            if(strlen($min_percentage) == 0 || !is_numeric($min_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00511"][$language]/* Please Enter Min Percentage */, 'data'=>"");

            if(strlen($max_percentage) == 0 || !is_numeric($max_percentage))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00512"][$language] /* Please Enter Max Percentage */, 'data'=>"");

            if(strlen($status) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00110"][$language] /* Please Choose a Status */, 'data'=>"");

            $db->where('payment_type', $payment_type);
            $db->where('credit_type', $credit_type);
            
            $result = $db->get('mlm_payment_method');
            if (!empty($result)){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00110"][$language] /* Setting already exist */, 'data'=>"");
            }else{

                $fields = array("credit_type", "status", "min_percentage","max_percentage", "payment_type", "created_at");
                $values = array($credit_type, $status, $min_percentage, $max_percentage, $payment_type, date("Y-m-d H:i:s"));
                $arrayData = array_combine($fields, $values);
                try{
                    $result = $db->insert("mlm_payment_method", $arrayData);
                }
                catch (Exception $e) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00513"][$language] /* Failed to add new payment method */, 'data'=>"");
                }

                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00178"][$language] /* Successfully Added */, 'data'=>"");
            }
        }

        public function getWithdrawalUnreadCount($userID){
                $db = MysqliDb::getInstance();
                $language       = General::$currentLanguage;
                $translations   = General::$translations;


                $db->where("id", $userID);
                $withdrawalUnreadCount =  $db->getValue("admin", "withdrawal_record_notification");
                $data["withdrawalUnreadCount"] = $withdrawalUnreadCount;

                $inbox = Client::getInboxUnreadMessage();
                $data['inboxUnreadMessage'] = $inbox["data"]["inboxUnreadMessage"];

                $db->where("admin_id", $userID);
                $db->where("type", 'kyc');
                $kycUnreadCount =  $db->getValue("admin_notification", "notification_count");
                $data["kycUnreadCount"] = $kycUnreadCount > 0 ? $kycUnreadCount : 0;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
  
        public function adminChangePassword($params,$userID) {
            $db = MysqliDb::getInstance();
            $language            = General::$currentLanguage;
            $translations        = General::$translations;

            $currentPassword     = $params['currentPassword'];
            $newPassword         = $params['newPassword'];
            $newPasswordConfirm  = $params['newPasswordConfirm'];

            // get password length
            $maxPass  = Setting::$systemSetting['maxPasswordLength'];
            $minPass  = Setting::$systemSetting['minPasswordLength'];
            // Get password encryption type

            if (empty($userID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            $idName        = 'Password';
            $msgFieldB     = $translations["A00120"][$language];
            $msgFieldS     = $translations["A00120"][$language];
            $maxLength     = $maxPass;
            $minLenght     = $minPass;

            if (empty($newPasswordConfirm)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."ConfirmError",
                                            'msg' => $translations["E00445"][$language] /* Please re-type */.  $msgFieldS
                                        );

            } else {
                if ($newPasswordConfirm != $newPassword) 
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."ConfirmError",
                                                'msg' => $translations["E00446"][$language] /* Re-type new  */ . " " . $msgFieldS . " no match."
                                            );
            }            

            // Retrieve the encrypted password based on settings
            $newEncryptedPassword = Setting::getEncryptedPassword($newPassword);
            // Retrieve the encrypted currentPassword based on settings
            $encryptedCurrentPassword = Setting::getEncryptedPassword($currentPassword);            

            $db->where('id', $userID);
            $result = $db->getOne('admin', 'password');
            if (empty($result)) 
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00443"][$language] /* Member not found. */, 'data'=> "");

            if (empty($currentPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "current".$idName."Error",
                                            'msg' => $translations["E00448"][$language] /* Please enter old  */ . " " . $msgFieldS
                                        );

            } else {

                // Check password encryption
                // We need to verify hash password by using this function
                if(!password_verify($currentPassword, $result['password'])) {
                    $errorFieldArr[] = array(
                                                'id'  => "current".$idName."Error",
                                                'msg' => $translations["E00449"][$language] /* Invalid  */ . " " . $msgFieldS
                                            );
                } 
            }

            if (empty($newPassword)) {
                $errorFieldArr[] = array(
                                            'id'  => "new".$idName."Error",
                                            'msg' => $translations["E00450"][$language] /* Please enter new  */ . " " . $msgFieldS
                                        );
            } else {
                if (strlen($newPassword) < $minLenght || strlen($newPassword) > $maxLength) {
                    $errorFieldArr[] = array(
                                                'id'  => "new".$idName."Error",
                                                'msg' => $msgFieldB . " " . $translations["E00451"][$language] /*  cannot be less than  */ . " " . $minLenght . " " . $translations["E00452"][$language] /*  or more than  */ . " " . $maxLength
                                            );

                }else if(!ctype_alnum($newPassword) || !preg_match('$\S*(?=\S*[a-z])(?=\S*[\d])\S*$', $newPassword)){

                    $errorFieldArr[] = array(
                        'id'  => "new".$idName."Error",
                        'msg' => $translations["M00190"][$language]
                    );

                }else {

                    //checking new password no match with current password
                    // We need to verify hash password by using this function
                    if(password_verify($newPassword, $result['password'])) {
                        $errorFieldArr[] = array(
                                                    'id'  => "new".$idName."Error",
                                                    'msg' => $translations["E00453"][$language] /* Please enter different  */ . " " . $msgFieldS
                                                );
                    }
                }
            }

            $data['field'] = $errorFieldArr;
            
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data' => $data);

            $updateData = array('password' => $newEncryptedPassword);
            $db->where('id', $userID);
            $updateResult = $db->update('admin', $updateData);
            if($updateResult)
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");

            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00455"][$language] /* Update failed. */, 'data' => "");
        }

        public function getBlockMemberLoginByCountryIP($params,$columnName){
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $countryList = $db->get('country', null, "id,$columnName, name");

            foreach ($countryList as $key => $value) {
                if ($value[$columnName]==1){
                    $value['availabilityDisplay']='Blocked';    
                }else{
                    $value['availabilityDisplay']='Enabled';
                }
                $countryListOutput[]=$value;
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>'', 'data' => $countryListOutput);
        }

        public function setBlockMemberLoginByCountryIP($params,$columnName=''){
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $countryIDAry=$params['checkedIDs'];
            $status=$params['status'];

            $updateData = array(
                                    "$columnName" => $status
                                );
            $db->where('id',$countryIDAry,'IN');
            $res=$db->update('country',$updateData);

            if($res){
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["M01131"][$language], 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' =>$translations["M01441"][$language], 'data' => '');
            }
        }

        public function getBlockMemberLoginByCountryIPandTree($params){
            $db = MysqliDb::getInstance();
            $language        = General::$currentLanguage;
            $translations    = General::$translations;
            
            $username=$params['username'];
            $db->where('username',$username);
            $clientID=$db->getValue('client','ID');
            if(!$clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00227"][$language], 'data' =>'' );
            }

            $countryList = $db->map('iso_code2')->ArrayBuilder()->get('country', null, "id,iso_code2,name,'Enabled' AS availabilityDisplay");
            $db->where('client_id',$clientID);
            $countryCodeList = $db->map('country_code')->ArrayBuilder()->get('client_country_ip_block', null, "country_code,blocked, (SELECT name FROM country WHERE country.iso_code2=client_country_ip_block.country_code ) AS name");

            foreach ($countryCodeList as $countryCode => $value) {
                if ($value['blocked']==1){
                    $countryList[$countryCode]['availabilityDisplay']='Blocked';    
                }else{
                    $countryList[$countryCode]['availabilityDisplay']='Enabled';
                }
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>'', 'data' => $countryList);
        }
        
        public function setBlockMemberLoginByCountryIPandTree($params,$columnName=''){
            $db = MysqliDb::getInstance();

            $language        = General::$currentLanguage;
            $translations    = General::$translations;

            $username=$params['username'];
            $countryIDAry=$params['checkedIDs'];
            $status=$params['status'];

            //blocks country IP bases on username's downline

            $db->where('username',$username);
            $clientID=$db->getValue('client','ID');

            if(!$clientID) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00227"][$language], 'data' =>'' );
            }
            $downlines = Tree::getSponsorTreeDownlines($clientID);
            

            $db->where('id',$countryIDAry,'IN');
            $countryCodeList = $db->get('country', null, "id,iso_code2, name");

            foreach ($countryCodeList as $key => $value) {


                $country_code=$value['iso_code2'];

                // return $downlines;
                foreach ($downlines as $key => $downlineID) {
                    //Should only work when both columns are a unique key
                    $db->rawQuery("INSERT INTO client_country_ip_block (client_id, country_code, blocked) VALUES($downlineID,'$country_code',$status) ON DUPLICATE KEY UPDATE blocked = VALUES(blocked)");
                }

            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["M01131"][$language], 'data' => '');
        }

        public function getCreditType($params,$setting) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $isShowMainWallet = $params['isShowMainWallet'];

            if($setting){
	            $db->where('name',$setting);
	            $db->where('value',0,"!=");
	            $res = $db->get('credit_setting',null,'credit_id');
	            $creditIDArray = array();
	            foreach ($res as $row) {
	                $creditIDArr[] = $row['credit_id'];
	            }
            }

            $db->where('name','isWallet');
            $db->where('value',1);
            $res = $db->get('credit_setting',null,'credit_id');
            $creditIDArray = array();
            foreach ($res as $row) {
            	if($creditIDArr && in_array($row["credit_id"], $creditIDArr)){
        			$creditIDArray[] = $row['credit_id'];
            	}else if(!$creditIDArr){
            		$creditIDArray[] = $row['credit_id'];
            	}
                
            }

            $creditArray = array();

            if ($isShowMainWallet) {
                $creditArray['cashCredit'] = $translations['M01496'][$language]; // Add RMB wallet
            }

            if (count($creditIDArray) > 0) {
                $db->where('id',$creditIDArray,'IN');
                $res = $db->get('credit',null,'name,admin_translation_code');

                foreach ($res as $row) {
                    $creditArray[$row['name']] = $translations[$row['admin_translation_code']][$language];
                }
            }

            $data = array(
                'creditArray' => $creditArray
            );

            return array('status'=>'ok','code'=>0,'statusMsg'=>'','data'=>$data);
        }

        public function getPagePermission($params,$userID){
        	$db = MysqliDb::getInstance();

        	$filePath = $params['filePath'];

            $db->where('name','Master Admin');
            $masterAdminRoleId = $db->getValue('roles','id');

        	$db->where("id",$userID);
        	$roleID = $db->getValue("admin","role_id");

        	$db->where("file_path",$filePath);
            $copyDb = $db->copy();
        	$premissionIDArr = $db->map('id')->get("permissions",NULL,"id");
            $masterAdminPremissionIDArr = $copyDb->map('id')->get("permissions",NULL,"id,name");

            if($roleID != $masterAdminRoleId){
            	$db->where("role_id",$roleID);
            	$db->where("permission_id",$premissionIDArr,"IN");
            	$db->where("disabled",0);
            	$res = $db->map('id')->get("roles_permission",NULL,"id,(SELECT name FROM permissions where id = permission_id) AS name");
            }else{
                $res = $masterAdminPremissionIDArr;
            }

        	$data['permission'] = $res;
    		return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);        	
        }

        public function getAgentID($adminID){
            $db = MysqliDb::getInstance();

            $db->where("admin_id", $adminID);
            $agentID = $db->getValue("admin_agent", "leader_id");

            return $agentID;
        }

        public function updateRebatePercentage($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $rebatePercentage = $params['rebatePercentage'];
            $monthPeriod     = $params['monthPeriod'];
            $activeDate      = trim($params['activeDate']);
            $currentDate     = date("Y-m-01");

            if(empty($monthPeriod)){
                $errorFieldArr[] = array(
                                                    'id'  => "activeDateError",
                                                    'msg' => $translations['E00156'][$language]
                                                );
            }

            if(empty($activeDate)){
                $errorFieldArr[] = array(
                                                    'id'  => "activeDateError",
                                                    'msg' => $translations['E00156'][$language]
                                                );
            }

            $activeDate = strtotime($monthPeriod."-".$activeDate);
            $activeDate = date('Y-m-d',$activeDate);

            $bonusID = $db->subQuery();
            $bonusID->where('name', 'rebateBonus');
            $bonusID->get('mlm_bonus', null, 'id');
            $db->where('bonus_id', $bonusID, 'in');
            $db->where('name', 'setRebatePercentage');
            $db->where('type', 'Rebate Type');
            $percentageRes = $db->getOne('mlm_bonus_setting','value,reference');
            $minPercentage = $percentageRes['value'];
            $maxPercentage = $percentageRes['reference'];

            if(!is_numeric($rebatePercentage) || empty($rebatePercentage)){
                $errorFieldArr[] = array(
                                                    'id'  => "rebatePercentageError",
                                                    'msg' => $translations['E00125'][$language]
                                                );
            }

            if($rebatePercentage < $minPercentage || $rebatePercentage > $maxPercentage){

                $errorMsg = str_replace(array('%%min%%','%%max%%'), array($minPercentage,$maxPercentage), 'Rebate Percentage cannot less than %%min%% and cannot more than %%max%%.');

                $errorFieldArr[] = array(
                                                    'id'  => "rebatePercentageError",
                                                    'msg' => $errorMsg
                                                );
            }

            if(strtotime($activeDate) < strtotime($currentDate)){
                $errorFieldArr[] = array(
                                                    'id'  => "activeDateError",
                                                    'msg' => $translations['E00156'][$language]
                                                );
            }

            $data['field'] = $errorFieldArr;
            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);

            $insertData = array(
                "unit_price"    => Setting::setDecimal($rebatePercentage,2),
                "type"          => "Rebate Percentage",
                "creator_id"    => $db->userID,
                "creator_type"  => $db->userType,
                "created_at"    => $db->now(),
                "actived_date"  => $activeDate,
            );

            $db->insert('mlm_unit_price',$insertData);

            return array('status'=>'ok','code'=>0,'statusMsg'=>'','data'=>$data);
        }

        public function getRebatePercentageList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            $searchData      = $params['inputData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            //Get the limit.
            $limit           = General::getLimit($pageNumber);
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];


            $column         = array(
                "id",
                "unit_price as percentage",
                "creator_id",
                "(SELECT username From admin where `admin`.`id` = creator_id) AS creator_username",
                "created_at",
                "actived_date",
            );
            $db->orderBy('created_at','DESC');
            $copyDb = $db->copy();
            $rebatePercentageList = $db->get("mlm_unit_price",$limit,$column);
            foreach ($rebatePercentageList as &$row) {

                $row['created_at']    = $row['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($row['created_at'])) : "-";
                $row['actived_date']    = $row['actived_date'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($row['actived_date'])) : "-";
                $row['percentage'] = Setting::setDecimal($row['percentage'],2);
            }

            $totalRecord                    = $copyDb->getValue("mlm_unit_price", "count(*)");
            $data['rebatePercentageList']   = $rebatePercentageList;
            $data['totalPage']              = ceil($totalRecord/$limit[1]);
            $data['pageNumber']             = $pageNumber;
            $data['totalRecord']            = $totalRecord;
            $data['numRecord']              = $limit[1];


            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00616"][$language], 'data' => $data);
        }

        public function adminSetAutoWithdrawal($params,$site){

            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientID      = trim($params['clientID']);
            $withdrawalType= trim($params['withdrawalType']);
            $creditType    = trim($params['creditType']);
            $walletAddress = trim($params['walletAddress']);
            $accountHolder = trim($params['accountHolderName']);
            $bankID        = trim($params['bankID']);
            $accountNo     = trim($params['accountNo']);
            $province      = trim($params['province']);
            $branch        = trim($params['branch']);
            $status        = trim($params['status']);
            $removeSetting  = trim($params['removeSetting']);

            if (empty($clientID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00461"][$language] /* Member not found. */, 'data'=> "");

            if ($status != 0 && $status != 1)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00396"][$language] /* Invalid Status */, 'data'=> "");


            // if($status == 1 || $removeSetting){
            	// set previous active to inactive
                $db->where('client_id',$clientID);
                $db->where('status','Active');
                $check=$db->get('mlm_client_wallet_address',null,'id');
                if ($check){
                    foreach ($check as $checkKey => $checkValue) {
                        $updateData = array(
                            "status" => "Inactive",
                        );
                        $db->where('id',$checkValue['id']);
                        $db->update('mlm_client_wallet_address', $updateData);
                    }
                }
                // set previous active to inactive
                $db->where('client_id',$clientID);
                $db->where('status','Active');
                $check=$db->get('mlm_client_bank',null,'id');
                if ($check){
                    foreach ($check as $checkKey => $checkValue) {
                        $updateData = array(
                            "status" => "Inactive",
                        );
                        $db->where('id',$checkValue['id']);
                        $db->update('mlm_client_bank', $updateData);
                    }
                }

            // }

            if($removeSetting){
            	$db->where('client_id',$clientID);
            	$db->where("name","isAutoWithdrawal");
            	$id = $db->getValue("client_setting","id");

            	if(!$id) return array('status' => "error", 'code' => 1, 'statusMsg' => "No record remove" , 'data' => "");

            	$db->where("id",$id);
            	$db->delete("client_setting");

            	return array('status' => "ok", 'code' => 0, 'statusMsg' => "Withdrawal Type is remove", 'data' => "");
            }

            if($withdrawalType == 'crypto'){

                // $acceptCoinType=json_decode(Setting::$systemSetting['cryptoCoinType']);
                $acceptCoinType = Client::getCryptoCredit(true, false);
                
                if(!trim($creditType) || !array_key_exists($creditType, $acceptCoinType)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'creditTypeError',
                                                'msg' => $translations["E00747"][$language]
                                            );
                }

                if(!$walletAddress || $walletAddress == ""){
                    $errorFieldArr[] = array(
                                                'id'  => 'walletAddressError',
                                                'msg' => $translations["M01941"][$language]/* Wallet Address cannot be empty. */
                                            );
                }
                else if(strlen($walletAddress)<30){
                    $errorFieldArr[] = array(
                                                'id'  => 'walletAddressError',
                                                'msg' => $translations["M01989"][$language]/* Enter a valid wallet address */
                                            );
                }

                if($errorFieldArr){
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
                }

                $insertData = array(
                    "client_id"      => $clientID,
                    "credit_type"      => $creditType,
                    "info"      => $walletAddress,
                    "created_at"      => date("Y-m-d H:i:s"),
                    "status" => 'Active'
                );
                $insertClientWalletResult = $db->insert("mlm_client_wallet_address", $insertData);

                $reference = $creditType;
                
                // Failed to insert client bank account
                if (!$insertClientWalletResult)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00817"][$language] /* Failed to add wallet address. */, 'data' => "");

            }else if($withdrawalType == 'bank' ){

                if (empty($accountHolder))
                    $errorFieldArr[] = array(
                                                'id'  => "accHolderNameError",
                                                'msg' => $translations["E00462"][$language] /* Please enter account holder name. */
                                            );
                if (empty($bankID))
                    $errorFieldArr[] = array(
                                                'id'  => "bankTypeError",
                                                'msg' => $translations["E00463"][$language] /* Please enter a bank. */
                                            );
                if (empty($accountNo))
                    $errorFieldArr[] = array(
                                                'id'  => "accountNoError",
                                                'msg' => $translations["E00464"][$language] /* Please enter account number. */
                                            );
                if (empty($province))
                    $errorFieldArr[] = array(
                                                'id'  => "provinceError",
                                                'msg' => $translations["E00465"][$language] /* Please enter province. */
                                            );
                if (empty($branch))
                    $errorFieldArr[] = array(
                                                'id'  => "branchError",
                                                'msg' => $translations["E00466"][$language] /* Please enter branch. */
                                            );

                $data['field'] = $errorFieldArr;
                if($errorFieldArr)
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00469"][$language] /* Data does not meet requirements. */, 'data' => $data);

                $insertClientBankData = array(
                                            "client_id"      => $clientID,
                                            "bank_id"        => $bankID,
                                            "account_no"     => $accountNo,
                                            "account_holder" => $accountHolder,
                                            "created_at"     => $db->now(),
                                            "status"         => 'Active',
                                            "province"       => $province,
                                            "branch"         => $branch

                                         );

                $insertClientBankResult  = $db->insert('mlm_client_bank', $insertClientBankData);

                    // Failed to insert client bank account
                    if (!$insertClientBankResult)
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00470"][$language] /* Failed to add bank account. */, 'data' => "");

                
                $reference = $bankID;
            }

            // $isFirstTimeAddWithdrawalOption = $this->checkIsFirstTimeAddWithdrawalOption($clientID);

            $rebuildParams["onOffOption"] = $status;
            $rebuildParams["withdrawalOption"] = $withdrawalType;
            $rebuildParams["reference"] = $reference;
            Self::setAutoWithdrawal($rebuildParams,$site,$clientID);


            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00599"][$language], 'data' => "");

        }

        function setAutoWithdrawal($params,$site,$userID){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $onOffValue = $params["onOffOption"]; // (0/1)
            $type       = $params["withdrawalOption"]; // (bank/crypto)
            $reference  = $params["reference"];

            if($site=="Member"){

                if($onOffValue == "0"){
                    $errorFieldArr[] = array(
                                        'id'  => 'onOffOptionError',
                                        'msg' => 'Cannot Off Auto Withdrawal.' /* Invalid value. */
                                    );
                }
            }

            $clientID = $userID;
                
            /* check user input */
            if($onOffValue != "0" && $onOffValue != "1")
            {
                $errorFieldArr[] = array(
                                        'id'  => 'onOffOptionError',
                                        'msg' => $translations["E00125"][$language] /* Invalid value. */
                                    );       
            }
            if($onOffValue == "1"){
                if(empty($type)) {
                $errorFieldArr[] = array(
                                        'id'  => 'withdrawalOptionError',
                                        'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                    );
                }
                if(empty($reference)) {
                    $errorFieldArr[] = array(
                                            'id'  => 'referenceError',
                                            'msg' => $translations["E00261"][$language] /* This field cannot be empty */
                                        );
                }
            }
            // else {
            //     $type = "";
            //     $reference = "";
            // }
            
            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00264"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            if($onOffValue == "1"){
                /* deeper input checking */
                $db->where("client_id", $clientID);
                $db->where("status", "Active");

                if($type=="crypto"){
                    $tableName = "mlm_client_wallet_address";
                    $columnName = "credit_type";
                }
                else { //if bank
                    $tableName = "mlm_client_bank";
                    $columnName = "bank_id";
                }

                $db->where($columnName, $reference);
                $deepCheckRes = $db->getValue($tableName, "id");

                if(!$deepCheckRes){
                    $errorFieldArr[] = array(
                                                'id'  => 'referenceError',
                                                'msg' => $translations["E00130"][$language] /* Data does not meet requirements */
                                            );
                }
                if($errorFieldArr) {
                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00264"][$language] /* Data does not meet the requirements */, 'data' => $data);
                }
            }
            

            $clientSettingName = "isAutoWithdrawal";
            $clientSettingData = array(
                "name"      => $clientSettingName, 
                "value"     => $onOffValue, 
                "type"      => $type, 
                "reference" => $reference,
                "client_id" => $clientID
            );

            // insert activity log before insert into client_setting
            $titleCode    = 'T00003';
            $activityCode = 'L00026';
            $activityType = 'Set Auto Withdrawal';

            $activityRes = Activity::insertActivity($activityType, $titleCode, $activityCode, $clientSettingData, $clientID);

            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00269"][$language] /* Failed to insert activity */, 'data'=> "");

            $db->where('client_id', $clientID);
            $db->where('name', $clientSettingName);
            $copyDb = $db->copy();
            $check = $db->getValue("client_setting", "id");

            if($check){
                /* if have record */
                if($onOffValue == 0 && !$type) $clientSettingData = array("value"     => $onOffValue);
                $copyDb->where("id", $check);
                $result = $copyDb->update('client_setting', $clientSettingData);
            }
            else{
                /* if no record */
                $db->insert("client_setting", $clientSettingData);
            }

            $data['setDefaultWithdrawal'] = $type;

            return array("status" => "ok", "code" => 0, "statusMsg" => $translations["E00708"][$language], "data" => $data);
        }

        function getAutoWithdrawalData($params,$userID,$site){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $clientID = $userID;
            if($site == 'Admin') $clientID = $params['clientID'];

            $db->where("client_id",$clientID);
            $db->where("status", "Active");
            $copyDb = $db->copy();

            /* get bank data */
            $bankData = $db->get("mlm_client_bank",NULL, "
                bank_id,
                account_no,
                account_holder,
                province,
                branch,
                (SELECT country_id FROM mlm_bank WHERE id = bank_id) AS country_id,
                (SELECT name FROM mlm_bank WHERE id = bank_id) AS bank_name,
                (SELECT translation_code FROM mlm_bank WHERE id = bank_id) AS bank_display
                ");
            if(!empty($bankData)) {
                foreach ($bankData as $key => &$bankDataRow) {
                    $bankDataRow["bank_display"] = $translations[$bankDataRow["bank_display"]][$language];
                    $infoDetails[$bankDataRow['bank_id']] = $bankDataRow["bank_display"];
                }
                $data['bankData'] = $bankData;
            }
            /* END get bank data */

            /* get wallet data */
            $cryptoCreditListDisplay = Client::getCryptoCredit(true);
            $walletData = $copyDb->get("mlm_client_wallet_address",NULL, "
                credit_type,
                info
                ");
            if(!empty($walletData)){
                foreach ($walletData as $key => &$walletDataRow) {
                    $infoDetails[$walletDataRow['credit_type']] = $walletDataRow['info'];

                    $walletDataRow["creditTypeDisplay"] = $cryptoCreditListDisplay[$walletDataRow['credit_type']];
                }
            }

            $db->where('name','isAutoWithdrawal');
            $db->where('client_id',$clientID);
            $autoWithdrawalSetting = $db->getOne('client_setting','value,type,reference');

            if($autoWithdrawalSetting['value'] == 1){
                $onOffSetting = $translations['M01160'][$language];
                $onOffValue = $autoWithdrawalSetting['value'];
            }else{
                $onOffSetting = $translations['M01161'][$language];
                $onOffValue = $autoWithdrawalSetting['value'];
            }

            if($autoWithdrawalSetting['type'] == 'bank'){
                $withdrawalTypeDisplay = $translations['M00467'][$language];
                $withdrawalType = $autoWithdrawalSetting['type'];

            }else if($autoWithdrawalSetting['type'] == 'crypto'){
                $withdrawalTypeDisplay = $translations['M02069'][$language];
                $withdrawalType = $autoWithdrawalSetting['type'];
            }

            $withdrawalInfo = $infoDetails[$autoWithdrawalSetting['reference']];

            $withdrawalSetting['onOffSetting'] = $onOffSetting;
            $withdrawalSetting['withdrawalType'] = $withdrawalType?$withdrawalType:"-";
            $withdrawalSetting['withdrawalTypeDisplay'] = $withdrawalTypeDisplay?$withdrawalTypeDisplay:"-";
            $withdrawalSetting['withdrawalInfo'] = $withdrawalInfo?$withdrawalInfo:"-";
            $withdrawalSetting['settingReference'] = $autoWithdrawalSetting['reference']?$autoWithdrawalSetting['reference']:"-";
            $withdrawalSetting['onOffValue'] = $onOffValue?$onOffValue:0;

            // $getAutoWithdrawalData['']
            /* END get wallet data */

            $data['withdrawalSetting'] = $withdrawalSetting;
            $data['bankData'] = $bankData;
            $data['walletData'] = $walletData;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getSenderAddressListing($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            
            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $walletProviderSearchType  = $params["walletProviderSearchType"];
            if (!$seeAll) $limit = General::getLimit($pageNumber);

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'walletProvider':
                            if ($walletProviderSearchType == "match") {
                                $db->where('wallet_provider', $dataValue);
                            } elseif ($walletProviderSearchType == "like") {
                                $db->where('wallet_provider', $dataValue . "%", "LIKE");
                            }
                            break;
                            
                        case 'senderAddress':
                            $db->where('info', $dataValue);
                            break;

                        default :
                            break;    
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('type', 'deposit');
            $copyDb = $db->copy();
            $db->orderBy('setOn', 'DESC');
            $res = $db->get('mlm_client_wallet_address', $limit, 'client_id, info, wallet_provider, created_at AS setOn');

            if (!$res) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach ($res as $row) {
                $clientIDAry[$row['client_id']] = $row['client_id'];
            }

            if ($clientIDAry) {
                $db->where('id', $clientIDAry, 'IN');
                $clientAry = $db->map('id')->get('client', NULL, 'id, username, member_id, phone, email, last_login_ip, created_at');
            }

            foreach ($res as $value) {
                unset($senderAddress);
                $senderAddress['signUpDate'] = $clientAry[$value['client_id']]['created_at'] ? date($dateTimeFormat, strtotime($clientAry[$value['client_id']]['created_at'])) : '-';
                $senderAddress['memberID']   = $clientAry[$value['client_id']]['member_id'] ? : '-';
                $senderAddress['username']   = $clientAry[$value['client_id']]['username'] ? : '-';
                $senderAddress['phone']      = $clientAry[$value['client_id']]['phone'] ? : '-';
                $senderAddress['email']      = $clientAry[$value['client_id']]['email'] ? : '-';
                $senderAddress['lastLoginIp']    = $clientAry[$value['client_id']]['last_login_ip'] ? : '-';
                $senderAddress['senderAddress']  = $value['info'] ? : '-';
                $senderAddress['walletProvider'] = $value['wallet_provider'] ? : '-';
                $senderAddress['setOn'] = $value['setOn'] ? date($dateTimeFormat, strtotime($value['setOn'])) : '-';

                $senderAddressList[] = $senderAddress;
            }

            $totalRecord = $copyDb->getValue("mlm_client_wallet_address", "count(id)");

            $data['senderAddressList'] = $senderAddressList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
        }

        public function addPrivateGameStg($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $sellingQty = trim($params['sellingQty']);
            $sellingPrice = trim($params['sellingPrice']);
            $sellingStartTS = trim($params['sellingStartTS']);
            $sellingEndTS = trim($params['sellingEndTS']);
            $voucherExpiredTS = trim($params['voucherExpiredTS']);
            $voucherID = trim($params['voucherID']);
            $packageName = trim($params['packageName']);
            $gameRoundArr = $params['gameRoundArr'];
            $category = "private";
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Self::privateGmStgVerification($params,"add");
            if($result['status'] == 'error'){
                return $result;
            }
            $gameArr = $result['data']['gameArr'];
            $fristGameTS = $result['data']['fristGameTS'];

            //Default Code
            $newCode = 1000;

            $db->orderBy('code','DESC');
            $lastCode = $db->getValue('private_game','code');
            if($lastCode) $newCode = $lastCode+1;

            foreach ($gameArr as $gameTimeTS => $insertRow) {
                unset($insertData);
                $batchID = $db->getNewID();
                $insertData = array(
                    "product_category" => $category,
                    "code"       => $newCode,
                    "name"       => $packageName,
                    "winner" => $insertRow['winner'],
                    "status"     => "await",
                    "start_time" => date("Y-m-d H:i:s",$gameTimeTS),
                    "batch_id"  => $batchID,
                    "created_at" => $dateTime,
                );
                $db->insert('private_game',$insertData);

                $roundData[date("Y-m-d H:i:s",$gameTimeTS)]['winner'] = $insertRow['winner'];
            }

            $gameData['sellingTime']['value'] = date("Y-m-d H:i:s",$sellingStartTS);
            $gameData['sellingTime']['reference'] = date("Y-m-d H:i:s",$sellingEndTS);
            $gameData['sellingPrice'] = $sellingPrice;
            $gameData['sellingQty'] = $sellingQty;
            $gameData['voucherStg']['value'] = $voucherID;
            $gameData['voucherStg']['reference'] = date("Y-m-d H:i:s",$voucherExpiredTS);

            foreach ($gameData as $settingName => $settingValue) {
                unset($insertData);
                switch ($settingName) {
                    case 'voucherStg':
                    case 'sellingTime':
                        $insertData = array(
                            "private_game_code" => $newCode,
                            "name"              => $settingName,
                            "value"             => $settingValue['value'],
                            "reference"         => $settingValue['reference'],
                            "created_at"        => $dateTime
                        );
                        break;
                    
                    default:
                        $insertData = array(
                            "private_game_code" => $newCode,
                            "name"              => $settingName,
                            "value"             => $settingValue,
                            "created_at"        => $dateTime
                        );
                        break;
                }
                $db->insert('private_game_setting',$insertData);
            }
            $gameData['roundData'] = $roundData;

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }

            // insert activity log
            $activityData = array('admin' => $adminName,"gameStartDate"=>date('Y-m-d',$fristGameTS),"gameData"=>$gameData);

            $activityRes = Activity::insertActivity('Set Private Game Setting', 'T00050', 'L00070', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function getPrivateGameList($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            
            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if (!$seeAll) $limit = General::getLimit($pageNumber);

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {

                        default :
                            break;    
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('status','disabled',"!=");
            $db->groupBy('code');
            $copyDb = $db->copy();
            $db->orderBy('created_at', 'DESC');
            $res = $db->map('code')->get('private_game', $limit, 'code');
            if (!$res) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $db->where('status','disabled',"!=");
            $db->where('code',$res,"IN");
            $gameDetialRes = $db->get('private_game',null,'id,name,winner,code,status,start_time AS gameDate,updated_at,updater_id,created_at');
            foreach ($gameDetialRes as $gameDetialRow) {
                $updateIDArr[$gameDetialRow['updater_id']] = $gameDetialRow['updater_id'];
            }

            //Get Updater username
            if($updateIDArr){
                $db->where('id',$updateIDArr,"IN");
                $updaterData = $db->map('id')->get('admin',null,'id,username');
            }

            foreach ($gameDetialRes as $gameDetialRow) {
                $code = $gameDetialRow['code'];
                $gameData[$code]['totalWinner'] += $gameDetialRow['winner'];
                $gameData[$code]['name'] = $gameDetialRow['name'];
                $gameData[$code]['code'] = $code;

                $gameDetialRow['statusDisplay'] = General::getTranslationByName($gameDetialRow['status']);
                $gameDetialRow['updater'] = $updaterData[$gameDetialRow['updater_id']]?$updaterData[$gameDetialRow['updater_id']]:"-";
                unset($gameDetialRow['updater_id'],$gameDetialRow['code']);

                $gameData[$code]['gameArr'][] = $gameDetialRow;

                if($gameData[$code]['status'] == 'await') continue;
                $gameData[$code]['status'] = $gameDetialRow['status'];
                $gameData[$code]['statusDisplay'] = General::getTranslationByName($gameDetialRow['status']);
            }

            foreach ($res as $gameDate) {
                $gameDataArr[] = $gameData[$gameDate];
            }

            $totalRecord = $copyDb->get("private_game", null,"id");
            $totalRecord = COUNT($totalRecord);

            $data['gameDataArr'] = $gameDataArr;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
        }

        public function getPrivateGameDetail($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;

            $code = trim($params['code']);
            

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $db->where('status',"disabled","!=");
            $db->where('code',$code);
            $res = $db->get('private_game', null,'id as packageID,name,start_time,winner');
            if (!$res) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $db->where('private_game_code',$code);
            $pvtStgRes = $db->get('private_game_setting',null,'private_game_code,name,value,reference');
            foreach ($pvtStgRes as $pvtStgRow) {
                switch ($pvtStgRow['name']) {
                    case 'sellingTime':
                        $pvtGameDetail["sellingStart"] = date("d/m/Y",strtotime($pvtStgRow['value']));
                        $pvtGameDetail["sellingEnd"] = date("d/m/Y",strtotime($pvtStgRow['reference']));
                        break;

                    case 'voucherStg':
                        $pvtGameDetail["voucherID"] = $pvtStgRow['value'];
                        $pvtGameDetail["voucherExpired"] = date("d/m/Y",strtotime($pvtStgRow['reference']));
                        break;
                    
                    default:
                        $pvtGameDetail[$pvtStgRow['name']] = $pvtStgRow['value'];
                        break;
                }
            }

            foreach ($res as &$row) {
                $row['start_time'] = date("d/m/Y",strtotime($row['start_time']));
            }

            $pvtGameDetail['packageName'] = $res[0]['name'];
            $pvtGameDetail['gameRoundArr'] = $res;

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $pvtGameDetail);
        }

        public function editPrivateGameStg($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $sellingQty = trim($params['sellingQty']);
            $sellingPrice = trim($params['sellingPrice']);
            $sellingStartTS = trim($params['sellingStartTS']);
            $sellingEndTS = trim($params['sellingEndTS']);
            $voucherExpiredTS = trim($params['voucherExpiredTS']);
            $voucherID = trim($params['voucherID']);
            $packageName = trim($params['packageName']);
            $gameCode   = trim($params['gameCode']);
            $gameRoundArr = $params['gameRoundArr'];
            $category = "private";
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Self::privateGmStgVerification($params,"edit");
            if($result['status'] == 'error'){
                return $result;
            }
            $productID = $result['data']['productID'];
            $gameArr = $result['data']['gameArr'];

            $db->where('code',$gameCode);
            $db->where('status','await');
            $gameRes = $db->map('id')->get('private_game',null,'id,winner,status,name,start_time');
            foreach ($gameArr as $gameTimeTS => $insertRow) {
                $packageID = $insertRow['packageID'];
                if($packageID){
                    if($gameRes[$packageID]['status'] != 'await' || !$gameRes[$packageID]){
                        unset($gameRes[$packageID]);
                        unset($gameArr[$gameTimeTS]);
                        continue;
                    }

                    if($insertRow['winner'] != $gameRes[$packageID]['winner']){
                        $editData[$packageID]['winner']['old'] = $gameRes[$packageID]['winner'];
                        $editData[$packageID]['winner']['new'] = $insertRow['winner'];
                    }

                    if($packageName != $gameRes[$packageID]['name']){
                        $editData[$packageID]['name']['old'] = $gameRes[$packageID]['name'];
                        $editData[$packageID]['name']['new'] = $packageName;
                    }

                    if($gameTimeTS != strtotime($gameRes[$packageID]['start_time'])){
                        $editData[$packageID]['start_time']['old'] = $gameRes[$packageID]['start_time'];
                        $editData[$packageID]['start_time']['new'] = date('Y-m-d H:i:s',$gameTimeTS);
                    }

                    unset($gameRes[$packageID]);
                    unset($gameArr[$gameTimeTS]);
                }
            }

            // Update Edit Data
            foreach ($editData as $editID => $editRow) {
                unset($updateData);
                foreach ($editRow as $column => $columnVal) {
                    $updateData[$column] = $columnVal['new'];
                }
                $updateData['updater_id'] = $userID;
                $updateData['updated_at'] = $dateTime;
                $db->where('id',$editID);
                $db->update('private_game',$updateData);
            }

            if($gameRes){
                $db->where('id',array_keys($gameRes),"IN");
                $db->update('private_game',array("status"=>"disabled","updater_id"=>$userID,"updated_at"=>$dateTime));
            }

            //Insert New Game Data
            foreach ($gameArr as $gameTimeTS => $insertRow) {
                unset($insertData);
                $batchID = $db->getNewID();
                $insertData = array(
                    "product_category" => $category,
                    "code"       => $gameCode,
                    "name"       => $packageName,
                    "winner" => $insertRow['winner'],
                    "status"     => "await",
                    "start_time" => date("Y-m-d H:i:s",$gameTimeTS),
                    "batch_id"  => $batchID,
                    "created_at" => $dateTime,
                    "updated_at" => $dateTime,
                    "updater_id" => $userID,
                );
                $db->insert('private_game',$insertData);

                $roundData[date("Y-m-d H:i:s",$gameTimeTS)]['winner'] = $insertRow['winner'];
            }

            $db->where('private_game_code',$gameCode);
            $privateGameStg = $db->map('name')->get('private_game_setting',null,'name,private_game_code,value,reference');

            $gameData['sellingTime']['value'] = date("Y-m-d H:i:s",$sellingStartTS);
            $gameData['sellingTime']['reference'] = date("Y-m-d H:i:s",$sellingEndTS);
            $gameData['sellingPrice'] = $sellingPrice;
            $gameData['sellingQty'] = $sellingQty;
            $gameData['voucherStg']['value'] = $voucherID;
            $gameData['voucherStg']['reference'] = date("Y-m-d H:i:s",$voucherExpiredTS);

            //Update Game Setting
            foreach ($gameData as $settingName => $settingRow) {
                unset($updateData);
                switch ($settingName) {
                    case 'voucherStg':
                    case 'sellingTime':
                        if($settingRow['value'] != $privateGameStg[$settingName]['value']){
                            $editStgData[$settingName]['value']['old'] = $privateGameStg[$settingName]['value'];
                            $editStgData[$settingName]['value']['new'] = $settingRow['value'];
                            $updateData['value'] = $settingRow['value'];
                        }

                        if($settingRow['reference'] != $privateGameStg[$settingName]['reference']){
                            $editStgData[$settingName]['reference']['old'] = $privateGameStg[$settingName]['reference'];
                            $editStgData[$settingName]['reference']['new'] = $settingRow['reference'];
                            $updateData['reference'] = $settingRow['reference'];
                        }
                        break;
                    
                    default:
                        if($settingRow != $privateGameStg[$settingName]['value']){
                            $editStgData[$settingName]['value']['old'] = $privateGameStg[$settingName]['value'];
                            $editStgData[$settingName]['value']['new'] = $settingRow;
                            $updateData['value'] = $settingRow;
                        }
                        break;
                }
                if($updateData){
                    $db->where('private_game_code',$gameCode);
                    $db->where('name',$settingName);
                    $db->update('private_game_setting',$updateData);
                }
            }
            $gameDataLog['editData'] = $editData;
            $gameDataLog['rmData'] = $gameRes;
            $gameDataLog['newData'] = $gameArr;
            $gameDataLog['editStgData'] = $editStgData;

            $gameData['roundData'] = $roundData;

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }

            // insert activity log
            $activityData = array('admin' => $adminName,"gameCode"=>$gameCode,"gameData"=>$gameData,"gameDataLog"=>$gameDataLog);

            $activityRes = Activity::insertActivity('Edit Private Game Setting', 'T00051', 'L00071', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function privateGmStgVerification($params,$type = "add"){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $sellingQty = trim($params['sellingQty']);
            $sellingPrice = trim($params['sellingPrice']);
            $sellingStartTS = trim($params['sellingStartTS']);
            $sellingEndTS = trim($params['sellingEndTS']);
            $voucherExpiredTS = trim($params['voucherExpiredTS']);
            $voucherID = trim($params['voucherID']);
            $packageName = trim($params['packageName']);
            $gameCode   = trim($params['gameCode']);
            $gameRoundArr = $params['gameRoundArr'];
            $dateTime = date('Y-m-d H:i:s');
            $category = "private";

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $db->where('active_at',$dateTime,"<=");
            $db->where('status','Active');
            $db->where('ref_id',$category);
            $db->orderBy('active_at','ASC');
            $db->orderBy('id','ASC');
            $settingRes = $db->map('name')->get("system_settings_admin",NULL,"name,value");
            $timeInterval = $settingRes['gameTimeInterval'];

            if(!$packageName){
                $errorFieldArr[] = array(
                    'id'  => 'packageNameError',
                    'msg' => $translations['E00998'][$language] /*Invalid Package Name.*/
                );
            }

            //Check Selling Qty
            if(!$sellingQty || $sellingQty <= 0 || !is_numeric($sellingQty) || (int)$sellingQty != $sellingQty){
                $errorFieldArr[] = array(
                    'id'  => 'sellingQtyError',
                    'msg' => $translations['E00999'][$language] /*Invalid Quantity.*/
                );
            }

            //Check Selling Price
            if(!$sellingPrice || $sellingPrice <= 0 || !is_numeric($sellingPrice) || (int)$sellingPrice != $sellingPrice){
                $errorFieldArr[] = array(
                    'id'  => 'sellingPriceError',
                    'msg' => $translations['E00910'][$language]
                );
            }

            //Check Game Round
            if(!$gameRoundArr){
                $errorFieldArr[] = array(
                    'id'  => 'gameRoundArrError',
                    'msg' => $translations['E01000'][$language] /*Invalid Game Details.*/
                );
            }else{
                foreach ($gameRoundArr as $gameRoundKey => $gameRoundRow) {
                    if($gameArr[$gameRoundRow['gameTimeTS']]){
                        $errorFieldArr[] = array(
                            'id'  => 'gameTimeTS'.($gameRoundKey+1).'Error',
                            'msg' => $translations['E01001'][$language] /*Invalid Game Time.*/
                        );
                    }
                    $gameArr[$gameRoundRow['gameTimeTS']]['winner'] = $gameRoundRow['winner'];
                    $gameArr[$gameRoundRow['gameTimeTS']]['columnKey'] = $gameRoundKey;
                    $gameArr[$gameRoundRow['gameTimeTS']]['packageID'] = $gameRoundRow['packageID'];
                    if($gameRoundRow['packageID']){
                        $packageIDArr[$gameRoundRow['packageID']] = $gameRoundRow;
                    }
                }
                ksort($gameArr);
            }

            $totalWinner = 0;
            foreach ($gameArr as $gameTimeTS => &$gameRow) {

                //Check Game Date
                if(($lastTimeTs) && ($gameTimeTS < ($lastTimeTs+strtotime($timeInterval,0)))){
                    $errorFieldArr[] = array(
                        'id'  => 'gameTimeTS'.($gameRow['columnKey']+1).'Error',
                        'msg' => str_replace("%%timeInterval%%", $timeInterval, $translations['E01002'][$language]) /*Game Time Interval cannot less than %%timeInterval%%.*/
                    );
                }

                //Check Winner setting
                if(($gameRow['winner'] <= 0) || ((int)$gameRow['winner'] != $gameRow['winner']) || (!is_numeric($gameRow['winner']))){
                    $errorFieldArr[] = array(
                        'id'  => 'winner'.($gameRow['columnKey']+1).'Error',
                        'msg' => $translations['E01003'][$language] /*Invalid Winner Setting.*/
                    );
                }


                $totalWinner += $gameRow['winner'];
                $lastTimeTs = $gameTimeTS;
            }

            if($totalWinner > $sellingQty){
                $errorFieldArr[] = array(
                    'id'  => 'winner'.($gameRow['columnKey']+1).'Error',
                    'msg' => $translations['E01004'][$language] /*Winner cannot more than package quantity.*/
                );
            }

            $fristGameTS = MIN(array_keys($gameArr));
            $lastGameTS = MAX(array_keys($gameArr));

            //Check Selling Time Start
            if(!$sellingStartTS || !is_numeric($sellingStartTS)){
                $errorFieldArr[] = array(
                    'id'  => 'sellingStartTSError',
                    'msg' => $translations['E00505'][$language] /*Invalid Value.*/
                );
            }elseif($sellingStartTS > $sellingEndTS){
                $errorFieldArr[] = array(
                    'id'  => 'sellingStartTSError',
                    'msg' => "Selling Start time cannot late than End time."
                );
            }

            //Check Selling End Start
            if(!$sellingEndTS){
                $errorFieldArr[] = array(
                    'id'  => 'sellingEndTSError',
                    'msg' => $translations['E00505'][$language] /*Invalid Value.*/
                );
            }elseif($sellingEndTS >= $fristGameTS){
                $errorFieldArr[] = array(
                    'id'  => 'sellingEndTSError',
                    'msg' => $translations['E01006'][$language] /*Selling End time cannot late than first game time.*/
                );
            }

            if(!$voucherID){
                $errorFieldArr[] = array(
                    'id'  => 'voucherError',
                    'msg' => "Invalid Voucher"
                );
            }else{
                $db->where('id',$voucherID);
                $db->where('status','Active');
                if(!$db->has('voucher')){
                    $errorFieldArr[] = array(
                        'id'  => 'voucherError',
                        'msg' => "Invalid Voucher"
                    );
                }
            }

            // Check Voucher Expired Time
            if($voucherExpiredTS <= $lastGameTS){
                $errorFieldArr[] = array(
                    'id'  => 'voucherExpiredTSError',
                    'msg' => $translations['E01007'][$language] /*Voucher expired time cannot early than last game time.*/
                );
            }

            if($type == 'edit'){
                if(!$gameCode){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01008'][$language] /*Invalid Game ID.*/, 'data' => "");
                }

                //Check Package ID
                $db->where('code',$gameCode);
                $gameCodeRes = $db->map('id')->get('private_game',null,'id,status,winner,start_time');
                if(!$gameCodeRes){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01009'][$language] /*Invalid Package ID.*/, 'data' => "");
                }else{
                    foreach ($packageIDArr as $packageID => $packageIDRow) {
                        if(!$gameCodeRes[$packageID]){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E01009'][$language] /*Invalid Package ID.*/, 'data' => "");
                        }
                    }
                    foreach ($gameCodeRes as $gameCodeRow) {
                        if(in_array($gameCodeRow['status'], array("pending","completed"))){
                            $ranGameArr[$gameCodeRow['id']] = $gameCodeRow;
                        }
                    }
                }

                foreach ($ranGameArr as $ranGameID => $ranGameRow) {
                    if(!$packageIDArr[$ranGameID]){
                        return array('status' => "error", 'code' => 2, 'statusMsg' => "Game already ran, cannot edit", 'data' => "");
                    }else{
                        if($ranGameRow['winner'] != $packageIDArr[$ranGameID]['winner']){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => "Game already ran, cannot edit", 'data' => "");
                        }

                        if($ranGameRow['start_time'] != date("Y-m-d H:i:s",$packageIDArr[$ranGameID]['gameTimeTS'])){
                            return array('status' => "error", 'code' => 2, 'statusMsg' => "Game already ran, cannot edit", 'data' => "");
                        }
                    }
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            $data['gameArr'] = $gameArr;
            $data['fristGameTS'] = $fristGameTS;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function addEVoucher($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $name = trim($params['name']);
            $nameLanguages = $params['nameLanguages'];
            $descrLanguages = $params['descrLanguages'];
            $uploadImage = $params['uploadImage'];
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Admin::eVoucherVerification($params,"add");
            if($result['status'] == 'error'){
                return $result;
            }

            $translationCode = General::generateDynamicCode("W");

            // Get System Languages
            $db->where("disabled", 0);
            $languages = $db->map("language_code")->get("languages", NULL, "language_code, language");

            // Insert language_translation
            foreach($nameLanguages as $nameRow) {
                $defaultName = $name;
                $nameLanguagesList[$nameRow['languageType']] = $nameRow;
            }

            foreach($languages as $languagesRow) {
                if($nameLanguagesList[$languagesRow]['languageType'] == $languagesRow) {
                    $insertProductNameTrans = array(
                        "code" => $translationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $nameLanguagesList[$languagesRow]['content'],
                        "created_at" => $dateTime
                    );
                } else {
                    $insertProductNameTrans = array(
                        "code" => $translationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $defaultName,
                        "created_at" => $dateTime
                    );
                }
                $db->insert('language_translation',$insertProductNameTrans);
            }

            $descrTranslationCode = General::generateDynamicCode("W");

            foreach($descrLanguages as $descriptionRow) {
                if($descriptionRow['content'] && !$defaultDescription) $defaultDescription = $descriptionRow['content'];
                $descLanguagesList[$descriptionRow['languageType']] = $descriptionRow;
            }

            foreach($languages as $languagesRow) {
                if($descLanguagesList[$languagesRow]['languageType'] == $languagesRow) {
                    $insertProductDescrTrans = array(
                        "code" => $descrTranslationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $descLanguagesList[$languagesRow]['content'],
                        "created_at" => $dateTime
                    );
                } else {
                    $insertProductDescrTrans = array(
                        "code" => $descrTranslationCode,
                        "module" => "Inventory",
                        "language" => $languagesRow,
                        "site" => "Inventory",
                        "type" => "Dynamic",
                        "content" => $defaultDescription,
                        "created_at" => $dateTime
                    );
                }
                $db->insert('language_translation',$insertProductDescrTrans);                        
            }

            $imageGroupUniqueChar   = General::generateUniqueChar("voucher","image_name");
            $imageUniqueChar = General::generateUniqueChar("voucher","image_name");
            $imageAry = explode(".",$uploadImage['imgName']);
            $imageExt = end($imageAry);
            $storedImage = time()."_".$imageUniqueChar."_".$imageGroupUniqueChar.".".$imageExt;

            //Insert Voucher
            $insertData = array(
                "name" => $name,
                "translation_code" => $translationCode,
                "description" => $descrTranslationCode,
                "image_name" => $storedImage,
                "status" => "Active",
                "created_at" => $dateTime,
            );
            $db->insert('voucher',$insertData);

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }

            $data['imgName'] = $storedImage;

            // insert activity log
            $activityData = array('admin' => $adminName,"name"=>$name);

            $activityRes = Activity::insertActivity('Add Voucher', 'T00052', 'L00072', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function eVoucherVerification($params,$type = "add"){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $name = trim($params['name']);
            $voucherID = trim($params['voucherID']);
            $status = trim($params['status']);
            $nameLanguages = $params['nameLanguages'];
            $descrLanguages = $params['descrLanguages'];
            $uploadImage = $params['uploadImage'];
            $validStatus = array("Active","Inactive");
            $dateTime = date('Y-m-d H:i:s');

            $db->where('type','Upload Setting');
            $uploadSetting = $db->map('name')->get('system_settings',null,'name,value,reference');

            if(empty($name)){
                $errorFieldArr[] = array(
                    'id'  => "nameError",
                    'msg' => $translations['E00628'][$language] /* This field value is invalid. */
                );
            }

            // Check Name Language Field
            if(empty($nameLanguages)) {
                $errorFieldArr[] = array(
                    'id'  => "nameLanguagesError",
                    'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                );
            }else{
                // Check Name Language Field
                foreach($nameLanguages as $nameRow) {
                    if(!$nameRow["languageType"]) {
                        $errorFieldArr[] = array(
                            'id'  => "nameLanguagesError",
                            'msg' => $translations['E00602'][$language] /* Please Select Language. */
                        );
                    }

                    if(empty($nameRow["content"])) {
                        $errorFieldArr[] = array(
                            'id'  => "nameLanguagesError",
                            'msg' => $translations['E00635'][$language] /* Please Enter Name. */
                        );
                    }
                }
            }

            // Check Description Language Field
            if(empty($descrLanguages)) {
                $errorFieldArr[] = array(
                    'id'  => "descrLanguagesError",
                    'msg' => $translations['E00662'][$language] /* Please Enter Description. */
                );
            }else{
                // check description language field
                foreach($descrLanguages as $descriptionRow) {
                    if(empty($descriptionRow["content"])) {
                        $errorFieldArr[] = array(
                            'id'  => "descrLanguagesError",
                            'msg' => $translations['E00662'][$language] /* Please Enter Description. */
                        );
                    }
                }
            }

            if(!$uploadImage){
                $errorFieldArr[] = array(
                    'id'  => "imgError",
                    'msg' => $translations['E00556'][$language] /*Image fields cannot be empty.*/
                );
            }else{
                $validImageSet  = $uploadSetting['validImageType'];
                $validImageType = explode("#", $validImageSet['value']);
                $validImageSize = $validImageSet['reference'];
                $sizeMB         = $validImageSize / 1024 / 1024;

                if(empty($uploadImage["imgName"]) || empty($uploadImage["imgType"])) {
                    $errorFieldArr[] = array(
                        'id'  => "imgError",
                        'msg' => $translations["E00925"][$language] /* No file chosen */
                    );
                }

                if(empty($uploadImage['uploadType']) || !in_array($uploadImage['uploadType'], array('image'))) {
                    $errorFieldArr[] = array(
                        'id'  => "uploadTypeError",
                        'msg' => $translations["E00741"][$language] /* Invalid Type */
                    );
                }

                if($uploadImageRow["imgFlag"] && $type != 'add'){
                    if(!in_array($uploadImage["imgType"], $validImageType)) {
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => $translations["E00899"][$language] /* Uploaded file is not a valid image or video. */
                        );
                    }

                    if(!$uploadImage['imgSize'] || $uploadImage['imgSize'] > $validImageSize){
                        $errorFieldArr[] = array(
                            'id'  => "imgTypeError",
                            'msg' => str_replace("%%maxSize%%", $sizeMB, $translations["E00976"][$language]) /* File size too big. (Only allow max 3MB) */
                        );
                    }
                }
            }

            if($type == 'edit'){
                $db->where('id',$voucherID);
                $voucherRes = $db->getOne('voucher','status,translation_code,description,image_name');
                $voucherStatus = $voucherRes['status'];
                if(!$voucherStatus){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => "Invalid Voucher", 'data' => "");
                }

                if(!in_array($status, $validStatus)){
                    $errorFieldArr[] = array(
                        'id'  => "statusError",
                        'msg' => $translations['E00396'][$language]
                    );
                }
                if($voucherStatus != $status){
                    $db->where('voucher_id',$voucherID);
                    $db->where('redeem_status','pending');
                    $checkVoucher = $db->getValue('private_game_detail','id');
                    if($checkVoucher){
                        $errorFieldArr[] = array(
                            'id'  => "statusError",
                            'msg' => "Still got available voucher, failed to inactive voucher."
                        );
                    }
                }
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }
            $data['voucherRes'] = $voucherRes;
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function editEVoucher($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $voucherID = trim($params['voucherID']);
            $status = trim($params['status']);
            $name = trim($params['name']);
            $nameLanguages = $params['nameLanguages'];
            $descrLanguages = $params['descrLanguages'];
            $uploadImage = $params['uploadImage'];
            $dateTime = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $result = Admin::eVoucherVerification($params,"edit");
            if($result['status'] == 'error'){
                return $result;
            }
            $translationsCode = $result['data']['voucherRes']['translation_code'];
            $descriptionCode = $result['data']['voucherRes']['description'];
            $imageName = $result['data']['voucherRes']['image_name'];
            $oldStatus = $result['data']['voucherRes']['status'];

            //Update Name Language
            $db->where("code", $translationsCode);
            $translationList = $db->get("language_translation", NULL, "id, code, language, content");

            // Update language_translation
            foreach ($nameLanguages as $nameRow) {
                $defaultName = $name;
                $nameLanguagesList[$nameRow['languageType']] = $nameRow;
            }

            foreach ($translationList as $translationRow) {
                if($nameLanguagesList[$translationRow['language']]['languageType'] == $translationRow['language']) {
                    $updateTranslation = array(
                        "language" => $translationRow["language"],
                        "content" => $nameLanguagesList[$translationRow['language']]['content'],
                        "updated_at" => $dateTime
                    );
                } else {
                    $updateTranslation = array(
                        "language" => $translationRow["language"],
                        "content" => $defaultName,
                        "updated_at" => $dateTime
                    );                   
                }
                $db->where("id", $translationRow['id']);
                $db->update("language_translation", $updateTranslation);
            }

            //Update Description Language
            $db->where("code", $descriptionCode);
            $descrTranslationList = $db->get("language_translation", NULL, "id, code, language, content");

            // Update language_translation
            foreach ($descrLanguages as $descrLanguagesRow) {
                if($descrLanguagesRow['content'] && !$defaultDescrName) $defaultDescrName = $descrLanguagesRow['content'];
                $descrLanguagesList[$descrLanguagesRow['languageType']] = $descrLanguagesRow;
            }

            foreach ($descrTranslationList as $descrTranslationListRow) {
                if ($descrLanguagesList[$descrTranslationListRow['language']]['languageType'] == $descrTranslationListRow["language"]) {
                    $updateDescrTranslation = array(
                        "language" => $descrTranslationListRow["language"],
                        "content" => $descrLanguagesList[$descrTranslationListRow['language']]['content'],
                        "updated_at" => $dateTime
                    );
                } else {
                    $updateDescrTranslation = array(
                        "language" => $descrTranslationListRow["language"],
                        "content" => $defaultDescrName,
                        "updated_at" => $dateTime
                    );       
                }
                $db->where('id',$descrTranslationListRow['id']);
                $db->update("language_translation", $updateDescrTranslation);
            }

            if($imageName != $uploadImage['imgName']){
                $imageGroupUniqueChar   = General::generateUniqueChar("voucher","image_name");
                $imageUniqueChar = General::generateUniqueChar("voucher","image_name");
                $imageAry = explode(".",$uploadImage['imgName']);
                $imageExt = end($imageAry);
                $storedImage = time()."_".$imageUniqueChar."_".$imageGroupUniqueChar.".".$imageExt;

                //Insert Media Trash
                unset($insertTrash);
                $insertTrash = array(
                    'file_name' => $imageName,
                    'created_at'=> $dateTime,
                    'deleted'   => '0'
                );
                $db->insert('media_trash', $insertTrash);

                $editData['image']['old'] = $imageName;
                $editData['image']['new'] = $storedImage;
            }

            if($oldStatus != $status){
                $editData['image']['old'] = $oldStatus;
                $editData['image']['new'] = $status;
            }

            //Insert Voucher
            $updateData = array(
                "name" => $name,
                "status" => $status,
                "updated_at" => $dateTime,
                "updater_id" => $userID,
            );
            if($storedImage) $updateData["image_name"] = $storedImage;
            $db->where('id',$voucherID);
            $db->update('voucher',$updateData);

            // Update autoRunLang
            $db->where("name","autoRunLangCron");
            $autoRun  = $db->getValue("system_settings","value");

            if($autoRun != 1) {
                $updateData = array("value"=>"1");
                $db->where("name","autoRunLangCron");
                $db->update("system_settings",$updateData);
            }
            $data['imgName'] = $storedImage;
            // insert activity log
            $activityData = array('admin' => $adminName,"name"=>$name, "editData"=>$editData);

            $activityRes = Activity::insertActivity('Edit Voucher', 'T00053', 'L00073', $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00373'][$language]/*Update Successfully*/, 'data'=> $data);
        }

        public function getVoucherList($params,$onlyList) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            
            $searchData      = $params['searchData'];
            $pageNumber      = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            if (!$seeAll && !$onlyList) $limit = General::getLimit($pageNumber);

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {

                        default :
                            break;    
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            if($onlyList) $db->where('status','Active');
            $db->orderBy('created_at', 'DESC');
            $voucherRes = $db->get('voucher', $limit, 'id,name,translation_code,description,created_at,status,updated_at,updater_id');
            if (!$voucherRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach ($voucherRes as $voucherRow) {
                $updaterIDArr[$voucherRow['updater_id']] = $voucherRow['updater_id'];
            }

            //Get Updater username
            if($updaterIDArr){
                $db->where('id',$updaterIDArr,"IN");
                $updaterData = $db->map('id')->get('admin',null,'id,username');
            }

            foreach ($voucherRes as $voucherRow) {
                $voucherData['voucherID']   = $voucherRow['id'];
                $voucherData['name']        = $voucherRow['name'];
                $voucherData['display']     = $translations[$voucherRow['translation_code']][$language];

                if(!$onlyList){
                    $voucherData['description'] = $translations[$voucherRow['description']][$language];
                    $voucherData['status']      = $voucherRow['status'];
                    $voucherData['statusDisplay'] = General::getTranslationByName($voucherRow['status']);
                    $voucherData['createdAt']   = $voucherRow['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($voucherRow['created_at'])) : "-";
                    $voucherData['updateAt']    = $voucherRow['updated_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($voucherRow['updated_at'])) : "-";
                    $voucherData['updater']     = $voucherRow['updater_id']?$updaterData[$voucherRow['updater_id']]:"-";
                }

                $voucherList[] = $voucherData;
            }

            if($onlyList){
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $voucherList);
            }

            $totalRecord = $copyDb->get("voucher", null,"id");
            $totalRecord = COUNT($totalRecord);

            $data['voucherList'] = $voucherList;
            $data['totalRecord'] = $totalRecord;
            $data['pageNumber']  = $pageNumber;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
        }

        public function getVoucherDetail($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $userID = $db->userID;
            $site = $db->userType;
            $voucherID  = trim($params['voucherID']);

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
            }else{
                $db->where('id',$userID);
                $adminName = $db->getValue('admin','username');
                if(!$adminName){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations['E00679'][$language] /*Invalid User*/, 'data' => "");
                }
            }

            $db->where('id',$voucherID);
            $voucherRes = $db->getOne('voucher', 'id,name,translation_code,description,status,image_name');
            if (!$voucherRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            $languageCodeArr[] = $voucherRes['translation_code'];
            $languageCodeArr[] = $voucherRes['description'];

            $db->where('code',$languageCodeArr,"IN");
            $langRes = $db->get('language_translation',null,'code,language,content');
            foreach ($langRes as $langRow) {
                $languageArr[$langRow['code']][$langRow['language']] = $langRow['content'];
            }
            $voucherRes['nameLang'] = $languageArr[$voucherRes['translation_code']];
            $voucherRes['descriptionLang'] = $languageArr[$voucherRes['description']];

            unset($voucherRes['translation_code'],$voucherRes['description']);
            $voucherData = $voucherRes;

            return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $voucherData);
        }

        public function getCVRateList($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $now            = date("Y-m-d H:i:s");
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $db->where("status", "Active");
            $db->where("currency_code", "", "!=");
            $db->orderBy("priority", "ASC");
            $copyDb = $db->copy();
            $totalRecord = $copyDb->getValue("country", "count(*)");

            $countryRes = $db->get("country", $limit, "id, name, translation_code, currency_code");

            foreach ($countryRes as $countryRow) {
                $countryIDArr[$countryRow['id']] = $countryRow['id'];
            }

            // $db->where("country_id", $countryIDArr, "IN");
            // $db->where("actived_at", $now, "<=");
            // $db->groupBy("country_id");
            // $maxIDListRes = $db->get('cv_rate', null, 'max(id) as id');
            foreach ($maxIDListRes as $maxIDListRow) {
                $maxIDList[$maxIDListRow['id']] = $maxIDListRow['id'];
            }

            $db->where("actived_at", $now, "<=");
            $db->orderBy('id','ASC');
            $fullListRes = $db->get('cv_rate', null, 'id, country_id, actived_at');
            foreach ($fullListRes as $fullListRow) {
                if(strtotime($fullListRow['actived_at']) >= strtotime($maxActive[$fullListRow['country_id']]['actived_at'])){
                    $maxActive[$fullListRow['country_id']]['actived_at'] = $fullListRow['actived_at'];
                    $maxActive[$fullListRow['country_id']]['id'] = $fullListRow['id'];
                }
            }

            foreach ($maxActive as $countryID => $maxActive) {
                $maxIDList[$maxActive['id']] = $maxActive['id'];
            }

            if($maxIDList){
                $db->where("id", $maxIDList, "IN");
                $cvListRes = $db->map('country_id')->get('cv_rate', null, 'country_id, rate, actived_at');
            }

            foreach ($countryRes as $countryRow) {

                if (Cash::$creatorType == "Admin") {
                    $rateData['id'] = $countryRow['id'];
                }

                $translateCode = $countryRow['translation_code'];
                $rateData['displayName'] = $translations[$translateCode][$language];
                $rateData['currencyCode'] = $countryRow['currency_code'];
                $rateData['rate'] = Setting::setDecimal($cvListRes[$countryRow['id']]["rate"], $decimalPlaces);

                $rateData['activedAt'] = "-";
                if($cvListRes[$countryRow['id']]["actived_at"] != '0000-00-00 00:00:00' && $cvListRes[$countryRow['id']]["actived_at"]){
                    $rateData['activedAt'] = date($dateTimeFormat, strtotime($cvListRes[$countryRow['id']]["actived_at"]));
                }

                $cvRateList[] = $rateData;
            }

            $data['cvRateList']  = $cvRateList;
            $data['totalPage']   = ceil($totalRecord/$limit[1]);
            $data['pageNumber']  = $pageNumber;
            $data['totalRecord'] = $totalRecord;
            $data['numRecord']   = $limit[1];

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function getCVRateHistory($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $userID         = $db->userID;
            $site           = $db->userType;

            $id             = trim($params['id']);

            // check admin
            if($site != "Admin"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            }

            if (empty($id))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $db->where("country_id", $id);
            $cvHistoryRes = $db->get('cv_rate', null, 'rate, actived_at, created_at, creator_id');

            if (!$cvHistoryRes) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00112"][$language] /* No Results Found. */, 'data' => "");
            }

            foreach ($cvHistoryRes as $cvHistoryRow) {
                $adminIDList[$cvHistoryRow['creator_id']] = $cvHistoryRow['creator_id'];
            }

            $db->where('id', $adminIDList, 'IN');
            $adminUsername = $db->map('id')->get('admin', null, 'id, username');

            foreach ($cvHistoryRes as $cvHistoryRow) {
                $rateData['rate'] = Setting::setDecimal($cvHistoryRow['rate']);

                $rateData['createdAt'] = $cvHistoryRow['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($cvHistoryRow['created_at'])) : "-";

                $rateData['activedAt'] = $cvHistoryRow['actived_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($cvHistoryRow['actived_at'])) : "-";

                $rateData['admin'] = $adminUsername[$cvHistoryRow['creator_id']];
                $cvRateHistory[] = $rateData;
            }

            $data['cvRateHistory']  = $cvRateHistory;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function editCVRate($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;
            
            $countryID      = trim($params['countryID']);
            $cvRate         = trim($params['cvRate']);
            $activeDate     = trim($params['activeDate']);
            $now            = date("Y-m-d H:i:s");

            if (empty($cvRate) || !is_numeric($cvRate) || $cvRate < 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $tsActive = strtotime($activeDate);
            $tsNow = strtotime($now);
            if (empty($activeDate) || $tsActive < $tsNow)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Active Date should be more than now.", 'data'=> "");

            if (empty($countryID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            // check admin
            if($site != "Admin"){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            }

            $db->where('id', $userID);
            $adminUsername = $db->getValue("admin", "username");

            $db->where("id", $countryID);
            $db->where("status", "Active");
            $validCountry = $db->getOne("country", "id, currency_code, translation_code");
            if (empty($validCountry) || !$validCountry['currency_code'])
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            unset($insertData);
            $insertData = array(
                "country_id"    => $validCountry['id'],
                "currency_code" => $validCountry['currency_code'],
                "rate"          => $cvRate,
                "actived_at"    => $activeDate,
                "created_at"    => $now,
                "creator_id"    => $userID,
            );

            if (!$db->insert("cv_rate", $insertData))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00180"][$language] /* Failed to update data. */, 'data'=> "");    

            // insert activity log
            $title   = 'Add CV Rate';
            $titleCode      = 'T00058'; // Add CV Rate
            $activityCode   = 'L00078'; // %%admin%% added CV rate: %%cvRate%%, active date: %%activeDate%%.
            $activityData   = array(
                'admin'     => $adminUsername,
                'activeDate'=> $activeDate,
                'cvRate'    => $cvRate,
            );

            $activityRes = Activity::insertActivity($title, $titleCode, $activityCode, $activityData, $userID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00419"][$language] /* Successfully add PV rate. */, 'data'=> "");
        }

        public function getMemberName($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;

            $memberID             = trim($params['memberID']);

            // check admin
            // if($site != "Admin"){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=>"");
            // }

            if (empty($memberID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00179"][$language] /* Data is invalid. */, 'data'=> "");

            $db->where("member_id", $memberID);
            $memberName = $db->getValue("client", "name");

            $data['memberName'] = $memberName?:'-';

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data'=> $data);
        }

        public function setTaxPercentage($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;
            $bonusSetting    = $params['bonusSetting'];
            $dateTime       = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
            }else{
                $db->where('id',$userID);
                $username = $db->getValue('admin','username');
                if(!$username){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
                }
            }

            foreach ($bonusSetting as $settingRow) {
                if(is_null($settingRow['minBonusAmt']) || !is_numeric($settingRow['minBonusAmt']) || ($settingRow['minBonusAmt'] < 0)){
                    $errorFieldArr[] = array(
                        'id' => 'minBonusAmt'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01146'][$language]/*Invalid Minimum Bonus Amount*/
                    );
                }

                if(($bonusStgArr[$settingRow['tier']]) || (!$settingRow['tier']) || (!is_numeric($settingRow['tier'])) || ($settingRow['tier'] <= 0)){
                    $errorFieldArr[] = array(
                        'id' => 'tier'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01147'][$language]/*Invalid Tier*/
                    );
                }

                if($lastTier+1 != $settingRow['tier']){
                    $errorFieldArr[] = array(
                        'id' => 'tier'.$settingRow['tier'].'Error',
                        'msg' => str_replace("%%lastTier%%", $lastTier+1, $translations['E01150'][$language]/*Tier %%lastTier%% is Missing.*/)
                    );
                }

                if(is_null($settingRow['npwpTax']) || !is_numeric($settingRow['npwpTax']) || ($settingRow['npwpTax'] < 0)){
                    $errorFieldArr[] = array(
                        'id' => 'npwpTax'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01148'][$language]/*Invalid Tax Percentage*/
                    );
                }

                if(is_null($settingRow['nonNpwpTax']) || !is_numeric($settingRow['nonNpwpTax']) || ($settingRow['nonNpwpTax'] < 0)){
                    $errorFieldArr[] = array(
                        'id' => 'nonNpwpTax'.$settingRow['tier'].'Error',
                        'msg' => $translations['E01148'][$language]/*Invalid Tax Percentage*/
                    );
                }

                $bonusStgArr[$settingRow['tier']] = $settingRow;

                $lastTier = $settingRow['tier'];
            }

            ksort($bonusStgArr);
            unset($lastBonusAmt);
            foreach ($bonusStgArr as $tier => $bonusStgRow) {
                if(($lastBonusAmt) && ($lastBonusAmt >= $bonusStgRow['minBonusAmt'])){
                    $errorFieldArr[] = array(
                        'id' => 'nonNpwpminBonusAmtTax'.$tier.'Error',
                        'msg' => $translations['E01149'][$language]/*Bonus Amount cannot less than last tier.*/
                    );
                }
                $lastBonusAmt = $bonusStgRow['minBonusAmt'];
            }

            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet the requirements */, 'data' => $data);
            }

            if(!$bonusStgArr){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00743"][$language] /*Failed to Update*/, 'data' => "");
            }

            foreach ($bonusStgArr as $bonusTier => $bonusRow) {
                unset($updateData);

                $db->where('name','bonusTaxPercentage');
                $db->where('ref_id',$bonusTier);
                $stgID = $db->getValue('system_settings_admin','id');

                $updateData['value']    = $bonusRow['minBonusAmt'];
                $updateData['type']     = $bonusRow['npwpTax'];
                $updateData['reference']= $bonusRow['nonNpwpTax'];
                $updateData['status']   = "Active";
                $updateData['creator_id']= $userID;

                if($stgID){
                    $db->where('id',$stgID);
                    $db->update('system_settings_admin',$updateData);
                }else{
                    $updateData['name'] = 'bonusTaxPercentage';
                    $updateData['ref_id'] = $bonusTier;
                    $updateData['creator_id']= $userID;
                    $updateData['status']    = "Active";
                    $updateData['active_at'] = $dateTime;
                    $updateData['created_at']= $dateTime;
                    $db->insert('system_settings_admin',$updateData);

                }
            }

            //Inactive others setting
            $db->where('name','bonusTaxPercentage');
            $db->where('ref_id',array_keys($bonusStgArr),"NOT IN");
            $db->update('system_settings_admin',array("status"=>"Inactive"));


            // insert activity log
            $titleCode      = 'T00072';
            $activityCode   = 'L00098';
            $transferType   = 'Set Bonus Tax Percentage';
            $activityData   = array(
                'adminName' => $username,
                'data'  => json_encode($bonusStgArr),
            );
            $activityRes = Activity::insertActivity($transferType, $titleCode, $activityCode, $activityData);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data'=> "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00744'][$language] /*Successfully Updated*/, 'data'=> $data);
        }

        public function getTaxPercentage($params) {
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;
            $site           = $db->userType;
            $dateTime       = date('Y-m-d H:i:s');

            if($site != 'Admin'){
                return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
            }else{
                $db->where('id',$userID);
                $username = $db->getValue('admin','username');
                if(!$username){
                    return array('status' => "error", 'code' => 2, 'statusMsg' => $translations["E00105"][$language] /* Invalid User. */, 'data'=> "");
                }
            }

            $db->where('name','bonusTaxPercentage');
            $db->where('status','Active');
            $db->orderBy('CAST(ref_id AS Integer)','ASC');
            $bonusTaxStgArr = $db->get('system_settings_admin',null,'value AS minBonusAmt,type AS npwpTax, reference AS nonNpwpTax,ref_id AS tier');
            $data['bonusTaxStgArr'] = $bonusTaxStgArr;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00547'][$language] /*Successfully retrieved.*/, 'data'=> $data);
        }

        public function getPurchaseRequestList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['inputData'];
            $sortData       = $params['sortData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        // purchase request id
                        case 'po_id':
                            $db->where('pr.id', $dataValue);
                            // $db->where('pr.id', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase buying date
                        case 'buyingDate':
                            $columnName = 'DATE(pr.buying_date)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(intval($dateFrom) > intval($dateTo))
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }

                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        // vendor name
                        case 'name':
                            $db->where('v.name', "%" . $dataValue . "%", 'LIKE');
                            break;
                        case 'refNo':
                            $db->where('pr.order_number', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request status
                        case 'status':
                            $db->where('pr.status', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve ppl
                        case 'approvedBy':
                            $db->where('pr.approved_by', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve date
                        case 'approvedDate':
                            $columnName = 'DATE(pr.approved_by)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(intval($dateFrom) > intval($dateTo))
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }
                            
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'warehouseSearch' :
                            $db->where('w.warehouse_location', '%'. $dataValue . '%' , 'LIKE');
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $sortOrder = "DESC";
            $sortField = 'pr.order_number';

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

            // $db->orderBy('pr.id', 'DESC');
            $db->join('warehouse w', 'w.id = pr.warehouse_id', 'LEFT');
            $db->join('vendor v', 'v.id = pr.vendor_id', 'LEFT');
            $copyDb = $db->copy();
	        $summaryDb = $db->copy();
            $db->join('product p', 'p.id = pr.product_id', 'LEFT');
            $results = $db->get('purchase_request pr', $limit, 'pr.id, pr.product_name as pr_name, pr.order_number as orderNumber, pr.vendor_id, pr.total_quantity, pr.total_cost, pr.buying_date, pr.status, pr.remarks, pr.approved_by, pr.approved_date, pr.Created_by, p.name, v.name as vendor_name, p.id as pid, pr.created_at, w.warehouse_location');
            $totalRecord = $copyDb->getValue ('purchase_request pr', 'count(*)');

            if (!empty($results)) {
                foreach($results as $value) {
                    $purchaseRequest['id']              = $value['id'];
                    $purchaseRequest['pr_name']         = $value['pr_name'];
                    $purchaseRequest['orderNumber']     = $value['orderNumber'];
                    $purchaseRequest['vendor']          = $value['vendor_name'];
                    $purchaseRequest['quantity']        = $value['total_quantity'];
                    $purchaseRequest['cost']            = $value['total_cost'];
                    $purchaseRequest['buying_date']     = $value['buying_date'];
                    $purchaseRequest['status']          = $value['status'];
                    $purchaseRequest['remarks']         = $value['remarks'];
                    $purchaseRequest['approved_by']     = ($value['approved_by']!=""?$value['approved_by']:"-");
                    $purchaseRequest['Created_by']      = $value['Created_by'];
                    $purchaseRequest['approved_date']   = $value['approved_date'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['approved_date'])) : "-";
                    $purchaseRequest['warehouse_location'] = $value['warehouse_location'];
                    $purchaseRequestList[] = $purchaseRequest;

                    // app get PR listing
                    $appPurchaseRequest['purchase_partner_id']  = $value['vendor_name'];
                    $appPurchaseRequest['purchase_order_id']    = $value['id'];
                    $appPurchaseRequest['purchase_order_name']  = '';
                    $appPurchaseRequest['purchase_order_date']  = $value['created_at'];
                    $appPurchaseRequest['purchase_order_amount']  = $value['total_cost'];
                    $appPurchaseRequest['purchase_order_state']  = 'purchase';
                    $appPurchaseRequest['purchase_order_location']  = 'GoTasty - Kuala Lumpur: Receipts';
                    $appPurchaseRequestList[] = $appPurchaseRequest;
                }

                $summaryDb->groupBy("pr.status");
                $arrSummaryDetail = $summaryDb->map("status")->get("purchase_request pr", null, "pr.status, count(*) as total");

                $allCount = $saveCount = $approveCount =$cancelledCount = 0;
                foreach($arrSummaryDetail as $skey => $svalue) {

                    if(strtolower($skey)=="approved") $approveCount = $svalue;
                    if(strtolower($skey)=="save") $saveCount = $svalue;
                    if(strtolower($skey)=="cancelled") $cancelledCount = $svalue;
                    $allCount += $svalue;
                }

		        $data['summary'] = array("all"=>$allCount, "Save"=>$saveCount, "Approved"=>$approveCount, "Cancelled"=>$cancelledCount);

                $data['purchaseRequestList']    = $purchaseRequestList;
                $data['appPurchaseRequestList'] = $appPurchaseRequestList;
                $data['pageNumber']             = $pageNumber;
                $data['totalRecord']            = $totalRecord;
                $data['totalPage']              = ceil($totalRecord/$limit[1]);
                $data['numRecord']              = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }
            else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function appPurchaseRequestList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['inputData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        // purchase request id
                        case 'po_id':
                            $db->where('pr.id', $dataValue);
                            // $db->where('pr.id', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase buying date
                        case 'buyingDate':
                            $columnName = 'DATE(pr.buying_date)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(intval($dateFrom) > intval($dateTo))
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }

                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        // vendor name
                        case 'name':
                            $db->where('v.name', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request status
                        case 'status':
                            $db->where('pr.status', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve ppl
                        case 'approvedBy':
                            $db->where('pr.approved_by', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve date
                        case 'approvedDate':
                            $columnName = 'DATE(pr.approved_by)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(intval($dateFrom) > intval($dateTo))
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }
                            
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'warehouseSearch' :
                            $db->where('w.warehouse_location', '%'. $dataValue . '%' , 'LIKE');
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->orderBy('pr.id', 'DESC');
            $db->join('warehouse w', 'w.id = pr.warehouse_id', 'LEFT');
            $db->join('vendor v', 'v.id = pr.vendor_id', 'LEFT');
            $copyDb = $db->copy();
	        $summaryDb = $db->copy();
            $db->join('product p', 'p.id = pr.product_id', 'LEFT');
            $results = $db->get('purchase_request pr', $limit, 'pr.id, pr.product_name as pr_name, pr.vendor_id, pr.total_quantity, pr.total_cost, pr.buying_date, pr.status, pr.remarks, pr.approved_by, pr.approved_date, pr.Created_by, p.name, v.name as vendor_name, p.id as pid, pr.created_at, w.warehouse_location');
            $totalRecord = $copyDb->getValue ('purchase_request pr', 'count(*)');

            if (!empty($results)) {
                foreach($results as $value) {
                    $purchaseRequest['id']              = $value['id'];
                    $purchaseRequest['pr_name']            = $value['pr_name'];
                    $purchaseRequest['vendor']          = $value['vendor_name'];
                    $purchaseRequest['quantity']        = $value['total_quantity'];
                    $purchaseRequest['cost']            = $value['total_cost'];
                    $purchaseRequest['buying_date']     = $value['buying_date'];
                    $purchaseRequest['status']          = $value['status'];
                    $purchaseRequest['remarks']         = $value['remarks'];
                    $purchaseRequest['approved_by']     = ($value['approved_by']!=""?$value['approved_by']:"-");
                    $purchaseRequest['Created_by']      = $value['Created_by'];
                    $purchaseRequest['approved_date']   = $value['approved_date'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['approved_date'])) : "-";
                    $purchaseRequest['warehouse_location'] = $value['warehouse_location'];
                    $purchaseRequestList[] = $purchaseRequest;

                    // app get PR listing
                    $appPurchaseRequest['purchase_partner_id']  = $value['vendor_name'];
                    $appPurchaseRequest['purchase_order_id']    = $value['id'];
                    $appPurchaseRequest['purchase_order_name']  = '';
                    $appPurchaseRequest['purchase_order_date']  = $value['created_at'];
                    $appPurchaseRequest['purchase_order_amount']  = $value['total_cost'];
                    $appPurchaseRequest['purchase_order_state']  = 'purchase';
                    $appPurchaseRequest['purchase_order_location']  = 'GoTasty - Kuala Lumpur: Receipts';
                    $appPurchaseRequestList[] = $appPurchaseRequest;
                }

                $db->groupBy("pr.status");
                $db->join('warehouse w', 'w.id = pr.warehouse_id', 'LEFT');
                $db->join('vendor v', 'v.id = pr.vendor_id', 'LEFT');
                $arrSummaryDetail = $db->map("status")->get("purchase_request pr", null, "pr.status, count(*) as total");

                $allCount = $saveCount = $approveCount =$cancelledCount = 0;
                foreach($arrSummaryDetail as $skey => $svalue) {

                    if(strtolower($skey)=="approved") $approveCount = $svalue;
                    if(strtolower($skey)=="save") $saveCount = $svalue;
                    if(strtolower($skey)=="cancelled") $cancelledCount = $svalue;
                    $allCount += $svalue;
                }

		        $data['summary'] = array("all"=>$allCount, "Save"=>$saveCount, "Approved"=>$approveCount, "Cancelled"=>$cancelledCount);

                $data['purchaseRequestList']    = $purchaseRequestList;
                $data['appPurchaseRequestList'] = $appPurchaseRequestList;
                $data['pageNumber']             = $pageNumber;
                $data['totalRecord']            = $totalRecord;
                $data['totalPage']              = ceil($totalRecord/$limit[1]);
                $data['numRecord']              = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }
            else {
                $db->groupBy("pr.status");
                $db->join('warehouse w', 'w.id = pr.warehouse_id', 'LEFT');
                $db->join('vendor v', 'v.id = pr.vendor_id', 'LEFT');
                $arrSummaryDetail = $db->map("status")->get("purchase_request pr", null, "pr.status, count(*) as total");

                $allCount = $saveCount = $approveCount =$cancelledCount = 0;
                foreach($arrSummaryDetail as $skey => $svalue) {

                    if(strtolower($skey)=="approved") $approveCount = $svalue;
                    if(strtolower($skey)=="save") $saveCount = $svalue;
                    if(strtolower($skey)=="cancelled") $cancelledCount = $svalue;
                    $allCount += $svalue;
                }

		        $data['summary'] = array("all"=>$allCount, "Save"=>$saveCount, "Approved"=>$approveCount, "Cancelled"=>$cancelledCount);

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => $data);
            }
        }

        public function addPurchase($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $userID         = $db->userID;
            $dateTime      = date('Y-m-d H:i:s');

            $product_name       = trim($params['product_name']);
            $product_id         = trim($params['product_id']);
            $vendor_id          = trim($params['vendor_id']);
            $quantity           = trim($params['quantity']);
            $product_cost       = trim($params['product_cost']);
            $purchase_date      = trim($params['buying_date']);
            $product_list       = ($params['product_list']);
            $product_request_id = ($params['product_request_id']);
            $warehouse_id       = trim($params['warehouse_id']);
            $vendor_address_id  = trim($params['vendor_address_id']);
            $remarks            = ($params['remarks']);
            $current_time       = date("Y-m-d H:i:s");
            $uploadImage        = $params['uploadImage'];
            $imageId            = $params['imageId'];
            $poType             = $params['poType'];
            $id                 = $params['id'];
            $assign_id          = $params['assign_id'];

            # check admin permission
            $permissionDataIn['type'] = $params['permissionType'];
            $permissionDataIn['module'] = $params['module'];
            $permissionDataIn['user_id'] = $userID;

            $adminPermission = Permission::checkActionPermission($permissionDataIn);
            if($adminPermission['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $adminPermission['statusMsg'], 'data'=>$adminPermission);
            }

            $arrPermission = $adminPermission['data'];

            if($poType === 'add')
            {
                if($arrPermission['add'] != 1)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01288"][$language] /* You do not have permission to create Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
                }
            }
            else
            {
                if($arrPermission['edit'] != 1)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01289"][$language] /* You do not have permission to edit Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
                }
            }

            if($arrPermission['read'] != 1)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01290"][$language] /* You do not have permission to view Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
            }

            $purchase_date_timestamp = strtotime($purchase_date);
            $current_date = strtotime(date('Y-m-d'));
            if ($purchase_date_timestamp < $current_date) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01222"][$language] /* Invalid buying date. Please select a date equal to or later than today */, 'data'=>"");
            }

            if(!$vendor_id )
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00986"][$language] /* Please Select Vendor */, 'data'=>"");
            }

            # make sure product list have at least one product
            $allDeleted = true;
            foreach ($product_list as $product) {
                if ($product["action"] !== "delete") {
                    $allDeleted = false;
                    break; 
                }
            }

            if ($allDeleted) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01299"][$language] /* Must have at least one product in this PO */, 'data'=>"");
            }
            
            if(empty($warehouse_id) && $poType === 'add')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01227"][$language] /* Must select warehouse */, 'data'=>"");
            }
            else if($poType != 'add')
            {
                # edit PO
                $dataIn['id']                      = $id;
                $dataIn['remarks']                 = $remarks;
                $dataIn['purchase_product_list']   = $product_list;
                $dataIn['vendor_id']               = $vendor_id;
                $dataIn['buying_date']             = $purchase_date;
                $dataIn['uploadImage']             = $uploadImage;
                $dataIn['imageId']                 = $imageId;
                $dataIn['assign_id']               = $assign_id;
                $dataIn['warehouse_id']            = $warehouse_id;

                $resultOut = Admin::purchaseOrderEdit($dataIn);
                if($resultOut['status'] == 'error')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'fail to edit purchase order', 'data'=>$resultOut);
                }
            }

            if($poType === 'add')
            {
                $db->orderBy("order_number", "DESC");
                $db->where('order_number', "%" . 'GT-PO' . "%", 'LIKE');
                $lastOrderRecord = $db->getOne("purchase_order");
    
                if($lastOrderRecord) {
                    $last_order_no = $lastOrderRecord["order_number"];
                } else {
                    $last_order_no = "GT-PO-000000";
                }
                $next_order_no = self::generatePOCode($last_order_no);
    
                $db->where('id', $vendor_address_id);
                $db->where('deleted', '0');
                $vendorFullAddress = $db->getOne('vendor_address');
    
                if(!$vendorFullAddress)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01216"][$language] /* Invalid branch address */, 'data' => "");
                }
    
                $total_quantity = 0;
                foreach($product_list as $list)
                {
                    if(empty($list['name']))
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                    }
    
                    if(empty($list['id']))
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                    }
    
                    if(intval($list['quantity']) < 1)
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01223"][$language] /* Product Quantity must be at least 1 */, 'data' => "");
                    }
    
                    $total_quantity = $total_quantity + $list['quantity'];
                }
                $db->where('id', $userID);
                $adminInfo = $db->getOne('admin');
                $fields = array("order_number", "vendor_id", "total_quantity", "total_cost", "purchase_date", "remarks", "status", "created_by","created_at", "warehouse_id", "vendor_address", "deleted");
                $values = array($next_order_no, $vendor_id, $total_quantity, $total_cost, $purchase_date, $remarks, "RFQ", $adminInfo['name'], $current_time, $warehouse_id, $vendorFullAddress['address'], '0');
                $arrayData = array_combine($fields, $values);
                $result = $db->insert('purchase_order', $arrayData);

                # insert to po_assign
                $insertData = array(
                    'po_id'       => $result,
                    'assignee'    => $assign_id,
                    'assign_by'   => $userID,
                    'status'      => 'pending',
                    'reason'      => $reason,
                    'created_at'  => $dateTime
                );
                $db->insert('po_assign', $insertData);

                $arrayData['id'] = $result;
                $params['product_order_id'] = $arrayData['id'];
                $response = Admin::insertPurchaseOrderProduct($params);
                if($response['status'] == 'error')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $response['statusMsg'], 'data' => $response);
                }
                $data['po_id'] = $arrayData['id'];

            }
            else
            {
                # check is there have existing assignee for this PO
                $db->orderBy('id', 'DESC');
                $db->where('po_id', $id);
                $db->where('status', 'reject', '!=');
                $poAssign = $db->getOne('po_assign');

                if(!$poAssign)
                {
                    # insert to po_assign
                    $insertData = array(
                        'po_id'       => $id,
                        'assignee'    => $assign_id,
                        'assign_by'   => $userID,
                        'status'      => 'pending',
                        'created_at'  => $dateTime
                    );
                    $db->insert('po_assign', $insertData);
                }
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> 'Purchase Order Has Been Added Successfully', 'data'=>$data);
        }

        public function insertPurchaseOrderProduct($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $product_name       = trim($params['product_name']);
            $product_id         = trim($params['product_id']);
            $vendor_id          = trim($params['vendor_id']);
            $quantity           = trim($params['quantity']);
            $product_cost       = trim($params['product_cost']);
            $purchase_date      = trim($params['buying_date']);
            $product_list       = ($params['product_list']);
            $product_order_id   = ($params['product_order_id']);
            $dateObj = DateTime::createFromFormat('m/d/Y', $buying_date);
            $buying_date = $dateObj ? $dateObj->format('Y-m-d') : null;

            foreach($product_list as $list)
            {

                if(empty($list['name']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                }
    
                if(empty($list['id']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                }
    
                if(intval($list['quantity']) < 1)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01223"][$language] /* Product Quantity must be at least 1 */, 'data' => "");
                }

                if(strtolower($list['type']) == 'foc')
                {
                    $insertPOType = 'FOC';
                }
                else
                {
                    $insertPOType = 'Purchase';
                }
                $total_cost = intval($list['quantity']) * floatval($list['cost']);
                $fields = array("purchase_order_id", "product_id", 'product_name', "quantity", "cost", "total_cost", "created_at", "type");
                $values = array($product_order_id, $list['id'], $list['name'], $list['quantity'], $list['cost'], $total_cost, date("Y-m-d H:i:s"), $insertPOType);
                $arrayData = array_combine($fields, $values);

                try{
                    $result = $db->insert('purchase_order_product', $arrayData);
                }
                catch (Exception $e) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00932"][$language] /* Failed to add product */, 'data' => "");
                }
                $costList[] = $total_cost;
                $nameList[] = $list['name'];
                $productList[] = $list['id'];
                $quantityList[] = $list['quantity'];
            }
            $totalCost = 0;
            foreach($costList as $row)
            {
                $totalCost = floatval($row) + $totalCost;
            }
            $quantity = 0;
            foreach ($quantityList as $quantity) {
                $totalQuantity += $quantity;
            }
            $nameList = implode(", ", $nameList);
            $productList = implode(", ", $productList);

            # update purchase order
            $updateData = array(
                'total_cost' => floatval($totalCost),
            );
            $db->where('id', $product_order_id);
            $db->update('purchase_order', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00932"][$language] /*Purchase Product Has Been Added Successfully */ , 'data'=> $data);
        }

        public function addPurchaseRequest($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
        
            $userID         = $db->userID;
        
            $product_name       = trim($params['product_name']);
            $product_id         = trim($params['product_id']);
            $vendor_id        = trim($params['vendor_id']);
            $quantity           = trim($params['quantity']);
            $product_cost       = trim($params['product_cost']);
            $buying_date        = trim($params['buying_date']);
            $product_list       = ($params['product_list']);
            $product_request_id       = ($params['product_request_id']);
            //$approved_by       = trim($params['approved_by']);
            $warehouse_id      = trim($params['warehouse_id']);
            $vendor_address_id  = trim($params['vendor_address_id']);
            $remarks           = ($params['remarks']);
            // $assignee_id       = trim($params['assignee_id']);
        
	        //$warehouse_id = "1"; //define0513
            
            $buying_date_timestamp = strtotime($buying_date);
            $current_date = strtotime(date('Y-m-d'));
            if ($buying_date_timestamp < $current_date) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01222"][$language] /* Invalid buying date. Please select a date equal to or later than today */, 'data'=>"");
            }
            
            if(empty($warehouse_id))
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01227"][$language] /* Must select warehouse */, 'data'=>"");
            }

            // check user is manager or admin
            // if branch > 1 = manager, branch == 1 is admin.
            // $db->where('creator_id', $userID);
            // $db->where('status', 'Active');
            // $db->where('name', 'warehouseId');
            // $userDetail = $db->get('system_settings_admin', null, 'value');
            
            // if($userDetail)
            // {
            //     // check warehouse_id option is in userDetail or not
            //     if (is_array($userDetail)) 
            //     {
            //         // Loop through each element in the array
            //         $matchFound = false;
            //         foreach ($userDetail as $detail) {
            //             // Check if 'value' key is set and its value matches with $warehouse_id option
            //             if (isset($detail['value']) && $detail['value'] == $warehouse_id) {
            //                 $matchFound = true;
            //                 break;
            //             }
            //         }
            //         if (!$matchFound) {
            //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01707"][$language] /* No branch found, please add new branch in edit admin. */, 'data' => "");
            //         }
            //     } 

            //     // if manager level
            //     if(count($userDetail) > 1)
            //     {
            //         if(empty($assignee_id))
            //         {
            //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01708"][$language] /* Please select assignee */, 'data' => '');
            //         }
        
            //         // get assignee detail
            //         $db->where('id', $assignee_id);
            //         $assigneeDetail = $db->getOne('admin');
        
            //         $current_time = date("Y-m-d H:i:s");
            //         $fields = array("product_name", "vendor_id", "product_id", "quantity", "product_cost","total_quantity", "total_cost", "buying_date", "remarks", "approved_date", "approved_by", "status", "Created_by", "warehouse_id","created_at");
            //         $values = array($product_name, $vendor_id, $product_id, $quantity, $product_cost, $total_quantity, $total_cost, $buying_date, $remarks, $current_time, $approved_by, "Save", $assigneeDetail['name'], $warehouse_id,$current_time);
            //         $arrayData = array_combine($fields, $values);
        
            //         try{
            //             $result = $db->insert('purchase_request', $arrayData);
            //             // $db->where('created_at', $current_time);
            //             // $prId = $db->getOne('purchase_request','id');
            //             $arrayData['id'] = $result;
            //             $params['product_request_id'] = $arrayData['id'];
            //             Admin::addPurchaseProduct($params);
            //         }
            //         catch (Exception $e) {
            //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data' => "");
            //         }

            //         // approve pr
            //         unset($params);
            //         $params['id']     = $result;
            //         $params['status'] = "Approved";
            //         Admin::purchaseRequestApprove($params, $approved_by);
        
            //         return array('status' => "ok", 'code' => 0, 'statusMsg'=> 'Purchase Request Has Been Added Successfully', 'data'=>$arrayData);
            //     }
            // }
            // if(count($userDetail) <1)
            // {
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01707"][$language] /* No branch found, please add new branch in edit admin. */, 'data' => "");
            // }
        
            // admin level 

            $db->orderBy("order_number", "DESC");
            $db->where('order_number', "%" . 'GT-PR' . "%", 'LIKE');
            $lastOrderRecord = $db->getOne("purchase_request");

            if($lastOrderRecord) {
                $last_order_no = $lastOrderRecord["order_number"];
            } else {
                $last_order_no = "GT-PR-000000";
            }
            $next_order_no = self::generatePRCode($last_order_no);
        
            if(!$total_quantity){
                $total_quantity = $quantity;
            }
        
            if(!$total_cost){
                $total_cost = $product_cost * $quantity;
            }
       
            $db->where("id", $userID);
            $Created_by = $db->getValue("admin", "name") ?? $userID;

            // get vendor full address by vendor_address_id
            $db->where('id', $vendor_address_id);
            $db->where('deleted', '0');
            $vendorFullAddress = $db->getOne('vendor_address');

            if(!$vendorFullAddress)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01216"][$language] /* Invalid branch address */, 'data' => "");
            }

            foreach($product_list as $list)
            {
                if(empty($list['name']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                }

                if(empty($list['id']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                }

                if(intval($list['quantity']) < 1)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01223"][$language] /* Product Quantity must be at least 1 */, 'data' => "");
                }
            }

            $current_time = date("Y-m-d H:i:s");
            // $fields = array("product_name", "vendor_id", "product_id", "quantity", "product_cost","total_quantity", "total_cost", "buying_date", "remarks", "approved_date", "approved_by", "status", "Created_by", "warehouse_id","created_at");
            // $values = array($product_name, $vendor_id, $product_id, $quantity, $product_cost, $total_quantity, $total_cost, $buying_date, $remarks, $current_time, $approved_by, "Save", $approved_by, $warehouse_id,$current_time);
            // $arrayData = array_combine($fields, $values);
            $fields = array("order_number","product_name", "vendor_id", "product_id", "quantity", "product_cost","total_quantity", "total_cost", "buying_date", "remarks", "status", "Created_by","created_at", "warehouse_id", "vendor_address");
            $values = array($next_order_no,$product_name, $vendor_id, $product_id, $quantity, $product_cost, $total_quantity, $total_cost, $buying_date, $remarks, "Save", $Created_by, $current_time, $warehouse_id, $vendorFullAddress['address']);
            $arrayData = array_combine($fields, $values);
            
            try{
                $result = $db->insert('purchase_request', $arrayData);
                // $db->where('created_at', $current_time);
                // $prId = $db->getOne('purchase_request','id');
                $arrayData['id'] = $result;
                $params['product_request_id'] = $arrayData['id'];
                $response = Admin::addPurchaseProduct($params);
                if($response['status'] == 'error')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $response['statusMsg'], 'data' => $response);
                }
            }
            catch (Exception $e) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity */, 'data' => "");
            }
        
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> 'Purchase Request Has Been Added Successfully', 'data'=>$arrayData);
        }

        public function addPurchaseProduct($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $product_name       = trim($params['product_name']);
            $product_id         = trim($params['product_id']);
            $vendor_id          = trim($params['vendor_id']);
            $quantity           = trim($params['quantity']);
            $product_cost       = trim($params['product_cost']);
            $buying_date        = trim($params['buying_date']);
            $product_list       = ($params['product_list']);
            $product_request_id       = ($params['product_request_id']);
            $dateObj = DateTime::createFromFormat('m/d/Y', $buying_date);
            $buying_date = $dateObj ? $dateObj->format('Y-m-d') : null;

            foreach($product_list as $list)
            {

                if(empty($list['name']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                }
    
                if(empty($list['id']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data'=>"");
                }
    
                if(intval($list['quantity']) < 1)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01223"][$language] /* Product Quantity must be at least 1 */, 'data' => "");
                }
                $total_cost = intval($list['quantity']) * floatval($list['cost']);
                $fields = array("purchase_request_id", "vendor_id", "product_id", 'product_name', "quantity", "cost", "total_cost", "created_at", "type");
                $values = array($product_request_id, $vendor_id, $list['id'], $list['name'], $list['quantity'], $list['cost'], $total_cost, date("Y-m-d H:i:s"), "Purchase");
                $arrayData = array_combine($fields, $values);

                try{
                    $result = $db->insert('pr_product', $arrayData);
                    // return $result;
                }
                catch (Exception $e) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00932"][$language] /* Failed to add product */, 'data' => "");
                }
                $costList[] = $total_cost;
                $nameList[] = $list['name'];
                $productList[] = $list['id'];
                $quantityList[] = $list['quantity'];
            }
            $totalCost = 0;
            foreach($costList as $row)
            {
                $totalCost = floatval($row) + $totalCost;
            }
            $quantity = 0;
            foreach ($quantityList as $quantity) {
                $totalQuantity += $quantity;
            }
            $nameList = implode(", ", $nameList);
            $productList = implode(", ", $productList);
            // update pr request
            $current_time = date("Y-m-d H:i:s");
            $fields = array("product_name", "product_cost", "product_id", "quantity","total_quantity", "total_cost", "updated_at");
            $values = array($nameList, $totalCost, $productList, $totalQuantity, $totalQuantity, $totalCost, $current_time);
            $data = array_combine($fields, $values);

            $db->where('id', $product_request_id);
            $db->update('purchase_request', $data);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["E00932"][$language] /*Purchase Product Has Been Added Successfully */ , 'data'=> $data);
        }

        public function purchaseRequestEdit($params, $username) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $userID         = $db->userID;

            $id                 = trim($params['id']);
            // $product_name       = trim($params['product_name']);
            // $quantity           = trim($params['quantity']);
            // $product_cost       = trim($params['product_cost']);
            // $total_quantity     = trim($params['total_quantity']);
            // $total_cost         = trim($params['total_cost']);
            // $approved_by        = trim($params['approved_by']);
            $approved_date      = trim($params['approved_date']);
            $remarks            = ($params['remarks']);
            $buying_date        = trim($params['buying_date']);
            $purchase_product_list   = ($params['purchase_product_list']);
            $vendor_id   = trim($params['vendor_id']);
            // $warehouse_id      = trim($params['warehouse_id']);
            // $assignee_id       = trim($params['assignee_id']);

            // check PR status is available to edit or not
            $db->where('id', $id);
            $prDetail = $db->getOne('purchase_request');
            if(empty($prDetail))
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => "");
            }
            if($prDetail['status'] != 'Save')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01710"][$language] /* Purchase Request is not able to edit */, 'data' => "");
            }

            $buying_date_timestamp = strtotime($buying_date);
            $current_date = strtotime(date('Y-m-d'));
            if ($buying_date_timestamp < $current_date) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01222"][$language] /* Invalid buying date. Please select a date equal to or later than today */, 'data'=>"");
            }

            // check user is manager or admin
            // if branch > 1 = manager, branch == 1 is admin.
            // $db->where('creator_id', $userID);
            // $db->where('status', 'Active');
            // $db->where('name', 'warehouseId');
            // $userDetail = $db->get('system_settings_admin', null, 'value');
            
            // if($userDetail)
            // {
            //     // check warehouse_id option is in userDetail or not
            //     if (is_array($userDetail)) 
            //     {
            //         // Loop through each element in the array
            //         $matchFound = false;
            //         foreach ($userDetail as $detail) {
            //             // Check if 'value' key is set and its value matches with $warehouse_id option
            //             if (isset($detail['value']) && $detail['value'] == $warehouse_id) {
            //                 $matchFound = true;
            //                 break;
            //             }
            //         }
            //         if (!$matchFound) {
            //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01707"][$language] /* No branch found, please add new branch in edit admin. */, 'data' => "");
            //         }
            //     } 

            //     // if manager level
            //     if(count($userDetail) > 1)
            //     {
            //         if(empty($assignee_id))
            //         {
            //             return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01708"][$language] /* Please select assignee */, 'data' => '');
            //         }
            //     }
            // }
            // if(count($userDetail) <1)
            // {
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01707"][$language] /* No branch found, please add new branch in edit admin. */, 'data' => "");
            // }
            
            if($total_cost || !$total_cost)
                $total_cost = $product_cost * $quantity;

            if($total_quantity || !$total_quantity)
                $total_quantity = $quantity;

            if($approved_by || !$approved_by)
                $approved_by = $approved_by;
                        
            // $fields = array("product_name", "quantity", "product_cost", "total_quantity", "total_cost", "buying_date", "remarks", "approved_date", "approved_by");
            // $values = array($product_name, $quantity, $product_cost, $total_quantity, $total_cost, $buying_date, $remarks ,date("Y-m-d H:i:s"), $username);
            // $arrayData = array_combine($fields, $values);
            // $db->where('id', $id);
            // $result = $db->update("purchase_request", $arrayData);

            // get all the product list from purchase_product table

            // edit purchase product
            foreach($purchase_product_list as $row)
            {
                $productDetail['id']           = $row['id'];
                $productDetail['product_id']   = $row['product_id'];
                $productDetail['product_name'] = $row['name'];
                $productDetail['quantity']     = $row['quantity'];
                $productDetail['cost']         = $row['cost'];
                $productDetail['total_cost']   = floatval($row['quantity'] * $row['cost']);
                $productDetail['product_id']   = $row['product_id'];

                if(empty($productDetail['product_id']) || empty($productDetail['product_name']))             
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data' => '');
                }

                if($row['action'] != 'delete')
                {
                    if(intval($productDetail['quantity']) < 1)
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01223"][$language] /* Product Quantity must be at least 1 */, 'data' => "");
                    }
                }

                $fields = array("quantity", "cost", "total_cost", "updated_at");
                $values = array($productDetail['quantity'],$productDetail['cost'], $productDetail['total_cost'], date("Y-m-d H:i:s"));
                $arrayData = array_combine($fields, $values);

                $db->where('id', $row['id']);
                $db->update('pr_product', $arrayData);
                $rowTypeList[] = $row['action'];
                if($row['action'] == 'add')
                {
                    // get po id
                    // $db->where('pr_id',$id);
                    // $po_id = $db->getOne('purchase_order', 'id');
                    // $po_id = $po_id['id'];
                    // add new product
                    $productDetail2['id']           = $row['id'];
                    $productDetail2['product_id']   = $row['product_id'];
                    $productDetail2['name']         = $row['name'];
                    $productDetail2['cost']         = $row['cost'];
                    $productDetail2['quantity']     = $row['quantity'];
                    $total_cost = intval($productDetail2['cost']) * intval($productDetail2['quantity']);
                    $current_time = date('Y-m-d H:i:s');

                    // sum back old data quantity
                    $db->where('purchase_request_id', $id);
                    $db->where('product_id', $productDetail2['product_id']);
                    $productQuantity = $db->getOne('pr_product','quantity');
                    if($productQuantity)
                    {
                        $productQuantity = $productQuantity['quantity'];
                    }

                    $totalQuantity = intval($productDetail2['quantity']) + $productQuantity;

                    $total_cost = intval($totalQuantity) * floatval($productDetail2['cost']);

                    $fields = array("purchase_request_id", "vendor_id", "product_id", 'product_name', "quantity", "cost", "total_cost", "created_at");
                    $values = array($id, $vendor_id, $productDetail2['product_id'], $productDetail2['name'], $productDetail2['quantity'], $productDetail2['cost'], $total_cost, date("Y-m-d H:i:s"));
                    $arrayData2 = array_combine($fields, $values);

                    // check if there is existing record or not
                    $db->where('purchase_request_id', $id);
                    $db->where('product_id', $productDetail2['product_id']);
                    $result = $db->get('pr_product');
                    if(empty($result))
                    {
                        $db->insert('pr_product', $arrayData2);
                    }
                    else
                    {
                        // if have existing record, and its same product id, sum it up.
                        foreach($result as $purchaseProductDetail)
                        {
                            if($purchaseProductDetail['product_id'] == $productDetail2['product_id'])
                            {
                                $updatedQuantity = intval($purchaseProductDetail['quantity']) + intval($productDetail2['quantity']);

                                $fields = array("purchase_request_id", "vendor_id", "product_id", 'product_name', "quantity", "cost", "total_cost", "created_at");
                                $values = array($id, $vendor_id, $productDetail2['product_id'], $productDetail2['name'], $updatedQuantity, $productDetail2['cost'], $total_cost, date("Y-m-d H:i:s"));
                                $arrayData = array_combine($fields, $values);

                                $db->where('purchase_request_id',$id);
                                $db->where('product_id', $productDetail2['product_id']);
                                $db->update('pr_product', $arrayData);
                            }
                            else
                            {
                                $db->where('purchase_request_id',$id);
                                $db->where('product_id', $productDetail2['product_id']);
                                $db->update('pr_product', $arrayData2);
                            }
                        }
                    }
                }
                else if($row['action'] == 'delete')
                {
                    // get po id
                    $db->where('pr_id',$id);
                    $po_id = $db->getOne('purchase_order', 'id');
                    $po_id = $po_id['id'];
                    // add new product
                    $productDetail2['id']           = $row['id'];
                    $productDetail2['product_id']   = $row['product_id'];
                    $productDetail2['name']         = $row['name'];
                    $productDetail2['cost']         = $row['cost'];
                    $productDetail2['quantity']     = $row['quantity'];
                    $total_cost = intval($productDetail2['cost']) * intval($productDetail2['quantity']);
                    $current_time = date('Y-m-d H:i:s');

                    $total_cost = intval($productDetail2['quantity']) * floatval($productDetail2['cost']);
                    $fields = array("purchase_request_id","purchase_order_id", "vendor_id", "product_id", 'product_name', "quantity", "cost", "total_cost", "created_at");
                    $values = array($id,$po_id, $vendor_id, $productDetail2['product_id'], $productDetail2['name'], $productDetail2['quantity'], $productDetail2['cost'], $total_cost, date("Y-m-d H:i:s"));
                    $arrayData2 = array_combine($fields, $values);

                    // check if there is existing record or not
                    $db->where('product_id', $productDetail2['product_id']);
                    $result = $db->get('pr_product');

                    if(!empty($result))
                    {
                        $db->where('id', $row['id']);
                        $db->where('purchase_request_id',$id);
                        $db->where('product_id', $row['product_id']);
                        $db->delete('pr_product');
                    }
                }
                else
                {
                    // check the product id is in purchase_product table or not
                    $db->where('product_id', $row['product_id']);
                    $db->where('purchase_request_id', $id);
                    $existResult = $db->get('pr_product');
                    if(empty($existResult))
                    {
                        $fields = array("product_id","product_name","vendor_id","quantity", "cost", "total_cost", "updated_at");
                        $values = array($productDetail['product_id'],$productDetail['product_name'],$vendor_id,$productDetail['quantity'],$productDetail['cost'], $productDetail['total_cost'], date("Y-m-d H:i:s"));
                        $arrayData = array_combine($fields, $values);
                        // return array("code" => 110, "status" => "ok", "empty" => $arrayData, "productDetail" => $productDetail);
                    }
                    else
                    {
                        $fields = array("product_id","product_name","vendor_id","quantity", "cost", "total_cost", "updated_at");
                        $values = array($productDetail['product_id'],$productDetail['product_name'],$vendor_id,$productDetail['quantity'],$productDetail['cost'], $productDetail['total_cost'], date("Y-m-d H:i:s"));
                        $arrayData = array_combine($fields, $values);
                        // return array("code" => 110, "status" => "ok", "not empty" => '');
                    }

                    // $fields = array("vendor_id", "quantity", "cost", "total_cost", "type", "updated_at");
                    // $values = array($vendor_id, $productDetail['quantity'],$productDetail['cost'], $productDetail['total_cost'], $productDetail['type'], date("Y-m-d H:i:s"));
                    // $arrayData = array_combine($fields, $values);
                    $db->where('purchase_request_id',$id);
                    $db->where('id',$productDetail['id']);
                    $db->update('pr_product', $arrayData);
                }
                // $costList[]     = $productDetail['total_cost'];
                // $quantityList[] = $productDetail['quantity'];
                $nameList[]     = $row['name'];
            }
            // update on pr
            $totalCost = 0;
            foreach($product_list as $list)
            {
                $nameList[] = $nameList;
            }
            // get product id list again
            $db->where('purchase_request_id', $id);
            $productDetail = $db->get('pr_product',null,'product_id, quantity, total_cost');
            foreach($productDetail as $row)
            {
                $productId = $row['product_id'];
                $productQuantity = $row['quantity'];
                $productCost = $row['total_cost'];
                $productList1[] = $productId;
                $quantityList[] = $productQuantity;
                $costList[]     = $productCost;
            }
            $nameList = implode(", ", $nameList);
            $productList = implode(", ", $productList1);
            $totalCost = 0;
            // foreach($costList as $row)
            // {
            //     $totalCost = floatval($row) + $totalCost;
            // }
            $db->where('purchase_request_id', $id);
            $getTotalCost = $db->get('pr_product', null, 'total_cost');

            foreach($getTotalCost as $sumCost)
            {
                $totalCost = $totalCost + $sumCost['total_cost'];
            }
            $totalQuantity = array_sum($quantityList);

            // update pr request
            // if manager level
            // $current_time = date("Y-m-d H:i:s");
            // if(count($userDetail) > 1)
            // {
            //     if(!empty($assignee_id))
            //     {
            //         // get assignee detail
            //         $db->where('id', $assignee_id);
            //         $assigneeDetail = $db->getOne('admin');

            //         $fields = array("product_name", "product_cost", "product_id", "quantity","total_quantity", "total_cost", "buying_date", "remarks", "Created_by", "warehouse_id", "updated_at");
            //         $values = array($nameList, $totalCost, $productList, $totalQuantity, $totalQuantity, $totalCost, $buying_date, $remarks, $assigneeDetail['name'], $warehouse_id, $current_time);
            //         $data = array_combine($fields, $values);
            //     }
            // }
            // else if(count($userDetail) == 1)
            // {
                $fields = array("product_name", "product_cost", "product_id", "quantity","total_quantity", "total_cost", "buying_date", "remarks", "updated_at");
                $values = array($nameList, $totalCost, $productList, $totalQuantity, $totalQuantity, $totalCost, $buying_date, $remarks, $current_time);
                $data = array_combine($fields, $values);
            // }
            $db->where('id', $id);
            $result = $db->update('purchase_request', $data);

            if ($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["A01744"][$language] /* Purchase Request has updated */, 'data' => '');
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid Purchase Request', 'data' => "");
            }
        }

        public function cancelledPurchaseRequest($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");

            $prID           = $params['id'];

            $updateData = array(
                'status'        => 'Cancelled',
                'updated_at'    => $dateTime,
            );

            // if PR is other than approve, will block user action
            $db->where('id', $prID);
            $prStatus = $db->getOne('purchase_request', 'status');

            if($prStatus['status'] == 'Approved')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["A01758"][$language] /* Failed to cancel Purchase Request */ , 'data'=> '');
            }

            $db->where('id', $prID);
            $result = $db->update('purchase_request', $updateData);

            // cancelled PO in the same time
            $db->where('pr_id', $prID);
            $result = $db->update('purchase_order', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["A01745"][$language] /* Purchase Request has been cancelled */ , 'data'=> $data);
        }

        public function getPurchaseRequestDetails($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if (strlen($id)==0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00104"][$language] /* Please Select Admin */, 'data'=> '');

            $db->where('pr.id', $id);
            $db->join('vendor v', 'pr.vendor_id = v.id', 'LEFT');
            $db->join('pr_product pp', 'pr.id = pp.purchase_request_id', "LEFT");
            $db->join('warehouse w', 'pr.warehouse_id = w.id', 'LEFT');
            $result = $db->get('purchase_request pr', null, 'pp.id as purchase_product_id, pp.product_id, pp.cost, pp.product_name as purchase_product_name, pp.quantity, v.id as vendor_id, v.name as vendor_name, pr.id as purchase_request_id, pr.total_cost, pr.remarks, pr.buying_date, pr.Created_by, pr.approved_by, pr.status, w.id as warehouse_id, w.warehouse_location, warehouse_address, v.name, v.vendor_code, pr.vendor_address');

            if (empty($result))
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => '', 'data' => "");
            }
            else
            {
                foreach($result as $row)
                {
                    $productDetail['purchase_product_id'] = $row['purchase_product_id'];
                    $productDetail['product_id'] = $row['product_id'];
                    $productDetail['cost'] = $row['cost'];
                    $productDetail['name'] = $row['purchase_product_name'];
                    $productDetail['quantity'] = $row['quantity'];
                    // $productDetail['vendor_id'] = $row['vendor_id'];
                    $productDetail['vendor_name'] = $row['vendor_name'];
                    $productDetail['purchase_request_id'] = $row['purchase_request_id'];
                    $otherDetails['total_cost'] = $row['total_cost'];
                    $otherDetails['remarks'] = $row['remarks'];
                    $otherDetails['buying_date'] = $row['buying_date'];
                    $otherDetails['vendor_id'] = $row['vendor_id'];
                    $otherDetails['Created_by'] = $row['Created_by'];
                    $otherDetails['approved_by'] = $row['approved_by'];
                    $otherDetails['status'] = $row['status'];
                    $otherDetails['warehouse_id'] = $row['warehouse_id'];
                    $otherDetails['warehouse_location'] = $row['warehouse_location'];
                    $otherDetails['warehouse_address'] = $row['warehouse_address'];
                    $otherDetails['vendor_name'] = $row['vendor_name'];
                    $otherDetails['vendor_code'] = $row['vendor_code'];
                    $otherDetails['vendor_address'] = $row['vendor_address'];

                    $productList[] = $productDetail;
                }
                $data['productList'] = $productList;
                $data['total_cost'] = $otherDetails['total_cost'];
                $data['Created_by'] = $otherDetails['Created_by'];
                $data['approved_by'] = $otherDetails['approved_by'];
                $data['status'] = $otherDetails['status'];
                $data['remarks'] = $otherDetails['remarks'];
                $data['buying_date'] = $otherDetails['buying_date'];
                $data['vendor_id'] = $otherDetails['vendor_id'];
                $data['vendor_name'] = $otherDetails['vendor_name'];
                $data['vendor_code'] = $otherDetails['vendor_code'];
                $data['vendor_address'] = $otherDetails['vendor_address'];
                $data['warehouse_id'] = $otherDetails['warehouse_id'];
                $data['warehouse_location'] = $otherDetails['warehouse_location'];
                $data['warehouse_address'] = $otherDetails['warehouse_address'];

                return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' => $data);
            }
        }

        public function getShopOwnerList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $flag           = trim($params['getShopOwnerFlag']) ? $params['getShopOwnerFlag'] : 0;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            if ($flag) {
                $db->where('type', "Owner");
                $db->where('deleted', 0);
                $result = $db->get('client', null, 'id, name');

                foreach($result as $key => $value) {
                    $getShopOwner['id']     = $value['id'];
                    $getShopOwner['name']   = $value['name'];

                    $getShopOwnerList[] = $getShopOwner;
                }

                $data['getShopOwnerList'] = $getShopOwnerList;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00514'][$language] /*Successfully retrieved.*/, 'data' => $data);
            }
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'username':
                            if ($dataType == "like") {
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            }
                            else {
                                $db->where('name', $dataValue);
                            }
                            break;

                        case 'name':
                            if ($dataType == "like") {
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            }
                            else {
                                $db->where('name', $dataValue);
                            }
                            break;

                        case 'disabled':
                            $db->where('disabled', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('type', "Owner");
            $db->orderBy('id', "DESC");
            $copyDb = $db->copy();
            $result = $db->get('client', $limit, 'id, username, name, disabled, created_at, last_login');

            $totalRecord = $copyDb->getValue('client', 'count(*)');

            if (!empty($result)) {
                foreach($result as $value) {
                    $owner['id']            = $value['id'];
                    $owner['username']      = $value['username'] != "" ? $value['username'] : "-";
                    $owner['name']          = $value['name'] != "" ? $value['name'] : "-";
                    $owner['disabled']      = ($value['disabled'] == 0) ? 'No' : 'Yes';
                    $owner['lastLogin']     = $value['last_login'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['last_login'])) : "-";
                    $owner['createdAt']     = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";

                    $ownerList[] = $owner;
                }

                $data['ownerList']      = $ownerList;
                $data['totalPage']      = ceil($totalRecord/$limit[1]);
                $data['pageNumber']     = $pageNumber;
                $data['totalRecord']    = $totalRecord;
                $data['numRecord']      = $limit[1];

                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => $translations['B00101'][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function addShopOwner($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $ownername      = trim($params['ownername']);
            $username       = trim($params['username']);
            $password       = trim($params['password']);
            $type           = "Owner"; // user type => owner

            if (!$ownername) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01153"][$language] /* Owner's name cannot be empty. */, 'data' => "");
            }
            if (!$username) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01154"][$language] /* Username cannot be empty. */, 'data' => "");
            }
            if (!$password) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01155"][$language] /* Password cannot be empty. */, 'data' => "");
            }
            if (strlen($password) < 8) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01156"][$language] /* Password must be at least 8 characters. */, 'data' => "");
            }
            if (!preg_match('/^[a-z0-9@$!%*#?&]*$/', $password, $matches)) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01157"][$language] /* Password must be alphanumeric and (@!%*#?&]) only. */, 'data' => "");
            }

            $db->where('username', $username);
            $checkUsername = $db->getOne('client', 'username');
            if ($checkUsername && $checkUsername['username']) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01158"][$language] /* Username already exists. */, 'data' => ""); 
            }

            /* Encrypt password */
            $encryptedPassword = $db->encrypt($password);

            /* Create new shop owner */
            $insertShopOwnerData = array(
                'name'          => $ownername,
                'username'      => $username,
                'password'      => $encryptedPassword,
                'type'          => $type,
                'activated'     => 1,
                'disabled'      => 0,
                'created_at'    => date('Y-m-d H:i:s')
            );
            $insertShopOwnerResult = $db->insert('client', $insertShopOwnerData);

            if ($insertShopOwnerResult) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00102"][$language] /* Successfully Added */, 'data' => "");
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00511"][$language] /* Failed to insert */, 'data' => "");
            }
        }

        public function getShopOwnerDetail($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            if (!$id)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01159"][$language] /* Shop owner does not exist. */, 'data' => '');

            $db->where('id', $id);
            $result = $db->getOne('client', 'id, username, name, password, disabled');

            if (!empty($result)) {
                foreach ($result as $key => $value) {
                    $ownerDetail[$key] = $value;
                }

                $data['ownerDetail'] = $ownerDetail;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00514"][$language] /* Successfully Retrieved */, 'data' => $data);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00515"][$language] /* Failed to Retrieved. */, 'data' => "");
            }
        }

        public function editShopOwner($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);
            $ownername      = trim($params['ownername']);
            $username       = trim($params['username']);
            $password       = trim($params['password']);
            $disabled       = trim($params['disabled']) ? : 0;

            if ($disabled == 0) {
                $activated = 1;
            }
            else {
                $activated = 0;
            }

            if (!$id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01159"][$language] /* Shop owner does not exist. */, 'data' => "");
            }
            if (!$ownername) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01153"][$language] /* Owner's name cannot be empty. */, 'data' => "");
            }
            if (!$username) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01154"][$language] /* Username cannot be empty. */, 'data' => "");
            }

            $db->where('id', $id);
            $checkUser = $db->getOne('client');

            if ($checkUser && $checkUser['id']) {
                if (strlen($password) >= 8) {
                    if (strlen($password) < 8) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01156"][$language] /* Password must be at least 8 characters. */, 'data' => "");
                    }
                    if (!preg_match('/^[a-z0-9@$!%*#?&]*$/', $password, $matches)) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01157"][$language] /* Password must be alphanumeric and (@!%*#?&]) only. */, 'data' => "");
                    }

                    /* Encrypt password */
                    $encryptedPassword = $db->encrypt($password);

                    /* Update shop owner (password) */
                    $updateShopOwnerData = array(
                        'name'          => $ownername,
                        'username'      => $username,
                        'password'      => $encryptedPassword,
                        'disabled'      => $disabled,
                        'activated'     => $activated,
                        'updated_at'    => date('Y-m-d H:i:s')
                    );
                }
                else {
                    /* Update shop owner */
                    $updateShopOwnerData = array(
                        'name'          => $ownername,
                        'username'      => $username,
                        'activated'     => $activated,
                        'disabled'      => $disabled,
                        'updated_at'    => date('Y-m-d H:i:s')
                    );
                }
                $db->where('id', $id);
                $updateShopOwnerResult = $db->update('client', $updateShopOwnerData);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01159"][$language] /* Shop owner does not exist. */, 'data' => "");
            }

            if ($updateShopOwnerResult) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00512"][$language] /* Successfully Updated */, 'data' => "");
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00513"][$language] /* Failed to update */, 'data' => "");
            }
        }

        public function getShopList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $flag           = trim($params['getShopFlag']) ? $params['getShopFlag'] : 0;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            if ($flag) {
                $db->where('deleted', 0);
                $result = $db->get('shop', null, 'id, name');

                foreach($result as $key => $value) {
                    $getShop['id']      = $value['id'];
                    $getShop['name']    = $value['name'];

                    $getShopList[] = $getShop;
                }

                $data['getShopList'] = $getShopList;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['B00514'][$language] /*Successfully retrieved.*/, 'data' => $data);
            }

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'shopname':
                            if ($dataType == "like") {
                                $db->where('shop.name', "%" . $dataValue . "%", 'LIKE');
                            }
                            else {
                                $db->where('shop.name', $dataValue);
                            }
                            break;

                        case 'ownername':
                            if ($dataType == "like") {
                                $db->where('client.name', "%" . $dataValue . "%", 'LIKE');
                            }
                            else {
                                $db->where('client.name', $dataValue);
                            }
                            break;

                        case 'deleted':
                            $db->where('shop.deleted', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->join('client', 'client.id = shop.client_id');
            $copyDb = $db->copy();
            $db->orderBy('shop.id', "DESC");
            $result = $db->get('shop', $limit, 'shop.id, shop.name, shop.address, client.name as ownername, shop.deleted, shop.created_at');

            $totalRecord = $copyDb->getValue('shop', 'count(*)');

            if (!empty($result)) {
                foreach($result as $value) {
                    $shop['id']             = $value['id'];
                    $shop['name']           = $value['name'] != "" ? $value['name'] : "-";
                    $shop['address']        = $value['address'] != "" ? $value['address'] : "-";
                    $shop['ownername']      = $value['ownername'] != "" ? $value['ownername'] : "-";
                    $shop['deleted']        = ($value['deleted'] == 0) ? 'No' : 'Yes';
                    $shop['createdAt']      = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";

                    $shopList[] = $shop;
                }

                $data['shopList']       = $shopList;
                $data['totalPage']      = ceil($totalRecord/$limit[1]);
                $data['pageNumber']     = $pageNumber;
                $data['totalRecord']    = $totalRecord;
                $data['numRecord']      = $limit[1];

                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => $translations['B00101'][$language] /* No Results Found. */, 'data' => $data);
            }
        }

        public function addShop($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $owner_id       = trim($params['ownerId']);
            $shop_name      = trim($params['shopName']);
            $address        = trim($params['address']);

            if (!$owner_id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01160"][$language] /* Please assign a shop owner. */, 'data' => "");
            }
            if (!$shop_name) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01161"][$language] /* Shop name cannot be empty. */, 'data' => "");
            }
            if (!$address) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01162"][$language] /* Address cannot be empty. */, 'data' => "");
            }

            $db->where('id', $owner_id);
            $checkUser = $db->getOne('client');

            if ($checkUser && $checkUser['id']) {
                $insertShopData = array(
                    'name'          => $shop_name,
                    'address'       => $address,
                    'client_id'     => $owner_id,
                    'deleted'       => 0,
                    'created_at'    => date('Y-m-d H:i:s')
                );
                $insertShopResult = $db->insert('shop', $insertShopData);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01159"][$language] /* Shop owner does not exist. */, 'data' => "");
            }

            if ($insertShopResult) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00102"][$language] /* Successfully Added */, 'data' => "");
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00511"][$language] /* Failed to insert */, 'data' => "");
            }
        }

        public function editShop($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $shop_id        = trim($params['shopId']);
            $owner_id       = trim($params['ownerId']);
            $shop_name      = trim($params['shopName']);
            $address        = trim($params['address']);
            $deleted        = trim($params['deleted']) ? : 0;

            if (!$shop_id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01163"][$language] /* Shop does not exist. */, 'data' => "");
            }
            if (!$owner_id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01160"][$language] /* Please assign a shop owner. */, 'data' => "");
            }
            if (!$shop_name) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01161"][$language] /* Shop name cannot be empty. */, 'data' => "");
            }
            if (!$address) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01162"][$language] /* Address cannot be empty. */, 'data' => "");
            }

            /* Check existing shop */
            $db->where('id', $shop_id);
            $checkShop = $db->getOne('shop');

            if (!$checkShop || !$checkShop['id']) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01163"][$language] /* Shop does not exist. */, 'data' => "");
            }

            /* Check existing shop owner */
            $db->where('id', $owner_id);
            $checkUser = $db->getOne('client');

            if ($checkUser && $checkUser['id']) {
                $updateShopData = array(
                    'name'          => $shop_name,
                    'address'       => $address,
                    'client_id'     => $owner_id,
                    'deleted'       => $deleted,
                    'updated_at'    => date('Y-m-d H:i:s')
                );
                $db->where('id', $shop_id);
                $updateShopResult = $db->update('shop', $updateShopData);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01159"][$language] /* Shop owner does not exist. */, 'data' => "");
            }

            if ($updateShopResult) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00512"][$language] /* Successfully Updated */, 'data' => "");
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00513"][$language] /* Failed to Update */, 'data' => "");
            }
        }

        public function getShopDetail($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);
            $flag           = trim($params['getShopOwnerFlag']) ? $params['getShopOwnerFlag'] : 1;

            if (!$id)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01163"][$language] /* Shop does not exist. */, 'data' => "");

            $getShopOwnerList = self::getShopOwnerList($params);

            if ($getShopOwnerList) {
                $data['getShopOwnerList'] = $getShopOwnerList['data']['getShopOwnerList'];
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01159"][$language] /* Shop owner does not exist. */, 'data' => "");
            }

            $db->where('id', $id);
            $result = $db->getOne('shop', 'id, name, address, client_id as owner_id, deleted');

            if (!empty($result)) {
                foreach ($result as $key => $value) {
                    $shopDetail[$key] = $value;
                }

                $data['shopDetail'] = $shopDetail;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00514"][$language] /* Successfully Retrieved */, 'data' => $data);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00515"][$language] /* Failed to Retrieve. */, 'data' => "");
            }
        }

        public function purchaseRequestApprove($params, $username) {
            $db                      = MysqliDb::getInstance();
            $language                = General::$currentLanguage;
            $translations            = General::$translations;

            $id                      = trim($params['id']);
            $status                  = trim($params['status']);
            $dateTime                = date("Y-m-d H:i:s");
            $remarks                 = trim($params['remarks']);
            $buying_date             = trim($params['buying_date']);
            $purchase_product_list   = ($params['purchase_product_list']);
            $vendor_id               = trim($params['vendor_id']);

            $dataIn['id']                       = $id;
            $dataIn['remarks']                  = $remarks;
            $dataIn['buying_date']              = $buying_date;
            $dataIn['purchase_product_list']    = $purchase_product_list;
            $dataIn['vendor_id']                = $vendor_id;

            $resultOut = Admin::purchaseRequestEdit($dataIn);
            if(!$resultOut || $resultOut['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $resultOut['statusMsg'], 'data' => "");
            }

            $db->where('id', $id);
            $prStatus = $db->getOne('purchase_request');

            if(!$prStatus || strtolower($prStatus['status']) != 'save')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01710"][$language]/* Purchase Request is not able to edit */, 'data'=>'');
            }
                  
            $fields = array("id", "approved_date", "approved_by", "status");
            $values = array($id, $dateTime, $username, $status);
            // return $values;
            $arrayData = array_combine($fields, $values);
            
            $db->where('id', $id);
            $result = $db->update("purchase_request", $arrayData);

            $db->where('id', $id);
            $to_po = $db->getOne("purchase_request");

            $db->orderBy("order_number", "DESC");
            $db->where('order_number', "%" . 'GT-PO' . "%", 'LIKE');
            $lastOrderRecord = $db->getOne("purchase_order");

            if($lastOrderRecord) {
                $last_order_no = $lastOrderRecord["order_number"];
            } else {
                $last_order_no = "P00000";
            }
            // $last_order_no = 'GT-PO-000001';
            $next_order_no = self::generatePOCode($last_order_no);

            // return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00113"][$language], 'data'=>$next_order_no);

            $customDataIn['order_number']	    = $next_order_no;
            $customDataIn['product_name']       = $to_po['product_name'];
            $customDataIn['pr_id']              = $to_po['id'];
            $customDataIn['quantity']           = $to_po['quantity'];
            $customDataIn['product_cost']       = $to_po['product_cost'];
            $customDataIn['total_quantity']     = $to_po['total_quantity'];
            $customDataIn['total_cost']         = $to_po['total_cost'];
            $customDataIn['remarks']            = $to_po['remarks'];
            $customDataIn['status']             = 'Draft';
            $customDataIn['Created_by']         = $to_po['Created_by'];
            // $customDataIn['approved_by']        = $username;
            $customDataIn['approved_date']      = $to_po['approved_date'];
            $customDataIn['created_at']         = $to_po['approved_date'];
            $customDataIn['warehouse_id']       = $to_po['warehouse_id'];
            $customDataIn['vendor_address']     = $to_po['vendor_address'];
            $poId = $db->insert("purchase_order", $customDataIn);

            //insert PO product
            $db->where("purchase_request_id", $id);
            $arrPRProduct = $db->get("pr_product");

            foreach($arrPRProduct as $prpkey =>$prpvalue) {
		        $popData["purchase_request_id"] = $prpvalue["purchase_request_id"];
                $popData["purchase_order_id"] = $poId;
                $popData["vendor_id"] = $prpvalue["vendor_id"];
                $popData["product_id"] = $prpvalue["product_id"];
                $popData["product_name"] = $prpvalue["product_name"];
                $popData["quantity"] = $prpvalue["quantity"];
                $popData["cost"] = $prpvalue["cost"];
                $popData["total_cost"] = $prpvalue["total_cost"];
                $popData["type"] = $prpvalue["type"];
                $popData["created_at"] = $prpvalue["created_at"];
                $popData["updated_at"] = $prpvalue["updated_at"];
		        $db->insert("po_product", $popData);
            }


            //return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => $db->getLastQuery());      
           

            // return $to_po;   
            if ($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => $arrayData);
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00118"][$language] /* Invalid Admin */, 'data' => "");
            }
        }

        public function getVendorList($params)
        {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $vendorList = '';
            $vendorList = $db->get('vendor');

            $db->where('disabled', '0');
            $adminList = $db->get('admin');

            $data['vendorList'] = $vendorList;
            $data['adminList']  = $adminList;

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> "", 'data' => $data);
        }

        public function getProductList($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $vendorName     = trim($params['vendor_name']);
            $vendorID       = trim($params['vendor_id']);
            $branchName     = trim($params['branch_name']);
            $branchAddress  = trim($params['branch_address']);

            if(!$vendorID)
            {
                // get vendor ID
                $db->where('name',$vendorName);
                $vendorID = $db->getOne('vendor','id');
                $vendorID = $vendorID['id'];
            }

            //get branch list
            $db->where('vendor_id',$vendorID);
            $db->where('deleted', '0');
            $branchList = $db->get('vendor_address');

            // get product list
            $db->where('vendor_id',$vendorID);
            $db->where('is_archive', '0');
            $db->where('deleted', '0');
            $db->where('product_type', 'product');
            $productList = $db->get('product');
            
            $data['branch'] =$branchList;
            $data['product'] = $productList;
            if($productList && $branchList){
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "", 'data' => $data);
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01311"][$language] /* The selected vendor does not have any products available at the moment. Please choose another vendor */, 'data' => '');
            }
        }

        public function getShopDeviceList() {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'deviceref':
                            if ($dataType == "like") {
                                $db->where('device.device_ref', "%" . $dataValue . "%", 'LIKE');
                            }
                            else {
                                $db->where('device.device_ref', $dataValue);
                            }
                            break;

                        case 'devicename':
                            if ($dataType == "like") {
                                $db->where('device.devicename', "%" . $dataValue . "%", 'LIKE');
                            }
                            else {
                                $db->where('device.devicename', $dataValue);
                            }
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->join('shop', 'shop.id = device.shop_id');
            $copyDb = $db->copy();
            $db->orderBy('device.id', "DESC");
            $result = $db->get('device', $limit, 'device.id, device.device_ref, device.name, device.data, shop.name as shop_name, device.disabled, device.created_at');

            $totalRecord = $copyDb->getValue('device', 'count(*)');

            if (!empty($result)) {
                foreach($result as $value) {
                    $device['id']           = $value['id'];
                    $device['deviceRef']    = $value['device_ref'] != "" ? $value['device_ref'] : "-";
                    $device['name']         = $value['name'] != "" ? $value['name'] : "-";
                    $device['data']         = $value['data'] != "" ? $value['data'] : "-";
                    $device['shopName']     = $value['shop_name'] != "" ? $value['shop_name'] : "-";
                    $device['disabled']     = ($value['disabled'] == 0) ? 'No' : 'Yes';
                    $device['createdAt']    = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";

                    $deviceList[] = $device;
                }

                $data['deviceList']     = $deviceList;
                $data['totalPage']      = ceil($totalRecord/$limit[1]);
                $data['pageNumber']     = $pageNumber;
                $data['totalRecord']    = $totalRecord;
                $data['numRecord']      = $limit[1];

                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => $translations['B00101'][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function addShopDevice($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $shop_id        = trim($params['shopId']);
            $device_ref     = trim($params['deviceRef']);
            $device_name    = trim($params['deviceName']);

            if (!$shop_id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01163"][$language] /* Shop does not exist. */, 'data' => "");
            }
            if (!$device_ref) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01173"][$language] /* Please generate a device reference id. */, 'data' => "");
            }
            if (!$device_name) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01174"][$language] /* Device name cannot be empty. */, 'data' => "");
            }

            /* Check shop exists or not */
            $db->where('id', $shop_id);
            $checkShop = $db->getOne('shop');

            /* Create device depends on shop id */
            if ($checkShop && $checkShop['id']) {
                $insertDeviceData = array(
                    'name'          => $device_name,
                    'device_ref'    => $device_ref,
                    'shop_id'       => $shop_id,
                    'disabled'      => 0,
                    'created_at'    => date('Y-m-d H:i:s')
                );
                $insertDeviceResult = $db->insert('device', $insertDeviceData);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01163"][$language] /* Shop does not exist. */, 'data' => "");
            }

            if ($insertDeviceResult) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00102"][$language] /* Successfully Added */, 'data' => "");
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00511"][$language] /* Failed to insert */, 'data' => "");
            }
        }

        public function getShopDeviceDetail($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);
            $flag           = trim($params['getShopFlag']) ? $params['getShopFlag'] : 1;

            if (!$id)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01175"][$language] /* Device does not exist. */, 'data' => "");

            $getShopList = self::getShopList($params);

            if ($getShopList) {
                $data['getShopList'] = $getShopList['data']['getShopList'];
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01163"][$language] /* Shop does not exists. */, 'data' => "");
            }

            $db->where('id', $id);
            $result = $db->getOne('device', 'id, device_ref, name, shop_id, disabled');

            if (!empty($result)) {
                foreach ($result as $key => $value) {
                    $deviceDetail[$key] = $value;
                }

                $data['deviceDetail'] = $deviceDetail;

                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00514"][$language] /* Successfully Retrieved */, 'data' => $data);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00515"][$language] /* Failed to Retrieve. */, 'data' => "");
            }
        }

        public function editShopDevice($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $device_id      = trim($params['deviceId']);
            $shop_id        = trim($params['shopId']);
            $device_ref     = trim($params['deviceRef']);
            $device_name    = trim($params['deviceName']);
            $disabled       = trim($params['disabled']) ? : 0;

            if (!$device_id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01175"][$language] /* Device does not exists. */, 'data' => "");
            }
            if (!$shop_id) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01163"][$language] /* Shop does not exists. */, 'data' => "");
            }
            if (!$device_ref) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01173"][$language] /* Please generate a device reference id. */, 'data' => "");
            }
            if (!$device_name) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01174"][$language] /* Device name cannot be empty. */, 'data' => "");
            }

            /* Check existing device */
            $db->where('id', $device_id);
            $checkDevice = $db->getOne('device');

            if (!$checkDevice || !$checkDevice['id']) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01175"][$language] /* Device does not exists. */, 'data' => "");
            }

            /* Check existing shop owner */
            $db->where('id', $shop_id);
            $checkShop = $db->getOne('shop');

            if ($checkShop && $checkShop['id']) {
                $updateDeviceData = array(
                    'name'          => $device_name,
                    'device_ref'    => $device_ref,
                    'shop_id'       => $shop_id,
                    'disabled'      => $disabled,
                    'updated_at'    => date('Y-m-d H:i:s')
                );
                $db->where('id', $device_id);
                $updateDeviceResult = $db->update('device', $updateDeviceData);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01175"][$language] /* Device does not exists. */, 'data' => "");
            }

            if ($updateDeviceResult) {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00512"][$language] /* Successfully Updated */, 'data' => "");
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00513"][$language] /* Failed to Update */, 'data' => "");
            }
        }

        public function getShopWorkerList() {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    // switch($dataName) {
                    //     case 'deviceref':
                    //         if ($dataType == "like") {
                    //             $db->where('device.device_ref', "%" . $dataValue . "%", 'LIKE');
                    //         }
                    //         else {
                    //             $db->where('device.device_ref', $dataValue);
                    //         }
                    //         break;

                    //     case 'devicename':
                    //         if ($dataType == "like") {
                    //             $db->where('device.devicename', "%" . $dataValue . "%", 'LIKE');
                    //         }
                    //         else {
                    //             $db->where('device.devicename', $dataValue);
                    //         }
                    //         break;
                    // }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->where('client.type', "Worker");
            $db->join('assign_shop', 'assign_shop.client_id = client.id', 'INNER');
            $db->join('shop', 'shop.id = assign_shop.shop_id', 'INNER');
            $db->join('client shopowner', 'shopowner.id = shop.client_id', 'INNER');
            $copyDb = $db->copy();
            $db->orderBy('client.id', "DESC");
            $result = $db->get('client', null, 'client.id, client.username, client.name, client.dial_code, client.phone, client.last_login, assign_shop.id as assign_id, assign_shop.assigned_at, shop.id as shop_id, shop.name as shop_name, shopowner.name as ownername');

            $totalRecord = $copyDb->getValue('client', 'count(*)');

            if (!empty($result)) {
                foreach($result as $value) {
                    $worker['id']           = $value['id'];
                    $worker['username']     = $value['username'] != "" ? $value['username'] : "-";
                    $worker['name']         = $value['name'] != "" ? $value['name'] : "-";
                    $worker['phone']        = $value['phone'] != "" ? $value['phone'] : "-";
                    $worker['shopName']     = $value['shop_name'] != "" ? $value['shop_name'] : "-";
                    $worker['assignId']     = $value['assign_id'] != "" ? $value['assign_id'] : "-";
                    $worker['ownername']    = $value['ownername'] != "" ? $value['ownername'] : "-";
                    $worker['disabled']     = ($value['disabled'] == 0) ? 'No' : 'Yes';
                    $worker['lastLogin']    = $value['last_login'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['last_login'])) : "-";
                    $worker['createdAt']    = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['created_at'])) : "-";

                    $workerList[] = $worker;
                }

                $data['workerList']     = $workerList;
                $data['totalPage']      = ceil($totalRecord/$limit[1]);
                $data['pageNumber']     = $pageNumber;
                $data['totalRecord']    = $totalRecord;
                $data['numRecord']      = $limit[1];

                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => "", 'data' => $data);
            }
            else {
                return array('status' => "ok", 'code' => 0, 'msg' => "", 'statusMsg' => $translations['B00101'][$language] /* No Results Found. */, 'data' => "");
            }
        }

        public function getPurchaseOrderList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $adminID        = $db->userID;

            $searchData     = $params['inputData'];
            $sortData       = $params['sortData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            # check admin permission
            $permissionDataIn['type'] = $params['permissionType'];
            $permissionDataIn['module'] = $params['module'];
            $permissionDataIn['user_id'] = $adminID;

            $adminPermission = Permission::checkActionPermission($permissionDataIn);
            if($adminPermission['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $adminPermission['statusMsg'], 'data'=>$adminPermission);
            }

            $arrPermission = $adminPermission['data'];
            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    if(!$dataValue){
                        return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");

                    }
                    
                    switch($dataName) {
                        // purchase request id
                        case 'po_id':
                            $db->where('po.id', $dataValue);
                            // $db->where('po.id', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase buying date
                        case 'buyingDate':
                            $columnName = 'DATE(po.purchase_date)';

                            // $dateFrom = trim($v['tsFrom']);
                            // $dateTo = trim($v['tsTo']);

                            if($dataValue == "false" || !$dataValue){
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }

                            $db->where($columnName, date('Y-m-d', $dataValue));

                            // if(intval($dateFrom) > intval($dateTo))
                            // {
                            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            // }
                            
                            // if(strlen($dateFrom) > 0) {
                            //     if($dateFrom < 0)
                            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            //     $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            // }
                            // if(strlen($dateTo) > 0) {
                            //     if($dateTo < 0)
                            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                            //     if($dateTo < $dateFrom)
                            //         return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                            //     // $dateTo += 86399;
                            //     $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            // }

                            // unset($dateFrom);
                            // unset($dateTo);
                            unset($columnName);
                            break;
                        // vendor name
                        case 'name':
                            $db->where('v.name', "%" . $dataValue . "%", 'LIKE');
                            break;
                        case 'refNo':
                            $db->where('po.order_number', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request status
                        case 'status':
                            $db->where('po.status', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve ppl
                        case 'approvedBy':
                            $db->where('po.approved_by', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve date
                        case 'approvedDate':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(intval($dateFrom) > intval($dateTo))
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }

                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        case 'assignee' :
                            $searchAssignee = $dataValue;
                            break;
                        case 'warehouseSearch' :
                            $arrPermission2 = array();
                            foreach($arrPermission as $detailPermission)
                            {
                                if (strcasecmp($detailPermission, $dataValue) === 0)
                                {
                                    $arrPermission2[] = $dataValue;
                                }
                            }
                            unset($arrPermission);
                            $arrPermission = $arrPermission2;
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $sortOrder = "DESC";
            $sortField = 'po.id';

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

            $rule = array(
                array('col' => 'po.status', 'val' => 'hard_delete', 'op' => '!=')
            );

            foreach($rule as $v){
                $db->where($v['col'], $v['val'], $v['op']);
            }
            if(count($arrPermission) > 0)
            {
                $db->where("w.warehouse_location", $arrPermission, "IN");
            }
            else
            {
                $db->where("w.warehouse_location", array($arrPermission), "IN");
            }
            $db->where('po.deleted', '0');
            $db->join('warehouse w', 'w.id = po.warehouse_id', 'LEFT');
            $db->join('vendor v', 'v.id = po.vendor_id', 'INNER');
            $copyDb = $db->copy();
	        $summaryDb = $db->copy();
            $results = $db->get('purchase_order po', $limit, 'po.id as id, po.purchase_date as buying_date, po.order_number as orderNo, v.name as vendor_name, po.total_cost as total_cost, po.status as status, po.created_by, po.approved_by, po.created_at, po.remarks, po.vendor_id, po.order_number, po.total_quantity, w.warehouse_location');
            $totalRecord = $copyDb->getValue ('purchase_order po', 'count(*)');

            $db->where('ps.status', 'reject' , '!=');
            $db->join('admin a', 'a.id = ps.assignee', 'INNER');
            $poAssignList = $db->get('po_assign ps');

            if (!empty($results)) {

                foreach($results as $value) {
                    foreach($poAssignList as $detailPoAssign)
                    {
                        if($detailPoAssign['po_id'] == $value['id'])
                        {
                            $assignee  = $detailPoAssign['name'];
                            break;
                        }
                        else
                        {
                            $assignee = '-';
                        }
                    }
                    if($searchAssignee){

                        $db->join('admin a', 'a.id = ps.assignee', 'INNER');
                        $db->where('name', "%" . $searchAssignee . "%", 'LIKE');
                        $poAssignList = $db->get('po_assign ps');

                        if(!empty($poAssignList)){
                            foreach($poAssignList as $detailPoAssign)
                            {
                                if($detailPoAssign['po_id'] == $value['id'])
                                {
                                    $assignee  = $detailPoAssign['name'];
    
                                    if(!$assignee)
                                    {
                                        $purchaseOrder['assignee']        = '-';
                                    }
                                    else
                                    {
                                        $purchaseOrder['assignee']        = $assignee;
                                    }
                                    $purchaseOrder['id']              = $value['id'];
                                    $purchaseOrder['order_number']    = $value['order_number'];
                                    $purchaseOrder['vendor']          = $value['vendor_name'];
                                    $purchaseOrder['quantity']        = $value['total_quantity'];
                                    $purchaseOrder['cost']            = number_format(($value['total_cost']), 2);
                                    $purchaseOrder['buying_date']     = $value['buying_date'];
                                    $purchaseOrder['status']          = $value['status'];
                                    $purchaseOrder['remarks']         = $value['remarks'];
                                    $purchaseOrder['approved_by']     = ($value['approved_by']!=""?$value['approved_by']:"-");
                                    $purchaseOrder['Created_by']      = $value['created_by'];
                                    $purchaseOrder['created_at']      = $value['created_at'];
                                    // $purchaseOrder['created_at']      = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['approved_date'])) : "-";
                                    $purchaseOrder['warehouse_location'] = $value['warehouse_location'];
                                    $purchaseOrderList[] = $purchaseOrder;
                                }
                                $totalRecord = count($purchaseOrderList);
                            }
                        }else{
                            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
                        }
                       
                    }else{
                        
                        if(!$assignee)
                        {
                            $purchaseOrder['assignee']        = '-';
                        }
                        else
                        {
                            $purchaseOrder['assignee']        = $assignee;
                        }
                        $purchaseOrder['id']              = $value['id'];
                        $purchaseOrder['order_number']    = $value['order_number'];
                        $purchaseOrder['vendor']          = $value['vendor_name'];
                        $purchaseOrder['quantity']        = $value['total_quantity'];
                        $purchaseOrder['cost']            = number_format(($value['total_cost']), 2);
                        $purchaseOrder['buying_date']     = $value['buying_date'];
                        $purchaseOrder['status']          = $value['status'];
                        $purchaseOrder['remarks']         = $value['remarks'];
                        $purchaseOrder['approved_by']     = ($value['approved_by']!=""?$value['approved_by']:"-");
                        $purchaseOrder['Created_by']      = $value['created_by'];
                        $purchaseOrder['created_at']      = $value['created_at'];
                        // $purchaseOrder['created_at']      = $value['created_at'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['approved_date'])) : "-";
                        $purchaseOrder['warehouse_location'] = $value['warehouse_location'];
                        $purchaseOrderList[] = $purchaseOrder;
                    }
                }

                $summaryDb->groupBy("po.status");
                $arrSummaryDetail = $summaryDb->map("status")->get("purchase_order po", null, "po.status, count(*) as total");

                $allCount = $rfqCount = $approvedCount = $purchasingCount = $stockInCount = $doneCount = $cancelledCount = 0;
                foreach($arrSummaryDetail as $skey => $svalue) {

                    if(strtolower($skey)=="done") $doneCount = $svalue;
                    if(strtolower($skey)=="rfq") $rfqCount = $svalue;
                    if(strtolower($skey)=="pending for stock in") $stockInCount = $svalue;
                    if(strtolower($skey)=="approved") $approvedCount = $svalue;
                    if(strtolower($skey)=="purchasing") $purchasingCount = $svalue;
                    if(strtolower($skey)=="cancelled") $cancelledCount = $svalue;
                    $allCount += $svalue;
                }

		        $data['summary'] = array("all"=>$allCount, "rfq"=>$rfqCount, "approved"=>$approvedCount, "purchasing"=>$purchasingCount, "stockIn"=>$stockInCount, "done"=>$doneCount, "cancelled"=>$cancelledCount);

                $data['purchaseOrderList']    = $purchaseOrderList;
                $data['pageNumber']             = $pageNumber;
                $data['totalRecord']            = $totalRecord;
                $data['totalPage']              = ceil($totalRecord/$limit[1]);
                $data['numRecord']              = $limit[1];

                # check admin got which permission
                $permissionDataIn['type'] = 'action';
                $permissionDataIn['module'] = 'PO';
                $permissionDataIn['user_id'] = $adminID;
                $adminPermission = Permission::checkActionPermission($permissionDataIn);

                $data['adminPermission'] = $adminPermission['data'];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }else {
                $permissionDataIn['type'] = 'action';
                $permissionDataIn['module'] = 'PO';
                $permissionDataIn['user_id'] = $adminID;
                $adminPermission = Permission::checkActionPermission($permissionDataIn);

                $data['adminPermission'] = $adminPermission['data'];
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => $data);
            }
        }

        public function appGetPurchaseOrderList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['inputData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);
                        
                    switch($dataName) {
                        // purchase request id
                        case 'po_id':
                            $db->where('po.id', $dataValue);
                            // $db->where('po.id', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase buying date
                        case 'buyingDate':
                            $columnName = 'DATE(pr.buying_date)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(intval($dateFrom) > intval($dateTo))
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }
                            
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }

                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        // vendor name
                        case 'name':
                            $db->where('v.name', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request status
                        case 'status':
                            $db->where('po.status', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve ppl
                        case 'approvedBy':
                            $db->where('po.approved_by', "%" . $dataValue . "%", 'LIKE');
                            break;
                        // purchase request approve date
                        case 'approvedDate':
                            $columnName = 'DATE(po.approved_date)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);

                            if(intval($dateFrom) > intval($dateTo))
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                            }

                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                $db->where($columnName, date('Y-m-d', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");

                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d', $dateTo), '<=');
                            }
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                        case 'warehouseSearch' :
                            $db->where('w.warehouse_location', '%'. $dataValue . '%' , 'LIKE');
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $db->orderBy('po.id', 'DESC');
            $db->join('warehouse w', 'w.id = po.warehouse_id', 'LEFT');
            $db->join('purchase_request pr', 'pr.id = po.pr_id', 'INNER');
            // $db->join('pr_product pp', 'pp.purchase_request_id = pr.id','LEFT');
            // $db->join('vendor v', 'v.id = pp.vendor_id', 'LEFT');
            $db->join('product p', 'p.id = pr.product_id', 'INNER');
            $db->join('vendor v', 'v.id = pr.vendor_id', 'INNER');
            $copyDb = $db->copy();
	        $summaryDb = $db->copy();
            $results = $db->get('purchase_order po', $limit, 'po.id as id, po.purchase_date as buying_date, v.name as vendor_name, po.total_cost as total_cost, po.status as status, po.Created_by as created_by, po.approved_by as approved_by, po.approved_date as approved_date, po.remarks as remarks, pr.vendor_id as vendor_id, po.order_number, po.total_quantity, w.warehouse_location');
            
            $totalRecord = $copyDb->getValue ('purchase_order po', 'count(*)');

            if (!empty($results)) {

                foreach($results as $value) {
                    $purchaseOrder['id']              = $value['id'];
                    $purchaseOrder['order_number']         = $value['order_number'];
                    $purchaseOrder['vendor']          = $value['vendor_name'];
                    $purchaseOrder['quantity']        = $value['total_quantity'];
                    $purchaseOrder['cost']            = $value['total_cost'];
                    $purchaseOrder['buying_date']     = $value['buying_date'];
                    $purchaseOrder['status']          = $value['status'];
                    $purchaseOrder['remarks']         = $value['remarks'];
                    $purchaseOrder['approved_by']     = ($value['approved_by']!=""?$value['approved_by']:"-");
                    $purchaseOrder['Created_by']      = $value['created_by'];
                    $purchaseOrder['approved_date']   = $value['approved_date'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['approved_date'])) : "-";
                    $purchaseOrder['warehouse_location'] = $value['warehouse_location'];
                    $purchaseOrderList[] = $purchaseOrder;

                }
		
                $db->groupBy("po.status");
                $db->join('warehouse w', 'w.id = po.warehouse_id', 'LEFT');
                $db->join('purchase_request pr', 'pr.id = po.pr_id', 'INNER');
                $db->join('product p', 'p.id = pr.product_id', 'INNER');
                $db->join('vendor v', 'v.id = pr.vendor_id', 'INNER');
                $arrSummaryDetail = $db->map("status")->get("purchase_order po", null, "po.status, count(*) as total");

                $allCount = $draftCount = $confirmedCount = $stockInCount = $doneCount = $cancelledCount = 0;
                foreach($arrSummaryDetail as $skey => $svalue) {

                    if(strtolower($skey)=="done") $doneCount = $svalue;
                    if(strtolower($skey)=="rfq") $rfqCount = $svalue;
                    if(strtolower($skey)=="pending for stock in") $stockInCount = $svalue;
                    if(strtolower($skey)=="approved") $approvedCount = $svalue;
                    if(strtolower($skey)=="purchasing") $purchasingCount = $svalue;
                    if(strtolower($skey)=="cancelled") $cancelledCount = $svalue;
                    $allCount += $svalue;
                }

		        $data['summary'] = array("all"=>$allCount, "rfq"=>$rfqCount, "approved"=>$approvedCount, "purchasing"=>$purchasingCount, "stockIn"=>$stockInCount, "done"=>$doneCount, "cancelled"=>$cancelledCount);

                $data['purchaseOrderList']    = $purchaseOrderList;
                $data['pageNumber']             = $pageNumber;
                $data['totalRecord']            = $totalRecord;
                $data['totalPage']              = ceil($totalRecord/$limit[1]);
                $data['numRecord']              = $limit[1];

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
                }else {
                    $db->groupBy("po.status");
                    $db->join('warehouse w', 'w.id = po.warehouse_id', 'LEFT');
                    $db->join('purchase_request pr', 'pr.id = po.pr_id', 'INNER');
                    $db->join('product p', 'p.id = pr.product_id', 'INNER');
                    $db->join('vendor v', 'v.id = pr.vendor_id', 'INNER');
                    $arrSummaryDetail = $db->map("status")->get("purchase_order po", null, "po.status, count(*) as total");

                    $allCount = $draftCount = $confirmedCount = $stockInCount = $doneCount = $cancelledCount = 0;
                    foreach($arrSummaryDetail as $skey => $svalue) {

                        if(strtolower($skey)=="done") $doneCount = $svalue;
                                if(strtolower($skey)=="draft") $draftCount = $svalue;
                                if(strtolower($skey)=="pending for stock in") $stockInCount = $svalue;
                                if(strtolower($skey)=="confirmed") $confirmedCount = $svalue;
                                if(strtolower($skey)=="cancelled") $cancelledCount = $svalue;
                        $allCount += $svalue;
                    }

                    $data['summary'] = array("all"=>$allCount, "draft"=>$draftCount, "confirmed"=>$confirmedCount, "stockIn"=>$stockInCount, "done"=>$doneCount, "cancelled"=>$cancelledCount);

                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => $data);
            }
        }

        public function getPurchaseOrderDetails($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $adminID        = $db->userID;
            $id             = trim($params['id']);

            # check admin permission
            $permissionDataIn['type'] = $params['permissionType'];
            $permissionDataIn['module'] = $params['module'];
            $permissionDataIn['user_id'] = $adminID;

            $adminPermission = Permission::checkActionPermission($permissionDataIn);
            if($adminPermission['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $adminPermission['statusMsg'], 'data'=>$adminPermission);
            }

            $arrPermission = $adminPermission['data'];

            if($arrPermission['read'] != 1)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01290"][$language] /* You do not have permission to view Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
            }

            if (strlen($id)==0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00104"][$language] /* Please Select Admin */, 'data'=> '');

            $getWarehouse = self::getWarehouse($params);
            $data['warehouse'] = $getWarehouse['data'];

            // get vendor detail
            $db->where('id', $id);
            $poDetail = $db->getOne('purchase_order');
            if(!empty($poDetail))
            {
                $db->where('id', $id);
                $vendor_id = $db->getOne('purchase_order', 'vendor_id');
            }

            if(!empty($vendor_id))
            {
                $vendor_id = $vendor_id['vendor_id'];
                $db->where('id', $vendor_id);
                $vendorDetail = $db->getOne('vendor');
            }

            // get warehouse location
            $db->where('id',$poDetail['warehouse_id']);
            $warehouseDetail = $db->getOne('warehouse');
            
            if(!empty($warehouseDetail))
            {
                $data['current_warehouse_location'] = $warehouseDetail['warehouse_location'];
                $data['current_warehouse_address'] = $warehouseDetail['warehouse_address'];
            }
            else
            {
                $data['current_warehouse_location'] = 'Not selected.';
            }
            $data['current_warehouse_id'] = $poDetail['warehouse_id'];

            $db->where('po.id', $id);
            $db->join('purchase_order_product pp', 'po.id = pp.purchase_order_id', 'LEFT');
            $db->join('product p', 'p.id = pp.product_id', 'LEFT');
            $db->join('vendor v', 'v.id = po.vendor_id', 'LEFT');
            $result = $db->get('purchase_order po', null, 'pp.id as purchase_product_id, po.order_number, pp.product_id, pp.cost, pp.product_name as name, pp.quantity, po.created_by, po.approved_by, po.id as purchase_order_id, po.total_cost, po.remarks, pp.type, p.expired_day, v.name as vendor_name, v.vendor_code, po.vendor_address, v.mobile as vendor_mobile, po.status, po.purchase_date');

            $db->orderBy('s.product_id', 'ASC');
            $db->where('s.po_id', $id);
            $db->where('s.status', 'Inactive', '!=');
            $db->join('product p', 's.product_id = p.id', 'LEFT');
            $stockDetailList = $db->get('stock s', null, 's.product_id as stock_product_id, p.name as stock_product_name, s.serial_number, s.rack_no, s.expiration_date');

            if($stockDetailList)
            {
                foreach($stockDetailList as $detailStock)
                {
                    $serialDetail['stock_product_id']       = $detailStock['stock_product_id'];
                    $serialDetail['stock_product_name']     = $detailStock['stock_product_name'];
                    $serialDetail['serial_number']          = $detailStock['serial_number'];

                    $serialList[] = $serialDetail;
                }
                foreach($stockDetailList as $detailStock)
                {
                    $serialNumberList[] = $detailStock['serial_number'];
                }

                # set for validated stock
                $validatedStock = array();
                foreach($stockDetailList as $detailStock)
                {
                    if($detailStock['rack_no'] != null && $detailStock['rack_no'] != '')
                    {
                        $validatedStockDetail['productName'] = $detailStock['stock_product_name'];
                        $validatedStockDetail['serialNo'] = $detailStock['serial_number'];
                        $validatedStockDetail['location'] = $detailStock['rack_no'];
                        $validatedStockDetail['bestBefore'] = $detailStock['expiration_date'];
                        $validatedStock[] = $validatedStockDetail;
                    }
                }
            }

            // Sort the array by stock_product_id
            usort($serialList, function ($a, $b) {
                return $a['stock_product_id'] <=> $b['stock_product_id'];
            });

            // Group the serial numbers by stock_product_id
            $sortedSerialList = [];
            $currentProductId = null;

            foreach ($serialList as $item) {
                $productId = $item['stock_product_id'];
                $productName = $item['stock_product_name'];
                $serialNumber = $item['serial_number'];

                if ($productId !== $currentProductId) {
                    $currentProductId = $productId;
                    $sortedSerialList[] = [
                        'stock_product_id' => $productId,
                        'stock_product_name' => $productName,
                        'serial_numbers' => [$serialNumber],
                    ];
                } else {
                    $lastIndex = count($sortedSerialList) - 1;
                    $sortedSerialList[$lastIndex]['serial_numbers'][] = $serialNumber;
                }
            }
            foreach ($sortedSerialList as &$item) {
                asort($item['serial_numbers']);
            }

            // Format the output
            $displaySerialNumber = array_map(function ($item) {
                $productName = $item['stock_product_name'];
                $serialNumbers = implode(', ', $item['serial_numbers']);
                return $productName . ': ' . $serialNumbers;
            }, $sortedSerialList);

            if (empty($result)) {
                // return $result;
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'Unexpected Error Occured', 'data' => $result);
            }
            else
            {
                // get PO image
                $db->where('deleted', '0');
                $db->where('reference_id', $id);
                $db->where('type', 'Image');
                $poImageDetails = $db->get('purchase_media', null, 'id, name, url, type as uploadType');

                foreach($poImageDetails as $poImageDetailsRow) {
                    if($poImageDetailsRow['uploadType'] == 'Image'){
                        $imageDetail['url']  = $poImageDetailsRow;
                        $imageList[] = $imageDetail['url'];
                    }elseif ($poImageDetailsRow['uploadType'] == 'Video') {
                        $videoList[] = $poImageDetailsRow;
                    }
                }

                foreach($result as $row)
                {
                    $productDetail['purchase_product_id']   = $row['purchase_product_id'];
                    $productDetail['product_id']            = $row['product_id'];
                    $productDetail['cost']                  = $row['cost'];
                    $productDetail['name']                  = $row['name'];
                    $productDetail['quantity']              = $row['quantity'];
                    // $productDetail['vendor_id']             = $vendorDetail['id'];
                    $productDetail['vendor_name']           = $vendorDetail['name'];
                    $productDetail['type']                  = $row['type'];
                    $otherDetails['created_by']             = $row['created_by'];
                    $otherDetails['approved_by']            = $row['approved_by'];
                    $productDetail['purchase_order_id']     = $row['purchase_order_id'];
                    $otherDetails['total_cost']             = $row['total_cost'];
                    $otherDetails['remarks']                = $row['remarks'];
                    $otherDetails['vendor_name']            = $row['vendor_name'];
                    $otherDetails['vendor_code']            = $row['vendor_code'];
                    $otherDetails['vendor_address']         = $row['vendor_address'];
                    $otherDetails['vendor_mobile']           = $row['vendor_mobile'];
                    $otherDetails['status']		            = $row['status'];
                    $otherDetails['order_number']           = $row['order_number'];
                    $otherDetails['purchase_date']          = $row['purchase_date'];
                    $currentDate = new DateTime();
                    $currentDate->add(new DateInterval('P' . $row['expired_day'] . 'D'));
                    $expiryDate = $currentDate->format('Y-m-d');
                    $productDetail['best_before_days']      = $expiryDate;

                    $productList[] = $productDetail;
                }

                // sort by product_id (descending)
                $productIds = array_column($productList, 'product_id');
                $types = array_column($productList, 'type');
                
                array_multisort($productIds, SORT_ASC, $types, $productList);

                $data['productList'] = $productList;
                $data['vendor_id'] = $vendorDetail['id'];
                $data['vendor_name'] = $otherDetails['vendor_name'];
                $data['vendor_code'] = $otherDetails['vendor_code'];
                $data['vendor_address'] = $otherDetails['vendor_address'];
                $data['vendor_mobile'] = $otherDetails['vendor_mobile'];
                $data['total_cost'] = $otherDetails['total_cost'];
                $data['created_by'] = $otherDetails['created_by'];
                $data['approved_by'] = $otherDetails['approved_by'];
		        $data['status'] =  $otherDetails['status'];
                $data['remarks'] = $otherDetails['remarks'];
                $data['purchase_date'] = $otherDetails['purchase_date'];
                $data['serialList'] = $displaySerialNumber;
                $data['serialNumberList'] = $serialNumberList;
                $data['order_number']   = $otherDetails['order_number'];
                $data['validatedStock'] = $validatedStock;

                # get serial number for each stock
                $db->where('po_id', $id);
                $db->where('status', 'inactive', '!=');
                $activeStockList = $db->get('stock', null, 'product_id, serial_number, rack_no');
                $data['activeStockList'] = $activeStockList;

                # sort serial number to show Increment style
                foreach ($stockDetailList as $item) {
                    $showIncrement[] = $item['serial_number'] . ',id:' . $item['stock_product_id'];
                }
                $data['showIncrement'] = $showIncrement;

                # get assignee
                $db->orderBy('pa.id', 'DESC');
                $db->where('pa.po_id', $id);
                $db->where('pa.status', 'reject', '!=');
                $db->join('admin a', 'a.id = pa.assignee', 'INNER');
                $poAssign = $db->getOne('po_assign pa', 'pa.assignee, a.name');

                $data['assignTo']   = $poAssign['assignee'];
                $data['assigneeName'] = $poAssign['name'];
                if(empty($imageList))
                {
                    $imageList = array();
                    $data['imageList'] = $imageList;
                }
                else
                {
                    $data['imageList'] = $imageList;
                }

                # get for full set serial number with info
                $db->groupBy('s.product_id');
                $db->where('s.po_id', $id);
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $totalList = $db->get('stock s', null, 'p.id as productId, p.name, COUNT(*) as quantity');

                # get Detailed Operations
                $db->orderBy('s.product_id', 'ASC');
                $db->where('s.po_id', $id);
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $getDetailedOperation = $db->get('stock s', null, 'p.id as productId, p.name, s.serial_number, s.expiration_date');

                $data['totalProductList'] = $totalList;
                $data['getDetailedOperation'] = $getDetailedOperation;

                # Company address
                $db->where('name', 'pickUpOrigins');
                $companyAddress = $db->getValue('system_settings', 'reference');
                $data['companyAddress'] = $companyAddress;

                # get Total PO
                $data['totalPO'] = $db->get('purchase_order', null ,'id');
                return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' => $data);
            }
        }

        public function purchaseOrderEdit($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date('Y-m-d H:i:s');
            $adminID                 = $db->userID;

            $id                      = trim($params['id']);
            $warehouse_id            = trim($params['warehouse_id']);
            $remarks                 = ($params['remarks']);
            $purchase_product_list   = ($params['purchase_product_list']);
            $vendor_id               = trim($params['vendor_id']);
            $buying_date             = trim($params['buying_date']);
            $approved_by             = trim($params['approved_by']);
            $uploadImage             = $params['uploadImage'];
            $imageId                 = $params['imageId'];
            $assign_id               = $params['assign_id'];
            
            if($total_cost || !$total_cost)
                $total_cost = $product_cost * $quantity;

            if($total_quantity || !$total_quantity)
                $total_quantity = $quantity;

            if($updated_at || !$updated_at)
                $updated_at = $updated_at;


            // check PO status is available to edit or not
            $db->where('id', $id);
            $poDetail = $db->getOne('purchase_order');

            if(empty($poDetail))
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => "");
            }
            if($poDetail['status'] != 'RFQ' && $poDetail['status'] != 'Approved' && $poDetail['status'] != 'Purchasing')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01711"][$language] /* Purchase Order is not able to edit */, 'data' => "");
            }

            # if different vendor id, should clear all product from this PO, re-insert
            if(!$vendor_id)
            {
                $vendor_id = $poDetail['vendor_id'];
            }
            if($vendor_id != $poDetail['vendor_id'])
            {
                $updateData = array(
                    'vendor_id'  => $vendor_id,
                    'updated_at' => $dateTime,
                );
                $db->where('id', $id);
                $db->update('purchase_order', $updateData);
    
                $db->where('purchase_order_id', $id);
                $db->delete('purchase_order_product');
            }

            # remove product that have action = delete
            foreach ($purchase_product_list as $key => $product) {
                if ($product['action'] === 'delete') {
                    unset($purchase_product_list[$key]);
                }
            }
            $purchase_product_list = array_values($purchase_product_list);

            $db->where('purchase_order_id', $id);
            $currentPOProduct = $db->get('purchase_order_product');

            $notInList = [];

            foreach ($currentPOProduct as $product) {
                $idToCheck = $product['id'];
                $found = false;

                foreach ($purchase_product_list as $item) {
                    if ($item['id'] == $idToCheck) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $notInList[] = $product;
                }
            }

            # delete
            foreach($notInList as $detailDelete)
            {
                $db->where('id', $detailDelete['id']);
                $db->where('purchase_order_id', $id);
                $db->delete('purchase_order_product');
            }

            foreach($purchase_product_list as $row)
            {
                $productDetail['quantity']    = $row['quantity'];
                $productDetail['cost']        = $row['cost'];
                $productDetail['total_cost']  = floatval($row['quantity'] * $row['cost']);
                $productDetail['product_id']  = $row['product_id'];

                if($row['action'] != 'delete')
                {
                    if(empty($productDetail['product_id']) || empty($row['name']))             
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00955"][$language] /* Invalid Product */, 'data' => $row);
                    }
                    
                    if(empty($productDetail['quantity']))
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01223"][$language] /* Product Quantity must be at least 1 */, 'data' => '');
                    }
                }

                if(strtolower($row['type']) != 'foc')
                {
                    $row['type'] = 'Purchase';
                }
                $productDetail['type']        = $row['type'];
                if($row['action'] == 'add')
                {
                    // add new product
                    $productDetail2['id']           = $row['id'];
                    $productDetail2['product_id']   = $row['product_id'];
                    $productDetail2['name']         = $row['name'];
                    $productDetail2['cost']         = $row['cost'];
                    $productDetail2['quantity']     = $row['quantity'];
                    $productDetail2['action']       = $row['action'];
                    // $total_cost = intval($productDetail2['cost']) * intval($productDetail2['quantity']);
                    $current_time = date('Y-m-d H:i:s');

                    $total_cost = intval($productDetail2['quantity']) * floatval($productDetail2['cost']);
                    $fields = array("purchase_order_id", "product_id", 'product_name', "quantity", "cost", "type", "total_cost", "created_at");
                    $values = array($id, $productDetail2['product_id'], $productDetail2['name'], $productDetail2['quantity'], $productDetail2['cost'], $row['type'], $total_cost, date("Y-m-d H:i:s"));
                    $arrayData2 = array_combine($fields, $values);
                    
                    if(strtolower($row['type']) == 'foc')
                    {
                        // record in FOC list array
                        $focList[] = $productDetail2;
                    }

                    // check if there is existing record or not
                    $db->where('product_id', $productDetail2['product_id']);
                    $db->where('purchase_order_id', $id);
                    if($row['type'] == 'foc')
                    {
                        $db->where('type', 'FOC');
                    }
                    else
                    {
                        $db->where('type', 'Purchase');
                    }
                    $result = $db->get('purchase_order_product');

                    if(strtolower($row['type']) != 'foc')
                    {
                        $storeProductID = array();
                        if(empty($result))
                        {
                            $db->insert('purchase_order_product', $arrayData2);
                        }
                        else
                        {
                            // if have existing record, and its same product id, sum it up.
                            foreach($result as $purchaseProductDetail)
                            {
                                if($purchaseProductDetail['product_id'] == $productDetail2['product_id'])
                                {
                                    if(!in_array($purchaseProductDetail['product_id'], $storeProductID)) 
                                    {
                                        $updatedQuantity = intval($purchaseProductDetail['quantity']) + intval($productDetail2['quantity']);
    
                                        $total_cost = $updatedQuantity * floatval($productDetail2['cost']);
                                        $fields = array("purchase_order_id", "product_id", 'product_name', "quantity", "cost", "type", "total_cost", "created_at");
                                        $values = array($id, $productDetail2['product_id'], $productDetail2['name'], $updatedQuantity, $productDetail2['cost'], $row['type'], $total_cost, date("Y-m-d H:i:s"));
                                        $arrayData = array_combine($fields, $values);
    
                                        $db->where('purchase_order_id',$id);
                                        $db->where('product_id', $productDetail2['product_id']);
                                        if(strtolower($row['type']) == 'foc')
                                        {
                                            $db->where('type', 'FOC');
                                        }
                                        else
                                        {
                                            $db->where('type', 'Purchase');
                                        }
                                        $db->where('id', $purchaseProductDetail['id']);
                                        $db->update('purchase_order_product', $arrayData);
                                        $storeProductID[] = $productDetail2['product_id'];
                                    }
                                }
                                else
                                {
                                    $db->where('purchase_order_id',$id);
                                    $db->where('product_id', $productDetail2['product_id']);
                                    if($row['type'] == 'foc')
                                    {
                                        $db->where('type', 'FOC');
                                    }
                                    else
                                    {
                                        $db->where('type', 'Purchase');
                                    }
                                    $db->update('purchase_order_product', $arrayData2);
                                }
                            }
                        }
                    }
                }
                else
                {
                    if(strtolower($row['type']) == 'foc')
                    {
                        // record in FOC list array
                        if($row['action'] != 'delete')
                        {
                            $focList[] = $productDetail;
                        }
                    }
                    $db->where('id', $productDetail['product_id']);
                    $productName = $db->getOne('product', 'name');
                    $fields = array("product_id", "product_name", "quantity", "cost", "total_cost", "type", "updated_at");
                    $values = array($productDetail['product_id'], $productName['name'], $productDetail['quantity'],$productDetail['cost'], $productDetail['total_cost'], $productDetail['type'], date("Y-m-d H:i:s"));
                    $arrayData = array_combine($fields, $values);

                    // check the modify data is foc or not
                    $db->where('id', $row['id']);
                    $poProductStatus = $db->getOne('purchase_order_product');

                    if($poProductStatus['type'] != 'FOC')
                    {
                        if(strtolower($row['type']) != 'foc')
                        {
                            $db->where('purchase_order_id',$id);
                            // $db->where('product_id',$productDetail['product_id']);
                            $db->where('id', $row['id']);
                            $db->where('type', 'Purchase');
                            $db->update('purchase_order_product', $arrayData);
                        }
                    }
                    else
                    {
                        $fields = array("quantity", "cost", "total_cost", "updated_at");
                        $values = array($productDetail['quantity'],$productDetail['cost'], $productDetail['total_cost'], date("Y-m-d H:i:s"));
                        $newArrayData = array_combine($fields, $values);
                        $db->where('id', $row['id']);
                        $db->update('purchase_order_product', $newArrayData);
                    }
                }
                $arrayList[]    = $arrayData;
            }
            // update every product in purchase product table cost that have FOC
            foreach($focList as $foc)
            {
                // get the purchase product table detail
                $db->where('purchase_order_id', $id);
                $db->where('product_id', $foc['product_id']);
                $productDetail = $db->getOne('purchase_order_product');

                $currentCost = $productDetail['cost'];
                $currentQuantity = $productDetail['quantity'];

                $updatedQuantity = intval($currentQuantity) + intval($foc['quantity']);
                $updatedCost     = floatval($productDetail['total_cost']) / $updatedQuantity;

                $updatedInfo['productId'] = $productDetail['id'];
                $updatedInfo['updatedCost'] = $updatedCost;
                $updatedInfo['updatedQuantity'] = $updatedQuantity;
                $updatedInformation[] = $updatedInfo;

                $db->where('id', $foc['product_id']);
                $db->where('deleted', '0');
                $focProductDetail = $db->getOne('product');

                $fileds = array("purchase_order_id", "product_id", "product_name","quantity", "cost", "total_cost", "total_cost", "created_at");
                $values = array($id, $focProductDetail['id'], $focProductDetail['name'], $foc['quantity'], '0', 'FOC', '0', date("Y-m-d H:i:s"));
                $data = array_combine($fields, $values);

                if($foc['action'] == 'add')
                {
                    $db->insert('purchase_order_product', $data);
                }
            }

            # upload receipt
            if(!empty($uploadImage)) {

                foreach($uploadImage as $key => $val) {

                    if (strpos($val['imgData'], 'https') !== false) {
                        $imageUrl = $val['imgData'];

                        $insertImage[] = array(
                            "name" => $val['imgName'],
                            "type" => 'Image',
                            "url" => $imageUrl,
                            "reference_id" => $id,
                            "created_at"   => $dateTime,
                        );
                    }
                }

                if(!empty($insertImage)) {
                    $insertInvImage = $db->insertMulti('purchase_media', $insertImage);

                    if(!$insertInvImage) {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01226"][$language], 'data' => ''); //Failed to upload profile image.
                    }
                }

                /*if($uploadImage['url']){
                    //check if is existing image
                    $db->where('reference_id', $id);
                    $db->where('deleted', '0');
                    $db->where('type', 'Image');
                    $db->where('url', $uploadImage['url']);
                    $check_res = $db->getOne('purchase_media');
                }

                if(!$check_res){
                    $db->where('reference_id', $id);
                    $db->where('deleted', '0');
                    $db->where('type', 'Image');
                    $updateArray = array(
                        'deleted' => '1'
                    );
                    $db->update('purchase_media', $updateArray);
                    foreach($uploadImage as $key => $val) {
                        $imgSrc = json_decode($val['imgData'], true);
                        $uploadParams['imgSrc'] = $imgSrc;
                        $uploadRes = aws::awsUploadImage($uploadParams);

                        if($uploadRes['status'] == 'ok') {
                            $imageUrl = $uploadRes['imageUrl'];
                            if($id)
                            {
                                $reference_id = $id;
                            }
                            else
                            {
                                $reference_id = $arrayData['id'];
                            }
                            // insert product_media
                            $insertImage = array(
                                "type" => "Image",
                                "name" => $val['imgName'],
                                "url" => $imageUrl,
                                "reference_id" => $reference_id,
                                "created_at"   => $dateTime
                            );

                            $insertInvImage = $db->insert('purchase_media', $insertImage);

                            if(!$insertInvImage) {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                            }
                        } else {
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00007"][$language], 'data' => ''); //Failed to upload profile image.
                        }
                    }
                }*/   
            }


            if(!empty($imageId))
            {
                $db->where('id', $imageId, 'IN');
                $db->where('reference_id', $id);
                $db->where('deleted', '0');
                $db->where('type', 'Image');
                $updateArray = array(
                    'deleted' => '1'
                );
                $db->update('purchase_media', $updateArray);
            }

            // update on po
            $db->where('purchase_order_id', $id);
            $purchaseProductList = $db->get('purchase_order_product');
            foreach($purchaseProductList as $productListDetail)
            {
                $costList[] = $productListDetail['total_cost'];
                $quantityList[] = $productListDetail['quantity'];
                $productList[] = $productListDetail['product_id'];
                $nameList[] = $productListDetail['product_name'];
            }

            $totalCost = 0;
            $nameList = implode(", ", $nameList);
            $productList = implode(", ", $productList);
            $totalCost = 0;
            foreach($costList as $row)
            {
                $totalCost = floatval($row) + $totalCost;
            }
            foreach ($quantityList as $quantity) {
                $totalQuantity += $quantity;
            }

            $db->where('purchase_order_id', $id);
            $result = $db->get('purchase_order_product');
            # check duplicate product
            foreach($result as  $detailDuplicate)
            {
                $key = $detailDuplicate['product_id'] . '-' . $detailDuplicate['type'];
                if (!isset($aggregatedData[$key])) {
                    $aggregatedData[$key] = $detailDuplicate;
                } else {
                    $aggregatedData[$key]['quantity'] += $detailDuplicate['quantity'];
                    $aggregatedData[$key]['total_cost'] += $detailDuplicate['quantity'] * $detailDuplicate['cost'];
                }
            }
            $aggregatedData = array_values($aggregatedData);

            # delete all from PO
            $db->where('purchase_order_id', $id);
            $db->delete('purchase_order_product');

            # re-insert data
            foreach($aggregatedData as $reInsert)
            {
                $db->insert('purchase_order_product', $reInsert);
            }

            // update po request
            $current_time = date("Y-m-d H:i:s");
            $fields = array("warehouse_id", "total_quantity", "total_cost", "remarks", "updated_at", "purchase_date");
            $values = array($warehouse_id ,$totalQuantity, $totalCost, $remarks, date('Y-m-d H:i:s'), $buying_date);
            $data = array_combine($fields, $values);

            $db->where('id', $id);
            $result = $db->update('purchase_order', $data);

            $db->where('po_id', $id);
            $db->where('status', 'pending');
            $poAssign = $db->getOne('po_assign');
            if($poAssign)
            {
                # update assign_id
                $updateData = array(
                    'assignee'      => $assign_id,
                    'updated_at'    => date('Y-m-d H:i:s')
                );
                $db->where('po_id', $id);
                $db->where('status', 'pending');
                $db->update('po_assign', $updateData);
            }
            else
            {
                $insertData = array(
                    'po_id'         => $id,
                    'assignee'      => $assign_id,
                    'assign_by'   => $adminID,
                    'status'        => 'pending',
                    'created_at'    => date('Y-m-d H:i:s'),
                );
                $db->insert('po_assign', $insertData);
            }

            if ($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => '');
            }
            else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00118"][$language] /* Invalid Admin */, 'data' => '');
            }
        }

        public function cancelledPurchaseOrder($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");
            $adminID        = $db->userID;
            $poID           = $params['id'];

            # check admin permission
            $permissionDataIn['type'] = $params['permissionType'];
            $permissionDataIn['module'] = $params['module'];
            $permissionDataIn['user_id'] = $adminID;

            $adminPermission = Permission::checkActionPermission($permissionDataIn);
            if($adminPermission['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $adminPermission['statusMsg'], 'data'=>$adminPermission);
            }

            $arrPermission = $adminPermission['data'];

            if($arrPermission['cancelled'] != 1)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01291"][$language] /* You do not have permission to cancel Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
            }

            if($arrPermission['read'] != 1)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01290"][$language] /* You do not have permission to view Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
            }

            $updateData = array(
                'status'        => 'Cancelled',
                'updated_at'    => $dateTime,
            );

            $db->where('id', $poID);
            $poStatus = $db->getOne('purchase_order', 'status');
            if($poStatus['status'] == 'Done' || $poStatus['status'] == 'Pending For Stock In')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["A01759"][$language] /* Failed to cancel Purchase Order */ , 'data'=> '');
            }

            $db->where('id', $poID);
            $result = $db->update('purchase_order', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["A01748"][$language] /* Purchase Order has been cancelled */ , 'data'=> $data);
        }

        public function assignSerial($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
        
            $id             = trim($params['id']);
            // $product_name   = trim($params['product_name']);
        
            $getProductList = $db->get('product');
            // check assign or not
            $db->orderBy('product_id', 'ASC');
            $db->where('po_id', $id);
            $stockDetail = $db->get('stock', null, 'serial_number, product_id, rack_no, expiration_date');
            if($stockDetail)
            {
                foreach($stockDetail as $detail)
                {
                    $increment[] = $detail['serial_number'] . ',id:' . $detail['product_id'];
                    $serialNumber[] = $detail['serial_number'];
                }

                $db->where('s.po_id', $id);
                $db->where('s.status', 'Inactive');
                $db->join('product p', 's.product_id = p.id', 'LEFT');
                $stockDetailList = $db->get('stock s', null, 's.product_id as stock_product_id, p.name as stock_product_name, s.serial_number');

                if($stockDetailList)
                {
                    foreach($stockDetailList as $detailStock)
                    {
                        $serialDetail['stock_product_id']       = $detailStock['stock_product_id'];
                        $serialDetail['stock_product_name']     = $detailStock['stock_product_name'];
                        $serialDetail['serial_number']          = $detailStock['serial_number'];

                        $serialList[] = $serialDetail;
                    }
                }

                // Sort the array by stock_product_id
                usort($serialList, function ($a, $b) {
                    return $a['stock_product_id'] <=> $b['stock_product_id'];
                });

                // Group the serial numbers by stock_product_id
                $sortedSerialList = [];
                $currentProductId = null;

                foreach ($serialList as $item) {
                    $productId = $item['stock_product_id'];
                    $productName = $item['stock_product_name'];
                    $serialNumberItem = $item['serial_number'];

                    if ($productId !== $currentProductId) {
                        $currentProductId = $productId;
                        $sortedSerialList[] = [
                            'stock_product_id' => $productId,
                            'stock_product_name' => $productName,
                            'serial_numbers' => [$serialNumberItem],
                        ];
                    } else {
                        $lastIndex = count($sortedSerialList) - 1;
                        $sortedSerialList[$lastIndex]['serial_numbers'][] = $serialNumberItem;
                    }
                }
                foreach ($sortedSerialList as &$item) {
                    asort($item['serial_numbers']);
                }

                // Format the output
                $displaySerialNumber = array_map(function ($item) {
                    $productName = $item['stock_product_name'];
                    $serialNumbers = implode(', ', $item['serial_numbers']);
                    return $productName . ': ' . $serialNumbers;
                }, $sortedSerialList);

                foreach ($increment as $item) {
                    preg_match('/id:(\d+)/', $item, $matches);
                    $incrementId = isset($matches[1]) ? (int)$matches[1] : 0;
                    $ids[] = $incrementId;
                }
                
                // Sort the 'showIncrement' array using the 'id' values in ascending order
                array_multisort($ids, SORT_ASC, $increment);

                # set for validated stock
                $validatedStock = array();
                foreach($stockDetail as $detailStock)
                {
                    if($detailStock['rack_no'] != null && $detailStock['rack_no'] != '')
                    {
                        foreach($getProductList as $detailProduct)
                        {
                            if($detailProduct['id'] == $detailStock['product_id'])
                            {
                                $validatedStockDetail['productName'] = $detailProduct['name'];
                                $validatedStockDetail['serialNo'] = $detailStock['serial_number'];
                                $validatedStockDetail['location'] = $detailStock['rack_no'];
                                $validatedStockDetail['bestBefore'] = $detailStock['expiration_date'];
                                $validatedStock[] = $validatedStockDetail;
                            }
                        }
                    }
                }

                # get for full set serial number with info
                $db->groupBy('s.product_id');
                $db->where('s.po_id', $id);
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $totalList = $db->get('stock s', null, 'p.name, COUNT(*) as quantity');

                # get all stock from this PO
                $db->where('s.po_id', $id);
                $db->where('status', 'Inactive');
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $stockTable = $db->get('stock s', null, 'p.name as productName, p.id as productId, p.barcode as skuCode');

                # get serial No that haven't stock in yet
                $db->where('s.po_id', $id);
                $db->where('s.status', 'Inactive');
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $inactiveSN = $db->get('stock s', null, 's.id, s.serial_number as serialNo, s.expiration_date as bestBefore, p.name as productName, s.status');

                $data['showIncrement'] = $increment;
                $data['serial_number'] = $serialNumber; // full set SN
                $data['displaySerialNumber'] = $displaySerialNumber;
                $data['totalProductList'] = $totalList;
                $data['stockTable'] = $stockTable;
                $data['validatedStock'] = $validatedStock;
                $data['inactiveSN'] = $inactiveSN;
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '' , 'data' => $data);
            }

            $db->where('purchase_order_id', $id);
            $purchaseProduct = $db->get('purchase_order_product');

            foreach($purchaseProduct as $product)
            {
                $db->where('id', $product['product_id']);
                $productName = $db->getOne('product','name');
                $productNameList[] = $productName;
            }
            foreach($purchaseProduct as $productId)
            {
                $db->where('id', $productId['product_id']);
                $product = $db->getOne('product','id');
                $productIdList[$productId['id']] = $product;
            }
            foreach($productIdList as $idList)
            {
                // use product id to get SKU code
                $db->where('id', $idList['id']);
                $barcodeDetail = $db->getOne('product', 'barcode');
                $barcodeWithoutN = str_replace("N", "", $barcodeDetail['barcode']);

                $db->where('p.id', $idList['id']);
                $db->where('s.serial_number', "%" . $barcodeWithoutN . '-' . "%", "LIKE");
                $db->join('serial_number s', 's.product_id = p.id', 'LEFT');
                $db->join('purchase_order_product pp', 'pp.product_id = p.id', 'LEFT');
                $getLastSerial = $db->getOne('product p', 'p.id, p.name, p.barcode, max(s.serial_number) as serial');

                if (strpos($getLastSerial['barcode'], "N") !== false)
                {
                    $getLastSerial['barcode'] = $barcodeWithoutN;
                }
        
                if (!empty($getLastSerial))
                {
                    $getId = $getLastSerial['id'];
                    $getName = $getLastSerial['name'];
                    $getBarcode = $getLastSerial['barcode'];
                    if($getLastSerial['serial'] == null)
                    {
                        $getSerial = $getLastSerial['barcode'] . '-000';
                        $getLastSerial['serial'] = $getSerial;
                    }
                    else
                    {
                        $getSerial = $getLastSerial['serial'];
                    }
                    // $getTotalQuantity = $getLastSerial['total_quantity'];
                    $getSerialSplit = explode('-', $getSerial);
                    $lastThreeNumbers = $getSerialSplit[2]; // Convert the second element to an integer
                }
                $lq = $db->getLastQuery();
                $queryList[] = $lq;
                // $getLastSerialList[] = $getLastSerial;
                $getLastSerialList[$idList['id']] = $getLastSerial;
            }
            $summedProducts = array();
        
            foreach($purchaseProduct as $productList)
            {
                if(!isset($summedProducts[$productList['product_id']])){
                    $summedProducts[$productList['product_id']]                = $productList;
                }else{

                    $summedProducts[$productList['product_id']]['quantity']    += $productList['quantity'];
                }
            }

            foreach($summedProducts as $pID => $info){
                $getLastSerial = $getLastSerialList[$pID];

                $currentBarcode = $getLastSerial['barcode'];
                $currentSerial = $getLastSerial['serial'];
                $currentSerialSplit = explode('-', $currentSerial); // example its is GT001-001-001
                $currentFirstSecond = $currentSerialSplit[0] . '-' . $currentSerialSplit[1]; // GT001 & 001
                $currentLastThreeNum = $currentSerialSplit[2]; // 001

                for ($i = 0; $i < $info['quantity']; $i++) {
                    $currentLastThreeNum++;
                    $currentLastThreeNum = str_pad($currentLastThreeNum, 3, '0', STR_PAD_LEFT);
                    $incrementedNumbers = $currentSerialSplit[0] . '-' . $currentSerialSplit[1] . '-' . $currentLastThreeNum;
                    // $showIncrement[] = $currentSerialSplit[0] . '-' . $currentSerialSplit[1] . '-' . $currentLastThreeNum . ',id:' . $productIdList[$count]['id'];
                    // $data2[] = $currentSerialSplit[0] . '-' . $currentSerialSplit[1] . '-' . $currentLastThreeNum;
                    $date[] = date("Y-m-d H:i:s");
                    $serial = $incrementedNumbers;
                    // insert into serial number table
                    $fields = array("purchase_order_id", "product_id", "serial_number", "created_at");
                    $values = array($id, $getLastSerial['id'], $serial , date("Y-m-d H:i:s"));
                    $arrayData = array_combine($fields, $values);
                    // do a checking here, no repeated, before insert to table
                    unset($checkSerialNumber);
                    $db->where('purchase_order_id', $id);
                    $db->where('serial_number', $serial);
                    $checkSerialNumber = $db->getOne('serial_number');

                    if($checkSerialNumber) // used before
                    {
                        // recheck the maximum serial number
                        $db->where('p.id', $getLastSerial['id']);
                        $db->join('purchase_order po', 'po.product_name = p.name', 'LEFT');
                        $db->join('serial_number s', 's.product_id = p.id', 'LEFT');
                        $db->join('po_product pp', 'pp.product_id = p.id', 'LEFT');
                        $getLastSerial2 = $db->getOne('product p', 'p.id, p.name, p.barcode, max(s.serial_number) as serial');
                
                        if (!empty($getLastSerial2))
                        {
                            $getId = $getLastSerial2['id'];
                            $getName = $getLastSerial2['name'];
                            $getBarcode = $getLastSerial2['barcode'];
                            $getSerial = $getLastSerial2['serial'];
                            // $getTotalQuantity = $getLastSerial['total_quantity'];
                            $getSerialSplit = explode('-', $getSerial);
                            $lastThreeNumbers = $getSerialSplit[2]; // Convert the second element to an integer
                        }
                        $getLastSerialList2[] = $getLastSerial2;
                        $numericPart = substr($getLastSerial2['serial'], strrpos($getLastSerial2['serial'], '-') + 1);
                        $incrementedNumericPart = str_pad((int)$numericPart + 1, strlen($numericPart), '0', STR_PAD_LEFT);
                        $newSerial = str_replace($numericPart, $incrementedNumericPart, $getLastSerial2['serial']);
                        $currentLastThreeNum = $numericPart; // renew the count
                        $fields = array("purchase_order_id", "product_id", "serial_number", "created_at");
                        $values = array($id, $getLastSerial['id'], $newSerial , date("Y-m-d H:i:s"));
                        $arrayData = array_combine($fields, $values);
                        try
                        {
                            $db->insert('serial_number', $arrayData);
                        }catch(Exception $e)
                        {
                            return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01185"][$language] /* Failed to execute query */, 'data' => '');
                        }
                        // show Increment with product_id

                        $showIncrement[] = $newSerial . ',id:' . $pID;
                        // show serial number only
                        $data2[] = $newSerial;
                    }
                    else
                    {
                        try
                        {
                            $db->insert('serial_number', $arrayData);
                        }catch(Exception $e)
                        {
                            return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["E01185"][$language] /* Failed to execute query */, 'data' => '');
                        }

                        $showIncrement[] = $currentSerialSplit[0] . '-' . $currentSerialSplit[1] . '-' . $currentLastThreeNum . ',id:' . $pID;
                        $data2[] = $currentSerialSplit[0] . '-' . $currentSerialSplit[1] . '-' . $currentLastThreeNum;
                    }

                    $arrayList[] = $arrayData;
                }
            }

                $db->where('s.po_id', $id);
                $db->where('s.status', 'Inactive');
                $db->join('product p', 's.product_id = p.id', 'LEFT');
                $stockDetailList = $db->get('stock s', null, 's.product_id as stock_product_id, p.name as stock_product_name, s.serial_number');
                // use id to get product detail
                unset($productIdList);
                foreach ($showIncrement as $item) {
                    $dataArr = explode(',', $item);
                    $idData = explode(':', $dataArr[1]);
                    $id = $idData[1];
                    
                    $productIdList[] = $id;
                }

                unset($productDetailList);
                foreach($productIdList as $idDetails)
                {
                    $db->where('id', $idDetails);
                    $productDetail = $db->getOne('product', 'id as stock_product_id, name as stock_product_name');

                    $productDetailList[] = $productDetail;
                }

                foreach ($productDetailList as $index => $item) {
                    $serialNumber = explode(',', $showIncrement[$index])[0];
                    $productDetailList[$index]['serial_number'] = $serialNumber;
                }

                if($productDetailList)
                {
                    foreach($productDetailList as $detailStock)
                    {
                        $serialDetail['stock_product_id']       = $detailStock['stock_product_id'];
                        $serialDetail['stock_product_name']     = $detailStock['stock_product_name'];
                        $serialDetail['serial_number']          = $detailStock['serial_number'];

                        $serialList[] = $serialDetail;
                    }
                    foreach($productDetailList as $detailStock)
                    {
                        $serialNumberList[] = $detailStock['serial_number'];
                    }
                }

                // Sort the array by stock_product_id
                usort($serialList, function ($a, $b) {
                    return $a['stock_product_id'] <=> $b['stock_product_id'];
                });

                // Group the serial numbers by stock_product_id
                $sortedSerialList = [];
                $currentProductId = null;

                foreach ($serialList as $item) {
                    $productId = $item['stock_product_id'];
                    $productName = $item['stock_product_name'];
                    $serialNumber = $item['serial_number'];

                    if ($productId !== $currentProductId) {
                        $currentProductId = $productId;
                        $sortedSerialList[] = [
                            'stock_product_id' => $productId,
                            'stock_product_name' => $productName,
                            'serial_numbers' => [$serialNumber],
                        ];
                    } else {
                        $lastIndex = count($sortedSerialList) - 1;
                        $sortedSerialList[$lastIndex]['serial_numbers'][] = $serialNumber;
                    }
                }
                foreach ($sortedSerialList as &$item) {
                    asort($item['serial_numbers']);
                }

                // Format the output
                $displaySerialNumber = array_map(function ($item) {
                    $productName = $item['stock_product_name'];
                    $serialNumbers = implode(', ', $item['serial_numbers']);
                    return $productName . ': ' . $serialNumbers;
                }, $sortedSerialList);

                foreach ($showIncrement as $item) {
                    preg_match('/id:(\d+)/', $item, $matches);
                    $incrementId = isset($matches[1]) ? (int)$matches[1] : 0;
                    $ids[] = $incrementId;
                }
                
                // Sort the 'showIncrement' array using the 'id' values in ascending order
                array_multisort($ids, SORT_ASC, $showIncrement);

                $firstItem = reset($showIncrement);
                $parts = explode('-', $firstItem);
                $prefix = $parts[0];
                
                // Create a custom comparison function for array_multisort
                usort($showIncrement, function ($a, $b) {
                    $idA = intval(explode(':', $a)[1]);
                    $idB = intval(explode(':', $b)[1]);
                    
                    return $idA - $idB;
                });

                // usort($showIncrement, function ($a, $b) use ($prefix) {
                //     $pattern = '/'. preg_quote($prefix, '/') . '-\d+-(\d+)/';
                //     preg_match($pattern, $a, $aMatches);
                //     preg_match($pattern, $b, $bMatches);
                
                //     return intval($aMatches[1]) - intval($bMatches[1]);
                // });
                
                // Extract the sorted serial numbers from the sorted showIncrement array
                $sorted_serial_numbers = array_map(function ($item) {
                    return explode(",", $item)[0];
                }, $showIncrement);

                # get for full set serial number with info
                $db->groupBy('s.product_id');
                $db->where('s.po_id', $id);
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $totalList = $db->get('stock s', null, 'p.name, COUNT(*) as quantity');

                # get all stock from this PO
                $db->where('s.po_id', $id);
                $db->where('status', 'Inactive');
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $stockTable = $db->get('stock s', null, 'p.name as productName, p.id as productId, p.barcode as skuCode');

                # get serial No that haven't stock in yet
                $db->where('s.po_id', $id);
                $db->where('s.status', 'Inactive');
                $db->join('product p', 'p.id = s.product_id', 'INNER');
                $inactiveSN = $db->get('stock s', null, 's.id, s.serial_number as serialNo, s.expiration_date as bestBefore, p.name as productName, s.status');

                unset($data);
                $data['showIncrement'] = $showIncrement;
                $data['serial_number'] = $sorted_serial_numbers; // full set serial number
                $data['totalProductList'] = $totalList;
                $data['stockTable'] = $stockTable;
                $data['inactiveSN'] = $inactiveSN;
                $data['displaySerialNumber'] = $displaySerialNumber;
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language], 'data' => $data);
        }

        function confirmSerial($params, $username){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
        
            $id             = trim($params['id']);
            $dateTime       = date("Y-m-d H:i:s");
            $insertSerial = $params['serial'];

            $quantityPerId = array();

            foreach ($insertSerial as $serial) {
                $dataArr = explode(',', $serial);
                $serialNumber = $dataArr[0];
                $productId = substr($dataArr[1], strpos($dataArr[1], ":") + 1);
            
                if (!isset($quantityPerId[$productId])) {
                    $quantityPerId[$productId] = array(
                        'product_id' => $productId,
                        'quantity' => 0,
                        'serial_numbers' => array()
                    );
                }
                $quantityPerId[$productId]['quantity']++;
            }

            // $product_name = trim($params['product_name']);
            // $arr = explode(",", $insertSerial);
            /////jimmy edit
            $db->where('po_id',$id);
            $db->delete('stock');
            ///// 
            $db->where('purchase_order_id', $id);
            $purchaseProduct = $db->get('po_product');
        
            foreach($purchaseProduct as $product)
            {
                $db->where('id', $product['product_id']);
                $productName = $db->getOne('product','name');
                $productNameList[] = $productName;
            }
            $lq = $db->getLastQuery();
            foreach($productNameList as $nameList)
            {
                $db->where('name', $nameList['name']);
                $result = $db->getOne('product', 'id, name, barcode');
                $resultList[] = $result;
            }

            foreach ($insertSerial as $serial) {
                $dataArr = explode(',', $serial);
                $serial = $dataArr[0];
                $productId = $dataArr[1];
                $productId = substr($productId, strpos($productId, ":") + 1);
                // get product expiration date
                $db->where('id', $productId);
                $productDetail = $db->getOne("product", "cost, expired_day");
                $expiration_date = date("Y-m-d H:i:s", strtotime("+" . $productDetail['expired_day'] . " days", strtotime($dateTime)));

                // calculate the cost
                foreach($quantityPerId as $stockCostDetails)
                {
                    if($stockCostDetails['product_id'] == $productId)
                    {
                        $db->where('type', 'Purchase');
                        $db->where('product_id', $productId);
                        $db->where('purchase_order_id', $id);
                        $getTotalCost = $db->get('po_product',null, 'total_cost');

                        unset($totalCost);
                        foreach($getTotalCost as $sumTotalCost)
                        {
                            $totalCost = $sumTotalCost['total_cost'];
                        }

                        $totalCost = floatval($totalCost) / intval($stockCostDetails['quantity']);
                    }
                }

                // get the warehouse id
                $db->where('id', $id);
                $purchaseOrderDetail = $db->getOne('purchase_order');
                if($purchaseOrderDetail)
                {
                    $purchaseOrderDetail = $purchaseOrderDetail['warehouse_id'];
                }

                $new_item = array(
                    "po_id"           => $id,
                    "warehouse_id"    => $purchaseOrderDetail,
                    "product_id"      => $productId,
                    "serial_number"   => $serial,
                    "cost"            => $totalCost,
                    "expiration_date" => $expiration_date,
                    "created_at"      => $dateTime,
                    "status"          => 'Inactive'
                );
                $new_list[] = $new_item;
            }

            // foreach ($insertSerial as $serial) {
            //     $dataArr = explode(',', $serial);
            //     $productId = $dataArr[1];
            //     $productId = substr($productId, strpos($productId, ":") + 1);
                
            //     $newCost = $costs[$productId] / $quantityPerId[$productId];

            //     $newCostList[] = $newCost;
            // }
            // return array('status' => "error", 'code' => 110, 'statusMsg' => 'testing' , 'newCostList' => $newCostList);

            $insertSerial = $db->insertMulti("stock", $new_list);

            $fields = array("status", "updated_at");
            $values = array("Pending For Stock In", date('Y-m-d H:i:s'));
            $arrayData = array_combine($fields, $values);
        
            $db->where('id', $id);
            $updateStatus = $db->update("purchase_order", $arrayData);
            
        
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'data' => $arrayData);
        }

        function getVendor($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);

            $getVendor = $db->get("vendor", null, "id, name, vendor_code");
            foreach ($getVendor as $key => $value) {
                $getVendorDetail[$key] = $value;
            }
            $data['getVendorDetail'] = $getVendorDetail;

            $db->where('deleted', '0');
            $getVendorAddress = $db->get("vendor_address", null, "id, branch_name, address");
            foreach ($getVendorAddress as $key => $value) {
                $getVendorAddressDetail[$key] = $value;
            }
            $data['getVendorAddressDetail'] = $getVendorAddressDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'last_query' => $db->getLastQuery(), 'data' => $data);
        }

        public function addNewWarehouse($params)
        {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $adminID        = $db->userID;
            $warehouse_location = trim($params['warehouse_location']);
            $warehouse_address  = trim($params['warehouse_address']);

            $fields = array("warehouse_location", "warehouse_address", "created_at");
            $values = array($warehouse_location, $warehouse_address, date('Y-m-d H:i:s'));
            $arrayData = array_combine($fields, $values);

            $result = $db->insert('warehouse', $arrayData);
            
            if(!$result)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["B00511"][$language] /* Failed to add */, 'data' => $arrayData);
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00102"][$language] /* Successfully Added */, 'data' => '');
        }

        public function editWarehouse($params)
        {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $warehouse_id   = trim($params['id']);
            $warehouse_location = trim($params['warehouse_location']);
            $warehouse_address  = trim($params['warehouse_address']);

            $fields = array("warehouse_location", "warehouse_address", "updated_at");
            $values = array($warehouse_location, $warehouse_address, date('Y-m-d H:i:s'));
            $arrayData = array_combine($fields, $values);

            $db->where('id', $warehouse_id);
            $result = $db->update('warehouse', $arrayData);
            
            if(!$result)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg'=> $translations["B00511"][$language] /* Failed to add */, 'data' => $arrayData);
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["A00684"][$language] /* Update Successful */, 'data' => '');
        }

        public function getWarehouseList($params)
        {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $result = $db->get('warehouse');

            if(empty($result))
            {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }
            foreach($result as $row)
            {
                $warehouseDetail['id']                  = $row['id'];
                $warehouseDetail['warehouse_location']  = $row['warehouse_location'];
                $warehouseDetail['warehouse_address']   = $row['warehouse_address'];
                $warehouseDetail['created_at']          = $row['created_at'];
                $warehouseDetail['updated_at']          = $row['updated_at'];
                
                $warehouseList[] = $warehouseDetail;
            }
            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["A00114"][$language] /* Search successful */, 'data' => $warehouseList);
        }

        function getWarehouse($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);
            $userID         = $db->userID;
            $site           = $db->site;

            if($params['permissionType'] != 'createAdmin')
            {
                # check admin permission
                $permissionDataIn['type'] = $params['permissionType'];
                $permissionDataIn['module'] = $params['module'];
                $permissionDataIn['user_id'] = $userID;
    
                $adminPermission = Permission::checkActionPermission($permissionDataIn);
                if($adminPermission['status'] != 'ok')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $adminPermission['statusMsg'], 'data'=>$adminPermission);
                }
    
                $arrPermission = $adminPermission['data'];
                if(count($arrPermission) > 0)
                {
                    $db->where("warehouse_location", $arrPermission, "IN");
                    $getWarehouse = $db->get("warehouse", null, "id, warehouse_location, warehouse_address");
                }
                else
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "You don't have branch permission to perform this action. " , 'data' => '');
                }
            }
            else
            {
                $getWarehouse = $db->get("warehouse", null, "id, warehouse_location, warehouse_address");
            }
            foreach ($getWarehouse as $key => $value) {
                $getWarehouseDetail[$key] = $value;
            }
            $data = $getWarehouseDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["A00114"][$language] /* Search successful */, 'data' => $data);
        }

        function acceptRejectPurchaseOrder($params) {
            $db                      = MysqliDb::getInstance();
            $language                = General::$currentLanguage;
            $translations            = General::$translations;
            $adminID                 = $db->userID;
            $dateTime                = date("Y-m-d H:i:s");

            $poID                    = $params['id'];
            $reason                  = $params['reason'];
            $status                  = $params['status'];
            $remarks                 = $params['remarks'];
            $product_list            = $params['product_list'];
            $vendor_id               = $params['vendor_id'];
            $purchase_date           = $params['buying_date'];
            $assign_id               = $params['assign_id'];
            $warehouse_id       = trim($params['warehouse_id']);

            # check admin permission
            $permissionDataIn['type'] = $params['permissionType'];
            $permissionDataIn['module'] = $params['module'];
            $permissionDataIn['user_id'] = $adminID;

            $adminPermission = Permission::checkActionPermission($permissionDataIn);
            if($adminPermission['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $adminPermission['statusMsg'], 'data'=>$adminPermission);
            }

            $arrPermission = $adminPermission['data'];

            if($arrPermission['read'] != 1)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01290"][$language] /* You do not have permission to view Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
            }

            # edit PO
            $dataIn['id']                      = $poID;
            $dataIn['remarks']                 = $remarks;
            $dataIn['purchase_product_list']   = $product_list;
            $dataIn['vendor_id']               = $vendor_id;
            $dataIn['buying_date']             = $purchase_date;
            $dataIn['assign_id']               = $assign_id;
            $dataIn['warehouse_id']            = $warehouse_id;

            $resultOut = Admin::purchaseOrderEdit($dataIn);
            if($resultOut['status'] == 'error')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => 'fail to edit purchase order', 'data'=>$resultOut);
            }

            # check is this admin is this PO assignee or not
            $db->where('po_id', $poID);
            $db->where('status', 'accept', '!=');
            $db->where('status', 'reject' ,'!=');
            $poAssign = $db->getOne('po_assign');

            if($poAssign['status'] == '')
            {
                $updateData = array(
                    'status' => 'pending',
                );
                $db->where('po_id', $poID);
                $db->where('status', '');
                $db->update('po_assign', $updateData);
            }

            $assign_id = $poAssign['assignee'];

            if(!$assign_id)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01708"][$language] /* Please select assignee */ , 'data' => '');
            }

            if($adminID != $assign_id)
            {
                if($status === 'accept')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' =>  $translations["E01296"][$language] /* Sorry, you cannot accept this purchase order as you are not the assigned assignee */ , 'data' => '');
                }
                else
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' =>  $translations["E01293"][$language] /* Sorry, you cannot reject this purchase order as you are not the assigned assignee */ , 'data' => '');
                }
            }

            $updateData = array(
                'po_id'       => $poID,
                'status'      => $status,
                'response_at' => $dateTime,
                'reason'      => $reason,
                'created_at'  => $dateTime
            );
            $db->where('po_id', $poID);
            $db->where('status', 'pending');
            $db->update('po_assign', $updateData);

            # update PO to purchasing status
            if($status === 'accept')
            {
                $updateData = array(
                    'status'      => 'Purchasing',
                    'updated_at'  => $dateTime
                );
                $db->where('id', $poID);
                $db->update('purchase_order', $updateData);

                return array("status"=> "ok", 'code' => 0, 'statusMsg' => $translations["E01295"][$language] /* The Purchase Order has been successfully accepted */, 'data' => $result);
            }

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => $translations["E01294"][$language] /* The purchase order has been rejected successfully */, 'data' => $result);
        }

        function approvePurchaseOrder($params, $username) {
            $db                      = MysqliDb::getInstance();
            $language                = General::$currentLanguage;
            $translations            = General::$translations;
            $adminID                 = $db->userID;

            $id                      = trim($params['id']);
            $status                  = trim($params['status']);
            $dateTime                = date("Y-m-d H:i:s");
            $remarks                 = ($params['remarks']);
            $purchase_product_list   = ($params['purchase_product_list']);
            $vendor_id               = trim($params['vendor_id']);
            $buying_date             = trim($params['buying_date']);
            $approved_by             = trim($params['approved_by']);
            $uploadImage             = $params['uploadImage'];
            $imageId                 = $params['imageId'];
            $type                    = $params['type'];
            $assign_id               = $params['assign_id'];
            $batchAction             = $params['batchAction'];
            $warehouse_id       = trim($params['warehouse_id']);

            # check admin permission
            $permissionDataIn['type'] = $params['permissionType'];
            $permissionDataIn['module'] = $params['module'];
            $permissionDataIn['user_id'] = $adminID;

            if(!$batchAction)
            {
                # make sure product list have at least one product
                $allDeleted = true;
                foreach ($purchase_product_list as $product) {
                    if ($product["action"] !== "delete") {
                        $allDeleted = false;
                        break; 
                    }
                }
                if ($allDeleted) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01299"][$language] /* Must have at least one product in this PO */, 'data'=>"");
                }
            }

            if(strtolower($status) != 'pending for stock in')
            {
                $adminPermission = Permission::checkActionPermission($permissionDataIn);
                if($adminPermission['status'] != 'ok')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $adminPermission['statusMsg'], 'data'=>$adminPermission);
                }
    
                $arrPermission = $adminPermission['data'];
    
                if($arrPermission['approve'] != 1)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01292"][$language] /* You do not have permission to approve Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
                }
    
                if($arrPermission['read'] != 1)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01290"][$language] /* You do not have permission to view Purchase Orders. Please contact your administrator for assistance */ , 'data' => '');
                }
            }

            $db->where('id', $id);
            $poStatus = $db->getOne('purchase_order');
            if($type != 'upgrade')
            {
                if(!$poStatus || strtolower($poStatus['status']) != 'rfq')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01711"][$language] /* Purchase Order is not able to edit */, 'data' => "");
                }
            }

            if($type === 'upgrade' && ($poStatus['status'] != 'Approved' && $poStatus['status'] != 'Purchasing'))
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01711"][$language] /* Purchase Order is not able to edit */, 'data' => "");
            }

            if($status === 'Pending For Stock In')
            {
                if(!$uploadImage)
                {
                    if($imageId){
                        //Got delete image /if after delete no more image prompt error
                        $db->where('id', $imageId, 'NOT IN');
                        $db->where('type', 'Image');
                        $db->where('deleted', '0');
                        $db->where('reference_id', $id);
                        $checkImageLeft = $db->getValue('purchase_media', 'id');
                        if(!$checkImageLeft){
                            return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00533"][$language] /* Please Upload Receipt */, 'data' => "");
                        }
                    }
                    //Currently no upload image & no image in database; prompt error
                    $db->where('reference_id', $id);
                    $db->where('deleted', '0');
                    $db->where('type', 'Image');
                    $uploadImage = $db->getOne('purchase_media');
                    if(!$uploadImage)
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00533"][$language] /* Please Upload Receipt */, 'data' => "");
                    }
                }

                if($uploadImage)
                {
                    foreach($uploadImage as $imageDetail)
                    {
                        if($imageDetail['imgData'] == '""')
                        {
                            $db->where('reference_id', $id);
                            $db->where('deleted', '0');
                            $db->where('type', 'Image');
                            $uploadImage = $db->getOne('purchase_media');
                            if(!$uploadImage)
                            {
                                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["B00533"][$language] /* Please Upload Receipt */, 'data' => "");
                            }
                        }
                    }
                }
            }

            $dataIn['id']                      = $id;
            $dataIn['remarks']                 = $remarks;
            $dataIn['purchase_product_list']   = $purchase_product_list;
            $dataIn['vendor_id']               = $vendor_id;
            $dataIn['buying_date']             = $buying_date;
            $dataIn['approved_by']             = $approved_by;
            $dataIn['uploadImage']             = $uploadImage;
            $dataIn['imageId']                 = $imageId;
            $dataIn['assign_id']               = $assign_id;
            $dataIn['warehouse_id']            = $warehouse_id;

            if(!$batchAction)
            {
                $resultOut = Admin::purchaseOrderEdit($dataIn);
                if(!$resultOut || $resultOut['status'] != 'ok')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid Purchase Order', 'data' => $resultOut);
                }
            }

            $db->where('id', $adminID);
            $adminInfo = $db->getOne('admin');

            if($status === 'Pending For Stock In')
            {
                $fields = array("id", "status", "updated_at");
                $values = array($id, $status, $dateTime);
            }
            else
            {
                $fields = array("id", "approved_date", "approved_by", "status", "updated_at");
                $values = array($id, $dateTime, $adminInfo['name'], $status, $dateTime);
            }

            $arrayData = array_combine($fields, $values);

            $db->where('id', $id);
            $result = $db->update("purchase_order", $arrayData);

            if($result) {
                return array("status"=> "ok", 'code' => 0, 'statusMsg' => $translations["A01740"][$language] /* Purchase Order Has Been Approved. */, 'data' => $result);
            }
            else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A01741"][$language] /* Error Occured for Purchase Order. */, 'data' => "");
            }

        }

        function getPackageBarcode($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $productID      = trim($params['productID']);

            $db->where('package_id', $productID);
            $db->where('deleted', 0);
            $productListArray = $db->getValue("package_item" ,"product_id", null);

            // $db->where('deleted', 0);
            // $db->where('is_archive', 0);
            // $db->where('is_published', 1);
            $db->where('id', $productListArray, 'IN');
            $productBarcodeArray = $db->getValue("product" ,"barcode", null);

            $data = implode(', ', $productBarcodeArray);

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => $translations["A00114"][$language] /* Search successful */, 'data' => $data);
        }

        function getStockList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['inputData'];
            $sortData       = $params['sortData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll          = $params['seeAll'];
            $limit          = General::getLimit($pageNumber);
            
            $layer          = $params['layer']; /* Stock Listing Layer */
            $product_id     = $params['productId'];
            $po_id          = $params['poId'];
            $lockQuantity   = 0;

            if (!$layer) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Stock Layer", 'data' => "");

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }

            if($seeAll){
                $limit = null;
            }

            if ($layer == 1) {
                // $db->join('product', 'product.id = stock.product_id', 'LEFT');
                // $db->groupBy('stock.product_id');
                // $db->orderBy('stock.id', 'DESC');
                // $result = $db->get('stock', null, 'stock.id, product.name');
                if(!empty($po_id))
                {
                    $db->orderBy('s.id', 'DESC');
                    $db->join('product p', 'p.id = s.product_id', 'LEFT');
                    $db->join('vendor v', 'v.id = p.vendor_id', 'LEFT');
                    $db->where('s.po_id', $po_id);
                    $result = $db->get('stock s');
                }
                else
                {
                    // $result = $db->rawQuery("SELECT stock.id, product.barcode, product.name, COUNT(*) AS on_hand, vendor.name as vendor, stock.expiration_date, stock.product_id FROM `stock` LEFT JOIN `product` ON product.id = stock.product_id LEFT JOIN `vendor` ON vendor.id = product.vendor_id GROUP BY stock.product_id ORDER BY stock.id DESC LIMIT $limit[1];");

                    if (count($searchData) > 0) {
                        foreach ($searchData as $k => $v) {
                            $dataName = trim($v['dataName']);
                            $dataValue = trim($v['dataValue']);
                            $dataType  = trim($v['dataType']);
        
                            switch ($dataName) {
                                
                                case 'skuCode':
                                    $skuCodeCount = substr_count($dataValue, ',');

                                    $ary = explode(",",$dataValue);
                                    foreach($ary as $temp){

                                        $tempAry[] = trim($temp);
                                    }

                                    if(count($tempAry) == 0){
                                        $db->where('barcode', "%" . $dataValue . "%", 'LIKE');
                                    }else{
                                        $db->where('barcode', $tempAry, 'IN');
                                    }

                                break;
        
                                case 'productName':
                                    $db->where('p.name', "%" . $dataValue . "%", 'LIKE');
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
                        array('col' => 'p.deleted', 'val' => '0')
                    );

                    $db->groupBy('p.id');
                    // $db->join('product p', 'p.id = s.product_id', 'LEFT');
                    foreach($rule as $v){
                        $db->where($v['col'], $v['val']);
                    }
                    $db->join('stock s', 'p.id = s.product_id', 'LEFT');
                    $db->join('vendor v', 'v.id = p.vendor_id', 'LEFT');
                    // $result = $db->get("stock s", $limit , "s.id as id, so_id as so_id, p.barcode as barcode, p.name as name, count(*) as on_hand, v.name as vendor, s.expiration_date as expiration_date, s.product_id as product_id");
                    $dbCopy = $db->Copy();
                    $result = $db->get("product p", $limit , "s.id as id, so_id as so_id, p.barcode as barcode, p.name as name, count(*) as on_hand, v.name as vendor, s.expiration_date as expiration_date, s.product_id as product_id, p.product_type,p.id as pid" );
                    $totalCount = $db->count;
                    // $data["debug3"] = $db->getLastQuery();

                    // $db->join('product p', 'p.id = s.product_id', 'LEFT');
                    // $resultCount = $db->get("stock s", null , "s.id as id, so_id as so_id, p.barcode as barcode, p.name as name, count(*) as on_hand, v.name as vendor, s.expiration_date as expiration_date, s.product_id as product_id");
                    $resultCount = $dbCopy->get("product p", null , "s.id as id, so_id as so_id, p.barcode as barcode, p.name as name, count(*) as on_hand, v.name as vendor, s.expiration_date as expiration_date, s.product_id as product_id");
                    $totalRecord = $dbCopy->count;

                    // $db->orderBy('s.id', 'DESC');
                    // $db->groupBy('s.product_id');
                    // $db->join('product p', 'p.id = s.product_id', 'LEFT');
                    // $db->join('vendor v', 'v.id = p.vendor_id', 'LEFT');
                    // $resultCount = $db->get("stock s", null , "s.id as id, so_id as so_id, p.barcode as barcode, p.name as name, count(*) as on_hand, v.name as vendor, s.expiration_date as expiration_date, s.product_id as product_id");

                    //for layer 1
                    /*$db->where('so.status', 'Paid');
                    $db->where('sod.product_id', 0, '>');
                    $db->where('sod.deleted', 0);
                    $db->join('sale_order so', 'sod.sale_id = so.id', 'INNER');
                    $db->groupBy('sod.product_id');
                    $lockQuantityArray = $db->map('product_id')->get('sale_order_detail sod', null, 'sod.product_id, sum(sod.quantity) as quantity');*/                    

                    $data['pageNumber']       = $pageNumber;
                    if($searchData){
                        $data['totalRecord']      = $totalCount;
                    }else{
                        $data['totalRecord']      = $totalRecord;
                    }
                    if($seeAll) {
                        $data['totalPage']    = 1;
                        $data['numRecord']    = $totalRecord;
                    } else {
                        $data['totalPage']    = ceil($totalRecord/$limit[1]);
                        $data['numRecord']    = $totalCount;
                    }
                }
            }
            else if ($layer == 2) {
                if (!$product_id) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Product ID", 'data' => "");
                // $result = $db->rawQuery("SELECT stock.id, product.barcode, product.name, COUNT(*) AS on_hand, vendor.name as vendor, stock.expiration_date, stock.stock_in_datetime, stock.po_id FROM `stock` LEFT JOIN `product` ON product.id = stock.product_id LEFT JOIN `vendor` ON vendor.id = product.vendor_id WHERE stock.product_id = $product_id GROUP BY stock.po_id ORDER BY stock.expiration_date ASC LIMIT $limit[1];");

                if (count($searchData) > 0) {
                    foreach ($searchData as $k => $v) {
                        $dataName = trim($v['dataName']);
                        $dataValue = trim($v['dataValue']);
                        $dataType  = trim($v['dataType']);
    
                        switch ($dataName) {
    
                            case 'skuCode':
                                $db->where('barcode', "%" . $dataValue . "%", 'LIKE');
                            break;
    
                            case 'branch':
                                $db->where('warehouse_id', "%" . $dataValue . "%", 'LIKE');
                            break;

                            case 'date':
                                $db->where('stock_in_datetime', "%" . $dataValue . "%", 'LIKE');
                            break;
    
                        }
                        unset($dataName);
                        unset($dataValue);
                    }
                }

                $sortOrder = "DESC";
                $sortField = 's.stock_in_datetime';

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

                $rule = array(
                    array('col' => 's.product_id', 'val' => $product_id)
                );

                // $db->orderBy('s.expiration_date', 'ASC');
                // $db->orderBy('s.po_id', 'DESC');
                $db->groupBy('s.po_id');
                foreach($rule as $v){
                    $db->where($v['col'], $v['val']);
                }
                // $db->where('s.product_id', $product_id);
                $db->join('product p', 'p.id = s.product_id', 'LEFT');
                $db->join('vendor v', 'v.id = p.vendor_id', 'LEFT');
                // $db->orderBy($sortField, $sortOrder);
                $result = $db->get("stock s", null , "s.id as id, p.barcode as barcode, p.name as name, count(*) as on_hand, s.po_id as po_id, s.stock_in_datetime as stock_in_datetime, s.warehouse_id as warehouse_id, s.product_id as product_id, p.product_type,p.id as pid");

            }
            else if ($layer == 3) {
                if (!$po_id) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Product Order ID", 'data' => "");
                // $result = $db->rawQuery("SELECT stock.id, product.name, stock.serial_number, stock.expiration_date, stock.stock_in_datetime FROM `stock` LEFT JOIN `product` ON product.id = stock.product_id WHERE stock.po_id = $po_id ORDER BY stock.expiration_date ASC LIMIT $limit[1];");

                if (count($searchData) > 0) {
                    foreach ($searchData as $k => $v) {
                        $dataName = trim($v['dataName']);
                        $dataValue = trim($v['dataValue']);
                        $dataType  = trim($v['dataType']);
    
                        switch ($dataName) {
    
                            case 'skuCode':
                                $db->where('barcode', "%" . $dataValue . "%", 'LIKE');
                            break;
    
                            case 'product':
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            break;

                            case 'serialNo':
                                $db->where('serial_number', "%" . $dataValue . "%", 'LIKE');
                            break;

                            case 'status':
                                $db->where('status', $dataValue);
                            break;

                            case 'date':
                                $db->where('expiration_date', "%" . $dataValue . "%", 'LIKE');
                            break;
    
                        }
                        unset($dataName);
                        unset($dataValue);
                    }
                }

                $sortOrder = "ASC";
                $sortField = 's.serial_number';

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
                    array('col' => 's.po_id', 'val' => $po_id),
                    array('col' => 's.product_id', 'val' => $product_id)
                );

                // $db->orderBy('s.expiration_date');
                foreach($rule as $v){
                    $db->where($v['col'], $v['val']);
                }
                // $db->where('s.po_id', $po_id);
                // $db->where('s.product_id', $product_id);
                $db->join('product p', 'p.id = s.product_id', 'LEFT');
                $result = $db->get("stock s", null , "s.id as id, p.barcode as barcode, p.name as name, s.status as status, s.serial_number as serial_number, s.expiration_date as expiration_date, s.product_id as product_id,s.product_id as product_id, p.product_type,p.id as pid");
            }

            // return array('status' => "error", 'code' => 1, 'statusMsg' => "No Record Found", 'data' => $result);

            if (!empty($result)) {
                foreach($result as $value) {
                    $lockStockForPaid = 0;
                    $packageLockStock = 0;

                    $db->where('po_id', $value['po_id']);
                    $db->where('product_id', $value['pid']);
                    $db->where('status', 'Active');
                    $countAvailable = $db->getValue('stock', 'count(*)');

                    $stock['id']                = $value['id'];
                    $stock['barcode']           = $value['barcode'] ? $value['barcode'] : "-";
                    $stock['status']            = $value['status'] ? $value['status'] : "-";
                    $stock['name']              = $value['name'] ? $value['name'] : "-";
                    $stock['on_handL2']         = $value['on_hand'] ? $value['on_hand'] : "0";
                    $stock['available_amount']  = $countAvailable;
                    $stock['vendor']            = $value['vendor'] ? $value['vendor'] : "-";
                    $stock['product_id']        = $value['pid'] ? $value['pid'] : "";
                    $stock['expiration_date']   = $value['expiration_date'] != '0000-00-00' ? date($dateTimeFormat, strtotime($value['expiration_date'])) : "-";
                    $stock['stock_in_datetime'] = $value['stock_in_datetime'] != '0000-00-00 00:00:00' ? date($dateTimeFormat, strtotime($value['stock_in_datetime'])) : "-";
                    $stock['po_id']             = $value['po_id'] ? $value['po_id'] : "";
                    $stock['serial_number']     = $value['serial_number'] ? $value['serial_number'] : "-";
                    $stock['product_type']     = $value['product_type'] ? $value['product_type'] : "-";

                    

                    $db->where('product_id', $stock['product_id']);
                    $db->where('deleted', 0);
                    $wishListAmt = $db->getValue('product_wishlist', 'count(*)') ?? 0;

                    // get product amount
                    if($value["product_type"] == "product"){
                    
                        $db->where('status', 'Active');
                        $db->where('product_id', $stock['product_id']);
                        $totalOnHand = $db->getValue('stock s', 'count(*)');

                        // check package product lock stock
                        $db->where('product_id', $stock['product_id']);
                        $db->where('deleted', '0');
                        $packageProductList = $db->get('package_item');

                        $packageLockAry = [];

                        foreach($packageProductList as $packageDetails)
                        {
                            $db->where("so.status", array("Order Processing","Pending Payment Approve", "Paid"),"IN");
                            $db->where("sod.deleted", 0);
                            $db->where("sod.product_id", $packageDetails['package_id']);
                            $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                            $db->groupBy('so.status');
                            $packageLock = $db->get("sale_order_detail sod",null, "SUM(sod.quantity) as quantity,so.status");


                            foreach($packageLock as $value) {
                                if($value["status"] == "Order Processing")$value["status"] = "Paid";
                                $packageLockAry[$value["status"]] +=  ($value["quantity"] ?? 0) * $packageDetails["quantity"];
                            }

                        }
                        $packageLockStock =  $packageLockAry["Paid"] ?? 0;

                        $availableAmt = $totalOnHand-$packageLockStock;

                        // Lock stock for pending payment approve
                        $db->where("so.status", array("Pending Payment Approve"),"IN");
                        $db->where("sod.deleted", 0);
                        $db->where("sod.product_id", $stock['product_id']);
                        $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                        $lockStockForPPA = $db->getValue("sale_order_detail sod", "SUM(sod.quantity)") ?? 0;	
                        $data["debug1"] = $lockStockForPPA;
                        $data["debug2"] = $db->getLastQuery();

                        // Lock stock for paid
                        $db->where("so.status", array("Paid","Order Processing"),"IN");
                        $db->where("sod.deleted", 0);
                        $db->where("sod.product_id", $stock['product_id']);
                        $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                        $lockStockForPaid = $db->getValue("sale_order_detail sod", "SUM(sod.quantity)") ?? 0;	

                        $availableAmt -= $lockStockForPaid;
                    }else if($value["product_type"] == "package"){

                        $db->where('package_id', $value['pid']);
                        $db->where('deleted', '0');
                        $packageItemList = $db->get('package_item');

                         // Lock stock for pending payment approve
                        $db->where("so.status", array("Pending Payment Approve"),"IN");
                        $db->where("sod.deleted", 0);
                        $db->where("sod.product_id", $value['pid']);
                        $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                        $packageItemTotalForecast = $db->getValue("sale_order_detail sod", "SUM(sod.quantity)") ?? 0;
                        //$data["debug2"] = $db->getLastQuery();

                         // Lock stock for pending payment approve
                        $db->where("so.status", array("Paid","Order Processing"),"IN");
                        $db->where("sod.deleted", 0);
                        $db->where("sod.product_id", $value['pid']);
                        $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                        $packageLockStock = $db->getValue("sale_order_detail sod", "SUM(sod.quantity)") ?? 0;

                        $packageItemAvailableAmt = [];
                        foreach($packageItemList as $packageItem){
                            
                            $packageLockAry = [];
                            $packageProductID = $packageItem["product_id"];
                            $db->where('status', 'Active');
                            $db->where('product_id', $packageProductID);
                            $packageItemTotalOnHand[$packageProductID] = $db->getValue('stock s', 'count(*)');

                             // Lock stock for Paid
                            $db->where("so.status", array("Paid","Order Processing"),"IN");
                            $db->where("sod.deleted", 0);
                            $db->where("sod.product_id", $packageProductID);
                            $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                            $packageLockStockForPaid = $db->getValue("sale_order_detail sod", "SUM(sod.quantity)") ?? 0;
                            $data["debug5"] = $db->getLastQuery();

                            $data["debug6"] = $packageItemTotalOnHand;

                            // check package product lock stock
                            $db->where('product_id', $packageProductID);
                            $db->where('deleted', '0');
                            $packageProductList = $db->get('package_item');

                            $packageItemLockAry = [];


                            foreach($packageProductList as $packageDetails)
                            {
                                $db->where("so.status", array("Order Processing", "Paid"),"IN");
                                $db->where("sod.deleted", 0);
                                $db->where("sod.product_id", $packageDetails['package_id']);
                                $db->join("sale_order so", "sod.sale_id=so.id", "INNER");
                                //$db->groupBy('so.status');
                                $packageLock = $db->get("sale_order_detail sod",null, "SUM(sod.quantity) as quantity,so.status");

                                $data["debug2"] = $packageLockAry;
                                foreach($packageLock as $value) {
                                    if($value["status"] == "Order Processing")$value["status"] = "Paid";
                                    $packageLockAry[$value["status"]] +=  ($value["quantity"] ?? 0) * $packageDetails["quantity"];
                                }

                            }

                            $data["debug3"] = $packageLockAry;
                            $packageItemAvailableAmt[$packageProductID] = $packageItemTotalOnHand[$packageProductID] - $packageLockAry["Paid"] - $packageLockStockForPaid;


                        }
                        $data["debug3"] = $packageItemAvailableAmt;

                        arsort($packageItemAvailableAmt);
                        //arsort($packageItemLockAry);

                        $lowestAvailableItem = array_pop($packageItemAvailableAmt);
                        $data["debug4"] = ($lowestAvailableItem);
                        
                        $totalOnHand = floor($lowestAvailableItem  / $packageItem["quantity"]);
                        $availableAmt = $totalOnHand;
                        $lockStockForPPA = $packageItemTotalForecast;


                    }

                    $stock['on_hand'] = $totalOnHand;
                    $stock['lock_amt'] = $lockStockForPaid + $packageLockStock;
                    $stock['forecast_amt'] = $lockStockForPPA + ($packageLockAry["Pending Payment Approve"] ?? 0);
                    $stock['available_amt'] = $availableAmt;
                    $stock['wishlist_amt'] = $wishListAmt;

                    // for layer 2
                    $db->where('id',$value['warehouse_id']);
                    $warehouse = $db->getOne("warehouse");
                    $stock['warehouse'] = $warehouse['warehouse_location'] ? $warehouse['warehouse_location'] : "-";

                    $stockList[] = $stock;
                }

                $data['stockList']      = $stockList;
                // $data['totalPage']      = ceil($totalRecord/$limit[1]);
                // $data['pageNumber']     = $pageNumber;
                // $data['totalRecord']    = $totalRecord;
                // $data['numRecord']      = $limit[1];

                if($params['type'] == "export"){
                    // return array('status' => "error", 'code' => 110, 'statusMsg' => 'testing' , 'params' => $params);
                    $params['command'] = __FUNCTION__;
                    $data = Excel::insertExportData($params);
                    return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
                }

                return array("status"=> "ok", 'code' => 0, 'statusMsg' => $db->getLastQuery(), 'data' => $data);
            }
            else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Record Found", 'data' => "");
            }
        }

        function getProduct($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $id             = trim($params['id']);
            $soType         = $params['soType'];

            $db->where('is_archive', '0');
            $db->where('deleted', '0');
            $getProduct = $db->get("product", null, "id, name, cost, sale_price, barcode, product_type");
            
            foreach ($getProduct as $key => $value) {
                $getProductDetail[$key] = $value;
            }

            # handle for add SO
            if($soType === 'addSO')
            {
                $db->where('status', 'active');
                $paymentArray = $db->get('gotasty_payment_method');
                $data['paymentArray'] = $paymentArray;
            }

            $data['getProductDetail'] = $getProductDetail;

            return array('status' => "ok", 'code' => 0, 'statusMsg'=> $translations["B00103"][$language] /* Admin Profile Successfully Updated */, 'last_query' => $db->getLastQuery(), 'data' => $data);
        }

        function promoCodeSystemSetting () {
            $db = MysqliDb::getInstance();
            $db->where('name','promoCode');
            $db->where('type','promoCode');
            $db->where('module','mlm_promo_code');
            $promoCodeSystemSetting = $db->getOne('system_settings');
            $typePurposeAry = explode('#',$promoCodeSystemSetting['value']);
            $statusAry = explode('#',$promoCodeSystemSetting['reference']);
            return array(
                'typePurposeAry' => $typePurposeAry,
                'statusAry' => $statusAry,
            );
        }

        public function addPromoCode ($params) {
            $db       = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $datetime = date("Y-m-d H:i:s");
            $adminID = $db->userID;
            $tableName = 'mlm_promo_code';
            $tableName2 = 'promo_code_detail';
    
            $name               = $params['name'];
            $code               = $params['code'];
            $type               = $params['type']; // discount or free delivery or free product
            // $quantity           = $params['quantity'];
            $promoCodeUsage     = $params['promo_code_usage']; // automatically applied or use a code
            $applyOnFirst       = $params['apply_on_first']; // apply on how many person
            $startDate          = $params['start_date'];
            $endDate            = $params['end_date'];
            $applicability      = $params['applicability']; // apply on current order or send a coupon
            $discount           = $params['discount']; // discount amount
            $maxQuantity        = $params['max_quantity'];
            $discountType       = $params['discount_type']; // in percentage or amount
            $productDetail      = $params['product_list'];
            $maxDiscountAmount  = $params['max_discount_amount'];
            $discountApplyOn    = $params['discount_apply_on']; // on order or on cheapest product or on specific products
            $promoProduct       = $params['promo_product'];
            $ftpStatus          = $params['ftpStatus'];
            $applyType          = $params['apply_type'];

            $promoCodeSystemSetting = Admin::promoCodeSystemSetting();
            $typePurposeAry = $promoCodeSystemSetting['typePurposeAry'];
            $statusAry = $promoCodeSystemSetting['statusAry'];
    
            if (empty($name)) {
                $errorFieldArr[] = array(
                    'id' => 'nameError',
                    'msg' => $translations["E00221"][$language]
                );
            }

            if($applyType != 'autoApply')
            {
                if (empty($code)) {
                    $errorFieldArr[] = array(
                        'id' => 'codeError',
                        'msg' => $translations["E00221"][$language]
                    );
                }
            }
            
            if (empty($type)) {
                $errorFieldArr[] = array(
                    'id' => 'typeError',
                    'msg' => $translations["E00221"][$language]
                );
            } 
            // else if (empty($quantity))
            // {
            //     $errorFieldArr[] = array(
            //         'id' => 'quantityError',
            //         'msg' => $translations["E00221"][$language]
            //     );
            // }
            else if($type == 'billDiscount')
            {
                if (empty($discountType))
                {
                    $errorFieldArr[] = array(
                        'id' => 'discountTypeError',
                        'msg' => $translations["E00221"][$language]
                    );
                }
                else if (empty($discount))
                {
                    $errorFieldArr[] = array(
                        'id' => 'discountError',
                        'msg' => $translations["E00221"][$language]
                    );
                }
            }
            else if($type == 'PWP')
            {
                if (empty($productDetail))
                {
                    $errorFieldArr[] = array(
                        'id' => 'productIdError',
                        'msg' => $translations["E00221"][$language]
                    );
                }
            }
            else if($type == 'firstTimePurchase')
            {
                if (empty($productDetail))
                {
                    $errorFieldArr[] = array(
                        'id' => 'productIdError',
                        'msg' => $translations["E00221"][$language]
                    );
                }

                if(empty($ftpStatus))
                {
                    $ftpStatus = 0;
                }
                else
                {
                    if(strtolower($ftpStatus) == 'active')
                    {
                        $ftpStatus = 1;
                    }
                    else if(strtolower($ftpStatus) == 'inactive')
                    {
                        $ftpStatus = 0;
                    }
                }
            }
            else if($type == 'PWP2')
            {
                if (empty($productDetail))
                {
                    $errorFieldArr[] = array(
                        'id' => 'productIdError',
                        'msg' => $translations["E00221"][$language]
                    );
                }
            }
            else if (empty($discountApplyOn))
            {
                $errorFieldArr[] = array(
                    'id' => 'discountApplyError',
                    'msg' => $translations["E00221"][$language]
                );
            }
            // else if (empty($productId))
            // {
            //     $errorFieldArr[] = array(
            //         'id' => 'productIdError',
            //         'msg' => $translations["E00221"][$language]
            //     );
            // }
            else {
                if (!in_array($type,$typePurposeAry)) {
                    $errorFieldArr[] = array(
                        'id' => 'typeError',
                        'msg' => 'Type error'
                    );
                } else {
                    if (empty($code)) {
                        $errorFieldArr[] = array(
                            'id' => 'codeError',
                            'msg' => $translations["E00221"][$language]
                        );
                    } else {
                        $db->where('code',$code);
                        // $db->where('type',$type);
                        // $db->where('status',$statusAry,'IN');
                        $db->where('disabled',0);
                        if ($db->has($tableName)) {
                            $errorFieldArr[] = array(
                                'id' => 'codeError',
                                'msg' => $translations["A01247"][$language]
                            );
                        }
                    }
                }
            }
    
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            // $db->where('code', $code);
            // $db->where('disabled', '0');
            // $db->where('status', 'Active');
            // $codeExist = $db->getOne('mlm_promo_code');
            // if($codeExist)
            // {
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01234"][$language] /* Duplicate Promo Code */, 'data'=> '');
            // }
    
            $insertArray = array(
                'promo_code_name'       => $name,
                'code'                  => $code,
                'type'                  => $type,
                'is_first_time_purchase'=> $ftpStatus,
                'status'                => 'Active',
                'disabled'              => 0,
                'reference_id'          => '',
                'discount_apply_on'     => $discountApplyOn,
                'discount_type'         => $discountType,
                'discount'              => $discount,
                'max_discount_amount'   => $maxDiscountAmount,
                'max_quantity'          => $maxQuantity,
                'apply_type'            => $applyType,
                'start_date'            => $startDate,
                'end_date'              => $endDate,
                'created_at'            => $datetime,
                'created_by'            => $adminID,
            );
            $bool = $db->insert($tableName,$insertArray);
    
            if (!$bool) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed insert', 'data'=> '');

            // insert in promo_code_detail table
            if(!$productDetail)
            {
                $insertData = array(
                    'promo_code_id'         => $bool,
                    'product_id'            => '',
                    'quantity'              => $quantity,
                    'type'                  => $type,
                    'promo_code_usage'      => $promoCodeUsage,
                    'apply_on_first'        => $applyOnFirst,
                    'disabled'              => '0',
                    'created_at'            => $datetime,
                );
                $bool2 = $db->insert($tableName2, $insertData);
            }
            else
            {
                foreach($productDetail as $productArrayDetail)
                {
                    $insertData = array(
                        'promo_code_id'         => $bool,
                        'product_id'            => $productArrayDetail['product_id'],
                        'quantity'              => $productArrayDetail['quantity'],
                        'type'                  => $type,
                        'promo_code_usage'      => $promoCodeUsage,
                        'apply_on_first'        => $applyOnFirst,
                        'disabled'              => '0',
                        'created_at'            => $datetime,
                    );

                    $bool2 = $db->insert($tableName2, $insertData);
                }

                foreach($promoProduct as $insertPromoProduct)
                {
                    $insertData = array(
                        'promo_code_id' => $bool,
                        'product_id'    => $insertPromoProduct['product_id'],
                        'quantity'      => $insertPromoProduct['quantity'],
                        'sale_price'    => $insertPromoProduct['price'],
                        'disabled'      => '0',
                        'created_at'    => $datetime,
                    );
                    $bool3 = $db->insert('promo_code_product', $insertData);
                }
            }
            // return array('status' => "error", 'code' => 110, 'statusMsg' => 'testing' , 'insertData' => $insertData);

            if (!$bool2) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed insert', 'data'=> '');
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Success insert', 'data'=> '');
        }

        public function getPromoCodeDetail($params) {
            $db       = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $datetime = date("Y-m-d H:i:s");
            
            // $seeAll = $params['seeAll'] ? : 0;
            // $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            // $limit = General::getLimit($pageNumber);
            $promoCodeId = $params['promo_code_id'];

            $db->where('id', $promoCodeId);
            $db->where('disabled', '0');
            $db->where('status', 'Active');
            $promoCodeResult = $db->getOne('mlm_promo_code', 'promo_code_name as name,id, code, type, is_first_time_purchase, status, issued, used_amount, discount_apply_on, discount_type, discount, max_discount_amount, max_quantity, apply_type, start_date, end_date, created_at, updated_at, created_by');

            if(!$promoCodeResult)
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01229"][$language] /* Promo Code not found, please check again */, 'data'=> "");
            }
            if($promoCodeResult['is_first_time_purchase'] == 1)
            {
                $promoCodeResult['is_first_time_purchase'] = 'Active';
            }
            else
            {
                $promoCodeResult['is_first_time_purchase'] = 'Inactive';
            }

            $db->where('promo_code_id', $promoCodeResult['id']);
            $db->where('disabled', '0');
            $promoCodeDetail = $db->get('promo_code_detail', null, 'id, promo_code_id, product_id, quantity, type, promo_code_usage, apply_on_first, created_at, updated_at');

            $db->where('promo_code_id', $promoCodeId);
            $db->where('disabled', '0');
            $promoCodeProduct = $db->get('promo_code_product');

            if($promoCodeDetail)
            {
                $data['promoCodeDetail'] = $promoCodeDetail;
            }
            else
            {
                $data['promoCodeDetail'] = '';
            }

            $data['promoCodeResult'] = $promoCodeResult;
            $data['promoCodeProduct'] = $promoCodeProduct;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getPromoCodeUserListing($params) {
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;

            $promoCodeId = $params['promo_code_id'];
            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'] ? : 0;
            $limit = General::getLimit($pageNumber);

            if($seeAll) {
                $limit = NULL;
            }

            $clientID = $db->userID;
            $site = $db->userType;

            $db->where('ss.promo_code_id', $promoCodeId);
            $db->where('ss.disabled', '0');
            $db->join('client c', 'ss.client_id = c.id', 'LEFT');
            $copyDb = $db->copy();
            $codeUser = $db->get('so_service ss', $limit, 'ss.id, c.name as fullname, c.username as phone_number, ss.sale_id as SO_ID');

            if(!$codeUser){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            if($codeUser)
            {
                $data['codeUser'] = $codeUser;
            }
            else
            {
                $data['codeUser'] = array();
            }

            $totalRecord = $copyDb->getValue('so_service ss', 'COUNT(*)');
            $data['pageNumber']          = $pageNumber;
            $data['totalRecord']         = $totalRecord;
            if($seeAll == "1"){
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            }else{
                $data['totalPage'] = ceil($totalRecord/$limit[1]);
                $data['numRecord'] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data'=> $data);
        }

        public function getDOListing($params, $type){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $tableName      = "inv_delivery_order";

            $searchData     = $params['searchData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit          = General::getLimit($pageNumber);
            // $limit = 10;
            $decimalPlaces  = Setting::getSystemDecimalPlaces();
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $seeAll         = $params['seeAll'];
            $type           = $params['type'];

            $clientID = $db->userID;
            $site = $db->userType;

            if($seeAll == 1){
                $limit = null;
            }

            if($params['type'] == "export"){
                $params['command'] = __FUNCTION__;
                $params['exportType'] = $type;

                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' =>$translations["E00716"][$language], 'data' => $data);
            }

            if($limit) $limitCond = "LIMIT ".implode(",", $limit);

            $cpDb = $db->copy();

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {

                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'doNo':
                            $rule[] = array('col' => 'delivery_order_no', 'val' => '%'.$dataValue.'%', 'operator' => 'LIKE');

                        break;

                        case 'deliveryPartner':
                            $rule[] = array('col' => 'delivery_partner', 'val' => '%'.$dataValue.'%', 'operator' => 'LIKE');
                        break;

                        case 'status':
                            $rule[] = array('col' => 'status', 'val' => '%'.$dataValue.'%', 'operator' => 'LIKE');
                        break;

                        case 'createdBy':
                            $db->where('name', "%" . $dataValue . "%", 'LIKE');                            
                            $creatorID = $db->getValue('admin', 'id');

                            $rule[] = array('col' => 'creator_id', 'val' => '%'.$creatorID.'%', 'operator' => 'LIKE');
                        break;

                        case 'updatedBy':
                            $db->where('name', "%" . $dataValue . "%", 'LIKE');                            
                            $updateID = $db->getValue('admin', 'id');    
                            
                            $rule[] = array('col' => 'updater_id', 'val' => '%'.$updateID.'%', 'operator' => 'LIKE');
                            break;

                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            foreach ($rule as $key => $val) {
                $db->where($val["col"], $val["val"], $val["operator"]);
            }
            $db->orderBy("id", "DESC");
            $invDeliveryList = $db->get('inv_delivery_order',$limit);
            // return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => $limit);

            if(empty($invDeliveryList)){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found */, 'data' => '');
            }

            $invDeliveryListTotal = $db->get('inv_delivery_order');
            $totalRecord = $db->count;

            $db->groupBy("status");
            $statusList = $db->get("inv_delivery_order",null, "status");

            foreach($invDeliveryList as $key => $value){
                $db->where('id', $value['creator_id']);
                $creator = $db->getValue('admin', 'name');

                $db->where('id', $value['updater_id']);
                $updater = $db->getValue('admin', 'name');

                $newArr['delivery_order_no'] = $value['delivery_order_no'];
                $newArr['delivery_partner'] = $value['delivery_partner'];
                $newArr['status'] = $value['status'];
                $newArr['creator'] = $creator;
                $newArr['updater'] = $updater ?:'-';

                $newArrList[] = $newArr;
            }

            $data['statusList'] = $statusList;
            $data['invDeliveryList'] = $newArrList;
            $data["pageNumber"] = $pageNumber;
            $data["totalRecord"] = $totalRecord;
            if($seeAll == "1") {
                $data["totalPage"] = 1;
                $data["numRecord"] = $totalRecord;
            } else {
                $data["totalPage"] = ceil($totalRecord/$limit[1]);
                $data["numRecord"] = $limit[1];
            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00421"][$language] /* Purchase Order List successfully retrieved. */, 'data' => $data);
        }

        public function getDODetails($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $doNo           = trim($params['doNo']);
            $quantity       = 0;

            if(!$doNo) {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03624"][$language]/* No Result Found */, 'data'=> "");
            }

            $db->where('delivery_order_no', $doNo);
            $doDetails = $db->getOne('inv_delivery_order');

            $db->where('inv_delivery_order_id', $doDetails['id']);
            $db->where('disabled', 0);
            $doDetailList = $db->get('inv_delivery_order_detail', null, 'id, product_id, serial_number, box, remark');

            // return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["M03624"][$language]/* No Result Found */, 'data'=> $doDetails['creator_id']);

            $db->where('id', $doDetails['so_id']);
            $soNo = $db->getValue('sale_order', 'so_no');
            
            $db->where('id', $doDetails['creator_id']);
            $creatorName = $db->getValue('admin', 'name');
               
            $db->where('id', $doDetails['updater_id']);
            $updaterName = $db->getValue('admin', 'name');

            $deliveryDetails['delivery_order_no']   = $doDetails['delivery_order_no'];
            $deliveryDetails['reference_number']    = $doDetails['reference_number'];
            $deliveryDetails['delivery_partner']    = $doDetails['delivery_partner'];
            $deliveryDetails['tracking_number']     = $doDetails['tracking_number'];
            $deliveryDetails['status']              = $doDetails['status'];
            $deliveryDetails['batch_id']            = $doDetails['batch_id'];
            $deliveryDetails['remark']              = $doDetails['remark'];
            $deliveryDetails['created_at']          = $doDetails['created_at'];
            $deliveryDetails['soNo']                = $soNo;
            $deliveryDetails['creatorName']         = $creatorName;
            $deliveryDetails['updaterName']         = $updaterName;

            foreach($doDetailList as $key => $value) {
                
                if($value['product_id']){
                    $db->where('id', $value['product_id']);
                    $productName = $db->getValue('product', 'name');
                }
                $deliverProduct['id']              = $value['id'];
                $deliverProduct['serial_number']   = $value['serial_number'];
                $deliverProduct['box']             = $value['box'];
                $deliverProduct['remark']          = $value['remark'];
                $deliverProduct['productName']     = $productName;
                $quantity++;

                $newDeliverProduct[] = $deliverProduct;
            }
            
            $data['deliveryOrderDetail']            = $deliveryDetails;
            $data['deliveryOrderProductDetails']    = $newDeliverProduct;
            $data['deliveryOrderProductQuantity']   = $quantity;

            return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' => $data);
        }

        public function updateBatchProduct($params){
            $db = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $checkedIDs     = $params['checkedIDs'];
            $status         = trim(strtolower($params['status']));


            if(!$status){
                return array("code" => 1, "status" => "error", "statusMsg" => 'Status is empty', 'data' => '');
            }

            if(!is_array($checkedIDs) || empty($checkedIDs)){
                return array("code" => 1, "status" => "error", "statusMsg" => 'Array is empty', 'data' => $checkedIDs);
            }

            foreach($checkedIDs as $value){
                $db->where('id', $value);
                $prouductExist = $db->getOne('product');

                if(!$prouductExist){
                    return array("code" => 1, "status" => "error", "statusMsg" => 'Product does not exist', 'data' => '');
                }
            }

            if($status != 'publish' && $status != 'unpublish' && $status != 'archive'  && $status != 'unarchive'){
                return array("code" => 1, "status" => "error", "statusMsg" => 'Invalid Status', 'data' => '');
            }

            if($status == 'publish' || $status == 'archive'){
                $updateValue = 1;
            }else{
                $updateValue = 0;
            }

            if($status == 'publish' || $status == 'unpublish'){
                $updateArray = array(
                    'is_published'       => $updateValue,
                    'updated_at'         => date("Y-m-d H:i:s"),
                );
            }else if($status == 'archive' || $status == 'unarchive'){
                $updateArray = array(
                    'is_archive'       => $updateValue,
                    'updated_at'         => date("Y-m-d H:i:s"),
                );
            }
            $db->where('id', $checkedIDs, 'IN');
            $updateProductArr = $db->update('product',$updateArray);
           
            return array("code" => 0, "status" => "ok", "statusMsg" => '', 'data' => $status);
        }

        public function updatePromoCodeStatus ($params) {
            $db       = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $datetime = date("Y-m-d H:i:s");
            $adminID = $db->userID;
            $tableName = 'mlm_promo_code';
    
            $name               = $params['name'];
            $promoCodeID        = $params['promoCodeID'];
            $status             = $params['status'];
            $code               = $params['code'];
            $type               = $params['type']; // discount or free delivery or free product
            // $quantity           = $params['quantity'];
            $promoCodeUsage     = $params['promo_code_usage']; // automatically applied or use a code
            $applyOnFirst       = $params['apply_on_first']; // apply on how many person
            $startDate          = $params['start_date'];
            $endDate            = $params['end_date'];
            $applicability      = $params['applicability']; // apply on current order or send a coupon
            $discount           = $params['discount']; // discount amount
            $maxQuantity        = $params['max_quantity'];
            $discountType       = $params['discount_type']; // in percentage or amount
            $productDetail      = $params['product_list'];
            $maxDiscountAmount  = $params['max_discount_amount'];
            $discountApplyOn    = $params['discount_apply_on']; // on order or on cheapest product or on specific products
            $promoProduct       = $params['promo_product'];
            $ftpStatus          = $params['ftpStatus'];
            $applyType          = $params['apply_type'];

            $db->where('id',$promoCodeID);
            // $db->where('status',$status);
            $db->where('disabled',0);
            $promoCodeDetails = $db->getOne($tableName);
            if (!$promoCodeDetails) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Promo code id error', 'data'=> '');

            $promoCodeSystemSetting = Admin::promoCodeSystemSetting();
            $statusAry = $promoCodeSystemSetting['statusAry'];
    
            if (empty($name)) {
                $errorFieldArr[] = array(
                    'id' => 'nameError',
                    'msg' => $translations["E00221"][$language]
                );
            }

            if (empty($status)) {
                $errorFieldArr[] = array(
                    'id' => 'statusError',
                    'msg' => $translations["E00221"][$language]
                );
            }
            else if (empty($type)) {
                $errorFieldArr[] = array(
                    'id' => 'typeError',
                    'msg' => $translations["E00221"][$language]
                );
            }
            else if($applyType != 'autoApply'){
                if (empty($code)) {
                $errorFieldArr[] = array(
                    'id' => 'codeError',
                    'msg' => $translations["E00221"][$language]
                );
               } 
            }
            else if (empty($discountType))
            {
                $errorFieldArr[] = array(
                    'id' => 'discountTypeError',
                    'msg' => $translations["E00221"][$language]
                );
            }
            else if($discountType == 'billDiscount')
            {
                if (empty($discount))
                {
                    $errorFieldArr[] = array(
                        'id' => 'discountError',
                        'msg' => $translations["E00221"][$language]
                    );
                }
            }
            else if (empty($discountApplyOn))
            {
                $errorFieldArr[] = array(
                    'id' => 'discountApplyError',
                    'msg' => $translations["E00221"][$language]
                );
            }
            else {
                if (!in_array($status,$statusAry)) {
                    $errorFieldArr[] = array(
                        'id' => 'statusError',
                        'msg' => 'Status error'
                    );
                }
            }
    
            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            if(strtolower($ftpStatus) == 'active')
            {
                $ftpStatus = 1;
            }
            else if(strtolower($ftpStatus) == 'inactive')
            {
                $ftpStatus = 0;
            }

            foreach($promoProduct as $checkPromoProduct)
            {
                if(empty($checkPromoProduct['product_id']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01188"][$language] /* Invalid Product */, 'data'=> '');
                }

                if($type != 'PWP2')
                {
                    if(empty($checkPromoProduct['quantity']))
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01188"][$language] /* Invalid Product */, 'data'=> '');
                    }
                    if(intval($checkPromoProduct['quantity']) == 0)
                    {
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01188"][$language] /* Invalid Product */, 'data'=> '');
                    }
                }


                if(empty($checkPromoProduct['price']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01188"][$language] /* Invalid Product */, 'data'=> '');
                }
            }

            foreach($productDetail as $checkProductDetail)
            {
                if(empty($checkProductDetail['product_id']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01188"][$language] /* Invalid Product */, 'data'=> '');
                }

                if(empty($checkProductDetail['quantity']))
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01188"][$language] /* Invalid Product */, 'data'=> '');
                }

                if(intval($checkProductDetail['quantity']) == 0)
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01188"][$language] /* Invalid Product */, 'data'=> '');
                }
            }
            $updateArray = array(
                'promo_code_name'       => $name,
                'code'                  => $code,
                'type'                  => $type,
                'is_first_time_purchase'=> $ftpStatus,
                'status'                => $status,
                'discount_apply_on'     => $discountApplyOn,
                'discount_type'         => $discountType,
                'discount'              => $discount,
                'max_quantity'          => $maxQuantity,
                'max_discount_amount'   => $maxDiscountAmount,
                'apply_type'            => $applyType,
                'start_date'            => $startDate,
                'end_date'              => $endDate,
                'updated_at'            => $datetime,
            );
            $db->where('id',$promoCodeID);
            $bool = $db->update($tableName,$updateArray);
            if (!$bool) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed', 'data'=> '');

            if($status == 'Deactive')
            {
                $disabled = 1;
            }
            else
            {
                $disabled = 0;
            }

            if(!$productDetail)
            {
                $updateData = array(
                    'product_id'            => '',
                    'quantity'              => $quantity,
                    'type'                  => $type,
                    'promo_code_usage'      => $promoCodeUsage,
                    'apply_on_first'        => $applyOnFirst,
                    'disabled'              => $disabled,
                    'updated_at'            => $datetime,
                );
                $db->where('promo_code_id', $promoCodeID);
                $bool2 = $db->update('promo_code_detail', $updateData);
            }
            else
            {
                $updateArray = array(
                    'disabled' => '1',
                    'updated_at'   => $datetime,
                );
                $db->where('promo_code_id', $promoCodeID);
                $db->where('disabled', '0');
                $db->update('promo_code_detail', $updateArray);

                foreach($productDetail as $productArrayDetail)
                {
                    $insertData = array(
                        'promo_code_id'         => $promoCodeID,
                        'product_id'            => $productArrayDetail['product_id'],
                        'quantity'              => $productArrayDetail['quantity'],
                        'type'                  => $type,
                        'promo_code_usage'      => $promoCodeUsage,
                        'apply_on_first'        => $applyOnFirst,
                        'disabled'              => '0',
                        'created_at'            => $datetime,
                    );
                    $bool2 = $db->insert('promo_code_detail', $insertData);
                }

                $updateArray = array(
                    'disabled' => '1',
                    'updated_at'   => $datetime,
                );
                $db->where('promo_code_id', $promoCodeID);
                $db->where('disabled', '0');
                $db->update('promo_code_product', $updateArray);

                foreach($promoProduct as $insertPromoProduct)
                {
                    $insertData = array(
                        'promo_code_id' => $promoCodeID,
                        'product_id'    => $insertPromoProduct['product_id'],
                        'quantity'      => $insertPromoProduct['quantity'],
                        'sale_price'    => $insertPromoProduct['price'],
                        'disabled'      => '0',
                        'created_at'    => $datetime,
                    );
                    $bool3 = $db->insert('promo_code_product', $insertData);
                }
            }
    
            if (!$bool2) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed', 'data'=> '');
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Success', 'data'=> '');
        }

        public function deletePromoCode ($params) {
            $db       = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $datetime = date("Y-m-d H:i:s");
            $adminID = $db->userID;
            $tableName = 'mlm_promo_code';

            $promoCodeID = $params['promoCodeID'];
            // $status = $params['status'];

            $db->where('id',$promoCodeID);
            $db->where('disabled',0);
            $promoCodeDetails = $db->getOne($tableName);
            if (!$promoCodeDetails) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Promo code id error', 'data'=> '');

            $updateArray = array(
                'disabled'    => 1,
                'updated_at'  => $datetime,
            );
            $db->where('id',$promoCodeID);
            $bool = $db->update($tableName,$updateArray);

            if (!$bool) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed', 'data'=> '');

            $updateData = array(
                'disabled'   => 1,
                'updated_At' => $datetime,
            );
            $db->where('promo_code_id', $promoCodeID);
            $bool = $db->update('promo_code_detail', $updateData);
    
            if (!$bool) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed', 'data'=> '');
            return array('status' => "ok", 'code' => 0, 'statusMsg' => 'Success', 'data'=> '');
        }

        public function getPromoCodeListing($params){
            if ($params['type'] == "export") {
                $params['command'] = __FUNCTION__;
                $params['site'] = 'Admin';
                $data = Excel::insertExportData($params);
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["E00716"][$language], 'data' => $data);
            }

            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $searchData = $params['searchData'];
            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll = $params['seeAll'] ? $params['seeAll'] : 0;
            if (!$seeAll) $limit = General::getLimit($pageNumber);
            // $limit = General::getLimit($pageNumber);
            $site = $db->userType ?: $params['site'];
            $clientID = $db->userID;
            $tableName = 'mlm_promo_code';

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch ($dataName) {

                        case 'type':
                            $db->where("type", $dataValue);
                            break;

                        case 'code':
                            $db->where("code", $dataValue);
                            break;
                        
                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'date':
                            // Set db column here
                            $columnName = 'date(created_at)';

                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if (strlen($dateFrom) > 0) {
                                if ($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data' => "");

                                $db->where($columnName, date('Y-m-d 00:00:00', $dateFrom), '>=');
                            }
                            if (strlen($dateTo) > 0) {
                                if ($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00160"][$language] /* Invalid date. */, 'data' => "");

                                if ($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data' => $data);
                                // $dateTo += 86399;
                                $db->where($columnName, date('Y-m-d 23:59:59', $dateTo), '<=');
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

            $db->where('disabled',0);
            $copyDb = $db->copy();
            $db->orderBy("created_at", "DESC");
            $results = $db->get($tableName, $limit);

            if (!$results) return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No results found */, 'data' => "");

            foreach ($results as $res) {
                $adminIDlist[$res['created_by']] = $res['created_by'];
            }

            if ($adminIDlist) {
                $db->where('id', $adminIDlist, 'IN');
                $adminUsernameAry = $db->map('id')->get('admin', null, 'id,username');
            }

            foreach ($results as $res) {
                unset($temp);
                $temp['promoCodeID'] = $res['id'];
                $temp['name'] = $res['promo_code_name'];
                $temp['code'] = $res['code'];
                // $temp['type'] = $res['type'];

                if($res['type'] == "billDiscount"){
                    $temp['type'] = 'Bill Discount';
                }
                else if($res['type'] == "freeShipping"){
                    $temp['type'] = 'Free Shipping';
                }
                else if($res['type'] == "freeProduct"){
                    $temp['type'] = 'Free Product';
                }
                else if($res['type'] == "PWP"){
                    $temp['type'] = 'PWP (Fixed)';
                }
                else if($res['type'] == "PWP2"){
                    $temp['type'] = 'PWP (Flexible Amount)';
                }
                else if($res['type'] == "firstTimePurchase"){
                    $temp['type'] = 'Bill Discount on Specific Product';
                }

                if(!empty($res['used_amount']))
                {
                    $temp['usedAmount'] = $res['used_amount'];
                }
                else
                {
                    $temp['usedAmount'] = 0;
                }
                $temp['status'] = $res['status'];
                $listing[] = $temp;
            }

            $totalRecord = $copyDb->getValue($tableName, 'COUNT(*)');

            $data['listing'] = $listing;
            $data['pageNumber'] = $pageNumber;

            if ($seeAll == "1") {
                $data['totalPage'] = 1;
                $data['numRecord'] = $totalRecord;
            } else {
                $data['totalPage'] = ceil($totalRecord / $limit[1]);
                $data['numRecord'] = $limit[1];
            }
            $data['totalRecord'] = $totalRecord;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function deleteShippingAddress($params) {
            $db = MysqliDb::getInstance();
            $language     = General::$currentLanguage;
            $translations = General::$translations;

            $addressID = $params['id'];
            $dateTime = date('Y-m-d H:i:s');

            $db->where('id', $addressID);
            $searchAddress = $db->getValue("address", "address");

           if(!$searchAddress){
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00531"][$language], 'data' => '');
           }else{ 
                $disUser = array(
                    "disabled"               => 1,
                    "updated_at"         => $dateTime,
                );

                $db->where('id', $addressID);
                $disabledAddress = $db->update("address", $disUser);
           }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00458"][$language], 'data' => $addressID);
        }

        public function addNewMember($params) {
            $db                 = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;
            $browserInfo        = General::getBrowserInfo();
            $ip                 = $browserInfo["ip"]?$browserInfo["ip"]:"Unknown";
            $ipInfo             = General::ip_info($ip);

            // $params = $msgpackData['params'];
            // personal information
            $fullName           = trim($params['fullName']); 
            $dialingArea        = trim($params['dialingArea']);
            $phone              = trim($params['phone']); 
            $email              = trim($params['email']);
            $password           = trim($params['password']);
            $checkPassword      = trim($params['checkPassword']);
            $userType           = trim($params['userType']);
            $shippingAddress     = $params['shipping'];
             
            $dateTime           = date("Y-m-d H:i:s");
            $date               = date("Y-m-d");
            $sponsorID          = $params['sponsorId']; 

            // return array("code" => 1, "status" => "error", "statusMsg" => "Data not meet requirement", "data" => $ip);

            // $ip = $db->$ip;
            // $ip = $msgpackData['ip'];

            if($sponsorID)
            {
                $sponsorID = intval($sponsorID);
            }
            $validationResult = self::memberRegistration($params);
            if($validationResult['status'] == 'error')
            {
                $data = $validationResult['data'];
                $fields = $data['field'];

                foreach ($fields as $key => $value) {
                    $newKey = $key + 1;
                    $newErrorArr[$newKey] = $newKey . '.' . $value['msg'];
                }

                $errorString = implode("\n", $newErrorArr);

                // for ($i = 0; $i < count($fields); $i++) {
                    $find = array("%%phoneNumber%%", "%%ip%%", "%%country%%", "%%errorMessage%%", "%%dateTime%%");
                    $replace = array($dialingArea.$phone, $ip, $ipInfo['country'], $errorString, date("Y-m-d H:i:s"));
                    $outputArray = Client::sendTelegramMessage('10013', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                // }
                return array("code" => 1, "status" => "error", "statusMsg" => "Data not meet requirement", "data" => $validationResult['data']);
            }

            $mobileNumberCheck = General::mobileNumberInfo($dialingArea.$phone, "MY");
            if($mobileNumberCheck['isValid'] != 1){
                $find = array("%%phoneNumber%%", "%%ip%%", "%%country%%", "%%errorMessage%%", "%%dateTime%%");
                $replace = array($dialingArea.$phone, $ip, $ipInfo['country'], $translations["E01093"][$language]/* Invalid mobile number format */, date("Y-m-d H:i:s"));
                $outputArray = Client::sendTelegramMessage('10013', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
            
                return array('status' => 'error', 'code' => 1, 'statusMsg' =>  $translations["E00942"][$language] /* Invalid phone number. */, "data" => $mobileNumberCheck);
            }
            $validPhone = $mobileNumberCheck['phone'];

            // Check the referral code is valid or not
            if(!empty($sponsorID))
            {
                $db->where('concat(dial_code, phone)',$sponsorID);
                $validSponsorID = $db->getOne('client');

                $mobileNumberCheck2 = General::mobileNumberInfo($sponsorID, "MY");
                if($mobileNumberCheck2['isValid'] != 1){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00942"][$language] /* Invalid phone number. */, 'data' => $mobileNumberCheck2);
                }


                if(!$validSponsorID)
                {
                    return array("code" => 1, "status" => "error", "statusMsg" => $translations["E01179"][$language] /* Invalid Referral ID. */ );
                }
            }
            $validSponsor = $mobileNumberCheck2['phone'];

            // verify user is Guest or Client
            $db->where('concat(dial_code, phone)', $dialingArea.$phone);
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
                    'sponsor_id'    => $validSponsor,
                    'updated_at'    => date("Y-m-d H:i:s"),
                );
    
                //update Guest to Client Account
                $db->where('concat(dial_code, phone)',$dialingArea.$phone);
                $db->where('type','Guest');
                $result = $db->update('client',$updateData);

                $db->where('username',$validPhone);
                $clientInfo = $db->getOne('client');
                // test

                if($result)
                {
                    // Get Referral Name
                    $db->where('concat(dial_code, phone)',$sponsorID);
                    $sponsorDetails = $db->getOne('client',null,'name, dial_code, phone');
                    $sponsorName = $sponsorDetails['name'];
                    $sponsorPhone = $sponsorDetails['dial_code'].$sponsorDetails['phone'];

                    $db->where('username',$validPhone);
                    $clientInfo = $db->getOne('client');

                    $find = array("%%phoneNumber%%" , "%%name%%" , "%%sponsorPhone%%" ,"%%sponsorName%%" , "%%ip%%", "%%country%%", "%%dateTime%%");
                    $replace = array($validPhone,  $fullName, $sponsorID, $sponsorName, $ip, $ipInfo['country'], date("Y-m-d H:i:s"));
                    $outputArray = Client::sendTelegramMessage('10014', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                    return array('status' => 'ok', 'code' => 0, 'statusMsg' => $translations["B00168"][$language] /* Update successful. */, 'data' => $result);
                }
                else
                {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E00131"][$language] /* Update failed. */, 'data' => '');
                }
            }

            //Check is the user Exist or not
            $db->where('dial_code',$dialingArea);
            $db->where('phone',$phone);
            $db->where('type','Client');
            $userExist = $db->get('client',null);
            
            if($userExist)
            {
                $find = array("%%phoneNumber%%", "%%ip%%", "%%country%%", "%%errorMessage%%", "%%dateTime%%");
                $replace = array($dialingArea.$phone, $ip, $ipInfo['country'], $translations["E01279"][$language]/* Mobile number already associate with a registered account. Please sign in instead. */, date("Y-m-d H:i:s"));
                $outputArray = Client::sendTelegramMessage('10013', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                return array("code" => 1, "status" => "error", "statusMsg" => $translations["E00749"][$language] /* Phone Already Used. */ , 'data' => '');
            }

            $site = $db->userType;
            $payerID = $db->userID;
            
            if(strtolower($validationResult['status']) != 'ok'){
                return $validationResult;
            }

            if($site == "Admin"){
                $payerID = $sponsorID;
            }
            
            $dialingArea = str_replace("+", "", $dialingArea);
            $db->where("country_code", $dialingArea);
            $countryID = $db->getValue("country", "id");


            $clientID     = $db->getNewID();
            $batchID      = $db->getNewID();
            $belongID     = $db->getNewID();

            $passwordLogin = $password;

            $password = Setting::getEncryptedPassword($password);
            $memberID = self::generateMemberID();

            $dateOfBirth = date("Y-m-d H:i:s", $dateOfBirth);
            // insert into client table -----------
            $insertClientData = array(
                "member_id" => $memberID,
                "name" => $fullName,
                "display_name" => $fullName,
                "username" => $validPhone, 
                "dial_code" => $dialingArea,
                "phone" => $phone,
                "email" => $email,
                "password" => $password,
                // "address" => $address,
                "state_id" => $state,
                "country_id" => $country,
                "type" => $userType,
                "activated" => '1',
                "disabled" => '0',
                "sponsor_id" => $sponsorID,
                "created_at" => $dateTime,
            );
    
            $insertClientResult  = $db->insert('client', $insertClientData);
            // $lq = $db->getLastQuery();
            // return array("code" => 110, "status" => "ok", "statusMsg" => $lq);
            // Failed to insert client account
            if (!$insertClientResult)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00334"][$language] /* Failed to register member. */, 'data' => "1");

            // get the client id
            $db->where('concat(dial_code,phone)', $dialingArea.$phone);
            $clientID = $db->getOne('client','id');
            $clientID = $clientID['id'];

            $shippingAddress = self::filterAndRemoveDuplicateShippingAddresses($shippingAddress);

            if($shippingAddress)
            {
                foreach($shippingAddress as $details)
                {
                    if($details['address_type'] = 'shipping'){
                        $addressType = 1;
                    }else{
                        $addressType = 0;
                    }

                    $db->where('status', 'Active');
                    $db->where('name', $details['countryName']);
                    $countryID = $db->getValue("country", "id");

                    $db->where('disabled', 0);
                    $db->where('country_id', $countryID);
                    $db->where('name', $details['stateName']);
                    $stateID = $db->getValue("state", "id");

                    if (!empty($details['name'])) {
                        // Regular expression pattern to match only alphabets, spaces, and hyphens
                        $pattern = '/[\'^£$%&*()}{@#~?><>,|=_+¬-]/';
        
                        if (preg_match($pattern, $details['name'])) {
                            // The full name is valid (no special characters)
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Full name should not contain special characters.', 'data' => '');
                        }
                    } else {
                        // The full name parameter is empty
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Please enter a full name.', 'data' => '');
                    }
        
                    if (!empty($details['email'])) {
                        if (!filter_var($details['email'], FILTER_VALIDATE_EMAIL)) {
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E00121'][$language] /* Invalid email format. */, 'data' => '');
                        } 
                    } 

                    if($details['phone']){
                        $phone = $details['phone'];
    
                        // Extract the first two characters of the phone string
                        $firstTwoDigits = substr($phone, 0, 2);
                        
                        if ($firstTwoDigits !== "60") {
                            // Return an error if the first two digits are not "60"
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Phone Format', 'data' => '');
                        }

                        $mobileNumberCheck = General::mobileNumberInfo($details['phone'], "MY");
                        if($mobileNumberCheck['isValid'] != 1){
                            return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Phone Format', 'data' => $mobileNumberCheck);
                        }
                    }

                    if($details['address'] == null){
                        return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Please fill in address', 'data' => $mobileNumberCheck);
                    }
        
                        // insert new data
                        $insertData = array(
                            'type'              => $addressType,
                            'client_id'         => $clientID,
                            'name'              => $details['name'],
                            'email'             => $details['email'],
                            'phone'             => $details['phone'],
                            'address'           => $details['address'],
                            'address_2'         => $details['address2'],
                            'district_id'       => $details['district_id'],
                            'sub_district_id'   => $details['sub_district_id'],
                            'post_code'         => $details['post_code'],
                            'city'              => $details['city'],
                            'state_id'          => $stateID,
                            'country_id'        => $countryID,
                            'address_type'      => $details['address_type'],
                            'remarks'           => $details['remarks'],
                            'created_at'        => $dateTime,
                            'disabled'          => 0,
                        );
                        $insertClientDeliveryAddressResult  = $db->insert('address', $insertData);
                }
            }
           
            $activityData = array('user' => $fullName);
            $activityRes = Activity::insertActivity('Registration', 'T00001', 'L00001', $activityData, $clientID);
            // Failed to insert activity
            if(!$activityRes)
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00144"][$language] /* Failed to insert activity. */, 'data' => "");

            // Get Referral Name
            $db->where('concat(dial_code, phone)',$sponsorID);
            $sponsorDetails = $db->getOne('client',null,'name, dial_code, phone');
            $sponsorName = $sponsorDetails['name'];
            $sponsorPhone = $sponsorDetails['dial_code'].$sponsorDetails['phone'];

            $find = array("%%phoneNumber%%" , "%%name%%" , "%%sponsorPhone%%" ,"%%sponsorName%%" , "%%ip%%", "%%country%%", "%%dateTime%%");
            $replace = array($validPhone,  $fullName, $sponsorID, $sponsorName, $ip, $ipInfo['country'], date("Y-m-d H:i:s"));
            $outputArray = Client::sendTelegramMessage('10014', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
            // $content = '*Register Message* '."\n\n".'Member ID: '.$memberID."\n".'Type: Client'."\n".'Phone Number: +'.$dialingArea.$phone."\n".'Referral ID: '.$sponsorID."\n".'Referral Name: '.$sponsorName."\n".'Referral Phone No: +'.$sponsorPhone."\n".'Date: '.date('Y-m-d')."\n".'Time: '.date('H:i:s');
            // Client::sendTelegramNotification($content);

            
            $db->where('username', $validPhone);
            $clientID = $db->getValue("client", "id");

            unset($msgpackData);
            unset($params);
            $params['id'] = '';
            $params['username'] = $dialingArea.$phone;
            $params['loginBy'] = 'phone';
            $params['password'] = $passwordLogin;
            $msgpackData['params'] = $params;
            // $msgpackData['ip'] = '127.0.0.1';
            $msgpackData['ip'] = $ip;
                        
            return array('status' => "ok", 'code' => 0, 'statusMsg' => "Registration Successful", 'data' => $clientID);
        }

        public function memberRegistration($params) {
            $db = MysqliDb::getInstance();
            $language           = General::$currentLanguage;
            $translations       = General::$translations;

            $batchRegister      = trim($params['batchRegister']); 
            // personal information
            $dialingArea        = trim($params['dialingArea']);
            $phone              = trim($params['phone']); 
            $password           = trim($params['password']);
            $checkPassword      = trim($params['checkPassword']);

            $step               = trim($params['step']);
            $type               = trim($params['registerType']);
            $registerMethod     = trim($params['registerMethod']); // default username  
            $sponsorID        = trim($params['sponsorId']); 

            //Placement Option
            $site = $db->userType;
            $payerID = $db->userID;

            if ($site == "Admin") {
                $payerID = $params['clientID'];
            }

            $passwordEncryption  = Setting::getMemberPasswordEncryption();

			$maxFName = Setting::$systemSetting['maxFullnameLength'];
			$minFName = Setting::$systemSetting['minFullnameLength'];
			$maxUName = Setting::$systemSetting['maxUsernameLength'];
			$minUName = Setting::$systemSetting['minUsernameLength'];
			$maxPass  = Setting::$systemSetting['maxPasswordLength'];
			$minPass  = Setting::$systemSetting['minPasswordLength'];
			$maxTPass = Setting::$systemSetting['maxTransactionPasswordLength'];
			$minTPass = Setting::$systemSetting['minTransactionPasswordLength'];
            $maxAccPP = Setting::$systemSetting['maxAccPerPhone'];
			$otpCodeVerify         = Setting::$systemSetting["otpCodeVerify"];
			$isSponsorCodeRegister = Setting::$systemSetting["isSponsorCodeRegister"];
            $martialStatusArr = array("single","married","widowed","divorced","separated");
            $genderArr = array("male", "female");

            if(!$step){
                $step = 1;
            }


            if(!$registerMethod) $registerMethod = "username";
                $registerMethodArray = array('phone','email','username');
                if (!in_array($registerMethod,$registerMethodArray)) {
                    return array('status'=>'error','code'=>'1','statusMsg'=>$translations["E01025"][$language],'data'=>array('field'=>'registerMethod'));
            }

            if($step >= 1){

                // Validate phone
                if (empty($dialingArea) || empty($phone)) {
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
                    $db->where("dial_code", $dialingArea);
                    $db->where("phone", $phone);
                    $totalAccThisPhone = $db->getValue("client", "COUNT(*)");
                }

                 // Validate password
                if (empty($password)) {
                    $errorFieldArr[] = array(
                        'id' => 'passwordError',
                        'msg' => $translations["E00306"][$language] /* Please fill in password */
                    );
                } elseif (!preg_match("#[0-9]+#", $password)) {
                    $errorFieldArr[] = array(
                        'id' => 'passwordError',
                        'msg' => $translations["E00810"][$language] /* Login Password must set at least 6 and not more than 20 alphanumeric */
                    );

                } elseif (!preg_match("#[a-zA-z]+#", $password)) {
                    $errorFieldArr[] = array(
                        'id' => 'passwordError',
                        'msg' => $translations["E00810"][$language] /* Login Password must set at least 6 and not more than 20 alphanumeric */
                    );

                } else {
                    if (strlen($password) < $minPass || strlen($password) > $maxPass) {
                        $errorFieldArr[] = array(
                            'id' => 'passwordError',
                            'msg' => str_replace(array("%%minPass%%", "%%maxPass%%"), array($minPass, $maxPass), $translations["E00808"][$language]),
                        );
                    }
                }
                if(!empty($sponsorID))
                {
                    if (!preg_match("#[0-9]+#", $sponsorID)) 
                    {
                        $errorFieldArr[] = array(
                            'id' => 'referralError',
                            'msg' => $translations["E01179"][$language] /* Invalid Referral ID. */
                        );
                    }
                }

                //checking re-type password
                if (empty($checkPassword)) {
                    $errorFieldArr[] = array(
                        'id' => 'checkPasswordError',
                        'msg' => $translations["E00306"][$language] /* Please fill in password */
                    );
                } else {
                    if ($checkPassword != $password) {
                        $errorFieldArr[] = array(
                            'id' => 'checkPasswordError',
                            'msg' => $translations["E00309"][$language] /* Password not match */
                        );
                    }
                }
            } 

            if ($step >= 2) {


                if(is_numeric($country) && $country) {
                    $db->where("id",$country);
                    $countryRes = $db->getOne("country","name,translation_code");
                    if(!$countryRes){
                        $errorFieldArr[] = array(
                            "id"  => "countryIDError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }

                if (is_numeric($state) && $state){
                    $db->where("id",$state);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $stateRes = $db->getOne("state","name,translation_code");
                    if(!$stateRes){
                        $errorFieldArr[] = array(
                            "id"  => "stateError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }

                if (is_numeric($city) && $city){
                    $db->where("id",$city);
                    $db->where("state_id",$state);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $cityRes = $db->getOne("city","name,translation_code");
                    if(!$cityRes){
                        $errorFieldArr[] = array(
                            "id"  => "cityError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }

                if (is_numeric($district) && $district){
                    $db->where("id",$district);
                    $db->where("city_id",$city);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $districtRes = $db->getOne("county","name,translation_code");
                    if(!$districtRes){
                        $errorFieldArr[] = array(
                            "id"  => "districtErrror",
                            "msg" => $translations["E01113"][$language]
                        );
                    }
                }

                if (is_numeric($subDistrict) && $subDistrict){
                    $db->where("id",$subDistrict);
                    $db->where("county_id",$district);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $subDistrictRes = $db->getOne("sub_county","name,translation_code");
                    if(!$subDistrictRes){
                        $errorFieldArr[] = array(
                            "id"  => "subDistrictError",
                            "msg" => $translations["E01028"][$language]
                        );
                    }
                }

                if (is_numeric($postalCode) && $postalCode){
                    $db->where("id",$postalCode);
                    $db->where("sub_county_id",$subDistrict);
                    $db->where("country_id",$country);
                    $db->where("disabled",0);
                    $postalCodeRes = $db->getOne("zip_code","name,translation_code");
                    if(!$postalCodeRes){
                        $errorFieldArr[] = array(
                            "id"  => "postalCodeError",
                            "msg" => $translations["E01029"][$language]
                        );
                    }
                }
            }

            if ($step >= 3 && $bankOptional) { 

                if (empty($bankID)) {
                    $errorFieldArr[] = array(
                        'id'  => "bankTypeError",
                        'msg' => $translations["E01031"][$language] /* Please Select A Bank. */
                    );
                }

                if (empty($branch)) {
                    $errorFieldArr[] = array(
                        'id'  => "branchError",
                        'msg' => $translations["E01032"][$language] /* Please Insert Branch */
                    );
                }

                if (empty($bankCity)) {
                    $errorFieldArr[] = array(
                        'id'  => "bankCityError",
                        'msg' => $translations["E01033"][$language] /* Please Insert Bank City */
                    );
                }

                if (empty($accountHolder)) {
                    $errorFieldArr[] = array(
                        'id'  => "accountHolderError",
                        'msg' => $translations["E01034"][$language] /* Please Insert Account Holder's Name */
                    );

                }else{
                    if($accountHolder != $fullName){
                        $errorFieldArr[] = array(
                            "id" => "accountHolderError",
                            "msg" => $translations["E01106"][$language]
                        );
                    }
                }

                if (empty($accountNo)) {
                    $errorFieldArr[] = array(
                        'id'  => "accountNoError",
                        'msg' => $translations["E01035"][$language] /* Please Insert Account Number */
                    );
                }

            }

            if ($step >= 4) {

                if($childNumber > 0 && !$batchRegister){
                    $childAgeOption = explode('#', Setting::$systemSetting['childAgeOption']);
                    // childAge
                    if(!is_array($childAge)){
                        $errorFieldArr[] = array(
                            'id' => 'childAgeError',
                            'msg' => $translations["E01111"][$language] /* Invalid Age. */
                        );
                    }else if(count($childAge) != $childNumber){
                        $errorFieldArr[] = array(
                            'id' => 'childAgeError',
                            'msg' => $translations["E01112"][$language] /* Total count of age not match. */
                        );
                    }else{
                        foreach ($childAge as $childAgeRow) {
                            if(!$childAgeOption[$childAgeRow]){
                                $errorFieldArr[] = array(
                                    'id' => 'childAgeError',
                                    'msg' => $translations["E01111"][$language] /* Invalid Age. */
                                );
                                break;
                            }
                        }
                    }
                }
            }

            if($errorFieldArr){
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=> $data);
            }

            if ($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements. */, 'data'=>$data);
            }

            $dateOfBirth = date("d/m/Y", $dateOfBirth);

            $dataOut["dialingArea"] = $dialingArea;
            $dataOut["phone"] = $phone;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $dataOut);
        }

        public function generateMemberID(){
            $db = MysqliDb::getInstance();
            $db->where('name','memberIDLength');
            $memberIDLength= $db->getOne('system_settings','value');
            $min = 1; $max = 9; 
            $memberIDLength['value'] -= 1;
            for($i=1;$i<(int)$memberIDLength['value'];$i++) $max .= "9";
            while(1){ 
            	$firstDigit = mt_rand(1, 9);
                $memberID = $firstDigit.sprintf("%0".$memberIDLength['value']."s", mt_rand((int)$min, (int)$max));
                $db->where('member_id',$memberID);
                $check = $db->getOne('client','COUNT(id)');
                if($check['COUNT(id)'] == 0) break;
            }
            return $memberID;
        }

        function getStockExpirationDate($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $stockID            = $params['stockID'];
            $type               = $params['type'];
            $expirationDate     = $params['expirationDate'];
            $dateTime           = date('Y-m-d H:i:s');

            
            if (!$stockID) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Stock", 'data' => "");

            $db->where('id',$stockID);
            $stockData = $db->getOne('stock');

            $db->where('id',$stockData['product_id']);
            $productName = $db->getValue('product', 'name');

            $data['stockData'] = $stockData;
            $data['stockData']['productName'] = $productName;

            if($type == 'edit'){
                $updateArr = array(
                    "expiration_date"    => $expirationDate,
                    "updated_at"         => $dateTime,
                );
                $db->where('id',$stockID);
                $updateAddr = $db->update("stock", $updateArr);
            }

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
        }

        function getStockMovement($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['inputData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $limit          = General::getLimit($pageNumber);

            $layer          = $params['layer']; /* Stock Listing Layer */
            $productId        = $params['productId'];

            if (!$layer) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Stock Layer", 'data' => "");

            if ($layer == 1) {
               
                // $db->orderBy('s.id', 'DESC');
                // $db->groupBy('s.product_id');
                // $db->join('product p', 'p.id = s.product_id', 'INNER');
                // $db->where('s.status', array('Sold', 'Active'), "IN");

                if (count($searchData) > 0) {
                    foreach ($searchData as $k => $v) {
                        $dataName = trim($v['dataName']);
                        $dataValue = trim($v['dataValue']);
                        $dataType  = trim($v['dataType']);
    
                        switch ($dataName) {
    
                            case 'productName':
                                $db->where('name', "%" . $dataValue . "%", 'LIKE');
                            break;
                        }
                        unset($dataName);
                        unset($dataValue);
                    }
                }

                $db->where('deleted', 0);
                $result = $db->get("product", $limit , "id, name");

                $db->where('deleted', 0);
                $result2 = $db->get("product", null , "id, name");
                $totalRecord = $db->count;

                $data['pageNumber']       = $pageNumber;
                $data['totalRecord']      = $totalRecord;
                if($seeAll) {
                    $data['totalPage']    = 1;
                    $data['numRecord']    = $totalRecord;
                } else {
                    $data['totalPage']    = ceil($totalRecord/$limit[1]);
                    $data['numRecord']    = $limit[1];

                }
            }
            else if ($layer == 2) {
                if (!$productId) return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Product ID", 'data' => $productId);
                // Means the search params is there
                // return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Product ID", 'data' => $searchData);
                if (count($searchData) > 0) {
                    foreach ($searchData as $k => $v) {
                        $dataName = trim($v['dataName']);
                        $dataValue = trim($v['dataValue']);
                        $dataType = trim($v['dataType']);

                        switch($dataName) {
                            case 'date':
                                $db->where('sp.date_done', "%" . $dataValue . "%", 'LIKE');
                                break;

                            case 'dateRange':
                                $dateFrom = trim($v['tsFrom']);
                                $dateTo = trim($v['tsTo']);

                                if(intval($dateFrom) > intval($dateTo))
                                {
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                
                                if(strlen($dateFrom) > 0) {
                                    if($dateFrom < 0)
                                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
                                }
                                if(strlen($dateTo) > 0) {
                                    if($dateTo < 0)
                                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00156"][$language] /* Invalid date. */, 'data'=>"");
    
                                    if($dateTo < $dateFrom)
                                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00158"][$language] /* Date from cannot be later than date to. */, 'data'=>"");
                                }
                                
                                $dateFrom = date('Y-m-d H:i:s', $dateFrom);
                                $dateTo = date('Y-m-d H:i:s', $dateTo);
                                $db->where('(sp.date_done >= \'' . $dateFrom . '\' AND sp.date_done <= \'' . $dateTo . '\')'); // Enclose date strings in single quotes
                                unset($dateFrom);
                                unset($dateTo);
                                break;

                            case 'reference':
                                $db->where('sp.name', "%" . $dataValue . "%", 'LIKE');
                                break;

                            case 'serialNumber':
                                $db->where('s.serial_number', "%" . $dataValue . "%", 'LIKE');
                                break;
                            case 'source':
                                $db->where('sp.origin', "%" . $dataValue . "%", 'LIKE');
                                break;
                            case 'fromLocation':
                                $db->where('complete_name', "%" . $dataValue . "%", 'LIKE');
                                $stockLocationId = $db->getOne('stock_location');
                                $db->where('sp.location_id', $stockLocationId['id']);
                                break;
                            case 'toLocation':
                                $db->where('complete_name', "%" . $dataValue . "%", 'LIKE');
                                $stockLocationId = $db->get('stock_location', null, 'id');
                                $idArray = [];
                                foreach ($stockLocationId as $location) {
                                    $idArray[] = $location['id'];
                                }
                                $db->where('sp.location_dest_id', $idArray, "IN");
                                break;
                        }
                        unset($dataName);
                        unset($dataValue);
                    }
                }
                // $dataValue = '003';
                // $db->where('s.serial_number', "%" . $dataValue . "%", 'LIKE');
                $db->orderBy('sp.date_done', 'DESC');
                $db->where('s.product_id', $productId);
                $db->where('s.status', array('Sold', 'Active'), "IN");
                $db->join('stock_move_line smv', 'smv.stock_id = s.id', 'INNER');
                $db->join('stock_picking sp', 'smv.picking_id = sp.id', 'INNER');
                $result = $db->get("stock s", null , "s.id as id, sp.date_done, sp.name, s.serial_number, sp.location_id, sp.location_dest_id, sp.origin");
            }
            
            if (!empty($result)) {
                foreach($result as $value) {
                    $stock['id']                = $value['id'];
                    $stock['source']            = $value['origin'];
                    $stock['name']              = $value['name'] ? $value['name'] : "-";
                    $stock['serial_number']     = $value['serial_number'] ? $value['serial_number'] : "-";
                    $stock['date_done']         = $value['date_done'] ? $value['date_done'] : "-";


                    $db->where('status', array('Sold', 'Active'), "IN");
                    $db->where('product_id', $value['id']);
                    $quantity = $db->getValue('stock s', 'count(*)');

                    $db->where('id', $value['location_id']);
                    $toAdrr = $db->getValue('stock_location', 'complete_name');

                    $db->where('id', $value['location_dest_id']);
                    $fromAdrr = $db->getValue('stock_location', 'complete_name');

                    $stock['quantity']              = $quantity  ? $quantity : "-";
                    $stock['to']                    = $toAdrr  ? $toAdrr : "-";
                    $stock['from']                  = $fromAdrr  ? $fromAdrr : "-";

                    $stockList[] = $stock;
                }

                $data['stockList']      = $stockList;
                
                return array("status"=> "ok", 'code' => 0, 'statusMsg' => $db->getLastQuery(), 'data' => $data);
            }
            else {
                $data['stockList']      = '';
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Record Found", 'data' => $data);
            }
        }

        public function getCustomerDetail($params) {
            $db                     = MysqliDb::getInstance();
            $language               = General::$currentLanguage;
            $translations           = General::$translations;
            $dateTime               = date("Y-m-d H:i:s");

            $mobileNo         = $params['mobileNo'];
            $addressID        = $params['addressID'];
            $type             = $params['type'];

            $userID = $db->userID;
            $site = $db->userType;

            if($mobileNo){

                $mobileNumberCheck = General::mobileNumberInfo($mobileNo, "MY");
                if($mobileNumberCheck['isValid'] != 1){
                    $errorFieldArr[] = array(
                        'id'  => "mobileNoError",
                        'msg' => $translations['E00773'][$language] /* Invalid phone number */
                    );

                    if($errorFieldArr) {
                        $data['field'] = $errorFieldArr;
                        return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
                    }
                }
                
                $validPhoneNumber = $mobileNumberCheck['phone'];

                $db->where('username', $validPhoneNumber);
                $getUserDetail = $db->getOne("client", "id,name");

                if(!$getUserDetail){
                    return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations['E00773'][$language] /* Invalid phone number */, 'data' => '');
                }
            }

            
            if($getUserDetail){
                $db->where("client_id", $getUserDetail['id']);
                $db->where("disabled", 0);
                $getBillingAddress = $db->get("address",null, "id,name,email,phone,address,district_id,sub_district_id,post_code,city,state_id,country_id,address_type,remarks");

                $db->where("client_id", $getUserDetail['id']);
                $db->where("disabled", 0);
                $getShippingAddress = $db->get("address",null, "id,name,email,phone,address,district_id,sub_district_id,post_code,city,state_id,country_id,address_type,remarks");


                foreach($getBillingAddress as $key => $value){
                    $address = $value['address'];
                    $city = $value['city'];
                    $postCode = $value['post_code'];
                    $stateID = $value['state_id']; 
                    $countryID = $value['country_id']; 
    
                    $db->where('id',$stateID);
                    $db->where('country_id',$countryID);
                    $db->where('disabled',0);
                    $state = $db->getValue("state", "name");
    
                    $db->where('status',"Active");
                    $db->where('id',$countryID);
                    $country = $db->getValue("country", "name");
    
                    $malaysiaAddress = $address . ", " . $city . ", " . $postCode;
                    if ($state !== '') {
                        $malaysiaAddress .= ", " . $state;
                    }
                    if ($country !== '') {
                        $malaysiaAddress .= ", " . $country;
                    }
                    $billingAddressList[$key]['id'] = $value['id'];
                    $billingAddressList[$key]['email'] = $value['email'];
                    $billingAddressList[$key]['phone'] = "60" . $value['phone'];
                    $billingAddressList[$key]['address'] = Cash::concateAddress($value['id'], 'address');
                }

                foreach($getShippingAddress as $key => $value){
                    $address = $value['address'];
                    $city = $value['city'];
                    $postCode = $value['post_code'];
                    $stateID = $value['state_id']; 
                    $countryID = $value['country_id']; 
    
                    $db->where('id',$stateID);
                    $db->where('country_id',$countryID);
                    $db->where('disabled',0);
                    $state = $db->getValue("state", "name");
    
                    $db->where('status',"Active");
                    $db->where('id',$countryID);
                    $country = $db->getValue("country", "name");
    
                    $malaysiaAddress = $address . ", " . $city . ", " . $postCode;
                    if ($state !== '') {
                        $malaysiaAddress .= ", " . $state;
                    }
                    if ($country !== '') {
                        $malaysiaAddress .= ", " . $country;
                    }
                    $shippingAddressList[$key]['id'] = $value['id'];
                    $shippingAddressList[$key]['email'] = $value['email'];
                    $shippingAddressList[$key]['phone'] = "60" . $value['phone'];
                    $shippingAddressList[$key]['address'] = Cash::concateAddress($value['id'], 'address');
                }
                                
                $data['userDetail'] = $getUserDetail;
                $data['billingAddressList'] = $billingAddressList;
                $data['shippingAddressList'] = $shippingAddressList;
            }

            if($addressID){
                $db->where("id", $addressID);
                $db->where("disabled", 0);
                $getAddress = $db->getOne("address", "id,name,email,phone,address,address_2,district_id,sub_district_id,post_code,city,state_id,country_id,remarks");

                $data['addressList'] = $getAddress;
                $data['address_type'] = $type;

            }

            return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["A00123"][$language] /* Confirm */, 'data' => $data);
        }

        function getDeliveryFee($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $quantity       = 1;

            $deliveryMethod            = $params['deliveryMethod'];
            $id                        = $params['id'];
            $saleID                    = $params['saleID'];
            $previousFee               = $params['previousFee'];


            if(strtolower($deliveryMethod) != 'self pickup'){
                $db->where('sod.sale_id',$saleID );
                $db->where('sod.deleted', 0);
                $db->join('product p', 'p.id = sod.product_id', 'LEFT');
                $getProductListArray = $db->get('sale_order_detail sod', null , 'sod.id as id, sod.quantity as quantity, sod.subtotal as subtotal , sod.price_reduce as priceReduce, p.delivery_method as deliveryMethodID');
                $itemCount = 0;
                $total = 0;
                $db->where('name', $deliveryMethod);
                $deliveryMethod = $db->getOne('gotasty_delivery_method');
                $shipping_fee = number_format($deliveryMethod['price'],2);

                foreach($getProductListArray as $row){
                    if($row['deliveryMethodID']== $deliveryMethod['id']){
                        $itemCount = $itemCount + $row['quantity'];
                    }
                    if($row['priceReduce'] > 0){
                        $total = $total + ($row['priceReduce'] * $row['quantity']);
                    }else{
                        $total = $total + $row['subtotal'];
                    }
                }

                $db->where('dm.id', $deliveryMethod['id']);
                $db->where('dm.deleted', '0');
                $db->join('gotasty_delivery_method dm','dm.id = dmd.delivery_method_id', 'LEFT');
                $deliveryMethodPromo = $db->getOne('delivery_method_detail dmd', null, 'dmd.amount as amount, dmd.quantity as quantity, dm.id as id');

                if($deliveryMethodPromo['amount'] != null){
                    if ($total >= $deliveryMethodPromo['amount']){
                        $shipping_fee = 0;
                    }
                }
                if($deliveryMethodPromo['quantity'] != null){
                    if($itemCount >= $deliveryMethodPromo['quantity']){
                        $shipping_fee = 0.00;
                    }
                }

            }else{
                $shipping_fee = 0;
            }
            

            $data['fee'] = $shipping_fee;
            $data['id']  = $id; 
            $data['type']  = 'shippingFee'; 
            $data['quantity']  = $quantity; 
            $data['total']  = $shipping_fee; 
            $data['previousFee'] = $previousFee;
            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
            
        }

        function getDeliveryFee2($params){
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $quantity       = 1;

            $delivery_method           = $params['deliveryMethod'];
            $id                        = $params['id'];
            $SaleID                    = $params['saleID'];
            $previousFee               = $params['previousFee'];
            $dateTime                  = date('Y-m-d H:i:s');
            $shippingPostCode          = $params['shippingPostCode'];

            $db->where('id', $SaleID);
            $saleOrderInfo = $db->getOne('sale_order');
            $clientID = $saleOrderInfo['client_id'];

            $db->orderBy('id', 'DESC');
            $db->where('sale_id', $SaleID);
            $oldToken = $db->getOne('guest_token');
            if(!$oldToken)
            {
                // generate token
                $autoLoginTokenRes = General::generateAutoLoginToken($dateTime, $timeOut);
                if(!$dateTime) $dateTime = date('Y-m-d H:i:s');
                $wbToken = $autoLoginTokenRes['wbToken'];
                $bkendToken = $autoLoginTokenRes['bkendToken'];
                $expiredTS  = $autoLoginTokenRes['expiredTS'];
    
                // insert into guest_token table
                $insertData = array(
                    'token'         => $bkendToken,
                    'client_id'     => $clientID,
                    'sale_id'       => $SaleID,
                    'created_at'    => $dateTime,
                );
                $insertGuestToken = $db->insert('guest_token', $insertData);
            }
            else
            {
                $bkendToken = $oldToken['token'];
            }

            # Check if SO status is not paid
            if($saleOrderInfo['status'] == 'Draft')
            {
                # Get Shopping Cart
                unset($dataIn);
                $dataIn['bkend_token'] = $bkendToken;
                $dataIn['promo_code'] = '';
                $dataIn['redeemAmount'] = '';
                $dataIn['deliveryMethod'] = $delivery_method;
                $dataIn['adminResetClientID'] = $clientID;
                $dataIn['postcode'] = $shippingPostCode;
                $getCartDetails = Inventory::getShoppingCart2($dataIn);
                if($getCartDetails['status'] != 'ok')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to get SO details' , 'data' => $getCartDetails , 'dataIn' => $dataIn);
                }

                $data['fee'] = $getCartDetails['data']['deliveryFee'];
                $data['id']  = $id; 
                $data['type']  = 'shippingFee'; 
                $data['quantity']  = $quantity; 
                $data['total']  = $getCartDetails['data']['deliveryFee']; 
                $data['previousFee'] = $previousFee;
            }
            else
            {
                $db->where('sale_id', $SaleID);
                $db->where('deleted', '0');
                $db->where('type' , 'shipping_fee');
                $saleOrderShippingFee = $db->getOne('sale_order_detail');
                $newProductArr[$key]['Total'] = number_format($value['cost']*$value['quantity'], 2);

                $data['fee'] = number_format($saleOrderShippingFee['item_price'], 2);
                $data['id']  = $id; 
                $data['type']  = 'shippingFee'; 
                $data['quantity']  = $quantity; 
                $data['total']  = number_format($saleOrderShippingFee['subtotal'], 2); 
                $data['previousFee'] = $previousFee;
            }

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
        }

        function addNewSO($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];
            $deliveryFee = Setting::$systemSetting['deliveryFee'];

            $clientID           = $params['clientID'];
            $shipping_fee       = $params['shipping_fee'];
            $payment_amount     = $params['payment_amount'];
            $payment_tax        = $params['payment_tax'];
            // $payment_method     = $params['payment_method'];
            // $delivery_method    = $params['delivery_method'];
            $billingAddr        = $params['billingAddr'];
            $shippingAddr       = $params['shippingAddr'];
            $orderDetailArray   = $params['orderDetailArray'];
            $orderServiceArray  = $params['orderServiceArray'];
            $adminID            = $db->userID;
            $deliveryCount      = 0;
            $deliveryCharges    = 0;
            $dryDelivery        = 0;
            $pickUp             = 0;

            if(!$clientID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A00178"][$language] /* Client does not exist. */, 'data'=> '');
            }

            // if(!$delivery_method){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03836"][$language] /* Select a Delivery Method. */, 'data'=> '');
            // }

            // if(!$payment_method){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00503"][$language] /* Please select payment method */, 'data'=> '');
            // }

            if(!$billingAddr || !$shippingAddr){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00950"][$language] /* Please select address. */, 'data'=> '');
            }

            foreach ($orderDetailArray as $index => $item) {
                if (empty($item["product_id"])) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03075"][$language] /* Please Select Product. */, 'data'=> '');
                }
            }

            foreach ($orderDetailArray as $index => $item) {
                if (empty($item["quantity"])) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00999"][$language] /* Invalid Quantity. */, 'data'=> '');
                }
            }

            // foreach ($orderServiceArray as $index => $item) {
            //     if (strtolower($item['name']) == 'delivery' || strtolower($item['name']) == 'pickup'){
            //         $deliveryCount++;
            //     }
            // }
            // if($deliveryCount == 0 || !$deliveryCount){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03836"][$language] /* Select a Delivery Method. */, 'data'=> '');
            // }else if($deliveryCount > 1){
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please select one delivery method per purchase" /* Invalid Quantity. */, 'data'=> '');
            // }

            // if($payment_method == 'FPX'){
            //     $status = "Paid";
            // }else{
            //     $status = "Pending Payment Approve";
            // }

            $status = "Draft";
            $payment_tax = 0;
            $createdDate = date("Y-m-d H:i:s");

            $saleParams['clientID'] = $clientID;
            
            // $db->where('a.disabled', 0);
            // $db->where('a.token', $bkendToken);
            // $db->join('product b', 'a.product_id = b.id', 'LEFT');
            // $payment_amount = $db->getValue('shopping_cart a', 'SUM(b.sale_price * a.quantity)');

            $release_amount = $payment_amount;
            $delivery_method = "delivery";

            foreach($orderDetailArray as $key => $value){
                $db->where('id', $value['product_id']);
                $setMethodforDelivery = $db->getValue('product', 'delivery_method');

                $db->where('id', $setMethodforDelivery);
                $getDeliveryMethodName = $db->getValue('gotasty_delivery_method', 'name');

                $setMethodforDeliveryArry[] = $getDeliveryMethodName;
            }

            if ($setMethodforDeliveryArry) {
                foreach ($setMethodforDeliveryArry as $value) {
                    if ($value == 'Delivery Charges') {
                        $deliveryCharges++;
                    } else if ($value == 'Dry Delivery Charges') {
                        $dryDelivery++;
                    } else if ($value == 'Self Pickup') {
                        $pickUp++;
                    }
                }
            }

            if($deliveryCharges != 0 && $dryDelivery != 0){
                $deliveryFee = Setting::$systemSetting['deliveryFee'];
            }
            else if($deliveryCharges != 0 && $dryDelivery == 0){
                $deliveryFee = Setting::$systemSetting['deliveryFee'];
            }
            else if($deliveryCharges == 0 && $dryDelivery != 0){
                $deliveryFee = Setting::$systemSetting['dryDeliveryFee'];
            }
            else{
                $deliveryFee = Setting::$systemSetting['deliveryFee'];
            }

            if($delivery_method == "delivery"){
                if ($payment_amount > 280){
                    $shippingFee = 0;
                }else{
                    $shippingFee = $deliveryFee;
                }
            }else{
                $shippingFee = 0;
            }

                $db->where("so_no LIKE 'S%'");
                $db->orderBy("CAST(SUBSTRING(so_no, 2) AS UNSIGNED)", "DESC");
                $lastSaleRecord = $db->getOne("sale_order");

                $billingDetail = Cash::getFullAddress($billingAddr);	
                $shippingDetail = Cash::getFullAddress($shippingAddr);	

                if($lastSaleRecord) {
                    $last_sale_no = $lastSaleRecord["so_no"];
                } else {
                    $last_sale_no = "S00000";
                }

                $last_sale_no = substr($last_sale_no, 1);
                $next_sale_no = "S".sprintf('%05d', ($last_sale_no + 1));
                $payment_amount += $shippingFee;
                //insert sale
                $field = array("so_no",
                    "client_id","package_id","payment_amount","redeem_amount",
                    "shipping_fee","payment_tax","release_amount",
                    "payment_expired_date","status","delivery_method",
                    "refund","remark","updated_at","created_at",
                    "promotion", "promotion_code", 'discount_amount',
                    "billing_name","billing_phone", "billing_address", "billing_address2", "billing_post_code", "billing_state_id", "billing_city","billing_email",
                    "shipping_name","shipping_phone", "shipping_address", "shipping_address2", "shipping_post_code", "shipping_state_id", "shipping_city", "shipping_email"
                    );

                $value = array($next_sale_no,
                        $clientID,$package_id,$payment_amount,$redeemAmount,
                        $shippingFee,$payment_tax,$release_amount,
                        date("Y-m-d H:i:s"),$status,$delivery_method,
                        $refund,$remark,date("Y-m-d H:i:s"), $createdDate,
                        $isPromo, $promoCode, $discountAmount,
                        $billingDetail['name'], $billingDetail['phone'], $billingDetail['address'], $billingDetail['address_2'],$billingDetail['post_code'],$billingDetail['state_id'],$billingDetail['city'],$billingDetail['email'],
                        $shippingDetail['name'], $shippingDetail['phone'], $shippingDetail['address'], $shippingDetail['address_2'],$shippingDetail['post_code'],$shippingDetail['state_id'],$shippingDetail['city'],$shippingDetail['email']
                    );

                $arrayData = array_combine($field, $value);
                $purchase_id = $db->insert("sale_order",$arrayData); 

                // Client::sendSalesOrderNotification($clientID,$purchase_id,'add');
        
                // $db->where('disabled', 0);
                // $db->where('token', $bkendToken);
                // $shopping_cart = $db->get('shopping_cart a', null, '');

                if($purchase_id){
                    // $productList = $orderDetailArray;
                    $db->where("id", $adminID);
                    $adminUsername = $db->getValue("admin", 'username');

                    $db->where("id", $clientID);
                    $clientUsername = $db->getValue("client", 'name');

                    foreach($orderDetailArray as $key => $value){
                        $newProductArr[$key]['Product'] = $value['name'];
                        $newProductArr[$key]['Quantity'] = $value['quantity'];
                        $newProductArr[$key]['Price'] = $value['cost'];
                        $newProductArr[$key]['Total'] = number_format($value['cost']*$value['quantity'], 2);
                    }

                    $productList = '';
                    foreach ($newProductArr as $orderDetail) {
                        $lines = array();
                        foreach ($orderDetail as $key => $value) {
                            $lines[] = "$key: $value";
                        }
                        $result .= implode("\n", $lines) . "\n\n";
                    }

                    $find = array("%%adminUsername%%", "%%soID%%", "%%clientName%%", "%%time%%", "%%deliveryOption%%", "%%paymentOption%%", "%%productList%%");
                    $replace = array($adminUsername, $next_sale_no, $clientUsername, date("Y-m-d H:i:s"),$delivery_method,$payment_method, $result);
                    $outputArray = Client::sendTelegramMessage('10025', NULL, NULL, $find, $replace,"","","telegramAdminGroup");
                }

                $product_idAry = [];
                foreach ($orderDetailArray as $key =>  $detailRow) {
                    $product_idAry[$key]['id'] = $detailRow['product_id'];
                    $product_idAry[$key]['quantity'] = $detailRow['quantity'];
                }

                if ($product_idAry) {
                    foreach ($product_idAry as $value) {
                        // Assuming $db is the database connection object
                        $db->where("id", $value['id']);
                        $productDetail = $db->getOne("product");
                
                        if ($productDetail) {
                            // Assuming the 'quantity' field exists in the 'product' table
                            $quantity = $value['quantity']; // Corrected from $$productDetail['quantity']
                            $productDetail['quantity'] = $quantity;
                
                            $productAry[] = $productDetail;
                        }
                    }
                }
                
                if ($purchase_id){
                    $createdDate = date("Y-m-d H:i:s");
                    $subtotal = 0;

                    // loop through each shopping cart item
                    foreach ($productAry as $i => $item) {
                        //check SO status
                        $db->where('id', $purchase_id);
                        $soStatus = $db->getOne('sale_order');
                        if($soStatus)
                        {
                            $soStatus = $soStatus['status'];
                        }

                        $db->where('id', $item['id']);
                        $db->where('deleted', '0');
                        $productType = $db->getValue('product', 'product_type');

                        if($productType){
                            if($productType == 'product')
                            {
                                $stockType = 'product';
                            }
                            else
                            {
                                $stockType = 'package';
                            }
                        }
                        else{
                            $stockType = 'product';
                        }
                        //insert sale detail
                        $field = array(
                                "client_id","product_id","product_template_id",
                                "item_name","item_price",
                                "quantity", "subtotal","sale_id", "type", "deleted","created_at", "updated_at"
                            );
                        
                        $value = array(
                                $clientID,$item['id'],$item['product_template_id'],
                                $item['name'],$item['sale_price'],
                                $item['quantity'], Setting::setDecimal($item['sale_price']*$item['quantity']),
                                $purchase_id, $stockType, 0, date("Y-m-d H:i:s"),date("Y-m-d H:i:s")
                            );

                        $arrayData = array_combine($field, $value);
                        $saleDetail_id = $db->insert("sale_order_detail",$arrayData); 
                        $db->where('id', $purchase_id);
                        $soNo = $db->getOne('sale_order');
                        for($loopQuantity = 0; $loopQuantity < intval($item['quantity']); $loopQuantity++)
                        {
                            if(strtolower($stockType) == 'package')
                            {
                                $db->where('package_id', $item['id']);
                                $db->where('deleted', '0');
                                $packageItemDetail = $db->get('package_item');
    
                                foreach($packageItemDetail as $insertPackageProduct)
                                {
                                    $insertData = array(
                                        'so_no'         => $soNo['so_no'],
                                        'so_details_id' => $saleDetail_id,
                                        'product_id'    => $insertPackageProduct['product_id'],
                                        'package_id'    => $item['id'],
                                        'is_package'    => '1',
                                        'remark'        => '',
                                        'deleted'       => '0',
                                    );
                                    $db->insert('sale_order_item', $insertData);
                                }
                            }
                            else if(strtolower($stockType) == 'product')
                            {
                                $insertData = array(
                                    'so_no'         => $soNo['so_no'],
                                    'so_details_id' => $saleDetail_id,
                                    'product_id'    => $item['id'],
                                    'package_id'    => '',
                                    'is_package'    => '0',
                                    'remark'        => '',
                                    'deleted'       => '0',
                                );
                                $db->insert('sale_order_item', $insertData);
    
                            }
                        }
                        Client::sendSalesOrderNotification($clientID,$purchase_id,'add');
                    }

                    if($delivery_method == 'delivery'){
                        
                        $insertData = array(
                            'client_id'                 => $clientID,
                            'product_id'                => 0,
                            'product_template_id'       => 0,
                            'item_name'                 => 'Delivery Charges',
                            'item_price'                => $deliveryFee,
                            'quantity'                  => 1,
                            'subtotal'                  => $deliveryFee,
                            'sale_id'                   => $purchase_id,
                            'type'                      => 'shipping_fee',
                            'deleted'                   => 0,
                            'created_at'                => date("Y-m-d H:i:s"),
                            'updated_at'                => date("Y-m-d H:i:s"),
                        );
                        $insertResult  = $db->insert('sale_order_detail', $insertData);
                    }else{
                        $insertData = array(
                            'client_id'                 => $clientID,
                            'product_id'                => 0,
                            'product_template_id'       => 0,
                            'item_name'                 => 'Self Pickup',
                            'item_price'                => 0,
                            'quantity'                  => 1,
                            'subtotal'                  => 0,
                            'sale_id'                   => $purchase_id,
                            'type'                      => 'shipping_fee',
                            'deleted'                   => 0,
                            'created_at'                => date("Y-m-d H:i:s"),
                            'updated_at'                => date("Y-m-d H:i:s"),
                        );
                        $insertResult  = $db->insert('sale_order_detail', $insertData);
                    }

                }
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $purchase_id);
        }

        function addNewSO2($params){
            $db = MysqliDb::getInstance();
            $language = General::$currentLanguage;
            $translations = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $clientID           = $params['clientID'];
            $shipping_fee       = $params['shipping_fee'];
            $payment_amount     = $params['payment_amount'];
            $payment_tax        = $params['payment_tax'];
            // $payment_method     = $params['payment_method'];
            // $delivery_method    = $params['delivery_method'];
            $billingAddr        = $params['billingAddr'];
            $shippingAddr       = $params['shippingAddr'];
            $orderDetailArray   = $params['orderDetailArray'];
            $orderServiceArray  = $params['orderServiceArray'];
            $shippingPostCode   = $params['shippingPostCode'];
            $adminID            = $db->userID;
            $deliveryCount      = 0;
            $deliveryCharges    = 0;
            $dryDelivery        = 0;
            $pickUp             = 0;

            if(!$clientID){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["A00178"][$language] /* Client does not exist. */, 'data'=> '');
            }

            if(!$billingAddr || !$shippingAddr){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00950"][$language] /* Please select address. */, 'data'=> '');
            }

            foreach ($orderDetailArray as $index => $item) {
                if (empty($item["product_id"])) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["M03075"][$language] /* Please Select Product. */, 'data'=> '');
                }
            }

            foreach ($orderDetailArray as $index => $item) {
                if (empty($item["quantity"])) {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00999"][$language] /* Invalid Quantity. */, 'data'=> '');
                }
            }

            $status = "Draft";
            $payment_tax = 0;
            $createdDate = date("Y-m-d H:i:s");

            $saleParams['clientID'] = $clientID;
            
            $release_amount = $payment_amount;
            $delivery_method = "delivery";

            # Add Shopping Cart
            foreach($orderDetailArray as $key => $value){
                unset($dataIn);
                if(!$bkendToken)
                {
                    $bkendToken = 'a';// pass variable to generate new token
                }
                else
                {
                    $bkendToken = $addCart['data']['bkend_token'];
                }
                $dataIn['adminResetClientID'] = $clientID;
                $dataIn['packageID'] = $value['product_id'];
                $dataIn['quantity'] = $value['quantity'];
                $dataIn['type'] = 'add';
                $dataIn['product_template'] = '';
                $dataIn['bkend_token'] = $bkendToken; 
                $dataIn['step'] = '1';
                $addCart = Inventory::addShoppingCart($dataIn);
                if($addCart['status'] != 'ok')
                {
                    return array('status' => "error", 'code' => 1, 'statusMsg' => $addCart['statusMsg'] , 'data' => $addCart);
                }
            }

            if($bkendToken == 'a')
            {
                $bkendToken = $addCart['data']['bkend_token'];
            }
            # Get Shopping Cart
            unset($dataIn);
            $dataIn['bkend_token'] = $bkendToken;
            $dataIn['promo_code'] = '';
            $dataIn['redeemAmount'] = '';
            $dataIn['deliveryMethod'] = $delivery_method;
            $dataIn['adminResetClientID'] = $clientID;
            $dataIn['postcode'] = $shippingPostCode;
            $getCartDetails = Inventory::getShoppingCart2($dataIn);
            if($getCartDetails['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $getCartDetails['statusMsg'] , 'data' => $getCartDetails);
            }

            ## delivery not available 
            if($getCartDetails['data']['deliveryAvailability'] == '0'){
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01284"][$language] /* Delivery is not available in your postcode area. */ , 'data' => $getCartDetails , 'dataIn' => $dataIn);
            }

            # Update Sale Order
            unset($dataIn);
            $dataIn['saleID'] = $addCart['saleID']['data'];
            $dataIn['promo_code'] = '';
            $dataIn['adminResetClientID'] = $clientID;
            $dataIn['paymentMethod'] = '';
            $dataIn['adminSOStatus'] = 'Draft';
            $dataIn['paymentDelivery'] = '';
            $dataIn['total_price'] = '';
            $dataIn['redeemAmount'] = '';
            $dataIn['bkend_token'] = $bkendToken;
            $dataIn['deliveryMethod'] = $delivery_method;
            $dataIn['postcode'] = $shippingPostCode;
            $updateSO = Cash::updateSaleOrder($dataIn);
            if($updateSO['status'] != 'ok')
            {
                return array('status' => "error", 'code' => 1, 'statusMsg' => $updateSO['statusMsg'] , 'data' => $updateSO, 'dataIn' => $dataIn);
            }

            # update Address to SO
            $billingDetail = Cash::getFullAddress($billingAddr);	
            $shippingDetail = Cash::getFullAddress($shippingAddr);	

            $updateData = array(
                'billing_name'          => $billingDetail['name'],
                'billing_phone'         => $billingDetail['phone'],
                'billing_address'       => $billingDetail['address'],
                'billing_address2'      => $billingDetail['address_2'],
                'billing_post_code'     => $billingDetail['post_code'],
                'billing_state_id'      => $billingDetail['state_id'],
                'billing_city'          => $billingDetail['city'],
                'billing_email'         => $billingDetail['email'],
                'shipping_name'         => $shippingDetail['name'],
                'shipping_phone'        => $shippingDetail['phone'],
                'shipping_address'      => $shippingDetail['address'],
                'shipping_address2'     => $shippingDetail['address_2'],
                'shipping_post_code'    => $shippingDetail['post_code'],
                'shipping_state_id'     => $shippingDetail['state_id'],
                'shipping_city'         => $shippingDetail['city'],
                'shipping_email'        => $shippingDetail['email'],
            );
            $db->where('id', $addCart['saleID']['data']);
            $db->update('sale_order', $updateData);

            $db->where("id", $adminID);
            $adminUsername = $db->getValue("admin", 'username');

            $db->where('id', $addCart['saleID']['data']);
            $soInfo = $db->getOne('sale_order');
            $next_sale_no = $soInfo['so_no'];

            $db->where("id", $clientID);
            $clientUsername = $db->getValue("client", 'name');

            
            $newOrderArray = $getCartDetails['data']['cartList'];

            foreach($newOrderArray as $key => $value)
            {
                $newProductArr[$key]['Product'] = $value['productName'];
                $newProductArr[$key]['Quantity'] = $value['quantity'];
                $newProductArr[$key]['Price'] = $value['price'];
                $newProductArr[$key]['Total'] = number_format($value['price']*$value['quantity'], 2);
            }

            $productList = '';
            foreach ($newProductArr as $orderDetail) {
                $lines = array();
                foreach ($orderDetail as $key => $value) {
                    $lines[] = "$key: $value";
                }
                $result .= implode("\n", $lines) . "\n\n";
            }

            $find = array("%%adminUsername%%", "%%soID%%", "%%clientName%%", "%%time%%", "%%deliveryOption%%", "%%paymentOption%%", "%%productList%%");
            $replace = array($adminUsername, $next_sale_no, $clientUsername, date("Y-m-d H:i:s"),$delivery_method,$payment_method, $result);
            $outputArray = Client::sendTelegramMessage('10025', NULL, NULL, $find, $replace,"","","telegramAdminGroup");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $addCart);
        }

        function getListing() {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;


            $db->where("status", 'Active');
            $getCountry = $db->get("country",null, "id,name");

            $db->where("country_id", $getCountry[0]['id']);
            $db->where("disabled", 0);
            $getState = $db->get("state",null, "id,name");


            $data['country'] = $getCountry;
            $data['state'] = $getState;



            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
        }

        function insertMemberAddress($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");

            $clientID               = $params['clientID'];
            $billingName            = $params['billingName'];
            $billingEmail           = $params['billingEmail'];
            $billingPhone           = $params['billingPhone'];
            $billingAddress         = $params['billingAddress'];
            $billingAddressLine2    = $params['billingAddressLine2'];
            $billingPostCode        = $params['billingPostCode'];
            $billingCity            = $params['billingCity'];
            $billingState           = $params['billingState'];
            $billingCountry         = $params['billingCountry'];
            $addressType            = $params['addressType'];

            if(!$clientID){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Client', 'data' => '');
            }

            if($addressType == 'billing'){
                if(!$billingName){
                    $errorFieldArr[] = array(
                        'id'  => "billingNameError",
                        'msg' => $translations['E00227'][$language] /* Invalid username */
                    );
                }
    
                if($billingEmail){
                    $pattern = "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/";
                    if (!preg_match($pattern, $billingEmail)) {
                        $errorFieldArr[] = array(
                            'id'  => "billingEmailError",
                            'msg' => $translations['E00121'][$language] /* Invalid email format. */
                        );
                    }
                }
    
                if(!$billingPhone){
                    $errorFieldArr[] = array(
                        'id'  => "billingPhoneError",
                        'msg' => $translations['E00773'][$language] /* Invalid phone number. */
                    );
                }
    
                if(!$billingAddress){
                    $errorFieldArr[] = array(
                        'id'  => "billingAddressError",
                        'msg' => $translations['E01275'][$language] /* Invalid Address */
                    );
                }

                if(!$billingAddressLine2){
                    $errorFieldArr[] = array(
                        'id'  => "billingAddressLine2Error",
                        'msg' => $translations['E01276'][$language] /* Invalid Address */
                    );
                }
    
                if(!$billingPostCode){
                    $errorFieldArr[] = array(
                        'id'  => "billingPostCodeError",
                        'msg' => $translations['E00946'][$language] /* Please Insert Post Code */
                    );
                }
    
                if(!$billingCity){
                    $errorFieldArr[] = array(
                        'id'  => "billingCityError",
                        'msg' => $translations['E01029'][$language] /* Please Insert City */
                    );
                }
    
                if(!$billingState){
                    $errorFieldArr[] = array(
                        'id'  => "billingStateError",
                        'msg' => $translations['M03427'][$language] /* Please Select State */
                    );
                }
    
                if(!$billingCountry){
                    $errorFieldArr[] = array(
                        'id'  => "billingCountryError",
                        'msg' => $translations['E00303'][$language] /* Please Select Country */
                    );
                }
    
                if($billingPhone){
                    $mobileNumberCheck = General::mobileNumberInfo($billingPhone, "MY");
    
                    if($mobileNumberCheck['isValid'] != 1){
                        // return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Phone Format', 'data' => $mobileNumberCheck);
                        $errorFieldArr[] = array(
                            'id'  => "billingPhoneError",
                            'msg' => $translations['E00773'][$language] /* Invalid phone number */
                        );
                    }
                    $validPhoneNumber = $mobileNumberCheck['phone'];
                }
            }
            else if($addressType == 'shipping'){
                if(!$billingName){
                    $errorFieldArr[] = array(
                        'id'  => "shippingNameError",
                        'msg' => $translations['E00227'][$language] /* Invalid username */
                    );
                }
    
                if($billingEmail){
                    $pattern = "/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/";
                    if (!preg_match($pattern, $billingEmail)) {
                        $errorFieldArr[] = array(
                            'id'  => "shippingEmailError",
                            'msg' => $translations['E00121'][$language] /* Invalid email format. */
                        );
                    }
                }
    
                if(!$billingPhone){
                    $errorFieldArr[] = array(
                        'id'  => "shippingPhoneError",
                        'msg' => $translations['E00773'][$language] /* Invalid phone number. */
                    );
                }
    
                if(!$billingAddress){
                    $errorFieldArr[] = array(
                        'id'  => "shippingAddressError",
                        'msg' => $translations['E01275'][$language] /* Invalid Address */
                    );
                }

                if(!$billingAddressLine2){
                    $errorFieldArr[] = array(
                        'id'  => "shippingAddressLine2Error",
                        'msg' => $translations['E01276'][$language] /* Invalid Address */
                    );
                }
    
                if(!$billingPostCode){
                    $errorFieldArr[] = array(
                        'id'  => "shippingPostCodeError",
                        'msg' => $translations['E00946'][$language] /* Please Insert Post Code */
                    );
                }
    
                if(!$billingCity){
                    $errorFieldArr[] = array(
                        'id'  => "shippingCityError",
                        'msg' => $translations['E01029'][$language] /* Please Insert City */
                    );
                }
    
                if(!$billingState){
                    $errorFieldArr[] = array(
                        'id'  => "shippingStateError",
                        'msg' => $translations['M03427'][$language] /* Please Select State */
                    );
                }
    
                if(!$billingCountry){
                    $errorFieldArr[] = array(
                        'id'  => "shippingCountryError",
                        'msg' => $translations['E00303'][$language] /* Please Select Country */
                    );
                }
    
                if($billingPhone){
                    $mobileNumberCheck = General::mobileNumberInfo($billingPhone, "MY");
    
                    if($mobileNumberCheck['isValid'] != 1){
                        // return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Phone Format', 'data' => $mobileNumberCheck);
                        $errorFieldArr[] = array(
                            'id'  => "shippingPhoneError",
                            'msg' => $translations['E00773'][$language] /* Invalid phone number */
                        );
                    }
                    $validPhoneNumber = $mobileNumberCheck['phone'];
                }
            }


            if($errorFieldArr) {
                $data['field'] = $errorFieldArr;
                return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E00130"][$language] /* Data does not meet requirements */, 'data' => $data);
            }

            if($addressType == 'shipping'){
                $type = 1;
            }else{
                $type = 0;
            }

            $addressChecking['clientID']            = $clientID;
            $addressChecking['billingName']         = $billingName;
            $addressChecking['billingEmail']        = $billingEmail;
            $addressChecking['billingPhone']        = $validPhoneNumber;
            $addressChecking['billingAddress']      = $billingAddress;
            $addressChecking['billingAddressLine2'] = $billingAddressLine2;
            $addressChecking['billingPostCode']     = $billingPostCode;
            $addressChecking['billingCity']         = $billingCity;
            $addressChecking['billingState']        = $billingState;
            $addressChecking['billingCountry']      = $billingCountry;

            $checkAddress = self::checkAddressDuplication($addressChecking);
            
            if($checkAddress['status'] != 'error'){
                $insertData = array(
                    'type'              => $type,
                    'client_id'         => $clientID,
                    'name'              => $billingName,
                    'email'             => $billingEmail,
                    'phone'             => $validPhoneNumber,
                    'address'           => $billingAddress,
                    'address_2'         => $billingAddressLine2,
                    'district_id'       => 0,
                    'sub_district_id'   => 0,
                    'post_code'         => $billingPostCode,
                    'city'              => $billingCity,
                    'state_id'          => $billingState,
                    'country_id'        => $billingCountry,
                    'address_type'      => $addressType,
                    'created_at'        => $dateTime,
                    'disabled'          => 0,
                );
                $insertResult  = $db->insert('address', $insertData);
            }

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $insertResult);
        }

        function checkAddressDuplication($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;

            $clientID               = $params['clientID'];
            $billingName            = $params['billingName'];
            $billingEmail           = $params['billingEmail'];
            $billingPhone           = $params['billingPhone'];
            $billingAddress         = $params['billingAddress'];
            $billingAddressLine2    = $params['billingAddressLine2'];
            $billingPostCode        = $params['billingPostCode'];
            $billingCity            = $params['billingCity'];
            $billingState           = $params['billingState'];
            $billingCountry         = $params['billingCountry'];
            // $addressType            = $params['addressType'];

            $db->where("client_id", $clientID);
            $db->where("disabled", 0);
            $addressList = $db->get("address",null, "id, name, phone, address, address_2, post_code, city, state_id, country_id");

            foreach($addressList as $key => $value){

                if($billingName == $value['name'] && $billingPhone == $value['phone'] && $billingAddress == $value['address'] && $billingAddressLine2 == $value['address_2'] && $billingPostCode == $value['post_code'] && $billingCity == $value['city'] && $billingState == $value['state_id'] && $billingCountry == $value['country_id']){
                    $addressID = $value['id'];
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Duplicate Address', 'data' => $addressID);
                }
            }

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => '');
        }

        function filterAndRemoveDuplicates($array) {
            $result = array();
            $addresses = array();
        
            foreach ($array as $item) {
                $address = $item['address_line_1'] ? $item['address_line_1'] : $item['address'];
                $branchName = $item['branch_name'];
                $key = $branchName . '|' . $address; 
        
                if (!isset($addresses[$key])) {
                    $result[] = $item; 
                    $addresses[$key] = true; 
                }
            }
        
            return $result;
        }
        
        function filterAndRemoveDuplicateShippingAddresses($array) {
            $result = array();
            $addresses = array();
        
            foreach ($array as $item) {
                // Create a unique key using all the values in the array
                $key = implode('|', $item);
        
                // Check if the key already exists in the result array
                if (!isset($addresses[$key])) {
                    $result[] = $item; // Add the item to the result array
                    $addresses[$key] = true; // Mark the key as added to the addresses array
                }
            }
        
            return $result;
        }
        
        function getStockCount($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime               = date("Y-m-d H:i:s");

            $productID               = $params['productID'];
           
            if(!$productID){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Product', 'data' => '');
            }

            // Get Latest Transfer No + 1 
            $db->orderBy("transfer_no", "DESC");
            $transferNo = $db->getValue("stock_transfer", "transfer_no");

            $numericPart = (int)substr($transferNo, 1); 
            $newTransferValue = "T" . str_pad($numericPart + 1, strlen($transferNo) - 1, '0', STR_PAD_LEFT); 

            // Get Product Name
            $db->where("id", $productID);
            $productDetail = $db->getValue("product", "name");

            // Get All stock Records
            $db->where("product_id", $productID);
            $db->where("status", 'Active');
            $getStockDetails = $db->get("stock",null, "id, product_id, serial_number, status, warehouse_id");
            $countActiveStock = $db->count;

            $db->where("product_id", $productID);
            $db->where("status", 'Sold');
            $getSoldStock = $db->get("stock",null, "id, product_id, serial_number, status, warehouse_id");
            $countSoldStock = $db->count;

            // Get Transfer Stock Count
            $db->where("product_id", $productID);
            $db->where("status", 'Transfer');
            $getTransferStock = $db->getValue("stock", "id",null);

            if($getTransferStock){
                $db->where("stock_id", $getTransferStock,"IN");
                $db->where("state", 'inProgress');
                $getPickID = $db->getValue("stock_move_line", "picking_id",null);
                $uniqueData = array_unique($getPickID);


                return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $uniqueData);

            }

            $db->where("name", "Stock");
            $db->where("active", "1");
            $getStockLocation = $db->get("stock_location",null, "id, complete_name, code");


            foreach($getStockDetails as $key => $value){
                $db->where("id", $value['warehouse_id']);
                $warehouse = $db->getValue("warehouse", "warehouse_location");

                $getStockDetails[$key]['location'] = $warehouse;
            }

            $data['transferNo'] = $newTransferValue;
            $data['productName'] = $productDetail;
            $data['stockList'] = $getStockDetails;
            $data['activeStockCount'] = $countActiveStock;
            $data['soldStockCount'] = $countSoldStock;
            $data['stockLocation'] = $getStockLocation;

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
        }

        function stockOutProcess($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $adminID        = $db->userID;
            $dateTime       = date("Y-m-d H:i:s");

            $transferNo               = $params['transferNo'];
            $toLocation               = $params['toLocation'];
            $fromLocation             = $params['fromLocation'];
            $stockTransferList        = $params['stockTransferList'];
            $trackingNo               = $params['trackingNo'];
            // $transferQuantity         = $params['transferQuantity'];
            $transferRemark           = $params['transferRemark'];
            $transferCount            = 0;
            $status                   = "Transfer";

            if(!$transferNo){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01240"][$language] /* Invalid Transfer Number */, 'data' => '');
            }

            // Get Latest Transfer No + 1 
            $db->orderBy("transfer_no", "DESC");
            $transferNo = $db->getValue("stock_transfer", "transfer_no");

            $numericPart = (int)substr($transferNo, 1); 
            $newTransferValue = "T" . str_pad($numericPart + 1, strlen($transferNo) - 1, '0', STR_PAD_LEFT); 

            // Test
            // $stockTransferList = array('GT024-001-002','GT024-001-003','GT024-001-004');

            $db->where("serial_number", $stockTransferList, "IN");
            $stockTransferListing = $db->get("stock", null, "id,serial_number,warehouse_id,status");

            if(!$stockTransferListing){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01241"][$language] /* Please insert correct serial number */, 'data' => '');
            }

            if($toLocation == $fromLocation){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01242"][$language] /* Start and End Location can not be the same */, 'data' => '');
            }

            // Check Stock from Same location
            foreach($stockTransferListing as $value){
                $transferCount++;

                $db->where("id", $value['warehouse_id']);
                $getWarehouseLct = $db->getValue("warehouse", "warehouse_location");

                $db->where("id", $fromLocation);
                $getLocationCode = $db->getValue("stock_location", "code");

                if($getWarehouseLct != $getLocationCode){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01243"][$language] /* Please select stock from given location */, 'data' => '');
                }

                if($value['warehouse_id'] == $toLocation){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01244"][$language] /* Please choose another storage location */, 'data' => '');
                }

                if($value['status'] == $status){
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations["E01245"][$language] /* Product already listed for transfer */, 'data' => '');
                }
            }

            $transferQuantity = count($stockTransferList);

            // if($transferCount != $transferQuantity){
            //     return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Please make sure all serial number is correct', 'data' => '');
            // }

            // make sure no repeated serial number input
            $serialNumbers = array();

            foreach ($stockTransferList as $item) {
                $serialNumber = $item;

                if (in_array($serialNumber, $serialNumbers)) {
                    return array('status' => 'error', 'code' => 1, 'statusMsg' => $translations['E01215'][$language], 'data' => $data);
                }

                $serialNumbers[] = $serialNumber;
            }

            $insertStockTransfer = array(
                'transfer_no'           => $newTransferValue,
                'total_quantity'        => $transferCount,
                'location_id'           => $fromLocation,
                'location_dest_id'      => $toLocation,
                'remarks'               => $transferRemark,
                'partner_id'            => $adminID,
                'created_at'            => $dateTime,
            );
            $insertSTID  = $db->insert('stock_transfer', $insertStockTransfer);

            $db->where("id", $fromLocation);
            $db->where("active", 1);
            $locationDetails = $db->getOne("stock_location");

            if($locationDetails['usage'] == 'internal'){
                $spName = $locationDetails['code'] . '/' . 'INT';
            }

            // Generate New Stock Picking Code
            $db->orderBy("id", "DESC");
            $db->where('name', "%" . $spName . "%", 'LIKE');
            $spBackValue = $db->getValue("stock_picking", "name");

            if(!$spBackValue){
                $spBackValue = $locationDetails['code'] . '/' . 'INT' . '/' . '00000';
            }

            $prefix = substr($spBackValue, 0, 7); 
            $numericPart = (int)substr($spBackValue, 7); 
            $newNumericPart = $numericPart + 1; 
            $newspBackValue = $prefix . str_pad($newNumericPart, 5, '0', STR_PAD_LEFT); 

            $insertStockPick = array(
                'name'                  => $newspBackValue,
                'origin'                => $newTransferValue,
                'scheduled_at'          => $dateTime,
                'state'                 => 'inProgress',
                'location_id'           => $fromLocation,
                'location_dest_id'      => $toLocation,
                'partner_id'            => $adminID,
                'created_at'            => $dateTime,
            );
            $stockPickID  = $db->insert('stock_picking', $insertStockPick);

            foreach($stockTransferListing as $key => $value){

                $insertMoveline = array(
                    'picking_id'            => $stockPickID,
                    'stock_id'              => $value['id'],
                    'state'                 => 'inProgress',
                    'tracking_no'           => $trackingNo,
                    'created_at'            => $dateTime,
                );
                $moveLineID  = $db->insert('stock_move_line', $insertMoveline);
            }

            foreach($stockTransferListing as $key => $value){

                $updateStockTable = array(
                    // 'warehouse_id'          => $toLocation,
                    'status'                => $status,
                    'updated_at'            => $dateTime,
                );
                $db->where("id", $value['id']);
                $db->update('stock', $updateStockTable);
            }

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => '');
        }

        function getStockTransferList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['inputData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;

            $limit          = General::getLimit($pageNumber);

            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType  = trim($v['dataType']);

                    switch ($dataName) {

                        case 'transferID':
                            $db->where('st.transfer_no', "%" . $dataValue . "%", 'LIKE');
                        break;

                        case 'state':
                            $db->where('sp.state', "%" . $dataValue . "%", 'LIKE');
                        break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            
            $db->orderBy('st.id', 'DESC');
            $db->join('stock_picking sp', 'sp.origin = st.transfer_no', 'LEFT');
            $result = $db->get('stock_transfer st', $limit, 'st.id, st.transfer_no, st.total_quantity, st.location_id, st.location_dest_id, st.remarks, st.partner_id, sp.state');

            $db->orderBy('st.id', 'DESC');
            $db->join('stock_picking sp', 'sp.origin = st.transfer_no', 'LEFT');
            $result2 = $db->get('stock_transfer st', null, 'st.id, st.transfer_no, st.total_quantity, st.location_id, st.location_dest_id, st.remarks, st.partner_id, sp.state');
            $totalRecord = $db->count;

            $data['pageNumber']       = $pageNumber;
            $data['totalRecord']      = $totalRecord;
            if($seeAll) {
                $data['totalPage']    = 1;
                $data['numRecord']    = $totalRecord;
            } else {
                $data['totalPage']    = ceil($totalRecord/$limit[1]);
                $data['numRecord']    = $limit[1];

            }
            
            
            if (!empty($result)) {
                foreach($result as $value) {
                    $stock['id']                = $value['id'];
                    $stock['transferNo']              = $value['transfer_no'];
                    $stock['quantity']     = $value['total_quantity'];
                    $stock['remark']         = $value['remarks'] ? $value['remarks'] : "-";

                    $db->where('id', $value['location_id']);
                    $fromAdrr = $db->getValue('stock_location', 'code');

                    $db->where('id', $value['location_dest_id']);
                    $toAdrr = $db->getValue('stock_location', 'code');

                    if($value['state'] == 'inProgress'){
                        $stock['state']                    = 'In Progress';
                    }else{
                        $stock['state']     = $value['state'];
                    }

                    $stock['to']                    = $toAdrr;
                    $stock['from']                  = $fromAdrr;

                    $stockList[] = $stock;
                }

                $data['stockList']      = $stockList;
                
                return array("status"=> "ok", 'code' => 0, 'statusMsg' => $db->getLastQuery(), 'data' => $data);
            }
            else {
                $data['stockList']      = '';
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Record Found", 'data' => $data);
            }
        }

        function getTransferDetail($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime               = date("Y-m-d H:i:s");

            $transferNo               = $params['transferNo'];
           
            if(!$transferNo){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Transfer Number', 'data' => '');
            }

            $db->where("transfer_no", $transferNo);
            $getTransferDetail = $db->getOne("stock_transfer");

            $db->where('id', $getTransferDetail['location_id']);
            $fromAdrr = $db->getValue('stock_location', 'code');

            $db->where('id', $getTransferDetail['location_dest_id']);
            $toAdrr = $db->getValue('stock_location', 'code');

            $db->where("origin", $transferNo);
            $getStockPicking = $db->getOne("stock_picking");

            if($getStockPicking['state'] == 'inProgress'){
                $data['state']     = 'In Progress';
            }else{
                $data['state']     = $getStockPicking['state'];
            }

            $db->where("picking_id", $getStockPicking['id']);
            $getStockPicking = $db->getValue("stock_move_line", "stock_id", null);

            $db->where('id', $getStockPicking, "IN");
            $getProductID = $db->get('stock', null, 'product_id, serial_number, status, warehouse_id');

            foreach($getProductID as $value) {
                $product['product_id']            = $value['product_id'];
                $product['serial_number']         = $value['serial_number'];
                $product['status']                = $value['status'];
                $product['warehouse_id']          = $value['warehouse_id'];

                $db->where('id', $value['product_id']);
                $productName = $db->getValue('product', 'name');

                $product['name']          = $productName;

                $productList[] = $product;
            }

            $data['to']                 = $toAdrr;
            $data['from']               = $fromAdrr;
            $data['product']            = $productList;
            $data['transferDetail']     = $getTransferDetail;

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);

        }

        function stockInProcess($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $adminID        = $db->userID;
            $dateTime       = date("Y-m-d H:i:s");

            $transferNo               = $params['transferNo'];
            $status                   = "done";

            if(!$transferNo){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Transfer Number', 'data' => '');
            }

            $db->where("origin", $transferNo);
            $db->where("state", 'inProgress');
            $stockPickID = $db->getValue("stock_picking", "id");

            $db->where("picking_id", $stockPickID);
            $db->where("state", 'inProgress');
            $stockID = $db->getValue("stock_move_line", "stock_id",null);

            $db->where("transfer_no", $transferNo);
            $stockLocation = $db->getValue("stock_transfer", "location_dest_id");

            $db->where('id', $stockLocation);
            $getStockLocation = $db->getValue("stock_location", "code");

            $db->where('warehouse_location', $getStockLocation);
            $getID = $db->getValue("warehouse", "id");

            $updateStockPick = array(
                'state'          => $status,
                'date_done'      => $dateTime,
                'updated_at'     => $dateTime,
            );
            $db->where("origin", $transferNo);
            $db->where("state", 'inProgress');
            $db->update('stock_picking', $updateStockPick);

            $updateMoveLine = array(
                'state'          => $status,
            );
            $db->where("picking_id", $stockPickID);
            $db->where("state", 'inProgress');
            $db->update('stock_move_line', $updateMoveLine);

            $updateStock = array(
                'warehouse_id'          => $getID,
                // 'status'          => 'Active',
                'status'          => 'Active',
                'stock_in_datetime'          => $dateTime,
                'updated_at'     => $dateTime,

            );
            $db->where("id", $stockID, "IN");
            $db->where("status", 'Transfer');
            $db->update('stock', $updateStock);
            

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => '');
        }

        function addStockList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime               = date("Y-m-d H:i:s");
           
            // Get Latest Transfer No + 1 
            $db->orderBy("transfer_no", "DESC");
            $transferNo = $db->getValue("stock_transfer", "transfer_no");

            $numericPart = (int)substr($transferNo, 1); 
            $newTransferValue = "T" . str_pad($numericPart + 1, strlen($transferNo) - 1, '0', STR_PAD_LEFT); 

            // Get Product Name
            $db->where("id", $productID);
            $productDetail = $db->getValue("product", "name");


            $db->where("name", "Stock");
            $db->where("active", "1");
            $getStockLocation = $db->get("stock_location",null, "id, complete_name, code");


            foreach($getStockDetails as $key => $value){
                $db->where("id", $value['warehouse_id']);
                $warehouse = $db->getValue("warehouse", "warehouse_location");

                $getStockDetails[$key]['location'] = $warehouse;
            }

            $db->orderBy("barcode", "ASC");
            $db->where("deleted", "0");
            $db->where("product_type", "product");
            $productList = $db->get("product",null, "id, name, barcode");

            $data['transferNo'] = $newTransferValue;
            $data['productList'] = $productList;
            $data['stockLocation'] = $getStockLocation;

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
        }

        function generateStockOutList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $adminID        = $db->userID;
            $dateTime       = date("Y-m-d H:i:s");

            $transferNo               = $params['transferNo'];
            $toLocation               = $params['toLocation'];
            $fromLocation             = $params['fromLocation'];
            $trackingNum              = $params['trackingNum'];
            $schDate                  = $params['schDate'];
            $transferRemark           = $params['transferRemark'];
            $orderDetailArray         = $params['orderDetailArray'];
            $transferQuantity         = 0;
            $status                   = "Transfer";

            if(!$transferNo){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Invalid Transfer Number', 'data' => '');
            }

            // Get Latest Transfer No + 1 
            $db->orderBy("transfer_no", "DESC");
            $transferNo = $db->getValue("stock_transfer", "transfer_no");

            $numericPart = (int)substr($transferNo, 1); 
            $newTransferValue = "T" . str_pad($numericPart + 1, strlen($transferNo) - 1, '0', STR_PAD_LEFT); 

            // Test
            // $stockTransferList = array('GT024-001-002','GT024-001-003','GT024-001-004');

            if($toLocation == $fromLocation){
                return array('status' => 'error', 'code' => 1, 'statusMsg' => 'Start and End Location can not be the same', 'data' => '');
            }

            // Check Stock from Same location
            $insertStockTransfer = array(
                'transfer_no'           => $newTransferValue,
                'total_quantity'        => $transferCount,
                'location_id'           => $fromLocation,
                'location_dest_id'      => $toLocation,
                'remarks'               => $transferRemark,
                'partner_id'            => $adminID,
                'trackingNo'            => $trackingNum,
                'created_at'            => $dateTime,
            );
            // $insertSTID  = $db->inserts('stock_transfer', $insertStockTransfer);

            $db->where("id", $fromLocation);
            $db->where("active", 1);
            $locationDetails = $db->getOne("stock_location");

            if($locationDetails['usage'] == 'internal'){
                $spName = $locationDetails['code'] . '/' . 'INT';
            }

            // Generate New Stock Picking Code
            $db->orderBy("id", "DESC");
            $db->where('name', "%" . $spName . "%", 'LIKE');
            $spBackValue = $db->getValue("stock_picking", "name");

            if(!$spBackValue){
                $spBackValue = $locationDetails['code'] . '/' . 'INT' . '/' . '00000';
            }

            $prefix = substr($spBackValue, 0, 7); 
            $numericPart = (int)substr($spBackValue, 7); 
            $newNumericPart = $numericPart + 1; 
            $newspBackValue = $prefix . str_pad($newNumericPart, 5, '0', STR_PAD_LEFT); 

            $insertStockPick = array(
                'name'                  => $newspBackValue,
                'origin'                => $newTransferValue,
                'scheduled_at'          => $schDate,
                'state'                 => 'Draft',
                'location_id'           => $fromLocation,
                'location_dest_id'      => $toLocation,
                'partner_id'            => $adminID,
                'created_at'            => $dateTime,
            );
            // $stockPickID  = $db->insert('stock_picking', $insertStockPick);

            foreach($orderDetailArray as $key => $value){
                $db->where('sl.id', $fromLocation);
                $db->where('s.product_id', $value['product_id']);
                $db->where('s.status', 'Active');
                $db->join('warehouse w', 's.warehouse_id = w.id', 'LEFT');
                $db->join('stock_location sl', 'sl.code = w.warehouse_location', 'LEFT');
                $barcodeList = $db->getValue("stock s", "serial_number", null);

                // $fullBarCode[$value['product_id']] = $barcodeList;
                foreach($barcodeList as $detailBarcode)
                {
                    $barcodeDetail['product'] = $value['product_id'];
                    $barcodeDetail['serial_number'] = $detailBarcode;
                    $fullBarCode[] = $barcodeDetail;
                }

                $transferQuantity += $value['quantity'];
            }

            $transformedData = [];

            foreach ($orderDetailArray as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $transformedData[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name']
                ];
            }
            }

            $data['orderDetailArray'] =  $orderDetailArray;
            $data['barCode']          =  $fullBarCode;
            $data['quantity']         =  $transferQuantity;
            $data['stockArr']         =  $transformedData;

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
        }

        function generatePOCode($input) {
            // Define the prefix
            $prefix = 'GT-PO-';

            // Extract the numeric portion from the input
            $numericPart = '';

            if (preg_match('/\d+/', $input, $matches)) {
                $numericPart = $matches[0];
            }

            // Increment the numeric part
            $numericPart = intval($numericPart) + 1;

            // Pad the numeric part with leading zeros
            $paddedNumericPart = str_pad($numericPart, 6, '0', STR_PAD_LEFT);

            // Combine the prefix and padded numeric part to create the code
            $code = $prefix . $paddedNumericPart;

            return $code;
        }

        function generatePRCode($input) {
            // Define the prefix
            $prefix = 'GT-PR-';

            // Extract the numeric portion from the input
            $numericPart = '';

            if (preg_match('/\d+/', $input, $matches)) {
                $numericPart = $matches[0];
            }

            // Increment the numeric part
            $numericPart = intval($numericPart) + 1;

            // Pad the numeric part with leading zeros
            $paddedNumericPart = str_pad($numericPart, 6, '0', STR_PAD_LEFT);

            // Combine the prefix and padded numeric part to create the code
            $code = $prefix . $paddedNumericPart;

            return $code;
        }

        function getStockMovementInOut($params) {
            $db                     = MysqliDb::getInstance();
            $language               = General::$currentLanguage;
            $translations           = General::$translations;
            $dateTime               = date("Y-m-d H:i:s");

            $product_id = $params['product_id'];
            $stockInCount = 0;
            $stockOutCount = 0;

            $db->where("product_id", $product_id);
            $stockIDArray = $db->getValue("stock", "id",null);

            if(!$stockIDArray){
                $data['stockInCount']   = 0;
                $data['stockOutCount']  = 0;

                return $data;
            }

            $db->where("stock_id", $stockIDArray, 'IN');
            $stockMVArray = $db->getValue("stock_move_line", "picking_id",null);

            if(!$stockMVArray){
                $data['stockInCount']   = 0;
                $data['stockOutCount']  = 0;

                return $data;
            }

            $db->where("id", $stockMVArray, 'IN');
            $stockPickingList = $db->get("stock_picking",null, "id, location_id, location_dest_id");

            foreach($stockPickingList as $value){
                $db->where("id", $value['location_id']);
                $stockFromLocation = $db->getValue("stock_location", "name");

                $db->where("id", $value['location_dest_id']);
                $stockToLocation = $db->getValue("stock_location", "name");

                //Warehouse to warehouse
                if($stockFromLocation == 'Stock' && $stockToLocation == 'Stock'){
                    $stockInCount++;
                    $stockOutCount++;
                //Warehouse to customer
                }else if($stockFromLocation == 'Stock' && $stockToLocation == 'Customers'){
                    $stockOutCount++;
                //Vendor to warehouse
                }else if($stockFromLocation == 'Vendors' && $stockToLocation == 'Stock'){
                    $stockInCount++;
                }
            }
            $data['stockInCount']   = $stockInCount;
            $data['stockOutCount']  = $stockOutCount;
            
            return $data;
        }

        function updatePaymentMethod($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTime       = date("Y-m-d H:i:s");

            $paymentMethod            = $params['paymentMethod'];
            $saleID                   = $params['saleID'];

            if(!$paymentMethod){
                return array("status"=> "error", 'code' => 1, 'statusMsg' => $translations['A01774'][$language], 'data' => '');
            }

            if($saleID){
                $updatePayment = array(
                    'payment_method' => $paymentMethod,
                    'updated_at'     => $dateTime,
    
                );
                $db->where("id", $saleID);
                $db->update('sale_order', $updatePayment);
            }

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => $translations['A01774'][$language], 'data' => $data);
        }

        public function getPostCodeList($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat = Setting::$systemSetting['systemDateTimeFormat'];

            $searchData     = $params['searchData'];
            $sortData       = $params['sortData'];
            $pageNumber     = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $seeAll         = $params['seeAll'];

            //Get the limit.
            $limit          = General::getLimit($pageNumber);

            if(!$seeAll){
                $limit = General::getLimit($pageNumber);
            }

            if($seeAll){
                $limit = null;
            }

            // Means the search params is there
            if (count($searchData) > 0) {
                $fromRange = 0;
                $toRange = 0;
                foreach ($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                    $dataType = trim($v['dataType']);

                    switch($dataName) {
                        case 'fromRange':
                            $fromRange = $dataValue;
                            break;
                        
                        case 'toRange':
                            $toRange = $dataValue;
                            break;
                        
                        case 'state':
                            $db->where('state', '%'.$dataValue.'%', 'LIKE');
                            break;
                        
                        case 'deliveryMethod':
                            if(!$dataValue) break;
                            $db->where('delivery_method_id', $dataValue);
                            break;
                        
                        case 'basePrice':
                            $db->where('shipping_fee', '%'.$dataValue.'%', 'LIKE');
                            break;
                        
                        case 'surcharge':
                            $db->where('surcharge', '%'.$dataValue.'%', 'LIKE');
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }

                if($fromRange && $toRange){
                    $db->where("(from_range <= ? AND to_range >= ?)", [$toRange, $fromRange]);
                } else if($fromRange && !$toRange){
                    $db->where(" ".$fromRange." BETWEEN from_range AND to_range " );
                } else if($toRange && !$fromRange){
                    $db->where(" ".$toRange." BETWEEN from_range AND to_range " );
                }
            }

            $sortOrder = "ASC";
            $sortField = 'from_range';

            // Means the sort params is there
            if (!empty($sortData)) { 
                if($sortData['field']) {
                    $sortField = $sortData['field'];
                }
                        
                if($sortData['order'] == 'DESC') {
                    $sortOrder = 'DESC';
                }
                
                if($sortData['field'] == 'delivery_method_id') {
                    if($sortData['order'] == 'DESC'){
                        $sortOrder = 'ASC';
                    }
                    else if($sortData['order'] == 'ASC'){
                        $sortOrder = 'DESC';
                    }
                }
            }

            // Sorting while switch case matched
            if ($sortField && $sortOrder) {
                $data['sortBy'] = array('field' => $sortField, 'order' => $sortOrder);
                $db->orderBy($sortField, $sortOrder);
                
                if($sortData['field'] == 'delivery_method_id') {
                    if($sortOrder == 'DESC'){
                        $sortOrder = 'ASC';
                    } else {
                        $sortOrder = 'DESC';
                    }
                    $data['sortBy'] = array('field' => $sortField, 'order' => $sortOrder);
                }
            }

            $rule = array(
                array('col' => 'disable', 'val' => '0')
            );

            foreach($rule as $v){
                $db->where($v['col'], $v['val']);
            }

            $copyDb = $db->copy();
            $results = $db->get('delivery_method_postcode', $limit, 'id, from_range, to_range, state, delivery_method_id as delivery_method, shipping_fee, surcharge, updated_at');
            $totalRecord = $copyDb->getValue ('delivery_method_postcode ', 'count(*)');

            if (!empty($results)) {
                foreach($results as $value) {
                    $postCode['id']                 = $value['id'];
                    $postCode['from_range']         = $value['from_range'];
                    $postCode['to_range']           = $value['to_range'];
                    $postCode['state']              = $value['state'];
                    $postCode['shipping_fee']       = $value['shipping_fee'];
                    $postCode['surcharge']          = $value['surcharge'];
                    $postCode['updated_at']         = $value['updated_at'];

                    if($value['delivery_method'] == 2) {
                        $postCode['delivery_method'] = "Whallo";
                    } else if($value['delivery_method'] == 3) {
                        $postCode['delivery_method'] = "Parcelhub";
                    }

                    $postCodeList[] = $postCode;
                }

                $data['postCodeList']           = $postCodeList;
                $data['pageNumber']             = $pageNumber;
                $data['totalRecord']            = $totalRecord;
                if($seeAll) {
                    $data['totalPage']    = 1;
                    $data['numRecord']    = $totalRecord;
                } else {
                    $data['totalPage']    = ceil($totalRecord/$limit[1]);
                    $data['numRecord']    = $limit[1];
                }

                return array('status' => "ok", 'code' => 0, 'statusMsg' =>"", 'data' => $data);
            }
            else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => $translations["B00101"][$language] /* No Results Found. */, 'data' => "");
            }
        }

        function getPostCodeData($params) {
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
            $dateTimeFormat  = Setting::$systemSetting['systemDateTimeFormat'];

            $postCodeID         = $params['postCodeID'];
            $type               = $params['type'];
            $surcharge          = $params['surcharge'];
            $dateTime           = date('Y-m-d H:i:s');
            
            if (!$postCodeID) return array('status' => "error", 'code' => 1, 'statusMsg' => $translations["E01298"][$language] /* Invalid Postcode */, 'data' => "");
            
            if($type == 'edit'){
                if (!preg_match('/^[0-9]+$/', $surcharge)) return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid surcharge', 'data' => "");
                $updateArr = array(
                    "surcharge"    => $surcharge,
                    "updated_at"   => $dateTime,
                );
                $db->where('id',$postCodeID);
                $db->update("delivery_method_postcode", $updateArr);
            }

            $db->where('id',$postCodeID);
            $postCodeData = $db->getOne('delivery_method_postcode');

            if($postCodeData['delivery_method_id'] == '2') {
                $postCodeData['delivery_method_id'] = 'Whallo';
            } else if($postCodeData['delivery_method_id'] == '3') {
                $postCodeData['delivery_method_id'] = 'Parcelhub';
            } 

            $data['postCodeData'] = $postCodeData;

            return array("status"=> "ok", 'code' => 0, 'statusMsg' => 'Successful', 'data' => $data);
        }

        public function getJournalLog($params) {
            
            $db             = MysqliDb::getInstance();
            $language       = General::$currentLanguage;
            $translations   = General::$translations;
        
            $module     = $params['module'];
            $module_id  = $params['module_id'];
        
            if(!$module){
                return array("status"=> "error", 'code' => 1, 'statusMsg' => $translations['A01774'][$language], 'data' => ''); //Module cannot be empty.
            }
        
            if(!$module_id){
                return array("status"=> "error", 'code' => 1, 'statusMsg' => $translations['A01774'][$language], 'data' => ''); //Module ID cannot be empty.
            }
        
            if($module == 'PO'){
                
                $db->where('purchase_order_id', $module_id);
                $pop_ids_res = $db->getValue('purchase_order_product', 'id', null);

                //handle for hard delete case
                $db->where('method', 'delete');
                $db->where('column_name', 'purchase_order_id');
                $db->where('old_value', $module_id);
                $db->groupBy('row_id');
                $pop_ids_res2 = $db->getValue('purchase_order_product_audit', 'row_id', null);

                if($pop_ids_res && $pop_ids_res2) $pop_ids_res = array_unique (array_merge ($pop_ids_res, $pop_ids_res2));
                if(!$pop_ids_res && $pop_ids_res2) $pop_ids_res = $pop_ids_res2;

                $pop_ids = implode(",",$pop_ids_res);

                $db->where('po_id', $module_id);
                $poa_ids = $db->getValue('po_assign', 'id', null);
                $poa_ids = implode(",",$poa_ids);

                $db->where('reference_id', $module_id);
                $pm_ids = $db->getValue('purchase_media', 'id', null);
                $pm_ids = implode(",",$pm_ids);

                $db->where('po_id', $module_id);
                $st_ids = $db->getValue('stock', 'id', null);
                $st_ids = implode(",",$st_ids);

                $subquery = "SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'po' as type
                            FROM purchase_order_audit
                            WHERE created_type = 'Admin'
                            AND row_id = $module_id";

                if($pop_ids){
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'pop' as type 
                                FROM purchase_order_product_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($pop_ids)";

                    $db->where('id', $pop_ids_res, 'IN');
                    $product_name_maps = $db->map('id')->get('purchase_order_product', null, 'id, product_name');
                }

                if($poa_ids){
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'poa' as type 
                                FROM po_assign_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($poa_ids)";
                }

                if($pm_ids){
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'pm' as type 
                                FROM purchase_media_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($pm_ids)";
                }

                if($st_ids){
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'st' as type 
                                FROM stock_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($st_ids)
                                AND column_name = 'status'
                                AND new_value = 'Active'
                                GROUP BY audit_id";
                }
        
                $query = "SELECT audit_log.* FROM (".$subquery.") AS audit_log ORDER BY created_at ASC, id ASC";
            }
            else if($module == 'SO'){
                if (strpos($module_id, 'S') !== false) {
                    $sale_no = $module_id;
                    // get sale id
                    $db->where('so_no',$module_id);
                    $SaleID = $db->getOne('sale_order', 'id');
                    if($SaleID)
                    {
                        $module_id = $SaleID['id'];
                    }
                }
                else
                {
                    $db->where('id', $module_id);
                    $sale_no = $db->getOne('sale_order', 'so_no');
                    $sale_no = $sale_no['so_no'];
                }

                $db->where('sale_id', $module_id);
                $pop_ids_res = $db->getValue('sale_order_detail', 'id', null);

                $db->where('so_id', $module_id);
                $ido_res = $db->getValue('inv_delivery_order', 'id', null);
                $ido_ids = implode(",",$ido_res);

                if($ido_ids)
                {
                    $db->where('inv_delivery_order_id', $ido_res, 'IN');
                    $idod_res = $db->getValue('inv_delivery_order_detail', 'id', null);
                    $idod_ids = implode(",",$idod_res);
                }

                if($sale_no)
                {
                    $db->where('so_no', $sale_no);
                    $db->where('deleted', '0');
                    $soi_res = $db->getValue('sale_order_item', 'id', null);
                    $soi_ids = implode(",",$soi_res);
                }

                //handle for hard delete case
                $db->where('method', 'delete');
                $db->where('column_name', 'sale_id');
                $db->where('old_value', $module_id);
                $db->groupBy('row_id');
                $pop_ids_res2 = $db->getValue('sale_order_detail_audit', 'row_id', null);

                if($pop_ids_res && $pop_ids_res2) $pop_ids_res = array_unique (array_merge ($pop_ids_res, $pop_ids_res2));
                if(!$pop_ids_res && $pop_ids_res2) $pop_ids_res = $pop_ids_res2;

                $pop_ids = implode(",",$pop_ids_res);

                $db->where('reference_id', $module_id);
                $pm_ids = $db->getValue('uploads', 'id', null);
                $pm_ids = implode(",",$pm_ids);

                $db->where('so_id', $module_id);
                $st_ids = $db->getValue('stock', 'id', null);
                $st_ids = implode(",",$st_ids);

                $subquery = "SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'so' as type
                FROM sale_order_audit
                WHERE created_type = 'Admin'
                AND row_id = $module_id";

                if($pop_ids){
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'sod' as type 
                                FROM sale_order_detail_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($pop_ids)";

                    $db->where('id', $pop_ids_res, 'IN');
                    $product_name_maps = $db->map('id')->get('sale_order_detail', null, 'id, item_name');
                }

                if($pm_ids){
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'ua' as type 
                                FROM uploads_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($pm_ids)";
                }

                if($st_ids){
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'st' as type 
                                FROM stock_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($st_ids)
                                AND column_name = 'status'
                                AND new_value = 'Active'
                                GROUP BY audit_id";
                }

                if($ido_ids)
                {
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'ido' as type 
                                FROM inv_delivery_order_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($ido_ids)";
                }

                if($idod_ids)
                {
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'idod' as type 
                                FROM inv_delivery_order_detail_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($idod_ids)";
                }

                if($soi_ids)
                {
                    $subquery .= " UNION
                                SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'soi' as type 
                                FROM sale_order_item_audit
                                WHERE created_type = 'Admin'
                                AND row_id IN ($soi_ids)";
                }

                $query = "SELECT audit_log.* FROM (".$subquery.") AS audit_log ORDER BY created_at ASC, id ASC";
            }
            else if($module == 'product'){
 


                $subquery = "SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'p' as type
                FROM product_audit
                WHERE created_type = 'Admin'
                AND row_id = $module_id";

                // if($pop_ids){
                //     $subquery .= " UNION
                //                 SELECT id, method, row_id, old_value, new_value, column_name, created_by, audit_id, created_at, updated_at, 'sod' as type 
                //                 FROM sale_order_detail_audit
                //                 WHERE created_type = 'Admin'
                //                 AND row_id IN ($pop_ids)";

                //     $db->where('id', $pop_ids_res, 'IN');
                //     $product_name_maps = $db->map('id')->get('sale_order_detail', null, 'id, item_name');
                // }

                $query = "SELECT audit_log.* FROM (".$subquery.") AS audit_log ORDER BY created_at ASC, id ASC";
            }
            if($query)
            {
                $result = $db->rawQuery($query);
            }

            if($result){
        
                //get mapping list
                $admin_list = $db->map('id')->get('admin', null, 'id, name');
                $product_maps = $db->map('id')->get('product', null, 'id, name');
                $vendor_maps = $db->map('id')->get('vendor', null, 'id, name');
                $warehouse_maps = $db->map('id')->get('warehouse', null, 'id, warehouse_location');
                $db->where('type', 'journalTranslate');
                $col_maps = $db->map('name')->get('enumerators', null, 'name, translation_code');
        
                //rebuild array
                foreach($result as $key => $value){
                    if($value['method'] == 'insert'){
                        if($value['column_name'] == 'id' && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales Order created on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                            $log[$value['audit_id']]['type'] = 'newSO';
                        }
                        else if($value['column_name'] == 'serial_number' && $value['type'] == 'idod')
                        {
                            $db->where('s.serial_number', $value['new_value']);
                            $db->join('product p', 'p.id = s.product_id', 'INNER');
                            $productName = $db->getOne('stock s', 'p.name');
                            $log[$value['audit_id']]['action_msg'] = 'Proceed Stock out Action on '.$value['created_at'];
                            $log[$value['audit_id']]['msg'][] = $productName['name'].'-'.'('.$value['new_value'].')';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'delivery_order_no' && $value['type'] == 'ido')
                        {
                            $log[$value['audit_id']]['action_msg'] = 'Create new Delivery Order on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'product_id' && $value['type'] == 'soi')
                        {
                            $db->where('id', $value['new_value']);
                            $productName = $db->getOne('product', 'name');
                            $log[$value['audit_id']]['action_msg'] = 'Change Sale Order Item' .' on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['msg'][] = $productName['name'];
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }

                        if (!in_array("Sales order receipt image have uploaded.", $log[$value['audit_id']]['msg']) && $value['type'] == 'pm'){
                            if(!$log[$value['audit_id']]['action_msg']) {
                                $log[$value['audit_id']]['action_msg'] = 'Sales order updated on '.$value['created_at'].'.';
                                $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                            }

                            $log[$value['audit_id']]['msg'][] = "Sales order receipt image have uploaded.";
                        }
                    }
                    else if($value['method'] == 'update'){
                        if($value['column_name'] == 'status' && $value['new_value'] == 'Pending Payment Approved' && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales order status change to pending payment approved on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'Paid' && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales order status change to paid on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'Order Processing' && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales order status change to order processing on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'Packed' && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales order status change to packed on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'Out For Delivery' && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales order status change to out for delivery in on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'Delivered' && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales order status change to delivered in on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'updated_at' && $value['type'] == 'p')
                        {
                            $log[$value['audit_id']]['action_msg'] = 'Product updated on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if(!$log[$value['audit_id']]['action_msg'] && $value['type'] == 'so'){
                            $log[$value['audit_id']]['action_msg'] = 'Sales order updated on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if(!$log[$value['audit_id']]['action_msg'] && $value['type'] == 'po'){
                            $log[$value['audit_id']]['action_msg'] = 'Purchase order updated on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'Pending for Pickup' && $value['type'] == 'ido'){
                            $log[$value['audit_id']]['action_msg'] = 'Delivery order status change to pending for pickup on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'In Transit' && $value['type'] == 'ido'){
                            $log[$value['audit_id']]['msg'][] = 'Delivery order status change'.' from '.$value['old_value'].' to '.$value['new_value'];
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }
                        else if($value['column_name'] == 'status' && $value['new_value'] == 'Delivered' && $value['type'] == 'ido'){
                            $log[$value['audit_id']]['msg'][] = 'Delivery order status change'.' from '.$value['old_value'].' to '.$value['new_value'];
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                        }

                        if($value['column_name'] == 'updated_at' 
                            || $value['column_name'] == 'id' 
                            || $value['column_name'] == 'deleted' 
                            || ($value['column_name'] == 'status' && $value['type'] != 'st')
                            || $value['column_name'] == 'approved_date'
                            || $value['column_name'] == 'approved_by'
                            || $value['column_name'] == 'response_at'
                            || $value['column_name'] == 'created_at' 
                            || $value['column_name'] == 'assignee' 
                            || $value['column_name'] == 'assigned_by' 
                            || $value['column_name'] == 'po_id' 
                            || $value['column_name'] == 'product_id' 
                            || $value['type'] == 'pm'
                            || $value['old_value'] == $value['new_value']
                            || $log[$value['audit_id']]['type'] == 'newPO'
                        ) continue;

                        //special for id mapping for name
                        if($value['column_name'] == 'warehouse_id'){
                            $log[$value['audit_id']]['msg'][] = "Warehouse change to ".$warehouse_maps[$value['new_value']];
                        }
                        else if($value['column_name'] == 'vendor_id'){
                            $log[$value['audit_id']]['msg'][] = "Vendor change to ".$vendor_maps[$value['new_value']];
                        }
                        else if($value['type'] == 'st'){

                            $db->where('audit_id', $value['audit_id']);
                            $db->where('column_name', 'status');
                            $db->where('new_value', 'Active');
                            $updated_stock = $db->getValue('stock_audit', 'row_id', null);

                            // if($updated_stock){
                            //     $db->where('id', $updated_stock, 'IN');
                            //     $db->groupBy('product_id');
                            //     $total_count = $db->get('stock', null, 'product_id, count(*) as total');

                            //     foreach($total_count as $key2 => $value2){
                            //         $log[$value['audit_id']]['msg'][] = $product_maps[$value2['product_id']]." : Received quantity increased ".$value2['total'].'.';
                            //     }
                            // }


                        }
                        else{

                            $value['column_name'] = $translations[$col_maps[$value['column_name']]][$language] ?: $value['column_name'];
                            //handle multiple product case
                            if($value['type'] == 'pop'){
                                $log[$value['audit_id']]['msg'][] = $product_name_maps[$value['row_id']].' : '.$value['column_name']. " change from ".$value['old_value']." to ".$value['new_value'];
                            }
                            else{
                                if($value['old_value']){
                                    $log[$value['audit_id']]['msg'][] = $value['column_name']. " change from ".$value['old_value']." to ".$value['new_value'];
                                }
                                else if($value['column_name'] == 'rewarded_point' && $value['type'] == 'so')
                                {
                                    $log[$value['audit_id']]['msg'][] = "Award ".$value['new_value'].' '. $value['column_name']. ' to client';
                                }
                                else if($value['column_name'] != 'updater_id' && $value['column_name'] != 'Payment Amount' && $value['column_name'] != 'do_no' && $value['column_name'] != 'serial_number'){
                                    $log[$value['audit_id']]['msg'][] = $value['column_name']. " change to ".$value['new_value'];
                                }
                            }
                        }
                    }
                    else if($value['method'] == 'delete'){
                        if($value['column_name'] == 'product_id' && $value['type'] == 'pop'){
                            if(!$log[$value['audit_id']]['action_msg']) $log[$value['audit_id']]['action_msg'] = 'Purchase order updated on '.$value['created_at'].'.';
                            $log[$value['audit_id']]['action_user'] = $admin_list[$value['created_by']];
                            $log[$value['audit_id']]['msg'][] = $product_maps[$value['old_value']]." is removed from this purchase order.";
                        }
                    }
                }                
            }

            $data['journal_list'] = $log ? array_reverse($log, true) : "";            
        
            return array("status"=> "ok", 'code' => 0, 'statusMsg' => $translations['A01774'][$language], 'data' => $data);
        }

    }
?>
