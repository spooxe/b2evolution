<?php
	/**
	 * This is the template that displays the linkblog
	 *
	 * This file is not meant to be called directly.
	 * It is meant to be called by an include in the _main.php template.
	 *
	 * b2evolution - {@link http://b2evolution.net/}
	 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
	 * @copyright (c)2003-2004 by Francois PLANQUE - {@link http://fplanque.net/}
	 *
	 * @package evoskins
	 */
	if( !defined('DB_USER') ) die( 'Please, do not access this page directly.' );
	
	if( !isset( $linkblog ) )
	{	// No link blog explicitely specified, we use default:
		$linkblog = $Blog->get('links_blog_ID');
	}
	
	if( ! $linkblog )
	{	// No linkblog blog requested for this blog
		return;
	}


	# maximum number of linkblog entries to display:
	if(!isset($linkblog_limit)) $linkblog_limit = 20;
	# global linkblog delimiters:
	if(!isset($linkblog_main_start)) $linkblog_main_start = '<div class="bSideItem"><h3>'.
																														T_('Linkblog').'</h3>';
	if(!isset($linkblog_main_end)) $linkblog_main_end = '</div>';
	# Category delimiters:
	if(!isset($linkblog_catname_before)) $linkblog_catname_before = '<h4>';
	if(!isset($linkblog_catname_after)) $linkblog_catname_after = '</h4><ul>';
	if(!isset($linkblog_catlist_end)) $linkblog_catlist_end = '</ul>';
	# Item delimiters:
	if(!isset($linkblog_item_before)) $linkblog_item_before = '<li>';
	if(!isset($linkblog_item_after)) $linkblog_item_after = '</li>';
	
	
	// --- //
	
	
	// Load the linkblog blog:
	$LinkblogList = & new ItemList( $linkblog, array(), '', '', '', $linkblog_cat, $linkblog_catsel, '', 'ASC',
																	'main_cat_ID title', '', '', '', '', '', '', '', '', $linkblog_limit,
																	'posts', $timestamp_min, $timestamp_max );
	
	
	// Dirty trick until we get everything into objects:
	$saved_blog = $blog;  
	$blog = $linkblog;
	
	// Open the global list
	echo $linkblog_main_start;
		
	$previous_cat = '';
	$linkblog_cat = '';
	
	while( $Item = $LinkblogList->get_category_group() )
	{
		// Open new cat:
		echo $linkblog_catname_before;
		$Item->main_category();
		echo $linkblog_catname_after;
		
		while( $Item = $LinkblogList->get_item() )
		{
			echo $linkblog_item_before;
			$Item->title(); 
			echo ' ';
			$Item->content( 1, 0, T_('more'), '[', ']' );	// Description + more link 
			?>
			<a href="<?php $Item->permalink() ?>" title="<?php echo T_('Permanent link to full entry') ?>"><img src="img/icon_minipost.gif" alt="<?php echo T_('Permalink') ?>" width="12" height="9" class="middle" /></a>
			<?php
			echo $linkblog_item_after;
		}
	
		// Close cat
		echo $linkblog_catlist_end;
	}
	// Close the global list
	echo $linkblog_main_end;
	
	// Restore after dirty trick:
	$blog = $saved_blog;		
?>