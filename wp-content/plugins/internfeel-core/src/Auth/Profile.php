<?php
/**
 * Student profile: the fields asked at first sign-in (plan.md 5.1).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Auth;

use InternFeel\Content\Pages;
use InternFeel\Fields\Fields;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and saves a student's profile.
 */
final class Profile {

	public const MIN_GRADUATION_YEAR = 1980;
	public const MAX_TEXT_LENGTH     = 120;

	/**
	 * Where students complete their profile: the second step of the sign-up page.
	 *
	 * @return string
	 */
	public static function url(): string {
		return add_query_arg( 'step', 'profile', Pages::url( 'signup' ) );
	}

	/**
	 * Where students go once their profile is complete. The dashboard arrives on day 6; until then, home.
	 *
	 * @return string
	 */
	public static function home_url(): string {
		return Pages::url( 'dashboard' );
	}

	/**
	 * Whether the student has answered every required profile question.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_complete( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return '' !== self::meta( $user_id, 'full_name' )
			&& '' !== self::meta( $user_id, 'college' )
			&& '' !== self::meta( $user_id, 'course' )
			&& (int) self::meta( $user_id, 'graduation_year' ) >= self::MIN_GRADUATION_YEAR
			&& in_array( self::meta( $user_id, 'is_under_18' ), array( '1', '0' ), true );
	}

	/**
	 * The profile as the student may see it.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public static function get( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return array();
		}

		$under_18  = self::meta( $user_id, 'is_under_18' );
		$full_name = self::meta( $user_id, 'full_name' );

		return array(
			'id'                 => $user->ID,
			'email'              => $user->user_email,
			'full_name'          => $full_name,
			// Empty until the student chooses one: before the profile is saved the account holds only a
			// placeholder name, and afterwards a blank choice means the full name is shown instead.
			'display_name'       => '' === $full_name || $full_name === $user->display_name ? '' : $user->display_name,
			'college'            => self::meta( $user_id, 'college' ),
			'course'             => self::meta( $user_id, 'course' ),
			'graduation_year'    => (int) self::meta( $user_id, 'graduation_year' ),
			'is_under_18'        => '' === $under_18 ? null : '1' === $under_18,
			'linkedin_url'       => self::meta( $user_id, 'linkedin_url' ),
			'hide_invite_credit' => '1' === self::meta( $user_id, 'hide_invite_credit' ),
			'profile_complete'   => self::is_complete( $user_id ),
		);
	}

	/**
	 * Validate and save the profile form.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $input   Submitted fields.
	 * @return true|\WP_Error
	 */
	public static function save( int $user_id, array $input ): true|\WP_Error {
		$full    = self::text( $input, 'full_name' );
		$display = self::text( $input, 'display_name', 60 );
		$college = self::text( $input, 'college' );
		$course  = self::text( $input, 'course' );
		$year    = is_numeric( $input['graduation_year'] ?? null ) ? (int) $input['graduation_year'] : 0;
		$max     = (int) gmdate( 'Y' ) + 8;

		if ( '' === $full ) {
			return Rest::error( 'if_required', __( 'Enter your full name. It is printed on your certificates.', 'internfeel' ), 400, 'full_name' );
		}
		if ( '' === $college ) {
			return Rest::error( 'if_required', __( 'Enter your college or university.', 'internfeel' ), 400, 'college' );
		}
		if ( '' === $course ) {
			return Rest::error( 'if_required', __( 'Enter your course, for example BTech Computer Science.', 'internfeel' ), 400, 'course' );
		}
		if ( $year < self::MIN_GRADUATION_YEAR || $year > $max ) {
			return Rest::error(
				'if_invalid',
				/* translators: 1: earliest year, 2: latest year. */
				sprintf( __( 'Enter a graduation year between %1$d and %2$d.', 'internfeel' ), self::MIN_GRADUATION_YEAR, $max ),
				400,
				'graduation_year'
			);
		}

		$under_18 = $input['is_under_18'] ?? null;
		if ( ! is_scalar( $under_18 ) || '' === (string) $under_18 ) {
			return Rest::error( 'if_required', __( 'Tell us whether you are under 18.', 'internfeel' ), 400, 'is_under_18' );
		}
		$under_18 = rest_sanitize_boolean( (string) $under_18 ) ? '1' : '0';

		$linkedin = self::text( $input, 'linkedin_url', 200 );
		if ( '' !== $linkedin ) {
			$linkedin = esc_url_raw( $linkedin, array( 'https' ) );
			if ( 1 !== preg_match( '#^https://([a-z]{2,3}\.)?linkedin\.com/#i', $linkedin ) ) {
				return Rest::error( 'if_invalid', __( 'Enter your LinkedIn profile link, starting with https://www.linkedin.com/', 'internfeel' ), 400, 'linkedin_url' );
			}
		}

		// A blank display name shows the full name (sign-off decision, 26 October 2026).
		if ( '' === $display ) {
			$display = $full;
		}

		// WordPress's own first and last name fields, for wp-admin only; the full name is the record.
		$parts  = explode( ' ', $full, 2 );
		$result = wp_update_user(
			array(
				'ID'           => $user_id,
				'first_name'   => $parts[0],
				'last_name'    => $parts[1] ?? '',
				'display_name' => $display,
				'nickname'     => $display,
			)
		);
		if ( is_wp_error( $result ) ) {
			return Rest::error( 'if_profile_failed', __( 'Your profile could not be saved. Please try again.', 'internfeel' ), 500 );
		}

		update_user_meta( $user_id, Fields::PREFIX . 'full_name', $full );
		update_user_meta( $user_id, Fields::PREFIX . 'college', $college );
		update_user_meta( $user_id, Fields::PREFIX . 'course', $course );
		update_user_meta( $user_id, Fields::PREFIX . 'graduation_year', $year );
		update_user_meta( $user_id, Fields::PREFIX . 'is_under_18', $under_18 );
		update_user_meta( $user_id, Fields::PREFIX . 'linkedin_url', $linkedin );

		// Only the dashboard's Profile tab sends this; first sign-in leaves it unset (name shown).
		if ( array_key_exists( 'hide_invite_credit', $input ) && is_scalar( $input['hide_invite_credit'] ) ) {
			update_user_meta( $user_id, Fields::PREFIX . 'hide_invite_credit', rest_sanitize_boolean( (string) $input['hide_invite_credit'] ) ? '1' : '0' );
		}

		return true;
	}

	/**
	 * A user meta value as a string.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Key without the _if_ prefix.
	 * @return string
	 */
	private static function meta( int $user_id, string $key ): string {
		$value = get_user_meta( $user_id, Fields::PREFIX . $key, true );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A submitted text field, sanitised and cut to length. Backslashes are dropped: they never belong
	 * in a name, college or course, and WordPress's meta functions would unslash them anyway.
	 *
	 * @param array<string, mixed> $input  Submitted fields.
	 * @param string               $key    Field name.
	 * @param int                  $length Maximum length in characters.
	 * @return string
	 */
	private static function text( array $input, string $key, int $length = self::MAX_TEXT_LENGTH ): string {
		$value = $input[ $key ] ?? '';

		return is_scalar( $value ) ? mb_substr( sanitize_text_field( str_replace( '\\', '', (string) $value ) ), 0, $length ) : '';
	}
}
