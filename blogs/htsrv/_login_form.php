<?php
/**
 * This is the login form
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2004 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package htsrv
 */

/**
 * Include page header:
 */
$page_title = T_('Login form');
$page_icon = 'icon_login.gif';
require(dirname(__FILE__).'/_header.php');

param( 'redirect_to', 'string', $ReqURI );
param( 'log', 'string', '' );		// last typed login

$location = $redirect_to;
$Debuglog->add( 'location: '.$location );

$Form = & new Form( $location );

$Form->begin_form( 'fform' );

if( !empty($mode) )
{	// We're in the process of bookmarkletting something, we don't want to loose it:
	param( 'text', 'html', '' );
	param( 'popupurl', 'html', '' );
	param( 'popuptitle', 'html', '' );
		
	$Form->hidden( 'mode', $mode );
	$Form->hidden( 'text', $text );
	$Form->hidden( 'popupurl', $popupurl );
	$Form->hidden( 'popuptitle', $popuptitle );
}

echo $Form->fieldstart;
	?>

		<div class="center"><span class="notes"><?php printf( T_('You will have to accept cookies in order to log in.') ) ?></span></div>
<?php

	$Form->text( 'log', $log, 16, T_('Login'), '', 20 , 'large' );
	
	$Form->password( 'pwd', '', 16, T_('Password'), '', 20, 'large' );
	
	echo $Form->fieldstart;
	echo $Form->inputstart;
	$Form->submit( array( 'submit', T_('Log in!'), 'search' ) );
	echo $Form->inputend;
	echo $Form->fieldend;
	
	echo $Form->fieldend;
	
	$Form->end_form();
	
?>

<div style="text-align:right">
<?php user_register_link( '', ' &middot; ' )?>
<a href="<?php echo $htsrv_url ?>login.php?action=lostpassword&amp;redirect_to=<?php echo urlencode( $redirect_to ) ?>"><?php echo T_('Lost your password ?') ?></a>
</div>

<?php
	require(dirname(__FILE__).'/_footer.php');
?>