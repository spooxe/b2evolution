<?php
/**
 * This file implements the Skin class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link https://github.com/b2evolution/b2evolution}.
 *
 * @license GNU GPL v2 - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @copyright (c)2003-2020 by Francois Planque - {@link http://fplanque.com/}
 * Parts of this file are copyright (c)2005-2006 by PROGIDISTRI - {@link http://progidistri.com/}.
 *
 * @package evocore
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );



load_class( '_core/model/dataobjects/_dataobject.class.php', 'DataObject' );


/**
 * Skin Class
 *
 * @package evocore
 */

class Skin extends DataObject
{
	var $name;
	var $folder;
	var $type;
	var $class;
        /**dynamic property*/
    var $dynamic_styles;

	/**
	 * Skin version
	 * @var string
	 */
	var $version = NULL;

	/**
	 * Do we want to use style.min.css instead of style.css ?
	 */
	var $use_min_css = false;  // true|false|'check' Set this to true for better optimization
	// Note: we set this to false by default for backwards compatibility with third party skins.
	// But for best performance, you should set it to true.

	/**
	 * Lazy filled.
	 * @var array
	 */
	var $container_list = NULL;

	/**
	 * The translations keyed by locale. They get loaded through include() of _global.php.
	 * @see Skin::T_()
	 * @var array
	 */
	var $_trans = array();

	/**
	 * Constructor
	 *
	 * @param table Database row
	 */
	function __construct( $db_row = NULL, $skin_folder = NULL )
	{
		// Call parent constructor:
		parent::__construct( 'T_skins__skin', 'skin_', 'skin_ID' );

		if( is_null($db_row) )
		{	// We are creating an object here:
			$this->set( 'folder', $skin_folder );
			$this->set( 'name', $this->get_default_name() );
			$this->set( 'type', $this->get_default_type() );
			$this->set( 'class', get_class( $this ) );
		}
		else
		{	// Wa are loading an object:
			$this->ID = $db_row->skin_ID;
			$this->name = $db_row->skin_name;
			$this->folder = $db_row->skin_folder;
			$this->type = $db_row->skin_type;
			$this->class = $db_row->skin_class;
		}
	}


	/**
	 * Get param prefix with is used on edit forms and submit data
	 *
	 * @return string
	 */
	function get_param_prefix()
	{
		return 'edit_skin_'.( empty( $this->ID ) ? '0' : $this->ID ).'_set_';
	}


	/**
	 * Get delete restriction settings
	 *
	 * @return array
	 */
	static function get_delete_restrictions()
	{
		return array(
				array( 'table'=>'T_blogs', 'fk'=>'blog_normal_skin_ID', 'fk_short'=>'normal_skin_ID', 'msg'=>T_('%d blogs using this skin') ),
				array( 'table'=>'T_blogs', 'fk'=>'blog_mobile_skin_ID', 'fk_short'=>'mobile_skin_ID', 'msg'=>T_('%d blogs using this skin') ),
				array( 'table'=>'T_blogs', 'fk'=>'blog_tablet_skin_ID', 'fk_short'=>'tablet_skin_ID', 'msg'=>T_('%d blogs using this skin') ),
				array( 'table'=>'T_blogs', 'fk'=>'blog_alt_skin_ID', 'fk_short'=>'alt_skin_ID', 'msg'=>T_('%d blogs using this skin') ),
				array( 'table'=>'T_settings', 'fk'=>'set_value', 'msg'=>T_('This skin is set as default skin.'),
						'and_condition' => '( set_name = "def_normal_skin_ID" OR set_name = "def_mobile_skin_ID" OR set_name = "def_tablet_skin_ID" OR set_name = "def_alt_skin_ID" )' ),
				array( 'table'=>'T_settings', 'fk'=>'set_value', 'msg'=>T_('The site is using this skin.'),
						'and_condition' => '( set_name = "normal_skin_ID" OR set_name = "mobile_skin_ID" OR set_name = "tablet_skin_ID" OR set_name = "alt_skin_ID" )' ),
			);
	}


	/**
	 * Install current skin to DB
	 */
	function install()
	{
		if( $skin_ID = $this->dbexists( 'skin_folder', $this->get( 'folder' ) ) )
		{	// Use already stored skin in DB:
			$this->ID = $skin_ID;
		}
		else
		{	// Insert new skin into DB:
			$this->dbinsert();
		}
	}


	/**
	 * Get default name for the skin.
	 * Note: the admin can customize it.
	 */
	function get_default_name()
	{
		return $this->folder;
	}


	/**
	 * Get default type/format for the skin.
	 *
	 * Possible values are normal, tablet, phone, feed, sitemap, alt.
	 */
	function get_default_type()
	{
		return (substr($this->folder,0,1) == '_' ? 'feed' : 'normal');
	}


	/**
	 * Does this skin provide normal (collection) skin functionality?
	 */
	function provides_collection_skin()
	{
		return true;	// If the skin doesn't override this, it will be a collection skin.
	}


	/**
	 * Does this skin provide site-skin functionality?
	 */
	function provides_site_skin()
	{
		return false;	// If the skin doesn't override this, it will NOT be a site skin.
	}


	/**
	 * Get the customized name for the skin.
	 */
	function get_name()
	{
		return $this->name;
	}


	/**
	 * What evoSkins API does has this skin been designed with?
	 *
	 * This determines where we get the fallback templates from (skins_fallback_v*)
	 * (allows to use new markup in new b2evolution versions)
	 */
	function get_api_version()
	{
		return 5;
	}


	/**
	 * Get the container codes of the skin main containers
	 *
	 * This should NOT be protected. It should be used INSTEAD of file parsing.
	 * File parsing should only be used if this function is not defined
	 *
	 * @return array Array which overrides default containers; Empty array means to use all default containers.
	 */
	function get_declared_containers()
	{
		// This function MUST be overriden by custom skin and return proper Array like sample below.
		// It is declared here only to avoid errors during upgrade in case of older/badly written Skins.

		// Array to override default containers from function get_skin_default_containers():
		// - Key is widget container code;
		// - Value: array( 0 - container name, 1 - container order ),
		//          NULL - means don't use the container, WARNING: it(only empty/without widgets) will be deleted from DB on changing of collection skin or on reload container definitions.
		/* Sample:
		return array(
				'sidebar_single'       => array( NT_('Sidebar Single'), 95 ),
				'front_page_main_area' => NULL,
			);
		*/

		return array();
	}


	/**
	 * Get supported collection kinds.
	 *
	 * This should be overloaded in skins.
	 *
	 * For each kind the answer could be:
	 * - 'yes' : this skin does support that collection kind (the result will be was is expected)
	 * - 'partial' : this skin is not a primary choice for this collection kind (but still produces an output that makes sense)
	 * - 'maybe' : this skin has not been tested with this collection kind
	 * - 'no' : this skin does not support that collection kind (the result would not be what is expected)
	 * There may be more possible answers in the future...
	 */
	public function get_supported_coll_kinds()
	{
		$supported_kinds = array(
				'main' => 'maybe',
				'std' => 'maybe',		// Blog
				'photo' => 'maybe',
				'forum' => 'no',
				'manual' => 'no',
				'group' => 'maybe',  // Tracker
				// Any kind that is not listed should be considered as "maybe" supported
			);

		return $supported_kinds;
	}


	final public function supports_coll_kind( $kind )
	{
		if( ! $this->provides_collection_skin() )
		{
			return 'no';
		}

		$supported_kinds = $this->get_supported_coll_kinds();

		if( isset($supported_kinds[$kind]) )
		{
			return $supported_kinds[$kind];
		}

		// When the skin doesn't say... consider it a "maybe":
		return 'maybe';
	}

	/*
	 * What CSS framework does has this skin been designed with?
	 *
	 * This may impact default markup returned by Skin::get_template() for example
	 */
	function get_css_framework()
	{
		return '';	// Other possibilities: 'bootstrap', 'foundation'... (maybe 'bootstrap4' later...)
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
			case 'name':
				// Restrict long skin names to avoid die error:
				$parvalue = utf8_substr( $parvalue, 0, 128 );
				break;
		}

		return parent::set( $parname, $parvalue, $make_null );
	}


	/**
	 * Get the declarations of the widgets that the skin recommends by default.
	 *
	 * The skin class defines a default set of widgets to used. Skins should override this.
	 *
	 * @param string Collection type: 'std', 'main', 'photo', 'group', 'forum', 'manual'
	 * @param string Skin type: 'normal' - Standard, 'mobile' - Phone, 'tablet' - Tablet
	 * @param array Additional params. Example value 'init_as_blog_b' => true
	 * @return array Array of default widgets:
	 *          - Key - Container code,
	 *          - Value - array of widget arrays OR SPECIAL VALUES:
	 *             - 'coll_type': Include this container only for collection kinds separated by comma, first char "-" means to exclude,
	 *             - 'type': Container type, empty - main container, other values: 'sub', 'page', 'shared', 'shared-sub',
	 *             - 'name': Container name,
	 *             - 'order': Container order,
	 *             - widget data array():
	 *                - 0: Widget order (*mandatory field*),
	 *                - 1: Widget code (*mandatory field*),
	 *                - 'params' - Widget params(array or serialized string),
	 *                - 'type' - Widget type(default = 'core', another value - 'plugin'),
	 *                - 'enabled' - Boolean value; default is TRUE; FALSE to install the widget as disabled,
	 *                - 'coll_type': Include this widget only for collection types separated by comma, first char "-" means to exclude,
	 *                - 'skin_type': Include this widget only for skin types separated by comma, first char "-" means to exclude,
	 *                - 'install' - Boolean value; default is TRUE; FALSE to skip this widget on install.
	 */
	function get_default_widgets( $coll_type, $skin_type = 'normal', $context = array() )
	{
		return array( '*' => true ); // For all containers, use b2evo defaults.
	}


	/**
	 * Load data from Request form fields.
	 *
	 * @return boolean true if loaded data seems valid.
	 */
	function load_from_Request()
	{
		// Name
		param_string_not_empty( 'skin_name', T_('Please enter a name.') );
		$this->set_from_Request( 'name' );

		// Skin type
		param( 'skin_type', 'string' );
		$this->set_from_Request( 'type' );

		return ! param_errors_detected();
	}


	/**
	 * Load params
	 */
	function load_params_from_Request()
	{
		load_funcs('plugins/_plugin.funcs.php');

		// Loop through all widget params:
		foreach( $this->get_param_definitions( array('for_editing'=>true) ) as $parname => $parmeta )
		{
			if( isset( $parmeta['type'] ) && $parmeta['type'] == 'input_group' )
			{
				if( ! empty( $parmeta['inputs'] ) )
				{
					foreach( $parmeta['inputs'] as $l_parname => $l_parmeta )
					{
						$l_parmeta['group'] = $parname; // inject group into meta
						autoform_set_param_from_request( $l_parname, $l_parmeta, $this, 'Skin' );
					}
				}
			}
			else
			{
				autoform_set_param_from_request( $parname, $parmeta, $this, 'Skin' );
			}
		}
	}


	/**
	 * Display a container
	 *
	 * @todo fp> if it doesn't get any skin specific, move it outta here! :P
	 * fp> Do we need Skin objects in the frontoffice at all? -- Do we want to include the dispatcher into the Skin object? WARNING: globals
	 * fp> We might want to customize the container defaults. -- Per blog or per skin?
	 *
	 * @param string Container name
	 * @param array Additional params
	 * @param string Container code
	 */
	function container( $sco_name, $params = array(), $container_code = NULL )
	{
		/**
		 * Blog currently displayed
		 * @var Blog
		 */
		global $Collection, $Blog;
		global $admin_url, $rsc_url;
		global $Timer, $Session, $debug;

		$params = array_merge( array(
				'container_display_if_empty' => true, // FALSE - If no widget, don't display container at all, TRUE - Display container anyway
				'container_start' => '',
				'container_end'   => '',
				// Restriction for Page Containers:
				'container_item_ID' => NULL,
			), $params );

		$WidgetContainer = isset( $params['WidgetContainer'] ) ? $params['WidgetContainer'] : NULL;

		if( ! empty( $WidgetContainer ) && $WidgetContainer->get( 'coll_ID' ) == 0 )
		{	// Shared container:
			$widgets_coll_ID = '';
		}
		else
		{	// Collection/skin container:
			$widgets_coll_ID = $Blog->ID;
		}

		if( $container_code === NULL )
		{	// Try to detect container in DB by name:
			global $DB;
			$SQL = new SQL( 'Get widget container of collection #'.$Blog->ID.' by name for current skin type' );
			$SQL->SELECT( 'wico_code' );
			$SQL->FROM( 'T_widget__container' );
			$SQL->WHERE( 'wico_name = '.$DB->quote( $sco_name ) );
			$SQL->WHERE_and( 'wico_coll_ID = '.$Blog->ID );
			$SQL->WHERE_and( 'wico_skin_type = '.$DB->quote( $Blog->get_skin_type() ) );
			$SQL->ORDER_BY( 'wico_ID' );
			$SQL->LIMIT( '1' );
			$container_code = $DB->get_var( $SQL );
		}

		$timer_name = 'skin_container('.$sco_name.')';
		$Timer->start( $timer_name );

		// Customize params with widget container properties:
		$container_params = widget_container_customize_params( $params, $container_code, $sco_name );

		// Start to get content of widgets:
		ob_start();

		$display_debug_containers = ( $debug == 2 ) || ( is_logged_in() && $Session->get( 'display_containers_'.$Blog->ID ) );

		if( $display_debug_containers )
		{ // Wrap container in visible container:
			echo '<div class="dev-blocks dev-blocks--container"><div class="dev-blocks-name">';
			if( check_user_perm( 'blog_properties', 'edit', false, $Blog->ID ) )
			{	// Display a link to edit this widget only if current user has a permission:
				echo '<span class="dev-blocks-action"><a href="'.$admin_url.'?ctrl=widgets&amp;blog='.$Blog->ID.'">Edit</a></span>';
			}
			echo 'Container: <b>'.$sco_name.'</b></div>';

			// Force to display container even if no widget:
			$container_params['container_display_if_empty'] = true;
		}

		if( $params['container_item_ID'] !== NULL )
		{	// Check restriction for page containers:
			if( empty( $WidgetContainer ) ||
			    ( $WidgetContainer->get_type() == 'page' && $WidgetContainer->get( 'item_ID' ) != $params['container_item_ID'] ) )
			{	// We should not try to get widgets from this container, because it is a not proper page container:
				$Widget_array = array();
			}
		}

		if( ! isset( $Widget_array ) )
		{	// Get enabled widget for the container:
			$EnabledWidgetCache = & get_EnabledWidgetCache();
			$Widget_array = & $EnabledWidgetCache->get_by_coll_container( $widgets_coll_ID,
				( $container_code === NULL ? $sco_name : $container_code ),// Use container code if it is defined, otherwise use container name
				( $container_code !== NULL ) );// Get by container code if it is defined
		}

		if( ! empty( $Widget_array ) )
		{
			foreach( $Widget_array as $w => $ComponentWidget )
			{ // Let the Widget display itself (with contextual params):
				if( $w == 0 )
				{ // Use special params for first widget in the current container
					$orig_params = $container_params;
					if( isset( $container_params['block_first_title_start'] ) )
					{
						$container_params['block_title_start'] = $container_params['block_first_title_start'];
					}
					if( isset( $container_params['block_first_title_end'] ) )
					{
						$container_params['block_title_end'] = $container_params['block_first_title_end'];
					}
				}
				$widget_timer_name = 'Widget->display('.$ComponentWidget->code.')';
				$Timer->start( $widget_timer_name );
				$ComponentWidget->display_with_cache( $params, array(
						// 'sco_name' => $sco_name, // fp> not sure we need that for now
					) );
				if( $w == 0 )
				{ // Restore the params for next widgets after first
					$container_params = $orig_params;
					unset( $orig_params );
				}
				$Timer->pause( $widget_timer_name );
			}
		}

		if( $display_debug_containers )
		{	// End of visible debug container:
			echo '</div>';
		}

		// Store content of widgets to var in order to display them in container wrapper:
		$container_widgets_content = ob_get_clean();

		if( $container_params['container_display_if_empty'] || ! empty( $Widget_array ) )
		{	// Display container wrapper with widgets content if it is not empty or we should display it anyway:

			// Display start of container wrapper:
			echo $container_params['container_start'];

			// Display widgets of the container:
			echo $container_widgets_content;

			if( empty( $Widget_array ) && is_logged_in() && $Session->get( 'designer_mode_'.$Blog->ID ) )
			{	// Display text for empty container on designer mode:
				echo '<div class="red">'.T_('Empty Container').'</div>';
			}

			// Display end of container wrapper:
			echo $container_params['container_end'];
		}

		$Timer->pause( $timer_name );
	}


	/**
	 * Discover containers included in skin files only in the given folder
	 *
	 * @param string Folder path or type:
	 *                  - '#skin_folder#' - Use skin folder of this skin
	 *                  - '#fallback_folders#' - Use fallback folders depending on skin version
	 *                  - real path on disk
	 * @param array Exclude the files
	 * @param boolean TRUE to display messages
	 * @return array Files that were prepared
	 */
	function discover_containers_by_folder( $folder, $exclude_files = array(), $display_messages = true )
	{
		global $Messages;

		switch( $folder )
		{
			case '#skin_folder#':
				// Get files from folder of this skin:
				global $skins_path;
				$skin_folder = $this->folder;
				$skin_path = $skins_path.$skin_folder.'/';
				break;

			case '#fallback_folders#':
				// Get files from fallback skin folders such as "skins_fallback_v5", "skins_fallback_v6", "skins_fallback_v7" and etc:
				for( $v = $this->get_api_version(); $v >= 5; $v-- )
				{	// Start with fallback files of current skin version and go down to find other fallback from older versions:
					if( $skin_fallback_path = skin_fallback_path( '', $v ) )
					{	// If fallback folder is detected for the version:
						$exclude_files = array_merge( $exclude_files, $this->discover_containers_by_folder( $skin_fallback_path, $exclude_files, $display_messages ) );
					}
				}
				return $exclude_files;

			default:
				// Use real path on disk:
				$skin_path = $folder;
				$skin_folder = basename( $skin_path );
				break;
		}

		// Store the file names to return
		$files = array();

		if( ! $dir = @opendir( $skin_path ) )
		{ // Skin directory not found!
			if( $display_messages )
			{
				$Messages->add( T_('Cannot open skin directory.'), 'error' ); // No trans
			}
			return $files;
		}

		// Go through all files in the skin directory:
		while( ( $file = readdir( $dir ) ) !== false )
		{
			if( is_array( $exclude_files ) && in_array( $file, $exclude_files ) )
			{ // Skip this file
				continue;
			}

			$af_main_path = $skin_path.$file;

			if( !is_file( $af_main_path ) || ! preg_match( '~\.php$~', $file ) )
			{ // Not a php template file, go to next:
				continue;
			}

			if( ! is_readable( $af_main_path ) )
			{ // Cannot open PHP file:
				if( $display_messages )
				{
					$Messages->add_to_group( sprintf( T_('Cannot read skin file &laquo;%s&raquo;!'), $skin_folder.'/'.$file ), 'error', T_('File read error:') );
				}
				continue;
			}

			$file_contents = @file_get_contents( $af_main_path );
			if( ! is_string( $file_contents ) )
			{ // Cannot get contents:
				if( $display_messages )
				{
					$Messages->add_to_group( sprintf( T_('Cannot read skin file &laquo;%s&raquo;!'), $skin_folder.'/'.$file ), 'error', T_('File read error:') );
				}
				continue;
			}

			$files[] = $file;

			// DETECT if the file contains containers:
			// if( ! preg_match_all( '~ \$Skin->container\( .*? (\' (.+?) \' )|(" (.+?) ") ~xmi', $file_contents, $matches ) )
			if( ! preg_match_all( '~ (\$Skin->|skin_|widget_)container\( .*? ([\'"] (.+?) [\'"]) ~xmi', $file_contents, $matches ) )
			{ // No containers in this file, go to next:
				continue;
			}

			$c = 0;
			foreach( $matches[3] as $container )
			{
				if( empty( $container ) )
				{ // regexp empty match -- NOT a container:
					continue;
				}

				if( $matches[1][ $c ] == 'widget_' )
				{	// Function widget_container() already uses container code as first param:
					$container_code = $container;
					// We should create container name from container code:
					$container = ucwords( str_replace( '_', ' ', $container_code ) );
				}
				else
				{	// Old functions $Skin->container() and skin_container() use container name, so we should auto convert it to code:
					$container_code = preg_replace( '/[^a-z\d]+/', '_', strtolower( $container ) );
				}

				if( in_array( $container_code, $this->container_list ) )
				{ // we already have that one
					continue;
				}

				// We have one more container:
				$c++;

				$this->container_list[ $container_code ] = array( $container, $c );
			}

			if( $c && $display_messages )
			{
				$Messages->add_to_group( sprintf( T_('%d containers have been found in skin template &laquo;%s&raquo;.'), $c, $skin_folder.'/'.$file ), 'success', sprintf( T_('Containers found in skin "%s":'), $skin_folder ) );
			}
		}

		return $files;
	}


	/**
	 * Discover containers included in skin files
	 *
	 * @param boolean TRUE to display messages
	 */
	function discover_containers( $display_messages = true )
	{
		global $Messages;

		$this->container_list = array();

		// Find the containers in the current skin folder:
		$skin_files = $this->discover_containers_by_folder( '#skin_folder#', array(), $display_messages );

		// Find the containers in the fallback skin folders with excluding the files that are contained in the skin folder:
		$this->discover_containers_by_folder( '#fallback_folders#', $skin_files, $display_messages );

		if( empty( $this->container_list ) )
		{
			if( $display_messages )
			{
				$Messages->add( T_('No containers found in this skin!'), 'error' );
			}
			return false;
		}

		return true;
	}


	/**
	 * Get the list of containers that have been previously discovered for this skin.
	 *
	 * @return array
	 */
	function get_containers()
	{
		if( is_null( $this->container_list ) )
		{
			$skin_declared_containers = $this->get_declared_containers();
			if( $this->get_api_version() > 5 || ! empty( $skin_declared_containers ) )
			{	// Get default containers and containers what declared by this skin:
				// All v6+ skins must use either declared containers or default containers,
				$this->container_list = array_merge( get_skin_default_containers(), $skin_declared_containers );

				foreach( $this->container_list as $wico_code => $wico_data )
				{
					if( $wico_data === NULL )
					{	// Exclude containers which are not used in the current Skin:
						unset( $this->container_list[ $wico_code ] );
					}

					if( ! is_array( $wico_data ) || // Must be array
					    ! isset( $wico_data[0] ) || // 1st for container title
					    ! isset( $wico_data[1] ) || // 2nd for container order
					    ! is_number( $wico_data[1] ) ) // Order must be a number
					{	// Skip wrong container data:
						unset( $this->container_list[ $wico_code ] );
					}
				}

				// Sort skin containers by order field:
               # var_dump($this->container_list);
               #bug
				@uasort( $this->container_list, array( $this, 'sort_containers' ));
			}
			else
			{	// Get containers from skin files:
				// Only v5 skins may use containers searched in skin files if they don't declare at least one container:
				$this->discover_containers( false );
			}
		}
        
		return $this->container_list;
	}


	/**
	 * Callback function to sort widget containers by order field
	 *
	 * @param array Container data: 0 - name, 1 - order
	 * @param array Container data: 0 - name, 1 - order
	 * @return boolean
	 */
	function sort_containers( $a_container, $b_container )
	{
		// Use 0 if order field is not defined:
		$a_container_order = isset( $a_container[1] ) ? $a_container[1] : 0;
		$b_container_order = isset( $b_container[1] ) ? $b_container[1] : 0;

		return $a_container_order > $b_container_order;
	}


	/**
	 * Display skinshot for skin folder in various places.
	 *
	 * Including for NON installed skins.
	 *
	 * @param string Skin folder
	 * @param string Skin name
	 * @param array Params
	 */
	static function disp_skinshot( $skin_folder, $skin_name, $disp_params = array() )
	{
		global $skins_path, $skins_url, $kind;

		$disp_params = array_merge( array(
				'selected'        => false,
				'skinshot_class'  => 'skinshot',
				'skin_compatible' => true,
				'highlighted'     => false,
			), $disp_params );

		if( isset( $disp_params['select_url'] ) )
		{	// Initialize params for link to SELECT new skin for collection:
			$skin_url = $disp_params['select_url'];
			$select_a_begin = '<a href="'.format_to_output( $disp_params['select_url'], 'htmlattr' ).'"'
					.( isset( $disp_params['onclick'] ) ? ' onclick="'.format_to_output( $disp_params['onclick'] , 'htmlattr' ).'"' : '' )
					.' title="'.format_to_output( T_('Select this skin!'), 'htmlattr' ).'">';
			$select_a_end = '</a>';
		}
		elseif( isset( $disp_params['function_url'] ) )
		{	// Initialize params for link to INSTALL new skin and probably select this automatically for collection:
			$skin_url = $disp_params['function_url'];
			$select_a_begin = '<a href="'.$disp_params['function_url'].'"'
				.( isset( $disp_params['onclick'] ) ? ' onclick="'.format_to_output( $disp_params['onclick'] , 'htmlattr' ).'"' : '' )
				.' title="'.format_to_output( T_('Install NOW!'), 'htmlattr' ).'">';
			$select_a_end = '</a>';
		}
		else
		{	// No link:
			$skin_url = '';
			$select_a_begin = '';
			$select_a_end = '';
		}

		// Display skinshot:
		echo '<div class="'.$disp_params['skinshot_class'].( $disp_params['selected'] ? ' skinshot_current' : '' ).( $disp_params['highlighted'] ? ' evo_highlight' : '' ).'">';
		echo '<div class="skinshot_placeholder">';
		if( file_exists( $skins_path.$skin_folder.'/skinshot.png' ) )
		{
			echo $select_a_begin;
			echo '<img src="'.$skins_url.$skin_folder.'/skinshot.png" width="240" height="180" alt="'.$skin_folder.'" />';
			echo $select_a_end;
		}
		elseif( file_exists( $skins_path.$skin_folder.'/skinshot.jpg' ) )
		{
			echo $select_a_begin;
			echo '<img src="'.$skins_url.$skin_folder.'/skinshot.jpg" width="240" height="180" alt="'.$skin_folder.'" />';
			echo $select_a_end;
		}
		elseif( file_exists( $skins_path.$skin_folder.'/skinshot.gif' ) )
		{
			echo $select_a_begin;
			echo '<img src="'.$skins_url.$skin_folder.'/skinshot.gif" width="240" height="180" alt="'.$skin_folder.'" />';
			echo $select_a_end;
		}
		else
		{
			echo '<div class="skinshot_noshot">'.( empty( $disp_params['same_skin'] ) ? T_('No skinshot available for') : '' ).'</div>';
			echo '<div class="skinshot_name">'.$select_a_begin.$skin_folder.$select_a_end.'</div>';
		}
		echo '</div>';

		//
		echo '<div class="legend">';
		if( isset( $disp_params['function'] ) )
		{
			echo '<div class="actions">';
			switch( $disp_params['function'] )
			{
				case 'broken':
					echo '<span class="text-danger">';
					if( ! empty( $disp_params['msg'] ) )
					{
						echo $disp_params[ 'msg' ];
					}
					else
					{
						echo T_('Broken.');
					}
					echo '</span>';

					if( ! empty( $disp_params['help_info'] ) )
					{
						echo ' '.get_icon( 'help', 'imgtag', array( 'title' => $disp_params['help_info'] ) );
					}
					break;

				case 'install':
					// Display a link to install the skin
					if( ! empty( $skin_url ) )
					{
						echo '<a href="'.$skin_url.'" title="'.T_('Install NOW!').'">';
						echo T_('Install NOW!').'</a>';
					}
					if( empty( $kind ) && get_param( 'tab' ) != 'coll_skin' && get_param( 'tab' ) != 'site_skin' )
					{	// Don't display the checkbox on new collection creating form and when we install one skin for the selected collection:
						$skin_name_before = '<label><input type="checkbox" name="skin_folders[]" value="'.$skin_name.'" /> ';
						$skin_name_after = '</label>';
					}
					break;

				case 'upgrade':
					$link_text = T_('Upgrade NOW!');
				case 'downgrade':
					if( empty( $link_text ) ) $link_text = T_('Downgrade NOW!');

					if( ! empty( $skin_url ) )
					{
						echo '<a href="'.$skin_url.'" title="'.$link_text.'">';
						echo $link_text.'</a>';
					}
					if( empty( $kind ) && get_param( 'tab' ) != 'coll_skin' && get_param( 'tab' ) != 'site_skin' )
					{	// Don't display the checkbox on new collection creating form and when we install one skin for the selected collection:
						$skin_name_before = '<label><input type="checkbox" name="skin_folders[]" value="'.$skin_name.'" /> ';
						$skin_name_after = '</label>';
					}
					break;

				case 'select':
					// Display a link to preview the skin:
					if( ! empty( $disp_params['function_url'] ) )
					{
						echo '<a href="'.$disp_params['function_url'].'" target="_blank" title="'.T_('Preview blog with this skin in a new window').'">';
						echo /* TRANS: Verb */ T_('Preview').'</a>';
					}
					break;
			}
			echo '</div>';
		}
		echo '<strong>'
				.( empty( $skin_name_before ) ? '<label>' : $skin_name_before )
					.$skin_name
				.( empty( $skin_name_after ) ? '</label>' : $skin_name_after )
			.'</strong>';
		echo '</div>';
		echo '</div>';
	}


	/**
	 * Get definitions for editable params
	 *
	 * @todo this is destined to be overridden by derived Skin classes
	 *
	 * @see Plugin::GetDefaultSettings()
	 * @param local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		global $Blog;

		$r = array();

		// Skin v7 definitions for kind of current collection:
		if( $this->get_api_version() == 7 &&
		    $this->provides_collection_skin() &&
		    ! empty( $Blog ) &&
		    method_exists( $this, 'get_param_definitions_'.$Blog->get( 'type' ) ) )
		{	// If skin has declared the method for collection kind:
			$coll_kind_param_definitions = call_user_func( array( $this, 'get_param_definitions_'.$Blog->get( 'type' ) ), $params );

			$r = array_merge( $coll_kind_param_definitions, $r );
		}

		return $r;
	}


	/**
	 * Get a skin specific param value from current Blog
	 *
	 * @param string Setting name
	 * @param string Input group name
	 * @param mixed Default value, Set to different than NULL only if it is called from a Skin::get_param_definitions() function to avoid infinite loop
	 * @return string|array|NULL
	 */
	function get_setting( $parname, $group = NULL, $default_value = NULL )
	{
		global $Collection, $Blog, $Settings;

		if( ! empty( $group ) )
		{ // $parname is prefixed with $group, we'll remove the group prefix
			$parname = substr( $parname, strlen( $group ) );
		}

		// Name of the setting in the blog settings:
		$setting_name = 'skin'.$this->ID.'_'.$group.$parname;

		if( isset( $Blog ) && $this->provides_collection_skin() )
		{	// Get skin settings of the current collection only if skin is provided for collections:
			$value = $Blog->get_setting( $setting_name );
		}
		elseif( $this->provides_site_skin() )
		{	// Get skin settings of the site only if skin is provided for site:
			$value = $Settings->get( $setting_name );
		}
		else
		{
			$value = NULL;
		}

		if( ! is_null( $value ) )
		{	// We have a value for this param:
			return $value;
		}

		if( $default_value !== NULL )
		{	// Use defined default value when it is not saved in DB yet:
			// (This call is used to get a value from function Skin::get_param_definition() to avoid infinite loop)
			return $default_value;
		}

		return $this->get_setting_default_value( $group.$parname, $group );
	}


	/**
	 * Get value of setting with format "checklist" which values are stored as array
	 *
	 * @param string Setting name
	 * @param string Option name
	 * @return string|NULL Option value or NULL if setting doesn't exist
	 */
	function get_checklist_setting( $setting_name, $option_name )
	{
		$setting_values = $this->get_setting( $setting_name );

		return isset( $setting_values[ $option_name ] ) ? $setting_values[ $option_name ] : NULL;
	}


	/**
	 * Get a skin specific param default value
	 *
	 * @param string Setting name
	 * @param string Input group name
	 * @return string|array|NULL
	 */
	function get_setting_default_value( $parname, $group = NULL )
	{
		if( ! empty ( $group ) )
		{
			$parname = substr( $parname, strlen( $group ) );
		}

		// Try default values:
		$params = $this->get_param_definitions( NULL );
		if( isset( $params[ $parname ]['defaultvalue'] ) )
		{ // We have a default value:
			return $params[ $parname ]['defaultvalue'] ;
		}
		elseif( isset( $params[ $parname ]['type'] ) &&
		        $params[ $parname ]['type'] == 'checklist' &&
		        ! empty( $params[ $parname ]['options'] ) )
		{ // Get default values for checkbox list:
			$options = array();
			foreach( $params[ $parname ]['options'] as $option )
			{
				if( isset( $option[2] ) )
				{ // Set default value only if it is defined by skin:
					$options[ $option[0] ] = $option[2];
				}
			}
			return $options;
		}
		elseif( isset( $params[ $parname ]['type'] ) &&
						$params[ $parname ]['type'] == 'fileselect' &&
						! empty( $params[ $parname ]['initialize_with'] ) &&
						$default_File = & get_file_by_abspath( $params[ $parname ]['initialize_with'], true ) )
		{ // Get default value for fileselect
			return $default_File->ID;
		}
		elseif( ! empty( $group ) &&
						isset( $params[ $group ]['type'] ) &&
						$params[ $group ]['type'] == 'input_group' &&
						! empty( $params[ $group ]['inputs'] ) &&
						isset( $params[ $group ]['inputs'][ $parname ] ) )
		{
			return $params[ $group ]['inputs'][ $parname ]['defaultvalue'];
		}

		return NULL;
	}


	/**
	 * Set dynamic style rule and store in array $this->dynamic_styles
	 * (Use Skin->get_dynamic_styles() to get style as single string)
	 *
	 * @param string Setting name
	 * @param string Style template with mask instead of setting value
	 * @param array Additional params
	 */
	function dynamic_style_rule( $setting_name, $style_template, $params = array() )
	{
		$params = array_merge( array(
				'value'   => NULL, // Custom value, different of what stored in the setting
				'options' => NULL, // Options per each value, Used for <select> or radio settings
				'suffix'  => NULL, // Suffix which should de added after value on each update by customzer JS code, e.g. 'px', '%'
				'type'    => NULL, // Type of the field, e.g. 'image_file', 'not_empty'
				'check'   => NULL, // 'not_empty' - don't apply style rule completely if value is empty
			), $params );

		if( $params['value'] === NULL )
		{	// Try to get current setting value:
			$setting_value = $this->get_setting( $setting_name );
		}
		else
		{	// Use custom value:
			$setting_value = $params['value'];
		}

		if( $setting_value === NULL )
		{	// No value for the requested setting:
			return;
		}

		global $Session, $blog;

		if( is_array( $params['options'] ) &&
		    isset( $params['options'][ $setting_value ] ) )
		{	// Get value from predefined array:
			$setting_value = $params['options'][ $setting_value ];
		}

		if( $params['type'] == 'image_file' )
		{	// Special setting type as ID of image file:
			if( $this->get_setting( $setting_name ) &&
					( $FileCache = & get_FileCache() ) &&
					( $image_File = & $FileCache->get_by_ID( $this->get_setting( $setting_name ), false, false ) ) &&
					$image_File->exists() )
			{
				$setting_value = 'url("'.$image_File->get_url().'")';
			}
			else
			{
				$setting_value = 'none';
			}
		}

		if( $params['suffix'] !== NULL )
		{	// Suffix for value:
			$setting_value .= $params['suffix'];
		}

		if( $Session->get( 'customizer_mode_'.$blog ) )
		{	// If customizer mode is enabled we should append a special css comment code
			// in order to quick change the value from the customizer panel on change input value:
			$setting_options = '';

			if( is_array( $params['options'] ) )
			{	// Append value presets to the comment in order to select them by JavaScript:;
				$setting_options .= '/options:';
				$p = 1;
				foreach( $params['options'] as $preset_val => $preset_style )
				{
					$setting_options .= $preset_val.'$'.$preset_style
						// separator between value options:
						.( $p < count( $params['options'] ) ? '|' : '' );
					$p++;
				}
			}

			if( $params['suffix'] !== NULL )
			{	// Suffix for value:
				$setting_options .= '/suffix:'.$params['suffix'];
			}

			if( $params['type'] !== NULL )
			{	// Setting type:
				$setting_options .= '/type:'.$params['type'];
			}

			if( $params['check'] == 'not_empty' )
			{	// If we should apply rule only when setting value is not empty:
				// Store full template, to get it from here on customizer mode by JS:
				$setting_options .= '/template:'.str_replace( '$setting_value$', '#setting_value#', $style_template );
				if( empty( $setting_value ) )
				{	// Don't apply rule completely when value is empty:
					$style_template = '';
				}
				// Wrap full template instead of value as for normal rule in order to clear it in case of empty value:
				$style_template = '/*customize:*/'.$style_template.'/*'.$setting_name.$setting_options.'*/';
			}
			else
			{	// Normal rule:
				$setting_value = '/*customize:*/'.$setting_value.'/*'.$setting_name.$setting_options.'*/';
			}
		}
		else
		{	// If customizer mode is disabled
			if( $params['check'] == 'not_empty' && empty( $setting_value ) )
			{	// Don't apply rule completely when value is empty:
				return;
			}
		}

		// Replace mask with setting value:
		$this->add_dynamic_style( str_replace( '$setting_value$', $setting_value, $style_template ) );
	}


	/**
	 * Add style rule in array $this->dynamic_styles
	 *
	 * @param string Style rule
	 */
	function add_dynamic_style( $style_rule )
	{
		if( ! isset( $this->dynamic_styles ) )
		{
			$this->dynamic_styles = array();
		}

		// Replace mask with setting value:
		$this->dynamic_styles[] = $style_rule;
	}


	/**
	 * Get dynamic style rules
	 *
	 * @return string
	 */
	function get_dynamic_styles()
	{
		return isset( $this->dynamic_styles ) ? implode( "\n", $this->dynamic_styles ) : '';
	}


	/**
	 * Add dynamic CSS rules headline
	 *
	 * @param string CSS rule for media exception of whole dynamic styles
	 */
	function add_dynamic_css_headline( $media_exception = NULL )
	{
		$dynamic_css = $this->get_dynamic_styles();
		if( ! empty( $dynamic_css ) )
		{
			if( $media_exception !== NULL )
			{	// Use media exception:
				$dynamic_css = $media_exception.'{ '.$dynamic_css.' }';
			}
			$dynamic_css = '<style type="text/css" id="evo_skin_styles">
<!--
'.$dynamic_css.'
-->
		</style>';
			add_headline( $dynamic_css );
		}
	}


	/**
	 * Get current skin post navigation setting.
	 * Possible values:
	 *    - NULL - In this case the Blog post navigation setting will be used
	 *    - 'same_category' - to always navigate through the same category in this skin
	 *    - 'same_author' - to always navigate through the same authors in this skin
	 *    - 'same_tag' - to always navigate through the same tags in this skin
	 *
	 * Set this to not NULL only if the same post navigation should be used in every Blog where this skin is used
	 */
	function get_post_navigation()
	{
		return NULL;
	}


	/**
	 * Get current skin path
	 * @return string
	 */
	function get_path()
	{
		global $skins_path;

		return trailing_slash($skins_path.$this->folder);
	}


	/**
	 * Get current skin URL
	 * @return string
	 */
	function get_url()
	{
		global $skins_url;

		return trailing_slash($skins_url.$this->folder);
	}


	/**
	 * Set a skin specific param value for current Blog or Site
	 *
	 * @param string parameter name
	 * @param mixed parameter value
	 */
	function set_setting( $parname, $parvalue )
	{
		global $Collection, $Blog, $Settings;

		// Name of the setting in the settings:
		$setting_name = 'skin'.$this->ID.'_'.$parname;

		// Convert array values into string for DB storage
		if( is_array( $parvalue ) )
		{
			$parvalue = serialize( $parvalue );
		}

		if( isset( $Blog ) )
		{	// Set collection skin setting:
			$Blog->set_setting( $setting_name, $parvalue );
		}
		else
		{ // Set site skin setting:
			$Settings->set( $setting_name, $parvalue );
		}
	}


	/**
	 * Save skin specific settings for current blgo to DB
	 */
	function dbupdate_settings()
	{
		global $Collection, $Blog, $Settings;

		if( isset( $Blog ) )
		{	// Update collection skin settings:
			$Blog->dbupdate();
		}
		else
		{	// Update site skin settings:
			$Settings->dbupdate();
		}
	}


	/**
	 * Get ready for displaying the skin.
	 *
	 * This method may register some CSS or JS.
	 * The default implementation can register a few common things that you may request in the $features param.
	 * This is where you'd specify you want to use BOOTSTRAP, etc.
	 *
	 * If this doesn't do what you need you may add functions like the following to your skin's display_init():
	 * require_js_async() , require_js_defer() , require_css() , add_js_headline()
	 *
	 * @param array of possible features you want to include. If empty, will default to {'b2evo_base', 'style', 'colorbox'} for backwards compatibility.
	 */
	function display_init( /*optional: $features = array() */ )
	{
		global $debug, $Messages, $disp, $UserSettings, $Collection, $Blog, $Session;

		// We get the optional arg this way for PHP7 comaptibility:
		@list( $features ) = func_get_args();

		if( empty($features) )
		{	// Fall back to v5 default set of features:
			$features = array( 'b2evo_base_css', 'style_css', 'colorbox', 'disp_auto' );
		}

		// "Temporary" patch to at least have disp_auto unless another disp_xxx was specified. Use 'disp_off' to NOT include anuthing.
		if( !preg_grep( '/disp_.*/', $features ) )
		{
			$features[] = 'disp_auto';
		}

		// We're NOT using foreach so that the array can continue to grow during parsing: (see 'disp_auto')
		for( $i = 0; isset($features[$i]); $i++ )
		{
			// Get next feature to include:
			$feature = $features[$i];

			switch( $feature )
			{
				case 'superbundle':
					// Include jQuery + Bootstrap + General front-office scripts:
					require_js_defer( 'build/bootstrap-evo_frontoffice-superbundle.bmin.js', 'blog' );
					// Initialize font-awesome icons and use them as a priority over the glyphicons, @see get_icon()
					init_fontawesome_icons( 'fontawesome-glyphicons', 'blog', false /* Don't load CSS file because it is bundled */ );
					// Include the bootstrap-b2evo_base CSS (NEW / v6 style) - Use this when you use Bootstrap:
					if( $debug )
					{	// Use readable CSS:
						// rsc/css/font-awesome.css
						// rsc/css/bootstrap/bootstrap.css
						// rsc/build/bootstrap-b2evo_base.bundle.css:
						//  - rsc/less/bootstrap-basic_styles.less
						//  - rsc/less/bootstrap-basic.less
						//  - rsc/less/bootstrap-blog_base.less
						//  - rsc/less/bootstrap-item_base.less
						//  - rsc/less/bootstrap-evoskins.less
						require_css( 'bootstrap-b2evo_base-superbundle.bundle.css', 'blog' ); // CSS concatenation of the above
					}
					else
					{	// Use minified CSS:
						require_css( 'bootstrap-b2evo_base-superbundle.bmin.css', 'blog' ); // Concatenation + Minifaction of the above
					}
					break;

				case 'jquery':
					// Include jQuery:
					if( ! in_array( 'superbundle', $features ) )
					{	// Don't include when it is already bundled:
						require_js_defer( '#jquery#', 'blog' );
					}
					break;

				case 'font_awesome':
					// Initialize font-awesome icons and use them as a priority over the glyphicons, @see get_icon()
					init_fontawesome_icons( 'fontawesome-glyphicons', 'blog' );
					break;

				case 'bootstrap':
					// Include Bootstrap:
					if( ! in_array( 'superbundle', $features ) )
					{	// Don't include when it is already bundled:
						require_js_defer( '#bootstrap#', 'blog' );
						require_css( '#bootstrap_css#', 'blog' );
					}
					break;

				case 'bootstrap_theme_css':
					// Include the Bootstrap Theme CSS:
					require_css( '#bootstrap_theme_css#', 'blog' );
					break;

				case 'bootstrap_evo_css':
					if( in_array( 'superbundle', $features ) )
					{	// Don't include when it is already bundled:
						break;
					}
					// Include the bootstrap-b2evo_base CSS (NEW / v6 style) - Use this when you use Bootstrap:
					if( $debug )
					{	// Use readable CSS:
						// rsc/less/bootstrap-basic_styles.less
						// rsc/less/bootstrap-basic.less
						// rsc/less/bootstrap-blog_base.less
						// rsc/less/bootstrap-item_base.less
						// rsc/less/bootstrap-evoskins.less
						require_css( 'bootstrap-b2evo_base.bundle.css', 'blog' );  // CSS concatenation of the above
					}
					else
					{	// Use minified CSS:
						require_css( 'bootstrap-b2evo_base.bmin.css', 'blog' ); // Concatenation + Minifaction of the above
					}
					break;

				case 'bootstrap_messages':
					// Initialize $Messages Class to use Bootstrap styles:
					$Messages->set_params( array(
							'class_success'  => 'alert alert-dismissible alert-success fade in',
							'class_warning'  => 'alert alert-dismissible alert-warning fade in',
							'class_error'    => 'alert alert-dismissible alert-danger fade in',
							'class_note'     => 'alert alert-dismissible alert-info fade in',
							'before_message' => '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>',
						) );
					break;

				case 'b2evo_base_css':
					// Include the b2evo_base CSS (OLD / v5 style) - Use this when you DON'T use Bootstrap:
					if( $debug )
					{	// Use readable CSS:
						// require_css( 'basic_styles.css', 'blog' ); // the REAL basic styles
						// require_css( 'basic.css', 'blog' ); // Basic styles
						// require_css( 'blog_base.css', 'blog' ); // Default styles for the blog navigation
						// require_css( 'item_base.css', 'blog' ); // Default styles for the post CONTENT
						// require_css( 'b2evo_base.bundle.css', 'blog' ); // Concatenation of the above
						require_css( 'b2evo_base.bundle.css', 'blog' ); // Concatenation + Minifaction of the above
					}
					else
					{	// Use minified CSS:
						require_css( 'b2evo_base.bmin.css', 'blog' ); // Concatenation + Minifaction of the above
					}
					break;

				case 'style_css':
					// Include the default skin style.css:
					// You should make sure this is called ahead of any custom generated CSS.
					if( $this->use_min_css == false
						|| $debug
						|| ( $this->use_min_css == 'check' && ! file_exists( $this->get_path().'style.min.css' ) ) )
					{	// Use readable CSS:
						$this->require_css( 'style.css' );
					}
					else
					{	// Use minified CSS:
						$this->require_css( 'style.min.css' );
					}

					if( $this->get_api_version() == 7 )
					{	// Get skin css file from folder of collection kind:
						$skin_css_folder = $Blog->get( 'type' ).'/';
						if( file_exists( $this->get_path().$skin_css_folder.'style.css' ) &&
							( $this->use_min_css == false
							|| $debug
							|| ( $this->use_min_css == 'check' && ! file_exists( $this->get_path().$skin_css_folder.'style.min.css' ) ) ) )
						{	// Use readable CSS:
							$this->require_css( $skin_css_folder.'style.css' );
						}
						elseif( file_exists( $this->get_path().$skin_css_folder.'style.min.css' ) )
						{	// Use minified CSS:
							$this->require_css( $skin_css_folder.'style.min.css' );
						}
					}
					break;

				case 'colorbox':
					// Colorbox (a lightweight Lightbox alternative) allows to zoom on images and do slideshows with groups of images:
					if( $this->get_setting( 'colorbox' ) )
					{	// This can be enabled by a setting in skins where it may be relevant
						require_js_helper( 'colorbox', 'blog' );
					}
					break;

				case 'disp_auto':
					// Automatically add a disp_xxx for current $disp:
					$features[] = 'disp_'.$disp;
					break;

				case 'disp_single':
					// Specific features for disp=single:
				case 'disp_page':
					// Specific features for disp=page:

					global $Collection, $Blog, $Item;

					if( ! empty( $Item ) && $Item->can_receive_webmentions() )
					{	// Send header and initialize <link> tags in order to mark current Item can receive webmentions by current User(usually anonymous user):
						$webmention_url = $Blog->get_htsrv_url().'webmention.php';
						header( 'Link: <'.$webmention_url.'>; rel="webmention"' );
						add_headline( '<link rel="webmention" href="'.$webmention_url.'" />' );
					}

					// Used to set rating for a new comment:
					init_ratings_js( 'blog' );

					// Used to vote on an item:
					init_voting_item_js( 'blog' );

					// Used to vote on the comments:
					init_voting_comment_js( 'blog' );

					// Used to display a tooltip to the right of plugin help icon:
					init_popover_js( 'blog', $this->get_template( 'tooltip_plugin' ) );

					// Used to autocomplete usernames in textarea:
					init_autocomplete_usernames_js( 'blog' );

					if( $Blog->get_setting( 'allow_rating_comment_helpfulness' ) )
					{ // Load jquery UI to animate background color on change comment status or on vote:
						require_js_defer( '#jqueryUI#', 'blog' );
					}

					if( $Blog->get_setting( 'use_workflow' ) && check_user_perm( 'blog_can_be_assignee', 'edit', false, $Blog->ID ) )
					{	// Initialize JS to autcomplete user logins and date picker to edit workflow properties:
						init_autocomplete_login_js( 'blog', $this->get_template( 'autocomplete_plugin' ) );
						init_datepicker_js( 'blog' );
					}

					// Used to change link position:
					require_js_defer( 'backoffice.js', 'blog' );
					break;

				case 'disp_users':
					// Specific features for disp=users:

					// Used to add new search field "Specific criteria":
					require_js_defer( '#jqueryUI#', 'blog' );
					require_css( '#jqueryUI_css#', 'blog' );
					// Load jQuery QueryBuilder plugin files for user list filters:
					init_querybuilder_js( 'blog' );

					// Require results.css to display thread query results in a table:
					if( ! in_array( 'bootstrap', $features ) )
					{ // Only for NON-bootstrap skins
						require_css( 'results.css', 'blog' ); // Results/tables styles
					}

					// Require functions.js to show/hide a panel with filters:
					require_js_defer( 'functions.js', 'blog' );
					break;

				case 'disp_messages':
					// Specific features for disp=messages:

					// Used to display a tooltip to the right of plugin help icon:
					init_popover_js( 'blog', $this->get_template( 'tooltip_plugin' ) );

					// Require results.css to display message query results in a table
					if( ! in_array( 'bootstrap', $features ) )
					{ // Only for NON-bootstrap skins
						require_css( 'results.css', 'blog' ); // Results/tables styles
					}

					// Require functions.js to show/hide a panel with filters:
					require_js_defer( 'functions.js', 'blog' );
					break;

				case 'disp_contacts':
					// Specific features for disp=contacts:

					// Used for combo box "Add all selected contacts to this group":
					require_js_defer( 'form_extensions.js', 'blog' );

					// Require results.css to display contact query results in a table
					if( ! in_array( 'bootstrap', $features ) )
					{ // Only for NON-bootstrap skins
						require_css( 'results.css', 'blog' ); // Results/tables styles
					}

					// Require functions.js to show/hide a panel with filters:
					require_js_defer( 'functions.js', 'blog' );
					break;

				case 'disp_threads':
					// Specific features for disp=threads:

					if( in_array( get_param( 'action' ), array( 'new', 'create', 'preview' ) ) )
					{ // Used to suggest usernames for the field "Recipients":
						init_tokeninput_js( 'blog' );
					}

					// Used to display a tooltip to the right of plugin help icon:
					init_popover_js( 'blog', $this->get_template( 'tooltip_plugin' ) );

					// Require results.css to display thread query results in a table:
					if( ! in_array( 'bootstrap', $features ) )
					{ // Only for NON-bootstrap skins
						require_css( 'results.css', 'blog' ); // Results/tables styles
					}

					// Require functions.js to show/hide a panel with filters
					require_js_defer( 'functions.js', 'blog' );
					break;

				case 'disp_search':
					// Used to suggest usernames for the field "Recipients":
					init_tokeninput_js( 'blog' );
					// Initialize JS to autcomplete user logins and date picker to edit workflow properties:
					init_autocomplete_login_js( 'blog', $this->get_template( 'autocomplete_plugin' ) );
					break;

				case 'disp_login':
				case 'disp_access_requires_login':
				case 'disp_content_requires_login':
					// Specific features for disp=login and disp=access_requires_login:

					global $Settings, $Plugins;

					if( can_use_hashed_password() )
					{	// Include JS for client-side password hashing:
						require_js_defer( 'build/sha1_md5.bmin.js', 'blog' );
						require_js_defer( '#jquery#', 'blog' );
						require_js_defer( 'src/evo_init_display_login_js_handler.js', 'blog' );
					}
					break;

				case 'disp_profile':
					// Specific features for disp=profile:

					// Used to add new user fields:
					init_userfields_js( 'blog', $this->get_template( 'tooltip_plugin' ) );

					// Used to crop profile pictures:
					require_js_defer( '#jquery#', 'blog' );
					require_js_defer( '#jcrop#', 'blog' );
					require_css( '#jcrop_css#', 'blog' );

					// Activate bozo validator in order not to miss the changes of the edit forms on page leave:
					if( $UserSettings->get( 'control_form_abortions' ) )
					{	// Only if user wants this:
						require_js_defer( 'bozo_validator.js', 'blog' );
					}
					break;

				case 'disp_avatar':
					// Specific features for disp=avatar:

					// Used to crop profile pictures:
					require_js_defer( '#jquery#', 'blog' );
					require_js_defer( '#jcrop#', 'blog' );
					require_css( '#jcrop_css#', 'blog' );

					// Activate bozo validator in order not to miss the changes of the edit forms on page leave:
					if( $UserSettings->get( 'control_form_abortions' ) )
					{	// Only if user wants this:
						require_js_defer( 'bozo_validator.js', 'blog' );
					}
					break;

				case 'disp_visits':
					// Require functions.js to show/hide a panel with filters
					require_js_defer( 'functions.js', 'blog' );
					break;

				case 'disp_pwdchange':
					// Specific features for disp=pwdchange:
				case 'disp_userprefs':
					// Specific features for disp=userprefs:
				case 'disp_subs':
					// Specific features for disp=subs:
				case 'disp_register_finish':
					// Specific features for disp=register_finish:

					// Activate bozo validator in order not to miss the changes of the edit forms on page leave:
					if( $UserSettings->get( 'control_form_abortions' ) )
					{	// Only if user wants this:
						require_js_defer( 'bozo_validator.js', 'blog' );
					}
					break;

				case 'disp_edit':
					// Specific features for disp=edit:

					// Require results.css to display attachments as a result table:
					if( ! in_array( 'bootstrap', $features ) )
					{	// Only for NON-bootstrap skins:
						require_css( 'results.css', 'blog' ); // Results/tables styles
					}

					init_tokeninput_js( 'blog' );

					// Used to display a date picker for date form fields:
					init_datepicker_js( 'blog' );

					// Used to display a tooltip to the right of plugin help icon:
					init_popover_js( 'blog', $this->get_template( 'tooltip_plugin' ) );

					// Used to switch to advanced editing and for link position changing:
					require_js_defer( 'backoffice.js', 'blog' );

					// Used to automatically checks the matching extracat when we select a new main cat:
					require_js_defer( 'extracats.js', 'blog' );

					// Used to autocomplete usernames in textarea:
					init_autocomplete_usernames_js( 'blog' );

					// Activate bozo validator in order not to miss the changes of the edit forms on page leave:
					if( $UserSettings->get( 'control_form_abortions' ) )
					{	// Only if user wants this:
						require_js_defer( 'bozo_validator.js', 'blog' );
					}
					break;

				case 'disp_edit_comment':
					// Specific features for disp=edit_comment:

					// Require results.css to display attachments as a result table:
					if( ! in_array( 'bootstrap', $features ) )
					{	// Only for NON-bootstrap skins:
						require_css( 'results.css', 'blog' ); // Results/tables styles
					}

					// Used to set rating for a new comment:
					init_ratings_js( 'blog' );

					// Used to display a date picker for date form fields:
					init_datepicker_js( 'blog' );

					// Used to display a tooltip to the right of plugin help icon:
					init_popover_js( 'blog', $this->get_template( 'tooltip_plugin' ) );

					// Used to autocomplete usernames in textarea:
					init_autocomplete_usernames_js( 'blog' );

					// Used to switch to advanced editing:
					require_js_defer( 'backoffice.js', 'blog' );
					break;

				case 'disp_useritems':
					// Specific features for disp=useritems:
				case 'disp_usercomments':
					// Specific features for disp=usercomments:

					// Require results.css to display item/comment query results in a table
					if( ! in_array( 'bootstrap', $features ) )
					{	// Only for NON-bootstrap skins:
						require_css( 'results.css', 'blog' ); // Results/tables styles
					}

					// Require functions.js to show/hide a panel with filters
					require_js_defer( 'functions.js', 'blog' );
					break;

				case 'disp_download':
					// Specific features for disp=download:
					global $Collection, $Blog;

					require_js_defer( '#jquery#', 'blog' );

					// Initialize JavaScript to download file after X seconds
					expose_var_to_js( 'evo_disp_download_delay_config', intval( $Blog->get_setting( 'download_delay' ) ) );
					break;

				default:
					// We no longer want to do this because of 'disp_auto':
					// debug_die( 'This skin has requested an unknown feature: \''.$feature.'\'. Maybe this skin requires a more recent version of b2evolution.' );
			}
		}

		if( ! in_array( 'superbundle', $features ) )
		{	// Load general JS file only when it is not bundled above:
			if( $this->get_api_version() >= 6 )
			{ // Bootstrap skin
				require_js_defer( 'build/bootstrap-evo_frontoffice.bmin.js', 'blog' );
			}
			else
			{ // Standard skin
				require_js_defer( 'build/evo_frontoffice.bmin.js', 'blog' );
			}
		}

		if( is_logged_in() && $Session->get( 'designer_mode_'.$Blog->ID ) )
		{	// If desinger mode when it is turned on from evo menu under "Designer Mode/Exit Designer" or "Collection" -> "Enable/Disable designer mode":
			require_js_defer( '#jquery#', 'blog' );
			if( check_user_perm( 'blog_properties', 'edit', false, $Blog->ID ) )
			{	// Initialize this url var only when current user has a permission to edit widgets:
				global $admin_url;
				add_js_headline( 'var b2evo_widget_edit_url = "'.get_admin_url( 'ctrl=widgets&action=edit&wi_ID=$wi_ID$&mode=customizer', '&' ).'";'
					.'var b2evo_widget_add_url = "'.get_admin_url( 'ctrl=widgets&blog='.$Blog->ID.'&skin_type='.$Blog->get_skin_type().'&action=add_list&container=$container$&container_code=$container_code$&mode=customizer', '&' ).'";'
					.'var b2evo_widget_duplicate_url = "'.get_admin_url( 'ctrl=widgets&action=duplicate&wi_ID=$wi_ID$&mode=customizer&crumb_widget=$crumb_widget$', '&' ).'";'
					.'var b2evo_widget_list_url = "'.get_admin_url( 'ctrl=widgets&blog='.$Blog->ID.'&skin_type='.$Blog->get_skin_type().'&action=customize&container=$container$&container_code=$container_code$&mode=customizer', '&' ).'";'
					.'var b2evo_widget_blog = \''.$Blog->ID.'\';'
					.'var b2evo_widget_crumb = \''.get_crumb( 'widget' ).'\';'
					.'var b2evo_widget_icon_top = \''.format_to_js( get_icon( 'designer_widget_top', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_order_top' ) ) ).'\';'
					.'var b2evo_widget_icon_up = \''.format_to_js( get_icon( 'designer_widget_up', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_order_up' ) ) ).'\';'
					.'var b2evo_widget_icon_down = \''.format_to_js( get_icon( 'designer_widget_down', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_order_down' ) ) ).'\';'
					.'var b2evo_widget_icon_bottom = \''.format_to_js( get_icon( 'designer_widget_bottom', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_order_bottom' ) ) ).'\';'
					.'var b2evo_widget_icon_duplicate = \''.format_to_js( get_icon( 'duplicate', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_duplicate', 'title' => T_('Duplicate') ) ) ).'\';'
					.'var b2evo_widget_icon_disable = \''.format_to_js( get_icon( 'minus', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_disable', 'title' => T_('Disable') ) ) ).'\';'
					.'var b2evo_widget_icon_add = \''.format_to_js( get_icon( 'add', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_add', 'title' => T_('Add Widget to container') ) ) ).'\';'
					.'var b2evo_widget_icon_list = \''.format_to_js( get_icon( 'designer_widget_list', 'imgtag', array( 'class' => 'evo_designer__action evo_designer__action_list', 'title' => T_('Manage Widgets of container') ) ) ).'\';'
					.'var evo_js_lang_close = \''.TS_('Close').'\';'
					.'var evo_js_lang_loading = \''.TS_('Loading...').'\';'
					.'var evo_js_lang_title_available_widgets = \''.sprintf( TS_('Widgets available for insertion into &laquo;%s&raquo;'), '$container_name$' ).'\';'
					.'var evo_js_lang_title_edit_widget = \''.sprintf( TS_('Edit widget "%s" in container "%s"'), '$widget_name$', '$container_name$' ).'\';'
					.'var evo_js_lang_server_error = \''.TS_('There was a server side error.').'\';'
					.'var evo_js_lang_sync_error = \''.TS_('Please reload the page to be in sync with the server.').'\';' );
			}
			require_js_defer( 'src/evo_widget_designer.js', 'blog' );
			require_js_defer( 'communication.js', 'blog' );
		}

		// Skin v7 specific initializations for kind of current collection:
		if( $this->get_api_version() == 7 && method_exists( $this, 'display_init_'.$Blog->get( 'type' ) ) )
		{	// If skin has declared the method for collection kind:
			call_user_func( array( $this, 'display_init_'.$Blog->get( 'type' ) ) );
		}
	}


	/**
	 * Get ready for displaying the site skin.
	 *
	 * This method may register some CSS or JS.
	 * The default implementation can register a few common things that you may request in the $features param.
	 * This is where you'd specify you want to use BOOTSTRAP, etc.
	 *
	 * If this doesn't do what you need you may add functions like the following to your skin's siteskin_init():
	 * require_js_async(), require_js_defer(), require_css(), add_js_headline()
	 */
	function siteskin_init()
	{
	}


	/**
	 * Translate a given string, in the Skin's context.
	 *
	 * This means, that the translation is obtained from the Skin's
	 * "locales" folder.
	 *
	 * It uses the global/regular {@link T_()} function as a fallback.
	 *
	 * @param string The string (english), that should be translated
	 * @param string Requested locale ({@link $current_locale} gets used by default)
	 * @return string The translated string.
	 *
	 * @uses T_()
	 * @since 3.2.0 (after beta)
	 */
	function T_( $string, $req_locale = '' )
	{
		global $skins_path;

		if( ( $return = T_( $string, $req_locale, array(
								'ext_transarray' => & $this->_trans,
								'alt_basedir'    => $skins_path.$this->folder,
							) ) ) == $string )
		{	// This skin did not provide a translation - fallback to global T_():
			return T_( $string, $req_locale );
		}

		return $return;
	}


	/**
	 * Translate and escape single quotes.
	 *
	 * This is to be used mainly for Javascript strings.
	 *
	 * @param string String to translate
	 * @param string Locale to use
	 * @return string The translated and escaped string.
	 *
	 * @uses Skin::T_()
	 * @since 3.2.0 (after beta)
	 */
	function TS_( $string, $req_locale = '' )
	{
		return str_replace( "'", "\\'", $this->T_( $string, $req_locale ) );
	}


	/**
	 * Those templates are used for example by the messaging screens.
	 */
	function get_template( $name )
	{
		switch( $this->get_css_framework() )
		{
			case 'bootstrap':
				switch( $name )
				{
					case 'Results':
					case 'compact_results':
						// Results list (Used to view the lists of the users, messages, contacts and etc.):
						$results_template = array(
							'page_url' => '', // All generated links will refer to the current page
							'before' => '<div class="results panel panel-default">',
							'content_start' => '<div id="$prefix$ajax_content">',
							'header_start' => '<div class="results_header clearfix">',
								'header_text' => '<div class="evo_pager"><div class="results_summary">$nb_results$ Results $reset_filters_button$</div><ul class="pagination">'
										.'$prev$$first$$list_prev$$list$$list_next$$last$$next$'
									.'</ul></div>',
								'header_text_single' => '<div class="results_summary">$nb_results$ Results $reset_filters_button$</div>',
							'header_end' => '</div>',
							'head_title' => '<div class="panel-heading fieldset_title"><span class="pull-right panel_heading_action_icons">$global_icons$</span><h3 class="panel-title">$title$</h3></div>'."\n",
							'global_icons_class' => 'btn btn-default btn-sm',
							'filters_start'        => '<div class="filters panel-body">',
							'filters_end'          => '</div>',
							'filter_button_class'  => 'evo_btn_apply_filters btn-sm btn-info',
							'filter_button_before' => '<div class="form-group pull-right">',
							'filter_button_after'  => '</div>',
							'messages_start' => '<div class="messages form-inline">',
							'messages_end' => '</div>',
							'messages_separator' => '<br />',
							'list_start' => '<div class="table_scroll">'."\n"
														 .'<table class="table table-striped table-bordered table-hover table-condensed" cellspacing="0">'."\n",
								'head_start' => "<thead>\n",
									'line_start_head' => '<tr>',  // TODO: fusionner avec colhead_start_first; mettre a jour admin_UI_general; utiliser colspan="$headspan$"
									'colhead_start' => '<th $class_attrib$>',
									'colhead_start_first' => '<th class="firstcol $class$">',
									'colhead_start_last' => '<th class="lastcol $class$">',
									'colhead_end' => "</th>\n",
									'sort_asc_off' => get_icon( 'sort_asc_off' ),
									'sort_asc_on' => get_icon( 'sort_asc_on' ),
									'sort_desc_off' => get_icon( 'sort_desc_off' ),
									'sort_desc_on' => get_icon( 'sort_desc_on' ),
									'basic_sort_off' => '',
									'basic_sort_asc' => get_icon( 'ascending' ),
									'basic_sort_desc' => get_icon( 'descending' ),
								'head_end' => "</thead>\n\n",
								'tfoot_start' => "<tfoot>\n",
								'tfoot_end' => "</tfoot>\n\n",
								'body_start' => "<tbody>\n",
									'line_start' => '<tr class="even">'."\n",
									'line_start_odd' => '<tr class="odd">'."\n",
									'line_start_last' => '<tr class="even lastline">'."\n",
									'line_start_odd_last' => '<tr class="odd lastline">'."\n",
										'col_start' => '<td $class_attrib$ $colspan_attrib$>',
										'col_start_first' => '<td class="firstcol $class$" $colspan_attrib$>',
										'col_start_last' => '<td class="lastcol $class$" $colspan_attrib$>',
										'col_end' => "</td>\n",
									'line_end' => "</tr>\n\n",
									'grp_line_start' => '<tr class="group">'."\n",
									'grp_line_start_odd' => '<tr class="odd">'."\n",
									'grp_line_start_last' => '<tr class="lastline">'."\n",
									'grp_line_start_odd_last' => '<tr class="odd lastline">'."\n",
												'grp_col_start' => '<td $class_attrib$ $colspan_attrib$>',
												'grp_col_start_first' => '<td class="firstcol $class$" $colspan_attrib$>',
												'grp_col_start_last' => '<td class="lastcol $class$" $colspan_attrib$>',
										'grp_col_end' => "</td>\n",
									'grp_line_end' => "</tr>\n\n",
								'body_end' => "</tbody>\n\n",
								'total_line_start' => '<tr class="total">'."\n",
									'total_col_start' => '<td $class_attrib$>',
									'total_col_start_first' => '<td class="firstcol $class$">',
									'total_col_start_last' => '<td class="lastcol $class$">',
									'total_col_end' => "</td>\n",
								'total_line_end' => "</tr>\n\n",
							'list_end' => "</table></div>\n\n",
							'footer_start' => '<div class="results_footer">',
							'footer_text' => '<div class="center"><ul class="pagination">'
									.'$prev$$first$$list_prev$$list$$list_next$$last$$next$'
								.'</ul></div><div class="center">$page_size$</div>'
																/* T_('Page $scroll_list$ out of $total_pages$   $prev$ | $next$<br />'. */
																/* '<strong>$total_pages$ Pages</strong> : $prev$ $list$ $next$' */
																/* .' <br />$first$  $list_prev$  $list$  $list_next$  $last$ :: $prev$ | $next$') */,
							'footer_text_single' => '<div class="center">$page_size$</div>',
							'footer_text_no_limit' => '', // Text if theres no LIMIT and therefor only one page anyway
								'page_current_template' => '<span>$page_num$</span>',
								'page_item_before' => '<li>',
								'page_item_after' => '</li>',
								'page_item_current_before' => '<li class="active">',
								'page_item_current_after'  => '</li>',
								'prev_text' => T_('Previous'),
								'next_text' => T_('Next'),
								'no_prev_text' => '',
								'no_next_text' => '',
								'list_prev_text' => '...',
								'list_next_text' => '...',
								'list_span' => 11,
								'scroll_list_range' => 5,
							'footer_end' => "\n\n",
							'no_results_start' => '<div class="panel-footer">'."\n",
							'no_results_end'   => '$no_results$ $reset_filters_button$</div>'."\n\n",
							'content_end' => '</div>',
							'after' => '</div>',
							'sort_type' => 'basic'
						);
						if( $name == 'compact_results' )
						{	// Use a little different template for compact results table:
							$results_template = array_merge( $results_template, array(
									'before' => '<div class="results">',
									'head_title' => '',
									'no_results_start' => '<div class="table_scroll">'."\n"
																				.'<table class="table table-striped table-bordered table-hover table-condensed" cellspacing="0"><tbody>'."\n",
									'no_results_end'   => '<tr class="lastline noresults"><td class="firstcol lastcol">$no_results$</td></tr>'
																				.'</tbody></table></div>'."\n\n",
								) );
						}
						return $results_template;

					case 'blockspan_form':
						// Form settings for filter area:
						return array(
							'layout'         => 'blockspan',
							'formclass'      => 'form-inline',
							'formstart'      => '',
							'formend'        => '',
							'title_fmt'      => '$title$'."\n",
							'no_title_fmt'   => '',
							'fieldset_begin' => '<fieldset $fieldset_attribs$>'."\n"
																		.'<legend $title_attribs$>$fieldset_title$</legend>'."\n",
							'fieldset_end'   => '</fieldset>'."\n",
							'fieldstart'     => '<div class="form-group form-group-sm" $ID$>'."\n",
							'fieldend'       => "</div>\n\n",
							'labelclass'     => 'control-label',
							'labelstart'     => '',
							'labelend'       => "\n",
							'labelempty'     => '<label></label>',
							'inputstart'     => '',
							'inputend'       => "\n",
							'infostart'      => '<div class="form-control-static">',
							'infoend'        => "</div>\n",
							'buttonsstart'   => '<div class="form-group form-group-sm">',
							'buttonsend'     => "</div>\n\n",
							'customstart'    => '<div class="custom_content">',
							'customend'      => "</div>\n",
							'note_format'    => ' <span class="help-inline">%s</span>',
							'bottom_note_format' => ' <div><span class="help-inline">%s</span></div>',
							// Additional params depending on field type:
							// - checkbox
							'fieldstart_checkbox'    => '<div class="form-group form-group-sm checkbox" $ID$>'."\n",
							'fieldend_checkbox'      => "</div>\n\n",
							'inputclass_checkbox'    => '',
							'inputstart_checkbox'    => '',
							'inputend_checkbox'      => "\n",
							'checkbox_newline_start' => '',
							'checkbox_newline_end'   => "\n",
							// - radio
							'inputclass_radio'       => '',
							'radio_label_format'     => '$radio_option_label$',
							'radio_newline_start'    => '',
							'radio_newline_end'      => "\n",
							'radio_oneline_start'    => '',
							'radio_oneline_end'      => "\n",
						);

					case 'compact_form':
					case 'Form':
						// Default Form settings (Used for any form on front-office):
						return array(
							'layout'         => 'fieldset',
							'formclass'      => 'form-horizontal',
							'formstart'      => '',
							'formend'        => '',
							'title_fmt'      => '<span style="float:right">$global_icons$</span><h2>$title$</h2>'."\n",
							'no_title_fmt'   => '<span style="float:right">$global_icons$</span>'."\n",
							'fieldset_begin' => '<div class="fieldset_wrapper $class$" id="fieldset_wrapper_$id$"><fieldset $fieldset_attribs$><div class="panel panel-default">'."\n"
																	.'<legend class="panel-heading" $title_attribs$>$fieldset_title$</legend><div class="panel-body $class$">'."\n",
							'fieldset_end'   => '</div></div></fieldset></div>'."\n",
							'fieldstart'     => '<div class="form-group" $ID$>'."\n",
							'fieldend'       => "</div>\n\n",
							'labelclass'     => 'control-label col-sm-3',
							'labelstart'     => '',
							'labelend'       => '',
							'labelempty'     => '<label class="control-label col-sm-3"></label>',
							'inputstart'     => '<div class="controls col-sm-9">',
							'inputend'       => "</div>\n",
							'infostart'      => '<div class="controls col-sm-9"><div class="form-control-static">',
							'infoend'        => "</div></div>\n",
							'buttonsstart'   => '<div class="form-group"><div class="control-buttons col-sm-offset-3 col-sm-9">',
							'buttonsend'     => "</div></div>\n\n",
							'customstart'    => '<div class="custom_content">',
							'customend'      => "</div>\n",
							'note_format'    => ' <span class="help-inline">%s</span>',
							'bottom_note_format' => ' <div><span class="help-inline">%s</span></div>',
							// Additional params depending on field type:
							// - checkbox
							'inputclass_checkbox'    => '',
							'inputstart_checkbox'    => '<div class="controls col-sm-9"><div class="checkbox"><label>',
							'inputend_checkbox'      => "</label></div></div>\n",
							'checkbox_newline_start' => '<div class="checkbox">',
							'checkbox_newline_end'   => "</div>\n",
							// - radio
							'fieldstart_radio'       => '<div class="form-group radio-group" $ID$>'."\n",
							'fieldend_radio'         => "</div>\n\n",
							'inputclass_radio'       => '',
							'radio_label_format'     => '$radio_option_label$',
							'radio_newline_start'    => '<div class="radio"><label>',
							'radio_newline_end'      => "</label></div>\n",
							'radio_oneline_start'    => '<label class="radio-inline">',
							'radio_oneline_end'      => "</label>\n",
						);

					case 'linespan_form':
						// Linespan form:
						return array(
							'layout'         => 'linespan',
							'formclass'      => 'form-horizontal',
							'formstart'      => '',
							'formend'        => '',
							'title_fmt'      => '<span style="float:right">$global_icons$</span><h2>$title$</h2>'."\n",
							'no_title_fmt'   => '<span style="float:right">$global_icons$</span>'."\n",
							'fieldset_begin' => '<div class="fieldset_wrapper $class$" id="fieldset_wrapper_$id$"><fieldset $fieldset_attribs$><div class="panel panel-default">'."\n"
																	.'<legend class="panel-heading" $title_attribs$>$fieldset_title$</legend><div class="panel-body $class$">'."\n",
							'fieldset_end'   => '</div></div></fieldset></div>'."\n",
							'fieldstart'     => '<div class="form-group" $ID$>'."\n",
							'fieldend'       => "</div>\n\n",
							'labelclass'     => '',
							'labelstart'     => '',
							'labelend'       => "\n",
							'labelempty'     => '',
							'inputstart'     => '<div class="controls">',
							'inputend'       => "</div>\n",
							'infostart'      => '<div class="controls"><div class="form-control-static">',
							'infoend'        => "</div></div>\n",
							'buttonsstart'   => '<div class="form-group"><div class="control-buttons">',
							'buttonsend'     => "</div></div>\n\n",
							'customstart'    => '<div class="custom_content">',
							'customend'      => "</div>\n",
							'note_format'    => ' <span class="help-inline">%s</span>',
							'bottom_note_format' => ' <div><span class="help-inline">%s</span></div>',
							// Additional params depending on field type:
							// - checkbox
							'inputclass_checkbox'    => '',
							'inputstart_checkbox'    => '<div class="controls"><div class="checkbox"><label>',
							'inputend_checkbox'      => "</label></div></div>\n",
							'checkbox_newline_start' => '<div class="checkbox">',
							'checkbox_newline_end'   => "</div>\n",
							'checkbox_basic_start'   => '<div class="checkbox"><label>',
							'checkbox_basic_end'     => "</label></div>\n",
							// - radio
							'fieldstart_radio'       => '',
							'fieldend_radio'         => '',
							'inputstart_radio'       => '<div class="controls">',
							'inputend_radio'         => "</div>\n",
							'inputclass_radio'       => '',
							'radio_label_format'     => '$radio_option_label$',
							'radio_newline_start'    => '<div class="radio"><label>',
							'radio_newline_end'      => "</label></div>\n",
							'radio_oneline_start'    => '<label class="radio-inline">',
							'radio_oneline_end'      => "</label>\n",
						);

					case 'fixed_form':
						// Form with fixed label width (Used for form on disp=user):
						return array(
							'layout'         => 'fieldset',
							'formclass'      => 'form-horizontal',
							'formstart'      => '',
							'formend'        => '',
							'title_fmt'      => '<span style="float:right">$global_icons$</span><h2>$title$</h2>'."\n",
							'no_title_fmt'   => '<span style="float:right">$global_icons$</span>'."\n",
							'fieldset_begin' => '<div class="fieldset_wrapper $class$" id="fieldset_wrapper_$id$"><fieldset $fieldset_attribs$><div class="panel panel-default">'."\n"
																	.'<legend class="panel-heading" $title_attribs$>$fieldset_title$</legend><div class="panel-body $class$">'."\n",
							'fieldset_end'   => '</div></div></fieldset></div>'."\n",
							'fieldstart'     => '<div class="form-group fixedform-group" $ID$>'."\n",
							'fieldend'       => "</div>\n\n",
							'labelclass'     => 'control-label fixedform-label',
							'labelstart'     => '',
							'labelend'       => "\n",
							'labelempty'     => '<label class="control-label fixedform-label"></label>',
							'inputstart'     => '<div class="controls fixedform-controls">',
							'inputend'       => "</div>\n",
							'infostart'      => '<div class="controls fixedform-controls"><div class="form-control-static">',
							'infoend'        => "</div></div>\n",
							'buttonsstart'   => '<div class="form-group"><div class="control-buttons fixedform-controls">',
							'buttonsend'     => "</div></div>\n\n",
							'customstart'    => '<div class="custom_content">',
							'customend'      => "</div>\n",
							'note_format'    => ' <span class="help-inline">%s</span>',
							'bottom_note_format' => ' <div><span class="help-inline">%s</span></div>',
							// Additional params depending on field type:
							// - checkbox
							'inputclass_checkbox'    => '',
							'inputstart_checkbox'    => '<div class="controls fixedform-controls"><div class="checkbox"><label>',
							'inputend_checkbox'      => "</label></div></div>\n",
							'checkbox_newline_start' => '<div class="checkbox">',
							'checkbox_newline_end'   => "</div>\n",
							// - radio
							'fieldstart_radio'       => '<div class="form-group radio-group" $ID$>'."\n",
							'fieldend_radio'         => "</div>\n\n",
							'inputclass_radio'       => '',
							'radio_label_format'     => '$radio_option_label$',
							'radio_newline_start'    => '<div class="radio"><label>',
							'radio_newline_end'      => "</label></div>\n",
							'radio_oneline_start'    => '<label class="radio-inline">',
							'radio_oneline_end'      => "</label>\n",
						);

					case 'fields_table_form':
						return array_merge( $this->get_template( 'Form' ), array(
								'fieldset_begin' => '<div class="evo_fields_table $class$" id="fieldset_wrapper_$id$" $fieldset_attribs$>'."\n",
								'fieldset_end'   => '</div>'."\n",
								'fieldstart'     => '<div class="evo_fields_table__field" $ID$>'."\n",
								'fieldend'       => "</div>\n\n",
								'labelclass'     => 'evo_fields_table__label',
								'labelstart'     => '',
								'labelend'       => "\n",
								'labelempty'     => '',
								'inputstart'     => '<div class="evo_fields_table__input">',
								'inputend'       => "</div>\n",
							) );
						break;

					case 'user_navigation':
						// The Prev/Next links of users (Used on disp=user to navigate between users):
						return array(
							'block_start'  => '<ul class="pager">',
							'prev_start'   => '<li class="previous">',
							'prev_end'     => '</li>',
							'prev_no_user' => '',
							'back_start'   => '<li>',
							'back_end'     => '</li>',
							'next_start'   => '<li class="next">',
							'next_end'     => '</li>',
							'next_no_user' => '',
							'block_end'    => '</ul>',
						);

					case 'button_classes':
						// Button classes (Used to initialize classes for action buttons like buttons to spam vote, or edit an intro post):
						return array(
							'button'       => 'btn btn-default btn-xs',
							'button_red'   => 'btn-danger',
							'button_green' => 'btn-success',
							'text'         => 'btn btn-default btn-xs',
							'group'        => 'btn-group',
						);

					case 'tooltip_plugin':
						// Plugin name for tooltips: 'bubbletip' or 'popover'
						// We should use 'popover' tooltip plugin for bootstrap skins
						// This tooltips appear on mouse over user logins or on plugin help icons
						return 'popover';

					case 'autocomplete_plugin':
						// Plugin name to autocomplete user logins: 'hintbox', 'typeahead'
						return 'typeahead';

					case 'plugin_template':
						// Template for plugins:
						return array(
								// This template is used to build a plugin toolbar with action buttons above edit item/comment area:
								'toolbar_before'       => '<div class="btn-toolbar plugin-toolbar $toolbar_class$" data-plugin-toolbar="$toolbar_class$" role="toolbar">',
								'toolbar_after'        => '</div>',
								'toolbar_title_before' => '<div class="btn-toolbar-title">',
								'toolbar_title_after'  => '</div>',
								'toolbar_group_before' => '<div class="btn-group btn-group-xs" role="group">',
								'toolbar_group_after'  => '</div>',
								'toolbar_button_class' => 'btn btn-default',
							);

					case 'modal_window_js_func':
						// JavaScript function to initialize Modal windows, @see echo_user_ajaxwindow_js()
						return 'echo_modalwindow_js_bootstrap';

					case 'colorbox_css_file':
						// CSS file of colorbox, @see require_js_helper( 'colorbox' )
						return 'colorbox-bootstrap.min.css';
				}
				break;
		}

		// Use default template:
		switch( $name )
		{
			case 'Results':
			case 'compact_results':
				// Results list:
				return array(
					'page_url' => '', // All generated links will refer to the current page
					'before' => '<div class="results">',
					'content_start' => '<div id="$prefix$ajax_content">',
					'header_start' => '<div class="results_nav">',
						'header_text' => '<strong>'.T_('Pages').'</strong>: $prev$ $first$ $list_prev$ $list$ $list_next$ $last$ $next$',
						'header_text_single' => '',
					'header_end' => '</div>',
					'head_title' => '<div class="title"><span style="float:right">$global_icons$</span>$title$</div>'
							            ."\n",
					'filters_start' => '<div class="filters">',
					'filters_end' => '</div>',
					'messages_start' => '<div class="messages">',
					'messages_end' => '</div>',
					'messages_separator' => '<br />',
					'list_start' => '<div class="table_scroll">'."\n"
					               .'<table class="grouped" cellspacing="0">'."\n",
						'head_start' => '<thead>'."\n",
							'line_start_head' => '<tr>',  // TODO: fusionner avec colhead_start_first; mettre a jour admin_UI_general; utiliser colspan="$headspan$"
							'colhead_start' => '<th $class_attrib$ $title_attrib$>',
							'colhead_start_first' => '<th class="firstcol $class$" $title_attrib$>',
							'colhead_start_last' => '<th class="lastcol $class$" $title_attrib$>',
							'colhead_end' => "</th>\n",
							'sort_asc_off' => get_icon( 'sort_asc_off' ),
							'sort_asc_on' => get_icon( 'sort_asc_on' ),
							'sort_desc_off' => get_icon( 'sort_desc_off' ),
							'sort_desc_on' => get_icon( 'sort_desc_on' ),
							'basic_sort_off' => '',
							'basic_sort_asc' => get_icon( 'ascending' ),
							'basic_sort_desc' => get_icon( 'descending' ),
						'head_end' => "</thead>\n\n",
						'tfoot_start' => "<tfoot>\n",
						'tfoot_end' => "</tfoot>\n\n",
						'body_start' => "<tbody>\n",
							'line_start' => '<tr class="even">'."\n",
							'line_start_odd' => '<tr class="odd">'."\n",
							'line_start_last' => '<tr class="even lastline">'."\n",
							'line_start_odd_last' => '<tr class="odd lastline">'."\n",
								'col_start' => '<td $class_attrib$ $colspan_attrib$>',
								'col_start_first' => '<td class="firstcol $class$" $colspan_attrib$>',
								'col_start_last' => '<td class="lastcol $class$" $colspan_attrib$>',
								'col_end' => "</td>\n",
							'line_end' => "</tr>\n\n",
							'grp_line_start' => '<tr class="group">'."\n",
							'grp_line_start_odd' => '<tr class="odd">'."\n",
							'grp_line_start_last' => '<tr class="lastline">'."\n",
							'grp_line_start_odd_last' => '<tr class="odd lastline">'."\n",
										'grp_col_start' => '<td $class_attrib$ $colspan_attrib$>',
										'grp_col_start_first' => '<td class="firstcol $class$" $colspan_attrib$>',
										'grp_col_start_last' => '<td class="lastcol $class$" $colspan_attrib$>',
								'grp_col_end' => "</td>\n",
							'grp_line_end' => "</tr>\n\n",
						'body_end' => "</tbody>\n\n",
						'total_line_start' => '<tr class="total">'."\n",
							'total_col_start' => '<td $class_attrib$>',
							'total_col_start_first' => '<td class="firstcol $class$">',
							'total_col_start_last' => '<td class="lastcol $class$">',
							'total_col_end' => "</td>\n",
						'total_line_end' => "</tr>\n\n",
					'list_end' => "</table></div>\n\n",
					'footer_start' => '<div class="results_nav nav_footer">',
					'footer_text' => '<strong>'.T_('Pages').'</strong>: $prev$ $first$ $list_prev$ $list$ $list_next$ $last$ $next$'
					                  /* T_('Page $scroll_list$ out of $total_pages$   $prev$ | $next$<br />'. */
					                  /* '<strong>$total_pages$ Pages</strong> : $prev$ $list$ $next$' */
					                  /* .' <br />$first$  $list_prev$  $list$  $list_next$  $last$ :: $prev$ | $next$') */,
					'footer_text_single' => '',
					'footer_text_no_limit' => '', // Text if theres no LIMIT and therefor only one page anyway
						'prev_text' => T_('Previous'),
						'next_text' => T_('Next'),
						'no_prev_text' => '',
						'no_next_text' => '',
						'list_prev_text' => '...',
						'list_next_text' => '...',
						'list_span' => 11,
						'scroll_list_range' => 5,
					'footer_end' => "</div>\n\n",
					'no_results_start' => '<table class="grouped" cellspacing="0">'."\n",
					'no_results_end'   => '<tr class="lastline"><td class="firstcol lastcol">$no_results$</td></tr>'
					                      .'</table>'."\n\n",
				'content_end' => '</div>',
				'after' => '</div>',
				'sort_type' => 'basic'
				);

			case 'messages':
				return array(
					'show_only_date' => true,
					'show_columns' => 'login',
				);

			case 'blockspan_form':
				// blockspan Form settings:
				return array(
					'layout' => 'blockspan',		// Temporary dirty hack
					'formstart' => '',
					'title_fmt' => '$title$'."\n", // TODO: icons
					'no_title_fmt' => '',          //           "
					'no_title_no_icons_fmt' => '',          //           "
					'fieldset_begin' => '<fieldset $fieldset_attribs$>'."\n"
															.'<legend $title_attribs$>$fieldset_title$</legend>'."\n",
					'fieldset_end' => '</fieldset>'."\n",
					'fieldstart' => '<span class="block" $ID$>',
					'labelclass' => '',
					'labelstart' => '',
					'labelend' => "\n",
					'labelempty' => '',
					'inputstart' => '',
					'inputend' => "\n",
					'infostart' => '',
					'infoend' => "\n",
					'fieldend' => '</span>'.get_icon( 'pixel' )."\n",
					'buttonsstart' => '',
					'buttonsend' => "\n",
					'customstart' => '',
					'customend' => "\n",
					'note_format' => ' <span class="notes">%s</span>',
					'bottom_note_format' => ' <div><span class="notes">%s</span></div>',
					'formend' => '',
				);

			case 'cat_array_mode':
				// What category level use to display the items on disp=posts:
				//   - 'children' - Get items from current category and from all its sub-categories recirsively
				//   - 'parent' - Get items ONLY from current category WITHOUT sub-categories
				return 'children';

			case 'colorbox_css_file':
				// CSS file of colorbox, @see require_js_helper( 'colorbox' )
				return 'colorbox-regular.min.css';

			case 'autocomplete_plugin':
				// Plugin name to autocomplete user logins: 'hintbox', 'typeahead'
				return 'hintbox';
		}

		return array();
	}


	/**
	 * Memorize that a specific css that file will be required by the current page.
	 * @see require_css() for full documentation,
	 * this function is used to add unique version number for each skin
	 *
	 * @param string Name of CSS file relative to <base> tag (current skin folder)
	 * @param string Position where the CSS file will be inserted, either 'headlines' (inside <head>) or 'footerlines' (before </body>)
	 */
	function require_css( $css_file, $position = 'headlines' )
	{
		global $app_version_long;
		require_css( $this->get_url().$css_file, 'absolute', NULL, NULL, $this->folder.'+'.$this->version.'+'.$app_version_long, false, $position );
	}


	/**
	 * Memorize that a specific javascript file will be required by the current page.
	 * @see require_js() for full documentation,
	 * this function is used to add unique version number for each skin
	 *
	 * @param string Name of JavaScript file relative to <base> tag (current skin folder)
	 * @param boolean 'async' or TRUE to add attribute "async" to load javascript asynchronously,
	 *                'defer' to add attribute "defer" asynchronously in the order they occur in the page,
	 *                'immediate' or FALSE to load javascript immediately
	 * @param boolean TRUE to print script tag on the page, FALSE to store in array to print then inside <head>
	 * @param string Position where the JS file will be inserted, either 'headlines' (inside <head>) or 'footerlines' (before </body>)
	 */
	function require_js( $js_file, $async_defer = false, $output = false, $position = 'headlines' )
	{
		global $app_version_long;
		require_js( $this->get_url().$js_file, 'absolute', $async_defer, $output, $this->folder.'+'.$this->version.'+'.$app_version_long, $position );
	}


	/**
	 * Require javascript file to load asynchronously with attribute "async"
	 *
	 * @param string Name of JavaScript file relative to <base> tag (current skin folder)
	 * @param boolean TRUE to print script tag on the page, FALSE to store in array to print then inside <head>
	 * @param string Position where the JS file will be inserted, either 'headlines' (inside <head>) or 'footerlines' (before </body>)
	 */
	function require_js_async( $js_file, $output = false, $position = 'headlines' )
	{
		$this->require_js( $js_file, 'async', $output, $position );
	}


	/**
	 * Require javascript file to load asynchronously with attribute "defer" in the order they occur in the page
	 *
	 * @param string Name of JavaScript file relative to <base> tag (current skin folder)
	 * @param boolean TRUE to print script tag on the page, FALSE to store in array to print then inside <head>
	 * @param string Position where the JS file will be inserted, either 'headlines' (inside <head>) or 'footerlines' (before </body>)
	 */
	function require_js_defer( $js_file, $output = false, $position = 'headlines' )
	{
		$this->require_js( $js_file, 'defer', $output, $position );
	}


	/**
	 * Web safe fonts for default skin usage
	 *
	 * Used for font customization
	 */
	private $font_definitions = array(
			'system_arial' => array( 'Arial', 'Arial, Helvetica, sans-serif' ),
			'system_arialblack' => array( 'Arial Black', '\'Arial Black\', Gadget, sans-serif' ),
			'system_arialnarrow' => array( 'Arial Narrow', '\'Arial Narrow\', sans-serif' ),
			'system_centrygothic' => array( 'Century Gothic', 'Century Gothic, sans-serif' ),
			'system_copperplategothiclight' => array( 'Copperplate Gothic Light', 'Copperplate Gothic Light, sans-serif' ),
			'system_couriernew' => array( 'Courier New', '\'Courier New\', Courier, monospace' ),
			'system_georgia' => array( 'Georgia', 'Georgia, Serif' ),
			'system_helveticaneue' => array( 'Helvetica Neue', '\'Helvetica Neue\',Helvetica,Arial,sans-serif' ),
			'system_impact' => array( 'Impact', 'Impact, Charcoal, sans-serif' ),
			'system_lucidaconsole' => array( 'Lucida Console', '\'Lucida Console\', Monaco, monospace' ),
			'system_lucidasansunicode' => array( 'Lucida Sans Unicode', '\'Lucida Sans Unicode\', \'Lucida Grande\', sans-serif' ),
			'system_palatinolinotype' => array( 'Palatino Linotype', '\'Palatino Linotype\', \'Book Antiqua\', Palatino, serif' ),
			'system_tahoma' => array( 'Tahoma', 'Tahoma, Geneva, sans-serif' ),
			'system_timesnewroman' => array( 'Times New Roman', '\'Times New Roman\', Times, serif' ),
			'system_trebuchetms' => array( 'Trebuchet MS', '\'Trebuchet MS\', Helvetica, sans-serif' ),
			'system_verdana' => array( 'Verdana', 'Verdana, Geneva, sans-serif' ),
		);


	/**
	 * Returns an option list for font customization
	 *
	 * Uses: $this->font_definitions
	 * @param string Type: 'select' - for skin setting <select>, 'style' - for using in styles
	 */
	function get_font_definitions( $type = 'select' )
	{
		$fonts = array();
		foreach( $this->font_definitions as $font_key => $font_data )
		{
			$font_data_index = $type == 'style' ? 1 : 0;
			$fonts[ $font_key ] = isset( $font_data[ $font_data_index ] ) ? $font_data[ $font_data_index ] : $font_data[0];
		}

		return $fonts;
	}


	/**
	 * Returns a CSS code for font customization
	 *
	 * Uses: $this->font_definitions
	 */
	function apply_selected_font( $target_element, $font_family_param, $text_size_param = NULL, $font_weight_param = NULL, $group = NULL )
	{
		$font_css = array();

		// Get default font family and font-weight
		$default_font_family = $this->get_setting_default_value( $font_family_param, $group );
		$default_font_weight = $this->get_setting_default_value( $font_weight_param, $group );

		// Select the font family CSS string
		$selected_font_family = $this->get_setting( $font_family_param, $group );
		if( $selected_font_family != $default_font_family )
		{
			$selected_font_definition = isset( $this->font_definitions[$selected_font_family] ) ? $this->font_definitions[$selected_font_family] : $this->font_definitions[$default_font_family];
			$font_css[] = "font-family: $selected_font_definition[1];";
		}

		// If $text_size_param is passed, add font-size property
		if( ! is_null( $text_size_param ) )
		{
			$selected_text_size = $this->get_setting( $text_size_param, $group );
			$font_css[] = 'font-size: '.$selected_text_size.';';
		}

		// If $font_weight_param is passed, add font-weight property
		if( ! is_null( $font_weight_param ) )
		{
			$selected_font_weight = $this->get_setting( $font_weight_param, $group );
			if( $selected_font_weight != $default_font_weight )
			{
				$font_css[] = 'font-weight: '.$selected_font_weight.';';
			}
		}

		// Prepare the complete CSS for font customization
		if( ! empty( $font_css ) )
		{
			$custom_css = $target_element.' { '.implode( ' ', $font_css )." }\n";
		}
		else
		{
			$custom_css = '';
		}

		return $custom_css;
	}


	/**
	 * Check if we can display a widget container when access is denied to collection by current user
	 *
	 * NOTE: To use this function your skin must has a checklist setting 'access_login_containers' with options of widget container keys
	 *
	 * @param string Widget container key: 'header', 'page_top', 'menu', 'sidebar', 'sidebar2', 'footer'
	 * @return boolean TRUE to display
	 */
	function show_container_when_access_denied( $container_key )
	{
		global $Collection, $Blog;

		if( $Blog->has_access() )
		{	// If current user has an access to this collection then don't restrict containers:
			return true;
		}

		// Get what containers are available for this skin when access is denied or requires login:
		$access = $this->get_setting( 'access_login_containers' );

		return ( ! empty( $access ) && ! empty( $access[ $container_key ] ) );
	}


	/**
	 * Additional JavaScript code for skin settings form
	 */
	function echo_settings_form_js()
	{
	}


	/**
	 * Call skin function with suffix that is current collection kind
	 *
	 * @param string Function name
	 * @param array Function parameters
	 * @return 
	 */
	function call_func_by_coll_type( $func_name, $params )
	{
		global $Blog;

		if( ! empty( $Blog ) &&
		    $this->get_api_version() == 7 && 
		    method_exists( $this, $func_name.'_'.$Blog->get( 'type' ) ) )
		{	// If skin has declared the method for collection kind:
			return call_user_func_array( array( $this, $func_name.'_'.$Blog->get( 'type' ) ), $params );
		}

		return NULL;
	}
}

?>
