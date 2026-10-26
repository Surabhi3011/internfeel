<?php
/**
 * Log-in page (/login/): the sign-up card from docs/approved-designs/signup.html with the switch on
 * Log in. Admins sign in at wp-login.php, where two-step sign-in applies.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

if_no_page_cache();

if ( is_user_logged_in() ) {
	wp_safe_redirect( current_user_can( 'edit_posts' ) || ! if_core_active() ? admin_url() : \InternFeel\Auth\Profile::home_url() );
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; the value is validated by the REST route.
$if_redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

get_header();
?>
<div class="if-container">
	<div class="if-auth">
		<?php if ( ! if_core_active() ) : ?>
			<h1 class="heading-1"><?php esc_html_e( 'Log-in is not available right now', 'internfeel' ); ?></h1>
		<?php else : ?>
			<section class="if-auth__card" aria-labelledby="if-login-title">
				<h1 id="if-login-title" class="heading-2"><?php esc_html_e( 'Log in to InternFeel', 'internfeel' ); ?></h1>

				<?php if_auth_switch( 'login' ); ?>
				<?php if_google_button( $if_redirect_to ); ?>

				<form class="if-form" method="post" data-if-rest="<?php echo esc_url( rest_url( 'internfeel/v1/auth/login' ) ); ?>" data-if-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
					<input type="hidden" name="_if_nonce" value="<?php echo esc_attr( wp_create_nonce( 'if_auth' ) ); ?>">
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $if_redirect_to ); ?>">
					<?php if_honeypot_field(); ?>

					<div class="if-field">
						<label for="if-login"><?php esc_html_e( 'Email', 'internfeel' ); ?></label>
						<input type="email" id="if-login" name="login" autocomplete="username" required>
					</div>

					<div class="if-field">
						<label for="if-password"><?php esc_html_e( 'Password', 'internfeel' ); ?></label>
						<input type="password" id="if-password" name="password" autocomplete="current-password" required>
						<a class="if-field__help" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot your password?', 'internfeel' ); ?></a>
					</div>

					<label class="if-check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Keep me logged in', 'internfeel' ); ?></label>

					<p class="if-field__error if-form__status" role="alert" data-if-status></p>

					<button type="submit" class="if-btn if-btn--primary if-btn--block"><?php esc_html_e( 'Log in', 'internfeel' ); ?></button>
				</form>

				<p class="small if-auth__fine-print">
					<?php esc_html_e( 'New to InternFeel?', 'internfeel' ); ?>
					<a href="<?php echo esc_url( home_url( '/signup/' ) ); ?>"><?php esc_html_e( 'Create an account', 'internfeel' ); ?></a>
				</p>
			</section>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
