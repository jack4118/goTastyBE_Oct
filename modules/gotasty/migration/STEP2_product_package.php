<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');

    //TRUNCATE DATA
    $db->rawQuery("TRUNCATE product_category");
    $db->rawQuery("TRUNCATE product_attribute");
    $db->rawQuery("TRUNCATE product_attribute_value");
    $db->rawQuery("TRUNCATE product");
    $db->rawQuery("TRUNCATE product_media");
    $db->rawQuery("TRUNCATE product_template");
    $db->rawQuery("TRUNCATE package_item");
    $db->rawQuery("TRUNCATE inv_language");
    $db->rawQuery("TRUNCATE product_wishlist");
    $db->rawQuery("TRUNCATE product_favorite");
    $db->rawQuery("TRUNCATE cooking_suggestion_details");
    $db->rawQuery("TRUNCATE delivery_method_detail");
    $db->rawQuery("TRUNCATE mlm_promo_code");
    $db->rawQuery("TRUNCATE promo_code_detail");
    $db->rawQuery("TRUNCATE promo_code_product");


    $db->rawQuery("ALTER TABLE product ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER full_instruction2");
    $db->rawQuery("ALTER TABLE product ADD COLUMN is_archive TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published");
    $db->rawQuery("ALTER TABLE product ADD COLUMN note varchar(255) not null default '' after description");

    $db->rawQuery("ALTER TABLE product_media add column name varchar(255) not null after type");

    //END TRUNCATE DATA

    # insert in to delivery_method_detail table
    $insertData = array(
        'delivery_method_id' => '2',
        'product_id' => '',
        'quantity' => '0',
        'amount' => '280',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);

    # insert in to delivery_method_detail table
    $insertData = array(
        'delivery_method_id' => '3',
        'product_id' => '',
        'quantity' => '0',
        'amount' => '280',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);

    //POSTGRES PRODUCTION
    $pdo = new PDO("pgsql:host=103.76.36.107;port=5432;dbname=goTasty", "kpong", 'kpong1234');//production
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'UTF8'");



    //CATEGORY
    $categoryStatement = $pdo->query("SELECT id, name, parent_path, create_date FROM product_public_category ORDER BY id ASC");

    $categoryMap = array();
    $arrPdoCategory = array();
    while ($row = $categoryStatement->fetch(PDO::FETCH_ASSOC)) {
	$parent_path = substr($row['parent_path'], 0, strlen($row['parent_path'])-1 );
	$arr_parent_path = explode("/", $parent_path);

	if(count($arr_parent_path)>1) {
	    $parent_id = $arr_parent_path[count($arr_parent_path)-2];
	} else {
	    $parent_id = 0;
	}

	$row['parent_id'] = $parent_id;
	$arrPdoCategory[] = $row;
    }

    foreach($arrPdoCategory as $key => $value) {
	$name = $value["name"];
	$created_at = date("Y-m-d H:i:s", strtotime($value["create_date"]));
	$updated_at = $created_at;

	$arrCategory = array("name"=>$name, "deleted"=>0, "created_at"=>$created_at, "updated_at"=>$updated_at);
	$catID = $db->insert("product_category", $arrCategory);

        $englishCatName = $pdo->query("select * from ir_translation where name = 'product.public.category,name' AND type = 'model' and lang='en_US' and res_id='".$value["id"]."' ");
        $englishCatDetail = $englishCatName->fetch(PDO::FETCH_ASSOC);
        $englishCat = $englishCatDetail["value"]==""?$name:$englishCatDetail["value"];

        $arrInvCategory = array("module"=>"category", "module_id"=>$catID, "type"=>"name", "language"=>"english", "content"=>$englishCat, "updated_at"=>$updated_at );
        $db->insert("inv_language", $arrInvCategory);


	$chineseCatName = $pdo->query("select * from ir_translation where name = 'product.public.category,name' AND type = 'model' and lang='zh_CN' and res_id='".$value["id"]."' ");
        $chineseCatDetail = $chineseCatName->fetch(PDO::FETCH_ASSOC);
        $chineseCat = $chineseCatDetail["value"]==""?$englishCat:$chineseCatDetail["value"];

	$arrInvCategory = array("module"=>"category", "module_id"=>$catID, "type"=>"name", "language"=>"chinese", "content"=>$chineseCat, "updated_at"=>$updated_at );
	$db->insert("inv_language", $arrInvCategory);

	$categoryMap[$value["id"]] = $catID;

    }

    foreach($arrPdoCategory as $key => $value) {

	if($value["parent_id"]>0) {

	    $cat_main_map = $categoryMap[$value["id"]];
	    $cat_parent_map = $categoryMap[$value["parent_id"]];
	
	    $db->where("id", $cat_main_map);
	    $db->update("product_category", array("parent_id"=>$cat_parent_map) );
	}

    }
    //print_r($arrPdoCategory);
    //print_r($categoryMap);
    //exit;

    $productAttributeStatement = $pdo->query("SELECT * FROM product_attribute");

    $paMap = array();

    while ($row = $productAttributeStatement->fetch(PDO::FETCH_ASSOC)) {

	$created_at = $row['create_date'] == "" ?  date('Y-m-d h:i:s', time()) : $row['create_date'];
        $updated_at =  date('Y-m-d h:i:s', time());

	$paData["name"] = $row['name'];
        $paData["deleted"] = "0";
        $paData["created_at"] = $created_at;
        $paData["updated_at"] = $updated_at;
	$paID = $db->insert("product_attribute", $paData);
	
	$paMap[$row["id"]] = $paID;
    }

    //print_r($paMap);

    $productAttributeValueStatement = $pdo->query("SELECT * FROM product_attribute_value");

    while ($row = $productAttributeValueStatement->fetch(PDO::FETCH_ASSOC)) {

	$product_attribute_id = $paMap[$row["attribute_id"]] ?? 0;
        $created_at = $row['create_date'] == "" ?  date('Y-m-d h:i:s', time()) : $row['create_date'];
        $updated_at =  date('Y-m-d h:i:s', time());

	$pavData["name"] = $row['name'];
        $pavData["product_attribute_id"] = $product_attribute_id;
        $pavData["deleted"] = "0";
        $pavData["created_at"] = $created_at;
        $pavData["updated_at"] = $updated_at;
	$db->insert("product_attribute_value", $pavData);

    }

    //PRODUCT
    $productStatement = $pdo->query("SELECT p.id as product_id, t.value as product_name, m.name as original_name,r.name as vendor_name, m.video,m.video_2 ,p.barcode, r.vendor_code, m.description, m.description_sale, m.detailed_type, m.expiration_time,  m.cook_time,  m.cook_option, m.full_instruction, m.full_instruction_2, m.create_date, p.product_tmpl_id, m.list_price, m.active, m.is_published, m.allow_out_of_stock_order   
FROM product_product p INNER JOIN product_template m on (p.product_tmpl_id = m.id)
INNER JOIN res_partner r on (p.barcode like CONCAT(r.vendor_code,'-','%'))
LEFT JOIN ir_translation t ON (t.res_id = m.id AND t.name = 'product.template,name' AND t.type = 'model' AND t.lang='en_US')
WHERE p.barcode not in ('GT0136-003', 'GT0136-011', 'GT0136-009', 'GT0136-008', 'GT0136-010', 'GT0132-016', 'GT0132-006', 'GT0132-007', 'GT0132-008', 'GT041-006', 'GT041-005', 'GT050-002', 'GT0132-012', 'GT0132-013', 'GT0132-014', 'GT0132-015', 'GT0132-010', 'GT0132-011') 
");

    while ($row = $productStatement->fetch(PDO::FETCH_ASSOC)) {

	//print_r($row);//exit;

	$db->where("name", $row["vendor_name"]);
	$vendor_id = $db->getValue("vendor", "id") ?? 0;

	$productCategory = $pdo->query("select c.id, c.name 
					from product_public_category_product_template_rel r INNER JOIN product_public_category c ON (r.product_public_category_id=c.id)
					where r.product_template_id=".$row["product_tmpl_id"]." ");

	$arrPublicCatID = array();
	while ($pcrow = $productCategory->fetch(PDO::FETCH_ASSOC)) {
	    $db->where("name", $pcrow["name"]);
	    $pcat = $db->getOne("product_category");
	    if($pcat) {
	    	$arrPublicCatID[] = (string)$pcat["id"];
	    }
	}
	
	$categ_id = json_encode($arrPublicCatID);


	//delivery method
        $productDeliveryMethod = $pdo->query("select * from product_delivery_carriers where delivery_carrier_ids=(select id from delivery_carrier where name='Sauces Delivery to EM') and product_temp_ids='".$row["product_tmpl_id"]."' ");

        $productDeliveryMethodDetail = $productDeliveryMethod->fetch(PDO::FETCH_ASSOC);

        if($productDeliveryMethodDetail) {
	    $delivery_method = "3";//dry RM15
	} else {
	    $delivery_method = "2";//frozen RM35
	}
	


	$name = $row['product_name'] == "" ?  $row['original_name'] : $row['product_name'];
	$product_type = $row['detailed_type'] == "" ? "" : $row['detailed_type'];
	$description_sale = $row['description_sale'] == "" ? "" : $row['description_sale'];
	$note = $row["description"] == "" ? "" : $row["description"];
	$barcode = $row['barcode'] == "" ? "" : $row['barcode'];
	$expired_day =  $row['expiration_time'] == "" ? 0 : $row['expiration_time'];
	$cooking_time =  $row['cook_time'] == "" ? "" : $row['cook_time'];
	$cooking_suggestion = $row['cook_option'] == "" ? "" : $row['cook_option'];
	$full_instruction = $row['full_instruction'] == "" ? "" : $row['full_instruction'];
	$full_instruction2 = $row['full_instruction_2'] == "" ? "" : $row['full_instruction_2'];
	$is_archive = ($row["active"]?0:1);
	$created_at = $row['create_date'] == "" ?  date('Y-m-d h:i:s', time()) : $row['create_date'];
	$updated_at = date('Y-m-d h:i:s', time());
	$allow_out_of_stock_order = $row["allow_out_of_stock_order"] ?? false;
        $video = $row['video'] == "" ? "" : $row['video'];
        $video2 = $row['video_2'] == "" ? "" : $row['video_2'];

	$sale_price = $row["list_price"];


	$productOriginalCost = $pdo->query("select * from ir_property where res_id='product.template,".$row["product_tmpl_id"]."' and name='original_cost'");

	$productOriginalCostDetail = $productOriginalCost->fetch(PDO::FETCH_ASSOC);
	$original_cost = $productOriginalCostDetail["value_float"] ?? 0;	


	$note = str_replace("\\n", "<br>", strip_tags(str_replace(array("<br>","<br/>"),"\\n",$note)) );
	$full_instruction = str_replace("\\n", "<br>", strip_tags(str_replace(array("<br>","<br/>"),"\\n",$full_instruction)) );
        $full_instruction2 = str_replace("\\n", "<br>", strip_tags(str_replace(array("<br>","<br/>"),"\\n",$full_instruction2)) );

	$productData["cost"] = $original_cost;
	$productData["sale_price"] = $sale_price;
	$productData["name"] = $name;

    if($name == 'Promo 99 Package')
    {
        $productData["product_type"] = 'package';
    }
    else
    {
        $productData["product_type"] = $product_type;
    }
    $productData["description"] = $description_sale;
	$productData["note"] = $note;
    $productData["barcode"] = $barcode;
    $productData["expired_day"] = $expired_day;
    $productData["vendor_id"] = $vendor_id;
    $productData["categ_id"] = $categ_id;
    $productData["cooking_time"] = $cooking_time;
    $productData["cooking_suggestion"] = $cooking_suggestion;
    $productData["full_instruction"] = $full_instruction;
    $productData["full_instruction2"] = $full_instruction2;
	$productData["is_published"] = $row["is_published"];
    $productData["is_archive"] = $is_archive;
    $productData["deleted"] = 0;
    $productData["created_at"] = $created_at;
    $productData["updated_at"] = $updated_at;
	$productData["delivery_method"] = $delivery_method;
    $productData["ignore_stock_count"] = $allow_out_of_stock_order;
	$pid = $db->insert("product", $productData);

    if($name == 'Promo 99 Package')
    {
        $updateData = array(
            'content' => '福利99配套',
        );
        # handle promo 99 chinese name
        $db->where('module_id', $pid);
        $db->where('type', 'name');
        $db->where('module', 'package');
        $db->where('language', 'chinese');

        $db->update('inv_language', $updateData);
    }

    # if categ_id == 12 "FOC", insert into delivery_method_detail table as FOC setting
    if (strpos($categ_id, '"12"') !== false)
    {
        $insertData = array(
            'delivery_method_id' => $delivery_method,
            'product_id' => $pid,
            'quantity' => '0',
            'amount' => '0',
            'deleted' => '0',
            'created_at' => $created_at,
        );
        $db->insert('delivery_method_detail', $insertData);
    }

	$templateData = array("product_id"=>$pid, "product_attribute_value_id"=>"", "deleted"=>0, "created_at"=>$created_at, "updated_at"=>$updated_at);
	$db->insert("product_template", $templateData);


	//Chinese product name
        $chineseProductName = $pdo->query("select * from ir_translation where name = 'product.template,name' AND type = 'model' and lang='zh_CN' and res_id='".$row["product_tmpl_id"]."' ");
        $chineseProductNameDetail = $chineseProductName->fetch(PDO::FETCH_ASSOC);
        $chineseName = $chineseProductNameDetail["value"] ?? "";

	if($chineseName) {
            $arrProductLang = array("module"=>"product", "module_id"=>$pid, "type"=>"name", "language"=>"chinese", "content"=>$chineseName, "updated_at"=>$updated_at );
            $db->insert("inv_language", $arrProductLang);
            $arrProductLang2 = array("module"=>"product", "module_id"=>$pid, "type"=>"description", "language"=>"chinese", "content"=>$description_sale, "updated_at"=>$updated_at );
            $db->insert("inv_language", $arrProductLang2);
	}


        $arrProductLang = array("module"=>"product", "module_id"=>$pid, "type"=>"name", "language"=>"english", "content"=>$name, "updated_at"=>$updated_at );
        $db->insert("inv_language", $arrProductLang);
        $arrProductLang2 = array("module"=>"product", "module_id"=>$pid, "type"=>"description", "language"=>"english", "content"=>$description_sale, "updated_at"=>$updated_at );
        $db->insert("inv_language", $arrProductLang2);


	if($video) {
	    $mediaData["type"] = "video";
	    $mediaData["name"] = "";
            $mediaData["url"] = $video;
            $mediaData["reference_id"] = $pid;
            $mediaData["deleted"] = "0";
            $mediaData["created_at"] = $created_at;
            $mediaData["updated_at"] = $updated_at;
	    $db->insert("product_media", $mediaData);
	}

        if($video2) {
            $mediaData["type"] = "video";
	    $mediaData["name"] = "";
            $mediaData["url"] = $video2;
            $mediaData["reference_id"] = $pid;
            $mediaData["deleted"] = "0";
            $mediaData["created_at"] = $created_at;
            $mediaData["updated_at"] = $updated_at;
            $db->insert("product_media", $mediaData);
        }

	$productImageStatement = $pdo->query("SELECT name, store_fname, mimetype, checksum FROM ir_attachment where res_id='".$row["product_tmpl_id"]."' and res_model='product.template' and res_field is not null order by file_size DESC limit 1");

	$productImageDetail = $productImageStatement->fetch(PDO::FETCH_ASSOC);

	if($productImageDetail) {

	    $arrOdooImageDetail = array("store_fname"=>$productImageDetail["store_fname"], 
					"name"=>$productImageDetail["name"], 
					"mimetype"=>$productImageDetail["mimetype"], 
					"checksum"=>$productImageDetail["checksum"]);
	    fn_keep_image($arrOdooImageDetail, $pid, $created_at, $updated_at);
	}

	$productExtraImageStatement = $pdo->query("select id, name from product_image where product_tmpl_id='".$row["product_tmpl_id"]."' ");

	while ($peirow = $productExtraImageStatement->fetch(PDO::FETCH_ASSOC)) {

	    $productExtraImage2Statement = $pdo->query("select name, store_fname, mimetype, checksum from ir_attachment where res_model='product.image' and res_id='".$peirow["id"]."' and res_field is not null order by file_size DESC limit 1");

            $productExtraImageDetail = $productExtraImage2Statement->fetch(PDO::FETCH_ASSOC);

	    if($productExtraImageDetail) {

                $arrOdooImageDetail = array("store_fname"=>$productExtraImageDetail["store_fname"], 
                                            "name"=>$productExtraImageDetail["name"], 
                                            "mimetype"=>$productExtraImageDetail["mimetype"], 
                                            "checksum"=>$productExtraImageDetail["checksum"]);
                fn_keep_image($arrOdooImageDetail, $pid, $created_at, $updated_at);
	    }

	}

    }
    $db->rawQuery("UPDATE product SET delivery_method = 2 WHERE categ_id LIKE '%12%'");
    $db->rawQuery("UPDATE product SET delivery_method = 3 WHERE barcode IN ('GT0132-005', 'GT0136-006', 'GT0136-002', 'GT0136-001', 'GT0136-004', 'GT0136-007', 'GT0136-005', 'GT027-001', 'GT0136-010', 'GT0136-011', 'GT0136-003')");

    //PACKAGE
    $packageStatement = $pdo->query("select b.product_tmpl_id as pkg_tmpl_id, bl.bom_id, bl.product_id, bl.product_tmpl_id, bl.product_qty, pp.barcode, pt.name
		from mrp_bom b inner join mrp_bom_line bl on (b.id=bl.bom_id)
		inner join product_product pp on (pp.id=bl.product_id)
		inner join product_template pt on (pt.id=bl.product_tmpl_id)
		where b.product_tmpl_id in (select pt.id from product_product pp inner join product_template pt on (pp.product_tmpl_id=pt.id))  
		order by b.product_tmpl_id");

    $arrPackage = array();
    $arrPackageID = array();
    while ($pkgrow = $packageStatement->fetch(PDO::FETCH_ASSOC)) {

	$arrPackage[$pkgrow["pkg_tmpl_id"]][] = array("product_tmpl_id"=>$pkgrow["product_tmpl_id"], "product_qty"=>$pkgrow["product_qty"], "barcode"=>$pkgrow["barcode"] );

	if(!in_array($pkgrow["pkg_tmpl_id"], $arrPackageID) ) {
	    $arrPackageID[] = $pkgrow["pkg_tmpl_id"];
	}

    }

    foreach($arrPackage as $key => $value) {

	foreach($value as $vkey => $vvalue) {
	    if(in_array($vvalue["product_tmpl_id"], $arrPackageID)) {
		$arrPackage[$key][$vkey]['type']="package";
	    } else {
		$arrPackage[$key][$vkey]['type']="product";
	    }

	}	

    }


    foreach($arrPackage as $key => $value) {

	$tmp_product = array();

        foreach($value as $vkey => $vvalue) {
	    if($vvalue["type"]=="package") {
		//print_r($vvalue);
		$tmp_product = array_merge($tmp_product, $arrPackage[$vvalue["product_tmpl_id"]]);
	    }
        }

	if(count($tmp_product)>0) {	
	    $arrPackage[$key] = $tmp_product;
	}

    }


//print_r($arrPackage);
//exit;

    foreach($arrPackage as $key => $value) {

	$packageTemplateStatement = $pdo->query("SELECT p.id as product_id, t.value as product_name, m.name as original_name, m.video,m.video_2, 
						p.barcode, m.description, m.description_sale, m.detailed_type, m.expiration_time, m.cook_time,  
						m.cook_option, m.full_instruction, m.full_instruction_2, m.create_date, 
						p.product_tmpl_id, m.list_price, m.active, m.is_published, m.allow_out_of_stock_order    
						FROM product_product p INNER JOIN product_template m on (p.product_tmpl_id = m.id)
						LEFT JOIN ir_translation t ON (t.res_id = m.id AND t.name = 'product.template,name' AND t.type = 'model' AND t.lang='en_US')
						where p.product_tmpl_id='".$key."' ");

	$row = $packageTemplateStatement->fetch(PDO::FETCH_ASSOC);

	print_r($key);
	print_r($value);

	print_r($row);


        $productCategory = $pdo->query("select c.id, c.name 
                                        from product_public_category_product_template_rel r INNER JOIN product_public_category c ON (r.product_public_category_id=c.id)
                                        where r.product_template_id=".$row["product_tmpl_id"]." ");

        $arrPublicCatID = array();
        while ($pcrow = $productCategory->fetch(PDO::FETCH_ASSOC)) {
            $db->where("name", $pcrow["name"]);
            $pcat = $db->getOne("product_category");
            if($pcat) {
                $arrPublicCatID[] = (string)$pcat["id"];
            }
        }
        
        $categ_id = json_encode($arrPublicCatID);

        $name = $row['product_name'] == "" ?  $row['original_name'] : $row['product_name'];
        $product_type = $row['detailed_type'] == "" ? "" : $row['detailed_type'];
        $description_sale = $row['description_sale'] == "" ? "" : $row['description_sale'];
        $note = $row["description"] == "" ? "" : $row["description"];
        $barcode = $row['barcode'] == "" ? "" : $row['barcode'];
        $expired_day =  $row['expiration_time'] == "" ? 0 : $row['expiration_time'];
        $cooking_time =  $row['cook_time'] == "" ? "" : $row['cook_time'];
        $cooking_suggestion = $row['cook_option'] == "" ? "" : $row['cook_option'];
        $full_instruction = $row['full_instruction'] == "" ? "" : $row['full_instruction'];
        $full_instruction2 = $row['full_instruction_2'] == "" ? "" : $row['full_instruction_2'];
        $is_archive = ($row["active"]?0:1);
        $created_at = $row['create_date'] == "" ?  date('Y-m-d h:i:s', time()) : $row['create_date'];
        $updated_at = date('Y-m-d h:i:s', time());
	$allow_out_of_stock_order = $row["allow_out_of_stock_order"] ?? false;

        $video = $row['video'] == "" ? "" : $row['video'];
        $video2 = $row['video_2'] == "" ? "" : $row['video_2'];

        $sale_price = $row["list_price"];


        $productOriginalCost = $pdo->query("select * from ir_property where res_id='product.template,".$row["product_tmpl_id"]."' and name='original_cost'");

        $productOriginalCostDetail = $productOriginalCost->fetch(PDO::FETCH_ASSOC);
        $original_cost = $productOriginalCostDetail["value_float"] ?? 0;      



        $note = str_replace("\\n", "<br>", strip_tags(str_replace(array("<br>","<br/>"),"\\n",$note)) );
        $full_instruction = str_replace("\\n", "<br>", strip_tags(str_replace(array("<br>","<br/>"),"\\n",$full_instruction)) );
        $full_instruction2 = str_replace("\\n", "<br>", strip_tags(str_replace(array("<br>","<br/>"),"\\n",$full_instruction2)) );

        $productData["cost"] = $original_cost;
        $productData["sale_price"] = $sale_price;
        $productData["name"] = $name;
        $productData["product_type"] = "package";
        $productData["description"] = $description_sale;
        $productData["note"] = $note;
        $productData["barcode"] = $barcode;
        $productData["expired_day"] = $expired_day;
        $productData["vendor_id"] = "";
        $productData["categ_id"] = $categ_id;
        $productData["cooking_time"] = $cooking_time;
        $productData["cooking_suggestion"] = $cooking_suggestion;
        $productData["full_instruction"] = $full_instruction;
        $productData["full_instruction2"] = $full_instruction2;
        $productData["is_published"] = $row["is_published"];
        $productData["is_archive"] = $is_archive;
        $productData["deleted"] = 0;
        $productData["created_at"] = $created_at;
        $productData["updated_at"] = $updated_at;
        $productData["delivery_method"] = "2";
	$productData["ignore_stock_count"] = $allow_out_of_stock_order;
        $pid = $db->insert("product", $productData);
        
        # if categ_id == 12 "FOC", insert into delivery_method_detail table as FOC setting
        if (strpos($categ_id, '"12"') !== false)
        {
            $insertData = array(
                'delivery_method_id' => '2',
                'product_id' => $pid,
                'quantity' => '0',
                'amount' => '0',
                'deleted' => '0',
                'created_at' => $created_at,
            );
            $db->insert('delivery_method_detail', $insertData);
        }
        $templateData = array("product_id"=>$pid, "product_attribute_value_id"=>"", "deleted"=>0, "created_at"=>$created_at, "updated_at"=>$updated_at);
        $db->insert("product_template", $templateData);


        //Chinese product name
        $chineseProductName = $pdo->query("select * from ir_translation where name = 'product.template,name' AND type = 'model' and lang='zh_CN' and res_id='".$row["product_tmpl_id"]."' ");
        $chineseProductNameDetail = $chineseProductName->fetch(PDO::FETCH_ASSOC);
        $chineseName = $chineseProductNameDetail["value"] ?? "";

        if($chineseName) {
            $arrProductLang = array("module"=>"package", "module_id"=>$pid, "type"=>"name", "language"=>"chinese", "content"=>$chineseName, "updated_at"=>$updated_at );
            $db->insert("inv_language", $arrProductLang);
            $arrProductLang2 = array("module"=>"package", "module_id"=>$pid, "type"=>"description", "language"=>"chinese", "content"=>$description_sale, "updated_at"=>$updated_at );
            $db->insert("inv_language", $arrProductLang2);
        }


        $arrProductLang = array("module"=>"package", "module_id"=>$pid, "type"=>"name", "language"=>"english", "content"=>$name, "updated_at"=>$updated_at );
        $db->insert("inv_language", $arrProductLang);
        $arrProductLang2 = array("module"=>"package", "module_id"=>$pid, "type"=>"description", "language"=>"english", "content"=>$description_sale, "updated_at"=>$updated_at );
        $db->insert("inv_language", $arrProductLang2);


        if($video) {
            $mediaData["type"] = "video";
            $mediaData["name"] = "";
            $mediaData["url"] = $video;
            $mediaData["reference_id"] = $pid;
            $mediaData["deleted"] = "0";
            $mediaData["created_at"] = $created_at;
            $mediaData["updated_at"] = $updated_at;
            $db->insert("product_media", $mediaData);
        }

        if($video2) {
            $mediaData["type"] = "video";
            $mediaData["name"] = "";
            $mediaData["url"] = $video2;
            $mediaData["reference_id"] = $pid;
            $mediaData["deleted"] = "0";
            $mediaData["created_at"] = $created_at;
            $mediaData["updated_at"] = $updated_at;
            $db->insert("product_media", $mediaData);
        }



        $productImageStatement = $pdo->query("SELECT name, store_fname, mimetype, checksum FROM ir_attachment where res_id='".$row["product_tmpl_id"]."' and res_model='product.template' and res_field is not null order by file_size DESC limit 1");

        $productImageDetail = $productImageStatement->fetch(PDO::FETCH_ASSOC);

        if($productImageDetail) {

            $arrOdooImageDetail = array("store_fname"=>$productImageDetail["store_fname"],
                                        "name"=>$productImageDetail["name"],
                                        "mimetype"=>$productImageDetail["mimetype"],
                                        "checksum"=>$productImageDetail["checksum"]);
            fn_keep_image($arrOdooImageDetail, $pid, $created_at, $updated_at);
        }

        $productExtraImageStatement = $pdo->query("select id, name from product_image where product_tmpl_id='".$row["product_tmpl_id"]."' ");

        while ($peirow = $productExtraImageStatement->fetch(PDO::FETCH_ASSOC)) {

            $productExtraImage2Statement = $pdo->query("select name, store_fname, mimetype, checksum from ir_attachment where res_model='product.image' and res_id='".$peirow["id"]."' and res_field is not null order by file_size DESC limit 1");

            $productExtraImageDetail = $productExtraImage2Statement->fetch(PDO::FETCH_ASSOC);

            if($productExtraImageDetail) {

                $arrOdooImageDetail = array("store_fname"=>$productExtraImageDetail["store_fname"],
                                            "name"=>$productExtraImageDetail["name"],
                                            "mimetype"=>$productExtraImageDetail["mimetype"],
                                            "checksum"=>$productExtraImageDetail["checksum"]);
                fn_keep_image($arrOdooImageDetail, $pid, $created_at, $updated_at);
            }

        }



	print_r($value);

	foreach($value as $vkey =>$vvalue) {

	    print_r($vkey);
	    print_r($vvalue);
	
	    $db->where('barcode', $vvalue["barcode"] );
	    $productID = $db->getValue("product", "id") ?? 0;
	    
	    $db->insert("package_item", array("package_id"=>$pid, "product_id"=>$productID, "quantity"=>$vvalue["product_qty"], "type"=>"product", "deleted"=>0, "created_at"=>$created_at, "updated_at"=>$updated_at) );

	}

    }


    $db->where("name", "Package Test");
    $testPID = $db->getValue("product", "id") ?? 0;

    $db->where("package_id", $testPID);
    $db->update("package_item", array("deleted"=>1) );


    //
    $db->where("cost", 0);
    $arrProduct = $db->get("product");

    foreach($arrProduct as $key =>$value) {

        if(strpos($value["barcode"], "N") !== false) {
           $chk_code = substr($value["barcode"], 0, strlen($value["barcode"])-1 );
        } else {
           $chk_code = $value["barcode"]."N";
        }

        $db->where("cost", 0, ">");
        $db->where("barcode", $chk_code);
        $newCost = $db->getValue("product", "cost") ?? 0;

        if($newCost>0) {
            //echo "\n".$value["barcode"];
            $db->where("id", $value["id"]);
            $db->update("product", array("cost"=>$newCost) );
        }

    }

    // set all product as continue selling
    //$db->rawQuery("UPDATE product SET ignore_stock_count = '1' WHERE ignore_stock_count != '1'");


    //package cost
    $db->where("deleted", 0);
    $db->where("type", "product");
    $arrPackageItem = $db->get("package_item");

    foreach($arrPackageItem as $key => $value) {

	$db->where("id", $value["product_id"]);
	$cost = $db->getValue("product", "cost") ?? 0;

	$db->where("id", $value["id"] );
	$db->update("package_item", array("cost"=>$cost) );
    }

    echo "\nDone....";


    function fn_keep_image($productImageDetail, $pid, $created_at, $updated_at) {

	global $db;

	$store_fname = $productImageDetail["store_fname"];
        $fname = $productImageDetail["name"];
        $mimetype = $productImageDetail["mimetype"];
        $checksum = $productImageDetail["checksum"];

        if($mimetype=="image/png") {
            $new_filename = $pid."_".$checksum.".png";
        } else if($mimetype=="image/jpeg") {
            $new_filename = $pid."_".$checksum.".jpeg";
        } else if($mimetype=="image/svg+xml") {
            $new_filename = $pid."_".$checksum.".svg";
        } else if($mimetype=="image/x-icon") {
            $new_filename = $pid."_".$checksum.".ico";
        } else {
            $new_filename = $pid."_".$checksum;
        }

        $mediaPath = "/opt/odoo/.local/share/Odoo/filestore/goTasty/".$store_fname;
        $tmpPath = "/var/www/goTastyBackend/modules/gotasty/migration/image/product/".$new_filename;

        $cmd = "scp root@103.76.36.107:".$mediaPath." ".$tmpPath;
        exec($cmd, $output, $result);

        $image = "https://scontent-speed101.sgp1.cdn.digitaloceanspaces.com/gotasty/image/product/".$new_filename;

        $mediaData["type"] = "Image";
        $mediaData["name"] = $fname;
        $mediaData["url"] = $image;
        $mediaData["reference_id"] = $pid;
        $mediaData["deleted"] = "0";
        $mediaData["created_at"] = $created_at;
        $mediaData["updated_at"] = $updated_at;
        $db->insert("product_media", $mediaData);

    }

    # handle specific product update to delviery_method 3
    $db->rawQuery("UPDATE product SET delivery_method = 3 WHERE barcode IN ('GT0136-002', 'GT0136-007', 'GT0136-005', 'GT0136-006', 'GT0136-001', 'GT0136-004')");
    # manually insert into delivery method details table
    $db->where('barcode', 'GT0136-007');
    $productID = $db->getOne('product', 'id');
    $insertData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '6',
        'amount' => '0',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);
    
    $db->where('barcode', 'GT0136-006');
    $productID = $db->getOne('product', 'id');
    $insertData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '6',
        'amount' => '0',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);

    $db->where('barcode', 'GT0136-005');
    $productID = $db->getOne('product', 'id');
    $insertData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '6',
        'amount' => '0',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);

    $db->where('barcode', 'GT0136-004');
    $productID = $db->getOne('product', 'id');
    $insertData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '6',
        'amount' => '0',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);

    $db->where('barcode', 'GT0136-002');
    $productID = $db->getOne('product', 'id');
    $insertData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '6',
        'amount' => '0',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);

    $db->where('barcode', 'GT0136-001');
    $productID = $db->getOne('product', 'id');
    $insertData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '6',
        'amount' => '0',
        'apply_condition' => 'sum_total',
        'deleted' => '0',
        'created_at' => $created_at,
    );
    $db->insert('delivery_method_detail', $insertData);

    // manually update
    $db->where('barcode', 'GT0136-010');
    $productID = $db->getOne('product', 'id');
    $updateData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '',
        'amount' => '0',
        'apply_condition' => '',
        'deleted' => '0',
        'updated_at' => $created_at,
    );
    $db->where('product_id', $productID['id']);
    $db->update('delivery_method_detail', $updateData);
    
    $db->where('barcode', 'GT0136-011');
    $productID = $db->getOne('product', 'id');
    $updateData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '',
        'amount' => '0',
        'apply_condition' => '',
        'deleted' => '0',
        'updated_at' => $created_at,
    );
    $db->where('product_id', $productID['id']);
    $db->update('delivery_method_detail', $updateData);

    $db->where('barcode', 'GT0136-003');
    $productID = $db->getOne('product', 'id');
    $updateData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '',
        'amount' => '0',
        'apply_condition' => '',
        'deleted' => '0',
        'updated_at' => $created_at,
    );
    $db->where('product_id', $productID['id']);
    $db->update('delivery_method_detail', $updateData);

    $db->where('barcode', 'GT0136-008');
    $productID = $db->getOne('product', 'id');
    $updateData = array(
        'delivery_method_id' => '3',
        'product_id' => $productID['id'],
        'quantity' => '',
        'amount' => '0',
        'apply_condition' => '',
        'deleted' => '0',
        'updated_at' => $created_at,
    );
    $db->where('product_id', $productID['id']);
    $db->update('delivery_method_detail', $updateData);

    # Handle Product Table

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-010');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-011');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-003');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-008');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-007');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-006');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-005');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-004');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-002');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0136-001');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0132-005');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT027-001');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0128-002');
    $db->update('product', $updateData);

    $updateData = array(
        'delivery_method' => '3',
        'updated_at' => $created_at,
    );
    $db->where('barcode', 'GT0128-001');
    $db->update('product', $updateData);

    echo 'Importing Promo Code Data';
    # handle for promo code
    $insertData = array(
        'promo_code_name'           => 'First Time Purchase',
        'code'                      => '',
        'type'                      => 'firstTimePurchase',
        'is_first_time_purchase'    => '1',
        'status'                    => 'Active',
        'disabled'                  => '0',
        'reference_id'              => '0',
        'used_amount'               => '0',
        'discount_type'             => 'percentage',
        'discount_apply_on'         => 'onOrder',
        'discount'                  => '20',
        'max_discount_amount'       => '30.2',
        'max_quantity'              => '',
        'apply_type'                => 'autoApply',
        'start_date'                => '2023-08-11 00:00:00',
        'end_date'                  => '2099-08-11 00:00:00',
        'created_at'                => $created_at,
        'created_by'                => ''     
    );
    $db->insert('mlm_promo_code', $insertData);
    
    $insertData = array(
        'promo_code_name'           => 'AXTJ SAUCE',
        'code'                      => 'AXTJSAUCE',
        'type'                      => 'firstTimePurchase',
        'is_first_time_purchase'    => '0',
        'status'                    => 'Active',
        'disabled'                  => '0',
        'reference_id'              => '0',
        'used_amount'               => '0',
        'discount_type'             => 'amount',
        'discount_apply_on'         => 'onOrder',
        'discount'                  => '19',
        'max_discount_amount'       => '',
        'max_quantity'              => '',
        'apply_type'                => 'useCode',
        'start_date'                => '2023-08-11 00:00:00',
        'end_date'                  => '2099-08-11 00:00:00',
        'created_at'                => $created_at,
        'created_by'                => ''     
    );
    $db->insert('mlm_promo_code', $insertData);

    $insertData = array(
        'promo_code_name'           => 'AXTJSAUCE PWP',
        'code'                      => '',
        'type'                      => 'PWP2',
        'is_first_time_purchase'    => '0',
        'status'                    => 'Active',
        'disabled'                  => '0',
        'reference_id'              => '0',
        'used_amount'               => '0',
        'discount_type'             => 'percentage',
        'discount_apply_on'         => 'onOrder',
        'discount'                  => '',
        'max_discount_amount'       => '',
        'max_quantity'              => '6',
        'apply_type'                => 'autoApply',
        'start_date'                => '2023-08-11 00:00:00',
        'end_date'                  => '2099-08-11 00:00:00',
        'created_at'                => $created_at,
        'created_by'                => ''     
    );
    $db->insert('mlm_promo_code', $insertData);

    $insertData = array(
        'promo_code_name'           => 'Durian Man PWP 1',
        'code'                      => '',
        'type'                      => 'PWP2',
        'is_first_time_purchase'    => '0',
        'status'                    => 'Active',
        'disabled'                  => '0',
        'reference_id'              => '0',
        'used_amount'               => '0',
        'discount_type'             => 'percentage',
        'discount_apply_on'         => 'onOrder',
        'discount'                  => '',
        'max_discount_amount'       => '',
        'max_quantity'              => '',
        'apply_type'                => 'autoApply',
        'start_date'                => '2023-08-11 00:00:00',
        'end_date'                  => '2099-08-11 00:00:00',
        'created_at'                => $created_at,
        'created_by'                => ''     
    );
    $db->insert('mlm_promo_code', $insertData);

    $insertData = array(
        'promo_code_name'           => 'Durian Man PWP 2',
        'code'                      => '',
        'type'                      => 'PWP2',
        'is_first_time_purchase'    => '0',
        'status'                    => 'Active',
        'disabled'                  => '0',
        'reference_id'              => '0',
        'used_amount'               => '0',
        'discount_type'             => 'percentage',
        'discount_apply_on'         => 'onOrder',
        'discount'                  => '',
        'max_discount_amount'       => '',
        'max_quantity'              => '',
        'apply_type'                => 'autoApply',
        'start_date'                => '2023-08-11 00:00:00',
        'end_date'                  => '2099-08-11 00:00:00',
        'created_at'                => $created_at,
        'created_by'                => ''     
    );
    $db->insert('mlm_promo_code', $insertData);

    $insertData = array(
        'promo_code_name'           => 'Durian Man PWP 3',
        'code'                      => 'PWPMOONCAKE',
        'type'                      => 'PWP2',
        'is_first_time_purchase'    => '0',
        'status'                    => 'Active',
        'disabled'                  => '0',
        'reference_id'              => '0',
        'used_amount'               => '0',
        'discount_type'             => 'percentage',
        'discount_apply_on'         => 'onOrder',
        'discount'                  => '',
        'max_discount_amount'       => '',
        'max_quantity'              => '2',
        'apply_type'                => 'autoApply',
        'start_date'                => '2023-08-11 00:00:00',
        'end_date'                  => '2099-08-11 00:00:00',
        'created_at'                => $created_at,
        'created_by'                => ''     
    );
    $db->insert('mlm_promo_code', $insertData);

    $insertData = array(
        'promo_code_name'           => 'Durian Man PWP 4',
        'code'                      => '',
        'type'                      => 'PWP2',
        'is_first_time_purchase'    => '0',
        'status'                    => 'Active',
        'disabled'                  => '0',
        'reference_id'              => '0',
        'used_amount'               => '0',
        'discount_type'             => 'percentage',
        'discount_apply_on'         => 'onOrder',
        'discount'                  => '',
        'max_discount_amount'       => '',
        'max_quantity'              => '2',
        'apply_type'                => 'autoApply',
        'start_date'                => '2023-08-11 00:00:00',
        'end_date'                  => '2099-08-11 00:00:00',
        'created_at'                => $created_at,
        'created_by'                => ''     
    );
    $db->insert('mlm_promo_code', $insertData);

    # promo code detail table
    $db->where('deleted', '0');
    $db->where('barcode', 'GT0132-016'); // GoTasty Special Pick
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    
    $insertData = array(
        'promo_code_id'             => '1',
        'product_id'                => $productId,
        'quantity'                  => '1',
        'type'                      => 'firstTimePurchase',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_detail', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT0132-006'); // GoTasty-DurianMan Package 1
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    
    $insertData = array(
        'promo_code_id'             => '4',
        'product_id'                => $productId,
        'quantity'                  => '1',
        'type'                      => 'PWP2',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_detail', $insertData);

    $insertData = array(
        'promo_code_id'             => '6',
        'product_id'                => $productId,
        'quantity'                  => '1',
        'type'                      => 'PWP2',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_detail', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT0132-007'); // GoTasty-DurianMan Package 2
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    
    $insertData = array(
        'promo_code_id'             => '7',
        'product_id'                => $productId,
        'quantity'                  => '1',
        'type'                      => 'PWP2',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_detail', $insertData);

    $insertData = array(
        'promo_code_id'             => '5',
        'product_id'                => $productId,
        'quantity'                  => '1',
        'type'                      => 'PWP2',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_detail', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT0136-008'); // GoTasty - Green Curry Paste X 2, Rendang Paste X 2, Crispy Dry Shrimp Chilli X1, Sambal Nasi Lemak X1
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    
    $insertData = array(
        'promo_code_id'             => '2',
        'product_id'                => $productId,
        'quantity'                  => '1',
        'type'                      => 'PWP2',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_detail', $insertData);

    $insertData = array(
        'promo_code_id'             => '3',
        'product_id'                => $productId,
        'quantity'                  => '1',
        'type'                      => 'PWP2',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_detail', $insertData);

    # promo_code_product
    $db->where('deleted', '0');
    $db->where('barcode', 'GT050-001'); // Kuala Lumpur DurianMan - Musang King Durian Mochi (1 box)
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];

    $insertData = array(
        'promo_code_id'             => '5',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '12',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $insertData = array(
        'promo_code_id'             => '4',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '12',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT050-005'); // DurianMan Moon Cake Exclusive Box (Musang King 3pcs + Black Thorn 3pcs)
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];

    $insertData = array(
        'promo_code_id'             => '6',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '99',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $insertData = array(
        'promo_code_id'             => '7',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '99',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT050-003'); // DurianMan MusangKing Pulp Cheese Cake (1 slice)
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];

    $insertData = array(
        'promo_code_id'             => '4',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '19.9',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $insertData = array(
        'promo_code_id'             => '5',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '19.9',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );

    $db->where('deleted', '0');
    $db->where('barcode', 'GT050-004'); // DurianMan Tiramisu Chocolate 150g
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];

    $insertData = array(
        'promo_code_id'             => '4',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '14.9',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $insertData = array(
        'promo_code_id'             => '5',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '14.9',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT0136-004'); // GoTasty - Steam Fish Chilli Sauce 250g
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];

    $insertData = array(
        'promo_code_id'             => '3',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '9.9',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT0136-005'); // GoTasty - Hainanese Chicken Rice Sauce 250g
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];

    $insertData = array(
        'promo_code_id'             => '3',
        'product_id'                => $productId,
        'quantity'                  => '0',
        'sale_price'                => '9.9',
        'disabled'                  => '0',
        'created_at'                => $created_at
    );
    $db->insert('promo_code_product', $insertData);

    echo "\nDone Promo Code Import";

    echo "\nInsert Promo 99";

    # special handle for promo 99 package
    $db->where('name', 'Promo 99 Package');
    $packageInfo = $db->getOne('product');

    $db->where('deleted', '0');
    $db->where('barcode', 'GT0132-002'); # GoTasty- Steam Minced Pork with Dong Choy
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    $insertData = array(
        'package_id' => $productInfo['id'],
        'product_id' => $productId,
        'quantity' => '2',
        'cost' => $productInfo['sale_price'],
        'type' => 'product',
        'deleted' => '0',
        'created_at' => $created_at,
    );

    $db->insert('package_item', $insertData);
    $db->where('deleted', '0');
    $db->where('barcode', 'GT0117-001'); # Penang Wang Ji - Bak Kut Teh (Mix)
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    $insertData = array(
        'package_id' => $productInfo['id'],
        'product_id' => $productId,
        'quantity' => '2',
        'cost' => $productInfo['sale_price'],
        'type' => 'product',
        'deleted' => '0',
        'created_at' => $created_at,
    );

    $db->insert('package_item', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT013-002'); # Kuala Lumpur Pamilya - Indonesian Styele Banana Leave Ikan Pari Bakar
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    $insertData = array(
        'package_id' => $productInfo['id'],
        'product_id' => $productId,
        'quantity' => '2',
        'cost' => $productInfo['sale_price'],
        'type' => 'product',
        'deleted' => '0',
        'created_at' => $created_at,
    );

    $db->insert('package_item', $insertData);

    $db->where('deleted', '0');
    $db->where('barcode', 'GT0125-001'); # Kuala Lumpur Pudu Lintang 138 - Kopi
    $productInfo = $db->getOne('product');
    $productId = $productInfo['id'];
    $insertData = array(
        'package_id' => $productInfo['id'],
        'product_id' => $productId,
        'quantity' => '2',
        'cost' => $productInfo['sale_price'],
        'type' => 'product',
        'deleted' => '0',
        'created_at' => $created_at,
    );

    $db->insert('package_item', $insertData);
    
    $insertData = array(
        'package_id' => $productInfo['id'],
        'product_id' => '0',
        'quantity' => '2',
        'cost' => '0',
        'type' => 'mystery',
        'deleted' => '0',
        'created_at' => $created_at,
    );

    $db->insert('package_item', $insertData);
    echo "\nDone imported Promo 99";
?>
