<?php
/**
 * This file implements the abstract DataObjectList base class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2007 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * {@internal License choice
 * - If you have received this file as part of a package, please find the license.txt file in
 *   the same folder or the closest folder above for complete license terms.
 * - If you have received this file individually (e-g: from http://evocms.cvs.sourceforge.net/)
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
 * Includes
 */
require_once $inc_path.'_misc/_results.class.php';

/**
 * Data Object List Base Class
 *
 * This is typically an abstract class, useful only when derived.
 * Holds DataObjects in an array and allows walking through...
 *
 * @package evocore
 * @version beta
 * @abstract
 */
class DataObjectList extends Results
{

	/**
	 * The following should probably be obsoleted by Results::Cache
	 */
	var	$dbtablename;
	var $dbprefix;
	var $dbIDname;

	/**
	 * Class name of objects handled in this list
	 */
	var $objType;

	/**
	 * Object array
	 */
	var $Obj = array();


	/**
	 * Constructor
	 *
	 * If provided, executes SQL query via parent Results object
	 *
	 * @param string Name of table in database
	 * @param string Prefix of fields in the table
	 * @param string Name of the ID field (including prefix)
	 * @param string Name of Class for objects within this list
	 * @param string SQL query
	 * @param integer number of lines displayed on one screen
	 * @param string prefix to differentiate page/order params when multiple Results appear one same page
	 * @param string default ordering of columns (special syntax)
	 */
	function DataObjectList( $tablename, $prefix = '', $dbIDname = 'ID', $objType = 'Item', $sql = NULL,
														$limit = 20, $param_prefix = '', $default_order = NULL )
	{
		$this->dbtablename = $tablename;
		$this->dbprefix = $prefix;
		$this->dbIDname = $dbIDname;
		$this->objType = $objType;

		if( !is_null( $sql ) )
		{	// We have an SQL query to execute:
			parent::Results( $sql, $param_prefix, $default_order, $limit );
		}
		else
		{	// TODO: do we want to autogenerate a query here???
			// Temporary...
			parent::Results( $sql, $param_prefix, $default_order, $limit );
		}
	}


	/**
	 * Get next object in list
	 *
	 * @return DataObject
	 */
	function & get_next()
	{
		if( $this->current_idx >= $this->result_num_rows )
		{	// No more comment in list
			$r = false;
			return $r;
		}
		return $this->Obj[$this->current_idx++];
	}

}

/*
 * $Log$
 * Revision 1.6  2007/04/26 00:11:09  fplanque
 * (c) 2007
 *
 * Revision 1.5  2006/11/24 18:27:24  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.4  2006/04/19 20:13:50  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.3  2006/03/12 23:08:58  fplanque
 * doc cleanup
 *
 * Revision 1.2  2006/03/09 15:23:26  fplanque
 * fixed broken images
 *
 * Revision 1.1  2006/02/23 21:11:57  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.13  2005/12/12 19:21:21  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.11  2005/10/27 15:25:03  fplanque
 * Normalization; doc; comments.
 *
 * Revision 1.10  2005/09/06 17:13:54  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.9  2005/05/24 15:26:52  fplanque
 * cleanup
 *
 * Revision 1.8  2005/04/28 20:44:20  fplanque
 * normalizing, doc
 *
 * Revision 1.7  2005/03/14 20:22:19  fplanque
 * refactoring, some cacheing optimization
 *
 * Revision 1.6  2005/03/09 20:29:39  fplanque
 * added 'unit' param to allow choice between displaying x days or x posts
 * deprecated 'paged' mode (ultimately, everything should be pageable)
 *
 * Revision 1.5  2005/02/28 09:06:32  blueyed
 * removed constants for DB config (allows to override it from _config_TEST.php), introduced EVO_CONFIG_LOADED
 *
 * Revision 1.4  2005/01/03 15:17:52  fplanque
 * no message
 *
 * Revision 1.3  2004/12/27 18:37:58  fplanque
 * changed class inheritence
 *
 * Revision 1.2  2004/12/10 19:45:55  fplanque
 * refactoring
 *
 * Revision 1.1  2004/10/13 22:46:32  fplanque
 * renamed [b2]evocore/*
 *
 * Revision 1.12  2004/10/12 10:27:18  fplanque
 * Edited code documentation.
 */
?>