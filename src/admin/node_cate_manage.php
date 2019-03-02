<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'CAT_MAN', 'CAT_DEL' );

$kernel->clean_array( "_REQUEST", array( "category_id" => V_INT ) );

switch( $kernel->vars['action'] )
{
	#############################################################################
	
	case "edit" :
	{
		$kernel->admin->read_permission_flags( 'CAT_MAN' );
		
		$kernel->tp->call( "admin_cate_edit" );
		
		$category = $kernel->db->row( "SELECT * FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $kernel->vars['category_id'] . " LIMIT 1" );
		
		$kernel->page->construct_category_list( $category['category_sub_id'] );
		$kernel->archive->construct_list_options( $category['category_doc_id'], "document", $kernel->db->query( "SELECT `document_id`, `document_title` FROM `" . TABLE_PREFIX . "documents` ORDER BY `document_title`" ) );
		
		$category['category_name'] = $kernel->format_input( $category['category_name'], T_FORM );
		$category['category_description'] = $kernel->format_input( $category['category_description'], T_FORM );
		
		$category['password_status'] = ( empty( $category['category_password'] ) ) ? "disabled=\"disabled\"" : "";
		
		$kernel->tp->cache( $category );
		
		break;
	}
	
	#############################################################################
	
	case "update" :
	{
		$kernel->admin->read_permission_flags( 'CAT_MAN' );
		
		$kernel->clean_array( "_POST", array( "category_name" => V_STR, "category_description" => V_STR,
		"category_password" => V_STR, "category_password_confirm" => V_STR, "category_doc_id" => V_INT,
		"category_sub_id" => V_INT ) );
		
		if( empty( $kernel->vars['category_name'] ) )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_category_name'], M_ERROR );
		}
		elseif( $kernel->vars['category_id'] == $kernel->vars['category_sub_id'] )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_category_move'], M_ERROR );
		}
		elseif( !empty( $kernel->vars['category_password'] ) AND strlen( $kernel->vars['category_password']	) < 3 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_invalid_password'], M_ERROR );
		}
		elseif( $kernel->vars['category_password'] != $kernel->vars['category_password_confirm'] )
		{
			$kernel->page->message_report( $kernel->ld['phrase_bad_match_password'], M_ERROR );
		}
		else
		{
			$categorydata = array(
				"category_sub_id" => $kernel->vars['category_sub_id'],
				"category_name" => $kernel->format_input( $kernel->vars['category_name'], T_DB ),
				"category_description" => $kernel->archive->string_word_length_slice( $kernel->format_input( $kernel->vars['category_description'], T_DB ), $kernel->config['string_max_word_length'] ),
				"category_doc_id" => $kernel->vars['category_doc_id']
			);
			
			if( !empty( $kernel->vars['category_password'] ) )
			{
				$categorydata['category_password'] = md5( $kernel->vars['category_password'] );
			}
			
			if( $_POST['category_password_clear'] == "1" )
			{
				$categorydata['category_password'] = "";
			}
			
			$kernel->db->update( "categories", $categorydata, "WHERE `category_id` = " . $kernel->vars['category_id'] );
			
			$kernel->admin->write_category_list();
			
			$kernel->archive->update_database_counter( "categories" );
			
			$kernel->admin->message_admin_report( "log_category_edited", $kernel->vars['category_name'] );
		}
		break;
	}
	
	#############################################################################
	
	case "manage" :
	{
		$count = 0;
		
		if( isset( $_POST['form_delete'] ) OR $kernel->vars['category_id'] > 0 )
		{
			$kernel->admin->read_permission_flags( 'CAT_DEL' );
			
			if( $kernel->vars['category_id'] > 0 )
			{
				$delete_data = $kernel->db->item( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $kernel->vars['category_id'] );
				
				$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $kernel->vars['category_id'] );
				$count++;
			}
			elseif( is_array( $_POST['checkbox'] ) )
			{
				foreach( $_POST['checkbox'] AS $category )
				{
					$delete_data[] = $kernel->db->item( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $category );
					
					$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $category );
					$count++;
				}
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
			}
			
			$kernel->admin->write_category_list();
			
			$kernel->archive->update_database_counter( "categories" );
			
			$kernel->admin->message_admin_report( "log_category_deleted", $count, $delete_data );
		}
		
		###########################################################################
		
		elseif( isset( $_POST['form_move'] ) )
		{
			$kernel->admin->read_permission_flags( 'CAT_MAN' );
			
			$kernel->clean_array( "_POST", array( "category_move_id" => V_INT ) );
			
			if( is_array( $_POST['checkbox'] ) )
			{
				foreach( $_POST['checkbox'] AS $category )
				{
					if( $category == $kernel->vars['category_move_id'] ) continue;
					
					$move_data[] = $kernel->db->item( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $category );
					
					$kernel->db->query( "UPDATE `" . TABLE_PREFIX . "categories` SET `category_sub_id` = " . $kernel->vars['category_move_id'] . " WHERE `category_id` = " . $category );
					$count++;
					
					$kernel->archive->update_category_file_count( $category );
					$kernel->archive->update_category_new_file( $category );
				}
				
				$kernel->archive->update_category_file_count( $kernel->vars['category_move_id'] );
				$kernel->archive->update_category_new_file( $kernel->vars['category_move_id'] );
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_checkbox_none_selected'], M_ERROR, HALT_EXEC );
			}
			
			$kernel->admin->write_category_list();
			
			$kernel->archive->update_database_counter( "categories" );
			
			$kernel->admin->message_admin_report( "log_category_moved", $count, $move_data );
		}
		
		break;
	}
	
	#############################################################################
	
	case "resync" :
	{
		$kernel->admin->read_permission_flags( 'CAT_MAN' );
		
		//resync categories and other bits
		$kernel->archive->update_category_file_count( $kernel->vars['category_id'] );
		$kernel->archive->update_category_new_file( $kernel->vars['category_id'] );
		
		$subcat_files = 0;
		$total_files = 0;
		$newest_file_id = 0;
		$root_category = $kernel->vars['category_id'];
		
		$kernel->archive->update_category_new_file( $kernel->vars['category_id'] );
		$kernel->archive->update_category_file_count( $kernel->vars['category_id'] );
		
		$category = $kernel->db->row( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $kernel->vars['category_id'] );
		
		$kernel->admin->write_category_list();
		
		$kernel->archive->update_database_counter( "categories" );
		
		$kernel->admin->message_admin_report( "log_category_resynced", $category['category_name'] );
		
		break;
	}
	
	#############################################################################
	
	case "view" :
	{
		$get_categories = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "categories` WHERE `category_sub_id` = " . $kernel->vars['category_id'] . " ORDER BY `category_order`, `category_name`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_categories'], M_ERROR );
		}
		else
		{
			$kernel->tp->call( "admin_cate_header" );
			
			while( $category = $kernel->db->data( $get_categories ) )
			{
				$kernel->tp->call( "admin_cate_row" );
				
				$category['category_name'] = $kernel->format_input( $category['category_name'], T_NOHTML );
				$category['category_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $category['category_description'], T_NOHTML ), 15 );
				
				//sub-categories
				if( $kernel->db->numrows( "SELECT category_id FROM " . TABLE_PREFIX . "categories WHERE category_sub_id = " . $category['category_id'] ) > 0 )
				{
					$category['category_html_name'] = "<a href=\"?hash=" . $kernel->session->vars['hash'] . "&node=cate_manage&action=view&category_id=" . $category['category_id'] . "\">" . $category['category_name'] . "</a>";
				}
				else
				{
					$category['category_html_name'] = $category['category_name'];
				}
				
				$category['category_state_icon'] = $kernel->admin->construct_icon( "lock.gif", $kernel->ld['phrase_category_password_protected'], ( !empty( $category['category_password'] ) ) );
				
				$kernel->tp->cache( $category );
			}
			
			$kernel->tp->call( "admin_cate_footer" );
			
			$kernel->page->construct_category_list();
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$get_categories = $kernel->db->query( "SELECT * FROM `" . TABLE_PREFIX . "categories` WHERE `category_sub_id` = 0 ORDER BY `category_order`, `category_name`" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_categories'], M_ERROR );
		}
		else
		{
			$kernel->tp->call( "admin_cate_header" );
			
			while( $category = $kernel->db->data( $get_categories ) )
			{
				$kernel->tp->call( "admin_cate_row" );
				
				$category['category_name'] = $kernel->format_input( $category['category_name'], T_NOHTML );
				$category['category_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $category['category_description'], T_NOHTML ), 15 );
				
				if( $kernel->db->numrows( "SELECT category_id FROM " . TABLE_PREFIX . "categories WHERE category_sub_id = " . $category['category_id'] ) > 0 )
				{
					$category['category_html_name'] = "<a href=\"?hash=" . $kernel->session->vars['hash'] . "&node=cate_manage&action=view&category_id=" . $category['category_id'] . "\">" . $category['category_name'] . "</a>";
				}
				else
				{
					$category['category_html_name'] = $category['category_name'];
				}
				
				$category['category_state_icon'] = $kernel->admin->construct_icon( "lock.gif", $kernel->ld['phrase_category_password_protected'], ( !empty( $category['category_password'] ) ) );
				
				$kernel->tp->cache( $category );
			}
			
			$kernel->tp->call( "admin_cate_footer" );
			
			$kernel->page->construct_category_list();
		}
		
		break;
	}
}

?>

