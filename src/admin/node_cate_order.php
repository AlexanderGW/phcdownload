<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( 'CAT_MAN' );

$kernel->clean_array( "_REQUEST", array( "category_id" => V_INT ) );

switch( $kernel->vars['action'] )
{	
	#############################################################################
	
	case "update" :
	{
		if( is_array( $_POST['categories'] ) )
		{
			$row_count = 0;
			
			foreach( $_POST['categories'] AS $category_id )
			{
				$order_data[] = $kernel->db->item( "SELECT `category_name` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $category_id );
				
				$kernel->db->update( "categories", array( "category_order" => $_POST['order'][ "$row_count" ] ), "WHERE `category_id` = " . $category_id );
				
				$row_count++;
			}
			
			$kernel->admin->write_category_list();
			
			$kernel->archive->update_database_counter( "categories" );
		}
		
		$kernel->admin->message_admin_report( $kernel->ld['phrase_log_category_reorder'], 0, $order_data );
		
		break;
	}
	
	#############################################################################
	
	case "view" :
	{
		$get_categories = $kernel->db->query( "SELECT c.* FROM " . TABLE_PREFIX . "categories c WHERE c.category_sub_id = " . $kernel->vars['category_id'] . " ORDER BY c.category_order, c.category_name" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_subcategories'], M_ERROR );
		}
		else
		{
			$kernel->tp->call( "admin_cate_order_header" );
			
			while( $category = $kernel->db->data( $get_categories ) )
			{
				$kernel->tp->call( "admin_cate_order_row" );
				
				$category['category_name'] = $kernel->format_input( $category['category_name'], T_NOHTML );
				$category['category_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $category['category_description'], T_NOHTML ), $kernel->config['string_max_words'] );
				
				if( $kernel->db->numrows( "SELECT category_id FROM " . TABLE_PREFIX . "categories WHERE category_sub_id = " . $category['category_id'] ) > 0 )
				{
					$category['category_html_name'] = "<a href=\"?hash=" . $kernel->session->vars['hash'] . "&node=cate_order&action=view&category_id=" . $category['category_id'] . "\">" . $category['category_name'] . "</a>";
				}
				else
				{
					$category['category_html_name'] = $category['category_name'];
				}
				
				$category['category_state_icon'] = $kernel->admin->construct_icon( "lock.gif", $kernel->ld['phrase_category_password_protected'], ( !empty( $category['category_password'] ) ) );
				
				$kernel->tp->cache( $category );
			}
			
			$kernel->tp->call( "admin_cate_order_footer" );
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		$get_categories = $kernel->db->query( "SELECT c.* FROM " . TABLE_PREFIX . "categories c WHERE c.category_sub_id = 0 ORDER BY c.category_order, c.category_name" );
		
		if( $kernel->db->numrows() == 0 )
		{
			$kernel->page->message_report( $kernel->ld['phrase_no_categories'], M_ERROR );
		}
		else
		{
			$kernel->tp->call( "admin_cate_order_header" );
			
			while( $category = $kernel->db->data( $get_categories ) )
			{
				$kernel->tp->call( "admin_cate_order_row" );
				
				$category['category_name'] = $kernel->format_input( $category['category_name'], T_NOHTML );
				$category['category_description'] = $kernel->archive->return_string_words(	$kernel->format_input( $category['category_description'], T_NOHTML ), $kernel->config['string_max_words'] );
				
				if( $kernel->db->numrows( "SELECT category_id FROM " . TABLE_PREFIX . "categories WHERE category_sub_id = " . $category['category_id'] ) > 0 )
				{
					$category['category_html_name'] = "<a href=\"?hash=" . $kernel->session->vars['hash'] . "&node=cate_order&action=view&category_id=" . $category['category_id'] . "\">" . $category['category_name'] . "</a>";
				}
				else
				{
					$category['category_html_name'] = $category['category_name'];
				}
				
				$category['category_state_icon'] = $kernel->admin->construct_icon( "lock.gif", $kernel->ld['phrase_category_password_protected'], ( !empty( $category['category_password'] ) ) );
				
				$kernel->tp->cache( $category );
			}
			
			$kernel->tp->call( "admin_cate_order_footer" );
		}
		
		break;
	}
}

?>

