<?php
    
    $config = array(
                    
	    // Connectivity
	    'dBHost'			  => "127.0.0.1",
	    // 'dB'				  => "goTastyTest",
	    'dB'				  => 'prelive_goTastyTest',
	    'dBUser'			  => "root",
        'processUser'         => "root",
        'processPassword'     => "abc123",
	    'dBPassword'		  => "abc123",
	    
	    // Routes
	    'root'				  => realpath(dirname(__FILE__))."/../",
	    
	    'frontendServerIP'    => '127.0.0.1', 
	    'memberLanguagePath'  => '/Users/cheelam/sites/gotasty/member/language',
	    'adminLanguagePath'   => '/Users/cheelam/sites/gotasty/admin/language',
	    'backendLanguagePath' => '/Users/cheelam/sites/gotasty-backend/language',

		 // digital ocean setting
		 "doApiKey"      => "QGQ23JHT56OSABF6F7ND",
		 "doSecretKey"   => "T+Pifo01byGMPG67lUxiI1HU3P1v1yz0anpK946B7yQ",
		 "doRegion"      => "sgp1",
		 "doEndpoint"    => "https://sgp1.digitaloceanspaces.com",
		 "doBucketName"  => "scontent-speed101",
		 "doFolderName"  => "gotasty/",
		 "tempMediaUrl"  => "https://scontent-speed101.sgp1.digitaloceanspaces.com/gotasty/",

    );
        
?>