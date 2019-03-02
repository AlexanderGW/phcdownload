<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'ANO_MAN', 'ANO_DEL' );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "announcement_id" => V_INT ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'ANO_MAN' );
		
		$kernel->tp->call( "admin_anno_edit" );
		
		$announcement = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "announcements` WHERE `announcement_id` = " . $kernel->vars['announcement_id'] . " LIMIT 1" );
		
		$kernel->page->construct_category_list( $announcement['announcement_cat_id'] );
		
		$announcement['announcement_title'] = $kernel->format_input( $announcement['announcement_title'], T_FORM );
		$announcement['announcement_data'] = $kernel->format_input( $announcement['announcement_data'], T_FORM );
		$announcement['announcement_from_date'] = ( !empty( $announcement['announcement_from_timestamp'] ) ? date( "Y-m-d", $announcement['announcement_from_timestamp'] ) : "" );
		$announcement['announcement_to_date'] = ( !empty( $announcement['announcement_to_timestamp'] ) ? date( "Y-m-d", $announcement['announcement_to_timestamp'] ) : "" );
		
		for( $i = 0; $i <= 23; $i++ ) $hours[ sprintf( "%02s", $i ) ] = sprintf( "%02s", $i );
		for( $i = 0; $i <= 59; $i++ ) $minutes[ sprintf( "%02s", $i ) ] = sprintf( "%02s", $i );
		
		$kernel->archive->construct_list_options( ( !empty( $announcement['announcement_from_timestamp'] ) ? date( "H", $announcement['announcement_from_timestamp'] ) : 0 ), "from_hours", $hours, false );
		$kernel->archive->construct_list_options( ( !empty( $announcement['announcement_from_timestamp'] ) ? date( "i", $announcement['announcement_from_timestamp'] ) : 0 ), "from_minutes", $minutes, false );
		
		$kernel->archive->construct_list_options( ( !empty( $announcement['announcement_to_timestamp'] ) ? date( "H", $announcement['announcement_to_timestamp'] ) : 0 ), "to_hours", $hours, false );
		$kernel->archive->construct_list_options( ( !empty( $announcement['announcement_to_timestamp'] ) ? date( "i", $announcement['announcement_to_timestamp'] ) : 0 ), "to_minutes", $minutes, false );
		
		if( $announcement['announcement_from_timestamp'] > 0 AND $announcement['announcement_from_timestamp'] > UNIX_TIME )
		{
			$announcement['announcement_from_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_not_reached'], "#ff3333" );
		}
		elseif( $announcement['announcement_from_timestamp'] > 0 AND $announcement['announcement_from_timestamp'] < UNIX_TIME )
		{
			$announcement['announcement_from_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_reached'], "#66cc66" );
		}
		else
		{
			$announcement['announcement_from_date_remark'] = "&nbsp;";
		}
		
		if( $announcement['announcement_to_timestamp'] > 0 AND $announcement['announcement_to_timestamp'] > UNIX_TIME )
		{
			$announcement['announcement_to_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_not_reached'], "#66cc66" );
		}
		elseif( $announcement['announcement_to_timestamp'] > 0 AND $announcement['announcement_to_timestamp'] < UNIX_TIME )
		{
			$announcement['announcement_to_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_reached'], "#ff3333" );
		}
		else
		{
			$announcement['announcement_to_date_remark'] = "&nbsp;";
		}
		
		$kernel->tp->cache( $announcement );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'ANO_MAN' );
		
		$kernel->clean_array( "_POST", array(
			"announcement_cat_id" => V_INT, "announcement_title" => V_STR, "announcement_data" => V_STR,
			"announcement_to_date" => V_STR, "announcement_to_hours" => V_STR, "announcement_to_minutes" => V_STR,
			"announcement_from_date" => V_STR, "announcement_from_hours" => V_STR, "announcement_from_minutes" => V_STR
		) );
		
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
			"announcement_author" => $kernel->format_input( $kernel->session->vars['adminsession_name'], T_DB ),
			"announcement_title" => $kernel->format_input( $kernel->vars['announcement_title'], T_DB ),
			"announcement_data" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['announcement_data'], T_DB ), $kernel->config['string_max_word_length'] ),
			"announcement_from_timestamp" => ( !empty( $kernel->vars['announcement_from_date'] ) ? strtotime( $kernel->vars['announcement_from_date'] . " " . $kernel->vars['announcement_from_hours'] . ":" . $kernel->vars['announcement_from_minutes'] . ":00" ) : 0 ),
			"announcement_to_timestamp" => ( !empty( $kernel->vars['announcement_to_date'] ) ? strtotime( $kernel->vars['announcement_to_date'] . " " . $kernel->vars['announcement_to_hours'] . ":" . $kernel->vars['announcement_to_minutes'] . ":59" ) : 0 ),
			"announcement_timestamp" => UNIX_TIME
		);
		
		if( !empty( $announcementdata['announcement_from_timestamp'] ) OR !empty( $announcementdata['announcement_to_timestamp'] ) )
		{
			if( $announcementdata['announcement_from_timestamp'] > UNIX_TIME OR $announcementdata['announcement_to_timestamp'] < UNIX_TIME )
			{
				$announcementdata['announcement_disabled'] = 1;
			}
		}

		$kernel->db->update( "announcements", $announcementdata, "WHERE announcement_id = " . $kernel->vars['announcement_id'] );
		
		$kernel->archive->update_database_counter( "announcements" );
		
		$kernel->admin->message_admin_report( "log_announcement_edited", $kernel->vars['announcement_title'] );
		
		break;
	}
	
	#############################################################################
	
	case "delete" :
	{
		$kernel->admin->read_permission_flags( 'ANO_DEL' );
		
		$delete_count = 0;
		
		if( $kernel->vars['announcement_id'] != "" )
		{
			$delete_data = $kernel->db->item( "SELECT `announcement_title` FROM `" . TABLE_PREFIX . "announcements` WHERE `announcement_id` = " . $kernel->vars['announcement_id'] );
			
			$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "announcements` WHERE `announcement_id` = " . $kernel->vars['announcement_id'] );
			$delete_count++;
		}
		elseif( is_array( $_POST['checkbox'] ) )
		{
			foreach( $_POST['checkbox'] AS $announcement )
			{
				$delete_data[] = $kernel->db->item( "SELECT `announcement_title` FROM `" . TABLE_PREFIX . "announcements` WHERE `announcement_id` = " . $announcement );
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "announcements` WHERE `announcement_id` = " . $announcement );
				$delete_count++;
			}
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
		}
		
		$kernel->archive->update_database_counter( "announcements" );
		
		$kernel->admin->message_admin_report( "log_announcement_deleted", $delete_count, $delete_data );
		
		break;
	}
	
	#############################################################################
	
	default :
	{		
		$check_announcements = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "announcements` ORDER BY `announcement_id`" );
		
		if( $kernel->db->numrows( $check_announcements ) == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_announcements'], M_ERROR );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_announcements ) );
			
			$kernel->tp->call( "admin_anno_header" );
			
			$get_announcements = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "announcements` ORDER BY `announcement_id` LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			while( $announcement = $kernel->db->data( $get_announcements ) )
			{
				$kernel->tp->call( "admin_anno_row" );
				
				$category = $kernel->db->row( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $announcement['announcement_cat_id'] );
				
				$announcement['announcement_title'] = $kernel->format_input( $announcement['announcement_title'], T_NOHTML );
				$announcement['announcement_data'] = $kernel->archive->return_string_words( $kernel->format_input( $announcement['announcement_data'], T_NOHTML ), $kernel->config['string_max_words'] );
				$announcement['announcement_cat_id'] = ( $announcement['announcement_cat_id'] == 0 ) ? $kernel->ld['phrase_global'] : $category['category_name'] ;
				
				$kernel->tp->cache( $announcement );
			}
			
			$kernel->tp->call( "admin_anno_footer" );
			
			$kernel->page->construct_category_filters();
			
			$kernel->page->construct_pagination( array(), $kernel->config['admin_pagination_page_proximity'] );
		}
		
		break;
	}
}

?>

