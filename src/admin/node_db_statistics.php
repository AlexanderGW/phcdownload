<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 0 );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	default :
	{
		$get_statistics = $kernel->db->query( "SELECT `datastore_key`, `datastore_value` FROM `" . TABLE_PREFIX . "datastore`" );
		
		while( $statistic = $kernel->db->data( $get_statistics ) )
		{
			if( strstr( $statistic['datastore_key'], "count_total_" ) == false ) continue;
			
			$datastore[ $statistic['datastore_key'] ] = $statistic['datastore_value'];
		}
		@ksort( $datastore );
		
		//----------------------------------------------
		
		/*$chart_keys = array(
			"count_total_users" => "red",
			"count_total_comments" => "green",
			"count_total_votes" => "yellow",
			"count_total_submissions" => "orange",
			"count_total_downloads" => "maroon",
			"count_total_views" => "black"
		);
		
		$kernel->tp->call( "admin_db_statistics_chart_header" );
		
		foreach( $datastore AS $stat['count_name'] => $stat['count_total'] )
		{
			if( !isset( $chart_keys[ $stat['count_name'] ] ) ) continue;
			
			$average_daily_count = ( $stat['count_total'] / ceil( $kernel->config['archive_start'] / 86400 ) );
			
			$percentage = ( round( $average_daily_count ) * 100 );
			$fraction = $average_daily_count;
			
			$stat['count_bar_width'] = $percentage;
			$stat['count_bar_title'] = $fraction . " per day";
			$stat['count_bar_percentage'] = $percentage / $stat['count_total'];
			$stat['count_bar_background_colour'] = $chart_keys[ $stat['count_name'] ];
			
			$kernel->tp->call( "admin_db_statistics_chart_row" );
			
			$kernel->tp->cache( $stat );
		}
		@reset( $datastore );
		
		$kernel->tp->call( "admin_db_statistics_chart_footer" );*/
		
		//----------------------------------------------
		
		$kernel->tp->call( "admin_db_statistics_header" );
		
		foreach( $datastore AS $stat['count_name'] => $stat['count_total'] )
		{
			$kernel->tp->call( "admin_db_statistics_row" );
			
			if( $stat['count_name'] == "count_total_data" )
			{
				$stat['count_total'] = $kernel->archive->format_round_bytes( $stat['count_total'] ) . " <i>" . $kernel->ld['phrase_approximately'] . "</i>";
			}
			else
			{
				$stat['count_total'] = $kernel->format_input( $stat['count_total'], T_NUM );
			}
			
			$stat['count_name'] = $kernel->ld[ 'phrase_' . substr( $stat['count_name'], 6, strlen( $stat['count_name'] ) - 1 ) ];
			
			$kernel->tp->cache( $stat );
		}
		
		$kernel->tp->call( "admin_db_statistics_footer" );
		
		break;
	}
}

?>

