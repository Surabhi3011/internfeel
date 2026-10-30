<?php
/**
 * Invite landing page (/join/?ref=CODE, plan.md 5.5). InternFeel\Invites\Invites::remember_code()
 * has already kept a valid code in a 30-day cookie; signing up within that time credits the inviter.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

use InternFeel\Invites\Invites;

// The cookie is set per visitor, so this page must never come from the page cache.
if_no_page_cache();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public invite link.
$if_code    = isset( $_GET['ref'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['ref'] ) ) ) : '';
$if_open    = '' !== $if_code && if_core_active() ? Invites::open_invite( $if_code ) : null;
$if_inviter = '';
if ( null !== $if_open && ! Invites::hides_name( $if_open[0] ) ) {
	$if_user    = get_userdata( $if_open[0] );
	$if_inviter = $if_user instanceof WP_User ? $if_user->display_name : '';
}

get_header();
?>
<div class="if-container if-join">
	<section class="if-join__card">
		<?php if ( is_user_logged_in() ) : ?>
			<h1 class="heading-1"><?php esc_html_e( 'You already have an account', 'internfeel' ); ?></h1>
			<p><?php esc_html_e( 'Invite links are for friends who are new to InternFeel. Pass it on to someone who hasn’t joined yet.', 'internfeel' ); ?></p>
			<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>"><?php esc_html_e( 'Go to your dashboard', 'internfeel' ); ?></a>
		<?php elseif ( null !== $if_open ) : ?>
			<span class="label if-teal-text"><?php esc_html_e( 'You’re invited', 'internfeel' ); ?></span>
			<h1 class="heading-1">
				<?php
				echo '' === $if_inviter
					? esc_html__( 'A student invited you to InternFeel', 'internfeel' )
					/* translators: %s: inviter's name. */
					: esc_html( sprintf( __( '%s invited you to InternFeel', 'internfeel' ), $if_inviter ) );
				?>
			</h1>
			<p class="body-lg"><?php esc_html_e( 'Read real internship experiences from students who’ve been there, and share your own. Every published experience earns a certificate.', 'internfeel' ); ?></p>
			<div class="if-join__actions">
				<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>"><?php esc_html_e( 'Create your account', 'internfeel' ); ?></a>
				<a class="if-btn if-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Look around first', 'internfeel' ); ?></a>
			</div>
			<p class="small if-muted"><?php esc_html_e( 'This invite is kept on this browser for 30 days, so you can sign up later.', 'internfeel' ); ?></p>
		<?php else : ?>
			<h1 class="heading-1"><?php esc_html_e( 'This invite link can’t be used', 'internfeel' ); ?></h1>
			<p><?php esc_html_e( 'Each invite link works once, and this one has been used or isn’t valid. You can still join InternFeel.', 'internfeel' ); ?></p>
			<a class="if-btn if-btn--primary" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>"><?php esc_html_e( 'Create your account', 'internfeel' ); ?></a>
		<?php endif; ?>
	</section>
</div>
<?php
get_footer();
