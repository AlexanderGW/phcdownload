<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

class class_xml
{
	var $xml_array = array();
	var $branch = array();
	var $file_data = '';
	
	/*
	 * Open read and store XML file data
	 **/
	
	function parse_file( $path_to_file )
	{
		global $kernel;
		
		$this->file_data = array();
		
		if( $kernel->url_exists( $path_to_file ) == false )
		{
			$kernel->page->message_report( sprintf( $kernel->ld['phrase_xml_file_open_error'], basename( $path_to_file ) ), M_ERROR );
		}
		
		$this->fp = fopen( $path_to_file, 'r' );
		
		while( !feof( $this->fp ) )
		{
			$this->file_data .= fread( $fp, 2048 );
		}
		
		fclose( $fp );
		
		$this->parse_data( $this->file_data );
	}
	
	/*
	 * Open read and store file data - do not call directly, see parse_file()
	 **/
	
	function parse_data( $parse_data )
	{
		global $kernel;
		
		$this->xml_array = array();
		
		$parser = xml_parser_create();
		
		xml_set_object( $parser, $this );
		xml_set_element_handler( $parser, 'tag_open', 'tag_close' );
		xml_set_character_data_handler( $parser, 'tag_data' );
		xml_parser_set_option( $parser, XML_OPTION_TARGET_ENCODING, 'UTF-8' );
		
		if( !xml_parse( $parser, $parse_data ) )
		{
			$kernel->page->message_report( sprintf( $kernel->ld['phrase_xml_file_data_error'], xml_error_string( xml_get_error_code( $parser ) ), xml_get_current_line_number( $parser ) ), M_CRITICAL_ERROR );
		}
		else
		{
			xml_parser_free( $parser );
		}
	}
	
	/*
	 * Open a tag for parsing - do not call directly, see parse_file()
	 **/
	
	function tag_open( $parser, $name, $attrib )
	{
		array_push( $this->branch, $name );
		
		$this->xml_array[] = array();
		
		if( count( $attrib ) > 0 )
		{
			$this->xml_array[ count( $this->xml_array ) - 1 ]['ATTRIB'] = $attrib;
		}
	}
	
	/*
	 * Close an opened tag - do not call directly, see parse_file()
	 **/
	
	function tag_close( $parser, $name )
	{
		$this->xml_array[ count( $this->branch ) - 1 ][ $this->branch[ count( $this->branch ) - 1 ] ][] = $this->xml_array[ count( $this->xml_array ) - 1 ];
		
		array_pop( $this->xml_array );
		
		$this->xml_array[ count( $this->branch ) - 1 ]['NODE'][] = $name;
		
		array_pop( $this->branch );
	}
	
	/*
	 * Get contents from a tag - do not call directly, see parse_file()
	 **/
	
	function tag_data( $parser, $data )
	{
		$this->xml_array[ count( $this->branch ) ]['TEXT_NODE'][] = $data;
	}
}

?>