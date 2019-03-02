/*
################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2007 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and use.
################################################################################
*/

if (document.layers) {
	document.captureEvents(Event.MOUSEMOVE);
}

document.onmousemove = captureMousePosition;
var xMousePos = 0;
var yMousePos = 0;
var xMousePosMax = 0;
var yMousePosMax = 0;

/**
 * Fetch X,Y coordinates of mouse position
 */

function captureMousePosition( e ) {
	if ( document.layers ) {
		xMousePos = e.pageX;
		yMousePos = e.pageY;
		xMousePosMax = window.innerWidth + window.pageXOffset;
		yMousePosMax = window.innerHeight + window.pageYOffset;
	} else if ( document.all ) {
		xMousePos = window.event.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
		yMousePos = window.event.clientY + document.body.scrollTop + document.documentElement.scrollTop;
		xMousePosMax = document.body.clientWidth + document.body.scrollLeft + document.documentElement.scrollLeft;
		yMousePosMax = document.body.clientHeight + document.body.scrollTop + document.documentElement.scrollTop;
	} else if ( document.getElementById ) {
		xMousePos = e.pageX;
		yMousePos = e.pageY;
		xMousePosMax = window.innerWidth + window.pageXOffset;
		yMousePosMax = window.innerHeight + window.pageYOffset;
	}
}

/**
 * Read page elements using available browser methods.
 */

function fetchObj( handle ) {
	if( document.layers ) {
		return document.layers[ handle ];
	} else if ( document.all ) {
		return document.all[ handle ];
	} else {
		return document.getElementById( handle );
	}
}

/**
 * Write to page element using available browser methods.
 */
 
function writeObj( handle, string ) {
	if ( document.layers ) {
		obj = document.layers[ handle ];
		obj.document.open();
		obj.document.write( string );
		obj.document.close();
	} else if ( document.all ) {
		obj = document.all[ handle ];
		obj.innerHTML = string;
	} else if ( document.getElementById ) {
		obj = document.getElementById( handle );
		obj.innerHTML = '';
		obj.innerHTML = string;
	}
}

/**
 * Convert bytes to human-readable formats
 */
 
function byteConv( bytes ) {
	var delimit = 0;
	var group = new Array( 'B', 'KB', 'MB', 'GB', 'TB' );
	
	while( bytes >= 1024 ) {
		bytes = ( bytes / 1024 );
		delimit++;
	}
	
	var value = Math.round( bytes * 100 ) / 100;
	
	return value + group[ delimit ];
}

function init_obj_focus( element, value ) {
	var object = fetchObj( element );
	
	if( object.value == value ) {
		object.value = "";
		object.style.color = "#000000";
	}
}

function init_obj_blur( element, value ) {
	var object = fetchObj( element );
	
	if( object.value == "" ) {
		object.value = value;
		object.style.color = "#c0c0c0";
	}
}

/**
 * Open pop-up window ready for file uploading.
 */

function initUploadPopup( system_root_url, hash, cache_key ) {
	var upload = window.open( system_root_url + '/upload.php?cache_key=' + cache_key, 'upload', 'width=600,height=220,scrollbars=1' );
	if( !upload ) {
		alert( 'Possible pop-up blocker has stopped the upload window from opening.' );
	}
	
	return false;
}

/**
 * Update the progress of an APC enabled PHP upload.
 */

function updateUploadProgress( full_filename, current, total, rate, state ) {
	var window = fetchObj( 'upload_window' );
	window.style.display = 'block';
	
	var message = fetchObj( 'message_window' );
	message.style.display = 'none';
	
	if( state == 2 ) {
		writeObj( 'progress_current_file', 'Added Successfully' );
		//if( opener ) opener.location.reload();
		setTimeout( 'self.close()', 1000 );
	} else {
		var percentage = ( total > 0 ) ? Math.round( current / total * 100 ) + '%' : '0%';
		if( rate > 0 ) var rate = Math.round( rate );
		
		fetchObj( 'progress_bar' ).style.width = percentage;
		writeObj( 'progress_bar_label', percentage );
		
		fetchObj( 'progress_bar' ).style.backgroundColor = ( state == 1 ) ? '#ff3333' : '#33ff33';
		
		if( total != '0' ) {
			if( percentage != '100%' ) {
				var filename_index = full_filename.lastIndexOf( '\\' );
				if( !filename_index ) filename_index = full_filename.lastIndexOf( '/' );
				var filename = full_filename.substring( filename_index + 1 );
				
				writeObj( 'progress_current_file', ( filename_index ) ? 'Uploading.. (' + filename + ')' : 'Uploading..' );
				
				document.title = 'Uploading.. ' + percentage + ' [' + filename + ']';
			} else {
				writeObj( 'progress_current_file', 'Adding file(s) to database..' );
				
				document.title = 'Adding file(s) to database..';
			}
		}
		
		writeObj( 'info_upload_size', byteConv( current ) );
		writeObj( 'info_upload_rate', ( rate > 0 ) ? byteConv( rate ) : 'Unknown' );
		
		writeObj( 'info_file_size', byteConv( total ) );
		writeObj( 'info_time_remain', ( rate > 0 ) ? Math.ceil( ( total - current ) / rate ) : 'Unknown' );
	}
}

/**
 * Update the progress message or an upload, used for errors.
 */

function updateUploadMessage( string ) {
	message = fetchObj( 'message_window' );
	message.className = 'message_window_red';
	message.style.display = 'block';
	
	frames['progress'].location.src = './include/index.htm';
	
	fetchObj( 'progress_bar' ).style.backgroundColor = '#ff3333';
	writeObj( 'message_window', string );
	writeObj( 'progress_current_file', 'Upload Stopped, an error occured..' );
	
	document.title = 'Upload Stopped, an error occured..';
}

/**
 * Non APC, or no upload progress.
 */

function initNoUpload() {
	var window = fetchObj( 'upload_window' );
	window.style.display = 'none';
	
	var message = fetchObj( 'message_window' );
	message.className = 'message_window_green';
	message.style.display = 'block';
	
	document.title = 'Please wait..';
}
