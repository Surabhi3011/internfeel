<?php
/**
 * The Invite friends panel (dashboard sidebar and Invites tab). Invite links, the /join page and
 * referral credit arrive on day 8; until then the panel explains how invites are earned.
 *
 * @package InternFeel
 *
 * @var array{credits?: int} $args
 */

defined( 'ABSPATH' ) || exit;

$if_credits = (int) ( $args['credits'] ?? 0 );
?>
<section class="if-dash__invite">
	<div>
		<h2 class="heading-3"><?php esc_html_e( 'Invite friends', 'internfeel' ); ?></h2>
		<p class="small if-muted"><?php esc_html_e( 'You earn 3 invites for every experience that is published and verified. Each link works once and never expires.', 'internfeel' ); ?></p>
	</div>
	<p class="if-dash__credits">
		<span class="if-dash__credits-number"><?php echo esc_html( number_format_i18n( $if_credits ) ); ?></span>
		<span class="small">
			<?php
			/* translators: Shown after the number of invites, as in "3 invites left". */
			echo esc_html( _n( 'invite left', 'invites left', $if_credits, 'internfeel' ) );
			?>
		</span>
	</p>
	<?php if ( 0 === $if_credits ) : ?>
		<p class="small"><?php esc_html_e( 'Publish an experience and add proof to earn your first invites.', 'internfeel' ); ?></p>
	<?php endif; ?>
</section>
