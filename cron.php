<?php
	
	require_once(dirname(__FILE__) . '/../../../wp-config.php');
	                                     
	nocache_headers();
	
	// check password
	if(isset($_REQUEST['code']) && $_REQUEST['code'] == get_option('wpo_croncode')) 
	{
		require_once( dirname(__FILE__) . '/wpomatic.php' );
		
		$wpomatic->runCron();
	} else                                                                          
    $wpomatic->log('Warning! cron.php was called with the wrong password or without one!');