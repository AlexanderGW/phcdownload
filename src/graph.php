<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

header( "Content-type: image/png" );

//----------------------------------
// GD libary not loaded
//----------------------------------

if( !extension_loaded( "gd" ) )
{
	readfile( $kernel->config['system_root_dir'] . DIR_STEP . 'images' . DIR_STEP . 'graph_disabled.png' );
}
else
{
	$kernel->clean_array( "_REQUEST", array( "type" => V_STR ) );
	
	if( empty( $kernel->vars['type'] ) ) $kernel->vars['type'] = "download";
	
	//----------------------------------
	// Has graph expired?
	//----------------------------------
	
	if( $kernel->vars['type'] == "download" AND $kernel->db->numrows( "SELECT `datastore_timestamp` FROM `" . TABLE_PREFIX . "datastore` WHERE `datastore_key` = 'graph_download_sync' AND `datastore_timestamp` < ( " . UNIX_TIME . " - " . $kernel->config['graph_cache_time'] . " ) LIMIT 1" ) == 1 )
	{
		list( $kernel->graph->vars['x'], $kernel->graph->vars['y'] ) = explode( 'x', $kernel->config['graph_size_dimensions'] );
		$kernel->graph->vars['x_legend'] = 'Last 24 Hours';
		$kernel->graph->vars['y_legend'] = 'Total Per Hour';
		
		$kernel->graph->create();
		
		$time_base = strtotime( date( "Y-m-" ) . date( "d", ( UNIX_TIME - 82800 ) ) . " " . date( "H", UNIX_TIME + 3600 ) . ":00:00" );
		
		$active = $leech = $broken = $modified = array();
		
		//----------------------------------
		// Store download state plots
		//----------------------------------
		
		for( $i = 0; $i <= 22; $i++ )
		{
			$active[] = $kernel->db->item( "SELECT COUNT( `log_id` ) AS `value` FROM `" . TABLE_PREFIX . "logs` WHERE `log_type` = 1 AND `log_timestamp` >= " . ( $time_base + ( 3600 * $i ) ) . " AND `log_timestamp` <= " . ( $time_base + ( 3600 * $i ) + 3600 ) );
			$leech[] = $kernel->db->item( "SELECT COUNT( `log_id` ) AS `value` FROM `" . TABLE_PREFIX . "logs` WHERE `log_type` = 2 AND `log_timestamp` >= " . ( $time_base + ( 3600 * $i ) ) . " AND `log_timestamp` <= " . ( $time_base + ( 3600 * $i ) + 3600 ) );
			$broken[] = $kernel->db->item( "SELECT COUNT( `log_id` ) AS `value` FROM `" . TABLE_PREFIX . "logs` WHERE `log_type` = 3 AND `log_timestamp` >= " . ( $time_base + ( 3600 * $i ) ) . " AND `log_timestamp` <= " . ( $time_base + ( 3600 * $i ) + 3600 ) );
			$modified[] = $kernel->db->item( "SELECT COUNT( `log_id` ) AS `value` FROM `" . TABLE_PREFIX . "logs` WHERE `log_type` = 4 AND `log_timestamp` >= " . ( $time_base + ( 3600 * $i ) ) . " AND `log_timestamp` <= " . ( $time_base + ( 3600 * $i ) + 3600 ) );
		}
		
		//----------------------------------
		// Draw graph & plot
		//----------------------------------
		
		$kernel->graph->draw( max( array( max( $active ), max( $leech ), max( $broken ), max( $modified ) ) ), 4 );
		
		for( $i = 0; $i <= 22; $i++ )
		{
			if( $active[ $i ] > 0 ) $kernel->graph->plot( $active[ $i ], $i, 0, '66cc66' );
			if( $leech[ $i ] > 0 ) $kernel->graph->plot( $leech[ $i ], $i, 1, '3366cc' );
			if( $modified[ $i ] > 0 ) $kernel->graph->plot( $modified[ $i ], $i, 2, 'ffcc66' );
			if( $broken[ $i ] > 0 ) $kernel->graph->plot( $broken[ $i ], $i, 3, 'cc3333' );
		}
		
		$kernel->graph->output( 'png', $kernel->config['system_root_dir'] . DIR_STEP . 'graph' . DIR_STEP . $kernel->vars['type'] );
		
		$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "datastore` SET `datastore_timestamp` = " . UNIX_TIME . " WHERE `datastore_key` = 'graph_download_sync'" );
	}
	
	//----------------------------------
	// Output graph image data
	//----------------------------------
	
	readfile( $kernel->config['system_root_dir'] . DIR_STEP . 'graph' . DIR_STEP . $kernel->vars['type'] . '.png' );
}
?>