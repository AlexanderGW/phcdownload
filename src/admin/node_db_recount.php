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
	
	case "update" :
	{
		#=========================================
		# Recount total files per category
		#=========================================
		
		if( isset( $_POST['form_category_total_files'] ) )
		{
			$fetch_categories = $kernel->db->query( "SELECT `category_id` FROM `" . TABLE_PREFIX . "categories`" );
			
			if( $kernel->db->numrows() > 0 )
			{
				while( $category = $kernel->db->data( $fetch_categories ) )
				{
					$total_files = 0;
					
					$kernel->archive->update_category_file_count( $category['category_id'] );
				}
			}
			
			$kernel->archive->update_database_counter( "files" );
			$kernel->archive->update_database_counter( "categories" );
			
			$kernel->admin->message_admin_report( "log_category_recount", 0, $sync_data );
		}
		
		#=========================================
		# Resync newest file per category
		#=========================================
		
		if( isset( $_POST['form_category_new_file'] ) )
		{
			$fetch_categories = $kernel->db->query( "SELECT `category_id` FROM `" . TABLE_PREFIX . "categories`" );
			
			if( $kernel->db->numrows() > 0 )
			{
				while( $category = $kernel->db->data( $fetch_categories ) )
				{
					$kernel->archive->update_category_new_file( $category['category_id'] );
				}
			}
			
			$kernel->archive->update_database_counter( "files" );
			$kernel->archive->update_database_counter( "categories" );
			
			$kernel->admin->message_admin_report( "log_category_resync", 0, $sync_data );
		}
		
		#=========================================
		# Recount all database statistic totals
		#=========================================
		
		if( isset( $_POST['form_recount_all_totals'] ) )
		{
			$kernel->archive->update_database_counter( "announcements" );
			$kernel->archive->update_database_counter( "categories" );
			$kernel->archive->update_database_counter( "comments" );
			$kernel->archive->update_database_counter( "documents" );
			$kernel->archive->update_database_counter( "fields" );
			$kernel->archive->update_database_counter( "fields_data" );
			$kernel->archive->update_database_counter( "files" );
			$kernel->archive->update_database_counter( "filetypes" );
			$kernel->archive->update_database_counter( "galleries" );
			$kernel->archive->update_database_counter( "images" );
			$kernel->archive->update_database_counter( "mirrors" );
			$kernel->archive->update_database_counter( "sites" );
			$kernel->archive->update_database_counter( "styles" );
			$kernel->archive->update_database_counter( "submissions" );
			$kernel->archive->update_database_counter( "subscriptions" );
			$kernel->archive->update_database_counter( "tags" );
			$kernel->archive->update_database_counter( "templates" );
			$kernel->archive->update_database_counter( "themes" );
			$kernel->archive->update_database_counter( "users" );
			$kernel->archive->update_database_counter( "usergroups" );
			$kernel->archive->update_database_counter( "votes" );
			$kernel->archive->update_database_counter( "downloads" );
			$kernel->archive->update_database_counter( "data" );
			$kernel->archive->update_database_counter( "views" );
			
			$kernel->admin->message_admin_report( "log_database_statistics_recount", 0 );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_recount" );
		
		break;
	}
}

?>

