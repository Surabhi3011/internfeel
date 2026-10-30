<?php
/**
 * The Browse band: filter menus, Verified only and the quick type chips (approved-designs README,
 * Home section 2), then the results grid. A plain GET form, so it works without JavaScript and every
 * filtered view has its own URL; browse.js swaps in live results and keeps the URL in step.
 *
 * @package InternFeel
 *
 * @var array{filters: array<string, mixed>, results: array{cards: list<array<string, mixed>>, total: int, pages: int}|null, action: string, title: string, show_results?: bool} $args
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Search\Cards;
use InternFeel\Search\Search;

$if_filters = $args['filters'];
$if_results = $args['results'];
$if_show    = $args['show_results'] ?? null !== $if_results;
$if_choices = Search::choices();
$if_counts  = Search::counts();
$if_pay     = array(
	'paid'    => __( 'Paid', 'internfeel' ),
	'unpaid'  => __( 'Unpaid', 'internfeel' ),
	'under10' => __( 'Paid, under ₹10k a month', 'internfeel' ),
	'10to25'  => __( 'Paid, ₹10k–25k a month', 'internfeel' ),
	'over25'  => __( 'Paid, above ₹25k a month', 'internfeel' ),
);

/**
 * One filter menu: a native select dressed as a menu chip.
 *
 * @param string               $name    Query parameter.
 * @param string               $label   Visible label.
 * @param array<string, string> $options Value => label.
 * @param string               $current Selected value.
 */
$if_menu = static function ( string $name, string $label, array $options, string $current ): void {
	$id = 'if-filter-' . $name;
	?>
	<span class="if-chip if-chip--menu if-chip--select<?php echo '' !== $current ? ' is-selected' : ''; ?>">
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<option value=""><?php echo esc_html( $label ); ?></option>
			<?php foreach ( $options as $value => $text ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) $value, $current ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
	</span>
	<?php
};
?>
<section id="browse" class="if-browse" aria-labelledby="if-browse-title">
	<div class="if-container if-browse__inner">
		<div class="if-browse__head">
			<h2 class="heading-2" id="if-browse-title"><?php echo esc_html( $args['title'] ); ?></h2>
			<span class="small if-muted" data-if-count>
				<?php
				$if_total = null === $if_results ? $if_counts['experiences'] : $if_results['total'];
				/* translators: %s: number of experiences. */
				echo esc_html( sprintf( _n( '%s experience', '%s experiences', $if_total, 'internfeel' ), number_format_i18n( $if_total ) ) );
				?>
			</span>
		</div>

		<form class="if-browse__form" method="get" action="<?php echo esc_url( $args['action'] ); ?>" role="search" data-if-browse data-rest="<?php echo esc_url( rest_url( 'internfeel/v1/experiences' ) ); ?>"<?php echo ( $args['show_results'] ?? false ) && ! Search::is_filtered( $if_filters ) ? ' data-always-results' : ''; ?>>
			<div class="if-browse__row">
				<span class="if-search if-browse__search">
					<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<label class="screen-reader-text" for="if-filter-q"><?php esc_html_e( 'Search experiences', 'internfeel' ); ?></label>
					<input id="if-filter-q" type="search" name="q" value="<?php echo esc_attr( (string) $if_filters['q'] ); ?>" placeholder="<?php esc_attr_e( 'Search experiences', 'internfeel' ); ?>">
				</span>
				<?php
				$if_menu( 'field', __( 'Field', 'internfeel' ), array_map( 'strval', $if_choices['field'] ), $if_filters['field'] > 0 ? (string) $if_filters['field'] : '' );
				$if_menu( 'organisation', __( 'Organisation', 'internfeel' ), array_map( 'strval', $if_choices['organisation'] ), $if_filters['organisation'] > 0 ? (string) $if_filters['organisation'] : '' );
				$if_menu( 'location', __( 'Location', 'internfeel' ), array_combine( $if_choices['location'], $if_choices['location'] ), (string) $if_filters['location'] );
				$if_years = array_map( 'strval', $if_choices['year'] );
				$if_menu( 'year', __( 'Year', 'internfeel' ), array_combine( $if_years, $if_years ), $if_filters['year'] > 0 ? (string) $if_filters['year'] : '' );
				$if_menu( 'pay', __( 'Paid or unpaid', 'internfeel' ), $if_pay, (string) $if_filters['pay'] );
				?>
				<label class="if-chip if-chip--toggle<?php echo $if_filters['verified'] ? ' is-selected' : ''; ?>">
					<input type="checkbox" name="verified" value="1" <?php checked( $if_filters['verified'] ); ?>>
					<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg><?php esc_html_e( 'Verified only', 'internfeel' ); ?>
				</label>
			</div>

			<fieldset class="if-browse__row if-browse__types">
				<legend class="screen-reader-text"><?php esc_html_e( 'Type', 'internfeel' ); ?></legend>
				<?php foreach ( array( '' => __( 'All types', 'internfeel' ) ) + Cards::types() as $if_value => $if_label ) : ?>
					<label class="if-chip if-chip--toggle<?php echo $if_value === $if_filters['type'] ? ' is-selected' : ''; ?>">
						<input type="radio" name="type" value="<?php echo esc_attr( (string) $if_value ); ?>" <?php checked( (string) $if_value, $if_filters['type'] ); ?>>
						<?php echo esc_html( $if_label ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<div class="if-browse__submit">
				<button class="if-btn if-btn--sm" type="submit"><?php esc_html_e( 'Show results', 'internfeel' ); ?></button>
				<a class="small" href="<?php echo esc_url( $args['action'] ); ?>" data-if-clear<?php echo Search::is_filtered( $if_filters ) ? '' : ' hidden'; ?>><?php esc_html_e( 'Clear filters', 'internfeel' ); ?></a>
			</div>
		</form>
	</div>
</section>

<section class="if-container if-results" aria-label="<?php esc_attr_e( 'Results', 'internfeel' ); ?>" data-if-results<?php echo $if_show ? '' : ' hidden'; ?>>
	<p class="if-results__status" role="status" data-if-status></p>
	<div class="if-grid" data-if-grid>
		<?php
		if ( null !== $if_results ) {
			if_the_cards( $if_results['cards'] );
		}
		?>
	</div>
	<p class="if-results__empty" data-if-empty<?php echo null !== $if_results && 0 === $if_results['total'] ? '' : ' hidden'; ?>><?php esc_html_e( 'No experiences match. Remove a filter or try another field.', 'internfeel' ); ?></p>
	<?php
	$if_next = null !== $if_results && $if_filters['page'] < $if_results['pages'] ? add_query_arg( 'page', $if_filters['page'] + 1 ) : '';
	?>
	<a class="if-btn if-results__more" href="<?php echo esc_url( $if_next ); ?>" data-if-more<?php echo '' === $if_next ? ' hidden' : ''; ?>><?php esc_html_e( 'Load more', 'internfeel' ); ?></a>
</section>
