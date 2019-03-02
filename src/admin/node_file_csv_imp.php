<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'FIL_ADD' );

$field_filter = array(
	"cat_id" => 0,
	"gallery_id" => 0,
	"pinned" => 0,
	"icon" => 0,
	"name" => 0,
	"author" => 0,
	"version" => 0,
	"description" => 0,
	"image_array" => 0,
	"timestamp" => 0,
	"mark_timestamp" => 0,
	"from_timestamp" => 0,
	"to_timestamp" => 0,
	"rating" => 0,
	"votes" => 0,
	"downloads" => 0,
	"views" => 0,
	"dl_limit" => 0,
	"size" => 0,
	"doc_id" => 0,
	"dl_url" => 0,
	"hash_data" => 0,
	//"total_comments" => 0,
	"disabled" => 0,
);

switch( $kernel->vars['action'] )
{

	#############################################################################
	
	case "confirm" :
	{
		$kernel->clean_array( "_POST", array( "file_import_data" => V_STR ) );
		
		$kernel->page->verify_upload_details();
		
		if( empty( $kernel->vars['file_import_data'] ) AND empty( $_FILES ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_file_no_import_data'], M_ERROR );
		}
		
		if( !empty( $_FILES ) AND !empty( $_FILES['csv_upload']['tmp_name'] ) )
		{
			$kernel->vars['file_import_data'] = file( $_FILES['csv_upload']['tmp_name'] );
		}
		elseif( !empty( $kernel->vars['file_import_data'] ) )
		{
			$kernel->vars['file_import_data'] = explode( ( ( strpos( $kernel->vars['file_import_data'], chr( 10 ) ) !== false ) ? chr( 10 ) : chr( 13 ) ), $kernel->vars['file_import_data'] );
		}
		else
		{
			$kernel->page->message_report( $kernel->ld['phrase_import_not_specified'], M_ERROR, HALT_EXEC );
		}
		
		$count = 0;
		$csv_files = array();
		
		if( is_array( $kernel->vars['file_import_data'] ) AND count( $kernel->vars['file_import_data'] ) > 0 )
		{
			foreach( $kernel->vars['file_import_data'] AS $no => $line )
			{
				$kernel->vars['file_import_data'][ $no ] = $kernel->format_input( $line, T_RAW );
			}
			
			foreach( $kernel->vars['file_import_data'] AS $no => $line )
			{
				if( empty( $line ) ) continue;
				
				if( $no == 0 )
				{
					$field_key = array_flip( explode( "\",\"", substr( trim( $line ), 1, -1 ) ) );
					
					if( !isset( $field_key['name'] ) ) $kernel->page->message_report( $kernel->ld['phrase_csv_no_filename'], M_ERROR, HALT_EXEC );
					
					if( !isset( $field_key['cat_id'] ) ) $kernel->page->message_report( $kernel->ld['phrase_csv_no_category'], M_ERROR, HALT_EXEC );
					
					if( !isset( $field_key['dl_url'] ) ) $kernel->page->message_report( $kernel->ld['phrase_csv_no_download_url'], M_ERROR, HALT_EXEC );
					
					foreach( array_flip( $field_key ) AS $field )
					{
						if( !isset( $field_filter[ $field ] ) ) $kernel->page->message_report( sprintf( $kernel->ld['phrase_csv_invalid_field'], $field ), M_ERROR, HALT_EXEC );
					}
				}
				else
				{
					$csv_files[ $count ] = explode( "\",\"", substr( trim( $line ), 1, -1 ) );
					
					$count++;
				}
			}
		}
		
		if( $count == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_nothing_imported'], M_ERROR );
		}
		else
		{
			$csv_field_id = array_flip( $field_key );
			
			$kernel->tp->call( "admin_file_csv_confirm_header" );
			
			$csv_field_row = "";
			
			foreach( $csv_field_id AS $field )
			{
				if( !isset( $kernel->ld['phrase_csv_file_' . $field ] ) ) continue;
				
				$csv_field_row .= $kernel->tp->call( "admin_file_csv_confirm_title_field", CALL_TO_PAGE );
				
				$csv_field_row = $kernel->tp->cache( "field_data", $kernel->ld['phrase_csv_file_' . $field ], $csv_field_row );
			}
			
			$kernel->tp->call( "admin_file_csv_confirm_row" );
			
			$kernel->tp->cache( 'csv_row_data', $csv_field_row );
			
			foreach( $csv_files AS $line => $fields )
			{
				$kernel->tp->call( "admin_file_csv_confirm_row" );
				
				$csv_field_row = "";
				
				foreach( $fields AS $key => $value )
				{
					if( !isset( $kernel->ld['phrase_csv_file_' . $csv_field_id[ $key ] ] ) ) continue;
					
					$csv_field_row .= $kernel->tp->call( "admin_file_csv_confirm_data_field", CALL_TO_PAGE );
					
					if( $csv_field_id[ $key ] == 'cat_id' )
					{
						if( !is_numeric( $value ) )
						{
							$fetch_category = $kernel->db->query( "SELECT `category_id` FROM `" . TABLE_PREFIX . "categories` WHERE `category_name` LIKE '%" . html_entity_decode( $value ) . "%'" );
							
							if( $kernel->db->numrows( $fetch_category ) == 0 )
							{
								$kernel->page->message_report( $kernel->ld['phrase_csv_data_category_not_found'], M_ERROR, HALT_EXEC );
							}
							else
							{
								$csv_files[ $line ][ $key ] = $kernel->db->item( $fetch_category );
							}
						}
						else
						{
							$value = $kernel->db->item( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $value );
						}
					}
					
					$csv_field_row = $kernel->tp->cache( "field_data", $value, $csv_field_row );
				}
				
				$kernel->tp->cache( 'csv_row_data', $csv_field_row );
			}
			
			$csv['csv_fields'] = base64_encode( serialize( $csv_field_id ) );
			$csv['csv_files'] = base64_encode( serialize( $csv_files ) );
			$csv['total_fields'] = count( $csv_field_id );
			$csv['total_files'] = count( $csv_files );
			
			$kernel->tp->call( "admin_file_csv_confirm_footer" );
			
			$kernel->tp->cache( $csv );
		}
		
		break;
	}
	
	#############################################################################
	
	case "import" :
	{
		$kernel->clean_array( "_POST", array( "csv_fields" => V_STR, "csv_files" => V_STR ) );
		
		if( empty( $kernel->vars['csv_fields'] ) OR empty( $kernel->vars['csv_files'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_missing_import_data'], M_ERROR );
		}
		else
		{
			$csv_fields = unserialize( base64_decode( $kernel->vars['csv_fields'] ) );
			$csv_files = unserialize( base64_decode( $kernel->vars['csv_files'] ) );
			//print_r($csv_fields);exit;
			$count = 0;
			$add_data = array();
			
			foreach( $csv_files AS $line )
			{
				foreach( $line AS $key => $value )
				{
					if( empty( $csv_fields[ $key ] ) ) continue;
					
					$filedata[ 'file_' . $csv_fields[ $key ] ] = str_replace( '#COMMA#', ',', $value );
				}
				
				if( !isset( $filedata['file_size'] ) )
				{
					$filedata['file_size'] = $kernel->archive->parse_url_size( $csv_fields['dl_url'], $config['system_parse_timeout'] );
				}
				
				$filedata['file_timestamp'] = $filedata['file_mark_timestamp'] = UNIX_TIME;
				
				$kernel->db->insert( "files", $filedata );
				
				$add_data[] = $filedata['file_name'];
				
				$count++;
			}
			
			if( $count > 0 ) $kernel->admin->message_admin_report( "log_file_imported", $count, $add_data );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_file_csv_import" );
		
		$kernel->tp->cache( array( "upload_size_bytes" => MAX_UPLOAD_SIZE, "upload_size" => sprintf( $kernel->ld['phrase_image_total_max_upload_size'], $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE ) ) ) );
	}
}

?>

