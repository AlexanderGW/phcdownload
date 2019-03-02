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
	 * Make available important gateway variables.
	 **/
	
	function setup_variables()
	{
		global $kernel;
		
		$api_vars = unserialize( $kernel->db->item( "SELECT `api_options` FROM `" . TABLE_PREFIX . "payments_api` WHERE `api_disabled` = 0 AND `api_class_name` = 'google'" ) );
		
		$this->api_vars['merchant_id'] = $api_vars['api_merchant_id'];
		$this->api_vars['merchant_key'] = $api_vars['api_merchant_key'];
		
		$this->api_vars['url'] = "sandbox.google.com/checkout";
		$this->api_vars['url_req_path'] = "/api/checkout/v2/checkoutForm/Merchant/" . $this->api_vars['merchant_id'];
		$this->api_vars['url_merch_path'] = "/api/checkout/v2/checkoutForm/Merchant/" . $this->api_vars['merchant_id'];
		$this->api_vars['forward_url'] = "https://" . $this->api_vars['url'] . $this->api_vars['url_merch_path'];
		
		$this->api_vars['test_url'] = $this->api_vars['url'] . $this->api_vars['url_req_path'];
		
		$this->api_state_flags = array( "Pending" => 0, "Completed" => 1, "Held" => 2, "Denied" => 3, "Cancelled" => 4, "Reversed" => 5 );
	}
	
	//Return hmac-sha1 signature (API v1.2.5)
	function sha1_cart_signature( $xml_data )
	{
		$blocksize = 64;
		$merch_key = $this->api_vars['merchant_key'];
		
		if ( strlen( $merch_key ) > $blocksize )
		{
			$merch_key = pack( "H*", sha1( $merch_key ) );
		}
		
		$merch_key = str_pad( $merch_key, $blocksize, chr( 0x00 ) );
		$ipad = str_repeat( chr( 0x36 ), $blocksize );
		$opad = str_repeat( chr( 0x5c ), $blocksize );
		
		return pack( "H*", sha1( ( $merch_key ^ $opad ) . pack( "H*", sha1( ( $merch_key ^ $ipad ) . $xml_data ) ) ) );
	}
	
	/*
	 * Display confirm page before proceeding.
	 **/
	
	function construct_forwarder( $data )
	{
		global $kernel;
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
<request-received xmlns="http://checkout.google.com/schema/2"
    serial-number="58ea39d3-025b-4d52-a697-418f0be74bf9"/>';
		
		$data['subscription_api_signature'] = base64_encode( $this->sha1_cart_signature( $xml ) );
		$data['subscription_api_cart'] = base64_encode( $xml );
		
		$kernel->tp->call( "subscription_forwarder_google" );
		
		$kernel->tp->call( "subscription_forwarder_footer" );
		
		$kernel->tp->cache( $data );
	}
	
	/*
	 * Checks the transaction vars for validity with payment gateway.
	 **/
	
	function verify_transaction()
	{
		global $kernel;
		
		//Create post var string to submit to paypal.
		$query = "<hello xmlns=\"http://checkout.google.com/schema/2\"/>";
		
		if( CURL_ENABLED == true )
		{
			$headers = array();
      $headers[] = "Authorization: Basic ".base64_encode( $this->api_vars['merchant_id'] . ":" . $this->api_vars['merchant_key'] );
      $headers[] = "Content-Type: application/xml; charset=UTF-8";
      $headers[] = "Accept: application/xml; charset=UTF-8";
      $headers[] = "User-Agent: GC-PHP-Sample_code (" . PHP_SAMPLE_CODE_VERSION . "/ropu)";
			
			
			$ch = curl_init();
			
			curl_setopt( $ch, CURLOPT_URL, $this->api_vars['url'] . $this->api_vars['url_req_path'] );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $kernel->config['system_parse_timeout'] );
			curl_setopt( $ch, CURLOPT_HEADER, 1 );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDSIZE, 0 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			
			$this->response = curl_exec( $ch );
			
			curl_close( $ch );
		}
		
		/*if( $this->response == false )
		{
			$header = "POST " . $this->api_vars['url_path'] . " HTTP/1.0\r\n";
			$header .= "Content-type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-length: " . strlen( $query_string ) . "\r\n\r\n";
			
			if( $fp = @fsockopen( $this->api_vars['url'], 80, $errno, $errstr, $kernel->config['system_parse_timeout'] ) )
			{
				@socket_set_timeout( $fp, $kernel->config['system_parse_timeout'] );
				fputs( $fp, $header . $query_string );
				
				while ( !feof( $fp ) )
				{
					$this->response = fgets( $fp, 1024 );
					if ( strcmp( $this->response, "VERIFIED" ) == 0 ) break;
				}
				
				fclose( $fp );
			}
			else
			{
				$kernel->page->message_report( sprintf( $kernel->ld['phrase_subscription_gateway_error'], $errstr, $errno ), M_ERROR, HALT_EXEC );
			}
		}*/
		echo $this->response; exit;
		
		
		
		
		
		
		
		
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