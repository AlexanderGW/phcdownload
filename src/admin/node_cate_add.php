<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'CAT_ADD' );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "category_sub_id" => V_INT,
		"category_name" => V_STR, "category_description" => V_STR, "category_doc_id" => V_INT,
		"category_password" => V_STR, "category_password_confirm" => V_STR ) );
		
		if( empty( $kernel->vars['category_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_category_name'], M_ERROR );
		}
		elseif( !empty( $kernel->vars['category_password'] ) AND strlen( $kernel->vars['category_password']	) < 3 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_invalid_password'], M_ERROR );
		}
		elseif( $kernel->vars['category_password'] !== $kernel->vars['category_password_confirm'] )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_match_password'], M_ERROR );
		}
		else
		{
			$categorydata = array(
				"category_sub_id" => $kernel->vars['category_sub_id'],
				"category_name" => $kernel->format_input( $kernel->vars['category_name'], T_DB ),
				"category_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['category_description'], T_DB ), $kernel->config['string_max_word_length'] ),
				"category_order" => 0,
				"category_doc_id" => $kernel->vars['category_doc_id']
			);
			
			if( !empty( $kernel->vars['category_password'] ) )
			{
				$categorydata['category_password'] = md5( $kernel->vars['category_password'] );
			}
			
			$kernel->db->insert( "categories", $categorydata );
			
			$kernel->admin->write_category_list();
			
			$kernel->archive->update_database_counter( "categories" );

			$kernel->admin->message_admin_report( "log_category_added", $kernel->vars['category_name'] );
		}
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_cate_add" );
		
		$kernel->page->construct_category_list();
		
		$kernel->archive->construct_list_options( 0, "document", $kernel->db->query( "SELECT `document_id`, `document_title` FROM `" . TABLE_PREFIX . "documents` ORDER BY `document_title`" ) );
		
		break;
	}
}

?>

