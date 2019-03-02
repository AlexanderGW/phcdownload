<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'GAL_ADD' );

switch( $kernel->vars['action'] )
{
	#############################################################################

	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "gallery_name" => V_STR, "gallery_description" => V_STR ) );
		
		if( empty( $kernel->vars['gallery_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_gallery_no_name'], M_ERROR );
		}
		else
		{
			$gallerydata = array(
				"gallery_name" => $kernel->format_input( $kernel->vars['gallery_name'], T_DB ),
				"gallery_description" => $kernel->format_input( $kernel->vars['gallery_description'], T_DB ),
				"gallery_timestamp" => UNIX_TIME
			);
			
			if( is_array( $_POST['images'] ) )
			{
				$gallerydata['gallery_image_array'] = implode( ",", $_POST['images'] );
			}
			
			$kernel->db->insert( "galleries", $gallerydata );
			
			$kernel->archive->update_database_counter( "galleries" );

			$kernel->admin->message_admin_report( "log_gallery_added", $kernel->vars['gallery_name'] );
		}
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_gall_add" );
		
		$kernel->archive->construct_list_options( 0, "image", $kernel->db->query( "SELECT `image_id`, `image_name` FROM `" . TABLE_PREFIX . "images` ORDER BY `image_name`" ) );
		
		break;
	}
}

?>

