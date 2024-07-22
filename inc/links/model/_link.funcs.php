<?php
/**
 * This file implements Link handling functions.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link https://github.com/b2evolution/b2evolution}.
 *
 * @license GNU GPL v2 - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @copyright (c)2003-2020 by Francois Planque - {@link http://fplanque.com/}.
 * Parts of this file are copyright (c)2004-2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @package evocore
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( 'links/model/_linkowner.class.php', 'LinkOwner' );
load_class( 'links/model/_linkcomment.class.php', 'LinkComment' );
load_class( 'links/model/_linkitem.class.php', 'LinkItem' );
load_class( 'links/model/_linkuser.class.php', 'LinkUser' );
load_class( 'links/model/_linkemailcampaign.class.php', 'LinkEmailCampaign' );
load_class( 'links/model/_linkmessage.class.php', 'LinkMessage' );
load_class( 'links/model/_temporaryid.class.php', 'TemporaryID' );
load_class( 'messaging/model/_message.class.php', 'Message' );

/**
 * Get a link owner object from link_type and object ID
 *
 * @param string link type ( item, comment, ... )
 * @param integer the corresponding object ID
 * @return object|NULL Link Owner
 */
function & get_LinkOwner( $link_type, $object_ID )
{
	$LinkOwner = NULL;

	if( empty( $object_ID ) )
	{	// ID must be defined to get Link Owner:
		return $LinkOwner;
	}

	switch( $link_type )
	{
		case 'item':
			// Create LinkItem object:
			$ItemCache = & get_ItemCache();
			if( $Item = & $ItemCache->get_by_ID( $object_ID, false ) )
			{	// If Item is found in DB by ID:
				$LinkOwner = new LinkItem( $Item );
			}
			break;

		case 'comment':
			// Create LinkComment object:
			$CommentCache = & get_CommentCache();
			if( $Comment = & $CommentCache->get_by_ID( $object_ID, false ) )
			{	// If Comment is found in DB by ID:
				$LinkOwner = new LinkComment( $Comment );
			}
			break;

		case 'user':
			// Create LinkUser object:
			$UserCache = & get_UserCache();
			if( $User = & $UserCache->get_by_ID( $object_ID, false ) )
			{	// If User is found in DB by ID:
				$LinkOwner = new LinkUser( $User );
			}
			break;

		case 'emailcampaign':
			// Create LinkEmailCampaign object:
			$EmailCampaignCache = & get_EmailCampaignCache();
			if( $EmailCampaign = $EmailCampaignCache->get_by_ID( $object_ID, false ) )
			{
				$LinkOwner = new LinkEmailCampaign( $EmailCampaign );
			}
			break;

		case 'message':
			// Create LinkMessage object:
			$MessageCache = & get_MessageCache();
			if( $Message = & $MessageCache->get_by_ID( $object_ID, false ) )
			{	// If Message is found in DB by ID:
				$LinkOwner = new LinkMessage( $Message );
			}
			break;

		case 'temporary':
			// Create Link temporary object:
			$TemporaryIDCache = & get_TemporaryIDCache();
			if( $TemporaryID = & $TemporaryIDCache->get_by_ID( $object_ID, false ) )
			{	// If TemporaryID is found in DB by ID:
				switch( $TemporaryID->get( 'type' ) )
				{
					case 'message':
						load_class( 'messaging/model/_message.class.php', 'Message' );
						$LinkOwner = new LinkMessage( new Message(), $object_ID );
						break;

					case 'item':
						load_class( 'items/model/_item.class.php', 'Item' );
						$LinkOwner = new LinkItem( new Item(), $object_ID );
						break;

					case 'comment':
					case 'metacomment':
						load_class( 'comments/model/_comment.class.php', 'Comment' );
						$Comment = new Comment();
						if( $TemporaryID->get( 'type' ) == 'metacomment' )
						{	// Set comment type to meta to ensure correct permissions:
							$Comment->type = 'meta';
						}
						$LinkOwner = new LinkComment( $Comment, $object_ID );
						break;
				}
				$LinkOwner->tmp_ID = $object_ID;
				$LinkOwner->type = 'temporary';
			}
			break;
	}

	return $LinkOwner;
}


/**
 * Get a link owner type by link ID
 *
 * @param integer Link ID
 * @return string Link owner type
 */
function get_link_owner_type( $link_ID )
{
	$LinkCache = & get_LinkCache();
	if( ( $Link = & $LinkCache->get_by_ID( $link_ID, false, false ) ) &&
	    ( $LinkOwner = & $Link->get_LinkOwner() ) )
	{
		return $LinkOwner->type;
	}

	return '';
}


/**
 * Display attachments fieldset
 *
 * @param object Form
 * @param object LinkOwner object
 * @param boolean true to allow folding for this fieldset, false otherwise
 * @param string Fieldset prefix, Use different prefix to display several fieldset on same page, e.g. for normal and internal comments
 */
function display_attachments_fieldset( & $Form, & $LinkOwner, $fold = false, $fieldset_prefix = '' )
{
	global $admin_url, $inc_path, $action;

	if( ! isset( $GLOBALS[ 'files_Module' ] ) )
	{	// Files module is not enabled:
		return;
	}

	if( ! $LinkOwner->check_perm( 'edit', false ) )
	{	// Current user has no perm to edit the link owner:
		return;
	}

	// Set title for modal window:
	switch( $LinkOwner->type )
	{
		case 'item':
			if( $LinkOwner->is_temp() )
			{
				$window_title = '';
			}
			else
			{
				$window_title = format_to_js( sprintf( T_('Attach files to "%s"'), $LinkOwner->Item->get( 'title' ) ) );
				if( ! $LinkOwner->Item->check_proposed_change_restriction() )
				{	// Display overlay if the Item has a restriction by existing proposed change:
					$restriction_overlay = T_('You must save the post and/or accept the proposed changes before you can edit the attachments.');
				}
			}
			$form_id = 'itemform_links';
			break;

		case 'comment':
			$window_title = $LinkOwner->is_temp() ? '' : format_to_js( sprintf( T_('Attach files to comment #%s'), $LinkOwner->Comment->ID ) );
			$form_id = 'cmntform_links';
			break;

		case 'emailcampaign':
			$window_title = format_to_js( sprintf( T_('Attach files to email campaign "%s"'), $LinkOwner->EmailCampaign->get( 'name' ) ) );
			$form_id = 'ecmpform_links';
			break;

		case 'message':
			$window_title = '';
			$form_id = 'msgform_links';
			break;

		default:
			$window_title = '';
			$form_id = 'atchform_links';
			break;
	}

	$fieldset_title = T_( 'Images &amp; Attachments' );

	if( is_admin_page() )
	{	// Display a link to manual page only on back-office:
		$fieldset_title .= ' '.get_manual_link( 'images-attachments-panel' );
	}

	if( check_user_perm( 'admin', 'restricted' ) && check_user_perm( 'files', 'view' ) )
	{	// Check if current user has a permission to back-office files manager:
		$attach_files_url = $admin_url.'?ctrl=files&amp;fm_mode=link_object&amp;link_type='.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).( $LinkOwner->type != 'message' ? '&amp;link_object_ID='.$LinkOwner->get_ID() : '' );
		if( $linkowner_FileList = $LinkOwner->get_attachment_FileList( 1 ) )
		{	// Get first file of the Link Owner:
			$linkowner_File = & $linkowner_FileList->get_next();
			if( ! empty( $linkowner_File ) && check_user_perm( 'files', 'view', false, $linkowner_File->get_FileRoot() ) )
			{	// Obtain and use file root of first file:
				$linkowner_FileRoot = & $linkowner_File->get_FileRoot();
				$attach_files_url .= '&amp;root='.$linkowner_FileRoot->ID;
				$attach_files_url .= '&amp;path='.dirname( $linkowner_File->get_rdfs_rel_path() ).'/';
			}
		}
		$fieldset_title .= ' - '
			.action_icon( T_('Attach existing files'), 'folder', $attach_files_url,
				T_('Attach existing files'), 3, 4,
				array( 'onclick' => 'return link_attachment_window( \''.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).'\', \''.$LinkOwner->get_ID().'\', \'\', \'\', \'\', \''.$fieldset_prefix.'\' )' ) );
		if( ! $LinkOwner->is_temp() )
		{	// Don't allow this option for new creating objects:
			$fieldset_title .= action_icon( T_('Attach existing files'), 'permalink', $attach_files_url,
				T_('Attach existing files'), 1, 0,
				array( 'target' => '_blank' ) );
		}
	}

	$fieldset_title .= '<span class="floatright panel_heading_action_icons">&nbsp;'

			.action_icon( T_('Refresh'), 'refresh', $LinkOwner->get_edit_url(),
				T_('Refresh'), 3, 4, array( 'class' => 'action_icon btn btn-default btn-sm', 'onclick' => 'return evo_link_refresh_list( \''.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).'\', \''.$LinkOwner->get_ID().'\', \'refresh\', \''.$fieldset_prefix.'\' )' ) )

			.action_icon( T_('Sort'), 'ascending', ( is_admin_page() || check_user_perm( 'admin', 'restricted' ) )
				? $admin_url.'?ctrl=links&amp;action=sort_links&amp;link_type='.$LinkOwner->type.'&amp;link_object_ID='.$LinkOwner->get_ID().'&amp;'.url_crumb( 'link' )
				: $LinkOwner->get_edit_url().'#',
				T_('Sort'), 3, 4, array( 'class' => 'action_icon btn btn-default btn-sm', 'onclick' => 'return evo_link_refresh_list( \''.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).'\', \''.$LinkOwner->get_ID().'\', \'sort\', \''.$fieldset_prefix.'\' )' ) )

		.'</span>';

	// Get a count of links in order to deny folding when there is at least one link
	$links_count = count( $LinkOwner->get_Links() );

	$Form->begin_fieldset( $fieldset_title, array(
			'id' => $fieldset_prefix.$form_id,
			'style' => 'display:none', // Show this uploader fieldset only when JS is enabled
			'fold' => $fold,
			'deny_fold' => ( $links_count > 0 ),
			'data-fieldset-prefix' => $fieldset_prefix,
		) );

	echo '<div id="'.$fieldset_prefix.'attachments_fieldset_wrapper" class="evo_attachments_fieldset__wrapper">';
		if( ! empty( $restriction_overlay ) )
		{	// Restrict attachments with overlay:
			echo '<div id="'.$fieldset_prefix.'attachments_fieldset_overlay" class="evo_attachments_fieldset__overlay"><b>'.$restriction_overlay.'</b></div>';
		}
		echo '<div id="'.$fieldset_prefix.'attachments_fieldset_block" class="evo_attachments_fieldset__block">';
			echo '<div id="'.$fieldset_prefix.'attachments_fieldset_table" class="evo_attachments_fieldset__table">';
				require $inc_path.'links/views/_link_list.view.php';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	$Form->end_fieldset();

	// Show fieldset of quick uploader only when JS is enabled:
	if( is_ajax_request() )
	{
		echo '<script type="text/javascript">jQuery( "#'.$fieldset_prefix.$form_id.'" ).show()</script>';
	}
	else
	{
		expose_var_to_js( 'fieldset_'.$fieldset_prefix.$form_id, array( 'fieldset_prefix' => $fieldset_prefix, 'form_id' => $form_id ), 'evo_display_attachments_fieldset_config' );
	}
	
	if( check_user_perm( 'admin', 'restricted' ) && check_user_perm( 'files', 'view' ) && empty( $restriction_overlay ) )
	{	// Check if current user has a permission to back-office files manager:

		// Initialize JavaScript to build and open window:
		echo_modalwindow_js();

		$link_attachment_window_config = array(
				'loader_title' => T_('Loading...'),
				'window_title' => $window_title,
				'crumb_link'   => get_crumb( 'link' ),
			);

		if( is_ajax_request() )
		{
			?>
			<script>
			jQuery( document ).ready( function() {
				if( ! window.evo_link_attachment_window_config )
				{
					window.evo_link_attachment_window_config = <?php echo evo_json_encode( $dragdrop_upload_button_config );?>;
				}
			} );
			</script>
			<?php
		}
		else
		{
			expose_var_to_js( 'evo_link_attachment_window_config', evo_json_encode( $link_attachment_window_config ) );
		}

		// Print JS function to allow edit file properties on modal window
		echo_file_properties();
	}
}


/**
 * Display a table with the attached files
 *
 * @param object LinkOwner
 * @param array display params
 */
function display_attachments( & $LinkOwner, $params = array() )
{
	global $redirect_to;

	$params = array_merge( array(
			'block_start' => '<div class="attachment_list">',
			'block_end'   => '</div>',
			'table_start' => '<table class="grouped" cellspacing="0" cellpadding="0">',
			'table_end'   => '</table>',
		), $params );

	$links = $LinkOwner->get_Links();

	if( count( $links ) < 1 )
	{ // there are no attachments
		return;
	}

	$redirect_to = urlencode( empty( $redirect_to ) ? regenerate_url( '', '', '', '&' ) : $redirect_to );

	echo $params['block_start'];
	echo $params['table_start'];
	echo '<thead>';
	echo '<th class="firstcol shrinkwrap"><span>'.T_('Icon/Type').'</span></th>';
	echo '<th class="nowrap"><span>'.T_('Path').'</span></th>';
	echo '<th class="lastcol shrinkwrap"><span>'.T_('Actions').'</span></th>';
	echo '</thead><tbody>';
	$row_style = '';
	foreach( $links as $Link )
	{ // display each link attachment in a row
		if( ! ( $link_File = & $Link->get_File() ) )
		{ // No File object
			global $Debuglog;
			$Debuglog->add( sprintf( 'Link ID#%d does not have a file object!', $Link->ID ), array( 'error', 'files' ) );
			continue;
		}
		$row_style = ( $row_style == 'even' ) ? 'odd' : 'even';
		echo '<tr class="'.$row_style.'"><td class="firstcol">';
		echo $link_File->get_preview_thumb( 'fulltype' );
		echo '</td><td class="nowrap left">';
		echo $link_File->get_view_link();
		echo '</td><td class="lastcol shrinkwrap">';
		if( check_user_perm( 'files', 'edit' ) )
		{ // display delete link action
			$delete_url = get_htsrv_url().'action.php?mname=collections&amp;action=unlink&amp;link_ID='.$Link->ID.'&amp;crumb_collections_unlink='.get_crumb( 'collections_unlink' ).'&amp;redirect_to='.$redirect_to;
			echo action_icon( T_('Remove'), 'remove', $delete_url );
		}
		echo '</td></tr>';
	}
	echo '</tbody>';
	echo $params['table_end'];
	echo $params['block_end'];
}


/**
 * Get a link destination
 *
 * @return string
 */
function link_destination()
{
	/**
	 * @var File
	 */
	global $current_File;

	if( empty( $current_File ) )
	{
		return '<span class="text-danger">Broken File!</span>';
	}

	$r = '';

	// File relative path & name:
	if( $current_File->is_dir() )
	{ // Directory
		$r .= $current_File->get_view_link();
	}
	else
	{ // File
		if( $view_link = $current_File->get_view_link() )
		{
			$r .= $view_link;
			// Use this hidden field to solve the conflicts on quick upload
			$r .= '<input type="hidden" value="'.$current_File->get_root_and_rel_path().'" />';
		}
		else
		{ // File extension unrecognized
			$r .= $current_File->dget( '_name' );
		}
	}

	$title = $current_File->dget('title');
	if( $title !== '' )
	{
		$r .= '<span class="filemeta"> - '.$title.'</span>';
	}

	return $r;
}


/**
 * Get select button for link in link list view
 *
 * @param integer Link ID
 * @return string
 */
function select_link_button( $link_ID, $file_type = 'image' )
{
	global $Blog, $LinkOwner, $current_File;

	$LinkCache = & get_LinkCache();
	$current_Link = & $LinkCache->get_by_ID( $link_ID );
	$linked_File = & $current_Link->get_File();


	if( empty( $Blog ) )
	{
		$Blog = & $LinkOwner->get_Blog();
	}

	$link_attribs = array();
	$link_attribs['class'] = 'evo_select_file btn btn-primary btn-xs';

	// Call evo_item_image_insert only after closing the current modal window to prevent
	// modal overlay not getting removed after closing the second modal window
	$link_attribs['onclick'] = 'closeModalWindow( window.document, function() {
		evo_item_image_insert( '.( empty( $Blog ) ? 'undefined' : $Blog->ID ).', \'image\', '.$link_ID.' );
	} );';

	$link_attribs['type'] = 'button';
	$link_attribs['title'] = T_('Select file');

	$r = '';

	if( $linked_File->get_file_type() == $file_type )
	{
		$r .= '<button'.get_field_attribs_as_string( $link_attribs, false ).'>'.T_('Select').'</button>';
		$r .= ' ';
	}

	return $r;
}


/**
 * Display link actions
 *
 * @param integer Link ID
 * @param string Index type of current row:
 *               'single' - when only one row in list
 *               'first'  - Current row is first in whole list
 *               'last'   - Current row is last in whole list
 *               'middle' - Current row is not first and not last
 * @param string Link type
 * @return string
 */
function link_actions( $link_ID, $row_idx_type = '', $link_type = 'item' )
{
	/**
	 * @var File
	 */
	global $current_File;
	global $LinkOwner;
	global $iframe_name, $admin_url, $blog;

	$r = '';

	$blog_param = empty( $blog ) ? '' : '&amp;blog='.$blog;

	// Change order.
	if( $LinkOwner->check_perm( 'edit' ) )
	{ // Check that we have permission to edit LinkOwner object:

		// Allow to move up all rows except of first, This action icon is hidden by CSS for first row
		$r .= action_icon( T_('Move upwards'), 'move_up',
						$admin_url.'?ctrl=links&amp;link_ID='.$link_ID.'&amp;action=link_move_up'.$blog_param.'&amp;'.url_crumb( 'link' ), NULL, NULL, NULL,
						array( 'class' => 'action_icon_link_move_up',
									 'onclick' => 'return evo_link_change_order( this, '.$link_ID.', \'move_up\' )',
									 'data-link-id' => $link_ID ) );

		// Allow to move down all rows except of last, This action icon is hidden by CSS for last row
		$r .= ' '.action_icon( T_('Move down'), 'move_down',
						$admin_url.'?ctrl=links&amp;link_ID='.$link_ID.'&amp;action=link_move_down'.$blog_param.'&amp;'.url_crumb( 'link' ), NULL, NULL, NULL,
						array( 'class' => 'action_icon_link_move_down',
									 'onclick' => 'return evo_link_change_order( this, '.$link_ID.', \'move_down\' )',
									 'data-link-id' => $link_ID ) );
	}

	if( $current_File && check_user_perm( 'files', 'view', false, $current_File->get_FileRoot() ) )
	{ // Locate file
		$title = $current_File->dir_or_file( T_('Locate this directory!'), T_('Locate this file!') );
		$url = $current_File->get_linkedit_url( $LinkOwner->type, $LinkOwner->get_ID() );
		$rdfp_path = ( $current_File->is_dir() ? $current_File->get_rdfp_rel_path() : dirname( $current_File->get_rdfp_rel_path() ) ).'/';

		// A link to open file manager in modal window:
		$r .= ' <a href="'.$url.'" onclick="return window.parent.link_attachment_window( \''.$LinkOwner->type.'\', \''.$LinkOwner->get_ID().'\', \''.$current_File->get_FileRoot()->ID.'\', \''.$rdfp_path.'\', \''.rawurlencode( $current_File->get_name() ).'\' )"'
					.' target="_parent" title="'.format_to_output( $title, 'htmlattr' ).'">'
					.get_icon( 'locate', 'imgtag', array( 'title' => $title ) ).'</a> ';
	}

	if( $current_File &&
	    check_user_perm( 'admin', 'restricted' ) &&
	    check_user_perm( 'files', 'edit_allowed', false, $current_File->get_FileRoot() ) )
	{	// Edit file:
		$title = T_('Edit properties...');
		$url = $current_File->get_linkedit_url( $LinkOwner->type, $LinkOwner->get_ID() );
		$rdfp_path = ( $current_File->is_dir() ? $current_File->get_rdfp_rel_path() : dirname( $current_File->get_rdfp_rel_path() ) ).'/';

		// A link to open file manager in modal window:
		$r .= ' <a href="'.$admin_url.'?ctrl=files&amp;root='.$current_File->get_FileRoot()->ID
					.'&amp;path='.rawurlencode( $current_File->get_dir_rel_path() )
					.'&amp;fm_selected[]='.rawurlencode( $current_File->get_rdfp_rel_path() )
                    .'&amp;action=edit_properties&amp;'.url_crumb( 'file' ).'"' 
				.' onclick="return window.parent.file_properties( \''.$current_File->get_FileRoot()->ID.'\', \''.$rdfp_path.'\', \''.$current_File->get_rdfp_rel_path().'\', \''.$LinkOwner->type.'\', \''.$LinkOwner->get_ID().'\', \''.( is_admin_page() ? 'backoffice' : 'frontoffice' ).'\' )"'
				.' target="_parent" title="'.format_to_output( $title, 'htmlattr' ).'">'
			.get_icon( 'edit', 'imgtag', array( 'title' => $title ) ).'</a> ';
	}

	// Unlink/Delete icons:
	if( $LinkOwner->check_perm( 'edit' ) )
	{	// If current user has a permission to edit LinkOwner object
		// Unlink icon:
		$r .= action_icon( T_('Delete this link!'), 'unlink',
					$admin_url.'?ctrl=links&amp;link_ID='.$link_ID.'&amp;action=unlink'.$blog_param.'&amp;'.url_crumb( 'link' ), NULL, NULL, NULL,
					array( 'onclick' => 'return evo_link_delete( this, \''.$LinkOwner->type.'\', '.$link_ID.', \'unlink\' )' ) );
		// Delete icon:
		$LinkCache = & get_LinkCache();
		$Link = & $LinkCache->get_by_ID( $link_ID, false, false );
		if( $current_File && ! $current_File->is_dir() && $Link && $Link->can_be_file_deleted() )
		{	// If current user has a permission to delete a file(not folder) completely
			$File = & $Link->get_File();
			$r .= action_icon( T_('Delete this file!'), 'delete',
						$admin_url.'?ctrl=links&amp;link_ID='.$link_ID.'&amp;action=delete'.$blog_param.'&amp;'.url_crumb( 'link' ), NULL, NULL, NULL,
						array( 'onclick' => 'return confirm( \''
								.sprintf( TS_('Are you sure want to DELETE the file &laquo;%s&raquo;?\nThis CANNOT be reversed!'), utf8_strip_tags( link_destination() ) )
								.'\' ) && evo_link_delete( this, \''.$LinkOwner->type.'\', '.$link_ID.', \'delete\' )',
								'data-link-id' => $link_ID ) );
		}
		else
		{	// If current user can only unlink the attachment (probably it is linked to several objects)
			$r .= get_icon( 'delete', 'imgtag', array( 'class' => 'action_icon empty_placeholder' ) );
		}
	}

	return $r;
}


/**
 * Display link position edit actions
 *
 * @param object Row of SQL query from T_links and T_files
 * @param boolean Show additional link actions
 * @param string Fieldset prefix, e.g. "meta_"
 * @return string
 */
function display_link_position( & $row, $show_actions = true, $fieldset_prefix = '' )
{
	global $LinkOwner, $blog, $Blog;
	global $current_File;

	$r = '';

	if( empty( $blog ) )
	{
		$Blog = $LinkOwner->get_Blog();
		$blog = empty( $Blog ) ? NULL : $Blog->ID;
	}

	// Get available link position for current link owner and file:
	$available_positions = $LinkOwner->get_positions( $row->file_ID );

	if( count( $available_positions ) > 1 )
	{	// Display a selector for link positions only if owner can has several positions:
		// (e.g. Message and EmailCampaign support only one position "Inline", so we don't need to display this selector there)
		$r .= '<select id="display_position_'.$row->link_ID.'">'
				.Form::get_select_options_string( $available_positions, $row->link_position, true)
			.'</select>';
	}

	if( $show_actions && $current_File )
	{
		if( isset( $available_positions['inline'] ) )
		{	// If link owner support inline position,
			// Display icon to insert image, audio, video or file inline tag into content:
			$type = $current_File->get_file_type();
			// $type = isset( $row->file_type ) ? $row->file_type : 'file';

			// valid file types: audio, video, image, other. See @link File::set_file_type()
			switch( $type )
			{
				case 'audio':
					break;

				case 'video':
					break;

				case 'image':
					break;

				case 'other':
					$type = 'file';
					break;
			}

			if( $type == 'image' )
			{
				$r .= ' '.get_icon( 'add', 'imgtag', array(
						'title'   => sprintf( T_('Insert %s tag into the post'), '['.$type.':]' ),
						'onclick' => 'return evo_item_image_insert( '.( empty( $blog ) ? 'null' : $blog ).', \'image\', '.$row->link_ID.', \''.$fieldset_prefix.'\' );',
						'style'   => 'cursor:pointer;'
					) );

			}
			elseif( $type == 'audio' || $type == 'video' || $type == 'file' )
			{
				$r .= ' '.get_icon( 'add__blue', 'imgtag', array(
							'title'   => sprintf( T_('Insert %s tag into the post'), '['.$type.':]' ),
							'onclick' => 'evo_link_insert_inline( \''.$type.'\', '.$row->link_ID.', \'\', 0, false, '.$fieldset_prefix.'b2evoCanvas )',
							'style'   => 'cursor:pointer;'
						) );

			}
			elseif( $current_File->is_dir() )
			{
				$r .= ' '.get_icon( 'add__cyan', 'imgtag', array(
							'title'   => sprintf( T_('Insert %s tag into the post'), '[folder:]' ),
							'onclick' => 'evo_link_insert_inline( \'folder\', '.$row->link_ID.', \'\', 0, false, '.$fieldset_prefix.'b2evoCanvas )',
							'style'   => 'cursor:default;'
						) );
			}
		}
	}

	return str_replace( array( "\r", "\n" ), '', $r );
}


/**
 * Print out JavaScript to change a link position
 */
function echo_link_position_js()
{
	global $Session;

	$evo_link_position_config = array(
			'selector' => 'select[id^=display_position_]',
			'url'       => get_htsrv_url(),
			'crumb'     => get_crumb( 'link' ),
			'alert_msg' => TS_('You can use the (+) icons to change the position to inline and automatically insert a short tag at the current cursor position.'),
			'display_inline_reminder' => $Session->get( 'display_inline_reminder', 'true' ),
			'defer_inline_reminder'   => false,
		);

	expose_var_to_js( 'evo_link_position_config', json_encode( $evo_link_position_config ) );
}


/**
 * Print out JavaScript to make the links table sortable
 *
 * @param string Fieldset prefix, Use different prefix to display several fieldset on same page, e.g. for normal and internal comments
 */
function echo_link_sortable_js( $fieldset_prefix = '' )
{
	$link_sortable_js_config = array(
			'fieldset_prefix' => $fieldset_prefix,
			'crumb_link'      => get_crumb( 'link' ),
		);

	if( is_ajax_request() )
	{
		?>
		<script>
		jQuery( document ).ready( function() {
			if( typeof( window.evo_link_sortable_js_config ) == 'undefined' )
			{
				window.evo_link_sortable_js_config = {};
			}
			window.evo_link_sortable_js_config['link_sortable_<?php echo $fieldset_prefix;?>'] = <?php echo evo_json_encode( $link_sortable_js_config );?>;
			window.init_link_sortable( evo_link_sortable_js_config['link_sortable_<?php echo $fieldset_prefix;?>'] );
		} );
		</script>
		<?php
	}
	else
	{
		expose_var_to_js( 'link_sortable_'.$fieldset_prefix, $link_sortable_js_config, 'evo_link_sortable_js_config' );
	}
}


/**
 * Get all links where file is used
 *
 * @param integer File ID
 * @param array Params
 * @return string The links to that posts, comments and users where the file is used
 */
function get_file_links( $file_ID, $params = array() )
{
	global $DB, $current_User, $baseurl, $admin_url;

	$params = array_merge( array(
			'separator'       => '<br />',
			'post_prefix'     => T_('Post').' - ',
			'comment_prefix'  => T_('Comment on').' - ',
			'user_prefix'     => T_('Profile picture').' - ',
			'emailcampaign_prefix' => T_('Email campaign').' - ',
			'current_link_ID' => 0,
			'current_before'  => '<b>',
			'current_after'   => '</b>',
		), $params );

	// Create result array
	$attached_to = array();

	// Get all links with posts and comments
	$links_SQL = new SQL();
	$links_SQL->SELECT( 'link_ID, link_itm_ID, link_cmt_ID, link_usr_ID, link_ecmp_ID, link_msg_ID' );
	$links_SQL->FROM( 'T_links' );
	$links_SQL->WHERE( 'link_file_ID = '.$DB->quote( $file_ID ) );
	$links = $DB->get_results( $links_SQL->get() );

	if( !empty( $links ) )
	{ // File is linked with some posts or comments
		$ItemCache = & get_ItemCache();
		$CommentCache = & get_CommentCache();
		$UserCache = & get_UserCache();
		$EmailCampaignCache = & get_EmailCampaignCache();
		$LinkCache = & get_LinkCache();
		foreach( $links as $link )
		{
			$link_object_ID = 0;
			$r = '';
			if( $params['current_link_ID'] == $link->link_ID )
			{
				$r .= $params['current_before'];
			}
			if( !empty( $link->link_itm_ID ) )
			{ // File is linked to a post
				if( $Item = & $ItemCache->get_by_ID( $link->link_itm_ID, false ) )
				{
					$Collection = $Blog = $Item->get_Blog();
					if( check_user_perm( 'item_post!CURSTATUS', 'view', false, $Item ) )
					{ // Current user can edit the linked post
						$r .= $params['post_prefix'].'<a href="'.url_add_param( $admin_url, 'ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$link->link_itm_ID ).'">'.$Item->get( 'title' ).'</a>';
					}
					else
					{ // No access to edit the linked post
						$r .= $params['post_prefix'].$Item->get( 'title' );
					}
					$link_object_ID = $link->link_itm_ID;
				}
			}
			elseif( !empty( $link->link_cmt_ID ) )
			{ // File is linked to a comment
				if( $Comment = & $CommentCache->get_by_ID( $link->link_cmt_ID, false ) )
				{
					$Item = $Comment->get_Item();
					if( check_user_perm( 'comment!CURSTATUS', 'moderate', false, $Comment ) )
					{ // Current user can edit the linked Comment
						$r .= $params['comment_prefix'].'<a href="'.url_add_param( $admin_url, 'ctrl=comments&amp;action=edit&amp;comment_ID='.$link->link_cmt_ID ).'">'.$Item->get( 'title' ).'</a>';
					}
					else
					{ // No access to edit the linked Comment
						$r .= $params['comment_prefix'].$Item->get( 'title' );
					}
					$link_object_ID = $link->link_cmt_ID;
				}
			}
			elseif( !empty( $link->link_usr_ID ) )
			{ // File is linked to user
				if( $User = & $UserCache->get_by_ID( $link->link_usr_ID, false ) )
				{
					if( $current_User->ID != $User->ID && ! check_user_perm( 'users', 'view' ) )
					{ // No permission to view other users in admin form
						$BlogCache = & get_BlogCache();
						$BlogCache->load_user_blogs();
						$user_url = '';
						if( ! empty( $BlogCache->cache ) )
						{	// Try to use alias user url:
							foreach( $BlogCache->cache as $user_Blog )
							{	// Use first found collection:
								$user_url = $user_Blog->get( 'userurl', array( 'user_ID' => $User->ID, 'user_login' => $User->login ) );
								break;
							}
						}
						if( empty( $user_url ) )
						{	// Use standard user url:
							$user_url = url_add_param( $baseurl, 'disp=user&amp;user_ID='.$User->ID );
						}
						$r .= $params['user_prefix'].'<a href="'.$user_url.'">'.$User->get_username().'</a>';
					}
					else
					{ // Build a link to display a user in admin form
						$r .= $params['user_prefix'].'<a href="?ctrl=user&amp;user_tab=profile&amp;user_ID='.$User->ID.'">'.$User->get_username().'</a>';
					}
					$link_object_ID = $link->link_usr_ID;
				}
			}
			elseif( ! empty( $link->link_ecmp_ID ) )
			{	// File is linked to email campaign:
				if( $EmailCampaign = & $EmailCampaignCache->get_by_ID( $link->link_ecmp_ID, false ) )
				{
					if( ! check_user_perm( 'emails', 'view' ) )
					{	// Build a link to display an email campaign in edit back-office form:
						$r .= $params['emailcampaign_prefix'].'<a href="?ctrl=campaigns&action=edit&tab=info&ecmp_ID='.$EmailCampaign->ID.'">'.$EmailCampaign->get( 'name' ).'</a>';
					}
					else
					{	// No permission to view email campaign in edit back-office form:
						$r .= $params['emailcampaign_prefix'].$EmailCampaign->get( 'name' );
					}
					$link_object_ID = $link->link_ecmp_ID;
				}
			}
			elseif( ! empty( $link->link_msg_ID ) )
			{	// File is linked to message:
				if( $Message = & $MessageCache->get_by_ID( $link->link_msg_ID, false ) )
				{
					$Thread = & $Message->get_Thread();
					if( ! check_user_perm( 'perm_messaging', 'reply' ) )
					{	// Build a link to display a message in edit back-office form:
						$r .= $params['message_prefix'].'<a href="?ctrl=messages&thrd_ID='.$Thread->ID.'">'.$Thread->get( 'title' ).' #'.$Message->ID.'</a>';
					}
					else
					{	// No permission to view message in edit back-office form:
						$r .= $params['message_prefix'].$Thread->get( 'title' ).' #'.$Message->ID;
					}
					$link_object_ID = $link->link_msg_ID;
				}
			}

			if( ! empty( $link_object_ID ) )
			{ // Action icon to unlink file from object
				if( ( $edited_Link = & $LinkCache->get_by_ID( $link->link_ID, false, false ) ) !== false &&
				    ( $LinkOwner = & $edited_Link->get_LinkOwner() ) !== false && $LinkOwner->check_perm( 'edit', false ) )
				{ // Allow to unlink only if current user has an permission
					$r .= ' '.action_icon( T_('Delete this link!'), 'unlink',
						$admin_url.'?ctrl=links&amp;link_ID='.$link->link_ID.'&amp;link_type=item&amp;link_object_ID='.$link_object_ID.'&amp;action=unlink&amp;redirect_to='.rawurlencode( regenerate_url( 'blog', '', '', '&' ) ).'&amp;'.url_crumb( 'link' ),
						NULL, NULL, NULL,
						array( 'onclick' => 'return confirm(\''.TS_('Are you sure want to unlink this file?').'\');' ) );
				}
			}

			if( $params['current_link_ID'] == $link->link_ID )
			{
				$r .= $params['current_after'];
			}
			if( !empty( $r ) )
			{
				$attached_to[] = $r;
			}
		}
	}

	return implode( $params['separator'], $attached_to );
}


/**
 * Save a vote for the link of file by user
 *
 * @param string Link ID
 * @param integer User ID
 * @param string Action of the voting ( 'like', 'noopinion', 'dontlike', 'inappropriate', 'spam' )
 * @param integer 1 = checked, 0 = unchecked (for checkboxes: 'Inappropriate' & 'Spam' )
 */
function link_vote( $link_ID, $user_ID, $vote_action, $checked = 1 )
{
	global $DB;

	// Set modified field name and value
	switch( $vote_action )
	{
		case 'like':
			$field_name = 'lvot_like';
			$field_value = '1';
			break;

		case 'noopinion':
			$field_name = 'lvot_like';
			$field_value = '0';
			break;

		case 'dontlike':
			$field_name = 'lvot_like';
			$field_value = '-1';
			break;

		case 'inappropriate':
			$field_name = 'lvot_inappropriate';
			$field_value = $checked;
			break;

		case 'spam':
			$field_name = 'lvot_spam';
			$field_value = $checked;
			break;

		default:
			// invalid vote action
			return;
	}

	$DB->begin();

	$SQL = new SQL( 'Check if current user already voted on link #'.$link_ID );
	$SQL->SELECT( 'lvot_link_ID, '.$field_name.' AS value' );
	$SQL->FROM( 'T_links__vote' );
	$SQL->WHERE( 'lvot_link_ID = '.$DB->quote( $link_ID ) );
	$SQL->WHERE_and( 'lvot_user_ID = '.$DB->quote( $user_ID ) );
	$existing_vote = $DB->get_row( $SQL );

	// Save a voting results in DB:
	if( empty( $existing_vote ) )
	{	// Add a new vote for first time:
		// Use a replace into to avoid duplicate key conflict in case when user clicks two times fast one after the other:
		$result = $DB->query( 'REPLACE INTO T_links__vote
			       ( lvot_link_ID, lvot_user_ID, '.$field_name.' )
			VALUES ( '.$DB->quote( $link_ID ).', '.$DB->quote( $user_ID ).', '.$DB->quote( $field_value ).' )',
			'Add new vote on link #'.$link_ID );
	}
	else
	{ // Update existing record, because user already has a vote for this file:
		if( $existing_vote->value == $field_value )
		{	// Undo previous vote:
			$field_value = NULL;
		}
		$result = $DB->query( 'UPDATE T_links__vote
			  SET '.$field_name.' = '.$DB->quote( $field_value ).'
			WHERE lvot_link_ID = '.$DB->quote( $link_ID ).'
			  AND lvot_user_ID = '.$DB->quote( $user_ID ),
			'Update a vote on link #'.$link_ID );
	}

	if( $result )
	{
		$DB->commit();
	}
	else
	{
		$DB->rollback();
	}
}


/**
 * Callback for function usort() to sort link objects by their file names
 *
 * @param object First Link object
 * @param object Second Link object
 * @return integer -1 if first file name is less than second,
 *                  1 if first file name is greater than second,
 *                  0 if they are equal.
 */
function sort_links_by_filename( $a_Link, $b_Link )
{
	$a_File = $a_Link->get_File();
	$b_File = $b_Link->get_File();

	$a_type = $a_File->dir_or_file( 'directory', 'file' );
	$b_type = $b_File->dir_or_file( 'directory', 'file' );

	if( $a_type === $b_type )
	{	// Compare only two equal types:
		$r = strnatcmp( $a_File->_name, $b_File->_name );
	}
	elseif( $a_type == 'directory' )
	{	// Directories must be before(on the top) files:
		$r = -1;
	}
	else
	{	// Files must be after(at the bottom) directories:
		$r = 1;
	}

	return $r;
}


function link_add_iframe( $link_destination )
{
	global $LinkOwner, $current_File, $iframe_name, $link_type;
	$link_owner_ID = $LinkOwner->get_ID();

	if( $current_File && $current_File->is_dir() && isset( $iframe_name ) )
	{
		$root = $current_File->get_FileRoot()->ID;
		$path = $current_File->get_rdfp_rel_path();

		// this could be made more robust
		#$link_destination = str_replace( '<a ', "<a onclick=\"return link_attachment_window( '${link_type}', '${link_owner_ID}', '${root}', '${path}' );\" ", $link_destination );
       # changed with next row $link_destination = str_replace( '<a ', "<a onclick=\"return link_attachment_window( '{${$link_type}}', '{${$link_owner_ID}}', '{${$root}}', '{${$path}}' );\" ", $link_destination );
        $link_destination = str_replace('<a ', "<a onclick=\"return link_attachment_window('$link_type', '$link_owner_ID', '$root', '$path');\" ", $link_destination);

    }

	return $link_destination;
}


/*
 * Sub Type column
 */
function display_subtype( $link_ID )
{
	global $LinkOwner, $current_File;

	$Link = $LinkOwner->get_link_by_link_ID( $link_ID );
	// Instantiate a File object for this line
	$current_File = $Link->get_File();

	return $Link->get_preview_thumb();
}

/**
 * Display attachments tab pane
 *
 * @param object Form
 * @param object LinkOwner object
 * @param boolean true to allow folding for this fieldset, false otherwise
 * @param string Tab pane prefix
 */
function display_attachments_tab_pane( & $Form, & $LinkOwner, $fold = false, $tab_pane_prefix = '' )
{
	global $admin_url, $inc_path;
	global $current_User, $action;

	if( ! isset( $GLOBALS[ 'files_Module' ] ) )
	{	// Files module is not enabled:
		return;
	}

	if( ! $LinkOwner->check_perm( 'edit', false ) )
	{	// Current user has no perm to edit the link owner:
		return;
	}

	// Set title for modal window:
	switch( $LinkOwner->type )
	{
		case 'item':
			if( $LinkOwner->is_temp() )
			{
				$window_title = '';
			}
			else
			{
				$window_title = format_to_js( sprintf( T_('Attach files to "%s"'), $LinkOwner->Item->get( 'title' ) ) );
				if( ! $LinkOwner->Item->check_proposed_change_restriction() )
				{	// Display overlay if the Item has a restriction by existing proposed change:
					$restriction_overlay = T_('You must save the post and/or accept the proposed changes before you can edit the attachments.');
				}
			}
			$form_id = 'itemform_links';
			break;

		case 'comment':
			$window_title = $LinkOwner->is_temp() ? '' : format_to_js( sprintf( T_('Attach files to comment #%s'), $LinkOwner->Comment->ID ) );
			$form_id = 'cmntform_links';
			break;

		case 'emailcampaign':
			$window_title = format_to_js( sprintf( T_('Attach files to email campaign "%s"'), $LinkOwner->EmailCampaign->get( 'name' ) ) );
			$form_id = 'ecmpform_links';
			break;

		case 'message':
			$window_title = '';
			$form_id = 'msgform_links';
			break;

		default:
			$window_title = '';
			$form_id = 'atchform_links';
			break;
	}

	$items_left = '';
	$items_right = '';

	if( is_admin_page() )
	{	// Display a link to manual page only on back-office:
		$items_right .= ' '.get_manual_link( 'images-attachments-panel' );
	}

	if( is_logged_in() && $current_User->check_perm( 'admin', 'restricted' ) && $current_User->check_perm( 'files', 'view' ) )
	{	// Check if current user has a permission to back-office files manager:
		$attach_files_url = $admin_url.'?ctrl=files&amp;fm_mode=link_object&amp;link_type='.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).( $LinkOwner->type != 'message' ? '&amp;link_object_ID='.$LinkOwner->get_ID() : '' );
		if( $linkowner_FileList = $LinkOwner->get_attachment_FileList( 1 ) )
		{	// Get first file of the Link Owner:
			$linkowner_File = & $linkowner_FileList->get_next();
			if( ! empty( $linkowner_File ) && $current_User->check_perm( 'files', 'view', false, $linkowner_File->get_FileRoot() ) )
			{	// Obtain and use file root of first file:
				$linkowner_FileRoot = & $linkowner_File->get_FileRoot();
				$attach_files_url .= '&amp;root='.$linkowner_FileRoot->ID;
				$attach_files_url .= '&amp;path='.dirname( $linkowner_File->get_rdfs_rel_path() ).'/';
			}
		}
		$items_left .= action_icon( T_('Attach existing files'), 'folder', $attach_files_url,
				T_('Attach existing files'), 3, 4,
				array( 'onclick' => 'return link_attachment_window( \''.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).'\', \''.$LinkOwner->get_ID().'\', \'\', \'\', \'\', \''.$tab_pane_prefix.'\' )' ) );
		if( ! $LinkOwner->is_temp() )
		{	// Don't allow this option for new creating objects:
			$items_left .= action_icon( T_('Attach existing files'), 'permalink', $attach_files_url,
				T_('Attach existing files'), 1, 0,
				array( 'target' => '_blank' ) );
		}
	}

	$items_right .= action_icon( T_('Refresh'), 'refresh', $LinkOwner->get_edit_url(),
			T_('Refresh'), 3, 4, array( 'class' => 'action_icon btn btn-default btn-sm', 'onclick' => 'return evo_link_refresh_list( \''.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).'\', \''.$LinkOwner->get_ID().'\' )' ) )

		.action_icon( T_('Sort'), 'ascending', ( is_admin_page() || ( is_logged_in() && $current_User->check_perm( 'admin', 'restricted' ) ) )
			? $admin_url.'?ctrl=links&amp;action=sort_links&amp;link_type='.$LinkOwner->type.'&amp;link_object_ID='.$LinkOwner->get_ID().'&amp;'.url_crumb( 'link' )
			: $LinkOwner->get_edit_url().'#',
			T_('Sort'), 3, 4, array( 'class' => 'action_icon btn btn-default btn-sm', 'onclick' => 'return evo_link_refresh_list( \''.( $LinkOwner->is_temp() ? 'temporary' : $LinkOwner->type ).'\', \''.$LinkOwner->get_ID().'\', \'sort\' )' ) );

	// Get a count of links in order to deny folding when there is at least one link
	$links_count = count( $LinkOwner->get_Links() );
	$Form->open_tab_pane( array(
			'id' => 'attachment',
			'class' => 'in active tab_pane_no_pads',
			'left_items' => $items_left,
			'right_items' => $items_right,
		) );

	echo '<div id="'.$tab_pane_prefix.'attachments_tab_pane_wrapper" class="evo_attachments_tab_pane__wrapper">';
		if( ! empty( $restriction_overlay ) )
		{	// Restrict attachments with overlay:
			echo '<div id="'.$tab_pane_prefix.'attachments_fieldset_overlay" class="evo_attachments_fieldset__overlay"><b>'.$restriction_overlay.'</b></div>';
		}
		echo '<div id="'.$tab_pane_prefix.'attachments_fieldset_block" class="evo_attachments_fieldset__block">';
			echo '<div id="'.$tab_pane_prefix.'attachments_fieldset_table" class="evo_attachments_fieldset__table">';
				require $inc_path.'links/views/_link_list.view.php';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	$Form->close_tab_pane();

	// Show fieldset of quick uploader only when JS is enabled:
	echo '<script type="text/javascript">jQuery( "#'.$tab_pane_prefix.$form_id.'" ).show()</script>';

	if( is_logged_in() && $current_User->check_perm( 'admin', 'restricted' ) && $current_User->check_perm( 'files', 'view' ) && empty( $restriction_overlay ) )
	{	// Check if current user has a permission to back-office files manager:

		// Initialize JavaScript to build and open window:
		echo_modalwindow_js();
?>
<script>
function link_attachment_window( link_owner_type, link_owner_ID, root, path, fm_highlight, prefix )
{
	openModalWindow( '<span class="loader_img loader_user_report absolute_center" title="<?php echo T_('Loading...'); ?>"></span>',
		'90%', '80%', true, '<?php echo $window_title; ?>', '', true );
	jQuery.ajax(
	{
		type: 'POST',
		url: '<?php echo get_htsrv_url(); ?>async.php',
		data:
		{
			'action': 'link_attachment',
			'link_owner_type': link_owner_type,
			'link_owner_ID': link_owner_ID,
			'crumb_link': '<?php echo get_crumb( 'link' ); ?>',
			'root': typeof( root ) == 'undefined' ? '' : root,
			'path': typeof( path ) == 'undefined' ? '' : path,
			'fm_highlight': typeof( fm_highlight ) == 'undefined' ? '' : fm_highlight,
			'prefix': typeof( prefix ) == 'undefined' ? '' : prefix,
		},
		success: function(result)
		{
			openModalWindow( result, '90%', '80%', true, '<?php echo $window_title; ?>', '' );
		}
	} );
	return false;
}
</script>
<?php
// Print JS function to allow edit file properties on modal window
echo_file_properties();
	}
}
?>
