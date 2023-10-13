<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');

    #TRUNCATE OLD DATA

    $db->rawQuery("CREATE TABLE IF NOT EXISTS `stock_picking` (
 `id` int(255) NOT NULL AUTO_INCREMENT,
 `name` varchar(100) NOT NULL,
 `origin` varchar(100) NOT NULL,
 `state` varchar(100) NOT NULL,
 `scheduled_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `date_done` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `location_id` int(20) NOT NULL,
 `location_dest_id` int(20) NOT NULL,
 `partner_id` bigint(20) NOT NULL DEFAULT 0,
 `created_at` datetime NOT NULL,
 `updated_at` datetime NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB");

    $db->rawQuery("CREATE TABLE IF NOT EXISTS `stock_location` (
 `id` int(255) NOT NULL AUTO_INCREMENT,
 `name` varchar(100) NOT NULL DEFAULT '',
 `complete_name` varchar(100) NOT NULL DEFAULT '',
 `usage` varchar(100) NOT NULL DEFAULT '',
 `code` varchar(100) NOT NULL DEFAULT '',
 `active` tinyint(1) not null default false,
 `created_at` datetime NOT NULL,
 `updated_at` datetime NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB");

    $db->rawQuery("CREATE TABLE IF NOT EXISTS `stock_move_line` (
 `id` int(255) NOT NULL AUTO_INCREMENT,
 `picking_id` bigint(25) NOT NULL DEFAULT '0',
 `stock_id` bigint(25) NOT NULL DEFAULT '0',
 `state` varchar(100) NOT NULL default '',
 `created_at` datetime NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB");


    $db->rawQuery("TRUNCATE purchase_media");


$db->rawQuery("CREATE TABLE IF NOT EXISTS `purchase_order_product` (
    `id` BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` BIGINT(20),
    `product_id` BIGINT(20),
    `product_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci,
    `quantity` INT(11),
    `cost` FLOAT(20, 2),
    `total_cost` FLOAT(20, 2),
    `type` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci,
    `created_at` DATETIME,
    `updated_at` DATETIME
) ENGINE=InnoDB");

    $db->rawQuery("TRUNCATE purchase_order_product");

    $db->rawQuery("TRUNCATE purchase_order");
    $db->rawQuery("TRUNCATE po_assign");

    $db->rawQuery("TRUNCATE serial_number");
    $db->rawQuery("TRUNCATE stock");
    $db->rawQuery("ALTER TABLE stock ADD COLUMN remark VARCHAR(255) NOT NULL DEFAULT '' AFTER expiration_date");

    $db->rawQuery("ALTER TABLE stock ADD COLUMN cost DECIMAL(20,8) NOT NULL DEFAULT 0 AFTER variant ");

    $db->rawQuery("TRUNCATE stock_picking");
    $db->rawQuery("TRUNCATE stock_location");

    $db->rawQuery("INSERT INTO `stock_location` (`id`, `name`, `complete_name`, `usage`, `code`, `active`, `created_at`, `updated_at`) VALUES (NULL, 'Vendors', 'Partner Locations/Vendors', 'supplier', '', '1', NOW(), NOW() )");
    $db->rawQuery("INSERT INTO `stock_location` (`id`, `name`, `complete_name`, `usage`, `code`, `active`, `created_at`, `updated_at`) VALUES (NULL, 'Customers', 'Partner Locations/Customers', 'customer', '', '1', NOW(), NOW() )");
    $db->rawQuery("INSERT INTO `stock_location` (`id`, `name`, `complete_name`, `usage`, `code`, `active`, `created_at`, `updated_at`) VALUES (NULL, 'Inventory adjustment', 'Virtual Locations/Inventory adjustment', 'inventory', '', '1', NOW(), NOW() )");
    $db->rawQuery("INSERT INTO `stock_location` (`id`, `name`, `complete_name`, `usage`, `code`, `active`, `created_at`, `updated_at`) VALUES (NULL, 'Stock', 'KL/Stock', 'internal', 'KL', '1', NOW(), NOW() )");
    $db->rawQuery("INSERT INTO `stock_location` (`id`, `name`, `complete_name`, `usage`, `code`, `active`, `created_at`, `updated_at`) VALUES (NULL, 'Stock', 'PN/Stock', 'internal', 'PN', '1', NOW(), NOW() )");
    $db->rawQuery("INSERT INTO `stock_location` (`id`, `name`, `complete_name`, `usage`, `code`, `active`, `created_at`, `updated_at`) VALUES (NULL, 'Stock', 'JB/Stock', 'internal', 'JB', '1', NOW(), NOW() )");

    $db->rawQuery("TRUNCATE stock_move_line");
    $db->rawQuery("TRUNCATE stock_adjustment");

    $db->rawQuery("TRUNCATE warehouse");

    $db->rawQuery("TRUNCATE po_assign_audit");
    $db->rawQuery("TRUNCATE purchase_media_audit");
    $db->rawQuery("TRUNCATE purchase_order_audit");
    $db->rawQuery("TRUNCATE purchase_order_product_audit");
    $db->rawQuery("TRUNCATE stock_audit");


    $db->rawQuery("INSERT INTO warehouse(warehouse_location, warehouse_address, created_at, updated_at) VALUES ('KL', 'SouthGate, Jalan Chan Sow Lin', NOW(), NOW())");
    $db->rawQuery("INSERT INTO warehouse(warehouse_location, warehouse_address, created_at, updated_at) VALUES ('PN', 'Skyline City, Lintang Sungai Pinang', NOW(), NOW())");
    $db->rawQuery("INSERT INTO warehouse(warehouse_location, warehouse_address, created_at, updated_at) VALUES ('JB', 'Johor Bahru', NOW(), NOW())");

    //END TRUNCATE OLD DATA



    $defineWarehouse = array("8"=>"1", "21"=>"2", "29"=>"3", "36"=>"1", "37"=>"1", "38"=>"1", "39"=>"1", "40"=>"1", "41"=>"1");
    $defineStockLocation = array("8"=>"4", "21"=>"5", "29"=>"6", "36"=>"4", "37"=>"4", "38"=>"4", "39"=>"4", "40"=>"4", "41"=>"4");

    //POSTGRES PRODUCTION
    $pdo = new PDO("pgsql:host=103.76.36.107;port=5432;dbname=goTasty", "kpong", 'kpong1234');//production
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");

    //PR
    $purchaseOrderStatement = $pdo->query("select po.id, po.name as po_number, po.date_planned, po.date_approve, po.partner_id as vendor_partner_id, p2.name as vendor_name, p2.display_name as vendor_display_name, p2.mobile as vendor_mobile, p2.vendor_code, 
po.state, po.invoice_status, po.amount_tax, po.amount_total, 
u.partner_id as applicant_partner_id, p.name as applicant_name, p.display_name as applicant_display_name, p.mobile as applicant_mobile
from purchase_order po  inner join res_users u on (u.id=po.user_id)
inner join res_partner p on (p.id=u.partner_id) 
inner join res_partner p2 on (p2.id=po.partner_id)
where po.state='purchase' 
order by po.id asc ");

    $arrPOPID = array();
    while ($row = $purchaseOrderStatement->fetch(PDO::FETCH_ASSOC)) {

        $pickingStatement = $pdo->query("select id, name, location_dest_id from stock_picking where origin='".$row["po_number"]."' AND state<>'cancel' ");
        $pickingDetail = $pickingStatement->fetch(PDO::FETCH_ASSOC);
        $picking_id = $pickingDetail["id"];
        $picking_no = $pickingDetail["name"];
        $picking_dest_id = $pickingDetail["location_dest_id"];
        $warehouse_id = $defineWarehouse[$picking_dest_id] ?? 0;
	$stock_location = $defineStockLocation[$picking_dest_id] ?? 0;

        $poProductStatement = $pdo->query("select pt.name, pt.id as product_tmpl_id, pl.product_id, pl.product_qty, pl.qty_received, pl.price_unit, pl.price_total, pl.order_id, pp.barcode   
from purchase_order_line pl inner join product_product pp on (pl.product_id=pp.id)
inner join product_template pt on (pt.id=pp.product_tmpl_id)
where pl.order_id='".$row["id"]."' 
order by pl.product_id");

	$productIDMap = array();
	$productIDCost = array();

        $qty_received = 0;
        $arrProductName = array();
        $arrProductId = array();
        $arrPORow = array();
        while ($porow = $poProductStatement->fetch(PDO::FETCH_ASSOC)) {

            $db->where("barcode", $porow["barcode"]);
            $productID = $db->getValue("product", "id") ?? 0;

	    $productIDMap[$porow["product_id"]] = $productID;
            $porow["product_id"] = $productID;

            $arrPORow[] = $porow;
            $arrProductName[] = $porow["name"];
            $arrProductId[] = $porow["product_id"];
            $qty_received += $porow["qty_received"];
        }
        //print_r($arrProductName);
        //print_r($arrProductId);
        //print_r($pickingDetail);exit;

        $db->where("deleted", 0);
        $db->where("vendor_code", $row["vendor_code"]);
        $vendor_id = $db->getValue("vendor", "id");

        $db->where('deleted', '0');
        $db->where('vendor_id', $vendor_id);
        $vendor_address = $db->getOne('vendor_address');

        //PO
        $poData["vendor_id"] = $vendor_id;
        $poData["vendor_address"] = $vendor_address['address'];
        $poData["order_number"] = $row["po_number"];
        $poData["warehouse_id"] = $warehouse_id;
        $poData["total_quantity"] = $qty_received;
        $poData["total_cost"] = $row["amount_total"];
        $poData["remarks"] = "Migrated from oddo, poid:".$row["id"];
        $poData["status"] = "Done";
        $poData["created_by"] = $row["applicant_name"];
        $poData["approved_by"] = $row["applicant_name"];
        $poData["requested_date"] = '';
        $poData["purchase_date"] = date("Y-m-d 00:00:00", strtotime($row["date_planned"]) );
        $poData["approved_date"] = $row["date_approve"];
        $poData["created_at"] = $row["date_planned"];
        $poData["updated_at"] = $row["date_approve"];

        $poID = $db->insert("purchase_order", $poData);

        foreach($arrPORow as $kporow => $vporow) {

            $purchaseOrderData["purchase_order_id"] = $poID;
            $purchaseOrderData["product_id"] = $vporow["product_id"];
            $purchaseOrderData["product_name"] = $vporow["name"];
            $purchaseOrderData["quantity"] = $vporow["qty_received"];
            $purchaseOrderData["cost"] = $vporow["price_unit"];
            $purchaseOrderData["total_cost"] = $vporow["price_total"];
            $purchaseOrderData["type"] = "Purchase";
            $purchaseOrderData["created_at"] = $row["date_planned"];
            $purchaseOrderData["updated_at"] = $row["date_approve"];
            $popid = $db->insert("purchase_order_product", $purchaseOrderData);

	    $productIDCost[$vporow["product_id"]] = $vporow["price_unit"];

	    $arrPOPID[$popid] = $vporow["product_qty"];
        }

	//STOCK SERIAL
	$db->where("active", 1);
	$db->where("name", "Vendors");
	$location_id = $db->getValue("stock_location", "id") ?? 0;

	$pickingData["name"] = $picking_no;
        $pickingData["origin"] = $row["po_number"];
        $pickingData["state"] = "done";
        $pickingData["scheduled_at"] = $row["date_approve"];
        $pickingData["date_done"] = $row["date_approve"];
        $pickingData["location_id"] = $location_id;
        $pickingData["location_dest_id"] = $stock_location;
        $pickingData["partner_id"] = $vendor_id;
        $pickingData["created_at"] = $row["date_approve"];
        $pickingData["updated_at"] = $row["date_approve"];
        $pickID = $db->insert("stock_picking", $pickingData);



	$stockStatement = $pdo->query("select ml.product_id, ml.lot_id, ml.lot_name, ml.date 
						from stock_move m inner join stock_move_line ml on (m.id=ml.move_id)
						where m.origin='".$row["po_number"]."' and 
						ml.lot_name<>'' AND ml.state<>'cancel' and 
						m.is_done=true  
						order by ml.product_id, ml.lot_name ");

        while ($strow = $stockStatement->fetch(PDO::FETCH_ASSOC)) {

	    $productID = $productIDMap[$strow["product_id"]] ?? 0;
	    $productCost = $productIDCost[$productID]??0;

	    $serialData["purchase_order_id"] = $poID;
            $serialData["product_id"] = $productID;
            $serialData["serial_number"] = strtoupper($strow["lot_name"]);
            $serialData["created_at"] = date("Y-m-d H:i:s", strtotime($strow["date"]));
            $serialData["updated_at"] = date("Y-m-d H:i:s", strtotime($strow["date"]));
	    $db->insert("serial_number", $serialData);



	    $stockDetailStatement = $pdo->query("select id, create_date, expiration_date 
					from stock_production_lot 
					where name='".$strow["lot_name"]."' ");

	    $stdrow = $stockDetailStatement->fetch(PDO::FETCH_ASSOC);
	    $stock_in_datetime = $stdrow["create_date"];
	    $expiration_date = $stdrow["expiration_date"];
	    $lot_id = $stdrow["id"];


	    $stockData["warehouse_id"] = $warehouse_id;
            $stockData["po_id"] = $poID;
            $stockData["so_id"] = "";
            $stockData["product_id"] = $productID;
            $stockData["serial_number"] = strtoupper($strow["lot_name"]);
            $stockData["variant"] = "";
            $stockData["status"] = "Active";
            $stockData["stock_in_datetime"] = $stock_in_datetime;
            $stockData["expiration_date"] = $expiration_date;
	    $stockData["remark"] = "Migrated from oddo, lotid:".$lot_id;
            $stockData["created_at"] = date("Y-m-d H:i:s", strtotime($strow["date"]));
            $stockData["updated_at"] = date("Y-m-d H:i:s", strtotime($strow["date"]));
	    $stockData["cost"] = $productCost;
	    $stockID = $db->insert("stock", $stockData);

	   
	    $mlData["picking_id"] = $pickID;
            $mlData["stock_id"] = $stockID;
            $mlData["state"] = "done";
            $mlData["created_at"] = date("Y-m-d H:i:s", strtotime($strow["date"]));
	    $db->insert("stock_move_line", $mlData); 

        }	

    }

    //Change status to purchasing
    $resPendingPO = $db->rawQuery("SELECT po.order_number FROM purchase_order po left join stock s on (s.po_id=po.id) where po.status='done' group by po.order_number having count(distinct s.serial_number)=0");

    foreach($resPendingPO as $key => $value) {

        $db->where("order_number", $value["order_number"]);
        $db->where("status", "done");
	$poid = $db->getValue("purchase_order", "id");

	if($poid>0) {

	    $db->where("purchase_order_id", $poid);
	    $pop = $db->get("purchase_order_product");
	    $total_quantity = 0;
	    foreach($pop as $pkey => $pvalue) {
		$popQty = $arrPOPID[$pvalue["id"]]??0;
		$total_quantity+=$popQty;
		$db->where("id", $pvalue["id"]);
		$db->update("purchase_order_product", array("quantity"=>$popQty) );
	    }
	    $db->where("order_number", $value["order_number"]);
	    $db->where("status", "done");
	    $db->update("purchase_order", array("status"=>"Purchasing", "total_quantity"=>$total_quantity) );
	}
    }

    $db->rawQuery("UPDATE `purchase_order` SET `deleted` = '0'");

    echo "\nDone...";

?>
