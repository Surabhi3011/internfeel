<?php
/**
 * Sign-up page (/signup/), from docs/approved-designs/signup.html.
 *
 * Signed out: "Create your account" (email and password, or Google).
 * Signed in with an incomplete profile: "Tell us a little about you" (first sign-in only).
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
<div class="if-container">
	<div class="if-auth">
		<?php if ( ! if_core_active() ) : ?>
			<h1 class="heading-1"><?php esc_html_e( 'Sign-up is not available right now', 'internfeel' ); ?></h1>
		<?php elseif ( ! is_user_logged_in() ) : ?>
			<div class="if-auth__intro">
				<h1 class="heading-1"><?php esc_html_e( 'Join InternFeel', 'internfeel' ); ?></h1>
				<p><?php esc_html_e( 'Free for students. Share your experiences, follow your views and comments, and download a certificate for each one we publish.', 'internfeel' ); ?></p>
			</div>

			<section class="if-auth__card" aria-labelledby="if-signup-title">
				<div class="if-auth__head">
					<span class="label"><?php esc_html_e( 'Step 1', 'internfeel' ); ?></span>
					<h2 id="if-signup-title" class="heading-2"><?php esc_html_e( 'Create your account', 'internfeel' ); ?></h2>
				</div>

				<?php if_auth_switch( 'signup' ); ?>
				<?php if_google_button( home_url( '/signup/' ) ); ?>

				<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/auth/signup' ) ); ?>" data-if-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
					<input type="hidden" name="_if_nonce" value="<?php echo esc_attr( wp_create_nonce( 'if_auth' ) ); ?>">
					<?php if_honeypot_field(); ?>

					<div class="if-field">
						<label for="if-email"><?php esc_html_e( 'Email', 'internfeel' ); ?></label>
						<input type="email" id="if-email" name="email" autocomplete="email" required>
					</div>

					<div class="if-field">
						<label for="if-password"><?php esc_html_e( 'Password', 'internfeel' ); ?></label>
						<input type="password" id="if-password" name="password" autocomplete="new-password" minlength="8" maxlength="200" required aria-describedby="if-password-help">
						<span class="if-field__help" id="if-password-help"><?php esc_html_e( 'At least 8 characters.', 'internfeel' ); ?></span>
					</div>

					<p class="if-field__error if-form__status" role="alert" data-if-status></p>

					<button type="submit" class="if-btn if-btn--primary if-btn--block"><?php esc_html_e( 'Create account', 'internfeel' ); ?></button>
				</form>

				<p class="small if-auth__fine-print">
					<?php
					printf(
						/* translators: 1: terms of use link, 2: privacy policy link, 3: log-in link, 4: forgotten password link. */
						esc_html__( 'By creating an account you agree to the %1$s and %2$s. Already have an account? %3$s. %4$s', 'internfeel' ),
						'<a href="' . esc_url( home_url( '/terms/' ) ) . '">' . esc_html__( 'Terms of use', 'internfeel' ) . '</a>',
						'<a href="' . esc_url( home_url( '/privacy-policy/' ) ) . '">' . esc_html__( 'Privacy policy', 'internfeel' ) . '</a>',
						'<a href="' . esc_url( home_url( '/login/' ) ) . '">' . esc_html__( 'Log in', 'internfeel' ) . '</a>',
						'<a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Forgot your password?', 'internfeel' ) . '</a>'
					);
					?>
				</p>
			</section>
		<?php else : ?>
			<?php $if_profile = \InternFeel\Auth\Profile::get( get_current_user_id() ); ?>
			<section class="if-auth__card" aria-labelledby="if-profile-title">
				<div class="if-auth__head">
					<span class="label"><?php esc_html_e( 'Step 2, first sign-in only', 'internfeel' ); ?></span>
					<h1 id="if-profile-title" class="heading-2"><?php esc_html_e( 'Tell us a little about you', 'internfeel' ); ?></h1>
					<p class="small if-auth__fine-print"><?php esc_html_e( 'You can change this later in Profile.', 'internfeel' ); ?></p>
				</div>

				<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/me' ) ); ?>" data-if-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
					<div class="if-field">
						<label for="if-full-name"><?php esc_html_e( 'Full name', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
						<input type="text" id="if-full-name" name="full_name" autocomplete="name" maxlength="120" required aria-describedby="if-full-name-help" value="<?php echo esc_attr( (string) $if_profile['full_name'] ); ?>">
						<span class="if-field__help" id="if-full-name-help"><?php esc_html_e( 'Printed on your certificates.', 'internfeel' ); ?></span>
					</div>

					<div class="if-field">
						<label for="if-display-name"><?php esc_html_e( 'Display name', 'internfeel' ); ?></label>
						<input type="text" id="if-display-name" name="display_name" maxlength="60" aria-describedby="if-display-name-help" value="<?php echo esc_attr( (string) $if_profile['display_name'] ); ?>">
						<span class="if-field__help" id="if-display-name-help"><?php esc_html_e( 'Optional. The name shown on your experiences and comments. Leave it blank to show your full name.', 'internfeel' ); ?></span>
					</div>

					<div class="if-field">
						<label for="if-college"><?php esc_html_e( 'College', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
						<input type="text" id="if-college" name="college" autocomplete="organization" maxlength="120" required value="<?php echo esc_attr( (string) $if_profile['college'] ); ?>">
					</div>

					<div class="if-form__two">
						<div class="if-field">
							<label for="if-course"><?php esc_html_e( 'Course', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
							<input type="text" id="if-course" name="course" maxlength="120" required value="<?php echo esc_attr( (string) $if_profile['course'] ); ?>">
						</div>
						<div class="if-field">
							<label for="if-graduation-year"><?php esc_html_e( 'Graduation year', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></label>
							<input type="number" id="if-graduation-year" name="graduation_year" inputmode="numeric" min="1980" max="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 8 ) ); ?>" required value="<?php echo $if_profile['graduation_year'] ? esc_attr( (string) $if_profile['graduation_year'] ) : ''; ?>">
						</div>
					</div>

					<div class="if-field">
						<label for="if-linkedin"><?php esc_html_e( 'LinkedIn profile', 'internfeel' ); ?></label>
						<input type="url" id="if-linkedin" name="linkedin_url" inputmode="url" maxlength="200" placeholder="https://www.linkedin.com/in/your-name" aria-describedby="if-linkedin-help" value="<?php echo esc_attr( (string) $if_profile['linkedin_url'] ); ?>">
						<span class="if-field__help" id="if-linkedin-help"><?php esc_html_e( 'Optional. Hidden on experiences you post anonymously.', 'internfeel' ); ?></span>
					</div>

					<fieldset class="if-field" aria-describedby="if-under-18-help">
						<legend><?php esc_html_e( 'Are you under 18?', 'internfeel' ); ?> <span class="if-req" aria-hidden="true">*</span></legend>
						<div class="if-segment">
							<label><input type="radio" name="is_under_18" value="0" required <?php checked( false === $if_profile['is_under_18'] ); ?>><span><?php esc_html_e( 'No', 'internfeel' ); ?></span></label>
							<label><input type="radio" name="is_under_18" value="1" required <?php checked( true === $if_profile['is_under_18'] ); ?>><span><?php esc_html_e( 'Yes', 'internfeel' ); ?></span></label>
						</div>
						<span class="if-field__help" id="if-under-18-help"><?php esc_html_e( 'If you are, we show you only non-personalised ads.', 'internfeel' ); ?></span>
					</fieldset>

					<p class="if-field__error if-form__status" role="alert" data-if-status></p>

					<button type="submit" class="if-btn if-btn--primary if-btn--block"><?php esc_html_e( 'Go to your dashboard', 'internfeel' ); ?></button>
				</form>
			</section>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
