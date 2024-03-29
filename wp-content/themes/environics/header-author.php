<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package environics
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-TVZ74DS');</script>
<!-- End Google Tag Manager -->

<?php wp_head(); ?>
</head>

<body>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TVZ74DS"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'environics' ); ?></a>

	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<?php $site_logo = get_field('site_logo', 'option'); ?>
			<a class="nav-item" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><img src="<?php echo $site_logo['url']; ?>" alt="<?php echo $site_logo['alt']; ?>"/></a>
			<div class="hamburger-container" tabindex="0" role="button" aria-label="Site menu">
				<p>M</p><p class="exit">E</p>
				<div class="hamburger">
					<div class="patty"></div>
				</div>
				<p>N</p><p class="exit">I</p>
				<p>U</p><p class="exit">T</p>
			</div>
		</div><!-- .site-branding -->
		
		<nav id="site-navigation" class="main-navigation nav-container hide" role="navigation">
			<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false"><?php esc_html_e( 'Primary Menu', 'environics' ); ?></button>
			<div class="menu-primary-container outer">
				<?php wp_nav_menu( array( 'theme_location' => 'primary', 'menu' => 'Primary' ) ); ?>
				<div class="social-icons">
					<?php if(get_field('twitter_account', 'option')): ?>
					<a href="<?php the_field('twitter_account', 'option'); ?>" target="_blank" alt="Twitter link" aria-label="Link to Environics Twitter account"><i class="fa fa-twitter" aria-hidden="true"></i></a>
				<?php endif; ?>
				<?php if(get_field('facebook_account', 'option')): ?>
					<a href="<?php the_field('facebook_account', 'option'); ?>" target="_blank" alt="Facebook link" aria-label="Link to Environics Facebook account"><i class="fa fa-facebook" aria-hidden="true"></i></a>
				<?php endif; ?>
				<?php if(get_field('instagram_account', 'option')): ?>
					<a href="<?php the_field('instagram_account', 'option'); ?>" target="_blank" alt="Instagram link" aria-label="Link to Environics Instagram account"><i class="fa fa-instagram" aria-hidden="true"></i></a>
				<?php endif; ?>
				<?php if(get_field('youtube_account', 'option')): ?>
					<a href="<?php the_field('youtube_account', 'option'); ?>" target="_blank" alt="Youtube link" aria-label="Link to Environics Youtube account"><i class="fa fa-youtube" aria-hidden="true"></i></a>
				<?php endif; ?>
				<?php if(get_field('linkedin_account', 'option')): ?>
					<a href="<?php the_field('linkedin_account', 'option'); ?>" target="_blank" alt="LinkedIn link" aria-label="Link to Environics LinkedIn account"><i class="fa fa-linkedin" aria-hidden="true"></i></a>
				<?php endif; ?>
				</div>
			</div>
		</nav>

	</header><!-- #masthead -->

	<div id="content" class="site-content">
		
