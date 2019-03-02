<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "id" => V_PINT ) );

//----------------------------------
// No ID ref
//----------------------------------

if( $kernel->vars['id'] == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_anno_specified'], M_ERROR );
}
else
{
	$get_announcements = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "announcements` WHERE `announcement_id` = " . $kernel->vars['id'] );
	
	//----------------------------------
	// Invalid ID
	//----------------------------------
	
	if( $kernel->db->numrows( $get_announcements ) == 0 )
	{
		$kernel->page->message_report( $kernel->ld['phrase_anno_no_exists'], M_ERROR );
	}
	else
	{
		$announcement = $kernel->db->data( $get_announcements );
		
		//----------------------------------
		// Is announcement active?
		//----------------------------------
		
		if( ( $announcement['announcement_from_timestamp'] > 0 OR $announcement['announcement_to_timestamp'] > 0 ) AND ( $announcement['announcement_from_timestamp'] > UNIX_TIME OR $announcement['announcement_to_timestamp'] < UNIX_TIME ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_anno_not_active'], M_ERROR );
		}
		else
		{
			//----------------------------------
			// Setup page vars
			//----------------------------------
			
			$kernel->vars['page_struct']['system_page_action_title'] = sprintf( $kernel->ld['phrase_page_title_announcement'], $announcement['announcement_title'] );
			$kernel->vars['page_struct']['system_page_navigation_html'] = array( SCRIPT_PATH => sprintf( $kernel->ld['phrase_page_title_announcement'], $announcement['announcement_title'] ) );
			if( $announcement['announcement_cat_id'] > 0 ) $kernel->vars['page_struct']['system_page_navigation_id'] = $announcement['announcement_cat_id'];
			
			//----------------------------------
			// Fetch announcement box template
			//----------------------------------
			
			$kernel->tp->call( "announcement_box_list" );

			$announcement['announcement_title'] = $kernel->format_input( $announcement['announcement_title'], T_HTML );
			$announcement['announcement_data'] = $kernel->format_input( $announcement['announcement_data'], T_HTML );
			$announcement['announcement_timestamp'] = $kernel->fetch_time( $announcement['announcement_timestamp'], DF_SHORT );
			$announcement['announcement_author'] = $kernel->format_input( $announcement['announcement_author'], T_HTML );
			$announcement['announcement_post_data'] = sprintf( $kernel->ld['phrase_posted_by'], $announcement['announcement_author'], $announcement['announcement_timestamp'] );

			$kernel->tp->cache( $announcement );
		}
	}
}

//----------------------------------
// Output page
//----------------------------------

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>