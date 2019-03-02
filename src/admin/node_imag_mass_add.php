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

switch( $kernel->vars['action'] )
{

	#############################################################################
	
	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "image_file_name" => V_STR ) );
		
		if( !is_array( $_POST['images'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_images_selected'], M_ERROR );
		}
		else
		{
			$count = 0;
			$last_image = "";
			
			//Add Images
			foreach( $_POST['images'] as $image )
			{
				if( $image == $last_image ) continue;
				
				$image_directory = $kernel->config['system_root_dir_gallery'] . DIR_STEP;
				
				$image = $kernel->format_input( $image, T_URL_DEC );
				
				$imagedata = array(
					"image_name" => $kernel->format_input( $image, T_DB ),
					"image_file_name" => $kernel->format_input( $image, T_DB ),
					"image_timestamp" => UNIX_TIME
				);
				
				$image_dimensions = @getimagesize( $image_directory . $image );
				$imagedata['image_dimensions'] = $kernel->format_input( $image_dimensions[0] . "x" . $image_dimensions[1], T_DB );
				
				if( $kernel->config['gd_thumbnail_feature'] == "true" )
				{
					$kernel->archive->check_file_permissions( $image_directory . "thumbs". DIR_STEP );
						
					$kernel->image->construct_thumbnail( $image_directory . $image, $image_directory . "thumbs" . DIR_STEP . $image, $image_dimensions );
				}
				
				$kernel->db->insert( "images", $imagedata );
				
				$add_data[] = $image;
				
				$count++;
				$last_image = $image;
			}
			
			$kernel->archive->update_database_counter( "images" );
			
			$kernel->admin->message_admin_report( "log_mass_image_added", $count, $add_data );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{		
		$kernel->tp->call( "admin_imag_mass_add" );
		
		$kernel->admin->read_image_directory_index( $kernel->config['system_root_dir_gallery'], 0 );
		
		break;
	}
}

?>

