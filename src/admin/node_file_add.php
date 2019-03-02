<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'FIL_ADD' );

if( $kernel->db->numrows( "SELECT `category_id` FROM `" . TABLE_PREFIX . "categories` LIMIT 1" ) == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_category_files'], M_ERROR );
}
else
{
	switch( $kernel->vars['action'] )
	{

		#############################################################################
		
		case "create" :
		{
			//error-check custom fields
			$kernel->archive->verify_custom_field_values( $_POST );
			
			//local_file
			
			//clean db associated vars
			$kernel->clean_array( "_POST", array(
				"file_doc_id" => V_INT, "file_dl_limit" => V_INT, "file_cat_id" => V_INT,
				"file_pinned" => V_INT, "file_gallery_id" => V_INT, "file_name" => V_STR,
				"file_icon" => V_STR, "file_author" => V_STR, "file_dl_url" => V_STR,
				"file_version" => V_STR, "file_description" => V_STR, "file_tags" => V_STR,
				"file_disabled" => V_INT, "file_size" => V_INT, "file_rating" => V_INT,
				"file_to_date" => V_STR, "file_to_hours" => V_STR, "file_to_minutes" => V_STR,
				"file_from_date" => V_STR, "file_from_hours" => V_STR, "file_from_minutes" => V_STR,
				"file_votes" => V_INT, "file_downloads" => V_INT, "local_directory" => V_STR, "local_file" => V_STR,
				"file_upload_name" => V_STR, "file_hash_data" => V_STR, "current_directory" => V_STR
			) );
			
			//file link method not provided
			if( $kernel->vars['file_dl_url'] == $kernel->config['system_root_url_upload'] . "/" AND empty( $_FILES['file_upload']['name'] ) AND empty( $kernel->vars['local_file'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_url_local_file_upload'], M_ERROR, HALT_EXEC );
			}
			
			//Attempting to add a file outside/behind the upload URL folder.
			if( strstr( $kernel->vars['file_dl_url'], $kernel->config['system_root_url_home'] ) == true AND strstr( $kernel->vars['file_dl_url'], $kernel->config['system_root_url_upload'] ) != true AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_url_outside_upload_folder'], M_ERROR, HALT_EXEC );
			}
			
			//Attempting to add a file outside/behind the upload DIR folder.
			if( ( $kernel->vars['file_dl_url']{1} == ":" OR $kernel->vars['file_dl_url']{0} == "/" OR $kernel->vars['file_dl_url']{0} == "\\" ) AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_url_outside_upload_folder'], M_ERROR, HALT_EXEC );
			}
			
			//no category specified
			if( $kernel->vars['file_cat_id'] == 0 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_category_selected'], M_ERROR, HALT_EXEC );
			}
			
			//check validity times
			if( !empty( $kernel->vars['file_from_date'] ) AND !empty( $kernel->vars['file_to_date'] ) )
			{
				if( $kernel->vars['file_from_date'] > $kernel->vars['file_to_date'] )
				{
					$kernel->page->message_report( $kernel->ld['phrase_file_date_invalid'], M_ERROR, HALT_EXEC );
				}
				
				if( $kernel->vars['file_from_date'] == $kernel->vars['file_to_date'] AND $kernel->vars['file_from_hours'] >= $kernel->vars['file_to_hours'] )
				{
					if( $kernel->vars['file_from_hours'] > $kernel->vars['file_to_hours'] )
					{
						$kernel->page->message_report( $kernel->ld['phrase_file_date_hours_invalid'], M_ERROR, HALT_EXEC );
					}
						
					if( $kernel->vars['file_from_hours'] == $kernel->vars['file_to_hours'] )
					{
						if( $kernel->vars['file_from_minutes'] > $kernel->vars['file_to_minutes'] )
						{
							$kernel->page->message_report( $kernel->ld['phrase_file_date_minutes_invalid'], M_ERROR, HALT_EXEC );
						}
					}
				}
			}
			
			//local uploaded file has been selected
			if( !empty( $kernel->vars['local_file'] ) )
			{
				$kernel->vars['local_directory'] = preg_replace( "/\\\\/", "/", $kernel->vars['local_directory'] );
				
				$kernel->vars['file_dl_url'] = $config['system_root_url_upload'] . $kernel->vars['local_directory'] . "/" . $kernel->vars['local_file'];
			}
			
			//prep file data for updating
			if( !empty( $_FILES['file_upload']['name'] ) )
			{
				$file_info = $kernel->archive->file_url_info( $_FILES['file_upload']['name'] );
			}
			elseif( !empty( $kernel->vars['local_file'] ) )
			{
				$file_info = $kernel->archive->file_url_info( $kernel->vars['local_file'] );
			}
			else
			{
				$file_info = $kernel->archive->file_url_info( $kernel->vars['file_dl_url'] );
			}
			
			//check for upload errors if applicable
			$kernel->page->verify_upload_details();
			
			//filetype not allowed
			if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_bad_filetype'], M_ERROR, HALT_EXEC );
			}
			
			//Flag for mirror construct to parse for hashes..
			if( $_POST['option_parse_file'] == "1" ) $kernel->vars['form_resync_mirrors'] = "1";
			
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
				
				//replacement upload file name
				if( !empty( $kernel->vars['file_upload_name'] ) ) $_FILES['file_upload']['name'] = basename( $kernel->vars['file_upload_name'] );
				
				//check for exisiting files under provided file name
				$new_file_name = $kernel->page->construct_upload_file_name( $_FILES['file_upload']['name'], $file_full_url );
				
				//upload
				if( !@move_uploaded_file( $_FILES['file_upload']['tmp_name'], $file_full_dir . $new_file_name ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_could_not_move_upload_file'], M_ERROR, HALT_EXEC );
				}
				else
				{
					@chmod( $file_full_dir . $new_file_name, 0666 );
					
					$kernel->vars['file_dl_url'] = $file_full_url . $new_file_name;
					
					if( empty( $kernel->vars['file_name'] ) )
					{
						$kernel->vars['file_name'] = $new_file_name;
					}
					
					$kernel->vars['file_size'] = $_FILES['file_upload']['size'];
				}
			}
				
			###########################################################################
			# URL
				
			else
			{
				if( empty( $kernel->vars['file_name'] ) )
				{
					$kernel->vars['file_name'] = $file_info['file_name'];
				}
				
				if( $_POST['option_parse_file'] == "1" )
				{
					$kernel->vars['file_size'] = $kernel->archive->parse_url_size( $kernel->vars['file_dl_url'], $config['system_parse_timeout'] );
				}
			}
				
			###########################################################################
			
			$filedata = array(
				"file_cat_id" => $kernel->vars['file_cat_id'],
				"file_gallery_id" => $kernel->vars['file_gallery_id'],
				"file_pinned" => $kernel->vars['file_pinned'],
				"file_icon" => $kernel->vars['file_icon'],
				"file_name" => $kernel->format_input( $kernel->vars['file_name'], T_DB ),
				"file_author" => $kernel->format_input( $kernel->vars['file_author'], T_DB ),
				"file_version" => $kernel->format_input( $kernel->vars['file_version'], T_DB ),
				"file_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['file_description'], T_DB ), $kernel->config['string_max_word_length'] ),
				"file_timestamp" => UNIX_TIME,
				"file_mark_timestamp" => UNIX_TIME,
				"file_from_timestamp" => ( !empty( $kernel->vars['file_from_date'] ) ? strtotime( $kernel->vars['file_from_date'] . " " . $kernel->vars['file_from_hours'] . ":" . $kernel->vars['file_from_minutes'] . ":00" ) : 0 ),
				"file_to_timestamp" => ( !empty( $kernel->vars['file_to_date'] ) ? strtotime( $kernel->vars['file_to_date'] . " " . $kernel->vars['file_to_hours'] . ":" . $kernel->vars['file_to_minutes'] . ":59" ) : 0 ),
				"file_rating" => 0,
				"file_votes" => 0,
				"file_downloads" => 0,
				"file_dl_limit" => $kernel->vars['file_dl_limit'],
				"file_size" => $kernel->vars['file_size'],
				"file_doc_id" => $kernel->vars['file_doc_id'],
				"file_dl_url" => $kernel->format_input( $kernel->vars['file_dl_url'], T_DB )
			);
			
			$filedata['file_image_array'] = $kernel->archive->construct_upload_images_list( $_FILES['image_upload'] );
			
			if( is_array( $_POST['images'] ) )
			{
				if( !empty( $filedata['file_image_array'] ) ) $filedata['file_image_array'] .= ",";
				$filedata['file_image_array'] .= implode( ",", $_POST['images'] );
			}
			
			if( $_POST['option_parse_file'] == "1" )
			{
				$filedata['file_hash_data'] = $kernel->archive->exec_file_hash( $kernel->vars['file_dl_url'] ) . "," . $kernel->archive->exec_file_hash( $kernel->vars['file_dl_url'], false );
			}
			
			//insert file data
			$kernel->db->insert( "files", $filedata );
			
			$file_id = $kernel->db->insert_id();
			
			//write file mirrors	
			$kernel->admin->construct_db_write_download_mirrors( $file_id, $_POST );
			
			//write file custom fields
			$kernel->archive->construct_db_write_custom_fields( $file_id, $_POST );
			
			//write file tags
			$kernel->archive->construct_db_write_tags( $file_id, $kernel->vars['file_tags'] );
			
			//resync categories and other bits
			$kernel->archive->update_category_file_count( $kernel->vars['file_cat_id'] );
			$kernel->archive->update_category_new_file( $kernel->vars['file_cat_id'] );
			
			//$kernel->archive->global_category_syncronisation();
			
			$kernel->archive->update_database_counter( "files" );
			$kernel->archive->update_database_counter( "fields_data" );
			$kernel->archive->update_database_counter( "data" );
			
			//done
			$kernel->admin->message_admin_report( "log_file_added", $kernel->vars['file_name'] );
			
			//echo "<script type=\"text/javascript\" language=\"javascript\">parent.updateUploadProgress( '" . $kernel->vars['file_name'] . "', '', '', '', '2' );</script>";
			
			break;
		}
		
		#############################################################################
		
		default :
		{
			if( $kernel->config['system_file_add_form_type'] == "0" )
			{
				$kernel->tp->call( "admin_file_add_lofi" );
			}
			else
			{
				$kernel->tp->call( "admin_file_add" );
				
				$kernel->vars['html']['apc_cache_key'] = uniqid();
			}
			
			$kernel->page->construct_category_list();
			
			if( $kernel->config['system_file_add_form_type'] == "1" )
			{
				$kernel->archive->construct_list_options( 0, "document", $kernel->db->query( "SELECT `document_id`, `document_title` FROM `" . TABLE_PREFIX . "documents` ORDER BY `document_title`" ) );
				$kernel->archive->construct_list_options( 0, "gallery", $kernel->db->query( "SELECT `gallery_id`, `gallery_name` FROM `" . TABLE_PREFIX . "galleries` ORDER BY `gallery_name`" ) );
				$kernel->archive->construct_list_options( 0, "image", $kernel->db->query( "SELECT `image_id`, `image_name` FROM `" . TABLE_PREFIX . "images` ORDER BY `image_name`" ) );
				
				$kernel->archive->construct_file_icon_selector( null );
				
				$kernel->archive->construct_file_custom_fields_form( 0 );
				
				$kernel->archive->construct_file_download_mirror_form( 0 );
			}
			
			$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => sprintf( $kernel->ld['phrase_image_total_max_upload_size'], $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) ) );
			
			$kernel->tp->cache( "system_root_url_upload", $kernel->config['system_root_url_upload'] );
			
			$kernel->vars['html']['directory_list_options'] = "<option value=\"\">" . DIR_STEP . " " . $kernel->ld['phrase_upload_dir_root'] . "</option>\r\n";
			
			//build upload directory tree
			$kernel->admin->read_directory_tree( $kernel->config['system_root_dir_upload'] );
			
			//calendar elements
			for( $i = 0; $i <= 23; $i++ ) $hours[] = sprintf( "%02s", $i );
			for( $i = 0; $i <= 59; $i++ ) $minutes[] = sprintf( "%02s", $i );
			
			$kernel->archive->construct_list_options( 0, "from_hours", $hours, false );
			$kernel->archive->construct_list_options( 0, "from_minutes", $minutes, false );
			
			$kernel->vars['html']['to_hours_list_options'] = $kernel->vars['html']['from_hours_list_options'];
			$kernel->vars['html']['to_minutes_list_options'] = $kernel->vars['html']['from_minutes_list_options'];
			
			break;
		}
	}
}

?>

