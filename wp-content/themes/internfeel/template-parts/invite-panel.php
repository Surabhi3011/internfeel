<?php
/**
 * The Invite friends panel (dashboard sidebar, and the Invites tab with its table of links), from
 * docs/approved-designs/dashboard.html. dashboard.js makes new links and copies them.
 *
 * @package InternFeel
 *
 * @var array{summary: array{credits: int, joined: int, published: int, invites: list<array{code: string, link: string, used: bool, used_by: string, used_at: string, published: int}>}, id: string, full?: bool} $args
 */

defined( 'ABSPATH' ) || exit;

$if_summary = $args['summary'];
$if_id      = 'if-invite-link-' . $args['id'];
$if_unused  = array_values( array_filter( $if_summary['invites'], static fn( array $invite ): bool => ! $invite['used'] ) );
$if_used    = count( $if_summary['invites'] ) - count( $if_unused );
$if_next    = $if_unused[0]['link'] ?? '';
$if_names   = array_filter( array_column( $if_summary['invites'], 'used_by' ) );
$if_text    = __( 'Read and share internship experiences on InternFeel. Here is my invite:', 'internfeel' );
?>
<section class="if-dash__invite" aria-labelledby="if-invite-title-<?php echo esc_attr( $args['id'] ); ?>">
	<div>
		<h2 class="heading-3" id="if-invite-title-<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Invite friends', 'internfeel' ); ?></h2>
		<p class="small if-muted"><?php esc_html_e( 'You earn 3 invites for every experience that is published and verified. Each link works once and never expires.', 'internfeel' ); ?></p>
	</div>
	<p class="if-dash__credits">
		<span class="if-dash__credits-number"><?php echo esc_html( number_format_i18n( $if_summary['credits'] ) ); ?></span>
		<span class="small">
			<?php
			/* translators: Shown after the number of invites, as in "3 invites left". */
			echo esc_html( _n( 'invite left', 'invites left', $if_summary['credits'], 'internfeel' ) );
			?>
		</span>
	</p>

	<?php if ( '' !== $if_next ) : ?>
		<div class="if-field">
			<label for="<?php echo esc_attr( $if_id ); ?>"><?php esc_html_e( 'Your invite link', 'internfeel' ); ?></label>
			<div class="if-dash__link">
				<input id="<?php echo esc_attr( $if_id ); ?>" class="mono" type="text" readonly value="<?php echo esc_attr( $if_next ); ?>">
				<button class="if-btn if-btn--primary if-btn--sm" type="button" data-if-copy="<?php echo esc_url( $if_next ); ?>"><span data-if-label><?php esc_html_e( 'Copy', 'internfeel' ); ?></span></button>
			</div>
		</div>
		<div class="if-dash__actions-plain">
			<a class="if-btn if-btn--sm" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $if_text . ' ' . $if_next ) ); ?>" target="_blank" rel="noopener">
				<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21l1.7-5A8.5 8.5 0 1 1 8 19.3z"/></svg><?php esc_html_e( 'WhatsApp', 'internfeel' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'internfeel' ); ?></span>
			</a>
			<a class="if-btn if-btn--sm" href="<?php echo esc_url( 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $if_next ) ); ?>" target="_blank" rel="noopener">
				<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="4"/><path d="M7 10v7M7 7v.01M11 17v-4a2.5 2.5 0 0 1 5 0v4M11 10v7"/></svg><?php esc_html_e( 'LinkedIn', 'internfeel' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'internfeel' ); ?></span>
			</a>
		</div>
		<p class="small if-muted"><?php esc_html_e( 'Each link works for one friend. Once it is used, the next one appears here.', 'internfeel' ); ?></p>
	<?php elseif ( $if_summary['credits'] > 0 ) : ?>
		<button class="if-btn if-btn--primary if-btn--sm" type="button" data-if-new-invite><?php esc_html_e( 'Make an invite link', 'internfeel' ); ?></button>
	<?php else : ?>
		<p class="small"><?php esc_html_e( 'Publish an experience and add proof to earn your first invites.', 'internfeel' ); ?></p>
	<?php endif; ?>

	<?php if ( array() !== $if_summary['invites'] ) : ?>
		<dl class="small if-dash__invite-stats">
			<dt><?php esc_html_e( 'Links used', 'internfeel' ); ?></dt>
			<dd>
				<?php
				/* translators: 1: links used, 2: links made. */
				echo esc_html( sprintf( __( '%1$d of %2$d', 'internfeel' ), $if_used, count( $if_summary['invites'] ) ) );
				?>
			</dd>
			<dt><?php esc_html_e( 'Friends who joined', 'internfeel' ); ?></dt>
			<dd><?php echo esc_html( array() === $if_names ? '0' : implode( ', ', $if_names ) ); ?></dd>
			<dt><?php esc_html_e( 'Their published experiences', 'internfeel' ); ?></dt>
			<dd><?php echo esc_html( number_format_i18n( $if_summary['published'] ) ); ?></dd>
		</dl>
	<?php endif; ?>
</section>

<?php if ( ! empty( $args['full'] ) && array() !== $if_summary['invites'] ) : ?>
	<div class="if-dash__table-wrap">
		<table class="if-dash__table">
			<caption class="screen-reader-text"><?php esc_html_e( 'Your invite links', 'internfeel' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Link', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Used by', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Joined', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Published', 'internfeel' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $if_summary['invites'] as $if_invite ) : ?>
					<tr>
						<td class="mono"><?php echo esc_html( $if_invite['code'] ); ?></td>
						<td><?php echo $if_invite['used'] ? esc_html__( 'Used', 'internfeel' ) : esc_html__( 'Unused', 'internfeel' ); ?></td>
						<td><?php echo esc_html( $if_invite['used_by'] ); ?></td>
						<td><?php echo '' === $if_invite['used_at'] ? '' : esc_html( (string) wp_date( 'j M Y', (int) strtotime( $if_invite['used_at'] . ' UTC' ) ) ); ?></td>
						<td><?php echo $if_invite['used'] ? esc_html( number_format_i18n( $if_invite['published'] ) ) : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
