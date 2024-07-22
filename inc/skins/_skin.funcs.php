<?php
/**
 * This file implements Template tags for use withing skins.
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


// DEBUG: (Turn switch on or off to log debug info for specified category)
$GLOBALS['debug_skins'] = true;


/**
 * Initialize internal states for the most common skin displays.
 *
 * For more specific skins, this function may not be called and
 * equivalent code may be customized within the skin.
 *
 * @param string What are we going to display. Most of the time the global $disp should be passed.
 */
function skin_init( $disp )
{
	/**
	 * @var Blog
	 */
	global $Collection, $Blog;
    


	/**
	 * @var Item
	 */
	global $Item;

	/**
	 * @var Skin
	 */
	global $Skin;

	global $robots_index;
	global $seo_page_type;

	global $redir, $ReqURL, $ReqURI, $m, $w, $preview;

	global $Chapter;
	global $Debuglog;

	/**
	 * @var ItemList2
	 */
	global $MainList;

	/**
	 * This will give more detail when $disp == 'posts'; otherwise it will have the same content as $disp
	 * @var string
	 */
	global $disp_detail, $Settings;

	global $Timer;

	global $Messages, $PageCache;

	global $Session, $current_User;

	$Timer->resume( 'skin_init' );

	if( empty($disp_detail) )
	{
		$disp_detail = $disp;
	}

	$Debuglog->add('skin_init: $disp='.$disp, 'skins' );

	if( in_array( $disp, array( 'threads', 'messages', 'contacts', 'msgform', 'user', 'profile', 'avatar', 'pwdchange', 'userprefs', 'subs', 'register_finish', 'visits', 'closeaccount' ) ) &&
	    $msg_Blog = & get_setting_Blog( 'msg_blog_ID' ) &&
	    $Blog->ID != $msg_Blog->ID )
	{	// Redirect to collection which should be used for profile/messaging pages:
		header_redirect( $msg_Blog->get( $disp.'url', array( 'glue' => '&' ) ) );
		// Exit here.
	}

	// Initialize site skin if it is enabled:
	siteskin_init();

	// This is the main template; it may be used to display very different things.
	// Do inits depending on current $disp:
	switch( $disp )
	{
		case 'front':
		case 'posts':
		case 'single':
		case 'page':
		case 'widget_page':
		case 'terms':
		case 'download':
		case 'feedback-popup':
		case 'flagged':
		case 'mustread':
			// We need to load posts for this display:

			if( $disp == 'flagged' && ! is_logged_in() )
			{	// Forbid access to flagged content for not logged in users:
				global $disp;
				$disp = '403';
				$Messages->add( T_('You must log in before you can see your flagged content.'), 'error' );
				break;
			}

			if( $disp == 'mustread' )
			{	// Check access to "must read" content:
				if( ! is_logged_in() )
				{	// Forbid access to "must read" content for not logged in users:
					header_redirect( get_login_url( 'no access to must read content', NULL, false, NULL, 'access_requires_loginurl' ), 302 );
					// Exit here.
				}
				if( ! is_pro() )
				{	// Forbid access for not PRO version:
					global $disp, $disp_detail;
					$disp = '404';
					$disp_detail = '404-not-supported';
					$Messages->add( T_('"Must Read" is supported only on b2evolution PRO.'), 'error' );
					break;
				}
				if( ! $Blog->get_setting( 'track_unread_content' ) )
				{	// Forbid access to "must read" content if collection doesn't track unread content:
					global $disp;
					$disp = '404';
					$Messages->add( T_('This feature only works when <b>Tracking of unread content</b> is enabled.'), 'error' );
					break;
				}
			}

			if( $disp == 'terms' )
			{	// Initialize the redirect param to know what page redirect after accepting of terms:
				param( 'redirect_to', 'url', '' );
			}

			// Note: even if we request the same post as $Item above, the following will do more restrictions (dates, etc.)
			// Init the MainList object:
			init_MainList( $Blog->get_setting('posts_per_page') );

			// Init post navigation
			$post_navigation = $Skin->get_post_navigation();
			if( empty( $post_navigation ) )
			{
				$post_navigation = $Blog->get_setting( 'post_navigation' );
			}

			if( ! empty( $MainList ) && $MainList->single_post &&
			    $single_Item = & mainlist_get_item() )
			{	// If we are currently viewing a single post
				// We assume the current user will have read the entire post and all its current comments:
				$single_Item->update_read_timestamps( true, true );
				// Add tags to the current User from the viewing Item:
				$single_Item->tag_user();
				// Restart the items list:
				$MainList->restart();
			}
			break;

		case 'search':
			// Searching post, comments and categories
			// Load functions to work with search results:
			load_funcs( 'collections/_search.funcs.php' );
			break;
	}

	// SEO stuff & redirects if necessary:
	$seo_page_type = NULL;
	switch( $disp )
	{
		// CONTENT PAGES:
		case 'single':
		case 'page':
		case 'widget_page':
		case 'terms':
			if( $disp == 'terms' && ! $Item )
			{	// Wrong post ID for terms page:
				global $disp;
				$disp = '404';
				$Messages->add( sprintf( T_('Terms not found. (post ID #%s)'), get_param( 'p' ) ), 'error' );
				break;
			}

			if( ( ! $preview ) && ( empty( $Item ) ) )
			{ // No Item, incorrect request and incorrect state of the application, a 404 redirect should have already happened
				//debug_die( 'Invalid page URL!' );
			}

			if( $disp == 'single' )
			{
				$seo_page_type = 'Single post page';
			}
			else
			{
				$seo_page_type = '"Page" page';
			}

			if( ! $preview )
			{ // Check if item has a goal to insert a hit into DB
				$Item->check_goal();
			}

			// Check if we want to redirect to a canonical URL for the post
			// Please document encountered problems.
			if( ! $preview &&
			    is_pro() &&
			    $Item->get_setting( 'external_canonical_url' ) &&
			    $Item->get( 'url' ) != '' )
			{	// Use post link to URL as an External Canonical URL:
				add_headline( '<link rel="canonical" href="'.format_to_output( $Item->get( 'url' ), 'htmlattr' ).'" />' );
			}
			elseif( ! $preview &&
			    ( ( $Blog->get_setting( 'canonical_item_urls' ) && $redir == 'yes' )
			      || $Blog->get_setting( 'relcanonical_item_urls' )
			      || $Blog->get_setting( 'self_canonical_item_urls' )
			    ) )
			{	// We want to redirect to the Item's canonical URL:
				$canonical_is_same_url = true;
				$item_Blog = & $Item->get_Blog();
				// Use item URL from first detected category of the current collection:
				$main_canonical_url = $Item->get_permanent_url( '', '', '&' );
				if( $item_Blog->get_setting( 'allow_crosspost_urls' ) )
				{	// If non-canonical URL is allowed for cross-posted items,
					// try to get a canonical URL in the current collection even it is not main/canonical collection of the Item:
					$canonical_url = $Item->get_permanent_url( '', $Blog->get( 'url' ), '&', array(), $Blog->ID );
				}
				else
				{	// If non-canonical URL is allowed for cross-posted items, then only get canonical URL in the main collection:
					$canonical_url = $main_canonical_url;
				}
				// Keep ONLY allowed params from current URL in the canonical URL by configs AND Item's switchable params:
				$canonical_url = url_keep_canonicals_params( $canonical_url, '&', array_keys( $Item->get_switchable_params() ) );
				if( preg_match( '|[&?](revision=(p?\d+))|', $ReqURI, $revision_param )
						&& check_user_perm( 'item_post!CURSTATUS', 'edit', false, $Item )
						&& $item_revision = $Item->get_revision( $revision_param[2] ) )
				{ // A revision of the post, keep only this param and discard all others:
					$canonical_url = url_add_param( $canonical_url, $revision_param[1], '&' );
					$Item->set( 'revision', $revision_param[2] );
					$Messages->add( sprintf( T_('You are viewing Revision #%s dated %s' ), $revision_param[2], date( locale_datetimefmt(), strtotime( $item_revision->iver_edit_last_touched_ts ) ) ), 'note' );
				}
				$canonical_is_same_url = is_same_url( $ReqURL, $canonical_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' );

				if( ! $canonical_is_same_url && in_array( $Blog->get_setting( 'single_links' ), array( 'subchap', 'chapters' ) ) )
				{	// If current URL is not same as first detected category then try to check all other categories from the current collection:
					$item_chapters = $Item->get_Chapters();
					foreach( $item_chapters as $item_Chapter )
					{	// Try to find in what category the Item may has the same canonical url as current requested URL:
						if( ! $item_Blog->get_setting( 'allow_crosspost_urls' ) &&
						    $item_Blog->ID != $item_Chapter->get( 'blog_ID' ) )
						{	// Don't allow to use URL of categories from cross-posted collection if it is restricted:
							continue;
						}
						$cat_canonical_url = $Item->get_permanent_url( '', $Blog->get( 'url' ), '&', array(), $Blog->ID, $item_Chapter->ID );
						// Keep ONLY allowed params from current URL in the canonical URL by configs AND Item's switchable params:
						$cat_canonical_url = url_keep_canonicals_params( $cat_canonical_url, '&', array_keys( $Item->get_switchable_params() ) );
						if( $canonical_is_same_url = is_same_url( $ReqURL, $cat_canonical_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' ) )
						{	// We have found the same URL, stop find another and stay on the current page without redirect:
							break;
						}
					}
				}

				if( ! $canonical_is_same_url )
				{	// The requested URL does not look like the canonical URL for this post...
					// url difference was resolved
					$url_resolved = false;
					// Check if the difference is because of an allowed post navigation param
					if( preg_match( '|[&?]cat=(\d+)|', $ReqURI, $cat_param ) )
					{ // A category post navigation param is set
						$extended_url = '';
						if( ( $post_navigation == 'same_category' ) && ( isset( $cat_param[1] ) ) )
						{ // navigatie through posts from the same category
							$category_ids = postcats_get_byID( $Item->ID );
							if( in_array( $cat_param[1], $category_ids ) )
							{ // cat param is one of this Item categories
								$extended_url = $Item->add_navigation_param( $canonical_url, $post_navigation, $cat_param[1], '&' );
								// Set MainList navigation target to the requested category
								$MainList->nav_target = $cat_param[1];
							}
						}
						$url_resolved = is_same_url( $ReqURL, $extended_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' );
					}
					if( preg_match( '|[&?]tag=([^&A-Z]+)|', $ReqURI, $tag_param ) )
					{ // A tag post navigation param is set
						$extended_url = '';
						if( ( $post_navigation == 'same_tag' ) && ( isset( $tag_param[1] ) ) )
						{ // navigatie through posts from the same tag
							$tag_names = $Item->get_tags();
							if( in_array( $tag_param[1], $tag_names ) )
							{ // tag param is one of this Item tags
								$extended_url = $Item->add_navigation_param( $canonical_url, $post_navigation, $tag_param[1], '&' );
								// Set MainList navigation target to the requested tag
								$MainList->nav_target = $tag_param[1];
							}
						}
						$url_resolved = is_same_url( $ReqURL, $extended_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' );
					}
					if( ! $url_resolved &&
					    $Blog->get_setting( 'canonical_item_urls' ) &&
					    $redir == 'yes' &&
					    ! $Item->stay_in_cross_posted_collection( 'auto', $Blog->ID ) ) // If the Item cannot stay in the current Collection
					{	// REDIRECT TO THE CANONICAL URL:
						$Debuglog->add( 'Redirecting to canonical URL ['.$canonical_url.'].', 'request' );
						header_redirect( $canonical_url, true );
						// EXITED.
					}
					elseif( $Blog->get_setting( 'relcanonical_item_urls' ) )
					{	// Use rel="canoncial" with MAIN canoncial URL:
						add_headline( '<link rel="canonical" href="'.$main_canonical_url.'" />' );
					}
				}
				elseif( $Blog->get_setting( 'self_canonical_item_urls' ) )
				{	// Use self-referencing rel="canonical" tag with MAIN canoncial URL:
					add_headline( '<link rel="canonical" href="'.$main_canonical_url.'" />' );
				}
			}

			if( $Blog->get_setting( 'single_noindex' ) || ! $MainList->result_num_rows )
			{	// We prefer robots not to index these pages,
				// OR There is nothing to display for this page, don't index it!
				$robots_index = false;
			}
			break;

		case 'download':
			if( empty( $Item ) )
			{ // No Item, incorrect request and incorrect state of the application, a 404 redirect should have already happened
				debug_die( 'Invalid page URL!' );
			}

			$seo_page_type = 'Download page';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{ // We prefer robots not to index these pages:
				$robots_index = false;
			}
			if( ! $Blog->get_setting( 'download_enable' ) )
			{	// If download is disabled for current Collection:
				global $disp;
				$disp = '404';
				$disp_detail = '404-download-disabled';
				break;
			}

			$download_link_ID = param( 'download', 'integer', 0 );

			// Check if we can allow to download the selected file
			$LinkCache = & get_LinkCache();
			if( ! (
			    ( $download_Link = & $LinkCache->get_by_ID( $download_link_ID, false, false ) ) && // Link exists in DB
			    ( $LinkItem = & $download_Link->get_LinkOwner() ) && // Link has an owner object
			    ( $LinkItem->Item && $LinkItem->Item->ID == $Item->ID ) && // Link is attached to this Item
			    ( $download_File = & $download_Link->get_File() ) && // Link has a correct File object
			    ( $download_File->exists() ) // File exists on the disk
			  ) )
			{ // Bad request, Redirect to Item permanent url
				$Messages->add( T_( 'The requested file is not available for download.' ), 'error' );
				$canonical_url = $Item->get_permanent_url( '', '', '&' );
				$Debuglog->add( 'Redirecting to canonical URL ['.$canonical_url.'].' );
				header_redirect( $canonical_url, true );
			}

			// Save the downloading Link to the global vars
			$GLOBALS['download_Link'] = & $download_Link;
			// Save global $Item to $download_Item, because $Item can be rewritten by function get_featured_Item() in some skins
			$GLOBALS['download_Item'] = & $Item;

			// Use meta tag to download file when JavaScript is NOT enabled
			add_headline( '<meta http-equiv="refresh" content="'.intval( $Blog->get_setting( 'download_delay' ) )
				.'; url='.$download_Link->get_download_url( array( 'type' => 'action' ) ).'" />' );
			break;

		case 'posts':
			if( ! $Blog->get_setting( 'postlist_enable' ) )
			{	// If post list is disabled for current Collection:
				global $disp;
				$disp = '404';
				$disp_detail = '404-post-list-disabled';
				break;
			}
			// fp> if we add this here, we have to exetnd the inner if()
			// init_ratings_js( 'blog' );

			// Get list of active filters:
			$active_filters = $MainList->get_active_filters();

			$is_front_disp = ( $Blog->get_setting( 'front_disp' ) == 'posts' );
			$is_first_page = ( empty( $active_filters ) || array_diff( $active_filters, array( 'posts' ) ) == array() );
			$is_next_pages = ( ! $is_first_page && array_diff( $active_filters, array( 'posts', 'page' ) ) == array() );

			if( ( $is_first_page && ! $is_front_disp ) || $is_next_pages )
			{	// This is first(but not front disp) or next pages of disp=posts:
				// Do we need to handle the canoncial url?
				if( ( $Blog->get_setting( 'canonical_posts' ) && $redir == 'yes' )
				    || $Blog->get_setting( 'relcanonical_posts' )
				    || $Blog->get_setting( 'self_canonical_posts' ) )
				{	// Check if the URL was canonical:
					$canonical_url = $Blog->get( 'url', array( 'glue' => '&' ) );
					if( ! $is_front_disp )
					{	// Append disp param only when this disp is not used as front page, because front page hides disp param in URL:
						$canonical_url = url_add_param( $canonical_url, 'disp=posts', '&' );
					}
					if( $is_next_pages )
					{	// Set param for paged url:
						$canonical_url = url_add_param( $canonical_url, $MainList->page_param.'='.$MainList->filters['page'], '&' );
					}
					// Keep ONLY allowed params from current URL in the canonical URL by configs:
					$canonical_url = url_keep_canonicals_params( $canonical_url );
					if( ! is_same_url( $ReqURL, $canonical_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' ) )
					{	// We are not on the canonical blog url:
						if( $Blog->get_setting( 'canonical_posts' ) && $redir == 'yes' )
						{	// REDIRECT TO THE CANONICAL URL:
							header_redirect( $canonical_url, ( empty( $display_containers ) && empty( $display_includes ) ) ? 301 : 303 );
						}
						elseif( $Blog->get_setting( 'relcanonical_posts' ) )
						{	// Use link rel="canoncial":
							add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
						}
					}
					elseif( $Blog->get_setting( 'self_canonical_posts' ) &&
					        ! ( $is_front_disp && $Blog->get_setting( 'self_canonical_homepage' ) ) )
					{	// Use self-referencing rel="canonical" tag,
						// but don't add twice when it is already added for front page:
						add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
					}
				}
			}

			if( $is_first_page )
			{	// This is first page of disp=posts:
				$disp_detail = 'posts-default';
				$seo_page_type = 'First posts page';
				if( $Blog->get_setting( 'posts_firstpage_noindex' ) )
				{	// We prefer robots not to index archive pages:
					$robots_index = false;
				}
			}
			elseif( ! empty( $active_filters ) )
			{	// The current page is being filtered...
				if( $is_next_pages )
				{ // This is just a follow "paged" page
					$disp_detail = 'posts-next';
					$seo_page_type = 'Next page';

					if( has_featured_Item() )
					{	// If current next page has Intro post:
						$disp_detail .= '-intro';
						$robots_index = ! $Blog->get_setting( 'paged_intro_noindex' );
					}
					else
					{	// If current next page has no Intro post:
						$disp_detail .= '-nointro';
						$robots_index = ! $Blog->get_setting( 'paged_noindex' );
					}
				}
				elseif( array_diff( $active_filters, array( 'cat_single', 'cat_array', 'cat_modifier', 'cat_focus', 'posts', 'page' ) ) == array() )
				{ // This is a category page
					$disp_detail = 'posts-cat';
					$seo_page_type = 'Category page';
					if( $Blog->get_setting( 'chapter_noindex' ) )
					{	// We prefer robots not to index category pages:
						$robots_index = false;
					}

					global $cat, $catsel;

					if( ( empty( $catsel ) || // 'catsel' filter is not defined
					      ( is_array( $catsel ) && count( $catsel ) == 1 ) // 'catsel' filter is used for single cat, e.g. when skin config 'cat_array_mode' = 'parent'
					    ) && preg_match( '~^[0-9]+$~', $cat ) ) // 'cat' filter is ID of category and NOT modifier for 'catsel' multicats
					{	// We are on a single cat page:
						// NOTE: we must have selected EXACTLY ONE CATEGORY through the cat parameter
						// BUT: - this can resolve to including children
						//      - selecting exactly one cat through catsel[] is NOT OK since not equivalent (will exclude children)

						$ChapterCache = & get_ChapterCache();
						$Chapter = & $ChapterCache->get_by_ID( $cat, false, false );

						// echo 'SINGLE CAT PAGE';
						$disp_detail = 'posts-topcat';  // may become 'posts-subcat' below.

						if( ( $Blog->get_setting( 'canonical_cat_urls' ) && $redir == 'yes' )
						    || $Blog->get_setting( 'relcanonical_cat_urls' )
						    || $Blog->get_setting( 'self_canonical_cat_urls' ) )
						{ // Check if the URL was canonical:
							if( empty( $Chapter ) && isset( $MainList->filters['cat_array'][0] ) )
							{	// Try to get Chapter from filters:
								$Chapter = & $ChapterCache->get_by_ID( $MainList->filters['cat_array'][0], false, false );
							}

							if( ! empty( $Chapter ) )
							{
								if( $Chapter->parent_ID )
								{	// This is a sub-category page (i-e: not a level 1 category)
									$disp_detail = 'posts-subcat';
								}

								$canonical_url = $Chapter->get_permanent_url( NULL, NULL, $MainList->get_active_filter('page'), NULL, '&' );
								// Keep ONLY allowed params from current URL in the canonical URL by configs:
								$canonical_url = url_keep_canonicals_params( $canonical_url );
								if( ! is_same_url( $ReqURL, $canonical_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' ) )
								{	// fp> TODO: we're going to lose the additional params, it would be better to keep them...
									// fp> what additional params actually?
									if( $Blog->get_setting( 'canonical_cat_urls' ) && $redir == 'yes' )
									{	// REDIRECT TO THE CANONICAL URL:
										header_redirect( $canonical_url, true );
									}
									elseif( $Blog->get_setting( 'relcanonical_cat_urls' ) )
									{	// Use rel="canonical":
										add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
									}
								}
								elseif( $Blog->get_setting( 'self_canonical_cat_urls' ) )
								{	// Use self-referencing rel="canonical" tag:
									add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
								}
							}
						}

						if( $post_navigation == 'same_category' )
						{ // Category is set and post navigation should go through the same category, set navigation target param
							$MainList->nav_target = $cat;
						}

						if( has_featured_Item() )
						{	// If current category has Intro post:
							$disp_detail .= '-intro';
							$robots_index = ! $Blog->get_setting( 'chapter_intro_noindex' );
						}
						else
						{	// If current category has no Intro post:
							$disp_detail .= '-nointro';
						}

						if( empty( $Chapter ) )
						{	// If the requested chapter was not found display 404 page:
							$Messages->add( T_('The requested chapter was not found') );
							global $disp;
							$disp = '404';
							break;
						}
					}
				}
				elseif( array_diff( $active_filters, array( 'tags', 'posts', 'page' ) ) == array() )
				{ // This is a tag page
					$disp_detail = 'posts-tag';
					$seo_page_type = 'Tag page';

					if( ( $Blog->get_setting( 'canonical_tag_urls' ) && $redir == 'yes' )
					    || $Blog->get_setting( 'relcanonical_tag_urls' )
					    || $Blog->get_setting( 'self_canonical_tag_urls' ) )
					{ // Check if the URL was canonical:
						$canonical_url = $Blog->gen_tag_url( $MainList->get_active_filter('tags'), $MainList->get_active_filter('page'), '&' );
						// Keep ONLY allowed params from current URL in the canonical URL by configs:
						$canonical_url = url_keep_canonicals_params( $canonical_url );
						if( ! is_same_url($ReqURL, $canonical_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' ) )
						{
							if( $Blog->get_setting( 'canonical_tag_urls' ) && $redir == 'yes' )
							{	// REDIRECT TO THE CANONICAL URL:
								header_redirect( $canonical_url, true );
							}
							elseif( $Blog->get_setting( 'relcanonical_tag_urls' ) )
							{	// Use rel="canoncial":
								add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
							}
						}
						elseif( $Blog->get_setting( 'self_canonical_tag_urls' ) )
						{	// Use self-referencing rel="canonical" tag:
							add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
						}
					}

					$tag = $MainList->get_active_filter('tags');
					if( $post_navigation == 'same_tag' && !empty( $tag ) )
					{ // Tag is set and post navigation should go through the same tag, set navigation target param
						$MainList->nav_target = $tag;
					}

					if( has_featured_Item() )
					{	// If current tag has Intro post:
						$disp_detail .= '-intro';
						$robots_index = ! $Blog->get_setting( 'tag_intro_noindex' );
					}
					else
					{	// If current tag has no Intro post:
						$disp_detail .= '-nointro';
						$robots_index = ! $Blog->get_setting( 'tag_noindex' );
					}
				}
				elseif( array_diff( $active_filters, array( 'ymdhms', 'week', 'posts', 'page' ) ) == array() ) // fp> added 'posts' 2009-05-19; can't remember why it's not in there
				{ // This is an archive page
					// echo 'archive page';
					$disp_detail = 'posts-date';
					$seo_page_type = 'Date archive page';

					if( ( $Blog->get_setting( 'canonical_archive_urls' ) && $redir == 'yes' )
					    || $Blog->get_setting( 'relcanonical_archive_urls' )
					    || $Blog->get_setting( 'self_canonical_archive_urls' ) )
					{ // Check if the URL was canonical:
						$canonical_url =  $Blog->gen_archive_url( substr( $m, 0, 4 ), substr( $m, 4, 2 ), substr( $m, 6, 2 ), $w, '&', $MainList->get_active_filter('page') );
						// Keep ONLY allowed params from current URL in the canonical URL by configs:
						$canonical_url = url_keep_canonicals_params( $canonical_url );
						if( ! is_same_url($ReqURL, $canonical_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' ) )
						{
							if( $Blog->get_setting( 'canonical_archive_urls' ) && $redir == 'yes' )
							{	// REDIRECT TO THE CANONICAL URL:
								header_redirect( $canonical_url, true );
							}
							elseif( $Blog->get_setting( 'relcanonical_archive_urls' ) )
							{	// Use rel="canoncial":
								add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
							}
						}
						elseif( $Blog->get_setting( 'self_canonical_archive_urls' ) )
						{	// Use self-referencing rel="canonical" tag:
							add_headline( '<link rel="canonical" href="'.$canonical_url.'" />' );
						}
					}

					if( $Blog->get_setting( 'archive_noindex' ) )
					{	// We prefer robots not to index archive pages:
						$robots_index = false;
					}
				}
				else
				{	// Other filtered pages:
					// pre_dump( $active_filters );
					$disp_detail = 'posts-filtered';
					$seo_page_type = 'Other filtered page';

					if( has_featured_Item() )
					{	// If current filtered page has Intro post:
						$disp_detail .= '-intro';
						$robots_index = ! $Blog->get_setting( 'filtered_intro_noindex' );
					}
					else
					{	// If current filtered page has no Intro post:
						$disp_detail .= '-nointro';
						$robots_index = ! $Blog->get_setting( 'filtered_noindex' );
					}
				}
			}
			break;

		case 'search':
			$seo_page_type = 'Search page';
			if( $Blog->get_setting( 'filtered_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			if( ! $Blog->get_setting( 'search_enable' ) )
			{	// If search is disabled for current Collection:
				global $disp;
				$disp = '404';
				$disp_detail = '404-search-disabled';
			}
			break;

		// SPECIAL FEATURE PAGES:
		case 'feedback-popup':
			$seo_page_type = 'Comment popup';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'arcdir':
			$seo_page_type = 'Date archive directory';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'catdir':
			$seo_page_type = 'Category directory';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'msgform':
			global $disp;

			// get expected message form type
			$msg_type = param( 'msg_type', 'string', '' );
			// initialize
			$recipient_User = NULL;
			$Comment = NULL;
			$allow_msgform = NULL;

			// get possible params
			$recipient_id = param( 'recipient_id', 'integer', 0, true );
			$comment_id = param( 'comment_id', 'integer', 0, true );
			$post_id = param( 'post_id', 'integer', 0, true );
			$subject = param( 'subject', 'string', '' );
			$redirect_to = param( 'redirect_to', 'url', regenerate_url(), true, true );

			// try to init recipient_User
			if( !empty( $recipient_id ) )
			{
				$UserCache = & get_UserCache();
				$recipient_User = & $UserCache->get_by_ID( $recipient_id );
			}
			elseif( !empty( $comment_id ) )
			{ // comment id is set, try to get comment author user
				$CommentCache = & get_CommentCache();
				if( $Comment = & $CommentCache->get_by_ID( $comment_id, false ) )
				{
					$recipient_User = & $Comment->get_author_User();
					if( empty( $recipient_User ) && ( $Comment->allow_msgform ) && ( is_email( $Comment->get_author_email() ) ) )
					{ // set allow message form to email because comment author (not registered) accepts email
						$allow_msgform = 'email';
					}
				}
			}
			else
			{ // Recipient was not defined, try set the blog owner as recipient
				global $Collection, $Blog;
				if( empty( $Blog ) )
				{ // Blog is not set, this is an invalid request
					debug_die( 'Invalid send message request!');
				}
				$recipient_User = $Blog->get_owner_User();
			}

			if( $recipient_User )
			{ // recipient User is set
				// get_msgform_possibility returns NULL (false), only if there is no messaging option between current_User and recipient user
				$allow_msgform = $recipient_User->get_msgform_possibility();

				if( $msg_type == 'email' && $recipient_User->get_msgform_possibility( NULL, 'email' ) != 'email' )
				{ // User doesn't want to receive email messages, Restrict if this was requested by wrong url:
					$msg_type = '';
				}

				if( $allow_msgform == 'login' )
				{ // user must login first to be able to send a message to this User
					$Messages->add( sprintf( T_( 'You must log in before you can contact "%s".' ), $recipient_User->get( 'login' ) ) );
					// Redirect to special blog for login actions:
					header_redirect( url_add_param( $Blog->get( 'loginurl', array( 'glue' => '&' ) ), 'redirect_to='.rawurlencode( $redirect_to ), '&' ) );
					// Exit here.
				}
				elseif( ( $allow_msgform == 'PM' ) && check_user_status( 'can_be_validated' ) )
				{ // user is not activated
					if( $recipient_User->accepts_email() )
					{ // recipient User accepts email allow to send email
						$allow_msgform = 'email';
						$msg_type = 'email';
						$activateinfo_link = 'href="'.get_activate_info_url( NULL, '&amp;' ).'"';
						$Messages->add( sprintf( T_( 'You must activate your account before you can send a private message to %s. However you can send them an email if you\'d like. <a %s>More info &raquo;</a>' ), $recipient_User->get( 'login' ), $activateinfo_link ), 'warning' );
					}
					else
					{ // Redirect to the activate info page for not activated users
						$Messages->add( T_( 'You must activate your account before you can contact a user. <b>See below:</b>' ) );
						header_redirect( get_activate_info_url(), 302 );
						// will have exited
					}
				}
				elseif( ( $msg_type == 'PM' ) && ( $allow_msgform == 'email' ) )
				{ // only email is allowed but user expect private message form
					if( ( !empty( $current_User ) ) && ( $recipient_id == $current_User->ID ) )
					{
						$Messages->add( T_( 'You cannot send a private message to yourself. However you can send yourself an email if you\'d like.' ), 'warning' );
					}
				}

				if( empty( $recipient_id ) && ! empty( $recipient_User ) )
				{	// Set recipient user param when it is not specified in GET/POST:
					set_param( 'recipient_id', $recipient_User->ID );
				}
			}

			if( $allow_msgform == NULL )
			{ // should be Prevented by UI
				if( !empty( $recipient_User ) )
				{
					$Messages->add( sprintf( T_( 'The user "%s" does not want to be contacted through the message form.' ), $recipient_User->get( 'login' ) ), 'error' );
				}
				elseif( !empty( $Comment ) )
				{
					$Messages->add( T_( 'This commentator does not want to get contacted through the message form.' ), 'error' );
				}

				// If it was a front page request or the front page is set to 'msgform' then we must not redirect to the front page because it is forbidden for the current User
				$redirect_to = ( is_front_page() || ( $Blog->get_setting( 'front_disp' ) == 'msgform' ) ) ? url_add_param( $Blog->gen_blogurl(), 'disp=403', '&' ) : $redirect_to;
				header_redirect( $redirect_to, 302 );
				// exited here
			}

			if( $allow_msgform == 'PM' || $allow_msgform == 'email' )
			{ // Some message form is available
				// Get the suggested subject for the email:
				if( empty($subject) )
				{ // no subject provided by param:
					global $DB;

					if( ! empty($comment_id) )
					{
						// fp>TODO there should be NO SQL in this file. Make a $ItemCache->get_by_comment_ID().
						$row = $DB->get_row( '
							SELECT post_title
								FROM T_items__item, T_comments
							 WHERE comment_ID = '.$DB->quote($comment_id).'
								 AND post_ID = comment_item_ID' );

						if( $row )
						{
							$subject = T_('Re:').' '.sprintf( /* TRANS: Used as mail subject; %s gets replaced by an item's title */ T_( 'Comment on %s' ), $row->post_title );
						}
					}

					if( empty($subject) && ! empty($post_id) )
					{
						// fp>TODO there should be NO SQL in this file. Use $ItemCache->get_by_ID.
						$row = $DB->get_row( '
								SELECT post_title
									FROM T_items__item
								 WHERE post_ID = '.$post_id );
						if( $row )
						{
							$subject = T_('Re:').' '.$row->post_title;
						}
					}
				}
				if( $allow_msgform == 'PM' && isset( $edited_Thread ) )
				{
					$edited_Thread->title = $subject;
				}
				else
				{
					param( 'subject', 'string', $subject, true );
				}
			}

			if( $msg_Blog = & get_setting_Blog( 'msg_blog_ID' ) && $Blog->ID != $msg_Blog->ID )
			{ // Redirect to special blog for messaging actions if it is defined in general settings
				header_redirect( $msg_Blog->get( 'msgformurl', array( 'glue' => '&' ) ) );
			}

			$seo_page_type = 'Contact form';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{ // We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'messages':
		case 'contacts':
		case 'threads':
			switch( $disp )
			{
				case 'messages':
					// Actions ONLY for disp=messages

					// fp> The correct place to get thrd_ID is here, because we want it in redirect_to in case we need to ask for login.
					$thrd_ID = param( 'thrd_ID', 'integer', '', true );

					if( !is_logged_in() )
					{ // Redirect to the login page for anonymous users
						$Messages->add( T_( 'You must log in to read your messages.' ) );
						header_redirect( get_login_url('cannot see messages'), 302 );
						// will have exited
					}

					// check if user status allow to view messages
					if( ! check_user_status( 'can_view_messages' ) )
					{ // user status does not allow to view messages
						if( check_user_status( 'can_be_validated' ) )
						{ // user is logged in but his/her account is not activate yet
							$Messages->add( T_( 'You must activate your account before you can read & send messages. <b>See below:</b>' ) );
							header_redirect( get_activate_info_url(), 302 );
							// will have exited
						}

						$Messages->add( 'You are not allowed to view Messages!' );
						header_redirect( $Blog->gen_blogurl(), 302 );
						// will have exited
					}

					// check if user permissions allow to view messages
					if( ! check_user_perm( 'perm_messaging', 'reply' ) )
					{ // Redirect to the blog url for users without messaging permission
						$Messages->add( 'You are not allowed to view Messages!' );
						header_redirect( $Blog->gen_blogurl(), 302 );
						// will have exited
					}

					if( !empty( $thrd_ID ) )
					{ // if this thread exists and current user is part of this thread update status because won't be any unread messages on this conversation
						// we need to mark this early to make sure the unread message count will be correct in the evobar
						mark_as_read_by_user( $thrd_ID, $current_User->ID );
					}

					if( ( $unsaved_message_params = get_message_params_from_session() ) !== NULL )
					{ // set Message and Thread saved params from Session
						global $edited_Message, $action;
						load_class( 'messaging/model/_message.class.php', 'Message' );
						$edited_Message = new Message();
						$edited_Message->text = $unsaved_message_params[ 'message' ];
						$edited_Message->original_text = $unsaved_message_params[ 'message_original' ];
						$edited_Message->set_renderers( $unsaved_message_params[ 'renderers' ] );
						$edited_Message->thread_ID = $thrd_ID;
						$action = $unsaved_message_params[ 'action' ];
					}
					break;

				case 'contacts':
					// Actions ONLY for disp=contacts

					if( !is_logged_in() )
					{ // Redirect to the login page for anonymous users
						$Messages->add( T_( 'You must log in to manage your contacts.' ) );
						header_redirect( get_login_url('cannot see contacts'), 302 );
						// will have exited
					}

					if( ! check_user_status( 'can_view_contacts' ) )
					{ // user is logged in, but his status doesn't allow to view contacts
						if( check_user_status( 'can_be_validated' ) )
						{ // user is logged in but his/her account was not activated yet
							// Redirect to the account activation page
							$Messages->add( T_( 'You must activate your account before you can manage your contacts. <b>See below:</b>' ) );
							header_redirect( get_activate_info_url(), 302 );
							// will have exited
						}

						// Redirect to the blog url for users without messaging permission
						$Messages->add( 'You are not allowed to view Contacts!' );
						$blogurl = $Blog->gen_blogurl();
						// If it was a front page request or the front page is set to display 'contacts' then we must not redirect to the front page because it is forbidden for the current User
						$redirect_to = ( is_front_page() || ( $Blog->get_setting( 'front_disp' ) == 'contacts' ) ) ? url_add_param( $blogurl, 'disp=403', '&' ) : $blogurl;
						header_redirect( $redirect_to, 302 );
					}

					if( has_cross_country_restriction( 'any' ) && empty( $current_User->ctry_ID ) )
					{ // User may browse/contact other users only from the same country
						$Messages->add( T_('Please specify your country before attempting to contact other users.') );
						header_redirect( get_user_profile_url() );
					}

					// Get action parameter from request:
					$action = param_action();

					if( ! check_user_perm( 'perm_messaging', 'reply' ) )
					{ // Redirect to the blog url for users without messaging permission
						$Messages->add( 'You are not allowed to view Contacts!' );
						$blogurl = $Blog->gen_blogurl();
						// If it was a front page request or the front page is set to display 'contacts' then we must not redirect to the front page because it is forbidden for the current User
						$redirect_to = ( is_front_page() || ( $Blog->get_setting( 'front_disp' ) == 'contacts' ) ) ? url_add_param( $blogurl, 'disp=403', '&' ) : $blogurl;
						header_redirect( $redirect_to, 302 );
						// will have exited
					}

					switch( $action )
					{
						case 'add_user': // Add user to contacts list
							// Check that this action request is not a CSRF hacked request:
							$Session->assert_received_crumb( 'messaging_contacts' );

							$user_ID = param( 'user_ID', 'integer', 0 );
							if( $user_ID > 0 )
							{ // Add user to contacts
								if( create_contacts_user( $user_ID ) )
								{ // Add user to the group
									$group_ID = param( 'group_ID', 'string', '' );
									if( $result = create_contacts_group_users( $group_ID, $user_ID, 'group_ID_combo' ) )
									{ // User has been added to the group
										$Messages->add( sprintf( T_('User has been added to the &laquo;%s&raquo; group.'), $result['group_name'] ), 'success' );
									}
									else
									{ // User has been added ONLY to the contacts list
										$Messages->add( 'User has been added to your contacts.', 'success' );
									}
								}
								header_redirect( $Blog->get( 'userurl', array( 'user_ID' => $user_ID ) ) );
							}
							break;

						case 'unblock': // Unblock user
							// Check that this action request is not a CSRF hacked request:
							$Session->assert_received_crumb( 'messaging_contacts' );

							$user_ID = param( 'user_ID', 'integer', 0 );
							if( $user_ID > 0 )
							{
								set_contact_blocked( $user_ID, 0 );
								$Messages->add( T_('Contact was unblocked.'), 'success' );
							}
							break;

						case 'remove_user': // Remove user from contacts group
							// Check that this action request is not a CSRF hacked request:
							$Session->assert_received_crumb( 'messaging_contacts' );

							$view = param( 'view', 'string', 'profile' );
							$user_ID = param( 'user_ID', 'integer', 0 );
							$group_ID = param( 'group_ID', 'integer', 0 );
							if( $user_ID > 0 && $group_ID > 0 )
							{ // Remove user from selected group
								if( remove_contacts_group_user( $group_ID, $user_ID ) )
								{ // User has been removed from the group
									if( $view == 'contacts' )
									{ // Redirect to the contacts list
										header_redirect( $Blog->get( 'contactsurl', array( 'glue' => '&' ) ) );
									}
									else
									{ // Redirect to the user profile page
										header_redirect( $Blog->get( 'userurl', array( 'user_ID' => $user_ID ) ) );
									}
								}
							}
							break;

						case 'add_group': // Add users to the group
							// Check that this action request is not a CSRF hacked request:
							$Session->assert_received_crumb( 'messaging_contacts' );

							$group = param( 'group', 'string', '' );
							$users = param( 'users', 'string', '' );

							if( $result = create_contacts_group_users( $group, $users ) )
							{	// Users have been added to the group
								$Messages->add( sprintf( T_('%d contacts have been added to the &laquo;%s&raquo; group.'), $result['count_users'], $result['group_name'] ), 'success' );
								$redirect_to = $Blog->get( 'contactsurl', array( 'glue' => '&' ) );

								$item_ID = param( 'item_ID', 'integer', 0 );
								if( $item_ID > 0 )
								{
									$redirect_to = url_add_param( $redirect_to, 'item_ID='.$item_ID, '&' );
								}
								header_redirect( $redirect_to );
							}
							break;

						case 'rename_group': // Rename the group
							// Check that this action request is not a CSRF hacked request:
							$Session->assert_received_crumb( 'messaging_contacts' );

							$group_ID = param( 'group_ID', 'integer', true );

							if( rename_contacts_group( $group_ID ) )
							{
								$item_ID = param( 'item_ID', 'integer', 0 );

								$redirect_to = url_add_param( $Blog->get( 'contactsurl', array( 'glue' => '&' ) ), 'g='.$group_ID, '&' );
								if( $item_ID > 0 )
								{
									$redirect_to = url_add_param( $redirect_to, 'item_ID='.$item_ID, '&' );
								}

								$Messages->add( T_('The group has been renamed.'), 'success' );
								header_redirect( $redirect_to );
							}
							break;

						case 'delete_group': // Delete the group
							// Check that this action request is not a CSRF hacked request:
							$Session->assert_received_crumb( 'messaging_contacts' );

							$group_ID = param( 'group_ID', 'integer', true );

							if( delete_contacts_group( $group_ID ) )
							{
								$item_ID = param( 'item_ID', 'integer', 0 );

								$redirect_to = $Blog->get( 'contactsurl', array( 'glue' => '&' ) );
								if( $item_ID > 0 )
								{
									$redirect_to = url_add_param( $redirect_to, 'item_ID='.$item_ID, '&' );
								}

								$Messages->add( T_('The group has been deleted.'), 'success' );
								header_redirect( $redirect_to );
							}
							break;
					}

					modules_call_method( 'switch_contacts_actions', array( 'action' => $action ) );
					break;

				case 'threads':
					// Actions ONLY for disp=threads

					if( !is_logged_in() )
					{ // Redirect to the login page for anonymous users
						$Messages->add( T_( 'You must log in to read your messages.' ) );
						header_redirect( get_login_url('cannot see messages'), 302 );
						// will have exited
					}

					if( ! check_user_status( 'can_view_threads' ) )
					{ // user status does not allow to view threads
						if( check_user_status( 'can_be_validated' ) )
						{ // user is logged in but his/her account is not activate yet
							$Messages->add( T_( 'You must activate your account before you can read & send messages. <b>See below:</b>' ) );
							header_redirect( get_activate_info_url(), 302 );
							// will have exited
						}

						$Messages->add( 'You are not allowed to view Messages!' );

						$blogurl = $Blog->gen_blogurl();
						// If it was a front page request or the front page is set to display 'threads' then we must not redirect to the front page because it is forbidden for the current User
						$redirect_to = ( is_front_page() || ( $Blog->get_setting( 'front_disp' ) == 'threads' ) ) ? url_add_param( $blogurl, 'disp=404', '&' ) : $blogurl;
						header_redirect( $redirect_to, 302 );
						// will have exited
					}

					if( ! check_user_perm( 'perm_messaging', 'reply' ) )
					{ // Redirect to the blog url for users without messaging permission
						$Messages->add( 'You are not allowed to view Messages!' );
						$blogurl = $Blog->gen_blogurl();
						// If it was a front page request or the front page is set to display 'threads' then we must not redirect to the front page because it is forbidden for the current User
						$redirect_to = ( is_front_page() || ( $Blog->get_setting( 'front_disp' ) == 'threads' ) ) ? url_add_param( $blogurl, 'disp=403', '&' ) : $blogurl;
						header_redirect( $redirect_to, 302 );
						// will have exited
					}

					$action = param( 'action', 'string', 'view' );
					if( $action == 'new' )
					{ // Before new message form is displayed ...
						if( has_cross_country_restriction( 'contact' ) && empty( $current_User->ctry_ID ) )
						{ // Cross country contact restriction is enabled, but user country is not set yet
							$Messages->add( T_('Please specify your country before attempting to contact other users.') );
							header_redirect( get_user_profile_url() );
						}
						elseif( check_create_thread_limit( true ) )
						{ // don't allow to create new thread, because the new thread limit was already reached
							set_param( 'action', 'view' );
						}
					}

					// Load classes
					load_class( 'messaging/model/_thread.class.php', 'Thread' );
					load_class( 'messaging/model/_message.class.php', 'Message' );

					// Get action parameter from request:
					$action = param_action( 'view' );

					switch( $action )
					{
						case 'new':
							// Check permission:
							check_user_perm( 'perm_messaging', 'reply', true );

							global $edited_Thread, $edited_Message;

							$edited_Thread = new Thread();
							$edited_Message = new Message();
							$edited_Message->Thread = & $edited_Thread;

							modules_call_method( 'update_new_thread', array( 'Thread' => & $edited_Thread ) );

							if( ( $unsaved_message_params = get_message_params_from_session() ) !== NULL )
							{ // set Message and Thread saved params from Session
								$edited_Message->text = $unsaved_message_params[ 'message' ];
								$edited_Message->original_text = $unsaved_message_params[ 'message_original' ];
								$edited_Message->set_renderers( $unsaved_message_params[ 'renderers' ] );
								$edited_Thread->title = $unsaved_message_params[ 'subject' ];
								$edited_Thread->recipients = $unsaved_message_params[ 'thrd_recipients' ];
								$edited_Message->Thread = $edited_Thread;

								global $thrd_recipients_array, $thrdtype, $action, $creating_success;

								$thrd_recipients_array = $unsaved_message_params[ 'thrd_recipients_array' ];
								$thrdtype = $unsaved_message_params[ 'thrdtype' ];
								$action = $unsaved_message_params[ 'action' ];
								$creating_success = !empty( $unsaved_message_params[ 'creating_success' ] ) ? $unsaved_message_params[ 'creating_success' ] : false;
							}
							else
							{
								if( empty( $edited_Thread->recipients ) )
								{
									$edited_Thread->recipients = param( 'thrd_recipients', 'string', '' );
								}
								if( empty( $edited_Thread->title ) )
								{
									$edited_Thread->title = param( 'subject', 'string', '' );
								}
							}
							break;

						default:
							// Check permission:
							check_user_perm( 'perm_messaging', 'reply', true );
							break;
					}
					break;
			}

			// Actions for disp = messages, contacts, threads:

			if( $msg_Blog = & get_setting_Blog( 'msg_blog_ID' ) && $Blog->ID != $msg_Blog->ID )
			{ // Redirect to special blog for messaging actions if it is defined in general settings
				$blog_url_params = array( 'glue' => '&' );
				if( ! empty( $thrd_ID ) )
				{ // Don't forget the important param on redirect
					$blog_url_params['url_suffix'] = 'thrd_ID='.$thrd_ID;
				}
				header_redirect( $msg_Blog->get( $disp.'url', $blog_url_params ) );
			}

			// just in case some robot would be logged in:
			$seo_page_type = 'Messaging module';
			$robots_index = false;

			// Display messages depending on user email status
			display_user_email_status_message();
			break;

		case 'access_requires_login':
		case 'content_requires_login':
			global $login_mode;

			// Check and redirect if current URL must be used as https instead of http:
			check_https_url( 'login' );

			if( is_logged_in() )
			{	// Don't display this page for already logged in user:
				global $Blog;
				header_redirect( $Blog->get( 'url' ) );
				// Exit here.
			}

			if( $Settings->get( 'http_auth_require' ) && ! isset( $_SERVER['PHP_AUTH_USER'] ) )
			{	// Require HTTP authentication:
				header( 'WWW-Authenticate: Basic realm="b2evolution"' );
				header( 'HTTP/1.0 401 Unauthorized' );
			}

			if( ! empty( $login_mode ) && $login_mode == 'http_basic_auth' )
			{	// Display this error if user already tried to log in by HTTP basic authentication and it was failed:
				$Messages->add( T_('Wrong Login/Password provided by browser (HTTP Auth).'), 'error' );
			}

			if( $disp == 'content_requires_login' )
			{	// Set default details for this disp:
				$disp_detail = '403-item-requires-login';
			}
			break;

		case 'login':
			// Log in form:
			global $Plugins, $login_mode;

			// Check and redirect if current URL must be used as https instead of http:
			check_https_url( 'login' );

			if( is_logged_in() )
			{ // User is already logged in
				if( check_user_status( 'can_be_validated' ) )
				{ // account is not active yet, redirect to the account activation page
					$Messages->add( T_( 'You are logged in but your account is not activated. You will find instructions about activating your account below:' ) );
					header_redirect( get_activate_info_url(), 302 );
					// will have exited
				}

				// User is already logged in, redirect to "redirect_to" page
				$Messages->add( T_( 'You are already logged in' ).'.', 'note' );
				$redirect_to = param( 'redirect_to', 'url', '' );
				$forward_to = param( 'forward_to', 'url', $redirect_to );
				header_redirect( $forward_to, 302 );
				// will have exited
			}

			if( $login_Blog = & get_setting_Blog( 'login_blog_ID', $Blog ) && $Blog->ID != $login_Blog->ID )
			{ // Redirect to special blog for login/register actions if it is defined in general settings
				header_redirect( $login_Blog->get( 'loginurl', array( 'glue' => '&' ) ) );
			}

			if( $Settings->get( 'http_auth_require' ) && ! isset( $_SERVER['PHP_AUTH_USER'] ) )
			{	// Require HTTP authentication:
				header( 'WWW-Authenticate: Basic realm="b2evolution"' );
				header( 'HTTP/1.0 401 Unauthorized' );
			}

			if( ! empty( $login_mode ) && $login_mode == 'http_basic_auth' )
			{	// Display this error if user already tried to log in by HTTP basic authentication and it was failed:
				$Messages->add( T_('Wrong Login/Password provided by browser (HTTP Auth).'), 'error' );
			}

			$seo_page_type = 'Login form';
			$robots_index = false;
			break;

		case 'register':
			// Register form:

			// Check and redirect if current URL must be used as https instead of http:
			check_https_url( 'login' );

			if( is_logged_in() )
			{	// If user is logged in the register form should not be displayed,
				// Redirect to the collection home page or to a specified url:
				$Messages->add( T_( 'You are already logged in' ).'.', 'note' );
				$forward_to = param( 'forward_to', 'url', $Blog->gen_blogurl() );
				header_redirect( $forward_to );
			}

			if( $login_Blog = & get_setting_Blog( 'login_blog_ID', $Blog ) && $Blog->ID != $login_Blog->ID )
			{ // Redirect to special blog for login/register actions if it is defined in general settings
				header_redirect( $login_Blog->get( 'registerurl', array( 'glue' => '&' ) ) );
			}

			$seo_page_type = 'Register form';
			$robots_index = false;

			$comment_ID = param( 'comment_ID', 'integer', 0 );
			if( $comment_ID > 0 )
			{	// Suggestion to register for anonymous user:
				$CommentCache = & get_CommentCache();
				$Comment = & $CommentCache->get_by_ID( $comment_ID, false, false );
				if( $Comment && $Comment->get( 'author_email' ) !== '' )
				{	// If comment is really from anonymous user:
					// Display info message:
					$Messages->add( T_('In order to manage all the comments you posted, please create a user account with the same email address.'), 'note' );
					// Prefill the registration form with data from anonymous comment:
					global $dummy_fields;
					set_param( $dummy_fields['email'], $Comment->get( 'author_email' ) );
					set_param( $dummy_fields['login'], $Comment->get( 'author' ) );
					set_param( 'firstname', $Comment->get( 'author' ) );
					$comment_Item = & $Comment->get_Item();
					set_param( 'locale', $comment_Item->get( 'locale' ) );
				}
			}
			break;

		case 'lostpassword':
			// Lost password form:

			// Check and redirect if current URL must be used as https instead of http:
			check_https_url( 'login' );

			if( is_logged_in() )
			{ // If user is logged in the lost password form should not be displayed. In this case redirect to the blog home page.
				$Messages->add( T_( 'You are already logged in' ).'.', 'note' );
				header_redirect( $Blog->gen_blogurl(), false );
			}

			if( $login_Blog = & get_setting_Blog( 'login_blog_ID', $Blog ) && $Blog->ID != $login_Blog->ID )
			{ // Redirect to special blog for login/register actions if it is defined in general settings
				header_redirect( $login_Blog->get( 'lostpasswordurl', array( 'glue' => '&' ) ) );
			}

			$seo_page_type = 'Lost password form';
			$robots_index = false;
			break;

		case 'activateinfo':
			// Activate info page:

			// Check and redirect if current URL must be used as https instead of http:
			check_https_url( 'login' );

			if( !is_logged_in() )
			{ // Redirect to the login page for anonymous users
				$Messages->add( T_( 'You must log in before you can activate your account.' ) );
				header_redirect( get_login_url('cannot see messages'), 302 );
				// will have exited
			}

			if( ! check_user_status( 'can_be_validated' ) )
			{ // don't display activateinfo screen
				$after_email_validation = $Settings->get( 'after_email_validation' );
				if( $after_email_validation == 'return_to_original' )
				{ // we want to return to original page after account activation
					// check if Session 'activateacc.redirect_to' param is still set
					$redirect_to = $Session->get( 'core.activateacc.redirect_to' );
					if( empty( $redirect_to ) )
					{ // Session param is empty try to get general redirect_to param
						$redirect_to = param( 'redirect_to', 'url', '' );
					}
					else
					{ // cleanup validateemail.redirect_to param from session
						$Session->delete('core.activateacc.redirect_to');
					}
				}
				else
				{ // go to after email validation url which is set in the user general settings form
					$redirect_to = $after_email_validation;
				}
				if( empty( $redirect_to ) || preg_match( '#disp=activateinfo#', $redirect_to ) )
				{ // redirect_to is pointing to the activate info display or is empty
					// redirect to referer page
					$redirect_to = '';
				}

				if( check_user_status( 'is_validated' ) )
				{
					$Messages->add( T_( 'Your account has already been activated.' ) );
				}
				header_redirect( $redirect_to, 302 );
				// will have exited
			}

			if( $login_Blog = & get_setting_Blog( 'login_blog_ID', $Blog ) && $Blog->ID != $login_Blog->ID )
			{ // Redirect to special blog for login/register actions if it is defined in general settings
				header_redirect( $login_Blog->get( 'activateinfourl', array( 'glue' => '&' ) ) );
			}
			break;

		case 'profile':
		case 'avatar':
			$action = param_action();
			if( $action == 'crop' && is_logged_in() )
			{ // Check data for crop action:
				global $current_User, $cropped_File;
				$file_ID = param( 'file_ID', 'integer' );
				if( ! ( $cropped_File = $current_User->get_File_by_ID( $file_ID, $error_code ) ) )
				{ // Current user cannot crop this file
					set_param( 'action', '' );
				}
			}
		case 'social':
		case 'register_finish':
		case 'pwdchange':
		case 'userprefs':
		case 'subs':
			if( $disp == 'pwdchange' || $disp == 'register_finish' )
			{	// Check and redirect if current URL must be used as https instead of http:
				check_https_url( 'login' );
			}

			$seo_page_type = 'Special feature page';
			if( $Blog->get_setting( 'special_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}

			// Display messages depending on user email status
			display_user_email_status_message();

			global $current_User, $demo_mode;
			if( $demo_mode && ( $current_User->ID <= 7 ) )
			{	// Demo mode restrictions: users created by install process cannot be edited:
				$Messages->add( T_('You cannot edit the admin and demo users profile in demo mode!'), 'error' );
				// Set action to 'view' in order to switch all form input elements to read mode:
				set_param( 'action', 'view' );
			}
			break;

		case 'users':
			// Check if current user has an access to public list of the users:
			check_access_users_list();

			$seo_page_type = 'Users list';
			$robots_index = false;

			if( ! $Blog->get_setting( 'userdir_enable' ) )
			{	// If user directory is disabled for current Collection:
				global $disp;
				$disp = '404';
				$disp_detail = '404-user-directory-disabled';
			}
			break;

		case 'visits':
			// Check if current user has an access to public list of the users:
			check_access_users_list();

			$seo_page_type = 'User visits';
			$robots_index = false;

			if( ! is_logged_in() || ! $Settings->get( 'enable_visit_tracking' ) )
			{	// Check if visit tracking is enabled and the user is logged in before allowing profile visit display:
				global $disp;
				$disp = '403';
				$disp_detail = '403-visit-tracking-disabled';
			}
			break;

		case 'user':
			// get user_ID because we want it in redirect_to in case we need to ask for login.
			$user_ID = param( 'user_ID', 'integer', '', true );

			// Check if current user has an access to view a profile of the requested user:
			check_access_user_profile( $user_ID );

			if( $Blog->get_setting( 'canonical_user_urls' ) && $redir == 'yes' )
			{	// Check if current user profile URL can be canonical:
				if( empty( $user_ID ) && is_logged_in() )
				{	// Use ID of current User for proper redirect to canonical url like '/user:admin':
					global $current_User;
					$user_ID = $current_User->ID;
				}
				$canonical_url = $Blog->get( 'userurl', array( 'user_ID' => $user_ID, 'glue' => '&' ) );
				// Keep ONLY allowed params from current URL in the canonical URL by configs:
				$canonical_url = url_keep_canonicals_params( $canonical_url );
				if( ! is_same_url( $ReqURL, $canonical_url, $Blog->get_setting( 'http_protocol' ) == 'allow_both' ) )
				{	// Redirect to canonical user profile URL:
					header_redirect( $canonical_url, true );
				}
			}

			// Initialize users list from session cache in order to display prev/next links:
			// It is used to navigate between users
			load_class( 'users/model/_userlist.class.php', 'UserList' );
			global $UserList;
			$UserList = new UserList();
			$UserList->memorize = false;
			$UserList->load_from_Request();

			$seo_page_type = 'User display';
			break;

		case 'anonpost':
			// New item form for anonymous user:
			if( is_logged_in() )
			{	// The logged in user has another page to create a post:
				header_redirect( url_add_param( $Blog->get( 'url', array( 'glue' => '&' ) ), 'disp=edit', '&' ), 302 );
				// will have exited
			}
			elseif( ! $Blog->get_setting( 'post_anonymous' ) )
			{	// Redirect to the login page if current collection doesn't allow to post by anonymous user:
				$redirect_to = url_add_param( $Blog->gen_blogurl(), 'disp=edit' );
				$Messages->add( T_( 'You must log in to create & edit posts.' ) );
				header_redirect( get_login_url( 'cannot create posts', $redirect_to ), 302 );
				// will have exited
			}

			// Check if the requested category can be used for new post on the current collection:
			$ChapterCache = & get_ChapterCache();
			$Chapter = & $ChapterCache->get_by_ID( param( 'cat', 'integer' ), false, false );
			if( ! $Chapter || // Not found
			    $Chapter->get( 'blog_ID' ) != $Blog->ID || // Category from another collection
			    $Chapter->get( 'meta' ) ) // Meta category cannot be used as post category
			{	// Use default category instead of the wrong requested:
				set_param( 'cat', $Blog->get_default_cat_ID() );
			}

			if( $Chapter && $Chapter->get_ItemType() === false )
			{	// Don't allow to post in category without default Item Type:
				$Messages->add( T_('You cannot post here'), 'error' );
				header_redirect( $Chapter->get_permanent_url( NULL, NULL, 1, NULL, '&' ), 302 );
			}
			break;

		case 'edit':
		case 'proposechange':
			global $current_User, $post_ID, $admin_url;

			// Post ID, go from $_GET when we edit a post from Front-office
			//          or from $_POST when we switch from Back-office
			$post_ID = param( 'p', 'integer', ( empty( $post_ID ) ? 0 : $post_ID ), true );

			if( !is_logged_in() )
			{ // Redirect to the login page if not logged in and allow anonymous user setting is OFF
				$redirect_to = url_add_param( $Blog->gen_blogurl(), ( $disp == 'edit' ? 'disp=edit' : 'disp=proposechange&amp;p='.$post_ID ) );
				$Messages->add( T_( 'You must log in to create & edit posts.' ) );
				header_redirect( get_login_url( 'cannot edit posts', $redirect_to ), 302 );
				// will have exited
			}

			if( ! check_user_status( 'can_edit_post' ) )
			{
				if( check_user_status( 'can_be_validated' ) )
				{ // user is logged in but his/her account was not activated yet
					// Redirect to the account activation page
					$Messages->add( T_( 'You must activate your account before you can create & edit posts. <b>See below:</b>' ) );
					header_redirect( get_activate_info_url(), 302 );
					// will have exited
				}

				// Redirect to the blog url for users without messaging permission
				$Messages->add( T_('You are not allowed to create & edit posts!') );
				header_redirect( $Blog->gen_blogurl(), 302 );
			}

			if( $disp == 'edit' )
			{	// Check permission to create/edit post:
				check_item_perm_edit( $post_ID );
			}

			if( ! blog_has_cats( $Blog->ID ) )
			{ // No categories are in this blog
				$error_message = T_('Since this blog has no categories, you cannot post into it.');
				if( check_user_perm( 'blog_cats', 'edit', false, $Blog->ID ) )
				{ // If current user has a permission to create a category
					$error_message .= ' '.sprintf( T_('You must <a %s>create categories</a> first.'), 'href="'.$admin_url.'?ctrl=chapters&amp;blog='.$Blog->ID.'"');
				}
				$Messages->add( $error_message, 'error' );
				header_redirect( $Blog->gen_blogurl(), 302 );
			}

			$cat = param( 'cat', 'integer' );
			if( $cat > 0 &&
			    ( $ChapterCache = & get_ChapterCache() ) &&
			    ( $selected_Chapter = & $ChapterCache->get_by_ID( $cat, false, false ) ) &&
			    ( $selected_Chapter->get_ItemType() === false ) )
			{	// Don't allow to post in category without default Item Type:
				$Messages->add( T_('You cannot post here'), 'error' );
				header_redirect( $selected_Chapter->get_permanent_url( NULL, NULL, 1, NULL, '&' ), 302 );
			}

			// Prepare the 'In-skin editing' / 'In-skin change proposal':
			init_inskin_editing();
			break;

		case 'edit_comment':
			global $current_User, $edited_Comment, $comment_Item, $Item, $comment_title, $comment_content, $display_params;

			// comment ID
			$comment_ID = param( 'c', 'integer', 0, true );

			if( !is_logged_in() )
			{ // Redirect to the login page if not logged in and allow anonymous user setting is OFF
				$redirect_to = url_add_param( $Blog->gen_blogurl(), 'disp=edit_comment' );
				$Messages->add( T_( 'You must log in to edit comments.' ) );
				header_redirect( get_login_url( 'cannot edit comments', $redirect_to ), 302 );
				// will have exited
			}

			if( ! check_user_status( 'can_edit_comment' ) )
			{
				if( check_user_status( 'can_be_validated' ) )
				{ // user is logged in but his/her account was not activated yet
					// Redirect to the account activation page
					$Messages->add( T_( 'You must activate your account before you can edit comments. <b>See below:</b>' ) );
					header_redirect( get_activate_info_url(), 302 );
					// will have exited
				}

				// Redirect to the blog url for users without messaging permission
				$Messages->add( 'You are not allowed to edit comments!' );
				header_redirect( $Blog->gen_blogurl(), 302 );
			}

			if( empty( $comment_ID ) )
			{ // Can't edit a not exisiting comment
				$Messages->add( 'Invalid comment edit URL!' );
				global $disp;
				$disp = 404;
				break;
			}

			$CommentCache = & get_CommentCache();
			$edited_Comment = $CommentCache->get_by_ID( $comment_ID );
			$comment_Item = $edited_Comment->get_Item();

			if( ! check_user_perm( 'comment!CURSTATUS', 'edit', false, $edited_Comment ) )
			{ // If User has no permission to edit comments with this comment status:
				$Messages->add( 'You are not allowed to edit the previously selected comment!' );
				header_redirect( $Blog->gen_blogurl(), 302 );
			}

			$comment_title = '';
			$comment_content = htmlspecialchars_decode( $edited_Comment->content );

			// Format content for editing, if we were not already in editing...
			$Plugins_admin = & get_Plugins_admin();
			$comment_Item->load_Blog();
			$params = array( 'object_type' => 'Comment', 'object_Blog' => & $comment_Item->Blog );
			$Plugins_admin->unfilter_contents( $comment_title /* by ref */, $comment_content /* by ref */, $edited_Comment->get_renderers_validated(), $params );

			$Item = $comment_Item;

			$display_params = array();

			// Restrict comment status by parent item:
			$edited_Comment->restrict_status();
			break;

		case 'useritems':
		case 'usercomments':
			global $display_params, $viewed_User;

			// get user_ID because we want it in redirect_to in case we need to ask for login.
			$user_ID = param( 'user_ID', 'integer', NULL, true );

			if( $user_ID === NULL && is_logged_in() )
			{	// Use current logged in User if it is not specified in param:
				$user_ID = $current_User->ID;
			}

			if( empty( $user_ID ) )
			{
				bad_request_die( sprintf( T_('Parameter &laquo;%s&raquo; is required!'), 'user_ID' ) );
			}
			// set where to redirect in case of error
			$error_redirect_to = empty( $Blog ) ? $baseurl : $Blog->gen_blogurl();

			if( !is_logged_in() )
			{ // Redirect to the login page if not logged in and allow anonymous user setting is OFF
				$Messages->add( T_('You must log in to view this user profile.') );
				header_redirect( get_login_url( 'cannot see user' ), 302 );
				// will have exited
			}

			if( is_logged_in() && ( !check_user_status( 'can_view_user', $user_ID ) ) )
			{ // user is logged in, but his/her status doesn't permit to view user profile
				if( check_user_status( 'can_be_validated' ) )
				{ // user is logged in but his/her account is not active yet
					// Redirect to the account activation page
					$Messages->add( T_('You must activate your account before you can view this user profile. <b>See below:</b>') );
					header_redirect( get_activate_info_url(), 302 );
					// will have exited
				}

				$Messages->add( T_('Your account status currently does not permit to view this user profile.') );
				header_redirect( $error_redirect_to, 302 );
				// will have exited
			}

			if( !empty( $user_ID ) )
			{
				$UserCache = & get_UserCache();
				$viewed_User = $UserCache->get_by_ID( $user_ID, false );

				if( empty( $viewed_User ) )
				{
					$Messages->add( T_('The requested user does not exist!') );
					header_redirect( $error_redirect_to );
					// will have exited
				}

				if( $viewed_User->check_status( 'is_closed' ) )
				{
					$Messages->add( T_('The requested user account is closed!') );
					header_redirect( $error_redirect_to );
					// will have exited
				}
			}

			$display_params = !empty( $Skin ) ? $Skin->get_template( 'Results' ) : NULL;

			if( $disp == 'useritems' )
			{ // Init items list
				global $user_ItemList;

				$useritems_Blog = NULL;
				$user_ItemList = new ItemList2( $useritems_Blog, NULL, NULL, NULL, 'ItemCache', 'useritems_' );
				$user_ItemList->load_from_Request();
				$user_ItemList->set_filters( array(
						'authors' => $user_ID,
					), true, true );
				$user_ItemList->query();
			}
			else // $disp == 'usercomments'
			{ // Init comments list
				global $user_CommentList;

				$user_CommentList = new CommentList2( NULL, NULL, 'CommentCache', 'usercmts_' );
				$user_CommentList->load_from_Request();
				$user_CommentList->set_filters( array(
						'author_IDs' => $user_ID,
					), true, true );
				$user_CommentList->query();
			}
			break;

		case 'comments':
			if( !$Blog->get_setting( 'comments_latest' ) )
			{ // If latest comments page is disabled - Display 404 page with error message
				$Messages->add( T_('This feature is disabled.'), 'error' );
				global $disp;
				$disp = '404';
			}
			break;

		case 'closeaccount':
			global $disp;
			if( ! $Settings->get( 'account_close_enabled' ) )
			{	// If an account closing page is disabled - Display 404 page with error message:
				$disp = is_logged_in() ? 'profile' : 'login';
				$Messages->add( T_('The account closing feature is disabled.'), 'error' );
			}
			elseif( ! is_logged_in() && ! $Session->get( 'account_closing_success' ) )
			{	// Don't display this message for not logged in users, except of one case to display a bye message after account closing:
				$disp = 'login';
				$Messages->add( T_('You must log in before you can close your account.'), 'error' );
			}
			elseif( check_user_perm( 'users', 'edit', false ) )
			{	// Don't allow admins close own accounts from front office:
				$disp = 'profile';
				$Messages->add( T_('You have user moderation privileges. In order to prevent mistakes, you cannot close your own account. Please ask the admin (or another admin) to remove your user moderation privileges before closing your account.'), 'error' );
			}
			elseif( $Session->get( 'account_close_reason' ) )
			{
				global $account_close_reason;
				$account_close_reason = $Session->get( 'account_close_reason' );
				$Session->delete( 'account_close_reason' );
			}
			elseif( $Session->get( 'account_closing_success' ) )
			{ // User has closed the account
				global $account_closing_success;
				$account_closing_success = $Session->get( 'account_closing_success' );
				// Unset this temp session var to don't display the message twice
				$Session->delete( 'account_closing_success' );
				if( is_logged_in() )
				{ // log out current User
					logout();
				}
			}
			break;

		case 'tags':
			$seo_page_type = 'Tags';
			if( $Blog->get_setting( $disp.'_noindex' ) )
			{	// We prefer robots not to index these pages:
				$robots_index = false;
			}
			break;

		case 'compare':
			$items = trim( param( 'items', '/^[\d,]*$/' ), ',' );
			if( ! empty( $items ) )
			{	// Check if at least one item exist in DB:
				$ItemCache = & get_ItemCache();
				$items = $ItemCache->load_list( explode( ',', $items ) );
			}
			if( empty( $items ) )
			{	// Display 404 page when no items to compare:
				global $disp;
				$disp = '404';
				$Messages->add( T_('The requested items don\'t exist.'), 'error' );
			}
			break;
	}

	// NOTE: Call the update item read status only after complete initialization of $disp_detail in the code above,
	//       because the $disp_detail is used to select correct intro Item:
	if( ( $disp == 'posts' || $disp == 'front' ) &&
	    ( $featured_intro_Item = & get_featured_Item( $disp, NULL, true ) ) )
	{	// We assume the current user will have read the entire intro Item and all its current comments:
		$featured_intro_Item->update_read_timestamps( true, true );
	}

	// Enable shortcut keys:
	if( is_logged_in() )
	{
		init_hotkeys_js( 'blog' );
	}

	// Add hreflang tags for Items with several versions:
	if( $version_Item = & get_current_Item() )
	{	// If current Item is detected
		$other_version_items = $version_Item->get_other_version_items();
		if( ! empty( $other_version_items ) )
		{	// If at least one other version exists for current Item:
			// Add also current Item as first:
			array_unshift( $other_version_items, $version_Item );
			$other_version_locales = array();
			$version_lang_keys = array();
			foreach( $other_version_items as $o => $other_version_Item )
			{	// Check to exclude what items cannot be displayed for hreflang tag:
				if( in_array( $other_version_Item->get( 'locale' ), $other_version_locales ) ||
				    ! $other_version_Item->can_be_displayed() )
				{	// Don't add hreflang tag with same locale
					// or if the Item cannot be displayed for current user on front-office
					unset( $other_version_items[ $o ] );
				}
				$other_version_locales[] = $other_version_Item->get( 'locale' );
				// Count different country locales with same language:
				$version_lang_key = substr( $other_version_Item->get( 'locale' ), 0, 2 );
				$version_lang_keys[ $version_lang_key ] = isset( $version_lang_keys[ $version_lang_key ] ) ? true : false;
			}
			if( count( $other_version_items ) > 1 )
			{	// Add hreflang tag only when at least two Items can be displayed:
				foreach( $other_version_items as $other_version_Item )
				{
					$version_lang_key = substr( $other_version_Item->get( 'locale' ), 0, 2 );
					add_headline( '<link rel="alternate" '
						// Use only language code like 'en' when it is a single, otherwise use full locale code with country code like 'en-US':
						.'hreflang="'.format_to_output( ( $version_lang_keys[ $version_lang_key ] ? $other_version_Item->get( 'locale' ) : $version_lang_key ), 'htmlattr' ).'" '
						.'href="'.format_to_output( $other_version_Item->get_permanent_url( '', '', '&' ), 'htmlattr' ).'">' );
				}
			}
		}
	}

	if( $Blog->get_setting( 'front_disp' ) == $disp )
	{	// This is the default/front collection page:
		$seo_page_type = 'Default page';
		if( $Blog->get_setting( 'default_noindex' ) )
		{	// We prefer robots not to index archive pages:
			$robots_index = false;
		}
	}

	$Debuglog->add('skin_init: $disp='.$disp. ' / $disp_detail='.$disp_detail.' / $seo_page_type='.$seo_page_type, 'skins' );

	// Make this switch block special only for 403 and 404 pages:
	switch( $disp )
	{
		case '403':
			// We have a 403 forbidden content error:
			header_http_response( '403 Forbidden' );
			$robots_index = false;
			break;

		case '404':
			// We have a 404 unresolved content error
			// How do we want do deal with it?
			skin_404_header();
			// This MAY or MAY not have exited -- will exit on 30x redirect, otherwise will return here.
			// Just in case some dumb robot needs extra directives on this:
			$robots_index = false;
			// Load functions to work with search results:
			load_funcs( 'collections/_search.funcs.php' );
			break;
	}

	global $Hit, $check_browser_version;
	if( $check_browser_version && $Hit->get_browser_version() > 0 && $Hit->is_IE( 9, '<' ) )
	{	// Display info message if browser IE < 9 version and it is allowed by config var:
		global $debug;
		$Messages->add( T_('Your web browser is too old. For this site to work correctly, we recommend you use a more recent browser.'), 'note' );
		if( $debug )
		{
			$Messages->add( 'User Agent: '.$Hit->get_user_agent(), 'note' );
		}
	}

	// dummy var for backward compatibility with versions < 2.4.1 -- prevents "Undefined variable"
	global $global_Cache, $credit_links;
	$credit_links = $global_Cache->getx( 'creds' );

	$Timer->pause( 'skin_init' );

	// Check if user is logged in with a not active account, and display an error message if required
	check_allow_disp( $disp );

	// initialize Blog enabled widgets, before displaying anything
	init_blog_widgets( $Blog->ID );

	// Initialize displaying....
	$Timer->start( 'Skin:display_init' );
	$Skin->display_init();
	$Timer->pause( 'Skin:display_init' );

	// Send the predefined cookies:
	evo_sendcookies();

	// Send default headers:
	// See comments inside of this function:
	headers_content_mightcache( 'text/html' );		// In most situations, you do NOT want to cache dynamic content!
	// Never allow Messages to be cached!
	if( $Messages->count() && ( !empty( $PageCache ) ) )
	{ // Abort PageCache collect
		$PageCache->abort_collect();
	}
}


/**
 * Get site Skin global object
 *
 * @return object Site Skin
 */
function & get_site_Skin()
{
	global $site_Skin;

	if( ! isset( $site_Skin ) )
	{	// Initialize site Skin only first time:

		global $Settings;
		if( ! $Settings->get( 'site_skins_enabled' ) )
		{	// Site skins are not enabled:
			$site_Skin = NULL;
			return $site_Skin;
		}

		global $Session;
		if( ! empty( $Session ) )
		{	// Get site skin Id depending on current session:
			if( $Session->is_mobile_session() )
			{	// Mobile session:
				$skin_ID = $Settings->get( 'mobile_skin_ID' );
			}
			elseif( $Session->is_tablet_session() )
			{	// Tablet session:
				$skin_ID = $Settings->get( 'tablet_skin_ID' );
			}
			elseif( $Session->is_alt_session() )
			{	// Session for alternative skin:
				$skin_ID = $Settings->get( 'alt_skin_ID' );
			}
		}
		if( empty( $skin_ID ) )
		{	// Use normal skin ID by default when mobile, tablet and alt skins are not defined for site:
			$skin_ID = $Settings->get( 'normal_skin_ID' );
		}

		// Try to get site Skin from DB by ID:
		$SkinCache = & get_SkinCache();
		$site_Skin = $SkinCache->get_by_ID( $skin_ID, false, false );
	}

	return $site_Skin;
}


/**
 * Initalize site Skin
 */
function siteskin_init()
{
	if( $site_Skin = & get_site_Skin() )
	{	// Initialize site skin:
		$site_Skin->siteskin_init();
	}
}


/**
 * Initialize skin for AJAX request
 *
 * @param string Skin name
 * @param string What are we going to display. Most of the time the global $disp should be passed.
 */
function skin_init_ajax( $skin_name, $disp )
{
	if( is_ajax_content() )
	{	// AJAX request
		if( empty( $skin_name ) )
		{	// Don't initialize without skin name
			return false;
		}

		global $ads_current_skin_path, $skins_path;

		// Init path for current skin
		$ads_current_skin_path = $skins_path.$skin_name.'/';

		// This is the main template; it may be used to display very different things.
		// Do inits depending on current $disp:
		skin_init( $disp );
	}

	return true;
}


/**
 * Init some global variables used by skins
 * Note: This initializations were removed from the _main.inc.php, because it should not be part of the main init.
 */
function skin_init_global_vars()
{
	global $credit_links, $francois_links, $fplanque_links, $skin_links, $skinfaktory_links;

	$credit_links = array();
	$francois_links = array(
		'fr' => array( 'http://fplanque.net/', array( array( 78, 'Fran&ccedil;ois'),  array( 100, 'Francois') ) ),
		'' => array( 'http://fplanque.com/', array( array( 78, 'Fran&ccedil;ois'),  array( 100, 'Francois') ) )
	);
	$fplanque_links = array(
		'fr' => array( 'http://fplanque.net/', array( array( 78, 'Fran&ccedil;ois Planque'),  array( 100, 'Francois Planque') ) ),
		'' => array( 'http://fplanque.com/', array( array( 78, 'Fran&ccedil;ois Planque'),  array( 100, 'Francois Planque') ) )
	);
	$skin_links = array(
		'' => array( 'http://skinfaktory.com/', array( array( 15, 'b2evo skin'), array( 20, 'b2evo skins'), array( 35, 'b2evolution skin'), array( 40, 'b2evolution skins'), array( 55, 'Blog skin'), array( 60, 'Blog skins'), array( 75, 'Blog theme'),array( 80, 'Blog themes'), array( 95, 'Blog template'), array( 100, 'Blog templates') ) ),
	);
	$skinfaktory_links = array(
		'' => array(
			array( 73, 'http://evofactory.com/', array( array( 61, 'Evo Factory'), array( 68, 'EvoFactory'), array( 73, 'Evofactory') ) ),
			array( 100, 'http://skinfaktory.com/', array( array( 92, 'Skin Faktory'), array( 97, 'SkinFaktory'), array( 99, 'Skin Factory'), array( 100, 'SkinFactory') ) ),
		)
	);
}


/**
 * Tells if we are on the default blog page / front page.
 *
 * @return boolean
 */
function is_default_page()
{
	global $is_front;
	return $is_front;
}


/**
 * Template tag. Include a sub-template at the current position
 *
 */
function skin_include( $template_name, $params = array() )
{
	if( is_ajax_content( $template_name ) )
	{ // When we request ajax content for results table we need to hide wrapper data (header, footer & etc)
		return;
	}

	global $skins_path, $ads_current_skin_path, $disp;

	// Globals that may be needed by the template:
	global $Collection, $Blog, $MainList, $Item;
	global $Plugins, $Skin;
	global $current_User, $Hit, $Session, $Settings, $debug;
	global $skin_url;
	global $credit_links, $skin_links, $francois_links, $fplanque_links, $skinfaktory_links;
	/**
	* @var Log
	*/
	global $Debuglog;
	global $Timer;

	$timer_name = 'skin_include('.$template_name.')';
	$Timer->resume( $timer_name );

	if( ! empty( $params['Item'] ) )
	{	// Get Item from params:
		$Item = $params['Item'];
	}

	if( $template_name == '$disp$' )
	{ // This is a special case.
		// We are going to include a template based on $disp:

		// Default display handlers:
		$disp_handlers = array(
				'disp_403'                   => '_403_forbidden.disp.php',
				'disp_404'                   => '_404_not_found.disp.php',
				'disp_access_denied'         => '_access_denied.disp.php',
				'disp_access_requires_login' => '_access_requires_login.disp.php',
				'disp_content_requires_login'=> '_content_requires_login.disp.php',
				'disp_activateinfo'          => '_activateinfo.disp.php',
				'disp_anonpost'              => '_anonpost.disp.php',
				'disp_arcdir'                => '_arcdir.disp.php',
				'disp_catdir'                => '_catdir.disp.php',
				'disp_closeaccount'          => '_closeaccount.disp.php',
				'disp_comments'              => '_comments.disp.php',
				'disp_download'              => '_download.disp.php',
				'disp_edit'                  => '_edit.disp.php',
				'disp_proposechange'         => '_proposechange.disp.php',
				'disp_edit_comment'          => '_edit_comment.disp.php',
				'disp_feedback-popup'        => '_feedback_popup.disp.php',
				'disp_flagged'               => '_flagged.disp.php',
				'disp_mustread'              => '_mustread.disp.php',
				'disp_front'                 => '_front.disp.php',
				'disp_help'                  => '_help.disp.php',
				'disp_login'                 => '_login.disp.php',
				'disp_lostpassword'          => '_lostpassword.disp.php',
				'disp_mediaidx'              => '_mediaidx.disp.php',
				'disp_messages'              => '_messages.disp.php',
				'disp_module_form'           => '_module_form.disp.php',
				'disp_msgform'               => '_msgform.disp.php',
				'disp_page'                  => '_page.disp.php',
				'disp_widget_page'           => '_widget_page.disp.php',
				'disp_postidx'               => '_postidx.disp.php',
				'disp_posts'                 => '_posts.disp.php',
				'disp_profile'               => '_profile.disp.php',
				'disp_avatar'                => '_profile.disp.php',
				'disp_pwdchange'             => '_profile.disp.php',
				'disp_userprefs'             => '_profile.disp.php',
				'disp_subs'                  => '_profile.disp.php',
				'disp_register_finish'       => '_profile.disp.php',
				'disp_visits'                => '_visits.disp.php',
				'disp_register'              => '_register.disp.php',
				'disp_search'                => '_search.disp.php',
				'disp_single'                => '_single.disp.php',
				'disp_sitemap'               => '_sitemap.disp.php',
				'disp_tags'                  => '_tags.disp.php',
				'disp_terms'                 => '_terms.disp.php',
				'disp_threads'               => '_threads.disp.php',
				'disp_contacts'              => '_threads.disp.php',
				'disp_user'                  => '_user.disp.php',
				'disp_useritems'             => '_useritems.disp.php',
				'disp_usercomments'          => '_usercomments.disp.php',
				'disp_users'                 => '_users.disp.php',
				'disp_compare'               => '_compare.disp.php',
			);

		if( is_pro() )
		{	// Additional disp handler for PRO version:
			$disp_handlers['disp_social'] = '_profile.disp.php';
		}

		// Add plugin disp handlers:
		if( $disp_Plugins = $Plugins->get_list_by_event( 'GetHandledDispModes' ) )
		{
			foreach( $disp_Plugins as $disp_Plugin )
			{ // Go through whole list of plugins providing disps
				if( $plugin_modes = $Plugins->call_method( $disp_Plugin->ID, 'GetHandledDispModes', $disp_handlers ) )
				{ // plugin handles some custom disp modes
					foreach( $plugin_modes as $plugin_mode )
					{
						$disp_handlers[$plugin_mode] = '#'.$disp_Plugin->ID;
					}
				}
			}
		}

		// Allow skin overrides as well as additional disp modes (This can be used in the famou shopping cart scenario...)
		$disp_handlers = array_merge( $disp_handlers, $params );

		if( !isset( $disp_handlers['disp_'.$disp] ) )
		{
			global $Messages;
			$Messages->add( sprintf( 'Unhandled disp type [%s]', htmlspecialchars( $disp ) ) );
			$Messages->display();
			$Timer->pause( $timer_name );
			$disp = '404';
		}

		$template_name = $disp_handlers['disp_'.$disp];

		if( empty( $template_name ) )
		{	// The caller asked not to display this handler
			$Timer->pause( $timer_name );
			return;
		}

		if( $template_name[0] != '#' && // if template is not handled by plugins
		    ( $disp == 'single' || $disp == 'page' ) &&
		    ! empty( $Item ) && ( $ItemType = & $Item->get_ItemType() ) )
		{	// Get template name for the current Item if it is defined by Item Type:
			$item_type_template_name = $ItemType->get( 'template_name' );
			if( ! empty( $item_type_template_name ) )
			{	// The item type has a specific template for this display:
				$item_type_template_name = '_'.$item_type_template_name.'.disp.php';
				if( ( $Skin->get_api_version() == 7 && file_exists( $ads_current_skin_path.$Blog->get( 'type' ).'/'.$item_type_template_name ) ) ||
						file_exists( $ads_current_skin_path.$item_type_template_name ) ||
						skin_fallback_path( $item_type_template_name ) )
				{	// Use template file name of the Item Type only if it exists:
					$template_name = $item_type_template_name;
				}
			}
		}
	}


	// DECIDE WHAT TO INCLUDE:
	if( $template_name[0] == '#' )
	{ // This disp mode is handled by a plugin:
		$debug_info = 'Call plugin';
		$disp_handled = 'plugin';
	}
	elseif( $Skin->get_api_version() == 7 && file_exists( $ads_current_skin_path.$Blog->get( 'type' ).'/'.$template_name ) )
	{ // The skin has a customized handler, use that one instead:
		$file = $ads_current_skin_path.$Blog->get( 'type' ).'/'.$template_name;
		$debug_info = '<b>Theme template for collection kind</b>: '.rel_path_to_base( $file );
		$disp_handled = 'custom';
	}
	elseif( file_exists( $ads_current_skin_path.$template_name ) )
	{ // The skin has a customized handler, use that one instead:
		$file = $ads_current_skin_path.$template_name;
		$debug_info = '<b>Skin template</b>: '.rel_path_to_base( $file );
		$disp_handled = 'custom';
	}
	elseif( $fallback_template_path = skin_fallback_path( $template_name ) )
	{ // Use the default/fallback template:
		$file = $fallback_template_path;
		$debug_info = '<b>Fallback to</b>: '.rel_path_to_base( $file );
		$disp_handled = 'fallback';
	}
	else
	{
		$disp_handled = false;
	}

	// Do we want a visible container for DEBUG/DEV ?:
	if( strpos( $template_name, '_html_' ) !== false )
	{	// We're outside of the page body: NEVER display wrap this include with a <div>
		$display_includes = false;
	}
	else
	{	// We may wrap with a <div>:
		$display_includes = ( $debug == 2 ) || ( is_logged_in() && $Session->get( 'display_includes_'.$Blog->ID ) );
	}
	if( $display_includes )
	{ // Wrap the include with a visible div:
		echo '<div class="dev-blocks dev-blocks--include">';
		echo '<div class="dev-blocks-name">';
		if( empty( $item_type_template_name ) )
		{ // Default template
			echo 'skin_include( <b>'.$template_name.'</b> )';
		}
		else
		{ // Custom template
			echo '<b>CUSTOM</b> skin_include( <b>'.$item_type_template_name.'</b> )';
		}
		echo ' -> '.$debug_info.'</div>';
	}

	switch( $disp_handled )
	{
		case 'plugin':
			// This disp mode is handled by a plugin:
			$plug_ID = substr( $template_name, 1 );
			$disp_params = array( 'disp' => $disp );
			$Plugins->call_method( $plug_ID, 'HandleDispMode', $disp_params );
			break;

		case 'custom':			// The skin has a customized handler, use that one instead:
		case 'fallback':		// Use the default/fallback template:
			$Debuglog->add('skin_include ('.($Item ? 'Item #'.$Item->ID : '-').'): '.$file, 'skins');
			require $file;
			break;
	}

	if( ! $disp_handled )
	{ // nothing handled the disp mode
		printf( '<div class="skin_error">Sub template [%s] not found.</div>', $template_name );
		if( !empty($current_User) && $current_User->level == 10 )
		{
			printf( '<div class="skin_error">User level 10 help info: [%s]</div>', $ads_current_skin_path.$template_name );
		}
	}

	if( $display_includes )
	{ // End of visible container:
		// echo get_icon( 'pixel', 'imgtag', array( 'class' => 'clear' ) );
		echo '</div>';
	}

	$Timer->pause( $timer_name );
}


/**
 * Get file path to fallback file depending on skin API version
 *
 * @param string Template name
 * @param integer Skin API version, NULL - to get API version of the current Skin
 * @return string|FALSE File path OR FALSE if fallback file doesn't exist
 */
function skin_fallback_path( $template_name, $skin_api_version = NULL )
{
	global $Skin, $basepath;

	if( $skin_api_version === NULL && ! empty( $Skin ) )
	{	// Get API version of the current skin:
		$skin_api_version = $Skin->get_api_version();
	}

	if( $skin_api_version == 7 )
	{	// Check fallback file for v7 API skin:
		$fallback_path = $basepath.'skins_fallback_v7/'.$template_name;
		if( file_exists( $fallback_path ) )
		{
			return $fallback_path;
		}
	}

	if( $skin_api_version >= 6 )
	{	// Check fallback file for v6 API skin:
		$fallback_path = $basepath.'skins_fallback_v6/'.$template_name;
		if( file_exists( $fallback_path ) )
		{
			return $fallback_path;
		}
	}

	// Check fallback file for v5 API skin:
	$fallback_path = $basepath.'skins_fallback_v5/'.$template_name;
	if( file_exists( $fallback_path ) )
	{
		return $fallback_path;
	}

	// No fallback file
	return false;
}


/**
 * Get file path to template file
 *
 * @param string Template name
 * @return string|FALSE File path OR FALSE if fallback file doesn't exist
 */
function skin_template_path( $template_name )
{
	global $Skin, $ads_current_skin_path;

	if( ! empty( $Skin ) && file_exists( $ads_current_skin_path.$template_name ) )
	{ // Template file exists for the current skin
		return $ads_current_skin_path.$template_name;
	}
	elseif( $fallback_path = skin_fallback_path( $template_name ) )
	{ // Falback file exists
		return $fallback_path;
	}

	return false;
}


/**
 * Template tag.
 *
 * @param string Template name
 * @param array Params
 */
function siteskin_include( $template_name, $params = array() )
{
	global $Settings, $skins_path, $Collection, $Blog, $baseurl;

	if( ! $Settings->get( 'site_skins_enabled' ) )
	{	// Site skins are not enabled:
		return;
	}

	if( is_ajax_content( $template_name ) )
	{ // When we request ajax content for results table we need to hide wrapper data (header, footer & etc)
		return;
	}

	// Globals that may be needed by the template:
	global $current_User, $Hit, $Session, $Settings, $debug;
	global $skin_url;
	global $credit_links, $skin_links, $francois_links, $fplanque_links, $skinfaktory_links;
	/**
	* @var Log
	*/
	global $Debuglog;
	global $Timer;

	$timer_name = 'siteskin_include('.$template_name.')';
	$Timer->resume( $timer_name );

	// Get site Skin:
	$site_Skin = & get_site_Skin();

	if( $site_Skin && file_exists( $site_Skin->get_path().$template_name ) )
	{	// Use site skin template:
		$file = $site_Skin->get_path().$template_name;
		$debug_info = '<b>Site Skin template</b>: '.rel_path_to_base( $file );
		$disp_handled = 'skin';
	}
	elseif( $fallback_template_path = skin_fallback_path( $template_name ) )
	{	// Use the default/fallback template:
		$file = $fallback_template_path;
		$debug_info = '<b>Site Skin Fallback to</b>: '.rel_path_to_base( $file );
		$disp_handled = 'fallback';
	}
	else
	{	// Site skin is wrong or the requested template file is not found in current site skin:
		$disp_handled = false;
	}


	// Do we want a visible container for DEBUG/DEV ?:
	if( strpos( $template_name, '_html_' ) !== false ||  strpos( $template_name, '_init.' ) !== false )
	{	// We're outside of the page body: NEVER display wrap this include with a <div>
		$display_includes = false;
	}
	elseif( isset( $Session ) )
	{	// We may wrap with a <div>:
		$display_includes = ( $debug == 2 ) || ( is_logged_in() && $Session->get( 'display_includes_'.( empty( $Blog ) ? 0 : $Blog->ID ) ) );
	}
	else
	{	// Request without defined $Session, Don't display the includes:
		$display_includes = false;
	}
	if( $display_includes )
	{	// Wrap the include with a visible div:
		echo '<div class="dev-blocks dev-blocks--siteinclude">';
		echo '<div class="dev-blocks-name">siteskin_include( <b>'.$template_name.'</b> ) -> '.$debug_info.'</div>';
	}


	if( $disp_handled )
	{	// Include site skin template file:
		$Debuglog->add('siteskin_include: '.rel_path_to_base( $file ), 'skins');
		require $file;
	}
	else
	{	// Nothing handled the display:
		printf( '<div class="skin_error">Site skin template [%s] not found.</div>', $template_name );
	}


	if( $display_includes )
	{	// End of visible container:
		echo '</div>';
	}


	$Timer->pause( $timer_name );
}


/**
 * Template tag. Output HTML base tag to current skin.
 *
 * This is needed for relative css and img includes.
 */
function skin_base_tag()
{
	global $skins_url, $skin, $Collection, $Blog, $disp;

	if( ! empty( $Blog ) )
	{	// We are displaying a blog:
		if( ! empty( $skin ) )
		{	// We are using a skin:
			$base_href = $Blog->get_local_skins_url().$skin.'/';
		}
		else
		{ // No skin used:
			$base_href = $Blog->gen_baseurl();
		}
	}
	else
	{	// We are displaying a general page that is not specific to a blog:
		global $baseurl;
		$base_href = $baseurl;
	}

	$target = NULL;
	if( !empty($disp) && strpos( $disp, '-popup' ) )
	{	// We are (normally) displaying in a popup window, we need most links to open a new window!
		$target = '_blank';
	}

	base_tag( $base_href, $target );
}


/**
 * Template tag
 *
 * Note for future mods: we do NOT want to repeat identical content on multiple pages.
 */
function skin_description_tag()
{
	global $Collection, $Blog, $disp, $disp_detail, $MainList, $Chapter, $is_front;

	$r = '';

	if( $is_front )
	{	// Use default description:
		if( ! empty( $Blog ) )
		{	// Description for the blog:
			$r = $Blog->get( 'shortdesc' );
		}
	}
	elseif( in_array( $disp_detail, array( 'posts-cat', 'posts-topcat-intro', 'posts-topcat-nointro', 'posts-subcat-intro', 'posts-subcat-nointro' ) ) )
	{
		if( $Blog->get_setting( 'categories_meta_description' ) && ( ! empty( $Chapter ) ) )
		{
			$r = $Chapter->get( 'description' );
		}
	}
	elseif( in_array( $disp, array( 'single', 'page' ) ) )
	{	// custom desc for the current single post:
		$Item = & $MainList->get_by_idx( 0 );
		if( is_null( $Item ) )
		{	// This is not an object (happens on an invalid request):
			return;
		}

		$r = $Item->get_metadesc();

		if( empty( $r )&& $Blog->get_setting( 'excerpts_meta_description' ) )
		{	// Fall back to excerpt for the current single post:
			// Replace line breaks with single space
			$r = preg_replace( '|[\r\n]+|', ' ', $Item->get('excerpt') );
		}
	}

	if( !empty($r) )
	{
		echo '<meta name="description" content="'.format_to_output( $r, 'htmlattr' )."\" />\n";
	}
}


/**
 * Template tag
 *
 * Note for future mods: we do NOT want to repeat identical content on multiple pages.
 */
function skin_keywords_tag()
{
	global $Collection, $Blog, $is_front, $disp, $MainList;

	$r = '';

	if( $is_front )
	{	// Use default keywords:
		if( !empty($Blog) )
		{
			$r = $Blog->get('keywords');
		}
	}
	elseif( in_array( $disp, array( 'single', 'page' ) ) )
	{	// custom keywords for the current single post:
		$Item = & $MainList->get_by_idx( 0 );
		if( is_null( $Item ) )
		{	// This is not an object (happens on an invalid request):
			return;
		}

		$r = $Item->get_metakeywords();


		if( empty( $r ) && $Blog->get_setting( 'tags_meta_keywords' ) )
		{	// Fall back to tags for the current single post:
			$r = implode( ', ', $Item->get_tags() );
		}

	}

	if( !empty($r) )
	{
		echo '<meta name="keywords" content="'.format_to_output( $r, 'htmlattr' )."\" />\n";
	}
}


/**
 * Template tag
 *
 * Note for future mods: we do NOT want to repeat identical content on multiple pages.
 */
function skin_favicon_tag()
{
	global $Collection, $Blog;

	if( ! empty( $Blog ) )
	{
		if( $favicon_File = $Blog->get( 'collection_favicon') )
		{
			if( $favicon_File->exists() && $favicon_File->is_image() )
			{
				$favicon_Filetype = $favicon_File->get_Filetype();
				echo sprintf( '<link rel="icon" type="%s" href="%s">', $favicon_Filetype->mimetype, $favicon_File->get_url() );
			}
		}
	}
}


/**
 * Template tag
 *
 * Used to print out open graph tags
 */
function skin_opengraph_tags()
{
	global $Collection, $Blog, $disp, $MainList;

	if( empty( $Blog ) ||
	    ! $Blog->get_setting( 'tags_open_graph' ) ||
	    in_array( $disp, array( 'content_requires_login', 'access_requires_login', 'access_denied' ) ) )
	{	// Open Graph tags are not allowed for current Collection or for current disp:
		return;
	}

	switch( $disp )
	{
		case 'single':
		case 'page':
			$Item = & $MainList->get_by_idx( 0 );

			// Get info for og:image tag
			if( is_null( $Item ) )
			{ // This is not an object (happens on an invalid request):
				return;
			}

			echo '<meta property="og:title" content="'.format_to_output( $Item->get( 'title' ), 'htmlattr' )."\" />\n";
			echo '<meta property="og:url" content="'.format_to_output( $Item->get_url( 'public_view' ), 'htmlattr' )."\" />\n";
			echo '<meta property="og:description" content="'.format_to_output( $Item->get_excerpt2(), 'htmlattr' )."\" />\n";
			echo '<meta property="og:site_name" content="'.format_to_output( $Item->get_Blog()->get( 'name' ), 'htmlattr' )."\" />\n";

			if( $Item->get_type_setting( 'use_coordinates' ) != 'never' )
			{
				if( $latitude = $Item->get_setting( 'latitude' ) )
				{
					echo '<meta property="og:latitude" content="'.$latitude."\" />\n";
				}
				if( $longitude = $Item->get_setting( 'longitude' ) )
				{
					echo '<meta property="og:latitude" content="'.$longitude."\" />\n";
				}
			}
			break;

		case 'posts':
			$intro_Item = & get_featured_Item( $disp, NULL, true );
			if( $intro_Item )
			{
				if( $intro_Item->is_intro() )
				{
					echo '<meta property="og:title" content="'.format_to_output( $intro_Item->get( 'title' ), 'htmlattr' )."\" />\n";
					echo '<meta property="og:url" content="'.format_to_output( $intro_Item->get_url( 'public_view' ), 'htmlattr' )."\" />\n";
					echo '<meta property="og:description" content="'.format_to_output( $intro_Item->get_excerpt2(), 'htmlattr' )."\" />\n";
					echo '<meta property="og:site_name" content="'.format_to_output( $intro_Item->get_Blog()->get( 'name' ), 'htmlattr' )."\" />\n";
					break;
				}
			}

		default:
			if( $Blog )
			{
				echo '<meta property="og:title" content="'.format_to_output( $Blog->name, 'htmlattr' )."\" />\n";
				echo '<meta property="og:url" content="'.format_to_output( $Blog->get( 'url' ), 'htmlattr' )."\" />\n";
				echo '<meta property="og:description" content="'.format_to_output( $Blog->longdesc, 'htmlattr' )."\" />\n";
				echo '<meta property="og:site_name" content="'.format_to_output( $Blog->get( 'name' ), 'htmlattr' )."\" />\n";
			}
	}

	$og_image_File = get_social_tag_image_file( $disp );
	if( ! empty( $og_image_File ) )
	{ // Open Graph image tag
		echo '<meta property="og:image" content="'.format_to_output( $og_image_File->get_url(), 'htmlattr' )."\" />\n";
		if( $image_dimensions = $og_image_File->get_image_size( 'widthheight') )
		{
			echo '<meta property="og:image:height" content="'.format_to_output( $image_dimensions[0], 'htmlattr' )."\" />\n";
			echo '<meta property="og:image:width" content="'.format_to_output( $image_dimensions[1], 'htmlattr' )."\" />\n";
		}
	}
}


function skin_twitter_tags()
{
	global $Collection, $Blog, $disp, $MainList;

	if( empty( $Blog ) || ! $Blog->get_setting( 'tags_twitter_card' ) )
	{ // Twitter summary card tags are not allowed
		return;
	}

	if( $Blog->get_setting( 'tags_open_graph' ) )
	{
		$open_tags_enabled = true;
	}

	switch( $disp )
	{
		case 'single':
		case 'page':
			$Item = & $MainList->get_by_idx( 0 );
			if( is_null( $Item ) )
			{ // This is not an object (happens on an invalid request):
				return;
			}

			// Get author's Twitter username
			if( $creator_User = & $Item->get_creator_User() )
			{
				if( $twitter_links = $creator_User->userfield_values_by_code( 'twitter' ) )
				{
					preg_match( '/https?:\/\/(www\.)?twitter\.com((?:\/\#!)?\/(\w+))/', $twitter_links[0], $matches );
					if( isset( $matches[3] ) )
					{
						echo '<meta property="twitter:creator" content="@'.$matches[3].'" />'."\n";
					}
				}
			}

			if( ! isset( $open_tags_enabled ) )
			{
				echo '<meta property="twitter:title" content="'.format_to_output( $Item->get( 'title' ), 'htmlattr' )."\" />\n";
				echo '<meta property="twitter:description" content="'.format_to_output( $Item->get_excerpt2(), 'htmlattr' )."\" />\n";
			}
			break;

		case 'posts':
			if( ! isset( $open_tags_enabled ) )
			{
				$intro_Item = & get_featured_Item( $disp, NULL, true );
				{
					if( $intro_Item->is_intro() )
					{
						if( ! isset( $open_tags_enabled ) )
						{
							echo '<meta property="twitter:title" content="'.format_to_output( $intro_Item->get( 'title' ), 'htmlattr' )."\" />\n";
							echo '<meta property="twitter:description" content="'.format_to_output( $intro_Item->get_excerpt2(), 'htmlattr' )."\" />\n";
						}
					}
				}
			}
			break;

		default:
			if( ! isset( $open_tags_enabled ) )
			{
				echo '<meta property="twitter:title" content="'.format_to_output( $Blog->name, 'htmlattr' )."\" />\n";
				echo '<meta property="twitter:description" content="'.format_to_output( $Blog->longdesc, 'htmlattr' )."\" />\n";
			}
			return;
	}

	$twitter_image_File = get_social_tag_image_file( $disp );
	if( ! empty( $twitter_image_File ) )
	{ // Has image, use summary with large image card
		echo '<meta property="twitter:card" content="summary_large_image" />'."\n";
		if( ! isset( $open_tags_enabled ) )
		{
			echo '<meta property="twitter:image" content="'.format_to_output( $twitter_image_File->get_url(), 'htmlattr' )."\" />\n";
		}

		if( $twitter_image_File->get( 'alt' ) )
		{ // Alternate text for image
			echo '<meta property="twitter:image:alt" content="'.format_to_output( $twitter_image_File->get( 'alt' ), 'htmlattr' )."\" />\n";
		}
	}
	else
	{ // No image, use only summary card
		echo '<meta property="twitter:card" content="summary" />'."\n";
	}
}


/**
 * Add structured data markup using JSON-LD format
 */
function skin_structured_data()
{
	global $Collection, $Blog, $disp, $MainList;

	if( empty( $Blog ) || ! $Blog->get_setting( 'tags_structured_data' ) )
	{ // Structured data markup is not allowed
		return;
	}

	switch( $disp )
	{
		case 'single':
		case 'page':
			$Item = & $MainList->get_by_idx( 0 );
			$creative_works_schema = array( 'Article', 'WebPage', 'BlogPosting', 'ImageGallery', 'DiscussionForumPosting', 'TechArticle', 'Review' );
			$products_schema = array( 'Product' );

			if( $Item && ( $item_schema = $Item->get_type_setting( 'schema' ) ) )
			{
				$markup = array(
					'@context' => 'http://schema.org',
					'@type' => $item_schema,
				);

				// Markup for CreativeWork schema:
				if( in_array( $item_schema, $creative_works_schema ) )
				{
					$markup['mainEntityOfPage'] = array(
							'@type' => 'WebPage',
							'@id' => $Item->get_permanent_url(),
						);
					$markup['headline'] = $Item->title;
					$markup['datePublished'] = date( 'c', mysql2timestamp( $Item->datestart ) );
					$markup['dateModified'] = date( 'c', mysql2timestamp( $Item->datemodified ) );
					$markup['author'] = array(
							'@type' => 'Person',
							'name' => $Item->get_creator_User()->get_preferred_name(),
						);
					$markup['description'] = $Item->get_excerpt();

					// Get publisher info:
					$FileCache = & get_FileCache();
					$publisher_name = $Blog->get_setting( 'publisher_name' );
					$publisher_logo_file_ID = $Blog->get_setting( 'publisher_logo_file_ID' );
					$publisher_logo_File = & $FileCache->get_by_ID( $publisher_logo_file_ID, false, false );
					if( $publisher_logo_File && $publisher_logo_File->is_image() )
					{
						$publisher_logo_url = $publisher_logo_File->get_url();
					}
					if( empty( $publisher_logo_url ) )
					{ // No publisher logo found, fallback to collection logo:
						$collection_logo_file_ID = $Blog->get_setting( 'collection_logo_file_ID' );
						$collection_logo_File = & $FileCache->get_by_ID( $collection_logo_file_ID, false, false );
						if( $collection_logo_File && $collection_logo_File->is_image() )
						{
							$publisher_logo_url = $collection_logo_File->get_url();
							$publisher_logo_File = $collection_logo_File;
						}
					}

					if( ! empty( $publisher_name ) || ! empty( $publisher_logo_url ) )
					{ // Add publisher data to markup:
						$markup['publisher'] = array( '@type' => 'Organization' );
						if( ! empty( $publisher_name ) )
						{
							$markup['publisher']['name'] = $publisher_name;
						}
						if( ! empty( $publisher_logo_url ) )
						{
							$markup['publisher']['logo'] = array(
									'@type' => 'ImageObject',
									'url' => $publisher_logo_url,
									'height' => $publisher_logo_File->get_image_size( 'height' ).'px',
									'width' => $publisher_logo_File->get_image_size( 'width' ).'px',
								);
						}
					}

					// Add article image:
					$article_image_File = get_social_media_image( $Item );
					if( $article_image_File )
					{
						$markup['image'] = $article_image_File->get_url();
					}
				}

				// Markup for Product schema:
				if( in_array( $item_schema, $products_schema ) )
				{
					$markup['name'] = $Item->title;
					$product_image_File = get_social_media_image( $Item );
					if( $product_image_File )
					{
						$markup['image'] = $product_image_File->get_url();
					}

					// Add product description:
					$markup['description'] = $Item->get_excerpt();
				}

				if( $Item->get_type_setting( 'add_aggregate_rating' ) )
				{ // Add aggregate rating:
					list( $ratings, $active_ratings ) = $Item->get_ratings();
					if( $ratings['all_ratings'] > 0 )
					{
						$markup['aggregateRating'] = array(
								'@type' => 'AggregateRating',
								'ratingValue' => round( $ratings["summary"] / $ratings['all_ratings'], 2 ),
								'ratingCount' => $ratings['all_ratings'],
							);
					}
				}

				// Add markup from custom fields:
				$custom_fields = $Item->get_custom_fields_defs();
				$custom_markup = array();
				foreach( $custom_fields as $custom_field )
				{
					if( ! empty( $custom_field['schema_prop'] ) )
					{
						$custom_markup = array_merge_recursive( $custom_markup, convert_path_to_array( $custom_field['schema_prop'], $custom_field['value'] ) );
					}
				}
				$markup = array_merge( $markup, $custom_markup );

				// Output the markup:
				echo '<!-- Start of Structured Data -->'."\n";
				echo '<script type="application/ld+json">'."\n";
				echo json_encode( $markup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )."\n";
				echo '</script>'."\n";
				echo '<!-- End of Structured Data -->'."\n";
			}
			break;

		default:
			// Do nothing
	}
}

/**
 * Sends the desired HTTP response header in case of a "404".
 */
function skin_404_header()
{
	global $Collection, $Blog;

	// We have a 404 unresolved content error
	// How do we want do deal with it?
	switch( $resp_code = $Blog->get_setting( '404_response' ) )
	{
		case '404':
			header_http_response('404 Not Found');
			break;

		case '410':
			header_http_response('410 Gone');
			break;

		case '301':
		case '302':
		case '303':
			// Redirect to home page:
			header_redirect( $Blog->get('url'), intval($resp_code) );
			// THIS WILL EXIT!
			break;

		default:
			// Will result in a 200 OK
	}
}


/**
 * Template tag. Output content-type header
 * For backward compatibility
 *
 * @see skin_content_meta()
 *
 * @param string content-type; override for RSS feeds
 */
function skin_content_header( $type = 'text/html' )
{
	header_content_type( $type );
}


/**
 * Template tag. Output content-type http_equiv meta tag
 *
 * @see skin_content_header()
 *
 * @param string content-type; override for RSS feeds
 */
function skin_content_meta( $type = 'text/html' )
{
	global $io_charset;

	echo '<meta http-equiv="Content-Type" content="'.$type.'; charset='.$io_charset.'" />'."\n";
}


/**
 * Template tag. Display a Widget.
 *
 * This load the widget class, instantiates it, and displays it.
 *
 * @param array
 */
function skin_widget( $params )
{
	global $inc_path;

	if( empty( $params['widget'] ) )
	{
		echo 'No widget code provided!';
		return false;
	}

	$widget_code = $params['widget'];
	unset( $params['widget'] );

	if( ! file_exists( $inc_path.'widgets/widgets/_'.$widget_code.'.widget.php' ) )
	{	// For some reason, that widget doesn't seem to exist... (any more?)
		echo "Invalid widget code provided [$widget_code]!";
		return false;
	}
	require_once $inc_path.'widgets/widgets/_'.$widget_code.'.widget.php';

	$widget_classname = $widget_code.'_Widget';

	/**
	 * @var ComponentWidget
	 */
	$Widget = new $widget_classname();	// COPY !!
      # $pa = array($params); 
	return $Widget->display( $params );
}


/**
 * Display a widget container
 *
 * @param string Container code
 * @param array Additional params
 */
function widget_container( $container_code, $params = array() )
{
	global $Blog, $Skin;

	$params = array_merge( array(
			'container_display_if_empty' => true, // FALSE - If no widget, don't display container at all, TRUE - Display container anyway
			'container_start' => '<div class="evo_container $wico_class$">',
			'container_end'   => '</div>',
			// Restriction for Page Containers:
			'container_item_ID' => NULL,
			// Default params for widget blocks:
			'block_start' => '<div class="evo_widget $wi_class$">',
			'block_end'   => '</div>',
		), $params );

	// Try to find widget container by code for current collection and skin type:
	$WidgetContainerCache = & get_WidgetContainerCache();
	$WidgetContainer = & $WidgetContainerCache->get_by_coll_skintype_code( $Blog->ID, $Blog->get_skin_type(), $container_code );

	if( ! $WidgetContainer )
	{	// Display error if widget container is not detected in DB by requested code:
		echo '<div class="text-danger">'
				.sprintf( T_('Requested widget container %s does not exist for current collection #%d and skin type %s!'),
					'<code>'.$container_code.'</code>', $Blog->ID, '<code>'.$Blog->get_skin_type().'</code>' )
			.'</div>';
		// Exit because we cannot display widgets without container:
		return;
	}

	// Pass WidgetContainer object:
	$params['WidgetContainer'] = $WidgetContainer;

	$Skin->container( $WidgetContainer->get( 'name' ), $params, $container_code );
}


/**
 * Display all widget containers for the requested Item
 *
 * @param integer Item ID
 * @param array Additional widget container params
 */
function widget_page_containers( $item_ID, $params = array() )
{
	global $Blog;

	if( empty( $Blog ) )
	{	// Skip wrong reuqest without current Collection:
		return;
	}

	// Try to find widget container by code for current collection and skin type:
	$WidgetContainerCache = & get_WidgetContainerCache();
	$widget_containers = $WidgetContainerCache->get_by_coll_skintype( $Blog->ID, $Blog->get_skin_type() );

	if( empty( $widget_containers ) )
	{	// No widget containers for current Collection and skin type:
		return;
	}

	// Set params for widget page containers:
	$params = array_merge( $params, array(
			// Signal that we are displaying within an Item:
			'widget_context' => 'item',
			// Restrict Page Container with these item ID and item type ID:
			'container_item_ID' => $item_ID,
		) );

	foreach( $widget_containers as $WidgetContainer )
	{
		if( $WidgetContainer->get_type() == 'page' &&
		    $WidgetContainer->get( 'item_ID' ) == $item_ID )
		{	// Display only widget page container for the requested Item:
			widget_container( $WidgetContainer->get( 'code' ), $params );
		}
	}
}


/**
 * Customize params with widget container properties on designer mode;
 * Replace variables/masks in params with widget container properties;
 * possible variables/masks in params:
 *     - $wico_class$ - Widget container class
 *
 * @param array Params with variables/masks
 * @param string Container code
 * @param string Container name
 * @return array Params with replaced values instead of source variables
 */
function widget_container_customize_params( $params, $wico_code, $wico_name )
{
	global $Collection, $Blog, $Session;

	$params = array_merge( array(
			'container_display_if_empty' => true, // FALSE - If no widget, don't display container at all, TRUE - Display container anyway
			'container_start' => '',
			'container_end'   => '',
		), $params );

	// Enable the desinger mode when it is turned on from evo menu under "Designer Mode/Exit Designer" or "Collection" -> "Enable/Disable designer mode"
	if( is_logged_in() && $Session->get( 'designer_mode_'.$Blog->ID ) )
	{	// Initialize hidden element with data which are used by JavaScript to build overlay designer mode html elements:
		if( $wico_code === NULL )
		{	// Display error if container cannot be detected in DB by name:
			echo ' <span class="text-danger">'.sprintf( T_('Container "%s" cannot be manipulated because it lacks a code name in the skin template.'), $wico_name ).'</span> ';
		}
		elseif( preg_match( '#<[^>]+evo_container[^>]+>#', $params['container_start'], $container_start_wrapper ) )
		{	// If container start param has a wrapper like '<div class="evo_container">':
			$designer_mode_data = array(
					'data-name' => $wico_name,
					'data-code' => $wico_code,
				);
			if( check_user_perm( 'blog_properties', 'edit', false, $Blog->ID ) )
			{	// Set data to know current user has a permission to edit this widget:
				$designer_mode_data['data-can-edit'] = 1;
			}
			// Append new data for container wrapper:
			$attrib_actions = array(
					'data-name'     => 'replace',
					'data-code'     => 'replace',
					'data-can-edit' => 'replace',
				);
			$params['container_start'] = str_replace( $container_start_wrapper[0], update_html_tag_attribs( $container_start_wrapper[0], $designer_mode_data, $attrib_actions ), $params['container_start'] );
		}
		else
		{	// If container code is NOT defined or detected by name or container wrapper is not correct:
			echo ' <span class="text-danger">'.sprintf( T_('Container %s cannot be manipulated because wrapper html tag has no %s.'), '"'.$wico_name.'"(<code>'.$wico_code.'</code>)', '<code>class="evo_container"</code>' ).'</span> ';
		}

		// Force to display container even if no widget:
		$params['container_display_if_empty'] = true;
	}

	// Replace variables/masks in params with widget container properties;
	// Possible variables/masks in params:
	//   - $wico_class$ - Widget container class

    # $params['WidgetContainer'] = settype( $params['WidgetContainer'], "array" );
     $cc_params['WidgetContainer'] = $params['WidgetContainer'];
     unset($params['WidgetContainer'] );
    # echo json_encode($params);
	# $params = str_replace( '$wico_class$', 'evo_container__'.str_replace( ' ', '_', $wico_code ), $params ); 
       
       $xwico_code =  'evo_container__'.str_replace( ' ', '_', $wico_code );
#var_dump($array);
      @$params = str_replace( '$wico_class$', $xwico_code, $params );
      $params['WidgetContainer'] = $cc_params['WidgetContainer']; 
     
    # $params['WidgetContainer'] = settype( $params['WidgetContainer'],"object" ); 
    #  $params['WidgetContainer'] = (object) $params['WidgetContainer'];
	 return $params;
}


/**
 * Display a container
 *
 * @deprecated Replaced with function widget_container( $container_code, $params = array() )
 *
 * @param string Container name
 * @param array Additional params
 * @param string Container code
 */
function skin_container( $sco_name, $params = array(), $container_code = NULL )
{
	global $Skin;

	$Skin->container( $sco_name, $params, $container_code );
}


/**
 * Get default skin/widget containers
 * They may be overridden by each Skin class in the function get_declared_containers()
 *
 * @return array Array of default containers: Key is widget container code, Value is array( 0 - container name, 1 - container order )
 */
function get_skin_default_containers()
{
	return array(
			'page_top'                  => array( NT_('Page Top'), 2 ),
			'header'                    => array( NT_('Header'), 10 ),
			'menu'                      => array( NT_('Menu'), 15 ),
			'front_page_main_area'      => array( NT_('Front Page Main Area'), 40 ),
			'front_page_secondary_area' => array( NT_('Front Page Secondary Area'), 45 ),
			'item_list'                 => array( NT_('Item List'), 48 ),
			'item_in_list'              => array( NT_('Item in List'), 49 ),
			'item_single_header'        => array( NT_('Item Single Header'), 50 ),
			'item_single'               => array( NT_('Item Single'), 51 ),
			'item_page'                 => array( NT_('Item Page'), 55 ),
			'comment_list'              => array( NT_('Comment List'), 57 ),
			'comment_area'              => array( NT_('Comment Area'), 60 ),
			'sidebar'                   => array( NT_('Sidebar'), 80 ),
			'sidebar_2'                 => array( NT_('Sidebar 2'), 90 ),
			'footer'                    => array( NT_('Footer'), 100 ),
			'user_profile_left'         => array( NT_('User Profile - Left'), 110 ),
			'user_profile_right'        => array( NT_('User Profile - Right'), 120 ),
			'404_page'                  => array( NT_('404 Page'), 130 ),
			'login_required'            => array( NT_('Login Required'), 140 ),
			'access_denied'             => array( NT_('Access Denied'), 150 ),
			'help'                      => array( NT_('Help'), 160 ),
			'register'                  => array( NT_('Register'), 170 ),
			'compare_main_area'         => array( NT_('Compare Main Area'), 180 ),
			'photo_index'               => array( NT_('Photo Index'), 190 ),
			'search_area'               => array( NT_('Search Area'), 200 ),
			'sitemap'                   => array( NT_('Site Map'), 210 ),
		);
}

/**
 * Install a skin
 *
 * @todo do not install if skin doesn't exist. Important for upgrade. Need to NOT fail if ZERO skins installed though :/
 *
 * @param string Skin folder
 * @param boolean TRUE if function should die on error
 * @return object Skin
 */
function & skin_install( $skin_folder, $halt_on_error = false )
{
	$SkinCache = & get_SkinCache();
	$Skin = & $SkinCache->new_obj( NULL, $skin_folder, $halt_on_error );

	$Skin->install();

	return $Skin;
}


/**
 * Checks if a skin is provided by a plugin.
 *
 * Used by front-end.
 *
 * @uses Plugin::GetProvidedSkins()
 * @return false|integer False in case no plugin provides the skin or ID of the first plugin that provides it.
 */
function skin_provided_by_plugin( $name )
{
	static $plugin_skins;
	if( ! isset($plugin_skins) || ! isset($plugin_skins[$name]) )
	{
		global $Plugins;

		$plugin_r = $Plugins->trigger_event_first_return('GetProvidedSkins', NULL, array('in_array'=>$name));
		if( $plugin_r )
		{
			$plugin_skins[$name] = $plugin_r['plugin_ID'];
		}
		else
		{
			$plugin_skins[$name] = false;
		}
	}

	return $plugin_skins[$name];
}


/**
 * Checks if a skin exists. This can either be a regular skin directory
 * or can be in the list {@link Plugin::GetProvidedSkins()}.
 *
 * Used by front-end.
 *
 * @param skin name (directory name)
 * @return boolean true is exists, false if not
 */
function skin_exists( $name, $filename = 'index.main.php' )
{
	global $skins_path;

	if( skin_file_exists( $name, $filename ) )
	{
		return true;
	}

	// Check list provided by plugins:
	if( skin_provided_by_plugin($name) )
	{
		return true;
	}

	return false;
}


/**
 * Checks if a specific file exists for a skin.
 *
 * @param skin name (directory name)
 * @param file name
 * @return boolean true is exists, false if not
 */
function skin_file_exists( $name, $filename = 'index.main.php' )
{
	global $skins_path;

	if( is_readable( $skins_path.$name.'/'.$filename ) )
	{
		return true;
	}

	return false;
}


/**
 * Check if a skin is installed.
 *
 * This can either be a regular skin or a skin provided by a plugin.
 *
 * @param Skin name (directory name)
 * @return boolean True if the skin is installed, false otherwise.
 */
function skin_installed( $name )
{
	$SkinCache = & get_SkinCache();

	if( skin_provided_by_plugin( $name ) || $SkinCache->get_by_folder( $name, false ) )
	{
		return true;
	}

	return false;
}


/**
 * Display a blog skin setting fieldset which can be normal, mobile, tablet or alt ( used on _coll_skin_settings.form.php )
 *
 * @param object Form
 * @param integer skin ID
 * @param array display params
 */
function display_skin_fieldset( & $Form, $skin_ID, $display_params )
{
	global $mode;

	if( $mode != 'customizer' )
	{	// Except of skin customer mode:
		$Form->begin_fieldset( $display_params[ 'fieldset_title' ].' '.$display_params[ 'fieldset_links' ] );
	}

	if( !$skin_ID )
	{ // The skin ID is empty use the same as normal skin ID
		echo '<div style="font-weight:bold;padding:0.5ex;">'.T_('Same as standard skin').'.</div>';
	}
	else
	{
		$SkinCache = & get_SkinCache();
		$edited_Skin = $SkinCache->get_by_ID( $skin_ID );

		if( $mode != 'customizer' )
		{	// Except of skin customer mode:
			echo '<div class="skin_settings well">';
			$disp_params = array( 'skinshot_class' => 'coll_settings_skinshot' );
			Skin::disp_skinshot( $edited_Skin->folder, $edited_Skin->name, $disp_params );

			// Skin name
			echo '<div class="skin_setting_row">';
				echo '<label>'.T_('Skin name').':</label>';
				echo '<span>'.$edited_Skin->name.'</span>';
			echo '</div>';

			// Skin version
			echo '<div class="skin_setting_row">';
				echo '<label>'.T_('Skin version').':</label>';
				echo '<span>'.( isset( $edited_Skin->version ) ? $edited_Skin->version : 'unknown' ).'</span>';
			echo '</div>';

			// Site Skin:
			echo '<div class="skin_setting_row">';
				echo '<label>'.T_('Site Skin').':</label>';
				echo '<span>'.( $edited_Skin->provides_site_skin() ? T_('Yes') : T_('No') ).'</span>';
			echo '</div>';

			// Collection Skin:
			echo '<div class="skin_setting_row">';
				echo '<label>'.T_('Collection Skin').':</label>';
				echo '<span>'.( $edited_Skin->provides_collection_skin() ? T_('Yes') : T_('No') ).'</span>';
			echo '</div>';

			// Skin format:
			echo '<div class="skin_setting_row">';
				echo '<label>'.T_('Skin format').':</label>';
				echo '<span>'.get_skin_type_title( $edited_Skin->type ).'</span>';
			echo '</div>';

			// Containers
			if( $skin_containers = $edited_Skin->get_containers() )
			{
				$skin_containers_names = array();
				foreach( $skin_containers as $skin_container_data )
				{
					$skin_containers_names[] = $skin_container_data[0];
				}
				$container_ul = '<ul><li>'.implode( '</li><li>', $skin_containers_names ).'</li></ul>';
			}
			else
			{
				$container_ul = '-';
			}
			echo '<div class="skin_setting_row">';
				echo '<label>'.T_('Containers').':</label>';
				echo '<span>'.$container_ul.'</span>';
			echo '</div>';

			echo '</div>';
			echo '<div class="skin_settings_form">';
		}

		$tmp_params = array( 'for_editing' => true );
		$skin_params = $edited_Skin->get_param_definitions( $tmp_params );

		if( !skin_exists( $edited_Skin->folder ) )
		{
			echo '<p class="text-danger">'.T_('The skin files are missing.').'</p>';
		}
		elseif( empty( $skin_params ) )
		{ // Advertise this feature!!
			echo '<p>'.T_('This skin does not provide any configurable settings.').'</p>';
		}
		else
		{
			load_funcs( 'plugins/_plugin.funcs.php' );

			// Check if skin settings contain at least one fieldset
			$skin_fieldsets_exist = false;
			foreach( $skin_params as $l_name => $l_meta )
			{
				if( isset( $l_meta['layout'] ) && $l_meta['layout'] == 'begin_fieldset' )
				{
					$skin_fieldsets_exist = true;
					break;
				}
			}

			if( ! $skin_fieldsets_exist )
			{ // Enclose all skin settings in single group if no group on the skin
				array_unshift( $skin_params, array(
						'layout' => 'begin_fieldset',
						'label'  => T_('Skin settings')
					) );
				array_push( $skin_params, array(
						'layout' => 'end_fieldset'
					) );
			}

			if( $mode == 'customizer' )
			{
				$Form->begin_group();
			}

			// Loop through all widget params:
			foreach( $skin_params as $l_name => $l_meta )
			{
				// Display field:
				autoform_display_field( $l_name, $l_meta, $Form, 'Skin', $edited_Skin );
			}

			if( $mode == 'customizer' )
			{
				$Form->end_group();
			}
		}

		if( $mode != 'customizer' )
		{	// Except of skin customer mode:
			echo '</div>';
		}
	}

	if( $mode != 'customizer' )
	{	// Except of skin customer mode:
		$Form->end_fieldset();
	}
}


/**
 * Template function to init and print out html attributes for <body> tag
 *
 * @param array Additional values for attributes
 */
function skin_body_attrs( $params = array() )
{
	$params = array_merge( array(
			'class' => NULL
		), $params );

	global $PageCache, $Collection, $Blog, $disp, $disp_detail, $Item, $current_User, $instance_name;

	// WARNING: Caching! We're not supposed to have Session dependent stuff in here. This is for debugging only!
	global $Session, $debug;

	$classes = array();

	if( ! empty( $params['class'] ) )
	{ // Prepend additional classes from template skin
		$classes[] = $params['class'];
	}

	// Device class:
	if( ! empty( $PageCache ) )
	{ // Try to detect device only when Page Cache is defined
		if( $PageCache->is_collecting )
		{ // Page is cached now
			$classes[] = 'unknown_device page_cached';
		}
		else
		{ // Page is NOT cached now
			global $Session;
			if( $Session->is_mobile_session() )
			{ // Mobile device
				$classes[] = 'mobile_device';
			}
			elseif( $Session->is_tablet_session() )
			{ // Tablet device
				$classes[] = 'tablet_device';
			}
			elseif( $Session->is_alt_session() )
			{	// Session for alternative skin:
				$classes[] = 'alt_skin';
			}
			else
			{ // Desktop device
				$classes[] = 'desktop_device';
			}
			$classes[] = 'page_notcached';
		}
	}

	// Instance class:
	$classes[] = 'instance_'.$instance_name;

	// Blog class:
	$classes[] = 'coll_'.( empty( $Blog ) ? 'none' : $Blog->ID );

	// $disp class:
	$classes[] = 'disp_'.( empty( $disp ) ? 'none' : $disp );

	// $disp_detail class:
	$classes[] = 'detail_'.( empty( $disp_detail ) ? 'none' : $disp_detail );

	// Item class:
	$classes[] = 'item_'.( empty( $Item ) ? 'none' : $Item->ID );

	// Logged in/Anonymous class:
	$classes[] = is_logged_in() ? 'loggedin' : 'anonymous';

	// Toolbar visibility class:
	$classes[] = show_toolbar() ? 'evo_toolbar_visible' : 'evo_toolbar_hidden';

	// User Group class:
	$classes[] = 'usergroup_'.( ! is_logged_in() && empty( $current_User->grp_ID ) ? 'none' : $current_User->grp_ID );

	// WARNING: Caching! We're not supposed to have Session dependent stuff in here. This is for debugging only!
	if( ( $debug == 2 || is_logged_in() ) && ! empty( $Blog ) )
	{
		if( $Session->get( 'display_includes_'.$Blog->ID ) )
		{
			$classes[] = 'dev_show_includes';
		}
		if( $Session->get( 'display_containers_'.$Blog->ID ) )
		{
			$classes[] = 'dev_show_containers';
		}
		if( $Session->get( 'customizer_mode_'.$Blog->ID ) )
		{
			$classes[] = 'dev_customizer_mode';
		}
		if( $Session->get( 'designer_mode_'.$Blog->ID ) )
		{
			$classes[] = 'dev_designer_mode';
		}
	}

	if( ! empty( $classes ) )
	{ // Print attr "class"
		echo ' class="'.implode( ' ', $classes ).'"';
	}
}


/**
 * Get skin version by ID
 *
 * @param integer Skin ID
 * @return string Skin version
 */
function get_skin_version( $skin_ID )
{
	$SkinCache = & get_SkinCache();
	$Skin = $SkinCache->get_by_ID( $skin_ID );

	if( isset( $Skin->version ) )
	{
		return $Skin->version;
	}

	return 'unknown';
}


/**
 * Check compatibility skin with requested type
 *
 * @param integer Skin ID
 * @param string Type: 'site' or 'coll'
 * @return boolean
 */
function skin_check_compatibility( $skin_ID, $type )
{
	$SkinCache = & get_SkinCache();
	$Skin = & $SkinCache->get_by_ID( $skin_ID, false, false );

	if( ! $Skin ||
	    ( $type == 'coll' && ! $Skin->provides_collection_skin() ) ||
	    ( $type == 'site' && ! $Skin->provides_site_skin() ) )
	{	// Skin cannot be used for requested type:
		return false;
	}

	return true;
}


/**
 * Get a skins base skin and version
 *
 * @param String skin name (directory name)
 * @return Array of base skin and version
 */
function get_skin_folder_base_version( $skin_folder )
{
	preg_match( '/-((\d+\.)?(\d+\.)?(\*|\d+))$/', $skin_folder, $matches );
	if( ! empty( $matches ) )
	{
		$base_skin = substr( $skin_folder, 0, strlen( $matches[0] ) * -1 );
		$skin_version = isset( $matches[2] ) ? $matches[1] : 0;
	}
	else
	{
		$base_skin = $skin_folder;
		$skin_version = 0;
	}

	return array( $base_skin, $skin_version );
}


/**
 * Get skin types
 *
 * @return array
 */
function get_skin_types()
{
	return array(
		'normal'  => array( T_('Standard'), T_('Standard skin for general browsing') ),
		'mobile'  => array( T_('Phone'), T_('Mobile skin for mobile phones browsers') ),
		'tablet'  => array( T_('Tablet'), T_('Tablet skin for tablet browsers') ),
		'alt'     => array( T_('Alt'), T_('Alt skin to display by conditions') ),
		'rwd'     => array( T_('RWD'), T_('Skin can be used for general, mobile phones and tablet browsers and for alt skin') ),
		'feed'    => array( T_('XML Feed'), T_('Special system skin for XML feeds like RSS and Atom') ),
		'sitemap' => array( T_('XML Sitemap'), T_('Special system skin for XML sitemaps') ),
	);
}


/**
 * Get title of skin type
 *
 * @param string Skin type
 * @return string Skin title
 */
function get_skin_type_title( $skin_type )
{
	$skin_types = get_skin_types();
	return ( isset( $skin_types[ $skin_type ] ) ? $skin_types[ $skin_type ][0] : $skin_type );
}


/**
 * Get setting value of the current Skin
 *
 * @param string Setting name
 * @param mixed Fallback value when no current Skin or the requested setting is not defined in the current Skin
 * @return mixed Setting value
 */
function get_skin_setting( $setting_name, $fallback_value = NULL )
{
	global $Skin;

	if( isset( $Skin ) && $Skin instanceof Skin )
	{	// Try to get setting value of the current Skin:
		$setting_value = $Skin->get_setting( $setting_name );
	}

	if( ! isset( $setting_value ) )
	{	// Fallback to default value when no current Skin or settings is not defined:
		$setting_value = $fallback_value;
	}

	return $setting_value;
}


/**
 * Output JavaScript code to confirm skin selection
 */
function echo_confirm_skin_selection_js()
{
	// Initialize JavaScript to build and open modal window:
	echo_modalwindow_js();
?>
<script type="text/javascript">
function confirm_skin_selection( link_obj, skin_type )
{
	var keep_url = jQuery( link_obj ).attr( 'href' );
	var reset_url = keep_url + '&reset_widgets=1';
	var modal_window_title = '';
	var modal_reset_button_class = 'btn-default btn-danger-hover';
	var modal_keep_button_class = 'btn-primary';

	switch( skin_type )
	{
		case 'mobile':
			modal_window_title = '<?php echo TS_('You are about to change the Mobile skin of your collection. Do you want to reset the widgets to what the new skin recommends?'); ?>';
			break;
		case 'tablet':
			modal_window_title = '<?php echo TS_('You are about to change the Tablet skin of your collection. Do you want to reset the widgets to what the new skin recommends?'); ?>';
			break;
		case 'normal':
		default:
			modal_window_title = '<?php echo TS_('You are about to change the Normal skin of your collection. Do you want to reset the widgets to what the new skin recommends?'); ?>';
			modal_reset_button_class = 'btn-danger';
			modal_keep_button_class = 'btn-default';
			break;
	}

	openModalWindow( '<p>' + modal_window_title + '</p>'
		+ '<form>'
		+ '<a href="' + reset_url + '" class="btn ' + modal_reset_button_class + '"><?php echo TS_('Reset widgets'); ?></a>'
		+ '<a href="' + keep_url + '" class="btn ' + modal_keep_button_class + '"><?php echo TS_('Keep existing widgets'); ?></a>'
		+ '</form>',
		'500px', '', true,
		'<span class="text-danger"><?php echo TS_('WARNING');?></span>', '', true );
	return false;
}
</script>
<?php
}
?>