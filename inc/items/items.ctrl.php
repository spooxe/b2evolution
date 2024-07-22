<?php
/**
 * This file implements the UI controller for managing posts.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/gnu-gpl-license}
 * @copyright (c)2003-2020 by Francois Planque - {@link http://fplanque.com/}
 *
 * @todo dh> AFAICS there are three params used for "item ID": "p", "post_ID"
 *       and "item_ID". This should get cleaned up.
 *       Side effect: "post_ID required" error if you switch tabs (expert/simple),
 *       after an error is display (e.g. entering an invalid issue time).
 *       (related to $tab_switch_params)
 * fp> Yes, it's a mess...
 *     Ironically the correct name would be itm_ID (which is what the DB uses,
 *     except for the Items table which should actually also use itm_ prefixes instead of post_
 *     ... a lot of history lead to this :p
 *
 * @package admin
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * @var AdminUI
 */
global $AdminUI;

/**
 * @var UserSettings
 */
global $UserSettings;

/**
 * @var User
 */
global $current_User;

/**
 * @var Blog
 */
global $Collection, $Blog;

global $admin_url;

global $basehost;

$action = param_action( 'list' );
$orig_action = $action; // Used to know what action is called really

$AdminUI->set_path( 'collections', 'posts' );	// Sublevel may be attached below

// We should activate toolbar menu items for this controller
$activate_collection_toolbar = true;

/*
 * Init the objects we want to work on.
 *
 * Autoselect a blog where we have PERMISSION to browse (preferably the last used blog):
 * Note: for some actions, we'll get the blog from the post ID
 */

$mass_create = param( 'mass_create', 'integer' );
if( $action == 'new_switchtab' && !empty( $mass_create ) )
{	// Replace action with mass create action
	$action = 'new_mass';
}

// for post from files
if( $action == 'group_action' )
{ // Get the real action from the select:
	$action = param( 'group_action', 'string', '' );
}


switch( $action )
{
	case 'edit':
	case 'propose':
	case 'history':
	case 'history_lastseen':
	case 'history_details':
	case 'history_compare':
	case 'history_restore':
		param( 'p', 'integer', true, true );
		param( 'restore', 'integer', 0 );

		if( $restore )
		{ // Restore a post from Session
			$edited_Item = get_session_Item( $p );
		}

		if( empty( $edited_Item ) )
		{ // Load post to edit from DB:
			$ItemCache = & get_ItemCache();
			$edited_Item = & $ItemCache->get_by_ID( $p );
		}

		if( $action == 'propose' )
		{	// Action to propose a change
			// Check if current User can create a new proposed change:
			$edited_Item->can_propose_change( true );
			if( $last_proposed_Revision = $edited_Item->get_revision( 'last_proposed' ) )
			{	// Suggest item fields values from last proposed change when user creates new propose change:
				$edited_Item->set( 'revision', 'p'.$last_proposed_Revision->iver_ID );
			}
		}

		// Load the blog we're in:
		$Collection = $Blog = & $edited_Item->get_Blog();
		set_working_blog( $Blog->ID );

		// Where are we going to redirect to?
		param( 'redirect_to', 'url', url_add_param( $admin_url, 'ctrl=items&filter=restore&blog='.$Blog->ID.'&highlight='.$edited_Item->ID, '&' ) );

		// Check if the editing Item has at least one proposed change:
		if( $action == 'edit' &&
		    ! $edited_Item->check_proposed_change_restriction( 'warning' ) &&
		    ( $last_proposed_Revision = $edited_Item->get_revision( 'last_proposed' ) ) )
		{	// Use item fields values from last proposed change:
			$edited_Item->set( 'revision', 'p'.$last_proposed_Revision->iver_ID );
		}
		break;

	case 'mass_edit':
		break;

	case 'update_edit':
	case 'update':
	case 'update_publish':
	case 'update_status':
	case 'publish':
	case 'publish_now':
	case 'restrict':
	case 'deprecate':
	case 'delete':
	case 'merge':
	case 'append':
	// Note: we need to *not* use $p in the cases above or it will conflict with the list display
	case 'edit_switchtab': // this gets set as action by JS, when we switch tabs
	case 'edit_type': // this gets set as action by JS, when we switch tabs
	case 'extract_tags':
	case 'save_propose':
	case 'accept_propose':
	case 'reject_propose':
	case 'link_version':
	case 'unlink_version':
		if( $action != 'edit_switchtab' && $action != 'edit_type' )
		{ // Stop a request from the blocked IP addresses or Domains
			antispam_block_request();
		}

		param( 'post_ID', 'integer', true, true );

		if( $action == 'edit_type' )
		{ // Load post from Session
			$edited_Item = get_session_Item( $post_ID );

			// Memorize action for list of post types
			memorize_param( 'action', 'string', '', $action );

			// Use this param to know how to redirect after item type updating
			param( 'from_tab', 'string', '' );
		}

		if( empty( $edited_Item ) || ( $action == 'edit_type' && $from_tab == 'type' ) )
		{ // Load post to edit from DB:
			// ...force the loading of post from DB if we are changing the Item Type from a post list
			$ItemCache = & get_ItemCache();
			$edited_Item = & $ItemCache->get_by_ID( $post_ID );
		}

		// Load the blog we're in:
		$Collection = $Blog = & $edited_Item->get_Blog();
		set_working_blog( $Blog->ID );

		// Where are we going to redirect to?
		param( 'redirect_to', 'url', url_add_param( $admin_url, 'ctrl=items&filter=restore&blog='.$Blog->ID.'&highlight='.$edited_Item->ID, '&' ) );

		// What form button has been pressed?
		param( 'save', 'string', '' );
		$exit_after_save = ( $action != 'update_edit' && $action != 'extract_tags' );

		if( $action == 'update_edit' )
		{	// Get params to restore height and scroll position of item content field:
			$content_height = intval( param( 'content_height', 'string', 0 ) );
			$content_scroll = intval( param( 'content_scroll', 'string', 0 ) );
		}
		break;

	case 'update_type':
		break;

	case 'mass_save' :
		param( 'redirect_to', 'url', url_add_param( $admin_url, 'ctrl=items&filter=restore&blog=' . $Blog->ID, '&' ) );
		break;

	case 'new':
	case 'new_switchtab': // this gets set as action by JS, when we switch tabs
	case 'new_mass':
	case 'new_type':
	case 'copy':
	case 'new_version':
	case 'create_edit':
	case 'create_link':
	case 'create':
	case 'create_publish':
	case 'list':
		if( in_array( $action, array( 'create_edit', 'create_link', 'create', 'create_publish' ) ) )
		{ // Stop a request from the blocked IP addresses or Domains
			antispam_block_request();
		}

		if( $action == 'list' )
		{	// We only need view permission
			$selected = autoselect_blog( 'blog_ismember', 'view' );
		}
		else
		{	// We need posting permission
			$selected = autoselect_blog( 'blog_post_statuses', 'edit' );
		}

		if( ! $selected  )
		{ // No blog could be selected
			$Messages->add( TB_('Sorry, you have no permission to post yet.'), 'error' );
			$action = 'nil';
		}
		else
		{
			if( set_working_blog( $selected ) )	// set $blog & memorize in user prefs
			{	// Selected a new blog:
				$BlogCache = & get_BlogCache();
				$Collection = $Blog = & $BlogCache->get_by_ID( $blog );
			}

			// Where are we going to redirect to?
			param( 'redirect_to', 'url', url_add_param( $admin_url, 'ctrl=items&filter=restore&blog='.$Blog->ID, '&' ) );

			// What form buttton has been pressed?
			param( 'save', 'string', '' );
			$exit_after_save = ( $action != 'create_edit' && $action != 'create_link' );
		}
		break;

	case 'make_posts_pre':
		// form for edit several posts

		if( empty( $Blog ) )
		{
			$Messages->add( TB_('No destination blog is selected.'), 'error' );
			break;
		}

		// Check perms:
		check_user_perm( 'blog_post_statuses', 'edit', true, $Blog->ID );
		break;

	case 'make_posts_from_files':
		// Make posts with selected images:

		// Stop a request from the blocked IP addresses or Domains
		antispam_block_request();

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'file' );

		$FileRootCache = & get_FileRootCache();
		// getting root
		$root = param( 'root', 'string' );

		$fm_FileRoot = & $FileRootCache->get_by_ID( $root, true );

		// fp> TODO: this block should move to a general level
		// Try to go to the right blog:
		if( $fm_FileRoot->type == 'collection' )
		{
			set_working_blog( $fm_FileRoot->in_type_ID );
			// Load the blog we're in:
			$Collection = $Blog = & $BlogCache->get_by_ID( $blog );
		}
		// ---

		if( empty( $Blog ) )
		{
			$Messages->add( TB_('No destination blog is selected.'), 'error' );
			break;
		}

		// Get status (includes PERM CHECK):
		$item_status = param( 'post_status', 'string', $Blog->get_allowed_item_status() );
		check_user_perm( 'blog_post!'.$item_status, 'create', true, $Blog->ID );

		load_class( 'files/model/_filelist.class.php', 'FileList' );
		$selected_Filelist = new Filelist( $fm_FileRoot, false );
		$fm_selected = param( 'fm_selected', 'array:filepath' );
		foreach( $fm_selected as $l_source_path )
		{
			$selected_Filelist->add_by_subpath( $l_source_path, true );
		}
		// make sure we have loaded metas for all files in selection!
		$selected_Filelist->load_meta();

		// Ready to create post(s):
		load_class( 'items/model/_item.class.php', 'Item' );

		$fileNum = 0;
		$cat_Array = param( 'category', 'array:string' );
		$title_Array = param( 'post_title', 'array:string' );
		$new_categories = param( 'new_categories', 'array:string', array() );
		while( $l_File = & $selected_Filelist->get_next() )
		{
			// Create a post:
			$edited_Item = new Item();
			$edited_Item->set( 'status', $item_status );

			// replacing category if selected at preview screen
			if( isset( $cat_Array[ $fileNum ] ) )
			{
				// checking if selected "same as above" category option
				switch( $cat_Array[ $fileNum ] )
				{
					case 'same':
						// Get a category ID from previous item
						$cat_Array[ $fileNum ] = $cat_Array[ $fileNum - 1 ];
						break;

					case 'new':
						// Create a new category from an entered name

						// Check permissions:
						check_user_perm( 'blog_cats', '', true, $blog );

						$ChapterCache = & get_ChapterCache();
						$new_Chapter = & $ChapterCache->new_obj( NULL, $blog );	// create new category object
						$new_Chapter->set( 'name', $new_categories[ $fileNum ] );
						if( $new_Chapter->dbinsert() !== false )
						{ // Category is created successfully
							$Messages->add_to_group( sprintf( TB_('New category %s created.'), '<b>'.$new_categories[ $fileNum ].'</b>' ), 'success', TB_('Creating posts:') );
							$ChapterCache->clear();
						}
						else
						{ // Error on creating new category
							$Messages->add( sprintf( TB_('New category %s creation failed.'), '<b>'.$new_categories[ $fileNum ].'</b>' ), 'error' );
							continue 2; // Skip this post
						}
						$cat_Array[ $fileNum ] = $new_Chapter->ID;
						break;
				}
				$edited_Item->set( 'main_cat_ID', intval( $cat_Array[ $fileNum ] ) );
			}
			else
			{ // Use default category ID if it was not selected on the form
				$edited_Item->set( 'main_cat_ID', $Blog->get_default_cat_ID() );
			}

			$title = $l_File->get('title');
			if( empty( $title ) )
			{
				$title = $l_File->get('name');
			}

			$edited_Item->set( 'title', $title );

			// replacing category if selected at preview screen
			if( isset( $title_Array[ $fileNum ] ) ) {
				$edited_Item->set( 'title', $title_Array[ $fileNum ] );
			}

			$DB->begin( 'SERIALIZABLE' );
			// INSERT NEW POST INTO DB:
			if( $edited_Item->dbinsert() )
			{
				// echo '<br>file meta: '.$l_File->meta;
				if( $l_File->meta == 'notfound' )
				{ // That file has no meta data yet, create it now!
					$l_File->dbsave();
				}

				// Let's make the link!
				$edited_Link = new Link();
				$edited_Link->set( 'itm_ID', $edited_Item->ID );
				$edited_Link->set( 'file_ID', $l_File->ID );
				$edited_Link->set( 'position', 'teaser' );
				$edited_Link->set( 'order', 1 );
				$edited_Link->dbinsert();

				$DB->commit();

				// Invalidate blog's media BlockCache
				BlockCache::invalidate_key( 'media_coll_ID', $edited_Item->get_blog_ID() );

				$Messages->add_to_group( sprintf( TB_('&laquo;%s&raquo; has been posted.'), $l_File->dget('name') ), 'success', TB_('Creating posts:') );
				$fileNum++;
			}
			else
			{
				$DB->rollback();
				$Messages->add( sprintf( TB_('&laquo;%s&raquo; couldn\'t be posted.'), $l_File->dget('name') ), 'error' );
			}
		}

		// Note: we redirect without restoring filter. This should allow to see the new files.
		// &filter=restore
		header_redirect( $admin_url.'?ctrl=items&blog='.$blog );	// Will save $Messages

		// Note: we should have EXITED here. In case we don't (error, or sth...)

		// Reset stuff so it doesn't interfere with upcomming display
		unset( $edited_Item );
		unset( $edited_Link );
		$selected_Filelist = new Filelist( $fm_Filelist->get_FileRoot(), false );
		break;

	case 'hide_quick_button':
	case 'show_quick_button':
		// Show/Hide quick button to publish a post

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Update setting:
		$UserSettings->set( 'show_quick_publish_'.$blog, ( $action == 'show_quick_button' ? 1 : 0 ) );
		$UserSettings->dbupdate();

		$prev_action = param( 'prev_action', 'string', '' );
		$item_ID = param( 'p', 'integer', 0 );

		// REDIRECT / EXIT
		header_redirect( $admin_url.'?ctrl=items&action='.$prev_action.( $item_ID > 0 ? '&p='.$item_ID : '' ).'&blog='.$blog );
		break;

	case 'reset_quick_settings':
		// Reset quick default settings for current user on the edit item screen:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Reset settings to default values
		$DB->query( 'DELETE FROM T_users__usersettings
			WHERE uset_name LIKE "fold_itemform_%_'.$blog.'"
			   OR uset_name = "show_quick_publish_'.$blog.'"' );

		$prev_action = param( 'prev_action', 'string', '' );
		$item_ID = param( 'p', 'integer', 0 );

		// REDIRECT / EXIT
		header_redirect( $admin_url.'?ctrl=items&action='.$prev_action.( $item_ID > 0 ? '&p='.$item_ID : '' ).'&blog='.$blog );
		break;

	case 'create_comments_post':
		// Create new post from selected comments:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'comments' );

		$item_ID = param( 'p', 'integer', 0 );
		$selected_comments = param( 'selected_comments', 'array:integer' );

		if( empty( $selected_comments ) )
		{	// If no comments selected:
			$Messages->add( TB_('Please select at least one comment.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $admin_url.'?ctrl=items&blog='.$blog.'&p='.$item_ID.'&comment_type=feedback#comments' );
		}

		// Check perm:
		check_user_perm( 'blog_post_statuses', 'edit', true, $blog );

		$new_post_creation_result = false;
		$CommentCache = & get_CommentCache();
		$moved_comments_IDs = array();
		$reattached_comments_IDs = array();
		foreach( $selected_comments as $s => $selected_comment_ID )
		{
			$selected_Comment = & $CommentCache->get_by_ID( $selected_comment_ID, false, false );
			if( ! $selected_Comment || $selected_Comment->item_ID != $item_ID )
			{	// Skip wrong comment:
				continue;
			}

			$moved_comments_IDs[] = $selected_Comment->ID;

			if( $s == 0 )
			{	// Create post from first comment:
				if( empty( $selected_Comment->author_user_ID ) )
				{	// Don't create a post from comment with anonymous user:
					$Messages->add( TB_('Could not create new post from comment without author.'), 'error' );
					break;
				}

				$comment_Item = & $selected_Comment->get_Item();

				// Use same chapters of the parent Item:
				$comment_item_chapters = $comment_Item->get_Chapters();
				$comment_item_chapters_IDs = array();
				foreach( $comment_item_chapters as $comment_item_Chapter )
				{
					$comment_item_chapters_IDs[] = $comment_item_Chapter->ID;
				}

				$new_Item = new Item();
				$new_Item->set( $new_Item->creator_field, $selected_Comment->author_user_ID );
				$new_Item->set( 'status', $comment_Item->status );
				$new_Item->set( 'main_cat_ID', $comment_Item->main_cat_ID );
				$new_Item->set( 'extra_cat_IDs', $comment_item_chapters_IDs );
				$new_Item->set( 'title', substr( sprintf( TB_('Branched from: %s'), $comment_Item->title ), 0, 255 ) );
				$new_Item->set( 'content', $selected_Comment->content );
				$new_Item->set( 'ityp_ID', $comment_Item->ityp_ID );
				$new_Item->set( 'renderers', $selected_Comment->get_renderers() );
				if( $new_Item->dbinsert() )
				{	// New post creation is success:
					$Messages->add( sprintf( TB_('New post has been created from comment #%d'), $selected_Comment->ID ), 'success' );
					$new_post_creation_result = true;
					// Move all links/attachments from old comment to new created post:
					$DB->query( 'UPDATE T_links
						  SET link_itm_ID = '.$new_Item->ID.', link_cmt_ID = NULL
						WHERE link_cmt_ID = '.$selected_Comment->ID );
					// Delete source comment after creating of new post:
					$selected_Comment->dbdelete( true );
				}
				else
				{	// New post creation is failed:
					$Messages->add( sprintf( TB_('Could not create new post from comment #%d'), $selected_Comment->ID ), 'error' );
					break;
				}
			}
			else
			{	// Append all next comments for new created post which has been created from first comment:
				$selected_Comment->set( 'item_ID', $new_Item->ID );
				// Set proper parent Comment if comment has been not moved to new Item:
				$selected_Comment->set_correct_parent_comment();
				// Update comment with new data:
				if( $selected_Comment->dbupdate() )
				{
					$reattached_comments_IDs[] = $selected_Comment->ID;
				}
			}
		}

		// Set proper parent Comment for all child comments if the parent comment has been moved to other new Item:
		foreach( $moved_comments_IDs as $moved_comments_ID )
		{
			$moved_Comment = & $CommentCache->get_by_ID( $moved_comments_ID );
			$child_comment_IDs = $moved_Comment->get_child_comment_IDs();
			foreach( $child_comment_IDs as $child_comment_ID )
			{
				$child_Comment = & $CommentCache->get_by_ID( $child_comment_ID );
				$old_in_reply_to_cmt_ID = $child_Comment->get( 'in_reply_to_cmt_ID' );
				$child_Comment->set_correct_parent_comment();
				if( $old_in_reply_to_cmt_ID != $child_Comment->get( 'in_reply_to_cmt_ID' ) )
				{	// Update only if parent comment has been really corrected:
					if( $child_Comment->dbupdate() )
					{
						$reattached_comments_IDs[] = $child_Comment->ID;
					}
				}
			}
		}

		if( count( $reattached_comments_IDs ) )
		{	// Display a message about the reattached comments:
			$Messages->add( sprintf( TB_('Comments #%s have been attached to new post.'), implode( ',', $reattached_comments_IDs ) ), 'success' );
		}

		// REDIRECT / EXIT
		header_redirect( $admin_url.'?ctrl=items&blog='.$blog.'&p='.( $new_post_creation_result ? $new_Item->ID : $item_ID.'&comment_type=feedback#comments' ) );
		break;

	case 'items_visibility':
		// Change visibility of selected items:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'items' );

		$selected_items = param( 'selected_items', 'array:integer' );
		$page = param( 'page', 'integer', 1 );
		$tab = param( 'tab', 'string', 'type' );
		$tab_type = param( 'tab_type', 'string', '' );

		// Set an URL to redirect to items list after this action:
		$redirect_to = param( 'redirect_to', 'url', NULL );

		if( empty( $redirect_to ) )
		{
			$redirect_to = $admin_url.'?ctrl=items&blog='.$blog.'&tab='.$tab.( $page > 1 ? '&items_'.$tab.'_paged='.$page : '' );
		}
		if( $tab == 'type' && ! empty( $tab_type ) )
		{
			$redirect_to .= '&tab_type='.$tab_type;
		}

		if( empty( $selected_items ) )
		{	// If no items selected:
			$Messages->add( TB_('Please select at least one item.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		$item_status = param( 'post_status', 'string' );
		$status_options = get_visibility_statuses();
		$item_status_title = isset( $status_options[ $item_status ] ) ? $status_options[ $item_status ] : $item_status;

		$ItemCache = & get_ItemCache();
		$items_success = 0;
		$items_restricted = array();
		$items_failed = 0;
		foreach( $selected_items as $selected_item_ID )
		{
			if( ( $selected_Item = & $ItemCache->get_by_ID( $selected_item_ID, false, false ) ) &&
			    check_user_perm( 'item_post!CURSTATUS', 'edit', false, $selected_Item ) )
			{	// If current User has a permission to edit the selected Item:
				$selected_Item->set( 'status', $item_status );
				if( $selected_Item->dbupdate() )
				{
					if( $item_status == $selected_Item->get( 'status' ) )
					{	// If the item has been updated to the requested status:
						$items_success++;
					}
					else
					{	// If the item could not be updated to the requested status because of restriction e.g. by post status:
						if( ! isset( $items_restricted[ $selected_Item->get( 'status' ) ] ) )
						{
							$items_restricted[ $selected_Item->get( 'status' ) ] = 0;
						}
						$items_restricted[ $selected_Item->get( 'status' ) ]++;
					}
					continue;
				}
			}
			// Wrong item or current User has no perm to edit the selected item:
			$items_failed++;
		}

		if( $items_success )
		{	// Inform about success updates:
			$Messages->add( sprintf( TB_('Visibility of %d items have been updated to %s.'), $items_success, $item_status_title ), 'success' );
		}
		foreach( $items_restricted as $restricted_status => $restricted_status_num )
		{	// Inform about restricted updates:
			$Messages->add( sprintf( TB_('Visibility of %d items have been restricted to %s.'), $restricted_status_num, isset( $status_options[ $restricted_status ] ) ? $status_options[ $restricted_status ] : $restricted_status ), 'note' );
		}
		if( $items_failed )
		{	// Inform about failed updates:
			$Messages->add( sprintf( TB_('Visibility of %d items could not be updated to %s.'), $items_failed, $item_status_title ), 'error' );
		}

		// REDIRECT / EXIT:
		header_redirect( $redirect_to );
		break;

	case 'mass_delete':
		// Delete selected items:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'items' );

		$selected_items = param( 'selected_items', 'array:integer' );
		$page = param( 'page', 'integer', 1 );
		$tab = param( 'tab', 'string', 'type' );
		$tab_type = param( 'tab_type', 'string', '' );
		$confirm = param( 'confirm', 'integer', 0 );

		// Set an URL to redirect to items list after this action:
		$redirect_to = $admin_url.'?ctrl=items&blog='.$blog.'&tab='.$tab.( $page > 1 ? '&items_'.$tab.'_paged='.$page : '' );
		if( $tab == 'type' && ! empty( $tab_type ) )
		{
			$redirect_to .= '&tab_type='.$tab_type;
		}

		if( empty( $selected_items ) )
		{	// If no items selected:
			$Messages->add( TB_('Please select at least one item.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		if( $confirm )
		{	// Mass deleting of the selected items after confirmation:
			$ItemCache = & get_ItemCache();
			$items_success = 0;
			$items_restricted = array();
			$items_failed = 0;
			foreach( $selected_items as $selected_item_ID )
			{
				if( ( $selected_Item = & $ItemCache->get_by_ID( $selected_item_ID, false, false ) ) &&
				    check_user_perm( 'item_post!CURSTATUS', 'delete', false, $selected_Item ) &&
				    $selected_Item->dbdelete() )
				{	// If current User has a permission to delete the selected Item:
					$items_success++;
				}
				else
				{	// Wrong item or current User has no perm to delete the selected item:
					$items_failed++;
				}
			}
			if( $items_success )
			{	// Inform about success deletes:
				$Messages->add( sprintf( TB_('%d items have been deleted.'), $items_success ), 'success' );
			}
			if( $items_failed )
			{	// Inform about failed updates:
				$Messages->add( sprintf( TB_('%d items could not be deleted.'), $items_failed ), 'error' );
			}
		}
		else
		{	// Redirect to page for confirmation of mass deleting items:
			$redirect_to .= '&confirm_action=mass_delete&selected_items='.implode( ',', $selected_items );
		}

		// REDIRECT / EXIT:
		header_redirect( $redirect_to );
		break;

	case 'comments_visibility':
		// Set visibility of selected comments:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'comments' );

		$item_ID = param( 'p', 'integer', 0 );
		$selected_comments = param( 'selected_comments', 'array:integer' );
		$page = param( 'page', 'integer', 1 );

		if( $item_ID > 0 )
		{	// Set an URL to redirect to item view details page after this action:
			$redirect_to = $admin_url.'?ctrl=items&blog='.$blog.'&p='.$item_ID.'&comment_type=feedback#comments';
		}
		else
		{	// Set an URL to redirect to comments list after this action:
			$redirect_to = $admin_url.'?ctrl=comments&blog='.$blog.( $page > 1 ? '&cmnt_fullview_paged='.$page : '' );
		}

		if( empty( $selected_comments ) )
		{	// If no comments selected:
			$Messages->add( TB_('Please select at least one comment.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		$comment_status = param( 'comment_status', 'string' );
		$status_options = get_visibility_statuses();
		$comment_status_title = isset( $status_options[ $comment_status ] ) ? $status_options[ $comment_status ] : $comment_status;

		$CommentCache = & get_CommentCache();
		$comments_success = 0;
		$comments_restricted = array();
		$comments_failed = 0;
		foreach( $selected_comments as $selected_comment_ID )
		{
			if( ( $selected_Comment = & $CommentCache->get_by_ID( $selected_comment_ID, false, false ) ) &&
			    check_user_perm( 'comment!CURSTATUS', 'moderate', false, $selected_Comment ) &&
			    check_user_perm( 'comment!'.$comment_status, 'moderate', false, $selected_Comment ) )
			{	// If current User has a permission to edit the selected Comment:
				$selected_Comment->set( 'status', $comment_status );
				if( $selected_Comment->dbupdate() )
				{
					if( $comment_status == $selected_Comment->get( 'status' ) )
					{	// If the comment has been updated to the requested status:
						$comments_success++;
					}
					else
					{	// If the comment could not be updated to the requested status because of restriction e.g. by post status:
						if( ! isset( $comments_restricted[ $selected_Comment->get( 'status' ) ] ) )
						{
							$comments_restricted[ $selected_Comment->get( 'status' ) ] = 0;
						}
						$comments_restricted[ $selected_Comment->get( 'status' ) ]++;
					}
					continue;
				}
			}
			// Wrong comment or current User has no perm to edit the selected comment:
			$comments_failed++;
		}

		if( $comments_success )
		{	// Inform about success updates:
			$Messages->add( sprintf( TB_('Visibility of %d comments have been updated to %s.'), $comments_success, $comment_status_title ), 'success' );
		}
		foreach( $comments_restricted as $restricted_status => $restricted_status_num )
		{	// Inform about restricted updates:
			$Messages->add( sprintf( TB_('Visibility of %d comments have been restricted to %s.'), $restricted_status_num, isset( $status_options[ $restricted_status ] ) ? $status_options[ $restricted_status ] : $restricted_status ), 'note' );
		}
		if( $comments_failed )
		{	// Inform about failed updates:
			$Messages->add( sprintf( TB_('Visibility of %d comments could not be updated to %s.'), $comments_failed, $comment_status_title ), 'error' );
		}

		// REDIRECT / EXIT:
		header_redirect( $redirect_to );
		break;

	case 'recycle_comments':
	case 'delete_comments':
		// Recycle/Delete the selected comments:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'comments' );

		$item_ID = param( 'p', 'integer', 0 );
		$selected_comments = param( 'selected_comments', 'array:integer' );

		if( $item_ID > 0 )
		{	// Set an URL to redirect to item view details page after this action:
			$redirect_to = $admin_url.'?ctrl=items&blog='.$blog.'&p='.$item_ID.'&comment_type=feedback#comments';
		}
		else
		{	// Set an URL to redirect to comments list after this action:
			$redirect_to = $admin_url.'?ctrl=comments&blog='.$blog;
		}

		if( empty( $selected_comments ) )
		{	// If no comments selected:
			$Messages->add( TB_('Please select at least one comment.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		// Force a permanent deletion for given action even if comments were not recycled:
		$force_permanent_delete = ( $action == 'delete_comments' );

		$CommentCache = & get_CommentCache();
		$comments_success_recycled = 0;
		$comments_success_deleted = 0;
		$comments_failed_recycled = 0;
		$comments_failed_deleted = 0;
		foreach( $selected_comments as $selected_comment_ID )
		{
			$comment_status = false;
			if( ( $selected_Comment = & $CommentCache->get_by_ID( $selected_comment_ID, false, false ) ) &&
			    check_user_perm( 'comment!CURSTATUS', 'delete', false, $selected_Comment ) )
			{	// If current User has a permission to recycle/delete the selected Comment:
				$comment_status = $selected_Comment->get( 'status' );
				if( $selected_Comment->dbdelete( $force_permanent_delete ) )
				{
					if( $force_permanent_delete || $comment_status == 'trash' )
					{	// If a selected comment has been deleted completely:
						$comments_success_deleted++;
					}
					else
					{	// If a selected comment has been moved to recycle bin:
						$comments_success_recycled++;
					}
					continue;
				}
			}
			// Wrong comment or current User has no perm to delete the selected comment:
			if( $force_permanent_delete || $comment_status == 'trash' )
			{	// If a selected comment has NOT been deleted completely:
				$comments_failed_deleted++;
			}
			else
			{	// If a selected comment has NOT been moved to recycle bin:
				$comments_failed_recycled++;
			}
		}

		if( $comments_success_recycled )
		{	// Inform about success recycling:
			$Messages->add( sprintf( TB_('%d comments have been recycled.'), $comments_success_recycled ), 'success' );
		}
		if( $comments_success_deleted )
		{	// Inform about success deleted:
			$Messages->add( sprintf( TB_('%d comments have been deleted.'), $comments_success_deleted ), 'success' );
		}
		if( $comments_failed_recycled )
		{	// Inform about failed deletions:
			$Messages->add( sprintf( TB_('%d comments could not be recycled.'), $comments_failed_recycled ), 'error' );
		}
		if( $comments_failed_deleted )
		{	// Inform about failed deletions:
			$Messages->add( sprintf( TB_('%d comments could not be deleted.'), $comments_failed_deleted ), 'error' );
		}

		// REDIRECT / EXIT:
		header_redirect( $redirect_to );
		break;

	case 'mass_change_cat':
		// Mass change main category or add extra categories of selected items:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'items' );

		$selected_items = param( 'selected_items', 'array:integer' );
		$cat_type = param( 'cat_type', 'string' );

		// Set an URL to redirect to items list after this action:
		$redirect_to = param( 'redirect_to', 'url', NULL );

		if( empty( $selected_items ) )
		{	// If no items selected:
			$Messages->add( TB_('Please select at least one item.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		$ChapterCache = & get_ChapterCache();
		if( $cat_type == 'main' )
		{	// Get a selected main category:
			$main_cat_ID = param( 'post_category', 'integer', true );
			$main_Chapter = & $ChapterCache->get_by_ID( $main_cat_ID );
		}
		else
		{	// Get the selected extra categories:
			$extra_categories = param( 'post_extracats', 'array:integer' );
		}

		if( empty( $main_cat_ID ) && empty( $extra_categories ) )
		{	// If no categories selected:
			$Messages->add( TB_('Please select a category.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		$ItemCache = & get_ItemCache();
		$items_success = 0;
		$items_failed = 0;
		foreach( $selected_items as $selected_item_ID )
		{
			if( ( $selected_Item = & $ItemCache->get_by_ID( $selected_item_ID, false, false ) ) &&
			    check_user_perm( 'item_post!CURSTATUS', 'edit', false, $selected_Item ) )
			{	// If current User has a permission to edit the selected Item:
				$current_extra_categories = postcats_get_byID( $selected_Item->ID );
				if( $cat_type == 'main' )
				{	// Change main category:
					$selected_Item->set( 'main_cat_ID', $main_cat_ID );
					// Don't lose current extra categories:
					$selected_Item->set( 'extra_cat_IDs', $current_extra_categories );
				}
				elseif( $cat_type == 'extra' )
				{	// Add extra categories to previous linked categories:
					$selected_Item->set( 'extra_cat_IDs', array_unique( array_merge( $current_extra_categories, $extra_categories ) ) );
				}
				elseif( $cat_type == 'remove_extra' )
				{	// Remove extra categories from previous linked categories except if an extra category is also the primary category:
					$main_cat_ID = $selected_Item->get( 'main_cat_ID' );
					$remove_extra_categories = $extra_categories;
					if( ( $key = array_search( $main_cat_ID, $remove_extra_categories ) ) !== false )
					{
						unset( $remove_extra_categories[$key] );
					}
					
					if( empty( $remove_extra_categories ) )
					{	// Nothing to remove, skip to next Item:
						continue;
					}

					$selected_Item->set( 'extra_cat_IDs', array_diff( $current_extra_categories, $remove_extra_categories ) );
				}
				if( $selected_Item->dbupdate() )
				{	// If the item has been updated to the requested categories:
					$items_success++;
					continue;
				}
			}
			// Wrong item or current User has no perm to edit the selected item:
			$items_failed++;
		}

		if( $cat_type == 'main' )
		{	// Report about changed main category:
			if( $items_success )
			{	// Inform about success updates:
				$Messages->add( sprintf( TB_('Main category of %d items have been changed to %s.'), $items_success, '"'.$main_Chapter->get( 'name' ).'"' ), 'success' );
			}
			if( $items_failed )
			{	// Inform about failed updates:
				$Messages->add( sprintf( TB_('Main category of %d items could not be changed to %s.'), $items_failed, '"'.$main_Chapter->get( 'name' ).'"' ), 'error' );
			}
		}
		elseif( $cat_type == 'extra' )
		{	// Report about added extra categories:
			$extra_cats_names = array();
			foreach( $extra_categories as $extra_cat_ID )
			{
				if( $extra_Chapter = & $ChapterCache->get_by_ID( $extra_cat_ID, false, false ) )
				{
					$extra_cats_names[] = '"'.$extra_Chapter->get( 'name' ).'"';
				}
			}
			if( $items_success )
			{	// Inform about success updates:
				$Messages->add( sprintf( TB_('Extra categories %s of %d items have been added.'), implode( ', ', $extra_cats_names ), $items_success ), 'success' );
			}
			if( $items_failed )
			{	// Inform about failed updates:
				$Messages->add( sprintf( TB_('Extra categories %s of %d items could not be added.'), implode( ', ', $extra_cats_names ), $items_failed ), 'error' );
			}
		}
		elseif( $cat_type == 'remove_extra' )
		{	// Report about removed extra categories:
			$extra_cats_names = array();
			foreach( $extra_categories as $extra_cat_ID )
			{
				if( $extra_Chapter = & $ChapterCache->get_by_ID( $extra_cat_ID, false, false ) )
				{
					$extra_cats_names[] = '"'.$extra_Chapter->get( 'name' ).'"';
				}
			}
			if( $items_success )
			{	// Inform about success updates:
				$Messages->add( sprintf( TB_('Extra categories %s of %d items have been removed.'), implode( ', ', $extra_cats_names ), $items_success ), 'success' );
			}
			if( $items_failed )
			{	// Inform about failed updates:
				$Messages->add( sprintf( TB_('Extra categories %s of %d items could not be removed.'), implode( ', ', $extra_cats_names ), $items_failed ), 'error' );
			}
		}

		// REDIRECT / EXIT:
		header_redirect( $redirect_to );
		break;

	case 'mass_change_renderer':
		// Mass change renderers of selected items:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'items' );

		$selected_items = param( 'selected_items', 'array:integer' );
		$renderer_change_type = param( 'renderer_change_type', 'string' );

		// Set an URL to redirect to items list after this action:
		$redirect_to = param( 'redirect_to', 'url', NULL );

		if( empty( $selected_items ) )
		{	// If no items selected:
			$Messages->add( TB_('Please select at least one item.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		// Get the selected renderers:
		$renderers = param( 'renderers', 'array:string' );

		if( empty( $renderers ) )
		{	// If no categories selected:
			$Messages->add( TB_('Please select a renderer.'), 'error' );
			// REDIRECT / EXIT:
			header_redirect( $redirect_to );
		}

		$ItemCache = & get_ItemCache();
		$items_success = 0;
		$items_failed = 0;
		foreach( $selected_items as $selected_item_ID )
		{
			if( ( $selected_Item = & $ItemCache->get_by_ID( $selected_item_ID, false, false ) ) &&
			    check_user_perm( 'item_post!CURSTATUS', 'edit', false, $selected_Item ) )
			{	// If current User has a permission to edit the selected Item:
				if( $renderer_change_type == 'add_renderer' )
				{
					foreach( $renderers as $renderer )
					{
						$selected_Item->add_renderer( $renderer );
					}
				}
				elseif( $renderer_change_type == 'remove_renderer' )
				{
					foreach( $renderers as $renderer )
					{
						$selected_Item->remove_renderer( $renderer );
					}
				}

				// In any case, remove 'default' renderer:
				$selected_Item->remove_renderer( 'default' );
				
				if( $selected_Item->dbupdate() )
				{	// If the item has been updated with the requested renderers:
					$items_success++;
					continue;
				}
			}
			// Wrong item or current User has no perm to edit the selected item:
			$items_failed++;
		}

		global $Plugins;

		if( $renderer_change_type == 'add_renderer' )
		{	// Report about added renderers:
			$renderer_names = array();
			foreach( $renderers as $code )
			{
				if( $renderer_Plugin = & $Plugins->get_by_code( $code	) )
				{
					$renderer_names[] = '"'.$renderer_Plugin->name.'"';
				}
			}
			if( $items_success )
			{	// Inform about success updates:
				$Messages->add( sprintf( TB_('Renderers %s of %d items have been added.'), implode( ', ', $renderer_names ), $items_success ), 'success' );
			}
			if( $items_failed )
			{	// Inform about failed updates:
				$Messages->add( sprintf( TB_('Renderers %s of %d items could not be added.'), implode( ', ', $renderer_names ), $items_failed ), 'error' );
			}
		}
		elseif( $renderer_change_type == 'remove_renderer' )
		{	// Report about removed extra categories:
			$renderer_names = array();
			foreach( $renderers as $code )
			{
				if( $renderer_Plugin = & $Plugins->get_by_code( $code ) )
				{
					$renderer_names[] = '"'.$renderer_Plugin->name.'"';
				}
			}
			if( $items_success )
			{	// Inform about success updates:
				$Messages->add( sprintf( TB_('Renderers %s of %d items have been removed.'), implode( ', ', $renderer_names ), $items_success ), 'success' );
			}
			if( $items_failed )
			{	// Inform about failed updates:
				$Messages->add( sprintf( TB_('Renderers %s of %d items could not be removed.'), implode( ', ', $renderer_names ), $items_failed ), 'error' );
			}
		}

		// REDIRECT / EXIT:
		header_redirect( $redirect_to );
		break;

	default:
		// Try to handle action by modules:
		$module_result = modules_call_method( 'handle_backoffice_action', array(
				'ctrl'        => 'items',
				'action'      => $action,
				'action_type' => 'action1',
			) );
		if( $module_result === NULL )
		{	// Deny wrong action if it is not handled by any module:
			debug_die( 'unhandled action 1:'.htmlspecialchars($action) );
		}
}

$AdminUI->breadcrumbpath_init( true, array( 'text' => TB_('Collections'), 'url' => $admin_url.'?ctrl=collections' ) );
$AdminUI->breadcrumbpath_add( TB_('Posts'), $admin_url.'?ctrl=items&amp;blog=$blog$&amp;tab=full&amp;filter=restore' );

/**
 * Perform action:
 */
switch( $action )
{
	case 'nil':
		// Do nothing
		break;

	case 'new':
	case 'new_mass':
		// $set_issue_date = 'now';
		$item_issue_date = date_i18n( locale_datefmt(), $localtimenow );
		$item_issue_time = date( 'H:i:s', $localtimenow );
		// pre_dump( $item_issue_date, $item_issue_time );
	case 'new_switchtab': // this gets set as action by JS, when we switch tabs
	case 'new_type': // this gets set as action by JS, when we switch tabs
		// New post form  (can be a bookmarklet form if mode == bookmarklet )

		// We don't check the following earlier, because we want the blog switching buttons to be available:
		if( ! blog_has_cats( $blog ) )
		{
			break;
		}

		// Used when we change a type of the duplicated item:
		$duplicated_item_ID = param( 'p', 'integer', NULL );

		if( in_array( $action, array( 'new', 'new_type' ) ) )
		{
			param( 'restore', 'integer', 0 );
			if( $restore || $action == 'new_type' )
			{ // Load post from Session
				$edited_Item = get_session_Item( 0 );

				// Memorize action for list of post types
				memorize_param( 'action', 'string', '', $action );
			}
		}

		load_class( 'items/model/_item.class.php', 'Item' );
		if( empty( $edited_Item ) )
		{ // Create new Item object
			$edited_Item = new Item();
			// Prefill data from url:
			$edited_Item->set( 'title', param( 'post_title', 'string' ) );
			$edited_Item->set( 'urltitle', param( 'post_urltitle', 'string' ) );
		}

		$edited_Item->set('main_cat_ID', $Blog->get_default_cat_ID());

		// We use the request variables to fill the edit form, because we need to be able to pass those values
		// from tab to tab via javascript when the editor wants to switch views...
		// Also used by bookmarklet
		$edited_Item->load_from_Request( true ); // needs Blog set

		// Set default locations from current user
		$edited_Item->set_creator_location( 'country' );
		$edited_Item->set_creator_location( 'region' );
		$edited_Item->set_creator_location( 'subregion' );
		$edited_Item->set_creator_location( 'city' );

		$edited_Item->status = param( 'post_status', 'string', $Blog->get_setting( 'default_post_status' ) );		// 'published' or 'draft' or ...
		// We know we can use at least one status,
		// but we need to make sure the requested/default one is ok:
		$edited_Item->status = $Blog->get_allowed_item_status( $edited_Item->status, $edited_Item );

		// Check if new category was started to create. If yes then set up parameters for next page:
		check_categories_nosave( $post_category, $post_extracats, $edited_Item, ( $action == 'new_switchtab' ? 'frontoffice' : 'backoffice' ) );

		$edited_Item->set ( 'main_cat_ID', $post_category );
		if( $edited_Item->main_cat_ID && ( get_allow_cross_posting() < 2 ) && $edited_Item->get_blog_ID() != $blog )
		{ // the main cat is not in the list of categories; this happens, if the user switches blogs during editing:
			$edited_Item->set('main_cat_ID', $Blog->get_default_cat_ID());
		}
		$post_extracats = param( 'post_extracats', 'array:integer', $post_extracats );
		$edited_Item->set( 'extra_cat_IDs', $post_extracats );

		param( 'item_tags', 'string', '' );

		// Trackback addresses (never saved into item)
		param( 'trackback_url', 'string', '' );

		// Item type ID:
		param( 'item_typ_ID', 'integer', 1 );

		// Initialize a page title depending on item type:
		if( empty( $item_typ_ID ) )
		{	// No selected item type, use default:
			$title = TB_('New post');
		}
		else
		{	// Get item type to set a pge title:
			$ItemTypeCache = & get_ItemTypeCache();
			$ItemType = & $ItemTypeCache->get_by_ID( $item_typ_ID );
			$title = sprintf( TB_('New [%s]'), $ItemType->get_name() );
		}

		$AdminUI->breadcrumbpath_add( $title, '?ctrl=items&amp;action=new&amp;blog='.$Blog->ID.'&amp;item_typ_ID='.$item_typ_ID );

		$AdminUI->title_titlearea = $title.': ';;

		// Params we need for tab switching:
		$tab_switch_params = 'blog='.$blog;

		if( $action == 'new_type' )
		{	// Save the changes of Item to Session:
			set_session_Item( $edited_Item );
			// Initialize original item ID that is used on diplicating action:
			param( 'p', 'integer', NULL );
		}
		break;


	case 'copy': // Duplicate post
	case 'new_version': // Add version
		$item_ID = param( 'p', 'integer', true );
		$ItemCache = &get_ItemCache();
		$edited_Item = & $ItemCache->get_by_ID( $item_ID );

		// Load tags of the duplicated item:
		$item_tags = implode( ', ', $edited_Item->get_tags() );

		// Load all settings of the duplicating item and copy them to new item:
		$edited_Item->load_ItemSettings();
		$edited_Item->ItemSettings->_load( $edited_Item->ID, NULL );
		$edited_Item->ItemSettings->cache[0] = $edited_Item->ItemSettings->cache[ $edited_Item->ID ];

		// Load all custom fields:
		$edited_Item->get_custom_fields_defs();

		// Set parent item ID to find category order
		$edited_Item->set( 'parent_item_ID', $edited_Item->ID );

		// Set ID of copied post to 0, because some functions can update current post, e.g. $edited_Item->get( 'excerpt' )
		$edited_Item->ID = 0;

		// Change creator user to current user for correct permission checking:
		$edited_Item->set( 'creator_user_ID', $current_User->ID );

		$edited_Item->load_Blog();
		$item_status = $edited_Item->Blog->get_allowed_item_status();

		$edited_Item->set( 'status', $item_status );
		$edited_Item->set( 'dateset', 0 );	// Date not explicitly set yet
		$edited_Item->set( 'issue_date', date( 'Y-m-d H:i:s', $localtimenow ) );

		if( $action == 'new_version' )
		{	// Creating new version
			if( param( 'post_locale', 'string', NULL ) !== NULL )
			{	// Set locale:
				$edited_Item->set_from_Request( 'locale' );
			}

			if( param( 'post_create_child', 'integer', NULL ) === 1 )
			{	// Set parent Item:
				$edited_Item->set( 'parent_ID', $item_ID );
			}

			// Duplicate same images depending on setting from modal window:
			$duplicate_same_images = param( 'post_same_images', 'integer', NULL );

			if( param( 'post_coll_ID', 'integer', NULL ) !== NULL &&
			    $post_coll_ID != $edited_Item->get_blog_ID() )
			{	// Create Item in different collection:
				$BlogCache = & get_BlogCache();
				$linked_Blog = $BlogCache->get_by_ID( $post_coll_ID, false, false );
				if( ! check_user_perm( 'blog_post_statuses', 'edit', false, $post_coll_ID ) )
				{	// If current User cannot create an Item in the selected locale collection,
					// Redirect back to edit Item form:
					$Messages->add( sprintf( TB_('You don\'t have a permission to create new Item in the collection "%s"!'), $linked_Blog ? $linked_Blog->get( 'name' ) : '#'.$post_coll_ID ) );
					header_redirect( $admin_url.'?ctrl=items&blog='.$edited_Item->get_blog_ID().'&action=edit&p='.$p );
					// Exit here.
				}
				// Reset Item collection to new selected:
				set_working_blog( $post_coll_ID );
				unset( $edited_Item->Blog );
				// Set default category in different selected collection:
				$edited_Item->set( 'main_cat_ID', $linked_Blog->get_default_cat_ID() );
				$post_extracats = array( $edited_Item->get( 'main_cat_ID' ) );
			}

			if( param( 'item_typ_ID', 'integer', NULL ) !== NULL )
			{	// Set Item Type:
				if( ! $edited_Item->get_Blog()->is_item_type_enabled( $item_typ_ID ) )
				{	// Use default Item Type if it is NOT enabled for the selected collection:
					$default_ItemType = & $edited_Item->get_Blog()->get_default_new_ItemType();
					$item_typ_ID = $default_ItemType->ID;
				}
				$edited_Item->set( 'ityp_ID', $item_typ_ID );
			}
		}
		else
		{	// Always duplicate images on action=copy:
			$duplicate_same_images = true;
		}

		// Set post comment status and extracats
		$post_comment_status = $edited_Item->get( 'comment_status' );
		if( ! isset( $post_extracats ) )
		{
			$post_extracats = postcats_get_byID( $p );
		}

		// Check if new category was started to create. If yes then set up parameters for next page:
		check_categories_nosave( $post_category, $post_extracats, $edited_Item );

		if( $duplicate_same_images )
		{	// Duplicate attachments from source Item:
			$edited_Item->duplicate_attachments( $item_ID );
		}

		// Initialize a page title depending on item type:
		$ItemTypeCache = & get_ItemTypeCache();
		$ItemType = & $ItemTypeCache->get_by_ID( $edited_Item->ityp_ID );
		$title = sprintf( TB_('Duplicate %s'), $ItemType->get_name() );

		$AdminUI->breadcrumbpath_add( $title, '?ctrl=items&amp;action=copy&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID );

		$AdminUI->title_titlearea = $title.': ';

		// Params we need for tab switching:
		$tab_switch_params = 'blog='.$blog;
		break;


	case 'edit_switchtab': // this gets set as action by JS, when we switch tabs
	case 'edit_type': // this gets set as action by JS, when we switch tabs
		// This is somewhat in between new and edit...

		// Check permission based on DB status:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		$edited_Item->status = param( 'post_status', 'string', NULL );		// 'published' or 'draft' or ...
		// We know we can use at least one status,
		// but we need to make sure the requested/default one is ok:
		$edited_Item->status = $Blog->get_allowed_item_status( $edited_Item->status, $edited_Item );

		param( 'load_from_request', 'integer', 1 );
		param( 'from_tab', 'string', NULL );

		if( $load_from_request && !( $action == 'edit_type' && $from_tab == 'type' ) )
		{	// We use the request variables to fill the edit form, because we need to be able to pass those values
			// from tab to tab via javascript when the editor wants to switch views...
			// ...except when we are changing the Item Type from a post list where no request variables are available for the Item
			$edited_Item->load_from_Request( true ); // needs Blog set
		}

		// Check if new category was started to create. If yes then set up parameters for next page:
		check_categories_nosave( $post_category, $post_extracats,$edited_Item, ( $action == 'edit_switchtab' ? 'frontoffice' : 'backoffice' ) );

		$edited_Item->set( 'main_cat_ID', $post_category );
		if( $edited_Item->main_cat_ID && ( get_allow_cross_posting() < 2 ) && $edited_Item->get_blog_ID() != $blog )
		{ // the main cat is not in the list of categories; this happens, if the user switches blogs during editing:
			$edited_Item->set('main_cat_ID', $Blog->get_default_cat_ID());
		}
		$post_extracats = param( 'post_extracats', 'array:integer', $post_extracats );
		$edited_Item->set( 'extra_cat_IDs', $post_extracats );

		param( 'item_tags', 'string', '' );

		// Trackback addresses (never saved into item)
		param( 'trackback_url', 'string', '' );

		// Page title:
		$AdminUI->title_titlearea = sprintf( TB_('Editing post #%d: %s'), $edited_Item->ID, $Blog->get('name') );

		$AdminUI->breadcrumbpath_add( sprintf( /* TRANS: noun */ TB_('Post').' #%s', $edited_Item->ID ), '?ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID );
		$AdminUI->breadcrumbpath_add( TB_('Edit'), '?ctrl=items&amp;action=edit&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID );

		// Params we need for tab switching:
		$tab_switch_params = 'p='.$edited_Item->ID;

		if( $action == 'edit_type' )
		{ // Save the changes of Item to Session
			set_session_Item( $edited_Item );
		}
		break;

	case 'history':
		// Check permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );
		break;

	case 'history_lastseen':
		// Check permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		$SQL = new SQL( 'Find last not seen revision of the Item #'.$edited_Item->ID.' by current User' );
		$SQL->SELECT( 'iver_ID' );
		$SQL->FROM( 'T_items__version' );
		$SQL->WHERE( 'iver_itm_ID = '.$DB->quote( $edited_Item->ID ) );
		$SQL->WHERE_and( 'iver_type = "archived"' );
		$SQL->WHERE_and( 'iver_edit_last_touched_ts <= '.$DB->quote( $edited_Item->get_user_data( 'item_date' ) ) );
		$SQL->ORDER_BY( 'iver_edit_last_touched_ts DESC' );
		$SQL->LIMIT( '1' );
		$lastnotseen_revision_ID = $DB->get_var( $SQL );

		if( $lastnotseen_revision_ID > 0 && $edited_Item->get_user_data( 'item_date' ) < $edited_Item->get( 'last_touched_ts' ) )
		{	// Redirect to compare last not seen revision with current version:
			header_redirect( $admin_url.'?ctrl=items&action=history_compare&p='.$edited_Item->ID.'&r1=a'.$lastnotseen_revision_ID.'&r2=c' );
		}
		else
		{	// Redirect to view current version because User have already seen all changes before:
			$Messages->add( TB_('You have already seen all changes of this Item.'), 'note' );
			header_redirect( $admin_url.'?ctrl=items&action=history_details&p='.$edited_Item->ID.'&r=c' );
		}
		break;

	case 'history_details':
		// Check permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		$Revision = $edited_Item->get_revision( param( 'r', 'string' ) );

		if( ! $Revision )
		{	// Redirect to history list on wrong requested revision:
			if( substr( get_param( 'r' ), 0, 1 ) == 'p' )
			{	// When view old(not existing) proposed change:
				$Messages->add( TB_('The changes have already been accepted or rejected.'), 'error' );
			}
			else
			{	// When view archived version:
				// Don't translate because it should not happens on normal work:
				$Messages->add( 'The requested version does not exist.', 'error' );
			}
			header_redirect( $admin_url.'?ctrl=items&action=history&p='.$edited_Item->ID );
		}
		break;

	case 'history_compare':
		// Check permission:
		if( ! check_user_perm( 'item_post!CURSTATUS', 'edit', false, $edited_Item ) )
		{
			$Messages->add( TB_('You have no permission to view history for this item.'), 'error' );
			header_redirect( $admin_url );
		}

		$Revision_1 = $edited_Item->get_revision( param( 'r1', 'string' ) );
		$Revision_2 = $edited_Item->get_revision( param( 'r2', 'string' ) );

		if( ! $Revision_1 || ! $Revision_2 )
		{	// Redirect to history list on wrong requested revision:
			if( substr( get_param( 'r1' ), 0, 1 ) == 'c' && substr( get_param( 'r2' ), 0, 1 ) == 'p' )
			{	// When compare current version with old(not existing) proposed change(e.g. on opening url from old email message):
				$Messages->add( TB_('The changes have already been accepted or rejected.'), 'error' );
			}
			else
			{	// When compare all other cases:
				// Don't translate because it should not happens on normal work:
				$Messages->add( 'The requested version does not exist.', 'error' );
			}
			header_redirect( $admin_url.'?ctrl=items&action=history&p='.$edited_Item->ID );
		}

		load_class( '_core/model/_diff.class.php', 'Diff' );

		// Compare the titles of two revisions:
		$revisions_difference_title = new Diff( explode( "\n", $Revision_1->iver_title ), explode( "\n", $Revision_2->iver_title ) );
		$TitleDiffFormatter = new TitleDiffFormatter();
		$revisions_difference_title = $TitleDiffFormatter->format( $revisions_difference_title );

		// Compare the contents of two revisions:
		$revisions_difference_content = new Diff( explode( "\n", $Revision_1->iver_content ), explode( "\n", $Revision_2->iver_content ) );
		$TableDiffFormatter = new TableDiffFormatter();
		$revisions_difference_content = $TableDiffFormatter->format( $revisions_difference_content );

		// Compare the custom fields of two revisions:
		$oneline_TableDiffFormatter = new TableDiffFormatter();
		$oneline_TableDiffFormatter->block_header = '';
		// Switch to 1st revision:
		$edited_Item->set( 'revision', get_param( 'r1' ) );
		$custom_fields = $edited_Item->get_type_custom_fields();
		$r1_custom_fields = array();
		foreach( $custom_fields as $custom_field )
		{
			$r1_custom_fields[ $custom_field['name'] ] = $edited_Item->get_custom_field_value( $custom_field['name'] );
		}
		// Switch to 2nd revision:
		$edited_Item->set( 'revision', get_param( 'r2' ) );
		$r2_custom_fields = $edited_Item->get_type_custom_fields();
		foreach( $r2_custom_fields as $r2_custom_field )
		{
			if( ! isset( $custom_fields[ $r2_custom_field['name'] ] ) )
			{	// Append custom fields which don't exist in 1st revision but exist in 2nd revision:
				$custom_fields[ $r2_custom_field['name'] ] = $r2_custom_field;
			}
		}
		$revisions_difference_custom_fields = empty( $custom_fields ) ? false : array(); // FALSE means Item has no custom fields
		foreach( $custom_fields as $custom_field )
		{
			// Get custom field values of both revisions:
			$r1_custom_field_value = isset( $r1_custom_fields[ $custom_field['name'] ] ) ? $r1_custom_fields[ $custom_field['name'] ] : false;
			$r2_custom_field_value = $edited_Item->get_custom_field_value( $custom_field['name'], false, false );
			$revisions_difference_custom_field = '';
			if( $r1_custom_field_value != $r2_custom_field_value )
			{	// If values are different:
				if( $r1_custom_field_value !== false &&
				    $r2_custom_field_value !== false )
				{	// Compare custom field values of 1st and 2nd revisions if they are used for both revisions:
					$revisions_difference_custom_field = new Diff(
							explode( "\n", $r1_custom_field_value ),
							explode( "\n", $r2_custom_field_value )
						);
					if( $custom_field['type'] == 'html' || $custom_field['type'] == 'text' )
					{	// Display a line number for custom fields with multiple lines:
						$revisions_difference_custom_field = $TableDiffFormatter->format( $revisions_difference_custom_field );
					}
					else
					{	// Don't display a line number for custom fields with single line:
						$revisions_difference_custom_field = $oneline_TableDiffFormatter->format( $revisions_difference_custom_field );
					}
				}
				elseif( $r1_custom_field_value !== false ||
				        $r2_custom_field_value !== false)
				{	// Don't compare custom field values if at least one is not used in revision but however they are different:
					$revisions_difference_custom_field = NULL;
				}
			}

			if( $revisions_difference_custom_field !== '' )
			{	// Display custom fields only with differences:
				$revision_custom_fields = array(
						'r1_label' => $custom_field['label'],
						'r2_label' => isset( $r2_custom_fields[ $custom_field['name'] ] ) ? $r2_custom_fields[ $custom_field['name'] ]['label'] : $custom_field['label'],
					);
				if( $revisions_difference_custom_field === NULL )
				{	// Store field value instead of difference if one field is not used in some revision:
					if( $r1_custom_field_value !== false )
					{	// If only old revision has the field:
						$revision_custom_fields['r1_value'] = $r1_custom_field_value;
					}
					else
					{	// If only new revision has the field:
						$revision_custom_fields['r2_value'] = $r2_custom_field_value;
					}
				}
				else
				{	// Store a difference if field is used in both revisions:
					$revision_custom_fields['diff_value'] = $revisions_difference_custom_field;
				}
				if( $revision_custom_fields['r1_label'] != $revision_custom_fields['r2_label'] )
				{	// Compare the labels of custom fields:
					$revisions_difference_label = new Diff( explode( "\n", $revision_custom_fields['r1_label'].':' ), explode( "\n", $revision_custom_fields['r2_label'].':' ) );
					$TitleDiffFormatter = new TitleDiffFormatter();
					$revisions_difference_label = $TitleDiffFormatter->format( $revisions_difference_label );
					if( ! empty( $revisions_difference_label ) )
					{
						$revision_custom_fields['diff_label'] = preg_replace( '/^<tr/', '<tr class="diff-custom-field"', $revisions_difference_label );
					}
				}
				$revisions_difference_custom_fields[] = $revision_custom_fields;
			}
		}

		// Compare the links of two revisions:
		$LinkOwner = new LinkItem( $edited_Item );
		// Switch to 1st revision:
		$edited_Item->set( 'revision', get_param( 'r1' ) );
		$revisions_difference_links = false; // FALSE means Item has no attached files
		if( $r1_LinkList = $LinkOwner->get_attachment_LinkList() )
		{
			$revisions_difference_links = array();
			while( $r1_Link = & $r1_LinkList->get_next() )
			{
				$revisions_difference_links[ $r1_Link->ID ]['r1'] = array(
						'icon'     => $r1_Link->get_preview_thumb(),
						'path'     => ( $r1_link_File = & $r1_Link->get_File() ? $r1_link_File->get_view_link() : false ),
						'order'    => $r1_Link->get( 'order' ),
						'position' => $r1_Link->get( 'position' ),
						'file_ID'  => $r1_Link->get( 'file_ID' ),
					);
			}
		}
		// Switch to 2nd revision:
		$edited_Item->set( 'revision', get_param( 'r2' ) );
		if( $r2_LinkList = $LinkOwner->get_attachment_LinkList() )
		{
			if( ! is_array( $revisions_difference_links ) )
			{
				$revisions_difference_links = array();
			}
			while( $r2_Link = & $r2_LinkList->get_next() )
			{
				$r_link_data = array(
						'icon'     => $r2_Link->get_preview_thumb(),
						'path'     => ( $r2_link_File = & $r2_Link->get_File() ? $r2_link_File->get_view_link() : false ),
						'order'    => $r2_Link->get( 'order' ),
						'position' => $r2_Link->get( 'position' ),
						'file_ID'  => $r2_Link->get( 'file_ID' ),
					);
				if( isset( $revisions_difference_links[ $r2_Link->ID ]['r1'] ) && $revisions_difference_links[ $r2_Link->ID ]['r1'] == $r_link_data )
				{	// The links/attachments of both revisions are equal:
					unset( $revisions_difference_links[ $r2_Link->ID ] );
				}
				else
				{	// The links/attachments of both revisions have at least one difference:
					$revisions_difference_links[ $r2_Link->ID ]['r2'] = $r_link_data;
				}
			}
		}

		// Clear revision in order to display current data on the form:
		$edited_Item->clear_revision();
		break;

	case 'history_restore':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		param( 'r', 'integer', 0 );

		if( $r > 0 && $edited_Item->update_from_revision( $r ) )
		{	// Update item only from revisions ($r == 0 for current version):
			$Messages->add( sprintf( TB_('Item has been restored from revision #%s'), $r ), 'success' );
		}

		header_redirect( regenerate_url( 'action', 'action=history', '', '&' ) );
		break;

	case 'edit':
		// Check permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		// Restrict Item status by Collection access restriction AND by CURRENT USER write perm:
		$edited_Item->restrict_status();

		$post_comment_status = $edited_Item->get( 'comment_status' );
		$post_extracats = postcats_get_byID( $p ); // NOTE: dh> using $edited_Item->get_Chapters here instead fails (empty list, since no postIDlist).

		$item_tags = implode( ', ', $edited_Item->get_tags() );
		$trackback_url = '';

		// Page title:
		$AdminUI->title_titlearea = sprintf( TB_('Editing post #%d: %s'), $edited_Item->ID, $Blog->get('name') );

		$AdminUI->breadcrumbpath_add( sprintf( /* TRANS: noun */ TB_('Post').' #%s', $edited_Item->ID ), '?ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID );
		$AdminUI->breadcrumbpath_add( TB_('Edit'), '?ctrl=items&amp;action=edit&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID );

		// Params we need for tab switching:
		$tab_switch_params = 'p='.$edited_Item->ID;
		break;

	case 'propose':
		// Check permission:
		check_user_perm( 'blog_item_propose', 'edit', true, $Blog->ID );

		$AdminUI->breadcrumbpath_add( sprintf( /* TRANS: noun */ TB_('Post').' #%s', $edited_Item->ID ), '?ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID );
		$AdminUI->breadcrumbpath_add( TB_('Propose change'), '?ctrl=items&amp;action=propose&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID );
		break;


	case 'create_edit':
	case 'create_link':
	case 'create':
	case 'create_publish':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Get params to skip/force/mark notifications and pings:
		if( check_user_perm( 'blog_edit_ts', 'edit', false, $Blog->ID ) )
		{	// If user has a permission to edit advanced properties of items:
			param( 'item_members_notified', 'string', NULL );
			param( 'item_community_notified', 'string', NULL );
			param( 'item_pings_sent', 'string', NULL );
		}
		else
		{	// Use auto mode for notifications depending on the edited Item status:
			$item_members_notified = false;
			$item_community_notified = false;
			$item_pings_sent = false;
		}

		// We need early decoding of these in order to check permissions:
		param( 'post_status', 'string', 'published' );

		if( $action == 'create_publish' )
		{ // load publish status from param, because a post can be published to many status
			$post_status = load_publish_status( true );
		}

		// Check if new category was started to create. If yes check if it is valid:
		check_categories( $post_category, $post_extracats );

		// Check if allowed to cross post.
		check_cross_posting( $post_category, $post_extracats );

		// Check permission on statuses:
		check_user_perm( 'cats_post!'.$post_status, 'create', true, $post_extracats );

		// Get requested Post Type:
		$item_typ_ID = param( 'item_typ_ID', 'integer', true /* require input */ );
		// Check permission on post type: (also verifies that post type is enabled and NOT reserved)
		$valid_item_type = check_perm_posttype( $item_typ_ID, $post_extracats, false );

		// Update the folding positions for current user
		save_fieldset_folding_values( $Blog->ID );
		
		// Update the active tab pane for current user
		save_active_tab_pane_value( $Blog->ID );

		// CREATE NEW POST:
		load_class( 'items/model/_item.class.php', 'Item' );
		$edited_Item = new Item();

		// Set the params we already got:
		$edited_Item->set( 'status', $post_status );
		$edited_Item->set( 'main_cat_ID', $post_category );
		$edited_Item->set( 'extra_cat_IDs', $post_extracats );

		// Set object params:
		$edited_Item->load_from_Request( /* editing? */ ($action == 'create_edit' || $action == 'create_link'), /* creating? */ true );

		$Plugins->trigger_event ( 'AdminBeforeItemEditCreate', array ('Item' => & $edited_Item ) );

		// Validate first enabled captcha plugin:
		$Plugins->trigger_event_first_return( 'ValidateCaptcha', array( 'form_type' => 'item' ) );

		if( !empty( $mass_create ) )
		{	// ------ MASS CREATE ------
			$Items = & create_multiple_posts( $edited_Item, param( 'paragraphs_linebreak', 'boolean', 0 ) );
			if( empty( $Items ) )
			{
				param_error( 'content', TB_( 'Content must not be empty.' ) );
			}
		}

		$result = !$Messages->has_errors();

		if( $result )
		{ // There are no validation errors
			if( isset( $Items ) && !empty( $Items ) )
			{	// We can create multiple posts from single post
				foreach( $Items as $edited_Item )
				{	// INSERT NEW POST INTO DB:
					$result = $edited_Item->dbinsert();
				}
			}
			else
			{	// INSERT NEW POST INTO DB:
				$result = $edited_Item->dbinsert();
			}
			if( !$result )
			{ // Add error message
				$Messages->add( TB_('Couldn\'t create the new post'), 'error' );
			}
		}

		if( $result && $action == 'create_link' )
		{	// If the item has been inserted correctly and we should copy all links from the duplicated item:
			if( check_user_perm( 'item_post!CURSTATUS', 'edit', false, $edited_Item )
			    && check_user_perm( 'files', 'view', false ) )
			{	// Allow this action only if current user has a permission to view the links of new created item:
				$original_item_ID = param( 'p', 'integer', NULL );
				$ItemCache = & get_ItemCache();
				if( $original_Item = & $ItemCache->get_by_ID( $original_item_ID, false, false ) )
				{	// Copy the links only if the requested item is correct:
					if( check_user_perm( 'item_post!CURSTATUS', 'view', false, $original_Item ) )
					{	// Current user must has a permission to view an original item
						$DB->query( 'INSERT INTO T_links ( link_datecreated, link_datemodified, link_creator_user_ID,
								link_lastedit_user_ID, link_itm_ID, link_file_ID, link_position, link_order )
							SELECT '.$DB->quote( date2mysql( $localtimenow ) ).', '.$DB->quote( date2mysql( $localtimenow ) ).', '.$current_User->ID.',
								'.$current_User->ID.', '.$edited_Item->ID.', link_file_ID, link_position, link_order
									FROM T_links
									WHERE link_itm_ID = '.$original_Item->ID );
					}
					else
					{	// Display error if user tries to duplicate the disallowed item:
						$Messages->add( TB_('You have no permission to duplicate the original post.'), 'error' );
						$result = false;
					}
				}
			}
		}

		if( !$result )
		{ // could not insert the new post ( validation errors or unsuccessful db insert )
			if( !empty( $mass_create ) )
			{
				$action = 'new_mass';
			}
			// Params we need for tab switching:
			$tab_switch_params = 'blog='.$blog;
			break;
		}

		// post post-publishing operations:
		param( 'trackback_url', 'string' );
		if( !empty( $trackback_url ) )
		{
			if( $edited_Item->status != 'published' )
			{
				$Messages->add( TB_('Post not publicly published: skipping trackback...'), 'note' );
			}
			else
			{ // trackback now:
				load_funcs('comments/_trackback.funcs.php');
				trackbacks( $trackback_url, $edited_Item );
			}
		}

		// Execute or schedule notifications & pings:
		$edited_Item->handle_notifications( NULL, true, $item_members_notified, $item_community_notified, $item_pings_sent );

		$Messages->add( TB_('Post has been created.'), 'success' );

		// Delete Item from Session
		delete_session_Item( 0 );

		if( ! $exit_after_save && check_user_perm( 'item_post!CURSTATUS', 'edit', false, $edited_Item ) )
		{	// We want to continue editing...
			$tab_switch_params = 'p='.$edited_Item->ID;
			$action = 'edit';	// It's basically as if we had updated
			break;
		}

		// We want to highlight the edited object on next list display:
		$Session->set( 'fadeout_array', array( 'item-'.$edited_Item->ID ) );

		if( ! $valid_item_type )
		{	// Item Type is not enabled for this collection, we will redirect to item type selection to allow user to change it t a valid one:
			$Messages->add( sprintf( TB_('You just edited an Item of Type "%s" which is not valid for this collection. Please select a new Item type below...'), $edited_Item->get( 't_type' ) ), 'warning' );

			// load_from_request param set to 0 will prevent loading of Item data from request because we are not passing any item data from request!
			$redirect_to = url_add_param( $admin_url, 'ctrl=items&action=edit_type&post_ID='.$edited_Item->ID.'&load_from_request=0' );
		}
		elseif( $edited_Item->status != 'redirected' &&
		    ! strpos( $redirect_to, 'tab=tracker' ) &&
		    ! strpos( $redirect_to, 'tab=manual' ) )
		{	// Where to go after creating the post?
			// yura> When a post is created from "workflow" or "manual" we should display a post list

			// Where do we want to go after creating?
			if( $edited_Item->Blog->get_setting( 'enable_goto_blog' ) == 'blog' && $edited_Item->can_be_displayed() )
			{	// Redirect to collection home page ONLY if current user can view it:
				$edited_Item->load_Blog();
				$redirect_to = $edited_Item->Blog->gen_blogurl();
			}
			elseif( $edited_Item->Blog->get_setting( 'enable_goto_blog' ) == 'post' && $edited_Item->can_be_displayed() )
			{	// Redirect to item page to view new created item ONLY if current user can view it:
				$redirect_to = $edited_Item->get_permanent_url();
			}
			else// 'no'
			{	// redirect to posts list:
				$redirect_to = NULL;
				// header_redirect( regenerate_url( '', '&highlight='.$edited_Item->ID, '', '&' ) );
			}
		}

		if( $redirect_to !== NULL )
		{	// The redirect url was NOT set to NULL ( $blog_redirect_setting == 'no' )

			// TRY TO REDIRECT / EXIT
			header_redirect( $redirect_to, 303, false, true /* RETURN if forbidden */ );

			// If we have not Exited yet, it means the redirect was refused because it was on a different domain
		}

		// Set highlight
		$Session->set( 'highlight_id', $edited_Item->ID );

		// REDIRECT / EXIT:
		header_redirect( regenerate_url( '', '&highlight='.$edited_Item->ID, '', '&' ) );
		/* EXITED */
		break;

	case 'link_version':
		// Link the edited Post with the selected Post:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check edit permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		param( 'dest_post_ID', 'integer', true );

		$ItemCache = & get_ItemCache();
		if( $dest_Item = & $ItemCache->get_by_ID( $dest_post_ID, false, false ) )
		{	// Do the linking:
			$dest_Item->set_group_ID( $edited_Item->ID );
			$dest_Item->dbupdate();

			// Remember what last collection was used for linking in order to display it by default on next linking:
			$UserSettings->set( 'last_linked_coll_ID', $dest_Item->get_blog_ID() );
			$UserSettings->dbupdate();

			// Inform user about duplicated locale in the same group:
			$other_version_items = $dest_Item->get_other_version_items();
			foreach( $other_version_items as $other_version_Item )
			{
				if( $dest_Item->get( 'locale' ) == $other_version_Item->get( 'locale' ) )
				{	// This is a duplicate locale
					$Messages->add( sprintf( TB_('WARNING: several versions of this Item use the same locale %s.'), '<code>'.$dest_Item->get( 'locale' ).'</code>' ), 'warning' );
					break;
				}
			}

			// Display result message after redirect:
			$Messages->add( sprintf( TB_('The Item "%s" (%s) has been linked to the current Item.'), $dest_Item->get( 'title' ), $dest_Item->get( 'locale' ) ), 'success' );
		}

		// REDIRECT / EXIT:
		header_redirect( $edited_Item->get_edit_url( array( 'glue' => '&' ) ) );
		/* EXITED */
		break;

	case 'unlink_version':
		// Unlink the selected Post from the edited Post:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check edit permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		param( 'unlink_item_ID', 'integer', true );

		$ItemCache = & get_ItemCache();
		if( $unlink_Item = & $ItemCache->get_by_ID( $unlink_item_ID, false, false ) )
		{	// Do the unlinking:
			$unlink_Item->set( 'igrp_ID', NULL, true );
			$unlink_Item->dbupdate();

			// Display result message after redirect:
			$Messages->add( sprintf( TB_('The Item %s (%s) has been unlinked from the current Item.'), $unlink_Item->get( 'title' ), $unlink_Item->get( 'locale' ) ), 'success' );
		}

		// REDIRECT / EXIT:
		header_redirect( $edited_Item->get_edit_url( array( 'glue' => '&' ) ) );
		/* EXITED */
		break;

	case 'update_edit':
	case 'update':
	case 'update_publish':
	case 'extract_tags':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check edit permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		// Update the folding positions for current user
		save_fieldset_folding_values( $Blog->ID );
		
		// Update the active tab pane for current user
		save_active_tab_pane_value( $Blog->ID );

		// Get params to skip/force/mark notifications and pings:
		if( check_user_perm( 'blog_edit_ts', 'edit', false, $Blog->ID ) )
		{	// If user has a permission to edit advanced properties of items:
			param( 'item_members_notified', 'string', NULL );
			param( 'item_community_notified', 'string', NULL );
			param( 'item_pings_sent', 'string', NULL );
		}
		else
		{	// Use auto mode for notifications depending on the edited Item status:
			$item_members_notified = false;
			$item_community_notified = false;
			$item_pings_sent = false;
		}

		// We need early decoding of these in order to check permissions:
		param( 'post_status', 'string', 'published' );

		if( $action == 'update_publish' )
		{ // load publish status from param, because a post can be published to many status
			$post_status = load_publish_status();
		}

		// Check if new category was started to create.  If yes check if it is valid:
		$isset_category = check_categories( $post_category, $post_extracats, $edited_Item );

		// Check if allowed to cross post.
		if( ! check_cross_posting( $post_category, $post_extracats, $edited_Item->main_cat_ID ) )
		{
			break;
		}

		// Get requested Post Type:
		$item_typ_ID = param( 'item_typ_ID', 'integer', true /* require input */ );
		// Check permission on post type: (also verifies that post type is enabled and NOT reserved)
		$valid_item_type = check_perm_posttype( $item_typ_ID, $post_extracats, false );

		// UPDATE POST:
		// Set the params we already got:
		$edited_Item->set( 'status', $post_status );

		if( $isset_category )
		{ // we change the categories only if the check was succesful

			// get current extra_cats that are in collections where current user is not a coll admin
			$ChapterCache = & get_ChapterCache();

			$prev_extra_cat_IDs = postcats_get_byID( $edited_Item->ID );
			$off_limit_cats = array();
			$r = array();

			foreach( $prev_extra_cat_IDs as $cat )
			{
				$cat_blog = get_catblog( $cat );
				if( ! check_user_perm( 'blog_admin', '', false, $cat_blog ) )
				{
					$Chapter = $ChapterCache->get_by_ID( $cat );
					$off_limit_cats[$cat] = $Chapter;
					$r[] = '<a href="'.$Chapter->get_permanent_url().'">'.$Chapter->dget( 'name' ).'</a>';
				}
			}

			if( $off_limit_cats )
			{
				$Messages->add( sprintf( TB_('Please note: this item is also cross-posted to the following other categories/collections: %s'),
						implode( ', ', $r ) ), 'note' );
			}

			$post_extracats = array_unique( array_merge( $post_extracats,array_keys( $off_limit_cats ) ) );

			$edited_Item->set( 'main_cat_ID', $post_category );
			$edited_Item->set( 'extra_cat_IDs', $post_extracats );
		}

		// Set object params:
		$edited_Item->load_from_Request( false );

		$Plugins->trigger_event( 'AdminBeforeItemEditUpdate', array( 'Item' => & $edited_Item ) );

		// Params we need for tab switching (in case of error or if we save&edit)
		$tab_switch_params = 'p='.$edited_Item->ID;

		if( $Messages->has_errors() )
		{	// There have been some validation errors:
			break;
		}

		// UPDATE POST IN DB:
		if( !$edited_Item->dbupdate() )
		{ // Could not update successful
			$Messages->add( TB_('The post couldn\'t be updated.'), 'error' );
			break;
		}

		if( strpos( $action, 'update' ) === 0 )
		{	// Update attachments folder:
			$edited_Item->update_attachments_folder();
		}

		// post post-publishing operations:
		param( 'trackback_url', 'string' );
		if( !empty( $trackback_url ) )
		{
			if( $edited_Item->status != 'published' )
			{
				$Messages->add( TB_('Post not publicly published: skipping trackback...'), 'note' );
			}
			else
			{ // trackback now:
				load_funcs('comments/_trackback.funcs.php');
				trackbacks( $trackback_url, $edited_Item );
			}
		}

		// Execute or schedule notifications & pings:
		$edited_Item->handle_notifications( NULL, false, $item_members_notified, $item_community_notified, $item_pings_sent );

		if( in_array( $action, array( 'update_edit', 'update', 'update_publish' ) ) )
		{	// Clear all proposed changes of the updated Item:
			$edited_Item->clear_proposed_changes();
		}

		$Messages->add( TB_('Post has been updated.'), 'success' );

		if( $action == 'extract_tags' )
		{	// Extract all possible tags from item contents:
			$searched_tags = $edited_Item->search_tags_by_content();
			// Append new searched tags to existing item's tags:
			$item_tags .= ','.implode( ',', $searched_tags );
			// Clear temp commas:
			$item_tags = utf8_trim( $item_tags, ',' );
		}

		// Delete Item from Session
		delete_session_Item( $edited_Item->ID );

		if( ! $exit_after_save )
		{ // We want to continue editing...
			break;
		}

		// Where to go after editing the post?
		if( ! $valid_item_type )
		{	// Item Type is not enabled for this collection, we will redirect to item type selection to allow user to change it t a valid one:
			$Messages->add( sprintf( TB_('You just edited an Item of Type "%s" which is not valid for this collection. Please select a new Item type below...'), $edited_Item->get( 't_type' ) ), 'warning' );
			$blog_redirect_setting = 'post_type';
		}
		elseif( $edited_Item->status == 'redirected' ||
		    strpos( $redirect_to, 'tab=tracker' ) )
		{ // We should show the posts list if:
			//    a post is in "Redirected" status
			//    a post is updated from "workflow" view tab
			$blog_redirect_setting = 'no';
		}
		elseif( strpos( $redirect_to, 'tab=manual' ) )
		{	// Use the original $redirect_to if a post is updated from "manual" view tab:
			$blog_redirect_setting = 'orig';
		}
		else
		{	// The post was changed:
			$blog_redirect_setting = $edited_Item->Blog->get_setting( 'editing_goto_blog' );
		}

		if( $blog_redirect_setting == 'blog' && $edited_Item->can_be_displayed() )
		{	// Redirect to collection home page ONLY if current user can view it:
			$edited_Item->load_Blog();
			$redirect_to = $edited_Item->Blog->gen_blogurl();
		}
		elseif( $blog_redirect_setting == 'post' && $edited_Item->can_be_displayed() )
		{	// Redirect to item page to view new created item ONLY if current user can view it:
			$redirect_to = $edited_Item->get_permanent_url();
		}
		elseif( $blog_redirect_setting == 'orig' )
		{ // Use original $redirect_to:
			// Set highlight:
			$Session->set( 'highlight_id', $edited_Item->ID );
			$Session->set( 'fadeout_array', array( 'item-'.$edited_Item->ID ) );
			$redirect_to = url_add_param( $redirect_to, 'highlight_id='.$edited_Item->ID, '&' );
		}
		elseif( $blog_redirect_setting == 'post_type' )
		{	// load_from_request param set to 0 will prevent loading of Item data from request because we are not passing any item data from request!
			$redirect_to = url_add_param( $admin_url, 'ctrl=items&action=edit_type&post_ID='.$edited_Item->ID.'&load_from_request=0' );
		}
		else
		{ // $blog_redirect_setting == 'no', set redirect_to = NULL which will redirect to posts list
			$redirect_to = NULL;
		}

		if( $redirect_to !== NULL )
		{	// The redirect url was NOT set to NULL ( $blog_redirect_setting == 'no' )

			// TRY TO REDIRECT / EXIT
			header_redirect( $redirect_to, 303, false, true /* RETURN if forbidden */ );

			// If we have not Exited yet, it means the redirect was refused because it was on a different domain
		}

		// Set highlight
		$Session->set( 'highlight_id', $edited_Item->ID );

		// REDIRECT / EXIT:
		header_redirect( regenerate_url( '', '&highlight='.$edited_Item->ID, '', '&' ) );
		/* EXITED */
		break;

	case 'update_type':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		param( 'from_tab', 'string', NULL );
		param( 'post_ID', 'integer', true, true );
		param( 'ityp_ID', 'integer', true );

		// Used when we change a type of the duplicated item:
		$duplicated_item_ID = param( 'p', 'integer', NULL );

		// Load post from Session
		$edited_Item = get_session_Item( $post_ID );

		if( $Blog->is_item_type_enabled( $ityp_ID ) )
		{ // Set new post type only if it is enabled for the Blog:
			$edited_Item->set( 'ityp_ID', $ityp_ID );

			// Check if current post status is still applicable for new post type
			if( $edited_Item->get( 'pst_ID' ) != NULL )
			{
				$ItemTypeCache = & get_ItemTypeCache();
				$current_ItemType = $ItemTypeCache->get_by_ID( $ityp_ID );
				if( ! in_array( $edited_Item->get( 'pst_ID' ), $current_ItemType->get_applicable_post_status() ) )
				{
					$edited_Item->set( 'pst_ID', NULL );
					$Messages->add( TB_('The current item status is no longer valid for the new item type and has been reset.'), 'warning' );
				}
			}
		}

		// Unset old object of Post Type to reload it on next page
		unset( $edited_Item->ItemType );

		// Check edit permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		set_session_Item( $edited_Item );

		if( ! empty( $from_tab ) && $from_tab == 'type' )
		{ // It goes from items lists to update item type immediately:

			if( $Blog->is_item_type_enabled( $ityp_ID ) )
			{ // Update only when the selected item type is enabled for the Blog:
				$Messages->add( TB_('Post type has been updated.'), 'success' );

				// Update item to set new type right now
				$edited_Item->dbupdate();

				// Highlight the updated item in list
				$Session->set( 'highlight_id', $edited_Item->ID );
			}

			// Set redirect back to items list with new item type tab
			$redirect_to = $admin_url.'?ctrl=items&blog='.$Blog->ID.'&tab=type&tab_type='.$edited_Item->get_type_setting( 'usage' ).'&filter=restore';
		}
		else
		{ // Set default redirect urls (It goes from the item edit form)
			if( $post_ID > 0 )
			{ // Edit item form
				$redirect_to = $admin_url.'?ctrl=items&blog='.$Blog->ID.'&action=edit&restore=1&p='.$edited_Item->ID;
			}
			elseif( $duplicated_item_ID > 0 )
			{ // Copy item form
				$redirect_to = $admin_url.'?ctrl=items&blog='.$Blog->ID.'&action=new&restore=1&p='.$duplicated_item_ID;
			}
			else
			{ // New item form
				$redirect_to = $admin_url.'?ctrl=items&blog='.$Blog->ID.'&action=new&restore=1';
			}
		}

		// REDIRECT / EXIT
		header_redirect( $redirect_to );
		/* EXITED */
		break;

	case 'update_status':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		param( 'status', 'string', true );
		param( 'cat_ID', 'integer', NULL );

		// Check edit permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );
		check_user_perm( 'item_post!'.$status, 'edit', true, $edited_Item );

		// Set new post type
		$edited_Item->set( 'status', $status );

		if( $edited_Item->get( 'status' ) == 'redirected' && empty( $edited_Item->url ) )
		{ // Note: post_url is not part of the simple form, so this message can be a little bit awkward there
			param_error( 'post_url',
				TB_('If you want to redirect this post, you must specify an URL!').' ('.TB_('Advanced properties panel').')',
				TB_('If you want to redirect this post, you must specify an URL!') );
		}

		if( param_errors_detected() )
		{ // If errors then redirect to edit form
			$redirect_to = $admin_url.'?ctrl=items&blog='.$Blog->ID.'&action=edit&restore=1&p='.$edited_Item->ID;
		}
		else
		{ // No errors, Update the item and redirect back to list
			$Messages->add( TB_('Post status has been updated.'), 'success' );

			// Update item to set new type right now
			$edited_Item->dbupdate();

			// Execute or schedule notifications & pings:
			$edited_Item->handle_notifications();

			// Set redirect back to items list with new item type tab:
			$tab = param( 'tab', 'string', 'type' );
			$tab_type = get_tab_by_item_type_usage( $edited_Item->get_type_setting( 'usage' ) );
			$tab_type_param = ( $tab == 'type' ? '&tab_type='.( $tab_type ? $tab_type[0] : 'post' ) : '' );
			$cat_param = ( $cat_ID === NULL ? '' : '&cat_ID='.$cat_ID );
			$redirect_to = $admin_url.'?ctrl=items&blog='.$Blog->ID.'&tab='.$tab.$tab_type_param.$cat_param.'&filter=restore';

			// Highlight the updated item in list
			$Session->set( 'highlight_id', $edited_Item->ID );
		}

		// REDIRECT / EXIT
		header_redirect( $redirect_to );
		/* EXITED */
		break;


	case 'mass_save' :
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb ( 'item' );

		init_list_mode ();
		$ItemList->query ();

		global $DB;

		$update_nr = 0;

		while ( $Item = & $ItemList->get_item () )
		{	// check user permission
			check_user_perm( 'item_post!CURSTATUS', 'edit', true, $Item );

			// Not allow html content on post titles
			$title = param ( 'mass_title_' . $Item->ID, 'htmlspecialchars', NULL );
			$urltitle = param ( 'mass_urltitle_' . $Item->ID, 'string', NULL );
			$titletag = param ( 'mass_titletag_' . $Item->ID, 'string', NULL );

			if ($title != NULL)
			{
				$Item->set ( 'title', $title );
			}

			if ($urltitle != NULL)
			{
				$Item->set ( 'urltitle', $urltitle );
			}

			if ($titletag != NULL)
			{
				$Item->set ( 'titletag', $titletag );
			}

			if( $Item->dbupdate ())
			{
				$update_nr++;	// successfully updated post number
			}
		}

		if( $update_nr > 0 )
		{
			$Messages->add( $update_nr == 1 ?
				TB_('One post has been updated!') :
				sprintf( TB_('%d posts have been updated!'), $update_nr ), 'success' );
		}
		else
		{
			$Messages->add( TB_('No update executed!') );
		}
		// REDIRECT / EXIT
		header_redirect ( $redirect_to, 303 );
		/* EXITED */
		break;

	case 'publish' :
	case 'publish_now' :
		// Publish NOW:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		$post_status = ( $action == 'publish_now' ) ? 'published' : param( 'post_status', 'string', 'published' );

		// Check permissions:
		/* TODO: Check extra categories!!! */
		check_user_perm( 'item_post!'.$post_status, 'edit', true, $edited_Item );

		$edited_Item->set( 'status', $post_status );

		if( $action == 'publish_now' )
		{ // Update post dates
			check_user_perm( 'blog_edit_ts', 'edit', true, $Blog->ID );
			// fp> TODO: remove seconds ONLY if date is in the future
			$edited_Item->set( 'datestart', remove_seconds($localtimenow) );
			$edited_Item->set( 'datemodified', date('Y-m-d H:i:s', $localtimenow) );
		}

		// UPDATE POST IN DB:
		if( $edited_Item->dbupdate() )
		{
			// Update attachments folder:
			$edited_Item->update_attachments_folder();

			// Clear all proposed changes of the updated Item:
			$edited_Item->clear_proposed_changes();
		}

		// Get params to skip/force/mark notifications and pings:
		if( check_user_perm( 'blog_edit_ts', 'edit', false, $Blog->ID ) )
		{	// If user has a permission to edit advanced properties of items:
			param( 'item_members_notified', 'string', NULL );
			param( 'item_community_notified', 'string', NULL );
			param( 'item_pings_sent', 'string', NULL );
		}
		else
		{	// Use auto mode for notifications depending on the edited Item status:
			$item_members_notified = false;
			$item_community_notified = false;
			$item_pings_sent = false;
		}

		// Execute or schedule notifications & pings:
		$edited_Item->handle_notifications( NULL, false, $item_members_notified, $item_community_notified, $item_pings_sent );

		// Set the success message corresponding for the new status
		switch( $edited_Item->status )
		{
			case 'published':
				$success_message = TB_('Post has been published.');
				break;
			case 'community':
				$success_message = TB_('The post is now visible by the community.');
				break;
			case 'protected':
				$success_message = TB_('The post is now visible by the members.');
				break;
			case 'review':
				$success_message = TB_('The post is now visible by moderators.');
				break;
			default:
				$success_message = TB_('Post has been updated.');
				break;
		}
		$Messages->add( $success_message, 'success' );

		// Delete Item from Session
		delete_session_Item( $edited_Item->ID );

		// fp> I noticed that after publishing a new post, I always want to see how the blog looks like
		// If anyone doesn't want that, we can make this optional...

		// REDIRECT / EXIT
		if( $action == 'publish' && !empty( $redirect_to ) && ( strpos( $redirect_to, $admin_url ) !== 0 ) )
		{ // We clicked publish button from the front office
			header_redirect( $redirect_to );
		}
		elseif( $edited_Item->Blog->get_setting( 'enable_goto_blog' ) == 'blog' && $edited_Item->can_be_displayed() )
		{	// Redirect to collection home page ONLY if current user can view it:
			$edited_Item->load_Blog();
			header_redirect( $edited_Item->Blog->gen_blogurl() );
		}
		elseif( $edited_Item->Blog->get_setting( 'enable_goto_blog' ) == 'post' && $edited_Item->can_be_displayed() )
		{	// Redirect to item page to view new created item ONLY if current user can view it:
			header_redirect( $edited_Item->get_permanent_url() );
		}
		else// 'no'
		{	// Redirect to posts list:
			header_redirect( regenerate_url( '', '&highlight='.$edited_Item->ID, '', '&' ) );
		}
		// Switch to list mode:
		// $action = 'list';
		// init_list_mode();
		break;

	case 'restrict':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		$post_status = param( 'post_status', 'string', true );
		// Check permissions:
		check_user_perm( 'item_post!'.$post_status, 'moderate', true, $edited_Item );

		$edited_Item->set( 'status', $post_status );

		// UPDATE POST IN DB:
		$edited_Item->dbupdate();

		$Messages->add( TB_('Post has been restricted.'), 'success' );

		// REDIRECT / EXIT
		header_redirect( $redirect_to );
		break;

	case 'deprecate':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		$post_status = 'deprecated';
		// Check permissions:
		/* TODO: Check extra categories!!! */
		check_user_perm( 'item_post!'.$post_status, 'edit', true, $edited_Item );

		$edited_Item->set( 'status', $post_status );
		$edited_Item->set( 'datemodified', date('Y-m-d H:i:s',$localtimenow) );

		// UPDATE POST IN DB:
		$edited_Item->dbupdate();

		$Messages->add( TB_('Post has been deprecated.'), 'success' );

		// REDIRECT / EXIT
		header_redirect( $redirect_to );
		// Switch to list mode:
		// $action = 'list';
		// init_list_mode();
		break;


	case 'delete':
		// Delete an Item:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check permission:
		check_user_perm( 'blog_del_post', '', true, $blog );

		// fp> TODO: non javascript confirmation
		// $AdminUI->title = TB_('Deleting post...');

		$Plugins->trigger_event( 'AdminBeforeItemEditDelete', array( 'Item' => & $edited_Item ) );

		if( ! $Messages->has_errors() )
		{	// There have been no validation errors:
			// DELETE POST FROM DB:
			$edited_Item->dbdelete();

			$Messages->add( TB_('Post has been deleted.'), 'success' );
		}

		// REDIRECT / EXIT
		header_redirect( $redirect_to );
		// Switch to list mode:
		// $action = 'list';
		// init_list_mode();
		break;


	case 'mass_edit' :
		init_list_mode ();
		break;


	case 'list':
		init_list_mode();

		if( $ItemList->single_post )
		{	// We have requested to view a SINGLE specific post:
			$action = 'view';
		}
		break;

	case 'make_posts_pre':
		// Make posts with selected images action:
		break;

	case 'merge':
	case 'append':
		// Merge/Append the edited Item to another selected Item:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check edit permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		$dest_post_ID = param( 'dest_post_ID', 'integer', true );

		if( $edited_Item->ID == $dest_post_ID )
		{	// If same Item is used as source for merging:
			// DO NOT translate, because it is a wrong request:
			$Messages->add( 'Don\'t use the same item for merging!', 'error' );
			// REDIRECT / EXIT
			header_redirect();
		}

		$ItemCache = & get_ItemCache();
		if( ! ( $dest_Item = & $ItemCache->get_by_ID( $dest_post_ID, false, false ) ) )
		{	// If Item doesn't exist in DB:
			$Messages->add( 'Item to merge does not exist any more.', 'error' );
			// REDIRECT / EXIT
			header_redirect();
		}

		if( $action == 'append' )
		{	// If we should append item and comments at the end with new dates
			$SQL = new SQL( 'Get the latest comment of Item #'.$dest_Item->ID.' to append Item #'.$edited_Item->ID );
			$SQL->SELECT( 'MAX( comment_date )' );
			$SQL->FROM( 'T_comments' );
			$SQL->WHERE( 'comment_item_ID = '.$dest_Item->ID );
			$latest_comment_time = $DB->get_var( $SQL );
			if( $latest_comment_time !== NULL )
			{	// If target Item has at lest one comment use new date/time for new appended comments:
				$append_comment_timestamp = strtotime( $latest_comment_time ) + 60;
			}
		}

		// Convert the source Item to comment of the target Item:
		$Comment = new Comment();
		$Comment->set( 'item_ID', $dest_Item->ID );
		$Comment->set( 'content', $edited_Item->get( 'content' ) );
		$Comment->set_renderers( $edited_Item->get_renderers() );
		$Comment->set( 'status', $edited_Item->get( 'status' ) == 'redirected' ? 'draft' : $edited_Item->get( 'status' ) );
		$Comment->set( 'author_user_ID', $edited_Item->get( 'creator_user_ID' ) );
		if( isset( $append_comment_timestamp ) )
		{	// Append action with 1 minute incrementing:
			$Comment->set( 'date', date2mysql( $append_comment_timestamp ) );
			$append_comment_timestamp += 60;
		}
		else
		{	// Merge action with saving date/time:
			$Comment->set( 'date', $edited_Item->get( 'datestart' ) );
		}
		$Comment->set( 'notif_status', $edited_Item->get( 'notifications_status' ) );
		$notifications_flags = $edited_Item->get( 'notifications_flags' );
		if( is_array( $notifications_flags ) )
		{
			foreach( $notifications_flags as $n => $notifications_flag )
			{
				if( ! in_array( $notifications_flag, array( 'moderators_notified', 'members_notified', 'community_notified' ) ) )
				{	// Skip values which are not allowed for comment:
					unset( $notifications_flags[ $n ] );
				}
			}
		}
		$Comment->set( 'notif_flags', $notifications_flags );
		if( $Comment->dbinsert() )
		{	// If comment has been created try to copy all attachments from source Item:
			$DB->query( 'UPDATE T_links
				  SET link_itm_ID = NULL, link_cmt_ID = '.$Comment->ID.'
				WHERE link_itm_ID = '.$edited_Item->ID );
			$DB->query( 'UPDATE T_links
				  SET link_position = "aftermore"
				WHERE link_cmt_ID = '.$Comment->ID.'
				  AND link_position != "teaser"
				  AND link_position != "aftermore"' );
		}
		// Move all comments of the source Item to the target Item:
		if( isset( $append_comment_timestamp ) )
		{	// Append comments with new dates after the latest comment of the target Item:
			$SQL = new SQL( 'Get all comments of source Item #'.$edited_Item->ID.' in order to append to target Item #'.$dest_Item->ID );
			$SQL->SELECT( 'comment_ID' );
			$SQL->FROM( 'T_comments' );
			$SQL->WHERE( 'comment_item_ID = '.$edited_Item->ID );
			$SQL->ORDER_BY( 'comment_date' );
			$source_comment_IDs = $DB->get_col( $SQL );
			foreach( $source_comment_IDs as $source_comment_ID )
			{
				$DB->query( 'UPDATE T_comments
					  SET comment_item_ID = '.$dest_Item->ID.',
					      comment_date = '.$DB->quote( date2mysql( $append_comment_timestamp ) ).'
					WHERE comment_ID = '.$source_comment_ID );
				// Increment 1 minute for each next appending comment:
				$append_comment_timestamp += 60;
			}
		}
		else
		{	// Merge comments with saving their dates:
			$DB->query( 'UPDATE T_comments
				  SET comment_item_ID = '.$dest_Item->ID.'
				WHERE comment_item_ID = '.$edited_Item->ID );
		}
		// Copy all slugs from source Item to destination Item:
		$DB->query( 'UPDATE T_slug
				  SET slug_itm_ID = '.$dest_Item->ID.'
				WHERE slug_itm_ID = '.$edited_Item->ID );
		// Delete the source Item completely:
		$edited_Item_ID = $edited_Item->ID;
		$edited_Item->dbdelete();

		$Messages->add( sprintf( ( $action == 'append' )
			? TB_('Item #%d has been appended to current Item.')
			: TB_('Item #%d has been merged to current Item.'), $edited_Item_ID ), 'success' );

		// REDIRECT / EXIT
		header_redirect( $admin_url.'?ctrl=items&blog='.$blog.'&p='.$dest_Item->ID );
		break;

	case 'save_propose':
		// Save new proposed change:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check edit permission:
		check_user_perm( 'blog_item_propose', 'edit', true, $Blog->ID );

		// Check if current User can create a new proposed change:
		$edited_Item->can_propose_change( true );

		if( $edited_Item->create_proposed_change() )
		{	// If new proposed changes has been inserted in DB successfully:
			$Messages->add( TB_('New proposed change has been recorded.'), 'success' );
			if( check_user_perm( 'item_post!CURSTATUS', 'edit', false, $edited_Item ) )
			{	// Redirect to item history page with new poroposed change if current User has a permisson:
				header_redirect( $admin_url.'?ctrl=items&action=history&p='.$edited_Item->ID );
			}
			else
			{	// Redirect to item view page:
				header_redirect( $admin_url.'?ctrl=items&blog='.$edited_Item->get_blog_ID().'&p='.$edited_Item->ID );
			}
		}

		// If some errors on creating new proposed change,
		// Display the same submitted form of new proposed change:
		$action = 'propose';
		break;

	case 'accept_propose':
	case 'reject_propose':
		// Accept/Reject the proposed change:

		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'item' );

		// Check edit permission:
		check_user_perm( 'item_post!CURSTATUS', 'edit', true, $edited_Item );

		// Try to get a proposed change by requested ID:
		$Revision = $edited_Item->get_revision( param( 'r', 'string' ) );

		if( ! $Revision || $Revision->iver_type != 'proposed' )
		{	// Stop on wrong requested proposed change:
			debug_die( 'The proposed change #'.$r.' could not be found for Item #'.$edited_Item->ID.' in DB!' );
		}

		if( $action == 'accept_propose' )
		{	// Accept the proposed change:
			// Update current Item with values from the requested proposed change:
			$result = $edited_Item->update_from_revision( get_param( 'r' ) );
			$success_message = sprintf( TB_('The proposed change #%d has been accepted.'), $Revision->iver_ID );
		}
		else
		{	// Reject the proposed change:
			$result = true;
			$success_message = sprintf( TB_('The proposed change #%d has been rejected.'), $Revision->iver_ID );
		}
		if( $result )
		{	// Delete also the proposed changes with custom fields and links to complete accept/reject action:
			$edited_Item->clear_proposed_changes( $action, $Revision->iver_ID );
			// Display success message:
			$Messages->add( $success_message, 'success' );
		}

		// Redirect to item history page with new poroposed change:
		header_redirect( $admin_url.'?ctrl=items&action=history&p='.$edited_Item->ID );
		break;

	default:
		debug_die( 'unhandled action 2: '.htmlspecialchars($action) );
}


/**
 * Initialize list mode; Several actions need this.
 */
function init_list_mode()
{
	global $tab, $tab_type, $Collection, $Blog, $UserSettings, $ItemList, $AdminUI;

	// set default itemslist param prefix
	$items_list_param_prefix = 'items_';

	if ( param( 'p', 'integer', NULL ) || param( 'title', 'string', NULL ) )
	{	// Single post requested, do not filter any post types. If the user
		// has clicked a post link on the dashboard and previously has selected
		// a tab which would filter this post, it wouldn't be displayed now.
		$tab = 'full';
		// in case of single item view params prefix must be empty
		$items_list_param_prefix = NULL;
	}
	else
	{	// Store/retrieve preferred tab from UserSettings:
		$UserSettings->param_Request( 'tab', 'pref_browse_tab', 'string', NULL, true /* memorize */, true /* force */ );
		$UserSettings->param_Request( 'tab_type', 'pref_browse_tab_type', 'string', NULL, true /* memorize */, true /* force */ );
		if( ! in_array( $tab_type, array( 'post', 'page', 'intro', 'content-block', 'special' ) ) )
		{	// Fix wrong requested type:
			$tab_type = 'post';
		}
	}

	if( $tab == 'tracker' && ( ! $Blog->get_setting( 'use_workflow' ) || ! check_user_perm( 'blog_can_be_assignee', 'edit', false, $Blog->ID ) ) )
	{ // Display workflow view only if it is enabled
		global $Messages;
		$Messages->add( TB_('Workflow feature has not been enabled for this collection.'), 'note' );
		$tab = 'full';
	}

	/*
	 * Init list of posts to display:
	 */
	load_class( 'items/model/_itemlist.class.php', 'ItemList2' );

	if( !empty( $tab ) && !empty( $items_list_param_prefix ) )
	{	// Use different param prefix for each tab
		$items_list_param_prefix .= substr( $tab, 0, 7 ).'_';//.utf8_strtolower( $tab_type ).'_';
	}

	// Set different filterset name for each different tab and tab_type
	$filterset_name = ( $tab == 'type' ) ? $tab.'_'.utf8_strtolower( $tab_type ) : $tab;
	// Append collection ID to filterset in order to keep filters separately per collection:
	$filterset_name .= $Blog->ID;

	// Create empty List:
	$ItemList = new ItemList2( $Blog, NULL, NULL, $UserSettings->get('results_per_page'), 'ItemCache', $items_list_param_prefix, $filterset_name /* filterset name */ ); // COPY (func)

	$ItemList->set_default_filters( array(
			'visibility_array' => get_visibility_statuses('keys'),
		) );

	if( $Blog->get_setting('orderby') == 'RAND' )
	{	// Do not display random posts in backoffice for easy management
		$ItemList->set_default_filters( array(
				'orderby' => 'datemodified',
			) );
	}

	switch( $tab )
	{
		case 'full':
			$ItemList->set_default_filters( array(
					'itemtype_usage' => NULL // All types
				) );
			// $AdminUI->breadcrumbpath_add( TB_('All items'), '?ctrl=items&amp;blog=$blog$&amp;tab='.$tab.'&amp;filter=restore' );

			// require colorbox js
			require_js_helper( 'colorbox' );

			// require clipboardjs
			require_js_async( '#clipboardjs#' );

			$AdminUI->breadcrumbpath_add( TB_('All'), '?ctrl=items&amp;blog=$blog$&amp;tab=full&amp;filter=restore' );
			break;

		case 'summary':
			$ItemList->set_default_filters( array(
					'itemtype_usage' => NULL // All types
				) );

			// require colorbox js
			require_js_helper( 'colorbox' );

			$AdminUI->breadcrumbpath_add( TB_('Summary'), '?ctrl=items&amp;blog=$blog$&amp;tab=summary&amp;filter=restore' );
			break;

		case 'manual':
			if( $Blog->get( 'type' ) != 'manual' )
			{	// Display this tab only for manual blogs
				global $admin_url;
				header_redirect( $admin_url.'?ctrl=items&blog='.$Blog->ID.'&tab=type&tab_type=post&filter=restore' );
			}

			global $ReqURI, $blog;

			init_field_editor_js( array(
					'action_url' => $ReqURI.'&blog='.$blog.'&order_action=update&order_data=',
				) );

			$AdminUI->breadcrumbpath_add( TB_('Manual view'), '?ctrl=items&amp;blog=$blog$&amp;tab='.$tab.'&amp;filter=restore' );
			break;

		case 'type':
			// Filter a posts list by type
			$ItemList->set_default_filters( array(
					'itemtype_usage' => implode( ',', get_item_type_usage_by_tab( $tab_type ) ),
				) );
			$AdminUI->breadcrumbpath_add( TB_( $tab_type ), '?ctrl=items&amp;blog=$blog$&amp;tab='.$tab.'&amp;tab_type='.urlencode( $tab_type ).'&amp;filter=restore' );

			// JS to edit an order of items from list view:
			require_js_defer( 'customized:jquery/jeditable/jquery.jeditable.js', 'rsc_url' );
			break;

		case 'tracker':
			// In tracker mode, we want a different default sort:
			$ItemList->set_default_filters( array(
					'orderby' => 'priority',
					'order' => 'ASC' ) );
			$AdminUI->breadcrumbpath_add( TB_( 'Workflow view' ), '?ctrl=items&amp;blog=$blog$&amp;tab=tracker&amp;filter=restore' );

			$AdminUI->set_page_manual_link( 'workflow-features' );

			// JS to edit priority of items from list view
			require_js_defer( 'customized:jquery/jeditable/jquery.jeditable.js', 'rsc_url' );
			break;

		default:
			// Delete the pref_browse_tab setting so that the default
			// (full) gets used the next time the user wants to browse
			// a blog and we don't run into the same error again.
			$UserSettings->delete( 'pref_browse_tab' );
			$UserSettings->dbupdate();
			debug_die( 'Unknown filterset ['.$tab.']' );
	}

	// Init filter params:
	if( ! $ItemList->load_from_Request() )
	{ // If we could not init a filterset from request
		// typically happens when we could no fall back to previously saved filterset...
		// echo ' no filterset!';
	}
}

/**
 * Configure page navigation:
 */
switch( $action )
{
	case 'new':
	case 'new_switchtab': // this gets set as action by JS, when we switch tabs
	case 'new_type': // this gets set as action by JS, when we switch tabs
	case 'copy':
	case 'new_version':
	case 'create_edit':
	case 'create_link':
	case 'create':
	case 'create_publish':
		// Generate available blogs list:
		$AdminUI->set_coll_list_params( 'blog_post_statuses', 'edit', array( 'ctrl' => 'items', 'action' => 'new' ) );

		// We don't check the following earlier, because we want the blog switching buttons to be available:
		if( ! blog_has_cats( $blog ) )
		{
			$error_message = TB_('Since this blog has no categories, you cannot post into it.');
			if( check_user_perm( 'blog_cats', 'edit', false, $blog ) )
			{ // If current user has a permission to create a category
				global $admin_url;
				$error_message .= ' '.sprintf( TB_('You must <a %s>create categories</a> first.'), 'href="'.$admin_url.'?ctrl=chapters&amp;blog='.$blog.'"');
			}
			$Messages->add( $error_message, 'error' );
			$action = 'nil';
			break;
		}

		/* NOBREAK */

	case 'edit':
	case 'edit_switchtab': // this gets set as action by JS, when we switch tabs
	case 'edit_type': // this gets set as action by JS, when we switch tabs
	case 'propose':
	case 'update_edit':
	case 'update': // on error
	case 'update_publish': // on error
	case 'history':
	case 'history_details':
	case 'history_compare':
	case 'extract_tags':
		// Generate available blogs list:
		$AdminUI->set_coll_list_params( 'blog_ismember', 'view', array( 'ctrl' => 'items', 'filter' => 'restore' ) );

		$display_permalink = false;

		// Display item's title and ID in <title> tag instead of default breadcrumb path:
		$AdminUI->htmltitle = $edited_Item->get_title( array(
				'title_field' => 'short_title,title',
				'link_type'   => 'none',
			) );
		if( empty( $AdminUI->htmltitle ) )
		{	// Display collection short name when item has no yet e.g. on creating or when titles are disabled for current Item Type:
			$AdminUI->htmltitle = $Blog->get( 'shortname' );
		}
		$AdminUI->htmltitle .= ' ('.( empty( $edited_Item->ID ) ? TB_('New') : '#'.$edited_Item->ID ).')';

		switch( $action )
		{
			case 'edit':
			case 'edit_switchtab': // this gets set as action by JS, when we switch tabs
			case 'update_edit':
			case 'update': // on error
			case 'update_publish': // on error
			case 'extract_tags':
				$item_permanent_url = $edited_Item->get_permanent_url( '', '', '&amp;', array( 'none' ) );
				if( $item_permanent_url !== false )
				{	// Display item permanent URL only if permanent type is not 'none':
					$AdminUI->global_icon( TB_('Permanent link to full entry'), 'permalink', $item_permanent_url,
							' '.TB_('Permalink'), 4, 3, array(
									'style' => 'margin-right: 3ex',
							) );
					$display_permalink = true;
				}

				if( $Blog->get_setting( 'allow_comments' ) != 'never' )
				{
					$comments_number = generic_ctp_number( $edited_Item->ID, 'comments', 'total', true );
					$item_feedback_title = ( $comments_number == 0 ? TB_('no comment') : ( $comments_number == 1 ? TB_('1 comment') : sprintf( TB_('%d comments'), $comments_number ) ) );
					$AdminUI->global_icon( $item_feedback_title, ( $comments_number > 0 ? 'comments' : 'nocomment' ), $admin_url.'?ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$edited_Item->ID.'#comments',
						' '.$item_feedback_title, 4, 3, array(
								'style' => 'margin-right: 3ex;',
						) );
				}

				$edited_item_url = $edited_Item->get_copy_url();
				if( ! empty( $edited_item_url ) )
				{	// If user has a permission to copy the edited Item:
					$AdminUI->global_icon( TB_('Duplicate this post...'), 'copy', $edited_item_url,
						' '.TB_('Duplicate...'), 4, 3, array(
								'style' => 'margin-right: 3ex;',
						) );
				}

				if( check_user_perm( 'item_post!CURSTATUS', 'edit', false, $edited_Item ) )
				{	// If user has a permission to merge the edited Item:
					$AdminUI->global_icon( TB_('Merge with...'), 'merge', '#',
						' '.TB_('Merge with...'), 4, 3, array(
								'style' => 'margin-right: 3ex;',
								'onclick' => 'return evo_merge_load_window( '.$edited_Item->ID.' )',
						) );
				}

				if( check_user_perm( 'item_post!CURSTATUS', 'delete', false, $edited_Item ) )
				{	// User has permissions to delete this post
					$AdminUI->global_icon( TB_('Delete this post'), 'delete', $admin_url.'?ctrl=items&amp;action=delete&amp;post_ID='.$edited_Item->ID.'&amp;'.url_crumb('item'),
						' '.TB_('Delete'), 4, 3, array(
								'onclick' => 'return confirm(\''.TS_('You are about to delete this post!\\nThis cannot be undone!').'\')',
								'style' => 'margin-right: 3ex;',	// Avoid misclicks by all means!
						) );
				}
				break;
		}

		if( ! in_array( $action, array( 'new_type', 'edit_type', 'history', 'history_details', 'history_compare' ) ) )
		{
			if( $edited_Item->ID > 0 )
			{ // Display a link to history if Item exists in DB
				$AdminUI->global_icon( TB_('Changes'), '', $edited_Item->get_history_url(),
					$edited_Item->history_info_icon().' '.TB_('Changes'), 4, 3, array(
							'style' => 'margin-right: 3ex'
					) );

				// Params we need for tab switching
				$tab_switch_params = 'p='.$edited_Item->ID;
			}
			else
			{
				$tab_switch_params = '';
			}

			// Rearrange global icons to show: Permalink - History - Duplicate - Delete - Close
			if( count( $AdminUI->global_icons ) > 1 && $edited_Item->ID > 0 )
			{
				$history_icon = array_pop( $AdminUI->global_icons );

				if( $display_permalink )
				{ // Insert the history icon right after the permalink icon
					array_splice( $AdminUI->global_icons, 1, 0, array( $history_icon ) );
				}
				else
				{ // Move the history icon in front
					array_unshift( $AdminUI->global_icons, $history_icon );
				}
			}

			if( $action != 'propose' && $Blog->get_setting( 'in_skin_editing' ) && ( check_user_perm( 'blog_post!published', 'edit', false, $Blog->ID ) || get_param( 'p' ) > 0 ) )
			{ // Show 'In skin' link if Blog setting 'In-skin editing' is ON and User has a permission to publish item in this blog
				$mode_inskin_url = url_add_param( $Blog->get( 'url' ), 'disp=edit&amp;'.$tab_switch_params );
				$mode_inskin_action = get_htsrv_url().'item_edit.php';
				$AdminUI->global_icon( TB_('In-skin editing'), 'edit', $mode_inskin_url,
						' '.TB_('In-skin editing'), 4, 3, array(
						'style' => 'margin-right: 3ex',
						'data-shortcut' => 'f2',
						'onclick' => 'return b2edit_reload( \'#item_checkchanges\', \''.$mode_inskin_action.'\' );'
				) );
			}

			$AdminUI->global_icon( TB_('Cancel editing').'!', 'close', $redirect_to, TB_('Cancel'), 4, 2 );

			init_tokeninput_js( 'blog' );
			init_hotkeys_js( 'blog', array( 'f2', 'f9' ) );
		}

		if( in_array( $action, array( 'history', 'history_details', 'history_compare' ) ) )
		{	// History tabs:
			if( check_user_perm( 'item_post!CURSTATUS', 'delete', false, $edited_Item ) )
			{	// User has permissions to edit this Item:
				$AdminUI->global_icon( TB_('Edit current version'), 'edit',  $admin_url.'?ctrl=items&amp;action=edit&amp;p='.$edited_Item->ID, TB_('Edit current version'), 4, 3, array( 'style' => 'margin-right:3ex' ) );
			}

			$item_permanent_url = $edited_Item->get_permanent_url( '', '', '&amp;', array( 'none' ) );
			if( $item_permanent_url !== false )
			{	// Display item permanent URL only if permanent type is not 'none':
				$AdminUI->global_icon( TB_('Permanent link to full entry'), 'permalink', $item_permanent_url,
						' '.TB_('Permalink'), 4, 3, array(
								'style' => 'margin-right: 3ex',
						) );
			}

			$AdminUI->global_icon( TB_('Cancel editing').'!', 'close', regenerate_url( 'action', 'action=history' ), TB_('Cancel'), 4, 2 );
		}

		break;

	case 'new_mass':

		$AdminUI->set_coll_list_params( 'blog_post_statuses', 'edit', array( 'ctrl' => 'items', 'action' => 'new' ) );

		// We don't check the following earlier, because we want the blog switching buttons to be available:
		if( ! blog_has_cats( $blog ) )
		{
			$error_message = TB_('Since this blog has no categories, you cannot post into it.');
			if( check_user_perm( 'blog_cats', 'edit', false, $blog ) )
			{ // If current user has a permission to create a category
				global $admin_url;
				$error_message .= ' '.sprintf( TB_('You must <a %s>create categories</a> first.'), 'href="'.$admin_url.'?ctrl=chapters&amp;blog='.$blog.'"');
			}
			$Messages->add( $error_message, 'error' );
			$action = 'nil';
			break;
		}

	break;

	case 'view':
	case 'history_compare':
	case 'history_details':
		// We're displaying a SINGLE specific post:
		$item_ID = param( 'p', 'integer', true );

		$AdminUI->title_titlearea = TB_('View post & comments');

		if( ! isset( $tab ) )
		{
			$tab = 'full';
		}

		init_hotkeys_js( 'blog', array( 'f2', 'ctrl+f2' ) );

		// Generate available blogs list:
		$AdminUI->set_coll_list_params( 'blog_ismember', 'view', array( 'ctrl' => 'items', 'tab' => $tab, 'filter' => 'restore' ) );

		$AdminUI->breadcrumbpath_add( sprintf( /* TRANS: noun */ TB_('Post').' #%s', $item_ID ), '?ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$item_ID );
		$AdminUI->breadcrumbpath_add( TB_('View post & comments'), '?ctrl=items&amp;blog='.$Blog->ID.'&amp;p='.$item_ID );
		break;

	case 'list':
		// We're displaying a list of posts:

		$AdminUI->title_titlearea = TB_('Browse blog');

		// Generate available blogs list:
		$AdminUI->set_coll_list_params( 'blog_ismember', 'view', array( 'ctrl' => 'items', 'tab' => $tab, 'filter' => 'restore' ) );

		break;
}

/*
 * Add sub menu entries:
 * We do this here instead of _header because we need to include all filter params into regenerate_url()
 */
attach_browse_tabs();


if( isset( $edited_Item ) && ( $ItemType = & $edited_Item->get_ItemType() ) )
{	// Set a tab type for edited/viewed item:
	$tab = 'type';
	if( $tab_type = get_tab_by_item_type_usage( $ItemType->usage ) )
	{	// Only if tab exists for current item type usage:
		$tab_type = $tab_type[0];
	}
	else
	{
		$tab_type = 'all';
	}
}

if( ! empty( $tab ) && $tab == 'type' )
{ // Set a path from dynamic tabs
	$AdminUI->append_path_level( 'type_'.str_replace( ' ', '_', utf8_strtolower( $tab_type ) ) );
}
else
{ // Set a path to selected tab
	$AdminUI->append_path_level( empty( $tab ) ? 'full' : $tab );
}

if( ( isset( $tab ) && in_array( $tab, array( 'full', 'summary', 'type', 'tracker' ) ) ) || strpos( $action, 'edit' ) === 0 )
{ // Init JS to autcomplete the user logins
	init_autocomplete_login_js( 'rsc_url', $AdminUI->get_template( 'autocomplete_plugin' ) );
	// Initialize date picker for _item_expert.form.php
	init_datepicker_js();
}

// Load the appropriate blog navigation styles (including calendar, comment forms...):
require_css( $AdminUI->get_template( 'blog_base.css' ) ); // Default styles for the blog navigation
init_popover_js( 'rsc_url', $AdminUI->get_template( 'tooltip_plugin' ) );

/* fp> I am disabling this. We haven't really used per-blof styles yet and at the moment it creates interference with boostrap Admin
// Load the appropriate ITEM/POST styles depending on the blog's skin:
// It's possible that we have no Blog on the restricted admin interface, when current User doesn't have permission to any blog
if( !empty( $Blog ) )
{ // set blog skin ID if the Blog is set
	$blog_sking_ID = $Blog->get_skin_ID();
	if( ! empty( $blog_sking_ID ) )
	{
		$SkinCache = & get_SkinCache();
		$Skin = $SkinCache->get_by_ID( $blog_sking_ID );
		require_css( 'basic_styles.css', 'blog' ); // the REAL basic styles
		require_css( 'item_base.css', 'blog' ); // Default styles for the post CONTENT
		require_css( $skins_url.$Skin->folder.'/item.css' ); // fp> TODO: this needs to be a param... "of course" -- if none: else item_default.css ?
		// else: $item_css_url = $rsc_url.'css/item_base.css';
	}
	// else item_default.css ? is it still possible to have no skin set?
}
*/

if( $action == 'view' || $action == 'history_compare' || strpos( $action, 'edit' ) !== false || strpos( $action, 'new' ) !== false || $action == 'copy' )
{	// Initialize js to autocomplete usernames in post/comment form
	init_autocomplete_usernames_js();
	// Require colorbox js:
	require_js_helper( 'colorbox' );
}

if( in_array( $action, array( 'new', 'new_version', 'copy', 'create_edit', 'create_link', 'create', 'create_publish', 'edit', 'update_edit', 'update', 'update_publish', 'extract_tags' ) ) )
{ // Set manual link for edit expert mode
	$AdminUI->set_page_manual_link( 'expert-edit-screen' );
}

// Set an url for manual page:
switch( $action )
{
	case 'history':
	case 'history_details':
		$AdminUI->set_page_manual_link( 'item-revision-history' );
		break;
	case 'new':
	case 'new_switchtab':
	case 'edit':
	case 'edit_switchtab':
	case 'copy':
	case 'new_version':
	case 'create':
	case 'create_edit':
	case 'create_link':
	case 'create_publish':
	case 'update':
	case 'update_edit':
	case 'update_publish':
	case 'extract_tags':
		$AdminUI->set_page_manual_link( 'expert-edit-screen' );
		break;
	case 'edit_type':
		$AdminUI->set_page_manual_link( 'change-post-type' );
		break;
	case 'new_mass':
		$AdminUI->set_page_manual_link( 'mass-new-screen' );
		break;
	case 'mass_edit':
		$AdminUI->set_page_manual_link( 'mass-edit-screen' );
		break;
	default:
		switch( get_param( 'tab' ) )
		{
			case 'tracker':
				$AdminUI->set_page_manual_link( 'task-list' );
				break;
			case 'manual':
				$AdminUI->set_page_manual_link( 'manual-pages-editor' );
				break;
			case 'type':
				$AdminUI->set_page_manual_link( $tab_type.'-list' );
				break;
			default:
				$AdminUI->set_page_manual_link( 'browse-edit-tab' );
		}
		break;
}

// Display <html><head>...</head> section! (Note: should be done early if actions do not redirect)
$AdminUI->disp_html_head();

// Display title, menu, messages, etc. (Note: messages MUST be displayed AFTER the actions)
$AdminUI->disp_body_top( $mode != 'iframe' );	// do NOT display stupid messages in iframe (UGLY UGLY UGLY!!!!)


/*
 * Display payload:
 */
switch( $action )
{
	case 'nil':
		// Do nothing
		break;

	case 'new_switchtab': // this gets set as action by JS, when we switch tabs
	case 'edit_switchtab': // this gets set as action by JS, when we switch tabs
	case 'new_type': // this gets set as action by JS, when we switch tabs
	case 'edit_type': // this gets set as action by JS, when we switch tabs
		$bozo_start_modified = true;	// We want to start with a form being already modified
	case 'new':
	case 'copy':
	case 'new_version':
	case 'create_edit':
	case 'create_link':
	case 'create':
	case 'create_publish':
	case 'edit':
	case 'propose':
	case 'update_edit':
	case 'update':	// on error
	case 'update_publish':	// on error
	case 'extract_tags':
		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// We never allow HTML in titles, so we always encode and decode special chars.
        if ($edited_Item->get( 'title' ) !== null){
                $item_title = htmlspecialchars_decode( $edited_Item->get( 'title' ) );    
        }
        


		$item_content = $edited_Item->get( 'content' );
		if( $item_content === NULL )
		{	// Use text template for new creating Item:
			$item_content = $edited_Item->get_type_setting( 'text_template' );
		}
		$item_content = prepare_item_content( $item_content );

		if( ! $edited_Item->get_type_setting( 'allow_html' ) )
		{ // HTML is disallowed for this post, content is encoded in DB and we need to decode it for editing:
			$item_content = htmlspecialchars_decode( $item_content );
		}

		// Format content for editing, if we were not already in editing...
		$Plugins_admin = & get_Plugins_admin();
		$edited_Item->load_Blog();
		$params = array( 'object_type' => 'Item', 'object_Blog' => & $edited_Item->Blog );
		$Plugins_admin->unfilter_contents( $item_title /* by ref */, $item_content /* by ref */, $edited_Item->get_renderers_validated(), $params );

		// Display VIEW:
		switch( $action )
		{
			case 'new_type':
			case 'edit_type':
				// Form to change post type:
				$AdminUI->disp_view( 'items/views/_item_edit_type.form.php' );
				break;

			case 'propose':
				// Form to change post type:
				$AdminUI->disp_view( 'items/views/_item_propose.form.php' );
				break;

			default:
				// Form to edit item
				$AdminUI->disp_view( 'items/views/_item_expert.form.php' );
		}

		// End payload block:
		$AdminUI->disp_payload_end();
		break;


	case 'new_mass':
		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// We never allow HTML in titles, so we always encode and decode special chars.
		$item_title = htmlspecialchars_decode( $edited_Item->title );

		$item_content = prepare_item_content( $edited_Item->content );

		if( ! $edited_Item->get_type_setting( 'allow_html' ) )
		{ // HTML is disallowed for this post, content is encoded in DB and we need to decode it for editing:
			$item_content = htmlspecialchars_decode( $item_content );
		}

		// Format content for editing, if we were not already in editing...
		$Plugins_admin = & get_Plugins_admin();
		$edited_Item->load_Blog();
		$params = array( 'object_type' => 'Item', 'object_Blog' => & $edited_Item->Blog );
		$Plugins_admin->unfilter_contents( $item_title /* by ref */, $item_content /* by ref */, $edited_Item->get_renderers_validated(), $params );

		$AdminUI->disp_view( 'items/views/_item_mass.form.php' );

		// End payload block:
		$AdminUI->disp_payload_end();

		break;


	case 'view':
	case 'delete':
		// View a single post:

		// Memorize 'p' in case we reload while changing some display settings
		memorize_param( 'p', 'integer', NULL );

		// What comments view, 'feedback' - all user comments, 'meta' - internal comments of the admins
		param( 'comment_type', 'string', 'feedback', true );

		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// We use the "full" view for displaying single posts:
		$AdminUI->disp_view( 'items/views/_item_list_full.view.php' );

		// End payload block:
		$AdminUI->disp_payload_end();

		break;

	case 'history':
		memorize_param( 'action', 'string', NULL );

		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// view:
		$AdminUI->disp_view( 'items/views/_item_history.view.php' );

		// End payload block:
		$AdminUI->disp_payload_end();
		break;

	case 'history_details':
		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// view:
		$AdminUI->disp_view( 'items/views/_item_history_details.view.php' );

		// End payload block:
		$AdminUI->disp_payload_end();
		break;

	case 'history_compare':
		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// view:
		$AdminUI->disp_view( 'items/views/_item_history_compare.view.php' );

		// End payload block:
		$AdminUI->disp_payload_end();
		break;

	case 'mass_edit' :
		// Begin payload block:
		$AdminUI->disp_payload_begin ();

		// view:
		$AdminUI->disp_view ( 'items/views/_item_mass_edit.view.php' );

		// End payload block:
		$AdminUI->disp_payload_end ();
		break;

	case 'make_posts_pre':
		// Make posts with selected images action:

		$FileRootCache = & get_FileRootCache();
		// getting root
		$root = param( 'root', 'string' );
		global $fm_FileRoot;
		$fm_FileRoot = & $FileRootCache->get_by_ID($root, true);

		// Begin payload block:
		$AdminUI->disp_payload_begin();
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'file' );

		$AdminUI->disp_view( 'items/views/_file_create_posts.form.php' );
		// End payload block:
		$AdminUI->disp_payload_end();
		break;

	case 'list':
	default:
		// Begin payload block:
		$AdminUI->disp_payload_begin();

		// fplanque> Note: this is depressing, but I have to put a table back here
		// just because IE supports standards really badly! :'(
		$table_browse_template = $AdminUI->get_template( 'table_browse' );
		echo $table_browse_template['table_start'];

		if( $tab == 'manual' )
		{
			echo $table_browse_template['full_col_start'];
		}
		else{
			echo $table_browse_template['left_col_start'];
		}

			switch( $tab )
			{
				case 'tracker':
					// Display VIEW:
					$AdminUI->disp_view( 'items/views/_item_list_track.view.php' );
					break;

				case 'full':
					// Display VIEW:
					$AdminUI->disp_view( 'items/views/_item_list_full.view.php' );
					break;

				case 'summary':
					// Display VIEW:
					$AdminUI->disp_view( 'items/views/_item_list_summary.view.php' );
					break;

				case 'manual':
					// Display VIEW:
					$AdminUI->disp_view( 'items/views/_item_list_manual.view.php' );
					break;

				case 'type':
				default:
					// Display VIEW:
					$AdminUI->disp_view( 'items/views/_item_list_table.view.php' );
					break;
			}

			// TODO: a specific field for the backoffice, at the bottom of the page
			// would be used for moderation rules.
			if( $Blog->get( 'notes' ) )
			{
				$edit_link = '';
				if( check_user_perm( 'blog_properties', 'edit', false, $blog ) )
				{
					$edit_link = action_icon( TB_('Edit').'...', 'edit_button', $admin_url.'?ctrl=coll_settings&amp;tab=general&amp;blog='.$Blog->ID, ' '.TB_('Edit').'...', 3, 4, array( 'class' => 'btn btn-default btn-sm' ) );
				}
				$block_item_Widget = new Widget( 'block_item' );
				$block_item_Widget->title = '<span class="pull-right panel_heading_action_icons">'.$edit_link.'</span>'.TB_('Notes');
				$block_item_Widget->disp_template_replaced( 'block_start' );
				$Blog->disp( 'notes', 'htmlbody' );
				$block_item_Widget->disp_template_replaced( 'block_end' );
			}

		echo $table_browse_template['left_col_end'];

		if( $tab != 'manual' )
		{
			echo $table_browse_template['right_col_start'];
				// Display VIEW:
				$AdminUI->disp_view( 'items/views/_item_list_sidebar.view.php' );
			echo $table_browse_template['right_col_end'];
		}

		echo $table_browse_template['table_end'];

		// End payload block:
		$AdminUI->disp_payload_end();
		break;
}

// Display body bottom, debug info and close </html>:
$AdminUI->disp_global_footer();

?>
