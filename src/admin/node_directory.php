<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	default :
	{
		$padding = 0;
		$directory = $kernel->config['system_root_dir_upload'];
		$handle = opendir( $directory );
		$html = "";
		
		while( ( $item = @readdir( $handle ) ) !== false )
		{
			if( $item == "." OR $item == ".." ) continue;
			
			if( @chdir( $directory . DIR_STEP . $item ) )
			{
				$directories[] = $item;
			}
			else
			{
				$files[] = $item;
			}
		}
		
		closedir( $handle );
		
		$kernel->tp->call( "admin_directory" );
		
		if( count( $files ) > 0 )
		{
			@asort( $files );
			
			foreach( $files AS $file )
			{
				$item = array(
					"directory_file_name" => $file,
					"directory_file_size" => $kernel->archive->format_round_bytes( filesize( $directory . DIR_STEP . $file ) ),
					"directory_file_modify_time" => $kernel->fetch_time( filemtime( $directory . DIR_STEP . $file ), DF_SHORT )
				);
				
				$html .= $kernel->tp->call( "admin_directory_file", CALL_TO_PAGE );
				
				$html = $kernel->tp->cache( $item, 0, $html );
			}
		}
		else
		{
			$html = $kernel->tp->call( "admin_directory_empty", CALL_TO_PAGE );
		}
		
		$item = array(
			"directory_name" => "(Root)",
			"directory_offset" => $padding,
			"directory_path" => $directory,
			"directory_files" => $html,
			"directory_total_files" => sprintf( $kernel->ld['phrase_x_files'], ( ( ( count( $files ) > 0 ) ) ? count( $files ) : 0 ) )
		);
		
		$kernel->tp->cache( $item );
		
		if( count( $directories ) > 0 )
		{
			@asort( $directories );
			
			foreach( $directories AS $dir )
			{
				$kernel->admin->read_upload_directory_html_index( $dir, $directory . DIR_STEP );
			}
		}
			
		break;
	}
}

?>

