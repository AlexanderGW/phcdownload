<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->clean_array( "_REQUEST", array( "template_id" => V_INT, "theme_id" => V_INT ) );

$kernel->admin->read_permission_flags( 'THM_ADD', 'THM_MAN', 'THM_DEL' );

$template_filter = array(
	"announcement_box" => 0,
	"announcement_box_list" => 0,
	"category_description" => 0,
	"category_footer" => 0,
	"category_header" => 0,
	"category_login" => 0,
	"category_newfile" => 0,
	"category_no_newfile" => 0,
	"category_row" => 0,
	"comment_add" => 0,
	"comment_footer" => 0,
	"comment_header" => 0,
	"comment_row" => 0,
	"custom_field_subheader" => 0,
	"custom_field_subheader_end" => 0,
	"custom_field_row_single" => 0,
	"custom_field_row_multiple" => 0,
	"custom_field_row_menu" => 0,
	"document_box" => 0,
	"download_time_item" => 0,
	"download_time_item_spacer" => 0,
	"file_add" => 0,
	"file_custom_field" => 0,
	"file_field_cell" => 0,
	"file_field_subheader" => 0,
	"file_footer" => 0,
	"file_header" => 0,
	"file_mirror_box" => 0,
	"file_rate" => 0,
	"file_row" => 0,
	"file_row_break" => 0,
	"file_view" => 0,
	"file_view_hash_row" => 0,
	"file_view_tags_row" => 0,
	"form_security_code" => 0,
	"gallery_empty_item" => 0,
	"gallery_item" => 0,
	"gallery_row_break" => 0,
	"gallery_view" => 0,
	"image_footer" => 0,
	"image_header" => 0,
	"location_bar_footer" => 0,
	"location_bar_header" => 0,
	"location_bar_item" => 0,
	"login_notice" => 0,
	"message_box" => 0,
	"page_footer" => 0,
	"page_header" => 0,
	"page_navigation" => 0,
	"page_statistics" => 0,
	"pagination_category_selector" => 0,
	"pagination_display_selectors" => 0,
	"pagination_end" => 0,
	"pagination_nextpage" => 0,
	"pagination_previouspage" => 0,
	"pagination_span" => 0,
	"pagination_start" => 0,
	"pagination_span_current" => 0,
	"rss_empty_feed" => 0,
	"rss_footer" => 0,
	"rss_header" => 0,
	"rss_item" => 0,
	"search" => 0,
	"search_results_header" => 0,
	"subscriptions_footer" => 0,
	"subscriptions_header" => 0,
	"subscriptions_item" => 0,
	"subscription_forwarder_footer" => 0,
	"subscription_forwarder_nochex" => 0,
	"subscription_forwarder_paypal" => 0,
	"subscription_gateway_option" => 0,
	"subscription_gateway_select" => 0,
	"sub_category" => 0,
	"sub_category_item" => 0,
	"user_add" => 0,
	"user_logged_in" => 0,
	"user_login" => 0,
	"user_login_form" => 0,
	"user_panel_footer" => 0,
	"user_panel_header" => 0,
	"user_panel_overview" => 0,
	"user_panel_profile" => 0,
	"user_panel_subscription_footer" => 0,
	"user_panel_subscription_header" => 0,
	"user_panel_subscription_row" => 0
);

switch( $kernel->vars['action'] )
{
	
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'THM_MAN' );
		
		$template = $kernel->db->row( "SELECT t.template_id, t.template_theme AS `theme_id`, t.template_name, t.template_data, t.template_description FROM " . TABLE_PREFIX . "templates t WHERE t.template_id = " . $kernel->vars['template_id'] . " LIMIT 1" );
		
		$kernel->tp->call( "admin_template_edit" );
		
		$template['template_name'] = $kernel->format_input( $template['template_name'], T_FORM );
		$template['template_description'] = $kernel->format_input( $template['template_description'], T_FORM );
		
		$template['template_data'] = htmlspecialchars( $template['template_data'] );
		$template['template_data'] = str_replace( chr( 36 ), "&#36;", $template['template_data'] );
		
		$kernel->tp->cache( $template );
	
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		//update theme details
		if( $kernel->vars['theme_id'] > 0 AND isset( $_POST['form'] ) )
		{
			$kernel->admin->read_permission_flags( 'THM_MAN' );
			
			$kernel->clean_array( "_POST", array( "theme_name" => V_STR, "theme_description" => V_STR, "theme_styles" => V_ARRAY, "theme_disabled" => V_STR ) );
			
			$theme = $kernel->db->row( "SELECT `theme_name` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_name` = '" . $kernel->vars['theme_name'] . "' AND `theme_id` = " . $kernel->vars['theme_id'] );
			
			if( $theme['theme_name'] != $kernel->vars['theme_name'] AND $kernel->db->numrows( "SELECT `theme_name` FROM " . TABLE_PREFIX . "themes WHERE `theme_name` = '" . $kernel->vars['theme_name'] . "'" ) == 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_theme_name_already_used'], M_NOTICE );
			}
			else
			{
				if( $kernel->vars['theme_disabled'] == 1 AND $kernel->vars['theme_id'] == 1 )
				{
					$kernel->page->message_report( 'The root theme can not be disabled.' );
				}
				else
				{
					$themedata = array(
						"theme_name" => $kernel->format_input( $kernel->vars['theme_name'], T_DB ),
						"theme_description" => $kernel->format_input( $kernel->vars['theme_description'], T_DB ),
						"theme_styles" => serialize( $kernel->vars['theme_styles'] ),
						"theme_disabled" => $kernel->vars['theme_disabled'],
					);
					
					$kernel->db->update( "themes", $themedata, "WHERE `theme_id` = '" . $kernel->vars['theme_id'] . "'" );
					
					$kernel->archive->update_database_counter( "themes" );
					
					$kernel->admin->message_admin_report( "log_theme_edited", $kernel->vars['theme_name'] );
				}
			}
		}
		elseif( $kernel->vars['theme_id'] > 0 AND isset( $_POST['form_add'] ) )
		{
			$kernel->admin->read_permission_flags( 'THM_ADD' );
			
			$kernel->clean_array( "_POST", array( "template_name" => V_STR, "template_description" => V_STR ) );
			
			if( $kernel->db->numrows( "SELECT `template_name` FROM `" . TABLE_PREFIX . "templates` WHERE `template_name` = '" . $kernel->vars['template_name'] . "' AND `template_theme` = " . $kernel->vars['theme_id'] ) == 1 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_template_name_already_used'], M_NOTICE );
			}
			else
			{
				$templatedata = array(
					"template_theme" => $kernel->vars['theme_id'],
					"template_name" => $kernel->format_input( $kernel->vars['template_name'], T_DB ),
					"template_description" => $kernel->format_input( $kernel->vars['template_description'], T_DB ),
					"template_timestamp" => UNIX_TIME,
					"template_author" => $kernel->format_input( $kernel->session->vars['adminsession_name'], T_DB )
				);
				
				$kernel->db->insert( "templates", $templatedata );
				
				$kernel->archive->update_database_counter( "templates" );
				
				$kernel->admin->message_admin_report( "log_template_added", $kernel->vars['template_name'] );
			}
		}
		else
		{
			$kernel->admin->read_permission_flags( 'THM_MAN' );
			
			$kernel->clean_array( "_POST", array( "template_name" => V_STR, "template_data" => V_STR, "template_description" => V_STR ) );
			
			$theme = $kernel->db->row( "SELECT p.theme_id, p.theme_name FROM " . TABLE_PREFIX . "templates t LEFT JOIN " . TABLE_PREFIX . "themes p ON ( t.template_theme = p.theme_id ) WHERE t.template_id = " . $kernel->vars['template_id'] );
			$template = $kernel->db->row( "SELECT t.template_name, t.template_data FROM " . TABLE_PREFIX . "templates t WHERE t.template_id = " . $kernel->vars['template_id'] );
			
			$kernel->vars['template_data'] = str_replace( "&#36;", chr( 36 ), $kernel->vars['template_data'] );
			
			$templatedata = array(
				"template_name" => $kernel->format_input( $kernel->vars['template_name'], T_DB ),
				"template_data" => $kernel->format_input( $_POST['template_data'], T_STRIP ),
				"template_description" => $kernel->format_input( $kernel->vars['template_description'], T_DB ),
				"template_author" => $kernel->format_input( $kernel->session->vars['adminsession_name'], T_DB ),
				"template_timestamp" => UNIX_TIME
			);
			
			if( isset( $template_filter[ $template['template_name'] ] ) AND $theme['theme_id'] == 1 AND $template['template_name'] != $kernel->vars['template_name'] )
			{
				$kernel->page->message_report( $kernel->ld['phrase_template_name_cannot_be_edited_root'], M_ERROR );
			}
			else
			{
				//put the first entered data as the original
				$check_original = $kernel->db->row( "SELECT `template_original` FROM `" . TABLE_PREFIX . "templates` WHERE `template_id` = " . $kernel->vars['template_id'] );
				
				if( empty( $check_original['template_original'] ) )
				{
					$templatedata['template_original'] = $kernel->format_input( $_POST['template_data'], T_STRIP );
				}
				
				//original data backup
				$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "templates` SET `template_data_bak` = '" . addslashes( $template['template_data'] ) . "' WHERE `template_id` = " . $kernel->vars['template_id'] );
				
				$kernel->db->update( "templates", $templatedata, "WHERE `template_id` = '" . $kernel->vars['template_id'] . "'" );
				
				$kernel->archive->update_database_counter( "templates" );
				
				$kernel->admin->message_admin_report( "log_template_edited", $kernel->vars['template_name'], $theme['theme_name'] );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "delete_template" :
	{
		$kernel->admin->read_permission_flags( 'THM_DEL' );
		
		if( $kernel->vars['template_id'] > 0 )
		{
			$template = $kernel->db->row( "SELECT `template_name`, `template_theme` FROM `" . TABLE_PREFIX . "templates` WHERE `template_id` = " . $kernel->vars['template_id'] );
			
			if( isset( $template_filter[ $template['template_name'] ] ) AND $template['template_theme'] == "1" )
			{
				$kernel->page->message_report( $kernel->ld['phrase_template_name_cannot_be_deleted_root'], M_ERROR );
			}
			elseif( $kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "templates` WHERE `template_id` = " . $kernel->vars['template_id'] ) )
			{
				$kernel->archive->update_database_counter( "templates" );
				
				$kernel->admin->message_admin_report( "log_template_deleted", $template['template_name'] );
			}
			else
			{
				$kernel->page->message_report( sprintf( $kernel->ld['phrase_cannot_delete_template'], $template['template_name'] ), M_ERROR );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "delete" :
	{
		$kernel->admin->read_permission_flags( 'THM_DEL' );
		
		$delete_count = 0;
		
		//single item
		if( $kernel->vars['theme_id'] > 0 )
		{
			if( $kernel->vars['theme_id'] == $kernel->config['THEME'] )
			{
				$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_archive_theme'], M_NOTICE );
			}
			elseif( $kernel->vars['theme_id'] == "1" )
			{
				$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_root_theme'], M_NOTICE );
			}
			else
			{
				$delete_data[] = $kernel->db->item( "SELECT `theme_name` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_id` = " . $kernel->vars['theme_id'] );
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "themes` WHERE `theme_id` = " . $kernel->vars['theme_id'] );
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "templates` WHERE `template_theme` = " . $kernel->vars['theme_id'] );
				
				$delete_count++;
			}
		}
		
		//item array
		elseif( is_array( $_POST['checkbox'] ) )
		{
			foreach( $_POST['checkbox'] AS $theme )
			{
				if( $theme == $kernel->config['THEME'] )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_archive_theme'], M_NOTICE );
				}
				elseif( $theme == "1" )
				{
					$kernel->page->message_report( $kernel->ld['phrase_cannot_delete_root_theme'], M_NOTICE );
				}
				else
				{
					$delete_data[] = $kernel->db->item( "SELECT `theme_name` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_id` = " . $theme );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "themes` WHERE `theme_id` = " . $theme );
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "templates` WHERE `template_theme` = " . $theme );
					$delete_count++;
				}
			}
		}
		
		//no items
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
		}
		
		//done
		if( $delete_count > 0 )
		{
			$kernel->archive->update_database_counter( "themes" );
			$kernel->archive->update_database_counter( "templates" );
			
			$kernel->admin->message_admin_report( "log_theme_deleted", $delete_count, $delete_data );
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_items_were_deleted'], M_ERROR );
		}
		
		break;
	}
	
	#############################################################################
	
	case "rollback" :
	{
		$kernel->admin->read_permission_flags( 'THM_MAN' );
		
		$template = $kernel->db->row( "SELECT `template_name`, `template_data`, `template_data_bak` FROM `" . TABLE_PREFIX . "templates` WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$templatedata = array(
			"template_data" => $template['template_data_bak'],
			"template_data_bak" => $template['template_data']
		);
		
		$kernel->db->update( "templates", $templatedata, "WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$kernel->admin->message_admin_report( "log_template_rolled_back", $template['template_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "revert" :
	{
		$kernel->admin->read_permission_flags( 'THM_MAN' );
		
		$template = $kernel->db->row( "SELECT `template_name`, `template_data`, `template_original` FROM `" . TABLE_PREFIX . "templates` WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$templatedata = array(
			"template_data" => $template['template_original'],
			"template_original" => $template['template_data']
		);
		
		$kernel->db->update( "templates", $templatedata, "WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$kernel->admin->message_admin_report( "log_template_reverted", $template['template_name'] );
		
		break;
	}
		
	#############################################################################
	
	case "view" :
	{
		$kernel->tp->call( "admin_template_header" );
		
		$theme = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "themes` WHERE `theme_id` = " . $kernel->vars['theme_id'] );
		
		$kernel->tp->cache( $theme );
		
		$kernel->page->construct_vars_flags( $theme );
		
		$get_templates = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "templates` WHERE `template_theme` = " . $kernel->vars['theme_id'] . " ORDER BY `template_name`" );
		
		//Select styles
		$kernel->archive->construct_list_options( unserialize( $kernel->db->item( "SELECT `theme_styles` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_id` = " . $kernel->vars['theme_id'] ) ), "style", $kernel->db->query( "SELECT s.style_id, s.style_name FROM " . TABLE_PREFIX . "styles s ORDER BY s.style_name" ), false );
		
		while( $template = $kernel->db->data( $get_templates ) )
		{
			$kernel->tp->call( "admin_template_row" );
			
			//setup user action button states
			$template['template_option_rollback'] = $kernel->admin->button_status_if( ( empty( $template['template_data_bak'] ) ), $template['template_id'], "rollback", $kernel->ld['phrase_undo_changes'], "?hash=" . $kernel->session->vars['hash'] . "&node=temp_manage&template_id=" . $template['template_id'] . "&action=rollback" );
			$template['template_option_revert'] = $kernel->admin->button_status_if( ( $template['template_data'] == $template['template_original'] ), $template['template_id'], "revert", $kernel->ld['phrase_revert_original'], "?hash=" . $kernel->session->vars['hash'] . "&node=temp_manage&template_id=" . $template['template_id'] . "&action=revert" );
			
			$template['template_name'] = $kernel->format_input( $template['template_name'], T_NOHTML );
			$template['theme_name'] = $kernel->format_input( $template['theme_name'], T_FORM );
			$template['theme_description'] = $kernel->format_input( $template['theme_description'], T_FORM );
			$template['template_timestamp'] = $kernel->fetch_time( $template['template_timestamp'], DF_SHORT );
			
			$kernel->tp->cache( $template );
		}
		
		$kernel->tp->call( "admin_template_footer" );
	
		break;
	}
	
	#############################################################################
	
	default :
	{
		$get_themes = $kernel->db->query( "SELECT t.theme_id, t.theme_name, t.theme_description FROM " . TABLE_PREFIX . "themes t ORDER BY t.theme_name" );
		
		$kernel->tp->call( "admin_theme_header" );
		
		while( $theme = $kernel->db->data( $get_themes ) )
		{
			$kernel->tp->call( "admin_theme_row" );
			
			$theme['theme_name'] = $kernel->format_input( $theme['theme_name'], T_NOHTML );
			
			if( $theme['theme_id'] == $kernel->config['default_skin'] AND $theme['theme_id'] == 1 )
			{
				$theme['theme_html_name'] = $kernel->page->string_colour( $theme['theme_name'], "orange" );
			}
			elseif( $theme['theme_id'] == $kernel->config['default_skin'] )
			{
				$theme['theme_html_name'] = $kernel->page->string_colour( $theme['theme_name'], "#33cc33" );
			}
			elseif( $theme['theme_id'] == 1 )
			{
				$theme['theme_html_name'] = $kernel->page->string_colour( $theme['theme_name'], "red" );
			}
			else
			{
				$theme['theme_html_name'] = $theme['theme_name'];
			}
			
			$theme['theme_description'] = $kernel->format_input( $theme['theme_description'], T_NOHTML );
			
			$kernel->tp->cache( $theme );
		}
		
		$kernel->tp->call( "admin_theme_footer" );
		
		break;
	}
}

?>

