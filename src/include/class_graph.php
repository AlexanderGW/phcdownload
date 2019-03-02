<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

define( "GRAPH_WIDTH", 500 );
define( "GRAPH_XEIGHT", 250 );

define( "GRAPH_PADDING_LEFT", 2 );
define( "GRAPH_PADDING_RIGHT", 8 );
define( "GRAPH_PADDING_BOTTOM", 38 );
define( "GRAPH_PADDING_TOP", 6 );

define( "GRAPH_Y_LEGEND", '' );
define( "GRAPH_Y_LEGEND_FONT_SIZE", 3 );

define( "GRAPH_X_LEGEND", '' );
define( "GRAPH_X_LEGEND_FONT_SIZE", 3 );

class class_graph
{
	var $im = false;
	var $vars = array();
	var $colours = array();
	
	/*
	 * Prepare and store preference variables for graph creation.
	 **/
	
	function setup_variables( $vars = array() )
	{
		$this->vars = array(
			'x'				=> GRAPH_WIDTH,
			'y'				=> GRAPH_XEIGHT,
			'x_legend'		=> GRAPH_X_LEGEND,
			'y_legend'		=> GRAPH_Y_LEGEND,
		);
		
		if( count( $vars ) > 0 )
		{
			foreach( $vars AS $key => $value )
			{
				$this->vars[ $key ] = $value;
			}
		}
	}
	
	/*
	 * Prepare and store colours for graph assignment.                             
	 **/
	
	function setup_colours( $colours = array() )
	{
		$default_colours = array(
			'bg'			=> 'f5f5f5',
			'grid_border'	=> '444444',
			'grid_legend'	=> '666666',
			'grid_marker'	=> '444444',
			'grid'			=> 'c0c0c0'
		);
		
		if( count( $colours ) > 0 )
		{
			foreach( $colours AS $key => $value )
			{
				$this->allocate_colour( $value, $key );
			}
		}
		
		foreach( $default_colours AS $key => $value )
		{
			if( isset( $this->colours[ $key ] ) ) continue;
			
			$this->allocate_colour( $value, $key );
		}
	}
	
	/*
	 * Create colour based on supplied HTML hex value.
	 **/
	
	function allocate_colour( $hex = 'ffffff', $name = false )
	{
		if( $this->im == false ) return false;
		
		$dec = array();
		
		for( $i = 0; $i <= 5; $i += 2 )
		{
			$dec[] = hexdec( '\x' . $hex{ $i } . $hex{ $i + 1 } );
		}
		
		if( $name == false )
		{
			return imagecolorallocate( $this->im, $dec[0], $dec[1], $dec[2] );
		}
		else
		{
			$this->colours[ $name ] = imagecolorallocate( $this->im, $dec[0], $dec[1], $dec[2] );
		}
	}
	
	/*
	 * Create empty GD canvas
	 **/
	
	function create()
	{
		$this->im = ( function_exists( "imagecreatetruecolor" ) ) ? imagecreatetruecolor( $this->vars['x'], $this->vars['y'] ) : imagecreate( $this->vars['x'], $this->vars['y'] );
		
		$this->setup_colours( $colours );
		
		if( isset( $this->colours['bg'] ) ) imagefill( $this->im, 0, 0, $this->colours['bg'] );
	}
	
	/*
	 * Draw graph template with X, Y axis
	 **/
	
	function draw( $max, $group_total = 0 )
	{
		if( $this->im == false ) return false;
		
		$this->vars['y_point_offset'] = ( ( $this->vars['y'] - ( GRAPH_PADDING_BOTTOM + GRAPH_PADDING_TOP ) ) / 10 );
		$this->vars['y_point_increment'] = ceil( $max / 10 );
		$this->vars['y_point_max'] = ( $this->vars['y_point_increment'] * 10 );
		
		define( "GRAPH_X_LEGEND_LENGTH", strlen( $this->vars['x_legend'] ) );
		define( "GRAPH_Y_LEGEND_LENGTH", strlen( $this->vars['y_legend'] ) );
		define( "GRAPH_Y_POINT_PADDING", ( strlen( $this->vars['y_point_max'] ) * ( 4 + ( GRAPH_X_LEGEND_FONT_SIZE - 1 ) ) ) + 15 );
		
		for( $i = 1; $i <= 10; $i++ )
		{
			//Marker Y Tick
			imageline( $this->im, GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 5, ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) - ( $this->vars['y_point_offset'] * $i ) ), ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ), ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) - ( $this->vars['y_point_offset'] * $i ) ), $this->colours['grid_border'] );
			
			//Marker Y Line
			imageline( $this->im, ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING ) + 10, ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) - ( $this->vars['y_point_offset'] * $i ) ), ( $this->vars['x'] - GRAPH_PADDING_RIGHT ), ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) - ( $this->vars['y_point_offset'] * $i ) ), $this->colours['grid'] );
			
			//Marker Y Increment
			imagestring( $this->im, 2, GRAPH_PADDING_LEFT + 18, ( ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) - ( $this->vars['y_point_offset'] * $i ) ) - 7 ), sprintf( '%' . strlen( $this->vars['y_point_max'] ) . 's', $this->vars['y_point_increment'] * $i ), $this->colours['grid_marker'] );
		}
		
		//Marker Y Zero
		imagestring( $this->im, 2, GRAPH_PADDING_LEFT + 18, ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) - 7 ), sprintf( '%' . strlen( $this->vars['y_point_max'] ) . 's', '0' ), $this->colours['grid_marker'] );
		
		//Legend Y
		imagestringup( $this->im, GRAPH_Y_LEGEND_FONT_SIZE, GRAPH_PADDING_LEFT - 2, ( ( ( $this->vars['y'] - ( GRAPH_PADDING_BOTTOM - GRAPH_PADDING_TOP ) ) / 2 ) + ( ( GRAPH_Y_LEGEND_LENGTH + ( GRAPH_Y_LEGEND_LENGTH * ( 4 + ( GRAPH_Y_LEGEND_FONT_SIZE - 1 ) ) ) ) / 2 ) ), $this->vars['y_legend'], $this->colours['grid_legend'] );
		
		//Marker X Border Line
		imageline( $this->im, ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 5 ), ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ), ( ( $this->vars['x'] - GRAPH_PADDING_RIGHT ) ), ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ), $this->colours['grid_border'] );
		
		//Legend X
		imagestring( $this->im, GRAPH_X_LEGEND_FONT_SIZE, ( ( ( ( $this->vars['x'] - ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 5 ) ) / 2 ) - ( ( GRAPH_X_LEGEND_LENGTH * ( 4 + ( GRAPH_X_LEGEND_FONT_SIZE - 1 ) ) ) / 2 ) ) + 10 ), ( $this->vars['y'] - ( GRAPH_PADDING_BOTTOM - 25 ) ), $this->vars['x_legend'], $this->colours['grid_legend'] );
		
		$this->vars['x_point_offset'] = ( ( $this->vars['x'] - ( GRAPH_PADDING_LEFT + GRAPH_PADDING_RIGHT + GRAPH_Y_POINT_PADDING + 10 ) ) / 23 );
		$this->vars['x_point_group_offset'] = ( $group_total > 0 ) ? ( $this->vars['x_point_offset'] / 4 ) : $this->vars['x_point_offset'];
		$this->vars['x_point_increment'] = date( "H", ( UNIX_TIME + 3600 ) );
		
		for( $i = 0; $i <= 23; $i++ )
		{
			//Marker X Tick
			imageline( $this->im, ( ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ) + ( $this->vars['x_point_offset'] * $i ) ), ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) + 5 ), ( ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ) + ( $this->vars['x_point_offset'] * $i ) ), ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) ), $this->colours['grid_border'] );
			
			//Marker X Line
			imageline( $this->im, ( ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ) + ( $this->vars['x_point_offset'] * $i ) ), ( ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ) - 1 ), ( ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ) + ( $this->vars['x_point_offset'] * $i ) ), GRAPH_PADDING_TOP, $this->colours['grid'] );
			
			//Marker X Increment
			imagestring( $this->im, 2, ( ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ) + ( $this->vars['x_point_offset'] * $i ) ) - 5, ( $this->vars['y'] - GRAPH_PADDING_BOTTOM + 8 ), sprintf( '%02s', $this->vars['x_point_increment'] ), $this->colours['grid_marker'] );
			
			$this->vars['x_point_increment']++;
			
			if( $this->vars['x_point_increment'] > 23 )
			{
				$this->vars['x_point_increment'] = 0;
			}
		}
		
		//Marker Y Border Line
		imageline( $this->im, ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ), ( $this->vars['y'] - GRAPH_PADDING_BOTTOM ), ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 10 ), GRAPH_PADDING_TOP, $this->colours['grid_border'] );
	}
	
	/*
	 * Draw graph plot
	 **/
	
	function plot( $plot, $offset, $group_offset, $colour = '' )
	{
		if( $this->im == false ) return false;
		
		if( empty( $colour ) ) $colour = '99ff33';
		
		if( $plot > 0 ) $plot = ceil( ( ( ( $plot / $this->vars['y_point_max'] ) * 100 ) / 100 ) * ( ( $this->vars['y'] - ( GRAPH_PADDING_BOTTOM + 1 ) - GRAPH_PADDING_TOP ) ) );
		
		imagefilledrectangle( $this->im, floor( ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 11 ) + ( ( $offset * $this->vars['x_point_offset'] ) + ( $this->vars['x_point_group_offset'] * $group_offset ) ) ), 
		floor( $this->vars['y'] - ( GRAPH_PADDING_BOTTOM + 1 ) ), 
		floor( ( ( GRAPH_PADDING_LEFT + GRAPH_Y_POINT_PADDING + 11 ) + ( $offset * $this->vars['x_point_offset'] ) ) + ( $this->vars['x_point_group_offset'] * ( $group_offset + 1 ) ) ) - 1, 
		floor( ( $this->vars['y'] - ( GRAPH_PADDING_BOTTOM + 1 ) ) - $plot ), 
		$this->allocate_colour( $colour ) );
	}
	
	/*
	 * Output canvas in specified image format, optionally specify a filename to write to.
	 **/
	
	function output( $type = 'png', $filename = false )
	{
		$function_suffix = array( 'png' => 'png', 'jpg' => 'jpg', 'bmp' => 'bmp', 'gif' => 'gif' );
		
		if( !isset( $function_suffix[ $type ] ) ) return false;
		
		$filename_param = ( $filename != false ) ? ", '" . $filename . "." . $function_suffix[ $type ] . "'" : '';
		
		eval( "image" . $function_suffix[ $type ] . "( \$this->im" . $filename_param . " );" );
		
		imagedestroy( $this->im );
	}
}

?>