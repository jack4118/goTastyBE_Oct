<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');


    $db->rawQuery("CREATE TABLE IF NOT EXISTS `stock_adjustment` (
 `id` int(255) NOT NULL AUTO_INCREMENT,
 `adjustment_no` varchar(255) NOT NULL DEFAULT '',
 `total_quantity` int(20) NOT NULL,
 `location_id` int(20) NOT NULL,
 `location_dest_id` int(20) NOT NULL,
 `remarks` varchar(255) DEFAULT NULL,
 `partner_id` bigint(20) NOT NULL DEFAULT 0,
 `created_at` datetime NOT NULL,
 `updated_at` datetime NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB");

   
    $defineWarehouse = array("8"=>"1", "21"=>"2", "29"=>"3", "36"=>"1", "37"=>"1", "38"=>"1", "39"=>"1", "40"=>"1", "41"=>"1");
    $defineStockLocation = array("8"=>"4", "21"=>"5", "29"=>"6", "36"=>"4", "37"=>"4", "38"=>"4", "39"=>"4", "40"=>"4", "41"=>"4");

    //POSTGRES PRODUCTION
    $pdo = new PDO("pgsql:host=103.76.36.107;port=5432;dbname=goTasty", "kpong", 'kpong1234');//production
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");



    $serialStatement = $pdo->query("SELECT DISTINCT name FROM stock_production_lot WHERE name<>'' AND name not like '%O%' AND name not like '%N%' ");


    $arrOddoSerial = array();
    while ($row = $serialStatement->fetch(PDO::FETCH_ASSOC)) {

        $oqr = trim($row["name"]);

        if( strpos(strtolower($oqr), "cancel") !== false ){
        } else {
            if(!in_array(strtolower($oqr, $arrOddoSerial)) ){
                $arrOddoSerial[] = $oqr;
            }
        }
    }

    $db->where("remark LIKE 'Migrated%' ");
    $mysqlSerialDetail = $db->get("stock", null, "DISTINCT serial_number");

    $arrMysqlSerial = array();
    foreach($mysqlSerialDetail as $key => $value) {
        $arrMysqlSerial[] = $value["serial_number"];
    }

    $diffSerial2=array_diff($arrOddoSerial, $arrMysqlSerial);
    //print_r($diffSerial2);

    if(count($diffSerial2)>0) {

	foreach($diffSerial2 as $serial) {

	    //print_r($serial);//exit;

	    $serial2Statement = $pdo->query("select id, product_id, lot_id, location_id, location_dest_id, reference 
					from stock_move_line where lot_id in (select id from stock_production_lot 
					where name = '".$serial."')
					order by id limit 1");

	    $serialrow = $serial2Statement->fetch(PDO::FETCH_ASSOC);
	    
	    if($serialrow) {
		$product_id = $serialrow["product_id"];
		$lot_id = $serialrow["lot_id"];
		$reference = $serialrow["reference"];
		$location_id = $serialrow["location_id"];
		$location_dest_id = $serialrow["location_dest_id"];

		if( strpos($reference, "/OUT/") !== false ){
		    $warehouse_id = $defineWarehouse[$location_id] ?? 0;
		    $stock_location = $defineStockLocation[$location_id] ?? 0;
		} else {
		    $warehouse_id = $defineWarehouse[$location_dest_id] ?? 0;
		    $stock_location = $defineStockLocation[$location_dest_id] ?? 0;
		}
	  
                $stockDetailStatement = $pdo->query("select id, create_date, expiration_date 
                                        from stock_production_lot 
                                        where name='".$serial."' ");

                $stdrow = $stockDetailStatement->fetch(PDO::FETCH_ASSOC);
                $stock_in_datetime = $stdrow["create_date"];
                $expiration_date = $stdrow["expiration_date"];

 
		$productStatement = $pdo->query("SELECT * from product_product where id='".$product_id."' ");
		$productrow = $productStatement->fetch(PDO::FETCH_ASSOC);
		$barcode = $productrow["barcode"];

		$db->where("barcode", $barcode);
		$productRec = $db->getOne("product");
		$productID = $productRec["id"];
		$vendor_id = $productRec["vendor_id"];

		//print_r($serialrow);
		//print_r($productrow);


		//echo "\nproductID: ".$productID;
		//exit;

                $stockData["warehouse_id"] = $warehouse_id;
                $stockData["po_id"] = "";
                $stockData["so_id"] = "";
                $stockData["product_id"] = $productID;
                $stockData["serial_number"] = $serial;
                $stockData["variant"] = "";
                $stockData["status"] = "Active";
                $stockData["stock_in_datetime"] = $stock_in_datetime;
                $stockData["expiration_date"] = $expiration_date;
                $stockData["remark"] = "Migrated from oddom no PO, lotid:".$lot_id;
                $stockData["created_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
                $stockData["updated_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
		$stockID = $db->insert("stock", $stockData);		
		//print_r($stockData);//exit;

                $serialData["purchase_order_id"] = 0;
                $serialData["product_id"] = $productID;
                $serialData["serial_number"] = $serial;
                $serialData["created_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
                $serialData["updated_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
                $db->insert("serial_number", $serialData);
                //print_r($serialData);//exit;

		$db->where("id", $stock_location);
		$location_code = $db->getValue("stock_location", "code");

        	$db->where("active", 1);
	        $db->where("name", "Inventory adjustment");
        	$location_id = $db->getValue("stock_location", "id") ?? 0;

		$db->where("name LIKE '".$location_code."/ADJ/%'");
		//$db->where("location_id", $location_id);
		$db->orderBy("CAST(SUBSTRING(name, 8) AS UNSIGNED)", "DESC");
		$lastPickRecord = $db->getOne("stock_picking");

		if($lastPickRecord) {
		   $last_picking_no = $lastPickRecord["name"]; 
		} else {
		   $last_picking_no = $location_code."/ADJ/00000";
		}

		$arrPickingItem = explode("/", $last_picking_no);
		$last_running_no = $arrPickingItem[count($arrPickingItem)-1];
		$next_running_no = sprintf('%05d', ($last_running_no + 1));		
		$next_picking_no = $location_code."/ADJ/".$next_running_no;
		//echo "\nnext picking_no: ".$next_picking_no;


                $db->orderBy("CAST(SUBSTRING(adjustment_no, 2) AS UNSIGNED)", "DESC");
                $lastAdjustmentRecord = $db->getOne("stock_adjustment");

                if($lastAdjustmentRecord) {
                   $last_adjustment_no = $lastAdjustmentRecord["adjustment_no"];
                } else {
                   $last_adjustment_no = "A00000";
                }

                $last_adjustment_no = substr($last_adjustment_no, 1);
                $next_adjustment_no = "A".sprintf('%05d', ($last_adjustment_no + 1));


		$adjustData["adjustment_no"] = $next_adjustment_no;
                $adjustData["total_quantity"] = 1;
                $adjustData["location_id"] = $location_id;
                $adjustData["location_dest_id"] = $stock_location;
                $adjustData["remarks"] = "odoo manual adjust";
                $adjustData["partner_id"] = $vendor_id;
                $adjustData["created_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
                $adjustData["updated_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
		$db->insert("stock_adjustment", $adjustData);
		



        	$pickingData["name"] = $next_picking_no;
	        $pickingData["origin"] = $next_adjustment_no;
	        $pickingData["state"] = "done";
	        $pickingData["scheduled_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
	        $pickingData["date_done"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
	        $pickingData["location_id"] = $location_id;
	        $pickingData["location_dest_id"] = $stock_location;
	        $pickingData["partner_id"] = $vendor_id;
	        $pickingData["created_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
	        $pickingData["updated_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
	        $pickID = $db->insert("stock_picking", $pickingData);



                $mlData["picking_id"] = $pickID;
                $mlData["stock_id"] = $stockID;
                $mlData["state"] = "done";
                $mlData["created_at"] = date("Y-m-d H:i:s", strtotime($stock_in_datetime));
                $db->insert("stock_move_line", $mlData);


	    }

	}
    }


/*
    $db->where("(origin='' or name='' )");
    $arrEmptyPick = $db->get("stock_picking");

    print_r($arrEmptyPick);

    foreach($arrEmptyPick as $key => $value) {

	$db->where("order_number", $value["origin"]);
	$poID = $db->getValue("purchase_order", "id") ?? 0;

	if($poID>0) {

	    $db->where("id", $poID);
	    $db->delete("purchase_order");
	}

	$db->where("id", $value["id"]);
	$db->delete("stock_picking");
    }
*/

    //
    $db->where("barcode", "GT038-001");
    $productID = $db->getValue("product", "id");
    $db->where("serial_number LIKE 'GT038-001%' ");
    $db->where("product_id", "0");
    $db->update("stock", array("product_id"=>$productID) );

    $db->where("barcode", "GT038-002N");
    $productID = $db->getValue("product", "id");
    $db->where("serial_number LIKE 'GT038-002%' ");
    $db->where("product_id", "0");
    $db->update("stock", array("product_id"=>$productID) );


    $arrProductMap = array("Restauran Kee Heong Bak Kut Teh - Pork Belly (Dry)"=>"GT038-001",
				"Restauran Kee Heong Bak Kut Teh - Pork Belly (Soup)"=>"GT038-002N");


    echo "\nDone...";

?>
