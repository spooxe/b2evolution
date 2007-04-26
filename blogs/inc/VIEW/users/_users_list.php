<?php
/**
 * This file implements the UI view for the user/group list for user/group editing.
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
 * @package admin
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );


/**
 * @var User
 */
global $current_User;
/**
 * @var GeneralSettings
 */
global $Settings;
/**
 * @var DB
 */
global $DB;


// query which groups have users (in order to prevent deletion of groups which have users)
global $usedgroups;	// We need this in a callback below
$usedgroups = $DB->get_col( 'SELECT grp_ID
															 FROM T_groups INNER JOIN T_users ON user_grp_ID = grp_ID
															GROUP BY grp_ID');

/*
 * Query user list:
 */
$keywords = param( 'keywords', 'string', '', true );

$where_clause = '';

if( !empty( $keywords ) )
{
	$kw_array = split( ' ', $keywords );
	foreach( $kw_array as $kw )
	{
		$where_clause .= 'CONCAT( user_login, \' \', user_firstname, \' \', user_lastname, \' \', user_nickname, \' \', user_email) LIKE "%'.$DB->escape($kw).'%" AND ';
	}
}

$sql = "SELECT T_users.*, grp_ID, grp_name, COUNT(blog_ID) AS nb_blogs
					FROM T_users RIGHT JOIN T_groups ON user_grp_ID = grp_ID
								LEFT JOIN T_blogs on user_ID = blog_owner_user_ID
				 WHERE $where_clause 1
				 GROUP BY user_ID
				 ORDER BY grp_name, *";


$Results = & new Results( $sql, 'user_', '-A' );

$Results->title = T_('Groups & Users');

/*
 * Table icons:
 */
if( $current_User->check_perm( 'users', 'edit', false ) )
{ // create new user link
	$Results->global_icon( T_('Add a user...'), 'new', '?ctrl=users&amp;action=new_user', T_('Add user'), 3, 4  );
	$Results->global_icon( T_('Add a group...'), 'new', '?ctrl=users&amp;action=new_group', T_('Add group'), 3, 4  );
}


/**
 * Callback to add filters on top of the result set
 *
 * @param Form
 */
function filter_userlist( & $Form )
{
	$Form->text( 'keywords', get_param('keywords'), 20, T_('Keywords'), T_('Separate with space'), 50 );
}
$Results->filter_area = array(
	'callback' => 'filter_userlist',
	'url_ignore' => 'results_user_page,keywords',
	'presets' => array(
		'all' => array( T_('All users'), '?ctrl=users' ),
		)
	);


/*
 * Grouping params:
 */
$Results->group_by = 'grp_ID';
$Results->ID_col = 'user_ID';


/*
 * Group columns:
 */
$Results->grp_cols[] = array(
						'td_class' => 'firstcol'.($current_User->check_perm( 'users', 'edit', false ) ? '' : ' lastcol' ),
						'td_colspan' => -1,  // nb_colds - 1
						'td' => '<a href="?ctrl=users&amp;grp_ID=$grp_ID$">$grp_name$</a>'
										.'¤conditional( (#grp_ID# == '.$Settings->get('newusers_grp_ID').'), \' <span class="notes">('.T_('default group for new users').')</span>\' )¤',
					);

function grp_actions( & $row )
{
	global $usedgroups, $Settings;

	$r = action_icon( T_('Edit this group...'), 'edit', regenerate_url( 'action', 'grp_ID='.$row->grp_ID ) );

	$r .= action_icon( T_('Duplicate this group...'), 'copy', regenerate_url( 'action', 'action=new_group&amp;grp_ID='.$row->grp_ID ) );

	if( ($row->grp_ID != 1) && ($row->grp_ID != $Settings->get('newusers_grp_ID')) && !in_array( $row->grp_ID, $usedgroups ) )
	{ // delete
		$r .= action_icon( T_('Delete this group!'), 'delete', regenerate_url( 'action', 'action=delete_group&amp;grp_ID='.$row->grp_ID ) );
	}
	else
	{
		$r .= get_icon( 'delete', 'noimg' );
	}
	return $r;
}
$Results->grp_cols[] = array(
						'td_class' => 'shrinkwrap',
						'td' => '%grp_actions( {row} )%',
					);

/*
 * Data columns:
 */
$Results->cols[] = array(
						'th' => T_('ID'),
						'th_class' => 'shrinkwrap',
						'td_class' => 'shrinkwrap',
						'order' => 'user_ID',
						'td' => '$user_ID$',
					);

$Results->cols[] = array(
						'th' => T_('Login'),
						'th_class' => 'shrinkwrap',
						'order' => 'user_login',
						'td' => '<a href="?ctrl=users&amp;user_ID=$user_ID$">$user_login$</a>',
					);

$Results->cols[] = array(
						'th' => T_('Nickname'),
						'th_class' => 'shrinkwrap',
						'order' => 'user_nickname',
						'td' => '$user_nickname$',
					);

$Results->cols[] = array(
						'th' => T_('Name'),
						'order' => 'user_lastname, user_firstname',
						'td' => '$user_firstname$ $user_lastname$',
					);

function user_mailto( $email )
{
	if( empty( $email ) )
	{
		return '&nbsp;';
	}
	return action_icon( T_('Email').': '.$email, 'email', 'mailto:'.$email, T_('Email') );
}
$Results->cols[] = array(
						'th' => T_('Email'),
						'td_class' => 'shrinkwrap',
						'td' => '%user_mailto( #user_email# )%',
					);

$Results->cols[] = array(
						'th' => T_('URL'),
						'td_class' => 'shrinkwrap',
						'td' => '¤conditional( (#user_url# != \'http://\') && (#user_url# != \'\'), \'<a href="$user_url$" title="Website: $user_url$">'
								.get_icon( 'www', 'imgtag', array( 'class' => 'middle', 'title' => 'Website: $user_url$' ) ).'</a>\', \'&nbsp;\' )¤',
					);

$Results->cols[] = array(
						'th' => T_('Blogs'),
						'order' => 'nb_blogs',
						'th_class' => 'shrinkwrap',
						'td_class' => 'center',
						'td' => '¤conditional( (#nb_blogs# > 0), #nb_blogs#, \'&nbsp;\' )¤',
					);

if( ! $current_User->check_perm( 'users', 'edit', false ) )
{
	$Results->cols[] = array(
						'th' => T_('Level'),
						'th_class' => 'shrinkwrap',
						'td_class' => 'right',
						'order' => 'user_level',
						'default_dir' => 'D',
						'td' => '$user_level$',
					);
}
else
{
	function display_level( $user_level, $user_ID )
	{
		$r = '';
		if( $user_level > 0)
		{
			$r .= action_icon( TS_('Decrease user level'), 'decrease',
							regenerate_url( 'action', 'action=promote&amp;prom=down&amp;user_ID='.$user_ID ) );
		}
		else
		{
			$r .= get_icon( 'decrease', 'noimg' );
		}
		$r .= sprintf( '<code>% 2d </code>', $user_level );
		if( $user_level < 10 )
		{
			$r.= action_icon( TS_('Increase user level'), 'increase',
							regenerate_url( 'action', 'action=promote&amp;prom=up&amp;user_ID='.$user_ID ) );
		}
		else
		{
	  	$r .= get_icon( 'increase', 'noimg' );
		}
		return $r;
	}
	$Results->cols[] = array(
						'th' => T_('Level'),
						'th_class' => 'shrinkwrap',
						'td_class' => 'shrinkwrap',
						'order' => 'user_level',
						'default_dir' => 'D',
						'td' => '%display_level( #user_level#, #user_ID# )%',
					);

	$Results->cols[] = array(
						'th' => T_('Actions'),
						'td_class' => 'shrinkwrap',
						'td' => action_icon( T_('Edit this user...'), 'edit', '%regenerate_url( \'action\', \'user_ID=$user_ID$\' )%' )
										.action_icon( T_('Duplicate this user...'), 'copy', '%regenerate_url( \'action\', \'action=new_user&amp;user_ID=$user_ID$\' )%' )
										.'¤conditional( (#user_ID# != 1) && (#nb_blogs# < 1) && (#user_ID# != '.$current_User->ID.'), \''
											.action_icon( T_('Delete this user!'), 'delete',
												'%regenerate_url( \'action\', \'action=delete_user&amp;user_ID=$user_ID$\' )%' ).'\', \''
	                    .get_icon( 'delete', 'noimg' ).'\' )¤'
					);
}


// Display result :
$Results->display();


/*
 * $Log$
 * Revision 1.15  2007/04/26 00:11:13  fplanque
 * (c) 2007
 *
 * Revision 1.14  2007/01/23 22:09:03  fplanque
 * visual alignment
 *
 * Revision 1.13  2006/11/24 18:27:26  blueyed
 * Fixed link to b2evo CVS browsing interface in file docblocks
 *
 * Revision 1.12  2006/08/21 01:02:10  blueyed
 * whitespace
 *
 * Revision 1.11  2006/08/20 22:25:22  fplanque
 * param_() refactoring part 2
 *
 * Revision 1.10  2006/08/20 20:12:33  fplanque
 * param_() refactoring part 1
 *
 * Revision 1.9  2006/07/16 16:44:41  blueyed
 * Fixed td_colspan for results (typo+handling of "0")
 *
 * Revision 1.8  2006/06/25 21:13:17  fplanque
 * minor
 *
 * Revision 1.7  2006/06/25 17:42:47  fplanque
 * better use of Results class (mainly for filtering)
 *
 * Revision 1.6  2006/06/13 21:49:16  blueyed
 * Merged from 1.8 branch
 *
 * Revision 1.4.2.1  2006/06/12 20:00:40  fplanque
 * one too many massive syncs...
 *
 * Revision 1.4  2006/04/19 20:14:03  fplanque
 * do not restrict to :// (does not catch subdomains, not even www.)
 *
 * Revision 1.3  2006/04/14 19:25:32  fplanque
 * evocore merge with work app
 *
 * Revision 1.2  2006/03/12 23:09:01  fplanque
 * doc cleanup
 *
 * Revision 1.1  2006/02/23 21:12:18  fplanque
 * File reorganization to MVC (Model View Controller) architecture.
 * See index.hml files in folders.
 * (Sorry for all the remaining bugs induced by the reorg... :/)
 *
 * Revision 1.58  2005/12/13 14:30:09  fplanque
 * no message
 *
 * Revision 1.57  2005/12/12 19:21:20  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.56  2005/12/08 22:23:44  blueyed
 * Merged 1-2-3-4 scheme from post-phoenix
 *
 * Revision 1.55  2005/11/25 22:45:37  fplanque
 * no message
 *
 * Revision 1.54  2005/11/16 04:16:53  blueyed
 * Made action "promote" make use of $edited_User; fixed possible SQL injection
 *
 * Revision 1.53  2005/11/03 18:23:43  fplanque
 * minor
 *
 * Revision 1.52  2005/10/28 21:02:00  fplanque
 * prevent filter matches on loginfirstname for example
 *
 * Revision 1.51  2005/10/27 15:25:03  fplanque
 * Normalization; doc; comments.
 *
 * Revision 1.50  2005/10/20 16:35:18  halton
 * added search / filtering to user list
 *
 * Revision 1.49  2005/10/03 17:26:43  fplanque
 * synched upgrade with fresh DB;
 * renamed user_ID field
 *
 * Revision 1.48  2005/09/06 17:13:53  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.47  2005/06/02 18:50:52  fplanque
 * no message
 *
 * Revision 1.46  2005/05/24 15:26:51  fplanque
 * cleanup
 *
 * Revision 1.45  2005/05/04 18:16:55  fplanque
 * Normalizing
 *
 * Revision 1.44  2005/05/03 14:38:14  fplanque
 * finished multipage userlist
 *
 * Revision 1.43  2005/05/02 19:06:45  fplanque
 * started paging of user list..
 *
 * Revision 1.42  2005/04/28 20:44:18  fplanque
 * normalizing, doc
 *
 * Revision 1.41  2005/04/21 18:01:28  fplanque
 * CSS styles refactoring
 *
 * Revision 1.40  2005/04/07 17:55:48  fplanque
 * minor changes
 *
 * Revision 1.39  2005/04/06 13:33:28  fplanque
 * minor changes
 *
 * Revision 1.38  2005/03/22 19:17:30  fplanque
 * cleaned up some nonsense...
 *
 */
?>