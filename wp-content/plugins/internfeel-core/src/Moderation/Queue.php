<?php
/**
 * The review queue in wp-admin (architecture.md 5.8): new submissions, imported experiences, proof,
 * master-list suggestions and the moderation log, oldest first.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Moderation;

use InternFeel\Auth\Roles;
use InternFeel\Fields\Fields;
use InternFeel\Submit\Form;
use InternFeel\Verify\Proofs;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Review screen. Decisions post to admin-post.php (Moderation::handle_form).
 */
final class Queue {

	public const SLUG     = 'internfeel-review';
	public const PER_PAGE = 20;

	/**
	 * Items waiting longer than this are highlighted, against the 24-hour promise.
	 */
	public const OVERDUE_HOURS = 18;

	/**
	 * Add the Review menu, with a count of what is waiting.
	 */
	public static function menu(): void {
		$waiting = self::count( 'new' ) + self::count( 'proofs' );
		$bubble  = $waiting > 0 ? sprintf( ' <span class="awaiting-mod">%d</span>', $waiting ) : '';

		add_menu_page(
			__( 'Review queue', 'internfeel' ),
			__( 'Review', 'internfeel' ) . $bubble,
			Roles::MODERATE,
			self::SLUG,
			array( self::class, 'render' ),
			'dashicons-yes-alt',
			3
		);
	}

	/**
	 * The queue's address, on one tab.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public static function url( string $tab = '' ): string {
		$url = admin_url( 'admin.php?page=' . self::SLUG );

		return array_key_exists( $tab, self::tabs() ) ? add_query_arg( 'tab', $tab, $url ) : $url;
	}

	/**
	 * Tabs and their labels.
	 *
	 * @return array<string, string>
	 */
	private static function tabs(): array {
		return array(
			'new'         => __( 'New submissions', 'internfeel' ),
			'imported'    => __( 'Imported', 'internfeel' ),
			'proofs'      => __( 'Proof', 'internfeel' ),
			'suggestions' => __( 'Suggestions', 'internfeel' ),
			'log'         => __( 'Log', 'internfeel' ),
		);
	}

	/**
	 * The query for one tab.
	 *
	 * @param string $tab  Tab key.
	 * @param int    $page Page number.
	 * @return array<string, mixed> WP_Query arguments.
	 */
	public static function query_args( string $tab, int $page = 1 ): array {
		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => self::PER_PAGE,
			'paged'          => max( 1, $page ),
			'orderby'        => 'date',
			'order'          => 'ASC',
		);

		$legacy = array( 'key' => Fields::PREFIX . 'legacy_wp_id' );

		switch ( $tab ) {
			case 'imported':
				$args['post_status'] = 'pending';
				$args['meta_query']  = array( array_merge( $legacy, array( 'compare' => 'EXISTS' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin screen.
				break;
			case 'proofs':
				$args['post_status'] = array( 'draft', 'pending', 'publish' );
				$args['meta_query']  = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin screen.
					array(
						'key'   => Fields::PREFIX . 'verification_status',
						'value' => 'pending',
					),
				);
				$args['orderby']     = 'modified';
				break;
			case 'suggestions':
				$args['post_status'] = array( 'draft', 'pending', 'publish' );
				$args['meta_query']  = array( 'relation' => 'OR' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin screen.
				foreach ( Decisions::SUGGESTIONS as $field ) {
					$args['meta_query'][] = array(
						'key'     => Fields::PREFIX . $field . '_suggestion',
						'value'   => '',
						'compare' => '!=',
					);
				}
				break;
			default:
				// New submissions are ordered by when they were submitted, so a draft started long ago
				// but submitted today is not shown as overdue.
				$args['post_status'] = 'pending';
				$args['meta_query']  = array( array_merge( $legacy, array( 'compare' => 'NOT EXISTS' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin screen.
				$args['orderby']     = 'modified';
		}

		return $args;
	}

	/**
	 * How many items a tab holds.
	 *
	 * @param string $tab Tab key.
	 * @return int
	 */
	public static function count( string $tab ): int {
		$query = new \WP_Query(
			array_merge(
				self::query_args( $tab ),
				array(
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			)
		);

		return $query->found_posts;
	}

	/**
	 * The Review screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( Roles::MODERATE ) ) {
			return;
		}

		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'new'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab  = array_key_exists( $tab, $tabs ) ? $tab : 'new';
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.

		$notice = get_transient( 'if_moderation_notice_' . get_current_user_id() );
		delete_transient( 'if_moderation_notice_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Review queue', 'internfeel' ); ?></h1>
			<p><?php esc_html_e( 'Oldest first. Anything waiting more than 18 hours is highlighted: we promise students a decision within 24 hours. Notes you write are shown to the student.', 'internfeel' ); ?></p>

			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice <?php echo $notice['ok'] ? 'notice-success' : 'notice-error'; ?> is-dismissible">
					<p><strong><?php echo esc_html( (string) $notice['title'] ); ?>:</strong> <?php echo esc_html( (string) $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $label ); ?>
						<?php if ( 'log' !== $key ) : ?>
							<span class="count">(<?php echo esc_html( number_format_i18n( self::count( $key ) ) ); ?>)</span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'log' === $tab ) {
				self::render_log();
			} else {
				self::render_items( $tab, $page );
			}
			?>
		</div>
		<style>
			.if-review { display: grid; gap: 16px; margin-top: 16px; max-width: 72rem; }
			.if-review__item { background: #fff; border: 1px solid #c3c4c7; border-left-width: 4px; padding: 12px 16px; }
			.if-review__item.is-overdue { border-left-color: #d63638; }
			.if-review__item h2 { margin: 0 0 4px; font-size: 16px; }
			.if-review__meta { margin: 0 0 8px; color: #50575e; }
			.if-review__overdue { color: #b32d2e; font-weight: 600; }
			.if-review__excerpt { margin: 0 0 12px; max-width: 60rem; }
			.if-review__form { display: grid; gap: 8px; max-width: 40rem; }
			.if-review__form textarea { width: 100%; }
			.if-review__buttons { display: flex; flex-wrap: wrap; gap: 8px; }
			.if-review__suggestion { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
		</style>
		<?php
	}

	/**
	 * The items on one tab, with their decision forms.
	 *
	 * @param string $tab  Tab key.
	 * @param int    $page Page number.
	 */
	private static function render_items( string $tab, int $page ): void {
		$query = new \WP_Query( self::query_args( $tab, $page ) );
		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'Nothing waiting here.', 'internfeel' ) . '</p>';
			return;
		}

		echo '<div class="if-review">';
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				self::render_item( $post, $tab );
			}
		}
		echo '</div>';

		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%', self::url( $tab ) ),
				'format'  => '',
				'current' => $page,
				'total'   => (int) $query->max_num_pages,
			)
		);
		if ( is_string( $links ) ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
		}
	}

	/**
	 * One experience in the queue.
	 *
	 * @param \WP_Post $post Experience.
	 * @param string   $tab  Tab key.
	 */
	private static function render_item( \WP_Post $post, string $tab ): void {
		$values   = Form::values( $post->ID );
		$imported = Decisions::is_imported( $post );
		$since    = self::waiting_since( $post, $tab );
		$overdue  = ! $imported && $since > 0 && time() - $since > self::OVERDUE_HOURS * HOUR_IN_SECONDS;

		if ( $imported ) {
			$year = (int) get_post_meta( $post->ID, Fields::PREFIX . 'legacy_published_year', true );
			/* translators: 1: student name from the old site, 2: year. */
			$byline = sprintf( __( 'Imported · %1$s · %2$d', 'internfeel' ), (string) get_post_meta( $post->ID, Fields::PREFIX . 'legacy_student_name', true ), $year );
		} else {
			$author = get_userdata( (int) $post->post_author );
			$byline = $author instanceof \WP_User ? $author->display_name : __( 'Unknown student', 'internfeel' );
		}

		$view = 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post );
		?>
		<article class="if-review__item<?php echo $overdue ? ' is-overdue' : ''; ?>">
			<h2>
				<a href="<?php echo esc_url( (string) $view ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $post ) ); ?></a>
			</h2>
			<p class="if-review__meta">
				<?php echo esc_html( $byline ); ?>
				<?php if ( '' !== (string) $values['organisation'] ) : ?>
					· <?php echo esc_html( (string) $values['organisation'] ); ?>
				<?php endif; ?>
				· <?php echo esc_html( self::status_label( $post ) ); ?>
				<?php if ( $since > 0 && ! $imported ) : ?>
					·
					<span class="<?php echo $overdue ? 'if-review__overdue' : ''; ?>">
						<?php
						/* translators: %s: time, such as "3 hours". */
						echo esc_html( sprintf( __( 'waiting %s', 'internfeel' ), human_time_diff( $since ) ) );
						?>
					</span>
				<?php endif; ?>
				· <a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php esc_html_e( 'Edit', 'internfeel' ); ?></a>
			</p>

			<?php if ( 'proofs' !== $tab && 'suggestions' !== $tab ) : ?>
				<p class="if-review__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ), 45, '…' ) ); ?></p>
			<?php endif; ?>

			<?php
			if ( 'proofs' === $tab ) {
				self::render_proof_form( $post );
			} elseif ( 'suggestions' === $tab ) {
				self::render_suggestion_forms( $post );
			} else {
				self::render_review_form( $post, $imported );
			}
			?>
		</article>
		<?php
	}

	/**
	 * Approve, request changes or reject.
	 *
	 * @param \WP_Post $post     Experience.
	 * @param bool     $imported Whether it came from the old site.
	 */
	private static function render_review_form( \WP_Post $post, bool $imported ): void {
		self::open_form( $post, $imported ? 'imported' : 'new' );
		?>
			<label for="if-note-<?php echo esc_attr( (string) $post->ID ); ?>"><?php esc_html_e( 'Note for the student (required to request changes or reject)', 'internfeel' ); ?></label>
			<textarea id="if-note-<?php echo esc_attr( (string) $post->ID ); ?>" name="note" rows="2"></textarea>
			<div class="if-review__buttons">
				<button type="submit" name="decision" value="approve" class="button button-primary"><?php esc_html_e( 'Approve and publish', 'internfeel' ); ?></button>
				<?php if ( ! $imported ) : ?>
					<button type="submit" name="decision" value="request_changes" class="button"><?php esc_html_e( 'Request changes', 'internfeel' ); ?></button>
				<?php endif; ?>
				<button type="submit" name="decision" value="reject" class="button button-link-delete"><?php esc_html_e( 'Reject', 'internfeel' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * The proof files, and verify or reject.
	 *
	 * @param \WP_Post $post Experience.
	 */
	private static function render_proof_form( \WP_Post $post ): void {
		$nonce = wp_create_nonce( 'wp_rest' );
		?>
		<ul>
			<?php foreach ( Proofs::list_for( $post->ID ) as $proof ) : ?>
				<li>
					<a href="<?php echo esc_url( add_query_arg( '_wpnonce', $nonce, $proof['url'] ) ); ?>"><?php echo esc_html( $proof['name'] ); ?></a>
					(<?php echo esc_html( size_format( $proof['size'] ) ); ?>)
				</li>
			<?php endforeach; ?>
		</ul>
		<?php self::open_form( $post, 'proofs' ); ?>
			<label for="if-note-<?php echo esc_attr( (string) $post->ID ); ?>"><?php esc_html_e( 'Note for the student (required to reject)', 'internfeel' ); ?></label>
			<textarea id="if-note-<?php echo esc_attr( (string) $post->ID ); ?>" name="note" rows="2"></textarea>
			<div class="if-review__buttons">
				<button type="submit" name="decision" value="verify" class="button button-primary"><?php esc_html_e( 'Verify proof', 'internfeel' ); ?></button>
				<button type="submit" name="decision" value="reject_proof" class="button button-link-delete"><?php esc_html_e( 'Reject proof', 'internfeel' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * One form per suggested name: add it to the master list, optionally correcting the spelling.
	 *
	 * @param \WP_Post $post Experience.
	 */
	private static function render_suggestion_forms( \WP_Post $post ): void {
		$labels = array(
			'organisation' => __( 'Organisation', 'internfeel' ),
			'programme'    => __( 'Programme', 'internfeel' ),
			'field'        => __( 'Field', 'internfeel' ),
		);
		foreach ( Decisions::SUGGESTIONS as $field ) {
			$suggestion = (string) get_post_meta( $post->ID, Fields::PREFIX . $field . '_suggestion', true );
			if ( '' === $suggestion ) {
				continue;
			}
			$id = 'if-name-' . $post->ID . '-' . $field;
			self::open_form( $post, 'suggestions' );
			?>
				<div class="if-review__suggestion">
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $labels[ $field ] ); ?></label>
					<input id="<?php echo esc_attr( $id ); ?>" type="text" name="name" value="<?php echo esc_attr( $suggestion ); ?>" class="regular-text">
					<input type="hidden" name="field" value="<?php echo esc_attr( $field ); ?>">
					<button type="submit" name="decision" value="add_suggestion" class="button"><?php esc_html_e( 'Add to master list', 'internfeel' ); ?></button>
				</div>
			</form>
			<?php
		}
	}

	/**
	 * The opening tag and hidden fields of a decision form.
	 *
	 * @param \WP_Post $post Experience.
	 * @param string   $tab  Tab to return to.
	 */
	private static function open_form( \WP_Post $post, string $tab ): void {
		?>
		<form class="if-review__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Moderation::FORM_ACTION ); ?>">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( Moderation::FORM_ACTION . '_' . $post->ID ); ?>
		<?php
	}

	/**
	 * The latest log entries.
	 */
	private static function render_log(): void {
		$entries = Log::recent( 100 );
		if ( array() === $entries ) {
			echo '<p>' . esc_html__( 'No decisions yet.', 'internfeel' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="margin-top: 16px">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'When (UTC)', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Who', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Experience', 'internfeel' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Note', 'internfeel' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php $actor = get_userdata( $entry['actor_id'] ); ?>
					<tr>
						<td><?php echo esc_html( $entry['created_at'] ); ?></td>
						<td><?php echo esc_html( $actor instanceof \WP_User ? $actor->display_name : '#' . $entry['actor_id'] ); ?></td>
						<td><?php echo esc_html( trim( $entry['action'] . ' ' . $entry['reason'] ) ); ?></td>
						<td>
							<?php if ( in_array( $entry['target_type'], array( 'experience', 'proof' ), true ) ) : ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $entry['target_id'] ) ); ?>"><?php echo esc_html( get_the_title( $entry['target_id'] ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $entry['target_type'] . ' #' . $entry['target_id'] ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $entry['note'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * When the item started waiting: the submission time for new experiences, the last change for proof.
	 *
	 * @param \WP_Post $post Experience.
	 * @param string   $tab  Tab key.
	 * @return int Unix time, or 0 if unknown.
	 */
	private static function waiting_since( \WP_Post $post, string $tab ): int {
		$submitted = (string) get_post_meta( $post->ID, Fields::PREFIX . 'submitted_at', true );
		if ( 'new' === $tab && '' !== $submitted ) {
			return (int) strtotime( $submitted );
		}

		return (int) strtotime( $post->post_modified_gmt . ' UTC' );
	}

	/**
	 * The experience's publication and proof status in words.
	 *
	 * @param \WP_Post $post Experience.
	 * @return string
	 */
	private static function status_label( \WP_Post $post ): string {
		$status = get_post_status_object( $post->post_status );
		$label  = $status instanceof \stdClass && isset( $status->label ) ? (string) $status->label : $post->post_status;

		$proof = (string) get_post_meta( $post->ID, Fields::PREFIX . 'verification_status', true );
		if ( 'pending' === $proof ) {
			$label .= ' · ' . __( 'proof waiting', 'internfeel' );
		} elseif ( 'verified' === $proof ) {
			$label .= ' · ' . __( 'verified', 'internfeel' );
		}

		return $label;
	}
}
