<?php
/**
 * Page not found, on the brand line "peep before you leap": the reader leapt to a page that is not
 * there. Beside the message sits an experience card for the internship that never happened, with a
 * pair of eyes peeping over its edge. Then the search, the internship types and the ways back in.
 * No ads (inc/ads.php skips 404s).
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

$if_types = class_exists( '\InternFeel\Search\Cards' ) ? \InternFeel\Search\Cards::types() : array();

get_header();
?>
<section class="if-404" aria-labelledby="if-404-title">
	<div class="if-container if-404__inner">
		<div class="if-404__text">
			<span class="label if-404__label"><?php esc_html_e( 'Error 404 · Page not found', 'internfeel' ); ?></span>
			<h1 class="display if-404__title" id="if-404-title"><?php esc_html_e( 'You leapt before you peeped.', 'internfeel' ); ?></h1>
			<p class="body-lg if-muted"><?php esc_html_e( 'This page has moved, or it was never here at all. Have a proper look around instead: there are real experiences waiting on the other side.', 'internfeel' ); ?></p>

			<form class="if-404__search" role="search" method="get" action="<?php echo esc_url( home_url( '/experiences/' ) ); ?>">
				<span class="if-search">
					<svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<label class="screen-reader-text" for="if-404-q"><?php esc_html_e( 'Search experiences', 'internfeel' ); ?></label>
					<input id="if-404-q" type="search" name="q" placeholder="<?php esc_attr_e( 'Organisation, programme or field', 'internfeel' ); ?>">
				</span>
				<button class="if-btn" type="submit"><?php esc_html_e( 'Search', 'internfeel' ); ?></button>
			</form>

			<?php if ( array() !== $if_types ) : ?>
				<nav class="if-404__types" aria-label="<?php esc_attr_e( 'Browse by type', 'internfeel' ); ?>">
					<span class="small if-muted"><?php esc_html_e( 'Or peep at:', 'internfeel' ); ?></span>
					<ul class="if-chips">
						<?php foreach ( $if_types as $if_slug => $if_name ) : ?>
							<li><a class="if-chip" href="<?php echo esc_url( add_query_arg( 'type', $if_slug, home_url( '/experiences/' ) ) ); ?>"><?php echo esc_html( $if_name ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<p class="if-404__actions">
				<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the home page', 'internfeel' ); ?></a>
				<a class="if-btn if-btn--ghost" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>"><?php esc_html_e( 'Share a real experience', 'internfeel' ); ?></a>
			</p>
		</div>

		<?php // The joke card is decoration: screen readers already have the message above. ?>
		<div class="if-404__art" aria-hidden="true">
			<svg class="if-404__eyes" viewBox="0 0 120 50" focusable="false">
				<path class="if-404__brow" d="M22 8l20 6M98 8l-20 6"/>
				<g class="if-404__lids">
					<circle class="if-404__eye" cx="34" cy="36" r="13"/>
					<circle class="if-404__eye" cx="86" cy="36" r="13"/>
					<circle class="if-404__pupil" cx="37" cy="33" r="5.5"/>
					<circle class="if-404__pupil" cx="89" cy="33" r="5.5"/>
				</g>
			</svg>
			<div class="if-card if-404__card">
				<div class="if-card__top">
					<span class="if-card__org">?</span>
					<span class="if-card__orgname"><strong><?php esc_html_e( 'Organisation not found', 'internfeel' ); ?></strong><?php esc_html_e( 'Somewhere, Nowhere', 'internfeel' ); ?></span>
				</div>
				<p class="if-card__title"><?php esc_html_e( 'The internship that never happened', 'internfeel' ); ?></p>
				<ul class="if-card__facts">
					<li><?php esc_html_e( '0 days', 'internfeel' ); ?></li>
					<li><?php esc_html_e( 'Unpaid, and unfound', 'internfeel' ); ?></li>
					<li><?php esc_html_e( 'Year unknown', 'internfeel' ); ?></li>
				</ul>
				<div class="if-404__rating">
					<span class="if-404__score">0<span>/10</span></span>
					<span class="small if-muted"><?php esc_html_e( '“Would not recommend. Could not find the office.”', 'internfeel' ); ?></span>
				</div>
				<div class="if-card__foot">
					<span class="if-status if-status--rejected"><?php esc_html_e( 'Not found', 'internfeel' ); ?></span>
					<span class="mono">IF-404-000000</span>
				</div>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
