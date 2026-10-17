<?php
/**
 * Site header.
 *
 * @package SwatTournamentOS
 */
defined( 'ABSPATH' ) || exit;
$is_gameday = swat_os_is_gameday_request();
$event      = $is_gameday ? swat_os_current_event() : array();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="swat-skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'swat-tournament-os' ); ?></a>

<?php if ( true ) : /* child: site header on Game Day too */ ?>
	<header class="swat-site-header" role="banner">
		<div class="swat-shell swat-header-inner">
			<a class="swat-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( swat_os_logo_url() ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></a>
			<button class="swat-menu-toggle" type="button" aria-expanded="false" aria-controls="swat-primary-nav"><span class="swat-menu-bars" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'swat-tournament-os' ); ?></span></button>
			<nav id="swat-primary-nav" class="swat-primary-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'swat-tournament-os' ); ?>">
				<?php if ( has_nav_menu( 'primary' ) ) : ?>
                <?php wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'menu', 'fallback_cb' => false ) ); ?>
                <?php else : ?>
                <ul class="menu">
					<li><a href="<?php echo esc_url( home_url( '/tournaments/' ) ); ?>"><?php esc_html_e( 'Tournaments', 'swat-tournament-os-child' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/teams/' ) ); ?>"><?php esc_html_e( 'Teams', 'swat-tournament-os-child' ); ?></a></li>
					<li class="swat-menu-parent menu-item-swat-gameday"><a class="swat-btn swat-btn-gold" href="<?php echo esc_url( swat_os_gameday_url() ); ?>"><?php esc_html_e( 'Game Day', 'swat-tournament-os-child' ); ?></a><ul class="swat-submenu">
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'schedule' ) ) ); ?>"><?php esc_html_e( 'Schedule', 'swat-tournament-os-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'scores' ) ) ); ?>"><?php esc_html_e( 'Scores', 'swat-tournament-os-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'standings' ) ) ); ?>"><?php esc_html_e( 'Standings', 'swat-tournament-os-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'pitching' ) ) ); ?>"><?php esc_html_e( 'Pitch Counts', 'swat-tournament-os-child' ); ?></a></li>
					</ul></li>
					<li class="swat-menu-parent"><a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>"><?php esc_html_e( 'Rules', 'swat-tournament-os-child' ); ?></a><ul class="swat-submenu">
						<li><a href="<?php echo esc_url( home_url( '/pitching/' ) ); ?>"><?php esc_html_e( 'Pitching Limits', 'swat-tournament-os-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/rules/#division-rules' ) ); ?>"><?php esc_html_e( 'Rules by Division', 'swat-tournament-os-child' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/rules/#full-rules' ) ); ?>"><?php esc_html_e( 'Full Handbook', 'swat-tournament-os-child' ); ?></a></li>
					</ul></li>
					<li><a href="<?php echo esc_url( home_url( '/fields-facilities/' ) ); ?>"><?php esc_html_e( 'Fields & Facilities', 'swat-tournament-os-child' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'swat-tournament-os-child' ); ?></a></li>
				</ul>
                <?php endif; ?>
			</nav>
			<div class="swat-header-actions">
				<a class="swat-btn swat-btn-ghost swat-header-register" href="https://play.swatourneys.com/"><?php esc_html_e( 'REGISTER', 'swat-tournament-os-child' ); ?></a>
			</div>
		</div>
	</header>
<?php endif; ?>

<main id="main" class="<?php echo $is_gameday ? 'swat-app-main' : 'swat-site-main'; ?>">
