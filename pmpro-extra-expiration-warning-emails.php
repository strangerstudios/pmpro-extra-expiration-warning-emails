<?php
/*
Plugin Name: Paid Memberships Pro - Extra Expiration Warning Emails Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/extra-expiration-warning-emails-add-on/
Description: Send out more than one "membership expiration warning" email to users with PMPro.
Version: 1.0.1
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-extra-expiration-warning-emails
Domain Path: /languages
*/

define( 'PMPROEEWE_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Load the languages folder for translations.
 */
function pmproeewe_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-extra-expiration-warning-emails', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'pmproeewe_load_plugin_text_domain' );

/**
 * Trigger a test run of this plugin.
 *
 * @since 1.0
 */
function pmproeewe_test() {
	global $wpdb;
	
	if ( pmproeewe_is_test() ) {
		pmproeewe_log( "TEST: Running expiration functionality" );
		pmproeewe_extra_emails();
		pmproeewe_log( "TEST: Running the expiration functionality again (expecting no records found)" );
		pmproeewe_extra_emails();
		pmproeewe_log( "TEST: Cleaning up after the test" );
		
		// Clean up after the test.
		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'pmproewee_expiration_test_notice_%'" );

		// Output the log.
		pmproeewe_output_log();
	}
}

add_action( 'init', 'pmproeewe_test' );

/*
 * Send the extra expiration warning emails.
 *
 * @since 1.0
 */
function pmproeewe_extra_emails() {
	global $wpdb;

	// New in v3.5: Unhook the PMPro_Scheduled_Actions class expiration reminder function.
	if ( class_exists( 'PMPro_Recurring_Actions' ) ) {
		remove_action( 'pmpro_schedule_daily', array( PMPro_Recurring_Actions::instance(), 'membership_expiration_reminders' ), 99 );
	} else {
		// Fallback for older versions of PMPro.
		remove_action( 'pmpro_cron_expiration_warnings', 'pmpro_cron_expiration_warnings' );
		// Clean up errors in the memberships_users table that could cause problems.
		if( function_exists( 'pmpro_cleanup_memberships_users_table' ) ) {
			pmpro_cleanup_memberships_users_table();
		}
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
	 * @since 1.0
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
	pmproeewe_log( "Template array: " . print_r( $emails, true ) );
	
	/**
	 * Allow the admin to be Bcc'd on all emails sent by this add-on.
	 *
	 * @since 1.0
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
	
	// The previous $days value that we sent emails for.
	$last = 0;
	
	foreach ( $emails as $days => $email_template ) {	
		// If we don't have a template, use the default PMPro one.
		$email_template = empty( $email_template ) ? 'membership_expiring' : $email_template;

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
		// Allow setting a limit on the number of records to process.
		if ( defined( 'PMPRO_CRON_LIMIT' ) ) {
			$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;
		}
		pmproeewe_log( "SQL used: {$sqlQuery}" );
		
		$expiring_soon = $wpdb->get_results( $sqlQuery );
		pmproeewe_log( "Found {$wpdb->num_rows} records to process for expiration warnings that are {$days} days out" );
		
		foreach ( $expiring_soon as $e ) {
			// Make sure that we have a user.
			$euser = get_userdata( $e->user_id );
			if ( ! empty( $euser ) ) {
				$euser->membership_level = pmpro_getSpecificMembershipLevelForUser( $euser->ID, $e->membership_id );
				
				// Only actually send the message if we're not testing.
				if ( apply_filters( 'pmproeewe_send_reminder_to_user', true, $euser ) ) {
					if ( ! pmproeewe_is_test() ) {
						// If we are sending the default 'membership_expiring' template, let core PMPro handle it.
						if ( $email_template === 'membership_expiring' ) {
							$pmproemail = new PMProEmail();
							$pmproemail->sendMembershipExpiringEmail( $euser, $e->membership_id );
						} else {
							// Send the custom template email.
							$pmproemail = new PMProEmail();
							$pmproemail->email   = $euser->user_email;
							$pmproemail->subject = sprintf( __( 'Your membership at %s will end soon', 'pmpro-extra-expiration-warning-emails' ), get_option( 'blogname' ) );
							
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
								"renew_url"             =>  ( ! empty( $euser->membership_level ) && ! empty( $euser->membership_level->id ) ) ? pmpro_url( 'checkout', '?pmpro_level=' . $euser->membership_level->id ) : pmpro_url( 'levels' ),
							);
							$pmproemail->sendEmail();
						}
					} else {
						$test_exp_days = round( ( ( $euser->membership_level->enddate - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ), 0 );
						pmproeewe_log( "Test mode and processing warnings for day {$days} (user's membership expires in {$test_exp_days} days): Faking email using template {$email_template} to user ID {$e->user_id}." );
					}
					pmproeewe_log( sprintf("Membership expiring email sent to user ID %d. ",  $e->user_id ) );
				}

				// Update user meta to track that we sent notice.
				$full_meta = $meta . $e->membership_id;
				if ( false == update_user_meta( $e->user_id, $full_meta, $today ) ) {
					pmproeewe_log( "Error: Unable to update {$full_meta} key for {$e->user_id}!" );
				} else {
					pmproeewe_log( "Saved {$full_meta} = {$today} for {$e->user_id}: enddate = " . date_i18n( 'Y-m-d H:i:s', $euser->membership_level->enddate ) );
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

	// If we're not testing, output the log.
	if ( ! pmproeewe_is_test() ) {
		pmproeewe_output_log();
	}
}

/**
 * Hook the expiration emails to the PMPro scheduled actions.
 *
 * @since 1.0.1
 */
function pmproeewe_schedule_expiration_emails() {
	// New in v3.5: Hook the PMPro_Scheduled_Actions class daily action.
	if ( class_exists( 'PMPro_Recurring_Actions' ) ) {
		add_action( 'pmpro_schedule_daily', 'pmproeewe_extra_emails', 98 );
	} else {
		// Fallback for older versions of PMPro.
		add_action( 'pmpro_cron_expiration_warnings', 'pmproeewe_extra_emails', 5 );
	}
}
add_action( 'init', 'pmproeewe_schedule_expiration_emails' );

/**
 * Helper function to determine whether a test is being run.
 *
 * @since 1.0
 *
 * @return bool true if a test is being run, false otherwise.
 */
function pmproeewe_is_test() {
	return ( isset( $_REQUEST['pmproeewe_test'] ) && intval( $_REQUEST['pmproeewe_test'] ) === 1 && current_user_can( 'manage_options' ) );
}

/**
 * Fix data after updating to new versions.
 *
 * @since 1.0
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
add_action( 'init', 'pmproeewe_check_for_upgrades', 99 );

/*
 * Filter to add admin as Bcc for messages from this add-on.
 *
 * @since 1.0
 */
function pmproeewe_add_admin_as_bcc( $headers ) {
	$a_email   = get_option( 'admin_email' );
	$admin     = get_user_by( 'email', $a_email );
	$headers[] = "Bcc: {$admin->first_name} {$admin->last_name} <{$admin->user_email}>";
	
	return $headers;
}

/**
 * Add a log entry to the PMProEWEE log.
 *
 * @since 1.0
 *
 * @param string $message The message to log.
 */
function pmproeewe_log( $message ) {
	global $pmproewee_logstr;
	$pmproewee_logstr .= "\t" . $message . "\n";
}

/**
 * Output the PMProEWEE log to an email or log file
 * depending on the value of the PMPROEEWE_DEBUG constant.
 *
 * @since 1.0
 */
function pmproeewe_output_log() {
	global $pmproewee_logstr;

	$pmproewee_logstr = "Logged On: " . date_i18n("m/d/Y H:i:s") . "\n" . $pmproewee_logstr . "\n-------------\n";

	//log in file or email?
	if ( defined( 'PMPROEEWE_DEBUG' ) && PMPROEEWE_DEBUG === 'log' ) {
		// Output to log file.
		$logfile = apply_filters( 'pmproeewe_logfile', PMPROEEWE_DIR . '/logs/pmproeewe.txt' );
		$loghandle = fopen( $logfile, "a+" );
		fwrite( $loghandle, $pmproewee_logstr );
		fclose( $loghandle );
	} elseif ( defined( 'PMPROEEWE_DEBUG' ) && false !== PMPROEEWE_DEBUG ) {
		// Send via email.
		$log_email = strpos( PMPROEEWE_DEBUG, '@' ) ? PMPROEEWE_DEBUG : get_option( 'admin_email' );
		wp_mail( $log_email, get_option( 'blogname' ) . ' PMPro EEWE Debug Log', nl2br( esc_html( $pmproewee_logstr ) ) );
	}
}

/*
 * Function to add links to the plugin row meta.
 *
 * @since 1.0
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
