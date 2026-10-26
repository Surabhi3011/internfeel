<?php
/**
 * Sign-up page (/signup/): create an account, then complete the profile (plan.md 5.1).
 *
 * Step 1 (signed out): email and password, or Google.
 * Step 2 (signed in, profile incomplete): name, college, course, graduation year and the under-18
 * question; display name and LinkedIn are optional.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

if_no_page_cache();

$if_complete = is_user_logged_in() && if_core_active() && \InternFeel\Auth\Profile::is_complete( get_current_user_id() );
if ( $if_complete || current_user_can( 'edit_posts' ) ) {
	wp_safe_redirect( if_core_active() ? \InternFeel\Auth\Profile::home_url() : home_url( '/' ) );
	exit;
}

get_header();
?>
<div class="if-container if-auth">
	<?php if ( ! if_core_active() ) : ?>
		<h1><?php esc_html_e( 'Sign-up is not available right now', 'internfeel' ); ?></h1>
	<?php elseif ( ! is_user_logged_in() ) : ?>
		<h1><?php esc_html_e( 'Create your InternFeel account', 'internfeel' ); ?></h1>
		<p class="if-auth__lead"><?php esc_html_e( 'Share your internship experience, get a certificate and help other students choose well.', 'internfeel' ); ?></p>

		<?php if_google_button(); ?>

		<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/auth/signup' ) ); ?>" data-if-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<input type="hidden" name="_if_nonce" value="<?php echo esc_attr( wp_create_nonce( 'if_auth' ) ); ?>">
			<?php if_honeypot_field(); ?>

			<div class="if-field">
				<label for="if-email"><?php esc_html_e( 'Email', 'internfeel' ); ?></label>
				<input type="email" id="if-email" name="email" autocomplete="email" required>
			</div>

			<div class="if-field">
				<label for="if-password"><?php esc_html_e( 'Password', 'internfeel' ); ?></label>
				<input type="password" id="if-password" name="password" autocomplete="new-password" minlength="8" maxlength="200" required aria-describedby="if-password-hint">
				<p class="if-field__hint" id="if-password-hint"><?php esc_html_e( 'At least 8 characters.', 'internfeel' ); ?></p>
			</div>

			<p class="if-form__status" role="alert" data-if-status></p>

			<button type="submit" class="if-button if-button--primary if-button--block"><?php esc_html_e( 'Create account', 'internfeel' ); ?></button>
		</form>

		<p class="if-auth__switch">
			<?php esc_html_e( 'Already have an account?', 'internfeel' ); ?>
			<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>"><?php esc_html_e( 'Log in', 'internfeel' ); ?></a>
		</p>
		<p class="if-auth__fine-print">
			<?php
			printf(
				/* translators: 1: terms of use link, 2: privacy policy link. */
				esc_html__( 'By creating an account you agree to the %1$s and the %2$s.', 'internfeel' ),
				'<a href="' . esc_url( home_url( '/terms/' ) ) . '">' . esc_html__( 'terms of use', 'internfeel' ) . '</a>',
				'<a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '">' . esc_html__( 'privacy policy', 'internfeel' ) . '</a>'
			);
			?>
		</p>
	<?php else : ?>
		<?php $if_profile = \InternFeel\Auth\Profile::get( get_current_user_id() ); ?>
		<h1><?php esc_html_e( 'Tell us about you', 'internfeel' ); ?></h1>
		<p class="if-auth__lead"><?php esc_html_e( 'This appears with your experiences. You can post anonymously later; your college and course always show.', 'internfeel' ); ?></p>

		<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/me' ) ); ?>" data-if-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<div class="if-form__row">
				<div class="if-field">
					<label for="if-first-name"><?php esc_html_e( 'First name', 'internfeel' ); ?></label>
					<input type="text" id="if-first-name" name="first_name" autocomplete="given-name" maxlength="120" required value="<?php echo esc_attr( (string) $if_profile['first_name'] ); ?>">
				</div>
				<div class="if-field">
					<label for="if-last-name"><?php esc_html_e( 'Last name (optional)', 'internfeel' ); ?></label>
					<input type="text" id="if-last-name" name="last_name" autocomplete="family-name" maxlength="120" value="<?php echo esc_attr( (string) $if_profile['last_name'] ); ?>">
				</div>
			</div>

			<div class="if-field">
				<label for="if-college"><?php esc_html_e( 'College or university', 'internfeel' ); ?></label>
				<input type="text" id="if-college" name="college" autocomplete="organization" maxlength="120" required value="<?php echo esc_attr( (string) $if_profile['college'] ); ?>">
			</div>

			<div class="if-form__row">
				<div class="if-field">
					<label for="if-course"><?php esc_html_e( 'Course', 'internfeel' ); ?></label>
					<input type="text" id="if-course" name="course" maxlength="120" required aria-describedby="if-course-hint" value="<?php echo esc_attr( (string) $if_profile['course'] ); ?>">
					<p class="if-field__hint" id="if-course-hint"><?php esc_html_e( 'For example BTech Computer Science.', 'internfeel' ); ?></p>
				</div>
				<div class="if-field">
					<label for="if-graduation-year"><?php esc_html_e( 'Graduation year', 'internfeel' ); ?></label>
					<input type="number" id="if-graduation-year" name="graduation_year" inputmode="numeric" min="1980" max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 8 ) ); ?>" required value="<?php echo $if_profile['graduation_year'] ? esc_attr( (string) $if_profile['graduation_year'] ) : ''; ?>">
				</div>
			</div>

			<fieldset class="if-field if-field--choice">
				<legend><?php esc_html_e( 'Are you under 18?', 'internfeel' ); ?></legend>
				<label><input type="radio" name="is_under_18" value="1" required <?php checked( true === $if_profile['is_under_18'] ); ?>> <?php esc_html_e( 'Yes', 'internfeel' ); ?></label>
				<label><input type="radio" name="is_under_18" value="0" required <?php checked( false === $if_profile['is_under_18'] ); ?>> <?php esc_html_e( 'No', 'internfeel' ); ?></label>
			</fieldset>

			<div class="if-field">
				<label for="if-display-name"><?php esc_html_e( 'Display name (optional)', 'internfeel' ); ?></label>
				<input type="text" id="if-display-name" name="display_name" maxlength="60" aria-describedby="if-display-name-hint">
				<p class="if-field__hint" id="if-display-name-hint"><?php esc_html_e( 'How your name appears on the site. Leave empty to use your first name and last initial.', 'internfeel' ); ?></p>
			</div>

			<div class="if-field">
				<label for="if-linkedin"><?php esc_html_e( 'LinkedIn profile (optional)', 'internfeel' ); ?></label>
				<input type="url" id="if-linkedin" name="linkedin_url" inputmode="url" maxlength="200" placeholder="https://www.linkedin.com/in/" value="<?php echo esc_attr( (string) $if_profile['linkedin_url'] ); ?>">
			</div>

			<p class="if-form__status" role="alert" data-if-status></p>

			<button type="submit" class="if-button if-button--primary if-button--block"><?php esc_html_e( 'Save and continue', 'internfeel' ); ?></button>
		</form>
	<?php endif; ?>
</div>
<?php
get_footer();
