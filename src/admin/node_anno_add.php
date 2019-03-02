<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'ANO_ADD' );

switch( $kernel->vars['action'] )
{
	#############################################################################

	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array(
			"announcement_cat_id" => V_INT, "announcement_title" => V_STR, "announcement_data" => V_STR,
			"announcement_to_date" => V_STR, "announcement_to_hours" => V_STR, "announcement_to_minutes" => V_STR,
			"announcement_from_date" => V_STR, "announcement_from_hours" => V_STR, "announcement_from_minutes" => V_STR
		) );
		
		if( empty( $kernel->vars['announcement_title'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_announcement_title'], M_ERROR, HALT_EXEC );
		}
		
		if( !empty( $kernel->vars['announcement_from_date'] ) AND !empty( $kernel->vars['announcement_to_date'] ) )
		{
			if( $kernel->vars['announcement_from_date'] > $kernel->vars['announcement_to_date'] )
			{
				$kernel->page->message_report( $kernel->ld['phrase_announcement_date_invalid'], M_ERROR, HALT_EXEC );
			}
			
			if( $kernel->vars['announcement_from_date'] == $kernel->vars['announcement_to_date'] AND $kernel->vars['announcement_from_hours'] >= $kernel->vars['announcement_to_hours'] )
			{
				if( $kernel->vars['announcement_from_hours'] > $kernel->vars['announcement_to_hours'] )
				{
					$kernel->page->message_report( $kernel->ld['phrase_announcement_date_hours_invalid'], M_ERROR, HALT_EXEC );
				}
				
				if( $kernel->vars['announcement_from_hours'] == $kernel->vars['announcement_to_hours'] )
				{
					if( $kernel->vars['announcement_from_minutes'] > $kernel->vars['announcement_to_minutes'] )
					{
						$kernel->page->message_report( $kernel->ld['phrase_announcement_date_minutes_invalid'], M_ERROR, HALT_EXEC );
					}
				}
			}
		}
		
		$announcementdata = array(
			"announcement_cat_id" => $kernel->vars['announcement_cat_id'],
			"announcement_title" => $kernel->format_input( $kernel->vars['announcement_title'], T_DB ),
			"announcement_author" => $kernel->session->vars['adminsession_name'],
			"announcement_data" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['announcement_data'], T_DB ), $kernel->config['string_max_word_length'] ),
			"announcement_from_timestamp" => ( !empty( $kernel->vars['announcement_from_date'] ) ? strtotime( $kernel->vars['announcement_from_date'] . " " . $kernel->vars['announcement_from_hours'] . ":" . $kernel->vars['announcement_from_minutes'] . ":00" ) : 0 ),
			"announcement_to_timestamp" => ( !empty( $kernel->vars['announcement_to_date'] ) ? strtotime( $kernel->vars['announcement_to_date'] . " " . $kernel->vars['announcement_to_hours'] . ":" . $kernel->vars['announcement_to_minutes'] . ":59" ) : 0 ),
			"announcement_timestamp" => UNIX_TIME
		);
		
		$kernel->db->insert( "announcements", $announcementdata );
		
		$kernel->archive->update_database_counter( "announcements" );

		$kernel->admin->message_admin_report( "log_announcement_added", $kernel->vars['announcement_title'] );
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_anno_add" );
		
		for( $i = 0; $i <= 23; $i++ ) $hours[] = sprintf( "%02s", $i );
		for( $i = 0; $i <= 59; $i++ ) $minutes[] = sprintf( "%02s", $i );
		
		$kernel->archive->construct_list_options( 0, "from_hours", $hours, false );
		$kernel->archive->construct_list_options( 0, "from_minutes", $minutes, false );
		
		$kernel->vars['html']['to_hours_list_options'] = $kernel->vars['html']['from_hours_list_options'];
		$kernel->vars['html']['to_minutes_list_options'] = $kernel->vars['html']['from_minutes_list_options'];
		
		$kernel->page->construct_category_list( 0 );
		
		break;
	}
}

?>