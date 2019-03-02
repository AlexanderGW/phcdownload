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

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "template_id" => V_INT ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";

switch( $kernel->vars['action'] )
{
	
	#############################################################################
	
	case "edit" :
	{
		$template = $kernel->db->row( "SELECT `template_name`, `template_data`, `template_subject` FROM `" . TABLE_PREFIX . "templates_email` WHERE `template_id` = " . $kernel->vars['template_id'] . " LIMIT 1" );
		
		$kernel->tp->call( "admin_email_edit" );
		
		$template['template_name'] = $kernel->format_input( $template['template_name'], T_FORM );
		
		$template['template_subject'] = htmlspecialchars( $template['template_subject'] );
		$template['template_subject'] = str_replace( chr( 36 ), "&#36;", $template['template_subject'] );
		
		$template['template_data'] = htmlspecialchars( $template['template_data'] );
		$template['template_data'] = str_replace( chr( 36 ), "&#36;", $template['template_data'] );
		
		$kernel->tp->cache( $template );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->clean_array( "_REQUEST", array( "template_id" => V_INT, "template_name" => V_STR, "template_subject" => V_STR, "template_data" => V_STR ) );
		
		$kernel->vars['template_data'] = str_replace( "&#36;", chr( 36 ), $kernel->vars['template_data'] );
		
		$templatedata = array(
		  "template_name" => $kernel->format_input( $kernel->vars['template_name'], T_DB ),
		  "template_subject" => $kernel->format_input( $kernel->vars['template_subject'], T_DB ),
		  "template_data" => $kernel->format_input( $_POST['template_data'], T_STRIP ),
		  "template_author" => $kernel->format_input( $kernel->session->vars['adminsession_name'], T_DB ),
		  "template_timestamp" => UNIX_TIME
		);
		
		//put the first entered data as the original
		$check_original = $kernel->db->row( "SELECT `template_original` FROM `" . TABLE_PREFIX . "templates_email` WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		if( empty( $check_original['template_original'] ) )
		{
			$templatedata['template_original'] = $kernel->format_input( $kernel->vars['template_data'], T_STRIP );
		}
		
		//original data backup
		$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "templates_email` SET `template_data_bak` = '" . addslashes( $kernel->vars['template_data'] ) . "' WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$kernel->db->update( "templates_email", $templatedata, "WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$kernel->archive->update_database_counter( "templates_email" );
		
		$kernel->admin->message_admin_report( "log_email_templates_edited", $kernel->vars['template_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "rollback" :
	{
		$template = $kernel->db->row( "SELECT `template_name`, `template_data`, `template_data_bak` FROM `" . TABLE_PREFIX . "templates_email` WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$templatedata = array(
			"template_data" => $template['template_data_bak'],
			"template_data_bak" => $template['template_data']
		);
		
		$kernel->db->update( "templates_email", $templatedata, "WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$kernel->admin->message_admin_report( "log_template_rolled_back", $template['template_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "revert" :
	{
		$template = $kernel->db->row( "SELECT `template_name`, `template_data`, `template_original` FROM `" . TABLE_PREFIX . "templates_email` WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$templatedata = array(
			"template_data" => $template['template_original'],
			"template_original" => $template['template_data']
		);
		
		$kernel->db->update( "templates_email", $templatedata, "WHERE `template_id` = " . $kernel->vars['template_id'] );
		
		$kernel->admin->message_admin_report( "log_template_reverted", $template['template_name'] );
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$check_templates = $kernel->db->query( "SELECT `template_id` FROM `" . TABLE_PREFIX . "templates_email` ORDER BY `template_id`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_templates'], M_ERROR );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_templates ) );
			$last_type = 0;
			
			$kernel->tp->call( "admin_email_header" );
			
			$get_templates = $kernel->db->query( "SELECT `template_id`, `template_type`, `template_name`, `template_data_bak`, `template_original`, `template_author`, `template_timestamp` FROM `" . TABLE_PREFIX . "templates_email` ORDER BY `template_type`, `template_id` LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			while( $template = $kernel->db->data( $get_templates ) )
			{
				if( $last_type == 0 AND $template['template_type'] == 1 )
				{
					$kernel->tp->call( "admin_email_row_break" );
				}
				$last_type = $template['template_type'];	
				
				$kernel->tp->call( "admin_email_row" );
				
				//setup user action button states
				$template['template_option_rollback'] = $kernel->admin->button_status_if( ( empty( $template['template_data_bak'] ) ), $template['template_id'], "rollback", $kernel->ld['phrase_undo_changes'], "?hash=" . $kernel->session->vars['hash'] . "&node=email&template_id=" . $template['template_id'] . "&action=rollback" );
				$template['template_option_revert'] = $kernel->admin->button_status_if( ( $template['template_data'] == $template['template_original'] ), $template['template_id'], "revert", $kernel->ld['phrase_revert_original'], "?hash=" . $kernel->session->vars['hash'] . "&node=email&template_id=" . $template['template_id'] . "&action=revert" );
				
				$template['template_name'] = $kernel->format_input( $template['template_name'], T_PREVIEW );
				$template['template_description'] = $kernel->format_input( $kernel->ld[ 'phrase_template_' . $template['template_name'] . '_desc' ], T_HTML );
				$template['template_timestamp'] = $kernel->fetch_time( $template['template_timestamp'], DF_SHORT );
				
				if( $template['template_type'] == 0 )
				{
					$template['template_html_name'] = $kernel->admin->construct_icon( "user_admin.gif", $kernel->ld['phrase_administrator_template'], true );
				}
				else
				{
					$template['template_html_name'] = $kernel->admin->construct_icon( "user_regular.gif", $kernel->ld['phrase_user_template'], true );
				}
				
				$template['template_html_name'] .= $template['template_name'];
				
				$kernel->tp->cache( $template );
			}
			
			$kernel->tp->call( "admin_email_footer" );
			
			$kernel->page->construct_category_filters();
			
			$kernel->page->construct_pagination( array(), $kernel->config['admin_pagination_page_proximity'] );
		}
		
		break;
	}
}

?>

