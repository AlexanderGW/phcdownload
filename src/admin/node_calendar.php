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
		$kernel->tp->call( "admin_calendar" );
		
		if( empty( $_REQUEST['year'] ) ) $_REQUEST['year'] = date( 'Y', UNIX_TIME );
		if( empty( $_REQUEST['month'] ) ) $_REQUEST['month'] = date( 'm', UNIX_TIME );
		if( empty( $_REQUEST['day'] ) ) $_REQUEST['day'] = date( 'd', UNIX_TIME );
		
		$_REQUEST['working_time'] = strtotime( $_REQUEST['year'] . '-' . $_REQUEST['month'] . '-' . $_REQUEST['day'] . ' 00:00:00' );
		$_REQUEST['day_of_week'] = date( 'w', $_REQUEST['working_time'] );
		$_REQUEST['day_total'] = date( 't', $_REQUEST['working_time'] );
		
		$next_month_stamp = $_REQUEST['working_time'] + ( 86400 * $_REQUEST['day_total'] - $_REQUEST['day'] );
		$previous_month_stamp = $_REQUEST['working_time'] - ( 86400 * $_REQUEST['day_total'] - $_REQUEST['day'] );
		
		$calendar['calendar_back_month'] = "index.php?hash=" . $kernel->session->vars['hash'] . "&amp;node=calendar&amp;element=" . $_REQUEST['element'] . "&amp;month=" . date( 'm', $previous_month_stamp ) . "&amp;year=" . date( 'Y', $previous_month_stamp );
		$calendar['calendar_next_month'] = "index.php?hash=" . $kernel->session->vars['hash'] . "&amp;node=calendar&amp;element=" . $_REQUEST['element'] . "&amp;month=" . date( 'm', $next_month_stamp ) . "&amp;year=" . date( 'Y', $next_month_stamp );
		$calendar['calendar_back_year'] = "index.php?hash=" . $kernel->session->vars['hash'] . "&amp;node=calendar&amp;element=" . $_REQUEST['element'] . "&amp;month=" . date( 'm', $_REQUEST['working_time'] ) . "&amp;year=" . ( date( 'Y', $previous_month_stamp ) - 1 );
		$calendar['calendar_next_year'] = "index.php?hash=" . $kernel->session->vars['hash'] . "&amp;node=calendar&amp;element=" . $_REQUEST['element'] . "&amp;month=" . date( 'm', $_REQUEST['working_time'] ) . "&amp;year=" . ( date( 'Y', $next_month_stamp ) + 1 );
		$calendar['calendar_event_date'] = date( "Y F", $_REQUEST['working_time'] );
		
		$kernel->tp->cache( $calendar );
		$calendar = array();
		
		$calendar_first_day = date( 'w', strtotime( $_REQUEST['year'] . '-' . $_REQUEST['month'] . '-01 00:00:00' ) );

		//Pad the calander table for current day of week.
		for( $i = $calendar_first_day; $i > 0; $i-- ) $kernel->vars['html']['calendar_day_cells'] .= $kernel->tp->call( "admin_calendar_cell_blank", CALL_TO_PAGE );
		
		//Work through calendar days
		for( $i = 1; $i <= $_REQUEST['day_total']; $i++ )
		{
			$calendar['calendar_event_time'] = strtotime( $_REQUEST['year'] . '-' . $_REQUEST['month'] . '-' . $i . ' 00:00:00' );
			
			//Start new row after each week ending.
			if( $calendar_first_day > 6 )
			{
				$kernel->vars['html']['calendar_day_cells'] .= $kernel->tp->call( "admin_calendar_cell_week_break", CALL_TO_PAGE );
				$calendar_first_day = 0;
			}
			
			$calendar['calendar_event_caption'] = date( 'd', $calendar['calendar_event_time'] );
			$calendar['calendar_event_time_string'] = date( "Y-m-d", $calendar['calendar_event_time'] );
			$calendar['calendar_element'] = $_REQUEST['element'];
		
			//Is its todays calendar day?
			if( date( 'Y m d', UNIX_TIME ) === date( 'Y m d', $calendar['calendar_event_time'] ) )
			{
				$kernel->vars['html']['calendar_day_cells'] .= $kernel->tp->call( "admin_calendar_cell_today", CALL_TO_PAGE );
				$kernel->vars['html']['calendar_day_cells'] = $kernel->tp->cache( $calendar, 0, $kernel->vars['html']['calendar_day_cells'] );
			}
			else
			{
				$kernel->vars['html']['calendar_day_cells'] .= $kernel->tp->call( "admin_calendar_cell_day", CALL_TO_PAGE );
				$kernel->vars['html']['calendar_day_cells'] = $kernel->tp->cache( $calendar, 0, $kernel->vars['html']['calendar_day_cells'] );
			}
			
			$calendar_first_day++;
		}
		
		//Pad the calander table to week ending.
		for( $i = $calendar_first_day; $i < 7; $i++ ) $kernel->vars['html']['calendar_day_cells'] .= $kernel->tp->call( "admin_calendar_cell_blank", CALL_TO_PAGE );
		
		break;
	}
}

?>

