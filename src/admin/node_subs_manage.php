<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'SCR_MAN', 'SCR_DEL' );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "subscription_id" => V_INT ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "subscription_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'SCR_MAN' );
		
		$kernel->page->message_report( $kernel->ld['phrase_subscription_no_guests'], M_NOTICE );
		
		$kernel->tp->call( "admin_subs_edit" );
		
		$subscription = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $kernel->vars['subscription_id'] . " LIMIT 1" );
		
		$subscription['subscription_name'] = $kernel->format_input( $subscription['subscription_name'], T_FORM );
		$subscription['subscription_description'] = $kernel->format_input( $subscription['subscription_description'], T_FORM );
		
		foreach( unserialize( $subscription['subscription_value'] ) AS $currency => $value )
		{
			$subscription_values[ "subscription_value_" . $currency ] = $value;
		}
		
		$kernel->page->construct_category_list( unserialize( $subscription['subscription_categories'] ) );
		
		$kernel->archive->construct_list_options( $subscription['subscription_length_span'], "subscription_span", array( "0" => $kernel->ld['phrase_days'], "1" => $kernel->ld['phrase_weeks'], "2" => $kernel->ld['phrase_months'], "3" => $kernel->ld['phrase_years'] ), false );
		
		$kernel->archive->construct_usergroup_options( unserialize( $subscription['subscription_usergroups'] ), $kernel->db->query( "SELECT `usergroup_id`, `usergroup_title` FROM `" . TABLE_PREFIX . "usergroups` WHERE `usergroup_id` > 1 ORDER BY `usergroup_title`" ), false, true );
		
		$kernel->page->construct_vars_flags( $subscription );
		
		$kernel->tp->cache( $subscription );
		$kernel->tp->cache( $subscription_values );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'SCR_MAN' );
		
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
				"subscription_disabled" => $kernel->vars['subscription_disabled']
			);
			
			$kernel->db->update( "subscriptions", $subscriptiondata, "WHERE `subscription_id` = " . $kernel->vars['subscription_id'] );
			
			$kernel->archive->update_database_counter( "subscriptions" );
			
			$kernel->admin->message_admin_report( "log_subscription_edited", $kernel->vars['subscription_name'] );
		}
		
		break;
	}
	
	#############################################################################
	
	case "delete" :
	{
		$kernel->admin->read_permission_flags( 'SCR_DEL' );
		
		$delete_count = 0;
		
		if( $kernel->vars['subscription_id'] > 0 )
		{
			$delete_data[] = $kernel->db->item( "SELECT `subscription_name` FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $kernel->vars['subscription_id'] );
			
			$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $kernel->vars['subscription_id'] );
			$delete_count++;
		}
		elseif( is_array( $_POST['checkbox'] ) )
		{
			foreach( $_POST['checkbox'] AS $subscription )
			{
				$delete_data[] = $kernel->db->item( "SELECT `subscription_name` FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $subscription );
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $subscription );
				$delete_count++;
			}
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
		}
		
		$kernel->archive->update_database_counter( "subscriptions" );
		
		$kernel->admin->message_admin_report( "log_subscription_deleted", $delete_count, $delete_data );
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$check_subscriptions = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "subscriptions` ORDER BY `subscription_id`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_subscriptions'], M_ERROR );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_subscriptions ) );
			
			$kernel->tp->call( "admin_subs_header" );
			
			$get_subscriptions = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "subscriptions` ORDER BY `subscription_id` LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			while( $subscription = $kernel->db->data( $get_subscriptions ) )
			{
				$kernel->tp->call( "admin_subs_row" );
				
				$subscription['subscription_name'] = $kernel->format_input( $subscription['subscription_name'], T_NOHTML );
				$subscription['subscription_description'] = $kernel->archive->return_string_words( $kernel->format_input( $subscription['subscription_description'], T_NOHTML ), $kernel->config['string_max_words'] );
				
				if( $subscription['subscription_disabled'] == 1 )
				{
					$subscription['subscription_html_name'] .= $kernel->page->string_colour( $subscription['subscription_name'], "#999999" );
				}
				else
				{
					$subscription['subscription_html_name'] .= $subscription['subscription_name'];
				}
				
				$kernel->tp->cache( $subscription );
			}
			
			$kernel->tp->call( "admin_subs_footer" );
			
			$kernel->page->construct_category_filters();
			
			$kernel->page->construct_pagination( array(), $kernel->config['admin_pagination_page_proximity'] );
		}
		
		break;
	}
}

?>

