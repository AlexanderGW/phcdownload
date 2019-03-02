<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and use.
################################################################################

class class_gateway extends class_subscription
{
	var $api_vars = array();
	var $response = false;
	var $api_state_flags = array();
	var $state = 0;
	var $state_update = false;
	
	/*
	 * Set important variables.
	 **/
	
	function setup_variables()
	{
		global $kernel;
		
		$this->api_vars['url'] = "sandbox.paypal.com";
		$this->api_vars['url_path'] = "/uk/cgi-bin/webscr";
		$this->api_vars['forward_url'] = "http://www." . $this->api_vars['url'] . $this->api_vars['url_path'];
		$this->api_vars['test_url'] = "http://www." . $this->api_vars['url'] . $this->api_vars['url_path'];
		
		$this->api_state_flags = array( "Pending" => 0, "Completed" => 1, "Held" => 2, "Denied" => 3, "Cancelled" => 4, "Reversed" => 5 );
	}
	
	/*
	 * Display confirm page before proceeding.
	 **/
	
	function construct_forwarder( $data )
	{
		global $kernel;
		
		$kernel->tp->call( "subscription_forwarder_paypal" );
		
		$kernel->tp->call( "subscription_forwarder_footer" );
		
		$kernel->tp->cache( $data );
	}
	
	/*
	 * Checks the transaction vars for validity with Paypal.
	 **/
	
	function verify_transaction()
	{
		global $kernel;
  	
		$kernel->clean_array( "_POST", array(
			"txn_type" => V_STR,
			"mc_currency" => V_STR,
			"business" => V_STR,
			"tax" => V_STR,
			"txn_id" => V_STR,
			"payer_id" => V_STR,
			"item_number" => V_STR,
			"payment_status" => V_STR,
			"mc_gross" => V_STR,
			"receiver_email" => V_STR,
			"verify_sign" => V_STR,
		) );
		
		//Create post var string to submit to paypal.
		$query_string[] = "cmd=_notify-validate";
		foreach( $_POST AS $arg => $val )
		{
			$query_string[] = $arg . "=" . urlencode( $val );
		}
		
		$query_string = implode( "&", $query_string );
		
		if( CURL_ENABLED == true )
		{
			$handle = curl_init( $this->api_vars['forward_url'] );
			
			curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $kernel->config['system_parse_timeout'] );
			curl_setopt( $handle, CURLOPT_HEADER, 0 );
			curl_setopt( $handle, CURLOPT_FAILONERROR, 1 );
			curl_setopt( $handle, CURLOPT_POST, 1 );
			curl_setopt( $handle, CURLOPT_POSTFIELDSIZE, 0 );
			curl_setopt( $handle, CURLOPT_POSTFIELDS, $query_string );
			curl_setopt( $handle, CURLOPT_RETURNTRANSFER, 1 );
			
			$this->response = curl_exec( $handle );
			
			if( ( $errno = curl_errno( $handle ) ) > 0 )
			{
				$errstr = curl_error( $handle );
				
				curl_close( $handle );
				
				$kernel->page->message_report( sprintf( $kernel->ld['phrase_subscription_gateway_error'], $errstr, $errno ), M_ERROR, HALT_EXEC );
			}
			else
			{
				curl_close( $handle );
			}
		}
		
		if( $this->response == false )
		{
			$parse = @parse_url( $kernel->subscription->api_vars['forward_url'] );
			
			$host = $parse['host'];
			$path = $parse['path'];
			$port = empty( $parse['port'] ) ? 80 : $parse['port'];
			
			$header = "POST " . $this->api_vars['url_path'] . " HTTP/1.0\r\n";
			$header .= "Content-type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-length: " . strlen( $query_string ) . "\r\n\r\n";
			
			if( $fp = @fsockopen( $host, $port, $errno, $errstr, $kernel->config['system_parse_timeout'] ) )
			{
				if( function_exists( "socket_set_timeout" ) ) @socket_set_timeout( $fp, $kernel->config['system_parse_timeout'] );
				
				@fwrite( $fp, $header . $query_string );
				
				while ( !feof( $fp ) )
				{
					$this->response = @fgets( $fp, 1024 );
					if ( strcmp( $this->response, "VERIFIED" ) == 0 ) break;
				}
				
				fclose( $fp );
			}
			else
			{
				$kernel->page->message_report( sprintf( $kernel->ld['phrase_subscription_gateway_error'], $errstr, $errno ), M_ERROR, HALT_EXEC );
			}
		}
		
		if( $this->response == "VERIFIED" )
		{
			$this->state = $this->api_state_flags[ $kernel->vars['payment_status'] ];
			
			$fetch_transaction = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "transactions` WHERE `transaction_api_tx_id` = '" . $kernel->vars['txn_id'] . "' AND `transaction_user_id` = '" . $kernel->vars['user_id'] . "' AND `transaction_subscription_id` = '" . $kernel->vars['item_number'] . "'" );
			
			if( $kernel->db->numrows() == 0 )
			{
				$transactiondata = array(
					"transaction_user_id" => $kernel->vars['user_id'],
					"transaction_api_tx_id" => $kernel->vars['txn_id'],
					"transaction_api_id" => $kernel->db->item( "SELECT `api_id` FROM `" . TABLE_PREFIX . "payments_api` WHERE `api_name` = '" . $kernel->vars['gateway'] . "'" ),
					"transaction_subscription_id" => $kernel->vars['item_number'],
					"transaction_payment_id" => $kernel->vars['payment_id'],
					"transaction_state" => $this->state,
					"transaction_amount" => $kernel->vars['mc_gross'],
					"transaction_currency" => strtolower( $kernel->vars['mc_currency'] ),
					"transaction_string" => serialize( $_POST ),
					"transaction_timestamp" => UNIX_TIME,
				);
				
				$this->state_update = true;
				
				$subscription = $kernel->db->row( "SELECT `subscription_length`, `subscription_length_span` FROM `" . TABLE_PREFIX . "subscriptions` WHERE `subscription_id` = " . $kernel->vars['item_number'] );
				
				$kernel->db->insert( "transactions", $transactiondata );
				
				$this->transaction_id = $kernel->db->insert_id();
				
				$kernel->db->update( "payments", array( "payment_complete" => 1 ), "WHERE `payment_id` = " . $kernel->vars['payment_id'] );
			}
			else
			{
				$transaction = $kernel->db->row( $fetch_transaction );
				
				if( $transaction['transaction_state'] != $this->state )
				{
					$this->state_update = true;
				}
				
				$this->transaction_id = $transaction['transaction_id'];
				
				$kernel->db->update( "transactions", array( "transaction_state" => $this->state ), "WHERE `transaction_id` = " . $transaction['transaction_id'] );
			}
			
			return true;
		}
	}
}

?>