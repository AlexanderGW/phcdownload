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

$kernel->clean_array( "_REQUEST", array( "page" => V_PINT, "limit" => V_PINT, "sort" => V_STR, "order" => V_STR, "start" => V_PINT, "query" => V_STR ) );

$kernel->vars['page'] = ( $kernel->vars['page'] < 1 ) ? 1 : $kernel->vars['page'];
$kernel->vars['limit'] = ( $kernel->vars['limit'] < 1 ) ? $kernel->config['admin_display_default_limit'] : $kernel->vars['limit'];

if( empty( $kernel->vars['sort'] ) ) $kernel->vars['sort'] = "file_name";
if( empty( $kernel->vars['order'] ) ) $kernel->vars['order'] = "asc";
if( empty( $kernel->vars['query'] ) ) $kernel->vars['query'] = "SELECT VERSION()";

if( $kernel->config['system_sql_console_enabled'] != "true" )
{
	$kernel->page->message_report( $kernel->ld['phrase_query_console_disabled'], M_NOTICE );
}
else
{
	$kernel->page->message_report( $kernel->ld['phrase_query_console'], M_WARNING );
	
	$kernel->tp->call( "admin_db_console" );
	
	switch( $kernel->vars['action'] )
	{
	
		#############################################################################
		
		case "result" :
		{
			if( IN_DEMO_MODE == true )
			{
				$kernel->page->message_report( $kernel->ld['phrase_in_demo_mode'], M_ERROR, HALT_EXEC );
			}
			
			$kernel->vars['query'] = $kernel->format_input( $kernel->vars['query'], T_URL_DEC );
			
			$kernel->vars['query'] = $kernel->format_input( $kernel->vars['query'], T_DB );
			
			$check_results = $kernel->db->query( $kernel->vars['query'] );
			
			if( substr( trim( strtoupper( $kernel->vars['query'] ) ), 0, 6 ) == "SELECT" OR substr( trim( strtoupper( $kernel->vars['query'] ) ), 0, 4 ) == "SHOW" )
			{
				if( $kernel->db->numrows() == 0 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_results'], M_NOTICE );
				}
				else
				{
					$header_field = "";
					$row_field = "";
					$current_field = 0;
					
					$kernel->archive->construct_pagination_vars( $kernel->db->numrows( $check_results ) );
					
					$kernel->tp->call( "admin_db_console_header" );
					
					$working_query = $kernel->vars['query'];
					
					if( !ereg( strtoupper( "LIMIT" ), $working_query ) AND substr( trim( strtoupper( $kernel->vars['query'] ) ), 0, 6 ) == "SELECT" )
					{
						$working_query .= " LIMIT " . $kernel->vars['start'] . ", " . $kernel->vars['limit'];
					}
					
					$get_results = $kernel->db->query( $working_query );
					
					while( $current_field < $kernel->db->numfields( $get_results ) )
					{
						$header_field_data = $kernel->db->data_field( $get_results, $current_field );
						
						$header_field .= $kernel->tp->call( "admin_db_console_header_field", CALL_TO_PAGE );
						$header_field = $kernel->tp->cache( "field_data", $header_field_data->name, $header_field );
						
						$current_field++;
					}
					
					$query_data_formatted = $kernel->vars['query'];
					
					$query_data_formatted = preg_replace( "/([a-z]+)/", "<font color='indianred'>\\1</font>", $query_data_formatted );
					$query_data_formatted = preg_replace( "/([0-9]+)/", "<font color='green'>\\1</font>", $query_data_formatted );
					$query_data_formatted = preg_replace( "/([A-Z]+)/", "<font color='#996666'><b>\\1</b></font>", $query_data_formatted );
					$query_data_formatted = nl2br( $query_data_formatted );
					$kernel->tp->cache( "query_data_formatted", $query_data_formatted );
					
					$kernel->tp->cache( "header_fields", $header_field );
					$kernel->tp->cache( "header_total_fields", $kernel->db->numfields( $get_results ) );
					
					while( $rowdata = $kernel->db->data( $get_results ) )
					{
						$kernel->tp->call( "admin_db_console_row" );
						
						foreach( $rowdata as $key => $value )
						{
							$row_field .= $kernel->tp->call( "admin_db_console_row_field", CALL_TO_PAGE );
							
							//raw-a-tize
							$current = array( "&", chr( 36 ), chr( 10 ), chr( 13 ) );
							$replace = array( "&amp;", "&#36;", "\\n", "\\r" );
							
							$value = str_replace( $current, $replace, $value );
							
							$row_field = $kernel->tp->cache( "field_data", $value, $row_field );
						}
						
						$kernel->tp->cache( "row_fields", $row_field );
						
						unset( $row_field );
					}
					
					$kernel->tp->call( "admin_db_console_footer" );
					
					$kernel->page->construct_category_filters();
					
					$query_formatted = $kernel->format_input( $kernel->vars['query'], T_URL_ENC );
					
			 		$kernel->page->construct_pagination( array( 'action' => 'result', 'query' => $query_formatted ), $kernel->config['admin_pagination_page_proximity'] );
				}
			}
			else
			{
				$affected_rows = $kernel->db->affectrows();
				
				if( $affected_rows == -1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_rows_affected'], M_NOTICE );
				}
				elseif( substr( trim( strtoupper( $kernel->vars['query'] ) ), 0, 6 ) == "INSERT" )
				{
					$kernel->page->message_report( sprintf( $kernel->ld['phrase_rows_inserted'], $affected_rows ) );
				}
				elseif( substr( trim( strtoupper( $kernel->vars['query'] ) ), 0, 7 ) == "REPLACE" )
				{
					$kernel->page->message_report( sprintf( $kernel->ld['phrase_rows_replaced'], $affected_rows ) );
				}
				elseif( substr( trim( strtoupper( $kernel->vars['query'] ) ), 0, 6 ) == "DELETE" )
				{
					$kernel->page->message_report( sprintf( $kernel->ld['phrase_rows_deleted'], $affected_rows ) );
				}
				elseif( substr( trim( strtoupper( $kernel->vars['query'] ) ), 0, 6 ) == "UPDATE" )
				{
					$kernel->page->message_report( sprintf( $kernel->ld['phrase_rows_updated'], $affected_rows ) );
				}
				else
				{
					$kernel->page->message_report( sprintf( $kernel->ld['phrase_rows_affected'], $affected_rows ) );
				}
			}
			
			break;
		}
		
		#############################################################################
		
		default :
		{
			break;
		}
	}
	
	$kernel->tp->cache( "query", $kernel->vars['query'] );
}

?>

