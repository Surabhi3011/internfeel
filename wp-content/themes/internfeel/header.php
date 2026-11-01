<?php
/**
 * Site header (docs/approved-designs/README.md, "Header and footer"; plan.md 5.3): logo, navigation,
 * a compact search on inner pages, Submit, and either Log in and Sign up or Dashboard (with the
 * unread count) and the avatar menu. Below 1000 px a Menu button holds the navigation and account links.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

$if_unread = is_user_logged_in() && class_exists( '\InternFeel\Notify\Notify' ) ? \InternFeel\Notify\Notify::unread_count( get_current_user_id() ) : 0;

$if_nav = array(
	'/experiences/' => __( 'Experiences', 'internfeel' ),
	'/org/'         => __( 'Programmes', 'internfeel' ),
	'/lists/'       => __( 'Listicles', 'internfeel' ),
	'/about/'       => __( 'About', 'internfeel' ),
);

$if_account = array();
if ( is_user_logged_in() ) {
	$if_account = array(
		home_url( '/dashboard/' )         => __( 'Dashboard', 'internfeel' ),
		home_url( '/dashboard/#invites' ) => __( 'Invites', 'internfeel' ),
		home_url( '/dashboard/#profile' ) => __( 'Profile', 'internfeel' ),
	);
	if ( current_user_can( 'if_moderate' ) ) {
		$if_account[ admin_url( 'admin.php?page=internfeel-review' ) ] = __( 'Admin', 'internfeel' );
	}
	$if_account[ wp_logout_url( home_url( '/' ) ) ] = __( 'Log out', 'internfeel' );
}

$if_initials = mb_strtoupper( mb_substr( (string) wp_get_current_user()->display_name, 0, 1 ) );
$if_path     = (string) wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH );
$if_home     = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
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
			<?php foreach ( $if_nav as $if_url => $if_label ) : ?>
				<a href="<?php echo esc_url( home_url( $if_url ) ); ?>"<?php echo str_starts_with( $if_path, $if_home . $if_url ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $if_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="if-header__actions">
			<?php if ( ! is_front_page() ) : ?>
				<form class="if-search if-header__search" role="search" method="get" action="<?php echo esc_url( home_url( '/experiences/' ) ); ?>">
					<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<label class="screen-reader-text" for="if-header-q"><?php esc_html_e( 'Search experiences', 'internfeel' ); ?></label>
					<input id="if-header-q" type="search" name="q" placeholder="<?php esc_attr_e( 'Search', 'internfeel' ); ?>">
				</form>
			<?php endif; ?>

			<?php if ( ! is_user_logged_in() ) : ?>
				<a class="if-btn if-btn--ghost if-btn--sm if-header__wide" href="<?php echo esc_url( home_url( '/login/' ) ); ?>"><?php esc_html_e( 'Log in', 'internfeel' ); ?></a>
				<a class="if-btn if-btn--sm if-header__wide" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>"><?php esc_html_e( 'Sign up', 'internfeel' ); ?></a>
			<?php endif; ?>

			<?php // The dashboard and the form have their own Share button, so the header leaves it out there. ?>
			<?php if ( ! is_page( array( 'dashboard', 'submit' ) ) ) : ?>
				<a class="if-btn if-btn--primary if-btn--sm" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>">
					<span class="if-hide-mobile"><?php esc_html_e( 'Share your experience', 'internfeel' ); ?></span>
					<span class="if-show-mobile"><?php esc_html_e( 'Share', 'internfeel' ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( is_user_logged_in() ) : ?>
				<?php
				// The unread count sits on the Dashboard link (the bell was dropped as redundant, 1 November 2026).
				$if_dash_label = 0 === $if_unread
					? __( 'Dashboard', 'internfeel' )
					/* translators: %d: number of unread updates. */
					: sprintf( _n( 'Dashboard, %d new update', 'Dashboard, %d new updates', $if_unread, 'internfeel' ), $if_unread );
				?>
				<a class="if-btn if-btn--ghost if-btn--sm if-header__wide if-header__dashboard" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" aria-label="<?php echo esc_attr( $if_dash_label ); ?>"<?php echo is_page( 'dashboard' ) ? ' aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'Dashboard', 'internfeel' ); ?>
					<?php if ( $if_unread > 0 ) : ?>
						<span class="if-header__count" aria-hidden="true"><?php echo esc_html( (string) min( $if_unread, 99 ) ); ?></span>
					<?php endif; ?>
				</a>
				<details class="if-menu if-header__wide">
					<summary class="if-menu__toggle" aria-label="<?php esc_attr_e( 'Your account menu', 'internfeel' ); ?>"><span class="if-avatar" aria-hidden="true"><?php echo esc_html( $if_initials ); ?></span></summary>
					<ul class="if-menu__list">
						<?php foreach ( $if_account as $if_url => $if_label ) : ?>
							<li><a href="<?php echo esc_url( $if_url ); ?>"><?php echo esc_html( $if_label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>

			<details class="if-menu if-header__phone">
				<summary class="if-menu__toggle" aria-label="<?php echo esc_attr( $if_unread > 0 ? sprintf( /* translators: %d: number of unread updates. */ _n( 'Menu, %d new update', 'Menu, %d new updates', $if_unread, 'internfeel' ), $if_unread ) : __( 'Menu', 'internfeel' ) ); ?>">
					<svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
					<?php if ( $if_unread > 0 ) : ?>
						<span class="if-header__count if-header__count--dot" aria-hidden="true"><?php echo esc_html( (string) min( $if_unread, 99 ) ); ?></span>
					<?php endif; ?>
				</summary>
				<div class="if-menu__list">
					<form class="if-search" role="search" method="get" action="<?php echo esc_url( home_url( '/experiences/' ) ); ?>">
						<label class="screen-reader-text" for="if-phone-q"><?php esc_html_e( 'Search experiences', 'internfeel' ); ?></label>
						<input id="if-phone-q" type="search" name="q" placeholder="<?php esc_attr_e( 'Search experiences', 'internfeel' ); ?>">
					</form>
					<ul>
						<?php foreach ( $if_nav as $if_url => $if_label ) : ?>
							<li><a href="<?php echo esc_url( home_url( $if_url ) ); ?>"><?php echo esc_html( $if_label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
					<ul>
						<?php if ( is_user_logged_in() ) : ?>
							<?php foreach ( $if_account as $if_url => $if_label ) : ?>
								<li><a href="<?php echo esc_url( $if_url ); ?>"><?php echo esc_html( $if_label ); ?></a></li>
							<?php endforeach; ?>
						<?php else : ?>
							<li><a href="<?php echo esc_url( home_url( '/login/' ) ); ?>"><?php esc_html_e( 'Log in', 'internfeel' ); ?></a></li>
							<li><a href="<?php echo esc_url( home_url( '/signup/' ) ); ?>"><?php esc_html_e( 'Sign up', 'internfeel' ); ?></a></li>
						<?php endif; ?>
					</ul>
				</div>
			</details>
		</div>
	</div>
</header>

<main id="content" class="if-main">
