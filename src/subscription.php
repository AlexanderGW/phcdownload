<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "id" => V_INT, "action" => V_STR, "currency" => V_STR, "gateway" => V_STR ) );

// No ID ref
if( $kernel->vars['id'] == 0 AND $kernel->vars['action'] != "return" )
{
	$kernel->page->message_report( $kernel->ld['phrase_no_subscription'], M_ERROR, HALT_EXEC );
}

switch( $kernel->vars['action'] )
{
	#############################################################################
	# Gateway forwarder for subscription
	
	case "forward" :
	{
		if( empty( $kernel->vars['currency'] ) OR empty( $kernel->vars['gateway'] ) )
		{
			header( "Location: subscription.php?id=" . $kernel->vars['id'] ); exit;
		}
		else
		{
			$fetch_subscription = $kernel->db->query( "SELECT `subscription_name`, `subscription_value` FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $kernel->vars['id'] );
			
			if( $kernel->db->numrows() > 0 )
			{
				$subscription = $kernel->db->row( $fetch_subscription );
				
				//Has a valid and supported currency been supplied for the subscription?
				$supported_currency = unserialize( $subscription['subscription_value'] );
				
				if( !isset( $supported_currency[ $kernel->vars['currency'] ] ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_invalid_subscription_currency'], M_ERROR, HALT_EXEC );
				}
					
				$fetch_payment_api = $kernel->db->query( "SELECT `api_currency_support`, `api_options` FROM `" . TABLE_PREFIX . "payments_api` WHERE `api_disabled` = 0 AND `api_class_name` = '" . $kernel->vars['gateway'] . "'" );
				
				if( $kernel->db->numrows() == 0 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_invalid_gateway'], M_ERROR, HALT_EXEC );
				}
				
				$api = $kernel->db->row( $fetch_payment_api );
				
				$api_supported_currency = array_flip( explode( ",", $api['api_currency_support'] ) );
				$api_options = unserialize( $api['api_options'] );
				
				if( !isset( $api_supported_currency[ $kernel->vars['currency'] ] ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_invalid_gateway_currency'], M_ERROR, HALT_EXEC );
				}
				
				$check_payments = $kernel->db->query( "SELECT `payment_id`, `payment_complete` FROM `" . TABLE_PREFIX . "payments` WHERE `payment_subscription_id` = " . $kernel->vars['id'] . " AND `payment_user_id` = " . $kernel->session->vars['session_user_id'] . " AND `payment_expire_timestamp` > " . UNIX_TIME );
				
				if( $kernel->db->numrows() > 0 )
				{
					$payment = $kernel->db->row( $check_payments );
					
					if( $payment['payment_complete'] == 1 )
					{
						header( "Location: index.php" ); exit;
					}
					
					$payment_id = $payment['payment_id'];
				}
				else
				{
					//Create payment reciept for when we return..
					$kernel->db->insert( "payments", array( "payment_subscription_id" => $kernel->vars['id'], "payment_user_id" => $kernel->session->vars['session_user_id'], "payment_timestamp" => UNIX_TIME ) );
					
					$payment_id = $kernel->db->insert_id();
				}
				//$kernel->subscription->api_vars['forward_url']
				//224935279815579
				$subscriptiondata = array(
					"api_primary_email_address" => $api_options['api_primary_email_address'],
					"subscription_form_action" => "https://sandbox.google.com/checkout/api/checkout/v2/checkoutForm/Merchant/224935279815579",
					"subscription_id" => $kernel->vars['id'],
					"subscription_payment_id" => $payment_id,
					"subscription_amount" => $supported_currency[ $kernel->vars['currency'] ],
					"subscription_currency" => strtoupper( $kernel->vars['currency'] ),
					"subscription_name" => $subscription['subscription_name'],
					"subscription_return_url" => $kernel->config['system_root_url_path'] . "/subscription.php?action=return&gateway=" . $kernel->vars['gateway'] . "&payment_id=" . $payment_id . "&user_id=" . $kernel->session->vars['session_user_id']
				);
				
				$kernel->subscription->construct_forwarder( $subscriptiondata );
			}
		}
		
		break;
	}
	
	#############################################################################
	# Gateway returned info, verify payment info and subscription.
	
	case "return" :
	{
		$kernel->clean_array( "_REQUEST", array( "payment_id" => V_INT, "user_id" => V_INT ) );
		
		if( empty( $_POST ) OR $kernel->vars['payment_id'] == 0 OR $kernel->vars['user_id'] == 0 OR empty( $kernel->vars['gateway'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_subscription'], M_ERROR, HALT_EXEC );
		}
		
		$transaction_state = array( "pending", "completed", "held", "denied", "cancelled", "reversed" );
		
		$kernel->subscription->verify_transaction();
		
		//State of the transaction has changed, do we need to notify?
		if( $kernel->subscription->state_update == true )
		{
			$subscription = $kernel->db->row( "SELECT s.subscription_name, s.subscription_length, s.subscription_length_span, t.transaction_id, t.transaction_api_tx_id, t.transaction_subscription_id, u.user_name, u.user_email, a.api_name FROM " . TABLE_PREFIX . "transactions t LEFT JOIN " . TABLE_PREFIX . "subscriptions s ON ( t.transaction_subscription_id = s.subscription_id ) LEFT JOIN " . TABLE_PREFIX . "users u ON ( t.transaction_user_id = u.user_id ) LEFT JOIN " . TABLE_PREFIX . "payments_api a ON ( t.transaction_api_id = a.api_id ) WHERE t.transaction_id = " . $kernel->subscription->transaction_id );
			
			$emaildata = array(
				"subscription_name" => $subscription['subscription_name'],
				"transaction_state" => $kernel->ld['phrase_' . $transaction_state[ $kernel->subscription->state ] ],
				"transaction_id" => $subscription['transaction_id'],
				"transaction_api_id" => $subscription['transaction_api_tx_id'],
				"api_name" => $subscription['api_name'],
				"user_name" => $subscription['user_name'],
				"archive_title" => $kernel->config['archive_name']
			);
			
			$kernel->archive->construct_send_email( "subscription_admin_notification", $kernel->config['mail_inbound'], $emaildata );
			
			$kernel->archive->construct_send_email( "subscription_user_notification", $subscription['user_email'], $emaildata );
			
			//Update payment info
			$paymentdata = array( "payment_complete" => 1 );
			
			//calc the expire date according to day, week etc..
			$span_multiplier = array( 86400, 604800, 2592000, 31536000 );
			
			if( $kernel->subscription->state == 1 )
			{
				$paymentdata['payment_timestamp'] = UNIX_TIME;
				$paymentdata['payment_expire_timestamp'] = UNIX_TIME + ( $subscription['subscription_length'] * $span_multiplier[ $subscription['subscription_length_span'] ] );
			}
			
			$kernel->db->update( "payments", $paymentdata, "WHERE `payment_id` = " . $kernel->vars['payment_id'] );
		}
		
		//Completed
		if( $kernel->subscription->state == 1 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_subscription_thank_you_complete'], M_NOTICE );
		}
		
		//Pending
		elseif( $kernel->subscription->state == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_subscription_pending_complete'], M_NOTICE );
		}
		
		//Reversed
		elseif( $kernel->subscription->state == 5 )
		{
			$kernel->db->update( "payments", array( "payment_expire_timestamp" => UNIX_TIME ), "WHERE `payment_id` = " . $kernel->vars['payment_id'] );
			
			$kernel->page->message_report( $kernel->ld['phrase_subscription_payment_reversed'], M_NOTICE );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		if( empty( $kernel->vars['currency'] ) )
		{
			$kernel->subscription->construct_subscriptions( array( $kernel->vars['id'] ) );
		}
		else
		{
			$total_gateways = 0;
			$time_span_phrases = array( "day", "week", "month", "year" );
			
			$subscription = $kernel->db->row( "SELECT `subscription_id`, `subscription_name`, `subscription_value`, `subscription_length`, `subscription_length_span` FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $kernel->vars['id'] );
			
			$subscription_costs = unserialize( $subscription['subscription_value'] );
			
			$fetch_paymentapi = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "payments_api` WHERE `api_disabled` = 0 ORDER BY `api_name`" );
			
			while( $api = $kernel->db->data( $fetch_paymentapi ) )
			{
				$supported_currency = array_flip( explode( ",", $api['api_currency_support'] ) );	
				
				if( !isset( $supported_currency[ $kernel->vars['currency'] ] ) ) continue;
				
				$subscription_method_options .= $kernel->tp->call( "subscription_gateway_option", CALL_TO_PAGE );
				$subscription_method_options = $kernel->tp->cache( $subscription, 0, $subscription_method_options );
				$subscription_method_options = $kernel->tp->cache( $api, 0, $subscription_method_options );
				
				$total_gateways++;
			}
			
			//None of the gateways support the specified currency
			if( $total_gateways == 0 )
			{
				$kernel->page->message_report( $kernel->ld['phrase_subscription_no_gateway_support_currency'], M_ERROR, HALT_EXEC );
			}
			
			$api['subscription_method_options'] = $subscription_method_options;
			
			$kernel->tp->call( "subscription_gateway_select" );
			
			if( $subscription['subscription_length'] == 1 )
			{
				$length_phrase = $time_span_phrases[ $subscription['subscription_length_span'] ];
			}
			else
			{
				$length_phrase = $time_span_phrases[ $subscription['subscription_length_span'] ] . "s";
			}
			
			$subscription['subscription_cost'] = $subscription_costs[ $kernel->vars['currency'] ];
			$subscription['subscription_currency'] = $kernel->ld[ "phrase_currency_" . $kernel->vars['currency'] ];
			$subscription['subscription_length'] = sprintf( $kernel->ld[ "phrase_" . $length_phrase ], $subscription['subscription_length'] );
			
			$kernel->tp->cache( $subscription );
			$kernel->tp->cache( $api );
		}
		
		break;
	}
}

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>