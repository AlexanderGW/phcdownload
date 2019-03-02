<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "id" => V_INT, "file_id" => V_INT, "forced" => V_INT ) );

//----------------------------------
// No ID ref
//----------------------------------

if( $kernel->vars['file_id'] == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_file_specified'], M_ERROR );
}
else
{
	//----------------------------------
	// Read session cache info
	//----------------------------------
	
	if( !empty( $kernel->session->vars['session_hash'] ) AND USER_LOGGED_IN == true )
	{
		$usergroupdata = $kernel->db->row( "SELECT g.usergroup_session_downloads, g.usergroup_session_baud FROM " . TABLE_PREFIX . "usergroups g LEFT JOIN " . TABLE_PREFIX . "users u ON ( g.usergroup_id = u.user_group_id ) WHERE u.user_id = '" . $kernel->session->vars['session_user_id'] . "'" );
		
		$cachedata = $kernel->db->row( "SELECT c.cache_user_id, c.cache_timestamp, c.cache_session_downloads, c.cache_session_bandwidth FROM " . TABLE_PREFIX . "session_cache c WHERE c.cache_session_hash = '" . $kernel->session->vars['session_hash'] . "' AND c.cache_user_id = '" . $kernel->session->vars['session_user_id'] . "'" );
	}
	
	//----------------------------------
	// No mirrors, direct to download
	//----------------------------------
	
	if( $kernel->db->numrows( "SELECT `mirror_id` FROM `" . TABLE_PREFIX . "mirrors` WHERE `mirror_file_id` = " . $kernel->vars['file_id'] ) == 0 )
	{
		header( "Location: download.php?id=" . $kernel->vars['file_id'] ); exit;
	}
	
	//----------------------------------
	// Fetch file details
	//----------------------------------
	
	$file = $kernel->db->row( "SELECT f.file_id, f.file_name, f.file_cat_id, f.file_downloads, f.file_dl_limit, f.file_doc_id, f.file_size, f.file_disabled, f.file_hash_data FROM " . TABLE_PREFIX . "files f WHERE f.file_id = " . 	$kernel->vars['file_id'] . " LIMIT 1" );
	
	//----------------------------------
	// Set page vars
	//----------------------------------
	
	$kernel->vars['page_struct']['system_page_navigation_id'] = $file['file_cat_id'];
	$kernel->vars['page_struct']['system_page_navigation_html'] = array( "file.php?id=" . $kernel->vars['file_id'] . "" => $file['file_name'], SCRIPT_PATH => $kernel->ld['phrase_downloading_file_mirror'] );
	$kernel->vars['page_struct']['system_page_action_title'] = sprintf( $kernel->ld['phrase_page_title_mirror'], $file['file_name'] );
	
	//----------------------------------
	// Check category permissions
	//----------------------------------
	
	$kernel->page->read_category_permissions( $file['file_cat_id'], SCRIPT_PATH );
	
	//----------------------------------
	// Check for subscriptions
	//----------------------------------
	
	$kernel->subscription->init_category_subscriptions( $file['file_cat_id'], SCRIPT_PATH );
	
	//----------------------------------
	// File disabled
	//----------------------------------
	
	if( $kernel->archive->synchronise_file_status() == false )
	{
		$kernel->page->message_report( $kernel->ld['phrase_file_status_disabled'], M_ERROR, HALT_EXEC );
	}
	
	//----------------------------------
	// Mirror ID ref supplied
	//----------------------------------
	
	if( $kernel->vars['id'] > 0 )
	{
		if( $kernel->db->numrows( "SELECT `mirror_file_url` FROM `" . TABLE_PREFIX . "mirrors` WHERE `mirror_id` = " . $kernel->vars['id'] ) == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_mirror'], M_ERROR, HALT_EXEC );
		}
		
		//----------------------------------
		// Download limit reached for the user
		//----------------------------------
		
		if( $cachedata['cache_session_downloads'] >= $usergroupdata['usergroup_session_downloads'] AND $usergroupdata['usergroup_session_downloads'] != 0 )
		{
			$kernel->page->message_report( sprintf( $kernel->ld['phrase_download_limit_reached'], $kernel->format_seconds( ( $cachedata['cache_timestamp'] + 86400 ) - UNIX_TIME ) ), M_WARNING, HALT_EXEC );
		}
		
		//----------------------------------
		// User has permission to download?
		//----------------------------------
		
		if( $kernel->session->read_permission_flag( 'FIL_DWN', true ) == true )
		{
			if( $file['file_dl_limit'] > 0 AND $file['file_downloads'] >= $file['file_dl_limit'] )
			{
				$kernel->page->message_report( $kernel->ld['phrase_download_limit'], M_ERROR, HALT_EXEC );
			}
			
			//----------------------------------
			// Check for leeching
			//----------------------------------
			
			$kernel->archive->verify_leech_status();
			
			$category_doc_id = $kernel->db->item( "SELECT `category_doc_id` FROM " . TABLE_PREFIX . "categories WHERE category_id = " . $file['file_cat_id'] );
			
			//----------------------------------
			// File document exists, has not been agreed to
			//----------------------------------
			
			if( !isset( $_SESSION['document_agree'] ) AND $file['file_doc_id'] > 0 )
			{
				header( "Location: document.php?id=" . $file['file_doc_id'] . "&file_id=" . $kernel->vars['file_id'] . "&mirror_id=" . $kernel->vars['id'] ); exit;
			}
			
			//----------------------------------
			// Category document exists, has not been agreed to
			//----------------------------------
			
			if( !isset( $_SESSION['document_agree'] ) AND $category_doc_id > 0 )
			{
				header( "Location: document.php?id=" . $category['category_doc_id'] . "&file_id=" . $kernel->vars['file_id'] . "&mirror_id=" . $kernel->vars['id'] ); exit;
			}
			
			//----------------------------------
			// Fetch mirror details
			//----------------------------------
			
			$mirror = $kernel->db->row( "SELECT `mirror_name`, `mirror_file_url` FROM `" . TABLE_PREFIX . "mirrors` WHERE `mirror_id` = " . $kernel->vars['id'] );
			
			//----------------------------------
			// Increment download counter
			//----------------------------------
			
			$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "files` SET `file_downloads` = `file_downloads` + 1 WHERE `file_id` = " . $kernel->vars['file_id'] );
			
			//----------------------------------
			// Add to download log
			//----------------------------------
			
			$kernel->db->insert( "logs", array( "log_type" => 1, "log_file_id" => $kernel->vars['file_id'], "log_user_id" => $kernel->session->vars['session_user_id'], "log_mirror_id" => $kernel->vars['id'], "log_user_agent" => HTTP_AGENT, "log_timestamp" => UNIX_TIME, "log_ip_address" => IP_ADDRESS ) );
			
			//----------------------------------
			// Update session cache info
			//----------------------------------
			
			if( !empty( $kernel->session->vars['session_hash'] ) AND USER_LOGGED_IN == true )
			{
				$kernel->db->update( "session_cache", array( "cache_session_downloads" => $cachedata['cache_session_downloads'] + 1, "cache_session_bandwidth" => $cachedata['cache_session_bandwidth'] + $file['file_size'] ), "WHERE `cache_user_id` = '" . $kernel->session->vars['session_user_id'] . "'" );
				
				$user = $kernel->db->row( "SELECT `user_downloads`, `user_bandwidth` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = '" . $kernel->session->vars['session_user_id'] . "'" );
				
				$kernel->db->update( "users", array( "user_downloads" => $user['user_downloads'] + 1, "user_bandwidth" => $user['user_bandwidth'] + $file['file_size'] ), "WHERE `user_id` = '" . $kernel->session->vars['session_user_id'] . "'" );
			}
			
			unset( $HTTP_SESSION_VARS['document_agree'] );
			
			//----------------------------------
			// Start file download
			//----------------------------------
			
			$kernel->archive->exec_file_download( array( "name" => $file['file_name'] . "[ " . $mirror['mirror_name'] . " ]", "url" => $mirror['mirror_file_url'], "timestamp" => $file['file_timestamp'], "size" => $file['file_size'], "id" => $kernel->vars['file_id'], "miror_id" => $kernel->vars['id'], "hash" => $file['file_hash_data'] ), $usergroupdata['usergroup_session_baud'] );
		}
	}
	else
	{
		$check_mirrors = $kernel->db->query( "SELECT `mirror_id`, `mirror_file_id`, `mirror_name` FROM `" . TABLE_PREFIX . "mirrors` WHERE `mirror_file_id` = " . $kernel->vars['file_id'] . " ORDER BY `mirror_order`, `mirror_name`" );
		
		$kernel->tp->call( "file_mirror_box" );
		
		$kernel->tp->cache( "mirror_name", $kernel->ld['phrase_direct_file_link'] );
		$kernel->tp->cache( "mirror_url", "download.php?id=" . $kernel->vars['file_id'] . "&forced=1" );
		
		while( $mirror = $kernel->db->data( $check_mirrors ) )
		{
			$kernel->tp->call( "file_mirror_box" );
			
			$mirror['mirror_url'] = "mirror.php?id=" . $mirror['mirror_id'] . "&file_id=" . $mirror['mirror_file_id'];
			$mirror['mirror_file_name'] = $file['file_name'];
			
			$kernel->tp->cache( $mirror );
		}
	}
}

//----------------------------------
// Output page
//----------------------------------

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>