<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

header( "Content-Type: application/xml; charset=" . $kernel->ld['lang_var_charset'] );

echo "<" . "?xml version=\"1.0\" encoding=\"" . $kernel->ld['lang_var_charset'] . "\" ?" . ">";

################################################################################
# Permission to view feeds?
################################################################################

if( $kernel->session->read_permission_flag( 'RSS_VEW' ) == true )
{
	$kernel->clean_array( "_REQUEST", array( "limit" => V_PINT, "feed" => V_STR ) );
	
	if( $kernel->vars['limit'] == 0 OR $kernel->vars['limit'] > 100 ) $kernel->vars['limit'] = $kernel->config['display_default_limit'];
	if( empty( $kernel->vars['feed'] ) ) $kernel->vars['feed'] = "file_new";
	
	$feed_type = array(
		"file_new"		=> array( "`file_timestamp`", "`file_id`" ),
		"file_updated"	=> array( "`file_timestamp`", "`file_id`" ),
		"top_download"	=> array( "`file_downloads`", "`file_downloads`" ),
		"top_rank"		=> array( "( file_rating / file_votes ) AS `file_rank`", "`file_rank`" ),
	);
	
	$kernel->tp->call( "rss_header" );
	
	$rssdata = array(
		"rss_title" => $kernel->format_input( sprintf( $kernel->ld['phrase_rss_title_' . $kernel->vars['feed'] ], $kernel->vars['limit'] ), T_PREVIEW ),
		"rss_description" => $kernel->format_input( $kernel->ld['phrase_rss_desc_' . $kernel->vars['feed'] ], T_PREVIEW ),
		"rss_link" => $kernel->config['system_root_url_path'] . "/index.php",
	);
	
	$kernel->tp->cache( $rssdata );
	
	$fetch_file_data = $kernel->db->query( "SELECT `file_id`, `file_name`, `file_description`, `file_timestamp`, `file_mark_timestamp` " . $feed_type[ $kernel->vars['feed'] ][0] . " FROM `" . TABLE_PREFIX . "files` ORDER BY " . $feed_type[ $kernel->vars['feed'] ][1] . " DESC LIMIT " . $kernel->vars['limit'] );
	
	if( $kernel->db->numrows() > 0 )
	{
		while( $file = $kernel->db->data( $fetch_file_data ) )
		{
			$kernel->tp->call( "rss_item" );
			
			$file['file_name'] = $kernel->format_input( $file['file_name'], T_PREVIEW );
			$file['file_description'] = ( !empty( $file['file_description'] ) ) ? $kernel->archive->return_string_words( $kernel->format_input( $file['file_description'], T_NOHTML ), $kernel->config['string_max_length'] ) : '';
			$file['file_view_url'] = $kernel->config['system_root_url_path'] . "/file.php?id=" . $file['file_id'];
			
			$kernel->tp->cache( $file );
		}
	}
	
	$kernel->tp->call( "rss_footer" );
}

################################################################################
# No permission
################################################################################

else
{
	$kernel->tp->call( "rss_empty_feed" );
}

$kernel->tp->dump();

?>