<?php
/**
 * This file implements the post by mail cron job
 *
 * Uses MIME E-mail message parser classes written by Manuel Lemos: {@link http://www.phpclasses.org/browse/package/3169.html}
 *
 * @author Stephan Knauss
 * @author tblue246: Tilman Blumenbach
 * @author sam2kb: Alex
 *
 * TODO:
 * - Try more exotic email clients like mobile phones
 * - TODO Tested and working with thunderbird (text, html, signed), yahoo mail (text, html), outlook webmail, K800i
 * - Allow the user to choose whether to upload attachments to the blog media folder or to his user root.
 * - Create a copy of check_html_sanity function and clean up dangerous HTML code
 * - Add support for shortcodes instead of <tags> similar to:
 *	[title Your post title]
 *	[category x,y,z]
 *	[excerpt]some excerpt[/excerpt]
 *	[tags x,y,z]
 *	[delay +1 hour]
 *	[comments on | off]
 *	[status publish | pending | draft | private]
 *	[slug some-url-name]
 *	[end] � everything after this shortcode is ignored (i.e. signatures)
 *	[more] � more tag
 *	[nextpage] � pagination
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


global $Settings, $DB, $result_message, $default_locale, $current_charset;
global $pbm_item_files, $pbm_messages, $pbm_items, $post_cntr, $del_cntr, $is_cron_mode;

// Are we in cron job mode?
$is_cron_mode = 'yes';

load_funcs( 'cron/model/_post_by_mail.funcs.php');

if( ! $Settings->get( 'eblog_enabled' ) )
{
	pbm_msg( T_('Post by email feature is not enabled.'), true );
	return 2; // error
}

if( ! extension_loaded('imap') )
{
	pbm_msg( T_('The php_imap extension is not available to PHP on this server. Please load it in php.ini or ask your hosting provider to do so.'), true );
	return 2; // error
}

// Make sure current locale is set
locale_overwritefromDB();
locale_activate( $default_locale );

// Set encoding for MySQL connection:
$DB->set_connection_charset( $current_charset );

load_funcs( '_core/_param.funcs.php' );
load_class( 'items/model/_itemlist.class.php', 'ItemList' );
load_class( '_ext/mime_parser/rfc822_addresses.php', 'rfc822_addresses_class' );
load_class( '_ext/mime_parser/mime_parser.php', 'mime_parser_class' );

if( isset($GLOBALS['files_Module']) )
{
	load_funcs( 'files/model/_file.funcs.php');
}

if( $Settings->get('eblog_test_mode') )
{
	pbm_msg( T_('This is just a test run. Nothing will be posted to the database nor will your inbox be altered'), true );
}

if( ! $mbox = pbm_connect() )
{	// We couldn't connect to the mail server
	return 2; // error
}

// Read messages from server
pbm_msg('Reading messages from server');
$imap_obj = imap_check( $mbox );
pbm_msg('Found '.$imap_obj->Nmsgs.' messages');

if( $imap_obj->Nmsgs == 0 )
{
	pbm_msg( T_('There are no messages in the mailbox'), true );
	imap_close( $mbox );
	return 1; // success
}

// Create posts
pbm_process_messages( $mbox, $imap_obj->Nmsgs );

if( ! $Settings->get('eblog_test_mode') && count($del_cntr) > 0 )
{	// We want to delete processed emails from server
	imap_expunge( $mbox );
	pbm_msg( sprintf('Deleted %d processed message(s) from inbox.', $del_cntr) );
}

imap_close( $mbox );

// Send reports
if( $post_cntr > 0 )
{
	pbm_msg( sprintf( T_('New posts created: %d'), $post_cntr ), true );

	$subject = T_('Post by email report');
	foreach( $pbm_items as $User )
	{	// Send report to post author
		$msg = T_('You just created the following posts:')."\n\n";
		$to = $to_name = '';
		foreach( $User as $Item )
		{
			if( $to == '' )
			{	// Get author name and email
				$to = $Item->Author->get('email');
				$to_name = $Item->Author->get_preferred_name();
			}
			$msg .= format_to_output($Item->title)."\n".$Item->get_permanent_url()."\n\n";
		}
		send_mail( $to, $to_name, $subject, $msg );
	}

	// sam2kb> TODO: Send detailed report to blog owner
	// global $pbm_messages;
	// send_mail( $blog_owner_email, $blog_owner_name, T_('Post by email detailed report'), implode("\n",$pbm_messages) );
}

return 1; // success

/*
 * $Log$
 * Revision 1.2  2011/10/18 07:28:12  sam2kb
 * Post by Email fixes
 *
 * Revision 1.1  2011/10/17 20:16:52  sam2kb
 * Post by Email converted into internal scheduled job
 *
 *
 */
?>