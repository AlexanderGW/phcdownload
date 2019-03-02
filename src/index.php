<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$get_categories = $kernel->db->query( "SELECT `category_id`, `category_name`, `category_description`, `category_password`, `category_file_total` FROM `" . TABLE_PREFIX . "categories` WHERE `category_sub_id` = 0 ORDER BY `category_order`, `category_name`" );

//----------------------------------
// No categories in archive
//----------------------------------

if( $kernel->db->numrows() == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_categories'], M_NOTICE );
}
else
{
	$call_category_header = false;
	
	//----------------------------------
	// Setup page vars
	//----------------------------------
	
	$kernel->vars['page_struct']['system_page_action_title'] = $kernel->ld['phrase_page_title_index'];
	
	$allowed_categories = ( !empty( $kernel->session->vars['session_categories'] ) ) ? array_flip( unserialize( $kernel->session->vars['session_categories'] ) ) : array();
	
	//----------------------------------
	// Get categories
	//----------------------------------
	
	while( $category = $kernel->db->data( $get_categories ) )
	{
		//----------------------------------
		// Usergroup accessable categories
		//----------------------------------
		
		if( !isset( $allowed_categories[ $category['category_id'] ] ) AND $kernel->session->vars['session_group_id'] <> 1 ) continue;
		
		if( $call_category_header == false )
		{
			$kernel->tp->call( "category_header" );
			
			$call_category_header = true;
		}
		
		$kernel->tp->call( "category_row" );
		
		$category['category_name'] = $kernel->format_input( $category['category_name'], T_HTML );
		
		if( !empty( $category['category_description'] ) )
		{
			$category['category_description'] = $kernel->format_input( $category['category_description'], T_HTML ) . "<br />";
		}
		
		//----------------------------------
		// Fetch category new file
		//----------------------------------
		
		$category['category_newfile'] = $kernel->page->format_category_new_file( $category['category_id'], $category['category_password'] );
		
		//----------------------------------
		// Fetch sub categories
		//----------------------------------
		
		$category['category_sub_cats'] = $kernel->page->construct_sub_category_list( $category['category_id'], $category['category_password'] );
		
		//----------------------------------
		// Fetch category file count
		//----------------------------------
		
		$category['category_files'] = $kernel->page->format_category_file_count( $category['category_id'], $category['category_password'], $category['category_file_total'] );
		
		$kernel->tp->cache( $category );
	}
	
	if( $call_category_header == true )
	{
		$kernel->tp->call( "category_footer" );
		
		$kernel->page->construct_pagination_menu( false, R_CATEGORY, "category.php" );
		
		$kernel->page->construct_category_list( 0 );
	}
	
	//----------------------------------
	// No categories available to user
	//----------------------------------
	
	else
	{
		$kernel->page->message_report( $kernel->ld['phrase_no_usergroup_categories'], M_NOTICE );
	}
}

//----------------------------------
// Output page
//----------------------------------

$kernel->page->construct_output( R_HEADER, R_FOOTER, R_ANNOUNCEMENTS, R_NAVIGATION );

?>