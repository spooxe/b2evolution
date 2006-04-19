<?php
/**
 * This file implements the UI controller for file upload.
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
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @global File
 */
global $edit_File;

// Begin payload block:
$this->disp_payload_begin();

$Form = & new Form( NULL, 'fm_properties_checkchanges' );

$Form->global_icon( T_('Close properties!'), 'close', regenerate_url('fm_mode') );

$Form->begin_form( 'fform', T_('File properties') );
	$Form->hidden_ctrl();
	$Form->hidden( 'action', 'update_properties' );
	$Form->hiddens_by_key( get_memorized() );

	$Form->begin_fieldset( T_('Properties') );
		$Form->info( T_('Filename'), $edit_File->get_name(), T_('This is the name of the file on the server hard drive.') );
		$Form->info( T_('Type'), $edit_File->get_icon().' '.$edit_File->get_type() );
	$Form->end_fieldset();

	$Form->begin_fieldset( T_('Meta data') );
		if( $current_User->check_perm( 'files', 'edit' ) )
		{ // User can edit:
			$Form->text( 'title', $edit_File->title, 50, T_('Long title'), T_('This is a longer descriptive title'), 255 );
			$Form->text( 'alt', $edit_File->alt, 50, T_('Alternative text'), T_('This is useful for images'), 255 );
			$Form->textarea( 'desc', $edit_File->desc, 10, T_('Caption/Description') );
		}
		else
		{ // User can view only:
			$Form->info( T_('Long title'), $edit_File->dget('title'), T_('This is a longer descriptive title') );
			$Form->info( T_('Alternative text'), $edit_File->dget('alt'), T_('This is useful for images') );
			$Form->info( T_('Caption/Description'), $edit_File->dget('desc') );
		}
	$Form->end_fieldset();

if( $current_User->check_perm( 'files', 'edit' ) )
{ // User can edit:
	$Form->end_form( array( array( 'submit', '', T_('Update'), 'SaveButton' ),
													array( 'reset', '', T_('Reset'), 'ResetButton' ) ) );
}
else
{ // User can view only:
	$Form->end_form();
}

// End payload block:
$this->disp_payload_end();

/*
 * $Log$
 * Revision 1.4  2006/04/19 20:13:51  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.3  2006/03/12 23:09:01  fplanque
 * doc cleanup
 *
 * Revision 1.2  2006/03/12 03:03:33  blueyed
 * Fixed and cleaned up "filemanager".
 *
 * Revision 1.1  2006/02/23 21:12:17  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.13  2006/02/11 21:19:29  fplanque
 * added bozo validator to FM
 *
 * Revision 1.12  2005/12/12 19:21:20  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.11  2005/10/31 23:20:45  fplanque
 * keeping things straight...
 *
 * Revision 1.10  2005/10/28 20:08:46  blueyed
 * Normalized AdminUI
 *
 * Revision 1.9  2005/09/06 17:13:53  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.8  2005/08/22 18:42:25  fplanque
 * minor
 *
 * Revision 1.7  2005/07/29 19:43:53  blueyed
 * minor: forceFM is a user setting!; typo in comment.
 *
 * Revision 1.6  2005/05/09 16:09:31  fplanque
 * implemented file manager permissions through Groups
 *
 * Revision 1.5  2005/04/28 20:44:18  fplanque
 * normalizing, doc
 *
 * Revision 1.4  2005/04/27 19:05:43  fplanque
 * normalizing, cleanup, documentaion
 *
 * Revision 1.3  2005/04/15 18:02:58  fplanque
 * finished implementation of properties/meta data editor
 * started implementation of files to items linking
 *
 * Revision 1.2  2005/04/14 19:57:51  fplanque
 * filemanager refactoring & cleanup
 * started implementation of properties/meta data editor
 * note: the whole fm_mode thing is not really desireable...
 *
 * Revision 1.1  2005/04/14 18:34:02  fplanque
 * filemanager refactoring
 *
 */
?>