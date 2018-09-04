=== Paid Memberships Pro - Extra Expiration Warning Emails Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, members, expiration, email, member communication
Requires at least: 4
Tested up to: 4.9.8
Stable tag: .4

Send more than one customized "membership expiration warning" email to users with PMPro.

== Description ==
By default, Paid Memberships Pro will send members an email notice 7 days prior to their expiration date. Use this add on to send notifications at additional intervals. It also allows you to set a custom email message for each additional interval (i.e. send a custom message at the 30 day and 60 days from expiration point).

== Installation ==

1. Upload the `pmpro-extra-expiration-warning-emails` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the `Plugins` menu in WordPress.
1. Update the $emails array to change when the additional emails are sent and to define a custom template file, if desired.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the issues section of GitHub and we'll fix it as soon as we can. Thanks for helping. https://github.com/strangerstudios/pmpro-extra-expiration-warning-emails/issues 

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==
= .4 =
* BUG FIX: Would sometimes allow the run date/time to be incorrectly formatted.
* BUG FIX: Would sometimes save the wrong date in user meta, leading to extra emails being sent out.
* BUG FIX/ENHANCEMENT: If running a current version of PMPro, will use the pmpro_cleanup_memberships_users_table() function before finding expiring emails to avoid certain issues caused by errors in the memberships_users table.

= .3.7 =
* BUG: Tweaked SQL query to fix some cases where warnings weren't being emailed out.

= .3.6 =
* BUG: Fixed bug where members were receiving warnings multiple days in a row.
* ENHANCEMENT: Using 'pmproeewe_test=1' and 'pmproeewe_test_date=YYYY-MM-DD' (for '$today' variable) in URL (as GET request) to test data retrieval and _not_ send warning messages (but log them to error_log() if WP_DEBUG is defined and true)

= .3.5 =
* BUG: Another fix to the query to find expiring and exlcude users who already received an email recently.

= .3.4 =
* BUG: Didn't correctly exclude all previously notified users

= .3.3 =
* BUG: The SQL query to find expiring members was including some users who had already received an email.
* BUG: Corrected the pmproeewe_add_admin_as_bcc filter to _bcc vs _cc.

= .3.2 =
* BUG: Fixed bug where $expiring_soon array was empty.

= .3.1 =
* BUG: Fixed bug where deleted or expired users were being processed and possibly sent expiration warnings.

= .3 =
* BUG: Fixed bug where users were getting 2 expiration emails.
* ENHANCEMENT: Added pmproeewe_email_frequency_and_templates template to adjust the array of email frequencies and templates via filters instead of editing the plugin.

= .2 =
* ENHANCEMENT: Allow different templates for emails at different days

= .1 =
* Initial commit.

== Upgrade Notice ==
= .3 =
IMPORTANT NOTE: You or a developer may have edited this plugin to set specific timing for the extra expiration warning emails. If so, do not upgrade unless you are okay with the default 30, 60, 90 periods or have backed up your settings so you can implement them again in the new version of this addon.
