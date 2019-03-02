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
	$kernel->clean_array( "_REQUEST", array( "current_directory" => V_STR, "file_cat_id" => V_INT ) );

	switch( $kernel->vars['action'] )
	{

		#############################################################################
		
		case "create" :
		{
			if( empty( $kernel->vars['file_cat_id'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_category_selected'], M_WARNING );
			}
			elseif( !is_array( $_POST['files'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_files_selected_adding'], M_ERROR );
			}
			else
			{
				$count = 0;
				$last_file = "";
				
				//build URL to the specified upload folder, base upload folder url. add sub directories if applicable.
				$file_base_url = $kernel->config['system_root_url_upload'] . $kernel->vars['current_directory'] . DIR_STEP;
				$file_full_url = $kernel->config['system_root_url_upload'] . substr( $file_base_url, strlen( $kernel->config['system_root_url_upload'] ), strlen( $file_base_url ) );
				
				//conver backslashes into forward slashes for windows servers.
				$file_full_url = preg_replace( "/\\\\/", "/", $file_full_url );
				
				//add trailing slash if it doesnt exist.
				preg_match( "/\/$/", $file_full_url, $matches );
				
				if( !isset( $matches[0] ) ) $file_full_url .= "/";
				
				//Add the files
				foreach( $_POST['files'] AS $file )
				{
					$file_info = $kernel->archive->file_url_info( $file );
					
					//filetype not allowed
				if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 ) continue;
					
					$file_name = $file_info['file_name'];
					
					if( $file_name == $last_file ) continue; //last file could be added twice without this, i still don't know why
					
					//$file_icon = $file_info['file_icon'];
					$file_icon = "0";
					
					$file_size = @filesize( $kernel->config['system_root_dir_upload'] . $kernel->vars['current_directory'] . DIR_STEP . $file );
					
					if( empty( $file_size ) )
					{					
						$file_size = 0;
					}
					
					$filedata = array(
						"file_cat_id" => $kernel->vars['file_cat_id'],
						"file_pinned" => 0,
						"file_icon" => $file_icon,
						"file_name" => $kernel->format_input( $file_name, T_DB ),
						"file_author" => $kernel->format_input( $kernel->session->vars['adminsession_name'], T_DB ),
						"file_version" => "",
						"file_description" => "",
						"file_timestamp" => UNIX_TIME,
						"file_mark_timestamp" => UNIX_TIME,
						"file_rating" => 0,
						"file_votes" => 0,
						"file_downloads" => 0,
						"file_dl_limit" => 0,
						"file_size" => $file_size,
						"file_doc_id" => 0,
						"file_dl_url" => $kernel->format_input( $file_full_url . $file, T_DB )
					);
					
					$kernel->db->insert( "files", $filedata );
					
					$add_data[] = $file_name;
					
					$count++;
					$last_file = $file_name;
				}
				
				//resync categories and other bits
				$kernel->archive->update_category_file_count( $kernel->vars['file_cat_id'] );
				$kernel->archive->update_category_new_file( $kernel->vars['file_cat_id'] );
				
				$kernel->archive->global_category_syncronisation();
				
				$kernel->archive->update_database_counter( "files" );
				$kernel->archive->update_database_counter( "data" );
				
				$kernel->admin->message_admin_report( "log_mass_file_added", $count, $add_data );
			}
			break;
		}
		
		#############################################################################
		
		default :
		{
			if( !@opendir( $kernel->config['system_root_dir_upload'] . $kernel->vars['current_directory'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_unable_to_read_folder'], M_ERROR );
			}
			else
			{			
				$kernel->tp->call( "admin_file_mass_add" );
			
				$kernel->page->construct_category_list( 0 );
				
				//Get files
				$kernel->vars['html']['directory_list_options'] = "<option value=\"\">" . DIR_STEP . " " . $kernel->ld['phrase_upload_dir_root'] . "</option>\r\n";
				
				//build upload directory tree
				$kernel->admin->read_directory_tree( $kernel->config['system_root_dir_upload'], $kernel->vars['current_directory'] );
				
				//read current folder
				$kernel->admin->read_upload_directory_index( $kernel->config['system_root_dir_upload'] . $kernel->vars['current_directory'], 0 );
				
				$kernel->tp->cache( "current_directory", $kernel->vars['current_directory'] );
				$kernel->tp->cache( "system_root_dir_upload", $kernel->config['system_root_dir_upload'] );
			}
			break;
		}
	}
}

?>

