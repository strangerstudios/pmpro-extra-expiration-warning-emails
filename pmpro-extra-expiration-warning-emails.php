<?php
/*
Plugin Name: PMPro Extra Expiration Warning Emails
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-extra-expiration-warning-emails/
Description: Send out more than one "membership expiration warning" email to users with PMPro.
Version: .1
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
		Here is where you set how many emails you want to send and how early.
	*/
	$emails = array(30,60,90);		//<--- !!! UPDATE THIS LINE TO CHANGE WHEN EMAILS GO OUT !!! -->
	sort($emails, SORT_NUMERIC);
	
	//array to store ids of folks we sent emails to so we don't email them twice
	$sent_emails = array();
	
	foreach($emails as $days)
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
				$pmproemail->sendMembershipExpiringEmail($euser);
				
				printf(__("Membership expiring email sent to %s. ", "pmpro"), $euser->user_email);
				
				$sent_emails[] = $e->user_id;
			}
				
			//update user meta so we don't email them again
			update_user_meta($e->user_id, "pmpro_expiration_notice_" . $days, $today);
		}
	}
}