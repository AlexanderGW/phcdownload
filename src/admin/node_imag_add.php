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
		$kernel->clean_array( "_REQUEST", array( "image_name" => V_STR, "image_file_name" => V_STR, "image_description" => V_STR ) );
		
		if( empty( $kernel->vars['image_file_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_image_selected'], M_ERROR );
		}
		else
		{
			if( empty( $kernel->vars['image_name'] ) )
			{
				$kernel->vars['image_name'] = $kernel->vars['image_file_name'];
			}
			
			$image_directory = $kernel->config['system_root_dir_gallery'] . DIR_STEP;
			
			$kernel->vars['image_file_name'] = $kernel->format_input( $kernel->vars['image_file_name'], T_URL_DEC );
			
			$imagedata = array(
				"image_name" => $kernel->format_input( $kernel->vars['image_name'], T_DB ),
				"image_file_name" => $kernel->format_input( $kernel->vars['image_file_name'], T_DB ),
				"image_description" => $kernel->format_input( $kernel->vars['image_description'], T_DB ),
				"image_timestamp" => UNIX_TIME
			);
			
			$image_dimensions = @getimagesize( $image_directory . $kernel->vars['image_file_name'] );
			$imagedata['image_dimensions'] = $kernel->format_input( $image_dimensions[0] . "x" . $image_dimensions[1], T_DB );
			
			if( $kernel->config['gd_thumbnail_feature'] == "true" )
			{
				$kernel->archive->check_file_permissions( $image_directory . "thumbs". DIR_STEP );
				
				$kernel->image->construct_thumbnail( $image_directory . $kernel->vars['image_file_name'], $image_directory . "thumbs" . DIR_STEP . $kernel->vars['image_file_name'], $image_dimensions );
			}
			
			$kernel->db->insert( "images", $imagedata );
			
			$kernel->archive->update_database_counter( "images" );
			
			$kernel->admin->message_admin_report( "log_image_added", $kernel->vars['image_name'] );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_imag_add" );
		
		//$kernel->db->numrows( "SELECT `image_id` FROM `" . TABLE_PREFIX . "images` WHERE `image_file_name` = '" . $image . "'" ) > 0" );
		$kernel->admin->read_directory_index( "image", $image['image_file_name'], "phrase_menu_choose_images", $kernel->config['system_root_dir_gallery'] . DIR_STEP, LIST_FILE );
		
		$kernel->page->construct_category_list();
		
		break;
	}
}

?>

