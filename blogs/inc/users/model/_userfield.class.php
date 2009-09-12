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
 * @author evofactory-test
 * @author fplanque: Francois Planque.
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class('_core/model/dataobjects/_dataobject.class.php');

/**
 * Userfield Class
 *
 * @package evocore
 */
class Userfield extends DataObject
{
	var $type = '';
	var $name = '';

	/**
	 * Constructor
	 *
	 * @param table Database row
	 */
	function Userfield( $db_row = NULL )
	{
		// Call parent constructor:
		parent::DataObject( 'T_users__fielddefs', 'ufdf_', 'ufdf_ID' );

		// Allow inseting specific IDs
		$this->allow_ID_insert = true;


		$this->delete_restrictions = array(
			);

  	$this->delete_cascades = array(
			);

 		if( $db_row != NULL )
		{
			$this->ID            = $db_row->ufdf_ID;
			$this->type          = $db_row->ufdf_type;
			$this->name          = $db_row->ufdf_name;
		}
		else
		{	// Create a new user field:
		}
	}


	/**
	 * Load data from Request form fields.
	 *
	 * @return boolean true if loaded data seems valid.
	 */
	function load_from_Request()
	{
		// Name
		if( param( 'new_ufdf_ID', 'string', NULL ) !== NULL )
		{
			param_check_number( 'new_ufdf_ID', T_('ID must be a number'), true );
			$this->set_from_Request( 'ID', 'new_ufdf_ID' );
		}

		// Name
		param_string_not_empty( 'ufdf_type', T_('Please enter a type.') );
		$this->set_from_Request( 'type' );

		// Name
		param_string_not_empty( 'ufdf_name', T_('Please enter a name.') );
		$this->set_from_Request( 'name' );

		return ! param_errors_detected();
	}

	function get_name()
	{
		return $this->name;
	}

	/**
	 * Set param value
	 *
	 * By default, all values will be considered strings
	 *
	 * @param string parameter name
	 * @param mixed parameter value
	 */
	function set( $parname, $parvalue )
	{
		switch( $parname )
		{
			case 'type':
			case 'name':
			default:
				$this->set_param( $parname, 'string', $parvalue );
		}
	}

}

/*
 * $Log$
 * Revision 1.1  2009/09/11 18:34:06  fplanque
 * userfields editing module.
 * needs further cleanup but I think it works.
 *
 */
?>