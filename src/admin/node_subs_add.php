<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'SCR_ADD' );

switch( $kernel->vars['action'] )
{
	#############################################################################

	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "subscription_name" => V_STR, "subscription_description" => V_STR, "subscription_value" => V_ARRAY,
			"subscription_length" => V_STR, "subscription_length_span" => V_STR, "subscription_categories" => V_ARRAY, "subscription_usergroups" => V_ARRAY, "subscription_timestamp" => V_INT,
			"subscription_disabled" => V_INT
		) );
		
		if( empty( $kernel->vars['subscription_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_subscription_name'], M_ERROR );
		}
		elseif( empty( $kernel->vars['subscription_length'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_subscription_length'], M_ERROR );
		}
		elseif( array_sum( $kernel->vars['subscription_value'] ) == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_subscription_value'], M_ERROR );
		}
		else
		{
			$subscriptiondata = array(
				"subscription_name" => $kernel->format_input( $kernel->vars['subscription_name'], T_DB ),
				"subscription_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['subscription_description'], T_DB ), $kernel->config['string_max_word_length'] ),
				"subscription_value" => $kernel->format_input( serialize( $kernel->vars['subscription_value'] ), T_DB ),
				"subscription_length" => $kernel->format_input( $kernel->vars['subscription_length'], T_DB ),
				"subscription_length_span" => $kernel->format_input( $kernel->vars['subscription_length_span'], T_DB ),
				"subscription_categories" => $kernel->format_input( serialize( $kernel->vars['subscription_categories'] ), T_DB ),
				"subscription_usergroups" => $kernel->format_input( serialize( $kernel->vars['subscription_usergroups'] ), T_DB ),
				"subscription_timestamp" => UNIX_TIME,
				"subscription_disabled" => 0
			);
			
			$kernel->db->insert( "subscriptions", $subscriptiondata );
			
			$kernel->archive->update_database_counter( "subscriptions" );
			
			$kernel->admin->message_admin_report( "log_subscription_added", $kernel->vars['subscription_name'] );
		}
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->page->message_report( $kernel->ld['phrase_subscription_no_guests'], M_NOTICE );
		
		$kernel->tp->call( "admin_subs_add" );
		
		$kernel->page->construct_category_list();
		
		$kernel->archive->construct_list_options( 0, "subscription_span", array( "0" => $kernel->ld['phrase_days'], "1" => $kernel->ld['phrase_weeks'], "2" => $kernel->ld['phrase_months'], "3" => $kernel->ld['phrase_years'] ), false );
		
		$kernel->archive->construct_usergroup_options( 0, $kernel->db->query( "SELECT `usergroup_id`, `usergroup_title` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` > 1 ORDER BY `usergroup_title`" ), false, true );
		
		break;
	}
}

?>

