<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');

    //TRUNCATE DATA
    $db->rawQuery("TRUNCATE state");
    $db->rawQuery("TRUNCATE client");
    $db->rawQuery("TRUNCATE client_audit");
    $db->rawQuery("TRUNCATE client_detail");
    $db->rawQuery("TRUNCATE client_detail_audit");
    $db->rawQuery("TRUNCATE client_setting");
    $db->rawQuery("TRUNCATE address");
    $db->rawQuery("TRUNCATE vendor");
    $db->rawQuery("TRUNCATE vendor_address");
    $db->rawQuery("TRUNCATE credit_transaction");
    $db->rawQuery("TRUNCATE mlm_Ip_Country_Code");
    $db->rawQuery("TRUNCATE new_client");
	$db->rawQuery("TRUNCATE guest_token");
	$db->rawQuery("TRUNCATE client_session");
	$db->rawQuery("TRUNCATE session_data");
	$db->rawQuery("TRUNCATE payment_gateway_details");
	$db->rawQuery("TRUNCATE vendor_media");
    $db->rawQuery("TRUNCATE product_wishlist");
    $db->rawQuery("TRUNCATE product_favorite");
    $db->rawQuery("TRUNCATE shopping_cart");

    $db->rawQuery("TRUNCATE acc_closing");
    $db->rawQuery("TRUNCATE acc_closing_batch");

    $db->rawQuery("alter table `vendor` add column `note` varchar(255) not null default '' after `deleted`");
    $db->rawQuery("alter table `vendor` add column `remark` varchar(255) not null default '' after `note`");
    $db->rawQuery("alter table client add column display_name VARCHAR(255) not null default '' after name");
    $db->rawQuery("alter table client add column remark VARCHAR(255) not null default '' after sponsor_code");

    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (1,'creditSales','creditSales','Internal','Expenses',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (2,'withdrawal','withdrawal','Internal','Suspense',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (3,'transfer','transfer','Internal','Suspense',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (4,'convert','convert','Internal','Suspense',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (5,'payout','payout','Internal','Expenses',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (6,'creditAdjustment','creditAdjustment','Internal','Earnings',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (7,'creditRefund','creditRefund','Internal','Expenses',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (8,'creditSpending','creditSpending','Internal','Earnings',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (9,'bonusPayout','bonusPayout','Internal','Expenses',NOW()); ");
    $db->rawQuery("INSERT INTO client(id, username, name, type, description, created_at) VALUES (10,'System','System','Internal','Earnings',NOW()); ");

    $db->rawQuery("INSERT INTO client(id, username, name, password, transaction_password, type, description, dial_code, phone, activated, created_at) VALUES (1000000,'director','director','','','Client','First account in the company','60','123456789',1,NOW()); ");

    $db->rawQuery("INSERT INTO address(type, client_id, name, email, phone, address, post_code, city, state_id, country_id, address_type, remarks, created_at, updated_at) VALUES (0, 0, 'Go Tasty Address', 'gotasty@gmail.com', '60182626000', 'Go Tasty Sdn. Bhd. 31-G, Jalan Damai Raya 6, Alam Damai', '56000', 'Kuala Lumpur', '4', '129', 'shipping', 'go tasty self pickup address', NOW(), NOW()) ");



    //web service
    $arrws = $db->rawQuery("SHOW TABLES LIKE 'web_services_%'");
    foreach($arrws as $key => $value) {
	foreach($value as $key2 => $value2) {
	    $drop_tbl = "DROP TABLE ".$value2.";";
	    $db->rawQuery($drop_tbl);
	    echo "\n".$drop_tbl;
	}
    }

    //acc credit
    $arrws = $db->rawQuery("SHOW TABLES LIKE 'acc_credit_%'");
    foreach($arrws as $key => $value) {
        foreach($value as $key2 => $value2) {
            $drop_tbl = "DROP TABLE ".$value2.";";
            $db->rawQuery($drop_tbl);
            echo "\n".$drop_tbl;
        }
    }

    //sent history
    $arrws = $db->rawQuery("SHOW TABLES LIKE 'sent_history_%'");
    foreach($arrws as $key => $value) {
        foreach($value as $key2 => $value2) {
            $drop_tbl = "DROP TABLE ".$value2.";";
            $db->rawQuery($drop_tbl);
            echo "\n".$drop_tbl;
        }
    }

    //activity log
    $arrws = $db->rawQuery("SHOW TABLES LIKE 'activity_log_%'");
    foreach($arrws as $key => $value) {
        foreach($value as $key2 => $value2) {
            $drop_tbl = "DROP TABLE ".$value2.";";
            $db->rawQuery($drop_tbl);
            echo "\n".$drop_tbl;
        }
    }




//    $db->rawQuery("INSERT INTO address(type, client_id, name, email, phone, address, post_code, city, state_id, country_id, address_type, remarks, created_at, updated_at) VALUES (0, 0, 'Go Tasty PN Address', 'gotasty@gmail.com', '60182626000', 'Go Tasty Sdn. Bhd. (1429649-H) 66-G, Skyline City, Lintang Sungai Pinang, 10150, Jelutong, Penang', '10150', 'Pulau Pinang', '14', '129', 'shipping', 'go tasty self pickup address', NOW(), NOW()) ");

//    $db->rawQuery("INSERT INTO address(type, client_id, name, email, phone, address, post_code, city, state_id, country_id, address_type, remarks, created_at, updated_at) VALUES (0, 0, 'Go Tasty JB Address', 'gotasty@gmail.com', '60182626000', 'Go Tasty Sdn. Bhd. (1429649-H) Johor Bahru', '55200', 'Johor Bahru', '1', '129', 'shipping', 'go tasty self pickup address', NOW(), NOW()) ");

    //END TRUNCATE DATA


    //POSTGRES PRODUCTION
    $pdo = new PDO("pgsql:host=103.76.36.107;port=5432;dbname=goTasty", "kpong", 'kpong1234');//production
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");


    $stateSatement = $pdo->query("select * from res_country_state where country_id = 157");

    $country_id = 129;//MALAYSIA
    $arrStateMap = array();
    while ($row = $stateSatement->fetch(PDO::FETCH_ASSOC)) {

	$arrMysqlState = array("country_id"=>$country_id ,"name"=>$row['name'], "translation_code"=>"", "disabled"=>0, "created_at"=>date("Y-m-d H:i:s", strtotime($row['create_date']) ) );
	
	$stateID = $db->insert("state", $arrMysqlState);
	
	$arrStateMap[$row["id"]] = $stateID;
    }
    //print_r($arrStateMap);exit;
    //exit;




    //VENDOR
    $vendorStatement = $pdo->query("SELECT p.id, p.name, p.mobile, p.email, p.country_id, p.state_id, p.street, p.street2, p.zip, p.city, c.name as country_name, 
                                s.name as state_name, p.create_date as created_at, p.type, p.parent_id, p.display_name, p.vendor_code, p.phone, p.comment 
                                FROM res_partner p FULL OUTER JOIN res_country c on p.country_id = c.id 
                                FULL OUTER JOIN res_country_state s on p.state_id = s.id 
                                where p.active = true and p.vendor_code<>'' 
                                ORDER BY p.id ASC");

    $allVendorMobile = array();
    while ($row = $vendorStatement->fetch(PDO::FETCH_ASSOC)) {

        $odooID = $row["id"];
        $name = $row["name"];
        $vendor_code = $row["vendor_code"];
        $country_id = "";
        $mobile = str_replace(array(" ","-", "+"), "", $row['mobile']);
        $email = $row["email"] ?? "";
        $pic = "";
        $created_at = date("Y-m-d H:i:s", strtotime($row['created_at']));
        $updated_at = $created_at;
        $comment = $row["comment"];
        $comment = str_replace("\\n", "<br>", strip_tags(str_replace(array("<br>","<br/>"),"\\n",$comment)) );


        $arrVendor = array("name"=>$name,
                                "vendor_code"=>$vendor_code,
                                "country_id"=>$country_id,
                                "mobile"=>$mobile,
                                "email"=>$email,
                                "pic"=>$pic,
                                "deleted"=>0,
                                "created_at"=>$created_at,
                                "updated_at"=>$updated_at,
                                "note"=>$comment,
                                "remark"=>"vid:".$odooID);


        $vendor_id = $db->insert("vendor", $arrVendor);

        $address = trim($row['street']." ". $row['street2']." ".$row['city']." ".$row['state_name']." ".$row['zip']." ".$row['country_name']);

        $arrVendorAddress = array("address"=>$address,
                                        "vendor_id"=>$vendor_id,
                                        "deleted"=>0,
                                        "created_at"=>$created_at,
                                        "updated_at"=>$updated_at );

        $db->insert("vendor_address", $arrVendorAddress);

	$allVendorMobile[] = $mobile;
    }




    //user
    $userStatement = $pdo->query("SELECT p.id as id, p.name, p.display_name, u.password, p.total_points, p.mobile, p.email, p.referral_id, p.create_date as created_at
				FROM res_partner p INNER JOIN res_users u ON (u.partner_id = p.id)
				WHERE p.active = true and  
				p.country_id=157 and p.type in ('contact','other') and p.mobile<>''
				ORDER BY p.total_points asc, u.id ASC");

    $arrUser = array();
    while ($row = $userStatement->fetch(PDO::FETCH_ASSOC)) {

	$username = str_replace(array(" ","-", "+"), "", $row['mobile']);
	$dial_code = substr($username, 0, 2);
	$phone = substr($username, 2);

	$oddoID = $row["id"];
	$name = $row["name"];
        if($row["display_name"]!="") $display_name = $row["display_name"];
        else $display_name = $row["name"];
	$password = $row["password"];
	$email = $row["email"];
	$created_at = $row["created_at"];
	$updated_at = $row["created_at"];
	$sponsor_id = 0;

	if(in_array($username, $allVendorMobile) && $username!="60182626000" ) continue;


	$arrUser[$username] = array("username"=>$username, "name"=>$name, "display_name"=>$display_name, "password"=>$password, "email"=>$email, "dial_code"=>$dial_code, "phone"=>$phone, "type"=>"Client", "country_id"=>$country_id, "activated"=>"1", "encryption_method"=>"pbkdf2_sha512", "created_at"=>$created_at, "updated_at"=>$updated_at, "sponsor_id"=>0, "remark"=>"oid:".$oddoID );

    }

    //guest
    $guestStatement = $pdo->query("SELECT p.id as id, p.name, p.display_name, p.total_points, p.mobile, p.email, p.referral_id, p.create_date as created_at, p.type
				FROM res_partner p 
				WHERE p.active = true and 
				p.country_id=157 and p.mobile<>'' and p.id not in (select partner_id from res_users) 
				ORDER BY p.id ASC");

    while ($row = $guestStatement->fetch(PDO::FETCH_ASSOC)) {

        //print_r($row);

        $username = str_replace(array(" ","-", "+"), "", $row['mobile']);
        $dial_code = substr($username, 0, 2);
        $phone = substr($username, 2);

	$oddoID = $row["id"];
        $name = $row["name"];
	if($row["display_name"]!="") $display_name = $row["display_name"];
	else $display_name = $row["name"];
        $password = "";
        $email = $row["email"];
        $created_at = $row["created_at"];
        $updated_at = $row["created_at"];
        $sponsor_id = 0;

	if(in_array($username, $allVendorMobile)) continue;

	if(!$arrUser[$username]) {
            $arrUser[$username] = array("username"=>$username, "name"=>$name, "display_name"=>$display_name, "password"=>$password, "email"=>$email, "dial_code"=>$dial_code, "phone"=>$phone, "type"=>"Guest", "country_id"=>$country_id, "activated"=>"1", "encryption_method"=>"bcrypt", "created_at"=>$created_at, "updated_at"=>$updated_at, "sponsor_id"=>0, "remark"=>"oid:".$oddoID );
	}

    }

    //print_r($arrUser);
    $db->insertMulti("client", $arrUser);
    //exit;

    //Address
    $arrAddress = array();
    $addressStatement = $pdo->query("SELECT p.id as id, p.name, p.mobile, p.email, p.birthdate, p.country_id, p.state_id, p.city, c.name as country_name, s.name as state_name, p.create_date as created_at, p.type, p.display_name, p.street, p.street2, p.zip, p.parent_id 
				FROM res_partner p INNER JOIN res_country c on (p.country_id = c.id) 
				FULL OUTER JOIN res_country_state s on p.state_id = s.id
				where p.mobile<>'' and p.country_id='157' and (p.street!='' or p.street2!='') 
				order by mobile");

    while ($row = $addressStatement->fetch(PDO::FETCH_ASSOC)) {

	$state_id = $arrStateMap[$row["state_id"]] ?? 0;

	if($row['parent_id']=="") {
	    $address_type = "billing";
	    $db->where("remark", "oid:".$row["id"]);
	} else {
	    $address_type = "delivery";
	    $db->where("remark", "oid:".$row["parent_id"]);
	}

	$client_id = $db->getValue("client", "id") ?? 0;

	if($client_id>0) {

	    $name = $row["name"];
	    $email = $row["email"] ?? "";
	    $phone = str_replace(array(" ","-", "+"), "", $row['mobile']);
	    $address = $row['street'];
	    $address2 = $row['street2'];
	    $post_code = $row['zip'] ?? "";
	    $city = $row['city'] ?? "";
	    $created_at = date("Y-m-d H:i:s", strtotime($row['created_at']));
	    $updated_at = $created_at;

	    if($address!="" && $address2=="") {
		//echo "\n>>".$address;
		$arrAddressPart = explode(",", $address);
		//print_r($arrAddressPart);
		$tmpArrAddress = array();
		foreach($arrAddressPart as $partAddress) {
		    if(trim($partAddress)!="") {
			$tmpArrAddress[] = trim($partAddress);
		    }
		}
		$address2_part = intval(count($tmpArrAddress)/2);
		$address1_part = count($tmpArrAddress)-$address2_part;
		//echo "\naddress1_part: ".$address1_part;
		//echo "\naddress2_part: ".$address2_part;

		$pint = 1;
		$tmpAddress1 = "";
		$tmpAddress2 = "";
		foreach($tmpArrAddress as $partAddress) {
		    if($pint<=$address1_part) {
			//echo "\naddress1: ".$partAddress;
			if($tmpAddress1!="") $tmpAddress1 .= ", ";
			$tmpAddress1 .= $partAddress;
		    } else {
			//echo "\naddress2: ".$partAddress;
                        if($tmpAddress2!="") $tmpAddress2 .= ", ";
                        $tmpAddress2 .= $partAddress;
		    }
		    $pint++;
                }
                //echo "\naddress1_part: ".$tmpAddress1;
                //echo "\naddress2_part: ".$tmpAddress2;
		$address = $tmpAddress1;
		$address2 = $tmpAddress2;
	    }

	    $arrAddress[] = array("type"=>0, 
				"client_id"=>$client_id, 
				"name"=>$name, 
				"email"=>$email, 
				"phone"=>$phone, 
				"address"=>$address, 
				"address_2"=>$address2, 
				"post_code"=>$post_code, 
				"city"=>$city, 
				"state_id"=>$state_id, 
				"country_id"=>"129", 
				"address_type"=>$address_type, 
				"created_at"=>$created_at, 
				"updated_at"=>$updated_at );


	}
    }
    $db->insertMulti("address", $arrAddress);
    //print_r($arrAddress);
    //echo "\n\nDone";    
    //exit;



    //User Point
    $db->where("name", "payout");
    $accountID = $db->getValue ("client", "id");

    $batchID       = $db->getNewID();

    //$db->where("username", "60138992266");
    $db->where("id", "1000001", ">=");
    $db->orderBy("id", "ASC");
    $arrClient = $db->get("client");

    foreach($arrClient as $key => $value) {

	$pointStatement = $pdo->query("select total_points from res_partner where active=true and mobile='".$value["username"]."' order by total_points desc limit 1");
	$rowPoint = $pointStatement->fetch(PDO::FETCH_ASSOC);
 	$point = $rowPoint["total_points"] ?? 0;
echo "\n".$value["username"]." => ".$point;
	if($point>0) {
	    $belongID       = $db->getNewID();
	    Cash::insertTAccount($accountID, $value["id"], 'bonusDef', $point, "Migration adjust in", $belongID, "", $db->now(), $batchID, $value["id"],"");
	}

    }

    //print_r($arrVendor);
    //print_r($arrVendorAddress);
    //exit;
    echo "\n\nDone...";
?>
