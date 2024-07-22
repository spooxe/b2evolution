<?php
/**
 * Upgrade - This is a LINEAR controller
 *
 * This file is part of b2evolution - {@link http://b2evolution.net/}
 * See also {@link https://github.com/b2evolution/b2evolution}.
 *
 * @license GNU GPL v2 - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @copyright (c)2009-2016 by Francois Planque - {@link http://fplanque.com/}
 * Parts of this file are copyright (c)2009 by The Evo Factory - {@link http://www.evofactory.com/}.
 *
 * Released under GNU GPL License - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @package maintenance
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @vars string paths
 */
global $basepath, $upgrade_path, $install_path;

// Check minimum permission:
check_user_perm( 'admin', 'normal', true );
check_user_perm( 'maintenance', 'upgrade', true );

// Used in the upgrade process
$script_start_time = $servertimenow;

$tab = param( 'tab', 'string', '', true );

// Set options path:
$AdminUI->set_path( 'options', 'misc', 'upgrade'.$tab );

// Get action parameter from request:
param_action();

switch( $action )
{
	case 'delete':
		// Delete already downloaded ZIP file or folder:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'upgrade_delete' );

		$file = param( 'file', 'string' );

		if( empty( $file ) )
		{
			debug_die( 'You don\'t select a file/folder for deleting!' );
		}

		// Decide file of folder depending on extension:
		$is_dir = ! preg_match( '#\.zip$#i', $file );

		$file_path = $upgrade_path.$file;

		if( ! file_exists( $file_path ) )
		{	// Display error when a requested file/folder doesn't exist:
			// NOTE: Do NOT translate these messages because it must not occurs normally!
			$Messages->add( sprintf( $is_dir
				? 'The directory %s does not exist.'
				: 'The file %s does not exist.', '<code>'.$file.'</code>' ), 'error' );
			break;
		}

		// Check real type of the requested file or folder before deleting:
		$is_dir = is_dir( $file_path );

		if( $is_dir )
		{	// Delete a folder:
			$del_result = rmdir_r( $file_path );
		}
		else
		{	// Delete a file:
			$del_result = @unlink( $file_path );
		}

		if( $del_result )
		{	// Successful deleting:
			$Messages->add( sprintf( $is_dir
				? TB_('The directory &laquo;%s&raquo; has been deleted.')
				: TB_('The file &laquo;%s&raquo; has been deleted.'), $file ), 'success' );
		}
		else
		{	// Failed deleting:
			$Messages->add( sprintf( $is_dir
				? TB_('Cannot delete directory %s. Please check the permissions or delete it manually.')
				: TB_('File %s could not be deleted.'), '<code>'.$file.'</code>' ), 'error' );
		}

		// Redirect back to don't try delete the same file/folder twice:
		header_redirect( $admin_url.'?ctrl=upgrade' );
		// Exit here.
		break;

	case 'download':
	case 'force_download':
		// Check for STEP 2: DOWNLOAD.
		if( ! $auto_upgrade_from_any_url )
		{	// Don't allow upgrade from any URL:
			global $global_Cache;
			// Extract array of info about available update:
			$updates = $global_Cache->getx( 'updates' );
			if( empty( $updates[0]['url'] ) )
			{	// No new available version on the upgrade server:
				$Messages->add( /*Don't translate because it must not happens on normal back-office UI*/'Auto upgrade is not allowed from URL!'
						// Display additional messages from upgrade server:
						.( empty( $updates[0]['name'] ) ? '' : '<br>'.$updates[0]['name'] )
						.( empty( $updates[0]['description'] ) ? '' : '<br>'.$updates[0]['description'] ),
					'error' );
				header_redirect( $admin_url.'?ctrl=upgrade' );
				// EXIT here!
			}
		}
		break;
}

// Display message if the upgrade config file doesn't exist
check_upgrade_config( true );

$AdminUI->breadcrumbpath_init( false );  // fp> I'm playing with the idea of keeping the current blog in the path here...
$AdminUI->breadcrumbpath_add( TB_('System'), $admin_url.'?ctrl=system' );
$AdminUI->breadcrumbpath_add( TB_('Maintenance'), $admin_url.'?ctrl=tools' );
if( $tab == 'git' )
{
	if( ! $auto_upgrade_from_any_url )
	{	// Deny upgrade from Git because upgrade from any URL is denied by config:
		debug_die( 'Auto upgrade is not allowed from URL!' );
	}

	$AdminUI->breadcrumbpath_add( TB_('Upgrade from Git'), $admin_url.'?ctrl=upgrade&amp;tab='.$tab );

	// Set an url for manual page:
	$AdminUI->set_page_manual_link( 'upgrade-from-git' );
}
else
{
	$AdminUI->breadcrumbpath_add( TB_('Auto Upgrade'), $admin_url.'?ctrl=upgrade' );

	// Set an url for manual page:
	$AdminUI->set_page_manual_link( 'auto-upgrade' );
}


// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top();

$AdminUI->disp_payload_begin();

echo '<h2 class="red">'.TB_('WARNING: USE WITH CAUTION!').'</h2>';

evo_flush();

/**
 * Do Action Display Payload:
 */
switch( $action )
{
	case 'start':
	default:
		// STEP 1: Check for updates.

		autoupgrade_display_steps( 1, $tab );

		if( $tab == '' )
		{
			$block_item_Widget = new Widget( 'block_item' );
			$block_item_Widget->title = TB_('Updates from b2evolution.net').get_manual_link( 'auto-upgrade' );
			$block_item_Widget->disp_template_replaced( 'block_start' );

			// Note: hopefully, the update will have been downloaded in the shutdown function of a previous page (including the login screen)
			// However if we have outdated info, we will load updates here.
			load_funcs( 'dashboard/model/_dashboard.funcs.php' );
			// Let's clear any remaining messages that should already have been displayed before...
			$Messages->clear();
			b2evonet_get_updates( true );

			// Display info & error messages
			$Messages->display();

			/**
			 * @var AbstractSettings
			 */
			global $global_Cache;

			// Display the current version info for now. We may remove this in the future.
			$version_status_msg = $global_Cache->getx( 'version_status_msg' );
			if( !empty($version_status_msg) )
			{	// We have managed to get updates (right now or in the past):
				echo '<p>'.$version_status_msg.'</p>';
				$extra_msg = $global_Cache->getx( 'extra_msg' );
				if( !empty($extra_msg) )
				{
					echo '<p>'.$extra_msg.'</p>';
				}
			}

			$block_item_Widget->disp_template_replaced( 'block_end' );
			unset( $block_item_Widget );

			// Extract array of info about available update:
			$updates = $global_Cache->getx( 'updates' );

			$action = 'start';
			$AdminUI->disp_view( 'maintenance/views/_upgrade.form.php' );
		}
		elseif( $tab == 'git' )
		{
			$action = 'start';
			$AdminUI->disp_view( 'maintenance/views/_upgrade_git.form.php' );
		}
		break;

	case 'export_git':
		// GIT STEP 2: DOWNLOAD.

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'upgrade_started' );

		if( $demo_mode )
		{
			$Messages->clear();
			$Messages->add( TB_('This feature is disabled on the demo server.'), 'error' );
			$Messages->display();
			break;
		}

		$git_url = param( 'git_url', 'string', '', true );
		$git_branch = param( 'git_branch', 'string', '', true );

		$UserSettings->set( 'git_upgrade_url', $git_url );
		$UserSettings->set( 'git_upgrade_branch', $git_branch );
		$UserSettings->dbupdate();

		$Messages->clear(); // Clear the messages to avoid a double displaying here

		if( param_check_not_empty( 'git_url', TB_('Please enter the URL of repository') ) &&
		    param_check_url( 'git_url', 'download_src' ) &&
		    preg_match( '#([^/]+)/([^/]+)/?$#', $git_url, $git_data ) )
		{	// Generate an URL to download GIT branch as ZIP archive:
			$git_owner = $git_data[1];
			$git_repo = preg_replace( '#\.git$#', '', $git_data[2] );
			set_param( 'upd_url', 'https://github.com/'.$git_owner.'/'.$git_repo.'/archive/'.$git_branch.'.zip' );
			memorize_param( 'upd_url', 'url', NULL, get_param( 'upd_url' ) );
			// No break here in order to go to the "download" form below
		}
		else
		{	// Display the errors and the config form again to fix data:
			$Messages->display();
			autoupgrade_display_steps( 2, $tab );
			$action = 'start';
			$AdminUI->disp_view( 'maintenance/views/_upgrade_git.form.php' );
			break;
		}

	case 'download':
	case 'force_download':
		// STEP 2: DOWNLOAD.

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'upgrade_started' );

		if( $demo_mode )
		{
			$Messages->clear();
			$Messages->add( TB_('This feature is disabled on the demo server.'), 'error' );
			$Messages->display();
			break;
		}

		$action_success = true;
		$download_success = true;

		autoupgrade_display_steps( 2, $tab );

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = TB_('Downloading package...');
		$block_item_Widget->disp_template_replaced( 'block_start' );

		$Messages->clear(); // Clear the messages to avoid a double displaying here

		if( $auto_upgrade_from_any_url )
		{	// Allow to upgrade from any URL:
			$download_url = param( 'upd_url', 'string', '', true );
			param_check_not_empty( 'upd_url', TB_('Please enter the URL to download ZIP archive') );
		}
		elseif( ! empty( $updates[0]['url'] ) )
		{	// New available version on the upgrade server:
			$download_url = $updates[0]['url'];
			// Set global param for additional check server URL by same as for any not trust URL:
			set_param( 'upd_url', $download_url );
		}
		else
		{	// This must not happens because of redirect on checking above:
			debug_die( 'Auto upgrade is not allowed from URL!' );
		}

		// Check the download url for correct http, https, ftp URI
		$success = param_check_url( 'upd_url', 'download_src', NULL );
		if( $success && ! preg_match( '#\.zip$#i', $download_url ) )
		{ // Check the download url is a .zip or .ZIP
			param_error( 'upd_url', sprintf( TB_('The URL "%s" must point to a ZIP archive.'), $download_url ) );
		}

		if( $Messages->count() )
		{ // Display the errors and the download form again to fix url
			$Messages->display();

			// Extract array of info about available update:
			$updates = $global_Cache->getx( 'updates' );

			$action = 'start';
			$AdminUI->disp_view( 'maintenance/views/_upgrade.form.php' );
			break;
		}

		$upgrade_name = pathinfo( $download_url );
		$upgrade_name = $upgrade_name['filename'].'.zip';
		$upgrade_file = $upgrade_path.$upgrade_name;

		// Memorize ZIP file name for next step submitting:
		memorize_param( 'upd_file', 'string', NULL, $upgrade_name );

		if( file_exists( $upgrade_file ) )
		{ // The downloading file already exists
			if( $action == 'force_download' )
			{ // Try to delete previous package if the downloading is forced
				if( ! @unlink( $upgrade_file ) )
				{
					echo '<p class="red">'.sprintf( TB_('Unable to delete previously downloaded package %s before forcing the download.'), '<b>'.$upgrade_file.'</b>' ).'</p>';
					$action_success = false;
				}
			}
			else
			{
				echo '<div class="action_messages"><div class="log_error" style="text-align:center;font-weight:bold">'
					.sprintf( TB_('The package %s is already downloaded.'), $upgrade_name ).'</div></div>';
				$action_success = false;
			}
			evo_flush();
		}

		if( $action_success && ( $download_success = prepare_maintenance_dir( $upgrade_path, true ) ) )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			echo '<p>'.sprintf( TB_('Downloading package to &laquo;<strong>%s</strong>&raquo;...'), $upgrade_file );
			evo_flush();

			// Downloading
			$file_contents = fetch_remote_page( $download_url, $info, 1800 );

			if( $info['status'] != 200 || empty( $file_contents ) )
			{ // Impossible to download
				$download_success = false;
				echo '</p><p style="color:red">'.sprintf( TB_('Unable to download package from &laquo;%s&raquo;'), $download_url ).'</p>';
			}
			elseif( ! save_to_file( $file_contents, $upgrade_file, 'w' ) )
			{ // Impossible to save file...
				$download_success = false;
				echo '</p><p style="color:red">'.sprintf( TB_('Unable to create file: &laquo;%s&raquo;'), $upgrade_file ).'</p>';

				if( file_exists( $upgrade_file ) )
				{ // Remove file from disk
					if( ! @unlink( $upgrade_file ) )
					{
						echo '<p style="color:red">'.sprintf( TB_('Unable to remove file: &laquo;%s&raquo;'), $upgrade_file ).'</p>';
					}
				}
			}
			else
			{ // The package is downloaded successfully
				echo ' OK '.bytesreadable( filesize( $upgrade_file ), false, false ).'.</p>';
			}
			evo_flush();
		}

		// Pause the process before next step
		$AdminUI->disp_view( 'maintenance/views/_upgrade_downloaded.form.php' );
		unset( $block_item_Widget );
		break;

	case 'unzip':
	case 'force_unzip':
		// STEP 3: UNZIP.

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'upgrade_downloaded' );

		if( $demo_mode )
		{
			$Messages->clear();
			$Messages->add( TB_('This feature is disabled on the demo server.'), 'error' );
			$Messages->display();
			break;
		}

		$action_success = true;
		$unzip_success = true;

		autoupgrade_display_steps( 3, $tab );

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = TB_('Unzipping package...');
		$block_item_Widget->disp_template_replaced( 'block_start' );
		evo_flush();

		$upd_file = param( 'upd_file', 'string', '', true );

		if( ! preg_match( '#\.zip$#i', $upd_file ) )
		{	// Check the provided file is a .zip or .ZIP:
			debug_die( 'The file "'.$upd_file.'" must be a ZIP archive!' );
		}

		$upgrade_dir_name = substr( $upd_file, 0, -4 );

		$upgrade_dir = $upgrade_path.$upgrade_dir_name;
		$upgrade_file = $upgrade_path.$upd_file;

		// Memorize folder name for next step submitting:
		memorize_param( 'upd_dir', 'string', NULL, $upgrade_dir_name );

		if( file_exists( $upgrade_dir ) )
		{ // The downloading file already exists
			if( $action == 'force_unzip' )
			{ // Try to delete previous package if the downloading is forced
				if( ! rmdir_r( $upgrade_dir ) )
				{
					echo '<p class="red">'.sprintf( TB_('Unable to delete previous unzipped package %s before forcing the unzip.'), '<b>'.$upgrade_dir.'</b>' ).'</p>';
					$action_success = false;
				}
			}
			else
			{
				echo '<div class="action_messages"><div class="log_error" style="text-align:center;font-weight:bold">'
					.sprintf( TB_('The package %s is already unzipped.'), $upd_file ).'</div></div>';
				$action_success = false;
			}
			evo_flush();
		}

		if( $action_success )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			echo '<p>'.sprintf( TB_('Unpacking package to &laquo;<strong>%s</strong>&raquo;...'), $upgrade_dir );
			evo_flush();

			// Unpack package
			if( $unzip_success = unpack_archive( $upgrade_file, $upgrade_dir, true ) )
			{
				echo ' OK.</p>';
				evo_flush();
			}
			else
			{
				echo '</p>';
				// Additional check
				rmdir_r( $upgrade_dir );
			}
		}

		// Pause the process before next step
		$AdminUI->disp_view( 'maintenance/views/_upgrade_unzip.form.php' );
		unset( $block_item_Widget );
		break;

	case 'ready':
		// STEP 4: READY TO UPGRADE.

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'upgrade_is_ready' );

		autoupgrade_display_steps( 4, $tab );

		$upgrade_name = param( 'upd_dir', 'string', '', true );

		$block_item_Widget = new Widget( 'block_item' );
		$block_item_Widget->title = TB_('Ready to upgrade').'...';
		$block_item_Widget->disp_template_replaced( 'block_start' );
		evo_flush();

		// Pause the process before next step
		$AdminUI->disp_view( 'maintenance/views/_upgrade_ready.form.php' );
		unset( $block_item_Widget );
		break;

	case 'backup_and_overwrite':
		// STEP 5: BACKUP & UPGRADE.

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'upgrade_is_launched' );

		autoupgrade_display_steps( 5, $tab );

		if( $demo_mode )
		{
			$Messages->clear();
			$Messages->add( TB_('This feature is disabled on the demo server.'), 'error' );
			$Messages->display();
			break;
		}

		// Load Backup class (PHP4) and backup all of the folders and files
		load_class( 'maintenance/model/_backup.class.php', 'Backup' );
		$Backup = new Backup();
		// Memorize all form params in order t oresubmit form if some errors exist
		$Backup->load_from_Request( true );

		// File options:
		if( param( 'fm_default_chmod_dir', 'string', NULL ) !== NULL )
		{
			param_check_regexp( 'fm_default_chmod_dir', '~^[0-7]{3}$~', TB_('Invalid CHMOD value. Use 3 digits.') );
			$Settings->set( 'fm_default_chmod_dir', $fm_default_chmod_dir );
		}
		if( param( 'fm_default_chmod_file', 'string', NULL ) !== NULL )
		{
			param_check_regexp( 'fm_default_chmod_file', '~^[0-7]{3}$~', TB_('Invalid CHMOD value. Use 3 digits.') );
			$Settings->set( 'fm_default_chmod_file', $fm_default_chmod_file );
		}

		if( param_errors_detected() )
		{	// Display the previous step form in order to fix the detected errors:
			$upgrade_name = param( 'upd_dir', 'string', '', true );

			$Messages->display();

			$block_item_Widget = new Widget( 'block_item' );
			$block_item_Widget->title = TB_('Ready to upgrade').'...';
			$block_item_Widget->disp_template_replaced( 'block_start' );
			evo_flush();

			$AdminUI->disp_view( 'maintenance/views/_upgrade_ready.form.php' );
			unset( $block_item_Widget );
			break;
		}

		// Update file options:
		$Settings->dbupdate();

		if( !isset( $block_item_Widget ) )
		{
			$block_item_Widget = new Widget( 'block_item' );
			$block_item_Widget->title = TB_('Installing package...');
			$block_item_Widget->disp_template_replaced( 'block_start' );

			$upgrade_name = param( 'upd_name', 'string', NULL, true );
			if( $upgrade_name === NULL )
			{ // Get an upgrade name from url (Used for auto-upgrade, not git)
				forget_param( 'upd_name' );
				$upgrade_name = param( 'upd_dir', 'string', '', true );
			}

			$success = true;
		}

		// Enable maintenance mode:
		$success = ( $success && switch_maintenance_mode( true, 'upgrade', TB_('System upgrade is in progress. Please reload this page in a few minutes.') ) );

		if( $success )
		{
			// Set maximum execution time
			set_max_execution_time( 1800 ); // 30 minutes

			// Verify that all destination files can be overwritten
			echo '<h4>'.TB_('Verifying that all destination files can be overwritten...').'</h4>';
			evo_flush();

			$read_only_list = array();

			// Get a folder path where we should get the files
			$upgrade_folder_path = get_upgrade_folder_path( $upgrade_name );

			$success = verify_overwrite( $upgrade_folder_path, no_trailing_slash( $basepath ), 'Verifying', false, $read_only_list );

			if( $success && empty( $read_only_list ) )
			{ // We can backup files and database
				if( ! function_exists( 'gzopen' ) )
				{
					$Backup->pack_backup_files = false;
				}

				// Start backup
				if( $success = $Backup->start_backup() )
				{ // We can upgrade files and database

					// Copying new folders and files
					echo '<h4>'.TB_('Copying new folders and files...').'</h4>';
					evo_flush();

					$success = verify_overwrite( $upgrade_folder_path, no_trailing_slash( $basepath ), 'Copying', true, $read_only_list );
					if( ( ! $success ) || ( ! empty( $read_only_list ) ) )
					{ // In case if something was changed before the previous verify_overwrite check
						echo '<p style="color:red"><strong>'.TB_('The files and database backup was created successfully but all folders and files could not be overwritten');
						if( empty( $read_only_list ) )
						{ // There was some error in the verify_overwrite() function, but the corresponding error message was already displayed.
							echo '.</strong></p>';
						}
						else
						{ // Some file/folder could not be overwritten, display it
							echo ':</strong></p>';
							foreach( $read_only_list as $read_only_file )
							{
								echo $read_only_file.'<br/>';
							}
						}
						echo '<p style="color:red"><strong>'.sprintf( TB_('Please restore the backup files from the &laquo;%s&raquo; package. The database was not changed.'), $backup_path ).'</strong></p>';
						evo_flush();
					}

					// Flag to know files were upgraded, in order to don't include new files below,
					// because they may contain new functions which are not loaded yet when this script was started:
					$files_upgraded = true;
				}
			}
			else
			{
				echo '<p style="color:red">'.TB_('<strong>The following folders and files can\'t be overwritten:</strong>').'</p>';
				evo_flush();
				foreach( $read_only_list as $read_only_file )
				{
					echo $read_only_file.'<br/>';
				}
				$success = false;
			}
		}

		if( $success )
		{ // Pause a process before upgrading, and display a link to the normal upgrade action
			$block_item_Widget->disp_template_replaced( 'block_end' );
			$Form = new Form();
			$Form->begin_form( 'fform' );
			$Form->begin_fieldset( TB_('Actions') );
			echo '<p><b>'.TB_('All new b2evolution files are in place. You will now be redirected to the installer to perform a DB upgrade.').'</b> '.TB_('Note: the User Interface will look different.').'</p>';
			$continue_onclick = 'location.href=\''.$baseurl.'install/index.php?action=auto_upgrade&locale='.$current_locale.'\'';
			$Form->end_form( array( array( 'button', 'continue', TB_('Continue to installer'), 'SaveButton', $continue_onclick ) ) );
			unset( $block_item_Widget );
		}
		else
		{ // Disable maintenance mode
			switch_maintenance_mode( false, 'upgrade' );
			echo '<h4 style="color:red">'.TB_('Upgrade failed!').'</h4>';

			// Display a form to resubmit a previous form
			$block_item_Widget->disp_template_replaced( 'block_end' );
			$Form = new Form( NULL, 'upgrade_form', 'post' );
			$Form->add_crumb( 'upgrade_is_launched' ); // In case we want to continue
			$Form->hiddens_by_key( get_memorized( 'action' ) );
			$Form->begin_form( 'fform' );
			$Form->begin_fieldset( TB_('Actions') );
			$Form->end_form( array( array( 'submit', 'actionArray['.$action.']', TB_('Retry'), 'SaveButton' ) ) );
			unset( $block_item_Widget );
		}
		break;
}

if( isset( $block_item_Widget ) )
{
	$block_item_Widget->disp_template_replaced( 'block_end' );
}

if( ! empty( $files_upgraded ) )
{	// We cannot require new footer files because they may contain new functions
	// which are not loaded yet when this script was started:
	echo '<span class="small note">'.TB_('No footer is displayed here because we are in between 2 versions.').'</span>';
}
else                               
{
	$AdminUI->disp_payload_end();

	// Display body bottom, debug info and close </html>:
	$AdminUI->disp_global_footer();
}
?>