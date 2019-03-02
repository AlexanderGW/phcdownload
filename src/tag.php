<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "category_id" => V_INT, "phrase" => V_STR ) );

if( $kernel->vars['page'] == 0 ) $kernel->vars['page'] = 1;
if( $kernel->vars['limit'] == 0 ) $kernel->vars['limit'] = $kernel->config['display_default_limit'];
if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = $kernel->config['display_default_sort'];
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = $kernel->config['display_default_order'];

if( $kernel->vars['id'] > 0 )
{
	$kernel->vars['page_struct']['system_page_navigation_id'] = $kernel->vars['id'];
}

$kernel->vars['page_struct']['system_page_navigation_html'] = array( "tag.php?string=" . $kernel->vars['phrase'] . "" => sprintf( $kernel->ld['phrase_searching'], $kernel->vars['phrase'] ) );
$kernel->vars['page_struct']['system_page_action_title'] = sprintf( $kernel->ld['phrase_page_title_search'], $kernel->vars['phrase'] );

if( $kernel->session->read_permission_flag( 'FIL_SRH', true ) == true )
{
	if( !empty( $kernel->vars['phrase'] ) )
	{
		$tag_syntax = "";
		
		$tag_phrases = explode( " ", $kernel->vars['phrase'] );
		
		foreach( $tag_phrases AS $phrase )
		{
			$tag_syntax .= "t.tag_phrase = '" . $phrase . "' OR ";
		}
		$tag_syntax = preg_replace( "/OR $/", "", $tag_syntax );
		
		//Basic search query
		$check_files = $kernel->db->query( "SELECT f.*, t.tag_file_id FROM " . TABLE_PREFIX . "tags t LEFT JOIN " . TABLE_PREFIX . "files f ON ( t.tag_file_id = f.file_id ) WHERE f.file_disabled = 0 AND " . $tag_syntax . " GROUP BY t.tag_file_id" );
		
		//No results
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_search_no_files'], M_NOTICE );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_files ) );
			
			$kernel->vars['html']['file_custom_fields_headers'] = "";
			$total_fields = 0;
			$last_file_status = 0;
			
			$kernel->tp->call( "search_results_header" );
			
			// Run paginated search query
			$get_files = $kernel->db->query( "SELECT f.*, t.tag_file_id FROM " . TABLE_PREFIX . "tags t LEFT JOIN " . TABLE_PREFIX . "files f ON ( t.tag_file_id = f.file_id ) WHERE f.file_disabled = 0 AND " . $tag_syntax . " GROUP BY t.tag_file_id ORDER BY f.file_pinned DESC, " . $kernel->vars['sort'] . " " . $kernel->vars['order'] . ", f.file_name LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			// Check for custom fields
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
			
			// fetch and display category files
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
			
			$kernel->tp->call( "file_footer" );
			
			$kernel->vars['pagination_vars'] = array( 'phrase' => $kernel->format_input( $kernel->vars['phrase'], T_URL_ENC ), 'sort' => $kernel->vars['sort'], 'order' => $kernel->vars['order'], 'id' => $kernel->vars['id'] );
			
			$kernel->page->construct_pagination_menu( R_FILE, false, SCRIPT_NAME );
		}
	}
	
	$kernel->page->construct_category_list( $kernel->vars['id'] );
}

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>