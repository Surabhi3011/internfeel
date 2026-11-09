<?php
/**
 * Sign-up page (/signup/), from docs/approved-designs/signup.html.
 *
 * Signed out: "Create your account" (email and password, or Google).
 * Signed in without a name (a Google sign-up that gave none): "What should we call you?". The rest of
 * the profile is filled in later, from Profile on the dashboard.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

if_no_page_cache();

$if_complete = is_user_logged_in() && if_core_active() && \InternFeel\Auth\Profile::has_name( get_current_user_id() );
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

				<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/auth/signup' ) ); ?>">
					<input type="hidden" name="_if_nonce" value="<?php echo esc_attr( wp_create_nonce( 'if_auth' ) ); ?>">
					<?php if_honeypot_field(); ?>

					<div class="if-field">
						<label for="if-signup-name"><?php esc_html_e( 'Full name', 'internfeel' ); ?></label>
						<input type="text" id="if-signup-name" name="full_name" autocomplete="name" maxlength="120" required aria-describedby="if-signup-name-help">
						<span class="if-field__help" id="if-signup-name-help"><?php esc_html_e( 'Shown on your comments and printed on your certificates.', 'internfeel' ); ?></span>
					</div>

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
			<section class="if-auth__card" aria-labelledby="if-profile-title">
				<div class="if-auth__head">
					<h1 id="if-profile-title" class="heading-2"><?php esc_html_e( 'What should we call you?', 'internfeel' ); ?></h1>
					<p class="small if-auth__fine-print"><?php esc_html_e( 'You can add your college and course later, from Profile on your dashboard.', 'internfeel' ); ?></p>
				</div>
				<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/me' ) ); ?>" data-if-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
					<input type="hidden" name="name_only" value="1">
					<div class="if-field">
						<label for="if-full-name"><?php esc_html_e( 'Full name', 'internfeel' ); ?></label>
						<input type="text" id="if-full-name" name="full_name" autocomplete="name" maxlength="120" required aria-describedby="if-full-name-help">
						<span class="if-field__help" id="if-full-name-help"><?php esc_html_e( 'Shown on your comments and printed on your certificates.', 'internfeel' ); ?></span>
					</div>
					<p class="if-field__error if-form__status" role="alert" data-if-status></p>
					<button type="submit" class="if-btn if-btn--primary if-btn--block"><?php esc_html_e( 'Go to your dashboard', 'internfeel' ); ?></button>
				</form>
			</section>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
