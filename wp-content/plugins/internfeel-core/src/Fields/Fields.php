<?php
/**
 * Experience, user and master-list meta (architecture.md 4.1 to 4.3).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Fields;

use InternFeel\Content\Content;
use InternFeel\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every meta key with its type and sanitising. Keys are stored with the _if_ prefix.
 *
 * Only fields marked public are readable through the REST API. Private fields (proofs, reviewer notes,
 * referral details, counters used for moderation) never leave the server.
 *
 * Submission rules (required fields, edit-once) are enforced by the Submit module; this module makes
 * sure whatever is stored has the right shape.
 */
final class Fields implements Module {

	public const PREFIX = '_if_';

	/**
	 * Experience meta on the built-in post type.
	 *
	 * @var array<string, array{type: string, public: bool, enum?: list<string>, min?: int, max?: int, format?: string}>
	 */
	private const POST_META = array(
		// Where and what.
		'organisation_term'      => array(
			'type'   => 'integer',
			'public' => true,
		),
		'programme_term'         => array(
			'type'   => 'integer',
			'public' => true,
		),
		'field_term'             => array(
			'type'   => 'integer',
			'public' => true,
		),
		'type'                   => array(
			'type'   => 'string',
			'public' => true,
			'enum'   => array( 'research', 'industry', 'government', 'ngo', 'virtual' ),
		),
		'mode'                   => array(
			'type'   => 'string',
			'public' => true,
			'enum'   => array( 'on-site', 'remote', 'hybrid' ),
		),
		'city'                   => array(
			'type'   => 'string',
			'public' => true,
		),
		'country'                => array(
			'type'   => 'string',
			'public' => true,
		),
		'start_month'            => array(
			'type'   => 'string',
			'public' => true,
			'format' => 'month',
		),
		'duration_weeks'         => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
			'max'    => 104,
		),
		// Money.
		'paid'                   => array(
			'type'   => 'boolean',
			'public' => true,
		),
		'stipend_amount_inr'     => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
		),
		'selection_difficulty'   => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
			'max'    => 5,
		),
		// Story.
		'story'                  => array(
			'type'   => 'string',
			'public' => true,
			'format' => 'json',
		),
		'advice'                 => array(
			'type'   => 'string',
			'public' => true,
			'format' => 'textarea',
		),
		// Ratings (1 to 5; 0 means not rated yet).
		'rating_overall'         => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
			'max'    => 5,
		),
		'rating_learning'        => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
			'max'    => 5,
		),
		'rating_mentorship'      => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
			'max'    => 5,
		),
		'rating_culture'         => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
			'max'    => 5,
		),
		// Identity.
		'is_anonymous'           => array(
			'type'   => 'boolean',
			'public' => true,
		),
		// Referral (shown as a credit line by the theme, never as raw IDs).
		'referrer_user_id'       => array(
			'type'   => 'integer',
			'public' => false,
		),
		'invite_code'            => array(
			'type'   => 'string',
			'public' => false,
		),
		// Counters.
		'view_count'             => array(
			'type'   => 'integer',
			'public' => true,
			'min'    => 0,
		),
		'report_count'           => array(
			'type'   => 'integer',
			'public' => false,
			'min'    => 0,
		),
		'flagged'                => array(
			'type'   => 'boolean',
			'public' => false,
		),
		// Verification (private, except the status shown as the Verified badge).
		'proof_files'            => array(
			'type'   => 'string',
			'public' => false,
			'format' => 'json',
		),
		'verification_status'    => array(
			'type'   => 'string',
			'public' => true,
			'enum'   => array( 'not_submitted', 'pending', 'verified', 'rejected' ),
		),
		'verified_by'            => array(
			'type'   => 'integer',
			'public' => false,
		),
		'verified_at'            => array(
			'type'   => 'string',
			'public' => true,
			'format' => 'datetime',
		),
		'verification_note'      => array(
			'type'   => 'string',
			'public' => false,
			'format' => 'textarea',
		),
		// Certificate.
		'certificate_no'         => array(
			'type'   => 'string',
			'public' => true,
		),
		'certificate_issued_at'  => array(
			'type'   => 'string',
			'public' => true,
			'format' => 'datetime',
		),
		'certificate_revoked_at' => array(
			'type'   => 'string',
			'public' => true,
			'format' => 'datetime',
		),
		// Editing.
		'edit_count'             => array(
			'type'   => 'integer',
			'public' => false,
			'min'    => 0,
			'max'    => 1,
		),
		// Legacy (migrated posts).
		'legacy_wp_id'           => array(
			'type'   => 'integer',
			'public' => false,
		),
		'legacy_student_name'    => array(
			'type'   => 'string',
			'public' => true,
		),
		'legacy_published_year'  => array(
			'type'   => 'integer',
			'public' => true,
		),
	);

	/**
	 * Student profile and counters. None of it is exposed through core REST routes; the plugin's own
	 * me endpoints return what the student may see.
	 *
	 * @var array<string, array{type: string, public: bool, enum?: list<string>, min?: int, max?: int, format?: string}>
	 */
	private const USER_META = array(
		// Printed on certificates, even for anonymous experiences.
		'full_name'           => array(
			'type'   => 'string',
			'public' => false,
		),
		'college'             => array(
			'type'   => 'string',
			'public' => false,
		),
		'course'              => array(
			'type'   => 'string',
			'public' => false,
		),
		'graduation_year'     => array(
			'type'   => 'integer',
			'public' => false,
		),
		'linkedin_url'        => array(
			'type'   => 'string',
			'public' => false,
			'format' => 'url',
		),
		'avatar_id'           => array(
			'type'   => 'integer',
			'public' => false,
		),
		// "1" or "0"; missing means the student has not answered yet.
		'is_under_18'         => array(
			'type'   => 'string',
			'public' => false,
			'enum'   => array( '1', '0' ),
		),
		'referral_code'       => array(
			'type'   => 'string',
			'public' => false,
		),
		'referred_by_user_id' => array(
			'type'   => 'integer',
			'public' => false,
		),
		'referred_at'         => array(
			'type'   => 'string',
			'public' => false,
			'format' => 'datetime',
		),
		'invite_credits'      => array(
			'type'   => 'integer',
			'public' => false,
			'min'    => 0,
		),
		'invite_codes'        => array(
			'type'   => 'string',
			'public' => false,
			'format' => 'json',
		),
		'invites_joined'      => array(
			'type'   => 'integer',
			'public' => false,
			'min'    => 0,
		),
		'verified_count'      => array(
			'type'   => 'integer',
			'public' => false,
			'min'    => 0,
		),
		'notifications'       => array(
			'type'   => 'string',
			'public' => false,
			'format' => 'json',
		),
	);

	/**
	 * Master-list term meta.
	 *
	 * @var array<string, array{type: string, public: bool, enum?: list<string>, min?: int, max?: int, format?: string}>
	 */
	private const TERM_META = array(
		'kind'      => array(
			'type'   => 'string',
			'public' => true,
			'enum'   => array( 'organisation', 'programme', 'field' ),
		),
		'org_type'  => array(
			'type'   => 'string',
			'public' => true,
			'enum'   => array( 'company', 'institute', 'government', 'ngo' ),
		),
		'city'      => array(
			'type'   => 'string',
			'public' => true,
		),
		'country'   => array(
			'type'   => 'string',
			'public' => true,
		),
		'recurring' => array(
			'type'   => 'boolean',
			'public' => true,
		),
		'active'    => array(
			'type'   => 'boolean',
			'public' => true,
		),
	);

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ), 6 );
	}

	/**
	 * Register every meta key. Runs after Content registers the taxonomy and post types.
	 */
	public static function register_meta(): void {
		foreach ( self::POST_META as $key => $definition ) {
			register_post_meta( 'post', self::PREFIX . $key, self::args( $definition ) );
		}

		foreach ( self::USER_META as $key => $definition ) {
			register_meta( 'user', self::PREFIX . $key, self::args( $definition ) );
		}

		foreach ( self::TERM_META as $key => $definition ) {
			$args = self::args( $definition );
			if ( 'active' === $key ) {
				$args['default'] = true;
			}
			register_term_meta( Content::TAXONOMY, self::PREFIX . $key, $args );
		}

		register_post_meta(
			Content::LIST_TYPE,
			self::PREFIX . 'list_items',
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => static fn( mixed $value ): array => is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : array(),
				'auth_callback'     => static fn(): bool => current_user_can( 'manage_options' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_post_meta(
			Content::LIST_TYPE,
			self::PREFIX . 'home_order',
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static fn(): bool => current_user_can( 'manage_options' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Whether an experience is published anonymously.
	 *
	 * @param int $post_id Experience ID.
	 * @return bool
	 */
	public static function is_anonymous( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::PREFIX . 'is_anonymous', true );
	}

	/**
	 * Names of the experience meta keys that the REST API must never return.
	 *
	 * @return list<string>
	 */
	public static function private_post_keys(): array {
		$keys = array();
		foreach ( self::POST_META as $key => $definition ) {
			if ( ! $definition['public'] ) {
				$keys[] = self::PREFIX . $key;
			}
		}

		return $keys;
	}

	/**
	 * Build register_meta() arguments from a field definition.
	 *
	 * @param array{type: string, public: bool, enum?: list<string>, min?: int, max?: int, format?: string} $definition Field definition.
	 * @return array<string, mixed>
	 */
	private static function args( array $definition ): array {
		$defaults = array(
			'integer' => 0,
			'boolean' => false,
			'string'  => '',
		);

		return array(
			'type'              => $definition['type'],
			'single'            => true,
			'default'           => $defaults[ $definition['type'] ] ?? '',
			'sanitize_callback' => static fn( mixed $value ): int|bool|string => self::sanitize( $value, $definition ),
			// Only admins may write InternFeel meta through core REST routes; students use the plugin's routes.
			'auth_callback'     => static fn(): bool => current_user_can( 'edit_others_posts' ),
			'show_in_rest'      => $definition['public'] ? array( 'schema' => array( 'type' => $definition['type'] ) ) : false,
		);
	}

	/**
	 * Coerce a value to its field's type, range, list of allowed values and format.
	 *
	 * @param mixed                                                                                         $value      Raw value.
	 * @param array{type: string, public: bool, enum?: list<string>, min?: int, max?: int, format?: string} $definition Field definition.
	 * @return int|bool|string
	 */
	public static function sanitize( mixed $value, array $definition ): int|bool|string {
		if ( 'integer' === $definition['type'] ) {
			$number = is_numeric( $value ) ? (int) $value : 0;
			if ( isset( $definition['min'] ) ) {
				$number = max( $definition['min'], $number );
			}
			if ( isset( $definition['max'] ) ) {
				$number = min( $definition['max'], $number );
			}

			return $number;
		}

		if ( 'boolean' === $definition['type'] ) {
			if ( is_bool( $value ) ) {
				return $value;
			}

			return rest_sanitize_boolean( is_scalar( $value ) ? (string) $value : '' );
		}

		$text = is_scalar( $value ) ? (string) $value : '';

		if ( isset( $definition['enum'] ) ) {
			return in_array( $text, $definition['enum'], true ) ? $text : '';
		}

		switch ( $definition['format'] ?? '' ) {
			case 'json':
				return self::sanitize_json( $text );
			case 'textarea':
				return sanitize_textarea_field( $text );
			case 'url':
				return esc_url_raw( $text, array( 'https' ) );
			case 'month':
				return 1 === preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $text ) ? $text : '';
			case 'datetime':
				return 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $text ) ? $text : '';
			default:
				return sanitize_text_field( $text );
		}
	}

	/**
	 * Keep JSON only if it decodes to an array or object, with every string value sanitised.
	 *
	 * @param string $json Raw JSON.
	 * @return string Clean JSON, or an empty string.
	 */
	private static function sanitize_json( string $json ): string {
		if ( '' === $json ) {
			return '';
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return '';
		}

		array_walk_recursive(
			$data,
			static function ( mixed &$item ): void {
				if ( is_string( $item ) ) {
					$item = sanitize_textarea_field( $item );
				}
			}
		);

		return (string) wp_json_encode( $data );
	}
}
