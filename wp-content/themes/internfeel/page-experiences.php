<?php
/**
 * Experiences archive and search results (/experiences/?…), from the approved-designs README "Pages
 * not drawn": the Browse band, then the results grid with Load more. Ad slots arrive on day 11.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Search\Search;

if ( ! if_core_active() ) {
	get_header();
	get_footer();
	return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filters.
$if_filters = Search::filters( $_GET );

get_header();
?>
<div class="if-container if-archive__intro">
	<h1 class="heading-1"><?php esc_html_e( 'Internship experiences', 'internfeel' ); ?></h1>
	<p class="if-muted"><?php esc_html_e( 'Written by students about research, industry, government, NGO and virtual internships.', 'internfeel' ); ?></p>
</div>
<?php
get_template_part(
	'template-parts/browse',
	null,
	array(
		'filters'      => $if_filters,
		'results'      => Search::run( $if_filters ),
		'action'       => (string) get_permalink(),
		'title'        => __( 'Filter experiences', 'internfeel' ),
		'show_results' => true,
	)
);
get_footer();
