<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and use.
################################################################################

define( "R_TYPE_STR",		1 );
define( "R_TYPE_MIME",		2 );

define( "OPT_USER_ID",		0 );
define( "OPT_USER_NAME",	1 );

class class_archive_function
{
	/*
	 * Return file name (with suffix), file suffix, file mime, file icon
	 **/
	
	function file_url_info( $file_url )
	{
		global $kernel, $file_info;
		
		$file_info = array();
		
		$file_url = str_replace( "\\", "/", $file_url );
		
		preg_match( "/[^.]+$/", $file_url, $matches );
		
		$file_info['file_name'] =  str_replace( " ", "%20", ( substr( $file_url, -1 ) == "/" ) ? "" : array_shift( explode( "?", basename( $file_url ) ) ) );
		
		$file_info['file_type'] = ( substr( $file_url, -1 ) == "/" ) ? "" : strtoupper( $matches[0] );
		if( strstr( $file_info['file_type'], "/" ) ) $file_info['file_type'] = "";
		
		$file_info['file_icon'] = ( isset( $kernel->filetype[ $file_info['file_type'] ] ) ) ? $kernel->filetype[ $file_info['file_type'] ][0] : $kernel->filetype[""][0];
		
		/*if( @extension_loaded( "fileinfo" ) AND function_exists( "finfo_open" ) )
		{
			$fi = finfo_open( FILEINFO_MIME );
			
			$mime_info = finfo_file( $fi, $file_url );
			
			if( strpos( $mime_info, ";" ) )
			{
				$mime_info = explode( ";", $mime_info );
				
				$file_info['file_mime'] = $mime_info[0];
			}
			else
			{
				$file_info['file_mime'] = $mime_info;
			}
		}
		else
		{*/
			$file_info['file_mime'] = ( isset( $kernel->filetype[ $file_info['file_type'] ] ) ) ? $kernel->filetype[ $file_info['file_type'] ][1] : $kernel->filetype[""][1];
		//}
		
		$file_info['file_type_exists'] = ( !empty( $file_info['file_type'] ) AND isset( $kernel->filetype[ $file_info['file_type'] ] ) ) ? true : false;
		
		return $file_info;
	}
	
	/*
	 * Parse supplied URL and return the headers char length
	 **/
	
	function parse_url_size( $url, $timeout = 5 )
	{
		global $kernel;
		
		$url = str_replace( " ", "%20", $url );
		
		if( @ini_get( "allow_url_fopen" ) == 0 AND CURL_ENABLED == false ) return 0;
		
		$parse_bytes = 0;
		
		if( strpos( $url, $kernel->config['system_root_dir_upload'] ) !== false )
		{
			$parse_bytes = filesize( $url );
		}
		
		if( CURL_ENABLED == true AND $parse_bytes == 0 )
		{
			$handle = curl_init( $url );
			
			curl_setopt( $handle, CURLOPT_TIMEOUT, $kernel->config['system_parse_timeout'] );
			curl_setopt( $handle, CURLOPT_HEADER, 0 );
			curl_setopt( $handle, CURLOPT_FAILONERROR, 1 );
			curl_setopt( $handle, CURLOPT_NOBODY, 1 );
			curl_setopt( $handle, CURLOPT_RETURNTRANSFER, 1 );
			
			if( !empty( $kernel->config['upload_dir_http_username'] ) AND strpos( $url, $kernel->config['system_root_url_upload'] ) !== false )
			{
				curl_setopt( $handle, CURLOPT_USERPWD, $kernel->config['upload_dir_http_username'] . ':' . $kernel->config['upload_dir_http_password'] );
			}
			
			curl_exec( $handle );
			
			$parse_bytes = curl_getinfo( $handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD );
			
			curl_close( $handle );
		}
		
		if( $parse_bytes == 0 )
		{
			$parse = @parse_url( $url );
			
			$host = $parse['host'];
			$path = $parse['path'];
			$port = empty( $parse['port'] ) ? 80 : $parse['port'];
			
			$string = "Content-length: ";
			
			$headers = "HEAD " . $path . " HTTP/1.1\r\n";
			$headers .= "Host: " . HTTP_HOST . "\r\n";
			
			if( !empty( $kernel->config['upload_dir_http_username'] ) AND strpos( $url, $kernel->config['system_root_url_upload'] ) !== false )
			{
				$authenicate_string = base64_encode( $kernel->config['upload_dir_http_username'] . ":" . $kernel->config['upload_dir_http_password'] );
				
				$headers .= "Authorization: Basic " . $authenicate_string . "\r\n";
			}
			
			$headers .= "Connection: Close\r\n";
			
			if( !$fp = @fsockopen( $host, $port, $errno, $errstr, $kernel->config['system_parse_timeout'] ) ) 
			{
				return 0;
			}
			else
			{
				if( function_exists( "socket_set_timeout" ) ) @socket_set_timeout( $fp, $kernel->config['system_parse_timeout'] );
				
				@fwrite( $fp, $headers . "\r\n" );
				
				while( !feof( $fp ) )
				{
					$data_length .= fgets( $fp, 128 );
				}
			}
			
			@fclose( $fp );
			
			$data_array = explode( "\n", $data_length );
			
			foreach( $data_array AS $data_line )
			{
				if( substr( strtolower( $data_line ), 0, strlen( $string ) ) == strtolower( $string ) )
				{
					$parse_bytes = substr( $data_line, strlen( $string ) );
					break;
				}
			}
		}
		
		return $parse_bytes;
	}
	
	/*
	 * Check permissions on a path and or file and return as required.
	 **/
	
	function check_file_permissions( $path, $file = "", $phrases = "", $exit = true )
	{
		global $kernel;
		
		if( !empty( $file ) ) $file_phrase = "<b>" . $file . "</b> at ";
		if( empty( $phrases ) ) $phrases = array( "", "" );
		
		foreach( $phrases AS $key => $phrase )
		{
			$phrases[ "$key" ] .= "<p />";
		}
		
		if( @is_readable( $path ) == false )
		{
			$kernel->page->message_report( $phrases[0] . "Unable to read from " . $file_phrase . "<b>" . $path . "</b>", M_ERROR, ( ( $exit == true ) ? HALT_EXEC : 0 ) );
		}
		@clearstatcache();
		
		if( @is_writable( $path ) == false )
		{
			$kernel->page->message_report( $phrases[1] . "Unable to write to " . $file_phrase . "<b>" . $path . "</b>", M_ERROR, ( ( $exit == true ) ? HALT_EXEC : 0 ) );
		}
		@clearstatcache();
		
		return true;
	}
	
	/*
	 * Return a specific number of words from a string
	 **/
	
	function return_string_words( $string, $max_length = "" )
	{
		$string_words = explode( " ", str_replace( "&nbsp;", " ", $string ) );
		
		if( sizeof( $string_words ) > $max_length )
		{
		  $string = implode( " ", array_slice( $string_words, 0, $max_length ) ) . " ...";
		}
		
		return $string;
	}
	
	/*
	 * Return a specific number of characters from a string
	 **/
	
	function return_string_chars( $string, $max_length = "" )
	{
		if( preg_match_all( "/\&\#[0-9a-z]+?\;/i", $string, $matches ) )
		{
			$string_length = sizeof( $matches[0] );
			
			if( $string_length <= $max_length )
			{
				return $string;
			}
			else
			{
				for( $char = 0; $char <= $string_length; $char++ )
				{
					if( $char >= $max_length )
					{
						break;
					}
					else
					{
						$string .= $matches[0][ $char ];
					}
				}
				
				if( $string_length > $max_length )
				{
					$string .= "..";
				}
			}
		}
		else
		{
			if( strlen( $string ) > $max_length )
			{
				$string = substr( $string, 0, $max_length ) . "..";
			}
		}
		
		return $string;
	}
	
	/*
	 * Return over length words hyphenated for auto line-breaking. (only seems to work with IE)
	 **/
	
	function string_word_length_slice( $string, $max_length )
	{
		return $string;
		/*$word_array = explode( " ", $string );
		$words = 0;
		
		foreach( $word_array AS $word )
		{
			if( strlen( $word ) > $max_length )
			{
				$word_section = "";
				$word_formatted = "";
				
				if( preg_match_all( "/\&\#[0-9a-z]+?\;/i", $word, $matches ) )
				{
					if( sizeof( $matches[0] ) < $max_length )
					{
						$word_formatted = $word;
					}
					else
					{
						$char = 0;
						
						for( $l = 0; $l <= sizeof( $matches[0] ); $l++ )
						{
							$word_section .= $matches[0][ $l ];
							
							if( $char > $max_length )
							{
								$word_section .= "-\r\n";
								
								$char = 0;
							}
							
							$char++;
						}
						
						$word_formatted = $word_section;
					}
				}
				else
				{
					for( $i = 0; $i <= strlen( $word ); $i += $max_length )
					{
					$word_section .= substr( $word, $i, $max_length );
					
					if( ( $i + $max_length ) < strlen( $word ) ) $word_section .= "-\r\n";
					
					$word_formatted = $word_section;
					}
				}
				
				$word_array[ $words ] = $word_formatted; 
			}
			
			$words++;
		}
		
		$string = implode( " ", $word_array );
		
		return $string;*/
	}
	
	/*
	 * Round integer for every 1024 until round or integer end
	 **/
	
	function format_round_bytes( $bytes = 0 )
	{
		global $kernel;
		
		if( $bytes == 0 ) return ( $kernel->ld['phrase_unknown'] ) ? "<i>" . $kernel->ld['phrase_unknown'] . "</i>" : "<i>" . $kernel->ld['phrase_unknown'] . "</i>";
		
		$round_loop = 0;
		
		$round_set = explode( LINE_BREAK, $kernel->config['file_byte_rounders'] );
		
		foreach( $round_set AS $round_line )
		{
			if( empty( $round_line ) ) continue;
			
			$round_line_section = explode( "=", $round_line );
			$round_group[] = $round_line_section[0];
			$round_round[] = $round_line_section[1];
			
			if( $bytes >= 1024 )
			{
				if( $round_loop > 0 )
				{
					$bytes = $bytes / 1024;
				}
				$round_loop++;
			}
		}
		
		$use_round_set = $round_loop - 1;
		
		if( $round_loop == 0 )
		{
			return ceil( $bytes ) . $round_group[0];
		}
		else
		{
			return round( $bytes, $round_round[ $use_round_set ] ) . $round_group[ $use_round_set ];
		}
	}
	
	/*
	 * Construct file hash strings
	 **/
	
	function construct_file_hash_fields( $string = 0 )
	{
		global $kernel;
		
		$kernel->vars['html']['file_hash_fields'] = "";
		$file_hash_data = "";
		
		if( $kernel->config['archive_file_hash_mode'] != 0 AND !empty( $string ) )
		{
			$kernel->vars['html']['file_hash_fields'] .= $kernel->tp->call( "file_view_hash_row", CALL_TO_PAGE );
			
			list( $file_hash_md5, $file_hash_sha1 ) = explode( ",", $string );
			
			if( !empty( $file_hash_md5 ) AND $kernel->config['archive_file_hash_mode'] == 1 OR $kernel->config['archive_file_hash_mode'] == 3 )
			{
				$file_hash_data .= "<b>MD5:</b> <i>" . $file_hash_md5 . "</i>";
			}
			
			if( !empty( $file_hash_md5 ) AND !empty( $file_hash_sha1 ) AND $kernel->config['archive_file_hash_mode'] == 3 )
			{
				$file_hash_data .= "&nbsp;&middot;&nbsp;";
			}
			
			if( !empty( $file_hash_sha1 ) AND $kernel->config['archive_file_hash_mode'] == 2 OR $kernel->config['archive_file_hash_mode'] == 3 )
			{
				$file_hash_data .= "<b>SHA1:</b> <i>" . $file_hash_sha1 . "</i>";
			}
			
			$kernel->vars['html']['file_hash_fields'] = $kernel->tp->cache( "file_hash_data", $file_hash_data, $kernel->vars['html']['file_hash_fields'] );
		}
		
		return;
	}
	
	/*
	 * Construct file tag strings
	 **/
	
	function construct_file_tags_field( $file_id )
	{
		global $kernel;
		
		$kernel->vars['html']['file_tags_fields'] = "";
		$file_tags_data = "";
		
		$fetch_tags = $kernel->db->query( "SELECT `tag_id`, `tag_phrase` FROM `" . TABLE_PREFIX . "tags` WHERE `tag_file_id` = " . $file_id );
		
		if( $kernel->db->numrows( $fetch_tags ) > 0 )
		{
			$kernel->vars['html']['file_tags_fields'] .= $kernel->tp->call( "file_view_tags_row", CALL_TO_PAGE );
			
			while( $tag = $kernel->db->data( $fetch_tags ) )
			{
				$file_tags_data .= "<a href='tag.php?phrase=" . $kernel->format_input( $tag['tag_phrase'], T_URL_ENC ) . "'>" . $tag['tag_phrase'] . "</a>&nbsp;&nbsp;";
			}
			
			$kernel->vars['html']['file_tags_fields'] = $kernel->tp->cache( "file_tags_data", $file_tags_data, $kernel->vars['html']['file_tags_fields'] );
		}
		
		return;
	}
	
	/*
	 * Construct file download time counters
	 **/
	
	function construct_download_counters( $bytes = 0 )
	{
		global $kernel;
		
		$kernel->vars['html']['file_download_counters'] = "";
		$download_times = "";
		$current_counter = 1;
		
		$counter_set = explode( LINE_BREAK, $kernel->config['file_download_time_counters'] );
		
		$total_counters = sizeof( $counter_set );
		
		foreach( $counter_set as $round_line )
		{
			if( empty( $round_line ) ) continue;
			
			$part = explode( "=", $round_line );
			
			$download_times = array(
				"file_speed_title" => $kernel->format_input( $part[0], T_HTML ),
				"file_speed_time" => $kernel->format_seconds( ceil( $bytes / ( $part[1] / 8 ) ) )
			);
			
			$kernel->vars['html']['file_download_counters'] .= $kernel->tp->call( "download_time_item", CALL_TO_PAGE );
			$kernel->vars['html']['file_download_counters'] = $kernel->tp->cache( $download_times, 0, $kernel->vars['html']['file_download_counters'] );
			
			if( $current_counter !== $total_counters )
			{
				$kernel->vars['html']['file_download_counters'] .= $kernel->tp->call( "download_time_item_spacer", CALL_TO_PAGE );
			}
			
			$current_counter++;
		}
	}
	
	/*
	 * Construct pagination variables
	 **/
	
	function construct_pagination_vars( $total_rows = 0 )
	{
		global $kernel;
		
		$kernel->vars['pagination_menu'] = array(
			$kernel->vars['limit'] => "selected=\"selected\"",
			$kernel->vars['order'] => "selected=\"selected\"",
			$kernel->vars['sort'] => "selected=\"selected\""
		);
		
		$kernel->vars['total_rows'] = $total_rows;
		$kernel->vars['total_pages'] = ceil( $kernel->vars['total_rows'] / $kernel->vars['limit'] );
			
		if( $kernel->vars['page'] > $kernel->vars['total_pages'] )
		{
			$kernel->vars['page'] = $kernel->vars['total_pages'];
		}
		
		$kernel->vars['start'] = ( $kernel->vars['page'] * $kernel->vars['limit'] ) - $kernel->vars['limit'];
	}
	
	/*
	 * Construct file icon
	 **/
	
	function construct_file_icon( $file_url = "", $file_icon = "" )
	{
		global $kernel;
		
		if( $file_icon == "0" )
		{
			$file_info = $kernel->archive->file_url_info( $file_url );
			
			return "<img src=\"" . $kernel->config['system_root_url_path'] . "/images/filetype/" . $file_info['file_icon'] . "\" border=\"0\" alt=\"" . $file_info['file_type'] . "\" />";
		}
		elseif( !empty( $file_icon ) )
		{
			return "<img src=\"" . $kernel->config['system_root_url_path'] . "/images/icons/" . $file_icon . "\" border=\"0\" alt=\"\" />";
		}
		else
		{
			return "&nbsp;";
		}
	}
	
	/*
	 * Construct file icon
	 **/
	
	function construct_file_custom_fields( $file_id = 0 )
	{
		global $kernel;
		
		$html = "";
		
		$get_fields = $kernel->db->query( "SELECT `field_id`, `field_name` FROM `" . TABLE_PREFIX . "fields` WHERE `field_category_display` = 1 ORDER BY `field_name`" );
		if( $kernel->db->numrows() > 0 )
		{			
			while( $field = $kernel->db->data( $get_fields ) )
			{
				$field_data = $kernel->db->row( "SELECT `field_file_data` FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_id` = " . $field['field_id'] . " AND `field_file_id` = " . $file_id );
				
				if( !empty( $field_data['field_file_data'] ) )
				{
					$field_data['field_file_data'] = $kernel->format_input( $field_data['field_file_data'], T_HTML );
				}
				else
				{
					$field_data['field_file_data'] = "&nbsp;";
				}
				
				$html .= $kernel->tp->call( "file_field_cell", CALL_TO_PAGE );
						
				$html = $kernel->tp->cache( $field_data, 0, $html );
			}
		}
		
		return $html;
	}
	
	/*
	 * Construct file custom field HTML forms
	 **/
	
	function construct_file_custom_fields_form( $file_id = 0 )
	{
		global $kernel;
		
		$field_submit_syntax = ( SCRIPT_NAME == "submit.php" ) ? "WHERE `field_submit_display` = 1" : ""; 
		
		$get_fields = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "fields` " . $field_submit_syntax );
		
		$kernel->vars['html']['custom_fields'] = "";
		
		if( $kernel->db->numrows() > 0 )
		{
			if( SCRIPT_NAME != "submit.php" )
			{
				$kernel->vars['html']['custom_fields'] .= $kernel->tp->call( "admin_file_fiel_header", CALL_TO_PAGE );
			}
			else
			{
				$table_col_span = " colspan=\"2\"";
				$kernel->vars['html']['custom_fields'] .= $kernel->tp->call( "file_fiel_header", CALL_TO_PAGE );
			}
			
			$field_group = ( $kernel->vars['node'] == "file_sub_manage" ) ? "submission" : "file";
			
			while( $field = $kernel->db->data( $get_fields ) )
			{
				$field_data = $kernel->format_input( $kernel->db->item( "SELECT `field_file_data` FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_id` = " . $field['field_id'] . " AND `field_" . $field_group . "_id` = " . $file_id ), T_FORM );
				
				$field_required_phrase = "";
				
				if( $field['field_data_rule'] == 1 )
				{
					$field_required_phrase = $kernel->ld['phrase_field_value_required'];
				}
				elseif( $field['field_data_rule'] == 2 )
				{
					$field_required_phrase = $kernel->ld['phrase_field_value_required_numeric'];
				}
				
				$field['field_name'] = $kernel->format_input( $field['field_name'], T_STR );
				$field['field_description'] = $kernel->format_input( $field['field_description'], T_HTML );
				
				$kernel->vars['html']['custom_fields'] .= "<tr><td class=\"subheader\"" . $table_col_span . "><strong>" . $field['field_name'] . "</strong>&nbsp;" . $field['field_description'] . "</td></tr>";
				
				if( $kernel->vars['action'] == "manage" )
				{
					$html_tag_name = "files[" . $file_id . "][field_" . $field['field_id'] . "]";
				}
				else
				{
					$html_tag_name = "field_" . $field['field_id'];
				}
				
				if( $field['field_type'] == 0 )
				{
					if( $file_id > 0 )
					{
						$kernel->vars['html']['custom_fields'] .= "<tr><td class=\"row\"" . $table_col_span . "><input type=\"text\" name=\"" . $html_tag_name . "\" value=\"" . $field_data . "\" size=\"50\" maxlength=\"255\"> <span style=\"color: red;font-size: 11px;\">" . $field_required_phrase . "</span></td></tr>\r\n";
					}
					else
					{
						$kernel->vars['html']['custom_fields'] .= "<tr><td class=\"row\"" . $table_col_span . "><input type=\"text\" name=\"" . $html_tag_name . "\" value=\"\" size=\"50\" maxlength=\"255\"> <span style=\"color: red;font-size: 11px;\">" . $field_required_phrase . "</span></td></tr>\r\n";
					}
				}
				elseif( $field['field_type'] == 2 )
				{
					$menu_options = explode( LINE_BREAK_CHR, $field['field_options'] );
					
					$options = "<option value=\"\"></option>\r\n<optgroup label=\"" . $kernel->ld['phrase_menu_choose_option'] . "\">\r\n";
					
					foreach( $menu_options AS $item )
					{
						if( empty( $item ) ) continue;
						
						$item = explode( "=", $item );
						
						foreach( $item AS $item_id => $item_data )
						{
							$item[ $item_id ] = $kernel->format_input( $item_data, T_FORM );
						}
						
						if( $field_data == $item[1] )
						{
							$options .= "<option value=\"" . $item[1] . "\" selected=\"selected\">" . $item[0] . "</option>\r\n";
						}
						else
						{
							$options .= "<option value=\"" . $item[1] . "\">" . $item[0] . "</option>\r\n";
						}
					}
					
					$options .= "</optgroup>\r\n";
					
					$kernel->vars['html']['custom_fields'] .= "<tr><td class=\"row\"" . $table_col_span . "><select name=\"" . $html_tag_name . "\">" . $options . "</select> <span style=\"color: red;font-size: 11px;\">" . $field_required_phrase . "</span></td></tr>\r\n";
				}
				else
				{
					if( $file_id > 0 )
					{
						$kernel->vars['html']['custom_fields'] .= "<tr><td class=\"row\"" . $table_col_span . "><textarea name=\"" . $html_tag_name . "\" cols=\"60\" rows=\"8\">" . $field_data . "</textarea> <span style=\"color: red;font-size: 11px;\">" . $field_required_phrase . "</span></td></tr>\r\n";
					}
					else
					{
						$kernel->vars['html']['custom_fields'] .= "<tr><td class=\"row\"" . $table_col_span . "><textarea name=\"" . $html_tag_name . "\" cols=\"60\" rows=\"8\"></textarea> <span style=\"color: red;font-size: 11px;\">" . $field_required_phrase . "</span></td></tr>\r\n";
					}
				}
			}
			
			if( SCRIPT_NAME != "submit.php" )
			{
				$kernel->vars['html']['custom_fields'] .= $kernel->tp->call( "admin_file_fiel_footer", CALL_TO_PAGE );
			}
			else
			{
				$kernel->vars['html']['custom_fields'] .= $kernel->tp->call( "file_fiel_footer", CALL_TO_PAGE );
			}
		}
	}
	
	/*
	 * Construct file download mirror HTML forms
	 **/
	
	function construct_file_download_mirror_form( $file_id = 0 )
	{
		global $kernel;
		
		$kernel->vars['html']['download_mirrors'] = $kernel->tp->call( "admin_file_mirror_header", CALL_TO_PAGE );
		
		$mirror_order = 0;
		
		if( $file_id > 0 )
		{
			$get_mirrors = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "mirrors` WHERE `mirror_file_id` = " . $file_id . " ORDER BY `mirror_order`, `mirror_name`" );
			
			if( $kernel->db->numrows() > 0 )
			{
				while( $mirror = $kernel->db->data( $get_mirrors ) )
				{
					//$kernel->vars['html']['download_mirrors'] .= "<tr><td class=\"row\"><input type=\"hidden\" name=\"mirror_id[]\" value=\"" . $mirror['mirror_id'] . "\"><input type=\"text\" name=\"mirror_order[]\" value=\"" . $mirror['mirror_order'] . "\" size=\"3\"></td><td class=\"row\"><input type=\"text\" name=\"mirror_name[]\" value=\"" . $mirror['mirror_name'] . "\" size=\"40\"></td><td class=\"row\"><input type=\"text\" name=\"mirror_file_url[]\" value=\"" . $mirror['mirror_file_url'] . "\" style=\"width: 99%;\"></td></tr>\r\n";
					
					$kernel->vars['html']['download_mirrors'] .= $kernel->tp->call( "admin_file_mirror_row_edit", CALL_TO_PAGE );
					
					list( $mirror['mirror_file_hash_md5'], $mirror['mirror_file_hash_sha1'] ) = explode( ",", $mirror['mirror_file_hash_data'] );
					if( empty( $mirror['mirror_file_hash_md5'] ) ) $mirror['mirror_file_hash_md5'] = $kernel->ld['phrase_unknown'];
					if( empty( $mirror['mirror_file_hash_sha1'] ) ) $mirror['mirror_file_hash_sha1'] = $kernel->ld['phrase_unknown'];
					
					$mirror['mirror_url_check'] = $this->return_verify_file_url( $mirror['mirror_file_url'], $file_id, $mirror['mirror_id'] );
					
					$kernel->vars['html']['download_mirrors'] = $kernel->tp->cache( $mirror, 0, $kernel->vars['html']['download_mirrors'] );
					
					$mirror_order = $mirror['mirror_order'];
				}
				
				if( $kernel->config['form_max_mirror_fields'] != "0" )
				{
					$kernel->vars['html']['download_mirrors'] .= $kernel->tp->call( "admin_file_mirror_break", CALL_TO_PAGE );
				}
			}
			
			$mirror_order++;
		}
		
		for( $i = 0; $i <= intval( $kernel->config['form_max_mirror_fields'] - 1 ); $i++ )
		{
			$kernel->vars['html']['download_mirrors'] .= $kernel->tp->call( "admin_file_mirror_row", CALL_TO_PAGE );
			$kernel->vars['html']['download_mirrors'] = $kernel->tp->cache( "mirror_order", $mirror_order, $kernel->vars['html']['download_mirrors'] );
			
			$mirror_order++;
		}
		
		$kernel->vars['html']['download_mirrors'] .= $kernel->tp->call( "admin_file_mirror_footer", CALL_TO_PAGE );
	}
	
	/*
	 * Construct file star rating
	 **/
	
	function construct_file_rating( $rating_points = 0, $total_votes = 0 )
	{
		global $kernel;
		
		$star_rank = 0;
		
		if( $total_votes == 0 )
		{
			return $kernel->ld['phrase_no_votes'];
		}
		
		if( $rating_points < 0 OR $rating_points > 5 )
		{
			$star_rank = ( $rating_points < 0 ) ? 0 : 5;
		}
		else
		{
			$star_rank = $rating_points;
		}
		
		if( $kernel->config['file_user_rating_mode'] == 1 OR $kernel->config['file_user_rating_mode'] == 2 )
		{
			$html = "<img src=\"./images/star_" . $star_rank . ".gif\" style=\"vertical-align: middle;\" alt=\"" . $star_rank . "/5\" />";
			
			if( $kernel->config['file_user_rating_mode'] == 2 )
  		{
  			$html .= "&nbsp;" . $star_rank . "/5";
  		}
		}
		else
		{
			return $star_rank . "/5";
		}
		
		return $html;
	}
	
	/*
	 * Construct file icon selector
	 **/
	
	//Needs expanding for file_mass_edit vars
	
	function construct_file_icon_selector( $icon_info = false, $file_id = 0 )
	{
		global $kernel;
		
		$kernel->vars['html']['icon_list_options'] = "";
		
		$handle = opendir( $kernel->config['system_root_dir'] . DIR_STEP . "images" . DIR_STEP . "icons" . DIR_STEP );
		
		if( $file_id > 0 )
		{
			$html_tag_name = "files[" . $file_id . "][file_icon]";
		}
		else
		{
			$html_tag_name = "file_icon";
		}
		
		$file_icon_none = ( empty( $icon_info ) ) ? " checked=\"checked\"" : "";
		
		$kernel->vars['html']['icon_list_options'] .= "<div style=\"margin: 10px;\"><input type=\"radio\" value=\"\" name=\"" . $html_tag_name . "\"" . $file_icon_none . ">&nbsp;<b>" . $kernel->ld['phrase_no_icon'] . "</b></div>\r\n";
		
		$file_icon_auto = ( $icon_info == "0" OR $icon_info == false ) ? " checked=\"checked\"" : "";
		
		$kernel->vars['html']['icon_list_options'] .= "<div style=\"margin: 10px;\"><input type=\"radio\" value=\"0\" name=\"" . $html_tag_name . "\"" . $file_icon_auto . ">&nbsp;<b>" . $kernel->ld['phrase_filetype_icon'] . "</b></div>\r\n";
  	
		while( ( $item = readdir( $handle ) ) !== false )
		{
			if( $item == "." OR $item == ".." OR $item == "index.htm" OR $item == "index.html" ) continue;
				
			if( $icon_info == $item )
			{
				$kernel->vars['html']['icon_list_options'] .= "<div style=\"display: inline;text-align: center; margin: 10px;\"><input type=\"radio\" value=\"" . $item . "\" name=\"" . $html_tag_name . "\" checked=\"checked\"><img src=\"../images/icons/" . $item . "\" border=\"0\"></div>\r\n";
			}
			else
			{
				$kernel->vars['html']['icon_list_options'] .= "<div style=\"display: inline;text-align: center; margin: 10px;\"><input type=\"radio\" value=\"" . $item . "\" name=\"" . $html_tag_name . "\"><img src=\"../images/icons/" . $item . "\" border=\"0\"></div>\r\n";
			}
		}
		
		closedir( $handle );
	}
	
	/*
	 * Construct file tags for form editing.
	 **/
	
	function construct_file_tags( $file_id = 0 )
	{
		global $kernel;
		
		$string = "";
		
		if( $file_id > 0 )
		{
			$fetch_tags = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "tags` WHERE `tag_file_id` = " . $file_id . " ORDER BY `tag_phrase`" );
			
			if( $kernel->db->numrows( $fetch_tags ) > 0 )
			{
				while( $tag = $kernel->db->data( $fetch_tags ) )
				{
					$string .= $tag['tag_phrase'] . " ";
				}
				
				$string = preg_replace( "/ $/", "", $string );
			}
		}
		
		return $string;
	}
	
	/*
	 * Synchronise file status - Update based on file validity period of set, return file state.
	 **/
	
	function synchronise_file_status()
	{
		global $kernel, $file;
		
		$file_disabled = "";
		
		if( ( $file['file_from_timestamp'] > 0 AND $file['file_from_timestamp'] > UNIX_TIME ) OR ( $file['file_to_timestamp'] > 0 AND $file['file_to_timestamp'] < UNIX_TIME ) )
		{
			$file_disabled = "1";
		}
		elseif( ( $file['file_from_timestamp'] > 0 AND $file['file_from_timestamp'] < UNIX_TIME ) OR ( $file['file_to_timestamp'] > 0 AND $file['file_to_timestamp'] > UNIX_TIME ) )
		{
			$file_disabled = "0";
		}
		
		if( $file_disabled != "" )
		{
			$kernel->db->update( "files", array( "file_disabled" => $file_disabled ), "WHERE `file_id` = " . $file['file_id'] );
			$file['file_disabled'] = $file_disabled;
		}
		
		return ( $file['file_disabled'] == "0" ) ? true : false;
	}
	
	/*
	 * Construct <select> options list, Fields must be selected in right order! (id, name)
	 **/
	
	function construct_list_options( $select_id = "", $list_group = "", $get_items, $phrase = true )
	{
		global $kernel;
		
		$list_group_array = array(
			"gallery" => "phrase_menu_choose_gallery",
			"image" => "phrase_menu_choose_images",
			"document" => "phrase_menu_choose_document",
			"theme" => "phrase_menu_choose_a_theme",
			"style" => "phrase_menu_choose_a_style",
		);
		
		$html = ( $phrase == true ) ? "<option value=\"\"></option>\r\n<optgroup label=\"" . $kernel->ld[ $list_group_array[ "$list_group" ] ] . "\">\r\n" : "";
		
		if( is_resource( $get_items ) )
		{
			if( $kernel->db->numrows( $get_items ) > 0 )
			{
				while( $item = $kernel->db->data( $get_items, MYSQL_NUM ) )
				{
					$item[1] = $kernel->format_input( $item[1], T_HTML );
					
					if( $select_id == $item[0] AND $select_id != "" )
					{
					$html .= "<option value=\"" . $item[0] . "\" selected=\"selected\">" . $item[1] . "</option>\r\n";
					}
					else
					{
						if( is_array( $select_id ) )
						{
							$match_found = false;
							
							foreach( $select_id AS $item_id )
							{
								if( $item_id == $item[0] )
								{
							$html .= "<option value=\"" . $item[0] . "\" selected=\"selected\">" . $item[1] . "</option>\r\n";
									$match_found = true; break;
								}
							}
							
							if( $match_found == false )
							{
								$html .= "<option value=\"" . $item[0] . "\">" . $item[1] . "</option>\r\n";
							}
							
							@reset( $select_id );
						}
						else
						{
							$html .= "<option value=\"" . $item[0] . "\">" . $item[1] . "</option>\r\n";
						}
					}
				}
			}
		}
		else
		{
			foreach( $get_items AS $id => $item )
			{
				$item = $kernel->format_input( $item, T_HTML );
				
				if( $select_id == $id AND $select_id != "" )
				{
					$html .= "<option value=\"" . $id . "\" selected=\"selected\">" . $item . "</option>\r\n";
				}
				else
				{
					if( is_array( $select_id ) )
					{
						$match_found = false;
						
						foreach( $select_id AS $item_id )
						{
							if( $item_id == $id )
							{
								$html .= "<option value=\"" . $id . "\" selected=\"selected\">" . $item . "</option>\r\n";
								$match_found = true;
								break;
							}
						}
						
						if( $match_found == false )
						{
							$html .= "<option value=\"" . $id . "\">" . $item . "</option>\r\n";
						}
						
						@reset( $select_id );
					}
					else
					{
						$html .= "<option value=\"" . $id . "\">" . $item . "</option>\r\n";
					}
				}
			}
		}
		
		$html .= ( $phrase == true ) ? "</optgroup>\r\n" : "\r\n";
		
		if( !empty( $list_group ) )
		{
			$kernel->vars['html'][ $list_group . "_list_options" ] = $html;
		}
		else
		{
			return $html;
		}
	}
	
	/*
	 * Construct <select> options list for usergroups, Fields must be selected in right order! (id, name)
	 **/
	
	function construct_usergroup_options( $select_id = "", $get_items, $hide_empty = false, $show_guest = false )
	{
		global $kernel;
		
		$html = ( $hide_empty == false ) ? "<option value=\"\"></option>\r\n" : "";
		
		$html = "<optgroup label=\"" . $kernel->ld['phrase_user_filter_list_groups'] . "\">\r\n";
		
		while( $item = $kernel->db->data( $get_items, MYSQL_NUM ) )
		{
			if( $item[0] < 0 AND $show_guest != true ) continue;
			
			if( $kernel->session->vars['adminsession_group_id'] <> 1 AND $item[0] == 1 OR $kernel->session->vars['adminsession_group_id'] <> 1 AND strpos( $item[2], "1" ) !== false ) continue;
			
			$item[1] = $kernel->format_input( $item[1] , T_FORM );
			
			if( $select_id == $item[0] AND $select_id != "" )
			{
				$html .= "<option value=\"" . $item[0] . "\" selected=\"selected\">" . $item[1] . "</option>\r\n";
			}
			else
			{
				if( is_array( $select_id ) )
				{
					$match_found = false;
					
					foreach( $select_id AS $item_id )
					{
						if( $item_id == $item[0] )
						{
							$html .= "<option value=\"" . $item[0] . "\" selected=\"selected\">" . $item[1] . "</option>\r\n";
							$match_found = true; break;
						}
					}
					
					if( $match_found == false )
					{
						$html .= "<option value=\"" . $item[0] . "\">" . $item[1] . "</option>\r\n";
					}
					
					@reset( $select_id );
				}
				else
				{
					$html .= "<option value=\"" . $item[0] . "\">" . $item[1] . "</option>\r\n";
				}
			}
		}
		
		$html .= "</optgroup>\r\n";
		
		$kernel->vars['html'][ "usergroup_list_options" ] = $html;
	}
	
	/*
	 * Construct <select> options list for specific groups of users.
	 **/
	
	function construct_user_options( $select_id = "", $usergroups = "", $option_value_mode = 0 )
	{
		global $kernel;
		
		$html = "<option value=\"\">" . $kernel->ld['phrase_menu_all_users'] . "</option>\r\n";
		
		$usergroup_query_syntax = "";
		$last_usergroup_id = 0;
		
		//build usergroup id's into query syntax
		if( is_array( $usergroups ) )
		{
			$usergroup_query_syntax = "WHERE ";
			
			foreach( $usergroups AS $usergroup_id )
			{
				$usergroup_query_syntax .= "`user_group_id` = " . $usergroup_id . " OR ";
			}
			
			$usergroup_query_syntax = preg_replace( "/OR $/", "", $usergroup_query_syntax );
		}
		
		//run query
		$get_items = $kernel->db->query( "SELECT u.user_id, u.user_name, u.user_group_id, g.usergroup_title FROM " . TABLE_PREFIX . "users u LEFT JOIN " . TABLE_PREFIX . "usergroups g ON ( u.user_group_id = g.usergroup_id ) " . $usergroup_query_syntax . " ORDER BY `user_group_id`, `user_name`" );
		
		while( $item = $kernel->db->data( $get_items, MYSQL_NUM ) )
		{
			if( $last_usergroup_id != $item[2] )
			{
				if( $last_usergroup_id > 0 )
				{
					$html .= "</optgroup>\r\n";
				}
				
				$html .= "<optgroup label=\"" . $item[3] . "\">\r\n";
			}
			$last_usergroup_id = $item[2];
			
			$option_value = ( $option_value_mode == 0 ) ? $item[0] : $kernel->format_input( $item[1] , T_URL_ENC );
			
			$item[1] = $kernel->format_input( $item[1] , T_FORM );
			
			if( $select_id == $option_value AND $select_id != "" )
			{
				$html .= "<option value=\"" . $option_value . "\" selected=\"selected\">" . $item[1] . "</option>\r\n";
			}
			else
			{
				$html .= "<option value=\"" . $option_value . "\">" . $item[1] . "</option>\r\n";
			}
		}
		
		$html .= "</optgroup>\r\n";
		
		$kernel->vars['html'][ "user_list_options" ] = $html;
	}
	
	/*
	 * Update the database counter for the specified item
	 **/
	
	function update_database_counter( $string, $script_name="", $script_line=0 )
	{
		global $kernel;
		
		$tablefield = array (
			"announcements" => "announcement_id",
			"categories" => "category_id",
			"comments" => "comment_id",
			"documents" => "document_id",
			"fields" => "field_id",
			"fields_data" => "field_id",
			"files" => "file_id",
			"galleries" => "gallery_id",
			"images" => "image_id",
			"mirrors" => "mirror_id",
			"sites" => "site_id",
			"styles" => "style_id",
			"submissions" => "submission_id",
			"subscriptions" => "subscription_id",
			"tags" => "tag_id",
			"templates" => "template_id",
			"themes" => "theme_id",
			"users" => "user_id",
			"usergroups" => "usergroup_id",
			"votes" => "vote_id"
		);
		
		if( $string == "comments" )
		{
			$new_count = $kernel->db->item( "SELECT SUM( `file_total_comments` ) AS `value` FROM `" . TABLE_PREFIX . "files` WHERE `file_disabled` = 0", MYSQL_NUM, $script_name, $script_line );
		}
		elseif( $string == "downloads" )
		{
			$new_count = $kernel->db->item( "SELECT SUM( `file_downloads` ) AS `value` FROM `" . TABLE_PREFIX . "files` WHERE `file_disabled` = 0", MYSQL_NUM, $script_name, $script_line );
		}
		elseif( $string == "data" )
		{
			$new_count = $kernel->db->item( "SELECT SUM( `file_size` ) AS `value` FROM `" . TABLE_PREFIX . "files` WHERE `file_disabled` = 0", MYSQL_NUM, $script_name, $script_line );
		}
		elseif( $string == "files" )
		{
			$new_count = $kernel->db->item( "SELECT COUNT( `file_id` ) AS `value` FROM `" . TABLE_PREFIX . "files` WHERE `file_disabled` = 0", MYSQL_NUM, $script_name, $script_line );
		}
		elseif( $string == "views" )
		{
			$new_count = $kernel->db->item( "SELECT SUM( `file_views` ) AS `value` FROM `" . TABLE_PREFIX . "files` WHERE `file_disabled` = 0", MYSQL_NUM, $script_name, $script_line );
		}
		elseif( !empty( $tablefield[ $string ] ) )
		{
			$new_count = $kernel->db->numrows( "SELECT `" . $tablefield[ $string ] . "` FROM `" . TABLE_PREFIX . $string . "`", $script_name, MYSQL_NUM, $script_line );
		}
		else
		{
			$new_count = 0;
		}
		
		$kernel->db->update( "datastore", array( "datastore_value" => $new_count, "datastore_timestamp" => UNIX_TIME ), "WHERE `datastore_key` = 'count_total_" . $string . "'", $script_name, $script_line );
	}
	
	/*
	 * Check supplied vars for fields and there data against the field data rule
	 **/
	
	function verify_custom_field_values( $field_data )
	{
		global $kernel;
		
		$get_fields = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "fields`" );
		
		if( $kernel->db->numrows() > 0 )
		{
			while( $field = $kernel->db->data( $get_fields ) )
			{
				if( isset( $field_data[ 'field_'.$field['field_id'] ] ) )
				{
					if( $field['field_data_rule'] == 1 AND empty( $field_data[ 'field_'.$field['field_id'] ] ) )
					{
						$kernel->page->message_report( sprintf( $kernel->ld['phrase_field_bad_value_alphanumeric'], $field['field_name'] ), M_ERROR, HALT_EXEC );
					}
					
					if( $field['field_data_rule'] == 2 AND strlen( intval( $field_data[ 'field_'.$field['field_id'] ] ) ) !== strlen( $field_data[ 'field_'.$field['field_id'] ] ) )
					{
						$kernel->page->message_report( sprintf( $kernel->ld['phrase_field_bad_value_numeric'], $field['field_name'] ), M_ERROR, HALT_EXEC );
					}
				}
			}
		}
	}
	
	/*
	 * Construct and execute db queries for custom fields based on _POST vars
	 **/
	
	function construct_db_write_custom_fields( $file_id = 0, $field_data )
	{
		global $kernel, $file;
		
		$field_group = ( $kernel->vars['node'] == "file_sub_manage" OR SCRIPT_NAME == "submit.php" ) ? "submission" : "file";
		
		if( $file_id > 0 )
		{
			$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "fields_data` WHERE `field_" . $field_group . "_id` = " . $file_id );
		}
		
		$get_fields = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "fields`" );
		
		if( $kernel->db->numrows() > 0 )
		{
			while( $field = $kernel->db->data( $get_fields ) )
			{
				if( isset( $field_data[ 'field_'.$field['field_id'] ] ) )
				{
					if( $field['field_data_rule'] == 1 AND empty( $field_data[ 'field_'.$field['field_id'] ] ) )
					{
						$kernel->page->message_report( sprintf( $kernel->ld['phrase_field_bad_value_alphanumeric'], $field['field_name'] ), M_ERROR, M_ERROR );
					}
					elseif( $field['field_data_rule'] == 2 AND $kernel->iis_numeric( $field_data[ 'field_' . $field['field_id'] ] ) == false )
					{
						$kernel->page->message_report( sprintf( $kernel->ld['phrase_field_bad_value_numeric'], $field['field_name'] ), M_ERROR, M_ERROR );
					}
					else
					{
						$fielddata = array(
							"field_id" => $field['field_id'],
							"field_" . $field_group . "_id" => $file_id,
							"field_file_data" => $kernel->format_input( $field_data[ 'field_'.$field['field_id'] ], T_DB )
						);
						
						$kernel->db->insert( "fields_data", $fielddata );
					}
				}
			}
		}
	}
	
	/*
	 * Construct and upload posted images
	 **/
	
	function construct_upload_images_list( $images )
	{
		global $kernel;
		
		$return = "";
		
		if( is_array( $images['name'] ) )
		{
			$list = array();
			$total_files = sizeof( $images['name'] );
			
			//overwrite filetypes global with just image types
			$kernel->filetype = array_flip( array( "BMP", "GIF", "JPG", "JPEG", "PNG", "TIFF", "TIF" ) );
			
			for( $i = 0; $i <= $total_files; $i++ )
			{
				if( empty( $images['name'][$i] ) ) continue;
				
				//prep file data for updating
				$file_info = $kernel->archive->file_url_info( $images['name'][$i] );
				
				//check for upload errors
				$kernel->page->verify_upload_details( "image_upload" );
				
				//filetype not allowed
				if( !isset( $kernel->filetype[ $file_info['file_type'] ] ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_bad_image_filetype'], M_ERROR, HALT_EXEC );
				}
				
				$image_directory = $kernel->config['system_root_dir_gallery'] . DIR_STEP;
				
				//check for exisiting files under provided file name
				$new_file_name = $kernel->page->construct_upload_file_name( $images['name'][$i], $image_directory );
				
				//upload
				if( !@move_uploaded_file( $images['tmp_name'][$i], $image_directory . $new_file_name ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_could_not_move_upload_file'], M_CRITICAL_ERROR );
				}
				else
				{
					$imagedata = array(
						"image_name" => $kernel->format_input( $new_file_name, T_DB ),
						"image_file_name" => $kernel->format_input( $new_file_name, T_DB ),
						"image_timestamp" => UNIX_TIME
					);
					
					$image_dimensions = @getimagesize( $image_directory . $new_file_name );
					$imagedata['image_dimensions'] = $kernel->format_input( $image_dimensions[0] . "x" . $image_dimensions[1], T_DB );
					
					if( $kernel->config['gd_thumbnail_feature'] == "true" )
					{
						$kernel->archive->check_file_permissions( $image_directory . "thumbs". DIR_STEP );
						
						$kernel->image->construct_thumbnail( $image_directory . $new_file_name, $image_directory . "thumbs" . DIR_STEP . $new_file_name, $image_dimensions );
					}
					
					$kernel->db->insert( "images", $imagedata );
					
					$list[] = $kernel->db->insert_id();
				}
			}
			
			$kernel->archive->update_database_counter( "images" );
			
			$return = implode( ",", $list );
		}
		
		return $return;
	}
	
	/*
	 * Construct and execute db queries for file tags based on _POST vars
	 **/
	
	function construct_db_write_tags( $file_id = 0, $field_data )
	{
		global $kernel;
		
		$existing_tags = array();
		
		if( $field_data != "" )
		{
			$submit_tags = explode( " ", $field_data );
		}
		
		//Fetch existing tags
		$fetch_existing_tags = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "tags` WHERE `tag_file_id` = " . $file_id );
		
		if( $kernel->db->numrows( $fetch_existing_tags ) > 0 )
		{
			while( $tag = $kernel->db->data( $fetch_existing_tags ) )
			{
				$existing_tags[ $tag['tag_phrase'] ] = 0;
			}
		}
		
		//Add new tags
		if( is_array( $submit_tags ) AND count( $submit_tags ) > 0 )
		{
			foreach( $submit_tags AS $phrase )
			{
				if( isset( $existing_tags[ "$phrase" ] ) ) continue;
				
				$new_tags[ "$phrase" ] = $phrase;
				
				$kernel->db->insert( "tags", array( "tag_file_id" => $file_id, "tag_phrase" => $phrase ) );
			}
		}
		
		//Delete any missing tags
		if( is_array( $existing_tags ) AND count( $existing_tags ) > 0 )
		{
			$submit_tags = array_flip( $submit_tags );
			
			foreach( $existing_tags AS $phrase => $i )
			{
				if( !isset( $submit_tags[ "$phrase" ] ) )
				{
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "tags` WHERE `tag_file_id` = " . $file_id . " AND `tag_phrase` = '" . $phrase . "'" );
				}
			}
		}
		
		$kernel->archive->update_database_counter( "tags" );
	}
	
	/*
	 * Return STRTIME period converted to UNIXTIME for query use
	 **/
  
	function construct_db_timestamp_filter( $filter = "today", $query_field_name = "l.log_timestamp" )
	{	
		//today
		if( $filter == "today" )
		{
			$start = strtotime( date( "Y", UNIX_TIME ) . "-" . date( "m", UNIX_TIME ) . "-" . date( "d", UNIX_TIME ) . " 00:00:00" );
			$end = ( $start + 86399 );
			
			return array( "( " . $query_field_name . " >= " . $start . " )", $start, $end );
		}
  	
		//entire day
		elseif( ( $filter >= 1 AND $filter <= 7 ) )
		{
			$time = ( UNIX_TIME - ( 86400 * ( $filter ) ) );
			
			$start = strtotime( date( "Y", $time ) . "-" . date( "m", $time ) . "-" . date( "d", $time ) . " 00:00:00" );
			$end = ( $start + 86399 );
			
			return array( "( " . $query_field_name . " >= " . $start . " ) AND ( " . $query_field_name . " <= " . $end . " )", $start, $end );
		}
		
		//this week, based on todays date
		elseif( $filter == "week" )
		{
			$time = ( UNIX_TIME - ( 86400 * ( date( "w", UNIX_TIME ) ) ) );
			
			$start = strtotime( date( "Y", $time ) . "-" . date( "m", $time ) . "-" . date( "d", $time ) . " 00:00:00" );
			$end = ( $start + 604799 );
			
			return array( "( " . $query_field_name . " >= " . $start . " )", $start, $end );
		}
		
		//entire week
		elseif( substr( $filter, 0, 4 ) == "week" )
		{
			$flag = explode( ":", $filter );
			
			$time = ( UNIX_TIME - ( ( 604800 * $flag[1] ) + ( 86400 * date( "w", UNIX_TIME ) ) ) );
			
			$start = strtotime( date( "Y", $time ) . "-" . date( "m", $time ) . "-" . date( "d", $time ) . " 00:00:00" );
			$end = ( $start + 604799 );
			
			return array( "( " . $query_field_name . " >= " . $start . " ) AND ( " . $query_field_name . " <= " . $end . " )", $start, $end );
		}
  	
		//entire month
		elseif( substr( $filter, 0, 5 ) == "month" )
		{
			$flag = explode( ":", $filter );
			
			$start = strtotime( $flag[1] . "-" . $flag[2] . "-01 00:00:00" );
			$end = ( $start + ( 86400 * date( "t", $start ) ) - 1 );
			
			return array( "( " . $query_field_name . " >= " . $start . " ) AND ( " . $query_field_name . " <= " . $end . " )", $start, $end );
		} 
	}
	
	/*
	 * Construct date list options to todays date from archive start time (soo...messy..)
	 **/
	
	function construct_date_list_options( $selected_time = "" )
	{
		global $kernel;
		
		$menu_selected[ 'date_' . $selected_time ] = " selected=\"selected\"";
		$log_break = false;
		
		//day options
		$kernel->vars['html']['filter_list_options'] = "<optgroup label=\"" . $kernel->ld['phrase_days'] . "\">\r\n";
		
		$kernel->vars['html']['filter_list_options'] .= "<option value=\"today\"" . $menu_selected['date_today'] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $kernel->ld['phrase_today'] . "</option>\r\n";
		
		for( $i = 1; $i <= 7; $i++ )
		{
			$time = ( UNIX_TIME - ( 86400 * $i ) );
			$stamp = strtotime( date( "Y", $time ) . "-" . date( "m", $time ) . "-" . date( "d", $time ) . " 00:00:00" );
			
			$selected_day = $i;
			$display_day = date( "l", $stamp );
			
			if( $i == 1 )
			{
				$kernel->vars['html']['filter_list_options'] .= "<option value=\"" . $selected_day . "\"" . $menu_selected['date_' . $selected_day . '' ] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $kernel->ld['phrase_yesterday'] . "</option>\r\n";
			}
			elseif( $i == 7 )
			{
				$kernel->vars['html']['filter_list_options'] .= "<option value=\"" . $selected_day . "\"" . $menu_selected['date_' . $selected_day . '' ] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $kernel->ld['phrase_last_menu_day'] . "" . $display_day . "</option>\r\n";
			}
			else
			{
				$kernel->vars['html']['filter_list_options'] .= "<option value=\"" . $selected_day . "\"" . $menu_selected['date_' . $selected_day . '' ] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $display_day . "</option>\r\n";
			}
		}
		
		//week options
		$kernel->vars['html']['filter_list_options'] .= "</optgroup><optgroup label=\"" . $kernel->ld['phrase_weeks'] . "\">\r\n<option value=\"week:0\"" . $menu_selected['date_week:0'] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $kernel->ld['phrase_this_week'] . "</option>\r\n<option value=\"week:1\"" . $menu_selected['date_week:1'] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $kernel->ld['phrase_last_week'] . "</option>\r\n<option value=\"week:2\"" . $menu_selected['date_week:2'] . ">" . $kernel->ld['phrase_menu_sort'] . "2 Weeks ago</option>\r\n<option value=\"week:3\"" . $menu_selected['date_week:3'] . ">" . $kernel->ld['phrase_menu_sort'] . "3 Weeks ago</option>\r\n<option value=\"week:4\"" . $menu_selected['date_week:4'] . ">" . $kernel->ld['phrase_menu_sort'] . "4 Weeks ago</option>\r\n";
		
		//month options
		$i = 0;
		$current_year = date( "Y", UNIX_TIME );
		$current_month = date( "n", UNIX_TIME );
		
		$kernel->vars['html']['filter_list_options'] .= "</optgroup><optgroup label=\"" . $kernel->ld['phrase_months'] . "\">\r\n";
		
		while( $log_break == false )
		{
			$stamp = strtotime( $current_year . "-" . $current_month . "-01 00:00:00" );
			
			$selected_mnt = date( "M", $stamp );
			$display_mnt = $kernel->fetch_time( $stamp, "%B" );
			$mnt_year = $current_year;
			
			if( $i == 0 )
			{
				$kernel->vars['html']['filter_list_options'] .= "<option value=\"month:" . $current_year . ":" . $current_month . "\"" . $menu_selected[ 'date_month:' . $current_year . ':' . $current_month ] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $kernel->ld['phrase_this_month'] . "</option>\r\n";
			}
			else
			{
				$kernel->vars['html']['filter_list_options'] .= "<option value=\"month:" . $current_year . ":" . $current_month . "\"" . $menu_selected[ 'date_month:' . $current_year . ':' . $current_month ] . ">" . $kernel->ld['phrase_menu_sort'] . "" . $display_mnt . " " . $mnt_year . "</option>\r\n";
			}
			
			$current_year = ( $current_month == 1 ) ? ( $current_year - 1 ) : $current_year;
			$current_month = ( ( $current_month - 1 ) < 1 ) ? 12 : ( $current_month - 1 );
  		
			if( $kernel->config['archive_start'] >= $stamp )
			{
				$log_break = true; break;
			}
			
			$i++;
		}
		
		$kernel->vars['html']['filter_list_options'] .= "</optgroup>";
	}
	
	/*
	 * Update category "New File" for specified category.
	 **/
	
	function update_category_new_file( $id )
	{
		global $kernel;
		
		$kernel->db->update( "categories", array( "category_newfile_id" => intval( $kernel->db->item( "SELECT `file_id` FROM `" . TABLE_PREFIX . "files` WHERE `file_cat_id` = " . $id . " AND `file_disabled` = 0 ORDER BY `file_mark_timestamp` DESC" ) ) ), "WHERE `category_id` = " . $id );
	}
	
	/*
	 * Update category "File Count" for specified category.
	 **/
	
	function update_category_file_count( $id )
	{
		global $kernel;
		
		$kernel->db->update( "categories", array( "category_file_total" => $kernel->db->item( "SELECT COUNT(*) AS `count` FROM `" . TABLE_PREFIX . "files` WHERE `file_cat_id` = " . $id . " AND `file_disabled` = 0" ) ), "WHERE `category_id` = " . $id );
	}
	
	/*
	 * Wrapper function for global category syncronisation
	 **/
	
	function global_category_syncronisation()
	{
		global $kernel;
		
		$fetch_categories = $kernel->db->query( "SELECT `category_id` FROM `" . TABLE_PREFIX . "categories`" );
		
		if( $kernel->db->numrows() > 0 )
		{
			while( $category = $kernel->db->data( $fetch_categories ) )
			{
				$total_files = 0;
				
				$this->update_category_new_file( $category['category_id'] );
				
				$this->update_category_file_count( $category['category_id'] );
			}
		}
	}
	
	/*
	 * Return leech status
	 **/
	
	function verify_leech_status()
	{
		global $kernel;
		
		$leech_attempt_flag = ( ( $kernel->config['site_list_leech_mode'] == 1 ) ? false : true );
		
		$check_sites = $kernel->db->query( "SELECT `site_name` FROM `" . TABLE_PREFIX . "sites`" );
		
		while( $site = $kernel->db->data( $check_sites ) )
		{
			if( strstr( SCRIPT_REFERER, $site['site_name'] ) !== false )
			{
				$leech_attempt_flag = ( ( $kernel->config['site_list_leech_mode'] == 1 ) ? true : false );
			}
		}
		
		if( strstr( SCRIPT_REFERER, str_replace( "www.", "", HTTP_HOST ) ) !== false )
		{
			$leech_attempt_flag = false;
		}
		
		// Log leech attempt
		if( $leech_attempt_flag == true )
		{
			$kernel->db->insert( "logs", array( "log_type" => 2, "log_file_id" => $kernel->vars['id'], "log_user_id" => $kernel->session->vars['session_user_id'], "log_mirror_id" => 0, "log_user_agent" => HTTP_AGENT, "log_timestamp" => UNIX_TIME, "log_ip_address" => IP_ADDRESS ) );
			
			$kernel->page->message_report( $kernel->ld['phrase_no_leeching'], M_WARNING, HALT_EXEC );
		}
	}
	
	/*
	 * Return integrity of file url
	 **/
	
	function return_verify_file_url( $file_url, $file_id, $mirror_id = 0, $return_flag = 0 )
	{
		global $kernel, $_SERVER;
		
		$file_info = $this->file_url_info( $file_url );
		
		if( ereg( $_SERVER['HTTP_HOST'], $file_url ) )
		{
			if( $kernel->url_exists( $file_url ) == true )
			{
				return ( $return_flag == 1 ) ? 1 : "<a href=\"javascript:initPopup( '" . $kernel->config['system_root_url_path'] . "/admin/index.php?hash=" . $kernel->session->vars['hash'] . "&node=file_validate&file_id=" . $file_id . "&mirror_id=" . $mirror_id . "&action=verify', 600, 600 );\"><img border=\"0\" src=\"./images/checked.gif\" alt=\"" . $kernel->ld['phrase_file_valid_url'] . "\"></a>";
			}
			else
			{
				return ( $return_flag == 1 ) ? 2 : "<a href=\"javascript:initPopup( '" . $kernel->config['system_root_url_path'] . "/admin/index.php?hash=" . $kernel->session->vars['hash'] . "&node=file_validate&file_id=" . $file_id . "&mirror_id=" . $mirror_id . "&action=verify', 600, 600 );\"><img border=\"0\" src=\"./images/alert.gif\" alt=\"" . $kernel->ld['phrase_file_invalid_url'] . "\"></a>";
			}
		}
		else
		{
			if( empty( $file_info['file_name'] ) )
			{
				return ( $return_flag == 1 ) ? 3 : "<a href=\"javascript:initPopup( '" . $kernel->config['system_root_url_path'] . "/admin/index.php?hash=" . $kernel->session->vars['hash'] . "&node=file_validate&file_id=" . $file_id . "&mirror_id=" . $mirror_id . "&action=verify', 600, 600 );\"><img border=\"0\" src=\"./images/alert.gif\" alt=\"" . $kernel->ld['phrase_file_unknown_location'] . "\"></a>";
			}
			else
			{
				return ( $return_flag == 1 ) ? 4 : "<a href=\"javascript:initPopup( '" . $kernel->config['system_root_url_path'] . "/admin/index.php?hash=" . $kernel->session->vars['hash'] . "&node=file_validate&file_id=" . $file_id . "&mirror_id=" . $mirror_id . "&action=verify', 600, 600 );\"><img border=\"0\" src=\"./images/information.gif\" alt=\"" . $kernel->ld['phrase_file_external_file'] . "\"></a>";
			}
		}
	}
	
	/*
	 * Retrieve file MD5 or SHA1 hash
	 **/
	
	function exec_file_hash( $url, $return_md5 = true )
	{
		global $kernel;
		
		$url = $kernel->format_input( $url, T_PATH_PARSE );
		
		if( defined( "OPENSSL_KEYTYPE_RSA" ) AND function_exists( "exec" ) AND strpos( $url, $kernel->config['system_root_dir_upload'] ) !== false )
		{
			$result = split( "=", exec( "openssl " . ( ( $return_md5 ) ? "md5" : "sha1" ) . " " . str_replace( " ", "\ ", escapeshellcmd( $url ) ) ) );
			return $result[1];
		}
		
		if( PHP_VERSION >= 5.1 )
		{
			if( $return_md5 == false )
			{
				$hash = sha1_file( $url );
			}
			elseif( $return_md5 == true )
			{
				$hash = md5_file( $url );
			}
			
			return ( $hash == false ) ? "" : $hash;
		}
		
		if( PHP_VERSION >= 4.2 )
		{
			if( $url{1} == ":" OR $url{0} == "/" OR $url{0} == "\\" )
			{
				if( $return_md5 == false AND PHP_VERSION >= 4.3 )
				{
					$hash = sha1_file( $url );
				}
				elseif( $return_md5 == true )
				{
					$hash = md5_file( $url );
				}
				
				return ( $hash == false ) ? "" : $hash;
			}
			
			if( strpos( $url, $kernel->config['system_root_url_upload'] ) !== false )
			{
				if( $return_md5 == false AND PHP_VERSION >= 4.3 )
				{
					$hash = sha1_file( $url );
				}
				elseif( $return_md5 == true )
				{
					$hash = md5_file( $url );
				}
				
				return ( $hash == false ) ? "" : $hash;
			}
		}
		
		return "";
	}
	
	/*
	 * Prepare and exec file download
	 **/
	
	function exec_file_download( $details, $user_baud = 0 )
	{
		global $kernel, $_SERVER;
		
		if( empty( $details['timestamp'] ) ) $details['timestamp'] = strtotime( date( "Y" ) . "-01-01 00:00:00" );
		
		$file_info = $kernel->archive->file_url_info( $details['url'] );
		
		$file_url_state = $this->return_verify_file_url( $details['url'], $details['id'], $details['mirror_id'], 1 );
		
		//Hash checking enabled?
		if( $kernel->config['archive_file_check_hash'] == "true" AND !empty( $details['hash'] ) )
		{
			list( $file_hash_md5, $file_hash_sha1 ) = explode( ",", $details['hash'] );
			
			$md5 = $this->exec_file_hash( $details['url'] );
			$sha1 = $this->exec_file_hash( $details['url'], false );
			
			if( ( $file_hash_md5 != $md5 AND !empty( $md5 ) ) OR ( $file_hash_sha1 != $sha1 AND !empty( $sha1 ) ) )
			{
				//Log hash difference and continue..
				$kernel->db->insert( "logs", array( "log_type" => 4, "log_file_id" => $details['id'], "log_user_id" => $kernel->session->vars['session_user_id'], "log_mirror_id" => $details['mirror_id'], "log_user_agent" => HTTP_AGENT, "log_timestamp" => UNIX_TIME, "log_ip_address" => IP_ADDRESS ) );
				
				if( $kernel->config['email_notify_modified_url'] == "true" )
				{
					$emaildata = array(
						"file_name" => $details['name'],
						"user_name" => ( !empty( $kernel->session->vars['session_name'] ) AND USER_LOGGED_IN == true ) ? $kernel->session->vars['session_name'] : $kernel->ld['phrase_guest'],
						"category_name" => $kernel->db->item( "SELECT c.category_name FROM " . TABLE_PREFIX . "files f LEFT JOIN " . TABLE_PREFIX . "categories c ON ( f.file_cat_id = c.category_id ) WHERE f.file_id = " . $details['id'] ),
						"archive_title" => $kernel->config['archive_name']
					);
					
					$kernel->archive->construct_send_email( "file_modified_url_notification", $kernel->config['mail_inbound'], $emaildata );
				}
			}
		}
		
		@ob_end_clean();
		
		header( "Last-Modified: " . date( "D, d M Y H:i:s \G\M\T" , $details['timestamp'] ) . "\r\n" );
		header( "Cache-Control: no-cache, must-revalidate\r\n" );
		header( "Pragma: no-cache\r\n" );
		header( "Accept-Ranges: bytes\r\n" );
		
		//URL is not a server link and archive not in masked download mode.
		if( $kernel->config['file_download_mode'] != 1 AND ( $details['url']{1} != ":" AND $details['url']{0} != "/" AND $details['url']{0} != "\\" ) )
		{
			$details['url'] = $kernel->format_input( $details['url'], T_DL_PARSE );
			
			//Direct link download mode
			if( $kernel->config['file_download_mode'] == 0 )
			{
				//URL is indirect, new window?
				if( $file_url_state == 3 AND $kernel->config['file_download_indirect_mode'] == "true" )
				{
					$kernel->tp->call( "file_download_forwarder" );
					
					$kernel->tp->cache( $details );
				}
				else
				{
					if( $kernel->url_exists( $details['url'] ) == false )
					{
						$this->return_file_download_error( $details );
					}
					else
					{
						header( "Content-Type: application/octet-stream\r\n" );
						header( "Content-Disposition: attachment; filename=" . $file_info['file_name'] . "\r\n" );
						
						if( $details['size'] > 0 AND $kernel->config['file_download_size_mode'] == 1 )
						{
							header( "Content-Length: " . $details['size'] . "\r\n" );
						}
						
						if( !empty( $kernel->config['upload_dir_http_username'] ) AND strpos( $details['url'], $kernel->config['system_root_url_upload'] ) !== false )
						{
							header( "Authorization: Basic " . base64_encode( $kernel->config['upload_dir_http_username'] . ":" . $kernel->config['upload_dir_http_password'] ) . "\r\n" );
						}
						
						header( "Location: " . $details['url'] . "\r\n" );
						
						exit;
					}
				}
			}
		}
		else
		{
			//Masked download mode and URL is indirect
			if( $file_url_state == 3 AND $kernel->config['file_download_indirect_mode'] == "true" )
			{
				$kernel->tp->call( "file_download_forwarder" );
				
				$kernel->tp->cache( $details );
			}
			else
			{
				//Offer resume point if available.
				if( isset( $_SERVER['HTTP_RANGE'] ) )
				{
					$seek_range = substr( $_SERVER['HTTP_RANGE'], 6 );
					
					list( $seek_start, $seek_end ) = explode( "=", $seek_range );
					
					header( "HTTP/1.1 206 Partial Content\r\n" );
					header( "Content-Length: " . ( $seek_end - $seek_start + 1 ) . "\r\n" );
					header( "Content-Range: bytes " . $seek_start . "-" . $seek_end . "/" . $details['size'] . "\r\n" );
				}
				else
				{
					header( "Content-Length: " . $details['size'] . "\r\n" );
				}
				
				//Bandwidth limiting only works with server links, replace upload URL path with upload DIR path..
				if( strpos( $details['url'], $kernel->config['system_root_url_upload'] ) !== false )
				{
					$details['url'] = str_replace( '/', DIR_STEP, str_replace( $kernel->config['system_root_url_upload'], $kernel->config['system_root_dir_upload'], $details['url'] ) );
				}
				
				$fp = @fopen( $details['url'], "rb" );
				
				if( $fp == true )
				{
					header( "Content-Type: application/octet-stream" . "\r\n" );
					header( "Content-Disposition: attachment; filename=" . $file_info['file_name'] . "\r\n" );
					
					@flush();
					@ob_flush();
					
					if( $user_baud > 0 )
					{
						if( isset( $_SERVER['HTTP_RANGE'] ) )
						{
							@fseek( $fp, $seek_start );
						}
						
						while( !feof( $fp ) AND connection_status() == 0 )
						{
							print @fread( $fp, ( $user_baud * 1024 ) );
							
							@flush();
							@ob_flush();
							
							sleep( 1 );
						}
					}
					else
					{
						@readfile( $details['url'] );
					}
					
					@fclose( $fp );
					
					exit;
				}
				else
				{
					$this->return_file_download_error( $details );
				}
			}
		}
	}
	
	/*
	 * Construct and send out an email
	 **/
	
	function return_file_download_error( $details )
	{
		global $kernel;
		
		@ob_end_clean();
		
		$kernel->db->insert( "logs", array( "log_type" => 3, "log_file_id" => $details['id'], "log_user_id" => $kernel->session->vars['session_user_id'], "log_mirror_id" => $details['mirror_id'], "log_user_agent" => HTTP_AGENT, "log_timestamp" => UNIX_TIME, "log_ip_address" => IP_ADDRESS ) );
		
		if( $kernel->config['email_notify_broken_url'] == "true" )
		{
			$emaildata = array(
				"file_name" => $details['name'],
				"user_name" => ( !empty( $kernel->session->vars['session_name'] ) AND USER_LOGGED_IN == true ) ? $kernel->session->vars['session_name'] : $kernel->ld['phrase_guest'],
				"category_name" => $kernel->db->item( "SELECT c.category_name FROM " . TABLE_PREFIX . "files f LEFT JOIN " . TABLE_PREFIX . "categories c ON ( f.file_cat_id = c.category_id ) WHERE f.file_id = " . $details['id'] ),
				"archive_title" => $kernel->config['archive_name']
			);
			
			$kernel->archive->construct_send_email( "file_broken_url_notification", $kernel->config['mail_inbound'], $emaildata );
		}
		
		$kernel->page->message_report( $kernel->ld['phrase_file_no_download'], M_ERROR, HALT_EXEC );
	}
	
	/*
	 * Construct and send out an email
	 **/
	
	function construct_send_email( $template_name, $recipient, $data )
	{
		global $kernel;
		
		$get_template = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "templates_email` WHERE `template_name` = '" . $template_name . "'" );
		
		if( $kernel->db->numrows() > 0 )
		{
			$template = $kernel->db->data( $get_template );
			
			$headers = "From: " . $kernel->config['mail_outbound'] . "\r\n";
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-type: text/html; charset=" . $kernel->ld['lang_var_charset'] . "\r\n";
			
			$template['template_data'] = $kernel->format_input( $template['template_data'], T_HTML );
			
			$template['template_subject'] = $kernel->tp->cache( $data, 0, $template['template_subject'] );
			$template['template_data'] = $kernel->tp->cache( $data, 0, $template['template_data'] );
			
			if( FUNC_INI_GET == true )
			{
				if( !empty( $kernel->config['mail_smtp_path'] ) ) @ini_set( "SMTP", $kernel->config['mail_smtp_path'] );
				if( !empty( $kernel->config['mail_smtp_port'] ) ) @ini_set( "smtp_port", $kernel->config['mail_smtp_port'] );
				
				@ini_set( "sendmail_from", $kernel->config['mail_outbound'] );
			}
			
			if( @mail( $recipient, $template['template_subject'], $template['template_data'], $headers ) )
			{
				return true;
			}
		}
		
		return false;
	}
}

?>