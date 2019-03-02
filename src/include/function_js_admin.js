/*
################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2007 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and use.
################################################################################
*/

var element = 'checkbox';
var css_colour_box = '';

/**
 * Escape CP frames
 */

function escapeFrames() {
	if( top.frames.length != 0 ) {
		top.location = self.document.location;
	}
}

/**
 * Set node title as frame title
 */

function initPageTitle() {
	if ( parent.document.title && typeof( parent.document.title ) == 'string' ) {
		parent.document.title = document.title;
	}
}

/**
 * Focus empty login field
 */

function focusForm() {
	var username = fetchObj( 'username' );
	
	if( username.value == '' ) {
		username.focus();
	} else {
		fetchObj( 'password' ).focus();
	}
}

/**
 * Toogle the state of the selected node
 */

function toggleNode( id ) {
	if( fetchObj( 'menu' + id ).style.display == 'none' ) {
		fetchObj( 'blurb' + id ).style.display = 'none';
		fetchObj( 'menu' + id ).style.display = 'block';
		document.images[ 'img' + id ].src = eval( 'img_collapse.src' );
	} else {
		document.images[ 'img' + id ].src = eval( 'img_expand.src' );
		fetchObj( 'menu' + id ).style.display = 'none';
		fetchObj( 'blurb' + id ).style.display = 'block';
	}
}

/**
 * Toogle the state of all nodes
 */

function toggleAllNode( state ) {
	for ( var i = 1; i <= node_config.length; i++ ) {
		if( state == 0 ) {
			if( fetchObj( 'menu' + i ).style.display != 'none' ) {
				document.images[ 'img' + i ].src = eval( 'img_expand.src' );
				fetchObj( 'menu' + i ).style.display = 'none';
				fetchObj( 'blurb' + i ).style.display = 'block';
			}
		} else {
			if( fetchObj( 'menu' + i ).style.display == 'none' ) {
				fetchObj( 'blurb' + i ).style.display = 'none';
				fetchObj( 'menu' + i ).style.display = 'block';
				document.images[ 'img' + i ].src = eval( 'img_collapse.src' );
			}
		}
	}
}

/**
 * Load menu configuration for user
 */

function setupNodeConfig() {
	var c = 1;

	for ( var i = 0; i <= node_config.length; i++ ) {
		if( node_config[ i ] == 1 ) {
			fetchObj( 'menu' + c ).style.display = 'block';
			fetchObj( 'blurb' + c ).style.display = 'none';
		}
		c++;
	}
}

/**
 * Save menu configuration for user
 */

function saveMenuConfig( url ) {
	var config = new Array();
	var c = 0;

	for( var i = 1; i <= node_config.length; i++ ) {
		var node = fetchObj( 'menu' + i );
		
		if( node.style.display != 'none' ) {
			config[ c ] = 1;
		} else {
			config[ c ] = 0;
		}
		c++;
	}
	
	window.location = url + '&menucfg=' + config.join( ',' );
}

/**
 * Apply checked="true" to  all checkbox[] elements
 */

function checkAll() {
	if( document.getElementsByName ) {
		var element = document.getElementsByName( 'checkbox[]' );
		
		for ( var i = 0; i <= ( element.length - 1 ); i++ ) {
			if( element[ i ] ) element[ i ].checked = true;
		}
	}
}

/**
 * Apply checked="true" between all pre checked checkbox[] element points.
 */

function checkRanges() {
	if( document.getElementsByName ) {
		var points = new Array();
		var element = document.getElementsByName( 'checkbox[]' );
		
		for ( var i = 0; i <= ( element.length - 1 ); i++ ) {
			if( element[ i ] && element[ i ].checked ) points.push( i );
		}
		
		for ( var i = 0; i <= ( points.length - 1 ); i += 2 ) {
			for ( var p = points[ i ]; p <= points[ i + 1 ]; p++ ) {
				element[ p ].checked = true;
			}
		}
	}
}

/**
 * Apply checked="false" to  all checkbox[] elements
 */

function uncheckAll( total_rows ) {
	if( document.getElementsByName ) {
		var element = document.getElementsByName( 'checkbox[]' );
		
		for ( var i = 0; i <= ( element.length - 1 ); i++ ) {
			if( element[ i ] ) element[ i ].checked = false;
		}
	}
}

/**
 * Invert the checked="" state on all checkbox[] elements
 */

function invertCheckAll( total_rows ) {
	if( document.getElementsByName ) {
		var element = document.getElementsByName( 'checkbox[]' );
		
		for ( var i = 0; i <= ( element.length - 1 ); i++ ) {
			if( element[ i ] ) element[ i ].checked = !element[ i ].checked;
		}
	}
}

/**
 * Invert the checked="" state on all specified elements
 */

function invert_column( handle ) {
	if( document.getElementsByName ) {
		var element = document.getElementsByName( handle + '[]' );
		
		for ( var i = 0; i <= ( element.length - 1 ); i++ ) {
			if( element[ i ] ) element[ i ].checked = !element[ i ].checked;
		}
	}
}

/**
 * Pop-up template viewer.
 */

function initTemplatePreview() {
	alert( 'not done.' );
}

/**
 * Pop-up template viewer.
 */

function initPopup( window_url, width, height ) {
	var popup = window.open( window_url, 'popup', 'toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=0,width=' + width + ',height=' + height );
	if( !popup ) {
		alert( 'Could not open pop-up window. Please check your pop-up blocker.' ); return false;
	}
}

/**
 * Prompt user with confirm box with message before proceeding.
 */

function confirmActionMsg( string ) {
	var check = confirm( string );
	if( !check ) {
		return false;
	} else {
		return true;
	}
}

//=============================================================================
// Open up the date calendar
//=============================================================================

function initCalendar(id,hash)
{
	var calendar = fetchObj( "calendar_window" );
	var location = "index.php?hash=" + hash + "&element=" + id + "&node=calendar";
	
	calendar.style.left = ( xMousePos + 10 ) + 'px';
	calendar.style.top = ( yMousePos + 10 ) + 'px';
	
	if( calendar.style.display == "none" )
	{
		calendar.style.display = "block";
		
		document.getElementById('calendar_frame').src = location;
	}
	else
	{
		calendar.style.display = "none";
	}
}

//=============================================================================
// Return calendar day select
//=============================================================================

function setCalendarDate(id,time) {
	var stringField = eval( 'parent.document.forms[0].' + id );
	stringField.value = time;
	
	parent.document.getElementById( 'calendar_window' ).style.display = "none";
}

//=============================================================================
// Open up the colour swatch palette
//=============================================================================

function initPalettePicker( id ) {
	var swatch = fetchObj( 'palette_swatch' );
	
	swatch.style.left = ( xMousePos + 10 ) + 'px';
	swatch.style.top = ( yMousePos + 10 ) + 'px';
	
	if( swatch.style.display == 'none' ) {
		swatch.style.display = 'block';
		css_colour_box = id;
	} else {
		swatch.style.display = 'none';
		css_colour_box = '';
	}
}

//=============================================================================
// Colour swatch has been selected from palette
//=============================================================================

function setPaletteColour( hex_colour ) {
	var swatch = fetchObj( "palette_swatch" );
	var palette_box = fetchObj( "palette_" + css_colour_box );
	var palette_string = fetchObj( "palette_string_" + css_colour_box );
	
	if( hex_colour == "transparent" )
	{
		palette_box.style.background = "url('../images/css_transparent.gif')";
		palette_string.value = "transparent";
	}
	else
	{
		palette_box.style.background = "";
		palette_box.style.backgroundColor = "#" + hex_colour;
		palette_string.value = "#" + hex_colour;
	}
	
	swatch.style.display = "none";
	css_colour_box = "";
}

//=============================================================================
// Update palette swatch with manually entered hexdec string
//=============================================================================

function setPaletteString( palette_id )
{
	var palette_box = fetchObj( "palette_" + palette_id );
	var palette_string = fetchObj( "palette_string_" + palette_id );
	
	if( palette_string.value == "transparent" )
	{
		palette_box.style.background = "url('../images/css_transparent.gif')";
		palette_box.style.backgroundColor = "transparent";
	}
	else
	{
		palette_box.style.backgroundColor = palette_string.value;
	}
}
//=============================================================================
// Sample background image into the palette swatch
//=============================================================================

function initBgSample( class_id, palette_id )
{
	var palette_box = fetchObj( "palette_" + palette_id );
	var image_box = fetchObj( "background_image_" + class_id );
	
	if( image_box.value )
	{
		palette_box.style.background = "url('" + image_box.value + "')";
	}
}

//=============================================================================
// Update file add form with local file selector IFRAME data
//=============================================================================

function setLocalFile()
{
	fetchObj( "local_directory" ).value = frames['local_file_browse'].document.select.directory.value;
	fetchObj( "local_file" ).value = frames['local_file_browse'].document.select.file.value;
}

//=============================================================================
// Show/hide the page loading box
//=============================================================================

function toggleProgressWindow( element ) {
	if( element.style.display != 'none' ) {
		element.style.display = 'none';
	} else {
		element.style.display = 'block';
	}
}