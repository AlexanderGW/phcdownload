<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( -1 );

$kernel->clean_array( "_REQUEST", array( "api_id" => V_INT ) );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$api = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "payments_api` WHERE `api_id` = " . $kernel->vars['api_id'] . " LIMIT 1" );
		
		$kernel->tp->call( "admin_subs_pay_gateway_edit" );
		
		$kernel->tp->cache( "api_custom_fields", $kernel->tp->call( "admin_subs_pay_gateway_" . $api['api_class_name'], CALL_TO_PAGE ) );
		
		$api_options = unserialize( $api['api_options'] );
		
		$kernel->page->construct_vars_flags( $api );
		
		$kernel->tp->cache( $api );
		$kernel->tp->cache( $api_options );
		
		break;
	}
	
	#############################################################################
	
	case "test" :
	{
		$kernel->clean_array( "_REQUEST", array( "gateway" => V_STR ) );
		
		$response = false;
		
		if( CURL_ENABLED == true )
		{
			$handle = curl_init( $kernel->subscription->api_vars['test_url'] );
			
			curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $kernel->config['system_parse_timeout'] );
			curl_setopt( $handle, CURLOPT_HEADER, 1 );
			curl_setopt( $handle, CURLOPT_FAILONERROR, 1 );
			curl_setopt( $handle, CURLOPT_NOBODY, 1 );
			curl_setopt( $handle, CURLOPT_RETURNTRANSFER, 1 );
			
			$response = curl_exec( $handle );
			
			if( ( $errno = curl_errno( $handle ) ) > 0 )
			{
				$errstr = curl_error( $handle );
				
				curl_close( $handle );
				
				$kernel->page->message_report( $errstr . " (No. " . $errno . ")", M_ERROR );
			}
			else
			{
				curl_close( $handle );
			}
		}
		
		if( $response == false )
		{
			$parse = @parse_url( $kernel->subscription->api_vars['test_url'] );
			
			
			$host = $parse['host'];
			$path = $parse['path'];
			$port = empty( $parse['port'] ) ? 80 : $parse['port'];
			
			$headers = "HEAD " . $path . " HTTP/1.1\r\n";
			$headers .= "Host: " . HTTP_HOST . "\r\n";
			$headers .= "Connection: Close\r\n";
			
			if( $fp = @fsockopen( $host, $port, $errno, $errstr, $kernel->config['system_parse_timeout'] ) )
			{
				if( function_exists( "socket_set_timeout" ) ) @socket_set_timeout( $fp, $kernel->config['system_parse_timeout'] );
				
				@fwrite( $fp, $headers . "\r\n" );
				
				$response = fgets( $fp, 1024 );
				
				fclose( $fp );
			}
			else
			{
				$kernel->page->message_report( $errstr . " (No. " . $errno . ")", M_ERROR );
			}
		}
		
		if( $response != false AND strlen( $response ) > 0 )
		{
			$api = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "payments_api` WHERE `api_class_name` = '" . $kernel->vars['gateway'] . "' LIMIT 1" );
			$api['response'] = $response;
			
			$kernel->tp->call( "admin_subs_gateway_connnection_test" );
			
			$kernel->tp->cache( $api );
		}
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->clean_array( "_REQUEST", array( "api_name" => V_STR, "api_options" => V_ARRAY, "api_disabled" => V_INT ) );
		
		$apidata = array(
			"api_name" => $kernel->format_input( $kernel->vars['api_name'], T_DB ),
			"api_options" => $kernel->format_input( serialize( $kernel->vars['api_options'] ), T_DB ),
			"api_disabled" => $kernel->vars['api_disabled']
		);
		
		$kernel->db->update( "payments_api", $apidata, "WHERE `api_id` = " . $kernel->vars['api_id'] );
		
		$kernel->admin->message_admin_report( "log_api_edited", $kernel->vars['api_name'] );
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_subs_pay_gateway_header" );
		
		$get_payments_api = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "payments_api` ORDER BY `api_name`" );
		
		while( $api = $kernel->db->data( $get_payments_api ) )
		{
			$kernel->tp->call( "admin_subs_pay_gateway_row" );
			
			$api['api_name'] = $kernel->format_input( $api['api_name'], T_HTML );
			$api['api_currency_support'] = explode( ",", $api['api_currency_support'] );
			
			$api_currency_support = false;
			
			foreach( $api['api_currency_support'] AS $flag )
			{
				$api_currency_support .= "&nbsp;<img src=\"../images/flag_" . $flag . ".gif\" border=\"0\" alt=\"" . $kernel->ld['phrase_subscription_currency_' . $flag ] . "\" />&nbsp;";
			}
			
			$api['api_active_state'] = ( $api['api_disabled'] == 1 ) ? $kernel->admin->construct_icon( "delete.gif", $kernel->ld['phrase_no'], true, "middle" ) : $kernel->admin->construct_icon( "tick.gif", $kernel->ld['phrase_yes'], true, "middle" );
			
			$kernel->tp->cache( "api_currency_support", $api_currency_support );
			$kernel->tp->cache( $api );
			
		}
		
		$kernel->tp->call( "admin_subs_pay_gateway_footer" );
		
		break;
	}
}

?>

