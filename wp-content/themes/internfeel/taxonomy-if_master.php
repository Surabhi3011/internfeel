<?php
/**
 * Hub page for an organisation, programme or field (/org/{slug}/), from the approved-designs README
 * "Programme hub": the name as the title, a key-facts box, the programmes (for an organisation), then
 * the cards.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Search\Search;

$if_term = get_queried_object();
if ( ! $if_term instanceof WP_Term || ! if_core_active() ) {
	get_template_part( 'index' );
	return;
}

$if_kind   = (string) get_term_meta( $if_term->term_id, '_if_kind', true );
$if_parent = $if_term->parent > 0 ? get_term( $if_term->parent, $if_term->taxonomy ) : null;
$if_facts  = Search::hub_facts( $if_term->term_id );
$if_cards  = Search::run( Search::filters( array( 'organisation' => (string) $if_term->term_id ) ), 24 );
$if_kids   = 'organisation' === $if_kind ? get_terms(
	array(
		'taxonomy'   => $if_term->taxonomy,
		'parent'     => $if_term->term_id,
		'hide_empty' => true,
	)
) : array();
$if_kids   = is_array( $if_kids ) ? $if_kids : array();
$if_labels = array(
	'organisation' => __( 'Organisation', 'internfeel' ),
	'programme'    => __( 'Programme', 'internfeel' ),
	'field'        => __( 'Field', 'internfeel' ),
);

get_header();
?>
<div class="if-container if-hub">
	<nav class="small if-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'internfeel' ); ?>">
		<a href="<?php echo esc_url( home_url( '/org/' ) ); ?>"><?php esc_html_e( 'Programmes', 'internfeel' ); ?></a>
		<?php if ( $if_parent instanceof WP_Term ) : ?>
			<span aria-hidden="true">›</span> <a href="<?php echo esc_url( (string) get_term_link( $if_parent ) ); ?>"><?php echo esc_html( $if_parent->name ); ?></a>
		<?php endif; ?>
	</nav>

	<div class="if-hub__head">
		<?php if ( isset( $if_labels[ $if_kind ] ) ) : ?>
			<span class="label if-teal-text"><?php echo esc_html( $if_labels[ $if_kind ] ); ?></span>
		<?php endif; ?>
		<h1 class="heading-1">
			<?php echo esc_html( $if_term->name ); ?>
			<?php if ( $if_parent instanceof WP_Term ) : ?>
				<span class="if-hub__at"><?php echo esc_html( $if_parent->name ); ?></span>
			<?php endif; ?>
		</h1>
		<?php if ( '' !== $if_term->description ) : ?>
			<p class="body-lg if-muted"><?php echo esc_html( $if_term->description ); ?></p>
		<?php endif; ?>
	</div>

	<section class="if-facts" aria-label="<?php esc_attr_e( 'Key facts', 'internfeel' ); ?>">
		<dl>
			<div>
				<dt><?php esc_html_e( 'Experiences', 'internfeel' ); ?></dt>
				<dd><?php echo esc_html( number_format_i18n( $if_facts['experiences'] ) ); ?></dd>
			</div>
			<?php if ( $if_facts['rating'] > 0 ) : ?>
				<div>
					<dt><?php esc_html_e( 'Average rating', 'internfeel' ); ?></dt>
					<dd>
						<?php
						/* translators: %s: rating, such as 4.3. */
						echo esc_html( sprintf( __( '%s of 5', 'internfeel' ), number_format_i18n( $if_facts['rating'], 1 ) ) );
						?>
					</dd>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $if_facts['band'] ) : ?>
				<div>
					<dt><?php esc_html_e( 'Typical stipend', 'internfeel' ); ?></dt>
					<dd><?php echo esc_html( $if_facts['band'] ); ?><small><?php esc_html_e( 'a month, when paid', 'internfeel' ); ?></small></dd>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $if_facts['months'] ) : ?>
				<div>
					<dt><?php esc_html_e( 'Usually starts', 'internfeel' ); ?></dt>
					<dd><?php echo esc_html( implode( __( ' or ', 'internfeel' ), $if_facts['months'] ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	</section>

	<?php if ( array() !== $if_kids ) : ?>
		<section class="if-hub__programmes" aria-labelledby="if-programmes-title">
			<h2 class="heading-3" id="if-programmes-title"><?php esc_html_e( 'Programmes', 'internfeel' ); ?></h2>
			<ul class="if-chips">
				<?php foreach ( $if_kids as $if_kid ) : ?>
					<li><a class="if-chip" href="<?php echo esc_url( (string) get_term_link( $if_kid ) ); ?>"><?php echo esc_html( $if_kid->name ); ?> <span class="if-chip__count"><?php echo esc_html( number_format_i18n( $if_kid->count ) ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() === $if_cards['cards'] ) : ?>
		<p class="if-muted"><?php esc_html_e( 'No published experiences here yet.', 'internfeel' ); ?></p>
	<?php else : ?>
		<div class="if-grid"><?php if_the_cards( $if_cards['cards'], 'h2' ); ?></div>
		<?php if ( $if_cards['total'] > count( $if_cards['cards'] ) ) : ?>
			<a class="if-btn if-results__more" href="<?php echo esc_url( add_query_arg( 'organisation', $if_term->term_id, home_url( '/experiences/' ) ) ); ?>"><?php esc_html_e( 'See them all', 'internfeel' ); ?></a>
		<?php endif; ?>
		<?php if_the_ad( 'hub' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
