<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    
    $db->rawQuery("TRUNCATE cooking_suggestion");


    $inputFileName = $argv[1];
    // $inputFileName = "Cooking Suggestion.xlsx";

    if ($inputFileName=="") {
        echo "Please enter input file name.";
        exit;
    }

    try {
        $inputFileType  = PHPExcel_IOFactory::identify($inputFileName);
        $objReader      = PHPExcel_IOFactory::createReader($inputFileType);
        $objPHPExcel    = $objReader->load($inputFileName);

 
        $sheet          = $objPHPExcel->getSheet(0); 
        $highestRow     = $sheet->getHighestRow(); 
        $highestColumn  = $sheet->getHighestColumn();
        //$highestColumn  = "G"; //Highest Column L, take until Column G only

        //echo "\nhighestRow: ".$highestRow;
        //echo "\nhighestColumn: ".$highestColumn;
	//exit;

        $rowData = $sheet->rangeToArray('A1:'.$highestColumn.'1', NULL, FALSE, FALSE);
        $headerData = array_filter($rowData[0]);

        for ($row = 2; $row <= $highestRow; $row++) { 

            $rowData = $sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, NULL, FALSE, FALSE);
         
            foreach ($rowData as $index => $datas) {
		//print_r($datas);
		//exit;
		$no = $datas[0];
		$englishName = $datas[1];
		$chineseName = $datas[2];
		$englishDesc = $datas[3];
		$chineseDesc = $datas[4];
		$defaultUrl = $datas[5];
		$cookingTime = $datas[6];

		if($no<>"") {
		    $suggestionData = array();
		    $suggestionData["name"] = $englishName;
                    $suggestionData["description"] = $englishDesc;
                    $suggestionData["cooking_time"] = $cookingTime;
                    $suggestionData["defaultURL"] = $defaultUrl;
                    $suggestionData["status"] = "Active";
                    $suggestionData["updated_at"] = date("Y-m-d H:i:s");
                    $suggestionData["created_at"] = date("Y-m-d H:i:s");
		    $suggestionID = $db->insert("cooking_suggestion", $suggestionData );

		
		    $invLang = array();
		    $invLang[] = array("module"=>"cookingSuggestion", "module_id"=>$suggestionID, "type"=>"name", "language"=>"english", "content"=>$englishName, "updated_at"=>date("Y-m-d H:i:s") );
                    $invLang[] = array("module"=>"cookingSuggestion", "module_id"=>$suggestionID, "type"=>"name", "language"=>"chinese", "content"=>$chineseName, "updated_at"=>date("Y-m-d H:i:s") );         

                    $invLang[] = array("module"=>"cookingSuggestion", "module_id"=>$suggestionID, "type"=>"description", "language"=>"english", "content"=>$englishDesc, "updated_at"=>date("Y-m-d H:i:s") );
                    $invLang[] = array("module"=>"cookingSuggestion", "module_id"=>$suggestionID, "type"=>"description", "language"=>"chinese", "content"=>$chineseDesc, "updated_at"=>date("Y-m-d H:i:s") );

 		    $db->insertMulti("inv_language", $invLang);

		}
            }

        }

    } catch(Exception $e) {
        die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
    }


    echo "\n\nDone....";


?>
