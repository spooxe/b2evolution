<?php
/**
 * This file implements the UI for file deletion
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2005 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2004-2005 by Daniel HAHLER - {@link https://thequod.de/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 * {@internal
 * b2evolution is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * b2evolution is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with b2evolution; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * In addition, as a special exception, the copyright holders give permission to link
 * the code of this program with the PHP/SWF Charts library by maani.us (or with
 * modified versions of this library that use the same license as PHP/SWF Charts library
 * by maani.us), and distribute linked combinations including the two. You must obey the
 * GNU General Public License in all respects for all of the code used other than the
 * PHP/SWF Charts library by maani.us. If you modify this file, you may extend this
 * exception to your version of the file, but you are not obligated to do so. If you do
 * not wish to do so, delete this exception statement from your version.
 * }}
 *
 * {@internal
 * Daniel HAHLER grants Francois PLANQUE the right to license
 * Daniel HAHLER's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 * @author blueyed: Daniel HAHLER
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * @global Filelist
 */
global $selected_Filelist;


$Form = & new Form( NULL );

$Form->global_icon( T_('Cancel delete!'), 'close', regenerate_url() );

$Form->begin_form( 'fform', T_('Delete') );

	$Form->hidden_ctrl();
	$Form->hiddens_by_key( get_memorized() );
	$Form->hidden( 'action', 'delete' );
	$Form->hidden( 'confirmed', 1 );

	echo $selected_Filelist->count() > 1
		? T_('Do you really want to delete the following files?')
		: T_('Do you really want to delete the following file?');

	$selected_Filelist->restart();
	echo '<ul>';
		while( $l_File = & $selected_Filelist->get_next() )
		{
			echo '<li>'.$l_File->get_prefixed_name().'</li>';
		}
	echo '</ul>';

$Form->end_form( array(
		array( 'submit', 'submit', T_('Delete'), 'DeleteButton' ) ) );


/*
 * $Log$
 * Revision 1.2  2006/03/12 03:03:33  blueyed
 * Fixed and cleaned up "filemanager".
 *
 * Revision 1.1  2006/02/23 21:12:17  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.7  2006/01/20 00:07:26  blueyed
 * 1-2-3-4 scheme for files.php again. Not fully tested.
 *
 * Revision 1.5  2005/11/20 21:29:07  blueyed
 * "X" (cancel) global icon back again, to allow canceling the action. Sorry.. ;)
 *
 * Revision 1.4  2005/11/19 23:45:52  blueyed
 * no "cancel" global icon, because it's no mode
 *
 * Revision 1.3  2005/11/19 05:23:06  blueyed
 * omg, just fixing log message
 *
 * Revision 1.2  2005/11/19 05:20:40  blueyed
 * this form needs no reset button at the moment
 *
 * Revision 1.1  2005/11/19 03:45:51  blueyed
 * Transformed 'delete' to 1-2-3-4 scheme, plus small fixes
 *
 */
?>