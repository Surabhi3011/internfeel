<?php
/**
 * Submission form (/submit/), from docs/approved-designs/submit-step-1.html: four steps in one form,
 * autosaved every 10 seconds. /submit/?id={draft} continues a draft. Signed in only; no ads.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Submit\Form;
use InternFeel\Submit\Questions;
use InternFeel\Submit\Submit;
use InternFeel\Verify\Proofs;

if_no_page_cache();

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( home_url( '/submit/' ) ), home_url( '/login/' ) ) );
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; ownership is checked below.
$if_id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$if_post   = $if_id > 0 ? get_post( $if_id ) : null;
$if_ok     = $if_post instanceof WP_Post && 'post' === $if_post->post_type && get_current_user_id() === (int) $if_post->post_author;
$if_draft  = $if_ok && 'draft' === $if_post->post_status;
$if_values = $if_draft && class_exists( Form::class ) ? Form::values( $if_id ) : array();
$if_proofs = $if_draft && class_exists( Proofs::class ) ? Proofs::list_for( $if_id ) : array();
$if_home   = if_core_active() ? \InternFeel\Auth\Profile::home_url() : home_url( '/' );

/**
 * A saved value for a field, or a default.
 *
 * @param string $key     Field name.
 * @param mixed  $fallback Value when nothing is saved.
 * @return mixed
 */
$if_value = static fn( string $key, mixed $fallback = '' ): mixed => $if_values[ $key ] ?? $fallback;

/**
 * Print checked for a radio whose value matches the saved one.
 *
 * @param string $key   Field name.
 * @param string $value This radio's value.
 */
$if_checked = static function ( string $key, string $value ) use ( $if_values ): void {
	$saved = $if_values[ $key ] ?? null;
	if ( is_bool( $saved ) ) {
		$saved = $saved ? '1' : '0';
	}
	checked( null !== $saved && '' !== $saved && (string) $saved === $value );
};

get_header();
?>
<div class="if-container if-submit">
	<div class="if-submit__intro">
		<a class="small" href="<?php echo esc_url( $if_home ); ?>">‹ <?php esc_html_e( 'Back to your dashboard', 'internfeel' ); ?></a>
		<h1 class="heading-1 if-submit__title"><?php esc_html_e( 'Share your internship experience', 'internfeel' ); ?></h1>
		<p><?php esc_html_e( 'Takes about 20 minutes. Your draft saves every 10 seconds, so you can stop and come back.', 'internfeel' ); ?></p>
	</div>

	<?php if ( ! class_exists( Submit::class ) ) : ?>
		<p><?php esc_html_e( 'Submissions are not available right now.', 'internfeel' ); ?></p>
	<?php elseif ( $if_id > 0 && ! $if_draft ) : ?>
		<div class="if-auth__card">
			<h2 class="heading-2"><?php esc_html_e( 'This experience has already been submitted', 'internfeel' ); ?></h2>
			<p><?php esc_html_e( 'We review every experience within 24 hours. You can follow it on your dashboard.', 'internfeel' ); ?></p>
			<p><a class="if-btn if-btn--primary" href="<?php echo esc_url( $if_home ); ?>"><?php esc_html_e( 'Go to your dashboard', 'internfeel' ); ?></a></p>
		</div>
	<?php else : ?>
		<noscript><p class="if-field__error"><?php esc_html_e( 'The submission form needs JavaScript. Please turn it on, or use another browser.', 'internfeel' ); ?></p></noscript>

		<ol class="if-stepper if-submit__stepper" aria-label="<?php esc_attr_e( 'Progress', 'internfeel' ); ?>" data-if-stepper>
			<li aria-current="step"><span><?php esc_html_e( 'Where and what', 'internfeel' ); ?></span></li>
			<li><span><?php esc_html_e( 'Your story', 'internfeel' ); ?></span></li>
			<li><span><?php esc_html_e( 'Ratings and advice', 'internfeel' ); ?></span></li>
			<li><span><?php esc_html_e( 'Review and submit', 'internfeel' ); ?></span></li>
		</ol>

		<div class="if-submit__layout">
			<form class="if-submit__card" novalidate
				data-if-submit
				data-id="<?php echo esc_attr( (string) ( $if_draft ? $if_id : 0 ) ); ?>"
				data-create="<?php echo esc_url( rest_url( 'internfeel/v1/experiences' ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
				data-home="<?php echo esc_url( $if_home ); ?>"
				data-form-url="<?php echo esc_url( Submit::url() ); ?>">

				<?php /* ---------- Step 1: where and what ---------- */ ?>
				<section class="if-submit__step" data-step="1" aria-labelledby="if-step-1">
					<div class="if-submit__head">
						<h2 id="if-step-1" class="heading-2" tabindex="-1"><?php esc_html_e( 'Where and what', 'internfeel' ); ?></h2>
						<p class="small"><?php esc_html_e( 'Fields marked * are required.', 'internfeel' ); ?></p>
					</div>

					<div class="if-field">
						<label for="if-organisation"><?php esc_html_e( 'Organisation', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
						<input id="if-organisation" name="organisation" type="text" list="if-organisations" autocomplete="off" maxlength="120" required aria-describedby="if-organisation-help" value="<?php echo esc_attr( (string) $if_value( 'organisation' ) ); ?>">
						<span class="if-field__help" id="if-organisation-help"><?php esc_html_e( 'Pick from the list or add a new one.', 'internfeel' ); ?></span>
						<datalist id="if-organisations">
							<?php foreach ( Form::suggestions( 'organisation' ) as $if_name ) : ?>
								<option value="<?php echo esc_attr( $if_name ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>

					<div class="if-field">
						<label for="if-programme"><?php esc_html_e( 'Programme or role', 'internfeel' ); ?></label>
						<input id="if-programme" name="programme" type="text" maxlength="120" aria-describedby="if-programme-help" value="<?php echo esc_attr( (string) $if_value( 'programme' ) ); ?>">
						<span class="if-field__help" id="if-programme-help"><?php esc_html_e( 'Optional. The fellowship, scheme or job title, if it had one.', 'internfeel' ); ?></span>
					</div>

					<fieldset class="if-field">
						<legend><?php esc_html_e( 'Type', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></legend>
						<div class="if-chips">
							<?php
							$if_types = array(
								'research'   => __( 'Research', 'internfeel' ),
								'industry'   => __( 'Industry', 'internfeel' ),
								'government' => __( 'Government', 'internfeel' ),
								'ngo'        => __( 'NGO', 'internfeel' ),
								'virtual'    => __( 'Virtual', 'internfeel' ),
							);
							foreach ( $if_types as $if_key => $if_label ) :
								?>
								<label class="if-chip"><input type="radio" name="type" value="<?php echo esc_attr( $if_key ); ?>" required <?php $if_checked( 'type', $if_key ); ?>><?php echo esc_html( $if_label ); ?></label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<div class="if-form__two">
						<div class="if-field">
							<label for="if-field-of-study"><?php esc_html_e( 'Field', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
							<input id="if-field-of-study" name="field" type="text" list="if-fields" autocomplete="off" maxlength="120" required value="<?php echo esc_attr( (string) $if_value( 'field' ) ); ?>">
							<datalist id="if-fields">
								<?php foreach ( Form::suggestions( 'field' ) as $if_name ) : ?>
									<option value="<?php echo esc_attr( $if_name ); ?>"></option>
								<?php endforeach; ?>
							</datalist>
						</div>
						<fieldset class="if-field">
							<legend><?php esc_html_e( 'Mode', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></legend>
							<div class="if-segment">
								<label><input type="radio" name="mode" value="on-site" required <?php $if_checked( 'mode', 'on-site' ); ?>><span><?php esc_html_e( 'On-site', 'internfeel' ); ?></span></label>
								<label><input type="radio" name="mode" value="remote" required <?php $if_checked( 'mode', 'remote' ); ?>><span><?php esc_html_e( 'Remote', 'internfeel' ); ?></span></label>
								<label><input type="radio" name="mode" value="hybrid" required <?php $if_checked( 'mode', 'hybrid' ); ?>><span><?php esc_html_e( 'Hybrid', 'internfeel' ); ?></span></label>
							</div>
						</fieldset>
					</div>

					<div class="if-form__two">
						<div class="if-field">
							<label for="if-city"><?php esc_html_e( 'City', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
							<input id="if-city" name="city" type="text" maxlength="80" required value="<?php echo esc_attr( (string) $if_value( 'city' ) ); ?>">
						</div>
						<div class="if-field">
							<label for="if-country"><?php esc_html_e( 'Country', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
							<input id="if-country" name="country" type="text" maxlength="80" required autocomplete="country-name" value="<?php echo esc_attr( (string) $if_value( 'country', 'India' ) ); ?>">
						</div>
					</div>

					<div class="if-form__two">
						<div class="if-field">
							<label for="if-start-month"><?php esc_html_e( 'Start month', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
							<input id="if-start-month" name="start_month" type="month" max="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>" required value="<?php echo esc_attr( (string) $if_value( 'start_month' ) ); ?>">
						</div>
						<div class="if-field">
							<label for="if-duration"><?php esc_html_e( 'Duration in weeks', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
							<input id="if-duration" name="duration_weeks" type="number" inputmode="numeric" min="1" max="104" required value="<?php echo $if_value( 'duration_weeks', 0 ) ? esc_attr( (string) $if_value( 'duration_weeks' ) ) : ''; ?>">
						</div>
					</div>

					<div class="if-form__two">
						<fieldset class="if-field">
							<legend><?php esc_html_e( 'Paid or unpaid', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></legend>
							<div class="if-segment">
								<label><input type="radio" name="paid" value="1" required <?php $if_checked( 'paid', '1' ); ?>><span><?php esc_html_e( 'Paid', 'internfeel' ); ?></span></label>
								<label><input type="radio" name="paid" value="0" required <?php $if_checked( 'paid', '0' ); ?>><span><?php esc_html_e( 'Unpaid', 'internfeel' ); ?></span></label>
							</div>
						</fieldset>
						<div class="if-field">
							<label for="if-stipend"><?php esc_html_e( 'Stipend a month', 'internfeel' ); ?></label>
							<div class="if-money"><span aria-hidden="true">₹</span><input id="if-stipend" name="stipend_amount_inr" type="number" inputmode="numeric" min="0" step="500" aria-describedby="if-stipend-help" value="<?php echo $if_value( 'stipend_amount_inr', 0 ) ? esc_attr( (string) $if_value( 'stipend_amount_inr' ) ) : ''; ?>"></div>
							<span class="if-field__help" id="if-stipend-help"><?php esc_html_e( 'Optional, in rupees. Cards show it as a band: under ₹10k, ₹10k–25k or above ₹25k.', 'internfeel' ); ?></span>
						</div>
					</div>

					<fieldset class="if-field" aria-describedby="if-difficulty-help">
						<legend><?php esc_html_e( 'How hard was it to get in?', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></legend>
						<div class="if-segment">
							<?php for ( $if_n = 1; $if_n <= 5; $if_n++ ) : ?>
								<label><input type="radio" name="selection_difficulty" value="<?php echo esc_attr( (string) $if_n ); ?>" required <?php $if_checked( 'selection_difficulty', (string) $if_n ); ?>><span><?php echo esc_html( (string) $if_n ); ?></span></label>
							<?php endfor; ?>
						</div>
						<span class="if-field__help" id="if-difficulty-help"><?php esc_html_e( '1 is open to most applicants, 5 is very competitive.', 'internfeel' ); ?></span>
					</fieldset>
				</section>

				<?php /* ---------- Step 2: your story ---------- */ ?>
				<section class="if-submit__step" data-step="2" aria-labelledby="if-step-2" hidden>
					<div class="if-submit__head">
						<h2 id="if-step-2" class="heading-2" tabindex="-1"><?php esc_html_e( 'Your story', 'internfeel' ); ?></h2>
						<p class="small"><?php esc_html_e( 'Write the way you would tell a junior: dates, money, the tools you used. Fields marked * are required.', 'internfeel' ); ?></p>
					</div>
					<?php
					$if_story = (array) $if_value( 'story', array() );
					foreach ( Questions::all() as $if_key => $if_question ) :
						$if_required = Questions::is_required( $if_key );
						?>
						<div class="if-field">
							<label for="if-<?php echo esc_attr( $if_key ); ?>"><?php echo esc_html( $if_question ); ?><?php echo $if_required ? ' <span class="if-req" aria-hidden="true">*</span>' : ''; ?></label>
							<textarea id="if-<?php echo esc_attr( $if_key ); ?>" name="story[<?php echo esc_attr( $if_key ); ?>]" rows="6" maxlength="<?php echo esc_attr( (string) Form::MAX_ANSWER ); ?>"<?php echo $if_required ? ' required' : ''; ?>><?php echo esc_textarea( (string) ( $if_story[ $if_key ] ?? '' ) ); ?></textarea>
						</div>
					<?php endforeach; ?>
				</section>

				<?php /* ---------- Step 3: ratings and advice ---------- */ ?>
				<section class="if-submit__step" data-step="3" aria-labelledby="if-step-3" hidden>
					<div class="if-submit__head">
						<h2 id="if-step-3" class="heading-2" tabindex="-1"><?php esc_html_e( 'Ratings and advice', 'internfeel' ); ?></h2>
						<p class="small"><?php esc_html_e( '1 is poor, 5 is excellent.', 'internfeel' ); ?></p>
					</div>
					<?php
					$if_ratings = array(
						'rating_overall'    => __( 'Overall', 'internfeel' ),
						'rating_learning'   => __( 'Learning', 'internfeel' ),
						'rating_mentorship' => __( 'Mentorship', 'internfeel' ),
						'rating_culture'    => __( 'Work culture', 'internfeel' ),
					);
					foreach ( $if_ratings as $if_key => $if_label ) :
						?>
						<fieldset class="if-field">
							<legend><?php echo esc_html( $if_label ); ?> <span class="if-req" aria-hidden="true">*</span></legend>
							<div class="if-segment">
								<?php for ( $if_n = 1; $if_n <= 5; $if_n++ ) : ?>
									<label><input type="radio" name="<?php echo esc_attr( $if_key ); ?>" value="<?php echo esc_attr( (string) $if_n ); ?>" required <?php $if_checked( $if_key, (string) $if_n ); ?>><span><?php echo esc_html( (string) $if_n ); ?></span></label>
								<?php endfor; ?>
							</div>
						</fieldset>
					<?php endforeach; ?>

					<div class="if-field">
						<label for="if-advice"><?php esc_html_e( 'Advice for students applying', 'internfeel' ); ?></label>
						<textarea id="if-advice" name="advice" rows="5" maxlength="<?php echo esc_attr( (string) Form::MAX_ADVICE ); ?>" aria-describedby="if-advice-help"><?php echo esc_textarea( (string) $if_value( 'advice' ) ); ?></textarea>
						<span class="if-field__help" id="if-advice-help"><?php esc_html_e( 'Optional. Shown in its own box after your story.', 'internfeel' ); ?></span>
					</div>
				</section>

				<?php /* ---------- Step 4: review and submit ---------- */ ?>
				<section class="if-submit__step" data-step="4" aria-labelledby="if-step-4" hidden>
					<div class="if-submit__head">
						<h2 id="if-step-4" class="heading-2" tabindex="-1"><?php esc_html_e( 'Review and submit', 'internfeel' ); ?></h2>
					</div>

					<fieldset class="if-field" aria-describedby="if-anonymous-help">
						<legend><?php esc_html_e( 'How should we show your name?', 'internfeel' ); ?></legend>
						<div class="if-segment">
							<label><input type="radio" name="is_anonymous" value="0" <?php checked( ! $if_value( 'is_anonymous', false ) ); ?>><span><?php esc_html_e( 'Show my name', 'internfeel' ); ?></span></label>
							<label><input type="radio" name="is_anonymous" value="1" <?php checked( (bool) $if_value( 'is_anonymous', false ) ); ?>><span><?php esc_html_e( 'Post anonymously', 'internfeel' ); ?></span></label>
						</div>
						<span class="if-field__help" id="if-anonymous-help"><?php esc_html_e( 'Posting anonymously hides only your name, photo and LinkedIn. Your college, course, year and the details stay visible.', 'internfeel' ); ?></span>
					</fieldset>

					<div class="if-field" data-if-proofs>
						<label for="if-proof"><?php esc_html_e( 'Proof (optional)', 'internfeel' ); ?></label>
						<input id="if-proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="if-proof-help">
						<span class="if-field__help" id="if-proof-help"><?php esc_html_e( 'Your offer letter or certificate earns the Verified badge. PDF, JPG or PNG, up to 5 MB each, 3 files at most. Only you and InternFeel admins can open them. You can also add proof later from your dashboard.', 'internfeel' ); ?></span>
						<ul class="if-proofs" data-if-proof-list>
							<?php foreach ( $if_proofs as $if_proof ) : ?>
								<li data-file="<?php echo esc_attr( (string) $if_proof['file'] ); ?>"><span><?php echo esc_html( (string) $if_proof['name'] ); ?></span> <button type="button" class="if-btn if-btn--ghost if-btn--sm" data-if-proof-remove><?php esc_html_e( 'Remove', 'internfeel' ); ?></button></li>
							<?php endforeach; ?>
						</ul>
					</div>

					<section class="if-submit__preview" aria-labelledby="if-preview-title">
						<h3 id="if-preview-title" class="heading-3"><?php esc_html_e( 'Preview', 'internfeel' ); ?></h3>
						<div data-if-preview></div>
					</section>

					<label class="if-check"><input type="checkbox" name="guidelines" value="1" required> <span>
						<?php
						printf(
							/* translators: %s: link to the content guidelines. */
							esc_html__( 'This is my own experience, and it follows the %s.', 'internfeel' ),
							'<a href="' . esc_url( home_url( '/content-guidelines/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'content guidelines', 'internfeel' ) . '</a>'
						);
						?>
					</span></label>
				</section>

				<p class="if-field__error if-form__status" role="alert" data-if-status></p>

				<div class="if-submit__actions">
					<span class="small" aria-live="polite" data-if-saved></span>
					<div class="if-submit__buttons">
						<button type="button" class="if-btn if-btn--ghost" data-if-back hidden><?php esc_html_e( 'Back', 'internfeel' ); ?></button>
						<button type="button" class="if-btn if-btn--ghost" data-if-save-exit><?php esc_html_e( 'Save and exit', 'internfeel' ); ?></button>
						<button type="submit" class="if-btn if-btn--primary" data-if-next>
							<span data-if-next-label><?php esc_html_e( 'Next: your story', 'internfeel' ); ?></span>
							<svg class="if-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
						</button>
					</div>
				</div>
			</form>

			<div class="if-submit__done" data-if-done hidden>
				<h2 class="heading-2" tabindex="-1"><?php esc_html_e( 'Submitted for review', 'internfeel' ); ?></h2>
				<p><?php esc_html_e( 'We review every experience within 24 hours. You will see the result on your dashboard.', 'internfeel' ); ?></p>
				<p><a class="if-btn if-btn--primary" href="<?php echo esc_url( $if_home ); ?>"><?php esc_html_e( 'Go to your dashboard', 'internfeel' ); ?></a></p>
			</div>

			<aside class="if-submit__aside">
				<section class="if-submit__tip">
					<h2 class="heading-3"><?php esc_html_e( 'Before you start', 'internfeel' ); ?></h2>
					<ul>
						<li><?php esc_html_e( 'Keep your offer letter or certificate handy. You can add it as proof in step 4, or later from your dashboard.', 'internfeel' ); ?></li>
						<li><?php esc_html_e( 'Write the way you would tell a junior: dates, money, the tools you used.', 'internfeel' ); ?></li>
						<li><?php esc_html_e( 'Posting anonymously hides only your name, photo and LinkedIn. Your college, course and the details stay visible.', 'internfeel' ); ?></li>
						<li><?php esc_html_e( 'We review every experience within 24 hours.', 'internfeel' ); ?></li>
					</ul>
				</section>
				<section class="if-submit__questions">
					<h2 class="heading-3"><?php esc_html_e( 'The questions in step 2', 'internfeel' ); ?></h2>
					<ol class="small">
						<?php foreach ( Questions::all() as $if_question ) : ?>
							<li><?php echo esc_html( $if_question ); ?></li>
						<?php endforeach; ?>
					</ol>
				</section>
			</aside>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
