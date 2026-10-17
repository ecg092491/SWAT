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
				<ul class="menu">
					<li><a href="<?php echo esc_url( home_url( '/tournaments/' ) ); ?>">Tournaments</a></li>
					<li><a href="<?php echo esc_url( home_url( '/teams/' ) ); ?>">Teams</a></li>
					<li class="swat-menu-parent menu-item-swat-gameday"><a class="swat-btn swat-btn-gold" href="<?php echo esc_url( swat_os_gameday_url() ); ?>">Game Day</a><ul class="swat-submenu">
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'schedule' ) ) ); ?>">Schedule</a></li>
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'scores' ) ) ); ?>">Scores</a></li>
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'standings' ) ) ); ?>">Standings</a></li>
						<li><a href="<?php echo esc_url( swat_os_gameday_url( array( 'view' => 'pitching' ) ) ); ?>">Pitch Counts</a></li>
					</ul></li>
					<li class="swat-menu-parent"><a href="<?php echo esc_url( home_url( '/rules/' ) ); ?>">Rules</a><ul class="swat-submenu">
						<li><a href="<?php echo esc_url( home_url( '/pitching/' ) ); ?>">Pitching Limits</a></li>
						<li><a href="<?php echo esc_url( home_url( '/rules/#division-rules' ) ); ?>">Rules by Division</a></li>
						<li><a href="<?php echo esc_url( home_url( '/rules/#full-rules' ) ); ?>">Full Handbook</a></li>
					</ul></li>
					<li><a href="<?php echo esc_url( home_url( '/fields-facilities/' ) ); ?>">Fields &amp; Facilities</a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
				</ul>
			</nav>
			<div class="swat-header-actions">
				<a class="swat-btn swat-btn-ghost swat-header-register" href="https://play.swatourneys.com/">REGISTER</a>
			</div>
		</div>
	</header>
<?php endif; ?>

<main id="main" class="<?php echo $is_gameday ? 'swat-app-main' : 'swat-site-main'; ?>">
