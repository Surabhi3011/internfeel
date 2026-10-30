<?php
/**
 * Admin tools for invites (plan.md 5.5 abuse rules): flagged sign-ups under Review, and removing a
 * credit from a student's profile screen. Every decision is written to the moderation log.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Invites;

use InternFeel\Auth\Roles;
use InternFeel\Fields\Fields;
use InternFeel\Module;
use InternFeel\Moderation\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Review → Flagged referrals, and an Invites section on the user edit screen.
 */
final class Admin implements Module {

	public const SLUG   = 'internfeel-referrals';
	public const ACTION = 'if_referral';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'edit_user_profile', array( self::class, 'profile_section' ) );
	}

	/**
	 * Review → Flagged referrals.
	 */
	public static function menu(): void {
		$count = count( self::flagged() );

		add_submenu_page(
			Queue::SLUG,
			__( 'Flagged referrals', 'internfeel' ),
			__( 'Flagged referrals', 'internfeel' ) . ( $count > 0 ? sprintf( ' <span class="awaiting-mod">%d</span>', $count ) : '' ),
			Roles::MODERATE,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Students whose sign-up through an invite was flagged.
	 *
	 * @return list<\WP_User>
	 */
	public static function flagged(): array {
		$users = get_users(
			array(
				'meta_key'     => Fields::PREFIX . 'referral_flagged', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a short admin list.
				'meta_compare' => 'EXISTS',
				'number'       => 100,
			)
		);

		return array_values( array_filter( $users, static fn( mixed $user ): bool => $user instanceof \WP_User ) );
	}

	/**
	 * The Flagged referrals screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( Roles::MODERATE ) ) {
			return;
		}

		$notice = get_transient( 'if_referral_notice_' . get_current_user_id() );
		delete_transient( 'if_referral_notice_' . get_current_user_id() );
		$users = self::flagged();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Flagged referrals', 'internfeel' ); ?></h1>
			<p><?php esc_html_e( 'Sign-ups through an invite from the inviter’s own IP address within 24 hours of the invite. Keep the referral if it looks genuine, or remove it so the inviter is not credited.', 'internfeel' ); ?></p>
			<?php if ( is_string( $notice ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( array() === $users ) : ?>
				<p><?php esc_html_e( 'Nothing flagged.', 'internfeel' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'New student', 'internfeel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Invited by', 'internfeel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Joined (UTC)', 'internfeel' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Decision', 'internfeel' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $users as $user ) : ?>
							<?php $inviter = get_userdata( (int) get_user_meta( $user->ID, Fields::PREFIX . 'referred_by_user_id', true ) ); ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a></td>
								<td>
									<?php if ( $inviter instanceof \WP_User ) : ?>
										<a href="<?php echo esc_url( get_edit_user_link( $inviter->ID ) ); ?>"><?php echo esc_html( $inviter->display_name ); ?></a>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) get_user_meta( $user->ID, Fields::PREFIX . 'referred_at', true ) ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
										<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>">
										<?php wp_nonce_field( self::ACTION . '_' . $user->ID ); ?>
										<button class="button" type="submit" name="decision" value="keep"><?php esc_html_e( 'Keep referral', 'internfeel' ); ?></button>
										<button class="button button-link-delete" type="submit" name="decision" value="remove"><?php esc_html_e( 'Remove referral', 'internfeel' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The Invites section on a student's edit screen, with Remove one credit.
	 *
	 * @param \WP_User $user The user being edited.
	 */
	public static function profile_section( \WP_User $user ): void {
		if ( ! current_user_can( Roles::MODERATE ) ) {
			return;
		}

		$summary = Invites::summary( $user->ID );
		$inviter = get_userdata( (int) get_user_meta( $user->ID, Fields::PREFIX . 'referred_by_user_id', true ) );
		?>
		<h2><?php esc_html_e( 'InternFeel invites', 'internfeel' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Credits', 'internfeel' ); ?></th>
				<td>
					<?php echo esc_html( number_format_i18n( $summary['credits'] ) ); ?>
					<?php if ( $summary['credits'] > 0 ) : ?>
						<?php // A link, not a nested form: this section sits inside the profile form. ?>
						<a class="button" href="
						<?php
						echo esc_url(
							wp_nonce_url(
								add_query_arg(
									array(
										'action'   => self::ACTION,
										'user_id'  => $user->ID,
										'decision' => 'remove_credit',
									),
									admin_url( 'admin-post.php' )
								),
								self::ACTION . '_' . $user->ID
							)
						);
						?>
												"><?php esc_html_e( 'Remove one credit', 'internfeel' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Links made, friends joined', 'internfeel' ); ?></th>
				<td><?php echo esc_html( count( $summary['invites'] ) . ', ' . $summary['joined'] ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Joined through', 'internfeel' ); ?></th>
				<td><?php echo $inviter instanceof \WP_User ? esc_html( $inviter->display_name ) : esc_html__( 'No invite', 'internfeel' ); ?></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Keep or remove a flagged referral, or remove a credit.
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- checked by check_admin_referer() below.
		$user_id  = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;
		$decision = isset( $_REQUEST['decision'] ) ? sanitize_key( wp_unslash( $_REQUEST['decision'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		if ( ! current_user_can( Roles::MODERATE ) ) {
			wp_die( esc_html__( 'Only admins can do this.', 'internfeel' ), 403 );
		}
		check_admin_referer( self::ACTION . '_' . $user_id );

		$actor = get_current_user_id();
		if ( 'remove_credit' === $decision ) {
			$message = Invites::remove_credit( $user_id, $actor ) ? __( 'One credit removed and logged.', 'internfeel' ) : __( 'The student has no credits.', 'internfeel' );
			$back    = get_edit_user_link( $user_id );
		} elseif ( 'remove' === $decision ) {
			$message = Invites::remove_referral( $user_id, $actor ) ? __( 'Referral removed and logged.', 'internfeel' ) : __( 'There was no referral to remove.', 'internfeel' );
			$back    = admin_url( 'admin.php?page=' . self::SLUG );
		} else {
			Invites::clear_flag( $user_id, $actor );
			$message = __( 'Referral kept.', 'internfeel' );
			$back    = admin_url( 'admin.php?page=' . self::SLUG );
		}

		set_transient( 'if_referral_notice_' . $actor, $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( $back );
		exit;
	}
}
