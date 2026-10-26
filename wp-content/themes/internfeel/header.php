<?php
/**
 * Site header (docs/approved-designs/README.md, "Header and footer").
 *
 * Still to come on day 10: the compact search on inner pages, the avatar menu, and the phone menu
 * (which takes over the navigation and, on phones, Log in and Sign up).
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

$if_unread = is_user_logged_in() && class_exists( '\InternFeel\Notify\Notify' ) ? \InternFeel\Notify\Notify::unread_count( get_current_user_id() ) : 0;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'internfeel' ); ?></a>

<header class="if-header" data-if-header>
	<div class="if-header__inner">
		<?php if_the_logo(); ?>

		<nav class="if-nav" aria-label="<?php esc_attr_e( 'Main', 'internfeel' ); ?>">
			<a href="<?php echo esc_url( home_url( '/experiences/' ) ); ?>"><?php esc_html_e( 'Experiences', 'internfeel' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/org/' ) ); ?>"><?php esc_html_e( 'Programmes', 'internfeel' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/lists/' ) ); ?>"><?php esc_html_e( 'Listicles', 'internfeel' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About', 'internfeel' ); ?></a>
		</nav>

		<div class="if-header__actions">
			<?php if ( is_user_logged_in() ) : ?>
				<a class="if-btn if-btn--primary if-btn--sm" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>">
					<span class="if-hide-mobile"><?php esc_html_e( 'Submit experience', 'internfeel' ); ?></span>
					<span class="if-show-mobile"><?php esc_html_e( 'Submit', 'internfeel' ); ?></span>
				</a>
				<?php
				$if_bell_label = 0 === $if_unread
					? __( 'Notifications', 'internfeel' )
					/* translators: %d: number of unread notifications. */
					: sprintf( _n( 'Notifications, %d unread', 'Notifications, %d unread', $if_unread, 'internfeel' ), $if_unread );
				?>
				<a class="if-bell" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" aria-label="<?php echo esc_attr( $if_bell_label ); ?>">
					<svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
					<?php if ( $if_unread > 0 ) : ?>
						<span class="if-bell__count" aria-hidden="true"><?php echo esc_html( (string) min( $if_unread, 99 ) ); ?></span>
					<?php endif; ?>
				</a>
				<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'internfeel' ); ?></a>
			<?php else : ?>
				<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( home_url( '/login/' ) ); ?>"><?php esc_html_e( 'Log in', 'internfeel' ); ?></a>
				<a class="if-btn if-btn--sm if-hide-mobile" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>"><?php esc_html_e( 'Sign up', 'internfeel' ); ?></a>
				<a class="if-btn if-btn--primary if-btn--sm" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>">
					<span class="if-hide-mobile"><?php esc_html_e( 'Submit experience', 'internfeel' ); ?></span>
					<span class="if-show-mobile"><?php esc_html_e( 'Submit', 'internfeel' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</header>

<main id="content" class="if-main">
