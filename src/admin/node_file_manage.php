<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'FIL_MAN', 'FIL_DEL' );

if( $kernel->db->numrows( "SELECT `category_id` FROM `" . TABLE_PREFIX . "categories` LIMIT 1" ) == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_category_files'], M_ERROR );
}
else
{
	$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "file_id" => V_INT, "category_id" => V_INT ) );

	$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
	$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

	if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
	if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";

	switch( $kernel->vars['action'] )
	{
		#############################################################################
		
		case "edit" :
		{
			$kernel->admin->read_permission_flags( 'FIL_MAN' );
			
			//Multiple selected items from the search page.
			if( is_array( $_GET['file_id'] ) )
			{
			if( sizeof( $_GET['file_id'] ) > 1 )
				{
				header( "Location: index.php?hash=" . $kernel->vars['hash'] . "&node=" . $kernel->vars['node'] . "&action=manage&form_edit=1&checkbox=" . $kernel->format_input( serialize( $_GET['file_id'] ), T_URL_ENC ) );
				exit;
			}
			else
			{
				$kernel->vars['file_id'] = $_GET['file_id'][0];
			}
			}
			
			if( $kernel->config['system_file_edit_form_type'] == "0" )
			{
				$kernel->tp->call( "admin_file_edit_lofi" );
			}
			else
			{
				$kernel->tp->call( "admin_file_edit" );
			}
			
			$file = $kernel->db->row( "SELECT f.* FROM " . TABLE_PREFIX . "files f WHERE f.file_id = " . $kernel->vars['file_id'] . " LIMIT 1" );
			
			$kernel->archive->synchronise_file_status();
			
			$file['file_name'] = $kernel->format_input( $file['file_name'], T_FORM );
			$file['file_description'] = $kernel->format_input( $file['file_description'], T_FORM );
			
			$file['file_rank'] = $kernel->archive->construct_file_rating( $file['file_rating'], $file['file_votes'] );
			
			$file['file_size_formatted'] = $kernel->archive->format_round_bytes( $file['file_size'] );
			
			$file['file_views'] = $kernel->format_input( $file['file_views'], T_NUM );
			$file['file_timestamp'] = $kernel->fetch_time( $file['file_timestamp'], DF_LONG );
			$file['file_downloads'] = $kernel->format_input( $file['file_downloads'], T_NUM );
			$file['file_votes'] = $kernel->format_input( $file['file_votes'], T_NUM );
			
			$file['file_from_date'] = ( !empty( $file['file_from_timestamp'] ) ? date( "Y-m-d", $file['file_from_timestamp'] ) : "" );
			$file['file_to_date'] = ( !empty( $file['file_to_timestamp'] ) ? date( "Y-m-d", $file['file_to_timestamp'] ) : "" );
			
			list( $file['file_hash_md5'], $file['file_hash_sha1'] ) = explode( ",", $file['file_hash_data'] );
			if( empty( $file['file_hash_md5'] ) ) $file['file_hash_md5'] = $kernel->ld['phrase_unknown'];
			if( empty( $file['file_hash_sha1'] ) ) $file['file_hash_sha1'] = $kernel->ld['phrase_unknown'];
			
			$kernel->page->construct_category_list( $file['file_cat_id'] );
			
			$file['file_url_check'] = $kernel->archive->return_verify_file_url( $file['file_dl_url'], $file['file_id'] );
			
			$file['file_tags'] = $kernel->archive->construct_file_tags( $file['file_id'] );
			
			if( $kernel->config['system_file_edit_form_type'] == "1" )
			{
				$kernel->archive->construct_list_options( $file['file_doc_id'], "document", $kernel->db->query( "SELECT `document_id`, `document_title` FROM `" . TABLE_PREFIX . "documents` ORDER BY `document_title`" ) );
				$kernel->archive->construct_list_options( $file['file_gallery_id'], "gallery", $kernel->db->query( "SELECT `gallery_id`, `gallery_name` FROM `" . TABLE_PREFIX . "galleries` ORDER BY `gallery_name`" ) );
				$kernel->archive->construct_list_options( explode( ",", $file['file_image_array'] ), "image", $kernel->db->query( "SELECT `image_id`, `image_name` FROM `" . TABLE_PREFIX . "images` ORDER BY `image_name`" ) );
				
				$kernel->archive->construct_file_icon_selector( $file['file_icon'] );
				$kernel->archive->construct_file_custom_fields_form( $file['file_id'] );
				$kernel->archive->construct_file_download_mirror_form( $file['file_id'] );
				
				for( $i = 0; $i <= 23; $i++ ) $hours[ sprintf( "%02s", $i ) ] = sprintf( "%02s", $i );
				for( $i = 0; $i <= 59; $i++ ) $minutes[ sprintf( "%02s", $i ) ] = sprintf( "%02s", $i );
				
				$kernel->archive->construct_list_options( ( !empty( $file['file_from_timestamp'] ) ? date( "H", $file['file_from_timestamp'] ) : 0 ), "from_hours", $hours, false );
				$kernel->archive->construct_list_options( ( !empty( $file['file_from_timestamp'] ) ? date( "i", $file['file_from_timestamp'] ) : 0 ), "from_minutes", $minutes, false );
				
				$kernel->archive->construct_list_options( ( !empty( $file['file_to_timestamp'] ) ? date( "H", $file['file_to_timestamp'] ) : 0 ), "to_hours", $hours, false );
				$kernel->archive->construct_list_options( ( !empty( $file['file_to_timestamp'] ) ? date( "i", $file['file_to_timestamp'] ) : 0 ), "to_minutes", $minutes, false );
				
				if( $file['file_from_timestamp'] > 0 AND $file['file_from_timestamp'] > UNIX_TIME )
				{
					$file['file_from_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_not_reached'], "#ff3333" );
				}
				elseif( $file['file_from_timestamp'] > 0 AND $file['file_from_timestamp'] < UNIX_TIME )
				{
					$file['file_from_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_reached'], "#66cc66" );
				}
				else
				{
					$file['file_from_date_remark'] = "&nbsp;";
				}
				
				if( $file['file_to_timestamp'] > 0 AND $file['file_to_timestamp'] > UNIX_TIME )
				{
					$file['file_to_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_not_reached'], "#66cc66" );
				}
				elseif( $file['file_to_timestamp'] > 0 AND $file['file_to_timestamp'] < UNIX_TIME )
				{
					$file['file_to_date_remark'] = $kernel->page->string_colour( $kernel->ld['phrase_date_reached'], "#ff3333" );
				}
				else
				{
					$file['file_to_date_remark'] = "&nbsp;";
				}
			}
			
			$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => sprintf( $kernel->ld['phrase_image_total_max_upload_size'], $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) ) );

			$file['arg_true_file_pinned'] = ( $file['file_pinned'] == 1 ) ? "checked=\"checked\"" : "";
			$file['arg_false_file_pinned'] = ( $file['file_pinned'] == 0 ) ? "checked=\"checked\"" : "";
						
			$file['arg_true_file_disabled'] = ( $file['file_disabled'] == 1 ) ? "checked=\"checked\"" : "";
			$file['arg_false_file_disabled'] = ( $file['file_disabled'] == 0 ) ? "checked=\"checked\"" : "";
			
			$kernel->tp->cache( $file );
			
			$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) );
			
			break;
		}
		
		#############################################################################
		
		case "update" :
		{
			$kernel->admin->read_permission_flags( 'FIL_MAN' );
			
			//error-check custom fields
			$kernel->archive->verify_custom_field_values( $_POST );
			
			//clean db associated vars
			$kernel->clean_array( "_POST", array(
				"file_doc_id" => V_INT, "file_dl_limit" => V_INT, "file_cat_id" => V_INT,
				"file_pinned" => V_INT, "file_gallery_id" => V_INT, "file_name" => V_STR,
				"file_icon" => V_STR, "file_author" => V_STR, "file_dl_url" => V_STR,
				"file_version" => V_STR, "file_description" => V_STR, "file_tags" => V_STR, "file_size" => V_INT,
				"file_disabled" => V_INT, "form_resync_mirrors" => V_STR,
				"file_to_date" => V_STR, "file_to_hours" => V_STR, "file_to_minutes" => V_STR,
				"file_from_date" => V_STR, "file_from_hours" => V_STR, "file_from_minutes" => V_STR,
			) );
			
			//file link method not provided
			if( $kernel->vars['file_dl_url'] == $kernel->config['system_root_url_upload'] . "/" OR empty( $kernel->vars['file_dl_url'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_url_local_file_upload'], M_ERROR, HALT_EXEC );
			}
			
			//Attempting to add a file outside/behind the upload URL folder.
			if( strstr( $kernel->vars['file_dl_url'], $kernel->config['system_root_url_home'] ) == true AND strstr( $kernel->vars['file_dl_url'], $kernel->config['system_root_url_upload'] ) == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
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
			
			//prep file data for updating
			$file_info = $kernel->archive->file_url_info( $kernel->vars['file_dl_url'] );
			
			if( $kernel->vars['file_name'] == "" )
			{
				$kernel->vars['file_name'] = $file_info['file_name'];
			}
			
			//filetype not allowed
			if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_bad_filetype'], M_ERROR, HALT_EXEC );
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
				"file_from_timestamp" => ( !empty( $kernel->vars['file_from_date'] ) ? strtotime( $kernel->vars['file_from_date'] . " " . $kernel->vars['file_from_hours'] . ":" . $kernel->vars['file_from_minutes'] . ":00" ) : 0 ),
				"file_to_timestamp" => ( !empty( $kernel->vars['file_to_date'] ) ? strtotime( $kernel->vars['file_to_date'] . " " . $kernel->vars['file_to_hours'] . ":" . $kernel->vars['file_to_minutes'] . ":59" ) : 0 ),
				"file_dl_limit" => $kernel->vars['file_dl_limit'],
				"file_doc_id" => $kernel->vars['file_doc_id'],
				"file_disabled" => $kernel->vars['file_disabled'],
				"file_dl_url" => $kernel->format_input( $kernel->vars['file_dl_url'], T_DB )
			);
			
			$filedata['file_image_array'] = $kernel->archive->construct_upload_images_list( $_FILES['image_upload'] );
			
			if( !empty( $filedata['file_from_timestamp'] ) OR !empty( $filedata['file_to_timestamp'] ) )
			{
				if( $filedata['file_from_timestamp'] > UNIX_TIME OR $filedata['file_to_timestamp'] < UNIX_TIME )
				{
					$filedata['file_disabled'] = 1;
				}
			}
			
			if( is_array( $_POST['images'] ) )
			{
				if( !empty( $filedata['file_image_array'] ) ) $filedata['file_image_array'] .= ",";
				$filedata['file_image_array'] .= implode( ",", $_POST['images'] );
			}
			
			//reset options		
			if( $_POST['form_clear_time'] == "1" )
			{
				$filedata['file_timestamp'] = UNIX_TIME;
			}
			
			if( $_POST['form_clear_rating'] == "1" )
			{
				$filedata['file_votes'] = 0;
				$filedata['file_rating'] = 0;
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "votes` WHERE `file_id` = " . $kernel->vars['file_id'] );
			}
			
			if( $_POST['form_clear_downloads'] == "1" )
			{
				$filedata['file_downloads'] = 0;
			}
			
			if( $_POST['form_clear_views'] == "1" )
			{
				$filedata['file_views'] = 0;
			}
			
			if( $_POST['form_resync_file'] == "1" )
			{
				$filedata['file_size'] = $kernel->archive->parse_url_size( $kernel->vars['file_dl_url'], $kernel->config['system_parse_timeout'] );
				
				$filedata['file_hash_data'] = $kernel->archive->exec_file_hash( $kernel->vars['file_dl_url'] ) . "," . $kernel->archive->exec_file_hash( $kernel->vars['file_dl_url'], false );
			}
			else
			{
				$filedata['file_size'] = $kernel->vars['file_size'];
			}
			
			if( $_POST['form_clear_comments'] == "1" )
			{
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "comments` WHERE `comment_file_id` = " . $kernel->vars['file_id'] );
				
				$filedata['file_total_comments'] = 0;
			}
			
			$current_file_cat_id = $kernel->db->item( "SELECT `file_cat_id` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $kernel->vars['file_id'] );
			
			//update file data
			$kernel->db->update( "files", $filedata, "WHERE `file_id` = " . $kernel->vars['file_id'] );
			
			//update file custom fields
			$kernel->archive->construct_db_write_custom_fields( $kernel->vars['file_id'], $_POST );
			
			//update file mirrors
			$kernel->admin->construct_db_write_download_mirrors( $kernel->vars['file_id'], $_POST );
			
			//write file tags
			$kernel->archive->construct_db_write_tags( $kernel->vars['file_id'], $kernel->vars['file_tags'] );
			
			$kernel->archive->global_category_syncronisation();
			
			$kernel->archive->update_database_counter( "files" );
			$kernel->archive->update_database_counter( "fields_data" );
			$kernel->archive->update_database_counter( "data" );
			
			if( $_POST['form_clear_views'] == "1" ) $kernel->archive->update_database_counter( "views" );
			if( $_POST['form_clear_downloads'] == "1" ) $kernel->archive->update_database_counter( "downloads" );
			if( $_POST['form_clear_comments'] == "1" ) $kernel->archive->update_database_counter( "comments" );
			
			//done
			$kernel->admin->message_admin_report( "log_file_edited", $kernel->vars['file_name'] );
			
			break;
		}
		
		#############################################################################
		
		case "manage" :
		{
			$count = 0;
			
			if( isset( $_POST['form_delete'] ) OR $kernel->vars['file_id'] > 0 )
			{
				$kernel->admin->read_permission_flags( 'FIL_DEL' );
				
				//single item
				if( $kernel->vars['file_id'] > 0 )
				{
					$file = $kernel->db->row( "SELECT `file_cat_id`, `file_dl_url` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $kernel->vars['file_id'] );
					
					$delete_data[] = $kernel->db->item( "SELECT `file_name` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $kernel->vars['file_id'] );
					
					if( $kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $kernel->vars['file_id'] ) )
					{
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_file_id` = " . $kernel->vars['file_id'] );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "comments` WHERE `comment_file_id` = " . $kernel->vars['file_id'] );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "votes` WHERE `file_id` = " . $kernel->vars['file_id'] );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "mirrors` WHERE `mirror_file_id` = " . $kernel->vars['file_id'] );
						$count++;
					}
					
					$kernel->archive->update_category_file_count( $file['file_cat_id'] );
					$kernel->archive->update_category_new_file( $file['file_cat_id'] );
				}
				
				//item array
				elseif( is_array( $_POST['checkbox'] ) )
				{
					foreach( $_POST['checkbox'] AS $file_id )
					{
						if( $_POST['option_physical_delete'] == "1" )
						{
							$file = $kernel->db->row( "SELECT `file_dl_url` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $file_id );
							
							if( strpos( $file['file_dl_url'], $kernel->config['system_root_url_upload'] ) !== false )
							{
								$file_path = str_replace( "/", DIR_STEP, str_replace( $kernel->config['system_root_url_upload'], $kernel->config['system_root_dir_upload'], $file['file_dl_url'] ) );
								
								@unlink( $file_path );
							}
						}
						
						$delete_data[] = $kernel->db->item( "SELECT `file_name` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $file_id );
						
						if( $kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $file_id ) )
						{
							$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_file_id` = " . $file_id );
							$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "comments` WHERE `comment_file_id` = " . $file_id );
							$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "votes` WHERE `file_id` = " . $file_id );
							$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "mirrors` WHERE `mirror_file_id` = " . $file_id );
							$count++;
						}
					}
					
					$kernel->archive->update_category_file_count( $_POST['current_category_id'] );
					$kernel->archive->update_category_new_file( $_POST['current_category_id'] );
				}
				
				//no items
				else
				{
					$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
				}
				
				if( sizeof( $delete_data ) > 0 )
				{
					//resync categories and other bits
					$kernel->archive->global_category_syncronisation();
					
					//resync categories and other bits
					$kernel->archive->update_database_counter( "files" );
					$kernel->archive->update_database_counter( "data" );
					$kernel->archive->update_database_counter( "votes" );
					$kernel->archive->update_database_counter( "fields_data" );
					$kernel->archive->update_database_counter( "comments" );
					$kernel->archive->update_database_counter( "mirrors" );
					
					//done
					$kernel->admin->message_admin_report( "log_file_deleted", $count, $delete_data );
				}
				else
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_items_were_deleted'], M_ERROR );
				}
			}
			
			###########################################################################
			
			elseif( isset( $_POST['form_move'] ) )
			{
				$kernel->admin->read_permission_flags( 'FIL_MAN' );
				
				if( $kernel->vars['category_id'] == 0 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_valid_category_selected'], M_WARNING );
				}
				elseif( $kernel->vars['category_id'] == $_POST['current_category_id'] )
				{
					$kernel->page->message_report( $kernel->ld['phrase_category_select_different'], M_ERROR );
				}
				else
				{
					if( is_array( $_POST['checkbox'] ) )
					{
						foreach( $_POST['checkbox'] AS $file )
						{
							$move_data[] = $kernel->db->item( "SELECT `file_name` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $file );
							
							$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "files` SET `file_cat_id` = '" . $kernel->vars['category_id'] . "' WHERE `file_id` = " . $file );
							$count++;
						}
					}
					
					if( $_POST['option_move_children'] == "1" )
					{
						$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "categories` SET `category_sub_id` = '" . $kernel->vars['category_id'] . "' WHERE `category_sub_id` = " . $_POST['current_category_id'] );
					}
					
					//resync categories and other bits
					$kernel->archive->update_category_file_count( $kernel->vars['category_id'] );
					$kernel->archive->update_category_new_file( $kernel->vars['category_id'] );
					
					$kernel->archive->update_category_file_count( $_POST['current_category_id'] );
					$kernel->archive->update_category_new_file( $_POST['current_category_id'] );
					
					//done
					$kernel->admin->message_admin_report( "log_file_moved", $count, $move_data );
				}
			}
			
			###########################################################################
			
			//Check POST for data from file_manage checkbox[] vals, GET for data from file edit search vals.
			elseif( isset( $_POST['form_edit'] ) OR isset( $_GET['form_edit'] ) )
			{
				$kernel->admin->read_permission_flags( 'FIL_MAN' );
				
				$_POST['checkbox'] = ( isset( $_GET['checkbox'] ) ) ? unserialize( $kernel->format_input( $_GET['checkbox'], T_URL_DEC ) ) : $_POST['checkbox'];
				
				if( !is_array( $_POST['checkbox'] ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_files_selected'], M_ERROR );
				}
				else
				{
					$kernel->page->construct_progress_window( count( $_POST['checkbox'] ) );
					
					$kernel->tp->call( "admin_file_mass_edit_header" );
					
					foreach( $_POST['checkbox'] AS $file_id )
					{
						if( $kernel->config['system_file_mass_edit_form_type'] == "0" )
						{
							$kernel->tp->call( "admin_file_mass_edit_lofi" );
						}
						else
						{
							$kernel->tp->call( "admin_file_mass_edit" );
						}
						
						$file = $kernel->db->row( "SELECT f.* FROM " . TABLE_PREFIX . "files f WHERE f.file_id = " . $file_id . " LIMIT 1" );
						
						list( $file['file_hash_md5'], $file['file_hash_sha1'] ) = explode( ",", $file['file_hash_data'] );
						if( empty( $file['file_hash_md5'] ) ) $file['file_hash_md5'] = $kernel->ld['phrase_unknown'];
						if( empty( $file['file_hash_sha1'] ) ) $file['file_hash_sha1'] = $kernel->ld['phrase_unknown'];
						
						$kernel->page->construct_category_list( $file['file_cat_id'] );
						
						$file['file_url_check'] = $kernel->archive->return_verify_file_url( $file['file_dl_url'], $file['file_id'] );
						
						$file['file_tags'] = $kernel->archive->construct_file_tags( $file['file_id'] );
						
						if( $kernel->config['system_file_mass_edit_form_type'] == "1" )
						{
							$kernel->archive->construct_list_options( $file['file_doc_id'], "document", $kernel->db->query( "SELECT `document_id`, `document_title` FROM `" . TABLE_PREFIX . "documents` ORDER BY `document_title`" ) );
							$kernel->archive->construct_list_options( $file['file_gallery_id'], "gallery", $kernel->db->query( "SELECT `gallery_id`, `gallery_name` FROM `" . TABLE_PREFIX . "galleries` ORDER BY `gallery_name`" ) );
							$kernel->archive->construct_list_options( explode( ",", $file['file_image_array'] ), "image", $kernel->db->query( "SELECT `image_id`, `image_name` FROM `" . TABLE_PREFIX . "images` ORDER BY `image_name`" ) );
							
							$kernel->archive->construct_file_icon_selector( $file['file_icon'], $file['file_id'] );
							$kernel->archive->construct_file_custom_fields_form( $file['file_id'] );
							$kernel->archive->construct_file_download_mirror_form( $file['file_id'] );
						}
						
						$file['arg_true_file_pinned'] = ( $file['file_pinned'] == 1 ) ? "checked=\"checked\"" : "";
						$file['arg_false_file_pinned'] = ( $file['file_pinned'] == 0 ) ? "checked=\"checked\"" : "";
						
						$file['arg_true_file_disabled'] = ( $file['file_disabled'] == 1 ) ? "checked=\"checked\"" : "";
						$file['arg_false_file_disabled'] = ( $file['file_disabled'] == 0 ) ? "checked=\"checked\"" : "";
						
						$kernel->tp->cache( $file );
						
						$kernel->tp->cache( "custom_fields", $kernel->vars['html']['custom_fields'] );
						$kernel->tp->cache( "icon_list_options", $kernel->vars['html']['icon_list_options'] );
						
						unset( $file );
						
						$kernel->page->update_progress_window_bar();
					}
					
					$kernel->tp->call( "admin_file_mass_edit_footer" );
					
					$kernel->page->finish_progress_window();
					
					break;
				}
			}
			
			break;
		}
		
		#############################################################################
		
		case "mass_update" :
		{
			$kernel->admin->read_permission_flags( 'FIL_MAN' );
			
			$count = 0;
			
			print_r( $_POST ); exit;
			
			foreach( $_POST['files'] AS $file_id => $file )
			{
				//clean db associated vars
				$kernel->clean_array( $file, array(
				"file_doc_id" => V_INT, "file_dl_limit" => V_INT, "file_cat_id" => V_INT,
				"file_pinned" => V_INT, "file_gallery_id" => V_INT, "file_name" => V_STR,
				"file_icon" => V_STR, "file_author" => V_STR, "file_dl_url" => V_STR,
				"file_version" => V_STR, "file_description" => V_STR, "file_tags" => V_STR, "file_size" => V_INT,
				"file_disabled" => V_INT
			) );
			
			//error-check custom fields
			$kernel->archive->verify_custom_field_values( $file );
				
			//file link method not provided
			if( $kernel->vars['file_dl_url'] == $kernel->config['system_root_url_upload'] . "/" OR empty( $kernel->vars['file_dl_url'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_url_local_file_upload'] . " (<b>" . $kernel->vars['file_name'] . "</b>)", M_ERROR, HALT_EXEC );
			}
			
			//Attempting to add a file outside/behind the upload URL folder.
			if( strstr( $kernel->vars['file_dl_url'], $kernel->config['system_root_url_home'] ) == true AND strstr( $kernel->vars['file_dl_url'], $kernel->config['system_root_url_upload'] ) == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_url_outside_upload_folder'] . " (<b>" . $kernel->vars['file_name'] . "</b>)", M_ERROR, HALT_EXEC );
			}
			
			//Attempting to add a file outside/behind the upload DIR folder.
			if( ( $kernel->vars['file_dl_url']{1} == ":" OR $kernel->vars['file_dl_url']{0} == "/" OR $kernel->vars['file_dl_url']{0} == "\\" ) AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_url_outside_upload_folder'] . " (<b>" . $kernel->vars['file_name'] . "</b>)", M_ERROR, HALT_EXEC );
			}
			
			//no category specified
			if( $kernel->vars['file_cat_id'] == 0 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_category_selected'] . " (<b>" . $kernel->vars['file_name'] . "</b>)", M_ERROR, HALT_EXEC );
			}
				
			//prep file data for updating
			$file_info = $kernel->archive->file_url_info( $kernel->vars['file_dl_url'] );
			
			if( $kernel->vars['file_name'] == "" )
			{
				$kernel->vars['file_name'] = $file_info['file_name'];
			}
			
			//filetype not allowed
			if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_bad_filetype'] . " (<b>" . $kernel->vars['file_name'] . "</b>)", M_ERROR, HALT_EXEC );
			}
				
				$filedata = array(
					"file_cat_id" => $kernel->vars['file_cat_id'],
					"file_gallery_id" => $kernel->vars['file_gallery_id'],
					"file_pinned" => $kernel->vars['file_pinned'],
					"file_icon" => $kernel->vars['file_icon'],
					"file_name" => $kernel->format_input( $kernel->vars['file_name'], T_DB ),
					"file_author" => $kernel->format_input( $kernel->vars['file_author'], T_DB ),
					"file_version" => $kernel->format_input( $kernel->vars['file_version'], T_DB ),
					"file_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['file_description'], T_DB ), $kernel->config['string_max_word_length'] ),
					"file_dl_limit" => $kernel->vars['file_dl_limit'],
					"file_doc_id" => $kernel->vars['file_doc_id'],
					"file_dl_url" => $kernel->format_input( $kernel->vars['file_dl_url'], T_DB ),
					"file_disabled" => $kernel->vars['file_disabled']
				);
				
				if( is_array( $_POST['images'][ $file_id ] ) )
				{
					$filedata['file_image_array'] = implode( ",", $_POST['images'][ $file_id ] );
				}
				
				//reset options			
				if( $_POST['form_clear_time'] == "1" )
				{
					$filedata['file_timestamp'] = UNIX_TIME;
				}
				
				if( $_POST['form_clear_rating'] == "1" )
				{
					$filedata['file_votes'] = 0;
					$filedata['file_rating'] = 0;
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "votes` WHERE `file_id` = " . $file_id );
				}
				
				if( $_POST['form_clear_downloads'] == "1" )
				{
					$filedata['file_downloads'] = 0;
				}
				
				if( $_POST['form_clear_views'] == "1" )
				{
					$filedata['file_views'] = 0;
				}
				
				if( $_POST['form_resync_file'] == "1" )
				{
					$filedata['file_size'] = $kernel->archive->parse_url_size( $kernel->vars['file_dl_url'], $kernel->config['system_parse_timeout'] );
					
					$filedata['file_hash_data'] = $kernel->archive->exec_file_hash( $kernel->vars['file_dl_url'] ) . "," . $kernel->archive->exec_file_hash( $kernel->vars['file_dl_url'], false );
				}
				else
				{
					$filedata['file_size'] = $kernel->vars['file_size'];
				}
				
				if( $_POST['form_clear_comments'] == "1" )
				{
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "comments` WHERE `comment_file_id` = " . $file_id );
				}
				
				$current_file_cat_id = $kernel->db->item( "SELECT `file_cat_id` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $file_id );
				
				//update file data
				$kernel->db->update( "files", $filedata, "WHERE `file_id` = " . $file_id );
				
				$edit_data[] = $kernel->vars['file_name'];
				
				//update file custom fields
				$kernel->archive->construct_db_write_custom_fields( $file_id, $file );
				
				//write file tags
				$kernel->archive->construct_db_write_tags( $file_id, $kernel->vars['file_tags'] );
				
				if( $current_file_cat_id != $kernel->vars['file_cat_id'] )
				{
					$kernel->archive->update_category_file_count( $current_file_cat_id );
					$kernel->archive->update_category_new_file( $current_file_cat_id );
				}
				
				$kernel->archive->update_category_file_count( $file['file_cat_id'] );
				$kernel->archive->update_category_new_file( $file['file_cat_id'] );
				
				$count++;
			}
			
			//resync categories and other bits
			$kernel->archive->global_category_syncronisation();
			
			$kernel->archive->update_database_counter( "files" );
			$kernel->archive->update_database_counter( "fields" );
			$kernel->archive->update_database_counter( "fields_data" );
			if( $_POST['form_clear_views'] == "1" ) $kernel->archive->update_database_counter( "views" );
			if( $_POST['form_clear_downloads'] == "1" ) $kernel->archive->update_database_counter( "downloads" );
			if( $_POST['form_resync_file'] == "1" ) $kernel->archive->update_database_counter( "data" );
			if( $_POST['form_clear_comments'] == "1" ) $kernel->archive->update_database_counter( "comments" );
			
			$kernel->admin->message_admin_report( "log_mass_file_edited", $count, $edit_data );
			
			break;
		}
		
		#############################################################################
		
		case "view" :
		{
			$get_categories = $kernel->db->query( "SELECT c.category_id, c.category_name, c.category_description, c.category_password, c.category_file_total FROM " . TABLE_PREFIX . "categories c WHERE c.category_sub_id = " . $kernel->vars['category_id'] . " ORDER BY c.category_order, c.category_name" );
			
			//do sub-categories exist?
			if( $kernel->db->numrows() > 0 )
			{
				$kernel->tp->call( "admin_file_cate_header" );
				
				while( $category = $kernel->db->data( $get_categories ) )
				{
					$subcat_files = 0;
					
					$kernel->tp->call( "admin_file_cate_row" );
					
					$category['category_name'] = $kernel->format_input( $category['category_name'], T_NOHTML );
					$category['category_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $category['category_description'], T_NOHTML ), 15 );
					
					$category['category_file_total'] = $kernel->page->format_category_file_count( $category['category_id'], $category['category_password'], $category['category_file_total'] );
					$category['category_state_icon'] = $kernel->admin->construct_icon( "lock.gif", $kernel->ld['phrase_category_password_protected'], ( !empty( $category['category_password'] ) ) );
					
					$kernel->tp->cache( $category );
				}
				
				$kernel->tp->call( "admin_file_cate_footer" );
			}
			
			//check and get files
			$check_files = $kernel->db->query( "SELECT f.file_id FROM " . TABLE_PREFIX . "files f WHERE f.file_cat_id = " . $kernel->vars['category_id'] );
			
			if( $kernel->db->numrows() == 0 )
			{
				if( $kernel->db->numrows( $get_categories ) == 0 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_files_in_category'], M_ERROR );
				}
			}
			else
			{
				$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_files ) );
				$last_filegroup = -1;
				
				$kernel->tp->call( "admin_file_header" );
				
				$get_files = $kernel->db->query( "SELECT f.file_id, f.file_cat_id, f.file_name, f.file_pinned, f.file_description, f.file_disabled FROM " . TABLE_PREFIX . "files f WHERE f.file_cat_id = " . $kernel->vars['category_id'] . " ORDER BY f.file_pinned DESC, f.file_name LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
				
				while( $file = $kernel->db->data( $get_files ) )
				{
					if( $last_filegroup == 1 AND $file['file_pinned'] == 0 )
					{
						$kernel->tp->call( "admin_file_row_break" );
					}
					$last_filegroup = $file['file_pinned'];
					
					$kernel->tp->call( "admin_file_row" );
					
					$file['file_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $file['file_description'], T_NOHTML ), $kernel->config['string_max_words'] );
					$file['file_name'] = $kernel->format_input( $file['file_name'], T_NOHTML );
					
					$file['file_html_name'] = $kernel->admin->construct_icon( "shield.gif", $kernel->ld['phrase_file_is_pinned_important'], ( $file['file_pinned'] == 1 ) );
					
					if( $file['file_disabled'] == 1 )
					{
						$file['file_html_name'] .= $kernel->page->string_colour( $file['file_name'], "#999999" );
					}
					else
					{
						$file['file_html_name'] .= $file['file_name'];
					}
					
					$kernel->tp->cache( $file );
				}
				
				$kernel->tp->call( "admin_file_footer" );
				
				$kernel->page->construct_category_filters();
				
				$kernel->page->construct_pagination( array( 'action' => 'view', 'category_id' => $kernel->vars['category_id'] ), $kernel->config['admin_pagination_page_proximity'] );
							
				$kernel->page->construct_category_list( $kernel->vars['category_id'] );
			}
		
			break;
		}
		
		#############################################################################
		
		default :
		{
			$get_categories = $kernel->db->query( "SELECT c.category_id, c.category_name, c.category_description, c.category_password, c.category_file_total FROM " . TABLE_PREFIX . "categories c WHERE c.category_sub_id = 0 ORDER BY c.category_order, c.category_name" );
			
			$kernel->tp->call( "admin_file_cate_header" );
			
			while( $category = $kernel->db->data( $get_categories ) )
			{
				$subcat_files = 0;
				
				$kernel->tp->call( "admin_file_cate_row" );
				
				$category['category_name'] = $kernel->format_input( $category['category_name'], T_NOHTML );
				$category['category_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $category['category_description'], T_NOHTML ), 15 );
				
				$category['category_file_total'] = $kernel->page->format_category_file_count( $category['category_id'], $category['category_password'], $category['category_file_total'] );
				
				$category['category_state_icon'] = $kernel->admin->construct_icon( "lock.gif", $kernel->ld['phrase_category_password_protected'], ( !empty( $category['category_password'] ) ) );
				
				$kernel->tp->cache( $category );
			}
			
			$kernel->page->construct_category_list();
			
			$kernel->tp->call( "admin_file_cate_footer" );
			
			break;
		}
	}
}

?>

