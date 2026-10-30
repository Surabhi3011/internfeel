<?php
/**
 * Review → Reports: reported experiences and comments, flagged ones first, with Keep (dismiss the
 * reports) or Remove. Both decisions are logged.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Reports;

use InternFeel\Auth\Roles;
use InternFeel\Module;
use InternFeel\Moderation\Log;
use InternFeel\Moderation\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * The admin screen and its form handler.
 */
final class Admin implements Module {

	public const SLUG   = 'internfeel-reports';
	public const ACTION = 'if_reports';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Review → Reports, with a count of flagged items.
	 */
	public static function menu(): void {
		$flagged = count( array_filter( self::items(), static fn( array $item ): bool => $item['flagged'] ) );

		add_submenu_page(
			Queue::SLUG,
			__( 'Reports', 'internfeel' ),
			__( 'Reports', 'internfeel' ) . ( $flagged > 0 ? sprintf( ' <span class="awaiting-mod">%d</span>', $flagged ) : '' ),
			Roles::MODERATE,
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Every reported experience and comment, flagged first, then most reported.
	 *
	 * @return list<array{type: string, id: int, title: string, link: string, excerpt: string, count: int, flagged: bool}>
	 */
	public static function items(): array {
		$items = array();

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_key'       => Reports::COUNT_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a short admin list.
				'meta_value'     => '0', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- a short admin list.
				'meta_compare'   => '>',
				'meta_type'      => 'NUMERIC',
			)
		);
		foreach ( $posts as $post ) {
			$items[] = array(
				'type'    => 'experience',
				'id'      => $post->ID,
				'title'   => get_the_title( $post ),
				'link'    => (string) get_permalink( $post ),
				'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' ),
				'count'   => Reports::count( 'experience', $post->ID ),
				'flagged' => Reports::is_flagged( 'experience', $post->ID ),
			);
		}

		$comments = get_comments(
			array(
				'status'     => 'all',
				'number'     => 100,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a short admin list.
					array(
						'key'     => Reports::COUNT_KEY,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		foreach ( is_array( $comments ) ? $comments : array() as $comment ) {
			if ( ! $comment instanceof \WP_Comment || 'trash' === $comment->comment_approved ) {
				continue;
			}
			$items[] = array(
				'type'    => 'comment',
				'id'      => (int) $comment->comment_ID,
				/* translators: %s: experience title. */
				'title'   => sprintf( __( 'Comment on “%s”', 'internfeel' ), get_the_title( (int) $comment->comment_post_ID ) ),
				'link'    => (string) get_permalink( (int) $comment->comment_post_ID ),
				'excerpt' => wp_trim_words( $comment->comment_content, 40, '…' ),
				'count'   => Reports::count( 'comment', (int) $comment->comment_ID ),
				'flagged' => Reports::is_flagged( 'comment', (int) $comment->comment_ID ),
			);
		}

		usort( $items, static fn( array $a, array $b ): int => array( $b['flagged'], $b['count'] ) <=> array( $a['flagged'], $a['count'] ) );

		return $items;
	}

	/**
	 * The Reports screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( Roles::MODERATE ) ) {
			return;
		}

		$notice = get_transient( 'if_reports_notice_' . get_current_user_id() );
		delete_transient( 'if_reports_notice_' . get_current_user_id() );
		$items   = self::items();
		$reasons = Reports::reasons();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reports', 'internfeel' ); ?></h1>
			<p>
				<?php
				/* translators: %d: number of reports that hides an item. */
				echo esc_html( sprintf( __( 'Readers can report an experience or a comment once each. At %d reports it is hidden from listings (comments are held) until you decide here.', 'internfeel' ), Reports::FLAG_AT ) );
				?>
			</p>
			<?php if ( is_string( $notice ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php if ( array() === $items ) : ?>
				<p><?php esc_html_e( 'No reports.', 'internfeel' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $items as $item ) : ?>
				<div class="card" style="max-width: 60rem">
					<h2 class="title">
						<a href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['title'] ); ?></a>
						<?php if ( $item['flagged'] ) : ?>
							<span style="color: #b32d2e"> · <?php esc_html_e( 'hidden', 'internfeel' ); ?></span>
						<?php endif; ?>
					</h2>
					<p><?php echo esc_html( $item['excerpt'] ); ?></p>
					<ul>
						<?php foreach ( Log::for_target( $item['type'], $item['id'] ) as $entry ) : ?>
							<?php if ( 'report' === $entry['action'] ) : ?>
								<li>
									<?php echo esc_html( ( $reasons[ $entry['reason'] ] ?? $entry['reason'] ) . ( '' !== $entry['note'] ? ': ' . $entry['note'] : '' ) ); ?>
									<span class="description">(<?php echo esc_html( $entry['created_at'] ); ?> UTC)</span>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
						<input type="hidden" name="type" value="<?php echo esc_attr( $item['type'] ); ?>">
						<input type="hidden" name="id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
						<?php wp_nonce_field( self::ACTION . '_' . $item['type'] . '_' . $item['id'] ); ?>
						<p>
							<label for="if-report-note-<?php echo esc_attr( $item['type'] . '-' . $item['id'] ); ?>"><?php esc_html_e( 'Note (kept with the decision; shown to the student if you remove their experience)', 'internfeel' ); ?></label><br>
							<input class="large-text" type="text" id="if-report-note-<?php echo esc_attr( $item['type'] . '-' . $item['id'] ); ?>" name="note">
						</p>
						<button class="button" type="submit" name="decision" value="keep"><?php esc_html_e( 'Keep and clear reports', 'internfeel' ); ?></button>
						<button class="button button-link-delete" type="submit" name="decision" value="remove"><?php echo 'experience' === $item['type'] ? esc_html__( 'Remove experience', 'internfeel' ) : esc_html__( 'Remove comment', 'internfeel' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Keep or remove.
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() below.
		$type     = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$note     = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! current_user_can( Roles::MODERATE ) || ! in_array( $type, Reports::TARGET_TYPES, true ) ) {
			wp_die( esc_html__( 'Only admins can do this.', 'internfeel' ), 403 );
		}
		check_admin_referer( self::ACTION . '_' . $type . '_' . $id );

		if ( 'remove' === $decision ) {
			Reports::remove( $type, $id, get_current_user_id(), $note );
			$message = __( 'Removed and logged.', 'internfeel' );
		} else {
			Reports::dismiss( $type, $id, get_current_user_id() );
			$message = __( 'Kept; reports cleared and logged.', 'internfeel' );
		}

		set_transient( 'if_reports_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}
}
