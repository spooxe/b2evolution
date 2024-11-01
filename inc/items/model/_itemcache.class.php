<?php
/**
 * This file implements the ItemCache class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link https://github.com/b2evolution/b2evolution}.
 *
 * @license GNU GPL v2 - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @copyright (c)2003-2020 by Francois Planque - {@link http://fplanque.com/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @package evocore
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/dataobjects/_dataobjectcache.class.php', 'DataObjectCache' );

load_class( 'items/model/_item.class.php', 'Item' );

/**
 * Item Cache Class
 *
 * @package evocore
 */
class ItemCache extends DataObjectCache
{
	/**
	 * Lazy filled index of url titles
	 */
	var $urltitle_index = array();

	/**
	 * Lazy filled map of items by category
	 */
	var $items_by_cat_map = array();


	/**
	 * Constructor
	 *
	 * @param string object type of elements in Cache
	 * @param string Name of the DB table
	 * @param string Prefix of fields in the table
	 * @param string Name of the ID field (including prefix)
	 */
	function __construct( $objType = 'Item', $dbtablename = 'T_items__item', $dbprefix = 'post_', $dbIDname = 'post_ID' )
	{
		parent::__construct( $objType, false, $dbtablename, $dbprefix, $dbIDname );
	}


	/**
	 * Load the cache **extensively**
	 */
	function load_all()
	{
		if( $this->all_loaded )
		{ // Already loaded
			return false;
		}

		debug_die( 'Load all is not allowed for ItemCache!' );
	}


	/**
	 * Get Item by category ID
	 *
	 * @param integer Category ID
	 * @param string Function to order/compare items for case when their category uses alphabetical sorting
	 * @return object Item
	 */
	function get_by_cat_ID( $cat_ID, $order_alpha_func = 'compare_items_by_title' )
	{
		$ChapterCache = & get_ChapterCache();
		$Chapter = $ChapterCache->get_by_ID( $cat_ID );

		if( ! isset( $this->items_by_cat_map[$cat_ID] ) )
		{ // Load items if not loaded yet
			$this->load_by_categories( array( $cat_ID ), $Chapter->blog_ID );
		}

		// Get callback method to compare items on sort:
		// - Alphabetical sorting by title or short title
		// - Manual sorting by order field
		$compare_method = ( $Chapter->get_subcat_ordering() == 'alpha' ? $order_alpha_func : 'compare_items_by_order' );
     // Automatic conversion of false to array is deprecated     
if (!isset($this->items_by_cat_map[$cat_ID]['sorted'][$compare_method])) {
    // Not sorted yet by requested method:
    if ($Chapter->get_subcat_ordering() == 'manual') {
        // If manual sorting by order field:
        foreach ($this->items_by_cat_map[$cat_ID]['items'] as $i => $sorted_Item) {
            // Set temp var in order to know what category order use to compare:
            $sorted_Item->sort_current_cat_ID = $cat_ID;
        }
    }
    // Ensure that 'sorted' is initialized as an array
    if (!isset($this->items_by_cat_map[$cat_ID]['sorted']) || !is_array($this->items_by_cat_map[$cat_ID]['sorted'])) {
        $this->items_by_cat_map[$cat_ID]['sorted'] = [];
    }
    // Initialize the specific sort method array
    $this->items_by_cat_map[$cat_ID]['sorted'][$compare_method] = $this->items_by_cat_map[$cat_ID]['items'];
    usort($this->items_by_cat_map[$cat_ID]['sorted'][$compare_method], array('Item', $compare_method));
}
//

		return $this->items_by_cat_map[$cat_ID]['sorted'][$compare_method];
	}


	/**
	 * Load items by the given categories or collection ID
	 * After the Items are loaded create a map of loaded items by categories
	 *
	 * @param array of category ids
	 * @param integer collection ID
	 * @return boolean true if load items was required and it was loaded successfully, false otherwise
	 */
	function load_by_categories( $cat_array, $coll_ID )
	{
		global $DB;

		if( empty( $cat_array ) && empty( $coll_ID ) )
		{ // Nothing to load
			return false;
		}

		// In case of an empty cat_array param, use categoriesfrom the given collection
		if( empty( $cat_array ) )
		{ // Get all categories from the given subset
			$ChapterCache = & get_ChapterCache();
			$subset_chapters = $ChapterCache->get_chapters_by_subset( $coll_ID );
			$cat_array = array();
			foreach( $subset_chapters as $Chapter )
			{
				$cat_array[] = $Chapter->ID;
			}
		}

		// Check which category is not loaded
		$not_loaded_cat_ids = array();
		foreach( $cat_array as $cat_ID )
		{
			if( ! isset( $this->items_by_cat_map[$cat_ID] ) )
			{ // This category is not loaded
				$not_loaded_cat_ids[] = $cat_ID;
				// Initialize items_by_cat_map for this cat_ID
				$this->items_by_cat_map[$cat_ID] = array( 'items' => array(), 'sorted' => false );
			}
		}

		if( empty( $not_loaded_cat_ids ) )
		{ // Requested categories items are all loaded
			return false;
		}

		// Query to load all Items from the given categories
		$sql = 'SELECT postcat_cat_ID as cat_ID, postcat_post_ID as post_ID FROM T_postcats
					WHERE postcat_cat_ID IN ( '.implode( ', ', $not_loaded_cat_ids ).' )
					ORDER BY postcat_post_ID';

		$cat_posts = $DB->get_results( $sql, ARRAY_A, 'Get all category post ids pair by category' );

		// Initialize $Blog from coll_ID
		$BlogCache = & get_BlogCache();
		$Collection = $Blog = $BlogCache->get_by_ID( $coll_ID );

		$visibility_statuses = is_admin_page() ? get_visibility_statuses( 'keys', array('trash') ) : get_inskin_statuses( $coll_ID, 'post' );

		// Create ItemQuery for loading visible items
		$ItemQuery = new ItemQuery( $this->dbtablename, $this->dbprefix, $this->dbIDname );

		// Set filters what to select
		$ItemQuery->SELECT( $this->dbtablename.'.*' );
		$ItemQuery->where_chapter2( $Blog, $not_loaded_cat_ids, "" );
		$ItemQuery->where_visibility( $visibility_statuses );
		$ItemQuery->where_datestart( NULL, NULL, NULL, NULL, $Blog->get_timestamp_min(), $Blog->get_timestamp_max() );
		$ItemQuery->where_itemtype_usage( 'post' );
		$ItemQuery->where_locale_visibility();

		// Clear previous items from the cache and load by the defined SQL
		$this->clear( true );
		$this->load_by_sql( $ItemQuery );

		foreach( $cat_posts as $row )
		{ // Iterate through the post - cat pairs and fill the map
			if( empty( $this->cache[ $row['post_ID'] ] ) )
			{ // The Item was not loaded because it does not correspond to the defined filters
				continue;
			}

			// Add to the map
			$this->items_by_cat_map[$row['cat_ID']]['items'][] = $this->get_by_ID( $row['post_ID'] );
		}
	}


	/**
	 * Load a set of Item objects into the cache by IDs and slugs
	 *
	 * @param array List of IDs and names of Item objects to load
	 * @return array List of Item objects
	 */
	function load_by_IDs_or_slugs( $IDs_slugs )
	{
		global $DB, $Debuglog;

		if( empty( $IDs_slugs ) || ! is_array( $IDs_slugs ) )
		{	// Wrong source data:
			return array();
		}

		$IDs = array();
		$slugs = array();
		foreach( $IDs_slugs as $ID_slug )
		{
			if( is_number( $ID_slug ) )
			{
				$IDs[] = $ID_slug;
			}
			else
			{
				$slugs[] = $ID_slug;
			}
		}

		$SQL = $this->get_SQL_object( 'Get the '.$this->objtype.' rows to load the objects into the cache by '.get_class().'->'.__FUNCTION__.'()' );
		$sql_where = array();
		if( ! empty( $IDs ) )
		{	// Load Items by IDs:
			$sql_where[] = $this->dbIDname.' IN ( '.$DB->quote( $IDs ).' )';
		}
		if( ! empty( $slugs ) )
		{	// Load Items by slugs:
			$SlugCache = & get_SlugCache();
			$sql_where[] = $SlugCache->name_field.' IN ( '.$DB->quote( $slugs ).' )';
			$SQL->SELECT_add( ', slug_title' );
			$SQL->FROM_add( 'INNER JOIN '.$SlugCache->dbtablename.' ON '.$this->dbIDname.' = slug_itm_ID' );
			$SQL->WHERE_and( 'slug_type = "item"' );
			$SQL->GROUP_BY( $this->dbIDname );
		}
		$SQL->WHERE_and( implode( ' OR ', $sql_where ) );

		$item_rows = $DB->get_results( $SQL );

		$items = array();
		foreach( $item_rows as $Item )
		{
			$item_slug_title = isset( $Item->slug_title ) ? $Item->slug_title : false;
			$Item = $this->instantiate( $Item );
			$items[] = $Item;
			if( $item_slug_title !== false &&
			    ! isset( $this->urltitle_index[ $item_slug_title ] ) )
			{	// Cache Item by slug:
				$Debuglog->add( 'Cached <strong>'.$this->objtype.'('.$item_slug_title.')</strong>' );
				$this->urltitle_index[ $item_slug_title ] = $Item;
			}
		}

		return $items;
	}


	/**
	 * Get an object from cache by its urltitle
	 *
	 * Load into cache if necessary
	 *
	 * @param string stub of object to load
	 * @param boolean false if you want to return false on error
	 * @param boolean true if function should die on empty/null
	 */
	function & get_by_urltitle( $req_urltitle, $halt_on_error = true, $halt_on_empty = true )
	{
		global $DB, $Debuglog;

		if( !isset( $this->urltitle_index[$req_urltitle] ) )
		{ // not yet in cache:
	    // Get from SlugCache
			$SlugCache = & get_SlugCache();
			$req_Slug =  $SlugCache->get_by_name( $req_urltitle, $halt_on_error, $halt_on_empty );

			if( $req_Slug && $req_Slug->get( 'type' ) == 'item' )
			{	// It is in SlugCache
				$itm_ID = $req_Slug->get( 'itm_ID' );
				if( $Item = $this->get_by_ID( $itm_ID, $halt_on_error, $halt_on_empty ) )
				{
					$this->urltitle_index[$req_urltitle] = $Item;
				}
				else
				{	// Item does not exist
					if( $halt_on_error ) debug_die( "Requested $this->objtype does not exist!" );
					$this->urltitle_index[$req_urltitle] = false;
				}
			}
			else
			{	// not in the slugCache
				if( $halt_on_error ) debug_die( "Requested $this->objtype does not exist!" );
				$this->urltitle_index[$req_urltitle] = false;
			}
		}
		else
		{
			$Debuglog->add( "Retrieving <strong>$this->objtype($req_urltitle)</strong> from cache" );
		}

		return $this->urltitle_index[$req_urltitle];
	}


	/**
	 * Load a list of item referenced by their urltitle into the cache
	 *
	 * @param array of urltitles of Items to load
	 */
	function load_urltitle_array( $req_array )
	{
		global $DB, $Debuglog;

		$req_list = "'".implode( "','", $req_array)."'";
		$Debuglog->add( "Loading <strong>$this->objtype($req_list)</strong> into cache", 'dataobjects' );
		$sql = "SELECT * FROM $this->dbtablename WHERE post_urltitle IN ( $req_list )";
		$dbIDname = $this->dbIDname;
		$objtype = $this->objtype;
		foreach( $DB->get_results( $sql ) as $row )
		{
			$this->cache[ $row->$dbIDname ] = new $objtype( $row ); // COPY!
			// $obj = $this->cache[ $row->$dbIDname ];
			// $obj->disp( 'name' );

			// put into index:
			$this->urltitle_index[$row->post_urltitle] = & $this->cache[ $row->$dbIDname ];

			$Debuglog->add( "Cached <strong>$this->objtype($row->post_urltitle)</strong>" );
		}

		// Set cache from Slug table:
		foreach( $req_array as $urltitle )
		{
			if( !isset( $this->urltitle_index[$urltitle] ) )
			{ // not yet in cache:
				$SlugCache = & get_SlugCache();
				if( $req_Slug = $SlugCache->get_by_name( $urltitle, false, false ) )
				{
					if( $req_Slug->get( 'type' ) == 'item' )
					{	// Is item slug
						if( $Item = $this->get_by_ID( $req_Slug->get( 'itm_ID' ), false ) )
						{	// Set cache
							$this->urltitle_index[$urltitle] = $Item;
							$Debuglog->add( "Cached <strong>$this->objtype($urltitle)</strong>" );
							continue;
						}
					}
				}
				// Set cache for non found objects:
				$this->urltitle_index[$urltitle] = false; // Remember it doesn't exist in DB either
				$Debuglog->add( "Cached <strong>$this->objtype($urltitle)</strong> as NON EXISTENT" );
			}
		}
	}


}

?>