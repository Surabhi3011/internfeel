<?php
/**
 * Small template helpers shared by the theme's templates.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep a page out of every cache. Pages with forms carry nonces, and pages for signed-in students
 * show personal details, so LiteSpeed Cache must never store them.
 */
function if_no_page_cache(): void {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
}

/**
 * Whether InternFeel Core is active. Templates that need it show a short notice instead of failing.
 *
 * @return bool
 */
function if_core_active(): bool {
	return class_exists( '\InternFeel\Auth\Profile' );
}

/**
 * Print the hidden honeypot field used by the public forms. People never see it; bots fill it in.
 */
function if_honeypot_field(): void {
	?>
	<div class="if-honeypot" aria-hidden="true">
		<label for="if-website"><?php esc_html_e( 'Leave this field empty', 'internfeel' ); ?></label>
		<input type="text" id="if-website" name="website" value="" tabindex="-1" autocomplete="off">
	</div>
	<?php
}

/**
 * Print the Sign up / Log in switch: two links styled as the segment control, the current page marked.
 *
 * @param string $current Either "signup" or "login".
 */
function if_auth_switch( string $current ): void {
	$pages = array(
		'signup' => __( 'Sign up', 'internfeel' ),
		'login'  => __( 'Log in', 'internfeel' ),
	);
	?>
	<nav class="if-segment" aria-label="<?php esc_attr_e( 'Sign up or log in', 'internfeel' ); ?>">
		<?php foreach ( $pages as $slug => $label ) : ?>
			<a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Print "Continue with Google" (a full-width outline button) and the divider, when Nextend Social
 * Login's Google provider is set up. Its shortcode renders nothing until it is, which is the check;
 * the button itself uses Nextend's documented log-in URL so it matches the design system.
 *
 * @param string $redirect Where to land after signing in.
 */
function if_google_button( string $redirect = '' ): void {
	if ( ! shortcode_exists( 'nextend_social_login' ) || '' === trim( do_shortcode( '[nextend_social_login provider="google"]' ) ) ) {
		return;
	}

	$args = array( 'loginSocial' => 'google' );
	if ( '' !== $redirect ) {
		$args['redirect'] = rawurlencode( $redirect );
	}
	?>
	<a class="if-btn if-btn--block" href="<?php echo esc_url( add_query_arg( $args, wp_login_url() ) ); ?>"><?php esc_html_e( 'Continue with Google', 'internfeel' ); ?></a>
	<div class="if-auth__or"><?php esc_html_e( 'or with your email', 'internfeel' ); ?></div>
	<?php
}

/**
 * Print the footer's social links. None are shown until their profile URLs are set through the
 * if_social_links filter, so the footer never links to a page that does not exist.
 */
function if_social_links(): void {
	$icons = array(
		'linkedin'  => array( __( 'InternFeel on LinkedIn', 'internfeel' ), '<rect x="2" y="2" width="20" height="20" rx="4"/><path d="M7 10v7M7 7v.01M11 17v-4a2.5 2.5 0 0 1 5 0v4M11 10v7"/>' ),
		'instagram' => array( __( 'InternFeel on Instagram', 'internfeel' ), '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5v.01"/>' ),
		'facebook'  => array( __( 'InternFeel on Facebook', 'internfeel' ), '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>' ),
		'threads'   => array( __( 'InternFeel on Threads', 'internfeel' ), '<circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/>' ),
	);

	/**
	 * Footer social profile URLs, keyed linkedin, instagram, facebook and threads.
	 *
	 * @param array<string, string> $urls Profile URLs.
	 */
	$urls = (array) apply_filters( 'if_social_links', array() );
	$urls = array_filter( array_intersect_key( $urls, $icons ) );
	if ( array() === $urls ) {
		return;
	}

	$svg = array(
		'rect'   => array(
			'x'      => true,
			'y'      => true,
			'width'  => true,
			'height' => true,
			'rx'     => true,
		),
		'path'   => array( 'd' => true ),
		'circle' => array(
			'cx' => true,
			'cy' => true,
			'r'  => true,
		),
	);

	echo '<div class="if-social">';
	foreach ( $urls as $network => $url ) {
		printf(
			'<a href="%1$s" aria-label="%2$s"><svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true">%3$s</svg></a>',
			esc_url( (string) $url ),
			esc_attr( $icons[ $network ][0] ),
			wp_kses( $icons[ $network ][1], $svg )
		);
	}
	echo '</div>';
}

/**
 * Print the InternFeel logo, linked to the home page.
 */
function if_the_logo(): void {
	printf(
		'<a class="if-header__logo" href="%1$s" rel="home"><img src="%2$s" width="129" height="36" alt="%3$s"></a>',
		esc_url( home_url( '/' ) ),
		esc_url( get_theme_file_uri( 'assets/img/internfeel-logo.png' ) ),
		esc_attr__( 'InternFeel home', 'internfeel' )
	);
}

/**
 * The badge symbols after a name: one per verified experience; above 10, ten symbols and the number
 * (plan.md 5.1). Prints nothing for 0.
 *
 * @param int $count Verified experiences.
 */
function if_the_badges( int $count ): void {
	if ( $count <= 0 ) {
		return;
	}

	/* translators: %d: number of verified experiences. */
	$label = sprintf( _n( '%d verified experience', '%d verified experiences', $count, 'internfeel' ), $count );
	$icon  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 1 8l11 5 9-4.1V15h2V8z"/><path d="M5.5 11.3v3.4c0 1.8 2.9 3.3 6.5 3.3s6.5-1.5 6.5-3.3v-3.4L12 14.2z"/></svg>';

	echo '<span class="if-badges" role="img" aria-label="' . esc_attr( $label ) . '">';
	echo str_repeat( $icon, min( $count, 10 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup.
	if ( $count > 10 ) {
		echo '<span class="if-badges__more" aria-hidden="true">' . esc_html( number_format_i18n( $count ) ) . '</span>';
	}
	echo '</span>';
}

/**
 * The rating bar: five segments, halves allowed, then the value out of 5.
 *
 * @param float $rating 0 to 5, in steps of 0.5.
 */
function if_the_rating( float $rating ): void {
	/* translators: %s: rating, such as 4.5. */
	$label = sprintf( __( 'Rated %s out of 5', 'internfeel' ), number_format_i18n( $rating, 1 ) );

	echo '<span class="if-rating" role="img" aria-label="' . esc_attr( $label ) . '"><span class="if-rating__bar" aria-hidden="true">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$class = $rating >= $i ? 'on' : ( $rating >= $i - 0.5 ? 'half' : '' );
		echo '<i class="' . esc_attr( $class ) . '"></i>';
	}
	echo '</span><span class="if-rating__value" aria-hidden="true">' . esc_html( number_format_i18n( $rating, 1 ) ) . '</span>';
	echo '<span class="if-rating__of" aria-hidden="true">/ 5</span></span>';
}

/**
 * The home page's rows: listicles with a home order (if_list, admin-curated), else three built-in rows
 * so the page is never empty before the first listicle exists. Rows without cards are left out.
 *
 * @return list<array{id: string, title: string, subtitle: string, url: string, cards: list<array<string, mixed>>}>
 */
function if_home_rows(): array {
	$rows  = array();
	$lists = get_posts(
		array(
			'post_type'      => 'if_list',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'meta_key'       => '_if_home_order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a handful of listicles.
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a handful of listicles.
				array(
					'key'     => '_if_home_order',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	foreach ( $lists as $list ) {
		$ids   = array_slice( array_map( 'intval', (array) get_post_meta( $list->ID, '_if_list_items', true ) ), 0, 6 );
		$cards = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && 'publish' === $post->post_status && count( $cards ) < 3 ) {
				$cards[] = \InternFeel\Search\Cards::data( $post );
			}
		}
		$rows[] = array(
			'id'       => 'if-row-' . $list->ID,
			'title'    => get_the_title( $list ),
			'subtitle' => wp_strip_all_tags( get_the_excerpt( $list ) ),
			'url'      => (string) get_permalink( $list ),
			'cards'    => $cards,
		);
	}

	if ( array() === $rows ) {
		$built_in = array(
			array( 'latest', __( 'Latest experiences', 'internfeel' ), __( 'Newly published by students', 'internfeel' ), array() ),
			array(
				'research',
				__( 'Top research internships', 'internfeel' ),
				__( 'Labs and institutes, highest rated first', 'internfeel' ),
				array(
					'type' => 'research',
					'sort' => 'rating',
				),
			),
			array(
				'paid-india',
				__( 'Paid internships in India', 'internfeel' ),
				__( 'With a stipend, in India', 'internfeel' ),
				array(
					'pay'      => 'paid',
					'location' => 'India',
				),
			),
		);
		foreach ( $built_in as $row ) {
			$filters = \InternFeel\Search\Search::filters( $row[3] );
			$rows[]  = array(
				'id'       => 'if-row-' . $row[0],
				'title'    => $row[1],
				'subtitle' => $row[2],
				'url'      => add_query_arg( $row[3], home_url( '/experiences/' ) ),
				'cards'    => \InternFeel\Search\Search::run( $filters, 3 )['cards'],
			);
		}
	}

	return array_values( array_filter( $rows, static fn( array $row ): bool => array() !== $row['cards'] ) );
}

/**
 * Print a list of experience cards.
 *
 * @param list<array<string, mixed>> $cards   Card data from InternFeel\Search\Cards::data().
 * @param string                     $heading Heading tag for the card titles.
 */
function if_the_cards( array $cards, string $heading = 'h3' ): void {
	foreach ( $cards as $card ) {
		get_template_part(
			'template-parts/card',
			null,
			array(
				'card'    => $card,
				'heading' => $heading,
			)
		);
	}
}
