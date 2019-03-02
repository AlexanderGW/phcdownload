<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'THM_MAN', 'THM_DEL' );

$kernel->clean_array( "_REQUEST", array( "theme_id" => V_INT ) );

switch( $kernel->vars['action'] )
{

	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'THM_MAN' );
		
		//clean db associated vars
		$kernel->clean_array( "_POST", array( "theme_import_data" => V_STR ) );
		
		$kernel->page->verify_upload_details();
		
		if( !empty( $_FILES['file_upload']['tmp_name'] ) )
		{
			$kernel->vars['theme_import_data'] = file_get_contents( $_FILES['file_upload']['tmp_name'] );
		}
		
		if( empty( $kernel->vars['theme_import_data'] ) AND empty( $kernel->vars['theme_import_data'] ) OR empty( $_FILES ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_theme_no_import_data'], M_ERROR );
		}
		else
		{
			require( ROOT_PATH . 'include' . DIR_STEP . 'class_xml.php' );
			$xml = new class_xml;
			
			$xml->parse_data( $kernel->vars['theme_import_data'] );
			
			if( $xml->xml_array[0]['ATTRIB']['LVERSION'] != FULL_VERSION )
			{
				$kernel->page->message_report( sprintf( $kernel->ld['phrase_xml_theme_data_bad_version'], $xml->xml_array[0]['ATTRIB']['SVERSION'], $kernel->config['short_version'] ), M_ERROR );
			}
			else
			{
				$kernel->tp->call( "admin_temp_import_header" );
				
				$theme = array(
					'theme_name' => $xml->xml_array[0]['PHCDL'][0]['THEME'][0]['ATTRIB']['NAME'],
					'theme_description' => $xml->xml_array[0]['PHCDL'][0]['THEME'][0]['ATTRIB']['DESCRIPTION']
				);
				
				$kernel->tp->cache( $theme );
				
				if( count( $xml->xml_array[0]['PHCDL'][0]['TEMPLATE'] ) < 1 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_xml_bad_theme_data'], M_ERROR );
				}
				else
				{
					foreach( $xml->xml_array[0]['PHCDL'][0]['TEMPLATE'] AS $template )
					{
						$template = array(
							'template_name' => $template['ATTRIB']['NAME'],
							'template_description' => $template['ATTRIB']['DESCRIPTION'],
							'template_data' => htmlspecialchars( $template['TEXT_NODE'][1] )
						);
						
						$kernel->tp->call( "admin_temp_import_row" );
						
						$kernel->tp->cache( $template );
					}
					
					$kernel->tp->call( "admin_temp_import_footer" );
				}
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "import" :
	{
		$kernel->admin->read_permission_flags( 'THM_MAN' );
		
		//clean db associated vars
		$kernel->clean_array( "_POST", array( "theme_name" => V_STR, "theme_description" => V_STR ) );
		
		if( empty( $kernel->vars['theme_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_theme_no_name'], M_WARNING );
		}
		else
		{
			//check theme name exists, or add a suffix
			if( $kernel->db->numrows( "SELECT `theme_name` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_name` = '" . $kernel->vars['theme_name'] . "'" ) > 0 )
			{
				$theme_match = true;
				$loop_count = 1;
				$extension_suffix = $kernel->config['file_rename_suffix'];
				
				while( $theme_match == true )
				{
					if( $kernel->db->numrows( "SELECT `theme_name` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_name` = '" . $kernel->vars['theme_name'] . $extension_suffix . $loop_count . "'" ) == 0 )
					{
						$kernel->vars['theme_name'] .= $extension_suffix . $loop_count;
						
						$theme_match = false;
						break;
					}
					
					$loop_count++;
				}
			}
			
			//prep new theme data
			$themedata = array(
				"theme_name" => $kernel->format_input( $kernel->vars['theme_name'], T_DB ),
				"theme_description" => $kernel->format_input( $kernel->vars['theme_description'], T_DB )
			);
		
			$kernel->db->insert( "themes", $themedata );
			
			$kernel->archive->update_database_counter( "themes" );
			
			$theme_id = $kernel->db->item( "SELECT t.theme_id FROM " . TABLE_PREFIX . "themes t ORDER BY t.theme_id DESC LIMIT 1" );
			
			$i = 0;
			
			//add new templates to theme
			foreach( $_POST['template_name'] AS $name )
			{
				$template_data_formatted = "";
				
				if( $name != $last_template )
				{
					$search = array( "&#36;", "&lt;", "&gt;" );
					$replace = array( chr( 36 ), "<", ">" );
					
					$template_data_formatted = str_replace( $search , $replace, $_POST['template_data'][ $i ] );
					
					$templatedata = array(
						"template_theme" => $theme_id,
						"template_name" => $kernel->format_input( $name, T_DB ),
						"template_description" => $kernel->format_input( $_POST['template_description'][ $i ], T_DB ),
						"template_data" => $kernel->format_input( $template_data_formatted, T_STRIP ),
						"template_timestamp" => UNIX_TIME,
						"template_author" => $kernel->format_input( $kernel->session->vars['adminsession_name'], T_DB )
					);
					
					$kernel->db->insert( "templates", $templatedata );
					
					$last_template = $name;
				}
				
				$i++;
			}
			
			$kernel->archive->update_database_counter( "templates" );

			$kernel->admin->message_admin_report( "log_theme_added", $kernel->vars['theme_name'] );
		}
		
		break;
	}
	
	#############################################################################
	
	case "export" :
	{
		$kernel->admin->read_permission_flags( 'THM_DEL' );
		
		$theme = $kernel->db->data( "SELECT `theme_name`, `theme_description` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_id` = " . $kernel->vars['theme_id'] );
		
		$file_date = date( "Y-m-d H:i:s" );
		$export_data = "";
		
		$file_theme_name = str_replace( " ", "_", $theme['theme_name'] );
		
		header( "Content-Type: text/x-delimtext; name=\"theme_" . strtolower( $file_theme_name ) . ".xml\"; charset=\"ISO-8859-1\"" );
		header( "Content-disposition: attachment; filename=theme_" . strtolower( $file_theme_name ) . ".xml" );
		
		//export header
		print "<" . "?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?" . ">\n\n<phcdl lversion=\"" . $kernel->config['full_version'] . "\" sversion=\"" . $kernel->config['short_version'] . "\" timestamp=\"" . UNIX_TIME . "\">\n";
		
		//theme line
		print "<theme name=\"" . $theme['theme_name'] . "\" description=\"" . $theme['theme_description'] . "\"></theme>\n";
		
		$get_templates = $kernel->db->query( "SELECT t.*, p.theme_id, p.theme_name, p.theme_description FROM " . TABLE_PREFIX . "templates t LEFT JOIN " . TABLE_PREFIX . "themes p ON( p.theme_id = t.template_theme ) WHERE t.template_theme = " . $kernel->vars['theme_id'] . " ORDER BY t.template_name" );
		
		while( $template = $kernel->db->data( $get_templates ) )
		{
			//template line
			print "<template name=\"" . $template['template_name'] . "\" description=\"" . $template['template_description'] . "\">\n<![CDATA[" . $template['template_data'] . "]]>\n</template>\n";
		}
		
		print '</phcdl>';
		
		exit;
	}
	
	#############################################################################
	
	default :
	{
		$upload_size = $kernel->archive->format_round_bytes( MAX_UPLOAD_SIZE );
		
		$get_themes = $kernel->db->query( "SELECT t.theme_id, t.theme_name, t.theme_description FROM " . TABLE_PREFIX . "themes t ORDER BY t.theme_name" );
		
		$kernel->tp->call( "admin_temp_export_header" );
		
		while( $theme = $kernel->db->data( $get_themes ) )
		{
			$kernel->tp->call( "admin_temp_export_row" );
			
			$theme['theme_name'] = $kernel->format_input( $theme['theme_name'], T_NOHTML );
			
			//colour indicators
			if( $theme['theme_id'] == $kernel->config['default_skin'] AND $theme['theme_id'] == 1 )
			{
				$theme['theme_html_name'] = $kernel->page->string_colour( $theme['theme_name'], "orange" );
			}
			elseif( $theme['theme_id'] == $kernel->config['default_skin'] )
			{
				$theme['theme_html_name'] = $kernel->page->string_colour( $theme['theme_name'], "#33cc33" );
			}
			elseif( $theme['theme_id'] == 1 )
			{
				$theme['theme_html_name'] = $kernel->page->string_colour( $theme['theme_name'], "red" );
			}
			else
			{
				$theme['theme_html_name'] = $theme['theme_name'];
			}
			
			$theme['theme_description'] = $kernel->format_input( $theme['theme_description'], T_NOHTML );
			
			$kernel->tp->cache( $theme );
			$kernel->tp->cache( "upload_size", $upload_size );
		}
		
		$kernel->tp->call( "admin_temp_export_footer" );
	}
}

?>

