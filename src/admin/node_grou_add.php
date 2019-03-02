<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'GRP_ADD' );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "usergroup_id" => V_INT, "usergroup_title" => V_STR, "usergroup_session_downloads" => V_INT, "usergroup_session_baud" => V_INT, "usergroup_categories" => V_ARRAY ) );
		
		if( empty( $kernel->vars['usergroup_title'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_usergroup_title'], M_ERROR );
		}
		elseif( $kernel->db->numrows( "SELECT `usergroup_title` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_title` = '" . $kernel->format_input( $kernel->vars['usergroup_title'], T_STRIP ) . "'" ) == 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_usergroup_title_in_use'], M_ERROR );
		}
		else
		{
			$usergroupdata = array(
				"usergroup_title" => $kernel->format_input( $kernel->vars['usergroup_title'], T_DB ),
				"usergroup_session_downloads" => $kernel->format_input( $kernel->vars['usergroup_session_downloads'], T_DB ),
				"usergroup_session_baud" => $kernel->format_input( $kernel->vars['usergroup_session_baud'], T_DB ),
				"usergroup_categories" => serialize( $kernel->vars['usergroup_categories'] )
			);
			
			if( intval( $kernel->session->vars['adminsession_group_id'] ) != 1 AND is_array( $_POST['panel_attrib'] ) )
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
				
				if( intval( $kernel->session->vars['adminsession_group_id'] ) == 1 )
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
						foreach( $_POST['panel_attrib'] as $set_key => $set_value )
						{
							$panel_data[ $set_key ] = 1;
						}
					}
					
					$usergroupdata['usergroup_panel_permissions'] = serialize( $panel_data );
				}
				
				
				foreach( $_POST['archive_attrib'] as $set_key => $set_value )
				{
					$archive_data[ $set_key ] = $set_value;
				}
			
				$usergroupdata['usergroup_archive_permissions'] = serialize( $archive_data );
				
				$kernel->db->insert( "usergroups", $usergroupdata );
				
				$kernel->archive->update_database_counter( "usergroups" );
				
				$kernel->admin->message_admin_report( "log_user_group_added", $kernel->vars['usergroup_title'] );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_grou_add" );
		
		$kernel->page->construct_category_list();
		
		break;
	}
}

?>

