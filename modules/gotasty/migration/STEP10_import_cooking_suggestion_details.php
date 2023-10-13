<?php

    $currentPath = __DIR__;
    include_once($currentPath.'/../include/classlib.php');
    
    $db->rawQuery("TRUNCATE cooking_suggestion_details");


    $inputFileName = $argv[1];
    // $inputFileName = "Product List (1).xlsx";

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
        // $highestColumn  = $sheet->getHighestColumn();
        $highestColumn  = "G"; //Highest Column L, take until Column G only

        // echo "\nhighestRow: ".$highestRow;
        // echo "\nhighestColumn: ".$highestColumn;


        $rowData = $sheet->rangeToArray('A1:'.$highestColumn.'1', NULL, FALSE, FALSE);
        $headerData = array_filter($rowData[0]);

        for ($row = 2; $row <= $highestRow; $row++) { 

            $rowData = $sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, NULL, FALSE, FALSE);
         
            foreach ($rowData as $index => $datas) {

                $db->where("barcode", $datas[3]);
                $productDetail = $db->getOne("product");

                if($productDetail) {

                    $instruction_id = intval($datas[5]);

                    $db->where("id", $instruction_id);
                    $suggestionDetail = $db->getOne("cooking_suggestion");

                    if($suggestionDetail) {

                        if($datas[6]!="") {
                            $suggestionUrl = $datas[6];
                        } else if(strtolower($suggestionDetail["defaultURL"])!="pending") {
                            $suggestionUrl = $suggestionDetail["defaultURL"];
                        } else {
                            $suggestionUrl = "";
                        }


                        $detailData["url"] = $suggestionUrl;
                        $detailData["remark"] = "";
                        $detailData["product_id"] = $productDetail["id"];
                        $detailData["suggestion_id"] = $instruction_id;
                        $detailData["updated_at"] = date("Y-m-d H:i:s");
                        $detailData["created_at"] = date("Y-m-d H:i:s");
                        $db->insert("cooking_suggestion_details", $detailData);

                        // print_r($detailData);
                        // exit;

                    } else {

                        echo "\nCooking Instruction ID Not Found...";
                        print_r($datas);

                    }
                    
                } else {

                    echo "\nProduct Not Found...";
                    print_r($datas);

                }

            }

        }

    } catch(Exception $e) {
        die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
    }


    echo "\n\nDone....";


?>
