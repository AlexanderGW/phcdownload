<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

if( !defined( "IN_ACP" ) ) die( "Node can not be accessed directly" );

$kernel->admin->read_permission_flags( -1 );

require_once( ROOT_PATH . "include/function_class_form_construct.php" );
$kernel->form = new class_form_construct;

$kernel->clean_array( "_REQUEST", array( "setting" => V_STR, "setting_id" => V_INT ) );

switch ( $kernel->vars['action'] )
{
	#############################################################################
	
	case "write" :
	{
		if( isset( $_POST['control_panel_session_timeout'] ) AND $_POST['control_panel_session_timeout'] < 60 ) $_POST['control_panel_session_timeout'] = 60;
		
		if( isset( $_POST['GD_CHAR_LENGTH'] ) AND $_POST['GD_CHAR_LENGTH'] < 1 ) $_POST['GD_CHAR_LENGTH'] = 1;
		if( isset( $_POST['GD_CHAR_LENGTH'] ) AND $_POST['GD_CHAR_LENGTH'] > 32 ) $_POST['GD_CHAR_LENGTH'] = 32;
		
		if( isset( $_POST['theme_set'] ) )
		{
			list( $_POST['default_skin'], $_POST['default_style'] ) = explode( ",", $_POST['theme_set'] );
			unset( $_POST['theme_set'] );
		}
		
		$kernel->admin->write_config_ini();
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		if( !empty( $kernel->vars['setting'] ) )
		{
			$kernel->tp->call( "admin_sett_menu_sized" );
		}
		else
		{
			$kernel->tp->call( "admin_sett_menu" );
		}
		
		$items = 0;
		$setting_note = array();
		$kernel->vars['html']['setting_list_options'] = "";
		$kernel->vars['setting_menu'][ $kernel->vars['setting'] ] = "selected=\"selected\"";
		
		//archive
		$setting_node[] = array(
			$kernel->ld['phrase_archive'], array(
				$kernel->ld['phrase_menu_archive_settings'] => "archive",
				$kernel->ld['phrase_menu_category_settings'] => "category",
				$kernel->ld['phrase_menu_file_settings'] => "file",
				$kernel->ld['phrase_menu_theme_style_settings'] => "style",
				$kernel->ld['phrase_menu_user_settings'] => "user",
			)
		);
		
		//control panel
		$setting_node[] = array(
			$kernel->ld['phrase_node_control_panel'], array(
				$kernel->ld['phrase_menu_control_panel_settings'] => "control"
			)
		);
		
		//system
		$setting_node[] = array(
			$kernel->ld['phrase_menu_title_system'], array(
				$kernel->ld['phrase_menu_system_settings'] => "system",
				$kernel->ld['phrase_menu_graph_settings'] => "graph",
				$kernel->ld['phrase_menu_logging_settings'] => "log",
				$kernel->ld['phrase_menu_security_settings'] => "security",
				$kernel->ld['phrase_menu_smtp_email_settings'] => "mail"
			)
		);
		
		//build the menu options
		foreach( $setting_node AS $group )
		{
			$kernel->vars['html']['setting_list_options'] .= "<optgroup label=\"" . $group[0] . "\">\r\n";
			
			foreach( $group[1] AS $node => $node_id )
			{
				$kernel->vars['html']['setting_list_options'] .= "<option value=\"" . $node_id . "\"" . $kernel->vars['setting_menu'][ "$node_id" ] . ">" . $node . "</option>\r\n";
				$items++;
			}
			
			$kernel->vars['html']['setting_list_options'] .= "</optgroup>\r\n";
			$items++;
		}
		
		$kernel->vars['html']['setting_list_total_items'] += $items;
		
		switch( $kernel->vars['setting'] )
		{
			#########################################################################
			
			case "archive" :
			{
				$sort_fields = array(
					"file_id" => "file_id",
					"file_name" => "file_name",
					"file_description" => "file_description",
					"file_timestamp" => "file_timestamp",
					"file_mark_timestamp" => "file_mark_timestamp",
					"file_size" => "file_size",
					"file_ranking" => "file_ranking",
					"file_author" => "file_author",
					"file_downloads" => "file_downloads"
				);
				
				$fetch_themes = $kernel->db->query( "SELECT `theme_id`, `theme_name`, `theme_styles`, `theme_disabled` FROM `" . TABLE_PREFIX . "themes` WHERE `theme_disabled` = 0 ORDER BY `theme_name`" );
				
				while( $theme = $kernel->db->data( $fetch_themes ) )
				{		
					if( is_array( unserialize( $theme['theme_styles'] ) ) )
					{
						foreach( unserialize( $theme['theme_styles'] ) AS $style_id )
						{
							$style = $kernel->db->row( "SELECT `style_id`, `style_name` FROM `" . TABLE_PREFIX . "styles` WHERE `style_id` = " . $style_id );
							
							$html_selected = ( $kernel->config['default_skin'] . "," . $kernel->config['default_style'] == $theme['theme_id'] . "," . $style['style_id'] ) ? " selected=\"selected\"" : "";
							
							$theme_list_options .= "<option value=\"" . $theme['theme_id'] . "," . $style['style_id'] . "\"" . $html_selected . ">" . $theme['theme_name'] . " (" . $style['style_name'] . ")</option>";
						}
					}
				}
				
				$kernel->vars['html']['theme_set_list_options'] = $theme_list_options;
				
				$kernel->admin->read_directory_index( "default_language", $kernel->config['default_language'], false, ROOT_PATH . DIR_STEP . "lang" . DIR_STEP, LIST_DIR );
				
				$kernel->page->construct_config_options( "display_default_limit", $kernel->array_set( array_flip( explode( ",", $kernel->config['display_limit_options'] ) ), explode( ",", $kernel->config['display_limit_options'] ) ) );
				$kernel->page->construct_config_options( "archive_search_mode", array( $kernel->ld['phrase_menu_fulltext'] => 0, $kernel->ld['phrase_menu_fulltext_boolean'] => 1, $kernel->ld['phrase_menu_word_phrase_tagging'] => 2 ) );
				$kernel->page->construct_config_options( "display_default_sort", $sort_fields );
				$kernel->page->construct_config_options( "display_default_order", array( $kernel->ld['phrase_menu_ascending'] => "asc", $kernel->ld['phrase_menu_descending'] => "desc" ) );
				
				$kernel->form->construct_table( "phrase_title_archive_display_configuration" );
				$kernel->form->add_field( F_TEXT, "archive_name", "phrase_archive_title" );
				$kernel->form->add_field( F_SELECT, "theme_set", "phrase_template_set" );
				$kernel->form->add_field( F_SELECT, "default_language", "phrase_language_set" );
				$kernel->form->add_field( F_RADIO, "archive_offline", "phrase_archive_status" );
				$kernel->form->add_field( F_TAREA, "archive_offline_message", "phrase_archive_status_message" );
				
				$kernel->form->construct_table( "phrase_title_search_configuration" );
				$kernel->form->add_field( F_SELECT, "archive_search_mode", "phrase_search_mode" );
				
				$kernel->form->construct_table( "phrase_title_archive_content_configuration" );
				$kernel->form->add_field( F_TEXT, "string_max_words", "phrase_announcement_preview_length" );
				$kernel->form->add_field( F_TEXT, "archive_max_comment_on_page", "phrase_comments_on_file_view" );
				$kernel->form->add_field( F_TEXT, "image_max_row_thumbnails", "phrase_gallery_images_per_row" );
				$kernel->form->add_field( F_TAREA, "archive_meta_keywords", "phrase_archive_meta_keywords", array( "rows" => 4 ) );
				$kernel->form->add_field( F_TAREA, "archive_meta_description", "phrase_archive_meta_description", array( "rows" => 4 ) );
				
				$kernel->form->construct_table( "phrase_title_pagination_configuration" );
				$kernel->form->add_field( F_TEXT, "archive_pagination_page_proximity", "phrase_page_pagination" );
				$kernel->form->add_field( F_TEXT, "display_limit_options", "phrase_result_per_page" );
				$kernel->form->add_field( F_SELECT, "display_default_limit", "phrase_default_per_page_option" );
				$kernel->form->add_field( F_SELECT, "display_default_sort", "phrase_page_sort_by_list" );
				$kernel->form->add_field( F_SELECT, "display_default_order", "phrase_page_order_by_list" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "category" :
			{
				$kernel->page->construct_config_options( "category_file_count_mode", array( $kernel->ld['phrase_menu_root_category'] => 0, $kernel->ld['phrase_menu_root_category_subcategories'] => 1, $kernel->ld['phrase_menu_root_category_subcategories_grouped'] => 2 ) );
				$kernel->page->construct_config_options( "category_new_file_construct_mode", array( $kernel->ld['phrase_menu_root_category'] => 0, $kernel->ld['phrase_menu_root_category_subcategories'] => 1 ) );
				$kernel->page->construct_config_options( "category_subcategory_mode", array( $kernel->ld['phrase_menu_do_not_show'] => 0, $kernel->ld['phrase_menu_show_first_page_only'] => 1, $kernel->ld['phrase_menu_show_all_pages'] => 2 ) );
				$kernel->page->construct_config_options( "category_subcategory_links_mode", array( $kernel->ld['phrase_menu_do_not_show'] => 0, $kernel->ld['phrase_menu_show_index_only'] => 1, $kernel->ld['phrase_menu_show_category_only'] => 2, $kernel->ld['phrase_menu_show_both_pages'] => 3 ) );
				
				$kernel->form->construct_table( "phrase_title_archive_display_configuration" );
				$kernel->form->add_field( F_SELECT, "category_subcategory_mode", "phrase_subcategory_display_mode" );
				$kernel->form->add_field( F_SELECT, "category_subcategory_links_mode", "phrase_subcategory_quicklink_display_mode" );
				$kernel->form->add_field( F_SELECT, "category_file_count_mode", "phrase_file_counting_method" );
				$kernel->form->add_field( F_SELECT, "category_new_file_construct_mode", "phrase_newest_file_method" );
				$kernel->form->add_field( F_RADIO, "archive_show_category_description", "phrase_show_category_description" );
				
				$kernel->form->construct_table( "phrase_title_pagination_configuration" );
				$kernel->form->add_field( F_TEXT, "string_max_length_file_name", "phrase_file_name_length" );
				$kernel->form->add_field( F_TEXT, "string_max_length", "phrase_file_preview_description_length" );
				$kernel->form->add_field( F_TEXT, "string_max_word_length", "phrase_max_word_characters" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "control" :
			{
				$kernel->page->construct_config_options( "admin_display_default_limit", $kernel->array_set( array_flip( explode( ",", $kernel->config['admin_display_limit_options'] ) ), explode( ",", $kernel->config['admin_display_limit_options'] ) ) );
				$kernel->page->construct_config_options( "system_file_add_form_type", array( $kernel->ld['phrase_menu_lofi_form'] => 0, $kernel->ld['phrase_menu_advanced_form'] => 1 ) );
				$kernel->page->construct_config_options( "system_file_edit_form_type", array( $kernel->ld['phrase_menu_lofi_form'] => 0, $kernel->ld['phrase_menu_advanced_form'] => 1 ) );
				$kernel->page->construct_config_options( "system_file_mass_edit_form_type", array( $kernel->ld['phrase_menu_lofi_form'] => 0, $kernel->ld['phrase_menu_advanced_form'] => 1 ) );
				$kernel->page->construct_config_options( "gd_thumbnail_watermark_mode", array( $kernel->ld['phrase_menu_no_archive_title'] => 0, $kernel->ld['phrase_menu_include_archive_title'] => 1 ) );
				$kernel->page->construct_config_options( "admin_message_page_forward_mode", array( $kernel->ld['phrase_menu_skip_messaging'] => 0, $kernel->ld['phrase_menu_show_messaging'] => 1 ) );
				$kernel->page->construct_config_options( "admin_message_redirect_mode", array( $kernel->ld['phrase_menu_node_main_only'] => 0, $kernel->ld['phrase_menu_previous_page'] => 1 ) );
				$kernel->page->construct_config_options( "notepad_access_mode", array( $kernel->ld['phrase_menu_no_restriction'] => 0, $kernel->ld['phrase_menu_administrators_only'] => 1 ) );
				
				$kernel->form->construct_table( "phrase_title_session_configuration" );
				$kernel->form->add_field( F_TEXT, "control_panel_session_timeout", "phrase_session_timeout" );
				$kernel->form->add_field( F_RADIO, "admin_session_sensitive_username", "phrase_case_sensitive_username", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "admin_session_ip_check", "phrase_check_ip_address", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "admin_session_http_check", "phrase_check_http_browser", array( "radio_mode" => 1 ) );
				
				$kernel->form->construct_table( "phrase_title_message_report_configuration" );
				$kernel->form->add_field( F_SELECT, "admin_message_page_forward_mode", "phrase_message_display_mode" );
				$kernel->form->add_field( F_SELECT, "admin_message_redirect_mode", "phrase_message_automatic_redirect" );
				$kernel->form->add_field( F_TEXT, "admin_message_refresh_seconds", "phrase_message_display_time", false, ( $kernel->config['admin_message_page_forward_mode'] == 0 ) );
				
				$kernel->form->construct_table( "phrase_title_management_configuration" );
				$kernel->form->add_field( F_SELECT, "system_file_add_form_type", "phrase_file_add_form" );
				$kernel->form->add_field( F_SELECT, "system_file_edit_form_type", "phrase_file_edit_form" );
				$kernel->form->add_field( F_SELECT, "system_file_mass_edit_form_type", "phrase_file_mass_edit_form" );
				$kernel->form->add_field( F_RADIO, "admin_allow_file_upload", "phrase_file_uploading", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "gd_thumbnail_feature", "phrase_image_thumbnails", array( "radio_mode" => 1 ), !extension_loaded( "gd" ) );
				$kernel->form->add_field( F_SELECT, "gd_thumbnail_watermark_mode", "phrase_image_watermark_mode", false, !extension_loaded( "gd" ) );
				$kernel->form->add_field( F_TEXT, "form_max_mirror_fields", "phrase_download_mirrors_fields" );
				$kernel->form->add_field( F_TEXT, "gd_thumbnail_max_dimensions", "phrase_thumbnail_max_size", false, !extension_loaded( "gd" ) );
				$kernel->form->add_field( F_TEXT, "upload_folder_sync_interval", "phrase_upload_folder_check_interval" );
				$kernel->form->add_field( F_SELECT, "notepad_access_mode", "phrase_notepad_access_mode" );
				
				$kernel->form->construct_table( "phrase_title_pagination_configuration" );
				$kernel->form->add_field( F_TEXT, "admin_pagination_page_proximity", "phrase_page_pagination" );
				$kernel->form->add_field( F_TEXT, "admin_display_limit_options", "phrase_result_per_page" );
				$kernel->form->add_field( F_SELECT, "admin_display_default_limit", "phrase_default_per_page_option" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "log" :
			{
				for( $i = 1; $i <= 12; $i++ )
				{
					$phrase_month = ( $i > 1 ) ? sprintf( $kernel->ld['phrase_x_months'], $i ) : sprintf( $kernel->ld['phrase_x_month'], $i );
					$month_list_options[ $phrase_month ] = $i;
				}
				
				$kernel->page->construct_config_options( "admin_log_active_period", $month_list_options );
				
				$kernel->form->construct_table( "phrase_title_pagination_configuration" );
				$kernel->form->add_field( F_SELECT, "admin_log_active_period", "phrase_log_active_period" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "file" :
			{
				$kernel->page->construct_config_options( "display_file_list_mode", array( $kernel->ld['phrase_menu_show_file_details'] => 0, $kernel->ld['phrase_menu_download_file'] => 1 ) );
				$kernel->page->construct_config_options( "archive_file_hash_mode", array( $kernel->ld['phrase_disabled'] => 0, $kernel->ld['phrase_menu_hash_md5'] => 1, $kernel->ld['phrase_menu_hash_sha1'] => 2, $kernel->ld['phrase_menu_hash_both'] => 3 ) );
				$kernel->page->construct_config_options( "file_user_rating_mode", array( $kernel->ld['phrase_menu_text_mode'] => 0, $kernel->ld['phrase_menu_stars_mode'] => 1, $kernel->ld['phrase_menu_stars_text_mode'] => 2 ) );
				$kernel->page->construct_config_options( "file_download_mode", array( $kernel->ld['phrase_menu_standard_redirect_method'] => 0, $kernel->ld['phrase_menu_masked_url_method'] => 1 ) );
				$kernel->page->construct_config_options( "file_download_size_mode", array( $kernel->ld['phrase_disabled'] => 0, $kernel->ld['phrase_menu_post_to_browser'] => 1 ) );
				
				$kernel->form->construct_table( "phrase_file_download_configuration" );
				
				$kernel->form->add_field( F_RADIO, "allow_unknown_url_linking", "phrase_allow_foreign_url" );
				$kernel->form->add_field( F_RADIO, "archive_file_check_hash", "phrase_file_hash_checking", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "archive_file_check_url", "phrase_file_url_checking", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_SELECT, "file_download_mode", "phrase_download_method" );
				$kernel->form->add_field( F_RADIO, "file_download_indirect_mode", "phrase_download_indirect_method", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_SELECT, "file_download_size_mode", "phrase_browser_post_file_size" );
				$kernel->form->add_field( F_TEXT, "system_parse_timeout", "phrase_file_parse_timeout" );
				$kernel->form->add_field( F_RADIO, "email_notify_modified_url", "phrase_file_url_broken_email_notice", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "email_notify_broken_url", "phrase_file_url_modified_email_notice", array( "radio_mode" => 1 ) );
				
				$kernel->form->construct_table( "phrase_file_upload_configuration" );
				$kernel->form->add_field( F_RADIO, "archive_allow_upload", "phrase_file_uploading", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "admin_file_type_filter_enabled", "phrase_filetype_filter", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "system_upload_scan_embedded_php", "phrase_embedded_php_code_scan", array( "radio_mode" => 1 ), IN_DEMO_MODE );
				
				$kernel->form->construct_table( "phrase_file_display_configuration" );
				$kernel->form->add_field( F_SELECT, "display_file_list_mode", "phrase_file_listing_mode" );
				$kernel->form->add_field( F_SELECT, "archive_file_hash_mode", "phrase_file_hash_mode" );
				$kernel->form->add_field( F_RADIO, "archive_show_empty_custom_fields", "phrase_show_empty_fields" );
				$kernel->form->add_field( F_SELECT, "file_user_rating_mode", "phrase_file_ranking_style" );
				
				$kernel->form->construct_table( "phrase_title_submission_configuration" );
				$kernel->form->add_field( F_RADIO, "admin_file_submit_approval", "phrase_moderator_approval", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "EMAIL_FILE_SUBMIT", "phrase_submission_email_notice", array( "radio_mode" => 1 ) );
				
				$kernel->form->construct_table( "phrase_file_content_configuration" );
				$kernel->form->add_field( F_TAREA, "file_download_time_counters", "phrase_download_time_calcuations" );
				$kernel->form->add_field( F_TAREA, "file_byte_rounders", "phrase_byte_conversions" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "mail" :
			{
				$kernel->form->construct_table( "phrase_title_smtp_email_configuration" );
				$kernel->form->add_field( F_TEXT, "mail_smtp_path", "phrase_smtp_path", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "mail_smtp_port", "phrase_smtp_port", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "mail_inbound", "phrase_inward_email", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "mail_outbound", "phrase_outward_email", false, IN_DEMO_MODE );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "security" :
			{
				$kernel->form->construct_table( "phrase_download_authentication_configuration" );
				$kernel->form->add_field( F_TEXT, "upload_dir_http_username", "phrase_http_username" );
				$kernel->form->add_field( F_TEXT, "upload_dir_http_password", "phrase_http_password" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "system" :
			{
				$kernel->page->message_report( $kernel->ld['phrase_database_details_hidden'], M_NOTICE );
				
				$kernel->page->construct_config_options( "debug_mode", array( $kernel->ld['phrase_disabled'] => 0, $kernel->ld['phrase_menu_debug_console'] => 1, $kernel->ld['phrase_menu_debug_all'] => 2, $kernel->ld['phrase_menu_debug_query_explain'] => 3 ) );
				
				$kernel->form->construct_table( "phrase_title_path_configuration" );
				$kernel->form->add_field( F_TEXT, "system_root_url_home", "phrase_title_home_url" );
				$kernel->form->add_field( F_TEXT, "system_root_url_path", "phrase_title_archive_root_url", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "system_root_dir", "phrase_title_archive_root_dir", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "system_root_url_upload", "phrase_title_upload_url", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "system_root_dir_upload", "phrase_title_upload_dir", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "system_root_url_gallery", "phrase_title_gallery_url", false, IN_DEMO_MODE );
				$kernel->form->add_field( F_TEXT, "system_root_dir_gallery", "phrase_title_gallery_dir", false, IN_DEMO_MODE );
				
				$kernel->form->construct_table( "phrase_title_system_configuration" );
				$kernel->form->add_field( F_TEXT, "system_date_format_short", "phrase_short_date_format" );
				$kernel->form->add_field( F_TEXT, "system_date_format_long", "phrase_long_date_format" );
				$kernel->form->add_field( F_TEXT, "file_rename_suffix", "phrase_file_rename_extension" );
				$kernel->form->add_field( F_TAREA, "system_allowed_html_tags", "phrase_archive_allowed_html_tags", array( "rows" => 4 ) );
				$kernel->form->add_field( F_RADIO, "gzip_enabled", "phrase_gzip_compression" );
				$kernel->form->add_field( F_RADIO, "allow_airs_access", "phrase_allow_airs_access", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_SELECT, "debug_mode", "phrase_debug_mode", false, ( IN_DEMO_MODE == true ) );
				//$kernel->form->add_field( F_TEXT, "upload_file_max_size", "phrase_maximum_upload_size" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "user" :
			{
				$kernel->form->construct_table( "phrase_title_user_access_configuration" );
				$kernel->form->add_field( F_RADIO, "archive_allow_user_login", "phrase_user_allow_login", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "archive_allow_user_registration", "phrase_user_login_registration", array( "radio_mode" => 1 ) );
				$kernel->form->add_field( F_RADIO, "session_force_login", "phrase_force_login" );
				$kernel->form->add_field( F_RADIO, "EMAIL_USER_ACTIVATION", "phrase_registration_activation" );
				$kernel->form->add_field( F_RADIO, "EMAIL_REG_NOTICE", "phrase_register_email_notice" );
				$kernel->form->add_field( F_RADIO, "session_sensitive_username", "phrase_case_sensitive_username", array( "radio_mode" => 1 ) );
				
				$kernel->form->construct_table( "phrase_title_user_security_configuration" );
				$kernel->form->add_field( F_RADIO, "GD_POST_MODE_GUEST", "phrase_use_gd_posting_guest", array( "radio_mode" => 1 ), !extension_loaded( "gd" ) );
				$kernel->form->add_field( F_RADIO, "GD_POST_MODE_USER", "phrase_use_gd_posting_user", array( "radio_mode" => 1 ), !extension_loaded( "gd" ) );
				$kernel->form->add_field( F_RADIO, "GD_REGISTER_MODE", "phrase_use_gd_register", array( "radio_mode" => 1 ), !extension_loaded( "gd" ) );
				$kernel->form->add_field( F_TEXT, "GD_CHAR_ARRAY", "phrase_gd_image_char_array" );
				$kernel->form->add_field( F_TEXT, "GD_CHAR_LENGTH", "phrase_gd_image_char_length" );
				$kernel->form->add_field( F_TEXT, "archive_comment_grace_time", "phrase_comment_grace_period" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "style" :
			{
				//$kernel->ld['phrase_menu_css_builder'] => 0, 
				$kernel->page->construct_config_options( "style_edit_mode", array( $kernel->ld['phrase_menu_advanced_simple_form'] => 1 ) );
				
				$kernel->form->construct_table( "phrase_title_user_access_configuration" );
				$kernel->form->add_field( F_RADIO, "style_cache_to_file", "phrase_style_css_cache_to_file" );
				$kernel->form->add_field( F_SELECT, "style_edit_mode", "phrase_style_edit_mode", false, true );
				$kernel->form->add_field( F_TEXT, "style_file_column_count", "phrase_style_file_column_count" );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			case "graph" :
			{
				$kernel->form->construct_table( "phrase_title_graph_configuration" );
				$kernel->form->add_field( F_TEXT, "graph_cache_time", "phrase_graph_cache_time", false, !extension_loaded( "gd" ) );
				$kernel->form->add_field( F_TEXT, "graph_size_dimensions", "phrase_graph_dimensions", false, !extension_loaded( "gd" ) );
				
				$kernel->form->finish();
				
				break;
			}
			
			#########################################################################
			
			default :
			{
				break;
			}
		}
		
		break;
	}
}

?>

