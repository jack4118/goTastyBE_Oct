<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');


    $db->rawQuery("ALTER TABLE sale_order ADD COLUMN so_no VARCHAR(255) NOT NULL DEFAULT '' AFTER id;");
    $db->rawQuery("ALTER TABLE sale_order_detail ADD COLUMN discount DECIMAL(20,8) NOT NULL DEFAULT 0 AFTER item_price");
    $db->rawQuery("ALTER TABLE sale_order_detail ADD COLUMN price_reduce DECIMAL(20,8) NOT NULL DEFAULT 0 AFTER discount");
    $db->rawQuery("ALTER TABLE sale_order_detail ADD COLUMN type VARCHAR(255) NOT NULL DEFAULT '' AFTER sale_id");
    $db->rawQuery("TRUNCATE sale_order");
    $db->rawQuery("TRUNCATE sale_order_detail");
	$db->rawQuery("TRUNCATE uploads");
	$db->rawQuery("TRUNCATE so_tracking");
	$db->rawQuery("TRUNCATE so_service");
	$db->rawQuery("TRUNCATE inv_delivery_order");
	$db->rawQuery("TRUNCATE inv_delivery_order_detail");
	$db->rawQuery("TRUNCATE sale_order_item");
	$db->rawQuery("TRUNCATE inv_delivery_order_audit");
	$db->rawQuery("TRUNCATE inv_delivery_order_detail_audit");
	$db->rawQuery("TRUNCATE sale_order_audit");
	$db->rawQuery("TRUNCATE sale_order_detail_audit");
	$db->rawQuery("TRUNCATE sale_order_item_audit");
	$db->rawQuery("TRUNCATE uploads_audit");

    
    $db->rawQuery("ALTER TABLE `sale_order_detail` CHANGE `item_name` `item_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT ''");
	$db->rawQuery("UPDATE `mlm_promo_code` SET `used_amount` = '0'");

    $db->rawQuery("ALTER TABLE sale_order ADD COLUMN billing_name varchar(255) not null default '' after receipt_id");
    $db->rawQuery("ALTER TABLE sale_order ADD COLUMN billing_phone varchar(255) not null default '' after billing_name");

    $db->rawQuery("ALTER TABLE sale_order ADD COLUMN shipping_name varchar(255) not null default '' after billing_address");
    $db->rawQuery("ALTER TABLE sale_order ADD COLUMN shipping_phone varchar(255) not null default '' after shipping_name");


    //POSTGRES PRODUCTION
    $pdo = new PDO("pgsql:host=103.76.36.107;port=5432;dbname=goTasty", "kpong", 'kpong1234');//production
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");



$arrNoteMapping = array("Delivery Charges" => "shipping_fee",
"Delivery Charges
Free Shipping" => "shipping_fee",
"Delivery Charges (Bottle Sauces)" => "shipping_fee",
"Delivery Charges (Bottle Sauces)
Free Shipping" => "shipping_fee",
"Free delivery" => "shipping_fee",
"Free delivery
Free Shipping" => "shipping_fee",
"Free delivery charges" => "shipping_fee",
"Self Pickup: Go Tasty Sdn. Bhd.
31-G, Jalan Damai Raya 6, Alam Damai,
56000 W.P. Kuala Lumpur." => "shipping_fee",
"Self Pickup: Go Tasty Sdn. Bhd.
31-G, Jalan Damai Raya 6, Alam Damai, 56000 W.P. Kuala Lumpur." => "shipping_fee",
"Self Pickup: Go Tasty Sdn. Bhd.
C-01-05, Southgate Commercial Centre,
Jalan Dua, Chan Sow Lin, 55200 Kuala Lumpur" => "shipping_fee",
"Sauces Delivery to EM" => "shipping_fee",
"免费运输
免费送货" => "shipping_fee",
"Discount: 6.6 Promo - On product with following taxes: " => "promo_code",
"Discount: ANG - On product with following taxes: " => "promo_code",
"Discount: AXTJ 5% - On product with following taxes: " => "promo_code",
"Discount: AXTJSAUCE - On product with following taxes: " => "promo_code",
"Discount: AXTJSAUCE PWP - On product with following taxes: " => "promo_code",
"Discount: DurianMan PWP 1/4 - On product with following taxes: " => "promo_code",
"Discount: DurianMan PWP 2/4 - On product with following taxes: " => "promo_code",
"Discount: DurianMan PWP 3/4 - On product with following taxes: " => "promo_code",
"Discount: DurianMan PWP 4/4 - On product with following taxes: " => "promo_code",
"Discount: First Purchase Deal - On product with following taxes: " => "promo_code",
"Discount: First Purchase Discount - On product with following taxes: ( limited to RM 30.20)" => "promo_code",
"Discount: First Purchase Discount - On product with following taxes: ( limited to RM 33.40)" => "promo_code",
"Discount: Gombak Xin Cun - On product with following taxes: " => "promo_code",
"Discount: JULYNEW - On product with following taxes: " => "promo_code",
"Discount: PWP Campaign - On product with following taxes: " => "promo_code",
"Discount: RM 10 Discount - On product with following taxes: " => "promo_code",
"Discount: Sauce ANG 3+3 RM88 - On product with following taxes: " => "promo_code",
"Discount: rm5 discount - On product with following taxes: " => "promo_code",
"FOC 2 sets of snacks. 1 set of ikan pari please change to sotong. " => "note",
"FOC CareNow Flower Tea - 1 packet " => "note",
"FOC cutleries" => "note",
"FOC ginger tea 3pkts" => "note",
"FOC popcorn" => "note",
"FOC snack" => "note",
"FOC: 2 sachets CareNow flower tea" => "note",
"FOC: 2 sachets CareNow flower tea
" => "note",
"FOC: 4 sachets CareNow flower tea
" => "note",
"FOC: Carenow flower tea X 1 sachet" => "note",
"FOC: Snacks" => "note",
"FOC: ginger tea 1 pack, snacks 1 pack." => "note",
"FOC: ginger tea X 2 packs" => "note",
"FOC: popcorn & Korea rice cracker" => "note",
"FOC: popcorn X 1" => "note",
"FOC: snack " => "note",
"FOC: snacks" => "note",
"FOC: snacks & flower tea" => "note",
"FOC： POPCORN，
FOC: snacks
Tau Sar Peah (4pcs) - heat up 1 minute in oven or air fryer (dont use microwave)" => "note",
"Free snack. " => "note",
"Gift: a set of flower tea. " => "note",
"Lawyer Ang youtube live 2 free gift" => "note",
"Reinstate Durian Mochi - missed out on last order" => "note",
"Test Note" => "note",
"change belacan crisps to 2xCareNow Sachet" => "note",
"please change 1 set of Ikan Pari Bakar to Sotong Bakar" => "note",
"please change dongchoy pork to pork lard rice & nasi lemak " => "note",
"please give a set of cutlery with pouch" => "note",
"please include a gift: cutleries set" => "note",
"please pack together with RM38 gift box & 1 pack of popcorn" => "note",
"please send free gift to Ms. Kong: a set of cutleries, NEW-rendang sauce, NEW-green curry" => "note",
"send to Mode Inn Icon City, estimate: 9pm, 26th March 2023. " => "note",
"送： 2包 * Oganick 姜茶
送：2包 *花茶（不同口味）" => "note",
"送： 2包 *Oganick 姜茶
送：2包 *花茶（不同口味）" => "note",
"[reward_ind] Reedem Point Ind" => "redeem_point");




    $arrPartnerID = array();


    $defineWarehouse = array("8"=>"1", "21"=>"2", "29"=>"3", "36"=>"1", "37"=>"1", "38"=>"1", "39"=>"1", "40"=>"1", "41"=>"1");
    $defineStockLocation = array("8"=>"4", "21"=>"5", "29"=>"6", "36"=>"4", "37"=>"4", "38"=>"4", "39"=>"4", "40"=>"4", "41"=>"4", "5"=>"2");

    //so.name in ('S06909', 'S09333', 'S09794', 'S09806', 'S09370', 'S09621')  


    $subqry = "select sl.id from sale_order so inner join sale_order_line sl on (sl.order_id=so.id) where sl.invoice_status in ('to invoice') and so.name in (select so.name from sale_order so inner join sale_order_line sl on (sl.order_id=so.id) where sl.invoice_status in ('invoiced','to invoice') group by so.name, sl.invoice_status having count(*)=2)";


    $saleOrderStatement = $pdo->query("select sl.display_type, so.id as order_id, so.name as so_number, sl.name, sl.invoice_status, sl.state, sl.price_unit, sl.price_subtotal, sl.price_total, sl.price_reduce, sl.price_reduce_taxinc, sl.price_reduce_taxexcl, 
sl.discount, sl.product_id, sl.qty_delivered_method, sl.qty_delivered, sl.qty_delivered_manual, sl.qty_invoiced, sl.untaxed_amount_invoiced, sl.order_partner_id,
sl.create_date, sl.sale_date, sl.margin, sl.margin_percent, sl.purchase_price, so.date_order, so.create_date, so.partner_id, so.partner_shipping_id, so.note, 
so.amount_untaxed, so.amount_total, so.delivery_message, so.amount_delivery, so.margin, so.margin_percent, so.code_promo_program_id, sl.id as order_line_id 
from sale_order_line sl inner join sale_order so on (sl.order_id=so.id)
where sl.state='sale' and (sl.invoice_status in ('upselling', 'invoiced') or sl.id in (".$subqry.") )  
order by so.id ");


    $arrSaleOrder = array();

    while ($row = $saleOrderStatement->fetch(PDO::FETCH_ASSOC)) {

	if(!in_array($row["partner_id"], $arrPartnerID)) $arrPartnerID[] = $row["partner_id"];
        if(!in_array($row["partner_shipping_id"], $arrPartnerID)) $arrPartnerID[] = $row["partner_shipping_id"];

        $partnerStatement = $pdo->query("select * from res_partner where id='".$row["partner_id"]."' ");
        $partnerDetail = $partnerStatement->fetch(PDO::FETCH_ASSOC);	
	$partnerUsername = str_replace(array(" ","-", "+"), "", $partnerDetail['mobile']);

	$partnerAddress = trim($partnerDetail['street']." ". $partnerDetail['street2']." ".$partnerDetail['city']." ".$partnerDetail['state_name']." ".$partnerDetail['zip']." ".$partnerDetail['country_name']);

        $partnerShippingStatement = $pdo->query("select * from res_partner where id='".$row["partner_shipping_id"]."' ");
        $partnerShippingDetail = $partnerShippingStatement->fetch(PDO::FETCH_ASSOC);    
        $partnerShippingUsername = str_replace(array(" ","-", "+"), "", $partnerShippingDetail['mobile']);

	$partnerShippingAddress = trim($partnerShippingDetail['street']." ". $partnerShippingDetail['street2']." ".$partnerShippingDetail['city']." ".$partnerShippingDetail['state_name']." ".$partnerShippingDetail['zip']." ".$partnerShippingDetail['country_name']);



	$invoiceStatement = $pdo->query("select * from account_move WHERE invoice_origin='".$row["so_number"]."' AND state='posted' AND move_type='out_invoice' ");
        $invoiceDetail = $invoiceStatement->fetch(PDO::FETCH_ASSOC);
	$invoiceNo = $invoiceDetail["name"] ?? "-";


	//picking
        $arrData = array("order_id"=>$row["order_id"],
                                "so_number"=>$row["so_number"],
				"invoice_number"=>$invoiceNo,
                                "partner_id"=>$row["partner_id"],
				"partner_username"=>$partnerUsername,
				"partner_billing_address"=>$partnerAddress,
				"partner_shipping_id"=>$row["partner_shipping_id"],
				"partner_shipping_username"=>$partnerShippingUsername,
				"partner_shipping_address"=>$partnerShippingAddress,
                                "amount_delivery"=>$row["amount_delivery"],
                                "amount_total"=>$row["amount_total"],
                                "code_promo_program_id"=>$row["code_promo_program_id"],
                                "date_order"=>$row["date_order"]);

	if($row["invoice_status"]=="to invoice") {
	    $qty_invoiced = $row["qty_delivered"];
	} else {
	    $qty_invoiced = $row["qty_invoiced"];
	}

//print_r($row);
        $arrData2 = array("name"=>$row["name"],
                                "product_id"=>$row["product_id"],
                                "qty_invoiced"=>$qty_invoiced,
                                "price_unit"=>$row["price_unit"],
                                "discount"=>$row["discount"],
                                "price_reduce"=>$row["price_reduce"],
                                "price_total"=>$row["price_total"],
                                "display_type"=>$row["display_type"],
                                "qty_delivered_method"=>$row["qty_delivered_method"]);

	if($row["product_id"]>0) {
	    $pkey = $row["product_id"];
	} else {
	    $pkey = "manual_".$row["order_line_id"];
	}

        $arrSaleOrder[$row["so_number"]]["main"] = $arrData;
        $arrSaleOrder[$row["so_number"]]["detail"][$pkey] = $arrData2;

	//print_r($row);
    }


    foreach($arrSaleOrder as $sonumber => $sodetail) {

	//picking
        $pickingStatement = $pdo->query("select id, name, scheduled_date, date_done, location_id, location_dest_id 
					from stock_picking 
					where origin='".$sonumber."' and state<>'cancel' ");
        $pickingDetail = $pickingStatement->fetch(PDO::FETCH_ASSOC);

        $arrPicking = array();
	$arrStock = array();
        if($pickingDetail) {

            $arrPicking = array("picking_id"=>$pickingDetail["id"],
                                "picking_name"=>$pickingDetail["name"],
                                "scheduled_date"=>$pickingDetail["scheduled_date"],
                                "date_done"=>$pickingDetail["date_done"],
				"location_id"=>$pickingDetail["location_id"],
				"location_dest_id"=>$pickingDetail["location_dest_id"]);


	    //stock move
	    $stockMoveStatement = $pdo->query("select * from stock_move_line where reference='".$pickingDetail["name"]."' ");

	    while ($smrow = $stockMoveStatement->fetch(PDO::FETCH_ASSOC)) {
		
		$arrStock[$smrow["product_id"]][] = array("lot_id"=>$smrow["lot_id"],
							"lot_name"=>$smrow["lot_name"],
							"state"=>$smrow["state"],
							"expiration_date"=>$smrow["expiration_date"] );

	    }
	    
        }

        $arrSaleOrder[$sonumber]["picking"] = $arrPicking;
	$arrSaleOrder[$sonumber]["stock"] = $arrStock;

    }

    echo "\n".json_encode($arrPartnerID);
    print_r($arrSaleOrder);
    //exit;

    foreach($arrSaleOrder as $saleOrderNo => $soDetails) {

	//print_r($saleOrderNo);
	print_r($soDetails);
	//exit;

	$soMain = $soDetails["main"];
	$soDetail = $soDetails["detail"];
	$soPicking = $soDetails["picking"];
	$soStock = $soDetails["stock"];

	//print_r($soPicking);//exit;

	//billing
	$db->where("username", $soMain["partner_username"]);
	$billingClientDetail = $db->getOne("client");
	$client_id = $billingClientDetail["id"] ?? 0;

	$db->where("client_id", 0, ">");
	$db->where("client_id", $client_id);
	$db->where("address_type", "billing");
	$db->orderBy("id", "DESC");
	$billing_detail = $db->getOne("address");
	if(!$billing_detail) {
	    $db->where("client_id", 0, ">");
            $db->where("client_id", $client_id);
            $db->orderBy("id", "DESC");
            $billing_detail = $db->getOne("address");
	}
        if(!$billing_detail) {
	    $billing_address = "-";
	    $billing_phone = $billingClientDetail["username"];
	    $billing_name = $billingClientDetail["display_name"];
	} else {
            $billing_address = $billing_detail["address"];
            $billing_phone = $billing_detail["phone"];
            $billing_name = $billing_detail["name"];
	}

	//shipping
        $db->where("username", $soMain["partner_shipping_username"]);
        $shippingClientDetail = $db->getOne("client");
	$shipping_client_id = $shippingClientDetail["id"] ?? 0;

	$db->where("client_id", 0, ">");
        $db->where("client_id", $shipping_client_id);
        $db->where("address_type", "shipping");
	$db->orderBy("id", "DESC");
        $shipping_detail = $db->getOne("address");
	if(!$shipping_detail) {
	    $db->where("client_id", 0, ">");
            $db->where("client_id", $shipping_client_id);
            $db->orderBy("id", "DESC");
            $shipping_detail = $db->getOne("address");
	}
        if(!$shipping_detail) {
	    $shipping_address = "-";
	    $shipping_phone = $shippingClientDetail["username"];
	    $shipping_name = $shippingClientDetail["display_name"];
	} else {
            $shipping_address = $shipping_detail["address"];
            $shipping_phone = $shipping_detail["phone"];
            $shipping_name = $shipping_detail["name"];
	}

	$rewarded_point = intval($soMain["amount_total"] - $soMain["amount_delivery"]);

	$soData["so_no"] = $soMain["so_number"];
	$soData["client_id"] = $client_id;
        $soData["payment_amount"] = $soMain["amount_total"];
        //$soData["discount_amount"] = "";
        $soData["shipping_fee"] = $soMain["amount_delivery"];
        $soData["release_amount"] = $soMain["amount_total"];
        $soData["payment_method"] = "odoo";
        $soData["payment_expired_date"] = $soMain["date_order"];
        $soData["status"] = "Delivered";
        $soData["invoice_id"] = $soMain["invoice_number"];
        $soData["invoice_date"] = $soMain["date_order"];
        //$soData["invoice"] = "";
        $soData["credit_release_at"] = $soMain["date_order"];
        $soData["released"] = "1";
        $soData["request_verify_at"] = $soMain["date_order"];
        $soData["verified"] = "1";
        $soData["payment_verified_at"] = $soMain["date_order"];
	$soData["billing_name"] = $billing_name;
	$soData["billing_phone"] = $billing_phone;
        $soData["billing_address"] = $billing_address;
        $soData["shipping_name"] = $shipping_name;
        $soData["shipping_phone"] = $shipping_phone;
        $soData["shipping_address"] = $shipping_address;
        $soData["updated_at"] = $soMain["date_order"];
        $soData["created_at"] = $soMain["date_order"];
	$soData["rewarded_point"] = $rewarded_point;
	$soID = $db->insert("sale_order", $soData);


        $old_location_id = $defineStockLocation[$soPicking["location_id"]] ?? 0;
        $new_location_id = $defineStockLocation[$soPicking["location_dest_id"]] ?? 0;

        $pickingData["name"] = $soPicking["picking_name"];
        $pickingData["origin"] = $soMain["so_number"];
        $pickingData["state"] = "done";
        $pickingData["scheduled_at"] = date("Y-m-d H:i:s", strtotime($soPicking["scheduled_date"]));
        $pickingData["date_done"] = date("Y-m-d H:i:s", strtotime($soPicking["date_done"]));
        $pickingData["location_id"] = $old_location_id;
        $pickingData["location_dest_id"] = $new_location_id;
        $pickingData["partner_id"] = $client_id;
        $pickingData["created_at"] = date("Y-m-d H:i:s", strtotime($soPicking["scheduled_date"]));
        $pickingData["updated_at"] = date("Y-m-d H:i:s", strtotime($soPicking["scheduled_date"]));
        $pickID = $db->insert("stock_picking", $pickingData);

	// get so_no
	$db->where('id', $soID);
	$soNo = $db->getOne('sale_order');
	$soNo = $soNo['so_no'];

	// $db->where('so_id', $soID);
	$stockList = $db->get('stock');

	$totalDiscount = 0;
	$deliveryMethod = "Delivery";
	foreach($soDetail as $sodKey => $sodValue) {

	    if($sodValue["qty_delivered_method"]=="manual") {

		$type = $arrNoteMapping[$sodValue["name"]] ?? "note";
		//$type = "note";

		$productID = 0;
		$productTmplID = 0;

		if($sodValue["price_total"]<0) {
		    $totalDiscount += abs($sodValue["price_total"]);
		}

		if($sodValue["product_id"]=="286") {
		    $deliveryMethod = "Pickup";
		}

	    } else {
		$type = "product";
	 
	    	$productStatement = $pdo->query("select * from product_product where id='".$sodValue["product_id"]."' ");
	        $productDetail = $productStatement->fetch(PDO::FETCH_ASSOC);
        	$productBarcode = $productDetail["barcode"] ?? "";
		$productTmplID = $productDetail["product_tmpl_id"] ?? "";

	    	$db->where("barcode", $productBarcode);
	    	$productID = $db->getValue("product", "id") ?? 0;

		if($productID==0 && $productTmplID!="") {
		    $englishProductName = $pdo->query("select * from ir_translation where name = 'product.template,name' AND type = 'model' and lang='en_US' and res_id='".$productTmplID."' ");
		    $englishProductNameDetail = $englishProductName->fetch(PDO::FETCH_ASSOC);
        	    $englishName = $englishProductNameDetail["value"] ?? "";

		    if($englishName!="") {
		        $db->where("name", $englishName);
		        $productID = $db->getValue("product", "id") ?? 0;
		    }
		}

            	$db->where("product_id", $productID);
            	$productTmplID = $db->getValue("product_template", "id") ?? 0;
	    }

	    if($sodValue["discount"]>0) {
		$itemPriceVal = abs($sodValue["price_reduce"]);
		$discountVal = 0;
	    } else {
		$itemPriceVal = abs($sodValue["price_unit"]);
		$discountVal = 0;
	    }


	    $sodData["client_id"] = $client_id;
            $sodData["product_id"] = $productID;
            $sodData["product_template_id"] = $productTmplID;
            $sodData["item_name"] = $sodValue["name"];
            $sodData["item_price"] = $itemPriceVal;
	    $sodData["discount"] = $discountVal;
            $sodData["price_reduce"] = abs($sodValue["price_reduce"]);
            $sodData["quantity"] = $sodValue["qty_invoiced"];
            $sodData["subtotal"] = abs($sodValue["price_total"]);
            $sodData["sale_id"] = $soID;
	    $sodData["type"] = $type;
            $sodData["created_at"] = $soMain["date_order"];
            $sodData["updated_at"] = $soMain["date_order"];
	    $soDetailID = $db->insert("sale_order_detail", $sodData); 

		// # insert into sale_order_item table
		// $db->where('id', $productID);
		// $productType = $db->getOne('product', 'product_type');
		// $selectedSerialNumbers = array();
		// for($loopQuantity = 0; $loopQuantity < $sodValue["qty_invoiced"] ; $loopQuantity++)
		// {
		// 	if(strtolower($productType['product_type']) == 'product')
		// 	{
		// 		$selectedSerialNumber = null;
		// 		foreach($stockList as $key2 => $detailStock)
		// 		{
		// 			if($detailStock['product_id'] == $productID)
		// 			{
		// 				if (!in_array($detailStock['serial_number'], $selectedSerialNumbers)) {
		// 					$selectedSerialNumber = $detailStock['serial_number'];
		// 					$selectedSerialNumbers[] = $selectedSerialNumber; 
		// 					unset($stockList[$key2]);
		// 					break; 
		// 				}
		// 			}
		// 		}
		// 		if($selectedSerialNumber)
		// 		{
		// 			$insertData = array(
		// 				'so_no'    			=> $soNo,
		// 				'do_no'	   			=> '',
		// 				'so_details_id'		=> $soDetailID,
		// 				'product_id'		=> $productID,
		// 				'package_id'		=> '0',
		// 				'is_package'		=> '0',
		// 				'serial_number'		=> $selectedSerialNumber,
		// 				'remark'			=> 'Migrated From Odoo',
		// 				'deleted' 			=> '0',
		// 			);
		// 			$db->insert('sale_order_item', $insertData);
		// 		}
		// 	}
		// 	else if(strtolower($productType['product_type']) == 'package')
		// 	{
		// 		$db->where('package_id', $productID);
		// 		$db->where('deleted', '0');
		// 		$packageItemDetail = $db->get('package_item');
	
		// 		foreach($packageItemDetail as $insertPackageProduct)
		// 		{
		// 			$selectedSerialNumber = null;
		// 			foreach($stockList as $key2 => $detailStock)
		// 			{
		// 				if ($detailStock['product_id'] == $insertPackageProduct['product_id']) {
		// 					if (!in_array($detailStock['serial_number'], $selectedSerialNumbers)) {
		// 						$selectedSerialNumber = $detailStock['serial_number'];
		// 						$selectedSerialNumbers[] = $selectedSerialNumber; 
		// 						unset($stockList[$key2]);
		// 						break; 
		// 					}
		// 				}
		// 			}
		// 			if($selectedSerialNumber)
		// 			{
		// 				$insertData = array(
		// 					'so_no'         => $soNo,
		// 					'do_no'	        => '',
		// 					'so_details_id' => $soDetailID,
		// 					'product_id'    => $insertPackageProduct['product_id'],
		// 					'package_id'    => $productID,
		// 					'is_package'    => '1',
		// 					'serial_number'		=> $selectedSerialNumber,
		// 					'remark'			=> 'Migrated From Odoo',
		// 					'deleted'       => '0',
		// 				);
		// 				$db->insert('sale_order_item', $insertData);
		// 			}
		// 		}
		// 	}
		// }

	    if($sodValue["discount"]>0){
	        $oriPrice = bcmul($sodValue["price_unit"], $sodValue["qty_invoiced"], 2);
	        $discountPrice = bcsub($oriPrice, $sodValue["price_total"], 2);

	        $totalDiscount += $discountPrice;
	    }


	    //print_r($sodValue);
	    //print_r($sodData);
	    //exit;
	}

	if($deliveryMethod=="Pickup") {
	    //$db->where("origin", $soMain["so_number"]);
	    //$deliveryLocId = $db->getValue("stock_picking", "location_id") ?? 0;
	
	    //$db->where("id", $deliveryLocId);
	    //$db->where("active", 1);
	    //$deliveryLocCode = $db->getValue("stock_location", "code");

	    $db->where("name", "Go Tasty Address");
	    $db->where("client_id", 0);
	    $deliveryAddress = $db->getValue("address", "address") ?? $shipping_address;

	    $shipping_name = "";
	    $shipping_phone = "";
	} else {
	    $deliveryAddress = $shipping_address;
	}

	$db->where("id", $soID);
	$db->update("sale_order", array("discount_amount"=>$totalDiscount, "delivery_method"=>$deliveryMethod, "shipping_address"=>$deliveryAddress, "shipping_name"=>$shipping_name, "shipping_phone"=>$shipping_phone ) );


	foreach($soStock as $productID => $stockDetail) {

	    foreach($stockDetail as $sdkey => $sdDetail) {

	        $db->where("serial_number", $sdDetail["lot_name"]);
  	        $stockID = $db->getValue("stock", "id") ?? 0;

                $mlData["picking_id"] = $pickID;
                $mlData["stock_id"] = $stockID;
                $mlData["state"] = "done";
                $mlData["created_at"] = date("Y-m-d H:i:s", strtotime($soPicking["date_done"]));
                $db->insert("stock_move_line", $mlData);

		$db->where("id", $stockID);
		$db->update("stock", array("status"=>"Sold", "so_id"=>$soID, "updated_at"=>date("Y-m-d H:i:s", strtotime($soPicking["date_done"])) ));

	    }

	}

	//print_r($soData);
	//exit;
    }

	# insert into sale_order_item table
	$db->where("so_id", 0, ">");
	$stockList = $db->get('stock');

	foreach($stockList as $detailStockList)
	{
		$db->where('id', $detailStockList['so_id']);
		$saleNO = $db->getOne('sale_order');

		$db->where('id', $detailStockList['product_id']);
		$productDetail = $db->getOne('product');
		if($productDetail['product_type'] == 'package')
		{
			$isPackage = 1;
			$packageID = $productDetail['id'];
		}
		else
		{
			$isPackage = 0;
			$packageID = 0;
		}

		$insertData = array(
			'so_no'    			=> $saleNO['so_no'],
			'do_no'	   			=> '',
			'so_details_id'		=> '',
			'product_id'		=> $detailStockList['product_id'],
			'package_id'		=> $packageID,
			'is_package'		=> $isPackage,
			'serial_number'		=> $detailStockList['serial_number'],
			'remark'			=> 'Migrated From Odoo',
			'deleted' 			=> '0',
			'updated_at'		=> date("Y-m-d H:i:s"),
		);
		$db->insert('sale_order_item', $insertData);
	}
    echo "\nDone....";

?>
