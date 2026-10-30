<?php
/**
 * The Lists module: admins build listicles (if_list) by pasting experience links in order, and set
 * where each appears on the home page (plan.md 5.2, architecture.md 4.6).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Lists;

use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Module;

defined( 'ABSPATH' ) || exit;

/**
 * A meta box on the listicle edit screen, and the ordered, published experiences of a list.
 */
final class Lists implements Module {

	public const NONCE    = 'if_list_items';
	public const MAX      = 50;
	public const ITEMS    = Fields::PREFIX . 'list_items';
	public const HOME_KEY = Fields::PREFIX . 'home_order';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_' . Content::LIST_TYPE, array( self::class, 'meta_box' ) );
		add_action( 'save_post_' . Content::LIST_TYPE, array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Add the box.
	 */
	public static function meta_box(): void {
		add_meta_box( 'if-list-items', __( 'Experiences in this listicle', 'internfeel' ), array( self::class, 'render' ), Content::LIST_TYPE, 'normal', 'high' );
	}

	/**
	 * The box: one experience link (or ID) per line, in order, and the home page position.
	 *
	 * @param \WP_Post $post Listicle.
	 */
	public static function render( \WP_Post $post ): void {
		$lines = array_map( static fn( int $id ): string => (string) get_permalink( $id ), self::ids( $post->ID ) );
		$order = (int) get_post_meta( $post->ID, self::HOME_KEY, true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<p>
			<label for="if-list-items"><?php esc_html_e( 'Paste one experience link per line, in the order they should appear. Only published experiences are shown.', 'internfeel' ); ?></label>
		</p>
		<textarea id="if-list-items" name="if_list_items" rows="10" class="large-text code"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
		<?php if ( array() !== $lines ) : ?>
			<ol>
				<?php foreach ( self::ids( $post->ID ) as $id ) : ?>
					<li>
						<?php echo esc_html( get_the_title( $id ) ); ?>
						<?php if ( 'publish' !== get_post_status( $id ) ) : ?>
							<em>(<?php esc_html_e( 'not published, hidden', 'internfeel' ); ?>)</em>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<p>
			<label for="if-home-order"><?php esc_html_e( 'Home page position (1 shows first; 0 keeps it off the home page)', 'internfeel' ); ?></label>
			<input id="if-home-order" name="if_home_order" type="number" min="0" max="20" value="<?php echo esc_attr( (string) $order ); ?>" class="small-text">
		</p>
		<?php
	}

	/**
	 * Save the box.
	 *
	 * @param int      $post_id Listicle ID.
	 * @param \WP_Post $post    Listicle.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['if_list_items'] ) ? sanitize_textarea_field( wp_unslash( $_POST['if_list_items'] ) ) : '';
		update_post_meta( $post_id, self::ITEMS, self::parse( $raw ) );
		update_post_meta( $post_id, self::HOME_KEY, isset( $_POST['if_home_order'] ) ? min( 20, absint( $_POST['if_home_order'] ) ) : 0 );
	}

	/**
	 * Experience IDs from pasted links or IDs: experiences only, no repeats, at most 50.
	 *
	 * @param string $raw One link or ID per line.
	 * @return list<int>
	 */
	public static function parse( string $raw ): array {
		$ids   = array();
		$lines = preg_split( '/\R/', $raw );
		foreach ( false === $lines ? array() : $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$id = ctype_digit( $line ) ? (int) $line : url_to_postid( esc_url_raw( $line ) );
			if ( $id > 0 && 'post' === get_post_type( $id ) && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return array_slice( $ids, 0, self::MAX );
	}

	/**
	 * The listicle's experience IDs, in order.
	 *
	 * @param int $list_id Listicle.
	 * @return list<int>
	 */
	public static function ids( int $list_id ): array {
		return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $list_id, self::ITEMS, true ) ) ) );
	}

	/**
	 * The listicle's published experiences, in order.
	 *
	 * @param int $list_id Listicle.
	 * @param int $limit   How many.
	 * @return list<\WP_Post>
	 */
	public static function posts( int $list_id, int $limit = self::MAX ): array {
		$posts = array();
		foreach ( self::ids( $list_id ) as $id ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
				$posts[] = $post;
			}
			if ( count( $posts ) >= $limit ) {
				break;
			}
		}

		return $posts;
	}
}
