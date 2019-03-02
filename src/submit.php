<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "action" => V_STR ) );

$kernel->vars['page_struct']['system_page_action_title'] = $kernel->ld['phrase_page_title_submit'];

switch( $kernel->vars['action'] )
{
	#############################################################################
	# add file
	
	case "create" :
	{
		//Check permissions
		if( $kernel->session->read_permission_flag( 'FIL_SUB', true ) == true )
		{
			//clean db associated vars
			$kernel->clean_array( "_POST", array(
  			"submission_cat_id" => V_INT, "submission_name" => V_STR, "submission_author" => V_STR,
			"submission_dl_url" => V_STR, "submission_version" => V_STR, "submission_description" => V_STR,
			"submission_information" => V_STR, "user_verify_key" => V_STR, "user_verify_hash" => V_STR
			) );
			
			// Check image security hash
			$kernel->page->verify_security_image_details( null );
			
			//file link method not provided
			if( empty( $kernel->vars['submission_dl_url'] ) AND empty( $_FILES['file_upload']['name'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_submit_no_url_upload'], M_ERROR, HALT_EXEC );
			}
			
			//Attempting to add a file outside/behind the upload URL folder.
			if( strstr( $kernel->vars['submission_dl_url'], $kernel->config['system_root_url_home'] ) == true AND strstr( $kernel->vars['submission_dl_url'], $kernel->config['system_root_url_upload'] ) != true )
			{
				$kernel->page->message_report( $kernel->ld['phrase_file_url_behind_upload_folder'], M_ERROR, HALT_EXEC );
			}
			
			//no category specified
			if( $kernel->vars['submission_cat_id'] == 0 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_category_selected'], M_ERROR, HALT_EXEC );
			}
			
			//prep file data for updating
			if( !empty( $_FILES['file_upload']['name'] ) )
			{
				$file_info = $kernel->archive->file_url_info( $_FILES['file_upload']['name'] );
			}
			else
			{
				$file_info = $kernel->archive->file_url_info( $kernel->vars['submission_dl_url'] );
			}
			
			//check for upload errors if applicable
			$kernel->page->verify_upload_details();
			
			//filetype not allowed
			if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['session_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_bad_filetype'], M_ERROR, HALT_EXEC );
			}
			
			###########################################################################
			# Upload
			
			if( !empty( $_FILES['file_upload']['name'] ) )
			{
				//build URL to the specified upload folder, base upload folder url. add sub directories if applicable. (DIR upload path)
				$file_base_dir = $kernel->config['system_root_dir_upload'] . $kernel->vars['current_directory'] . DIR_STEP;
				$file_full_dir = $kernel->config['system_root_dir_upload'] . substr( $file_base_dir, strlen( $kernel->config['system_root_dir_upload'] ), strlen( $file_base_dir ) );
				
				//build URL base upload folder url. add sub directories if applicable. (URL upload path)
				$file_base_url = $kernel->config['system_root_url_upload'] . $kernel->vars['current_directory'] . DIR_STEP;
				$file_full_url = $kernel->config['system_root_url_upload'] . substr( $file_base_url, strlen( $kernel->config['system_root_url_upload'] ), strlen( $file_base_url ) );
				
				//conver backslashes into forward slashes for windows servers.
				$file_full_url = preg_replace( "/\\\\/", "/", $file_full_url );
				
				//add trailing slash if it doesnt exist.
				preg_match( "/\/$/", $file_full_url, $matches );
				
				if( !isset( $matches[0] ) ) $file_full_url .= "/";
				
				//check for exisiting files under provided file name
				$new_submission_name = $kernel->page->construct_upload_file_name( $_FILES['file_upload']['name'], $file_full_url );
				
				//upload
				if( !@move_uploaded_file( $_FILES['file_upload']['tmp_name'], $file_full_dir . $new_submission_name ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_could_not_move_upload_file'], M_ERROR, HALT_EXEC );
				}
				else
				{
					$kernel->vars['submission_dl_url'] = $file_full_url . $new_submission_name;
					
					if( empty( $kernel->vars['submission_name'] ) )
					{
						$kernel->vars['submission_name'] = $new_submission_name;
					}
					
					$file_size = $_FILES['file_upload']['size'];
				}
			}
				
			###########################################################################
			# URL
			
			else
			{
				if( empty( $kernel->vars['submission_name'] ) )
				{
					$kernel->vars['submission_name'] = $file_info['submission_name'];
				}
			}
			
			if( empty( $kernel->session->vars['session_user_id'] ) )
			{
				$submission_author_id = 0;
			}
			else
			{
				$submission_author_id = $kernel->session->vars['session_user_id'];
			}
			
			###########################################################################
				
			$submissiondata = array(
				"submission_cat_id" => $kernel->vars['submission_cat_id'],
				"submission_name" => $kernel->format_input( $kernel->vars['submission_name'], T_DB ),
				"submission_author_id" => $submission_author_id,
				"submission_author" => $kernel->format_input( $kernel->vars['submission_author'], T_DB ),
				"submission_version" => $kernel->format_input( $kernel->vars['submission_version'], T_DB ),
				"submission_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['submission_description'], T_DB ), $kernel->config['string_max_word_length'] ),
				"submission_information" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['submission_information'], T_DB ), $kernel->config['string_max_word_length'] ),
				"submission_timestamp" => UNIX_TIME,
				"submission_dl_url" => $kernel->format_input( $kernel->vars['submission_dl_url'], T_DB ),
				"submission_ip" => IP_ADDRESS
			);
			
			$submissiondata['submission_image_array'] = $kernel->archive->construct_upload_images_list( $_FILES['image_upload'] );
			
			$kernel->db->insert( "submissions", $submissiondata );
			
			//write file custom fields
			$kernel->archive->construct_db_write_custom_fields( $kernel->db->insert_id(), $_POST );
			
			$kernel->archive->update_database_counter( "submissions" );
			
			// Add directly to archive
			if( $kernel->config['admin_file_submit_approval'] == "false" )
			{
				$file_size = $kernel->archive->parse_url_size( $kernel->vars['submission_dl_url'], $config['system_parse_timeout'] );
				
				$filedata = array(
					"file_cat_id" => $kernel->vars['submission_cat_id'],
					"file_gallery_id" => 0,
					"file_pinned" => 0,
					"file_icon" => 0,
					"file_name" => $kernel->format_input( $kernel->vars['submission_name'], T_DB ),
					"file_author" => $kernel->format_input( $kernel->vars['submission_author'], T_DB ),
					"file_version" => $kernel->format_input( $kernel->vars['submission_version'], T_DB ),
					"file_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['submission_description'], T_DB ), $kernel->config['string_max_word_length'] ),
					"file_timestamp" => UNIX_TIME,
					"file_mark_timestamp" => UNIX_TIME,
					"file_rating" => 0,
					"file_votes" => 0,
					"file_downloads" => 0,
					"file_dl_limit" => 0,
					"file_size" => $file_size,
					"file_doc_id" => 0,
					"file_dl_url" => $kernel->format_input( $kernel->vars['submission_dl_url'], T_DB )
				);
				
				if( !empty( $submissiondata['submission_image_array'] ) )
				{
					$filedata['file_image_array'] = $submissiondata['submission_image_array'];
				}
				
				$kernel->db->insert( "files", $filedata );
				
				//resync categories and other bits
				$kernel->archive->update_category_file_count( $kernel->vars['submission_cat_id'] );
				$kernel->archive->update_category_new_file( $kernel->vars['submission_cat_id'] );
				
				$kernel->archive->global_category_syncronisation();
				
				$kernel->archive->update_database_counter( "files" );
				
				$file_id = $kernel->db->item( "SELECT `file_id` FROM `" . TABLE_PREFIX . "files` ORDER BY `file_id` DESC LIMIT 1" );
				
				//write file custom fields
				$kernel->archive->construct_db_write_custom_fields( $file_id, $_POST );
				
				// Log the file add
				$logdata = array(
					"log_user_id" => $submission_author_id,
					"log_node" => "file_add",
					"log_reference"	=> $kernel->format_input( $kernel->vars['submission_name'], T_DB ),
					"log_data"	=> $kernel->format_input( $kernel->vars['submission_name'], T_DB ),
					"log_phrase" => "phrase_log_file_added",
					"log_timestamp" => UNIX_TIME,
					"log_ip_address" => IP_ADDRESS
				);
				
				$kernel->db->insert( "logs_admin", $logdata );
			}
			
			// Send an email?
			if( $kernel->config['EMAIL_FILE_SUBMIT'] == "true" )
			{
				$emaildata = array(
					"file_name" => $kernel->format_input( $kernel->vars['submission_name'], T_STRIP ),
					"file_author" => $kernel->format_input( $kernel->vars['submission_author'], T_STRIP ),
					"file_version" => $kernel->format_input( $kernel->vars['submission_version'], T_STRIP ),
					"file_description" => $kernel->format_input( $kernel->vars['submission_description'], T_STRIP ),
					"archive_title" => $kernel->config['archive_name']
				);
				
				$emaildata['file_category'] = $kernel->db->item( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $kernel->vars['submission_cat_id'] );
				
				$user_name = $kernel->db->item( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $submission_author_id );
				$emaildata['user_name'] = ( empty( $user_name ) ) ? $kernel->ld['phrase_guest'] : $user_name;
				
				$kernel->archive->construct_send_email( "file_submission_notification", $kernel->config['mail_inbound'], $emaildata );
			}
			
			header( "Location: submit.php?action=message" ); exit;
		}
		
		break;
	}
	
	#############################################################################
	# success message

	case "message" :
	{
		if( $kernel->session->read_permission_flag( 'FIL_SUB', true ) == true )
		{
			if( $kernel->config['archive_allow_upload'] == "false" )
			{
				$kernel->page->message_report( $kernel->ld['phrase_upload_disabled'], M_ERROR );
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_submitted_file'], M_NOTICE );
			}
		}

		break;
	}
	
	#############################################################################
	# add form
	
	default :
	{
		if( $kernel->session->read_permission_flag( 'FIL_SUB', true ) == true )
		{
			if( $kernel->config['archive_allow_upload'] == "false" )
			{
				$kernel->page->message_report( $kernel->ld['phrase_upload_disabled'], M_ERROR );
			}
			else
			{
				$kernel->tp->call( "file_add" );
				
				$kernel->vars['html']['apc_cache_key'] = uniqid();
				
				$kernel->session->construct_session_security_form();
				
				$kernel->page->construct_category_list( 0 );
				
				$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => sprintf( $kernel->ld['phrase_form_max_total_upload_size'], $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) ) );
				
				$kernel->tp->cache( $file );
				
				$kernel->archive->construct_file_custom_fields_form( 0 );
			}
		}

		break;
	}
}

$kernel->page->construct_output( R_HEADER, R_FOOTER, R_ANNOUNCEMENTS, R_NAVIGATION );

?>