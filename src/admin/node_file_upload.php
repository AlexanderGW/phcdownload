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

$kernel->clean_array( "_REQUEST", array( "file_upload_name" => V_STR, "current_directory" => V_STR ) );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "upload" :
	{
		//file link method not provided
		if( empty( $_FILES['file_upload']['name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_url_local_file_upload'], M_ERROR, HALT_EXEC );
		}
		
		//prep file data for updating
		$file_info = $kernel->archive->file_url_info( $_FILES['file_upload']['file_name'] );
		
		//check for upload errors
		$kernel->page->verify_upload_details();
		
		//filetype not allowed
		if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_filetype'], M_ERROR, HALT_EXEC );
		}
			
  		//build URL to the specified upload folder, base upload folder url.
  		$kernel->vars['current_directory'] = $kernel->config['system_root_dir_upload'] . $kernel->vars['current_directory'] . DIR_STEP;
  	  		
  		//add sub directories if applicable.
  		$file_base_url = $kernel->config['system_root_dir_upload'] . substr( $kernel->vars['current_directory'], strlen( $kernel->config['system_root_dir_upload'] ), strlen( $kernel->vars['current_directory'] ) );
		
		//replacement upload file name
		if( !empty( $kernel->vars['file_upload_name'] ) ) $_FILES['file_upload']['name'] = basename( $kernel->vars['file_upload_name'] );
		
		//check for exisiting files under provided file name
		$new_file_name = $kernel->page->construct_upload_file_name( $_FILES['file_upload']['name'], $file_base_url );
		
		//upload
		if( !move_uploaded_file( $_FILES['file_upload']['tmp_name'], $file_base_url . $new_file_name ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_could_not_move_upload_file'], M_ERROR, HALT_EXEC );
		}
		
		@chmod( $file_base_url . $new_file_name, 0666 );
		
		$kernel->admin->message_admin_report( "log_file_uploaded", $new_file_name );
		
		//echo "<script type=\"text/javascript\" language=\"javascript\">parent.updateUploadProgress( '" . $new_file_name . "', '', '', '', '2' );</script>";
		
		break;
	}
	
	#############################################################################
	
	case "create" :
	{
		//file link method not provided
		if( empty( $_FILES['file_upload']['name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_url_local_file_upload'], M_ERROR, HALT_EXEC );
		}
		
		//prep file data for updating
		$file_info = $kernel->archive->file_url_info( $_FILES['file_upload']['file_name'] );
		
		//check for upload errors
		$kernel->page->verify_upload_details();
		
		//filetype not allowed
		if( $kernel->config['allow_unknown_url_linking'] == 0 AND $file_info['file_type_exists'] == false AND $kernel->session->vars['adminsession_group_id'] <> 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_filetype'], M_ERROR, HALT_EXEC );
		}
			
  		//build URL to the specified upload folder, base upload folder url.
  		$kernel->vars['current_directory'] = $kernel->config['system_root_dir_upload'] . $kernel->vars['current_directory'] . DIR_STEP;
  	  		
  		//add sub directories if applicable.
  		$file_base_url = $kernel->config['system_root_dir_upload'] . substr( $kernel->vars['current_directory'], strlen( $kernel->config['system_root_dir_upload'] ), strlen( $kernel->vars['current_directory'] ) );
		
		//replacement upload file name
		if( !empty( $kernel->vars['file_upload_name'] ) ) $_FILES['file_upload']['name'] = basename( $kernel->vars['file_upload_name'] );
		
		//check for exisiting files under provided file name
		$new_file_name = $kernel->page->construct_upload_file_name( $_FILES['file_upload']['name'], $file_base_url );
		
		//upload
		if( !move_uploaded_file( $_FILES['file_upload']['tmp_name'], $file_base_url . $new_file_name ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_could_not_move_upload_file'], M_ERROR, HALT_EXEC );
		}
		
		@chmod( $file_base_url . $new_file_name, 0666 );
		
		$kernel->admin->message_admin_report( "log_file_uploaded", $new_file_name );
		
		//echo "<script type=\"text/javascript\" language=\"javascript\">parent.updateUploadProgress( '" . $new_file_name . "', '', '', '', '2' );</script>";
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_file_upload" );
		
		$kernel->vars['html']['apc_cache_key'] = uniqid();
		
		$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => sprintf( $kernel->ld['phrase_image_total_max_upload_size'], $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) ) );
		
		$kernel->vars['html']['directory_list_options'] = "<option value=\"\">" . DIR_STEP . " " . $kernel->ld['phrase_upload_dir_root'] . "</option>\r\n";
		
		//build upload directory tree
		$kernel->admin->read_directory_tree( $kernel->config['system_root_dir_upload'] );
		
		break;
	}
}

?>

