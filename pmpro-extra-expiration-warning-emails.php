<?php
/*
Plugin Name: Paid Memberships Pro - Extra Expiration Warning Emails Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-extra-expiration-warning-emails/
Description: Send out more than one "membership expiration warning" email to users with PMPro.
Version: .2.1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

//first, disable the default email
add_filter("pmpro_send_expiration_warning_email", "__return_false");

//now add our new function to run on crons
add_action("pmpro_cron_expiration_warnings", "pmproeewe_extra_emails", 30);

/*
	New expiration email function.
	Set the $emails array to include the days you want to send warning emails.
	e.g. array(30,60,90) sends emails 30, 60, and 90 days before expiration.
*/
function pmproeewe_extra_emails()
{
	global $wpdb;
	
	//make sure we only run once a day
	$today = date("Y-m-d 00:00:00");
	
	/*
		Here is where you set how many emails you want to send, how early, and which template files to e-mail.
		If you set the template file to an empty string '' then it will send the default PMPro expiring e-mail.
		Place your email templates in a subfolder of your active theme. Create a paid-memberships-pro folder in your theme folder,
		and then create an email folder within that. Your template files should have a suffix of .html, but you don't put it below. So if you
		create a file in there called myexpirationemail.html, then you'd just put 'myexpirationemail' in the array below.
		(PMPro will fill in the .html for you.)
	*/
	$emails = apply_filters( 'pmproeewe_email_frequency_and_templates', array(
					30	=> 'mem_expiring_30days',
					60	=> 'mem_expiring_60days',
					90	=>	'mem_expiring_90days'
				)
	);		//<--- !!! UPDATE THIS ARRAY TO CHANGE WHEN EMAILS GO OUT AND THEIR TEMPLATE FILES !!! -->
	ksort($emails, SORT_NUMERIC);
	
	// add admin as Cc recipient?
	$include_admin = apply_filters( 'pmproeewe_bcc_admin_user', false);
	
	if ($include_admin) 
	{
		add_filter('pmpro_email_headers', 'pmproeewe_add_admin_as_cc');
	}
	
	//array to store ids of folks we sent emails to so we don't email them twice
	$sent_emails = array();
	
	foreach(array_keys($emails) as $days)
	{	
		//look for memberships that are going to expire within one week (but we haven't emailed them within a week)
		$sqlQuery = "SELECT mu.user_id, mu.membership_id, mu.startdate, mu.enddate FROM $wpdb->pmpro_memberships_users mu LEFT JOIN $wpdb->usermeta um ON um.user_id = mu.user_id AND um.meta_key = 'pmpro_expiration_notice_" . $days . "' WHERE mu.status = 'active' AND mu.enddate IS NOT NULL AND mu.enddate <> '' AND mu.enddate <> '0000-00-00 00:00:00' AND DATE_SUB(mu.enddate, INTERVAL " . $days . " Day) <= '" . $today . "' AND (um.meta_value IS NULL OR DATE_ADD(um.meta_value, INTERVAL " . $days . " Day) <= '" . $today . "') ORDER BY mu.enddate";

		$expiring_soon = $wpdb->get_results($sqlQuery);
				
		foreach($expiring_soon as $e)
		{							
			if(!in_array($e->user_id, $sent_emails))
			{
				//send an email
				$pmproemail = new PMProEmail();
				$euser = get_userdata($e->user_id);		

				if($euser) {
					$euser->membership_level = pmpro_getMembershipLevelForUser($euser->ID);
						
					$pmproemail->email = $euser->user_email;
					$pmproemail->subject = sprintf(__("Your membership at %s will end soon", "pmpro"), get_option("blogname"));
					if(strlen($emails[$days])>0) {
						$pmproemail->template = $emails[$days];
					} else {
						$pmproemail->template = "membership_expiring";
					}
					$pmproemail->data = array("subject" => $pmproemail->subject, "name" => $euser->display_name, "user_login" => $euser->user_login, "sitename" => get_option("blogname"), "membership_id" => $euser->membership_level->id, "membership_level_name" => $euser->membership_level->name, "siteemail" => pmpro_getOption("from_email"), "login_link" => wp_login_url(), "enddate" => date(get_option('date_format'), $euser->membership_level->enddate), "display_name" => $euser->display_name, "user_email" => $euser->user_email);			
			
					$pmproemail->sendEmail();
				
					printf(__("Membership expiring email sent to %s. ", "pmpro"), $euser->user_email);
				
					$sent_emails[] = $e->user_id;
				}
			}
				
			//update user meta so we don't email them again
			update_user_meta($e->user_id, "pmpro_expiration_notice_" . $days, $today);
		}
	}
	
	// remove the filter for admin
	if ($include_admin) 
	{
		remove_filter('pmpro_email_headers', 'pmproeewe_add_admin_as_bcc');
	}

}

/*
Filter to add admin as Bcc for messages from this add-on
*/
function pmproeewe_add_admin_as_bcc( $headers ) {
	
	$a_email = get_option('admin_email');
	$admin = get_user_by('email', $a_email);
	$headers[] = "Bcc: {$admin->first_name} {$user->last_name} <{$admin->user_email}>";
	
	return $headers;
}

/*
Function to add links to the plugin row meta
*/
function pmproeewe_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-extra-expiration-warning-emails.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plus-add-ons/extra-expiration-warning-emails-add-on/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmproeewe_plugin_row_meta', 10, 2);
