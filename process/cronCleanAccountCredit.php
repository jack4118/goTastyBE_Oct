<?php
    
    /**
     * Script to clean up unnecessary tables or tables that have passed their lifetime.
     */
    
    $currentPath = __DIR__;
    $logPath = $currentPath.'/../log/';
    $logBaseName = basename(__FILE__, '.php');
    
    include($currentPath.'/../include/config.php');
    include($currentPath.'/../include/class.database.php');
    include($currentPath.'/../include/class.setting.php');
    include($currentPath.'/../include/class.log.php');
    
    $db = new MysqliDb($config['dBHost'], $config['dBUser'], $config['dBPassword'], $config['dB']);
    $setting = new Setting($db);
    $log = new Log($logPath, $logBaseName);
    
    $dbHost = $config['dBHost'];
    $databaseName = $config['dB'];
    $user = $config['dBUser'];
    $password = $config['dBPassword'];
    $todayDate = date('Ymd');


            $tblName = 'acc_credit_';
            $lastDate = date("Ymd", strtotime("-".$day." day"));

            $result = $db->rawQuery('SHOW TABLES LIKE "'.$db->escape($tblName).'%"');

            echo json_encode($result);
            // Get daily table_name
            foreach ($result as $array) {
                foreach ($array as $key => $val) {

                    $tblDate = $tblName;
                    echo $tblDate;
                    if(strtotime($tblDate) <= strtotime($lastDate)){
                        $dropTables[] = $val;
                    }
                }
            }

            if($dropTables){
                foreach ($dropTables as $key => $backupTable) {

                    if($row["backup"] == 1){
                        // If backup flag is turned on, we backup the table before dropping it
                        echo date("Y-m-d H:i:s")." Backing up $backupTable before DROP.\n\n";
                        $backupName = "$databaseName.$backupTable.sql";
                        $command = "/usr/bin/mysqldump --skip-lock-tables -u$user -p$password $databaseName ".$backupTable." > $backupPath$backupName";
                        exec($command);
                    }
                    
                    echo date("Y-m-d H:i:s")." DROPPING $backupTable now.\n";

                    $result = $db->rawQuery('DROP TABLE IF EXISTS '.$db->escape($backupTable).' ');
            
                }
            }

       


?>
