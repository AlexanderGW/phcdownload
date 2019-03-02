<?php

################################################################################
# PHCDownload (version 1.1.1) - Document Management & Manipulation System
################################################################################
# Copyright (c) 2005 - 2008 developed by Alex Gailey-White @ www.phpcredo.com
# PHCDownload is free to use. Please visit the website for further licence
# information and details on re-distribution and its use.
################################################################################

require_once( "global.php" );

$kernel->clean_array( "_REQUEST", array( "id" => V_INT, "node" => V_STR, "type" => V_STR, "action" => V_STR ) );

switch( $kernel->vars['node'] )
{
	#############################################################################
	# User login
	
	case "login" :
	{
		switch( $kernel->vars['type'] )
		{
			#############################################################################
		  
			case "category" :
			{
				if( $kernel->vars['id'] == 0 )
				{
					$kernel->page->message_report( $kernel->ld['phrase_no_category'], M_ERROR );
				}
				else
				{
					$kernel->clean_array( "_REQUEST", array( "category_password" => V_MD5, "referer" => V_STR ) );
					
					if( $kernel->db->numrows( "SELECT `category_id` FROM " . TABLE_PREFIX . "categories WHERE category_id = " . $kernel->vars['id'] ) == 0 )
					{
						$kernel->page->message_report( $kernel->ld['phrase_invalid_category'], M_ERROR );
					}
					else
					{
						$category = $kernel->db->row( "SELECT `category_id`, `category_name`, `category_password` FROM `" . TABLE_PREFIX . "categories` WHERE `category_id` = " . $kernel->vars['id'] );
						
						$kernel->vars['page_struct']['system_page_navigation_id'] = $kernel->vars['page_struct']['system_page_announcement_id'] = $category['category_id'];
						$kernel->vars['page_struct']['system_page_action_title'] = "";
						
						if( empty( $category['category_password'] ) )
						{
							$kernel->page->message_report( $kernel->ld['phrase_no_password_category'], M_ERROR );
						}
						else
						{
							if( isset( $_POST['login'] ) )
							{
								if( $kernel->vars['category_password'] !== $category['category_password'] )
								{
									$kernel->page->message_report( $kernel->ld['phrase_password_no_match'], M_ERROR );
								}
								else
								{
									$_SESSION['session_category_' . $category['category_id'] ] = $kernel->vars['category_password'];
									
									header( "Location: " . $kernel->format_input( $kernel->vars['referer'], T_URL_DEC ) ); exit;
								}
							}
							
							$kernel->tp->call( "category_login" );
						}
					}
						
					$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );
				}
						
				break;
			}
			
			#############################################################################
			
			default :
			{
				//if( !empty( $kernel->session->vars['session_hash'] ) AND $kernel->session->vars['session_hash'] === $_SESSION['phcdl_session_hash'] )
				if( !empty( $kernel->session->vars['session_hash'] ) AND USER_LOGGED_IN == true )
				{
					//$kernel->page->message_report( $kernel->ld['phrase_logged_in'], M_NOTICE );
					
					header( "Location: panel.php" ); exit;
				}
				else
				{
					if( isset( $_POST['do_login'] ) )
					{
						$kernel->clean_array( "_POST", array( "password" => V_MD5, "username" => V_STR ) );
						
						$panel = $kernel->db->row( "SELECT u.user_name FROM " . TABLE_PREFIX . "users u WHERE u.user_name = '" . $kernel->vars['username'] . "'" );
						
						if( ( $kernel->config['session_sensitive_username'] == "true" AND $panel['user_name'] != $kernel->vars['username'] ) OR ( $kernel->config['session_sensitive_username'] == "false" AND strtolower( $panel['user_name'] ) != strtolower( $kernel->vars['username'] ) ) )
						{
							$kernel->session->construct_user_login( $kernel->ld['phrase_bad_login_username'] );
						}
						else
						{
							$check_user_data = $kernel->db->query( "SELECT u.user_id, u.user_group_id, u.user_name, u.user_password, u.user_active, g.usergroup_archive_permissions, g.usergroup_categories FROM " . TABLE_PREFIX . "users u LEFT JOIN " . TABLE_PREFIX . "usergroups g ON ( u.user_group_id = g.usergroup_id ) WHERE u.user_name = '" . $kernel->vars['username'] . "' AND u.user_password = '" . $kernel->vars['password'] . "'" );
							
							if( $kernel->db->numrows() == 0 )
							{
								$kernel->session->construct_user_login( $kernel->ld['phrase_bad_login_password'] );
							}
							else
							{
								$panel = $kernel->db->row( $check_user_data );
								
								if( $panel['user_active'] == "N" )
								{
									$kernel->page->message_report( $kernel->ld['phrase_user_account_inactive'], M_WARNING );
								}
								else
								{
									$kernel->db->query( "DELETE FROM " . TABLE_PREFIX . "sessions WHERE session_name = '" . $kernel->vars['username'] . "'" );
									
									$new_hash = md5( uniqid( rand() ) );
									
									$userdata = array( 
										"session_hash"				=> $new_hash,
										"session_group_id"			=> $panel['user_group_id'],
										"session_user_id"			=> $panel['user_id'],
										"session_ip_address"		=> IP_ADDRESS,
										"session_name"				=> $panel['user_name'],
										"session_password"			=> $kernel->vars['password'],
										"session_agent"				=> HTTP_AGENT,
										"session_timestamp"			=> UNIX_TIME,
										"session_run_timestamp"		=> UNIX_TIME,
										"session_permissions"		=> $panel['usergroup_archive_permissions'],
										"session_categories"		=> $panel['usergroup_categories']
									);
									
									$kernel->db->insert( "sessions", $userdata );
									
									$cachedata = array( 
										"cache_session_hash"		=> $new_hash,
										"cache_user_id"				=> $panel['user_id']
									);
									
									if( $kernel->db->numrows( "SELECT `cache_user_id` FROM `" . TABLE_PREFIX . "session_cache` WHERE `cache_user_id` = " . $panel['user_id'] ) > 0 )
									{
										$kernel->db->update( "session_cache", $cachedata, "WHERE `cache_user_id` = " . $panel['user_id'] );
									}
									else
									{
										$kernel->db->insert( "session_cache", $cachedata );
									}
									
									$kernel->session->clear_user_cache();
									
									if( isset( $_POST['remember_cookie'] ) )
									{
										setcookie( "phcdl_session_hash", $new_hash, UNIX_TIME + ( 86400 * 365 ), "/", HTTP_HOST );
										setcookie( "phcdl_user_id", $panel['user_id'], UNIX_TIME + ( 86400 * 365 ), "/", HTTP_HOST );
										setcookie( "phcdl_user_password", $kernel->vars['password'], UNIX_TIME + ( 86400 * 365 ), "/", HTTP_HOST );
									}
									
									$_SESSION['phcdl_session_hash'] = $new_hash;
									$_SESSION['phcdl_user_id'] = $panel['user_id'];
									$_SESSION['phcdl_user_password'] = $kernel->vars['password'];
									
									header( "Location: index.php" ); exit;
								}
							}
						}
					}
					else
					{
						$kernel->session->construct_user_login( $kernel->ld['phrase_no_session'] );
					}
				}
				
				$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );
					
				break;
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "logout" :
	{
		$kernel->session->clear_user_cache();
		
		header( "Location: index.php" ); exit;
		
		break;
	}
	
	#############################################################################
	
	case "image" :
	{
		$kernel->clean_array( "_REQUEST", array( "verify_hash" => V_STR ) );
		
		if( empty( $kernel->vars['verify_hash'] ) )
		{
			exit;
		}
		else
		{
			$get_verification = $kernel->db->query( "SELECT `verify_key` FROM `" . TABLE_PREFIX . "users_verify` WHERE `verify_hash` = '" . $kernel->vars['verify_hash'] . "' AND `verify_ip_address` = '" . IP_ADDRESS . "'" );
			
			if( $kernel->db->numrows( $get_verification ) == 0 )
			{
				exit;
			}
			else
			{
				$kernel->image->construct_security_code_image( $kernel->db->data( $get_verification ) );
			}
		}
		
		break;
	}
	
	#############################################################################
	
	case "activate" :
	{
		$kernel->clean_array( "_GET", array( "activate_hash" => V_STR, "activate_id" => V_INT, "user_id" => V_INT ) );
		
		if( !empty( $kernel->vars['activate_hash'] ) AND !empty( $kernel->vars['activate_id'] ) AND !empty( $kernel->vars['user_id'] ) )
		{
			if( $kernel->db->numrows( "SELECT `activate_id` FROM `" . TABLE_PREFIX . "users_activate` WHERE `activate_id` = " . $kernel->vars['activate_id'] . " AND `activate_hash` = '" . $kernel->vars['activate_hash'] . "' AND `activate_user_id` = " . $kernel->vars['user_id'] ) == 1 )
			{
				$kernel->db->update( "users", array( "user_active" => "Y" ), "WHERE `user_id` = " . $kernel->vars['user_id'] );
				
				$kernel->page->message_report( $kernel->ld['phrase_user_activate_successful'], M_NOTICE );
			}
			else
			{
				$kernel->page->message_report( $kernel->ld['phrase_invalid_activate_details'], M_ERROR );
			}
			
			$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );
		}
		else
		{
			header( "Location: index.php" ); exit;
		}
		
		break;
	}
	
	#############################################################################
	
	default :
	{
		//Logged in, goto the user panel.
		if( !empty( $kernel->session->vars['session_hash'] ) AND USER_LOGGED_IN == true )
		{
			header( "Location: panel.php" ); exit;
		}
		else
		{
			if( $kernel->config['archive_allow_user_login'] !== "true" OR $kernel->config['archive_allow_user_registration'] !== "true" )
			{
				$kernel->page->message_report( $kernel->ld['phrase_disabled_register'], M_ERROR );
			}
			else
			{
				switch( $kernel->vars['action'] )
				{
					#############################################################################
					
					case "create" :
					{
						$kernel->clean_array( "_REQUEST", array( "user_verify_key" => V_STR, "user_verify_hash" => V_STR, "user_name" => V_STR, "user_password" => V_STR, "user_password_confirm" => V_STR, "user_email" => V_STR ) );
						
						// Check image security hash
						$kernel->page->verify_security_image_details( null );
						
						//invalid email address
						if( !preg_match( "/^[\w-]+(\.[\w-]+)*@([0-9a-z][0-9a-z-]*[0-9a-z]\.)+([a-z]{2,4})$/i", strtolower( $kernel->vars['user_email'] ) ) )
						{
							$kernel->page->message_report( $kernel->ld['phrase_register_invalid_email'], M_ERROR );
						}
						
						//no username specified
						elseif( empty( $kernel->vars['user_name'] ) )
						{
							$kernel->page->message_report( $kernel->ld['phrase_register_no_user'], M_ERROR );
						}
						
						//username already used
						elseif( $kernel->db->numrows( "SELECT `user_name` FROM " . TABLE_PREFIX . "users WHERE `user_name` = '" . $kernel->vars['user_name'] . "'" ) == 1 )
						{
							$kernel->page->message_report( $kernel->ld['phrase_register_user_taken'], M_ERROR );
						}
						
						//email already used
						elseif( $kernel->db->numrows( "SELECT `user_email` FROM " . TABLE_PREFIX . "users WHERE `user_email` = '" . $kernel->vars['user_email'] . "'" ) == 1 )
						{
							$kernel->page->message_report( $kernel->ld['phrase_register_email_taken'], M_ERROR );
						}
						
						//no, or short password
						elseif( empty( $kernel->vars['user_password'] ) OR strlen( $kernel->vars['user_password'] ) < 4 )
						{
							$kernel->page->message_report( $kernel->ld['phrase_register_invalid_password'], M_ERROR );
						}
						
						//password mismatch
						elseif( $kernel->vars['user_password'] !== $kernel->vars['user_password_confirm'] )
						{
							$kernel->page->message_report( $kernel->ld['phrase_register_bad_password'], M_ERROR );
						}
						else
						{
							$kernel->db->query( "DELETE FROM `" . TABLE_PREFIX . "users_verify` WHERE `verify_ip_address` = '" . IP_ADDRESS . "' AND `verify_hash` = '" . $kernel->vars['user_verify_hash'] . "'" );
							
							$kernel->vars['user_name'] = $kernel->format_input( $kernel->vars['user_name'], T_STRIP );
							$kernel->vars['user_email'] = $kernel->format_input( $kernel->vars['user_email'], T_STRIP );
							
							$userdata = array(
								"user_name"					=> $kernel->vars['user_name'],
								"user_email"				=> $kernel->vars['user_email'],
								"user_password"				=> md5( $kernel->vars['user_password'] ),
								"user_group_id"				=> 2,
								"user_active"				=> "Y",
								"user_timestamp"			=> UNIX_TIME
							);
							
							if( $kernel->config['EMAIL_USER_ACTIVATION'] == 1 )
							{
								$userdata['user_active'] = "N";
								
								$kernel->db->insert( "users", $userdata );
								
								$user = $kernel->db->row( "SELECT `user_id` FROM `" . TABLE_PREFIX . "users` WHERE `user_name` = '" . $kernel->vars['user_name'] . "'" );
								
								$new_activate_hash = md5( uniqid( rand() ) );
								
								$emaildata = array(
									"activate_hash"			=> $new_activate_hash,
									"activate_user_id"		=> $user['user_id'],
									"activate_time"			=> UNIX_TIME
								);
								
								$kernel->db->insert( "users_activate", $emaildata );
								
								$activation = $kernel->db->row( "SELECT `activate_id`, `activate_hash` FROM `" . TABLE_PREFIX . "users_activate` WHERE `activate_user_id` = '" . $user['user_id'] . "'" );
								
								$emaildata = array(
									"user_name"				=> $kernel->vars['user_name'],
									"user_email"			=> $kernel->vars['user_email'],
									"email_activation_link"	=> $kernel->config['system_root_url_path'] . "/user.php?node=activate&amp;user_id=" . $user['user_id'] . "&amp;activate_id=" . $activation['activate_id'] . "&amp;activate_hash=" . $new_activate_hash . "",
									"archive_title"			=> $kernel->config['archive_name']
								);
								
								$kernel->archive->construct_send_email( "user_register_activation", $kernel->vars['user_email'], $emaildata );
							}
							else
							{
								$kernel->db->insert( "users", $userdata );
							}
							
							if( $kernel->config['EMAIL_REG_NOTICE'] == 1 )
							{
								$useremaildata = array(
									"user_name"				=> $kernel->vars['user_name'],
									"user_email"			=> $kernel->vars['user_email'],
									"archive_title"			=> $kernel->config['archive_name']
								);
							
								$kernel->archive->construct_send_email( "user_register_notification", $kernel->config['mail_inbound'], $useremaildata );
							}
							
							$kernel->archive->update_database_counter( "users" );
							
							header( "Location: user.php?action=message" ); exit;
						}
						
						break;
					}
					
					#############################################################################
					# Add Success
				  
					case "message" :
					{
						if( $kernel->config['EMAIL_USER_ACTIVATION'] == 1 )
						{
							$kernel->page->message_report( $kernel->ld['phrase_submitted_user_activation'], M_NOTICE );
						}
						else
						{
							$kernel->page->message_report( $kernel->ld['phrase_submitted_user'], M_NOTICE );
						}
								
						break;
					}
					
					#############################################################################
					# Reg form
					
					default :
					{
						$kernel->tp->call( "user_add" );
						
						$kernel->session->construct_session_security_form();
						
						break;
					}
				}
			}
		}
		
		$kernel->page->construct_output( R_HEADER, R_FOOTER, false, R_NAVIGATION );
		
		break;
	}
}

?>