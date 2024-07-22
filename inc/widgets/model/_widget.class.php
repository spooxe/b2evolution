<?php
/**
 * This file implements the Widget class.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link https://github.com/b2evolution/b2evolution}.
 *
 * @license GNU GPL v2 - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @copyright (c)2003-2020 by Francois Planque - {@link http://fplanque.com/}
 *
 * @package evocore
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

load_class( '_core/model/dataobjects/_dataobject.class.php', 'DataObject' );

// Load functions for widget layout:
load_funcs( 'widgets/_widgets.funcs.php' );

/**
 * ComponentWidget Class
 *
 * A ComponentWidget is a displayable entity that can be placed into a Container on a web page.
 *
 * @package evocore
 */

class ComponentWidget extends DataObject
{
	/**
	 * Widget container ID
	 */
	var $wico_ID;
    
    /**dynamic property*/   
    var $renderers_validated;

	var $order;
	/**
	 * @var string Type of the plugin ("core" or "plugin")
	 */
	var $type;
	var $code;
	var $params;

	/**
	 * Indicates whether the widget is enabled.
	 *
	 * @var boolean
	 */
	var $enabled;

	/**
	 * Array of params which have been customized for this widget instance
	 *
	 * This is saved to the DB as a serialized string ($params)
	 */
	var $param_array = NULL;

	/**
	 * Array of params used during display()
	 */
	var $disp_params = NULL;

	/**
	 * Lazy instantiated.
	 *
	 * This gets set/used for widget plugins (those that hook into SkinTag).
	 * (false if this Widget is not handled by a Plugin)
	 * @see get_Plugin()
	 * @var Plugin
	 */
	var $Plugin;

	/**
	* @var BlockCache
	*/
	var $BlockCache;

	/**
	 * The widget container where this widget belongs to
	 *
	 * Lazy instantiated.
	 *
	 * @var WidgetContainer
	 */
	var $WidgetContainer;

	/**
	* @var Blog
	*/
	var $Blog = NULL;

	/**
	* @var target User that should be used depending on context
	*/
	var $target_User = NULL;

	/**
	 * Widget icon name.
	 * Use icon name from http://fontawesome.io/icons/
	 *
	 * @var string
	 */
	var $icon = 'cube';

	/**
	 * @var Mode: 'designer' or 'normal'
	 */
	var $mode = 'normal';
    /**dynamic property*/
    var $x_params;
    var $herold;


	/**
	 * Constructor
	 *
	 * @param object data row from db
	 */
	function __construct( $db_row = NULL, $type = 'core', $code = NULL )
	{
		// Call parent constructor:
		parent::__construct( 'T_widget__widget', 'wi_', 'wi_ID' );

		if( is_null($db_row) )
		{	// We are creating an object here:
			// Using parent:: instead of $this-> in order to fix http://forums.b2evolution.net//viewtopic.php?p=94778
			parent::set( 'type', $type );
			parent::set( 'code', $code );
		}
		else
		{	// We are loading an object:
			$this->ID       = $db_row->wi_ID;
			$this->wico_ID  = $db_row->wi_wico_ID;
			$this->type     = $db_row->wi_type;
			$this->code     = $db_row->wi_code;
			$this->params   = $db_row->wi_params;
			$this->order    = $db_row->wi_order;
			$this->enabled  = $db_row->wi_enabled;
		}
	}


	/**
	 * Get a member param by its name
	 *
	 * @param mixed Name of parameter
	 * @return mixed Value of parameter
	 */
	function get( $parname )
	{
		if( $parname == 'coll_ID' )
		{
			return $this->get_coll_ID();
		}

		return parent::get( $parname );
	}


	/**
	 * Get param prefix with is used on edit forms and submit data
	 *
	 * @return string
	 */
	function get_param_prefix()
	{
		return 'edit_widget_'.( empty( $this->ID ) ? '0' : $this->ID ).'_set_';
	}


	/**
	 * Get ref to the Plugin handling this Widget.
	 *
	 * @return Plugin
	 */
	function & get_Plugin()
	{
		global $Plugins;

		if( is_null( $this->Plugin ) )
		{
			if( $this->type != 'plugin' )
			{
				$this->Plugin = false;
			}
			else
			{
				$this->Plugin = & $Plugins->get_by_code( $this->code );
			}
		}

		return $this->Plugin;
	}


	/**
	 * Get WidgetContainer
	 */
	function & get_WidgetContainer()
	{
		if( ! isset( $this->WidgetContainer ) )
		{
			$WidgetContainerCache = & get_WidgetContainerCache();
			$this->WidgetContainer = & $WidgetContainerCache->get_by_ID( $this->wico_ID, false, false );
		}
		return $this->WidgetContainer;
	}


	/**
	 * Get the collection ID where this widget belongs to
	 *
	 * @return integer Collection ID
	 */
	function get_coll_ID()
	{
		return $this->get_container_param( 'coll_ID' );
	}


	/**
	 * Get param value of container
	 *
	 * @param string Param name
	 * @return string Param value
	 */
	function get_container_param( $param )
	{
		$WidgetContainer = & $this->get_WidgetContainer();

		if( empty( $WidgetContainer ) )
		{
			return NULL;
		}

		return $WidgetContainer->get( $param );
	}


	/**
	 * Load params
	 */
	function load_from_Request()
	{
		load_funcs('plugins/_plugin.funcs.php');

		// Loop through all widget params:
		foreach( $this->get_param_definitions( array( 'for_editing' => true, 'for_updating' => true  ) ) as $parname => $parmeta )
		{
			$parvalue = NULL;
			if( $parname == 'allow_blockcache'
					&& isset( $parmeta['disabled'] )
					&& ( $parmeta['disabled'] == 'disabled' ) )
			{ // Force checkbox "Allow caching" to unchecked when it is disallowed from widget config
				$parvalue = 0;
			}
			autoform_set_param_from_request( $parname, $parmeta, $this, 'Widget', NULL, $parvalue );
		}
	}


	/**
	 * Get name of widget
	 *
	 * Should be overriden by core widgets
	 */
	function get_name()
	{
		if( $this->type == 'plugin' )
		{
			// Make sure Plugin is loaded:
			if( $this->get_Plugin() )
			{
				return $this->Plugin->name;
			}
			return T_('Inactive / Uninstalled plugin').': "'.$this->code.'"';
		}
		elseif( $this->type == 'wrong' )
		{
			return T_('Wrong widget / Invalid code').': "'.$this->code.'"';
		}

		return T_('Unknown');
	}


	/**
	 * Get a very short desc. Used in the widget list.
	 *
	 * MAY be overriden by core widgets. Example: menu link widget.
	 */
	function get_short_desc()
	{
		return $this->get_name();
	}


	/**
	 * Get widget icon
	 *
	 * @return string
	 */
	function get_icon()
	{
		if( $this->type == 'plugin' )
		{	// Use widget icon from plugin:
			if( $this->get_Plugin() )
			{	// Get widget icon from plugin:
				return $this->Plugin->get_widget_icon();
			}
			else
			{	// Set icon for inactive / uninstalled plugin:
				$this->icon = 'warning';
			}
		}

		if( empty( $this->icon ) )
		{
			return '';
		}

		return '<span class="label label-info evo_widget_icon"><span class="fa fa-'.$this->icon.'"></span></span>';
	}


	/**
	 * Get a clean description to display in the widget list.
	 * @return string
	 */
	function get_desc_for_list()
	{
		$name = $this->get_name();

		if( $this->type == 'plugin' )
		{	// Plugin widget:
			$widget_Plugin = & $this->get_Plugin();

			if( $widget_Plugin )
			{
				if( isset( $this->disp_params['title'] ) && ! empty( $this->disp_params['title'] ) )
				{
					return $widget_Plugin->get_widget_icon().' <strong>'.$this->disp_params['title'].'</strong> ('.$name. ' - ' .T_('Plugin').')';
				}

				return $widget_Plugin->get_widget_icon().' <strong>'.$name.'</strong> ('.T_('Plugin').')';
			}
			else
			{
				$icon = '<span class="label label-info evo_widget_icon"><span class="fa fa-warning"></span></span>';
				return $icon.' <strong>'.$name.'</strong> ('.T_('Plugin').')';
			}
		}

		// Normal widget:
		$short_desc = $this->get_short_desc();
		$icon = $this->get_icon();

		if( $name == $short_desc || empty( $short_desc ) )
		{
			return $icon.' <strong>'.$name.'</strong>';
		}

		return $icon.' <strong>'.$short_desc.'</strong> ('.$name.')';
	}


	/**
	 * Get desc of widget
	 *
	 * Should be overriden by core widgets
	 */
	function get_desc()
	{
		if( $this->type == 'plugin' )
		{
			// Make sure Plugin is loaded:
			if( $this->get_Plugin() )
			{
				return $this->Plugin->short_desc;
			}
			return T_('Inactive / Uninstalled plugin').': "'.$this->code.'"';
		}

		return T_('Unknown');
	}


	/**
	 * Get help URL
	 *
	 * @return string|NULL URL, NULL - when core widget doesn't define the url yet
	 */
	function get_help_url()
	{
		if( $widget_Plugin = & $this->get_Plugin() )
		{ // Get url of the plugin widget
			$help_url = $widget_Plugin->get_help_url( '$widget_url' );
		}
		else
		{ // Core widget must defines this URL
			$help_url = NULL;
		}

		return $help_url;
	}


	/**
	 * Get help link
	 *
	 * @param string Icon
	 * @param boolean TRUE - to add info to display it in tooltip on mouseover
	 * @return string icon
	 */
	function get_help_link( $icon = 'help', $use_tooltip = true )
	{
		$widget_url = $this->get_help_url();

		if( empty( $widget_url ) )
		{ // Return empty string when widget URL is not defined
			return '';
		}

		$link_attrs = array( 'target' => '_blank' );

		if( $use_tooltip )
		{ // Add these data only for tooltip
			$link_attrs['class'] = 'action_icon help_plugin_icon';
			$link_attrs['data-popover'] = format_to_output( $this->get_desc(), 'htmlattr' );
		}

		return action_icon( '', $icon, $widget_url, NULL, NULL, NULL, $link_attrs );
	}


	/**
	 * Get definitions for editable params.
	 *
	 * @see Plugin::GetDefaultSettings()
	 *
	 * @param array Local params like 'for_editing' => true
	 */
	function get_param_definitions( $params )
	{
		$r = array();

		if( $this->type == 'plugin' )
		{
			// Make sure Plugin is loaded:
			if( $this->get_Plugin() )
			{
				$r = $this->Plugin->get_widget_param_definitions( $params );
			}
		}

		if( ! isset( $r['widget_css_class'] ) ||
		    ! isset( $r['widget_ID'] ) ||
		    ! isset( $r['allow_blockcache'] ) )
		{	// Start fieldset of advanced settings:
			$r['advanced_layout_start'] = array(
					'layout' => 'begin_fieldset',
					'label'  => T_('Advanced'),
				);
			$advanced_layout_is_started = true;
		}

		// Add advanced definitions if they are provided in a widget:
		$r = array_merge( $r, $this->get_advanced_param_definitions() );

		if( ! empty( $this->allow_link_css_params ) )
		{	// Enable link/button CSS classes only for specific widgets like menu widgets:
			$r['widget_link_class'] = array(
					'label' => '<span class="dimmed">'.T_('Link/Button Class').'</span>',
					'size' => 20,
					'note' => sprintf( T_('Replaces %s in class attribute of link/button.'), '<code>$link_class$</code>' ).' '.T_('Leave empty to use default values from skin or from widget.'),
				);
			$r['widget_active_link_class'] = array(
					'label' => '<span class="dimmed">'.T_('Active Link/Button Class').'</span>',
					'size' => 20,
					'note' => sprintf( T_('Replaces %s in class attribute of active link/button.'), '<code>$link_class$</code>' ).' '.T_('Leave empty to use default values from skin or from widget.'),
				);
		}

		if( ! isset( $r['widget_css_class'] ) )
		{	// Widget CSS class:
			$r['widget_css_class'] = array(
					'label' => '<span class="dimmed">'.T_( 'Widget CSS Class' ).'</span>',
					'size' => 20,
					'note' => sprintf( T_('Will be injected into %s in your skin containers (along with required system classes).'), '<code>$wi_class$</code>' ),
				);
		}

		if( ! isset( $r['widget_ID'] ) )
		{	// Widget ID:
			$r['widget_ID'] = array(
					'label' => '<span class="dimmed">'.T_( 'Widget DOM ID' ).'</span>',
					'size' => 20,
					'note' => sprintf( T_('Replaces %s in your skins containers.'), '<code>$wi_ID$</code>' ).' '.sprintf( T_('Leave empty to use default value: %s.'), '<code>widget_'.$this->type.'_'.$this->code.'_'.$this->ID.'</code>' ),
				);
		}

		if( ! isset( $r['allow_blockcache'] ) )
		{	// Allow widget/block caching:
			$widget_Blog = & $this->get_Blog();
			$r['allow_blockcache'] = array(
					'label' => T_( 'Allow caching' ),
					'note' => ( $widget_Blog && $widget_Blog->get_setting( 'cache_enabled_widgets' ) ) ?
							T_('Uncheck to prevent this widget from ever being cached in the block cache. (The whole page may still be cached.) This is only needed when a widget is poorly handling caching and cache keys.') :
							T_('Block caching is disabled for this collection.'),
					'type' => 'checkbox',
					'defaultvalue' => true,
				);
		}

		if( ! empty( $advanced_layout_is_started ) )
		{	// End fieldset of advanced settings:
			$r['advanced_layout_end'] = array(
					'layout' => 'end_fieldset',
				);
			$advanced_layout_is_started = false;
		}

		return $r;
	}


	/**
	 * Get advanced definitions for editable params.
	 *
	 * @see Plugin::GetDefaultSettings()
	 *
	 * @return array Advanced params
	 */
	function get_advanced_param_definitions()
	{
		return array();
	}


	/**
	 * Load param array.
	 */
	function load_param_array()
	{
		if( is_null( $this->param_array ) )
		{	// Param array has not been loaded yet
			$this->param_array = @unserialize( $this->params );

			if( empty( $this->param_array ) )
			{	// No saved param values were found:
				$this->param_array = array();
			}
		}
	}


	/**
	 * Get param value.
	 *
	 * @param string Parameter name
	 * @param mixed Default value, Set to different than NULL only if it is called from a widget::get_param_definition() function to avoid infinite loop
	 * @param string|NULL Group name
	 * @return mixed
	 */
	function get_param( $parname, $default_value = NULL, $group = NULL )
	{
		$this->load_param_array();

		if( strpos( $parname, '[' ) !== false )
		{	// Get value for array setting like "sample_sets[0][group_name_param_name]":
			$setting_names = explode( '[', $parname );
			if( isset( $this->param_array[ $setting_names[0] ] ) )
			{
				$setting_value = $this->param_array[ $setting_names[0] ];
				unset( $setting_names[0] );
				foreach( $setting_names as $setting_name )
				{
					$setting_name = trim( $setting_name, ']' );
					if( isset( $setting_value[ $setting_name ] ) )
					{
						$setting_value = $setting_value[ $setting_name ];
					}
					else
					{
						$setting_value = NULL;
						break;
					}
				}
				return $setting_value;
			}
		}
		else
		{	// Get normal(not array) setting value:
			if( isset( $this->disp_params[ $parname ] ) )
			{	// Get an overridden value from skin:
				return $this->disp_params[ $parname ];
			}
			elseif( isset( $this->param_array[ $parname ] ) )
			{	// Get value from DB:
				return $this->param_array[ $parname ];
			}
		}

		if( $default_value !== NULL )
		{	// Use defined default value when it is not saved in DB yet:
			// (This call is used to get a value from function widget::get_param_definition() to avoid infinite loop)
			return $default_value;
		}

		// Try default values from widget config:
		$params = $this->get_param_definitions( NULL );

		if( $group === NULL )
		{	// Get param from simple field:
			if( isset( $params[$parname]['defaultvalue'] ) )
			{	// We have a default value:
				return $params[$parname]['defaultvalue'] ;
			}
		}
		else
		{	// Get param from group field:
			$parname = substr( $parname, strlen( $group ) );
			if( isset( $params[$group]['inputs'][$parname]['defaultvalue'] ) )
			{	// We have a default value:
				return $params[$group]['inputs'][$parname]['defaultvalue'] ;
			}
		}

		return NULL;
	}


	/**
	 * Set param value
	 *
	 * @param string parameter name
	 * @param mixed parameter value
	 * @param boolean true to set to NULL if empty value
	 * @return boolean true, if a value has been set; false if it has not changed
	 */
	function set( $parname, $parvalue, $make_null = false, $group = NULL )
	{
		$params = $this->get_param_definitions( NULL );

		if( isset( $params[$parname] ) ||
		    ( $group !== NULL && isset( $params[ $group ]['inputs'][ substr( $parname, strlen( $group ) ) ] ) ) )
		{ // This is a widget specific param:
			// Make sure param_array is loaded before set the param value
			$this->load_param_array();
			$this->param_array[$parname] = $parvalue;
			// This is what'll be saved to the DB:
			return $this->set_param( 'params', 'string', serialize($this->param_array), $make_null );
		}

		switch( $parname )
		{
			default:
				return $this->set_param( $parname, 'string', $parvalue, $make_null );
		}
	}


	/**
	 * Request all required css and js files for this widget
	 */
	function request_required_files()
	{
	}


	/**
	 * Prepare display params
	 *
	 * @todo Document default params and default values.
	 * @todo fp> do NOT call this when just listing widget names in the back-office. It's overkill!
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function init_display( $params )
	{
		global $admin_url, $debug;

		if( !is_null($this->disp_params) )
		{ // Params have been initialized before...
			return;
		}

		// Generate widget defaults (editable params) array:
		$widget_defaults = array();
		$defs = $this->get_param_definitions( array() );
		foreach( $defs as $parname => $parmeta )
		{
			if( isset( $parmeta['type'] ) && $parmeta['type'] == 'checklist' )
			{
				$widget_defaults[ $parname ] = array();
				foreach( $parmeta['options'] as $parmeta_option )
				{
					$widget_defaults[ $parname ][ $parmeta_option[0] ] = $parmeta_option[2];
				}
			}
			else
			{
				$widget_defaults[ $parname ] = ( isset( $parmeta['defaultvalue'] ) ) ? $parmeta['defaultvalue'] : NULL;
			}
		}

		// Load DB configuration:
		$this->load_param_array();

		// DEFAULT CONTAINER PARAMS:
		// Merge basic defaults < widget defaults (editable params) < container params < DB params
		// note: when called with skin_widget it falls back to basic defaults < widget defaults < calltime params < array()
		$params = array_merge( array(
					'widget_context' => 'general',		// general | item | user
					'block_start' => '<div class="evo_widget widget $wi_class$">',
					'block_end' => '</div>',
					'block_display_title' => true,
					'block_title_start' => '<h3>',
					'block_title_end' => '</h3>',
					'block_body_start' => '',
					'block_body_end' => '',
					'collist_start' => '',
					'collist_end' => '',
					'coll_start' => '<h4>',
					'coll_end' => '</h4>',
					'list_start' => '<ul>',
					'list_end' => '</ul>',
					'item_start' => '<li>',
					'item_end' => '</li>',
						'item_title_start' => '<strong>',
						'item_title_end' => ':</strong> ',
						'link_default_class' => 'default',
						'link_selected_class' => 'selected',
						'item_text_start' => '',
						'item_text_end' => '',
						'item_text' => '%s',
					'item_selected_start' => '<li class="selected">',
					'item_selected_end' => '</li>',
					'item_selected_text' => '%s',

					// Automatically detect whether we are displaying menu links as list elements or as standalone buttons:
					'inlist' => 'auto',		// auto is based on 'list_start'; may also be true or false
					// Button styles used for Menu Links / Buttons widgets:
					'button_default_class' => 'btn btn-default btn-margin-right',
					'button_selected_class' => 'btn btn-default btn-margin-right active',
					'button_group_start' => '<span class="btn-group">',
					'button_group_end' => '</span>',
					// Tabs style:
					'tabs_start'         => '<ul class="nav nav-tabs">',
					'tabs_end'           => '</ul>',
					'tab_start'          => '<li>',
					'tab_end'            => '</li>',
					'tab_selected_start' => '<li class="active">',
					'tab_selected_end'   => '</li>',
					'tab_default_class'  => '',
					'tab_selected_class' => 'active',

					'grid_start' => '<table cellspacing="1" class="widget_grid">',
						'grid_colstart' => '<tr>',
							'grid_cellstart' => '<td>',
							'grid_cellend' => '</td>',
						'grid_colend' => '</tr>',
					'grid_end' => '</table>',
					'grid_nb_cols' => 2,
					'flow_start' => '<div class="widget_flow_blocks">',
						'flow_block_start' => '<div>',
						'flow_block_end' => '</div>',
					'flow_end' => '</div>',
					'rwd_start' => '<div class="widget_rwd_blocks row">',
						'rwd_block_start' => '<div class="$wi_rwd_block_class$"><div class="widget_rwd_content clearfix">',
						'rwd_block_end' => '</div></div>',
					'rwd_end' => '</div>',
					'thumb_size' => 'crop-80x80',
					'link_type' => 'canonic',		// 'canonic' | 'context' (context will regenrate URL injecting/replacing a single filter)
					'item_selected_text_start' => '',
					'item_selected_text_end' => '',
					'group_start' => '<ul>',
					'group_end' => '</ul>',
					'group_item_start' => '<li>',
					'group_item_end' => '</li>',
					'notes_start' => '<div class="notes">',
					'notes_end' => '</div>',
					'tag_cloud_start' => '<p class="tag_cloud">',
					'tag_cloud_end' => '</p>',
					'limit' => 100,
				), $widget_defaults, $params, $this->param_array );

		if( isset( $params['override_params_for_'.$this->code] ) )
		{	// Use specific widget params if they are defined for this widget by code:
			$params = array_merge( $params, $params['override_params_for_'.$this->code] );
		}

		// Customize params to the current widget:

		// Add additional css classes if required:
		$widget_css_class = 'widget_'.$this->type.'_'.$this->code.( empty( $params[ 'widget_css_class' ] ) ? '' : ' '.$params[ 'widget_css_class' ] );

		// Set additional css class depending on layout:
		$layout = isset( $params['layout'] ) ? $params['layout'] : ( isset( $params['thumb_layout'] ) ? $params['thumb_layout'] : NULL );
		switch( $layout )
		{
			case 'rwd':
				$widget_css_class .= ' evo_layout_rwd';
				break;
			case 'flow':
				$widget_css_class .= ' evo_layout_flow';
				break;
			case 'list':
				$widget_css_class .= ' evo_layout_list';
				break;
			case 'grid':
				$widget_css_class .= ' evo_layout_grid';
				break;
		}

		// Add custom id if required, default to generic id for validation purposes:
		$widget_ID = ( !empty($params[ 'widget_ID' ]) ? $params[ 'widget_ID' ] : 'widget_'.$this->type.'_'.$this->code.'_'.$this->ID );

 
         if (isset($params['WidgetContainer'])) {
                $x_params['WidgetContainer'] = clone $params['WidgetContainer'];
                unset ($params['WidgetContainer']);
                $herold = 'WidgetContainer';
         }
         
         if (isset($params['CommentList'])) {
                $x_params['CommentList'] = clone $params['CommentList'];
                unset ($params['CommentList']);
                $herold = 'CommentList';
         }
         if (isset($params['ItemList'])) {
                $x_params['ItemList'] = clone $params['ItemList'];
                unset ($params['ItemList']);
                $herold = 'ItemList';
         }

         if (isset($params['Item'])) {
                $x_params['Item'] = clone $params['Item'];
                unset ($params['Item']);
                $herold = 'Item';
         }         
         
         $herold = NULL;  
                   
   
   @$params = str_replace( array( '$wi_ID$', '$wi_class$' ), array( $widget_ID, $widget_css_class ), $params);
   /*
    foreach ($params as &$param) {
        $param = $param ?? '';
    $param = str_replace(
        array('$wi_ID$', '$wi_class$'),
        array($widget_ID, $widget_css_class),
        $param
    );
    }   
 */

switch ( $herold ) {
    case 'WidgetContainer':
        $params['WidgetContainer'] = $x_params['WidgetContainer']; 
        break;
    case "CommentList":
    $params['CommentList'] = $x_params['CommentList']; 
        break;
    case "ItemList":
    $params['ItemList'] = $x_params['ItemList']; 
        break;    
    case "Item":
    $params['Item'] = $x_params['Item']; 
        break;
}   
    
    $this->disp_params = $params; 

    } 

	/**
	 * Convert old display params to new name.
	 *
 	 * Use this function if some params were renamed.
	 * This function will look for the old params and convert them if no new param is present
	 */
	function convert_legacy_param( $old_name, $new_name )
	{
		//pre_dump( $this->disp_params );
		if( isset($this->disp_params[$old_name]) && !isset($this->disp_params[$new_name]) )
		{	// We have old param but NOT new param, duplicate old to new:
			$this->disp_params[$new_name] = $this->disp_params[$old_name];
		}
	}


	/**
	 * Display the widget!
	 *
	 * Should be overriden by core widgets
	 *
	 * @todo fp> handle custom params for each widget
	 *
	 * @param array MUST contain at least the basic display params
	 * @return bool true if the widget displayed something (other than a debug message)
	 */
	function display( $params )
	{
		global $Collection, $Blog;
		global $Plugins;
		global $rsc_url;

		// prepare for display:
		$this->init_display( $params );

		switch( $this->type )
		{
			case 'plugin':
				// Set widget ID param to make it available in plugin function SkinTag():
				$this->disp_params['wi_ID'] = $this->ID;
				// Call plugin (will return false if Plugin is not enabled):
				if( $Plugins->call_by_code( $this->code, $this->disp_params ) )
				{
					return true;
				}
				else
				{	// Plugin failed (happens when a plugin has been disabled for example):
					if( $this->mode == 'designer' )
					{	// Display red text in customizer widget designer mode in order to make this plugin visible for editing:
						echo $this->disp_params['block_start'].get_rendering_error( T_('Inactive / Uninstalled plugin').': "'.$this->code.'"', 'span' ).$this->disp_params['block_end'];
					}
					return false;
				}
		}

		echo "Widget $this->type : $this->code did not provide a display() method! ";

		return false;
	}


	/**
	 * Wraps display in a cacheable block.
	 *
	 * @param array MUST contain at least the basic display params
	 * @param array of extra keys to be used for cache keying
	 */
	function display_with_cache( $params, $keys = array() )
	{
		global $Collection, $Blog, $Timer, $debug, $admin_url, $Session;

		$this->init_display( $params );

		// Display the debug conatainers when $debug = 2 OR when it is turned on from evo menu under "Collection" -> "Show/Hide containers"
		$display_containers = ( $debug == 2 ) || ( is_logged_in() && $Session->get( 'display_containers_'.$Blog->ID ) );

		$force_nocaching = false;

		// Enable the desinger mode when it is turned on from evo menu under "Designer Mode/Exit Designer" or "Collection" -> "Enable/Disable designer mode"
		if( is_logged_in() && $Session->get( 'designer_mode_'.$Blog->ID ) )
		{	// Initialize data which is used by JavaScript to build overlay designer mode html elements:
			$designer_mode_data = array(
					'data-id'        => $this->ID,
					'data-type'      => $this->get_name(),
					'data-container' => $this->get_container_param( 'code' ),
				);
			if( $this->get( 'code' ) == 'subcontainer' &&
			    ( $sub_WidgetContainer = & $this->get_sub_WidgetContainer() ) )
			{	// For Sub-Container widget we should know what sub-container is used in order to list and add widgets on customizer mode:
				$designer_mode_data['data-subcontainer-name'] = $sub_WidgetContainer->get( 'name' );
				$designer_mode_data['data-subcontainer-code'] = $this->get_param( 'container' );
			}
			// Set data to know current user has a permission to edit this widget:
			$designer_mode_data['data-can-edit'] = check_user_perm( 'blog_properties', 'edit', false, $Blog->ID ) ? 1 : 0;
			// Don't load a widget content from cache when designer mode is enabled:
			$force_nocaching = true;
			// Set designer mode:
			$this->mode = 'designer';
			// Set this param for plugin widgets:
			$this->disp_params['debug_mode'] = $this->mode;
		}

		if( $force_nocaching
		    || ! $Blog->get_setting( 'cache_enabled_widgets' )
		    || ! $this->disp_params['allow_blockcache']
		    || $this->get_cache_status() == 'disallowed' )
		{ // NO CACHING - We do NOT want caching for this collection or for this specific widget:

			if( $display_containers )
			{ // DEBUG:
				$is_subcontainer = ( $this->get( 'code' ) == 'subcontainer' || $this->get( 'code' ) == 'subcontainer_row' );
				echo '<div class="dev-blocks '.( $is_subcontainer ? 'dev-blocks--subcontainer' : 'dev-blocks--widget' ).'"><div class="dev-blocks-name" title="'.
							( $Blog->get_setting('cache_enabled_widgets') ? 'Widget params have BlockCache turned off' : 'Collection params have BlockCache turned off' ).'">';
				if( check_user_perm( 'blog_properties', 'edit', false, $Blog->ID ) )
				{	// Display a link to edit this widget only if current user has a permission:
					echo '<span class="dev-blocks-action"><a href="'.$admin_url.'?ctrl=widgets&amp;action=edit&amp;wi_ID='.$this->ID.'">Edit</a></span>';
				}
				echo 'Widget: <b>'.$this->get_name().'</b> - Cache OFF <i class="fa fa-info">?</i></div>'."\n";
			}

			// Start to collect output buffer in order to can clean up rendering errors when it need below:
			ob_start();

			if( ! empty( $designer_mode_data ) )
			{	// Append designer mode html tag attributes to first not empty widget wrapper/container:
				$widget_wrappers = array(
						'block_start',
						'block_body_start',
						'list_start',
						array( 'item_start', 'item_selected_start' ),
					);
				foreach( $widget_wrappers as $widget_wrapper_items )
				{
					if( ! is_array( $widget_wrapper_items ) )
					{
						$widget_wrapper_items = array( $widget_wrapper_items );
					}
					$wrapper_is_found = false;
					foreach( $widget_wrapper_items as $widget_wrapper )
					{
						if( !empty( $params[ $widget_wrapper ] ) || !empty( $params['override_params_for_'.$this->code][ $widget_wrapper ] ) )
						{	// Append new data for widget wrapper:
							$attrib_actions = array(
									'data-id'        => 'replace',
									'data-type'      => 'replace',
									'data-container' => 'replace',
									'data-can-edit'  => 'replace',
								);
							if( !empty( $params[ $widget_wrapper ] ) )
							{	// If this wrapper is filled and used with current widget,
								$params[ $widget_wrapper ] = update_html_tag_attribs( $params[ $widget_wrapper ], $designer_mode_data, $attrib_actions );
							}

							if( !empty( $params['override_params_for_'.$this->code][ $widget_wrapper ] ) )
							{	// Also update override params:
								$params['override_params_for_'.$this->code][ $widget_wrapper ] = update_html_tag_attribs( $params['override_params_for_'.$this->code][ $widget_wrapper ], $designer_mode_data, $attrib_actions );
							}

							if( isset( $this->disp_params[ $widget_wrapper ] ) )
							{	// Also update params if they already have been initialized before:
								$this->disp_params[ $widget_wrapper ] = update_html_tag_attribs( $this->disp_params[ $widget_wrapper ], $designer_mode_data, $attrib_actions );
							}
							$wrapper_is_found = true;
						}
					}
					if( $wrapper_is_found )
					{	// Stop search other wrapper in order to use only first filled wrapper:
						break;
					}
				}
				if( ! $wrapper_is_found )
				{	// Display error if widget has no wrappers to enable designer mode:
					echo ' '.get_rendering_error( 'Widget <code>'.$this->code.'</code> cannot be manipulated because it lacks a wrapper tag.', 'span' ).' ';
				}
			}

			if( ! isset( $params['widget_'.$this->code.'_display'] ) || ! empty( $params['widget_'.$this->code.'_display'] ) )
			{	// Display widget content:
				$this->display( $params );
			}
			else
			{	// Hide the widget by code if it is requsted from skin:
				$this->display_debug_message( 'Widget "'.$this->get_name().'" is hidden by code <code>'.$this->code.'</code> from skin template.' );
			}

			$widget_content = ob_get_clean();

			if( ! check_user_perm( 'blog_admin', 'edit', false, $Blog->ID ) )
			{	// Clean up rendering errors from content if current User is not collection admin:
				$widget_content = clear_rendering_errors( $widget_content );
			}

			echo $widget_content;

			if( $display_containers )
			{ // DEBUG:
				echo "</div>\n";
			}
		}
		else
		{ // Instantiate BlockCache:
			$Timer->resume( 'BlockCache' );
			// Extend cache keys:
			$keys += $this->get_cache_keys();

			$this->BlockCache = new BlockCache( 'widget', $keys );

			$content = $this->BlockCache->check();

			$Timer->pause( 'BlockCache' );

			if( $content !== false )
			{ // cache hit, let's display:

				if( $display_containers )
				{ // DEBUG:
					echo '<div class="dev-blocks dev-blocks--widget dev-blocks--widget--incache"><div class="dev-blocks-name" title="Cache key = '.$this->BlockCache->serialized_keys.'">';
					if( check_user_perm( 'blog_properties', 'edit', false, $Blog->ID ) )
					{	// Display a link to edit this widget only if current user has a permission:
						echo '<span class="dev-blocks-action"><a href="'.$admin_url.'?ctrl=widgets&amp;action=edit&amp;wi_ID='.$this->ID.'">Edit</a></span>';
					}
					echo 'Widget: <b>'.$this->get_name().'</b> - FROM cache <i class="fa fa-info">?</i></div>'."\n";
				}

				if( ! check_user_perm( 'blog_admin', 'edit', false, $Blog->ID ) )
				{	// Clean up rendering errors from content if current User is not collection admin:
					$content = clear_rendering_errors( $content );
				}

				echo $content;

				if( $display_containers )
				{ // DEBUG:
					echo "</div>\n";
				}

			}
			else
			{ // Cache miss, we have to generate:

				if( $display_containers )
				{ // DEBUG:
					echo '<div class="dev-blocks dev-blocks--widget dev-blocks--widget--notincache"><div class="dev-blocks-name" title="Cache key = '.$this->BlockCache->serialized_keys.'">';
					if( check_user_perm( 'blog_properties', 'edit', false, $Blog->ID ) )
					{	// Display a link to edit this widget only if current user has a permission:
						echo '<span class="dev-blocks-action"><a href="'.$admin_url.'?ctrl=widgets&amp;action=edit&amp;wi_ID='.$this->ID.'">Edit</a></span>';
					}
					echo 'Widget: <b>'.$this->get_name().'</b> - NOT in cache <i class="fa fa-info">?</i></div>'."\n";
				}

				$this->BlockCache->start_collect();

				if( ! isset( $params['widget_'.$this->code.'_display'] ) || ! empty( $params['widget_'.$this->code.'_display'] ) )
				{	// Display widget content:
					$this->display( $params );
				}
				else
				{	// Hide the widget by code if it is requsted from skin:
					$this->display_debug_message( 'Widget "'.$this->get_name().'" is hidden by code <code>'.$this->code.'</code> from skin template.' );
				}

				// Save collected cached data if needed:
				$content = $this->BlockCache->end_collect( false );

				if( ! check_user_perm( 'blog_admin', 'edit', false, $Blog->ID ) )
				{	// Clean up rendering errors from content if current User is not collection admin:
					$content = clear_rendering_errors( $content );
				}

				echo $content;

				if( $display_containers )
				{ // DEBUG:
					echo "</div>\n";
				}

			}
		}
	}


	/**
	 * Maybe be overriden by some widgets, depending on what THEY depend on..
	 *
	 * @return array of keys this widget depends on
	 */
	function get_cache_keys()
	{
		global $Collection, $Blog;

		if( $this->type == 'plugin' && $this->get_Plugin() )
		{	// Get widget cache keys from plugin:
			return $this->Plugin->get_widget_cache_keys( $this->ID );
		}

		return array(
				'wi_ID'       => $this->ID, // Have the widget settings changed ?
				'set_coll_ID' => $Blog->ID, // Have the settings of the blog changed ? (ex: new skin)
			);
	}


	/**
	 * Get cache status
	 *
	 * @param boolean TRUE to check if blog allows a caching for widgets
	 * @return string 'enabled', 'disabled', 'disallowed', 'denied'
	 */
	function get_cache_status( $check_blog_restriction  = false )
	{
		$default_widget_params = $this->get_param_definitions( array() );
		if( ! empty( $default_widget_params )
		    && isset( $default_widget_params['allow_blockcache'] )
		    && isset( $default_widget_params['allow_blockcache']['disabled'] )
		    && ( $default_widget_params['allow_blockcache']['disabled'] == 'disabled' ) )
		{ // Widget cache is NOT allowed by widget config
			return 'disallowed';
		}
		else
		{ // Check current cache status if it is allowed
			if( $check_blog_restriction )
			{ // Check blog restriction for widget caching
				$widget_Blog = & $this->get_Blog();
				if( $widget_Blog && ! $widget_Blog->get_setting( 'cache_enabled_widgets' ) )
				{	// Widget/block cache is not allowed by collection setting:
					return 'denied';
				}
			}

			if( $this->get_param( 'allow_blockcache' ) )
			{ // Enabled
				return 'enabled';
			}
			else
			{ // Disabled
				return 'disabled';
			}
		}
	}


	/**
	 * Note: a container can prevent display of titles with 'block_display_title'
	 * This is useful for the lists in the headers
	 * fp> I'm not sure if this param should be overridable by widgets themselves (priority problem)
	 * Maybe an "auto" setting.
	 *
	 * @access protected
	 */
	function disp_title( $title = NULL, $display = true )
	{
		if( is_null($title) )
		{
			$title = & $this->disp_params['title'];
		}

		if( $this->disp_params['block_display_title'] && !empty( $title ) )
		{
			$r = $this->disp_params['block_title_start'];
			if( ! isset( $this->disp_params['hide_header_title'] ) )
			{
				$r .= format_to_output( $title );
			}
			$r .= $this->disp_params['block_title_end'];

			if( $display ) echo $r;

			return $r;
		}
	}


	/**
	 * List of collections/blogs
	 *
	 * @param array MUST contain at least the basic display params
	 */
	function disp_coll_list( $filter = 'public', $order_by = 'ID', $order_dir = 'ASC' )
	{
		/**
		 * @var Blog
		 */
		global $Collection, $Blog, $baseurl;

		echo $this->disp_params['block_start'];

		$this->disp_title();

		/**
		 * @var BlogCache
		 */
		$BlogCache = & get_BlogCache();

		if( $filter == 'owner' )
		{	// Load blogs of same owner
			$blog_array = $BlogCache->load_owner_blogs( $Blog->owner_user_ID, $order_by, $order_dir );
		}
		else
		{	// Load all public blogs
			$blog_array = $BlogCache->load_public( $order_by, $order_dir );
		}

		// 3.3? if( $this->disp_params['list_type'] == 'list' )
		// fp> TODO: init default value for $this->disp_params['list_type'] to avoid error
		{
			echo $this->disp_params['list_start'];

			foreach( $blog_array as $l_blog_ID )
			{	// Loop through all public blogs:

				$l_Blog = & $BlogCache->get_by_ID( $l_blog_ID );

				if( $Blog && $l_blog_ID == $Blog->ID )
				{ // This is the blog being displayed on this page:
					echo $this->disp_params['item_selected_start'];
					$link_class = empty( $this->disp_params['widget_active_link_class'] ) ? $this->disp_params['link_selected_class'] : $this->disp_params['widget_active_link_class'];
				}
				else
				{
					echo $this->disp_params['item_start'];
					$link_class = empty( $this->disp_params['widget_link_class'] ) ? $this->disp_params['link_default_class'] : $this->disp_params['widget_link_class'];
				}

				echo '<a href="'.$l_Blog->gen_blogurl().'" class="'.$link_class.'" title="'
											.$l_Blog->dget( 'name', 'htmlattr' ).'">';

				if( $Blog && $l_blog_ID == $Blog->ID )
				{ // This is the blog being displayed on this page:
					echo $this->disp_params['item_selected_text_start'];
					printf( $this->disp_params['item_selected_text'], $l_Blog->dget( 'shortname', 'htmlbody' ) );
					echo $this->disp_params['item_selected_text_end'];
					echo '</a>';
					echo $this->disp_params['item_selected_end'];
				}
				else
				{
					echo $this->disp_params['item_text_start'];
					printf( $this->disp_params['item_text'], $l_Blog->dget( 'shortname', 'htmlbody' ) );
					echo $this->disp_params['item_text_end'];
					echo '</a>';
					echo $this->disp_params['item_end'];
				}
			}

			echo $this->disp_params['list_end'];
		}
		/* 3.3?
			Problems:
			-In FF3/XP with skin evoCamp, I click to drop down and it already reloads the page on the same blog.
			-Missing appropriate CSS so it displays at least half nicely in most of teh default skins
		{
			$select_options = '';
			foreach( $blog_array as $l_blog_ID )
			{	// Loop through all public blogs:
				$l_Blog = & $BlogCache->get_by_ID( $l_blog_ID );

				// Add item select list:
				$select_options .= '<option value="'.$l_blog_ID.'"';
				if( $Blog && $l_blog_ID == $Blog->ID )
				{
					$select_options .= ' selected="selected"';
				}
				$select_options .= '>'.$l_Blog->dget( 'shortname', 'formvalue' ).'</option>'."\n";
			}

			if( !empty($select_options) )
			{
				echo '<form action="'.$baseurl.'" method="get">';
				echo '<select name="blog" onchange="this.form.submit();">'.$select_options.'</select>';
				echo '<noscript><input type="submit" value="'.T_('Go').'" /></noscript></form>';
			}
		}
		*/
		echo $this->disp_params['block_end'];
	}


	/**
	 * Insert object into DB based on previously recorded changes.
	 *
	 * @return boolean true on success
	 */
	function dbinsert()
	{
		global $DB;

		if( $this->ID != 0 )
		{
			debug_die( 'Existing object cannot be inserted!' );
		}

		$DB->begin();

		if( ! isset( $this->order ) )
		{
			$order_max = $DB->get_var(
				'SELECT MAX(wi_order)
					FROM T_widget__widget
					WHERE wi_wico_ID = '.$this->wico_ID, 0, 0, 'Get current max order' );

			$this->set( 'order', $order_max+1 );
		}

		$res = parent::dbinsert();

		$DB->commit();

		return $res;
	}


	/**
	 * Update the DB based on previously recorded changes
	 */
	function dbupdate()
	{
		$result = parent::dbupdate();

		if( $result )
		{	// If widget has been really updated
			// BLOCK CACHE INVALIDATION:
			// This widget has been modified, cached content depending on it should be invalidated:
			BlockCache::invalidate_key( 'wi_ID', $this->ID );
		}

		return $result;
	}


	/**
	 * Get Blog
	 *
	 * @return object Blog
	 */
	function & get_Blog()
	{
		if( $this->Blog === NULL )
		{ // Get blog only first time
			$BlogCache = & get_BlogCache();
			$this->Blog = & $BlogCache->get_by_ID( $this->get_coll_ID(), false, false );
		}

		return $this->Blog;
	}


	/**
	 * Get current layout
	 *
	 * @return string|NULL Widget layout | NULL - if widget has no layout setting
	 */
	function get_layout()
	{
		return get_widget_layout( $this->disp_params );
	}


	/**
	 * Get start of layout
	 *
	 * @return string
	 */
	function get_layout_start()
	{
		return get_widget_layout_start( $this->disp_params );
	}


	/**
	 * Get end of layout
	 *
	 * @param integer Cell index (used for grid/table layout)
	 * @return string
	 */
	function get_layout_end( $cell_index = 0 )
	{
		return get_widget_layout_end( $cell_index, $this->disp_params );
	}


	/**
	 * Get item start of layout
	 *
	 * @param integer Cell index (used for grid/table layout)
	 * @param boolean TRUE if current item/cell is selected
	 * @param string Prefix for param
	 * @return string
	 */
	function get_layout_item_start( $cell_index = 0, $is_selected = false, $disp_param_prefix = '' )
	{
		return get_widget_layout_item_start( $cell_index, $is_selected, $disp_param_prefix, $this->disp_params );
	}


	/**
	 * Get item end of layout
	 *
	 * @param integer Cell index (used for grid/table layout)
	 * @param boolean TRUE if current item/cell is selected
	 * @param string Prefix for param
	 * @return string
	 */
	function get_layout_item_end( $cell_index = 0, $is_selected = false, $disp_param_prefix = '' )
	{
		return get_widget_layout_item_end($cell_index, $is_selected, $disp_param_prefix, $this->disp_params );
	}


	/**
	 * Get User that should be used for this widget currently depending on context
	 *
	 * @return object User
	 */
	function & get_target_User()
	{
		if( $this->target_User === NULL )
		{	// Initialize target User only first time:
			global $Item, $Blog;

			if( $this->disp_params['widget_context'] == 'user' )
			{	// Use an user of current page disp=user (Only if we are in the context of displaying an User, not if $User is set from before):
				$user_ID = get_param( 'user_ID' );
				if( empty( $user_ID ) && is_logged_in() )
				{	// Use current logged in User:
					global $current_User;
					$user_ID = $current_User->ID;
				}
				if( ! empty( $user_ID ) )
				{	// Try to get User by ID:
					$UserCache = & get_UserCache();
					$this->target_User = & $UserCache->get_by_ID( $user_ID, false, false );
				}
			}

			if( empty( $this->target_User ) && $this->disp_params['widget_context'] == 'item' && ! empty( $Item ) )
			{	// Use an author of the current $Item (Only if we are in the context of displaying an Item, not if $Item is set from before):
				$this->target_User = & $Item->get_creator_User();
			}

			if( empty( $this->target_User ) && ! empty( $Blog ) )
			{	// Use an owner of the current $Blog:
				$this->target_User = & $Blog->get_owner_User();
			}
		}

		return $this->target_User;
	}


	/**
	 * Get the list of validated renderers for this Widget. This includes stealth plugins etc.
	 *
	 * @return array List of validated renderer codes
	 */
	function get_renderers_validated()
	{
		if( ! isset( $this->renderers_validated ) )
		{
			global $Plugins;

			$widget_Blog = & $this->get_Blog();

			// Convert active renderers options for plugin functions below:
              $this->disp_params['renderers']= array();
              $widget_renderers = array_keys( $this->disp_params['renderers'] );

			$this->renderers_validated = $Plugins->validate_renderer_list( $widget_renderers, array(
					'Blog'         => & $widget_Blog,
					'setting_name' => 'shared_apply_rendering'
				) );
		}

		return $this->renderers_validated;
	}


	/**
	 * Get content which is rendered with plugins
	 *
	 * @param string Source content
	 * @return string Rendered content
	 */
	function get_rendered_content( $content )
	{
		if( ! isset( $this->disp_params['renderers'] ) )
		{	// This widget has no render settings, Return original content:
			return $content;
		}

		global $Plugins;

		$widget_Blog = & $this->get_Blog();
		if( empty( $widget_Blog ) )
		{	// Use current collection if it is not defined, e.g. for shared widget containers:
			global $Blog;
			$widget_Blog = $Blog;
		}
		$widget_renderers = $this->get_renderers_validated();

		// Do some optional filtering on the content
		// Typically stuff that will help the content to validate
		// Useful for code display.
		// Will probably be used for validation also.
		// + APPLY RENDERING from Rendering Plugins:
		$Plugins_admin = & get_Plugins_admin();
		$params = array(
				'object_type' => 'Widget',
				'object'      => & $this,
				'object_Blog' => & $widget_Blog
			);
		$fake_title = '';
		$Plugins_admin->filter_contents( $fake_title /* by ref */, $content /* by ref */, $widget_renderers, $params /* by ref */ );

		// Render block content with selected plugins:
		$Plugins->render( $content, $widget_renderers, 'htmlbody', array( 'Blog' => & $widget_Blog, 'Widget' => $this ), 'Render' );
		$Plugins->render( $content, $widget_renderers, 'htmlbody', array( 'Blog' => & $widget_Blog, 'Widget' => $this ), 'Display' );

		return $content;
	}


	/**
	 * Get JavaScript code which helps to edit widget form
	 *
	 * @return string
	 */
	function get_edit_form_javascript()
	{
		return false;
	}


	/**
	 * Get a form display mode depending on requested skin param 'form_display'
	 *
	 * @param string Default value for form display: 'standard', 'compact', 'nolabels', 'inline', 'grouped'
	 * @return string Current form display: 'standard', 'compact', 'nolabels', 'inline', 'grouped' or another default of the widget
	 */
	function get_form_display( $default_form_display = 'standard' )
	{
		$form_display = isset( $this->disp_params['form_display'] ) ? $this->disp_params['form_display'] : $default_form_display;
		return in_array( $form_display, array( 'standard', 'compact', 'nolabels', 'inline', 'grouped' ) ) ? $form_display : $default_form_display;
	}


	/**
	 * Get Item's info from param by ID or slug
	 *
	 * @param string Param name
	 * @return string
	 */
	function get_param_item_info( $param_name )
	{
		$param_value = $this->get_param( $param_name, '' );
		if( empty( $param_value ) )
		{	// Param is not defined:
			return '';
		}

		$ItemCache = & get_ItemCache();
		$param_value_is_ID = is_number( $param_value );
		if( ! ( $param_value_is_ID && $param_Item = & $ItemCache->get_by_ID( $param_value, false, false ) ) &&
		    ! ( ! $param_value_is_ID && $param_Item = & $ItemCache->get_by_urltitle( $param_value, false, false ) ) )
		{	// Item is not detected:
			return get_rendering_error( T_('Item is not found.'), 'span' );
		}

		$item_info = '';
		$status_icons = get_visibility_statuses( 'icons' );
		if( isset( $status_icons[ $param_Item->get( 'status' ) ] ) )
		{	// Status colored icon:
			$item_info .= $status_icons[ $param_Item->get( 'status' ) ];
		}
		// Title with link to permament url:
		$item_info .= ' '.$param_Item->get_title( array( 'link_type' => 'admin_view' ) );
		// Icon to edit:
		$item_info .= ' '.$param_Item->get_edit_link( array( 'text' => '#icon#' ) );

		return $item_info;
	}


	/**
	 * Display debug message e-g on designer mode when we need to show widget when nothing to display currently
	 *
	 * @param string Message
	 */
	function display_debug_message( $message = NULL )
	{
		if( $this->mode == 'designer' )
		{	// Display message on designer mode:
			if( $message === NULL )
			{	// Set default message:
				$message = 'Widget "'.$this->get_name().'" is hidden because there is nothing to display.';
			}

			if( preg_match( '#class="[^"]*evo_widget[^"]*"#i', $this->disp_params['block_start'].$this->disp_params['block_body_start'] ) )
			{	// If standard widget wrappers have special style class "evo_widget" we can use it:
				echo $this->disp_params['block_start'];
				$this->disp_title();
				echo $this->disp_params['block_body_start'];
				echo $message;
				echo $this->disp_params['block_body_end'];
				echo $this->disp_params['block_end'];
			}
			else
			{	// Otherwise we should use more wrappers to design widgets correctly, e-g for Menu container:
				echo $this->disp_params['block_start'];
				$this->disp_title();
				echo $this->disp_params['block_body_start'];
				echo $this->get_layout_start();
				echo $this->get_layout_item_start();
				echo '<a href="#">(...)</a>';
				echo $this->get_layout_item_end();
				echo $this->get_layout_end();
				echo $this->disp_params['block_body_end'];
				echo $this->disp_params['block_end'];
			}
		}
	}


	/**
	 * Display an error message
	 *
	 * @param string Message
	 */
	function display_error_message( $message = NULL )
	{
		global $Blog;

		if( isset( $this->BlockCache ) )
		{	// Do NOT cache because this widget has an error which is dispalyed only for collection admin:
			$this->BlockCache->abort_collect();
		}

		if( $message === NULL )
		{
			$message = 'Unable to display widget '.$this->get_name();
		}

		echo $this->disp_params['block_start'];
		$this->disp_title();
		echo $this->disp_params['block_body_start'];
		if( check_user_perm( 'blog_admin', 'edit', false, $Blog->ID ) )
		{	// Display error only for collection admin:
			display_rendering_error( $message, 'span' );
		}
		echo $this->disp_params['block_body_end'];
		echo $this->disp_params['block_end'];
	}


	/**
	 * Create new sub-container automatically
	 *
	 * @param string Suffix for new sub-container
	 * @return string|boolean Code of new created sub-container OR FALSE on fail
	 */
	function create_auto_subcontainer( $name_suffix = '' )
	{
		if( ! isset( $this->cached_existing_containers ) )
		{	// Get existing containers to avoid duplicate error on inserting:
			global $DB;
			$SQL = new SQL( 'Get existing widget containers before auto create new' );
			$SQL->SELECT( 'wico_code' );
			$SQL->FROM( 'T_widget__container' );
			$SQL->WHERE( 'wico_coll_ID = '.$this->get_coll_ID() );
			$SQL->WHERE_and( 'wico_skin_type = '.$DB->quote( $this->get_container_param( 'skin_type' ) ) );
			$this->cached_existing_containers = array_map( 'strtolower', $DB->get_col( $SQL ) );
		}

		// Set data for new creating sub-container:
		$new_WidgetContainer = new WidgetContainer();
		$new_WidgetContainer->set( 'coll_ID', $this->get_coll_ID() );
		$auto_container_name = $this->get_container_param( 'name' ).$name_suffix;
		$new_container_name = $auto_container_name;
		$auto_container_code = strtolower( preg_replace( '/[^0-9a-z\-]+/i', '_', $new_container_name ) );
		if( strlen( $auto_container_code ) > 125 )
		{	// Limit widget code to avoid mysql error of long data:
			$auto_container_code = substr( $auto_container_code, strlen( $auto_container_code ) - 123 );
		}
		$new_container_code = $auto_container_code;
		$c = 1;
		while( in_array( $new_container_code, $this->cached_existing_containers ) )
		{	// Find unique container code per collection and skin type:
			$new_container_code = $auto_container_code.'_'.$c;
			$new_container_name = $auto_container_name.' '.$c;
			$c++;
		}
		$new_WidgetContainer->set( 'code', $new_container_code );
		$new_WidgetContainer->set( 'name', utf8_substr( $new_container_name, 0, 128 ) );
		$new_WidgetContainer->set( 'skin_type', $this->get_container_param( 'skin_type' ) );
		$new_WidgetContainer->set( 'main', 0 );

		// Insert new sub-container:
		if( ! $new_WidgetContainer->dbinsert() )
		{	// Stop updating if some new container cannot be created:
			return false;
		}

		// Cache new created sub-container:
		$this->cached_existing_containers[] = $new_container_code;
		// Set this temp flag to update widget form with new created sub-container:
		$this->reload_page_after_update = true;

		return $new_container_code;
	}
}
?>
