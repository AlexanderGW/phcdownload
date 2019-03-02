<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'FLD_ADD' );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "field_type" => V_STR, "field_category_display" => V_INT,
		"field_file_display" => V_INT, "field_submit_display" => V_INT, "field_name" => V_STR,
		"field_description" => V_STR, "field_options" => V_STR, "field_data_rule" => V_STR ) );
		
		if( empty( $kernel->vars['field_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_custom_field_name'], M_ERROR );
		}
		else
		{
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
			
			$kernel->db->insert( "fields", $fielddata );
			
			$kernel->archive->update_database_counter( "fields" );
			
			$kernel->admin->message_admin_report( "log_custom_field_added", $kernel->vars['field_name'] );
		}
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->archive->construct_list_options( 0, "field_type", array( $kernel->ld['phrase_field_type_single'], $kernel->ld['phrase_field_type_multi'], $kernel->ld['phrase_field_type_option'] ), false );
		$kernel->archive->construct_list_options( $field['field_data_rule'], "field_data_rule", array( $kernel->ld['phrase_field_option_optional'], $kernel->ld['phrase_field_option_required_mixed'], $kernel->ld['phrase_field_option_required_integer'] ), false );
		
		$kernel->tp->call( "admin_fiel_add" );
	}
}

?>

