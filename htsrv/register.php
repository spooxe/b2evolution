<?php
/**
 * Register a new user.
 *
 * This file is part of the evoCore framework - {@link http://evocore.net/}
 * See also {@link https://github.com/b2evolution/b2evolution}.
 *
 * @license GNU GPL v2 - {@link http://b2evolution.net/about/gnu-gpl-license}
 *
 * @copyright (c)2003-2020 by Francois Planque - {@link http://fplanque.com/}
 * Parts of this file are copyright (c)2004-2006 by Daniel HAHLER - {@link http://thequod.de/contact}.
 *
 * @package htsrv
 */

/**
 * Includes:
 */
require_once dirname(__FILE__).'/../conf/_config.php';

require_once $inc_path.'_main.inc.php';

// Check and redirect if current URL must be used as https instead of http:
check_https_url( 'login' );

// Login is not required on the register page:
$login_required = false;

global $baseurl;

if( is_logged_in() )
{	// If a user is already logged in don't allow to register:
	param( 'forward_to', 'url', $baseurl );
	header_redirect( $forward_to );
}

// Save trigger page
$session_registration_trigger_url = $Session->get( 'registration_trigger_url' );
if( empty( $session_registration_trigger_url ) && isset( $_SERVER['HTTP_REFERER'] ) )
{	// Trigger page still is not defined
	$session_registration_trigger_url = $_SERVER['HTTP_REFERER'];
	$Session->set( 'registration_trigger_url', $session_registration_trigger_url );
}

// Get required fields from registration master template:
$required_fields = get_registration_template_required_fields();

// Check if login is required
$registration_require_login = in_array( 'login', $required_fields );
// Check if email is required
$registration_require_email = in_array( 'email', $required_fields );
// Check if password is required
$registration_require_password = in_array( 'password', $required_fields );
// Check if firstname is required
$registration_require_firstname = in_array( 'firstname', $required_fields );
// Check if firstname is required
$registration_require_lastname = in_array( 'lastname', $required_fields );
// Check if nickname is required
$registration_require_nickname = false;
// Check if country is required
$registration_require_country = in_array( 'country', $required_fields );
// Check if gender is required
$registration_require_gender = in_array( 'gender', $required_fields );
// Check if registration ask for locale
$registration_require_locale = in_array( 'locale', $required_fields );

// Do not set email:
$ignore_email = false;
// Do not set firstname:
$ignore_firstname = false;
// Do not set lastname:
$ignore_lastname = false;
// Do not set nickname:
$ignore_nickname = false;
// Do not set country:
$ignore_country = false;
// Do not set gender:
$ignore_gender = false;
// Do not set locale:
$ignore_locale = false;

$login = param( $dummy_fields[ 'login' ], 'string', '' );
$email = utf8_strtolower( param( $dummy_fields[ 'email' ], 'string', '' ) );
param( 'action', 'string', '' );
param( 'firstname', 'string', '' );
param( 'lastname', 'string', '' );
param( 'nickname', 'string', '' );
param( 'country', 'integer', '' );
param( 'gender', 'string', '' );
param( 'locale', 'string', '' );
param( 'source', 'string', '' );
param( 'redirect_to', 'url', '' ); // do not default to $admin_url; "empty" gets handled better in the end (uses $blogurl, if no admin perms).
param( 'inskin', 'boolean', false, true );

global $Collection, $Blog;
if( $inskin && empty( $Blog ) )
{
	param( 'blog', 'integer', 0 );

	if( isset( $blog) && $blog > 0 )
	{
		$BlogCache = & get_BlogCache();
		$Collection = $Blog = $BlogCache->get_by_ID( $blog, false, false );
	}
}

if( $inskin && !empty( $Blog ) )
{	// in-skin register, activate current Blog locale
	locale_activate( $Blog->get('locale') );
}

// Check invitation code if it exists and registration is enabled
$invitation_code_status = check_invitation_code();

if( $invitation_code_status == 'deny' )
{	// Registration is disabled or system is locked:
	$action = 'disabled';
}

if( $register_user = $Session->get('core.register_user') )
{	// Get an user data from predefined session (after adding of a comment)
	$login = preg_replace( '/[^a-z0-9_\-\. ]/i', '', $register_user['name'] );
	$login = str_replace( ' ', '_', $login );
	$login = utf8_substr( $login, 0, 20 );
	$email = $register_user['email'];

	$Session->delete( 'core.register_user' );
}

switch( $action )
{
	case 'register':
	case 'quick_register':
	case 'social_register':
		// Check that this action request is not a CSRF hacked request:
		$Session->assert_received_crumb( 'regform' );

		// Use this boolean var to know when registration using social network credential is used:
		$is_social = ( $action == 'social_register' ) && is_pro();
		if( $action == 'social_register' )
		{	// Consider social registration as quick registration:
			$action = 'quick_register';
		}

		// Use this boolean var to know when quick registration is used
		$is_quick = ( $action == 'quick_register' );
		$is_inline = param( 'inline', 'integer', 0 ) == 1;

		// Stop a request from the blocked IP addresses or Domains
		antispam_block_request();

		// We will need the following parameter for the session data that will be set later:
		param( 'widget', 'integer', 0 );

		if( $is_quick || $is_inline )
		{	// Check if we can use a quick registration now:
			if( $Settings->get( 'newusers_canregister' ) != 'yes' || ! $Settings->get( 'quick_registration' ) )
			{	// Display error message when quick registration is disabled
				$Messages->add( T_('Quick registration is currently disabled on this system.'), 'error' );
				if( $is_social )
				{
					$Session->delete( 'social.access_credentials' );
					$Session->delete( 'social.user_profile' );
				}
				break;
			}

			if( $is_quick && ( empty( $Blog ) || ( empty( $widget ) && ! $is_inline ) ) && ! $is_social )
			{	// Don't use a quick registration if the request did not come from a blog page (except for social registration):
				debug_die( 'Quick registration is currently disabled on this system.' );
				break;
			}

			if( $is_social )
			{	// Set params for registration using social network credentials:
				param( 'provider', 'string', true );
				$social_params = $Session->get( 'social.registration_params' );
				$Session->delete( 'social.registration_params' );

				$registration_require_email     = isset( $social_params['require_email'] ) ? ( $social_params['require_email'] == 'required' ) : true;
				$registration_require_login     = false;
				$registration_require_password  = false;
				$registration_require_firstname = ( isset( $social_params['require_firstname'] ) && ( $social_params['require_firstname'] == 'required' ) );
				$registration_require_lastname  = ( isset( $social_params['require_lastname'] ) && ( $social_params['require_lastname'] == 'required' ) );
				$registration_require_nickname  = ( isset( $social_params['require_nickname'] ) && ( $social_params['require_nickname'] == 'required' ) );
				$registration_require_country   = ( isset( $social_params['require_country'] ) && ( $social_params['require_country'] == 'required' ) );
				$registration_require_gender    = ( isset( $social_params['require_gender'] ) && ( $social_params['require_gender'] == 'required' ) );
				$registration_require_locale    = ( isset( $oscial_params['require_locale'] ) && ( $social_params['require_locale'] == 'required' ) );

				// After social registration setting:
				$after_social_registration  = ( isset( $social_params['after_social_registration'] ) ? $social_params['after_social_registration'] : 'regform' );

				$ignore_firstname = ( isset( $social_params['require_firstname'] ) && ( $social_params['require_firstname'] == 'ignore' ) );
				$ignore_lastname  = ( isset( $social_params['require_lastname'] ) && ( $social_params['require_lastname'] == 'ignore' ) );
				$ignore_nickname  = ( isset( $social_params['require_nickname'] ) && ( $social_params['require_nickname'] == 'ignore' ) );
				$ignore_country   = ( isset( $social_params['require_country'] ) && ( $social_params['require_country'] == 'ignore' ) );
				$ignore_gender    = ( isset( $social_params['require_gender'] ) && ( $social_params['require_gender'] == 'ignore' ) );
				$ignore_email     = ( isset( $social_params['require_email'] ) && ( $social_params['require_email'] == 'ignore' ) );
				$ignore_locale    = ( isset( $social_params['require_locale'] ) && ( $social_params['require_locale'] == 'ignore' ) );

				// Set ignored fields to NULL so we wouldn't need to check them:
				if( $ignore_firstname )
				{
					$firstname = NULL;
				}
				if( $ignore_lastname )
				{
					$lastname = NULL;
				}
				if( $ignore_nickname )
				{
					$nickname = NULL;
				}
				if( $ignore_country )
				{
					$country = NULL;
				}
				if( $ignore_gender )
				{
					$gender = NULL;
				}
				if( $ignore_locale )
				{
					$locale = NULL;
				}
				if( $ignore_email )
				{	// Email cannot be NULL:
					$email = '';
				}
			}
			elseif( empty( $widget ) && $is_inline )
			{	// Set params for a request from inline tag "[emailcapture:]" :
				$registration_require_email     = true;
				$registration_require_login     = false;
				$registration_require_password  = false;
				$registration_require_firstname = ( param( 'ask_firstname', 'string', true ) == 'required' );
				$registration_require_lastname  = ( param( 'ask_lastname', 'string', true ) == 'required' );
				$registration_require_nickname  = false;
				$registration_require_country   = ( param( 'ask_country', 'string', true ) == 'required' );
				$registration_require_gender    = false;
				$registration_require_locale    = false;

				$source                  = param( 'source', 'string', true );
				$user_tags               = param( 'usertags', 'string', NULL );
				$auto_subscribe_posts    = param( 'subscribe_post', 'integer', true );
				$auto_subscribe_comments = param( 'subscribe_comment', 'integer', true );
				$newsletters             = param( 'newsletters', 'string', true );
				$newsletters             = explode( ',', $newsletters );
				$widget_newsletters      = array();
				foreach( $newsletters as $loop_newsletter_ID )
				{
					$widget_newsletters[$loop_newsletter_ID] = 1;
				}
				$widget_redirect_to      = param( 'redirect_to', 'string', true );

				// Use current collection to subscribe:
				$subscribe_coll_ID = $Blog->ID;
			}
			elseif( ! empty( $widget ) && ! $is_inline )
			{	// Set params for a request from widget quick registration:
				$WidgetCache = & get_WidgetCache();
				$user_register_quick_Widget = & $WidgetCache->get_by_ID( $widget, false, false );
				if( ! $user_register_quick_Widget ||
						$user_register_quick_Widget->code != 'user_register_quick' ||
						( $is_quick && $user_register_quick_Widget->get( 'coll_ID' ) !== NULL && $user_register_quick_Widget->get( 'coll_ID' ) != $Blog->ID ) )
				{ // Wrong or hacked request!
					debug_die( 'Quick registration is currently disabled on this system.' );
					break;
				}

				// Initialize the widget settings:
				$user_register_quick_Widget->init_display( array() );

				// Get params from widget settings:
				$registration_require_email     = true;
				$registration_require_login     = false;
				$registration_require_password  = false;
				$registration_require_firstname = ( $user_register_quick_Widget->disp_params['ask_firstname'] == 'required' );
				$registration_require_lastname  = ( $user_register_quick_Widget->disp_params['ask_lastname'] == 'required' );
				$registration_require_nickname  = false;
				$registration_require_country   = ( $user_register_quick_Widget->disp_params['ask_country'] == 'required' );
				$registration_require_gender    = false;
				$registration_require_locale    = false;

				$source                  = $user_register_quick_Widget->disp_params['source'];
				$auto_subscribe_posts    = $user_register_quick_Widget->disp_params['subscribe']['post'];
				$auto_subscribe_comments = $user_register_quick_Widget->disp_params['subscribe']['comment'];
				$widget_newsletters      = $user_register_quick_Widget->disp_params['newsletters'];
				$user_tags               = $user_register_quick_Widget->disp_params['usertags'];
				$widget_redirect_to      = trim( $user_register_quick_Widget->disp_params['redirect_to'] );

				// Use collection of the widget to subscribe:
				$subscribe_coll_ID = $user_register_quick_Widget->get( 'coll_ID' );
			}
		}

		// Check email:
		// Stop a request from the blocked email address or its domain:
		if( $registration_require_email || !empty( $email ) )
		{
			param_check_new_user_email( $dummy_fields['email'], $email );
			antispam_block_by_email( $email );
		}

		if( ! $is_quick )
		{
			/*
			 * Do the registration:
			 */
			$pass1 = param( $dummy_fields['pass1'], 'string', '' );
			$pass2 = param( $dummy_fields['pass2'], 'string', '' );

			// Remove the invalid chars from password vars
			$pass1 = preg_replace( '/[<>&]/', '', $pass1 );
			$pass2 = preg_replace( '/[<>&]/', '', $pass2 );

			// Call plugin event to allow catching input in general and validating own things from DisplayRegisterFormFieldset event
			$Plugins->trigger_event( 'RegisterFormSent', array(
					'login'     => & $login,
					'email'     => & $email,
					'country'   => & $country,
					'firstname' => & $firstname,
					'gender'    => & $gender,
					'locale'    => & $locale,
					'pass1'     => & $pass1,
					'pass2'     => & $pass2,
				) );

			// Validate first enabled captcha plugin:
			$Plugins->trigger_event_first_return( 'ValidateCaptcha', array( 'form_type' => 'register' ) );
		}

		// Set params to check:
		$paramsList = array();
		if( $registration_require_email || !empty( $email ) )
		{
			$paramsList['email'] = $email;
		}

		if( $registration_require_login || !empty( $login ) )
		{
			$paramsList['login'] = $login;
		}

		if( $registration_require_password )
		{
			$paramsList['pass1'] = $pass1;
			$paramsList['pass2'] = $pass2;
			$paramsList['pass_required'] = true;
		}

		if( $registration_require_firstname || !empty( $firstname ) )
		{
			$paramsList['firstname'] = $firstname;
		}

		if( $registration_require_lastname || !empty( $lastname ) )
		{
			$paramsList['lastname'] = $lastname;
		}

		if( $registration_require_nickname || !empty( $nickname ) )
		{
			$paramsList['nickname'] = $nickname;
		}

		if( $registration_require_country || !empty( $country ) )
		{
			$paramsList['country'] = $country;
		}

		if( $registration_require_gender == 'required' || !empty( $gender ) )
		{
			$paramsList['gender'] = $gender;
		}

		if( $registration_require_locale == 'required' || !empty( $locale ) )
		{
			$paramsList['locale'] = $locale;
		}

		if( $Settings->get( 'newusers_canregister' ) == 'invite' )
		{	// Invitation code must be not empty when user can register ONLY with this code
			$paramsList['invitation'] = get_param( 'invitation' );
		}

		// Check profile params:
		if( $is_social )
		{
			social_profile_check_params( $paramsList, $provider );
		}
		else
		{
			profile_check_params( $paramsList );
		}

		if( ( $is_quick || empty( $login ) ) && ! $Messages->has_errors() )
		{	// Generate a login for quick registration or when login is not required:
			// Note: This will generate a random login if no email, first name, last name or nickname is provided!
			$login = generate_login_from_register_info( $email, $firstname, $lastname, $nickname, true );
		}

		if( ! $is_quick && ! is_null( $login ) )
		{
			// We want all logins to be lowercase to guarantee uniqueness regardless of the database case handling for UNIQUE indexes:
			$login = utf8_strtolower( $login );

			$UserCache = & get_UserCache();
			if( $UserCache->get_by_login( $login ) )
			{	// The login is already registered
				param_error( $dummy_fields[ 'login' ], sprintf( T_('The login &laquo;%s&raquo; is already registered, please choose another one.'), $login ) );
			}
		}

		if( $Messages->has_errors() )
		{	// Stop registration if the errors exist
			if( $is_social )
			{
				$Session->delete( 'social.access_credentials' );
				$Session->delete( 'social.user_profile' );
			}
			break;
		}

		$user_domain = $Hit->get_remote_host( true );

		if( $is_quick )
		{	// Check quick registration for suspected data:
			$is_suspected_request = antispam_suspect_check_by_data( array(
				'IP_address'   => $Hit->IP,
				'domain'       => $user_domain,
				'email_domain' => $email,
				'country_IP'   => $Hit->IP,
			) );
			if( $is_suspected_request )
			{	// Current request is suspected by IP address, domain, domain of email address or country of current IP address,
				// We should not allow quick registration for such users, Redirect to normal registration form:
				$prefilled_params = array();
				if( ! empty( $login ) )
				{
					$prefilled_params[ $dummy_fields['login'] ] = $login;
				}
				if( ! empty( $email ) )
				{
					$prefilled_params[ $dummy_fields['email'] ] = $email;
				}
				if( ! empty( $firstname ) )
				{
					$prefilled_params['firstname'] = $firstname;
				}
				if( ! empty( $lastname ) )
				{
					$prefilled_params['lastname'] = $lastname;
				}
				if( ! empty( $nickname ) )
				{
					$prefilled_params['nickname'] = $nickname;
				}
				if( ! empty( $country ) )
				{
					$prefilled_params['country'] = $country;
				}
				if( ! empty( $gender ) )
				{
					$prefilled_params['gender'] = $gender;
				}
				if( ! empty( $locale ) )
				{
					$prefilled_params['locale'] = $locale;
				}
				if( ! empty( $widget ) )
				{
					$prefilled_params['widget'] = $widget;
				}

				if( $is_social )
				{
					$Session->delete( 'social.access_credentials' );
					$Session->delete( 'social.user_profile' );
				}

				// Redirect to normal registration form with prefilled data from quick registration form:
				header_redirect( url_add_param( get_user_register_url( $redirect_to, $source, false, '&' ), $prefilled_params, '&' ) );
				// Exit here.
			}
		}

		$DB->begin();

		if( is_null( $login ) )
		{	// At this point we should already have a login for the user:
			debug_die( 'No login provided.' );
		}

		$new_User = new User();
		$new_User->set( 'login', $login );

		if( ! empty( $widget_newsletters ) )
		{	// Set newsletters subscriptions from current widget "Email capture / Quick registration":
			foreach( $widget_newsletters as $widget_newsletter_ID => $widget_newsletter_is_enabled )
			{
				if( ! $widget_newsletter_is_enabled )
				{	// Remove disabled newsletter from list:
					unset( $widget_newsletters[ $widget_newsletter_ID ] );
				}
			}
			if( isset( $widget_newsletters['default'] ) )
			{	// Set also default newsletters for new users:
				$new_User->insert_default_newsletters = true;
				unset( $widget_newsletters['default'] );
			}
			else
			{	// Don't use default newsletters for new users because it is disabled by widget:
				$new_User->insert_default_newsletters = false;
			}
			if( count( $widget_newsletters ) )
			{	// If at least one newsletter is selected in widget params:
				$newsletter_subscription_params = array();
				if( ! empty( $user_tags ) )
				{
					$newsletter_subscription_params['usertags'] = $user_tags;
				}
				$new_User->set_newsletter_subscriptions( array_keys( $widget_newsletters ), $newsletter_subscription_params );
			}
		}

		if( ! empty( $user_tags ) )
		{	// Set user tags from current widget "Email capture / Quick registration":
			$new_User->add_usertags( $user_tags );
		}

		if( $is_quick || ( !$registration_require_password && empty( $pass1 ) ) )
		{	// Don't save password for quick registration (or if it was not required and no password is provided):
			$new_User->set( 'pass', '' );
			$new_User->set( 'salt', '' );
			$new_User->set( 'pass_driver', 'nopass' );
		}
		else
		{	// Save an entered password from normal registration form:
			$new_User->set_password( $pass1 );
		}
		$new_User->set( 'ctry_ID', $country );
		$new_User->set( 'firstname', $firstname );
		$new_User->set( 'lastname', $lastname );
		$new_User->set( 'nickname', $nickname );
		$new_User->set( 'gender', $gender );
		$new_User->set( 'source', $source );
		$new_User->set_email( $email );
		$new_User->set_datecreated( $localtimenow );
		if( $registration_require_locale || ! empty( $locale ) )
		{	// set locale if it was prompted, otherwise let default
			$new_User->set( 'locale', $locale );
		}

		if( ! empty( $invitation ) )
		{	// Invitation code was entered on the form
			$SQL = new SQL( 'Check if the entered invitation code is not expired' );
			$SQL->SELECT( 'ivc_source, ivc_grp_ID, ivc_level' );
			$SQL->FROM( 'T_users__invitation_code' );
			$SQL->WHERE( 'ivc_code = '.$DB->quote( $invitation ) );
			$SQL->WHERE_and( 'ivc_expire_ts > '.$DB->quote( date( 'Y-m-d H:i:s', $localtimenow ) ) );
			if( $invitation_code = $DB->get_row( $SQL ) )
			{	// Set source and group from invitation code:
				if( ! empty( $invitation_code->ivc_source ) )
				{	// Use invitation source only if it is filled:
					$new_User->set( 'source', $invitation_code->ivc_source );
				}
				if( ! empty( $invitation_code->ivc_level ) )
				{	// Use invitation level only if it is filled:
					$new_User->set( 'level', $invitation_code->ivc_level );
				}
				$GroupCache = & get_GroupCache();
				if( $new_user_Group = & $GroupCache->get_by_ID( $invitation_code->ivc_grp_ID, false, false ) )
				{	// Use invitation group only if it is filled:
					$new_User->set_Group( $new_user_Group );
				}
			}
		}

		if( $new_User->dbinsert() )
		{ // Insert system log about user's registration
			syslog_insert( 'User registration', 'info', 'user', $new_User->ID );
			report_user_create( $new_User );
		}

		$new_user_ID = $new_User->ID; // we need this to "rollback" user creation if there's no DB transaction support

		// TODO: Optionally auto create a blog (handle this together with the LDAP plugin)

		// TODO: Optionally auto assign rights

		// Actions to be appended to the user registration transaction:
		if( $Plugins->trigger_event_first_false( 'AppendUserRegistrTransact', array( 'User' => & $new_User ) ) )
		{
			// TODO: notify the plugins that have been called before about canceling of the event?!
			$DB->rollback();

			// Delete, in case there's no transaction support:
			$new_User->dbdelete( $Debuglog );

			$Messages->add( T_('No user account has been created!'), 'error' );
			break; // break out to _reg_form.php
		}

		// User created:
		$DB->commit();
		$UserCache->add( $new_User );

		$initial_hit = $Session->get_first_hit_params();
		if( ! empty ( $initial_hit ) )
		{	// Save User Settings
			$UserSettings->set( 'initial_sess_ID' , $initial_hit->hit_sess_ID, $new_User->ID );
			$UserSettings->set( 'initial_blog_ID' , $initial_hit->hit_coll_ID, $new_User->ID );
			$UserSettings->set( 'initial_URI' , $initial_hit->hit_uri, $new_User->ID );
			$UserSettings->set( 'initial_referer' , $initial_hit->hit_referer , $new_User->ID );
		}
		if( !empty( $session_registration_trigger_url ) )
		{	// Save Trigger page
			$UserSettings->set( 'registration_trigger_url' , $session_registration_trigger_url, $new_User->ID );
		}
		$UserSettings->set( 'created_fromIPv4', ip2int( $Hit->IP ), $new_User->ID );
		$UserSettings->set( 'user_registered_from_domain', $user_domain, $new_User->ID );
		$UserSettings->set( 'user_browser', substr( $Hit->get_user_agent(), 0 , 200 ), $new_User->ID );
		$UserSettings->dbupdate();

		// Auto subscribe new user to current collection posts/comments:
		if( ! empty( $subscribe_coll_ID ) && ( ! empty( $auto_subscribe_posts ) || ! empty( $auto_subscribe_comments ) ) )
		{	// If at least one option is enabled
			$DB->query( 'REPLACE INTO T_subscriptions ( sub_coll_ID, sub_user_ID, sub_items, sub_items_mod, sub_comments )
					VALUES ( '.$DB->quote( $subscribe_coll_ID ).', '.$DB->quote( $new_User->ID ).', '.$DB->quote( intval( $auto_subscribe_posts ) ).', 0, '.$DB->quote( intval( $auto_subscribe_comments ) ).' )' );
		}

		// Get user domain status:
		load_funcs( 'sessions/model/_hitlog.funcs.php' );
		$DomainCache = & get_DomainCache();
		$Domain = & get_Domain_by_subdomain( $user_domain );
		$dom_status_titles = stats_dom_status_titles();
		$dom_status = $dom_status_titles[ $Domain ? $Domain->get( 'status' ) : 'unknown' ];

		// Send notification email about new user registrations to users with edit users permission
		$email_template_params = array(
				'country'     => $new_User->get( 'ctry_ID' ),
				'reg_country' => $new_User->get( 'reg_ctry_ID' ),
				'reg_domain'  => $user_domain.' ('.$dom_status.')',
				'user_domain' => $user_domain,
				'firstname'   => $firstname,
				'lastname'    => $lastname,
				'fullname'    => $new_User->get( 'fullname' ),
				'gender'      => $gender,
				'locale'      => $locale,
				'source'      => $new_User->get( 'source' ),
				'trigger_url' => $session_registration_trigger_url,
				'initial_hit' => $initial_hit,
				'level'       => $new_User->get( 'level' ),
				'group'       => ( ( $user_Group = & $new_User->get_Group() ) ? $user_Group->get_name() : '' ),
				'login'       => $login,
				'email'       => $email,
				'new_user_ID' => $new_User->ID,
			);
		send_admin_notification( NT_('New user registration'), 'account_new', $email_template_params );

		// Send notification to owners of lists where new user is automatically subscribed:
		$new_User->send_list_owner_notifications( 'subscribe' );

		$Plugins->trigger_event( 'AfterUserRegistration', array( 'User' => & $new_User ) );
		// Move user to suspect group by IP address and reverse DNS domain and email address domain:
		// Make this move even if during the registration it was added to a trusted group:
		antispam_suspect_user_by_IP( '', $new_User->ID, false );
		antispam_suspect_user_by_reverse_dns_domain( $new_User->ID, false );
		antispam_suspect_user_by_email_domain( $new_User->ID, false );

		if( $Settings->get('newusers_mustvalidate') )
		{	// We want that the user validates his email address:
			$inskin_blog = $inskin ? $blog : NULL;
			if( $new_User->send_validate_email( $redirect_to, $inskin_blog ) )
			{
				$activateinfo_link = 'href="'.get_activate_info_url( NULL, '&amp;' ).'"';
				$Messages->add( sprintf( T_('An email has been sent to your email address. Please click on the link therein to activate your account. <a %s>More info &raquo;</a>'), $activateinfo_link ), 'success' );
			}
			elseif( $demo_mode )
			{
				$Messages->add( 'Sorry, could not send email. Sending email in demo mode is disabled.', 'error' );
			}
			else
			{
				$Messages->add( T_('Sorry, the email with the link to activate your account could not be sent.')
					.'<br />'.get_send_mail_error(), 'error' );
				// fp> TODO: allow to enter a different email address (just in case it's that kind of problem)
			}
		}
		else
		{	// Display this message after successful registration and without validation email
			$Messages->add( T_('You have successfully registered on this site. Welcome!'), 'success' );
		}

		// Autologin the user. This is more comfortable for the user and avoids
		// extra confusion when account validation is required.
		$Session->set_User( $new_User );

		if( $is_quick )
		{	// Set redirect_to after quick registration from social, widget or inline tag "[emailcapture:]":
			if( ! empty( $widget_redirect_to ) )
			{	// If a redirect param is defined:
				if( preg_match( '#^(https?://|/)#i', $widget_redirect_to ) )
				{	// Use absolute or relative url:
					$widget_redirect_to_url = $widget_redirect_to;
				}
				else
				{	// Try to find Item by slug:
					$ItemCache = & get_ItemCache();
					if( $widget_redirect_Item = & $ItemCache->get_by_urltitle( $widget_redirect_to, false, false ) )
					{	// Use permanent url of the detected Item by slug:
						$widget_redirect_to_url = $widget_redirect_Item->get_permanent_url( '', '', '&' );
					}
				}
			}

			$redirect_to_registration_form = true;
			if( $is_social )
			{	
				$redirect_to_registration_form = ( $after_social_registration == 'regform' );
			}
			else
			{
				$redirect_to_registration_form = ( $Settings->get( 'registration_after_quick' ) == 'regform' );
			}

			if( $redirect_to_registration_form )
			{	// If we should display additional registration screen after quick registration:
				$Messages->add( T_('Please double check your email address and choose a password so that you can log in next time you visit us.'), 'warning' );
				if( $is_social && empty( $Blog ) )
				{
					global $blog, $current_User;

					$widget_redirect_to_url = $redirect_to;

					// We do not have a $Blog and $current_User to autoselect a collection yet:
					$temp_current_User = $current_User;
					$current_User = $new_User;
					$temp_blog = $blog;  // need to restore later?
					$temp_Blog = $Blog;  // need to restore later?

					// Auto select a collection so we can get the redirect URL to the finish register form:
					$blog = autoselect_blog( 'blog_ismember' );
					$Blog = $BlogCache->get_by_ID( $blog );

					// Restore temporary var:
					$current_User = $temp_current_User;
				}

				$widget_redirect_to_url = $Blog->get( 'register_finishurl', array(
						'glue'       => '&',
						'url_suffix' => 'redirect_to='.rawurlencode( empty( $widget_redirect_to_url ) ? get_returnto_url() : $widget_redirect_to_url ),
					) );
			}

			if( isset( $widget_redirect_to_url ) )
			{	// Redirect to URL from widget config:
				header_redirect( $widget_redirect_to_url );
				// Exit here.
			}
		}

		// Set redirect_to pending from after_registration setting:
		$redirect_to = get_redirect_after_registration( $inskin );

		header_redirect( $redirect_to );
		break;


	case 'disabled':
		/*
		 * Registration disabled:
		 */
		$params = array(
				'register_form_title' => T_('Registration Currently Disabled'),
				'wrap_width'          => '350px',
			);
		require $adminskins_path.'login/_reg_form.main.php';

		exit(0);
}

if( ! empty( $is_quick ) )
{	// Redirect to previous page everytime when quick registration is used, even when errors exist
	if( ! empty( $param_input_err_messages ) )
	{	// Save all param errors in Session because of the redirect below
		$Session->set( 'param_input_err_messages_'.$widget, $param_input_err_messages );
	}
	$param_input_values = array(
			$dummy_fields['email'] => $email,
			'firstname'            => $firstname,
			'lastname'             => $lastname,
			'country'              => $country,
		);
	$Session->set( 'param_input_values_'.$widget, $param_input_values );
	header_redirect( $redirect_to );
}

/*
 * Default: registration form:
 */

// Add core.register_user info again to fill up registration form fields later
$register_user = array(
	'name' => $login,
	'email' => $email
);
$Session->set( 'core.register_user', $register_user );

if( $inskin && !empty( $Blog ) )
{	// in-skin display
	$SkinCache = & get_SkinCache();
	$Skin = & $SkinCache->get_by_ID( $Blog->get_skin_ID() );
	$skin = $Skin->folder;
	$disp = 'register';
	$ads_current_skin_path = $skins_path.$skin.'/';
	if( file_exists( $ads_current_skin_path.'register.main.php' ) )
	{	// Call custom file for register disp if it exists:
		require $ads_current_skin_path.'register.main.php';
	}
	else
	{	// Call index main skin file to display a register disp:
		require $ads_current_skin_path.'index.main.php';
	}
	// already exited here
	exit(0);
}

// Display reg form:
require_js_defer('#jquery#');
require_js_defer( 'src/evo_init_password_indicator.js', 'rsc_url' );

require $adminskins_path.'login/_reg_form.main.php';
  // Undefined array key "register_field_width"
#display_password_indicator( array( 'field_width' => $params['register_field_width'] ) );
display_password_indicator( array( 
    'field_width' => $params['register_field_width'] ?? 'default_width'
));

?>