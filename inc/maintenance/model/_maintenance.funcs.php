<?php

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * Get the upgrade folder path
 *
 * @param string Name of folder with current downloaded version
 * @return string The upgrade folder path (No slash at the end)
 */
function get_upgrade_folder_path( $version_folder_name )
{
	global $upgrade_path;

	if( empty( $version_folder_name ) || ! file_exists( $upgrade_path.$version_folder_name ) )
	{ // Don't allow an invalid upgrade folder
		debug_die( 'Invalid name of upgrade folder' );
	}

	// Use a root path by default:
	$upgrade_folder_path = $upgrade_path.$version_folder_name;

	if( $dir_handle = @opendir( $upgrade_folder_path ) )
	{
		while( ( $dir_name = readdir( $dir_handle ) ) !== false )
		{
			$dir_path = $upgrade_folder_path.'/'.$dir_name;
			if( is_dir( $dir_path ) && preg_match( '#^b2evolution#i', $dir_name ) )
			{	// Use any folder which name is started with "b2evolution":
				if( file_exists( $dir_path.'/blogs' ) )
				{	// Use 'b2evolution*/blogs' folder:
					$upgrade_folder_path = $dir_path.'/blogs';
					break;
				}
				elseif( file_exists( $dir_path.'/site' ) )
				{	// Use 'b2evolution*/site' folder:
					$upgrade_folder_path = $dir_path.'/site';
					break;
				}
				elseif( file_exists( $dir_path ) )
				{	// Use 'b2evolution*' folder:
					$upgrade_folder_path = $dir_path;
					break;
				}
			}
		}
		closedir( $dir_handle );
	}

	return $upgrade_folder_path;
}


/**
 * Check version of downloaded upgrade vs. current version
 *
 * @param new version dir name
 * @return array|NULL NULL - version is new, Array - version is old or same,
 *                    keys 'error' => 'old' or 'same', 'message' - Message text
 */
function check_version( $new_version_dir )
{
	global $rsc_url, $upgrade_path, $conf_path;

	$new_version_file = get_upgrade_folder_path( $new_version_dir ).'/conf/_application.php';

	if( ! file_exists( $new_version_file ) )
	{ // Invalid structure of the downloaded upgrade package
		debug_die( '/conf/_application.php not found in /b2evolution/blogs/ nor /b2evolution/site/ nor /b2evolution/! You may have downloaded an invalid ZIP package.' );
	}

	require( $new_version_file );

	$vc = evo_version_compare( $app_version, $GLOBALS['app_version'] );

	if( $vc < 0 )
	{
		$result = 'old';
	}
	elseif( $vc == 0 )
	{
		if( $app_date == $GLOBALS['app_date'] )
		{
			$result = 'same';
		}
		elseif( $app_date < $GLOBALS['app_date'] )
		{
			$result = 'old';
		}
	}

	if( empty( $result ) )
	{	// New version:
		return NULL;
	}
	elseif( $result == 'old' )
	{	// Old version:
		return array(
				'error'   => 'old',
				'message' => TB_('This is an old version!').'<br />'
					.'Current: '.$GLOBALS['app_version'].' '.$GLOBALS['app_date'].'<br />'			
					.'About to install: '.$app_version.' '.$app_date.'<br />'
					.TB_('You should NOT install this older version.')
			);
	}
	elseif( $result == 'same' )
	{	// Same version:
		return array(
				'error'   => 'same',
				'message' => TB_('This package is already installed!').'<br />'
					.TB_('No upgrade is needed at this time. You might force a re-install if you want to force a cleanup.')
			);
	}
}


/**
 * Enable/disable maintenance mode
 *
 * @param boolean Do we want to enable or disable maintenance mode?
 * @param string Mode: 'all', 'install', 'upgrade'
 * @param string maintenance mode message
 * @param boolean TRUE to don't print out a message status
 */
function switch_maintenance_mode( $enable, $mode = 'all', $msg = '', $silent = false )
{
	global $conf_path;
	static $maintenance_mode = 'unknown';

	switch( $mode )
	{
		case 'install':
			// Use maintenance mode except of install actions
			$maintenance_mode_file = 'imaintenance.html';
			break;

		case 'upgrade':
			// Use maintenance mode except of upgrade actions
			$maintenance_mode_file = 'umaintenance.html';
			break;

		default:
			// Use full maintenance mode
			$maintenance_mode_file = 'maintenance.html';
			break;
	}

	if( $enable )
	{	// Create maintenance file
		echo '<p>'.TB_('Switching to maintenance mode...');
		evo_flush();

		$content = '<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Site temporarily down for maintenance.</title>
</head>
<body>
<h1>503 Service Unavailable</h1>
<p>'.$msg.'</p>
<hr />
<p>Site administrators: please view the source of this page for details.</p>
<!--
If you need to manually put b2evolution OUT of maintenance mode, delete or rename the file
/conf/maintenance.html or /conf/imaintenance.html or /conf/umaintenance.html .
The presence of any of these files will make b2evolution show it is in maintenance mode.

WARNING: If you just had an upgrade fail in the middle of it, it is a very bad idea to just
get out of maintenance mode without immdiately restoring a DB backup first. Continuing without
a clean DB may make it impossible to ever ugrade your b2evolution in the future.
-->
</body>
</html>';

		if( save_to_file( $content, $conf_path.$maintenance_mode_file, 'w+' ) )
		{ // Maintenance file has been created
			echo ' OK.</p>';
		}
		else
		{ // Maintenance file has not been created
			echo '</p><p style="color:red"><evo:error>'.sprintf( TB_('Unable to switch to maintenance mode. Maintenance file can\'t be created: &laquo;%s&raquo;'), $maintenance_mode_file ).'</evo:error></p>';
			evo_flush();

			return false;
		}
	}
	else
	{	// Delete maintenance file
	    
		if( $maintenance_mode == 'unknown' ){
		    
		    if( ! $silent )
		    {
			    echo '<p>'.TB_('Switching out of maintenance mode...');
			    $maintenance_mode = 'disable';
		    }
		    // Delete a maintenance file if it exists and writable:
		    if( is_writable( $conf_path.$maintenance_mode_file ) && @unlink( $conf_path.$maintenance_mode_file ) )
		    {	// Unlink was successful:
			    if( ! $silent )
			    {	// Dispaly OK message:
				    echo ' OK.</p>';
				    evo_flush();
			    }
		    }
		    else
		    {	// Unlink failed:
			    echo '</p><p style="color:red"><evo:error>'.sprintf( TB_('Unable to delete maintenance file: &laquo;%s&raquo;'), $maintenance_mode_file ).'</evo:error></p>';
			    evo_flush();

			    return false;
		    }
		}
		
	}

	return true;
}


/**
 * Enable/disable maintenance lock
 *
 * @param boolean true if maintenance lock need to be enabled
 * @return bollean true on success, false otherwise
 */
function switch_maintenance_lock( $enable )
{
	global $Settings;

	if( $Settings->get( 'system_lock' ) != $enable )
	{ // Enable system lock
		$Settings->set( 'system_lock', $enable );
		return $Settings->dbupdate();
	}

	return true;
}


/**
 * Prepare maintenance directory
 *
 * @param string directory path
 * @param boolean create .htaccess file with 'deny from all' text
 * @return boolean
 */
function prepare_maintenance_dir( $dir_name, $deny_access = true )
{

	// echo '<p>'.TB_('Checking destination directory: ').$dir_name.'</p>';
	if( !file_exists( $dir_name ) )
	{	// We can create directory
		if ( ! mkdir_r( $dir_name ) )
		{
			echo '<p style="color:red">'.sprintf( TB_('Unable to create &laquo;%s&raquo; directory.'), $dir_name ).'</p>';
			evo_flush();

			return false;
		}
	}

	if( $deny_access )
	{	// Create .htaccess file
		echo '<p>'.TB_('Checking .htaccess denial for directory: ').$dir_name;
		evo_flush();

		$htaccess_name = $dir_name.'.htaccess';

		if( !file_exists( $htaccess_name ) )
		{	// We can create .htaccess file
			if( ! save_to_file( 'deny from all', $htaccess_name, 'w' ) )
			{
				echo '</p><p style="color:red">'.sprintf( TB_('Unable to create &laquo;%s&raquo; file in directory.'), $htaccess_name ).'</p>';
				evo_flush();

				return false;
			}

			if( ! file_exists($dir_name.'index.html') )
			{	// Create index.html to disable directory browsing
				save_to_file( '', $dir_name.'index.html', 'w' );
			}
		}

		echo ' : OK.</p>';
		evo_flush();

		// fp> TODO: make sure "deny all" actually works by trying to request the directory through HTTP
	}

	return true;
}


/**
 * Unpack ZIP archive to destination directory
 *
 * @param string source file path
 * @param string destination directory path
 * @param boolean true if create destination directory
 * @param string Zip file name
 * @param boolean TRUE to print error, FALSE to return error
 * @return boolean|string TRUE on success, FALSE|string on error
 */
function unpack_archive( $src_file, $dest_dir, $mk_dest_dir = false, $src_file_name = '', $print_error = true )
{
	global $Settings, $current_User, $basepath, $upgrade_path;

	if( ! check_user_perm( 'files', 'all' ) )
	{	// No permission to unzip files:
		$error = '<span class="text-danger">'.TB_('You don\'t have permission to UNZIP files automatically on the server.').'</span>';
		if( check_user_perm( 'users', 'edit' ) )
		{	// Link to edit permissions:
			global $admin_url;
			$error .= ' ('.sprintf( TB_('You can change this <a %s>here</a>'), 'href="'.$admin_url.'?ctrl=groups&amp;action=edit&amp;grp_ID='.$current_User->get( 'grp_ID' ).'#fieldset_wrapper_file"' ).')';
		}
		$error = '<p>'.$error.'</p>';
		if( $print_error )
		{	// Print error:
			echo $error;
			evo_flush();
			return false;
		}
		else
		{	// Return error message:
			return $error;
		}
	}

	if( strpos( $src_file, '://' ) !== false )
	{	// Deny ZIP file from urls:
		$invalid_path_error = sprintf( TB_('Path must not contain %s'), '<code>://</code>' );
	}
	else
	{	// Check if ZIP path is inside $basepath or $upgrade_path:
		$canonical_path = get_canonical_path( $src_file );
		if( strpos( $canonical_path, $basepath ) !== 0 &&
		    strpos( $canonical_path, get_canonical_path( $upgrade_path ) ) !== 0 )
		{	// ZIP file path must be started with $basepath or $upgrade_path:
			$invalid_path_error = sprintf( TB_('Path is outside %s and outside %s.'), '$basepath=<code>'.$basepath.'</code>', '$upgrade_path=<code>'.$upgrade_path.'</code>' );
		}
	}
	if( isset( $invalid_path_error ) )
	{	// Don't allow wrong ZIP file path:
		$error = '<p class="text-danger">'.sprintf( TB_('Invalid ZIP file path %s:'), '<code>'.$src_file.'</code>' ).' '.$invalid_path_error.'</p>';
		if( $print_error )
		{	// Print error:
			echo $error;
			evo_flush();
			return false;
		}
		else
		{	// Return error message:
			return $error;
		}
	}

	if( ! file_exists( $dest_dir ) && ! mkdir_r( $dest_dir ) )
	{	// Destination directory doesn't exist and it couldn't be created:
		$error = '<p class="text-danger">'.sprintf( TB_('Unable to create &laquo;%s&raquo; directory to extract files from ZIP archive.'), $dest_dir ).'</p>';
		if( $print_error )
		{	// Print error:
			echo $error;
			evo_flush();
			return false;
		}
		else
		{	// Return error message:
			return $error;
		}
	}

	if( class_exists( 'ZipArchive' ) )
	{	// Unpack using 'ZipArchive' extension:
		$ZipArchive = new ZipArchive();
		if( $ZipArchive->open( $src_file ) &&
		    $ZipArchive->extractTo( $dest_dir ) )
		{	// Change rights for unpacked folders and files after successful unpacking:
			chmod_r( $dest_dir );
			$ZipArchive->close();
		}
		else
		{
			$error = '<p class="text-danger">'
					.sprintf( TB_('Error: %s'), $ZipArchive->getStatusString() ).'<br />'
					.sprintf( TB_('Unable to decompress &laquo;%s&raquo; ZIP archive.'), ( empty( $src_file_name ) ? $src_file : $src_file_name ) )
				.'</p>';
			if( $print_error )
			{	// Print error:
				echo $error;
				evo_flush();
				return false;
			}
			else
			{	// Return error message:
				return $error;
			}
		}
	}
	else
	{
		debug_die( 'Unable to decompress the file because there is no \'ZipArchive\' extension installed in your PHP!' );
	}

	return true;
}


/**
 * Pack ZIP archive from destination directory/file
 *
 * @param string Path of new archive
 * @param string Directory path where files are located
 * @param string|array Files which should be added into ZIP archive
 * @param string|array Sub-directory name where files should added inside ZIP relative, Use empty to add in root of the ZIP archive; May be array: 0 key is for all files/folders, other key - for custom files/folders
 * @param array|string Exclude folders and files from folders with these names, 'subdirs' - to exclude ALL subfolders
 * @param string Type of log: 'print', 'msg_error'
 * @return boolean TRUE on success
 */
function pack_archive( $archive_path, $source_dir_path, $files, $add_in_subdirs = '', $exclude_folder_names = array(), $log_type = 'print' )
{
	global $Settings, $Messages;

	if( ! class_exists( 'ZipArchive' ) )
	{	// Stop when no installed extension:
		debug_die( 'Unable to compress the files because there is no \'ZipArchive\' extension installed in your PHP!' );
	}

	if( file_exists( $archive_path ) )
	{	// Don't try to create ZIP if same file already exists:
		$log_msg = sprintf( TB_('File %s already exists.'), '<code>'.$archive_path.'</code>' );
		if( $log_type == 'print' )
		{
			echo '<p class="text-danger">'.$log_msg.'</p>';
			evo_flush();
		}
		elseif( $log_type == 'msg_error' )
		{
			$Messages->add( $log_msg, 'error' );
		}
		return false;
	}

	// Pack using 'ZipArchive' extension:
	$ZipArchive = new ZipArchive();

	if( $ZipArchive->open( $archive_path, ZipArchive::CREATE ) !== TRUE )
	{	// Cannot create new ZIP archive:
		$log_msg = sprintf( TB_('Error: %s'), $ZipArchive->getStatusString() ).'<br />'
			.sprintf( TB_('Unable to create ZIP archive %s.'), '<code>'.$archive_path.'</code>' );
		if( $log_type == 'print' )
		{
			echo '<p class="text-danger">'.$log_msg.'</p>';
			evo_flush();
		}
		elseif( $log_type == 'msg_error' )
		{
			$Messages->add( $log_msg, 'error' );
		}

		return false;
	}

	if( ! is_array( $files ) )
	{	// Make array from single file:
		$files = array( $files );
	}

	$source_dir_path_length = strlen( $source_dir_path );

	if( ! is_array( $add_in_subdirs ) )
	{
		$add_in_subdirs = array( $add_in_subdirs );
	}
	foreach( $add_in_subdirs as $a => $add_in_subdir )
	{
		if( ! empty( $add_in_subdir ) )
		{	// Format sub-directory:
			$add_in_subdirs[ $a ] = trim( $add_in_subdir, '/' ).'/';
		}
	}

	if( is_array( $exclude_folder_names ) )
	{	// Initialize array to exclude subfolders by name:
		foreach( $exclude_folder_names as $e => $exclude_folder_name )
		{
			$exclude_folder_names[ $e ] = preg_quote( trim( $exclude_folder_name, '/' ) );
		}
		$exclude_folder_names_regexp = empty( $exclude_folder_names ) ? false : '#(^|/)'.implode( '|', $exclude_folder_names ).'(/|$)#';
	}
	else
	{
		$exclude_folder_names_regexp = false;
	}

	$zip_result = true;
	foreach( $files as $file )
	{	// Add files into archive:
		if( $log_type == 'print' )
		{
			echo sprintf( TB_('Adding &laquo;<strong>%s</strong>&raquo; to ZIP file...'), $source_dir_path.$file );
			evo_flush();
		}
		$add_in_subdir = isset( $add_in_subdirs[ $file ] ) ? $add_in_subdirs[ $file ] : $add_in_subdirs[0];
		if( is_dir( $source_dir_path.$file ) )
		{	// Add directory:
			if( $exclude_folder_names_regexp !== false &&
			    preg_match( $exclude_folder_names_regexp, $file ) )
			{	// Skip file by excluded folder name:
				continue;
			}
			$file_result = $ZipArchive->addEmptyDir( '/'.$add_in_subdir.trim( $file, '/' ) );
			if( $file_result && ( $dir_files = get_filenames( $source_dir_path.$file, array( 'inc_evocache' => true, 'recurse' => ( $exclude_folder_names !== 'subdirs' ), ) ) ) )
			{	// Add files of the directory:
				foreach( $dir_files as $dir_file )
				{
					$rel_dir_file_path = '/'.$add_in_subdir.substr( $dir_file, $source_dir_path_length );
					if( $exclude_folder_names_regexp !== false &&
					   preg_match( $exclude_folder_names_regexp, $rel_dir_file_path ) )
					{	// Skip file by excluded folder name:
						continue;
					}
					if( is_dir( $dir_file ) )
					{	// Add empty sub-directory:
						if( $exclude_folder_names !== 'subdirs' )
						{
							$file_result = $ZipArchive->addEmptyDir( $rel_dir_file_path ) && $file_result;
						}
					}
					else
					{	// Add file:
						$file_result = $ZipArchive->addFile( $dir_file, $rel_dir_file_path ) && $file_result;
					}
				}
			}
		}
		else
		{	// Add file:
			$file_result = $ZipArchive->addFile( $source_dir_path.$file, '/'.$add_in_subdir.$file );
		}

		if( $file_result )
		{	// Display success result:
			if( $log_type == 'print' )
			{
				echo ' OK.<br />';
				evo_flush();
			}
		}
		else
		{	// Display error:
			$log_msg = sprintf( TB_('Error: %s'), $ZipArchive->getStatusString() );
			if( $log_type == 'print' )
			{
				echo ' <span class="text-danger">'.$log_msg.'</span>.<br />';
				evo_flush();
			}
			elseif( $log_type == 'msg_error' )
			{
				$Messages->add( $log_msg, 'error' );
			}
		}

		$zip_result = $zip_result && $file_result;
	}

	if( $log_type == 'print' )
	{
		echo sprintf( TB_('Compressing &laquo;<strong>%s</strong>&raquo;...'), $archive_path );
		evo_flush();
	}

	$ZipArchive->close();

	if( $log_type == 'print' )
	{
		echo ' OK.<br />';
	}

	// Set rights for new created ZIP file:
	@chmod( $archive_path, octdec( $Settings->get( 'fm_default_chmod_file' ) ) );

	return $zip_result;
}


/**
 * Download ZIP archive
 *
 * @param string Path of the archive
 */
function download_archive( $archive_path )
{
	if( ! file_exists( $archive_path ) ||
	    ! preg_match( '/\.zip$/', $archive_path ) )
	{	// Don't try to download not existing of not ZIP file:
		return false;;
	}

	$archive_content = file_get_contents( $archive_path );

	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="'.basename( $archive_path ).'"' );
	header( 'Content-Length: '.strlen( $archive_content ) );
	header( 'Content-Transfer-Encoding: binary' );
	header( 'Cache-Control: no-cache, must-revalidate, max-age=60' );
	header( 'Expires: 0' );

	echo $archive_content;
}


/**
 * Verify that destination files can be overwritten
 *
 * @param string source directory
 * @param string destination directory
 * @param string action name
 * @param boolean overwrite
 * @param array read only file list
 */
  //* Deprecated Msg*/
function verify_overwrite( $src, $dest, & $read_only_list, $action = '', $overwrite = true )
{
	global $basepath, $Settings;

	/**
	 * Result of this function is FALSE when some error was detected
	 * @var boolean
	 */
	$result = true;

	$dir = opendir( $src );

	if( $dir === false )
	{ // $dir is not a valid directory or it can not be opened due to permission restrictions
		echo '<div class="red">The &laquo;'.htmlspecialchars( $src ).'&raquo; is not a valid direcotry or the directory can not be opened due to permission restrictions or filesystem errors.</div>';
		return false;
	}

	$dir_list = array();
	$file_list = array();
	while( false !== ( $file = readdir( $dir ) ) )
	{
		if ( ( $file != '.' ) && ( $file != '..' ) )
		{
			$srcfile = $src.'/'.$file;
			$destfile = $dest.'/'.$file;

			if( isset( $read_only_list ) && file_exists( $destfile ) && !is_writable( $destfile ) )
			{ // Folder or file is not writable
				$read_only_list[] = $destfile;
			}

			if ( is_dir( $srcfile ) )
			{
				$dir_list[$srcfile] = $destfile;
			}
			elseif( $overwrite )
			{ // Add to overwrite
				$file_list[$srcfile] = $destfile;
			}
		}
	}

	$config_ignore_files = get_upgrade_config( 'ignore' );
	$config_softmove_files = get_upgrade_config( 'softmove');
	$config_forcemove_files = get_upgrade_config( 'forcemove' );

	if( ! empty( $action ) && $action == 'Copying' )
	{ // Display errors about config file or the unknown and incorrect commands from config file
		$config_has_errors = false;
		if( is_string( $config_ignore_files ) )
		{ // Config file has some errors, but the upgrade should not fail because of that
			echo '<div class="red">'.$config_ignore_files.'</div>';
			$config_has_errors = true;
		}
		else
		{
			$config_unknown_commands = get_upgrade_config( 'unknown' );
			$config_incorrect_commands = get_upgrade_config( 'incorrect' );

			if( ! empty( $config_unknown_commands ) && is_array( $config_unknown_commands ) )
			{ // Unknown commands
				foreach( $config_unknown_commands as $config_unknown_command )
				{
					echo '<div class="red">'.sprintf( TB_('Unknown policy command: %s'), $config_unknown_command ).'</div>';
				}
				$config_has_errors = true;
			}

			if( ! empty( $config_incorrect_commands ) && is_array( $config_incorrect_commands ) )
			{ // Incorrect commands
				foreach( $config_incorrect_commands as $config_incorrect_command )
				{
					echo '<div class="red">'.sprintf( TB_('Incorrect policy command: %s'), $config_incorrect_command ).'</div>';
				}
				$config_has_errors = true;
			}
		}

		if( $config_has_errors )
		{ // The upgrade config file contains the errors, Stop the upgrading process
			echo '<div class="red">'.sprintf( TB_('To continue the upgrade process please fix the issues of the file %s or delete it.'), '<code>'.get_upgrade_config_file_name().'</code>' ).'</div>';
			return false;
		}
	}

	foreach( $dir_list as $src_dir => $dest_dir )
	{
		$dest_dir_name = str_replace( $basepath, '', $dest_dir );
		// Detect if we should ignore this folder
		$ignore_dir = $overwrite && is_array( $config_ignore_files ) && in_array( $dest_dir_name, $config_ignore_files );

		$dir_success = false;
		if( !empty( $action ) )
		{
			if( $ignore_dir )
			{ // Ignore folder
				echo '<div class="orange">'.sprintf( TB_('Ignoring %s because of %s'), '&laquo;<b>'.$dest_dir.'</b>&raquo;', '<code>'.get_upgrade_config_file_name().'</code>' ).'</div>';
			}
			else
			{ // progressive display of what backup is doing
				echo $action.' &laquo;<strong>'.$dest_dir.'</strong>&raquo;...';
				$dir_success = true;
			}
			evo_flush();
		}
		elseif( $ignore_dir )
		{ // This subfolder must be ingored, Display message about this
			echo '<div class="orange">'.sprintf( TB_('Ignoring %s because of %s'), '&laquo;<b>'.$dest_dir_name.'</b>&raquo;', '<code>'.get_upgrade_config_file_name().'</code>' ).'</div>';
			$dir_success = false;
			evo_flush();
		}

		if( $ignore_dir )
		{ // Skip the ignored folder
			continue;
		}

		if( $overwrite && !file_exists( $dest_dir ) )
		{
			// Create destination directory
			if( ! evo_mkdir( $dest_dir ) )
			{ // No permission to create a folder
				echo '<div class="red">'.sprintf( TB_('Unavailable creating of folder %s, probably no permissions.'), '&laquo;<b>'.$dest_dir_name.'</b>&raquo;' ).'</div>';
				$result = false;
				$dir_success = false;
				evo_flush();
				continue;
			}
		}

		if( $dir_success )
		{
			echo ' OK.<br />';
			evo_flush();
		}

		$result = $result && verify_overwrite( $src_dir, $dest_dir, '', $overwrite, $read_only_list );
	}

	foreach( $file_list as $src_file => $dest_file )
	{ // Overwrite destination file
		$dest_file_name = str_replace( $basepath, '', $dest_file );
		if( is_array( $config_ignore_files ) && in_array( $dest_file_name, $config_ignore_files ) )
		{ // Ignore this file
			echo '<div class="orange">'.sprintf( TB_('Ignoring %s because of %s'), '&laquo;<b>'.$dest_file_name.'</b>&raquo;', '<code>'.get_upgrade_config_file_name().'</code>' ).'</div>';
			evo_flush();
			continue;
		}

		if( is_array( $config_softmove_files ) && !empty( $config_softmove_files[ $dest_file_name ] ) )
		{ // Action 'softmove': This file should be copied to other location with saving old file
			$copy_file_name = $config_softmove_files[ $dest_file_name ];
			// Don't rewrite old file
			$rewrite_old_file = false;
		}
		if( is_array( $config_forcemove_files ) && !empty( $config_forcemove_files[ $dest_file_name ] ) )
		{ // Action 'forcemove': This file should be copied to other location with rewriting old file
			$copy_file_name = $config_forcemove_files[ $dest_file_name ];
			// Rewrite old file
			$rewrite_old_file = true;
		}

		if( ! empty( $copy_file_name ) )
		{ // This file is marked in config to copy to other location
			$copy_file = $basepath.$copy_file_name;
			if( ! $rewrite_old_file && file_exists( $copy_file ) )
			{ // Display warning if we cannot rewrite an existing file
				echo '<div class="orange">'.sprintf( TB_('Ignoring softmove of %s because %s is already in place (see %s)'),
						'&laquo;<b>'.$dest_file_name.'</b>&raquo;',
						'&laquo;<b>'.$copy_file_name.'</b>&raquo;',
						'<code>'.get_upgrade_config_file_name().'</code>' ).'</div>';
				evo_flush();
				unset( $copy_file_name );
				continue; // Skip this file
			}
			else
			{ // We can copy this file to other location
				echo '<div class="orange">'.sprintf( TB_('Moving %s to %s as stated in %s'),
						'&laquo;<b>'.$dest_file_name.'</b>&raquo;',
						'&laquo;<b>'.$copy_file_name.'</b>&raquo;',
						'<code>'.get_upgrade_config_file_name().'</code>' ).'</div>';
				evo_flush();
				// Set new location for a moving file
				$dest_file = $copy_file;
				$dest_file_name = $copy_file_name;
				unset( $copy_file_name );
			}
		}

		// Copying
		if( ! @copy( $src_file, $dest_file ) )
		{ // Display error if a copy command is unavailable
			echo '<div class="red">'.sprintf( TB_('Unavailable copying to %s, probably no permissions.'), '&laquo;<b>'.$dest_file_name.'</b>&raquo;' ).'</div>';
			$result = false;
			evo_flush();
		}
		else
		{	// Change rights for new file:
			@chmod( $dest_file, octdec( $Settings->get( 'fm_default_chmod_file' ) ) );
		}
	}

	closedir( $dir );

	return $result;
}


/**
 * Convert aliases to real table names as table backup works with real table names
 * @param mixed aliases
 * @return mixed
 */
function aliases_to_tables( $aliases )
{
	global $DB;

	if( is_array( $aliases ) )
	{
		$tables = array();
		foreach( $aliases as $alias )
		{
			$tables[] = preg_replace( $DB->dbaliases, $DB->dbreplaces, $alias );
		}
		return $tables;
	}
	elseif( $aliases == '*' )
	{
		return $aliases;
	}
	else
	{
		return preg_replace( $DB->dbaliases, $DB->dbreplaces, $aliases );
	}
}


/**
 * Check if the upgrade config file exists and display error message if config doesn't exist
 *
 * @return boolean TRUE if config exists
 */
function check_upgrade_config( $display_message = false )
{
	global $conf_path;

	if( ! file_exists( $conf_path.'upgrade_policy.conf' ) )
	{	// No upgrade config file
		if( $display_message )
		{	// Display error message:
			global $Messages;
			$Messages->add( TB_('WARNING: <code>upgrade_policy.conf</code> not found. We will use <code>/conf/upgrade_policy_sample.conf</code> by default but it is highly recommended you duplicate this file to <code>upgrade_policy.conf</code> and check its contents to make sure the upgrade policy is appropriate for your particluar site.'), 'warning' );
		}
		return false;
	}

	return true;
}


/**
 * Get file name of the upgrade config depending on what exists
 *
 * @return string
 */
function get_upgrade_config_file_name()
{
	global $conf_path;

	if( file_exists( $conf_path.'upgrade_policy.conf' ) )
	{	// Use custom file firstly:
		return 'upgrade_policy.conf';
	}
	else
	{	// Use sample file:
		return 'upgrade_policy_sample.conf';
	}
}


/**
 * Get a list of files and folders that must be ignored/removed on upgrade
 *
 * @param string Type of action: 'ignore', 'remove', 'softmove', 'forcemove'
 *                               'unknown' - Stores all unknown actions
 *                               'incorrect' - Stores all incorrect actions
 * @return array|string List of files and folders | Error message
 */
function get_upgrade_config( $action )
{
	global $conf_path, $upgrade_policy_config;

	if( ! isset( $upgrade_policy_config ) )
	{ // Init global array first time
		$upgrade_policy_config = array();
	}
	elseif( is_string( $upgrade_policy_config ) )
	{ // Return error about config file
		return $upgrade_policy_config;
	}

	if( isset( $upgrade_policy_config[ $action ] ) )
	{ // The config files were already initialized before, Don't make it twice
		return $upgrade_policy_config[ $action ];
	}

	$config_handle = @fopen( $conf_path.get_upgrade_config_file_name(), 'r' );
	if( ! $config_handle )
	{ // No permissions to open file
		$upgrade_policy_config = sprintf( TB_('No permission to open the %s file.'), '<code>'.get_upgrade_config_file_name().'</code>' );
		return $upgrade_policy_config;
	}

	// Get content from config file
	$config_content = '';
	while( !feof( $config_handle ) )
	{
		$config_content .= fgets( $config_handle, 4096 );
	}
	fclose( $config_handle );

	if( empty( $config_content ) )
	{ // Config file is empty for required action
		$upgrade_policy_config = sprintf( TB_('The %s file is empty.'), '<code>'.get_upgrade_config_file_name().'</code>' );
		return $upgrade_policy_config;
	}

	// Only these actions are available in the upgrade_policy.conf
	$available_actions = array( 'ignore', 'remove', 'softmove', 'forcemove' );

	$all_actions = array_merge( $available_actions, array( 'unknown', 'incorrect' ) );
	foreach( $all_actions as $available_action )
	{ // Init array for all actions only first time
		if( !isset( $upgrade_policy_config[ $available_action ] ) )
		{
			$upgrade_policy_config[ $available_action ] = array();
		}
	}

	$config_content = str_replace( "\r", '', $config_content );
	$config_content = explode( "\n", $config_content );

	foreach( $config_content as $config_line )
	{
		if( substr( $config_line, 0, 1 ) == ';' )
		{ // This line is comment text, Skip it
			continue;
		}

		$config_line = trim( $config_line );

		$config_line_params = explode( ' ', $config_line );
		$line_action =  $config_line_params[0];
		if( in_array( $line_action, $available_actions ) )
		{ // This line has an available action
			if( empty( $config_line_params[1] ) )
			{ // Incorrect command
				$upgrade_policy_config[ 'incorrect' ][] = $config_line;
				continue;
			}
			if( $line_action == 'softmove' || $line_action == 'forcemove' )
			{ // These actions have two params
				if( empty( $config_line_params[1] ) || empty( $config_line_params[2] ) )
				{ // Incorrect command
					$upgrade_policy_config[ 'incorrect' ][] = $config_line;
					continue;
				}
				$upgrade_policy_config[ $line_action ][ $config_line_params[1] ] = $config_line_params[2];
			}
			else
			{ // Actions 'ignore' & 'remove' have only one param
				$upgrade_policy_config[ $line_action ][] = $config_line_params[1];
			}
		}
		elseif( !empty( $line_action ) )
		{ // Also save all unknown actions to display error
			$upgrade_policy_config[ 'unknown' ][] = $config_line;
		}
	}

	return $upgrade_policy_config[ $action ];
}


/**
 * Remove files/folders after upgrade, See file upgrade_policy.conf
 */
function remove_after_upgrade()
{
	global $basepath, $conf_path;

	$upgrade_removed_files = get_upgrade_config( 'remove' );

	echo '<h4>'.TB_('Cleaning up...').'</h4>';
	evo_flush();

	if( is_string( $upgrade_removed_files ) )
	{ // Errors on opening of upgrade_policy.conf
		$config_error = $upgrade_removed_files;
	}
	elseif( empty( $upgrade_removed_files ) )
	{ // No files/folders to remove, Exit here
		$config_error = sprintf( TB_('No "remove" sections have been defined in the file %s.'), '<code>'.get_upgrade_config_file_name().'</code>' );
	}

	if( !empty( $config_error ) )
	{ // Display config error
		echo '<div class="red">';
		echo $config_error;
		echo ' '.TB_('No cleanup is being done. You should manually remove the <code>/install</code> folder and check for other unwanted files...');
		echo '</div>';
		return;
	}

	foreach( $upgrade_removed_files as $file_path )
	{
		$file_path = $basepath.$file_path;
		$log_message = sprintf( TB_('Removing %s as stated in %s...'), '<code>'.$file_path.'</code>', '<code>'.get_upgrade_config_file_name().'</code>' ).' ';
		$success = true;
		if( file_exists( $file_path ) )
		{ // File exists
			if( is_dir( $file_path ) )
			{ // Remove folder recursively
				if( rmdir_r( $file_path ) )
				{ // Success
					$log_message .= TB_('OK');
				}
				else
				{ // Failed
					$log_message .= TB_('Failed').': '.TB_('No permissions to delete the folder');
					$success = false;
				}
			}
			elseif( is_writable( $file_path ) )
			{ // Remove file
				if( @unlink( $file_path ) )
				{ // Success
					$log_message .= TB_('OK');
				}
				else
				{ // Failed
					$log_message .= TB_('Failed').': '.TB_('No permissions to delete the file');
					$success = false;
				}
			}
			else
			{ // File is not writable
				$log_message .= TB_('Failed').': '.TB_('No permissions to delete the file');
				$success = false;
			}
		}
		else
		{ // No file/folder
			$log_message .= TB_('Failed').': '.TB_('No file found');
			$success = false;
		}

		echo $success ? $log_message.'<br />' : '<div class="orange">'.$log_message.'</div>';
		evo_flush();
	}
}


/**
 * Get affected paths
 *
 * @param string Path
 * @return string
 */
function get_affected_paths( $path )
{
	global $basepath;

	$affected_paths = TB_('Affected paths:').' ';
	if( is_array( $path ) )
	{
		$paths = array();
		foreach( $path as $p )
			$paths[] = no_trailing_slash( $p );

		$affected_paths .= implode( ', ', $paths );
	}
	elseif( $path == '*' )
	{
		$filename_params = array(
				'inc_files'	=> false,
				'recurse'	=> false,
				'basename'	=> true,
			);
		$affected_paths .= implode( ', ', get_filenames( $basepath, $filename_params ) );
	}
	else
	{
		$affected_paths .= no_trailing_slash( $path );
	}
	return $affected_paths;
}


/**
 * Get affected tables
 *
 * @param string Table
 * @return string
 */
function get_affected_tables( $table )
{
	global $DB;

	$affected_tables = TB_('Affected tables:').' ';
	if( is_array( $table ) )
	{
		$affected_tables .= implode( ', ', aliases_to_tables( $table ) );
	}
	elseif( $table == '*' )
	{
		// Get tables what should be excluded from full tables list:
		global $backup_tables;
		$exclude_tables = array();
		foreach( $backup_tables as $backup_data )
		{
			if( isset( $backup_data['included'] ) &&
			    ! $backup_data['included'] &&
			    is_array( $backup_data['table'] ) )
			{
				$exclude_tables = array_merge( $exclude_tables, aliases_to_tables( $backup_data['table'] ) );
			}
		}

		$tables = array();
		foreach( $DB->get_results( 'SHOW TABLES', ARRAY_N ) as $row )
		{
			if( ! in_array( $row[0], $exclude_tables ) )
			{
				$tables[] = $row[0];
			}
		}

		$affected_tables .= implode( ', ', $tables );
	}
	else
	{
		$affected_tables .= aliases_to_tables( $table );
	}
	return $affected_tables;
}

/**
 * Get html template of steps panel
 *
 * @param array Steps
 * @param integer Current step
 * @return string
 */
function get_tool_steps( $steps, $current_step )
{
	if( empty( $steps ) || empty( $current_step ) )
	{ // Bad input data
		return '';
	}

	$r = '<div class="tool_steps">';
	foreach( $steps as $step_num => $step_title )
	{
		$r .= '<div class="step'
						.( $step_num == $current_step ? ' current' : '' )
						.( $step_num < $current_step ? ' completed' : '' ).'">'
					.'<div>'.$step_num
						.( $step_num < $current_step ? '<span>&#10003;</span>' : '' )
					.'</div>'
					.$step_title
				.'</div>';
	}
	$r .= '</div>';

	return $r;
}

/**
 * Display steps panel
 *
 * @param integer Current step
 * @param string Type: 'auto', 'git'
 */
function autoupgrade_display_steps( $current_step, $type = '' )
{
	$steps = array(
			1 => $type == 'git' ? TB_('Connect to Git') : TB_('Check for updates'),
			2 => TB_('Download'),
			3 => TB_('Unzip'),
			4 => TB_('Ready to upgrade'),
			5 => TB_('Backup &amp; Upgrade'),
			6 => TB_('Installer script'),
		);

	echo get_tool_steps( $steps, $current_step );
}
?>