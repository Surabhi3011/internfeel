<?php
/**
 * Admin decisions on experiences, proof and suggestions (architecture.md 5.1 and 5.8).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Moderation;

use InternFeel\Auth\Roles;
use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Notify\Notify;
use InternFeel\Submit\Form;
use InternFeel\Submit\Submit;

defined( 'ABSPATH' ) || exit;

/**
 * Each decision checks the experience is in the right state, makes the change, writes the moderation
 * log and notifies the student. The queue screen and the REST route both call apply().
 */
final class Decisions {

	/**
	 * Every decision.
	 */
	public const ACTIONS = array( 'approve', 'request_changes', 'reject', 'verify', 'reject_proof', 'add_suggestion' );

	/**
	 * Decisions the student must be told the reason for.
	 */
	private const NEEDS_NOTE = array( 'request_changes', 'reject', 'reject_proof' );

	/**
	 * Suggestion fields an admin can add to the master list.
	 */
	public const SUGGESTIONS = array( 'organisation', 'programme', 'field' );

	/**
	 * Apply one decision.
	 *
	 * @param string $action  One of self::ACTIONS.
	 * @param int    $post_id Experience ID.
	 * @param int    $actor   The admin deciding.
	 * @param string $note    Note for the student (required to reject or request changes).
	 * @param string $field   For add_suggestion: organisation, programme or field.
	 * @param string $name    For add_suggestion: the name to add, if the admin corrected it.
	 * @return string|\WP_Error A short confirmation for the admin.
	 */
	public static function apply( string $action, int $post_id, int $actor, string $note = '', string $field = '', string $name = '' ): string|\WP_Error {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'post' !== $post->post_type ) {
			return self::error( 'if_not_found', __( 'That experience was not found.', 'internfeel' ), 404 );
		}
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return self::error( 'if_unknown_action', __( 'Unknown moderation action.', 'internfeel' ) );
		}

		$note = trim( sanitize_textarea_field( $note ) );
		if ( '' === $note && in_array( $action, self::NEEDS_NOTE, true ) ) {
			return self::error( 'if_note_required', __( 'Write a note for the student explaining why.', 'internfeel' ) );
		}

		delete_transient( 'if_review_waiting' );

		return match ( $action ) {
			'approve'         => self::approve( $post, $actor, $note ),
			'request_changes' => self::send_back( $post, $actor, $note, 'draft' ),
			'reject'          => self::send_back( $post, $actor, $note, 'rejected' ),
			'verify'          => self::verify( $post, $actor, $note ),
			'reject_proof'    => self::reject_proof( $post, $actor, $note ),
			default           => self::add_suggestion( $post, $actor, $field, sanitize_text_field( $name ) ),
		};
	}

	/**
	 * Publish a pending experience.
	 *
	 * @param \WP_Post $post  Experience.
	 * @param int      $actor Admin.
	 * @param string   $note  Optional note.
	 * @return string|\WP_Error
	 */
	private static function approve( \WP_Post $post, int $actor, string $note ): string|\WP_Error {
		if ( 'pending' !== $post->post_status ) {
			return self::not_pending();
		}

		// Imported experiences keep their original slug and date; wp_update_post() keeps both when set.
		$updated = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		delete_post_meta( $post->ID, Fields::PREFIX . 'moderation_note' );
		$log = Log::add( $actor, 'experience', $post->ID, 'approve', '', $note );

		Notify::add(
			(int) $post->post_author,
			'submission_approved',
			/* translators: %s: experience title. */
			sprintf( __( 'Your experience “%s” is published.', 'internfeel' ), get_the_title( $post->ID ) ),
			(string) get_permalink( $post->ID ),
			"approved:{$post->ID}:{$log}"
		);

		$referrer = (int) get_post_meta( $post->ID, Fields::PREFIX . 'referrer_user_id', true );
		if ( $referrer > 0 ) {
			Notify::add(
				$referrer,
				'invite_published',
				__( 'A student you invited has published an experience.', 'internfeel' ),
				(string) get_permalink( $post->ID ),
				"invite_published:{$post->ID}"
			);
		}

		/**
		 * An experience has been published. Certificates hook in here.
		 *
		 * @param int $post_id Experience ID.
		 */
		do_action( 'if_experience_published', $post->ID );
		self::maybe_count_verified( $post->ID );

		return __( 'Published.', 'internfeel' );
	}

	/**
	 * Send a pending experience back to its student as a draft, or reject it.
	 *
	 * @param \WP_Post $post   Experience.
	 * @param int      $actor  Admin.
	 * @param string   $note   Why.
	 * @param string   $status draft or rejected.
	 * @return string|\WP_Error
	 */
	private static function send_back( \WP_Post $post, int $actor, string $note, string $status ): string|\WP_Error {
		if ( 'pending' !== $post->post_status ) {
			return self::not_pending();
		}
		if ( 'draft' === $status && self::is_imported( $post ) ) {
			return self::error( 'if_imported', __( 'Imported experiences have no student to send them back to. Edit it yourself, then approve or reject it.', 'internfeel' ), 409 );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => $status,
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		update_post_meta( $post->ID, Fields::PREFIX . 'moderation_note', wp_slash( $note ) );
		$changes = 'draft' === $status;
		$log     = Log::add( $actor, 'experience', $post->ID, $changes ? 'request_changes' : 'reject', '', $note );

		$title = get_the_title( $post->ID );
		Notify::add(
			(int) $post->post_author,
			$changes ? 'changes_requested' : 'submission_rejected',
			$changes
				/* translators: 1: experience title, 2: the reviewer's note. */
				? sprintf( __( 'Please make changes to “%1$s”: %2$s', 'internfeel' ), $title, $note )
				/* translators: 1: experience title, 2: the reviewer's note. */
				: sprintf( __( '“%1$s” was not published: %2$s', 'internfeel' ), $title, $note ),
			$changes ? Submit::url( $post->ID ) : '',
			( $changes ? 'changes_requested' : 'rejected' ) . ":{$post->ID}:{$log}"
		);

		return $changes ? __( 'Sent back to the student for changes.', 'internfeel' ) : __( 'Rejected.', 'internfeel' );
	}

	/**
	 * Accept the uploaded proof.
	 *
	 * @param \WP_Post $post  Experience.
	 * @param int      $actor Admin.
	 * @param string   $note  Optional note.
	 * @return string|\WP_Error
	 */
	private static function verify( \WP_Post $post, int $actor, string $note ): string|\WP_Error {
		if ( 'pending' !== get_post_meta( $post->ID, Fields::PREFIX . 'verification_status', true ) ) {
			return self::error( 'if_no_proof', __( 'This experience has no proof waiting to be checked.', 'internfeel' ), 409 );
		}

		update_post_meta( $post->ID, Fields::PREFIX . 'verification_status', 'verified' );
		update_post_meta( $post->ID, Fields::PREFIX . 'verified_by', $actor );
		update_post_meta( $post->ID, Fields::PREFIX . 'verified_at', current_time( 'mysql', true ) );
		delete_post_meta( $post->ID, Fields::PREFIX . 'verification_note' );
		$log = Log::add( $actor, 'proof', $post->ID, 'verify', '', $note );

		Notify::add(
			(int) $post->post_author,
			'proof_verified',
			/* translators: %s: experience title. */
			sprintf( __( 'Your proof for “%s” is verified.', 'internfeel' ), get_the_title( $post->ID ) ),
			self::student_link( $post ),
			"proof_verified:{$post->ID}:{$log}"
		);

		self::maybe_count_verified( $post->ID );

		return __( 'Proof verified.', 'internfeel' );
	}

	/**
	 * Turn down the uploaded proof; the student can upload again.
	 *
	 * @param \WP_Post $post  Experience.
	 * @param int      $actor Admin.
	 * @param string   $note  Why.
	 * @return string|\WP_Error
	 */
	private static function reject_proof( \WP_Post $post, int $actor, string $note ): string|\WP_Error {
		if ( 'pending' !== get_post_meta( $post->ID, Fields::PREFIX . 'verification_status', true ) ) {
			return self::error( 'if_no_proof', __( 'This experience has no proof waiting to be checked.', 'internfeel' ), 409 );
		}

		update_post_meta( $post->ID, Fields::PREFIX . 'verification_status', 'rejected' );
		update_post_meta( $post->ID, Fields::PREFIX . 'verification_note', wp_slash( $note ) );
		$log = Log::add( $actor, 'proof', $post->ID, 'reject_proof', '', $note );

		Notify::add(
			(int) $post->post_author,
			'proof_rejected',
			/* translators: 1: experience title, 2: the reviewer's note. */
			sprintf( __( 'Your proof for “%1$s” was not accepted: %2$s', 'internfeel' ), get_the_title( $post->ID ), $note ),
			self::student_link( $post ),
			"proof_rejected:{$post->ID}:{$log}"
		);

		return __( 'Proof rejected.', 'internfeel' );
	}

	/**
	 * Add a name the student typed to the master list (or match it to an entry with that name), and
	 * link the experience to it.
	 *
	 * @param \WP_Post $post  Experience.
	 * @param int      $actor Admin.
	 * @param string   $field organisation, programme or field.
	 * @param string   $name  Corrected name, or empty to use the student's.
	 * @return string|\WP_Error
	 */
	private static function add_suggestion( \WP_Post $post, int $actor, string $field, string $name ): string|\WP_Error {
		if ( ! in_array( $field, self::SUGGESTIONS, true ) ) {
			return self::error( 'if_unknown_field', __( 'Unknown suggestion field.', 'internfeel' ) );
		}

		$suggestion = (string) get_post_meta( $post->ID, Fields::PREFIX . $field . '_suggestion', true );
		$name       = '' !== trim( $name ) ? trim( $name ) : $suggestion;
		if ( '' === $suggestion || '' === $name ) {
			return self::error( 'if_no_suggestion', __( 'There is no suggestion to add.', 'internfeel' ), 409 );
		}

		$parent = 0;
		if ( 'programme' === $field ) {
			$parent = (int) get_post_meta( $post->ID, Fields::PREFIX . 'organisation_term', true );
			if ( $parent <= 0 ) {
				return self::error( 'if_organisation_first', __( 'Add the organisation first: programmes sit under their organisation.', 'internfeel' ), 409 );
			}
		}

		$term_id = self::find_term( $name, $field, $parent );
		if ( 0 === $term_id ) {
			$created = wp_insert_term( $name, Content::TAXONOMY, array( 'parent' => $parent ) );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$term_id = (int) $created['term_id'];
			update_term_meta( $term_id, Fields::PREFIX . 'kind', $field );
		}

		update_post_meta( $post->ID, Fields::PREFIX . $field . '_term', $term_id );
		update_post_meta( $post->ID, Fields::PREFIX . $field . '_suggestion', '' );
		Form::link_terms( $post->ID );
		Log::add( $actor, 'experience', $post->ID, 'add_suggestion', $field, $name );

		/* translators: %s: master-list entry name. */
		return sprintf( __( '“%s” is on the master list and linked.', 'internfeel' ), $name );
	}

	/**
	 * Once an experience is both published and verified (whichever comes second), count it towards
	 * the author's badges, exactly once. Invite credits hook in here.
	 *
	 * @param int $post_id Experience ID.
	 */
	public static function maybe_count_verified( int $post_id ): void {
		if ( 'publish' !== get_post_status( $post_id )
			|| 'verified' !== get_post_meta( $post_id, Fields::PREFIX . 'verification_status', true )
			|| metadata_exists( 'post', $post_id, Fields::PREFIX . 'verified_counted' ) ) {
			return;
		}

		update_post_meta( $post_id, Fields::PREFIX . 'verified_counted', true );
		$author = (int) get_post_field( 'post_author', $post_id );
		if ( $author <= 0 || (int) get_option( Roles::ARCHIVE_OPTION ) === $author ) {
			return;
		}

		update_user_meta( $author, Fields::PREFIX . 'verified_count', (int) get_user_meta( $author, Fields::PREFIX . 'verified_count', true ) + 1 );

		/**
		 * An experience is now published and verified.
		 *
		 * @param int $post_id Experience ID.
		 * @param int $author  Its author.
		 */
		do_action( 'if_experience_verified', $post_id, $author );
	}

	/**
	 * Whether the experience came from the old site.
	 *
	 * @param \WP_Post $post Experience.
	 * @return bool
	 */
	public static function is_imported( \WP_Post $post ): bool {
		return metadata_exists( 'post', $post->ID, Fields::PREFIX . 'legacy_wp_id' );
	}

	/**
	 * A master-list entry with this name and kind (and parent, for programmes), or 0.
	 *
	 * @param string $name      Name.
	 * @param string $kind      organisation, programme or field.
	 * @param int    $parent_id Parent term for programmes.
	 * @return int
	 */
	private static function find_term( string $name, string $kind, int $parent_id ): int {
		$terms = get_terms(
			array(
				'taxonomy'   => Content::TAXONOMY,
				'name'       => $name,
				'parent'     => $parent_id,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, admin-curated list.
					array(
						'key'   => Fields::PREFIX . 'kind',
						'value' => $kind,
					),
				),
			)
		);

		return is_array( $terms ) && array() !== $terms ? (int) $terms[0] : 0;
	}

	/**
	 * Where the student sees the experience: the live page, or the form while it is a draft.
	 *
	 * @param \WP_Post $post Experience.
	 * @return string
	 */
	private static function student_link( \WP_Post $post ): string {
		return 'publish' === $post->post_status ? (string) get_permalink( $post->ID ) : Submit::url( 'draft' === $post->post_status ? $post->ID : 0 );
	}

	/**
	 * The error for a decision on something no longer waiting for review.
	 *
	 * @return \WP_Error
	 */
	private static function not_pending(): \WP_Error {
		return self::error( 'if_not_pending', __( 'This experience is not waiting for review. Someone may have decided on it already.', 'internfeel' ), 409 );
	}

	/**
	 * A WP_Error with an HTTP status.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private static function error( string $code, string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
