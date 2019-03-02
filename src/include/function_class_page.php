<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and use.
################################################################################

define( "M_CRITICAL_ERROR",	0 );
define( "M_ERROR",				1 );
define( "M_WARNING",			2 );
define( "M_ALERT",				3 );
define( "M_NOTICE",			4 );
define( "M_MAINTENANCE",		5 );

define( "HALT_EXEC",			1 );

define( "R_ANNOUNCEMENTS",		true );
define( "R_HEADER",			true );
define( "R_FOOTER",			true );
define( "R_NAVIGATION",		true );

define( "C_FILE",				true );
define( "C_CATEGORY",			true );

class class_page_function
{
	/*
	 * Construct page output
	 **/
	
	function construct_output( $return_header = false, $return_footer = false, $return_announcements = false, $return_navigation = false )
	{
		global $kernel;
		
		if( $return_header !== false )
		{
			if( !defined( "IN_ACP" ) )
			{
				$kernel->tp->call( "page_header", CALL_DB, CACHE_TO_TOP );
				
				$style = $kernel->db->row( "SELECT `style_data`, `style_cache` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->config['default_style'] );
				
				$kernel->vars['page_struct']['system_css_data'] = ( empty( $style['style_cache'] ) ) ? "<style type=\"text/css\"><!--" . $style['style_data'] . "--></style>" : "<link type=\"text/css\" rel=\"Stylesheet\" href=\"./include/css/" . $style['style_cache'] . "\" />";
				
				//$style = $kernel->db->row( "SELECT `style_sheet_data`, `style_custom_data`, `style_cache` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->config['default_style'] );
				
				//$kernel->vars['page_struct']['system_css_data'] = ( empty( $style['style_cache'] ) OR $kernel->config['style_cache_to_file'] == 0 ) ? "<style type=\"text/css\"><!--" . $style['style_sheet_data'] . "--></style>" : "<link type=\"text/css\" rel=\"Stylesheet\" href=\"./include/css/" . $style['style_cache'] . "\" />";
			}
			else
			{
				$kernel->vars['html']['page_node_header'] = ( !empty( $kernel->vars['page_struct']['system_page_action_title'] ) AND !empty( $kernel->vars['node'] ) ) ? "<div class=\"header_logo\">" . $kernel->vars['page_struct']['system_page_action_title'] . "</div>" : "";
				
				$kernel->tp->call( "admin_header", CALL_DB, CACHE_TO_TOP );
			}
		}
		
		if( $return_announcements !== false AND !defined( "IN_ACP" ) )
		{
			$this->construct_announcements( $kernel->vars['page_struct']['system_page_announcement_id'] );
		}
		
		if( $return_navigation !== false AND !defined( "IN_ACP" ) )
		{
			$this->construct_navigation( $kernel->vars['page_struct']['system_page_navigation_id'], $kernel->vars['page_struct']['system_page_navigation_html'] );
		}
		
		if( $return_footer !== false )
		{
			if( !defined( "IN_ACP" ) )
			{
				$kernel->tp->call( "page_footer" );
				
				if( $kernel->session->read_permission_flag( 'FIL_SUB' ) == false OR $kernel->config['archive_allow_upload'] == "false" )
				{
					$kernel->ld['phrase_file_submit'] = "";
				}
				
				if( $kernel->session->read_permission_flag( 'FIL_SRH' ) == false )
				{
					$kernel->ld['phrase_header_search'] = "";
				}
				
				if( $kernel->session->vars['session_group_id'] <> 1 )
				{
					$kernel->ld['phrase_control_panel'] = "";
				}
			}
			else
			{
				$kernel->tp->call( "admin_copyright_notice" );
				
				$kernel->tp->call( "admin_footer" );
			}
			
			$microtime = explode( " ", microtime() );
			define( "PAGE_EXEC_END", $microtime[1] + $microtime[0] );
			
			$kernel->vars['page_struct']['system_page_exec_time'] = round( PAGE_EXEC_END - PAGE_EXEC_START, 4 );
		}
		
		if( !defined( "IN_ACP" ) )
		{
			$this->construct_session();
		}
		
		if( is_array( $kernel->session->vars ) )
		{
			$kernel->tp->cache( $kernel->session->vars );
		}
		
		//cached templates
		$kernel->tp->cache( $kernel->vars['html'] );
		
		//page titles etc
		$kernel->tp->cache( $kernel->vars['page_struct'] );
		
		//cleaned variables used in script
		$kernel->tp->cache( $kernel->vars );
		
		//loaded language phrases
		$kernel->tp->cache( $kernel->ld );
		
		//if archive offline and logged in as an admin, show message
		if( !defined( "IN_ACP" ) AND $kernel->session->vars['session_group_id'] == 1 AND $kernel->config['archive_offline'] == 1 )
		{
			echo "<div style=\"background-color: #ffff99; color: #000000; border: 1px solid #000000; padding: 10px; font-family: Verdana, Arial, Helvetica; font-size: 12px; text-align: center;\"><b>" . $kernel->ld['phrase_admin_message_archive_offline'] . "</b></div>\r\n\r\n";
		}
		
		//send template cache to browser, all done!
		$kernel->tp->dump();
		
		$this->exec_debug_console( null );
		
		$kernel->db->disconnect();
		
		unset( $kernel );
		
		exit;
	}
	
	/*
	 * Construct and print debugging details
	 **/
	
	function exec_debug_console()
	{
		global $kernel;
		
		if( $kernel->config['debug_mode'] >= 1 )
		{
			$memory_usage_percent = @round( ( function_exists( "memory_get_usage" ) ? memory_get_usage( true ) : 0 ) * ( 100 / ( ini_get( "memory_limit" ) * 1048576 ) ), 2 );
			
			$memory_usage_size = $kernel->archive->format_round_bytes( function_exists( "memory_get_usage" ) ? memory_get_usage( true ) : 0 );
			$memory_limit_size = $kernel->archive->format_round_bytes( ( ini_get( "memory_limit" ) * 1048576 ) );
			
			echo "\r\n\r\n<!-- START DEBUGGING CONSOLE --><div style=\"background-color: #ffff99; color: #000000; border: 1px solid #000000; padding: 10px; margin-top: 10px; font-family: Verdana; font-size: 12px;\">";
			echo "<span style=\"color: #000000; font-size: 16px;\">Debugging Console</span><br />";
			echo "<strong>Page Rendered (seconds):</strong> " . $kernel->vars['page_struct']['system_page_exec_time'] . "</strong>&nbsp; ";
			echo "<strong>Memory Used:</strong> " . $memory_usage_percent . "% (" . $memory_usage_size . "/" . $memory_limit_size . ")</strong>&nbsp; ";
			
			echo "<strong>Queries executed:</strong> " . $kernel->db->info['total_executed'] . "</strong>&nbsp; ";
			echo "<strong>Database driver:</strong> " . $kernel->config['db_driver'] . "</strong>&nbsp; ";
			
			echo "<strong>Templates loaded:</strong> " . $kernel->tp->info['total_loaded'] . "</strong>&nbsp; ";
			echo "<strong>Template variables:</strong> " . $kernel->tp->info['total_variables'] . "</strong>&nbsp; ";
			echo "<strong>Template arrays:</strong> " . $kernel->tp->info['total_arrays'] . "</strong>&nbsp; ";
			echo "</div><!-- END DEBUGGING CONSOLE -->";
			
			if( $kernel->config['debug_mode'] >= 2 )
			{
				$query_no = 1;
				
				foreach( $kernel->db->info['query_string'] AS $item )
				{
					//hide hashes from people who arn't administrators
					$item[0] = preg_replace( "/([0-9a-z]{32}+)/", ( ( $kernel->session->vars['session_group_id'] == 1 OR $kernel->session->vars['adminsession_group_id'] == 1 ) ? "<i>\\1</i>" : "******" ), $item[0] );
					$item[0] = preg_replace( "/([0-9]+)/", "<i>\\1</i>", $item[0] );
					$item[0] = preg_replace( "/([A-Z]{2,}+)/", "<b>\\1</b>", $item[0] );
					
					echo "\r\n\r\n<div style=\"background-color: #ffc999; color: #000000; border: 1px solid #000000; padding: 6px 10px 6px 10px; margin-top: 10px;\">";
					echo "<span style=\"font-size: 11px; font-family: Verdana;\">Query " . $query_no . " (" . $item[1] . " seconds): " . $item[0];
					
					if( !empty( $item[2] ) )
					{
						echo "<br /><hr style=\"border: 1px solid #000000; height: 1px;\" noshade=\"true\" />";
						
						foreach( $item[2] AS $key => $value )
						{
							echo "<b>" . $key . ":</b> " . $value . "&nbsp;&nbsp;";
						}
					}
					
					echo "</span></div>";
					
					$query_no++;
				}
			}
		}
	}
	
	/*
	 * Construct system message
	 **/
	
	function message_report( $string, $level = M_NOTICE, $exit = 0, $cache_level = 0 )
	{
		global $kernel;
		
		if( empty( $string ) ) return;
		
		if( $exit == HALT_EXEC ) $kernel->tp->template_cache = "";
		
		if( defined( "IN_ACP" ) )
		{
			$kernel->tp->call( "admin_message_box", 0, $cache_level );
		}
		else
		{
			$kernel->tp->call( "message_box", 0, $cache_level );
		}
		
		if( $level == M_CRITICAL_ERROR )
		{
			$kernel->tp->cache( "message_title", $kernel->ld['phrase_title_critical_error'] );
			$kernel->tp->cache( "message_class", "critical_error" );
			$exit = 1;
		}
		elseif( $level == M_ERROR )
		{
			$kernel->tp->cache( "message_title", $kernel->ld['phrase_title_error'] );
			$kernel->tp->cache( "message_class", "error" );
		}
		elseif( $level == M_WARNING )
		{
			$kernel->tp->cache( "message_title", $kernel->ld['phrase_title_warning'] );
			$kernel->tp->cache( "message_class", "warning" );
		}
		elseif( $level == M_ALERT )
		{
			$kernel->tp->cache( "message_title", $kernel->ld['phrase_title_alert'] );
			$kernel->tp->cache( "message_class", "alert" );
		}
		elseif( $level == M_NOTICE )
		{
			$kernel->tp->cache( "message_title", $kernel->ld['phrase_title_notice'] );
			$kernel->tp->cache( "message_class", "notice" );
		}
		elseif( $level == M_MAINTENANCE )
		{
			$kernel->tp->cache( "message_title", $kernel->ld['phrase_title_maintenance'] );
			$kernel->tp->cache( "message_class", "maintenance" );
			$exit = 1;
		}
		
		$kernel->tp->cache( "message_data", $string );
  	
		if( $exit == HALT_EXEC )
		{
			if( defined( "IN_ACP" ) )
			{
				$kernel->vars['page_struct']['system_page_title'] = $kernel->config['archive_name'];
				$kernel->vars['page_struct']['system_page_action_title'] = $kernel->ld['phrase_node_access_denied'];
			}
			else
			{
				$kernel->vars['page_struct']['system_page_title'] = $kernel->vars['page_struct']['system_page_action_title'] = $kernel->config['archive_name'];
			}
			
			$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );
			
			exit;
		}
	}
	
	/*
	 * Update upload error message with new string - see jScript function: updateUploadMessage()
	 **/
	
	function jscript_upload_message( $string, $exit = 0 )
	{
		echo "<html><head><script type=\"text/javascript\" language=\"javascript\">parent.updateUploadMessage( '" . $string . "' );</script></head></html>";
		
		if( $exit == HALT_EXEC ) exit;
	}
	
	/*
	 * Construct progress bar window, do not change whats below unless you really know what your doing, it could remove your progress bar all-together when loading pages.
	 **/
	
	function construct_progress_window( $loop = 1, $phrase = '', $length = 300, $height = 25, $padding = 4 )
	{
		global $kernel, $var;
		
		if( empty( $phrase ) ) $phrase = $kernel->ld['phrase_loading_please_wait'];
		
		$var['unit'] =  $var['next_increment'] = 1;
		$var['bar_height'] = $height;
		$var['bar_length'] = $var['volume'] = $length;
		$var['loop_total'] = $loop;
		
		if( $var['loop_total'] < 1 ) return false;
		
		$var['loop_increment'] = (float)( $var['bar_length'] / $var['loop_total'] );
		$var['loop_html_increment'] = ( $var['loop_increment'] < 1 ) ? 1 : floor( $var['loop_increment'] );
		$var['loop_percent'] = round( $var['loop_increment'] / $var['bar_length'] * 100 );
		
		//Some text needs be sent to the browser for the cache to offically start and show the progress bar while the rest of the page loads.
		echo  "<link type='text/css' rel='stylesheet' href='" . $kernel->config['system_root_url_path'] . "/include/css_page_bar.css' /><div id='progress_window' style='margin-left:-" . ( $length / 2 ) . "px;'><span id='progress_title'>" . $phrase . "</span><p class='title_padding' />";
		
		@ob_end_flush();
		
		echo "<div class='bar_container' style='width:" . ( $length + ( $padding * 2 ) ) . "px;height:" . ( $var['bar_height'] + ( $padding * 2 ) ) . "px;'><div class='bar_block_inner'>";
		
		$kernel->vars['html']['system_jscript_commands'] .= "toggleProgressWindow( document.getElementById( 'progress_window' ) );";
  	
		@ob_flush(); @flush();
	}
	
	/*
	 * Update progress based on previously constructed progress bar
	 **/
	
	function update_progress_window_bar()
	{
		global $var;
		
		if( $var['loop_total'] < 1 ) return false;
		
		if( (float) $var['next_increment'] >= 1 OR $var['unit'] == $var['loop_total'] )
		{
			$var['next_increment'] -= 1;
			
			$var['loop_html_increment'] = ( $var['unit'] == $var['loop_total'] ) ? ceil( $var['volume'] ) : $var['loop_html_increment'];
			
			echo "<div class='bar_block' style='width:" . $var['loop_html_increment'] . "px;height:" . $var['bar_height'] . "px;'></div>";
		}
		
		@ob_flush(); @flush();
			
		$var['unit']++;
		$var['next_increment'] += $var['loop_increment'];
		$var['volume'] -= $var['loop_increment'];
	}
	
	/*
	 * Finish progress bar window
	 **/
	
	function finish_progress_window()
	{
		global $var;
		
		if( $var['loop_total'] < 1 ) return false;
		
		echo "</div></div></div>";
		
		@ob_end_flush();
	}
	
	/*
	 * Construct pagination
	 **/
	
	function construct_pagination( $url_args = array(), $proximity = 0 )
	{
		global $kernel;
		
		if( count( $navigation_url ) == 0 )
		{
			$base_url = SCRIPT_NAME . "?limit=" . $kernel->vars['limit'];
			
			if( defined( "IN_ACP" ) )
			{
				$base_url .= "&hash=" . $kernel->session->vars['hash'] . "&node=" . $kernel->vars['node'];
			}
			
			foreach( $url_args AS $arg => $value )
			{
				$base_url .= "&" . $arg . "=" . $value;
			}
			
			$base_url .= "&page=";
			
			$navigation_url = array(
				"nextpage" => $base_url . "{\$nextpage}",
				"previouspage" => $base_url . "{\$previouspage}",
				"span" => $base_url . "{\$page}",
				"start" => $base_url . "1",
				"end" => $base_url . $kernel->vars['total_pages'],
			);
		}
		
		if( defined( "IN_ACP" ) )
		{
			$templates = array(
				"admin_page_navigation",
				"admin_pagination_nextpage",
				"admin_pagination_previouspage",
				"admin_pagination_span_current",
				"admin_pagination_start",
				"admin_pagination_end",
				"admin_pagination_span"
			);
		}
		else
		{
			$templates = array(
				"page_navigation",
				"pagination_nextpage",
				"pagination_previouspage",
				"pagination_span_current",
				"pagination_start",
				"pagination_end",
				"pagination_span"
			);
		}
		
		$kernel->tp->call( $templates[0] );
		
		if( $proximity == 0 ) $proximity = $kernel->config['archive_pagination_page_proximity'];
		
		$kernel->vars['total_pages'] = ceil( $kernel->vars['total_rows'] / $kernel->vars['limit'] );
		
		if( $kernel->vars['page'] != $kernel->vars['total_pages'] ) $kernel->vars['end'] = $kernel->vars['start'] + $kernel->vars['limit'];
		if( $kernel->vars['page'] == $kernel->vars['total_pages'] ) $kernel->vars['end'] = $kernel->vars['total_rows'];
		
		$kernel->vars['html']['pagination_start'] = "";
		$kernel->vars['html']['pagination_end'] = "";
		
		for( $i = 1; $i <= $kernel->vars['total_pages']; $i++ )
		{
			// if were not on the last page show nextpage link
			if( $kernel->vars['page'] != $kernel->vars['total_pages'] )
			{
				$nextpage = $kernel->vars['page'] + 1;
				$kernel->vars['html']['pagination_nextpage'] = $kernel->tp->call( $templates[1], CALL_TO_PAGE );
				$kernel->vars['html']['pagination_nextpage'] = $kernel->tp->cache( "navigation_url", $navigation_url['nextpage'], $kernel->vars['html']['pagination_nextpage'] );
				$kernel->vars['html']['pagination_nextpage'] = $kernel->tp->cache( "nextpage", $nextpage, $kernel->vars['html']['pagination_nextpage'] );
			}
			else
			{
				$kernel->vars['html']['pagination_nextpage'] = "";
			}
			
			//if were not on the first page show previouspage link
			if( $kernel->vars['page'] != 1 )
			{
				$previouspage = $kernel->vars['page'] - 1;
				$kernel->vars['html']['pagination_previouspage'] = $kernel->tp->call( $templates[2], CALL_TO_PAGE );
				$kernel->vars['html']['pagination_previouspage'] = $kernel->tp->cache( "navigation_url", $navigation_url['previouspage'], $kernel->vars['html']['pagination_previouspage'] );
				$kernel->vars['html']['pagination_previouspage'] = $kernel->tp->cache( "previouspage", $previouspage, $kernel->vars['html']['pagination_previouspage'] );
			}
			else
			{
				$kernel->vars['html']['pagination_previouspage'] = "";
			}
			
			//if current page is the page were on, dont show much
			if( $kernel->vars['page'] == $i )
			{
				$kernel->vars['html']['pagination_span'] .= $kernel->tp->call( $templates[3], CALL_TO_PAGE );
				$kernel->vars['html']['pagination_span'] = $kernel->tp->cache( "navigation_url", $navigation_url['span'], $kernel->vars['html']['pagination_span'] );
				$kernel->vars['html']['pagination_span'] = $kernel->tp->cache( "page", $i, $kernel->vars['html']['pagination_span'] );
			}
			else
			{
				//if page is after proximity of viewing page, show firstpage link, do nothing else..
				if( $i < ( $kernel->vars['page'] - intval( $proximity ) ) )
				{
					$kernel->vars['html']['pagination_start'] = $kernel->tp->call( $templates[4], CALL_TO_PAGE );
					$kernel->vars['html']['pagination_start'] = $kernel->tp->cache( "navigation_url", $navigation_url['start'], $kernel->vars['html']['pagination_start'] );
					continue;
				}
				
				//if page is before proximity of viewing page, show lastpage link, do nothing else..
				if( $i > ( $kernel->vars['page'] + intval( $proximity ) ) )
				{
					$kernel->vars['html']['pagination_end'] = $kernel->tp->call( $templates[5], CALL_TO_PAGE );
					$kernel->vars['html']['pagination_end'] = $kernel->tp->cache( "navigation_url", $navigation_url['end'], $kernel->vars['html']['pagination_end'] );
					break;
				}
				
				//if were within 2 pages from the viewing page, show page link
				$kernel->vars['html']['pagination_span'] .= $kernel->tp->call( $templates[6], CALL_TO_PAGE );
				$kernel->vars['html']['pagination_span'] = $kernel->tp->cache( "navigation_url", $navigation_url['span'], $kernel->vars['html']['pagination_span'] );
				$kernel->vars['html']['pagination_span'] = $kernel->tp->cache( "page", $i, $kernel->vars['html']['pagination_span'] );
			}
		}
		
		$kernel->vars['start']++;
		
		$kernel->ld['phrase_showing_files'] = sprintf( $kernel->ld['phrase_showing_files'], $kernel->vars['start'], $kernel->vars['end'], $kernel->vars['total_rows'] );
		$kernel->ld['phrase_showing_page'] = sprintf( $kernel->ld['phrase_showing_page'], $kernel->vars['page'], $kernel->vars['total_pages'] );
		
		$kernel->tp->cache( $kernel->vars['html'] );
	}
	
	/*
	 * Construct category filter options
	 **/
	
	function construct_category_filters()
	{
		global $kernel;
		
		$sort_fields = array(
			"file_id" => $kernel->ld['phrase_subheader_file_newest'],
			"file_name" => $kernel->ld['phrase_subheader_file_name'],
			"file_description" => $kernel->ld['phrase_subheader_file_description'],
			"file_timestamp" => $kernel->ld['phrase_subheader_file_timestamp'],
			"file_mark_timestamp" => $kernel->ld['phrase_subheader_file_mark_timestamp'],
			"file_size" => $kernel->ld['phrase_subheader_file_size'],
			"file_ranking" => $kernel->ld['phrase_subheader_file_rating'],
			"file_author" => $kernel->ld['phrase_author'],
			"file_downloads" => $kernel->ld['phrase_downloads']
		);
		
		$kernel->vars['pagination_menu'] = array(
			"limit_" . $kernel->vars['limit'] => "selected=\"selected\"",
			"sort_" . $kernel->vars['sort'] => "selected=\"selected\"",
			"order_" . $kernel->vars['order'] => "selected=\"selected\""
		);
		
		$order_fields = array(
			"asc" => $kernel->ld['phrase_menu_ascending'],
			"desc" => $kernel->ld['phrase_menu_descending']
		);
		
		$kernel->vars['html']['display_limit_options'] = "";
		
		$limit_options = explode( ",", ( defined( "IN_ACP" ) ) ? $kernel->config['admin_display_limit_options'] : $kernel->config['display_limit_options'] );
		foreach( $limit_options AS $option_value )
		{
			$kernel->vars['html']['display_limit_options'] .= "<option value=\"" . $option_value . "\" " . $kernel->vars['pagination_menu'][ "limit_" . $option_value ] . ">" . $kernel->ld['phrase_menu_limit'] . "" . $option_value . "</option>\r\n";
		}
		
		foreach( $sort_fields AS $option_value => $option_phrase )
		{
			$kernel->vars['html']['display_sort_options'] .= "<option value=\"" . $option_value . "\" " . $kernel->vars['pagination_menu'][ "sort_" . $option_value ] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $sort_fields[ $option_value ] . "</option>\r\n";
		}
			
		foreach( $order_fields AS $option_value => $option_phrase )
		{
			$kernel->vars['html']['display_order_options'] .= "<option value=\"" . $option_value . "\" " . $kernel->vars['pagination_menu'][ "order_" . $option_value ] . ">" . $kernel->ld['phrase_menu_order'] . "" . $order_fields[ $option_value ] . "</option>\r\n";
		}
	}
	
	/*
	 * Construct category list options, highlight selected options, and remove blocked categories based on usergroup session vars
	 **/
	
	function construct_category_list( $selected = 0 )
	{
		global $kernel, $_SESSION;
		
		$allowed_categories = ( !empty( $kernel->session->vars['session_categories'] ) ) ? array_flip( unserialize( $kernel->session->vars['session_categories'] ) ) : array();
		
		$options = $kernel->db->item( "SELECT `datastore_value` FROM `" . TABLE_PREFIX . "datastore` WHERE `datastore_key` = 'category_dropmenu'" );
		
		if( is_array( $selected ) )
		{
			$kernel->vars['html']['category_list_options'] = $options;
			
			foreach( $selected AS $id )
			{
				$kernel->vars['html']['category_list_options'] = str_replace( "value=\"" . $id . "\"", "value=\"" . $id . "\" selected=\"selected\"", $kernel->vars['html']['category_list_options'] );
			}
		}
		else
		{
			$new_options = array();
			
			$options_list = explode( LINE_BREAK_CHR, $options );
			
			$allowed_categories = ( !empty( $kernel->session->vars['session_categories'] ) ) ? array_flip( unserialize( $kernel->session->vars['session_categories'] ) ) : array();
			
			foreach( $options_list AS $item )
			{
				preg_match( "/[0-9]+/", $item, $matches );
				
				if( !empty( $matches[0] ) )
				{
					$category = $kernel->db->row( "SELECT `category_sub_id`, `category_password` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $matches[0] );
					
					if( ( !empty( $category['category_password'] ) AND $category['category_password'] != $_SESSION['session_category_' . $matches[0] ] ) OR isset( $protected_categories[ $category['category_sub_id'] ] ) )
					{
						$protected_root = ( !isset( $protected_categories[ $category['category_sub_id'] ] ) ? true : false );
						$protected_categories[ $matches[0] ] = 0;
					}
				}
				
				if( $kernel->session->vars['session_group_id'] == 1 OR defined( "IN_ACP" ) OR ( ( empty( $matches[0] ) OR isset( $allowed_categories[ $matches[0] ] ) ) AND ( !isset( $protected_categories[ $matches[0] ] ) OR $protected_root == true ) ) ) $new_options[] = $item;
			}
			
			$options = implode( LINE_BREAK_CHR, $new_options );
			
			$kernel->vars['html']['category_list_options'] = str_replace( "value=\"" . $selected . "\"", "value=\"" . $selected . "\" selected=\"selected\"", $options );
		}
	}
	
	/*
	 * Read and check for category password (usergroup filtering to be added)
	 **/
	
	function read_category_permissions( $read_category = 0, $http_referer = "" )
	{
		global $kernel;
		
		$allowed_categories = ( !empty( $kernel->session->vars['session_categories'] ) ) ? array_flip( unserialize( $kernel->session->vars['session_categories'] ) ) : array();
		
		while( $read_category != "" ) 
		{
			if( $kernel->session->vars['session_group_id'] == 1 ) break;
			
			//Usergroup accessable categories..
			if( !isset( $allowed_categories[ $read_category ] ) AND $read_category > 0 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_permission'], M_ERROR, HALT_EXEC );
			}
			
			//Requires a password..
			$category = $kernel->db->row( "SELECT `category_id`, `category_sub_id`, `category_password` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $read_category );
			
			$read_category = $category['category_sub_id'];
			
			if( !empty( $category['category_password'] ) AND $category['category_password'] != $_SESSION['session_category_' . $category['category_id'] ] )
			{
				$kernel->session->construct_category_login( $category['category_id'], $kernel->format_input( $http_referer, T_URL_ENC ) );
			}
		}
	}
	
	/*
	 * Construct announcements
	 **/
	
	function construct_announcements( $announcement_id = 0 )
	{
		global $kernel;
		
		if( $announcement_id > 0 )
		{
			$announcement_specified_syntax = "OR `announcement_cat_id` = " . $announcement_id;
		}
		
		$get_announcements = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "announcements` WHERE `announcement_cat_id` = 0 " . $announcement_specified_syntax . " ORDER BY `announcement_cat_id`, `announcement_title`" );
		
		if( $kernel->db->numrows() > 0 )
		{
			while( $announcement = $kernel->db->data( $get_announcements ) )
			{
				if( ( $announcement['announcement_from_timestamp'] > 0 OR $announcement['announcement_to_timestamp'] > 0 ) AND ( $announcement['announcement_from_timestamp'] > UNIX_TIME OR $announcement['announcement_to_timestamp'] < UNIX_TIME ) ) continue;
				
				$kernel->vars['html']['system_announcements_data'] .= $kernel->tp->call( "announcement_box", CALL_TO_PAGE );
				
				$announcement['announcement_timestamp'] = $kernel->fetch_time( $announcement['announcement_timestamp'], DF_SHORT );
				$announcement['announcement_title'] = $kernel->format_input( $announcement['announcement_title'], T_HTML );
				$announcement['announcement_data'] = $kernel->archive->return_string_words( $kernel->format_input( $announcement['announcement_data'], T_HTML ), $kernel->config['string_max_words'] );
				$announcement['announcement_author'] = $kernel->format_input( $announcement['announcement_author'], T_HTML );
				$announcement['announcement_post_data'] = sprintf( $kernel->ld['phrase_posted_by'], $announcement['announcement_author'], $announcement['announcement_timestamp'] );
				
				$kernel->vars['html']['system_announcements_data'] = $kernel->tp->cache( $announcement, 0, $kernel->vars['html']['system_announcements_data'] );
			}
		}
	}
	
	/*
	 * Construct navigation
	 **/
	
	function construct_navigation( $read_category = "", $custom_tree_list = "" )
	{
		global $kernel;
		
		$kernel->vars['html']['system_navigation_data'] = $kernel->tp->call( "location_bar_header", CALL_TO_PAGE );
		
		while( $read_category != "" ) 
		{
			$category = $kernel->db->row( "SELECT `category_id`, `category_sub_id`, `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $read_category );
			
			$read_category = $category['category_sub_id'];
			
			$category_tree_list[] = array( "category_id" => $category['category_id'], "category_name" => $category['category_name'] );
		}
		
		if( sizeof( $category_tree_list ) > 0 )
		{
			$category_tree_list = @array_reverse( $category_tree_list );
		}
		
		if( is_array( $category_tree_list ) )
		{
			foreach( $category_tree_list AS $category ) 
			{
				if( empty( $category['category_name'] ) ) continue;
				
				$kernel->vars['html']['system_navigation_data'] .= $kernel->tp->call( "location_bar_item", CALL_TO_PAGE );
				
				$kernel->vars['html']['system_navigation_data'] = $kernel->tp->cache( "navigation_item_url", "category.php?id=" . $category['category_id'] . "", $kernel->vars['html']['system_navigation_data'] );
				$kernel->vars['html']['system_navigation_data'] = $kernel->tp->cache( "navigation_item_name", $kernel->format_input( $category['category_name'], T_HTML ), $kernel->vars['html']['system_navigation_data'] );
			}
		}
		
		if( sizeof( $custom_tree_list ) > 0 )
		{
			foreach( $custom_tree_list AS $navigation_item_url => $navigation_item_name )
			{
				if( empty( $navigation_item_name ) ) continue;
				$kernel->vars['html']['system_navigation_data'] .= $kernel->tp->call( "location_bar_item", CALL_TO_PAGE );
				
				$kernel->vars['html']['system_navigation_data'] = $kernel->tp->cache( "navigation_item_url", $navigation_item_url, $kernel->vars['html']['system_navigation_data'] );
				$kernel->vars['html']['system_navigation_data'] = $kernel->tp->cache( "navigation_item_name", $navigation_item_name, $kernel->vars['html']['system_navigation_data'] );
			}
		}
		
		$kernel->vars['html']['system_navigation_data'] .= $kernel->tp->call( "location_bar_footer", CALL_TO_PAGE );
	}
	
	/*
	 * Construct user session bar, login or user panel
	 **/
	
	function construct_session()
	{
		global $kernel;
		
		if( $kernel->config['archive_allow_user_login'] == "false" )
		{
			return;
		}
		else
		{
			if( !empty( $kernel->session->vars['session_hash'] ) AND USER_LOGGED_IN == true )
			{
				$kernel->vars['html']['system_session_data'] = $kernel->tp->call( "user_logged_in", CALL_TO_PAGE );
				
				$kernel->ld['phrase_user_logged_in_as'] = sprintf( $kernel->ld['phrase_user_logged_in_as'], $kernel->session->vars['session_name'] );
			}
			else
			{
				if( LOGIN_FORM !== true )
				{
					$kernel->vars['html']['system_session_data'] = $kernel->tp->call( "user_login_form", CALL_TO_PAGE );
				}
			}
		}
	}
	
	/*
	 * Wrap string in HTML <span style="color: $hex_colour">$string</span>
	 **/
	
	function string_colour( $string = "", $hex_colour = "#000000" )
	{
		return "<span style=\"color: " . $hex_colour . "\">" . $string . "</span>";
	}
	
	/*
	 * Create flag markers for arguement states (0 and 1, true and false, y and n)
	 * ## Function no longer used with config - see form_construct class ##
	 **/
	
	function construct_vars_flags( $arg_array = "" )
	{
		global $kernel;
		
		//if( empty( $arg_array ) ) $arg_array =& $kernel->config;
		
		$lookup = array( "true" => 1, "false" => 0, "1" => 1, "0" => 0, "y" => 1, "n" => 0 );
  	
		foreach( $arg_array AS $key => $string )
		{
			if( isset( $lookup[ $string ] ) )
			{
				//true flag
				$kernel->vars['html'][ "arg_true_" . $key ] = ( $lookup[ $string ] == 1 ) ? "checked=\"checked\"" : "";
					
				//false flag
				$kernel->vars['html'][ "arg_false_" . $key ] = ( $lookup[ $string ] == 0 ) ? "checked=\"checked\"" : "";
			}
		}
	}
	
	/*
	 * Create config options and cache them
	 **/
	
	function construct_config_options( $config_name = "", $config_options = "", $custom_select = false )
	{
		global $kernel;
		
		if( isset( $kernel->config[ "$config_name" ] ) )
		{
			$selected[ $kernel->config[ "$config_name" ] ] = "selected=\"selected\"";
			
			$kernel->vars['html'][ $config_name . "_list_options" ] = "";
			
			foreach( $config_options AS $phrase => $value )
			{
				$kernel->vars['html'][ $config_name . "_list_options" ] .= "<option value=\"" . $value . "\"" . $selected[ "$value" ] . ">" . $phrase . "</option>\r\n";
			}
		}
		else
		{
			$selected[ "$custom_select" ] = "selected=\"selected\"";
			
			foreach( $config_options AS $phrase => $value )
			{
				$kernel->vars['html'][ $config_name . "_list_options" ] .= "<option value=\"" . $value . "\"" . $selected[ "$value" ] . ">" . $phrase . "</option>\r\n";
			}
		}
	}
	
	/*
	 * Check file upload elements for validity or stop script exec
	 **/
	
	function verify_upload_details( $globals_files_var_name = "file_upload" )
	{
		global $kernel, $_POST, $_FILES;
		
		if( is_array( $_FILES[ "$globals_files_var_name" ]['name'] ) )
		{
			$total_files = sizeof( $_FILES[ "$globals_files_var_name" ]['name'] );
			
			for( $i = 0; $i <= $total_files; $i++ )
			{
				//file upload exceeds post limit
				if( empty( $_POST ) AND empty( $_FILES ) OR $_FILES[ "$globals_files_var_name" ]['error'][$i] == 1 OR $_FILES[ "$globals_files_var_name" ]['error'][$i] == 2 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_exceeds_filesize_limit'], M_ERROR, HALT_EXEC );
				}
				
				//upload is disabled
				if( !empty( $_FILES[ "$globals_files_var_name" ]['name'][$i] ) AND ( defined( "IN_ACP" ) AND $kernel->config['admin_allow_file_upload'] == "false" ) OR ( !defined( "IN_ACP" ) AND $kernel->config['archive_allow_upload'] == "false" ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_uploading_disabled'], M_ERROR, HALT_EXEC );
				}
				
				//file upload was interuptted
				if( $_FILES[ "$globals_files_var_name" ]['error'][$i] == 3 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_file_partially_uploaded'], M_ERROR, HALT_EXEC );
				}
				
				//file could not be uploaded
				if( !empty( $_FILES[ "$globals_files_var_name" ]['name'][$i] ) AND $_FILES[ "$globals_files_var_name" ]['error'][$i] == 4 OR !empty( $_FILES[ "$globals_files_var_name" ]['name'][$i] ) AND $_FILES[ "$globals_files_var_name" ]['size'][$i] < 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_file_not_uploaded'], M_ERROR, HALT_EXEC );
				}
				
				//no tempoary cache folder for PHP to store uploaded data in
				if( $_FILES[ "$globals_files_var_name" ]['error'][$i] == 6 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_temporary_folder'], M_ERROR, HALT_EXEC );
				}
				
				//failed to write to server disk. Possible CRC error?
				if( $_FILES[ "$globals_files_var_name" ]['error'][$i] == 7 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_failed_to_write_disk'], M_ERROR, HALT_EXEC );
				}
				
				if( $kernel->config['system_upload_scan_embedded_php'] != "false" AND !empty( $_FILES[ "$globals_files_var_name" ]['tmp_name'][ $i ] ) )
				{
					$max_loop = 25;
					$loop = 0;
					
					$fp = fopen( $_FILES[ "$globals_files_var_name" ]['tmp_name'][ $i ], 'r' );
					
					$open_tag = "<" . "?";
					
					while( !feof( $fp ) )
					{
						if( $loop > $max_loop )
						{
							fclose( $fp );
							break;
						}
						
						$data = fgets( $fp, 1024 );
						
						if( $open_pos === false ) $open_pos = strpos( $data, $open_tag );
						
						if( $open_pos !== false )
						{
							if( strtolower( substr( $data, ( $open_pos + 2 ), 3 ) ) != "php" OR substr( $data, ( $open_pos + 2 ), 1 ) != " " )
							{
								$open_pos = false;
							}
							else
							{
								$close_pos = strpos( $data, "?" . ">" );
								
								if( $close_pos !== false )
								{
									$kernel->page->message_report( $kernel->ld['phrase_upload_embedded_php_found'], M_ERROR, HALT_EXEC );
									
									break;
								}
							}
						}
						
						$loop++;
					}
					
					@fclose( $fp );
				}
			}
		}
		else
		{
			//file upload exceeds post limit
			if( empty( $_POST ) AND empty( $_FILES ) OR $_FILES[ "$globals_files_var_name" ]['error'] == 1 OR $_FILES[ "$globals_files_var_name" ]['error'] == 2 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_exceeds_filesize_limit'], M_ERROR, HALT_EXEC );
			}
			
			//upload is disabled
			if( !empty( $_FILES[ "$globals_files_var_name" ]['name'] ) AND ( defined( "IN_ACP" ) AND $kernel->config['admin_allow_file_upload'] == "false" ) OR ( !defined( "IN_ACP" ) AND $kernel->config['archive_allow_upload'] == "false" ) )
			{
				$kernel->page->message_report( $kernel->ld['phrase_uploading_disabled'], M_ERROR, HALT_EXEC );
			}
			
			//file upload was interuptted
			if( $_FILES[ "$globals_files_var_name" ]['error'] == 3 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_file_partially_uploaded'], M_ERROR, HALT_EXEC );
			}
			
			//file could not be uploaded
			if( !empty( $_FILES[ "$globals_files_var_name" ]['name'] ) AND $_FILES[ "$globals_files_var_name" ]['error'] == 4 OR !empty( $_FILES[ "$globals_files_var_name" ]['name'] ) AND $_FILES[ "$globals_files_var_name" ]['size'] < 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_file_not_uploaded'], M_ERROR, HALT_EXEC );
			}
			
			//no tempoary cache folder for PHP to store uploaded data in
			if( $_FILES[ "$globals_files_var_name" ]['error'] == 6 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_no_temporary_folder'], M_ERROR, HALT_EXEC );
			}
			
			//failed to write to server disk. Possible CRC error?
			if( $_FILES[ "$globals_files_var_name" ]['error'] == 7 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_failed_to_write_disk'], M_ERROR, HALT_EXEC );
			}
			
			if( $kernel->config['system_upload_scan_embedded_php'] != "false" AND !empty( $_FILES[ "$globals_files_var_name" ]['tmp_name'] ) )
			{
				$max_loop = 25;
				$loop = 0;
				
				$fp = fopen( $_FILES[ "$globals_files_var_name" ]['tmp_name'], 'r' );
				
				$open_tag = "<" . "?";
				
				while( !feof( $fp ) )
				{
					if( $loop > $max_loop )
					{
						fclose( $fp );
						break;
					}
					
					$data = fgets( $fp, 1024 );
					
					if( $open_pos === false ) $open_pos = strpos( $data, $open_tag );
					
					if( $open_pos !== false )
					{
						if( strtolower( substr( $data, ( $open_pos + 2 ), 3 ) ) != "php" OR substr( $data, ( $open_pos + 2 ), 1 ) != " " )
						{
							$open_pos = false;
						}
						else
						{
							$close_pos = strpos( $data, "?" . ">" );
							
							if( $close_pos !== false )
							{
								$kernel->page->message_report( $kernel->ld['phrase_upload_embedded_php_found'], M_ERROR, HALT_EXEC );
								
								break;
							}
						}
					}
					
					$loop++;
				}
				
				@fclose( $fp );
			}
		}
	}
	
	/*
	 * Check supplied security image string with database string
	 **/
	
	function verify_security_image_details()
	{
		global $kernel;
		
		if( function_exists( "imagecreate" ) AND ( ( $kernel->config['GD_POST_MODE_GUEST'] == "true" AND empty( $kernel->session->vars['session_user_id'] ) ) OR ( $kernel->config['GD_POST_MODE_USER'] == "true" AND !empty( $kernel->session->vars['session_user_id'] ) ) OR ( $config['GD_REGISTER_MODE'] == "true" ) ) )
		{
			$check_verification = $kernel->db->query( "SELECT `verify_key` FROM `" . TABLE_PREFIX . "users_verify` WHERE `verify_hash` = '" . $kernel->vars['user_verify_hash'] . "' AND `verify_key` = '" . $kernel->vars['user_verify_key'] . "'" );
			$verification = $kernel->db->data( $check_verification );
		}
		
		if( isset( $verification ) AND $kernel->db->numrows( $check_verification ) == 0 OR isset( $verification ) AND $verification['verify_key'] !== $kernel->vars['user_verify_key'] )
		{
			$kernel->page->message_report( $kernel->ld['phrase_invalid_verify_hash'], M_ERROR, HALT_EXEC );
		}
	}
	
	/*
	 * Check existance of a filename in the specified folder, if exists add the rename suffix and loop check integer.
	 **/
	
	function construct_upload_file_name( $file_name, $file_directory )
	{
		global $kernel, $file_info;
		
		if( !@file( $file_directory . $file_name ) )
		{
			return $file_name;
		}
		else
		{
			$file_match = true;
			$loop_count = 1;
			
			while( $file_match == true )
			{
				$new_file_name = substr( $file_info['file_name'], 0, strlen( $file_info['file_name'] ) - ( strlen( $file_info['file_type'] ) + 1 ) );
				
				if( !@file( $file_directory . $new_file_name . $kernel->config['file_rename_suffix'] . $loop_count . "." . strtolower( $file_info['file_type'] ) ) )
				{
					$new_file_name = $new_file_name . $kernel->config['file_rename_suffix'] . $loop_count . "." . strtolower( $file_info['file_type'] );
					
					$file_match = false;
					break;
				}
				
				$loop_count++;
			}
		}
		
		return $new_file_name;
	}
	
	/*
	 * Return filtered (based on usergroup) number of files within sub-categories of the specified category.
	 **/
	
	function fetch_sub_category_file_count( $category_id, $allowed_categories )
	{
		global $kernel, $total_sub_files;
		
		if( $category_id == 0 ) return;
		
		$get_subcategory_file_count = $kernel->db->query( "SELECT `category_id`, `category_sub_id`, `category_file_total` FROM `" . TABLE_PREFIX . "categories` WHERE `category_sub_id` = " . $category_id );
		
		if( $kernel->db->numrows() > 0 )
		{
			while( $subcategory = $kernel->db->data( $get_subcategory_file_count ) )
			{
				//Usergroup accessable categories..
				if( isset( $allowed_categories[ $subcategory['category_id'] ] ) OR defined( "IN_ACP" ) OR $kernel->session->vars['session_group_id'] == 1 )
				{
					$total_sub_files += $subcategory['category_file_total'];
					
					$this->fetch_sub_category_file_count( $subcategory['category_id'], $allowed_categories );
				}
			}
		}
	}
	
	/*
	 * Return formatted (based on settings) number of files in a category.
	 **/	
	
	function format_category_file_count( $category_id, $category_password, $total_files )
	{
		global $kernel, $allowed_categories, $total_sub_files;
		
		$total_sub_files = 0;
		
		$this->fetch_sub_category_file_count( $category_id, $allowed_categories );
		
		if( ( !empty( $category_password ) AND $category_password != $_SESSION['session_category_' . $category_id ] ) AND !defined( "IN_ACP" ) AND $kernel->session->vars['session_group_id'] <> 1 )
		{
			$string = "<i>" . $kernel->ld['phrase_hidden'] . "</i>";
		}
		else
		{
			if( $kernel->config['category_file_count_mode'] == 1 )
			{
				$string = $kernel->format_input( ( $total_files + $total_sub_files ), T_NUM );
			}
			elseif( $kernel->config['category_file_count_mode'] == 2 )
			{
				if( $total_sub_files == 0 OR $total_files == $total_sub_files )
				{
					$string = $kernel->format_input( $total_files, T_NUM );
				}
				else
				{
					$string = $kernel->format_input( $total_files, T_NUM ) . " (" . $kernel->format_input( $total_sub_files, T_NUM ) . ")";
				}
			}
			else
			{
				$string = $kernel->format_input( $total_files, T_NUM );
			}
		}
		
		return $string;
	}
	
	/*
	 * Return filtered (based on usergroup) newest file within sub-categories of the specified category.
	 **/
	
	function fetch_sub_category_new_file( $category_id, $allowed_categories )
	{
		global $kernel, $new_file_id, $new_file_timestamp;
		
		if( $category_id == 0 ) return;
		
		$get_subcategory_new_file = $kernel->db->query( "SELECT `category_id`, `category_sub_id`, `category_newfile_id` FROM `" . TABLE_PREFIX . "categories` WHERE `category_sub_id` = " . $category_id );
		
		if( $kernel->db->numrows() > 0 )
		{
			while( $subcategory = $kernel->db->data( $get_subcategory_new_file ) )
			{
				//Usergroup accessable categories..
				if( isset( $allowed_categories[ $subcategory['category_id'] ] ) OR defined( "IN_ACP" ) OR $kernel->session->vars['session_group_id'] == 1 )
				{
					if( $subcategory['category_newfile_id'] > 0 ) $file_timestamp = $kernel->db->item( "SELECT `file_mark_timestamp` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $subcategory['category_newfile_id'] );
					
					if( $file_timestamp > $new_file_timestamp )
					{
						$new_file_id = $subcategory['category_newfile_id'];
						$new_file_timestamp = $file_timestamp;
					}
					
					$this->fetch_sub_category_new_file( $subcategory['category_id'], $allowed_categories );
				}
			}
		}
	}
	
	/*
	 * Return formatted (based on settings) newest file for a specific category.
	 **/	
	
	function format_category_new_file( $category_id, $category_password )
	{
		global $kernel, $_SESSION, $allowed_categories, $new_file_id, $new_file_timestamp;
		
		$new_file_id = $new_file_timestamp = 0;
		$html = '';
		
		if( $kernel->config['category_new_file_construct_mode'] == 1 )
		{
			$this->fetch_sub_category_new_file( $category_id, $allowed_categories );
		}
		
		$category_newfile_id = $kernel->db->item( "SELECT `category_newfile_id` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $category_id );
		
		if( $category_newfile_id > 0 ) $file_timestamp = $kernel->db->item( "SELECT `file_mark_timestamp` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $category_newfile_id );
		
		if( $file_timestamp > $new_file_timestamp )
		{
			$new_file_id = $category_newfile_id;
			$new_file_timestamp = $file_timestamp;
		}
		
		$file = $kernel->db->row( "SELECT `file_name`, `file_mark_timestamp`, `file_downloads` FROM `" . TABLE_PREFIX . "files` WHERE `file_id` = " . $new_file_id );
		
		// Show latest file added in category branch
		if( $new_file_id > 0 )
		{
			if( ( !empty( $category_password ) AND $category_password != $_SESSION['session_category_' . $category_id ] ) AND !defined( "IN_ACP" ) AND $kernel->session->vars['session_group_id'] <> 1 )
			{
				$html .= $kernel->tp->call( "category_hidden_newfile", CALL_TO_PAGE );
			}
			else
			{
				$html .= $kernel->tp->call( "category_newfile", CALL_TO_PAGE );
				
				$file['file_id'] = $new_file_id;
				$file['file_mark_timestamp'] = $kernel->fetch_time( $file['file_mark_timestamp'], DF_SHORT );
				$file['file_name'] = $kernel->archive->return_string_chars( $kernel->format_input( $file['file_name'], T_HTML ), $kernel->config['string_max_length_file_name'] );
				$file['file_downloads'] = $kernel->format_input( $file['file_downloads'], T_NUM );
				
				$html = $kernel->tp->cache( $category, 0, $html );
			}
		}
		else
		{
			$html .= $kernel->tp->call( "category_no_newfile", CALL_TO_PAGE );
		}
		
		$html = $kernel->tp->cache( $file, 0, $html );
		
		return $html;
	}
	
	/*
	 * Return formatted (based on settings) number of files in a category.
	 **/
	
	function construct_sub_category_list( $category_id, $category_password )
	{
		global $kernel, $_SESSION, $allowed_categories;
		
		$html = "";
		$count = 0;
		
		if( ( SCRIPT_NAME == "index.php" AND $kernel->config['category_subcategory_links_mode'] == 1 ) OR ( SCRIPT_NAME == "category.php" AND $kernel->config['category_subcategory_links_mode'] == 2 ) OR $kernel->config['category_subcategory_links_mode'] == 3 )
		{
			$get_subcategory_names = $kernel->db->query( "SELECT `category_id`, `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_sub_id` = " . $category_id . " ORDER BY `category_order`, `category_name`" );
			
			if( $kernel->db->numrows() > 0 )
			{
				if( ( !empty( $category_password ) AND $category_password == $_SESSION['session_category_' . $category_id ] ) OR empty( $category_password ) OR defined( "IN_ACP" ) OR $kernel->session->vars['session_group_id'] == 1 )
				{
					$html .= $kernel->tp->call( "sub_category", CALL_TO_PAGE );
					
					while( $subcategory = $kernel->db->data( $get_subcategory_names ) )
					{
						//Usergroup accessable categories..
						if( !isset( $allowed_categories[ $subcategory['category_id'] ] ) ) continue;
						
						$html .= $kernel->tp->call( "sub_category_item", CALL_TO_PAGE );
						
						$html = $kernel->tp->cache( $subcategory, 0, $html );
						
						$count++;
					}
					
					$html = ( $count > 0 ) ? preg_replace( "/, $/", "", $html ) : '';
				}
			}
		}
		
		return $html;
	}
	
	/*
	 * Archive pagination table for category jump and pagination display options.
	 **/
	
	function construct_pagination_menu( $return_file_menu = false, $return_category_menu = false, $target_page = "index.php" )
	{
		global $kernel;
		
		$kernel->tp->call( "pagination_table" );
		
		$kernel->vars['html']['pagination_file_menu'] = ( $return_file_menu == false ) ? "" : $kernel->tp->call( "pagination_display_selectors", CALL_TO_PAGE );
		
		$kernel->vars['html']['pagination_category_menu'] = ( $return_category_menu == false ) ? "" : $kernel->tp->call( "pagination_category_selector", CALL_TO_PAGE );
		
		$kernel->vars['html']['category_select_target_page'] = $target_page;
		
		if( $return_file_menu !== false )
		{
			$kernel->page->construct_pagination( $kernel->vars['pagination_vars'] );
			
			$kernel->page->construct_category_filters();
		}
	}
	
	/*
	 * Post code
	 **/
	
	function postit_code( $string = "" )
	{
		global $kernel;
		
		$original = array(
			"/([^\s\(\)\[\]\{\}\.\,\;\:\?\!\-]*)([\S]*\:\/\/[\S]*\.[\S]*)[^\s\(\)\[\]\{\}\.\,\;\:\?\!\-\d]*/i",
			"/\[img](.*)\[\/img\]/siU",
			"/\[b](.*)\[\/b\]/siU",
			"/\[i](.*)\[\/i\]/siU",
			"/\[u](.*)\[\/u\]/siU"
		);
		
		$replacement = array(
			"<a target=\"_blank\" href=\"\\1\\2\">\\1\\2</a>",
			"<img border=\"0\" src=\"\\1\" alt=\"\\1\">",
			"<b>\\1</b>",
			"<i>\\1</i>",
			"<u>\\1</u>"
		);
		
		$string = preg_replace( $original, $replacement, $string );
		
		return $string;
	}
}

?>