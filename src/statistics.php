<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->vars['page_struct']['system_page_action_title'] = $kernel->ld['phrase_page_title_statistics'];

// No categories in archive
if( $kernel->db->numrows( "SELECT `category_id` FROM `" . TABLE_PREFIX . "categories`" ) == 0 )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_categories'], M_NOTICE );
}
else
{
	$kernel->tp->call( "page_statistics" );
	
	$kernel->vars['stats'] = array();
	
	// Archive Highs
	$kernel->vars['stats'] = $kernel->vars['stats'] + $kernel->db->row( "SELECT `file_id` AS `latest_file_id`, `file_name` AS `latest_file_name`, `file_timestamp` AS `latest_file_timestamp` FROM `" . TABLE_PREFIX . "files` ORDER BY `file_id` DESC LIMIT 1" );
	$kernel->vars['stats']['latest_file_timestamp'] = $kernel->fetch_time( $kernel->vars['stats']['latest_file_timestamp'], DF_SHORT );
	
	$kernel->vars['stats'] = $kernel->vars['stats'] + $kernel->db->row( "SELECT `file_id` AS `mostdl_file_id`, `file_name` AS `mostdl_file_name`, `file_downloads` AS `mostdl_file_downloads` FROM `" . TABLE_PREFIX . "files` ORDER BY `file_downloads` DESC LIMIT 1" );
	$kernel->vars['stats']['mostdl_file_downloads'] = $kernel->vars['stats']['mostdl_file_downloads'];
	
	$kernel->vars['stats'] = $kernel->vars['stats'] + $kernel->db->row( "SELECT `file_id` AS `leastdl_file_id`, `file_name` AS `leastdl_file_name`, `file_downloads` AS `leastdl_file_downloads` FROM `" . TABLE_PREFIX . "files` ORDER BY `file_downloads` ASC LIMIT 1" );
	$kernel->vars['stats']['leastdl_file_downloads'] = $kernel->vars['stats']['leastdl_file_downloads'];
	
	$kernel->vars['stats'] = $kernel->vars['stats'] + $kernel->db->row( "SELECT `file_id` AS `highrate_file_id`, `file_name` AS `highrate_file_name`, `file_rating` AS `highrate_file_rating`, `file_votes` AS `highrate_file_votes` FROM `" . TABLE_PREFIX . "files` ORDER BY `file_rating` DESC, `file_votes` DESC LIMIT 1" );
	$kernel->vars['stats']['highrate_file_rank'] = $kernel->archive->construct_file_rating( $kernel->vars['stats']['highrate_file_rating'], $kernel->vars['stats']['highrate_file_votes'] );
	
	// Datastore counters
	$datastore_keys = array( "total_announcements", "total_files", "total_categories", "total_users", "total_downloads", "total_votes", "total_images", "total_comments" );
	
	foreach( $datastore_keys AS $counter )
	{
		$kernel->vars['stats'][ "$counter" ] = $kernel->format_input( $kernel->db->item( "SELECT `datastore_value` FROM `" . TABLE_PREFIX . "datastore` WHERE `datastore_key` = 'count_" . $counter . "'" ), T_NUM );
	}
	
	$kernel->ld['phrase_statistics_message'] = sprintf( $kernel->ld['phrase_statistics_message'], $kernel->fetch_time( $kernel->config['archive_start'], DF_SHORT ), $kernel->archive->format_round_bytes( $kernel->db->item( "SELECT `datastore_value` FROM `" . TABLE_PREFIX . "datastore` WHERE `datastore_key` = 'count_total_data'" ) ) );
	
	$kernel->tp->cache( $kernel->vars['stats'] );
}

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>