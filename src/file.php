<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "id" => V_INT, "voted" => V_BOOL, "posted" => V_BOOL ) );

//----------------------------------
// No ID
//----------------------------------

if( $kernel->vars['id'] == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_file_specified'], M_ERROR );
}
else
{
	$get_file = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $kernel->vars['id'] );
	
	//----------------------------------
	// Invalid ID
	//----------------------------------
	
	if( $kernel->db->numrows() == 0 )
	{
		$kernel->page->message_report( $kernel->ld['phrase_file_no_exists'], M_ERROR );
	}
	else
	{
		//----------------------------------
		// Download file, skip file details
		//----------------------------------
		
		if( $kernel->config['display_file_list_mode'] == 1 )
		{
			header( "Location: download.php?id=" . $kernel->vars['id'] ); exit;
		}
		
		$file = $kernel->db->data( $get_file );
		
		//----------------------------------
		// Setup page vars
		//----------------------------------
		
		$kernel->vars['page_struct']['system_page_action_title'] = sprintf( $kernel->ld['phrase_page_title_file'], $file['file_name'] );
		$kernel->vars['page_struct']['system_page_navigation_id'] = $file['file_cat_id'];
		$kernel->vars['page_struct']['system_page_navigation_html'] = array( SCRIPT_PATH => $file['file_name'] );
		
		//----------------------------------
		// Check category permissions
		//----------------------------------
		
		$kernel->page->read_category_permissions( $file['file_cat_id'], SCRIPT_PATH );
		
		//----------------------------------
		// Check for subscriptions
		//----------------------------------
		
		$kernel->subscription->init_category_subscriptions( $file['file_cat_id'], SCRIPT_PATH );
		
		//----------------------------------
		// File disabled
		//----------------------------------
		
		if( $kernel->archive->synchronise_file_status() == false )
		{
			$kernel->page->message_report( $kernel->ld['phrase_file_status_disabled'], M_NOTICE );
		}
		else
		{
			//----------------------------------
			// Submitted vote notice
			//----------------------------------
			
			if( $kernel->vars['voted'] == true )
			{
				$kernel->page->message_report( $kernel->ld['phrase_vote_submitted'], M_NOTICE );
			}
			
			//----------------------------------
			// Submitted comment notice
			//----------------------------------
			
			if( $kernel->vars['posted'] == true )
			{
				$kernel->page->message_report( $kernel->ld['phrase_comment_submitted'], M_NOTICE );
			}
			
			//----------------------------------
			// Increment file views counter
			//----------------------------------
			
			$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "files` SET `file_views` = file_views + 1 WHERE `file_id` = " . $kernel->vars['id'] );
			
			//----------------------------------
			// Call file template
			//----------------------------------
			
			$kernel->tp->call( "file_view" );
			
			//----------------------------------
			// Get URL file info
			//----------------------------------
			
			$file_info = $kernel->archive->file_url_info( $file['file_dl_url'] );
			
			//----------------------------------
			// Setup file page data
			//----------------------------------
			
			$kernel->archive->construct_download_counters( $file['file_size'] );
			
			//----------------------------------
			// Setup file hashes
			//----------------------------------
			
			$kernel->archive->construct_file_hash_fields( $file['file_hash_data'] );
			
			//----------------------------------
			// Setup file tags
			//----------------------------------
			
			$kernel->archive->construct_file_tags_field( $file['file_id'] );
			
			//----------------------------------
			// Setup file vars
			//----------------------------------
			
			$file['file_size'] = $kernel->archive->format_round_bytes( $file['file_size'] );
			$file['file_rank'] = $kernel->archive->construct_file_rating( $file['file_rating'], $file['file_votes'] );
			$file['file_type'] = $file_info['file_type'];
			$file['file_timestamp'] = $kernel->fetch_time( $file['file_timestamp'], DF_LONG );
			$file['file_downloads'] = $kernel->format_input( $file['file_downloads'], T_NUM );
			$file['file_votes'] = $kernel->format_input( $file['file_votes'], T_NUM );
			$file['file_name'] = $kernel->format_input( $file['file_name'], T_STR );
			$file['file_html_author'] = $kernel->format_input( $file['file_author'], T_URL_ENC );
			$file['file_views'] = $kernel->format_input( $file['file_views'], T_NUM );
			
			if( empty( $file['file_description'] ) )
			{
				$file['file_description'] = '<i>' . $kernel->ld['phrase_file_no_description'] . '</i>';
			}
			else
			{
				$file['file_description'] = $kernel->format_input( $file['file_description'], T_HTML );
			}
			
			$kernel->ld['phrase_sent_rankvotes'] = sprintf( $kernel->ld['phrase_sent_rankvotes'], $file['file_votes'] );
			
			//----------------------------------
			// Setup custom fields and file data
			//----------------------------------
			
			$file['file_custom_fields'] = "";
			
			$get_fields = $kernel->db->query( "SELECT `field_id`, `field_name` FROM `" . TABLE_PREFIX . "fields` WHERE `field_file_display` = 1 ORDER BY `field_name`" );
			
			if( $kernel->db->numrows() > 0 )
			{
				while( $field = $kernel->db->data( $get_fields ) )
				{
					$field_data = $kernel->db->row( "SELECT `field_file_data` FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_id` = " . $field['field_id'] . " AND `field_file_id` = " . $kernel->vars['id'] );
					
					if( $kernel->config['archive_show_empty_custom_fields'] == 0 AND empty( $field_data['field_file_data'] ) ) continue;
					
					if( !empty( $field_data['field_file_data'] ) )
					{
						$fielddata['field_file_data'] = $kernel->format_input( $field_data['field_file_data'], T_HTML );
					}
					else
					{
						$fielddata['field_file_data'] = "&nbsp;";
					}
					
					$file['file_custom_fields'] .= $kernel->tp->call( "file_custom_field", CALL_TO_PAGE );
					
					$file['file_custom_fields'] = $kernel->tp->cache( "field_name", $field['field_name'], $file['file_custom_fields'] );
					$file['file_custom_fields'] = $kernel->tp->cache( "field_file_data", $fielddata['field_file_data'], $file['file_custom_fields'] );
				}
			}
			
			$kernel->tp->cache( $file );
		}
		
		//----------------------------------
		// Check and sort permissions for hiding inaccessible features
		//----------------------------------
		
		if( $kernel->session->read_permission_flag( 'FIL_DWN' ) == false ) $kernel->ld['phrase_download_now'] = "";
		if( $kernel->session->read_permission_flag( 'FIL_SRH' ) == false ) $kernel->ld['phrase_more_from_developer'] = "";
		if( $kernel->session->read_permission_flag( 'FIL_RAT' ) == false ) $kernel->ld['phrase_rate_file'] = "";
		if( ( $kernel->session->read_permission_flag( 'COM_POS' ) AND $kernel->session->read_permission_flag( 'COM_VEW' ) ) == false ) $kernel->ld['phrase_view_comments'] = "";
		if( $kernel->session->read_permission_flag( 'GAL_VEW' ) == false ) $kernel->ld['phrase_view_gallery'] = "";
		
		//----------------------------------
		// Check comment view permissions
		//----------------------------------
		
		if( $kernel->session->read_permission_flag( 'COM_VEW' ) )
		{
			$get_comments = $kernel->db->query( "SELECT c.comment_file_id, c.comment_title, c.comment_data, c.comment_timestamp, u.user_name FROM " . TABLE_PREFIX . "comments c LEFT JOIN " . TABLE_PREFIX . "users u ON( c.comment_author_id = u.user_id ) WHERE c.comment_file_id = " . $kernel->vars['id'] . " ORDER BY c.comment_id DESC LIMIT " . $kernel->config['archive_max_comment_on_page'] );
			
			if( $kernel->db->numrows( $get_comments ) > 0 )
			{
				$kernel->tp->call( "comment_header" );
				
				while( $comment = $kernel->db->data( $get_comments ) )
				{
					$kernel->tp->call( "comment_row" );
					
					$comment['user_name'] = empty( $comment['user_name'] ) ? $kernel->ld['phrase_guest'] : $comment['user_name'];
					$comment['comment_title'] = $kernel->format_input( $comment['comment_title'], T_HTML );
					$comment['comment_data'] = $kernel->format_input( $comment['comment_data'], T_HTML );
					$comment['user_name'] = $kernel->format_input( $comment['user_name'], T_HTML );
					$comment['comment_timestamp'] = $kernel->fetch_time( $comment['comment_timestamp'], DF_SHORT );
					$comment['comment_post_data'] = sprintf( $kernel->ld['phrase_posted_by'], $comment['user_name'], $comment['comment_timestamp'] );
					
					$kernel->tp->cache( $comment );
				}
				
				$kernel->tp->call( "comment_footer" );
			}
		}
	}
}

//----------------------------------
// Output page
//----------------------------------

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>