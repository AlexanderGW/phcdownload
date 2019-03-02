<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

die( 'This script is currently not used.' );

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "node" => V_STR, "cache_key" => V_STR, "last_bytes" => V_INT, "action" => V_STR ) );

switch( $kernel->vars['node'] )
{
	#############################################################################
	
	case 'progress' :
	{
		if( isset( $kernel->vars['cache_key'] ) )
		{
			$cache_prefix = ( ( $value = @ini_get( "apc.rfc1867_prefix" ) ) !== false ) ? $value : 'upload_';
			
			$status = apc_fetch( $cache_prefix . $kernel->vars['cache_key'] );
			
			$state_flag = ( ( $status['current'] == $kernel->vars['last_bytes'] ) ? '1' : '0' );
			
			if( !is_array( $status ) )
			{
				$kernel->tp->call( "<script type=\"text/javascript\" language=\"javascript\">window.parent.initNoUpload();</script>", CALL_STRING );
			}
			else
			{
				$status['rate'] = ( $status['current'] - $kernel->vars['last_bytes'] );
				
				$kernel->tp->call( "<script type=\"text/javascript\" language=\"javascript\">window.parent.updateUploadProgress( '" . addslashes( $status['filename'] ) . "', '" . $status['current'] . "', '" . $status['total'] . "', '" . $status['rate'] . "', '" . $state_flag . "' );</script>", CALL_STRING );
				
				$kernel->vars['last_bytes'] = $status['current'];
			}
			
			if( $status['done'] != 1 )
			{
				$kernel->tp->call( "<meta http-equiv=\"refresh\" content=\"1;url=upload.php?node=progress&cache_key=" . $kernel->vars['cache_key'] . "&last_bytes=" . $kernel->vars['last_bytes'] . "\" />", CALL_STRING );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		if( extension_loaded( 'apc' ) && function_exists( 'apc_fetch' ) && @ini_get( "apc.rfc1867" ) == 1 && PHP_VERSION >= 5.2 )
		{
			$kernel->tp->call( "upload_apc_window" );
			
			$kernel->tp->cache( $kernel->vars );
			$kernel->tp->cache( $kernel->vars['page_struct'] );
		}
		else
		{
			$kernel->tp->call( "upload_window" );
		}
		
		break;
	}
}

$kernel->tp->dump();

?>