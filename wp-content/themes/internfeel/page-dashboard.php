<?php
/**
 * Student dashboard (/dashboard/), from docs/approved-designs/dashboard.html: counters, four tabs
 * (My experiences, Comments, Invites, Profile) and a sidebar with updates and invites. Signed in
 * only; no ads. dashboard.js switches tabs and handles proof upload, draft deletion and sharing.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Auth\Profile;
use InternFeel\Certificates\Certificates;
use InternFeel\Dashboard\Dashboard;
use InternFeel\Notify\Notify;
use InternFeel\Submit\Submit;

if_no_page_cache();

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( home_url( '/dashboard/' ) ), home_url( '/login/' ) ) );
	exit;
}
if ( ! if_core_active() ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$if_user_id = get_current_user_id();
if ( ! Profile::is_complete( $if_user_id ) ) {
	wp_safe_redirect( home_url( '/signup/' ) );
	exit;
}

$if_profile  = Profile::get( $if_user_id );
$if_user     = wp_get_current_user();
$if_name     = '' !== (string) $if_profile['display_name'] ? (string) $if_profile['display_name'] : (string) $if_profile['full_name'];
$if_stats    = Dashboard::stats( $if_user_id );
$if_items    = Dashboard::experiences( $if_user_id );
$if_comments = Dashboard::comments( $if_user_id );
$if_notes    = Notify::all( $if_user_id );
$if_verified = (int) get_user_meta( $if_user_id, '_if_verified_count', true );
$if_invites  = \InternFeel\Invites\Invites::summary( $if_user_id );
$if_nonce    = wp_create_nonce( 'wp_rest' );
$if_steps    = array(
	1 => __( 'where and what', 'internfeel' ),
	2 => __( 'your story', 'internfeel' ),
	3 => __( 'ratings and advice', 'internfeel' ),
	4 => __( 'review and submit', 'internfeel' ),
);

/**
 * A GMT database date in the site's time zone.
 *
 * @param string $gmt    Date in GMT, Y-m-d H:i:s.
 * @param string $format Date format.
 * @return string
 */
$if_date = static function ( string $gmt, string $format = 'j M Y' ): string {
	$time = strtotime( $gmt . ' UTC' );

	return '' === $gmt || false === $time || $time <= 0 ? '' : (string) wp_date( $format, $time );
};

/**
 * Initials for the avatar.
 *
 * @param string $name Name.
 * @return string
 */
$if_initials = static function ( string $name ): string {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$parts = false === $parts ? array() : $parts;
	$first = mb_substr( (string) ( $parts[0] ?? '' ), 0, 1 );
	$last  = count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '';

	return mb_strtoupper( $first . $last );
};

$if_tabs = array(
	'experiences' => __( 'My experiences', 'internfeel' ),
	'comments'    => __( 'Comments', 'internfeel' ),
	'invites'     => __( 'Invites', 'internfeel' ),
	'profile'     => __( 'Profile', 'internfeel' ),
);

$if_details = array_filter( array( (string) $if_profile['course'], (string) $if_profile['college'] ) );
$if_year    = (int) $if_profile['graduation_year'];

get_header();
?>
<div class="if-container if-dash" data-if-dashboard data-nonce="<?php echo esc_attr( $if_nonce ); ?>" data-rest="<?php echo esc_url( rest_url( 'internfeel/v1/' ) ); ?>">
	<div class="if-dash__head">
		<div class="if-dash__who">
			<span class="if-avatar if-avatar--lg" aria-hidden="true"><?php echo esc_html( $if_initials( $if_name ) ); ?></span>
			<div class="if-dash__name">
				<h1 class="heading-1 if-dash__title"><?php esc_html_e( 'Your dashboard', 'internfeel' ); ?></h1>
				<span class="if-dash__byline">
					<span class="if-byline"><?php echo esc_html( $if_name ); ?><?php if_the_badges( $if_verified ); ?></span>
					<span class="small if-muted">
						<?php
						echo esc_html( implode( ', ', $if_details ) );
						if ( $if_year > 0 ) {
							/* translators: %d: graduation year. */
							echo esc_html( ( array() === $if_details ? '' : ' · ' ) . sprintf( __( 'Class of %d', 'internfeel' ), $if_year ) );
						}
						?>
					</span>
				</span>
			</div>
		</div>
		<a class="if-btn if-btn--primary" href="<?php echo esc_url( Submit::url() ); ?>">
			<svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><?php esc_html_e( 'Add experience', 'internfeel' ); ?>
		</a>
	</div>

	<section class="if-dash__counters" aria-label="<?php esc_attr_e( 'Your numbers', 'internfeel' ); ?>">
		<?php
		$if_counters = array(
			array( $if_stats['published'], __( 'experiences published', 'internfeel' ) ),
			array( $if_stats['views'], __( 'total views', 'internfeel' ) ),
			array( $if_stats['comments'], __( 'comments received', 'internfeel' ) ),
			array( $if_stats['joined'], __( 'friends who joined through you', 'internfeel' ) ),
		);
		foreach ( $if_counters as $if_counter ) :
			?>
			<div class="if-dash__counter">
				<span class="if-dash__number"><?php echo esc_html( number_format_i18n( $if_counter[0] ) ); ?></span>
				<span class="small if-muted"><?php echo esc_html( $if_counter[1] ); ?></span>
			</div>
		<?php endforeach; ?>
	</section>

	<div class="if-dash__layout">
		<div class="if-dash__main">
			<div class="if-dash__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Dashboard sections', 'internfeel' ); ?>">
				<?php foreach ( $if_tabs as $if_key => $if_label ) : ?>
					<button class="if-dash__tab" type="button" role="tab" id="if-tab-<?php echo esc_attr( $if_key ); ?>" aria-controls="if-panel-<?php echo esc_attr( $if_key ); ?>" aria-selected="<?php echo 'experiences' === $if_key ? 'true' : 'false'; ?>"<?php echo 'experiences' === $if_key ? '' : ' tabindex="-1"'; ?>><?php echo esc_html( $if_label ); ?></button>
				<?php endforeach; ?>
			</div>

			<p class="if-field__error if-form__status" role="alert" data-if-status></p>

			<section class="if-dash__panel" role="tabpanel" id="if-panel-experiences" aria-labelledby="if-tab-experiences">
				<?php if ( array() === $if_items ) : ?>
					<div class="if-dash__empty">
						<p><?php esc_html_e( 'You have not shared an experience yet. It takes about 20 minutes, and your draft saves as you go.', 'internfeel' ); ?></p>
						<a class="if-btn if-btn--primary if-btn--sm" href="<?php echo esc_url( Submit::url() ); ?>"><?php esc_html_e( 'Share your first experience', 'internfeel' ); ?></a>
					</div>
				<?php endif; ?>

				<?php foreach ( $if_items as $if_item ) : ?>
					<?php
					$if_status = $if_item['status'];
					$if_proof  = $if_item['proof'];
					$if_live   = 'publish' === $if_status;
					$if_pills  = array(
						'draft'    => array( '', __( 'Draft', 'internfeel' ) ),
						'pending'  => array( 'review', __( 'In review', 'internfeel' ) ),
						'publish'  => array( 'published', __( 'Published', 'internfeel' ) ),
						'rejected' => array( 'rejected', __( 'Rejected', 'internfeel' ) ),
						'removed'  => array( 'removed', __( 'Removed', 'internfeel' ) ),
					);
					$if_proofs = array(
						'verified' => array( 'verified', __( 'Verified', 'internfeel' ) ),
						'pending'  => array( 'pending', __( 'Proof pending', 'internfeel' ) ),
						'rejected' => array( 'rejected', __( 'Proof rejected', 'internfeel' ) ),
					);
					$if_pill   = $if_pills[ $if_status ] ?? $if_pills['draft'];
					$if_meta   = implode( ' · ', array_filter( array( $if_item['organisation'], $if_item['programme'] ) ) );
					$if_upload = in_array( $if_status, array( 'pending', 'publish' ), true ) && 'verified' !== $if_proof && 'pending' !== $if_proof;
					?>
					<article class="if-dash__item" data-id="<?php echo esc_attr( (string) $if_item['id'] ); ?>">
						<div class="if-dash__pills">
							<span class="if-status<?php echo '' !== $if_pill[0] ? ' if-status--' . esc_attr( $if_pill[0] ) : ''; ?>"><?php echo esc_html( $if_pill[1] ); ?></span>
							<?php if ( isset( $if_proofs[ $if_proof ] ) ) : ?>
								<span class="if-status if-status--<?php echo esc_attr( $if_proofs[ $if_proof ][0] ); ?>"><?php echo esc_html( $if_proofs[ $if_proof ][1] ); ?></span>
							<?php elseif ( 'pending' === $if_status ) : ?>
								<span class="if-status"><?php esc_html_e( 'Proof not added', 'internfeel' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="if-dash__title-block">
							<h2 class="heading-3">
								<?php if ( in_array( $if_status, array( 'publish', 'draft' ), true ) ) : ?>
									<a href="<?php echo esc_url( $if_item['link'] ); ?>"><?php echo esc_html( $if_item['title'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $if_item['title'] ); ?>
								<?php endif; ?>
							</h2>
							<span class="small if-muted">
								<?php
								if ( $if_live ) {
									/* translators: %s: date. */
									echo esc_html( implode( ' · ', array_filter( array( $if_meta, sprintf( __( 'Published %s', 'internfeel' ), $if_date( $if_item['date'] ) ) ) ) ) );
								} elseif ( 'pending' === $if_status ) {
									/* translators: %s: date and time. */
									echo esc_html( sprintf( __( 'Submitted %s. We review every experience within 24 hours.', 'internfeel' ), $if_date( '' !== $if_item['submitted'] ? $if_item['submitted'] : $if_item['modified'], 'j M Y, g:i a' ) ) );
								} elseif ( 'draft' === $if_status ) {
									/* translators: 1: time ago, 2: step number, 3: step name. */
									echo esc_html( sprintf( __( 'Saved %1$s ago · step %2$d of 4, %3$s', 'internfeel' ), human_time_diff( (int) strtotime( $if_item['modified'] . ' UTC' ) ), $if_item['step'], $if_steps[ $if_item['step'] ] ) );
								} else {
									echo esc_html( $if_meta );
								}
								?>
							</span>
						</div>

						<?php if ( $if_live ) : ?>
							<div class="small if-dash__counts">
								<span>
									<?php
									/* translators: %s: number of views. */
									echo esc_html( sprintf( _n( '%s view', '%s views', $if_item['views'], 'internfeel' ), number_format_i18n( $if_item['views'] ) ) );
									?>
								</span>
								<span>
									<?php
									/* translators: %s: number of comments. */
									echo esc_html( sprintf( _n( '%s comment', '%s comments', $if_item['comments'], 'internfeel' ), number_format_i18n( $if_item['comments'] ) ) );
									?>
								</span>
								<?php if ( '' !== $if_item['certificate'] ) : ?>
									<span><?php esc_html_e( 'Certificate', 'internfeel' ); ?> <span class="mono if-ink"><?php echo esc_html( $if_item['certificate'] ); ?></span></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $if_item['note'] && in_array( $if_status, array( 'draft', 'rejected', 'removed' ), true ) ) : ?>
							<p class="if-dash__note"><strong><?php esc_html_e( 'Note from InternFeel:', 'internfeel' ); ?></strong> <?php echo esc_html( $if_item['note'] ); ?></p>
						<?php endif; ?>

						<?php if ( $if_upload ) : ?>
							<div class="if-dash__proof<?php echo 'rejected' === $if_proof ? ' is-rejected' : ''; ?>">
								<?php if ( 'rejected' === $if_proof && '' !== $if_item['proof_note'] ) : ?>
									<p><strong><?php esc_html_e( 'Note from InternFeel:', 'internfeel' ); ?></strong> <?php echo esc_html( $if_item['proof_note'] ); ?></p>
								<?php endif; ?>
								<div class="if-dash__drop">
									<span class="if-dash__drop-title"><?php echo 'rejected' === $if_proof ? esc_html__( 'Upload new proof', 'internfeel' ) : esc_html__( 'Add proof', 'internfeel' ); ?></span>
									<span class="small if-muted"><?php esc_html_e( 'PDF, JPG or PNG, up to 5 MB each, 3 files at most. Only you and InternFeel admins can open them.', 'internfeel' ); ?></span>
									<label class="if-btn if-btn--sm if-dash__file">
										<?php esc_html_e( 'Choose a file', 'internfeel' ); ?>
										<input type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-if-proof>
									</label>
								</div>
							</div>
						<?php endif; ?>

						<div class="if-dash__actions">
							<?php if ( $if_live ) : ?>
								<?php if ( '' !== $if_item['certificate'] ) : ?>
									<a class="if-btn if-btn--sm" href="<?php echo esc_url( Certificates::download_url( $if_item['id'] ) ); ?>">
										<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg><?php esc_html_e( 'Download certificate', 'internfeel' ); ?>
									</a>
								<?php endif; ?>
								<button class="if-btn if-btn--ghost if-btn--sm" type="button" data-if-share="<?php echo esc_url( $if_item['link'] ); ?>" data-title="<?php echo esc_attr( $if_item['title'] ); ?>">
									<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg><span data-if-label><?php esc_html_e( 'Share', 'internfeel' ); ?></span>
								</button>
								<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( $if_item['link'] ); ?>"><?php esc_html_e( 'View', 'internfeel' ); ?></a>
							<?php elseif ( 'pending' === $if_status ) : ?>
								<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( $if_item['link'] ); ?>"><?php esc_html_e( 'Preview', 'internfeel' ); ?></a>
							<?php elseif ( 'draft' === $if_status ) : ?>
								<a class="if-btn if-btn--primary if-btn--sm" href="<?php echo esc_url( $if_item['link'] ); ?>"><?php esc_html_e( 'Continue draft', 'internfeel' ); ?></a>
								<button class="if-btn if-btn--ghost if-btn--sm if-danger" type="button" data-if-delete><?php esc_html_e( 'Delete draft', 'internfeel' ); ?></button>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</section>

			<section class="if-dash__panel" role="tabpanel" id="if-panel-comments" aria-labelledby="if-tab-comments" hidden>
				<?php if ( array() === $if_comments ) : ?>
					<p class="if-dash__empty"><?php esc_html_e( 'No comments on your experiences yet.', 'internfeel' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $if_comments as $if_comment ) : ?>
					<article class="if-dash__item">
						<a class="small" href="<?php echo esc_url( (string) get_permalink( (int) $if_comment->comment_post_ID ) ); ?>"><?php echo esc_html( get_the_title( (int) $if_comment->comment_post_ID ) ); ?></a>
						<p class="if-dash__comment-by">
							<strong><?php echo esc_html( $if_comment->comment_author ); ?></strong>
							<span class="small if-muted"><?php echo esc_html( $if_date( $if_comment->comment_date_gmt, 'j M Y' ) ); ?></span>
						</p>
						<div class="if-dash__comment"><?php echo wp_kses_post( wpautop( $if_comment->comment_content ) ); ?></div>
						<div class="if-dash__actions">
							<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( (string) get_comment_link( $if_comment ) ); ?>"><?php esc_html_e( 'Reply', 'internfeel' ); ?></a>
						</div>
					</article>
				<?php endforeach; ?>
			</section>

			<section class="if-dash__panel" role="tabpanel" id="if-panel-invites" aria-labelledby="if-tab-invites" hidden>
				<?php
				get_template_part(
					'template-parts/invite-panel',
					null,
					array(
						'summary' => $if_invites,
						'id'      => 'tab',
						'full'    => true,
					)
				);
				?>
			</section>

			<section class="if-dash__panel" role="tabpanel" id="if-panel-profile" aria-labelledby="if-tab-profile" hidden>
				<div class="if-dash__item">
					<h2 class="heading-3"><?php esc_html_e( 'Profile', 'internfeel' ); ?></h2>
					<?php get_template_part( 'template-parts/profile-form', null, array( 'settings' => true ) ); ?>
					<div class="if-dash__actions">
						<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Set or change your password', 'internfeel' ); ?></a>
						<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Log out', 'internfeel' ); ?></a>
					</div>
				</div>
			</section>
		</div>

		<aside class="if-dash__aside">
			<section class="if-dash__updates" aria-labelledby="if-updates-title">
				<div class="if-dash__aside-head">
					<h2 class="heading-3" id="if-updates-title"><?php esc_html_e( 'Updates', 'internfeel' ); ?></h2>
					<?php if ( count( $if_notes ) > 5 ) : ?>
						<button class="if-link small" type="button" data-if-all-notes aria-expanded="false"><?php esc_html_e( 'See all', 'internfeel' ); ?></button>
					<?php endif; ?>
				</div>
				<?php if ( array() === $if_notes ) : ?>
					<p class="small if-muted"><?php esc_html_e( 'Decisions on your experiences, proof and comments show up here.', 'internfeel' ); ?></p>
				<?php else : ?>
					<ul class="if-notes" data-if-notes data-unread="<?php echo esc_attr( (string) Notify::unread_count( $if_user_id ) ); ?>">
						<?php foreach ( $if_notes as $if_index => $if_note ) : ?>
							<li class="if-note<?php echo $if_note['read'] ? '' : ' is-unread'; ?>"<?php echo $if_index >= 5 ? ' hidden' : ''; ?>>
								<div>
									<p>
										<?php if ( '' !== $if_note['link'] ) : ?>
											<a href="<?php echo esc_url( $if_note['link'] ); ?>"><?php echo esc_html( $if_note['message'] ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $if_note['message'] ); ?>
										<?php endif; ?>
										<?php if ( ! $if_note['read'] ) : ?>
											<span class="screen-reader-text"><?php esc_html_e( '(new)', 'internfeel' ); ?></span>
										<?php endif; ?>
									</p>
									<time datetime="<?php echo esc_attr( $if_note['created_at'] ); ?>">
										<?php
										/* translators: %s: time, such as "3 hours". */
										echo esc_html( sprintf( __( '%s ago', 'internfeel' ), human_time_diff( (int) strtotime( $if_note['created_at'] ) ) ) );
										?>
									</time>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<?php
			get_template_part(
				'template-parts/invite-panel',
				null,
				array(
					'summary' => $if_invites,
					'id'      => 'side',
				)
			);
			?>
		</aside>
	</div>
</div>
<?php
get_footer();
