<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

/*
 * Define function flag constants
 **/

define( "CACHE_TO_TOP",			1 );

define( "V_ARRAY",				110 );
define( "V_BIN",				120 );
define( "V_INT",				130 );
define( "V_PINT",				140 );
define( "V_STR",				150 );
define( "V_TIME",				160 );
define( "V_BOOL",				170 );
define( "V_MD5",				180 );

define( "T_HTML",				210 );
define( "T_NUM",				220 );
define( "T_FORM",				230 );
define( "T_URL_ENC",			240 );
define( "T_URL_DEC",			250 );
define( "T_NOHTML",				260 );
define( "T_DB",					270 );
define( "T_PREVIEW",			280 );
define( "T_STRIP",				290 );
define( "T_DL_PARSE",			300 );
define( "T_RAW",				310 );

/*
 * Core Kernel class
 **/

class class_kernel
{
	/*
	 * Sub class vars
	 **/
	
	var $db;						//database sub-class
	var $ld = array();				//phrase list from loaded language
	var $tp;						//template engine sub-class
	var $vars = array();			//template engine cached vars
	var $config = array();			//config globals
	var $subscription = array();	//user subscription functions and api data
	
	var $html = array();			//template html cache
	
	var $session = "";				//session sub-class functions
	var $page = "";					//page sub-class functions
	var $archive = "";				//archive sub-class functions
	var $admin = "";				//admin sub-class functions
	var $image = "";				//GDLib extension sub-class functions
	
	/*
	 * Initialise constant needed through out the kernel.
	 **/
	
	function init_system_environment()
	{
		
		/*
		 * Define system states
		 **/
		
		if( @function_exists( "ini_get" ) )
		{
			define( "FUNC_INI_GET", true );
			define( "SAFE_MODE", @ini_get( "safe_mode" ) ? true : false );
		}
		
		//Check cURL state
		define( "CURL_ENABLED", @function_exists( "curl_init" ) ? true : false );
		
		if( SAFE_MODE == false )
		{
			@set_time_limit( defined( SCRIPT_EXEC_LIMIT ) ? SCRIPT_EXEC_LIMIT : 0 );
		}
		
		//Disable various things for demo security purposes
		define( "IN_DEMO_MODE", false );
		
		/*
		 * Define system constants
		 **/
		
		define( "IP_ADDRESS", $_SERVER['REMOTE_ADDR'] );
		define( "HTTP_AGENT", $_SERVER['HTTP_USER_AGENT'] );
		define( "HTTP_HOST", $_SERVER['HTTP_HOST'] );
		define( "UNIX_TIME", time() );
		
		define( "DF_SHORT", $this->config['system_date_format_short'] );
		define( "DF_LONG", $this->config['system_date_format_long'] );
		
		define( "SCRIPT_NAME", $this->fetch_script_name() );
		//define( "SCRIPT_PATH", "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'] );
		define( "SCRIPT_PATH", sprintf( "http%s://%s%s", ( isset( $_SERVER['HTTPS'] ) AND $_SERVER['HTTPS'] == true ? "s" : "" ), $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) );
		define( "SCRIPT_REFERER", ( !empty( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : "" ) );
		
		define( "MAX_UPLOAD_SIZE", $this->fetch_max_upload_size() );
		
		define( "FULL_VERSION", $this->config['full_version'] );
		
		define( "SERVER_OS", strtoupper( substr( PHP_OS, 0, 3 ) ) );
		
		if( SERVER_OS == "WIN" )
		{
			$line_break = "\\r\\n";
			$line_break_char = chr( 13 ) . chr( 10 );
			$directory_seperator = "\\";
		}
		elseif( SERVER_OS == "MAC" )
		{
			$line_break = "\\r";
			$line_break_char = chr( 13 );
			$directory_seperator = "/";
		}
		else
		{
			$line_break = "\\n";
			$line_break_char = chr( 10 );
			$directory_seperator = "/";
		}
		
		define( "LINE_BREAK", $line_break );
		define( "LINE_BREAK_CHR", $line_break_char );
		define( "DIR_STEP", $directory_seperator );
		define( "SAPI_TYPE", php_sapi_name() );
		
		/*
		 * Define structure vars (need to clean this up, put somewhere tidy and logical)
		 **/
		
		$this->vars['page_struct'] = array();
		
		$this->vars['page_struct']['system_page_title'] = $this->config['archive_name'];
		$this->vars['page_struct']['system_page_action_title'] = "";
		$this->vars['page_struct']['system_page_announcement_id'] = 0;
		$this->vars['page_struct']['system_page_navigation_id'] = 0;
		$this->vars['page_struct']['system_page_navigation_html'] = array();
		$this->vars['page_struct']['system_jscript_commands'] = "";
		
		$this->vars['page_struct']['system_meta_keywords'] = $this->config['archive_meta_keywords'];
		$this->vars['page_struct']['system_meta_description'] = $this->config['archive_meta_description'];
		
		$this->vars['page_struct']['system_root_url'] = $this->config['system_root_url_path'];
		$this->vars['page_struct']['system_root_url_home'] = $this->config['system_root_url_home'];
		$this->vars['page_struct']['system_root_dir_upload'] = $this->config['system_root_dir_upload'];
		$this->vars['page_struct']['system_short_version'] = $this->config['short_version'];
		$this->vars['page_struct']['system_page_exec_time'] = 0;
		$this->vars['page_struct']['system_date_year'] = date( "Y", UNIX_TIME );
		
		$this->vars['page_struct']['total_rows'] = 0;
		
		/*
		 * Define html template vars (need to clean this up)
		 **/
		
		$this->vars['html']['system_announcements_data'] = "";
		$this->vars['html']['system_navigation_data'] = "";
		$this->vars['html']['system_session_data'] = "";
	}
	
	/*
	 * Clean $variables in $variable_group and store in $vars sub class
	 **/
	
	function clean_array( $variable_group, $variables )
	{
		if( !is_array( $variable_group ) )
		{
			$super_global = $GLOBALS[ "$variable_group" ];
		}
		else
		{
			$super_global =& $variable_group;
		}
		
		foreach( $variables AS $variable_name => $variable_type )
		{
			$this->vars[ "$variable_name" ] = $this->clean_input( $super_global[ "$variable_name" ], $variable_type, isset( $super_global[ "$variable_name" ] ) );
		}
	}
	
	/*
	 * Clean $variable_data based on $variable_type and return
	 **/
	
	function clean_input( $variable_data, $variable_type, $variable_exists = true )
	{
		if( $variable_exists )
		{
			switch( $variable_type )
			{
				//Array
				case V_ARRAY: $variable_data = ( is_array( $variable_data ) ) ? $variable_data : array(); break;
				
				//ASCI
				case V_BIN: $variable_data = strval( $variable_data ); break;
					
				//Bool
				case V_BOOL: $variable_data = ( $variable_data == true ) ? true : false; break;
					
				//Integer
				case V_INT: $variable_data = intval( $variable_data ); break;
					
				//MD5 Hash
				case V_MD5: $variable_data = md5( $variable_data ); break;
				
				//Positive Integer
				case V_PINT: $variable_data = ( intval( $variable_data ) < 0 ) ? 0 : intval( $variable_data ); break;
				
				//String
				case V_STR: $variable_data = $this->xss_clean( $variable_data, true ); break;
				
				//UNIX Time
				case V_TIME: $variable_data = substr( intval( $variable_data ), 0, 10 ); break;
				
				//Nothin'
				default: break;
				
				//return $variable_data;
			}
		}
		else
		{
			switch( $variable_type )
			{
				//Array
				case V_ARRAY: $variable_data = array(); break;
				
				//ASCI
				case V_BIN: $variable_data = ""; break;
						
				//Bool
				case V_BOOL: $variable_data = ( $variable_data == true ) ? true : false; break;
				
				//Integer
				case V_INT: $variable_data = 0; break;
						
				//MD5 Hash
				case V_MD5: $variable_data = md5( uniqid( mt_rand() ) ); break;
				
				//Positive Integer
				case V_PINT: $variable_data = 0; break;
				
				//String
				case V_STR: $variable_data = ""; break;
				
				//UNIX Time
				case V_TIME: $variable_data = UNIX_TIME; break;
				
				//Nothin'
				default: break;
			}
		}
		
		return $variable_data;
	}
	
	/*
	 * Format $variable_data based on $variable_type and return
	 **/
	
	function format_input( $variable_data, $variable_type )
	{
		global $kernel;
		
		switch( $variable_type )
		{
			//HTML clean #$this->page->postit_code#
			case T_HTML: $variable_data = nl2br( $this->strip_html( $variable_data, $this->config['system_allowed_html_tags'], false, true ) ); break;
			
			//HTML remove
			case T_NOHTML: $variable_data = $this->strip_html( $variable_data, $this->config['system_allowed_html_tags'], false, true, true ); break;
			
			//HTML remove
			case T_PREVIEW: $variable_data = $this->strip_html( $variable_data, $this->config['system_allowed_html_tags'], false, false ); break;
			
			//HTML <form> element clean
			case T_FORM: $variable_data = str_replace( array( '\\n', '\\r' ), array( chr( 10 ), chr( 13 ) ), $variable_data ); break;
			
			//Clean for database entry
			case T_DB: $variable_data = $this->xss_clean( $variable_data, false ); break;
			
			//Clean for database entry
			case T_RAW: $variable_data = $this->un_htmlspecialchars_new( $variable_data ); break;
			
			//Clean for database entry
			case T_STRIP: $variable_data = stripslashes( $variable_data ); break;
			
			//URL friendly
			case T_URL_ENC: $variable_data = urlencode( $variable_data ); break;
			
			//URL un-friendly
			case T_URL_DEC: $variable_data = urldecode( $variable_data ); break;
			
			//URL parsable for filesizes
			case T_PATH_PARSE:
			{
				if( strpos( $variable_data, $kernel->config['system_root_url_upload'] ) !== false )
				{
					$variable_data = str_replace( $kernel->config['system_root_url_upload'], $kernel->config['system_root_dir_upload'], $variable_data );
					if( SERVER_OS == "WIN" ) $variable_data = str_replace( "/", "\\", $variable_data );
				}
				else
				{
					$variable_data = str_replace( " ", "%20", $variable_data );
				}
				
				break;
			}
			
			//Format based on locale
			case T_NUM: $variable_data = number_format( $variable_data ); break;
			
			//Nothin'
			default: break;
		}
		
		return $variable_data;
	}
	
	/*
	 * Stripslashes recursivly on $array
	 **/
	
	function stripslashes_array( $array )
	{
		if( is_array( $array ) )
		{
			foreach( $array AS $array_key => $array_variable )
			{
				if( is_string( $array_variable ) )
				{
					$array[ "$array_key" ] = stripslashes( $array_variable );
				}
				elseif( is_array( $array_variable ) )
				{
					$array[ "$array_key" ] = $this->stripslashes_array( $array_variable ); 
				}
			}
		}
		
		return $array;
	}
	
	/*
	 * Very basic XSS cleanup
	 **/
	
	function xss_clean( $string, $block_all = false )
	{
		//Attached SQL queries
		if( strpos( strtolower( $string ), "union select" ) !== false )
		{
			$this->page->message_report( $this->ld['phrase_sql_injection_detected'], M_CRITICAL_ERROR, HALT_EXEC );
		}
		
		$string = $this->strip_html( $string, $this->config['system_allowed_html_tags'], $block_all );
		
		return $string;
	}
	
	/*
	 * Returns the name of the script.
	 **/
	
	function fetch_script_name()
	{
		$script_name = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_ENV['PHP_SELF'];
		
		preg_match( "/[^\/]+$/", $script_name, $matches );
		
		return $matches[0];
	}
	
	/*
	 * Returns the maximum available value for file uploads
	 **/
	
	function fetch_max_upload_size()
	{
		$config_values = array( @ini_get( "memory_limit" ), @ini_get( "post_max_size" ), @ini_get( "upload_max_filesize" ) );
		
		$delimit_groups = array( "K" => 1024, "M" => 1048576, "G" => 1073741824 );
		
		foreach( $config_values AS $key => $value )
		{
			if( is_numeric( $delimit = strtoupper( substr( $value, -1 ) ) ) ) continue;
			
			$config_values[ $key ] = ( substr( $value, 0, strlen( $value ) - 1 ) * $delimit_groups[ $delimit ] );
		}
		
		return min( $config_values );
	}
	
	/*
	 * INIT the DATABASE driver
	 **/
	
	function init_database_driver( $select_db = true )
	{
		if( empty( $this->config['db_driver'] ) OR $this->config['db_driver'] == "mysql_improved" AND PHP_VERSION < 5 )
		{
			$this->config['db_driver'] = "mysql_standard";
		}
		
		require_once( ROOT_PATH . "include" . DIR_STEP . "class_" . $this->config['db_driver'] . ".php" );
		
		$this->db = new class_database;
		
		$this->db->config['server']			= $this->config['db_server'];
		$this->db->config['username']		= $this->config['db_username'];
		$this->db->config['password']		= $this->config['db_password'];
		$this->db->config['table_prefix']	= $this->config['db_table_prefix'];
		$this->db->config['port']			= $this->config['db_port'];
		$this->db->config['database']		= $this->config['db_database'];
		
		define( "TABLE_PREFIX",			$this->config['db_table_prefix'] );
		
		$this->db->connect( $select_db );
	}
	
	/*
	 * INIT the TEMPLATE driver
	 **/
	
	function init_template_driver()
	{
		if( empty( $this->config['default_skin'] ) )
		{
			$this->config['default_skin'] = 1;
		}
		
		require_once( ROOT_PATH . "include" . DIR_STEP . "class_template.php" );
		
		$this->tp = new class_template;
	}
	
	/*
	 * Return time format
	 **/
	
	function fetch_time( $timestamp, $formatting )
	{
		return strftime( $formatting, $timestamp );
	}
	
	/*
	 * Replace $array_1 key values with $array_2 values
	 **/
	
	function array_set( $array_1, $array_2 )
	{
		foreach( $array_2 AS $value )
		{
			$array_1[ $value ] = $value;
		}
		
		return $array_1;
	}
	
	/*
	 * INIT the SESSION driver
	 **/
	
	function init_session_driver()
	{
		global $_SESSION, $_COOKIE;  //temp
		
		$usergroup = $this->db->data( "SELECT `usergroup_archive_permissions`, `usergroup_categories` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` = -1" );
		
		$this->session->vars['session_user_id'] = 0;
		$this->session->vars['session_group_id'] = -1;
		$this->session->vars['session_name'] = "";
		$this->session->vars['session_permissions'] = unserialize( $usergroup['usergroup_archive_permissions'] );
		$this->session->vars['session_categories'] = $usergroup['usergroup_categories'];
		
		
		if( $this->config['archive_allow_user_login'] == "true" )
		{
			if( empty( $_SESSION['phcdl_session_hash'] ) AND empty( $_COOKIE['phcdl_session_hash'] ) )
			{
				if( $this->config['session_force_login'] == 1 AND SCRIPT_NAME != "user.php" AND SCRIPT_NAME != "contact.php" )
				{
					$this->session->construct_user_login( $this->ld['phrase_no_session'] );
				}
			}
			else
			{
				$_SESSION['phcdl_session_hash'] = ( !empty( $_COOKIE['phcdl_session_hash'] ) ) ? $_COOKIE['phcdl_session_hash'] : $_SESSION['phcdl_session_hash'];
				$_SESSION['phcdl_user_id'] = ( !empty( $_COOKIE['phcdl_user_id'] ) ) ? $_COOKIE['phcdl_user_id'] : $_SESSION['phcdl_user_id'];
				$_SESSION['phcdl_user_password'] = ( !empty( $_COOKIE['phcdl_user_password'] ) ) ? $_COOKIE['phcdl_user_password'] : $_SESSION['phcdl_user_password'];
				
				$fetch_session = $this->db->query( "SELECT * FROM `" . TABLE_PREFIX . "sessions` WHERE `session_hash` = '" . $_SESSION['phcdl_session_hash'] . "' AND `session_user_id` = '" . $_SESSION['phcdl_user_id'] . "' AND `session_password` = '" . $_SESSION['phcdl_user_password'] . "'" );
				
				if( $this->db->numrows( $fetch_session ) == 0 )
				{
					$this->session->clear_user_cache();
					
					if( $this->config['session_force_login'] == 1 AND SCRIPT_NAME != "user.php" AND SCRIPT_NAME != "contact.php" )
					{
						$this->session->construct_user_login( $this->ld['phrase_no_session'] );
					}
				}
				else
				{
					$this->session->vars =& $this->db->row( $fetch_session );
					
					if( $this->session->vars['session_ip_address'] !== IP_ADDRESS AND $this->config['session_ip_check'] == 1 )
					{
						$this->session->construct_user_login( $this->ld['phrase_bad_ip_address'] );
					}
					
					if( $this->session->vars['session_ip_address'] !== HTTP_AGENT AND $this->config['session_http_agent_check'] == 1 )
					{
						$this->session->construct_user_login( $this->ld['phrase_bad_browser'] );
					}
					
					$fetch_user = $this->db->query( "SELECT u.user_group_id, g.usergroup_archive_permissions FROM " . TABLE_PREFIX . "users u LEFT JOIN " . TABLE_PREFIX . "usergroups g ON ( u.user_group_id = g.usergroup_id ) WHERE u.user_id = " . $this->session->vars['session_user_id'] . " AND u.user_name = '" . $this->session->vars['session_name'] . "' AND u.user_password = '" . $this->session->vars['session_password'] . "'" );
					
					if( $this->db->numrows( $fetch_user ) == 0 )
					{
						$this->session->construct_user_login( $this->ld['phrase_invalid_session'] );
					}
					else
					{
						$this->session->vars['session_permissions'] = unserialize( $this->session->vars['session_permissions'] );
						
						$this->db->update( "sessions", array( "session_run_timestamp" => UNIX_TIME ), "WHERE `session_hash` = '" . $this->session->vars['session_hash'] . "'" );
						
						define( "USER_LOGGED_IN", true );
						
						$this->db->update( "session_cache", array( "cache_timestamp" => UNIX_TIME, "cache_session_downloads" => 0, "cache_session_bandwidth" => 0 ), "WHERE `cache_timestamp` < ( " . UNIX_TIME . " - 86400 )" );
					}
				}
			}
		}
	}
	
	/*
	 * Load in language phrases
	 **/
	
	function fetch_language_phrases()
	{
		if( empty( $this->config['default_language'] ) )
		{
			$this->config['default_language'] = "English_ISO-8859-1";
		}
		
		if( !empty( $this->config['system_allowed_html_tags'] ) )
		{
			define( "ALLOWED_HTML_TAGS", htmlentities( str_replace( '><', ', ', strtoupper( substr( $this->config['system_allowed_html_tags'], 1, strlen( $this->config['system_allowed_html_tags'] ) - 2 ) ) ) ) );
		}
		
		require_once( ROOT_PATH . "lang" . DIR_STEP . $this->config['default_language']. "/phrase_global.php" );
		
		$this->ld =& $ld;
		
		if( defined( "IN_ACP" ) )
		{
			require_once( ROOT_PATH . "lang" . DIR_STEP . $this->config['default_language']. "/phrase_panel.php" );
		}
		else
		{
			require_once( ROOT_PATH . "lang" . DIR_STEP . $this->config['default_language']. "/phrase_archive.php" );
		}
		
		$this->ld =& $ld;
		
		if( isset( $this->ld['lang_var_locale'] ) OR isset( $this->ld['lang_var_language'] ) )
		{
			@setlocale( LC_ALL, $this->ld['lang_var_locale'] ? $this->ld['lang_var_locale'] : $this->ld['lang_var_language'] );
		}
	}
	
	/*
	 * Format seconds into Days, Hours, Minutes, Seconds
	 **/
	
	function format_seconds( $total_seconds = 0 )
	{
		global $kernel;
		
		if( $total_seconds > 0 )
		{
			$printstring = "";
			$remaning_seconds = $total_seconds;
			
			//days
			$count = 0;
			while( $remaning_seconds >= 86400 )
			{
				$remaning_seconds -= 86400;
				$count++;
			}
			$days = ( $count > 0 ) ? floor( $count ) : 0;
			
			//hours
			$count = 0;
			while( $remaning_seconds >= 3600 )
			{
				$remaning_seconds -= 3600;
				$count++;
			}
			$hours = ( $count > 0 ) ? floor( $count ) : 0;
			
			//minutes
			$count = 0;
			while( $remaning_seconds >= 60 )
			{
				$remaning_seconds -= 60;
				$count++;
			}
			$minutes = ( $count > 0 ) ? floor( $count ) : 0;
			
			//seconds
			$seconds = floor( $remaning_seconds );
			
			//Write the stringy
			if( $days != 0 )
			{
				$printstring .= ( $days == 1 ) ? sprintf( $kernel->ld['phrase_date_day'], $days ) : sprintf( $kernel->ld['phrase_date_days'], $days );
			}
				
			if( $hours != 0 )
			{
				$printstring .= ( $hours == 1 ) ? sprintf( $kernel->ld['phrase_date_hour'], $hours ) : sprintf( $kernel->ld['phrase_date_hours'], $hours );
			}

			if( $minutes != 0 )
			{
				$printstring .= ( $minutes == 1 ) ? sprintf( $kernel->ld['phrase_date_minute'], $minutes ) : sprintf( $kernel->ld['phrase_date_minutes'], $minutes );
			}

			if( $seconds != 0 )
			{
				$printstring .= ( $seconds == 1 ) ? sprintf( $kernel->ld['phrase_date_second'], $seconds ) : sprintf( $kernel->ld['phrase_date_seconds'], $seconds );
			}
		
			return $printstring;
		}
		else
		{
			return sprintf( $kernel->ld['phrase_date_seconds'], $total_seconds );
		}
	}
	
	/*
	 * Check external URL
	 **/
	
	function url_exists( $url = false )
	{
		global $kernel;
		
		if( $kernel->config['archive_file_check_url'] == "false" )
		{
			return true;
		}
		
		$url = str_replace( " ", "%20", $url );
		
		if( CURL_ENABLED == true )
		{
			$handle = curl_init( $url );
			
			curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $kernel->config['system_parse_timeout'] );
			curl_setopt( $handle, CURLOPT_HEADER, 1 );
			curl_setopt( $handle, CURLOPT_FAILONERROR, 1 );
			curl_setopt( $handle, CURLOPT_NOBODY, 1 );
			curl_setopt( $handle, CURLOPT_RETURNTRANSFER, 1 );
			
			if( !empty( $kernel->config['upload_dir_http_username'] ) AND strpos( $url, $kernel->config['system_root_url_upload'] ) !== false )
			{
				curl_setopt( $handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
				curl_setopt( $handle, CURLOPT_USERPWD, $kernel->config['upload_dir_http_username'] . ':' . $kernel->config['upload_dir_http_password'] );
			}
			
			$result = curl_exec( $handle );
			$error = curl_errno( $handle );
			
			curl_close( $handle );
			
			return ( $result AND $error == 0 ) ? true : false;
		}
		else
		{
			$headers = array( "http" => array( "header" => "" ) );
			
			if( !empty( $kernel->config['upload_dir_http_username'] ) AND strpos( $url, $kernel->config['system_root_url_upload'] ) !== false )
			{
				$authenicate_string = base64_encode( $kernel->config['upload_dir_http_username'] . ":" . $kernel->config['upload_dir_http_password'] );
				
				$headers['http']['header'] .= "Authorization: Basic " . $authenicate_string . "\r\n";
			}
			
			if( PHP_VERSION >= 5.1 )
			{
				$context = ( $authenicate_string ) ? stream_context_create( $headers ) : null;
				
				$result = @file_get_contents( $url, false, $context, 0, 50 );
				
				return ( $result ) ? true : false;
			}
			elseif( PHP_VERSION >= 5.0 AND function_exists( "stream_context_create" ) )
			{
				$headers['http']['header'] .= "Range: bytes=0-50\r\n";
				
				$result = @file_get_contents( $url, false, stream_context_create( $headers ) );
				
				return ( $result ) ? true : false;
			}
			else
			{
				$parse = parse_url( $url );
				
				$host = $parse['host'];
				$path = $parse['path'];
				$port = empty( $parse['port'] ) ? 80 : $parse['port'];
				
				$headers = "HEAD " . $path . " HTTP/1.1\r\n";
				$headers .= "Host: " . HTTP_HOST . "\r\n";
				
				if( !empty( $kernel->config['upload_dir_http_username'] ) AND strpos( $url, $kernel->config['system_root_url_upload'] ) !== false )
				{
					$headers .= "Authorization: Basic " . $authenicate_string . "\r\n";
				}
				
				$headers .= "Connection: Close\r\n";
				
				if( !$fp = fsockopen( $host, $port, $errno, $errstr, $kernel->config['system_parse_timeout'] ) ) 
				{
					return false;
				}
				else
				{
					fwrite( $fp, $headers . "\r\n" );
					
					$result = fgets( $fp, 128 );
				}
				
				fclose( $fp );
				
				return ( strpos( $result, '200 OK' ) ) ? true : false;
			}
		}
	}
	
	/*
	 * Produce random chars based on available chars and length
	 **/
	
	function generate_random_code()
	{
		global $kernel;
		
		$key_array = $kernel->config['GD_CHAR_ARRAY'];
		$string = "";
		$key_length = strlen( $key_array ) - 1;
		
		srand( ( double ) microtime() * 1000000 );
		
		for( $key = 0; $key < $kernel->config['GD_CHAR_LENGTH']; $key++ )
		{
			$string .= $key_array{ mt_rand( 0, $key_length ) };
		}
		
		return $string;
	}
	
	/*
	 * Check if string is all numbers and no letters.
	 **/
	
	function iis_numeric( $string )
	{
		return ( preg_match( "/^[+-]?[0-9]+$/", $string ) ) ? true : false;
	}
	
	/*
	 * Basically the same as htmlspecialchars()... except it won't kill encoded chars with an ampersand conversion
	 **/
	
	function htmlspecialchars_new( $string )
	{
		return str_replace( array( '&', '<', '>', '"', '\'', '&amp;#' ), array( '&amp;', '&lt;', '&gt;', '&quot;', '&#039;', '&#' ), $string );
	}
	
	/*
	 * It is the reversed function of htmlspecialchars_new
	 **/
	
	function un_htmlspecialchars_new( $string )
	{
		return str_replace( array( '&amp;', '&lt;', '&gt;', '&quot;', '&#039;' ), array( '&', '<', '>', '"', '\'' ), $string );
	}
	
	/*
	 * Clean all HTML, then convert back the accepted HTML tags.
	 **/
	
	function strip_html( $string, $allowed_tags = "", $block_all = false, $enc_reverse = false, $hide_html = false )
	{
		global $kernel;
		
		if( $block_all == false )
		{
			$tags = explode( "><", substr( $allowed_tags, 1, strlen( $allowed_tags ) - 2 ) );
			
			if( $enc_reverse == true )
			{
				$string = str_replace( array( '&lt;', '&gt;', '&quot;' ), array( '<', '>', '"' ), $string );
			}
			
			preg_match_all( "/<\/?\w+((\s+\w+(\s*=\s*(?:\".*?\\\"|.*?|[^\">\s]+))?)+\s*|\s*)\/?>/s", $string, $matches );
			
			if( count( $matches[0] ) > 0 )
			{
				$string = $this->htmlspecialchars_new( $string );
				
				foreach( $matches[0] AS $index => $code )
				{
					$tag_match = 0;
					
					$code_stripped = str_replace( array( " ", "%20" ), "", $code );
					
					foreach( $tags AS $tag )
					{
						if( strpos( $code, "<" . $tag . " " ) !== false OR strpos( $code_stripped, "<" . $tag . ">" ) !== false OR strpos( $code_stripped, "</" . $tag . ">" ) !== false OR strpos( $code_stripped, "<" . $tag . "/>" ) !== false )
						{
							$tag_match = 1;
						}
					}
					
					if( $tag_match == 1 )
					{
						if( $hide_html == false )
						{
							$string = str_replace( $this->htmlspecialchars_new( $code ), $code, $string );
						}
						else
						{
							$string = str_replace( $this->htmlspecialchars_new( $code ), "", $string );
						}
					}
				}
			}
		}
		else
		{
			$string = $this->htmlspecialchars_new( $string );
		}
		
		$string = str_replace( array( '<?', '?>' ), array( '&lt;?', '?&gt;' ), $string );
		
		return $string;
	}
}

?>