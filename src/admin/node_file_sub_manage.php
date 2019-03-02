<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'SUB_MAN', 'SUB_DEL', 'FIL_ADD' );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "submission_id" => V_INT ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'SUB_MAN' );
		
		$kernel->tp->call( "admin_sub_edit" );
		
		$submission = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $kernel->vars['submission_id'] . " LIMIT 1" );
		
		$kernel->page->construct_category_list( $submission['submission_cat_id'] );
		
		$kernel->tp->cache( $submission );
		
		$kernel->archive->construct_file_custom_fields_form( $kernel->vars['submission_id'] );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'SUB_MAN' );
		
		//error-check custom fields
		$kernel->archive->verify_custom_field_values( $_POST );
		
		//clean db associated vars
		$kernel->clean_array( "_POST", array(
			"submission_cat_id" => V_INT, "submission_name" => V_STR, "submission_author" => V_STR,
			"submission_dl_url" => V_STR, "submission_version" => V_STR, "submission_description" => V_STR,
			"submission_information" => V_STR
		) );
		
		$submissiondata = array(
			"submission_cat_id" => $kernel->vars['submission_cat_id'],
			"submission_name" => $kernel->format_input( $kernel->vars['submission_name'], T_DB ),
			"submission_author" => $kernel->format_input( $kernel->vars['submission_author'], T_DB ),
			"submission_version" => $kernel->format_input( $kernel->vars['submission_version'], T_DB ),
			"submission_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['submission_description'], T_DB ), $kernel->config['string_max_word_length'] ),
			"submission_information" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['submission_information'], T_DB ), $kernel->config['string_max_word_length'] ),
			"submission_dl_url" => $kernel->format_input( $kernel->vars['submission_dl_url'], T_DB )
		);
		
		//update file data
		$kernel->db->update( "submissions", $submissiondata, "WHERE `submission_id` = " . $kernel->vars['submission_id'] );
		
		$kernel->archive->update_database_counter( "submissions" );
		
		//update file custom fields
		$kernel->archive->construct_db_write_custom_fields( $kernel->vars['submission_id'], $_POST );
		
		//done
		$kernel->admin->message_admin_report( "log_submission_edited", $kernel->vars['submission_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "manage" :
	{
		$count = 0;
		
		if( isset( $_POST['form_delete'] ) OR $kernel->vars['submission_id'] > 0 )
		{
			$kernel->admin->read_permission_flags( 'SUB_DEL' );
			
			if( $kernel->vars['submission_id'] > 0 )
			{
				$delete_data[] = $kernel->db->item( "SELECT `submission_name` FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $kernel->vars['submission_id'] );
				
				if( $kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $kernel->vars['submission_id'] ) )
				{
					$count++;
				}
			}
			elseif( is_array( $_POST['checkbox'] ) )
			{
				foreach( $_POST['checkbox'] AS $submission )
				{
					$delete_data[] = $kernel->db->item( "SELECT `submission_name` FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $submission );
					
					if( $kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $submission ) )
					{
						$count++;
					}
				}
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
			}
			
			if( $count > 0 )
			{
				$kernel->archive->update_database_counter( "submissions" );
				
				$kernel->admin->message_admin_report( "log_submission_deleted", $count, $delete_data );
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_items_were_deleted'], M_ERROR );
			}
		}
		
		###########################################################################
		
		elseif( isset( $_POST['form_approve'] ) )
		{
			$kernel->admin->read_permission_flags( 'FIL_ADD' );
			
			if( is_array( $_POST['checkbox'] ) )
			{
				$last_id = "";
				
				foreach( $_POST['checkbox'] AS $submission_id )
				{
					$submission = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $submission_id . " LIMIT 1" );
					
					if( $submission['submission_id'] == $last_id ) continue;
					
					$file_info = $kernel->archive->file_url_info( $submission['submission_dl_url'] );
					
					//filetype not allowed
					if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 ) continue;
					
					$file_size = $kernel->archive->parse_url_size( $submission['submission_dl_url'], $kernel->config['system_parse_timeout'] );
					
					if( empty( $file_size ) ) $file_size = 0;
					
					$filedata = array(
						"file_cat_id" => $submission['submission_cat_id'],
						"file_pinned" => 0,
						"file_icon" => $file_info['file_icon'],
						"file_name" => $kernel->format_input( $submission['submission_name'], T_DB ),
						"file_author" => $kernel->format_input( $submission['submission_author'], T_DB ),
						"file_version" => $kernel->format_input( $submission['submission_version'], T_DB ),
						"file_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $submission['submission_description'], T_DB ), $kernel->config['string_max_word_length'] ),
						"file_image_array" => $kernel->format_input( $submission['submission_image_array'], T_DB ),
						"file_timestamp" => UNIX_TIME,
						"file_mark_timestamp" => UNIX_TIME,
						"file_rating" => 0,
						"file_votes" => 0,
						"file_downloads" => 0,
						"file_size" => $file_size,
						"file_dl_url" => $kernel->format_input( $submission['submission_dl_url'], T_DB )
					);
					
					$kernel->db->insert( "files", $filedata );
					
					//Migrate Custom Fields
					$file = $kernel->db->row( "SELECT `file_id` FROM `" . TABLE_PREFIX . "files` ORDER BY `file_id` DESC LIMIT 1" );
					
					$get_fields = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_submission_id` = " . $submission_id );
					
					if( $kernel->db->numrows() > 0 )
					{
						while( $field = $kernel->db->data( $get_fields ) )
						{
							$fielddata = array(
								"field_id" => $field['field_id'],
								"field_file_id" => $file['file_id'],
								"field_file_data" => $kernel->format_input( $field['field_file_data'], T_DB )
							);
							
							$kernel->db->insert( "fields_data", $fielddata );
							
							$add_data[] = $kernel->db->item( "SELECT `submission_name` FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $submission_id );
						}
					}
					
					if( $_POST['option_del_submission'] == "1" )
					{
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "submissions` WHERE `submission_id` = " . $submission_id . " LIMIT 1" );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_submission_id` = " . $submission_id );
					}
					
					$count++;
					$last_file = $submission['submission_id'];
					
					$kernel->archive->update_category_file_count( $submission['submission_cat_id'] );
					$kernel->archive->update_category_new_file( $submission['submission_cat_id'] );
					
					unset( $submission );
				}
				
				//resync categories and other bits
				$kernel->archive->global_category_syncronisation();
				
				$kernel->archive->update_database_counter( "submissions" );
				$kernel->archive->update_database_counter( "files" );
				$kernel->archive->update_database_counter( "data" );
				
				//done
				$kernel->admin->message_admin_report( "log_submission_approved", $count, $add_data );
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_submissions_selected'], M_ERROR );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$check_submissions = $kernel->db->query( "SELECT s.submission_id, s.submission_name, s.submission_description, s.submission_timestamp, s.submission_ip, u.user_name FROM " . TABLE_PREFIX . "submissions s LEFT JOIN " . TABLE_PREFIX . "users u ON( s.submission_author_id = u.user_id ) ORDER BY `submission_name`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_submissions'], M_ERROR );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_submissions ) );
			
			if( $kernel->config['admin_file_submit_approval'] != "true" )
			{
				$kernel->page->message_report( $kernel->ld['phrase_moderator_approve_disabled'], M_ALERT );
			}
			
			$kernel->tp->call( "admin_sub_header" );
			
			$get_submissions = $kernel->db->query( "SELECT s.submission_id, s.submission_name, s.submission_description, s.submission_information, s.submission_timestamp, s.submission_dl_url, s.submission_ip, s.submission_author, u.user_name FROM " . TABLE_PREFIX . "submissions s LEFT JOIN " . TABLE_PREFIX . "users u ON( s.submission_author_id = u.user_id ) ORDER BY s.submission_name LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			while( $submission = $kernel->db->data( $get_submissions ) )
			{
				$kernel->tp->call( "admin_sub_row" );
				
				$submission['submission_html_name'] = "";
				$submission['submission_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $submission['submission_description'], T_NOHTML ), $kernel->config['string_max_words'] );
				$submission['submission_information'] = $kernel->format_input( $submission['submission_information'], T_NOHTML );
				$submission['submission_name'] = $kernel->format_input( $submission['submission_name'], T_NOHTML );
				$submission['submission_timestamp'] = $kernel->fetch_time( $submission['submission_timestamp'], DF_LONG );
				
				$submission['user_name'] = ( empty( $submission['user_name'] ) ) ? $kernel->ld['phrase_guest'] : $submission['user_name'];
				
				//submission archive checker
				$submission_alt_text = "";
				
				$check_file_name = $kernel->db->numrows( "SELECT `file_name` FROM " . TABLE_PREFIX . "files WHERE `file_name` = '" . $submission['submission_name'] . "' AND `file_author` = '" . $submission['submission_author'] . "'" );
				
				$file_info = $kernel->archive->file_url_info( $submission['submission_dl_url'] );
				
				$check_archive_file_name = $kernel->db->numrows( "SELECT `file_name` FROM " . TABLE_PREFIX . "files WHERE `file_name` = '" . $file_info['file_name'] . "' AND `file_author` = '" . $submission['submission_author'] . "'" );
				
				if( $check_file_name > 0 )
				{
					$submission_alt_text .= sprintf( $kernel->ld['phrase_submission_match_filename'], $check_file_name );
				}
				
				if( $check_archive_file_name > 0 )
				{
					if( !empty( $submission_alt_text ) ) $submission_alt_text .= ", ";
					
					$submission_alt_text .= sprintf( $kernel->ld['phrase_submission_match_archive_name'], $check_file_name );
				}
				
				if( $check_file_name > 0 OR $check_archive_file_name > 0 )
				{
					$submission['submission_html_name'] = $kernel->admin->construct_icon( "alert.gif", $submission_alt_text, true );
				}
				
				$submission['submission_html_name'] .= $submission['submission_name'];
				
				$kernel->tp->cache( $submission );
			}
			
			$kernel->tp->call( "admin_sub_footer" );
			
			$kernel->page->construct_category_filters();
			
			$kernel->page->construct_pagination( array(), $kernel->config['admin_pagination_page_proximity'] );
		}
		
		break;
	}
}

?>

