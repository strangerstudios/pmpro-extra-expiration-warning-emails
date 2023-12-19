<?php
/*
Plugin Name: Paid Memberships Pro - Extra Expiration Warning Emails Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/extra-expiration-warning-emails-add-on/
Description: Send out more than one "membership expiration warning" email to users with PMPro.
Version: .4
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
*/

/**
 * Trigger a test run of this plugin.
 *
 * @since TBD
 */
function pmproeewe_test() {
	global $wpdb;

	// If PMPROEEWE_DEBUG_LOG is not set yet, set it to false.
	if ( ! defined( 'PMPROEEWE_DEBUG_LOG' ) ) {
		define( 'PMPROEEWE_DEBUG_LOG', false );
	}
	
	if ( pmproeewe_is_test() ) {
		
		if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG ) {
			error_log( "PMPROEEWE: Running expiration functionality" );
		}
		
		pmproeewe_extra_emails();
		
		if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG ) {
			error_log( "PMPROEEWE: Running the expiration functionality again (expecting no records found)" );
		}
		
		pmproeewe_extra_emails();
		
		if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG ) {
			error_log( "PMPROEEWE: Cleaning up after the test" );
		}
		
		// Clean up after the test.
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'pmproewee_expiration_test_notice_%'" );
	}
}

add_action( 'init', 'pmproeewe_test' );

/*
 * Send the extra expiration warning emails.
 *
 * @since TBD
 */
function pmproeewe_extra_emails() {
	global $wpdb;

	// Unhook the core expiration warning email function.
	remove_action( 'pmpro_cron_expiration_warnings', 'pmpro_cron_expiration_warnings' );

	// Clean up errors in the memberships_users table that could cause problems.
	if( function_exists( 'pmpro_cleanup_memberships_users_table' ) ) {
		pmpro_cleanup_memberships_users_table();
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
	 *
	 * @since TBD
	 *
	 * @param array $emails An array of days and template files to use for the emails.
	 */
	$emails = apply_filters( 'pmproeewe_email_frequency_and_templates', array(
			30 => 'membership_expiring',
			60 => 'membership_expiring',
			90 => 'membership_expiring',
		)
	);        //<--- !!! UPDATE THIS ARRAY TO CHANGE WHEN EMAILS GO OUT AND THEIR TEMPLATE FILES !!! -->
	ksort( $emails, SORT_NUMERIC );

	// If PMPROEEWE_DEBUG_LOG is not set yet, set it to false.
	if ( ! defined( 'PMPROEEWE_DEBUG_LOG' ) ) {
		define( 'PMPROEEWE_DEBUG_LOG', false );
	}
	
	if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG && isset( $_REQUEST['pmproeewe_test'] ) && current_user_can( 'manage_options' ) ) {
		error_log( "PMPROEEWE Template array: " . print_r( $emails, true ) );
	}
	
	/**
	 * Allow the admin to be Bcc'd on all emails sent by this add-on.
	 *
	 * @since TBD
	 *
	 * @param bool $bcc_admin true to Bcc the admin, false otherwise.
	 */
	$bcc_admin = apply_filters( 'pmproeewe_bcc_admin_user', false );
	if ( $bcc_admin ) {
		add_filter( 'pmpro_email_headers', 'pmproeewe_add_admin_as_bcc' );
	}

	// Use a dummy meta value for tests.
	$meta = pmproeewe_is_test() ? 'pmproewee_expiration_test_notice_' : 'pmproewee_expiration_notice_';

	// Get the current date/time.
	$today = date_i18n( "Y-m-d H:i:s", current_time( 'timestamp' ) );

	// Allow test environment to set the value of 'today'.
	if ( pmproeewe_is_test() && isset( $_REQUEST['pmproeewe_test_date'] ) ) {
		$today = sanitize_text_field( $_REQUEST['pmproeewe_test_date'] ) . ' 00:00:00';
	}
	
	// Set starting values for loop.
	$last = 0; // The previous $days value that we sent emails for.
	
	foreach ( $emails as $days => $email_template ) {	
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
 			  LEFT JOIN {$wpdb->usermeta} AS um ON ( um.user_id = mu.user_id ) AND ( um.meta_key = CONCAT( %s, mu.membership_id ) )
			WHERE ( um.meta_value IS NULL OR DATE_ADD(um.meta_value, INTERVAL %d DAY) < mu.enddate )  
				AND ( mu.status = 'active' )
				AND ( mu.enddate IS NOT NULL )
 			    AND ( mu.enddate <> '0000-00-00 00:00:00' )
 			    AND ( mu.enddate BETWEEN %s AND %s )
 			    AND ( mu.membership_id <> 0 OR mu.membership_id <> NULL )
			ORDER BY mu.enddate",
			$meta,
			$days,
			date_i18n( 'Y-m-d H:i:s', strtotime( "{$today} +{$last} days", current_time( 'timestamp' ) ) ), // Start date to being looking for expiring memberhsips.
			date_i18n( 'Y-m-d H:i:s', strtotime( "{$today} +{$days} days", current_time( 'timestamp' ) ) ) // End date to stop looking for expiring memberships.
		);
		
		if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG && isset( $_REQUEST['pmproeewe_test'] ) && current_user_can( 'manage_options' ) ) {
			error_log( "PMPROEEWE SQL used: {$sqlQuery}" );
		}
		
		$expiring_soon = $wpdb->get_results( $sqlQuery );
		
		if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG && isset( $_REQUEST['pmproeewe_test'] ) && current_user_can( 'manage_options' ) ) {
			error_log( "PMPROEEWE: Found {$wpdb->num_rows} records to process for expiration warnings that are {$days} days out" );
		}
		
		foreach ( $expiring_soon as $e ) {
			
			//send an email
			$pmproemail = new PMProEmail();
			$euser      = get_userdata( $e->user_id );
			
			if ( ! empty( $euser ) ) {
				
				$euser->membership_level = pmpro_getSpecificMembershipLevelForUser( $euser->ID, $e->membership_id );
				
				$pmproemail->email   = $euser->user_email;
				$pmproemail->subject = sprintf( __( "Your membership at %s will end soon", "pmpro" ), get_option( "blogname" ) );
				
				// The user specified a template name to use
				if ( ! empty( $email_template ) ) {
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
					"siteemail"             => get_option( 'pmpro_from_email' ),
					"login_link"            => wp_login_url(),
					"enddate"               => date_i18n( get_option( 'date_format' ), $euser->membership_level->enddate ),
					"display_name"          => $euser->display_name,
					"user_email"            => $euser->user_email,
				);
				
				// Only actually send the message if we're not testing.
				if ( apply_filters( 'pmproeewe_send_reminder_to_user', true, $euser ) ) {
					if ( ! pmproeewe_is_test() ) {
						$pmproemail->sendEmail();
					} else {
						if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG ) {
							$test_exp_days = round( ( ( $euser->membership_level->enddate - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ), 0 );
							error_log( "PMPROEEWE: Test mode and processing warnings for day {$days} (user's membership expires in {$test_exp_days} days): Faking email using template {$pmproemail->template} to {$euser->user_email} with parameters: " . print_r( $pmproemail->data, true ) );
						}
					}
					if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG ) {
						error_log( sprintf("(Fake) Membership expiring email sent to %s. ",  $euser->user_email ) );
					}
				}

				// Update user meta to track that we sent notice.
				$full_meta = $meta . $e->membership_id;
				if ( false == update_user_meta( $e->user_id, $full_meta, $today ) ) {
					
					if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG ) {
						error_log( "Error: Unable to update {$full_meta} key for {$e->user_id}!" );
					}
					
				} else {
					if ( WP_DEBUG && PMPROEEWE_DEBUG_LOG ) {
						error_log( "Saved {$full_meta} = {$today} for {$e->user_id}: enddate = " . date_i18n( 'Y-m-d H:i:s', $euser->membership_level->enddate ) );
					}
				}
			}
		}
		
		// To track intervals.
		$last = $days;
	}
	
	// remove the filter for admin
	if ( $bcc_admin ) {
		remove_filter( 'pmpro_email_headers', 'pmproeewe_add_admin_as_bcc' );
	}
	
}
add_action( 'pmpro_cron_expiration_warnings', 'pmproeewe_extra_emails', 5 );

/**
 * Helper function to determine whether a test is being run.
 *
 * @since TBD
 *
 * @return bool true if a test is being run, false otherwise.
 */
function pmproeewe_is_test() {
	return ( isset( $_REQUEST['pmproeewe_test'] ) && intval( $_REQUEST['pmproeewe_test'] ) === 1 && current_user_can( 'manage_options' ) );
}

/**
 * Fix data after updating to new versions.
 *
 * @since TBD
 */
function pmproeewe_check_for_upgrades() {
	global $wpdb;

	$pmproeewe_db_version = get_option( 'pmproeewe_db_version', 0 );

	// Check if upgrading to v1.0.
	if ( $pmproeewe_db_version < 1.0 ) {
		// Remove old option to track db version.
		delete_option( 'pmproeewe_cleanup' );

		// Delete all user meta beginning with pmpro_expiration_test_notice_
		// or pmpro_expiration_notice_ to start with a clean slate.
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'pmpro_expiration_test_notice_%' OR meta_key LIKE 'pmpro_expiration_notice_%'" );

		// Update the db version.
		update_option( 'pmproeewe_db_version', 1.0 );
	}
}
add_action( 'init', 'pmproeewe_cleanup', 99 );

/*
 * Filter to add admin as Bcc for messages from this add-on.
 *
 * @since TBD
 */
function pmproeewe_add_admin_as_bcc( $headers ) {
	$a_email   = get_option( 'admin_email' );
	$admin     = get_user_by( 'email', $a_email );
	$headers[] = "Bcc: {$admin->first_name} {$admin->last_name} <{$admin->user_email}>";
	
	return $headers;
}

/*
 * Function to add links to the plugin row meta.
 *
 * @since TBD
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
