<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 23, 24 );

$kernel->clean_array( "_REQUEST", array( "style_id" => V_INT ) );

switch( $kernel->vars['action'] )
{

	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 23 );
		
		//clean db associated vars
		$kernel->clean_array( "_POST", array( "style_import_data" => V_STR ) );
		
		$kernel->page->verify_upload_details();
		
		if( !empty( $_FILES['style_import_upload']['tmp_name'] ) )
		{
			$kernel->vars['style_import_data'] = file_get_contents( $_FILES['style_import_upload']['tmp_name'] );
		}
		
		if( empty( $kernel->vars['style_import_data'] ) OR empty( $kernel->vars['style_import_data'] ) AND empty( $_FILES ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_style_no_import_data'], M_ERROR );
		}
		else
		{
			require( ROOT_PATH . 'include/class_xml.php' );
			$xml = new class_xml;
			
			$xml->parse_data( $kernel->vars['style_import_data'] );
			
			if( $xml->xml_array[0]['ATTRIB']['LVERSION'] != FULL_VERSION )
			{
				$kernel->page->message_report( sprintf( $kernel->ld['phrase_xml_style_data_bad_version'], $xml->xml_array[0]['ATTRIB']['SVERSION'], $kernel->config['short_version'] ), M_ERROR );
			}
			else
			{
				if( count( $xml->xml_array[0]['PHCDL'][0]['STYLE'] ) < 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_xml_bad_style_data'], M_ERROR );
				}
				else
				{
					foreach( $xml->xml_array[0]['PHCDL'][0]['STYLE'] AS $style )
					{
						$style = array(
							'style_name' => $style['ATTRIB']['NAME'],
							'style_description' => $style['ATTRIB']['DESCRIPTION'],
							'style_data' => $kernel->format_input( $style['TEXT_NODE'][1], T_FORM )
						);
						
						$kernel->tp->call( "admin_styl_import_edit" );
						
						$kernel->tp->cache( $style );
					}
				}
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "import" :
	{
		$kernel->admin->read_permission_flags( 23 );
		
		//clean db associated vars
		$kernel->clean_array( "_POST", array( "style_name" => V_STR, "style_description" => V_STR, "style_data" => V_STR ) );
		
		if( empty( $kernel->vars['style_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_style_no_name'], M_WARNING );
		}
		else
		{
			if( $kernel->db->numrows( "SELECT `style_name` FROM `" . TABLE_PREFIX . "styles` WHERE `style_name` = '" . $kernel->vars['style_name'] . "'" ) > 0 )
			{
				$file_match = true;
				$loop_count = 1;
				$extension_suffix = $kernel->config['file_rename_suffix'];
				
				while( $file_match == true )
				{
					if( $kernel->db->numrows( "SELECT `style_name` FROM `" . TABLE_PREFIX . "styles` WHERE `style_name` = '" . $kernel->vars['style_name'] . $extension_suffix . $loop_count . "'" ) == 0 )
					{
						$kernel->vars['style_name'] .= $extension_suffix . $loop_count;
						
						$file_match = false;
						break;
					}
					
					$loop_count++;
				}
			}
			
			$style_data_formatted = str_replace( "&#36;", chr( 36 ), $kernel->vars['style_data'] );
			
			$styledata = array(
				"style_name" => $kernel->format_input( $kernel->vars['style_name'], T_DB ),
				"style_description" => $kernel->format_input( $kernel->vars['style_description'], T_DB ),
				"style_data" => $kernel->format_input( $style_data_formatted, T_STRIP )
			);
			
			$kernel->db->insert( "styles", $styledata );
			
			$kernel->archive->update_database_counter( "styles" );

			$kernel->admin->message_admin_report( "log_style_added", $kernel->vars['style_name'] );
		}
		
		break;
	}
	
	#############################################################################
	
	case "export" :
	{
		$kernel->admin->read_permission_flags( 24 );
		
		$style = $kernel->db->data( "SELECT `style_name`, `style_description`, `style_data`, `style_original` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $kernel->vars['style_id'] );
		
		$file_date = date( "Y-m-d H:i:s" );
		
		$file_style_name = str_replace( " ", "_", $style['style_name'] );
		
		header( "Content-Type: text/x-delimtext; name=\"style_" . strtolower( $file_style_name ) . ".xml\"; charset=\"ISO-8859-1\"" );
		header( "Content-disposition: attachment; filename=style_" . strtolower( $file_style_name ) . ".xml" );
		
		//export header
		print "<" . "?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?" . ">\n\n";
		
		$style['style_data'] = str_replace( chr( 10 ), "\\n", $style['style_data'] );
		$style['style_data'] = str_replace( chr( 13 ), "\\r", $style['style_data'] );
		
		//style data line
		print "<phcdl lversion=\"" . $kernel->config['full_version'] . "\" sversion=\"" . $kernel->config['short_version'] . "\" timestamp=\"" . UNIX_TIME . "\">\n\t<style name=\"" . $style['style_name'] . "\" description=\"" . $style['style_description'] . "\">\n\n<![CDATA[" . $style['style_data'] . "]]>\n</style>\n</phcdl>";
		
		exit;
	}
	
	#############################################################################
	
	default :
	{
		$upload_size = $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE );
		
		$get_styles = $kernel->db->query( "SELECT `style_id`, `style_name`, `style_description` FROM `" . TABLE_PREFIX . "styles` ORDER BY `style_name`" );
		
		$kernel->tp->call( "admin_style_export_header" );
		
		while( $style = $kernel->db->data( $get_styles ) )
		{
			$kernel->tp->call( "admin_style_export_row" );
			
			$style['style_name'] = $kernel->format_input( $style['style_name'], T_NOHTML );
			
			if( $style['style_id'] == $kernel->config['default_style'] AND $style['style_id'] == 1 )
			{
				$style['style_html_name'] = $kernel->page->string_colour( $style['style_name'], "orange" );
			}
			elseif( $style['style_id'] == $kernel->config['default_style'] )
			{
				$style['style_html_name'] = $kernel->page->string_colour( $style['style_name'], "#33cc33" );
			}
			elseif( $style['style_id'] == 1 )
			{
				$style['style_html_name'] = $kernel->page->string_colour( $style['style_name'], "red" );
			}
			else
			{
				$style['style_html_name'] = $style['style_name'];
			}
			
			$style['style_description'] = $kernel->format_input( $style['style_description'], T_NOHTML );
			
			$kernel->tp->cache( $style );
			$kernel->tp->cache( "upload_size", $upload_size );
		}
		
		$kernel->tp->call( "admin_style_export_footer" );
	}
}

?>

