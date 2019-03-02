<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation transaction
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( -1 );

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "filter" => V_STR, "state" => V_STR, "gateway" => V_STR, "transaction_id" => V_INT ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";
if( empty( $kernel->vars['filter'] ) ) $kernel->vars['filter'] = "today";

if( $kernel->vars['action'] != "detail" )
{
	$kernel->tp->call( "admin_trans_logs" );
}

$transaction_states = array(
	0 => $kernel->ld['phrase_pending'],
	1 => $kernel->ld['phrase_completed'],
	2 => $kernel->ld['phrase_held'],
	3 => $kernel->ld['phrase_denied'],
	4 => $kernel->ld['phrase_cancelled'],
	5 => $kernel->ld['phrase_reversed']
);

//build date filters
$kernel->archive->construct_date_list_options( $kernel->vars['filter'] );

$kernel->archive->construct_list_options( $kernel->vars['state'], "state", $transaction_states, false );
$kernel->vars['html']['state_list_options'] = "<option value=\"\">" . $kernel->ld['phrase_all_states'] . "</option>" . $kernel->vars['html']['state_list_options'];

$kernel->archive->construct_list_options( $kernel->vars['gateway'], "gateway", $kernel->db->query( "SELECT `api_id`, `api_name` FROM `" . TABLE_PREFIX . "payments_api` ORDER BY `api_name` ASC" ), false );
$kernel->vars['html']['gateway_list_options'] = "<option value=\"\">" . $kernel->ld['phrase_transaction_all_gateways'] . "</option>" . $kernel->vars['html']['gateway_list_options'];

switch( $kernel->vars['action'] )
{
	#############################################################################

	case "detail" :
	{
		$fetch_transaction = $kernel->db->query( "SELECT t.*, s.subscription_name, a.api_name FROM " . TABLE_PREFIX . "transactions t LEFT JOIN " . TABLE_PREFIX . "subscriptions s ON ( t.transaction_subscription_id = s.subscription_id ) LEFT JOIN " . TABLE_PREFIX . "payments_api a ON ( t.transaction_api_id = a.api_id ) WHERE `transaction_id` = " . $kernel->vars['transaction_id'] );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_transaction_no_exist'], M_ERROR );
		}
		else
		{
			$transaction = $kernel->db->row( $fetch_transaction );
			
			$kernel->tp->call( "admin_trans_detail_view" );
			
			$transaction['transaction_timestamp'] = $kernel->fetch_time( $transaction['transaction_timestamp'], DF_LONG );
			$transaction['transaction_author'] = $kernel->db->item( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $transaction['transaction_user_id'] );
			$transaction['transaction_html_currency'] = strtoupper( $transaction['transaction_currency'] ) . "&nbsp;<img src=\"../images/flag_" . $transaction['transaction_currency'] . ".gif\" border=\"0\" alt=\"" . $kernel->ld['phrase_subscription_currency_' . $transaction['transaction_currency'] ] . "\" />&nbsp;";
			
			$transaction['transaction_state'] = $transaction_states[ $transaction['transaction_state'] ];
			
			$transaction_raw_data = unserialize( $transaction['transaction_string'] );
			$transaction['transaction_string'] = "";
			
			if( $transaction['transaction_gateway'] == 1 ) //paypal
			{
				$transaction['transaction_state_reason'] = $transaction_raw_data['pending_reason'];
			}
			else
			{
				$transaction['transaction_state_reason'] = "";
			}
			
			foreach( $transaction_raw_data AS $key => $value )
			{
				$transaction['transaction_string'] .= "<b>" . $key . ":</b>&nbsp;" . $value . "<br />";
			}
			
			$kernel->tp->cache( $transaction );
		}
		
		break;
	}
	
	#############################################################################

	case "view" :
	{
		$filter_query = $kernel->archive->construct_db_timestamp_filter( $kernel->vars['filter'], "`transaction_timestamp`" );
		
		if( $kernel->vars['filter'] == "today" OR intval( $kernel->vars['filter'] ) <> 0 AND strlen( intval( $kernel->vars['filter'] ) ) === strlen( $kernel->vars['filter'] ) )
		{
			$kernel->vars['html']['search_filter'] = date( "Y M d", $filter_query[1] );
		}
		else
		{
			$kernel->vars['html']['search_filter'] = date( "Y M d", $filter_query[1] ) . " to " . date( "Y M d", $filter_query[2] );
		}
		
		if( $kernel->vars['state'] != "" )
		{
			$state_code = "AND `transaction_state` = " . $kernel->vars['state'];
		}
		
		if( !empty( $kernel->vars['gateway'] ) )
		{
			$gateway_code = "AND `transaction_api_id` = " . $kernel->vars['gateway'];
		}
		
		$check_transactions = $kernel->db->query( "SELECT `transaction_id` FROM `" . TABLE_PREFIX . "transactions` WHERE " . $filter_query[0] . " " . $state_code . " " . $gateway_code . " ORDER BY `transaction_id` DESC" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( sprintf( $kernel->ld['phrase_no_transactions_found'], $kernel->vars['html']['search_filter'] ), M_ERROR );
		}
		else
		{
			$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_transactions ) );
			
			$kernel->tp->call( "admin_trans_log_header" );
			
			$get_transaction_entries = $kernel->db->query( "SELECT t.transaction_id, t.transaction_api_tx_id, t.transaction_user_id, t.transaction_subscription_id, t.transaction_state, t.transaction_timestamp, s.subscription_name, a.api_name FROM " . TABLE_PREFIX . "transactions t LEFT JOIN " . TABLE_PREFIX . "subscriptions s ON ( t.transaction_subscription_id = s.subscription_id ) LEFT JOIN " . TABLE_PREFIX . "payments_api a ON ( t.transaction_api_id = a.api_id ) WHERE " . $filter_query[0] . " " . $state_code . " " . $gateway_code . " ORDER BY `transaction_id` DESC LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'] );
			
			while( $transaction = $kernel->db->data( $get_transaction_entries ) )
			{
				$kernel->tp->call( "admin_trans_log_row" );
				
				$transaction['transaction_timestamp'] = $kernel->fetch_time( $transaction['transaction_timestamp'], DF_LONG );
				$transaction['transaction_author'] = $kernel->db->item( "SELECT `user_name` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $transaction['transaction_user_id'] );
				$transaction['transaction_state'] = $transaction_states[ $transaction['transaction_state'] ];
				
				$kernel->tp->cache( $transaction );
			}
			
			$kernel->tp->call( "admin_trans_log_footer" );
			
	 		$kernel->page->construct_pagination( array( 'action' => 'view', 'filter' => $kernel->vars['filter'], 'state' => $kernel->vars['state'], 'gateway' => $kernel->vars['gateway'] ), $kernel->config['admin_pagination_page_proximity'] );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		//Latest 30 transactions?
		break;
	}
}

?>

