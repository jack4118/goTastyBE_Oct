<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    
    //POSTGRES PRODUCTION
    $pdo = new PDO("pgsql:host=103.76.36.107;port=5432;dbname=goTasty", "kpong", 'kpong1234');//production
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");


    $stockOnhandStatement = $pdo->query("select lot.name, q.quantity, l.complete_name, q.create_date, p.barcode
					from (select lot_id, max(id) as last_id
					from stock_quant
					group by lot_id) a inner join stock_quant q on (q.id=a.last_id)
					inner join stock_location l on (l.id=q.location_id)
					inner join stock_production_lot lot on (lot.id=q.lot_id)
					inner join product_product p on (p.id=lot.product_id)
					where l.name='Stock' and lot.name not like '%Cancel%'  
					order by lot.product_id, p.barcode asc");

    $arrOnHandSeria = array();

    while ($row = $stockOnhandStatement->fetch(PDO::FETCH_ASSOC)) {
	if(!in_array($row["name"], $arrOnHandSeria)){
	    $arrOnHandSeria[] = $row["name"];
	}
    }

    //print_r($arrOnHandSeria);exit;

    foreach($arrOnHandSeria as $serial) {

        $db->where("serial_number", $serial);
        $stockDetail = $db->getOne("stock");

        if($stockDetail) {

            if(strtolower($stockDetail["status"])=="active" ) {
                //look good
            } else {
                echo "\nOdoo active but php not | ".$serial;
            }

        } else {
            echo "\nStock not found...";
            print_r($row);
        }   

    }



    $defineWarehouseLoc = array("1"=>"4", "2"=>"5", "3"=>"6", "0"=>"4");
    $defineWarehouseLoc2 = array("1"=>"KL", "2"=>"PN", "3"=>"JB", "0"=>"KL");
    $defineLocCode = array("4"=>"KL", "5"=>"PN", "6"=>"JB", "0"=>"KL");

    $db->where("serial_number", $arrOnHandSeria, "NOT IN");
    $db->where("status", "Active");
    $db->groupBy("warehouse_id");
    $needAdjustDetail = $db->get("stock", null, "warehouse_id, count(*) as total");

    foreach($needAdjustDetail as $key => $value) {

	//print_r($value);

	$warehouse_id = $value["warehouse_id"];
	$total = $value["total"];	
	$stock_location = $defineWarehouseLoc2[$warehouse_id] ?? "KL";
	$origin_location_id = $defineWarehouseLoc[$warehouse_id] ?? "4";


        $db->where("id", $origin_location_id);
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
        $adjustData["total_quantity"] = $total;
        $adjustData["location_id"] = $origin_location_id;
        $adjustData["location_dest_id"] = $location_id;
        $adjustData["remarks"] = "manual tally stock";
        $adjustData["partner_id"] = "0";
        $adjustData["created_at"] = date("Y-m-d H:i:s");
        $adjustData["updated_at"] = date("Y-m-d H:i:s");
        $db->insert("stock_adjustment", $adjustData);



        $pickingData["name"] = $next_picking_no;
        $pickingData["origin"] = $next_adjustment_no;
        $pickingData["state"] = "done";
        $pickingData["scheduled_at"] = date("Y-m-d H:i:s");
        $pickingData["date_done"] = date("Y-m-d H:i:s");
        $pickingData["location_id"] = $origin_location_id;
        $pickingData["location_dest_id"] = $location_id;
        $pickingData["partner_id"] = "0";
        $pickingData["created_at"] = date("Y-m-d H:i:s");
        $pickingData["updated_at"] = date("Y-m-d H:i:s");
        $pickID = $db->insert("stock_picking", $pickingData);



	$db->where("warehouse_id", $warehouse_id);
        $db->where("serial_number", $arrOnHandSeria, "NOT IN");
        $db->where("status", "Active");
        $needAdjustWarehouseDetail = $db->get("stock");

        foreach($needAdjustWarehouseDetail as $nkey => $nvalue) {

	    $db->where("id", $nvalue["id"]);
	    $db->update("stock", array("status"=>"Inactive", "updated_at"=>date("Y-m-d H:i:s") ) );
	    
            $mlData["picking_id"] = $pickID;
            $mlData["stock_id"] = $nvalue["id"];
            $mlData["state"] = "done";
            $mlData["created_at"] = date("Y-m-d H:i:s");
            $db->insert("stock_move_line", $mlData);

        }

    }



    /*
    $returnStock = array('GT0136-001-002', 'GT0136-001-003', 'GT0136-001-004', 'GT0136-001-005', 'GT0136-001-006');

    foreach($returnStock as $returnSerial) {

	$db->where("serial_number", $returnSerial);
	$stockDetail = $db->getOne("stock");

	if($stockDetail) {

	    $db->where("l.stock_id", $stockDetail["id"] );
	    $db->orderBy("l.id", "DESC");
	    $db->join("stock_picking p", "p.id=l.picking_id", "INNER");
	    $lastPickingDetail = $db->getOne("stock_move_line l", "p.*");

	    if($lastPickingDetail) {
		//print_r($lastPickingDetail);

		$location_code = $defineLocCode[$lastPickingDetail["location_id"]];


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



        	$db->orderBy("CAST(SUBSTRING(adjustment_no, 2) AS UNSIGNED)", "DESC");
	        $lastAdjustmentRecord = $db->getOne("stock_adjustment");

        	if($lastAdjustmentRecord) {
	           $last_adjustment_no = $lastAdjustmentRecord["adjustment_no"];
        	} else {
	           $last_adjustment_no = "A00000";
        	}

	        $last_adjustment_no = substr($last_adjustment_no, 1);
        	$next_adjustment_no = "A".sprintf('%05d', ($last_adjustment_no + 1));


	        $pickingData["name"] = $next_picking_no;
        	$pickingData["origin"] = $next_adjustment_no;
	        $pickingData["state"] = "done";
        	$pickingData["scheduled_at"] = date("Y-m-d H:i:s");
	        $pickingData["date_done"] = date("Y-m-d H:i:s");
        	$pickingData["location_id"] = $lastPickingDetail["location_dest_id"];
	        $pickingData["location_dest_id"] = $lastPickingDetail["location_id"];
        	$pickingData["partner_id"] = $lastPickingDetail["partner_id"];
	        $pickingData["created_at"] = date("Y-m-d H:i:s");
        	$pickingData["updated_at"] = date("Y-m-d H:i:s");
	        $pickID = $db->insert("stock_picking", $pickingData);


                $mlData["picking_id"] = $pickID;
                $mlData["stock_id"] = $stockDetail["id"];
                $mlData["state"] = "done";
                $mlData["created_at"] = date("Y-m-d H:i:s");
                $db->insert("stock_move_line", $mlData);

		$db->where("id", $stockDetail["id"] );
            	$db->update("stock", array("status"=>"Active", "updated_at"=>date("Y-m-d H:i:s") ) );

		//print_r($pickingData);
		//exit;


	    }

	}

    }
    */

    echo "\n\nDone...";

?>
