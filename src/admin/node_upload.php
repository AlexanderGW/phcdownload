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

$kernel->clean_array( "_REQUEST", array( "current_directory" => V_STR ) );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "create" :
	{
		if( $kernel->vars['type'] == "file_add" )
		{
			$filedata = array();
			
			//file link method not provided
			if( empty( $_FILES['file_upload']['name'] ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_url_local_file_upload'], M_ERROR, HALT_EXEC );
			}
			
			//prep file data for updating
			$file_info = $kernel->archive->file_url_info( $_FILES['file_upload']['tmp_name'] );
			
			//check for upload errors
			$kernel->page->verify_upload_details();
			
			//filetype not allowed
			if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_bad_filetype'], M_ERROR, HALT_EXEC );
			}
			
			if( !empty( $_FILES['file_upload']['name'] ) )
			{
				$file_info = $kernel->archive->file_url_info( $_FILES['file_upload']['tmp_name'] );
				
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
				$new_file_name = $kernel->page->construct_upload_file_name( $kernel->vars['upload_file'], $file_full_url );
				
				//upload
				if( !@move_uploaded_file( $_FILES['file_upload']['tmp_name'], $file_full_dir . $new_file_name ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_could_not_move_upload_file'],  M_ERROR );
				}
				else
				{
					$filedata['file_dl_url'] = $file_full_url . $new_file_name;
					
					if( empty( $filedata['file_name'] ) )
					{
						$filedata['file_name'] = $new_file_name;
					}
					
					$filedata['file_size'] = $_FILES['file_upload']['size'];
				}
			}
			
			$filedata['file_image_array'] = $kernel->archive->construct_upload_images_list( $_FILES['image_upload'] );
			
			$kernel->db->update( "files", $filedata, "WHERE `file_id` = " . $file_id );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_file_add_upload" );
		
		$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => sprintf( $kernel->ld['phrase_image_total_max_upload_size'], $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) ) );
		
		$kernel->vars['html']['directory_list_options'] = "<option value=\"\">" . DIR_STEP . " " . $kernel->ld['phrase_upload_dir_root'] . "</option>\r\n";
		
		//build upload directory tree
		$kernel->admin->read_directory_tree( $kernel->config['system_root_dir_upload'] );
		
		break;
	}
}

?>

