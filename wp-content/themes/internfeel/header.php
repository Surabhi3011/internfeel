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
			<a class="if-button if-button--primary" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>"><?php esc_html_e( 'Submit experience', 'internfeel' ); ?></a>
		</div>
	</div>
</header>

<main id="content" class="if-main">
