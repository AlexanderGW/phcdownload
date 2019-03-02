<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "id" => V_INT, "string" => V_BOOL ) );

if( $kernel->vars['page'] == 0 ) $kernel->vars['page'] = 1;
if( $kernel->vars['limit'] == 0 ) $kernel->vars['limit'] = $kernel->config['display_default_limit'];
if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = $kernel->config['display_default_sort'];
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = $kernel->config['display_default_order'];

//----------------------------------
// No ID ref
//----------------------------------

if( $kernel->vars['id'] == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_category'], M_ERROR );
}
else
{
	$get_category = $kernel->db->query( "SELECT `category_id`, `category_name`, `category_description` FROM " . TABLE_PREFIX . "categories WHERE category_id = " . $kernel->vars['id'] );
	
	//----------------------------------
	// Invalid ID ref
	//----------------------------------
	
	if( $kernel->db->numrows() == 0 )
	{
		$kernel->page->message_report( $kernel->ld['phrase_invalid_category'], M_ERROR );
	}
	else
	{
		$call_category_header = false;
		
		$this_category = $kernel->db->row( $get_category );
		
		//----------------------------------
		// Setup page vars
		//----------------------------------
		
		$kernel->vars['page_struct']['system_page_navigation_id'] = $kernel->vars['page_struct']['system_page_announcement_id'] = $kernel->vars['id'];
		$kernel->vars['page_struct']['system_page_action_title'] = sprintf( $kernel->ld['phrase_page_title_category'], $this_category['category_name'] );
		
		//----------------------------------
		// Check category permissions
		//----------------------------------
		
		$kernel->page->read_category_permissions( $kernel->vars['id'], SCRIPT_PATH );
		
		//----------------------------------
		// Check for subscriptions
		//----------------------------------
		
		$kernel->subscription->init_category_subscriptions( $kernel->vars['id'], SCRIPT_PATH );
		
		//----------------------------------
		// Sub-categories
		//----------------------------------
		
		$get_categories = $kernel->db->query( "SELECT `category_id`, `category_name`, `category_description`, `category_password`, `category_file_total` FROM `" . TABLE_PREFIX . "categories` WHERE `category_sub_id` = " . $kernel->vars['id'] . " ORDER BY `category_order`, `category_name`" );
		
		if( $kernel->db->numrows() > 0 AND ( ( $kernel->config['category_subcategory_mode'] == 1 AND $kernel->vars['page'] == 1 ) OR ( $kernel->config['category_subcategory_mode'] == 2 ) ) )
		{
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
			}
		}
		
		//----------------------------------
		// Category description
		//----------------------------------
		
		if( !empty( $this_category['category_description'] ) AND $kernel->config['archive_show_category_description'] == 1 )
		{
			$kernel->tp->call( "category_description" );
			
			$this_category['category_description'] = $kernel->format_input( $this_category['category_description'], T_HTML );
			
			$kernel->tp->cache( $this_category );
		}
		
		//----------------------------------
		// Files
		//----------------------------------
		
		$check_files = $kernel->db->query( "SELECT f.file_id FROM " . TABLE_PREFIX . "files f WHERE f.file_cat_id = " . $kernel->vars['id'] . " AND f.file_disabled = 0" );
		
		if( $kernel->db->numrows() == 0 )
		{
			if( $kernel->db->numrows( $get_categories ) == 0 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_category_files'], M_NOTICE );
			}
		}
		else
		{
			//----------------------------------
			// Setup pagination
			//----------------------------------
			
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_files ) );
			
			$file_ranking_syntax = "";
			$kernel->vars['html']['file_custom_fields_headers'] = "";
			$total_fields = $last_file_status = 0;
			
			if( $kernel->vars['sort'] == "file_ranking" )
			{
				$file_ranking_syntax = ", ( f.file_rating / f.file_votes ) AS file_ranking";
			}
			
			$get_files = $kernel->db->query( "SELECT f.* " . $file_ranking_syntax . " FROM " . TABLE_PREFIX . "files f WHERE f.file_cat_id = " . $kernel->vars['id'] . " AND f.file_disabled = 0 ORDER BY f.file_pinned DESC, " . $kernel->vars['sort'] . " " . $kernel->vars['order'] . ", f.file_name LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			//----------------------------------
			// Setup custom fields
			//----------------------------------
			
			$get_fields = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "fields` WHERE `field_category_display` = 1 ORDER BY `field_name`" );
			
			if( $kernel->db->numrows( $get_fields ) > 0 )
			{
				while( $field = $kernel->db->data( $get_fields ) )
				{
					$kernel->vars['html']['file_custom_fields_headers'] .= $kernel->tp->call( "file_field_subheader", CALL_TO_PAGE );
					$kernel->vars['html']['file_custom_fields_headers'] = $kernel->tp->cache( "field_name", $field['field_name'], $kernel->vars['html']['file_custom_fields_headers'] );
					
					$total_fields++;
				}
			}
			
			$fields['file_total_columns'] = $total_fields + $kernel->config['style_file_column_count'];
			
			$kernel->tp->call( "file_header" );
			
			$kernel->tp->cache( $fields );
			
			//----------------------------------
			// Get files
			//----------------------------------
			
			while( $file = $kernel->db->data( $get_files ) )
			{
				if( $last_file_status == 1 AND $file['file_pinned'] == 0 )
				{
					$kernel->tp->call( "file_row_break" );
				}
				$last_file_status = $file['file_pinned'];
				
				$kernel->tp->call( "file_row" );
				
				$file['file_icon'] = $kernel->archive->construct_file_icon( $file['file_dl_url'], $file['file_icon'] );
				$file['file_rank'] = $kernel->archive->construct_file_rating( $file['file_rating'], $file['file_votes'] );
				$file['file_description'] = $kernel->archive->return_string_words( $kernel->format_input( $file['file_description'], T_NOHTML ), $kernel->config['string_max_length'] );
				$file['file_custom_fields'] = $kernel->archive->construct_file_custom_fields( $file['file_id'] );
				
				$file['file_timestamp'] = $kernel->fetch_time( $file['file_timestamp'], DF_SHORT );
				$file['file_mark_timestamp'] = $kernel->fetch_time( $file['file_mark_timestamp'], DF_SHORT );
				
				$file['file_size'] = $kernel->format_input( $file['file_size'], T_NUM );
				$file['file_author'] = $kernel->format_input( $file['file_author'], T_HTML );
				$file['file_downloads'] = $kernel->format_input( $file['file_downloads'], T_NUM );
				$file['file_views'] = $kernel->format_input( $file['file_views'], T_NUM );
				$file['file_votes'] = $kernel->format_input( $file['file_votes'], T_NUM );
				$file['file_name'] = $kernel->format_input( $file['file_name'], T_NOHTML );
				
				$file['file_prefix'] = ( $file['file_pinned'] == 1 ) ? $kernel->ld['phrase_title_pinned'] : "";
				
				$kernel->tp->cache( $file );
			}
			
			//----------------------------------
			// Fetch file template
			//----------------------------------
			
			$kernel->tp->call( "file_footer" );
			
			//----------------------------------
			// Prepare pagination vars
			//----------------------------------
			
			$kernel->vars['pagination_vars'] = array( 'sort' => $kernel->vars['sort'], 'order' => $kernel->vars['order'], 'id' => $kernel->vars['id'] );
			
			//----------------------------------
			// Construct pagination
			//----------------------------------
			
			$kernel->page->construct_pagination_menu( R_FILE, R_CATEGORY, SCRIPT_NAME );
			
			//----------------------------------
			// Construct category list
			//----------------------------------
			
			$kernel->page->construct_category_list( $kernel->vars['id'] );
		}
	}
}

//----------------------------------
// Output page
//----------------------------------

$kernel->page->construct_output( R_HEADER, R_FOOTER, R_ANNOUNCEMENTS, R_NAVIGATION );

?>