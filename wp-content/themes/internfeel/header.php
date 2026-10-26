<?php
/**
 * Site header. The full header from plan.md 5.3 (navigation, search, bell, avatar menu) is built on day 10.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;
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
	<div class="if-container if-header__inner">
		<?php if_the_logo(); ?>

		<?php
		if ( has_nav_menu( 'primary' ) ) {
			wp_nav_menu(
				array(
					'theme_location'       => 'primary',
					'container'            => 'nav',
					'container_aria_label' => __( 'Primary', 'internfeel' ),
					'depth'                => 1,
				)
			);
		}
		?>

		<div class="if-header__actions">
			<a class="if-button if-button--primary" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>">
				<span class="if-hide-mobile"><?php esc_html_e( 'Submit experience', 'internfeel' ); ?></span>
				<span class="if-show-mobile"><?php esc_html_e( 'Submit', 'internfeel' ); ?></span>
			</a>
			<?php if ( is_user_logged_in() ) : ?>
				<a class="if-header__link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'internfeel' ); ?></a>
			<?php else : ?>
				<a class="if-header__link" href="<?php echo esc_url( home_url( '/login/' ) ); ?>"><?php esc_html_e( 'Log in', 'internfeel' ); ?></a>
				<a class="if-button if-hide-mobile" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>"><?php esc_html_e( 'Sign up', 'internfeel' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>

<main id="content" class="if-main">
