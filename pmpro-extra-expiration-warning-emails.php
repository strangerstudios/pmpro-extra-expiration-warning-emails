<?php
/*
Plugin Name: Paid Memberships Pro - Extra Expiration Warning Emails Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-extra-expiration-warning-emails/
Description: Send out more than one "membership expiration warning" email to users with PMPro.
Version: .4
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

//first, disable the default email
add_filter( "pmpro_send_expiration_warning_email", "__return_false" );

//now add our new function to run on crons
add_action( "pmpro_cron_expiration_warnings", "pmproeewe_extra_emails", 30 );

/**
 * Trigger execution of test version for the plugin.
 */
function pmproeewe_test() {
	
	if ( isset( $_REQUEST['pmproeewe_test'] ) && intval( $_REQUEST['pmproeewe_test'] ) === 1 && current_user_can( 'manage_options' ) ) {
		
		// Force the system to _not_ send out emails
		add_filter( 'pmproeewe_send_reminder_to_user', '__return_false', 999 );
		
		if ( WP_DEBUG ) {
			error_log( "PMPROEEWE: Running expiration fuctionality" );
		}
		
		pmproeewe_extra_emails();
		
		if ( WP_DEBUG ) {
			error_log( "PMPROEEWE: Running the expiration functionality again (expecting no records found)" );
		}
		
		pmproeewe_extra_emails();
		
		if ( WP_DEBUG ) {
			error_log( "PMPROEEWE: Cleaning up after the test" );
		}
		
		pmproeewe_cleanup_test();
	}
}

add_action( 'init', 'pmproeewe_test' );

function pmproeewe_cleanup_test() {
	
	global $wpdb;
	
	$emails = apply_filters( 'pmproeewe_email_frequency_and_templates', array(
			30 => 'membership_expiring',
			60 => 'membership_expiring',
			90 => 'membership_expiring',
		)
	);
	
	// Sort the received array in numeric order
	ksort( $emails, SORT_NUMERIC );
	
	foreach ( $emails as $days => $template ) {
		
		$meta = "pmpro_expiration_test_notice_{$days}";
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta ) );
	}
}

/*
	New expiration email function.
	Set the $emails array to include the days you want to send warning emails.
	e.g. array(30,60,90) sends emails 30, 60, and 90 days before expiration.
*/
function pmproeewe_extra_emails() {
	global $wpdb;
	
	$last = null;
	
	//Default: make sure we only run once per day
	$today          = date_i18n( "Y-m-d 00:00:00", current_time( 'timestamp' ) );
	$interval_start = $today;
	
  //clean up errors in the memberships_users table that could cause problems
	if( function_exists( 'pmpro_cleanup_memberships_users_table' ) ) {
		pmpro_cleanup_memberships_users_table();
	}
  
	// Allow test environment to set the value of 'today'.
	if ( isset( $_REQUEST['pmproeewe_test_date'] ) && current_user_can( 'manage_options' ) ) {
		// Test: Set the date based on received value
		$test_date = isset( $_REQUEST['pmproeewe_test_date'] ) ? sanitize_text_field( $_REQUEST['pmproeewe_test_date'] ) : date( 'Y-m-d', current_time( 'timestamp' ) );
		$today     = "{$test_date} 00:00:00";
	}
	
	/**
	 * DO NOT edit this add-on!
	 *
	 * The 'pmproeewe_email_frequency_and_templates' filter is used to configure how many emails
	 * you want to send, how early, and which template files to use for the email messages. The
	 * filter handler should be added in a customization plugin.
	 *
	 * If you set the template file to an empty string '' then it will send the default PMPro expiring e-mail.
	 *
	 * Place your email templates in a subfolder of your active theme. Create a paid-memberships-pro folder
	 * in your theme folder, and then create an email folder within that. Your template files should have
	 * a suffix of .html, but you don't put it below. '
	 *
	 * If you create a file in there called myexpirationemail.html, then you'd just put 'myexpirationemail'
	 * in the array below.
	 *
	 * (PMPro will fill in the .html for you.)
	 */
	$emails = apply_filters( 'pmproeewe_email_frequency_and_templates', array(
			30 => 'membership_expiring',
			60 => 'membership_expiring',
			90 => 'membership_expiring',
		)
	);        //<--- !!! UPDATE THIS ARRAY TO CHANGE WHEN EMAILS GO OUT AND THEIR TEMPLATE FILES !!! -->
	
	ksort( $emails, SORT_NUMERIC );
	
	if ( WP_DEBUG && isset( $_REQUEST['pmproeewe_test'] ) && current_user_can( 'manage_options' ) ) {
		error_log( "PMPROEEWE Template array: " . print_r( $emails, true ) );
	}
	
	// add admin as Cc recipient?
	$include_admin = apply_filters( 'pmproeewe_bcc_admin_user', false );
	
	if ( $include_admin ) {
		add_filter( 'pmpro_email_headers', 'pmproeewe_add_admin_as_bcc' );
	}
	
	//array to store ids of folks we sent emails to so we don't email them twice
	$sent_emails = array();
	
	foreach ( $emails as $days => $email_template ) {
		
		$meta = "pmpro_expiration_notice_{$days}";
		
		// use a dummy meta value for tests
		if ( isset( $_REQUEST['pmproeewe_test'] ) && intval( $_REQUEST['pmproeewe_test'] ) === 1 && current_user_can( 'manage_options' ) ) {
			$meta = "pmpro_expiration_test_notice_{$days}";
		}
		
		$start_ts = strtotime( "{$today} +{$last} days", current_time( 'timestamp' ) );
		
		// Have a valid start timestamp (skip if we're going through the loop for the 1st time)
		if ( ! empty( $start_ts ) && ! is_null( $last ) ) {
			
			$interval_start = date_i18n( 'Y-m-d 00:00:00', $start_ts );
		}
		
		$interval_end_ts = strtotime( "{$today} +{$days} days", current_time( 'timestamp' ) );
		
		// Have an appropriate end timestamp?
		if ( empty( $interval_end_ts ) ) {
			continue;
		}
		
		$interval_end = date_i18n( 'Y-m-d 00:00:00', $interval_end_ts );
		
		// Query returns records that fit between the pmproeewe_email_frequency_and_templates day values
		// and only if they haven't had a warning notice sent already.
		$sqlQuery = $wpdb->prepare(
			"SELECT DISTINCT
  				mu.user_id,
  				mu.membership_id,
  				mu.startdate,
 				mu.enddate,
 				um.meta_value AS notice 			  
 			FROM {$wpdb->pmpro_memberships_users} AS mu
 			  LEFT JOIN {$wpdb->usermeta} AS um ON ( um.user_id = mu.user_id )
            	AND ( um.meta_key = %s )
			WHERE ( um.meta_value IS NULL OR DATE_ADD(um.meta_value, INTERVAL %d DAY) < %s )  
				AND ( mu.status = 'active' )
				AND ( mu.enddate IS NOT NULL )
 			    AND ( mu.enddate <> '0000-00-00 00:00:00' )
 			    AND ( mu.enddate BETWEEN %s AND %s )
 			    AND ( mu.membership_id <> 0 OR mu.membership_id <> NULL )
			ORDER BY mu.enddate",
			$meta,
			$days,
			$today,
			$interval_start,
			$interval_end
		);
		
		if ( WP_DEBUG && isset( $_REQUEST['pmproeewe_test'] ) && current_user_can( 'manage_options' ) ) {
			error_log( "PMPROEEWE SQL used: {$sqlQuery}" );
		}
		
		$expiring_soon = $wpdb->get_results( $sqlQuery );
		
		if ( WP_DEBUG && isset( $_REQUEST['pmproeewe_test'] ) && current_user_can( 'manage_options' ) ) {
			error_log( "PMPROEEWE: Found {$wpdb->num_rows} records to process for expiration warnings that are {$days} days out" );
		}
		
		foreach ( $expiring_soon as $e ) {
			
			//send an email
			$pmproemail = new PMProEmail();
			$euser      = get_userdata( $e->user_id );
			
			if ( !empty( $euser ) ) {
				
				$euser->membership_level = pmpro_getMembershipLevelForUser( $euser->ID );
				
				$pmproemail->email   = $euser->user_email;
				$pmproemail->subject = sprintf( __( "Your membership at %s will end soon", "pmpro" ), get_option( "blogname" ) );
				
				// The user specified a template name to use
				if ( !empty( $email_template ) ) {
					$pmproemail->template = $email_template;
				} else {
					$pmproemail->template = "membership_expiring";
				}
				
				$pmproemail->data = array(
					"subject"               => $pmproemail->subject,
					"name"                  => $euser->display_name,
					"user_login"            => $euser->user_login,
					"sitename"              => get_option( "blogname" ),
					"membership_id"         => $euser->membership_level->id,
					"membership_level_name" => $euser->membership_level->name,
					"siteemail"             => pmpro_getOption( "from_email" ),
					"login_link"            => wp_login_url(),
					"enddate"               => date_i18n( get_option( 'date_format' ), $euser->membership_level->enddate ),
					"display_name"          => $euser->display_name,
					"user_email"            => $euser->user_email,
				);
				
				// Only actually send the message if we're not testing.
				if ( true === apply_filters( 'pmproeewe_send_reminder_to_user', true ) ) {
					$pmproemail->sendEmail();
				} else {
					
					// Running a test exectution
					$test_exp_days = round( ( ( $euser->membership_level->enddate - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ), 0 );
					
					if ( WP_DEBUG && isset( $_REQUEST['pmproeewe_test'] ) && current_user_can( 'manage_options' ) ) {
						error_log( "PMPROEEWE: Test mode and processing warnings for day {$days} (user's membership expires in {$test_exp_days} days): Faking email using template {$pmproemail->template} to {$euser->user_email} with parameters: " . print_r( $pmproemail->data, true ) );
					}
				}
				
				if ( WP_DEBUG ) {
					error_log( sprintf("(Fake) Membership expiring email sent to %s. ",  $euser->user_email ) );
				}
				
				$sent_emails[] = $e->user_id;
				
				//delete any old user meta using this key just in case
				delete_user_meta( $e->user_id, $meta );
				
				//update user meta to track that we sent notice
				if ( false == update_user_meta( $e->user_id, $meta, $today ) ) {
					
					if ( WP_DEBUG ) {
						error_log( "Error: Unable to update {$meta} key for {$e->user_id}!" );
					}
					
				} else {
					if ( WP_DEBUG ) {
						error_log( "Saved {$meta} = {$today} for {$e->user_id}: enddate = " . date_i18n( 'Y-m-d H:i:s', $euser->membership_level->enddate ) );
					}
				}
			}
		}
		
		// To track intervals
		$last = $days;
	}
	
	// remove the filter for admin
	if ( $include_admin ) {
		remove_filter( 'pmpro_email_headers', 'pmproeewe_add_admin_as_bcc' );
	}
	
}

function pmproeewe_cleanup() {
	
	global $wpdb;
	
	$cleanup = get_option( 'pmproeewe_cleanup', false );
	
	if ( ! empty( $cleanup) ) {
		if ( WP_DEBUG ) {
			error_log( "No bad record cleanup needed: {$cleanup}");
		}
		return;
	}
	
	$sql = "SELECT umeta_id
				FROM {$wpdb->usermeta}
				WHERE meta_key LIKE 'pmpro_expiration_notice%%'
				AND meta_value = ' 00:00:00'";
	
	$id_list = $wpdb->get_col( $sql );
	
	if ( empty( $id_list ) ) {
		if ( WP_DEBUG ) {
			error_log( "Nothing to clean up!" );
		}
		update_option( 'pmproeewe_cleanup', '0.7.2', 'no' );
		return;
	}
	
	$in_list = implode( ',', array_map( 'intval', $id_list ) );
	
	if ( WP_DEBUG ) {
		error_log( "Will clean up " . count($id_list ) . " bad PMPro EEWE records" );
	}
	
	$result = $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE umeta_id IN ({$in_list})" );
	
	if ( false !== $result ) {
		update_option( 'pmproeewe_cleanup', '0.7.2', 'no' );
	}
}

add_action( 'init', 'pmproeewe_cleanup', 99 );
/*
Filter to add admin as Bcc for messages from this add-on
*/
function pmproeewe_add_admin_as_bcc( $headers ) {
	
	$a_email   = get_option( 'admin_email' );
	$admin     = get_user_by( 'email', $a_email );
	$headers[] = "Bcc: {$admin->first_name} {$admin->last_name} <{$admin->user_email}>";
	
	return $headers;
}

/*
Function to add links to the plugin row meta
*/
function pmproeewe_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-extra-expiration-warning-emails.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'http://www.paidmembershipspro.com/add-ons/plus-add-ons/extra-expiration-warning-emails-add-on/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url( 'http://paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}
	
	return $links;
}

add_filter( 'plugin_row_meta', 'pmproeewe_plugin_row_meta', 10, 2 );
