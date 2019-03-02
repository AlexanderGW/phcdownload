<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	default :
	{
		$paths = array(
			$kernel->config['system_root_dir'],
			$kernel->config['system_root_dir_gallery'],
			$kernel->config['system_root_dir_upload']
		);
		
		echo "<textarea style='font-family: Courier;' cols='100' rows='30'>";
		
		echo "--Details\n";
		
		echo $_SERVER['SERVER_SOFTWARE'] . "\n";
		
		echo "PHP [" . PHP_VERSION . "] [" . $_SERVER['GATEWAY_INTERFACE'] . "]\nMySQL [" . $kernel->db->item( "SELECT VERSION()" ) . "]\n--Permissions\n";
		
		foreach( $paths AS $key => $path )
		{
  		echo "[" . $path . "] is readable.. " . ( ( @is_readable( $path ) == false ) ? "FAIL" : "OK" ) . "\n";
			@clearstatcache();
			
     	echo "[" . $path . "] is writable.. " . ( ( @is_writable( $path ) == false ) ? "FAIL" : "OK" ) . "\n";
			@clearstatcache();
		}
		
		echo "</textarea>";
	}
}

?>

