<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "id" => V_INT, "node" => V_STR, "action" => V_STR ) );

//----------------------------------
// Not logged in
//----------------------------------

if( empty( $kernel->session->vars['session_hash'] ) OR USER_LOGGED_IN == false )
{
	$kernel->session->construct_user_login( $kernel->ld['phrase_no_session'] );
}

//----------------------------------
// Fetch panel main template
//----------------------------------

$kernel->tp->call( "user_panel_header" );

switch( $kernel->vars['node'] )
{
	#############################################################################
	
	case "profile" :
	{
		switch( $kernel->vars['action'] )
		{
			#############################################################################
			# Update profile information
			#############################################################################
			
		 	case "update" :
			{
				$kernel->clean_array( "_POST", array( "user_name" => V_STR, "user_email" => V_STR, "user_password" => V_STR, "user_password_confirm" => V_STR ) );
				
				$user = $kernel->db->row( "SELECT `user_name`, `user_email` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $kernel->session->vars['session_user_id'] );
				
				//----------------------------------
				// No username
				//----------------------------------
				
				if( empty( $kernel->vars['user_name'] ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_register_no_user'], M_ERROR );
				}
				
				//----------------------------------
				// Invalid e-mail address
				//----------------------------------
				
				elseif( empty( $kernel->vars['user_email'] ) OR !preg_match( "/^[\w-]+(\.[\w-]+)*@([0-9a-z][0-9a-z-]*[0-9a-z]\.)+([a-z]{2,6})$/i", strtolower( $kernel->vars['user_email'] ) ) )
				{
					$kernel->page->message_report( $kernel->ld['phrase_register_invalid_email'], M_ERROR );
				}
				
				//----------------------------------
				// Password too short
				//----------------------------------
				
				elseif( !empty( $kernel->vars['user_password'] ) AND strlen( $kernel->vars['user_password'] ) < 4 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_register_invalid_password'], M_ERROR );
				}
				
				//----------------------------------
				// Password mismatch
				//----------------------------------
				
				elseif( !empty( $kernel->vars['user_password'] ) AND $kernel->vars['user_password'] !== $kernel->vars['user_password_confirm'] )
				{
					$kernel->page->message_report( $kernel->ld['phrase_register_bad_password'], M_ERROR );
				}
				else
				{
					//----------------------------------
					// Username name not available
					//----------------------------------
					
					if( $kernel->db->numrows( "SELECT `user_name` FROM " . TABLE_PREFIX . "users WHERE `user_name` = '" . $kernel->vars['user_name'] . "'" ) == 1 AND $user['user_name'] != $kernel->vars['user_name'] )
					{
						$kernel->page->message_report( $kernel->ld['phrase_register_user_taken'], M_NOTICE );
					}
					
					//----------------------------------
					// E-mail address not available
					//----------------------------------
					
					elseif( $kernel->db->numrows( "SELECT `user_email` FROM " . TABLE_PREFIX . "users WHERE `user_email` = '" . $kernel->vars['user_email'] . "'" ) == 1 AND $user['user_email'] != $kernel->vars['user_email'] )
					{
						$kernel->page->message_report( $kernel->ld['phrase_register_email_taken'], M_NOTICE );
					}
					else
					{
						//----------------------------------
						// Prepare database entry
						//----------------------------------
						
						$userdata = array(
							"user_name" => $kernel->format_input( $kernel->vars['user_name'], T_DB ),
							"user_email" => $kernel->format_input( $kernel->vars['user_email'], T_DB )
						);
						
						if( !empty( $kernel->vars['user_password'] ) )
						{
							$userdata['user_password'] = md5( $kernel->vars['user_password'] );
						}
						
						$kernel->db->update( "users", $userdata, "WHERE `user_id` = " . $kernel->session->vars['session_user_id'] );
						
						//----------------------------------
						// Clear user session and redirect
						//----------------------------------
						
						$kernel->session->clear_user_cache();
						
						header( "Location: index.php" ); exit;
					}
				}
				
				break;
			}
			
			#############################################################################
			# Show user panel overview
			#############################################################################
			
			default :
			{
				$user = $kernel->db->row( "SELECT `user_name`, `user_email` FROM `" . TABLE_PREFIX . "users` WHERE `user_id` = " . $kernel->session->vars['session_user_id'] ); 
				
				$kernel->tp->call( "user_panel_profile" );
				
				$kernel->tp->cache( $user );
				
				break;
			}
		}
		
		break;
	}
	
	#############################################################################
	# Show subscription history
	#############################################################################
	
	case "subscription" :
	{
		$fetch_active_payments = $kernel->db->query( "SELECT p.payment_complete, p.payment_timestamp, p.payment_expire_timestamp, t.transaction_api_tx_id, t.transaction_amount, t.transaction_currency, s.subscription_name, s.subscription_description FROM " . TABLE_PREFIX . "payments p LEFT JOIN " . TABLE_PREFIX . "subscriptions s ON ( p.payment_subscription_id = s.subscription_id ) LEFT JOIN " . TABLE_PREFIX . "transactions t ON ( p.payment_id = t.transaction_payment_id ) WHERE p.payment_user_id = " . $kernel->session->vars['session_user_id'] . " ORDER BY p.payment_expire_timestamp DESC" );
		
		//----------------------------------
		// No active subscriptions
		//----------------------------------
		
		if( $kernel->db->numrows( $fetch_active_payments ) == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_active_subscriptions'], M_NOTICE );
		}
		else
		{
			$last_expire_time = -1;
			$expired = false;
			
			$kernel->tp->call( "user_panel_subscription_header" );
			
			while( $payment = $kernel->db->data( $fetch_active_payments ) )
			{
				if( $payment['payment_complete'] == 0 ) continue;
				
				if( $payment['payment_expire_timestamp'] > 0 AND $payment['payment_expire_timestamp'] < UNIX_TIME )
				{
					$payment['subscription_html_name'] = $kernel->page->string_colour( $payment['subscription_name'], "red" );
					
					$expired = true;
				}
				elseif( $payment['payment_expire_timestamp'] == 0 )
				{
					if( $last_expire_time > 0 )
					{
						$kernel->tp->call( "user_panel_subscription_row_break" );
					}
					
					$payment['subscription_html_name'] = $kernel->page->string_colour( $payment['subscription_name'], "orange" );
				}
				else
				{
					$payment['subscription_html_name'] = $kernel->page->string_colour( $payment['subscription_name'], "#33cc33" );
				}
				
				$last_expire_time = $payment['payment_expire_timestamp'];
				
				$payment['payment_timestamp'] = $kernel->fetch_time( $payment['payment_timestamp'], DF_LONG );
				$payment['payment_expire_timestamp'] = ( $payment['payment_expire_timestamp'] > 0 ) ? $kernel->fetch_time( $payment['payment_expire_timestamp'], DF_LONG ) : '&nbsp;';
				$payment['transaction_amount'] = "<img align=\"right\" src=\"./images/flag_" . $payment['transaction_currency'] . ".gif\" border=\"0\" alt=\"" . $kernel->ld['phrase_subscription_currency_' . $payment['transaction_currency'] ] . "\" />" . $payment['transaction_amount'];
				$payment['transaction_currency'] = strtoupper( $payment['transaction_currency'] );
				
				$kernel->tp->call( "user_panel_subscription_row" );
				
				$kernel->tp->cache( $payment );
				
				if( $expired == false )
				{
					$kernel->tp->call( "user_panel_subscription_row_break" );
				}
			}
			
			$kernel->tp->call( "user_panel_subscription_footer" );
			
			/*$kernel->tp->call( "user_panel_active_subscription_header" );
			
			while( $payment = $kernel->db->data( $fetch_active_payments ) )
			{
				$payment['subscription_description'] = $kernel->archive->return_string_words( $kernel->format_input( $payment['subscription_description'], T_NOHTML ), 20 );
				$payment['payment_timestamp'] = $kernel->fetch_time( $payment['payment_timestamp'], DF_LONG );
				$payment['payment_expire_timestamp'] = $kernel->fetch_time( $payment['payment_expire_timestamp'], DF_LONG );
				$payment['transaction_html_currency'] = strtoupper( $payment['transaction_currency'] ) . "&nbsp;<img src=\"./images/flag_" . $payment['transaction_currency'] . ".gif\" border=\"0\" alt=\"" . $kernel->ld['phrase_subscription_currency_' . $payment['transaction_currency'] ] . "\" />&nbsp;";
				
				$kernel->tp->call( "user_panel_subscription_row" );
				
				$kernel->tp->cache( $payment );
			}
			
			$kernel->tp->call( "user_panel_subscription_footer" );*/
		}
		
		$fetch_pending_payments = $kernel->db->query( "SELECT p.payment_timestamp, p.payment_expire_timestamp, t.transaction_api_tx_id, t.transaction_amount, t.transaction_currency, s.subscription_name, s.subscription_description FROM " . TABLE_PREFIX . "payments p LEFT JOIN " . TABLE_PREFIX . "subscriptions s ON ( p.payment_subscription_id = s.subscription_id ) LEFT JOIN " . TABLE_PREFIX . "transactions t ON ( p.payment_id = t.transaction_payment_id ) WHERE p.payment_user_id = " . $kernel->session->vars['session_user_id'] . " AND p.payment_expire_timestamp = 0 AND p.payment_complete = 1 ORDER BY p.payment_expire_timestamp DESC" );
		
		if( $kernel->db->numrows( $fetch_pending_payments ) > 0 )
		{
			$kernel->tp->call( "user_panel_pending_subscription_header" );
			
			while( $payment = $kernel->db->data( $fetch_pending_payments ) )
			{
				$payment['subscription_description'] = $kernel->archive->return_string_words( $kernel->format_input( $payment['subscription_description'], T_NOHTML ), 20 );
				$payment['payment_timestamp'] = $kernel->fetch_time( $payment['payment_timestamp'], DF_LONG );
				$payment['payment_expire_timestamp'] = ( $payment['payment_expire_timestamp'] > 0 ) ? $kernel->fetch_time( $payment['payment_expire_timestamp'], DF_LONG ) : '';
				$payment['transaction_html_currency'] = strtoupper( $payment['transaction_currency'] ) . "&nbsp;<img src=\"./images/flag_" . $payment['transaction_currency'] . ".gif\" border=\"0\" alt=\"" . $kernel->ld['phrase_subscription_currency_' . $payment['transaction_currency'] ] . "\" />&nbsp;";
				
				$kernel->tp->call( "user_panel_subscription_row" );
				
				$kernel->tp->cache( $payment );
			}
			
			$kernel->tp->call( "user_panel_subscription_footer" );
		}
		
		$fetch_expired_payments = $kernel->db->query( "SELECT p.payment_timestamp, p.payment_expire_timestamp, t.transaction_api_tx_id, t.transaction_amount, t.transaction_currency, s.subscription_name, s.subscription_description FROM " . TABLE_PREFIX . "payments p LEFT JOIN " . TABLE_PREFIX . "subscriptions s ON ( p.payment_subscription_id = s.subscription_id ) LEFT JOIN " . TABLE_PREFIX . "transactions t ON ( p.payment_id = t.transaction_payment_id ) WHERE p.payment_user_id = " . $kernel->session->vars['session_user_id'] . " AND p.payment_expire_timestamp < " . UNIX_TIME . " AND p.payment_expire_timestamp > 0 ORDER BY p.payment_expire_timestamp DESC" );
		
		if( $kernel->db->numrows( $fetch_expired_payments ) > 0 )
		{
			$kernel->tp->call( "user_panel_expired_subscription_header" );
			
			while( $payment = $kernel->db->data( $fetch_expired_payments ) )
			{
				$payment['subscription_description'] = $kernel->archive->return_string_words( $kernel->format_input( $payment['subscription_description'], T_NOHTML ), 20 );
				$payment['payment_timestamp'] = $kernel->fetch_time( $payment['payment_timestamp'], DF_LONG );
				$payment['payment_expire_timestamp'] = $kernel->fetch_time( $payment['payment_expire_timestamp'], DF_LONG );
				$payment['transaction_html_currency'] = strtoupper( $payment['transaction_currency'] ) . "&nbsp;<img src=\"./images/flag_" . $payment['transaction_currency'] . ".gif\" border=\"0\" alt=\"" . $kernel->ld['phrase_subscription_currency_' . $payment['transaction_currency'] ] . "\" />&nbsp;";
				
				$kernel->tp->call( "user_panel_subscription_row" );
				
				$kernel->tp->cache( $payment );
			}
			
			$kernel->tp->call( "user_panel_subscription_footer" );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->clean_array( "_REQUEST", array( "user_name" => V_STR, "user_password" => V_STR, "user_password_confirm" => V_STR, "user_email" => V_STR ) );
		
		$userdata = $kernel->db->row( "SELECT c.cache_session_downloads, c.cache_session_bandwidth, u.user_name, u.user_email, u.user_downloads, u.user_bandwidth FROM " . TABLE_PREFIX . "session_cache c LEFT JOIN " . TABLE_PREFIX . "users u ON ( c.cache_user_id = u.user_id ) WHERE c.cache_session_hash = '" . $_SESSION['phcdl_session_hash'] . "' AND c.cache_user_id = " . $kernel->session->vars['session_user_id'] );
		
		$kernel->tp->call( "user_panel_overview" );
		
		$userdata['cache_session_downloads'] = $kernel->format_input( $userdata['cache_session_downloads'], T_NUM );
		$userdata['cache_session_bandwidth'] = $kernel->archive->format_round_bytes( $userdata['cache_session_bandwidth'] );
		
		$userdata['user_downloads'] = $kernel->format_input( $userdata['user_downloads'], T_NUM );
		$userdata['user_bandwidth'] = $kernel->archive->format_round_bytes( $userdata['user_bandwidth'] );
		
		$kernel->tp->cache( $userdata );
		
		break;
	}
}

$kernel->tp->call( "user_panel_footer" );

//----------------------------------
// Output page
//----------------------------------

$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );

?>