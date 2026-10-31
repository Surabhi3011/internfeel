<?php
/**
 * Experience page, from docs/approved-designs/experience.html: title block, key facts, the story with
 * the ratings sidebar, comments, then more experiences of the same type. Anonymous experiences show
 * "Anonymous" and no photo, LinkedIn or caps (plan.md 5.1). Up to three ad slots: after the fourth
 * answer, at the end of the story, and in the sidebar on desktop (plan.md 5.6).
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Auth\Roles;
use InternFeel\Search\Cards;
use InternFeel\Search\Search;
use InternFeel\Submit\Form;
use InternFeel\Submit\Questions;

if ( ! if_core_active() || 'post' !== get_post_type() ) {
	get_header();
	while ( have_posts() ) {
		the_post();
		the_title( '<h1 class="heading-1">', '</h1>' );
		the_content();
	}
	get_footer();
	return;
}

the_post();
$if_id     = get_the_ID();
$if_v      = Form::values( $if_id );
$if_meta   = static fn( string $key ): string => (string) get_post_meta( $if_id, '_if_' . $key, true );
$if_author = (int) get_post_field( 'post_author', $if_id );
$if_anon   = (bool) $if_v['is_anonymous'];
$if_legacy = (int) get_option( Roles::ARCHIVE_OPTION ) === $if_author;
$if_types  = Cards::types();
$if_type   = (string) $if_v['type'];
$if_proof  = 'verified' === $if_meta( 'verification_status' );

// Who wrote it. Anonymous hides only the name, photo, LinkedIn and caps.
$if_name     = __( 'Anonymous', 'internfeel' );
$if_details  = '';
$if_linkedin = '';
$if_caps     = 0;
if ( $if_legacy ) {
	$if_name = $if_anon || '' === $if_meta( 'legacy_student_name' ) ? $if_name : $if_meta( 'legacy_student_name' );
} else {
	$if_profile = \InternFeel\Auth\Profile::get( $if_author );
	$if_year    = (int) $if_profile['graduation_year'];
	$if_details = implode( ', ', array_filter( array( (string) $if_profile['course'], (string) $if_profile['college'] ) ) );
	if ( $if_year > 0 ) {
		/* translators: %d: graduation year. */
		$if_details .= ( '' === $if_details ? '' : ' · ' ) . sprintf( __( 'Class of %d', 'internfeel' ), $if_year );
	}
	if ( ! $if_anon ) {
		$if_name     = '' !== (string) $if_profile['display_name'] ? (string) $if_profile['display_name'] : (string) $if_profile['full_name'];
		$if_linkedin = (string) $if_profile['linkedin_url'];
		$if_caps     = (int) get_user_meta( $if_author, '_if_verified_count', true );
	}
}

// The referral credit names the referrer, even on anonymous posts (plan.md section 4).
$if_referrer = (int) $if_meta( 'referrer_user_id' );
$if_credit   = '';
$if_invited  = $if_referrer > 0 && get_userdata( $if_referrer ) instanceof WP_User;
if ( $if_invited && ! \InternFeel\Invites\Invites::hides_name( $if_referrer ) ) {
	$if_ref     = \InternFeel\Auth\Profile::get( $if_referrer );
	$if_credit  = '' !== (string) $if_ref['display_name'] ? (string) $if_ref['display_name'] : (string) $if_ref['full_name'];
	$if_credit .= '' !== (string) $if_ref['college'] ? ', ' . $if_ref['college'] : '';
}

// Key facts; anything missing (as on many migrated posts) is left out.
$if_month = static function ( string $month ): string {
	$time = '' === $month ? false : strtotime( $month . '-01' );

	return false === $time ? '' : (string) wp_date( 'F Y', $time );
};
$if_weeks = (int) $if_v['duration_weeks'];
$if_modes = array(
	'on-site' => __( 'On-site', 'internfeel' ),
	'remote'  => __( 'Remote', 'internfeel' ),
	'hybrid'  => __( 'Hybrid', 'internfeel' ),
);
$if_level = array(
	1 => __( 'Easy', 'internfeel' ),
	__( 'Fairly easy', 'internfeel' ),
	__( 'Moderate', 'internfeel' ),
	__( 'Competitive', 'internfeel' ),
	__( 'Very competitive', 'internfeel' ),
);
$if_sel   = (int) $if_v['selection_difficulty'];
$if_pay   = '';
if ( true === $if_v['paid'] ) {
	/* translators: %s: amount in rupees. */
	$if_pay = $if_v['stipend_amount_inr'] > 0 ? sprintf( __( '₹%s a month', 'internfeel' ), number_format_i18n( (int) $if_v['stipend_amount_inr'] ) ) : __( 'Paid', 'internfeel' );
}
$if_facts = array(
	array( __( 'Organisation', 'internfeel' ), (string) $if_v['organisation'], '' ),
	array( __( 'Programme', 'internfeel' ), (string) $if_v['programme'], '' ),
	array( __( 'Type', 'internfeel' ), $if_types[ $if_type ] ?? '', $if_modes[ (string) $if_v['mode'] ] ?? '' ),
	array( __( 'Location', 'internfeel' ), (string) ( '' !== $if_v['city'] ? $if_v['city'] : $if_v['country'] ), '' !== $if_v['city'] ? (string) $if_v['country'] : '' ),
	/* translators: %d: number of weeks. */
	array( __( 'Dates', 'internfeel' ), $if_month( (string) $if_v['start_month'] ), $if_weeks > 0 ? sprintf( _n( '%d week', '%d weeks', $if_weeks, 'internfeel' ), $if_weeks ) : '' ),
	array( __( 'Stipend', 'internfeel' ), true === $if_v['paid'] ? $if_pay : ( false === $if_v['paid'] ? __( 'None', 'internfeel' ) : '' ), true === $if_v['paid'] && $if_v['stipend_amount_inr'] > 0 ? __( 'Paid', 'internfeel' ) : ( false === $if_v['paid'] ? __( 'Unpaid', 'internfeel' ) : '' ) ),
	/* translators: %d: selection difficulty from 1 to 5. */
	array( __( 'Selection', 'internfeel' ), $if_level[ $if_sel ] ?? '', $if_sel > 0 ? sprintf( __( '%d of 5', 'internfeel' ), $if_sel ) : '' ),
	array( __( 'Field', 'internfeel' ), (string) $if_v['field'], '' ),
);
$if_facts = array_filter( $if_facts, static fn( array $fact ): bool => '' !== $fact[1] );

// The story: standard questions for new submissions, the cleaned original HTML for migrated posts.
$if_story = array_filter( (array) $if_v['story'], static fn( mixed $answer ): bool => '' !== trim( (string) $answer ) );
$if_words = str_word_count( wp_strip_all_tags( get_the_content() ) );
$if_read  = max( 1, (int) round( $if_words / 220 ) );

$if_ratings = array(
	array( __( 'Learning', 'internfeel' ), (float) $if_meta( 'rating_learning' ) ),
	array( __( 'Mentorship', 'internfeel' ), (float) $if_meta( 'rating_mentorship' ) ),
	array( __( 'Work culture', 'internfeel' ), (float) $if_meta( 'rating_culture' ) ),
	array( __( 'Selection difficulty', 'internfeel' ), (float) $if_sel ),
);
$if_ratings = array_filter( $if_ratings, static fn( array $rating ): bool => $rating[1] > 0 );
$if_overall = (float) $if_meta( 'rating_overall' );

$if_initials = static function ( string $name ): string {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$parts = false === $parts ? array() : $parts;

	return mb_strtoupper( mb_substr( (string) ( $parts[0] ?? '' ), 0, 1 ) . ( count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '' ) );
};

$if_url    = (string) get_permalink();
$if_title  = get_the_title();
$if_shares = array(
	'LinkedIn' => array( 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $if_url ), '<rect x="2" y="2" width="20" height="20" rx="4"/><path d="M7 10v7M7 7v.01M11 17v-4a2.5 2.5 0 0 1 5 0v4M11 10v7"/>' ),
	'WhatsApp' => array( 'https://wa.me/?text=' . rawurlencode( $if_title . ' ' . $if_url ), '<path d="M3 21l1.7-5A8.5 8.5 0 1 1 8 19.3z"/>' ),
	'X'        => array( 'https://x.com/intent/post?url=' . rawurlencode( $if_url ) . '&text=' . rawurlencode( $if_title ), '<path d="M4 4l16 16M20 4 4 20"/>' ),
	'Facebook' => array( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $if_url ), '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>' ),
);

$if_more = '' === $if_type ? array() : array_values(
	array_filter(
		Search::run( Search::filters( array( 'type' => $if_type ) ), 4 )['cards'],
		static fn( array $card ): bool => $card['id'] !== $if_id
	)
);

get_header();
?>
<article class="if-exp" data-if-view="<?php echo esc_attr( (string) $if_id ); ?>" data-rest="<?php echo esc_url( rest_url( 'internfeel/v1/view/' . $if_id ) ); ?>">
	<header class="if-container if-exp__head">
		<nav class="small if-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'internfeel' ); ?>">
			<a href="<?php echo esc_url( home_url( '/experiences/' ) ); ?>"><?php esc_html_e( 'Experiences', 'internfeel' ); ?></a>
			<?php if ( isset( $if_types[ $if_type ] ) ) : ?>
				<span aria-hidden="true">›</span> <a href="<?php echo esc_url( add_query_arg( 'type', $if_type, home_url( '/experiences/' ) ) ); ?>"><?php echo esc_html( $if_types[ $if_type ] ); ?></a>
			<?php endif; ?>
			<?php if ( '' !== (string) $if_v['organisation'] ) : ?>
				<span aria-hidden="true">›</span> <span><?php echo esc_html( (string) $if_v['organisation'] ); ?></span>
			<?php endif; ?>
		</nav>

		<div class="if-exp__labels">
			<?php if ( isset( $if_types[ $if_type ] ) ) : ?>
				<span class="label if-teal-text">
					<?php
					/* translators: %s: type, such as Research. */
					echo esc_html( sprintf( __( '%s internship', 'internfeel' ), $if_types[ $if_type ] ) );
					?>
				</span>
			<?php endif; ?>
			<?php if ( $if_proof ) : ?>
				<span class="if-verified"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7"/></svg><?php esc_html_e( 'Verified', 'internfeel' ); ?></span>
			<?php endif; ?>
		</div>

		<h1 class="heading-1 if-exp__title"><?php the_title(); ?></h1>

		<div class="if-exp__author">
			<span class="if-avatar<?php echo $if_anon ? ' if-avatar--anon' : ''; ?>" aria-hidden="true"><?php echo $if_anon ? '' : esc_html( $if_initials( $if_name ) ); ?></span>
			<div class="if-exp__who">
				<span class="if-byline">
					<?php echo esc_html( $if_name ); ?>
					<?php if_the_badges( $if_caps ); ?>
					<?php if ( '' !== $if_linkedin ) : ?>
						<a class="if-exp__linkedin" href="<?php echo esc_url( $if_linkedin ); ?>" rel="noopener nofollow" target="_blank">
							<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="4"/><path d="M7 10v7M7 7v.01M11 17v-4a2.5 2.5 0 0 1 5 0v4M11 10v7"/></svg>
							<span class="screen-reader-text">
								<?php
								/* translators: %s: student's name. */
								echo esc_html( sprintf( __( '%s on LinkedIn', 'internfeel' ), $if_name ) );
								?>
							</span>
						</a>
					<?php endif; ?>
				</span>
				<?php if ( '' !== $if_details ) : ?>
					<span class="small if-muted"><?php echo esc_html( $if_details ); ?></span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $if_invited ) : ?>
			<span class="if-credit">
				<svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2 21c0-3.9 3.1-7 7-7s7 3.1 7 7"/><path d="M19 8v6M16 11h6"/></svg>
				<?php if ( '' === $if_credit ) : ?>
					<?php esc_html_e( 'Shared through an InternFeel student invite', 'internfeel' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Shared through an invite from', 'internfeel' ); ?> <strong><?php echo esc_html( $if_credit ); ?></strong>
				<?php endif; ?>
			</span>
		<?php endif; ?>

		<div class="if-exp__bar">
			<span class="small if-muted if-exp__stats">
				<?php if ( $if_legacy && (int) $if_meta( 'legacy_published_year' ) > 0 ) : ?>
					<span>
						<?php
						/* translators: %d: year. */
						echo esc_html( sprintf( __( 'First published on InternFeel in %d', 'internfeel' ), (int) $if_meta( 'legacy_published_year' ) ) );
						?>
					</span>
				<?php else : ?>
					<span>
						<?php
						/* translators: %s: date. */
						echo esc_html( sprintf( __( 'Published %s', 'internfeel' ), get_the_date( 'j M Y' ) ) );
						?>
					</span>
				<?php endif; ?>
				<span>
					<?php
					$if_views = (int) $if_meta( 'view_count' );
					/* translators: %s: number of views. */
					echo esc_html( sprintf( _n( '%s view', '%s views', $if_views, 'internfeel' ), number_format_i18n( $if_views ) ) );
					?>
				</span>
				<span>
					<?php
					$if_count = (int) get_comments_number();
					/* translators: %s: number of comments. */
					echo esc_html( sprintf( _n( '%s comment', '%s comments', $if_count, 'internfeel' ), number_format_i18n( $if_count ) ) );
					?>
				</span>
				<span>
					<?php
					/* translators: %d: minutes. */
					echo esc_html( sprintf( _n( '%d min read', '%d min read', $if_read, 'internfeel' ), $if_read ) );
					?>
				</span>
			</span>
			<div class="if-share" role="group" aria-label="<?php esc_attr_e( 'Share this experience', 'internfeel' ); ?>">
				<span class="small if-share__label"><?php esc_html_e( 'Share', 'internfeel' ); ?></span>
				<?php foreach ( $if_shares as $if_network => $if_share ) : ?>
					<a class="if-btn if-btn--ghost if-btn--sm" href="<?php echo esc_url( $if_share[0] ); ?>" target="_blank" rel="noopener">
						<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><?php echo $if_share[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG paths. ?></svg>
						<span class="if-share__name">
							<?php
							/* translators: %s: social network. */
							echo esc_html( $if_network );
							?>
						</span>
						<span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'internfeel' ); ?></span>
					</a>
				<?php endforeach; ?>
				<button class="if-btn if-btn--sm" type="button" data-if-copy="<?php echo esc_url( $if_url ); ?>">
					<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg><span data-if-label><?php esc_html_e( 'Copy link', 'internfeel' ); ?></span>
				</button>
			</div>
		</div>
	</header>

	<?php if ( array() !== $if_facts ) : ?>
		<div class="if-container">
			<section class="if-facts" aria-label="<?php esc_attr_e( 'Key facts', 'internfeel' ); ?>">
				<dl>
					<?php foreach ( $if_facts as $if_fact ) : ?>
						<div>
							<dt><?php echo esc_html( $if_fact[0] ); ?></dt>
							<dd><?php echo esc_html( $if_fact[1] ); ?><?php echo '' !== $if_fact[2] ? '<small>' . esc_html( $if_fact[2] ) . '</small>' : ''; ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		</div>
	<?php endif; ?>

	<div class="if-container if-exp__layout">
		<div class="if-exp__story">
			<?php if ( array() !== $if_story ) : ?>
				<?php $if_answered = 0; ?>
				<?php foreach ( Questions::all() as $if_key => $if_question ) : ?>
					<?php if ( isset( $if_story[ $if_key ] ) ) : ?>
						<section class="if-qa">
							<h2 class="if-qa__q"><?php echo esc_html( $if_question ); ?></h2>
							<div class="if-qa__a"><?php echo wp_kses_post( wpautop( esc_html( (string) $if_story[ $if_key ] ) ) ); ?></div>
						</section>
						<?php
						// Ad 1 after the fourth answer (plan.md 5.6).
						if ( 4 === ++$if_answered && count( $if_story ) > 4 ) {
							if_the_ad( 'inline' );
						}
						?>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="if-qa if-qa--legacy"><div class="if-qa__a"><?php the_content(); ?></div></div>
			<?php endif; ?>

			<?php if ( '' !== trim( (string) $if_v['advice'] ) ) : ?>
				<aside class="if-exp__advice" aria-label="<?php esc_attr_e( 'Advice', 'internfeel' ); ?>">
					<span class="label if-teal-text"><?php esc_html_e( 'Advice for students applying', 'internfeel' ); ?></span>
					<div class="body-lg"><?php echo wp_kses_post( wpautop( esc_html( (string) $if_v['advice'] ) ) ); ?></div>
				</aside>
			<?php endif; ?>

			<?php if_the_ad( 'end' ); ?>

			<p class="small if-muted if-exp__report"><?php esc_html_e( 'Something wrong with this experience?', 'internfeel' ); ?> <?php if_the_report_button( 'experience', $if_id, $if_author, __( 'Report it', 'internfeel' ) ); ?></p>
		</div>

		<?php if ( $if_overall > 0 || array() !== $if_ratings ) : ?>
			<aside class="if-exp__side" aria-labelledby="if-ratings-title">
				<section class="if-exp__ratings">
					<h2 class="heading-3" id="if-ratings-title"><?php esc_html_e( 'Ratings', 'internfeel' ); ?></h2>
					<?php if ( $if_overall > 0 ) : ?>
						<p class="if-exp__overall"><span class="if-exp__overall-number"><?php echo esc_html( number_format_i18n( $if_overall, 1 ) ); ?></span> <span class="small if-muted"><?php esc_html_e( 'overall, out of 5', 'internfeel' ); ?></span></p>
					<?php endif; ?>
					<?php if ( array() !== $if_ratings ) : ?>
						<ul class="if-rating-list">
							<?php foreach ( $if_ratings as $if_rating ) : ?>
								<li><span><?php echo esc_html( $if_rating[0] ); ?></span><?php if_the_rating( $if_rating[1] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
				<?php if_the_ad( 'sidebar' ); ?>
			</aside>
		<?php endif; ?>
	</div>
</article>

<?php comments_template(); ?>

<?php if ( array() !== $if_more ) : ?>
	<section class="if-container if-row" aria-labelledby="if-more-title">
		<div class="if-row__head">
			<h2 class="heading-2" id="if-more-title">
				<?php
				/* translators: %s: type, such as Research. */
				echo esc_html( sprintf( __( 'More %s internships', 'internfeel' ), $if_types[ $if_type ] ) );
				?>
			</h2>
		</div>
		<div class="if-grid"><?php if_the_cards( array_slice( $if_more, 0, 3 ) ); ?></div>
	</section>
<?php endif; ?>
<?php
get_footer();
