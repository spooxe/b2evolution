<?php
/**
 * This file implements the FileCache class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2006 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://cvs.sourceforge.net/viewcvs.py/evocms/)
 *   then you must choose one of the following licenses before using the file:
 *   - GNU General Public License 2 (GPL) - http://www.opensource.org/licenses/gpl-license.php
 *   - Mozilla Public License 1.1 (MPL) - http://www.opensource.org/licenses/mozilla1.1.php
 * }}
 *
 * {@internal Open Source relicensing agreement:
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Includes:
 */
require_once dirname(__FILE__).'/../dataobjects/_dataobjectcache.class.php';

/**
 * FileCache Class
 *
 * @package evocore
 */
class FileCache extends DataObjectCache
{
	/**
	 * Cache for 'root_type:root_in_type_ID:relative_path' -> File object reference
	 * @access private
	 * @var array
	 */
	var $cache_root_and_path = array();

	/**
	 * Constructor
	 */
	function FileCache()
	{
		parent::DataObjectCache( 'File', false, 'T_files', 'file_', 'file_ID' );
	}


	/**
	 * Instantiate a DataObject from a table row and then cache it.
	 *
	 * @param Object Database row
	 * @return Object
	 */
	function & instantiate( & $db_row )
	{
		// Get ID of the object we'ere preparing to instantiate...
		$obj_ID = $db_row->{$this->dbIDname};

 		if( !empty($obj_ID) )
		{	// If the object ID is valid:
	 		if( !isset($this->cache[$obj_ID]) )
			{	// If not already cached:
				// Instantiate a File object for this line:
				$current_File = new File( $db_row->file_root_type, $db_row->file_root_ID, $db_row->file_path ); // COPY!
				// Flow meta data into File object:
				$current_File->load_meta( false, $db_row );
				$this->add( $current_File );
			}
			else
			{	// Already cached:
				// Flow meta data into File object:
				$current_File->load_meta( false, $db_row );
			}
		}

		return $this->cache[$obj_ID];
	}


  /**
	 * Creates an object of the {@link File} class, while providing caching
	 * and making sure that only one reference to a file exists.
	 *
	 * @param string Root type: 'user', 'group' or 'collection'
	 * @param integer ID of the user, the group or the collection the file belongs to...
	 * @param string Subpath for this file/folder, relative the associated root, including trailing slash (if directory)
	 * @param boolean check for meta data?
	 * @return File an {@link File} object
	 */
	function & get_by_root_and_path( $root_type, $root_in_type_ID, $rel_path, $load_meta = false )
	{
		global $Debuglog, $cache_File;

		if( is_windows() )
		{
			$rel_path = strtolower(str_replace( '\\', '/', $rel_path ));
		}

		// Generate cache key for this file:
		$cacheindex = $root_type.':'.$root_in_type_ID.':'.$rel_path;

		if( isset( $this->cache_root_and_path[$cacheindex] ) )
		{	// Already in cache
			$Debuglog->add( 'File retrieved from cache: '.$cacheindex, 'files' );
			$File = & $this->cache_root_and_path[$cacheindex];
			if( $load_meta )
			{	// Make sure meta is loaded:
				$File->load_meta();
			}
		}
		else
		{	// Not in cache
			$Debuglog->add( 'File not in cache: '.$cacheindex, 'files' );
			$File = new File( $root_type, $root_in_type_ID, $rel_path, $load_meta ); // COPY !!
			$this->cache_root_and_path[$cacheindex] = & $File;
		}
		return $File;
	}


}

/*
 * $Log$
 * Revision 1.3  2006/04/19 20:13:50  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.2  2006/03/12 23:08:58  fplanque
 * doc cleanup
 *
 * Revision 1.1  2006/02/23 21:11:57  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.8  2006/01/20 16:40:56  blueyed
 * Cleanup
 *
 * Revision 1.7  2005/12/12 19:21:22  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.6  2005/09/06 17:13:54  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.5  2005/08/12 17:32:37  fplanque
 * minor
 *
 * Revision 1.4  2005/05/17 19:26:07  fplanque
 * FM: copy / move debugging
 *
 * Revision 1.3  2005/05/12 18:39:24  fplanque
 * storing multi homed/relative pathnames for file meta data
 *
 * Revision 1.2  2005/04/26 18:19:25  fplanque
 * no message
 *
 * Revision 1.1  2005/04/19 16:23:02  fplanque
 * cleanup
 * added FileCache
 * improved meta data handling
 *
 */
?>