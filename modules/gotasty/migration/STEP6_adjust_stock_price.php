<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');



    $db->where("cost", 0);
    $db->orderBy("id", "ASC");
    $stockDetail = $db->get("stock");

    foreach($stockDetail as $key => $value) {

	if($value["product_id"]>0) {
	    $db->where("id", $value["product_id"]);
	    $product_cost = $db->getValue("product", "cost");

	    $db->where("id", $value["id"]);
	    $db->update("stock", array("cost"=>$product_cost));
	    //print_r($value);
	    //exit;
	} else {
	
	    $arrSerial = explode("-", $value["serial_number"]);
	    $productCode = $arrSerial[0]."-".$arrSerial[1];
	    print_r($arrSerial);
	    print_r($value);

            $db->where("barcode", $productCode);
            $product_cost = $db->getValue("product", "cost");

	    if($product_cost>0){
		echo "\nproductCode: ".$productCode;
                $db->where("id", $value["id"]);
                $db->update("stock", array("cost"=>$product_cost));
                //exit;
	    }
	}
    }


    echo "\nDone....";


?>
