<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'SYL_MAN', 'SYL_DEL' );

$kernel->clean_array( "_REQUEST", array( "style_id" => V_INT ) );

switch( $kernel->vars['action'] )
{
	
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'SYL_MAN' );
		
		$style = $kernel->db->row( "SELECT s.style_id, s.style_name, s.style_data, s.style_description FROM " . TABLE_PREFIX . "styles s WHERE s.style_id = " . $kernel->vars['style_id'] . " LIMIT 1" );
		
		$kernel->tp->call( "admin_style_edit" );
		
		$style['style_name'] = $kernel->format_input( $style['style_name'], T_FORM );
		$style['style_description'] = $kernel->format_input( $style['style_description'], T_FORM );
		
		$style['style_data'] = $kernel->format_input( $kernel->htmlspecialchars_new( $style['style_data'] ), T_FORM );
		$style['style_data'] = str_replace( chr( 36 ), "&#36;", $style['style_data'] );
		
		$kernel->tp->cache( $style );
	
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'SYL_MAN' );
		
		$kernel->clean_array( "_REQUEST", array( "style_name" => V_STR, "style_data" => V_STR, "style_description" => V_STR ) );
		
		$kernel->vars['style_data'] = $kernel->format_input( $kernel->vars['style_data'], T_RAW );
		
		$style = $kernel->db->row( "SELECT s.style_name, s.style_data FROM " . TABLE_PREFIX . "styles s WHERE s.style_id = " . $kernel->vars['style_id'] . "" );
		
		//Original data backup
		$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "styles` SET `style_data_bak` = '" . addslashes( $kernel->vars['style_data'] ) . "' WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		$styledata = array(
			"style_name" => $kernel->format_input( $kernel->vars['style_name'], T_DB ),
			"style_data" => $kernel->vars['style_data'],
			"style_description" => $kernel->format_input( $kernel->vars['style_description'], T_DB ),
			"style_author" => $kernel->format_input( $kernel->session->vars['adminsession_name'], T_DB ),
			"style_timestamp" => UNIX_TIME
		);
		
		//new style? lets make what they add the original to revert to..
		$check_original = $kernel->db->row( "SELECT `style_original` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		if( empty( $check_original['style_original'] ) )
		{
			$styledata['style_original'] = $kernel->vars['style_data'];
		}
		
		$kernel->db->update( "styles", $styledata, "WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		$kernel->archive->update_database_counter( "styles" );
		
		$kernel->admin->message_admin_report( "log_style_edited", $kernel->vars['style_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "delete" :
	{
		$kernel->admin->read_permission_flags( 'SYL_DEL' );
		
		$delete_count = 0;
		
		if( $kernel->vars['style_id'] > 0 )
		{
			if( $kernel->vars['style_id'] == $kernel->config['default_style'] )
			{
				$kernel->page->message_report( $kernel->ld['cannot_delete_archive_style'], M_NOTICE );
			}
			elseif( $kernel->vars['style_id'] == 1 )
			{
				$kernel->page->message_report( $kernel->ld['cannot_delete_root_style'], M_NOTICE );
			}
			else
			{
				$delete_data[] = $kernel->db->item( "SELECT `style_name` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->vars['style_id'] );
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->vars['style_id'] . "" );
				$delete_count++;
			}
		}
		elseif( is_array( $_POST['checkbox'] ) )
		{
			foreach( $_POST['checkbox'] AS $style )
			{
				if( $style == $kernel->config['default_style'] )
				{
					$kernel->page->message_report( $kernel->ld['cannot_delete_archive_style'], M_NOTICE );
				}
				elseif( $style == 1 )
				{
					$kernel->page->message_report( $kernel->ld['cannot_delete_root_style'], M_NOTICE );
				}
				else
				{
					$delete_data[] = $kernel->db->item( "SELECT `style_name` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $style );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $style );
					$delete_count++;
				}
			}
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['checkbox_none_selected'], M_ERROR, HALT_EXEC );
		}
		
		if( $delete_count > 0 )
		{
			$kernel->archive->update_database_counter( "styles" );
			
			$kernel->admin->message_admin_report( "log_style_deleted", $delete_count, $delete_data );
		}
		
		break;
	}
	
	#############################################################################
	
	case "rollback" :
	{
		$kernel->admin->read_permission_flags( 'SYL_MAN' );
		
		$style = $kernel->db->row( "SELECT `style_name`, `style_data`, `style_data_bak` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		$styledata = array(
			"style_data" => addslashes( $style['style_data_bak'] ),
			"style_data_bak" => addslashes( $style['style_data'] )
		);
		
		$kernel->db->update( "styles", $styledata, "WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		
		$kernel->admin->message_admin_report( "log_style_rolled_back", $style['style_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "revert" :
	{
		$kernel->admin->read_permission_flags( 'SYL_MAN' );
		
		$style = $kernel->db->row( "SELECT `style_name`, `style_data`, `style_original` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		$styledata = array(
			"style_data" => addslashes( $style['style_original'] ),
			"style_data_bak" => addslashes( $style['style_data'] )
		);
		
		$kernel->db->update( "styles", $styledata, "WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		$kernel->admin->message_admin_report( "log_style_reverted", $style['style_name'] );
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$get_styles = $kernel->db->query( "SELECT s.* FROM " . TABLE_PREFIX . "styles s ORDER BY s.style_name" );
		
		$kernel->tp->call( "admin_style_header" );
		
		while( $style = $kernel->db->data( $get_styles ) )
		{
			$kernel->tp->call( "admin_style_row" );
			
			$style['style_name'] = $kernel->format_input( $style['style_name'], T_HTML );
			
			if( $style['style_id'] == $kernel->config['default_style'] AND $style['style_id'] == "1" )
			{
				$style['style_html_name'] = $kernel->page->string_colour( $style['style_name'], "orange" );
			}
			elseif( $style['style_id'] == $kernel->config['default_style'] )
			{
				$style['style_html_name'] = $kernel->page->string_colour( $style['style_name'], "#33cc33" );
			}
			elseif( $style['style_id'] == 1 )
			{
				$style['style_html_name'] = $kernel->page->string_colour( $style['style_name'], "red" );
			}
			else
			{
				$style['style_html_name'] = $style['style_name'];
			}
			
			$style['style_description'] = $kernel->format_input( $style['style_description'], T_HTML );
			
			//setup user action button states
			$style['style_option_rollback'] = $kernel->admin->button_status_if( ( empty( $style['style_data_bak'] ) ), $style['style_name'], "rollback", $kernel->ld['undo_changes'], "?hash=" . $kernel->session->vars['hash'] . "&node=styl_manage&style_id=" . $style['style_id'] . "&action=rollback" );
			
			$style['style_option_revert'] = $kernel->admin->button_status_if( ( $style['style_data'] == $style['style_original'] ), $style['style_name'], "revert", $kernel->ld['revert_original'], "?hash=" . $kernel->session->vars['hash'] . "&node=styl_manage&style_id=" . $style['style_id'] . "&action=revert" );
			
			$kernel->tp->cache( $style );
		}
		
		$kernel->tp->call( "admin_style_footer" );
		
		break;
	}
}

?>

