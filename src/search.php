<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "id" => V_INT, "string" => V_STR ) );

if( $kernel->vars['page'] == 0 ) $kernel->vars['page'] = 1;
if( $kernel->vars['limit'] == 0 ) $kernel->vars['limit'] = $kernel->config['display_default_limit'];
if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = $kernel->config['display_default_sort'];
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = $kernel->config['display_default_order'];

if( $kernel->vars['id'] > 0 )
{
	$kernel->vars['page_struct']['system_page_navigation_id'] = $kernel->vars['id'];
}

$kernel->vars['string'] = $kernel->format_input( $kernel->vars['string'], T_STR );

$kernel->vars['page_struct']['system_page_navigation_html'] = array( "search.php?string=" . $kernel->vars['string'] . "" => sprintf( $kernel->ld['phrase_searching'], $kernel->vars['string'] ) );
$kernel->vars['page_struct']['system_page_action_title'] = sprintf( $kernel->ld['phrase_page_title_search'], $kernel->vars['string'] );

//----------------------------------
// Check access permissions for searching
//----------------------------------

if( $kernel->session->read_permission_flag( 'FIL_SRH', true ) == true )
{
	$kernel->tp->call( "search" );
	
	if( !empty( $kernel->vars['string'] ) )
	{
		$file_ranking_syntax = $file_tag_syntax = "";
		
		//----------------------------------
		// Tags
		//----------------------------------
		
		foreach( explode( " ", $kernel->vars['string'] ) AS $phrase )
		{
			$tag[ $phrase ] = $kernel->db->numrows( "SELECT `tag_id` FROM `" . TABLE_PREFIX . "tags` WHERE `tag_phrase` = '" . $phrase . "'" );
			$tag_syntax .= "t.tag_phrase = '" . $phrase . "' OR ";
		}
		$tag_syntax = preg_replace( "/OR $/", "", $tag_syntax );
		
		//----------------------------------
		// Prepare search query
		//----------------------------------
		
		if( $kernel->config['archive_search_mode'] == 2 )
		{
			$search_syntax = $tag_syntax;
		}
		elseif( $kernel->config['archive_search_mode'] == 1 )
		{
			$search_syntax = "MATCH( f.file_name, f.file_description, f.file_version, f.file_author ) AGAINST ( '*" . $kernel->vars['string'] . "*' IN BOOLEAN MODE )";
		}
		else
		{
			$search_syntax = "MATCH( f.file_name, f.file_description, f.file_version, f.file_author ) AGAINST ( '*" . $kernel->vars['string'] . "*' )";
		}
		
		if( $kernel->vars['id'] > 0 )
		{
			$search_category = "AND f.file_cat_id = " . $kernel->vars['id'];
		}
		
		if( $kernel->vars['sort'] == "file_ranking" )
		{
			$file_ranking_syntax = ", ( f.file_rating / f.file_votes ) AS file_ranking";
		}
		
		if( $kernel->config['archive_search_mode'] == 2 )
		{
			//----------------------------------
			// Basic search query
			//----------------------------------
			
			$check_files = $kernel->db->query( "SELECT f.* " . $file_ranking_syntax . " FROM " . TABLE_PREFIX . "files f JOIN " . TABLE_PREFIX . "tags t WHERE " . $search_syntax . " " . $search_category . " AND f.file_disabled = 0 GROUP BY t.tag_file_id" );
		}
		else
		{
			//----------------------------------
			// FULLTEXT search
			//----------------------------------
			
			$check_files = $kernel->db->query( "SELECT f.* " . $file_ranking_syntax . " FROM " . TABLE_PREFIX . "files f WHERE " . $search_syntax . " " . $search_category . " AND f.file_disabled = 0" );
		}
		
		//----------------------------------
		// No results
		//----------------------------------
		
		if( $kernel->db->numrows( $check_files ) == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_search_no_files'], M_NOTICE );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_files ) );
			
			$kernel->vars['html']['file_custom_fields_headers'] = "";
			$total_fields = 0;
			$last_file_status = 0;
			
			//----------------------------------
			// Call search result header template
			//----------------------------------
			
			$kernel->tp->call( "search_results_header" );
			
			//----------------------------------
			// Run tag search
			//----------------------------------
			
			if( $kernel->config['archive_search_mode'] == 2 )
			{
				$get_files = $kernel->db->query( "SELECT f.* " . $file_ranking_syntax . " FROM " . TABLE_PREFIX . "tags t LEFT JOIN " . TABLE_PREFIX . "files f ON ( t.tag_file_id = f.file_id ) WHERE " . $search_syntax . " " . $search_category . " AND f.file_disabled = 0 GROUP BY t.tag_file_id ORDER BY f.file_pinned DESC, " . $kernel->vars['sort'] . " " . $kernel->vars['order'] . ", f.file_name LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			}
			
			//----------------------------------
			// Run regular search
			//----------------------------------
			
			else
			{
				$get_files = $kernel->db->query( "SELECT f.* " . $file_ranking_syntax . " FROM " . TABLE_PREFIX . "files f WHERE " . $search_syntax . " " . $search_category . " AND f.file_disabled = 0 ORDER BY f.file_pinned DESC, " . $kernel->vars['sort'] . " " . $kernel->vars['order'] . ", f.file_name LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			}
			
			//----------------------------------
			// Check for custom fields
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
			// Fetch and display category files
			//----------------------------------
			
			while( $file = $kernel->db->data( $get_files ) )
			{
				if( $last_file_status == 1 AND $file['file_pinned'] == 0 )
				{
					$kernel->tp->call( "file_row_break" );
				}
				$last_file_status = $file['file_pinned'];
				
				$kernel->tp->call( "file_row" );
				
				//----------------------------------
				// File search result vars
				//----------------------------------
				
				$file['file_icon'] = $kernel->archive->construct_file_icon( $file['file_dl_url'], $file['file_icon'] );
				$file['file_rank'] = $kernel->archive->construct_file_rating( $file['file_rating'], $file['file_votes'] );
				$file['file_description'] = $kernel->archive->return_string_words( $kernel->format_input( $file['file_description'], T_NOHTML ), $kernel->config['string_max_length'] );
				$file['file_custom_fields'] = $kernel->archive->construct_file_custom_fields( $file['file_id'] );
				
				$file['file_timestamp'] = $kernel->fetch_time( $file['file_timestamp'], DF_SHORT );
				$file['file_mark_timestamp'] = $kernel->fetch_time( $file['file_mark_timestamp'], DF_SHORT );
				
				$file['file_size'] = $kernel->format_input( $file['file_size'], T_NUM );
				$file['file_author'] = $kernel->format_input( $file['file_author'], T_STR );
				$file['file_downloads'] = $kernel->format_input( $file['file_downloads'], T_NUM );
				$file['file_views'] = $kernel->format_input( $file['file_views'], T_NUM );
				$file['file_votes'] = $kernel->format_input( $file['file_votes'], T_NUM );
				$file['file_name'] = $kernel->format_input( $file['file_name'], T_STR );
				
				$file['file_prefix'] = ( $file['file_pinned'] == 1 ) ? $kernel->ld['phrase_title_pinned'] : "";
				
				$kernel->tp->cache( $file );
			}
			
			$kernel->tp->call( "file_footer" );
			
			//----------------------------------
			// Prepare pagination vars
			//----------------------------------
			
			$kernel->vars['pagination_vars'] = array( 'string' => $kernel->format_input( $kernel->vars['string'], T_URL_ENC ), 'sort' => $kernel->vars['sort'], 'order' => $kernel->vars['order'], 'id' => $kernel->vars['id'] );
			
			//----------------------------------
			// Construct pagination
			//----------------------------------
			
			$kernel->page->construct_pagination_menu( R_FILE, false, SCRIPT_NAME );
		}
	}
	
	$kernel->page->construct_category_list( $kernel->vars['id'] );
}

//----------------------------------
// Output page
//----------------------------------

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>