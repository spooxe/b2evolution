<?php
/**
 * This file implements the Admin UI class for the evo skin.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link https://github.com/b2evolution/b2evolution}.
 *
 * @license GNU GPL v2 - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @copyright (c)2003-2020 by Francois Planque - {@link http://fplanque.com/}.
 * Parts of this file are copyright (c)2005 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @package admin-skin
 * @subpackage evo
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Includes
 */
require_once dirname(__FILE__).'/../_adminUI_general.class.php';


/**
 * We'll use the default AdminUI templates etc.
 *
 * @package admin-skin
 * @subpackage evo
 */
class AdminUI extends AdminUI_general
{
	/**dynamic property*/
		 	 public $htmltitle;

	/**
	 * @var string Skin name, Must be the folder name of skin
	 */
	var $skin_name = 'bootstrap';

	/**
	 * This function should init the templates - like adding Javascript through the {@link add_headline()} method.
	 */
	function init_templates()
	{
		global $Messages, $debug, $Hit, $check_browser_version, $adminskins_url, $rsc_url;

		require_js_defer( '#jquery#', 'rsc_url' );
		require_js_defer( 'customized:jquery/raty/jquery.raty.min.js', 'rsc_url' );

		require_js_defer( '#bootstrap#', 'rsc_url' );
		require_css( '#bootstrap_css#', 'rsc_url' );
		// require_css( '#bootstrap_theme_css#', 'rsc_url' );
		require_js_defer( '#bootstrap_typeahead#', 'rsc_url' );

		if( $debug )
		{	// Use readable CSS:
			// rsc/less/bootstrap-basic_styles.less
			// rsc/less/bootstrap-basic.less
			// rsc/less/bootstrap-evoskins.less
			require_css( 'bootstrap-backoffice-b2evo_base.bundle.css', 'rsc_url' ); // Concatenation of the above
		}
		else
		{	// Use minified CSS:
			require_css( 'bootstrap-backoffice-b2evo_base.bmin.css', 'rsc_url' ); // Concatenation + Minifaction of the above
		}

		// Make sure standard CSS is called ahead of custom CSS generated below:
		if( $debug )
		{	// Use readable CSS:
			require_css( $adminskins_url.'bootstrap/rsc/css/style.bundle.css', 'absolute' );
		}
		else
		{	// Use minified CSS:
			require_css( $adminskins_url.'bootstrap/rsc/css/style.bmin.css', 'absolute' );
		}

		// Load general JS file:
		require_js_defer( 'build/bootstrap-evo_backoffice.bmin.js', 'rsc_url' );

		// Set bootstrap css classes for messages
		$Messages->set_params( array(
				'class_outerdiv' => 'action_messages container-fluid',
				'class_success'  => 'alert alert-dismissible alert-success fade in',
				'class_warning'  => 'alert alert-dismissible alert-warning fade in',
				'class_error'    => 'alert alert-dismissible alert-danger fade in',
				'class_note'     => 'alert alert-dismissible alert-info fade in',
				'before_message' => '<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>',
			) );

		// Initialize font-awesome icons and use them as a priority over the glyphicons, @see get_icon()
		init_fontawesome_icons( 'fontawesome-glyphicons', 'rsc_uri' );

		if( $check_browser_version && $Hit->get_browser_version() > 0 && $Hit->is_IE( 9, '<' ) )
		{	// Display info message if browser IE < 9 version and it is allowed by config var:
			$Messages->add( T_('Your web browser is too old. For this site to work correctly, we recommend you use a more recent browser.'), 'note' );
			if( $debug )
			{
				$Messages->add( 'User Agent: '.$Hit->get_user_agent(), 'note' );
			}
		}

		// evo helpdesk widget:
		//require_css( $rsc_url.'css/evo_helpdesk_widget.min.css' );
		//require_js_defer( $rsc_url.'js/evo_helpdesk_widget.min.js' );
	}


	/**
	 * Get the end of the HTML <body>. Close open divs, etc...
	 *
	 * This is not called if {@link $mode} is set.
	 *
	 * @return string
	 */
	function get_body_bottom()
	{
		/*return '<script>
			// Initialize the b2evolution helpdesk widget:
			evo_helpdesk_widget.init( {
				site_url: "https://b2evolution.net/",
				collection: "man",
				'.( empty( $this->page_manual_slug ) ? '' : 'default_slug: "'.$this->page_manual_slug.'",' ).'
			} );
			</script>';*/
	}


	/**
	 * Get the top of the HTML <body>.
	 *
	 * @uses get_page_head()
	 * @return string
	 */
	function get_body_top()
	{
		$r = $this->get_page_head();

		// Blog selector
		$r .= $this->get_bloglist_buttons();

		return $r;
	}


	/**
	 * GLOBAL HEADER - APP TITLE, LOGOUT, ETC.
	 *
	 * @return string
	 */
	function get_page_head()
	{
		global $admin_url, $Settings;

		$r = '<nav class="navbar level1 navbar-inverse navbar-static-top">
			<div class="container-fluid">
				 <!-- Brand and toggle get grouped for better mobile display -->
				 <div class="navbar-header">
						<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#b2evo-top-navbar">
							 <span class="sr-only">Toggle navigation</span>
							 <span class="icon-bar"></span>
							 <span class="icon-bar"></span>
							 <span class="icon-bar"></span>
						</button>
						<a class="navbar-brand" href="'.$admin_url.'?ctrl=dashboard"'
								.( $Settings->get( 'site_color' ) != '' ? ' style="color:'.$Settings->get( 'site_color' ).'"' : '' ).'>'
							.$Settings->get( 'site_code' )
						.'</a>
				 </div>

				 <!-- Collect the nav links, forms, and other content for toggling -->
				 <div class="collapse navbar-collapse" id="b2evo-top-navbar">
						'.$this->get_html_menu().'
						<ul class="nav navbar-nav navbar-right">
							 <li>'.$this->page_manual_link.'</li>
						</ul>
				 </div><!-- /.navbar-collapse -->
			</div><!-- /.container-fluid -->
		</nav>';

		return $r;
	}


	/**
	 * Dsiplay the top of the HTML <body>...
	 *
	 * Typically includes title, menu, messages, etc.
	 *
	 * @param boolean Whether or not to display messages.
	 */
	function disp_body_top( $display_messages = true, $params = array() )
	{
		global $Messages;

		parent::disp_body_top( $display_messages );

		parent::disp_payload_begin( $params );

		if( $display_messages )
		{ // Display info & error messages:
			$Messages->display();
			// Clear the messages to avoid double displaying:
			$Messages->clear();
		}

		echo '<div class="container-fluid page-content">'."\n\t"
				.'<div class="row">'."\n\t\t"
			.'<div class="col-md-12">'."\n";
	}


	/**
	 * Display body bottom, debug info and close </html>
	 */
	function disp_global_footer()
	{
		echo "\n\t\t</div>"
				."\n\t</div>"
			."\n</div>";

		parent::disp_payload_end();

		parent::disp_global_footer();
	}


	/**
	 * Display the start of a payload block
	 *
	 * Note: it is possible to display several payload blocks on a single page.
	 *       The first block uses the "sub" template, the others "block".
	 *
	 * @see disp_payload_end()
	 */
	function disp_payload_begin( $params = array() )
	{
		// Nothing display here, because all already is printed in $this->disp_body_top()
	}


	/**
	 * Display the end of a payload block
	 *
	 * Note: it is possible to display several payload blocks on a single page.
	 *       The first block uses the "sub" template, the others "block".
	 * @see disp_payload_begin()
	 */
	function disp_payload_end()
	{
		// Nothing display here, because all already is printed in $this->disp_global_footer()
	}


	/**
	 * Get the footer text
	 *
	 * @return string
	 */
	function get_footer_contents()
	{
		global $app_footer_text, $copyright_text;

		return '<footer class="footer"><div class="container"><p class="text-muted text-center">'.$app_footer_text.' &ndash; '.$copyright_text."</p></div></footer>\n\n";
	}


	/**
	 * Get a template by name and depth.
	 *
	 * @param string The template name ('main', 'sub').
	 * @param integer Nesting level (start at 0)
	 * @param boolean TRUE to die on unknown template name
	 * @return array
	 */
	function get_template( $name, $depth = 0, $die_on_unknown = false )
	{
		switch( $name )
		{
			case 'main':
				// main level
				return array(
					'before' => '<ul class="nav navbar-nav">',
					'after' => '</ul>',
					'beforeEach' => '<li>',
					'afterEach' => '</li>',
					'beforeEachSel' => '<li class="active">',
					'afterEachSel' => ' <span class="sr-only">(current)</span></li>',
					'beforeEachSelWithSub' => '<li class="active">',
					'afterEachSelWithSub' => '</li>',
				);

			case 'sub':
				// a payload block with embedded submenu
				return array(
						'before' => '<div class="container-fluid level2">'."\n"
									.'<nav>'."\n"
								.'<ul class="nav nav-tabs">'."\n",
						'after' => '</ul>'."\n"
										.'</nav>'."\n"
									.'</div>'."\n",
						'empty' => '',
						'beforeEach'    => '<li role="presentation">',
						'afterEach'     => '</li>',
						'beforeEachSel' => '<li role="presentation" class="active">',
						'afterEachSel'  => '</li>',
						'beforeEachGrpLast'    => '<li role="presentation" class="grplast">',
						'afterEachGrpLast'     => '</li>',
						'beforeEachSelGrpLast' => '<li role="presentation" class="grplast active">',
						'afterEachSelGrpLast'  => '</li>',
						'end' => '', // used to end payload block that opened submenu
					);

			case 'menu3':
				// level 3 submenu:
				return array(
						'before' => '<div class="container-fluid level3">'."\n"
										.'<nav>'."\n"
									.'<ul class="nav nav-pills">'."\n",
						'after' => '</ul>'."\n"
									.'</nav>'."\n"
								.'</div>'."\n"
								.'<div class="container-fluid container-global-icons"><div class="pull-right">$global_icons$</div></div>'."\n",
						'empty' => '<div class="container-fluid"><div class="pull-right">$global_icons$</div></div>'."\n",
						'beforeEach' => '<li role="presentation">',
						'afterEach'  => '</li>',
						'beforeEachSel' => '<li role="presentation" class="active">',
						'afterEachSel' => '</li>',
					);

			case 'CollectionList':
				// Template for a list of Collections (Blogs)
				return array(
						'before' => '<div class="container-fluid coll-selector"><nav>$button_list_all$<div class="btn-group">',
						'after' => '</div>$button_add_blog$$collection_groups$</nav></div>',
						'select_start' => '<div class="btn-group" role="group">',
						'select_end' => '</div>',
						'buttons_start' => '',
						'buttons_end' => '',
						'beforeEach' => '',
						'afterEach' => '',
						'beforeEachSel' => '',
						'afterEachSel' => '',
					);

			case 'Results':
			case 'compact_results':
				// Results list:
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
					               .'<table class="table table-striped table-bordered table-hover table-condensed $list_class$" cellspacing="0" $list_attrib$>'."\n",
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
						.'</ul></div><div class="center page_size_selector">$page_size$</div>'
					                  /* T_('Page $scroll_list$ out of $total_pages$   $prev$ | $next$<br />'. */
					                  /* '<strong>$total_pages$ Pages</strong> : $prev$ $list$ $next$' */
					                  /* .' <br />$first$  $list_prev$  $list$  $list_next$  $last$ :: $prev$ | $next$') */,
					'footer_text_single' => '<div class="center page_size_selector">$page_size$</div>',
					'footer_text_no_limit' => '', // Text if theres no LIMIT and therefor only one page anyway
						'page_current_template' => '<span>$page_num$</span>',
						'page_item_before' => '<li>',
						'page_item_after' => '</li>',
						'page_item_current_before' => '<li class="active">',
						'page_item_current_after' => '</li>',
						'prev_text' => T_('Previous'),
						'next_text' => T_('Next'),
						'no_prev_text' => '',
						'no_next_text' => '',
						'list_prev_text' => '...',
						'list_next_text' => '...',
						'list_span' => 11,
						'scroll_list_range' => 5,
					'footer_end' => "</div>\n\n",
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
					'buttonsstart'   => '<div class="panel-footer control-buttons"><div class="col-sm-offset-3 col-sm-9">',
					'buttonsend'     => '</div></div>'."\n\n",
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
					'checkbox_basic_start'   => '<div class="checkbox"><label>',
					'checkbox_basic_end'     => "</label></div>\n",
					// - radio
					'inputclass_radio'       => '',
					'radio_label_format'     => '$radio_option_label$',
					'radio_newline_start'    => '',
					'radio_newline_end'      => "\n",
					'radio_oneline_start'    => '',
					'radio_oneline_end'      => "\n",
				);

			case 'compact_form':
				// Default Form settings:
				return array(
					'layout'         => 'fieldset',
					'formclass'      => 'form-horizontal',
					'formstart'      => '<div class="panel panel-default $formstart_class$">'."\n",
					'formend'        => '</div></div>',
					'title_fmt'      => '<div class="panel-heading"><span class="pull-right panel_heading_action_icons">$global_icons$</span><h3 class="panel-title">$title$</h3></div><div class="panel-body $class$">'."\n",
					'no_title_fmt'   => '<div class="panel-body $class$"><span class="pull-right">$global_icons$</span><div class="clear"></div>'."\n",
					'no_title_no_icons_fmt' => '<div class="panel-body $class$">'."\n",
					'global_icons_class' => 'btn btn-default btn-sm',
					'fieldset_begin' => '<div class="fieldset_wrapper $class$" id="fieldset_wrapper_$id$"><fieldset $fieldset_attribs$><div class="panel panel-default">'."\n"
															.'<legend class="panel-heading" $title_attribs$><h3 class="panel-title">$fieldset_title$</h3></legend><div class="panel-body $class$">'."\n",
					'fieldset_end'   => '</div></div></fieldset></div>'."\n",
					'fieldstart'     => '<div class="form-group" $ID$>'."\n",
					'fieldend'       => "</div>\n\n",
					'labelclass'     => 'control-label col-sm-3',
					'labelstart'     => '',
					'labelend'       => "\n",
					'labelempty'     => '<label class="control-label col-sm-3"></label>',
					'inputstart'     => '<div class="controls col-sm-9">',
					'inputend'       => "</div>\n",
					'infostart'      => '<div class="controls col-sm-9"><div class="form-control-static">',
					'infoend'        => "</div></div>\n",
					'buttonsstart'   => '<div class="panel-footer control-buttons"><div class="col-sm-offset-3 col-sm-9">',
					'buttonsend'     => '</div></div>'."\n\n",
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
					'checkbox_basic_start'   => '<div class="checkbox"><label>',
					'checkbox_basic_end'     => "</label></div>\n",
					// - radio
					'fieldstart_radio'       => '<div class="form-group radio-group" $ID$>'."\n",
					'fieldend_radio'         => "</div>\n\n",
					'inputclass_radio'       => '',
					'radio_label_format'     => '$radio_option_label$',
					'radio_newline_start'    => '<div class="radio $radio_option_class$"><label>',
					'radio_newline_end'      => "</label></div>\n",
					'radio_oneline_start'    => '<label class="radio-inline $radio_option_class$">',
					'radio_oneline_end'      => "</label>\n",
				);

			case 'Form':
				// Default Form settings:
				return array(
					'layout'         => 'fieldset',
					'formclass'      => 'form-horizontal',
					'formstart'      => '',
					'formend'        => '',
					'title_fmt'      => '<span class="global_icons">$global_icons$</span><h2 class="page-title">$title$</h2>'."\n",
					'no_title_fmt'   => '<span class="global_icons no_title">$global_icons$</span><div class="clear"></div>'."\n",
					'fieldset_title' => '',
					'fieldset_begin' => '<div class="fieldset_wrapper $class$" id="fieldset_wrapper_$id$"><fieldset $fieldset_attribs$><div class="panel panel-default">'."\n"
															.'<legend class="panel-heading" $title_attribs$><h3 class="panel-title">$fieldset_title$</h3></legend><div class="panel-body $class$">'."\n",
					'fieldset_end'   => '</div></div></fieldset></div>'."\n",
					'tab_pane_open' => '<div id="$id$" class="tab-pane fade $class$" $tab_pane_attribs$ ><div class="pull-left">$pull_left$</div><div class="pull-right">$pull_right$</div><div class="clearfix"></div>'."\n",
					'tab_pane_close'   => '</div>'."\n",
					'fieldstart'     => '<div class="form-group" $ID$>'."\n",
					'fieldend'       => "</div>\n\n",
					'labelclass'     => 'control-label col-sm-3',
					'labelstart'     => '',
					'labelend'       => "\n",
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
					'fieldstart_checkbox'    => '<div class="form-group checkbox-group" $ID$>'."\n",
					'fieldend_checkbox'      => "</div>\n\n",
					'inputclass_checkbox'    => '',
					'inputstart_checkbox'    => '<div class="controls col-sm-9"><div class="checkbox"><label>',
					'inputend_checkbox'      => "</label></div></div>\n",
					'checkbox_newline_start' => '<div class="checkbox">',
					'checkbox_newline_end'   => "</div>\n",
					'checkbox_basic_start'   => '<div class="checkbox"><label>',
					'checkbox_basic_end'     => "</label></div>\n",
					// - radio
					'fieldstart_radio'       => '<div class="form-group radio-group" $ID$>'."\n",
					'fieldend_radio'         => "</div>\n\n",
					'inputclass_radio'       => '',
					'radio_label_format'     => '$radio_option_label$',
					'radio_newline_start'    => '<div class="radio $radio_option_class$"><label>',
					'radio_newline_end'      => "</label></div>\n",
					'radio_oneline_start'    => '<label class="radio-inline $radio_option_class$">',
					'radio_oneline_end'      => "</label>\n",
				);

			case 'accordion_form':
				return array_merge( $this->get_template( 'Form' ), array(
						'layout'         => 'accordion',
						'group_begin'    => '<div class="panel-group accordion-caret $group_class$" role="tablist" aria-multiselectable="true" $group_attribs$>',
						'group_end'      => '</div>',
						'fieldset_title' => '<a class="accordion-toggler collapsed" data-toggle="collapse" data-parent="#$group_ID$" href="#$group_item_ID$" aria-expanded="false" aria-controls="$group_item_ID$">$fieldset_title$</a>',
						'fieldset_begin' =>
							'<div class="panel panel-default $class$" id="fieldset_wrapper_$id$" $fieldset_attribs$>'."\n"
								.'<div class="panel-heading" $title_attribs$>'
									.'<h3 class="panel-title">$fieldset_title$</h3>'
								.'</div>'."\n"
								.'<div id="$group_item_id$" class="panel-collapse collapse">'
									.'<div class="panel-body $class$">'."\n",
						'fieldset_end'   =>
									 '</div>' // End of <div class="panel-body...>
								.'</div>' // End of <div id="$group_item_id$...>
							.'</div>'."\n", // End of <div class="panel panel-default...>
					) );

			case 'accordion_table':
				return array_merge( $this->get_template( 'Results' ), array(
						'head_title' => '<div class="panel-heading fieldset_title"><span class="pull-right panel_heading_action_icons">$global_icons$</span><h3 class="panel-title"><a class="accordion-toggler collapsed" data-toggle="collapse" data-parent="#$group_id$" href="#$group_item_id$" aria-expanded="false" aria-controls="$group_item_id$">$title$</a></h3></div>'."\n",
					) );

			case 'linespan_form':
				// Linespan form:
				return array(
					'layout'         => 'linespan',
					'formclass'      => 'form-horizontal',
					'formstart'      => '',
					'formend'        => '',
					'title_fmt'      => '<span class="pull-right">$global_icons$</span><h2 class="page-title">$title$</h2>'."\n",
					'no_title_fmt'   => '<span class="pull-right">$global_icons$</span>'."\n",
					'fieldset_begin' => '<div class="fieldset_wrapper $class$" id="fieldset_wrapper_$id$"><fieldset $fieldset_attribs$><div class="panel panel-default">'."\n"
															.'<legend class="panel-heading" $title_attribs$><h3 class="panel-title">$fieldset_title$</h3></legend><div class="panel-body $class$">'."\n",
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
					'radio_newline_start'    => '<div class="radio $radio_option_class$"><label>',
					'radio_newline_end'      => "</label></div>\n",
					'radio_oneline_start'    => '<label class="radio-inline $radio_option_class$">',
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

			case 'file_browser':
				return array(
					'block_start' => '<div class="panel panel-default file_browser"><div class="panel-heading"><span class="pull-right panel_heading_action_icons">$global_icons$</span><h3 class="panel-title">$title$</h3></div><div class="panel-body">',
					'block_end'   => '</div></div>',
					'global_icons_class' => 'btn btn-default btn-sm',
				);

			case 'block_item':
			case 'dash_item':
				return array(
					'block_start' => '<div class="panel panel-default evo_content_block"><div class="panel-heading"><span class="pull-right panel_heading_action_icons">$global_icons$</span><h3 class="panel-title">$title$</h3></div><div class="panel-body">',
					'block_end'   => '</div></div>',
					'global_icons_class' => 'btn btn-default btn-sm',
				);

			case 'side_item':
				return array(
					'block_start' => '<div class="panel panel-default"><div class="panel-heading"><span class="pull-right panel_heading_action_icons">$global_icons$</span><h3 class="panel-title">$title$</h3></div><div class="panel-body">',
					'block_end'   => '</div></div>',
				);

			case 'user_navigation':
				// The Prev/Next links of users
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
				// Button classes
				return array(
					'button'       => 'btn btn-default',
					'button_red'   => 'btn-danger',
					'button_green' => 'btn-success',
					'text'         => 'btn btn-default',
					'text_primary' => 'btn btn-primary',
					'text_success' => 'btn btn-success',
					'text_danger'  => 'btn btn-danger',
					'text_warning' => 'btn btn-warning',
					'group'        => 'btn-group',
					'small_text'   => 'btn btn-default btn-xs',
					'small_text_primary' => 'btn btn-primary btn-xs',
					'small_text_success' => 'btn btn-success btn-xs',
					'small_text_danger'  => 'btn btn-danger btn-xs',
					'small_text_warning' => 'btn btn-warning btn-xs',
				);

			case 'table_browse':
				// A browse table for items and comments
				return array(
					'table_start'     => '<div class="row">',
					'full_col_start'  => '<div class="col-md-12">',
					'left_col_start'  => '<div class="col-md-9">',
					'left_col_end'    => '</div>',
					'right_col_start' => '<div class="col-md-3 form-inline">',
					'right_col_end'   => '</div>',
					'table_end'       => '</div>',
				);

			case 'tooltip_plugin':
				// Plugin name for tooltips: 'bubbletip' or 'popover'
				return 'popover';

			case 'autocomplete_plugin':
				// Plugin name to autocomplete the fields: 'hintbox', 'typeahead'
				return 'typeahead';

			case 'modal_window_js_func':
				// JavaScript function to initialize Modal windows, @see echo_user_ajaxwindow_js()
				return 'echo_modalwindow_js_bootstrap';

			case 'plugin_template':
				// Template for plugins
				return array(
						'toolbar_before'       => '<div class="btn-toolbar plugin-toolbar $toolbar_class$" data-plugin-toolbar="$toolbar_class$" role="toolbar">',
						'toolbar_after'        => '</div>',
						'toolbar_title_before' => '<div class="btn-toolbar-title">',
						'toolbar_title_after'  => '</div>',
						'toolbar_group_before' => '<div class="btn-group btn-group-xs" role="group">',
						'toolbar_group_after'  => '</div>',
						'toolbar_button_class' => 'btn btn-default',
					);

			case 'pagination':
				// Pagination, @see echo_comment_pages()
				return array(
						'list_start' => '<div class="center"><ul class="pagination">',
						'list_end'   => '</ul></div>',
						'prev_text'  => T_('Previous'),
						'next_text'  => T_('Next'),
						'pages_text' => '',
						'page_before'         => '<li>',
						'page_after'          => '</li>',
						'page_current_before' => '<li class="active"><span>',
						'page_current_after'  => '</span></li>',
					);

			case 'blog_base.css':
				// File name of blog_base.css that are used on several back-office pages
				return 'bootstrap-blog_base.css';

			case 'colorbox_css_file':
				// CSS file of colorbox, @see require_js_helper( 'colorbox' )
				return 'colorbox-bootstrap.min.css';

			default:
				// Delegate to parent class:
				return parent::get_template( $name, $depth, $die_on_unknown );
		}
	}

	/**
	 * Get colors for page elements that can't be controlled by CSS (charts)
	 */
	function get_color( $what )
	{
		switch( $what )
		{
			case 'payload_background':
				return 'fbfbfb';
				break;
		}
		debug_die( 'unknown color' );
	}


	/**
	 * Returns list of buttons for available Collections (aka Blogs) to work on.
	 *
	 * @param string Title
	 * @return string HTML
	 */
	function get_bloglist_buttons( $title = '' )
	{
		global $blog, $admin_url;

		$max_buttons = 7;

		if( empty( $this->coll_list_permname ) )
		{	// We have not requested a list of blogs to be displayed
			return;
		}

		// Prepare url params:
		$url_params = '?';
		foreach( $this->coll_list_url_params as $name => $value )
		{
			$url_params .= $name.'='.$value.'&amp;';
		}

		$template = $this->get_template( 'CollectionList' );

		$BlogCache = & get_BlogCache();

		$blog_array = $BlogCache->load_user_blogs( $this->coll_list_permname, $this->coll_list_permlevel );

		$buttons = '';
		$select_options = '';
		$not_favorite_blogs = false;
		foreach( $blog_array as $l_blog_ID )
		{ // Loop through all blogs that match the requested permission:

			$l_Blog = & $BlogCache->get_by_ID( $l_blog_ID );

			if( $l_Blog->favorite() || $l_blog_ID == $blog )
			{ // If blog is favorute OR current blog, Add blog as a button:
				$buttons .= $template[ $l_blog_ID == $blog ? 'beforeEachSel' : 'beforeEach' ];

				$buttons .= '<a href="'.format_to_output( $url_params.'blog='.$l_blog_ID, 'htmlattr' )
							.'" class="btn btn-default'.( $l_blog_ID == $blog ? ' active' : '' ).'"';

				if( !is_null($this->coll_list_onclick) )
				{	// We want to include an onclick attribute:
					$buttons .= ' onclick="'.sprintf( $this->coll_list_onclick, $l_blog_ID ).'"';
				}

				$buttons .= '>'.$l_Blog->dget( 'shortname', 'htmlbody' ).'</a> ';

				if( $l_blog_ID == $blog )
				{
					$buttons .= $template['afterEachSel'];
				}
				else
				{
					$buttons .= $template['afterEach'];
				}
			}

			if( !$l_Blog->favorite() )
			{ // If blog is not favorute, Add it into the select list:
				$not_favorite_blogs = true;
				$select_options .= '<li>';
				if( $l_blog_ID == $blog )
				{
					//$select_options .= ' selected="selected"';
				}
				$select_options .= '<a href="'.format_to_output( $url_params.'blog='.$l_blog_ID, 'htmlattr' ).'">'
					.$l_Blog->dget( 'shortname', 'htmlbody' ).'</a></li>';
			}
		}

		$r = $template['before'];

		$r .= $title;

		if( $this->coll_list_disp_sections )
		{	// Check if filter by section is used currently:
			$sec_ID = param( 'sec_ID', 'integer', 0 );
			if( ! is_logged_in() || ! ( check_user_perm( 'stats', 'view' ) || check_user_perm( 'section', 'view', false, $sec_ID ) ) )
			{
				$sec_ID = 0;
				set_param( 'sec_ID', 0 );
			}
		}

		if( !empty( $this->coll_list_all_title ) )
		{ // We want to add an "all" button
			$r .= $template[ empty( $sec_ID ) && $blog == 0 ? 'beforeEachSel' : 'beforeEach' ];
			$r .= '<a href="'.format_to_output( $this->coll_list_all_url, 'htmlattr' )
						.'" class="btn btn-default'.( empty( $sec_ID ) && $blog == 0 ? ' active' : '' ).'">'
						.format_to_output( $this->coll_list_all_title, 'htmlbody' ).'</a> ';
			$r .= $template[ empty( $sec_ID ) && $blog == 0 ? 'afterEachSel' : 'afterEach' ];
			// Don't display default button if custom is defined:
			$button_list_all = '';
		}
		else
		{	// Default button to list all collections:
			$button_list_all = '<a href="'.$admin_url.'?ctrl=collections" class="btn btn-default'.( $blog == 0 ? ' active' : '' ).'">'.T_('List').'</a> ';
		}

		$r .= $template['buttons_start'];
		$r .= $buttons;
		$r .= $template['buttons_end'];


		if( $not_favorite_blogs )
		{ // Display select list with not favorite blogs
			$r .= $template['select_start']
				.'<a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown">'.T_('Other')
				.'<span class="caret"></span></a>'
				.'<ul class="dropdown-menu">'
				.$select_options
				.'</ul>'
				.$template['select_end'];
		}

		// Button to add new collection:
		if( $this->coll_list_disp_add && check_user_perm( 'blogs', 'create' ) )
		{	// Display a button to add new collection if it is requested and current user has a permission
			$button_add_blog = '<a href="'.$admin_url.'?ctrl=collections&amp;action=new" class="btn btn-default" title="'.format_to_output( T_('New Collection'), 'htmlattr' ).'"><span class="fa fa-plus"></span></a>';
		}
		else
		{	// No request or permission to add new collection:
			$button_add_blog = '';
		}

		// Sections:
		if( $this->coll_list_disp_sections )
		{
			$collection_groups = '';

			$SectionCache = & get_SectionCache();
			$SectionCache->load_available();

			foreach( $SectionCache->cache as $Section )
			{	// Loop through all sections that match the requested permission:
				$collection_groups .= ( $Section->ID == $sec_ID ) ? $template['beforeEachSel'] : $template['beforeEach'];

				$collection_groups .= '<a href="'.format_to_output( $url_params.'blog=0&amp;sec_ID='.$Section->ID, 'htmlattr' )
					.'" class="btn btn-default'.( $Section->ID == $sec_ID ? ' active' : '' ).'">'
						.$Section->dget( 'name', 'htmlbody' )
					.'</a> ';

				$collection_groups .= ( $Section->ID == $sec_ID ) ? $template['afterEachSel'] : $template['afterEach'];
			}

			$collection_groups = empty( $collection_groups ) ? '' : '<div class="btn-group">'.$collection_groups.'</div>';
		}
		else
		{
			$collection_groups = '';
		}

		$r .= $template['after'];

		return str_replace( array( '$button_list_all$', '$button_add_blog$', '$collection_groups$' ),
			array( $button_list_all, $button_add_blog, $collection_groups ), $r );
	}


	/**
	 * Display tabs for customizer mode in left iframe
	 *
	 * @param array Params
	 */
	function display_customizer_tabs( $params = array() )
	{
		global $Blog, $Settings;

		$params = array_merge( array(
				'action_links'   => '',
				'active_submenu' => '',
				'path'           => NULL, // can be string like 'site', or array like array( 'coll', 'widgets' )
			), $params );

		if( empty( $Blog ) )
		{	// Get last working collection:
			$BlogCache = & get_BlogCache();
			$tab_Blog = & $BlogCache->get_by_ID( get_working_blog(), false, false );
		}
		else
		{	// Use current collection:
			$tab_Blog = $Blog;
			set_working_blog( $tab_Blog->ID );
		}

		$tabs = array();

		// Site:
		if( $Settings->get( 'site_skins_enabled' ) &&
				check_user_perm( 'options', 'edit' ) )
		{	// If current User can edit site skin settings:
			$tabs['site'] = array(
				'text' => T_('Site'),
				'href' => get_admin_url( 'ctrl=customize&amp;view=site_skin' ),
			);
		}
		// Collection:
		if( check_user_perm( 'blog_properties', 'edit', false, $tab_Blog->ID ) )
		{	// If current User can edit current collection settings:
			$tabs['coll'] = array(
				'text' => $tab_Blog->get( 'shortname' ),
				'href' => get_admin_url( 'ctrl=customize&amp;view=coll_skin&amp;blog='.$tab_Blog->ID ),
				'entries' => array(
					'skin' => array(
						'text' => T_('Skin'),
						'href' => get_admin_url( 'ctrl=customize&amp;view=coll_skin&amp;blog='.$tab_Blog->ID ),
					),
					'widgets' => array(
						'text' => T_('Widgets'),
						'href' => get_admin_url( 'ctrl=customize&amp;view=coll_widgets&amp;blog='.$tab_Blog->ID ),
					),
				)
			);
		}
		// Other:
		$BlogCache = & get_BlogCache();
		$BlogCache->clear();
		$BlogCache->load_user_blogs( 'blog_properties', 'edit' );
		if( count( $BlogCache->cache ) > 1 )
		{	// If current User can edit settings of at least two collections:
			$tabs['other'] = array(
				'text' => T_('Other'),
				'href' => get_admin_url( 'ctrl=customize&amp;view=other&amp;blog='.$tab_Blog->ID ),
			);
		}

		// Display tabs and menu entries:
		echo '<div class="evo_customizer__tabs">';

		if( count( $tabs ) )
		{	// Display tabs if they are allowed for current user by permissions:
			$path = ( empty( $params['path'] ) || is_string( $params['path'] ) ) ? array( $params['path'] ) : $params['path'];

			$active_tab_entries = NULL;
			echo '<ul class="nav nav-tabs">';
			foreach( $tabs as $tab_key => $tab )
			{
				$is_active_tab = ( isset( $path[0] ) && $tab_key == $path[0] );
				if( $is_active_tab && ! empty( $tab['entries'] ) )
				{	// Store entries of active tab to print out it below:
					$active_tab_entries = $tab['entries'];
				}
				echo '<li'.( $is_active_tab ? ' class="active"' : '' ).'>'
						.'<a href="'.$tab['href'].'">'.$tab['text'].'</a>'
					.'</li>';
			}
			echo '</ul>';

			if( $active_tab_entries !== NULL )
			{	// Display sub menu entries for currently active tab:
				echo '<div class="evo_customizer__menus">';
				echo '<nav><ul class="nav nav-pills">';
				foreach( $active_tab_entries as $entry_key => $entry )
				{
					echo '<li'.( ( isset( $path[1] ) && $entry_key == $path[1] ) ? ' class="active"' : '' ).'>'
							.'<a href="'.$entry['href'].'">'.$entry['text'].'</a>'
						.'</li>';
				}
				echo '</ul></nav>';
				echo '</div>';
			}
		}

		if( ! empty( $params['action_links'] ) )
		{	// Display additional action links:
			echo '<div class="evo_customizer__actions">'.$params['action_links'].'</div>';
		}

		// Buttons to collapse and hide left customizer panel:
		echo '<div class="evo_customizer__tab_buttons btn-group">'
				.'<button id="evo_customizer__collapser" class="btn btn-sm btn-default"><span class="fa fa-backward"></span></button>'
				.'<button id="evo_customizer__closer" class="btn btn-sm btn-default"><span class="fa fa-close"></span></a>'
			.'</div>';

		echo '</div>';
	}
}

?>