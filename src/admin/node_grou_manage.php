<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'GRP_MAN', 'GRP_DEL' );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "usergroup_id" => V_INT ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'GRP_MAN' );
		
		if( $kernel->vars['usergroup_id'] == 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_cannot_edit_administrator'], M_NOTICE, HALT_EXEC );
		}
		
		if( $kernel->vars['usergroup_id'] != -1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_session_clear_warning'], M_ALERT );
		}
		
		if( $kernel->vars['usergroup_id'] == -1 OR $kernel->vars['usergroup_id'] == 2 )
		{
			$kernel->tp->call( "admin_grou_edit_lo" );
		}
		else
		{
			$kernel->tp->call( "admin_grou_edit" );
		}
		
		$usergroup = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` = " . $kernel->vars['usergroup_id'] . " LIMIT 1" );
		
		$panel_permissions_data = $archive_permissions_data = array();
		$panel_data = unserialize( $usergroup['usergroup_panel_permissions'] );
		$archive_data = unserialize( $usergroup['usergroup_archive_permissions'] );
		
		if( is_array( $panel_data ) AND sizeof( $panel_data ) > 0 )
		{
			foreach( $panel_data AS $set_key => $set_value )
			{
				$panel_permissions_data[ $set_key ] = ( $set_value == "1" ) ? "checked=\"true\"" : "";
			}
		}
		$kernel->tp->cache( $panel_permissions_data );
		
		if( is_array( $archive_data ) AND sizeof( $archive_data ) > 0 )
		{
			foreach( $archive_data AS $set_key => $set_value )
			{
				$archive_permissions_data[ $set_key . "_Y" ] = ( $set_value == "1" ) ? "checked=\"true\"" : "";
				$archive_permissions_data[ $set_key . "_N" ] = ( $set_value == "0" ) ? "checked=\"true\"" : "";
			}
		}
		$kernel->tp->cache( $archive_permissions_data );
		
		$usergroup['usergroup_title'] = $kernel->format_input( $usergroup['usergroup_title'], T_FROM );
		
		$kernel->tp->cache( $usergroup );
		
		$kernel->page->construct_category_list( unserialize( $usergroup['usergroup_categories'] ) );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'GRP_MAN' );
		
		$kernel->clean_array( "_POST", array( "usergroup_title" => V_STR, "usergroup_session_downloads" => V_INT, "usergroup_session_baud" => V_INT, "usergroup_categories" => V_ARRAY ) );
		
		if( empty( $kernel->vars['usergroup_title'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_usergroup_title'], M_WARNING );
		}
		else
		{
			$usergroup = $kernel->db->row( "SELECT `usergroup_title` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` = '" . $kernel->vars['usergroup_id'] . "'" );
			
			$kernel->vars['usergroup_title'] = $kernel->format_input( $kernel->vars['usergroup_title'], T_DB );
			
			if( $kernel->db->numrows( "SELECT `usergroup_title` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_title` = '" . $kernel->vars['usergroup_title'] . "'" ) == 1 AND $usergroup['usergroup_title'] != $kernel->vars['usergroup_title'] )
			{
				$kernel->page->message_report( $kernel->ld['phrase_usergroup_title_in_use'], M_ERROR );
			}
			else
			{
				$usergroupdata = array(
					"usergroup_title" => $kernel->vars['usergroup_title'],
					"usergroup_session_downloads" => $kernel->vars['usergroup_session_downloads'],
					"usergroup_session_baud" => $kernel->vars['usergroup_session_baud'],
					"usergroup_categories" => serialize( $kernel->vars['usergroup_categories'] )
				);
				
				//Check for bad mods and build permissions string
				if( $kernel->session->vars['adminsession_group_id'] > 1 AND $kernel->vars['usergroup_id'] == 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_create_update_administrators'], M_ERROR );
				}
				else
				{
					$archive_data = array(
						'COM_POS' => 0, 'COM_VEW' => 0,
						'FIL_DWN' => 0, 'FIL_SRH' => 0, 'FIL_RAT' => 0, 'FIL_SUB' => 0,
						'GAL_VEW' => 0,
						'RSS_VEW' => 0,
					);
					
					if( $kernel->session->vars['adminsession_group_id'] == 1 )
					{
						$panel_data = array(
							'ANO_ADD' => 0, 'ANO_MAN' => 0, 'ANO_DEL' => 0,
							'CAT_ADD' => 0, 'CAT_MAN' => 0, 'CAT_DEL' => 0,
											'COM_MAN' => 0, 'COM_DEL' => 0,
							'DOC_ADD' => 0, 'DOC_MAN' => 0, 'DOC_DEL' => 0,
							'FLD_ADD' => 0, 'FLD_MAN' => 0, 'FLD_DEL' => 0,
							'FIL_ADD' => 0, 'FIL_MAN' => 0, 'FIL_DEL' => 0,
							'GAL_ADD' => 0, 'GAL_MAN' => 0, 'GAL_DEL' => 0,
							'GRP_ADD' => 0, 'GRP_MAN' => 0, 'GRP_DEL' => 0,
							'IMG_ADD' => 0, 'IMG_MAN' => 0, 'IMG_DEL' => 0,
							'SCR_ADD' => 0, 'SCR_MAN' => 0, 'SCR_DEL' => 0,
							'SYL_ADD' => 0, 'SYL_MAN' => 0, 'SYL_DEL' => 0,
											'SUB_MAN' => 0, 'SUB_DEL' => 0,
							'THM_ADD' => 0, 'THM_MAN' => 0, 'THM_DEL' => 0,
							'USR_ADD' => 0, 'USR_MAN' => 0, 'USR_DEL' => 0,
						);
						
						if( is_array( $_POST['panel_attrib'] ) )
						{
							foreach( $_POST['panel_attrib'] AS $set_key => $set_value )
							{
								$panel_data[ $set_key ] = 1;
							}
						}
						
						if( $kernel->vars['usergroup_id'] > 1 )
						{
							$usergroupdata['usergroup_panel_permissions'] = serialize( $panel_data );
						}
					}
					
					foreach( $_POST['archive_attrib'] AS $set_key => $set_value )
					{
						$archive_data[ $set_key ] = $set_value;
					}
					
					$usergroupdata['usergroup_archive_permissions'] = serialize( $archive_data );
					
					$kernel->db->update( "usergroups", $usergroupdata, "WHERE `usergroup_id` = " . $kernel->vars['usergroup_id'] );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions` WHERE `session_group_id` = " . $kernel->vars['usergroup_id'] . " AND `session_user_id` != " . $kernel->session->vars['adminsession_user_id'] );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_group_id` = " . $kernel->vars['usergroup_id'] . " AND `adminsession_user_id` != " . $kernel->session->vars['adminsession_user_id'] );
					
					$kernel->archive->update_database_counter( "usergroups" );
					
					//done
					$kernel->admin->message_admin_report( "log_user_group_edited", $kernel->vars['usergroup_title'] );
				}
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "manage" :
	{
		if( isset( $_POST['form_delete'] ) OR $kernel->vars['usergroup_id'] > 0 )
		{
			$kernel->clean_array( "_POST", array( "usergroup_move_id" => V_INT ) );
			
			if( $kernel->vars['usergroup_move_id'] > 0 )
			{
				$kernel->admin->read_permission_flags( 'GRP_MAN' );
				
				if( is_array( $_POST['checkbox'] ) )
				{
					foreach( $_POST['checkbox'] AS $usergroup )
					{
						if( $usergroup == $kernel->vars['usergroup_move_id'] ) continue;
						if( $usergroup == 1 ) continue;
						
						$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "users` SET `user_group_id` = " . $kernel->vars['usergroup_move_id'] . " WHERE `user_group_id` = " . $usergroup );
						$count++;
					}
				}
				else
				{
					$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
				}
				
				$kernel->archive->update_database_counter( "usergroups" );
			}
			
			$delete_count = 0;
			
			$kernel->admin->read_permission_flags( 'GRP_DEL' );
			
			//single item
			if( $kernel->vars['usergroup_id'] > 0 )
			{
				if( $kernel->vars['usergroup_id'] == $kernel->session->vars['adminsession_group_id'] )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_own_usergroup'], M_ERROR );
				}
				elseif( $kernel->vars['usergroup_id'] == 1 OR $kernel->vars['usergroup_id'] == 2 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_root_usergroup'], M_ERROR );
				}
				elseif( $kernel->vars['usergroup_id'] == -1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_guest_usergroup'], M_ERROR );
				}
				else
				{
					$delete_data[] = $kernel->db->item( "SELECT `usergroup_title` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` = " . $kernel->vars['usergroup_id'] );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` = " . $kernel->vars['usergroup_id'] );
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions` WHERE `session_group_id` = " . $kernel->vars['usergroup_id'] );
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_group_id` = " . $kernel->vars['usergroup_id'] );
					$delete_count++;
				}
			}
			
			//array items
			elseif( is_array( $_POST['checkbox'] ) )
			{
				foreach( $_POST['checkbox'] AS $usergroup )
				{
					if( $usergroup == $kernel->session->vars['adminsession_group_id'] )
					{
						$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_own_usergroup'], M_ERROR );
					}
					elseif( $usergroup == 1 OR $usergroup == 2 )
					{
						$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_root_usergroup'], M_ERROR );
					}
					elseif( $usergroup == -1 )
					{
						$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_guest_usergroup'], M_ERROR );
					}
					else
					{
						$delete_data[] = $kernel->db->item( "SELECT `usergroup_title` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` = " . $usergroup );
						
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` = " . $usergroup );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions` WHERE `session_group_id` = " . $usergroup );
						$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "sessions_admin` WHERE `adminsession_group_id` = " . $usergroup );
						$delete_count++;
					}
				}
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
			}
			
			if( $delete_count > 0 )
			{
				$kernel->archive->update_database_counter( "usergroups" );
				
				$kernel->admin->message_admin_report( "log_user_group_deleted", $delete_count, $delete_data );
			}
		}
		
		###########################################################################
		
		elseif( isset( $_POST['form_move'] ) )
		{
			$kernel->admin->read_permission_flags( 'GRP_MAN' );
			
			$kernel->clean_array( "_POST", array( "usergroup_move_id" => V_INT ) );
			
			if( is_array( $_POST['checkbox'] ) )
			{
				foreach( $_POST['checkbox'] AS $usergroup )
				{
					if( $usergroup == $kernel->vars['usergroup_move_id'] ) continue;
					
					$get_users = $kernel->db->query( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_group_id` = " . $usergroup );
					while( $user = $kernel->db->data( $get_users ) )
					{
						$move_data[] = $user['user_name'];
					}
					
					$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "users` SET `user_group_id` = " . $kernel->vars['usergroup_move_id'] . " WHERE `user_group_id` = " . $usergroup );
					
					$count = $kernel->db->affectrows();
				}
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
			}
			
			$kernel->archive->update_database_counter( "usergroups" );
			
			$kernel->admin->message_admin_report( "log_user_moved", $count, $move_data );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$check_usergroups = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "usergroups` ORDER BY `usergroup_id`" );
		
		$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_usergroups ) );
		$current_group_id = -1;
		
		$kernel->tp->call( "admin_grou_header" );
		
		$get_usergroups = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "usergroups` ORDER BY `usergroup_id` LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
		
		while( $usergroup = $kernel->db->data( $get_usergroups ) )
		{
			$kernel->tp->call( "admin_grou_row" );
			
			$usergroup['usergroup_title'] = $kernel->format_input( $usergroup['usergroup_title'], T_NOHTML );
			
			//setup user action button states
			$usergroup['usergroup_edit_button'] = $kernel->admin->button_status_if( ( $usergroup['usergroup_id'] == 1 ), $usergroup['usergroup_title'], "edit", $kernel->ld['phrase_delete'], "?hash=" . $kernel->session->vars['hash'] . "&node=grou_manage&usergroup_id=" . $usergroup['usergroup_id'] . "&action=edit" );
			$usergroup['usergroup_delete_button'] = $kernel->admin->button_status_if( ( $usergroup['usergroup_id'] == -1 OR $usergroup['usergroup_id'] == 1 OR $usergroup['usergroup_id'] == 2 ), $usergroup['usergroup_title'], "delete", $kernel->ld['phrase_delete'], "?hash=" . $kernel->session->vars['hash'] . "&node=grou_manage&usergroup_id=" . $usergroup['usergroup_id'] . "&action=manage" );
			
			//icons - user levels
			if( $usergroup['usergroup_id'] == 1 )
			{
				$usergroup['usergroup_html_title'] = $kernel->admin->construct_icon( "user_admin.gif", $kernel->ld['phrase_administrator'], true );
			}
			elseif( strpos( $usergroup['usergroup_panel_permissions'], "1" ) !== false )
			{
				$usergroup['usergroup_html_title'] = $kernel->admin->construct_icon( "user_mod.gif", $kernel->ld['phrase_moderator'], true );
			}
			elseif( $usergroup['usergroup_id'] == -1 )
			{
				$usergroup['usergroup_html_title'] = $kernel->admin->construct_icon( "user_guest.gif", $kernel->ld['phrase_alt_guest'], true );
			}
			else
			{
				$usergroup['usergroup_html_title'] = $kernel->admin->construct_icon( "user_regular.gif", $kernel->ld['phrase_user'], true );
			}
			
			//colour indicators
			if( $usergroup['usergroup_id'] == $kernel->session->vars['adminsession_group_id'] AND $usergroup['usergroup_id'] == 1 )
			{
				$usergroup['usergroup_html_title'] .= $kernel->page->string_colour( $usergroup['usergroup_title'], "orange" );
			}
			elseif( $usergroup['usergroup_id'] == $kernel->session->vars['adminsession_group_id'] )
			{
				$usergroup['usergroup_html_title'] .= $kernel->page->string_colour( $usergroup['usergroup_title'], "#33cc33" );
			}
			elseif( $usergroup['usergroup_id'] == 1 )
			{
				$usergroup['usergroup_html_title'] .= $kernel->page->string_colour( $usergroup['usergroup_title'], "red" );
			}
			else
			{
				$usergroup['usergroup_html_title'] .= $usergroup['usergroup_title'];
			}
			
			$kernel->tp->cache( $usergroup );
		}
		
		$kernel->tp->call( "admin_grou_footer" );
		
		$kernel->archive->construct_usergroup_options( 0, $kernel->db->query( "SELECT `usergroup_id`, `usergroup_title`, `usergroup_panel_permissions` FROM `" . TABLE_PREFIX . "usergroups` ORDER BY `usergroup_title`" ) );
		
		$kernel->page->construct_category_filters();
		
		$kernel->page->construct_pagination( array(), $kernel->config['admin_pagination_page_proximity'] );
		
		break;
	}
}

?>

