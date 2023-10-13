<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');


    $db->rawQuery("CREATE TABLE IF NOT EXISTS `stock_transfer` (
 `id` int(255) NOT NULL AUTO_INCREMENT,
 `transfer_no` varchar(255) NOT NULL DEFAULT '',
 `total_quantity` int(20) NOT NULL,
 `location_id` int(20) NOT NULL,
 `location_dest_id` int(20) NOT NULL,
 `remarks` varchar(255) DEFAULT NULL,
 `partner_id` bigint(20) NOT NULL DEFAULT 0,
 `created_at` datetime NOT NULL,
 `updated_at` datetime NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB");

    $db->rawQuery("TRUNCATE stock_transfer");

    
    //POSTGRES PRODUCTION
    $pdo = new PDO("pgsql:host=103.76.36.107;port=5432;dbname=goTasty", "kpong", 'kpong1234');//production
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");


    $defineWarehouse = array("8"=>"1", "21"=>"2", "29"=>"3", "36"=>"1", "37"=>"1", "38"=>"1", "39"=>"1", "40"=>"1", "41"=>"1");
    $defineStockLocation = array("8"=>"4", "21"=>"5", "29"=>"6", "36"=>"4", "37"=>"4", "38"=>"4", "39"=>"4", "40"=>"4", "41"=>"4");


    $internalTransferStatement = $pdo->query("select * from stock_picking where picking_type_id=5 and state='done' order by id");

    while ($row = $internalTransferStatement->fetch(PDO::FETCH_ASSOC)) {

	$picking_id = $row["id"];
	$transfer_no = $row["name"];
	$state = $row["state"];
	$scheduled_date = $row["scheduled_date"];
	$date_done = $row["date_done"];
	$user_id = $row["user_id"];
	$location_id = $row["location_id"];
	$location_dest_id = $row["location_dest_id"];

	$old_location_id = $defineStockLocation[$location_id] ?? 0;
	$new_location_id = $defineStockLocation[$location_dest_id] ?? 0;
	$new_warehouse_id = $defineWarehouse[$location_dest_id] ?? 0;

	$vendorMobileStatement = $pdo->query("select p.mobile from res_partner p inner join res_users u on (u.partner_id=p.id) where u.id='".$user_id."' ");
	$vendorMobileRow = $vendorMobileStatement->fetch(PDO::FETCH_ASSOC);
	$vendorMobile = $vendorMobileRow["mobile"] ?? "";

	$db->where("username", $vendorMobile);
	$vendorID = $db->getValue("client", "id") ?? 0;




        $db->orderBy("CAST(SUBSTRING(transfer_no, 2) AS UNSIGNED)", "DESC");
        $lastTransferRecord = $db->getOne("stock_transfer");

        if($lastTransferRecord) {
           $last_transfer_no = $lastTransferRecord["transfer_no"];
        } else {
           $last_transfer_no = "T00000";
        }

        $last_transfer_no = substr($last_transfer_no, 1);
        $next_transfer_no = "T".sprintf('%05d', ($last_transfer_no + 1));


        $transferData["transfer_no"] = $next_transfer_no;
        $transferData["total_quantity"] = 0;
        $transferData["location_id"] = $old_location_id;
        $transferData["location_dest_id"] = $new_location_id;
        $transferData["remarks"] = "Migrate from odoo";
        $transferData["partner_id"] = $vendorID;
        $transferData["created_at"] = date("Y-m-d H:i:s", strtotime($date_done));
        $transferData["updated_at"] = date("Y-m-d H:i:s", strtotime($date_done));
	$transferID = $db->insert("stock_transfer", $transferData);


        $pickingData["name"] = $transfer_no;
        $pickingData["origin"] = $next_transfer_no;
        $pickingData["state"] = "done";
        $pickingData["scheduled_at"] = date("Y-m-d H:i:s", strtotime($scheduled_date));
        $pickingData["date_done"] = date("Y-m-d H:i:s", strtotime($date_done));
        $pickingData["location_id"] = $old_location_id;
        $pickingData["location_dest_id"] = $new_location_id;
        $pickingData["partner_id"] = $vendorID;
        $pickingData["created_at"] = date("Y-m-d H:i:s", strtotime($scheduled_date));
        $pickingData["updated_at"] = date("Y-m-d H:i:s", strtotime($date_done));
        $pickID = $db->insert("stock_picking", $pickingData);


	$internalTransferDetailStatement = $pdo->query("select pl.id as lot_id, pl.name as lot_name, ml.date
							from stock_move_line ml inner join stock_production_lot pl on (pl.id=ml.lot_id)
							where ml.picking_id='".$picking_id."' and state='done'  ");

	$transferItemCount = 0;

    	while ($detailrow = $internalTransferDetailStatement->fetch(PDO::FETCH_ASSOC)) {

	    $db->where("serial_number", $detailrow["lot_name"]);
	    $db->where("status", "Active");
	    $stockID = $db->getValue("stock", "id") ?? 0;

            $mlData["picking_id"] = $pickID;
            $mlData["stock_id"] = $stockID;
            $mlData["state"] = "done";
            $mlData["created_at"] = date("Y-m-d H:i:s", strtotime($detailrow["date"]));
            $db->insert("stock_move_line", $mlData);

	    $stockData["warehouse_id"] = $new_warehouse_id;
            $stockData["updated_at"] = date("Y-m-d H:i:s", strtotime($detailrow["date"]));
	    $db->where("id", $stockID);
	    $db->update("stock", $stockData);
		
	    $transferItemCount++;
	}

	$db->where("id", $transferID);
	$db->update("stock_transfer", array("total_quantity"=>$transferItemCount) );
	//print_r($row);
 
	//echo "sdfd";exit;	

    }


    echo "\nDone...";

?>
