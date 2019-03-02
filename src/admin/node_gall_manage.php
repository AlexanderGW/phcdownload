<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'GAL_MAN', 'GAL_DEL' );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "gallery_id" => V_INT ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'GAL_MAN' );
		
		$kernel->tp->call( "admin_gall_edit" );
		
		$gallery = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "galleries` WHERE `gallery_id` = " . $kernel->vars['gallery_id'] . " LIMIT 1" );
		
		$kernel->page->construct_category_list( $gallery['gallery_file_id'] );
		
		$gallery['gallery_title'] = $kernel->format_input( $gallery['gallery_title'], T_FORM );
		$gallery['gallery_data'] = $kernel->format_input( $gallery['gallery_data'], T_FORM );
		
		$kernel->archive->construct_list_options( explode( ",", $gallery['gallery_image_array'] ), "image", $kernel->db->query( "SELECT `image_id`, `image_name` FROM `" . TABLE_PREFIX . "images` ORDER BY `image_name`" ) );
		
		$kernel->tp->cache( $gallery );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'GAL_MAN' );
		
		$kernel->clean_array( "_REQUEST", array( "gallery_name" => V_STR, "gallery_description" => V_STR ) );
		
		$gallerydata = array(
			"gallery_name" => $kernel->format_input( $kernel->vars['gallery_name'], T_DB ),
			"gallery_description" => $kernel->format_input( $kernel->vars['gallery_description'], T_DB ),
			"gallery_timestamp" => UNIX_TIME
		);
		
		if( is_array( $_POST['images'] ) )
		{
			$gallerydata['gallery_image_array'] = implode( ",", $_POST['images'] );
		}

		$kernel->db->update( "galleries", $gallerydata, "WHERE `gallery_id` = " . $kernel->vars['gallery_id'] );
		
		$kernel->archive->update_database_counter( "galleries" );
		
		$kernel->admin->message_admin_report( "log_gallery_edited", $kernel->vars['gallery_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "delete" :
	{
		$kernel->admin->read_permission_flags( 'GAL_DEL' );
		
		$delete_count = 0;
		
		if( $kernel->vars['gallery_id'] > 0 )
		{
			$delete_data[] = $kernel->db->item( "SELECT `gallery_name` FROM `" . TABLE_PREFIX . "galleries` WHERE `gallery_id` = " . $kernel->vars['gallery_id'] );
			
			$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "galleries` WHERE `gallery_id` = " . $kernel->vars['gallery_id'] );
			$delete_count++;
		}
		elseif( is_array( $_POST['checkbox'] ) )
		{
			foreach( $_POST['checkbox'] AS $gallery )
			{
				$delete_data[] = $kernel->db->item( "SELECT `gallery_name` FROM `" . TABLE_PREFIX . "galleries` WHERE `gallery_id` = " . $gallery );
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "galleries` WHERE `gallery_id` = " . $gallery );
				$delete_count++;
			}
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
		}
		
		$kernel->archive->update_database_counter( "galleries" );
		
		$kernel->admin->message_admin_report( "log_gallery_deleted", $delete_count, $delete_data );
		
		break;
	}
	
	#############################################################################
	
	default :
	{		
		$check_galleries = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "galleries` ORDER BY `gallery_id`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_galleries'], M_ERROR );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_galleries ) );
			
			$kernel->tp->call( "admin_gall_header" );
			
			$get_galleries = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "galleries` ORDER BY `gallery_name`, `gallery_id` LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			while( $gallery = $kernel->db->data( $get_galleries ) )
			{
				$kernel->tp->call( "admin_gall_row" );
				
				$gallery['gallery_name'] = $kernel->format_input( $gallery['gallery_name'], T_NOHTML );
				$gallery['gallery_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $gallery['gallery_description'], T_NOHTML ), $kernel->config['string_max_words'] );
				
				$kernel->tp->cache( $gallery );
			}
			
			$kernel->tp->call( "admin_gall_footer" );
			
			$kernel->page->construct_category_filters();
			
			$kernel->page->construct_pagination( array(), $kernel->config['admin_pagination_page_proximity'] );
		}
		
		break;
	}
}

?>

