<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'USR_MAN', 'USR_DEL' );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "user_id" => V_INT, "filter" => V_STR ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";
if( empty( $kernel->vars['filter'] ) ) $kernel->vars['filter'] = "all";

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'USR_MAN' );
		
		$user = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $kernel->vars['user_id'] . " LIMIT 1" );
		
		if( IN_DEMO_MODE == true AND $kernel->vars['user_id'] == 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_in_demo_mode'], M_ERROR, HALT_EXEC );
		}
		elseif( $kernel->session->vars['adminsession_user_id'] <> 1 AND $user['user_id'] == 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_cannot_update_root_administrator'], M_ERROR );
		}
		elseif( $kernel->session->vars['adminsession_group_id'] <> 1 AND $user['user_group_id'] == 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_cannot_create_update_administrators'], M_ERROR );
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_user_session_clear_warning'], M_WARNING );
			
			$kernel->tp->call( "admin_user_edit" );
			
			$user['user_name'] = $kernel->format_input( $user['user_name'], T_FORM );
			$user['user_email'] = $kernel->format_input( $user['user_email'], T_FORM );
			
			$kernel->tp->cache( $user );
			
			$kernel->archive->construct_usergroup_options( $user['user_group_id'], $kernel->db->query( "SELECT `usergroup_id`, `usergroup_title`, `usergroup_panel_permissions` FROM `" . TABLE_PREFIX . "usergroups` ORDER BY `usergroup_title`" ) );
		}
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'USR_MAN' );
		
		$kernel->clean_array( "_POST", array( "user_group_id" => V_INT, "user_name" => V_STR, "user_email" => V_STR, "user_password" => V_STR, "user_password_confirm" => V_STR ) );
		
		$user = $kernel->db->row( "SELECT `user_name`, `user_email` FROM " . TABLE_PREFIX . "users WHERE `user_id` = '" . $kernel->vars['user_id'] . "'" );
		
		if( empty( $kernel->vars['user_name'] ) )
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
		elseif( !empty( $kernel->vars['user_password'] ) AND strlen( $kernel->vars['user_password'] ) < 4 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_invalid_password'], M_ERROR );
		}
		elseif( !empty( $kernel->vars['user_password'] ) AND $kernel->vars['user_password'] !== $kernel->vars['user_password_confirm'] )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_match_password'], M_ERROR );
		}
		else
		{
			if( $kernel->db->numrows( "SELECT `user_name` FROM " . TABLE_PREFIX . "users WHERE `user_name` = '" . $kernel->vars['user_name'] . "'" ) == 1 AND $user['user_name'] != $kernel->vars['user_name'] )
			{
				$kernel->page->message_report( $kernel->ld['phrase_user_already_used'], M_NOTICE );
			}
			elseif( $kernel->db->numrows( "SELECT `user_email` FROM " . TABLE_PREFIX . "users WHERE `user_email` = '" . $kernel->vars['user_email'] . "'" ) == 1 AND $user['user_email'] != $kernel->vars['user_email'] )
			{
				$kernel->page->message_report( $kernel->ld['phrase_email_already_used'], M_NOTICE );
			}
			else
			{
				$userdata = array(
					"user_name" => $kernel->format_input( $kernel->vars['user_name'], T_DB ),
					"user_email" => $kernel->format_input( $kernel->vars['user_email'], T_DB )
				);
				
				if( !empty( $kernel->vars['user_password'] ) ) $userdata['user_password'] = md5( $kernel->vars['user_password'] );
				
				if( $_POST['form_clear_downloads'] == 1 ) $userdata['user_downloads'] = 0;
				
				if( $_POST['form_clear_bandwidth'] == 1 ) $userdata['user_bandwidth'] = 0;
				
				if( $kernel->session->vars['adminsession_group_id'] > 1 AND $kernel->vars['user_group_id'] == 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_create_update_administrators'], M_ERROR );
				}
				elseif( $kernel->vars['user_id'] == 1 AND $kernel->vars['user_group_id'] <> 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_downgrade_root_account'], M_ERROR );
				}
				else
				{
					$userdata['user_group_id'] = $kernel->vars['user_group_id'];
					
					$kernel->db->update( "users", $userdata, "WHERE `user_id` = '" . $kernel->vars['user_id'] . "'" );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions` WHERE `session_user_id` = " . $kernel->vars['user_id'] );
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_user_id` = " . $kernel->vars['user_id'] );
					
					$kernel->archive->update_database_counter( "users" );
					
					$kernel->admin->message_admin_report( "log_user_edited", $kernel->vars['user_name'] );
				}
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "manage" :
	{
		if( isset( $_POST['form_delete'] ) OR $kernel->vars['user_id'] > 0 )
		{
			$kernel->admin->read_permission_flags( 'USR_DEL' );
			
			$delete_count = 0;
			
			if( $kernel->vars['user_id'] > 0 )
			{
				$check_user = $kernel->db->row( "SELECT `user_group_id` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $kernel->vars['user_id'] );
				
				if( $kernel->vars['user_id'] == $kernel->session->vars['adminsession_user_id'] )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_own_account'], M_ERROR );
				}
				elseif( $kernel->vars['user_id'] == 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_root_user'], M_ERROR );
				}
				elseif( $check_user['user_group_id'] == 1 AND $kernel->session->vars['adminsession_group_id'] <> 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_administrator_user'], M_ERROR );
				}
				else
				{
					$delete_data[] = $kernel->db->item( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $kernel->vars['user_id'] );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $kernel->vars['user_id'] );
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions` WHERE `session_user_id` = " . $kernel->vars['user_id'] );
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_user_id` = " . $kernel->vars['user_id'] );
					$delete_count++;
				}
			}
			elseif( is_array( $_POST['checkbox'] ) )
			{
				foreach( $_POST['checkbox'] AS $user )
				{
					$check_user = $kernel->db->row( "SELECT `user_group_id` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $user );
					
					if( $user == $kernel->session->vars['adminsession_user_id'] )
					{
						$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_own_account'], M_ERROR );
					}
					elseif( $user == "1" )
					{
						$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_root_user'], M_NOTICE );
					}
					elseif( $check_user['user_group_id'] == 1 AND $kernel->session->vars['adminsession_group_id'] <> 1 )
					{
						$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_administrator_user'], M_ERROR );
					}
					else
					{
						$delete_data[] = $kernel->db->item( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $user );
						
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $user );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions` WHERE `session_user_id` = " . $user );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_user_id` = " . $user );
						$delete_count++;
					}
				}
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
			}
			
			$kernel->archive->update_database_counter( "users" );
			
			$kernel->admin->message_admin_report( "log_user_deleted", $delete_count, $delete_data );
		}
		
		###########################################################################
		
		elseif( isset( $_POST['form_move'] ) )
		{
			$kernel->admin->read_permission_flags( 'USR_MAN' );
			
			$count = 0;
			
			if( intval( $kernel->session->vars['adminsession_group_id'] ) <> 1 AND intval( $usergroup_move_id ) == 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_cannot_create_update_administrators'], M_ERROR );
			}
			else
			{
				if( is_array( $_POST['checkbox'] ) )
				{
					foreach( $_POST['checkbox'] AS $user )
					{
						if( $user == $_POST['usergroup_move_id'] ) continue;
						
						$move_data[] = $kernel->db->item( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $user );
						
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions` WHERE `session_user_id` = " . $user );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_user_id` = " . $user );
						
						$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "users` SET `user_group_id` = " . $_POST['usergroup_move_id'] . " WHERE `user_id` = " . $user );
						$count++;
					}
				}
				else
				{
					$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
				}
				
				$kernel->archive->update_database_counter( "users" );
				
				$kernel->admin->message_admin_report( "log_user_moved", $count, $move_data );
			}
		}
		
		###########################################################################
		
		elseif( isset( $_POST['form_activate'] ) )
		{
			$kernel->admin->read_permission_flags( 'USR_MAN' );
			
			$count = 0;
			
			if( is_array( $_POST['checkbox'] ) )
			{
				foreach( $_POST['checkbox'] AS $user_id )
				{
					if( $user_id == 1 ) continue;
					
					$toggle_data[] = $kernel->db->item( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $user_id );
					
					$user = $kernel->db->row( "SELECT `user_group_id`, `user_active` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $user_id );
					
					if( intval( $user['user_group_id'] ) <> 1 )
					{
						$userdata['user_active'] = ( $user['user_active'] == "N" ) ? "Y" : "N";
						
						$kernel->db->update( "users", $userdata, "WHERE `user_id` = " . $user_id );
						
						$count++;
					}
				}
				
				$kernel->admin->message_admin_report( "log_user_activated", $count, $toggle_data );
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
			}
		}

		break;
	}
	
	#############################################################################
	
	default :
	{
		$current_group_id = -1;
		
		if( $kernel->vars['filter'] == "pending" )
		{
			$user_filter_syntax = "WHERE `user_active` = 'N'";
		}
		elseif( $kernel->vars['filter'] == "all" )
		{
			$user_filter_syntax = "";
		}
		else
		{
			$user_filter_syntax = "WHERE `user_group_id` = " . $kernel->format_input( $kernel->vars['filter'], T_NUM );
		}
		
		$check_users = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "users` " . $user_filter_syntax . " ORDER BY `user_group_id`, `user_name`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_users_in_filter'], M_ERROR );
		}
		else
		{
			$kernel->tp->call( "admin_user_header" );
			
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_users ) );
			
			$get_users = $kernel->db->query( "SELECT u.*, g.usergroup_title, g.usergroup_panel_permissions FROM " . TABLE_PREFIX . "users u LEFT JOIN " . TABLE_PREFIX . "usergroups g ON ( u.user_group_id = g.usergroup_id ) " . $user_filter_syntax . " ORDER BY u.user_group_id, u.user_name LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			while( $user = $kernel->db->data( $get_users ) )
			{
				if( $user['user_group_id'] != $current_group_id AND $current_group_id >= 0 )
				{
					$kernel->tp->call( "admin_user_row_break" );
				}
				$current_group_id = $user['user_group_id'];
				
				$kernel->tp->call( "admin_user_row" );
				
				$user['user_name'] = $kernel->format_input( $user['user_name'], T_NOHTML );
				$user['user_timestamp'] = $kernel->fetch_time( $user['user_timestamp'], DF_SHORT );
				$user['user_downloads'] = $kernel->format_input( $user['user_downloads'], T_NUM );
				$user['user_bandwidth'] = $kernel->archive->format_round_bytes( $user['user_bandwidth'] );
				
				//setup user action button states
				$user['user_delete_button'] = $kernel->admin->button_status_if( ( ( $user['user_id'] == 1 OR $kernel->session->vars['adminsession_group_id'] <> 1 ) AND $user['user_group_id'] == 1 ), $user['user_name'], "delete", $kernel->ld['phrase_delete'], "?hash=" . $kernel->session->vars['hash'] . "&node=user_manage&user_id=" . $user['user_id'] . "&action=manage" );
				$user['user_edit_button'] = $kernel->admin->button_status_if( ( $kernel->session->vars['adminsession_group_id'] <> 1 AND $user['user_group_id'] == 1 ), $user['user_name'], "edit", $kernel->ld['phrase_edit'], "?hash=" . $kernel->session->vars['hash'] . "&node=user_manage&user_id=" . $user['user_id'] . "&action=edit" );
				
				//icons - user levels
				if( $user['user_group_id'] == 1 )
				{
					$user['user_html_name'] = $kernel->admin->construct_icon( "user_admin.gif", $kernel->ld['phrase_administrator'], true );
				}
				elseif( strpos( $user['usergroup_panel_permissions'], "1" ) !== false )
				{
					$user['user_html_name'] = ( $user['user_active'] == "Y" ) ? $kernel->admin->construct_icon( "user_mod.gif", $kernel->ld['phrase_moderator'] . " - " . $user['usergroup_title'], true ) : $kernel->admin->construct_icon( "user_pending.gif", $kernel->ld['phrase_moderator'] . " - " . $user['usergroup_title'] . " " . $kernel->ld['phrase_user_pending_activation'], true );
				}
				else
				{
					$user['user_html_name'] = ( $user['user_active'] == "Y" ) ? $kernel->admin->construct_icon( "user_regular.gif", $kernel->ld['phrase_user'] . " - " . $user['usergroup_title'], true ) : $kernel->admin->construct_icon( "user_pending.gif", $kernel->ld['phrase_user'] . " - " . $user['usergroup_title'] . " " . $kernel->ld['phrase_user_pending_activation'], true );
				}
				
				//icons - login status
				if( $kernel->db->numrows( "SELECT `session_id` FROM `" . TABLE_PREFIX . "sessions` WHERE `session_user_id` = '" . $user['user_id'] . "'" ) > 0 )
				{
					$user['user_html_name'] .= $kernel->admin->construct_icon( "tag_green.gif", $kernel->ld['phrase_logged_in_archive'], true );
				}
				
				if( $kernel->db->numrows( "SELECT `adminsession_id` FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_user_id` = '" . $user['user_id'] . "'" ) > 0 )
				{
					$user['user_html_name'] .= $kernel->admin->construct_icon( "tag.gif", $kernel->ld['phrase_logged_in_control_panel'], true );
				}
				
				//colour indicators
				if( $user['user_id'] == $kernel->session->vars['adminsession_user_id'] AND $user['user_id'] == "1" )
				{
					$user['user_html_name'] .= $kernel->page->string_colour( $user['user_name'], "orange" );
				}
				elseif( $user['user_id'] == $kernel->session->vars['adminsession_user_id'] )
				{
					$user['user_html_name'] .= $kernel->page->string_colour( $user['user_name'], "#33cc33" );
				}
				elseif( $user['user_id'] == 1 )
				{
					$user['user_html_name'] .= $kernel->page->string_colour( $user['user_name'], "red" );
				}
				else
				{
					$user['user_html_name'] .= $user['user_name'];
				}
				
				$kernel->tp->cache( $user );
			}
			
			$kernel->tp->call( "admin_user_footer" );
			
			//construct usergroup list
			$kernel->archive->construct_usergroup_options( $kernel->vars['filter'], $kernel->db->query( "SELECT `usergroup_id`, `usergroup_title`, `usergroup_panel_permissions` FROM `" . TABLE_PREFIX . "usergroups` ORDER BY `usergroup_title`" ), true );
			
			$kernel->page->construct_category_filters();
			
			$kernel->page->construct_pagination( array( 'filter' => $kernel->vars['filter'] ), $kernel->config['admin_pagination_page_proximity'] );
		}
		
		break;
	}
}

?>

