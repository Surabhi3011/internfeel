<?php
/**
 * Home page, from docs/approved-designs/home.html: hero, Browse band, trust strip, listicle rows, How
 * InternFeel works and, for students holding invites, the invite banner. Any filter in the URL shows
 * the results grid in place of the rows (plan.md 5.2). Ad slots arrive with AdSense (plan.md 7, day 11).
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Search\Search;

if ( ! if_core_active() ) {
	get_header();
	echo '<div class="if-container"><h1 class="heading-1">' . esc_html( get_bloginfo( 'name' ) ) . '</h1></div>';
	get_footer();
	return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filters.
$if_filters  = Search::filters( $_GET );
$if_filtered = Search::is_filtered( $if_filters );
$if_counts   = Search::counts();
$if_rows     = if_home_rows();

// The hero card: the newest verified experience, else the newest one.
$if_hero = Search::run( Search::filters( array( 'verified' => '1' ) ), 1 )['cards'];
if ( array() === $if_hero ) {
	$if_hero = Search::run( Search::filters( array() ), 1 )['cards'];
}

// Popular: the four organisations with the most experiences.
$if_popular = get_terms(
	array(
		'taxonomy'   => 'if_master',
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 4,
		'hide_empty' => true,
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, cached by WordPress.
			array(
				'key'   => '_if_kind',
				'value' => 'organisation',
			),
		),
	)
);
$if_popular = is_array( $if_popular ) ? $if_popular : array();

$if_credits = is_user_logged_in() ? (int) get_user_meta( get_current_user_id(), '_if_invite_credits', true ) : 0;

get_header();
?>
<section class="if-hero">
	<div class="if-container if-hero__inner">
		<div class="if-hero__text">
			<span class="label if-hero__label"><?php esc_html_e( 'Written by students, for students', 'internfeel' ); ?></span>
			<h1 class="display if-hero__title"><?php esc_html_e( 'Real internship experiences from students who’ve been there', 'internfeel' ); ?></h1>
			<p class="body-lg if-muted"><?php esc_html_e( 'Read what an internship was really like before you apply: the selection, the work, the stipend and the advice.', 'internfeel' ); ?></p>
			<form class="if-hero__search" role="search" method="get" action="<?php echo esc_url( home_url( '/experiences/' ) ); ?>">
				<span class="if-search">
					<svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
					<label class="screen-reader-text" for="if-hero-q"><?php esc_html_e( 'Search experiences', 'internfeel' ); ?></label>
					<input id="if-hero-q" type="search" name="q" placeholder="<?php esc_attr_e( 'Organisation, programme or field', 'internfeel' ); ?>">
				</span>
				<button class="if-btn" type="submit"><?php esc_html_e( 'Search', 'internfeel' ); ?></button>
			</form>
			<?php if ( array() !== $if_popular ) : ?>
				<p class="small if-muted if-hero__popular">
					<?php esc_html_e( 'Popular:', 'internfeel' ); ?>
					<?php
					$if_links = array();
					foreach ( $if_popular as $if_term ) {
						$if_links[] = '<a href="' . esc_url( add_query_arg( 'organisation', $if_term->term_id, home_url( '/experiences/' ) ) ) . '">' . esc_html( $if_term->name ) . '</a>';
					}
					echo wp_kses_post( implode( ' · ', $if_links ) );
					?>
				</p>
			<?php endif; ?>
			<div class="if-hero__actions">
				<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>"><?php esc_html_e( 'Submit your experience', 'internfeel' ); ?></a>
				<a class="if-btn if-btn--ghost" href="#how"><?php esc_html_e( 'How it works', 'internfeel' ); ?></a>
			</div>
		</div>
		<div class="if-hero__art" aria-hidden="true">
			<svg viewBox="0 0 560 440" preserveAspectRatio="xMidYMid slice" focusable="false">
				<rect x="0" y="48" width="200" height="392" class="if-art-tint"/>
				<rect x="200" y="0" width="360" height="240" class="if-art-teal"/>
				<rect x="200" y="240" width="176" height="200" class="if-art-charcoal"/>
				<rect x="376" y="240" width="184" height="200" class="if-art-teal-700"/>
				<rect x="70" y="150" width="520" height="36" rx="18" transform="rotate(-7 330 168)" class="if-art-canvas"/>
				<rect x="120" y="236" width="470" height="36" rx="18" transform="rotate(-7 355 254)" class="if-art-canvas"/>
			</svg>
		</div>
		<?php if ( array() !== $if_hero ) : ?>
			<div class="if-hero__card">
				<?php if_the_cards( $if_hero, 'h2' ); ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php
get_template_part(
	'template-parts/browse',
	null,
	array(
		'filters'      => $if_filters,
		'results'      => $if_filtered ? Search::run( $if_filters ) : null,
		'action'       => home_url( '/' ),
		'title'        => __( 'Browse experiences', 'internfeel' ),
		'show_results' => $if_filtered,
	)
);
?>

<div data-if-default<?php echo $if_filtered ? ' hidden' : ''; ?>>
	<section class="if-container if-trust" aria-label="<?php esc_attr_e( 'InternFeel in numbers', 'internfeel' ); ?>">
		<div class="if-trust__box">
			<?php
			$if_stats = array(
				array( $if_counts['experiences'], __( 'experiences shared', 'internfeel' ) ),
				array( $if_counts['verified'], __( 'verified with proof', 'internfeel' ) ),
				array( $if_counts['colleges'], __( 'colleges contributing', 'internfeel' ) ),
			);
			foreach ( $if_stats as $if_stat ) :
				?>
				<div class="if-trust__stat">
					<span class="if-trust__number"><?php echo esc_html( number_format_i18n( $if_stat[0] ) ); ?></span>
					<span class="small if-muted"><?php echo esc_html( $if_stat[1] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<?php foreach ( $if_rows as $if_row ) : ?>
		<section class="if-container if-row" aria-labelledby="<?php echo esc_attr( $if_row['id'] ); ?>">
			<div class="if-row__head">
				<div>
					<h2 class="heading-2" id="<?php echo esc_attr( $if_row['id'] ); ?>"><?php echo esc_html( $if_row['title'] ); ?></h2>
					<?php if ( '' !== $if_row['subtitle'] ) : ?>
						<span class="small if-muted"><?php echo esc_html( $if_row['subtitle'] ); ?></span>
					<?php endif; ?>
				</div>
				<a class="if-row__all" href="<?php echo esc_url( $if_row['url'] ); ?>">
					<?php esc_html_e( 'See all', 'internfeel' ); ?><span class="screen-reader-text"> <?php echo esc_html( $if_row['title'] ); ?></span>
					<svg class="if-icon if-icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
				</a>
			</div>
			<div class="if-grid">
				<?php if_the_cards( $if_row['cards'] ); ?>
			</div>
		</section>
	<?php endforeach; ?>

	<section id="how" class="if-container if-how" aria-labelledby="if-how-title">
		<h2 class="heading-2" id="if-how-title"><?php esc_html_e( 'How InternFeel works', 'internfeel' ); ?></h2>
		<div class="if-how__steps">
			<div class="if-how__step">
				<span class="if-how__icon" aria-hidden="true"><svg class="if-icon" viewBox="0 0 24 24"><path d="M2 5h7a3 3 0 0 1 3 3v12a2 2 0 0 0-2-2H2zM22 5h-7a3 3 0 0 0-3 3v12a2 2 0 0 1 2-2h8z"/></svg></span>
				<h3 class="heading-3"><?php esc_html_e( 'Read', 'internfeel' ); ?></h3>
				<p class="if-muted"><?php esc_html_e( 'First-hand accounts of research, industry, government and NGO internships, with the stipend and selection spelled out.', 'internfeel' ); ?></p>
			</div>
			<div class="if-how__step">
				<span class="if-how__icon" aria-hidden="true"><svg class="if-icon" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></span>
				<h3 class="heading-3"><?php esc_html_e( 'Share', 'internfeel' ); ?></h3>
				<p class="if-muted"><?php esc_html_e( 'Write about your own internship in about 20 minutes. We review every experience within 24 hours.', 'internfeel' ); ?></p>
			</div>
			<div class="if-how__step">
				<span class="if-how__icon" aria-hidden="true"><svg class="if-icon" viewBox="0 0 24 24"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/></svg></span>
				<h3 class="heading-3"><?php esc_html_e( 'Get certified', 'internfeel' ); ?></h3>
				<p class="if-muted"><?php esc_html_e( 'Every published experience earns a certificate of contribution that anyone can verify.', 'internfeel' ); ?></p>
			</div>
		</div>
		<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/submit/' ) ); ?>"><?php esc_html_e( 'Submit your experience', 'internfeel' ); ?></a>
	</section>

	<?php if ( $if_credits > 0 ) : ?>
		<section class="if-invite-banner" aria-labelledby="if-invite-title">
			<div class="if-container if-invite-banner__inner">
				<div>
					<h2 class="heading-3" id="if-invite-title">
						<?php
						/* translators: %s: number of invites. */
						echo esc_html( sprintf( _n( 'You have %s invite left', 'You have %s invites left', $if_credits, 'internfeel' ), number_format_i18n( $if_credits ) ) );
						?>
					</h2>
					<p class="if-muted"><?php esc_html_e( 'Invite a friend to share their internship. Each link works once.', 'internfeel' ); ?></p>
				</div>
				<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/dashboard/#invites' ) ); ?>"><?php esc_html_e( 'Get your invite link', 'internfeel' ); ?></a>
			</div>
		</section>
	<?php endif; ?>
</div>
<?php
get_footer();
