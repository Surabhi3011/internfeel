<?php
/**
 * One experience card (.if-card), from InternFeel\Search\Cards::data(). assets/js/cards.js builds the
 * same markup for live results; keep the two in step.
 *
 * @package InternFeel
 *
 * @var array{card: array<string, mixed>, heading?: string} $args
 */

defined( 'ABSPATH' ) || exit;

$if_card  = $args['card'];
$if_tag   = $args['heading'] ?? 'h3';
$if_facts = array_filter( array( $if_card['type'], $if_card['field'], $if_card['when'], $if_card['pay'] ) );
$if_stars = (float) $if_card['rating'];
?>
<article class="if-card">
	<?php if ( '' !== $if_card['organisation'] ) : ?>
		<div class="if-card__top">
			<span class="if-card__org" aria-hidden="true"><?php echo esc_html( (string) $if_card['initial'] ); ?></span>
			<span class="if-card__orgname"><strong><?php echo esc_html( (string) $if_card['organisation'] ); ?></strong><?php echo esc_html( (string) $if_card['place'] ); ?></span>
		</div>
	<?php endif; ?>
	<<?php echo tag_escape( $if_tag ); ?> class="if-card__title"><a href="<?php echo esc_url( (string) $if_card['url'] ); ?>"><?php echo esc_html( (string) $if_card['title'] ); ?></a></<?php echo tag_escape( $if_tag ); ?>>
	<?php if ( array() !== $if_facts ) : ?>
		<ul class="if-card__facts">
			<?php foreach ( $if_facts as $if_fact ) : ?>
				<li><?php echo esc_html( (string) $if_fact ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<div class="if-card__foot">
		<span class="if-card__marks">
			<?php if ( $if_stars > 0 ) : ?>
				<?php if_the_rating( $if_stars ); ?>
			<?php endif; ?>
			<?php if ( $if_card['verified'] ) : ?>
				<span class="if-verified"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg><?php esc_html_e( 'Verified', 'internfeel' ); ?></span>
			<?php endif; ?>
		</span>
		<span class="if-card__views">
			<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
			<?php echo esc_html( number_format_i18n( (int) $if_card['views'] ) ); ?><span class="screen-reader-text"> <?php esc_html_e( 'views', 'internfeel' ); ?></span>
		</span>
	</div>
	<?php if ( $if_card['invited'] ) : ?>
		<span class="if-card__credit">
			<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-3.9 3.1-7 7-7s7 3.1 7 7"/><path d="M19 8v6M16 11h6"/></svg><?php esc_html_e( 'Shared through an invite', 'internfeel' ); ?>
		</span>
	<?php endif; ?>
</article>
