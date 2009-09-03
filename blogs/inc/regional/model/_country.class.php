<?php
/**
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2009 by Francois PLANQUE - {@link http://fplanque.net/}
 * Parts of this file are copyright (c)2009 by The Evo Factory - {@link http://www.evofactory.com/}.
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
 * The Evo Factory grants Francois PLANQUE the right to license
 * The Evo Factory's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author efy-maxim: Evo Factory / Maxim.
 * @author fplanque: Francois Planque.
 *
 * @version $Id$
 */

if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class('_core/model/dataobjects/_dataobject.class.php');

/**
 * Country Class
 *
 */
class Country extends DataObject
{
	var $code = '';	
	var $name = '';

	/**
	 * Constructor
	 *
	 * @param db_row database row
	 */
	function Country( $db_row = NULL )
	{

		// Call parent constructor:
		parent::DataObject( 'T_country', 'ctry_', 'ctry_ID' );

		$this->delete_restrictions = array();

  		$this->delete_cascades = array();

 		if( $db_row != NULL )
		{
			$this->ID            = $db_row->ctry_ID;
			$this->code          = $db_row->ctry_code;			
			$this->name          = $db_row->ctry_name;
		}
	}

	/**
	 * Load data from Request form fields.
	 *
	 * @return boolean true if loaded data seems valid.
	 */
	function load_from_Request()
	{
		// Code
		$this->set_string_from_param( 'code', true );		

		// Name
		$this->set_string_from_param( 'name', true );

		return ! param_errors_detected();
	}

	/**
	 * Set param value
	 *
	 * By default, all values will be considered strings
	 *
	 * @param string parameter name
	 * @param mixed parameter value
	 * @param boolean true to set to NULL if empty value
	 * @return boolean true, if a value has been set; false if it has not changed
	 */
	function set( $parname, $parvalue, $make_null = false )
	{
		switch( $parname )
		{
			case 'code':
				$parvalue = strtoupper($parvalue);			
			case 'name':
			default:
				return $this->set_param( $parname, 'string', $parvalue, $make_null );
		}
	}
	
	/**
	 * Check existing of specified country code in ctry_code unique field.
	 *
	 * @return ID if country code exists otherwise NULL/false
	 */
	function dbexists()
	{
		return parent::dbexists('ctry_code', $this->code);		
	}
}
?>