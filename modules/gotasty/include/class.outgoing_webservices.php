<?php 

	class OutgoingWebservice {
        
        function __construct($db)
        {
            $this->db = $db;
        }

		// function insertXunWebserviceData($dataIn, $tblDate, $createTime, $command) {
		function insertOutgoingWebserviceData($dataIn, $tblDate, $createTime, $command) {
            $db = MysqliDb::getInstance();
            
			if(!trim($tblDate)) {
				$tblDate = date("Ymd");
			}

            $result = $db->rawQuery("CREATE TABLE IF NOT EXISTS outgoing_webservices_".$db->escape($tblDate)." LIKE outgoing_webservices");

			// Insert a new record into xun webservice table
            $command = $db->escape($command);
            $createTime = $db->escape($createTime);
            $dataIn = $db->escape($dataIn);
            
            $fields = array("command", "data_in", "created_at");
            $values = array($command, $dataIn, $createTime);
            $insertData = array_combine($fields, $values);

	        $insertId = $db->insert("outgoing_webservices_".$db->escape($tblDate)."", $insertData);
            
            return $insertId;
		}
        
        // function updateXunWebserviceData($webserviceID, $dataOut, $status, $completeTime, $processedTime, $tblDate, $httpCode) {
        function updateOutgoingWebserviceData($webserviceID, $dataOut, $status, $completeTime, $processedTime, $tblDate, $httpCode) {
            $db = MysqliDb::getInstance();

			if(!trim($tblDate)) {
				$tblDate = date("Ymd");
			}
            
            if (!$db->tableExists ('outgoing_webservices_'.$tblDate))
                $db->rawQuery("CREATE TABLE IF NOT EXISTS outgoing_webservices_".$db->escape($tblDate)." LIKE outgoing_webservices");

            $status = $db->escape($status);
            $completeTime = $db->escape($completeTime);
            $processedTime = $db->escape($processedTime);
            $dataOut = $db->escape($dataOut);
            $httpCode = $db->escape($httpCode);

            $fields = array("data_out", "status", "completed_at", "duration", "http_code", "updated_at");
            $values = array($dataOut, $status, $completeTime, $processedTime, $httpCode, $completeTime);
            $updateData = array_combine($fields, $values);
            
            $db->where("id", $webserviceID);
            $db->update("outgoing_webservices_".$tblDate."", $updateData);

		}
        
	}
?>
