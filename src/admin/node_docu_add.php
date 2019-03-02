<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'DOC_ADD' );

switch( $kernel->vars['action'] )
{
	#############################################################################

	case "create" :
	{
		$kernel->clean_array( "_REQUEST", array( "document_title" => V_STR, "document_data" => V_STR ) );
		
		if( empty( $kernel->vars['document_title'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_document_title'], M_ERROR );
		}
		else
		{
			$documentdata = array(
				"document_title" => $kernel->format_input( $kernel->vars['document_title'], T_DB ),
				"document_data" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['document_data'], T_DB ), $kernel->config['string_max_word_length'] ),
				"document_timestamp" => UNIX_TIME
			);
			
			$kernel->db->insert( "documents", $documentdata );
			
			$kernel->archive->update_database_counter( "documents" );

			$kernel->admin->message_admin_report( "log_document_added", $kernel->vars['document_title'] );
		}
		break;
	}
	
	#############################################################################
	
	default :
	{
		$kernel->tp->call( "admin_docu_add" );
		
		break;
	}
}

?>

