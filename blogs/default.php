<?php 
/**
 * This is the main public interface file!
 *
 * This file is NOT mandatory. You can delete it if you want.
 * You can also replace the contents of this file with contents similar to the contents
 * of noskin_a.php or multiblogs.php or those of a stub file (see stub.model)
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2004 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package b2evolution
 */

// First thing: Do the minimal initializations required for b2evo:
require_once dirname(__FILE__).'/b2evocore/_main.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php locale_lang() ?>" lang="<?php locale_lang() ?>">
<head>
	<base href="<?php echo $baseurl ?>/" />
	<meta http-equiv="Content-Type" content="text/html; charset=<?php locale_charset() ?>" />
	<title><?php echo T_('Default page for b2evolution') ?></title>
	<link href="rsc/b2evo.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div id="rowheader2">
<h1><a href="http://b2evolution.net/" title="b2evolution: Home"><img src="img/b2evolution_logo.png" alt="b2evolution" width="472" height="102" border="0" /></a></h1>
<div id="tagline"><?php echo T_('Multilingual multiuser multiblog engine!') ?></div>
</div>

<h1><?php echo T_('Welcome to b2evolution') ?></h1>

<p><?php echo T_('This is the default homepage for b2evolution. It will be displayed as long as you don\'t select a default blog in the general settings.') ?></p>


<h2><?php echo T_('Individual blogs on this system') ?>:</h2>
<ul>
<?php // --------------------------- BLOG LIST -----------------------------
	for( $curr_blog_ID=blog_list_start('stub'); 
				$curr_blog_ID!=false; 
				 $curr_blog_ID=blog_list_next('stub') ) 
	{ # by uncommenting the following lines you can hide some blogs
		if( $curr_blog_ID == 1 ) continue; // Hide blog 1...
		// if( $curr_blog_ID == 2 ) continue; // Hide blog 2...
		echo '<li><strong>';
		printf( T_('Blog #%d'), $curr_blog_ID );
		echo ': <a href="';
		blog_list_iteminfo( 'blogurl', 'raw');
		echo '" title="';
		blog_list_iteminfo( 'shortdesc', 'htmlattr');
		echo '">';
		blog_list_iteminfo( 'name', 'htmlbody');
		echo '</a></strong> &nbsp; (';
		blog_list_iteminfo( 'stub', 'raw');
		echo ')';
		echo '</li>';
	}
	// ---------------------------------- END OF BLOG LIST --------------------------------- ?>
</ul>
<?php 
// Select Blog #1: 
$blog = 1;
$Blog_all = Blog_get_by_ID( 1 );
if( $Blog_all->get( 'stub' ) != '' )
{	// Only display if the stub is set:
	?>
	<ul>
	<li><strong><?php echo T_('Blog #1') ?>: <a href="<?php $Blog_all->disp( 'blogurl', 'raw' ); ?>"><?php echo T_('This is a special blog that aggregates all messages from all other blogs!') ?></a></strong> &nbsp; (<?php $Blog_all->disp( 'stub', 'raw' ); ?>)</li>
	</ul>
	<?php 
}
?>
<p><?php echo T_('Please note: the above list (as well as the menu) is automatically generated and includes only the blogs that have a &quot;stub url name&quot;. You can set this in the blog configuration in the back-office.') ?></p>
<h2><?php echo T_('More demos') ?>:</h2>
<ul>
  <li><strong><?php echo T_('Custom template') ?>: <a href="multiblogs.php"><?php echo T_('Multiple blogs displayed on the same page') ?></a></strong> &nbsp; (multiblogs.php)</li>
  <li><strong><?php echo T_('Custom template') ?>: <a href="summary.php"><?php echo T_('Summary of last posts in all blogs') ?></a></strong> &nbsp; (summary.php)</li>
  <li><strong><?php echo T_('Custom template') ?>: <a href="default.php"><?php echo T_('The page you\'re looking at') ?></a></strong> &nbsp; (default.php)</li>
</ul>
<p><?php echo T_('Please note: those demos do not make use of evoSkins, even if you enabled them during install. The only way to change their look and feel is to edit their PHP template. But once, again, rememner these are just demos destined to inspire you for your own templates ;)') ?></p>

<h2><?php echo T_('Administration') ?>:</h2>
<ul>
	<li><strong><a href="<?php echo $admin_url ?>/">Go to admin!</a></strong></li>
</ul>


<div id="rowfooter">
<a href="http://b2evolution.net/"><?php echo T_('Official website') ?></a> &middot; <a href="http://b2evolution.net/about/license.html"><?php echo T_('GNU GPL license') ?></a>
</div>

</body>
</html>
