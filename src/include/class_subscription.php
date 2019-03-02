<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and use.
################################################################################

class class_subscription
{
	var $api; //Loaded payment gateway class
	var $transaction_id = 0;
	
	/*
	 * Construct subscription with available payment gateways
	 **/
	
	function init_category_subscriptions( $category_id, $http_referer )
	{
		global $kernel;
		
		$available_subscriptions = $tree_categories = array();
		
		//Let admins slide
		if( $kernel->session->vars['session_group_id'] == 1 ) return true;
		
		//list higher level categories.
		$read_id = $category_id;
		
		while( $read_id > 0 ) 
		{
			$category = $kernel->db->row( "SELECT `category_id`, `category_sub_id` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $read_id );
			
			$read_id = $category['category_sub_id'];
			
			$tree_categories[] = $category['category_id'];
		}
		
		//higher level categories for subscription checking.
		if( count( $tree_categories ) > 0 )
		{
			$tree_categories = @array_reverse( $tree_categories );
		
			foreach( $tree_categories AS $read_id )
			{
				$fetch_subscription = $kernel->db->query( "SELECT `subscription_id`, `subscription_categories`, `subscription_usergroups` FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_categories` LIKE '%\"" . $read_id . "\"%'" );
				
				if( $kernel->db->numrows( $fetch_subscription ) > 0 )
				{
					//Send all guests to login
					if( $kernel->session->vars['session_group_id'] < 0 )
					{
						$kernel->session->construct_user_login( $kernel->ld['phrase_subscription_require_login'] ); exit;
					}
					
					while( $subscription = $kernel->db->data( $fetch_subscription ) )
					{
						if( strpos( $subscription['subscription_usergroups'], '"' . $kernel->session->vars['session_group_id'] . '"' ) !== false )
						{
							$fetch_payment = $kernel->db->query( "SELECT `payment_expire_timestamp`, `payment_complete` FROM `" . TABLE_PREFIX . "payments` WHERE `payment_subscription_id` = " . $subscription['subscription_id'] . " AND `payment_user_id` = " . $kernel->session->vars['session_user_id'] . " ORDER BY `payment_timestamp` DESC LIMIT 1" );
							
							if( $kernel->db->numrows( $fetch_payment ) > 0 )
							{
								$payment = $kernel->db->row( $fetch_payment );
								
								//A transaction on a subscription for user already exists, check it.
								if( $payment['payment_complete'] == 1 )
								{
									$transaction_state = $kernel->db->item( "SELECT `transaction_state` FROM `" . TABLE_PREFIX . "transactions` WHERE `transaction_subscription_id` = " . $subscription['subscription_id'] . " AND `transaction_user_id` = " . $kernel->session->vars['session_user_id'] . " ORDER BY `transaction_timestamp` DESC LIMIT 1" );
									
									if( $transaction_state <> 1 )
									{
										$this->state_notification( $transaction_state );
									}
									elseif( $transaction_state == 1 AND $payment['payment_expire_timestamp'] <= UNIX_TIME )
									{
										$available_subscriptions[] = $subscription['subscription_id'];
									}
									else break;
								}
								else
								{
									header( "Location: subscription.php?id=" . $subscription['subscription_id'] ); exit;
								}
							}
							else
							{
								$available_subscriptions[] = $subscription['subscription_id'];
							}
						}
					}
					
					//Break after finding highest level subscriptions.
					if( count( $available_subscriptions ) > 0 ) break;
				}
			}
		}
		
		//Available subscriptions
		if( sizeof( $available_subscriptions ) > 0 )
		{
			$this->construct_subscriptions( $available_subscriptions );
			
			$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );
		}
		
		return true;
	}
	
	/*
	 * Construct subscription selected gateway forwarder
	 **/
	
	function construct_forwarder( $subscription_id, $gateway, $currency )
	{
		if( !is_subclass_of( $this, class_gateway ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_error_loading_gateway_api'], M_ERROR );
		}
	}
	
	/*
	 * Send transaction information to gateway provider for verification
	 **/
	
	function verify_transaction( $api_name, $subscription_id )
	{
		global $kernel;
		
		if( !is_subclass_of( $this, class_gateway ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_error_loading_gateway_api'], M_ERROR );
		}
	}
	
	/*
	 * Transaction state returned by the gateway verification
	 **/
	
	function state_notification( $state_id )
	{
		global $kernel;
		
		if( $state_id == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_pending'], M_NOTICE, HALT_EXEC );
		}
		elseif( $state_id == 2 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_held'], M_NOTICE, HALT_EXEC );
		}
		elseif( $state_id == 3 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_denied'], M_NOTICE, HALT_EXEC );
		}
		elseif( $state_id == 4 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_cancelled'], M_NOTICE, HALT_EXEC );
		}
		elseif( $state_id == 5 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_reversed'], M_NOTICE, HALT_EXEC );
		}
	}
	
	/*
	 * List available subscriptions submitted via $subscription array
	 **/
	
	function construct_subscriptions( $subscription = array() )
	{
		global $kernel;
		
		if( sizeof( $subscription ) > 0 )
		{
			$kernel->tp->call( "subscriptions_header" );
			
			foreach( $subscription AS $subscription_id )
			{
				$subscription = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $subscription_id . " AND `subscription_disabled` = 0" );
				
				$time_span_phrases = array( $kernel->ld['phrase_single_day'], $kernel->ld['phrase_single_week'], $kernel->ld['phrase_single_month'], $kernel->ld['phrase_single_year'] );
				
				if( $subscription['subscription_length'] == 1 )
				{
					$length_phrase = $time_span_phrases[ $subscription['subscription_length_span'] ];
				}
				else
				{
					$length_phrase = $time_span_phrases[ $subscription['subscription_length_span'] ] . "s";
				}
				
				$subscription['subscription_length'] = sprintf( $kernel->ld[ "phrase_" . $length_phrase ], $subscription['subscription_length'] );
				$subscription['subscription_length'] = sprintf( $kernel->ld['phrase_subscription_length_x'], $subscription['subscription_length'] );
				
				$subscription['subscription_name'] = $kernel->format_input( $subscription['subscription_name'], T_HTML );
				$subscription['subscription_description'] = $kernel->format_input( $subscription['subscription_description'], T_HTML );
				$subscription['subscription_timestamp'] = $kernel->fetch_time( $subscription['subscription_timestamp'], DF_SHORT );
				$subscription['subscription_recurring'] = "&nbsp;";
				
				$subscription_categories = array();
				
				foreach( unserialize( $subscription['subscription_categories'] ) AS $category_id )
				{
					$subscription_categories[] = $kernel->db->item( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $category_id );
				}
				$subscription['subscription_categories'] = sprintf( $kernel->ld['phrase_subscription_access_x'], implode( ", ", $subscription_categories ) );
				
				$subscription_costs = "";
				
				foreach( unserialize( $subscription['subscription_value'] ) AS $currency => $value )
				{
					if( $value > 0 ) $subscription_costs[ "$currency" ] = $value . " " . $kernel->ld[ 'phrase_subscription_currency_' . $currency ];
				}
				$subscription['subscription_cost_list_options'] = $kernel->archive->construct_list_options( "", "", $subscription_costs, false );
				
				$kernel->tp->call( "subscriptions_item" );
				
				$kernel->tp->cache( $subscription );
			}
			
			$kernel->tp->call( "subscriptions_footer" );
		}
	}
}

?>