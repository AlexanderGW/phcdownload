<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'FLD_MAN', 'FLD_DEL' );

$kernel->clean_array( "_REQUEST", array( "field_id" => V_INT ) );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'FLD_MAN' );
		
		$kernel->tp->call( "admin_fiel_edit" );
		
		$field = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "fields` WHERE `field_id` = " . $kernel->vars['field_id'] . " LIMIT 1" );
		
		$kernel->archive->construct_list_options( $field['field_type'], "field_type", array( $kernel->ld['phrase_field_type_single'], $kernel->ld['phrase_field_type_multi'], $kernel->ld['phrase_field_type_option'] ), false );
		$kernel->archive->construct_list_options( $field['field_data_rule'], "field_data_rule", array( $kernel->ld['phrase_field_option_optional'], $kernel->ld['phrase_field_option_required_mixed'], $kernel->ld['phrase_field_option_required_integer'] ), false );
		
		$kernel->vars['html']['field_category_status'] = ( $field['field_category_display'] == 1 ) ? "checked=\"checked\"" : "";
		$kernel->vars['html']['field_file_disabled'] = ( $field['field_file_display'] == 1 ) ? "checked=\"checked\"" : "";
		$kernel->vars['html']['field_submit_status'] = ( $field['field_submit_display'] == 1 ) ? "checked=\"checked\"" : "";
		
		$field['field_name'] = $kernel->format_input( $field['field_name'], T_FORM );
		$field['field_description'] = $kernel->format_input( $field['field_description'], T_FORM );
		
		$kernel->vars['html'][ "field_data_rule_" . $field['field_data_rule'] ] = "checked=\"checked\"";
		
		$kernel->tp->cache( $field );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'FLD_MAN' );
		
		$kernel->clean_array( "_REQUEST", array( "field_category_display" => V_INT,
		"field_file_display" => V_INT, "field_submit_display" => V_INT, "field_type" => V_INT,
		"field_name" => V_STR, "field_description" => V_STR, "field_options" => V_STR,
		"field_data_rule" => V_INT ) );
		
		$fielddata = array(
			"field_type" => $kernel->vars['field_type'],
			"field_category_display" => $kernel->vars['field_category_display'],
			"field_file_display" => $kernel->vars['field_file_display'],
			"field_submit_display" => $kernel->vars['field_submit_display'],
			"field_name" => $kernel->format_input( $kernel->vars['field_name'], T_DB ),
			"field_description" => $kernel->format_input( $kernel->vars['field_description'], T_DB ),
			"field_options" => $kernel->format_input( $kernel->vars['field_options'], T_DB ),
			"field_data_rule" => $kernel->vars['field_data_rule']
		);
		
		$kernel->db->update( "fields", $fielddata, "WHERE `field_id` = " . $kernel->vars['field_id'] );
		
		$kernel->archive->update_database_counter( "fields" );
		
		$kernel->admin->message_admin_report( "log_custom_field_edited", $kernel->vars['field_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "delete" :
	{
		$kernel->admin->read_permission_flags( 'FLD_DEL' );
		
		$delete_count = 0;
		
		if( $kernel->vars['field_id'] > 0 )
		{
			$delete_data[] = $kernel->db->item( "SELECT `field_name` FROM `" . TABLE_PREFIX . "fields` WHERE `field_id` = " . $kernel->vars['field_id'] );
			
			$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "fields` WHERE `field_id` = " . $kernel->vars['field_id'] );
			$delete_count++;
		}
		elseif( is_array( $_POST['checkbox'] ) )
		{
			foreach( $_POST['checkbox'] AS $field )
			{
				$delete_data[] = $kernel->db->item( "SELECT `field_name` FROM `" . TABLE_PREFIX . "fields` WHERE `field_id` = " . $field );
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "fields` WHERE `field_id` = " . $field );
				$delete_count++;
			}
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
		}
		
		$kernel->archive->update_database_counter( "fields" );
		
		$kernel->admin->message_admin_report( "log_custom_field_deleted", $delete_count, $delete_data );
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$get_fields = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "fields` ORDER BY `field_id`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_custom_fields'], M_ERROR );
		}
		else
		{
			$kernel->tp->call( "admin_fiel_header" );
			
			while( $field = $kernel->db->data( $get_fields ) )
			{
				$kernel->tp->call( "admin_fiel_row" );
				
				$field['field_description'] = $kernel->format_input( $field['field_description'], T_NOHTML );
				$field['field_name'] = $kernel->format_input( $field['field_name'], T_NOHTML );
				
				$field['field_category_state'] = ( $field['field_category_display'] == 1 ) ? $kernel->admin->construct_icon( "tick.gif", $kernel->ld['phrase_yes'], true, "middle" ) : $kernel->admin->construct_icon( "delete.gif", $kernel->ld['phrase_no'], true, "middle" );
				$field['field_file_state'] = ( $field['field_file_display'] == 1 ) ? $kernel->admin->construct_icon( "tick.gif", $kernel->ld['phrase_yes'], true, "middle" ) : $kernel->admin->construct_icon( "delete.gif", $kernel->ld['phrase_no'], true, "middle" );
				$field['field_submit_state'] = ( $field['field_submit_display'] == 1 ) ? $kernel->admin->construct_icon( "tick.gif", $kernel->ld['phrase_yes'], true, "middle" ) : $kernel->admin->construct_icon( "delete.gif", $kernel->ld['phrase_no'], true, "middle" );
				
				if( $field['field_type'] == 0 )
				{
					$field['field_html_name'] = $kernel->admin->construct_icon( "light_amber.gif", $kernel->ld['phrase_colour_indicator_single_field'], true );
				}
				elseif( $field['field_type'] == 1 )
				{
					$field['field_html_name'] = $kernel->admin->construct_icon( "light_green.gif", $kernel->ld['phrase_colour_indicator_multi_field'], true );
				}
				elseif( $field['field_type'] == 2 )
				{
					$field['field_html_name'] = $kernel->admin->construct_icon( "light_red.gif", $kernel->ld['phrase_colour_indicator_option_field'], true );
				}
				
				$field['field_html_name'] .= $field['field_name'];
				
				$kernel->tp->cache( $field );
			}
			
			$kernel->tp->call( "admin_fiel_footer" );
		}
		
		break;
	}
}

?>

