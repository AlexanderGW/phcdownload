<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'FIL_MAN' );

$kernel->clean_array( "_REQUEST", array( "search" => V_STR, "replace" => V_STR, "search_categories" => V_ARY ) );

switch( $kernel->vars['action'] )
{

	#############################################################################
	
	case "confirm" :
	{
		if( empty( $kernel->vars['search'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_url_search_terms'], M_ERROR );
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_url_not_parsed_after_replace'], M_ALERT );
			
			$fetch_files = $kernel->db->query( "SELECT `file_id` FROM `" . TABLE_PREFIX . "files` WHERE `file_dl_url` LIKE '%" . $kernel->vars['search'] . "%'" );
			
			if( $kernel->db->numrows( $fetch_files ) == 0 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_file_url_matches'] );
			}
			else
			{
				$kernel->tp->call( "admin_file_replace_confirm" );
				
				$kernel->tp->cache( "file_search", $kernel->vars['search'] );
				$kernel->tp->cache( "file_replace", $kernel->vars['replace'] );
				
				$kernel->ld['phrase_url_search_info'] = sprintf( $kernel->ld['phrase_url_search_info'], $kernel->db->numrows( $fetch_files ) );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "replace" :
	{
		$replace_data = array();
		
		$fetch_files = $kernel->db->query( "SELECT `file_id`, `file_name`, `file_dl_url` FROM `" . TABLE_PREFIX . "files` WHERE `file_dl_url` LIKE '%" . $kernel->vars['search'] . "%'" );
		
		while( $file = $kernel->db->data( $fetch_files ) )
		{
			$kernel->db->update( "files", array( "file_dl_url" => str_replace( $kernel->vars['search'], $kernel->vars['replace'], $file['file_dl_url'] ) ), "WHERE `file_id` = " . $file['file_id'] );
			
			$replace_data[] = $file['file_name'];
			$count++;
		}
		
		if( $count > 0 ) $kernel->admin->message_admin_report( "log_file_url_replace", $count, $replace_data );
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_file_replace_form" );
		
		$kernel->page->construct_category_list();
		
		break;
	}
}

?>

