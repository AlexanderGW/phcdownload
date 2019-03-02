<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'IMG_ADD' );

$kernel->clean_array( "_REQUEST", array( "current_directory" => V_STR, "limit" => V_INT ) );

if( empty( $kernel->vars['limit'] ) ) $kernel->vars['limit'] = 3;

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "create" :
	{
		$total_images = 0;
		
		if( is_array( $_FILES['image_upload']['name'] ) )
		{
			$total_files = sizeof( $_FILES['image_upload']['name'] );
			
			//overwrite filetypes global with just image types
			$kernel->filetype = array_flip( array( "BMP", "GIF", "JPG", "JPEG", "PNG", "TIFF", "TIF" ) );
			
			for( $i = 0; $i <= $total_files; $i++ )
			{
				if( empty( $_FILES['image_upload']['name'][ $i ] ) ) continue;
				
				//prep file data for updating
				$file_info = $kernel->archive->file_url_info( $_FILES['image_upload']['name'][ $i ] );
				
				//check for upload errors
				$kernel->page->verify_upload_details( "image_upload" );
				
				//filetype not allowed
				if( $file_info['file_type_exists'] == false )
				{
					$kernel->page->message_admin( $kernel->ld['phrase_bad_image_filetype'], M_ERROR, HALT_EXEC );
				}
				
				$image_directory = $kernel->config['system_root_dir_gallery'] . DIR_STEP;
				
				//check for exisiting files under provided file name
				$new_file_name = $kernel->page->construct_upload_file_name( $_FILES['image_upload']['name'][ $i ], $image_directory );
				
				//upload
				if( !move_uploaded_file( $_FILES['image_upload']['tmp_name'][ $i ], $image_directory . $new_file_name ) )
				{
					$kernel->page->message_admin( $kernel->ld['phrase_could_not_move_upload_file'] );
				}
				else
				{
					@chmod( $file_base_url . $new_file_name, 0666 );
					
					if( $_POST['option_add_to_database'] == "1" )
					{
						$imagedata = array(
							"image_name" => $kernel->format_input( $new_file_name, T_DB ),
							"image_file_name" => $kernel->format_input( $new_file_name, T_DB ),
							"image_timestamp" => UNIX_TIME
						);
						
						$image_dimensions = @getimagesize( $image_directory . $new_file_name );
						$imagedata['image_dimensions'] = $kernel->format_input( $image_dimensions[0] . "x" . $image_dimensions[1], T_DB );
      			
						if( $kernel->config['gd_thumbnail_feature'] == "true" )
						{
							$kernel->archive->check_file_permissions( $image_directory . "thumbs". DIR_STEP );
							
							$kernel->image->construct_thumbnail( $image_directory . $new_file_name, $image_directory . "thumbs" . DIR_STEP . $new_file_name, $image_dimensions );
							
							@chmod( $image_directory . "thumbs" . DIR_STEP . $new_file_name, 0666 );
						}
						
						$kernel->db->insert( "images", $imagedata );
					}
				}
				
				$image_names[] = $new_file_name;
				$total_images++;
			}
			
			$kernel->archive->update_database_counter( "images" );
		}
		
		if( $total_images == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_empty_image_upload'], M_ERROR, HALT_EXEC );
		}
		
		$kernel->admin->message_admin_report( "log_image_uploaded", $total_images, $image_names );
		
		//echo "<script type=\"text/javascript\" language=\"javascript\">parent.updateUploadProgress( '', '', '', '', '2' );</script>";
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->archive->construct_list_options( $kernel->vars['limit'], "image_row_limit", array( 3 => 3, 6 => 6, 9 => 9, 12 => 12, 15 => 15 ), false );
		
		$kernel->tp->call( "admin_imag_upload_header" );
		
		$kernel->vars['html']['apc_cache_key'] = uniqid();
		
		for( $i = 1; $i <= $kernel->vars['limit']; $i++ ) $kernel->tp->call( "admin_imag_upload_row" );
		
		$kernel->tp->call( "admin_imag_upload_footer" );
		
		$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => sprintf( $kernel->ld['phrase_image_total_max_upload_size'], $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) ) );
		
		break;
	}
}

?>

