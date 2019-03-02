<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( -1 );

switch( $kernel->vars['action'] )
{

	#############################################################################
	
	case "write" :
	{
		$kernel->admin->write_filetype_ini();
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->page->message_report( $kernel->ld['phrase_filetypes_mime_tip'], M_NOTICE );
		
		$kernel->tp->call( "admin_filetype_header" );
		
		foreach( $kernel->filetype AS $key => $value )
		{
			$kernel->tp->call( "admin_filetype_row" );
			
			$kernel->tp->cache( array( "filetype_name" => $key, "filetype_image" => $value[0], "filetype_mime" => $value[1] ) );
		}
		
		$handle = opendir( $kernel->config['system_root_dir'] . DIR_STEP . "images" . DIR_STEP . "filetype" );
		
		$image_list = "";
		
		while( ( $item = readdir( $handle ) ) !== false )
		{
			if( $item == "." OR $item == ".." OR $item == "index.html" ) continue;
			
			$image_list .= "<div style=\"display: inline;text-align: left; margin: 10px 10px 0px 10px;\"><img style=\"margin: 10px 0px 10px 10px; vertical-align: middle;\" src=\"../images/icons/" . $item . "\" border=\"0\">&nbsp;" . $item . "</div>\r\n";
		}
		
		closedir( $handle );
		
		$kernel->tp->call( "admin_filetype_footer" );
		
		$kernel->tp->cache( "image_list_options", $image_list );
		
		break;
	}
}

?>

