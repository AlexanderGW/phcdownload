<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

error_reporting( E_ALL &~ E_NOTICE );

define( "ROOT_PATH", dirname( __FILE__ ) . "/" );

//----------------------------------
// Start Kernel
//----------------------------------

require( ROOT_PATH . "include/class_kernel.php" );
$kernel = new class_kernel;

//----------------------------------
// Load configuration settings
//----------------------------------

require( ROOT_PATH . "include/config.ini.php" );
$kernel->config =& $config;

//----------------------------------
// Load filetypes
//----------------------------------

require( ROOT_PATH . "include/filetype.ini.php" );
$kernel->filetype =& $filetypes;

//----------------------------------
// Setup constants etc
//----------------------------------

$kernel->init_system_environment();

//----------------------------------
// Start database
//----------------------------------

$kernel->init_database_driver();

//----------------------------------
// Start template engine
//----------------------------------

$kernel->init_template_driver();

//----------------------------------
// Load language phrases
//----------------------------------

$kernel->fetch_language_phrases();

//----------------------------------
// Apply compression
//----------------------------------

if( $kernel->config['gzip_enabled'] == 1 AND SCRIPT_NAME !== "download.php" AND SCRIPT_NAME !== "mirror.php" )
{
	@ob_start( "ob_gzhandler" );
}

//----------------------------------
// Escape vars
//----------------------------------

if( get_magic_quotes_gpc() == 1 )
{
	if( is_array( $_REQUEST ) AND sizeof( $_REQUEST ) > 0 ) $_REQUEST = $kernel->stripslashes_array( $_REQUEST );
	if( is_array( $_POST ) AND sizeof( $_POST ) > 0 ) $_POST = $kernel->stripslashes_array( $_POST );
	if( is_array( $_GET ) AND sizeof( $_GET ) > 0 ) $_GET = $kernel->stripslashes_array( $_GET );
	if( is_array( $_FILES ) AND sizeof( $_FILES ) > 0 ) $_FILES = $kernel->stripslashes_array( $_FILES );
}

set_magic_quotes_runtime( 0 );

//----------------------------------
// Load classes
//----------------------------------

require_once( ROOT_PATH . "include" . DIR_STEP . "class_subscription.php" );
$kernel->subscription = new class_subscription;

if( ( SCRIPT_NAME == "subscription.php" OR ( defined( "IN_ACP" ) AND $_REQUEST['node'] == "subs_pay_gateway" ) ) AND !empty( $_REQUEST['gateway'] ) AND !empty( $_REQUEST['action'] ) )
{
	require_once( ROOT_PATH . "include" . DIR_STEP . "gateway" . DIR_STEP . "class_gateway_" . $_REQUEST['gateway'] . ".php" );
	$kernel->subscription = new class_gateway;
	
	$kernel->subscription->setup_variables();
}

if( SCRIPT_NAME == "graph.php" AND extension_loaded( "gd" ) )
{
	require( ROOT_PATH . "include" . DIR_STEP . "class_graph.php" );
	$kernel->graph = new class_graph;
}

//----------------------------------
// TODO: At some point i'll sort out calling class functions on the pages they are needed..
//----------------------------------

require_once( ROOT_PATH . "include" . DIR_STEP . "function_class_page.php" );
$kernel->page = new class_page_function;

require_once( ROOT_PATH . "include" . DIR_STEP . "function_class_session.php" );
$kernel->session = new class_session_function;

require_once( ROOT_PATH . "include" . DIR_STEP . "function_class_archive.php" );
$kernel->archive = new class_archive_function;

require_once( ROOT_PATH . "include" . DIR_STEP . "function_class_image.php" );
$kernel->image = new class_image_function;

$microtime = explode( " ", microtime() );
define( "PAGE_EXEC_START", $microtime[1] + $microtime[0] );

if( defined( "IN_ACP" ) )
{
	require_once( ROOT_PATH . "include" . DIR_STEP . "function_class_admin.php" );
	$kernel->admin = new class_admin_function;
}
else
{
	session_start();
	
	$kernel->init_session_driver();
	
	if( $kernel->config['archive_offline'] == 1 )
	{
		if( $kernel->session->vars['session_group_id'] <> 1 AND SCRIPT_NAME !== "user.php" AND SCRIPT_NAME !== "contact.php" )
		{
			$kernel->page->message_report( $kernel->config['archive_offline_message'], M_MAINTENANCE );
		}
	}
}

//----------------------------------
// Recount views and downloads after interval
//----------------------------------

if( $kernel->db->numrows( "SELECT `datastore_timestamp` FROM `" . TABLE_PREFIX . "datastore` WHERE `datastore_key` = 'archive_file_sync' AND `datastore_timestamp` < ( " . UNIX_TIME . " - 3600 ) LIMIT 1" ) == 1 )
{
	$kernel->archive->update_database_counter( "views" );
	$kernel->archive->update_database_counter( "downloads" );
	
	$kernel->db->update( "datastore", array( "datastore_timestamp" => UNIX_TIME ), "WHERE `datastore_key` = 'archive_file_sync'" );
}

?>