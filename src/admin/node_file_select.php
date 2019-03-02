<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->clean_array( "_REQUEST", array( "directory" => V_STR ) );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_file_select" );
		
		//Get files
		$kernel->vars['html']['directory_list_options'] = "<option value=\"\">" . DIR_STEP . " " . $kernel->ld['phrase_upload_dir_root'] . "</option>\r\n";
		
		//build upload directory tree
		$kernel->admin->read_directory_tree( $kernel->config['system_root_dir_upload'], $kernel->vars['directory'], 0 );
		
		//read current folder
		$kernel->admin->read_upload_directory_index( $kernel->config['system_root_dir_upload'] . $kernel->vars['directory'], 1 );
		
		$kernel->tp->cache( "directory", $kernel->vars['directory'] );
		$kernel->tp->cache( "system_root_dir_upload", $kernel->config['system_root_dir_upload'] );
	}
}

?>

