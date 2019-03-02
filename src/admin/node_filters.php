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
	
	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "site_name" => V_STR ) );
		
		if( empty( $kernel->vars['site_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_site_given'], M_ERROR );
		}
		else
		{
			$kernel->db->insert( "sites", array( "site_name" => $kernel->format_input( $kernel->vars['site_name'], T_DB ) ) );
			
			$kernel->archive->update_database_counter( "sites" );
			
			$kernel->admin->message_admin_report( "log_site_added", $kernel->vars['site_name'] );
		}
		
		break;
	}
	
	#############################################################################
	
	case "manage" :
	{		
		if( isset( $_POST['form_update'] ) )
		{
			$kernel->admin->write_config_ini( null );
		}
		elseif( isset( $_POST['form_delete'] ) )
		{			
			$count = 0;
			
			if( is_array( $_POST['sites'] ) )
			{
				foreach( $_POST['sites'] as $site )
				{
					$delete_data[] = $kernel->db->item( "SELECT `site_name` FROM `" . TABLE_PREFIX . "sites` WHERE `site_id` = " . $site );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sites` WHERE `site_id` = " . $site );
					$count++;
				}
			}
			
			$kernel->archive->update_database_counter( "sites" );
			
			$kernel->admin->message_admin_report( "log_site_deleted", $count, $delete_data );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->page->construct_vars_flags( array( "" ) );
		
		$kernel->page->message_report( sprintf( $kernel->ld['phrase_site_domain_filter'], HTTP_HOST ) );
		
		$kernel->tp->call( "admin_filters" );
		
		$kernel->archive->construct_list_options( 0, "site", $kernel->db->query( "SELECT `site_id`, `site_name` FROM `" . TABLE_PREFIX . "sites` ORDER BY `site_name`" ), false );
		
		$kernel->page->construct_config_options( "site_list_leech_mode", array( $kernel->ld['phrase_menu_allow_all'] => 1, $kernel->ld['phrase_menu_block_all'] => 2 ) );
	}
}

?>

