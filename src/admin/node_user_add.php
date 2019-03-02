<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'USR_ADD' );

switch( $kernel->vars['action'] )
{
	case "create" :
	{
		###########################################################################
		
		$kernel->clean_array( "_POST", array( "user_group_id" => V_INT, "user_name" => V_STR, "user_email" => V_STR, "user_password" => V_STR, "user_password_confirm" => V_STR ) );
		
		if( $kernel->vars['user_name'] == "" )
		{
			$kernel->page->message_report( $kernel->ld['phrase_user_no_name'], M_ERROR );
		}
		elseif( empty( $kernel->vars['user_group_id'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_user_no_group'], M_ERROR );
		}
		elseif( $kernel->vars['user_email'] == "" OR !preg_match( "/^[\w-]+(\.[\w-]+)*@([0-9a-z][0-9a-z-]*[0-9a-z]\.)+([a-z]{2,6})$/i", strtolower( $kernel->vars['user_email'] ) ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_invalid_email_address'], M_ERROR );
		}
		elseif( empty( $kernel->vars['user_password'] ) OR strlen( $kernel->vars['user_password'] ) < 4 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_invalid_password'], M_ERROR );
		}
		elseif( $kernel->vars['user_password'] !== $kernel->vars['user_password_confirm'] )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_match_password'], M_ERROR );
		}
		else
		{			
			$userdata = array(
				"user_name" => $kernel->format_input( $kernel->vars['user_name'], T_DB ),
				"user_password" => md5( $kernel->vars['user_password'] ),
				"user_email" => $kernel->format_input( $kernel->vars['user_email'], T_DB ),
				"user_downloads" => 0,
				"user_bandwidth" => 0,
				"user_timestamp" => UNIX_TIME,
				"user_active" => 'Y',
			);
			
			if( $kernel->session->vars['adminsession_group_id'] > 1 AND $kernel->vars['user_group_id'] == 1 )
  		{
				$kernel->page->message_report( $kernel->ld['phrase_cannot_create_update_administrators'], M_ERROR );
  		}
			else
			{
				$userdata['user_group_id'] = $kernel->vars['user_group_id'];
				
				$kernel->db->insert( "users", $userdata );
				
				$kernel->archive->update_database_counter( "users" );
				
				$kernel->admin->message_admin_report( "log_user_added", $kernel->vars['user_name'] );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_user_add" );
		
		$kernel->archive->construct_usergroup_options( 0, $kernel->db->query( "SELECT `usergroup_id`, `usergroup_title`, `usergroup_panel_permissions` FROM `" . TABLE_PREFIX . "usergroups` ORDER BY `usergroup_title`" ) );
		
		break;
	}
}

?>

