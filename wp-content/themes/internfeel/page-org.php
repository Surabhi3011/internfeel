<?php
/**
 * Programmes (/org/): every organisation with published experiences, A to Z, with its programmes.
 * Each links to its hub page (taxonomy-if_master.php).
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

$if_orgs = get_terms(
	array(
		'taxonomy'   => 'if_master',
		'parent'     => 0,
		'hide_empty' => true,
		'orderby'    => 'name',
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-curated list, cached by WordPress.
			array(
				'key'   => '_if_kind',
				'value' => 'organisation',
			),
		),
	)
);
$if_orgs = is_array( $if_orgs ) ? $if_orgs : array();

get_header();
?>
<div class="if-container if-hub">
	<div class="if-hub__head">
		<h1 class="heading-1"><?php esc_html_e( 'Programmes and organisations', 'internfeel' ); ?></h1>
		<p class="body-lg if-muted"><?php esc_html_e( 'Where students have interned, and what they said about it.', 'internfeel' ); ?></p>
	</div>

	<?php if ( array() === $if_orgs ) : ?>
		<p><?php esc_html_e( 'Organisations appear here once their experiences are published.', 'internfeel' ); ?></p>
	<?php else : ?>
		<ul class="if-list__all">
			<?php foreach ( $if_orgs as $if_org ) : ?>
				<?php
				$if_programmes = get_terms(
					array(
						'taxonomy'   => 'if_master',
						'parent'     => $if_org->term_id,
						'hide_empty' => true,
					)
				);
				?>
				<li class="if-list__item">
					<h2 class="heading-3"><a href="<?php echo esc_url( (string) get_term_link( $if_org ) ); ?>"><?php echo esc_html( $if_org->name ); ?></a></h2>
					<span class="small if-muted">
						<?php
						/* translators: %s: number of experiences. */
						echo esc_html( sprintf( _n( '%s experience', '%s experiences', $if_org->count, 'internfeel' ), number_format_i18n( $if_org->count ) ) );
						?>
					</span>
					<?php if ( is_array( $if_programmes ) && array() !== $if_programmes ) : ?>
						<ul class="if-chips">
							<?php foreach ( $if_programmes as $if_programme ) : ?>
								<li><a class="if-chip" href="<?php echo esc_url( (string) get_term_link( $if_programme ) ); ?>"><?php echo esc_html( $if_programme->name ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
<?php
get_footer();
