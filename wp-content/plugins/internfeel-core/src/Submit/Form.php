<?php
/**
 * Reads, saves and validates the four-step submission form (plan.md 5.1, "Submission form").
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Submit;

use InternFeel\Content\Content;
use InternFeel\Fields\Fields;
use InternFeel\Support\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Drafts save whatever is filled in (autosave and "Save and exit"); submitting checks every required
 * field, then builds the title, the story HTML and the excerpt.
 */
final class Form {

	public const TYPES = array( 'research', 'industry', 'government', 'ngo', 'virtual' );
	public const MODES = array( 'on-site', 'remote', 'hybrid' );

	public const RATINGS = array( 'rating_overall', 'rating_learning', 'rating_mentorship', 'rating_culture' );

	public const MAX_ANSWER = 5000;
	public const MAX_ADVICE = 2000;

	/**
	 * The experience's form values, as the form shows them.
	 *
	 * @param int $post_id Experience ID.
	 * @return array<string, mixed>
	 */
	public static function values( int $post_id ): array {
		$meta = static fn( string $key ): string => (string) get_post_meta( $post_id, Fields::PREFIX . $key, true );

		$values = array(
			'organisation'         => self::term_or_suggestion( (int) $meta( 'organisation_term' ), $meta( 'organisation_suggestion' ) ),
			'programme'            => self::term_or_suggestion( (int) $meta( 'programme_term' ), $meta( 'programme_suggestion' ) ),
			'field'                => self::term_or_suggestion( (int) $meta( 'field_term' ), $meta( 'field_suggestion' ) ),
			'type'                 => $meta( 'type' ),
			'mode'                 => $meta( 'mode' ),
			'city'                 => $meta( 'city' ),
			'country'              => $meta( 'country' ),
			'start_month'          => $meta( 'start_month' ),
			'duration_weeks'       => (int) $meta( 'duration_weeks' ),
			'paid'                 => '' === $meta( 'paid' ) ? null : (bool) $meta( 'paid' ),
			'stipend_amount_inr'   => (int) $meta( 'stipend_amount_inr' ),
			'selection_difficulty' => (int) $meta( 'selection_difficulty' ),
			'advice'               => $meta( 'advice' ),
			'is_anonymous'         => (bool) $meta( 'is_anonymous' ),
			'story'                => self::story( $post_id ),
		);
		foreach ( self::RATINGS as $rating ) {
			$values[ $rating ] = (int) $meta( $rating );
		}

		return $values;
	}

	/**
	 * Save the fields present in the input. Unknown fields are ignored; nothing here is required.
	 *
	 * @param int                  $post_id Draft experience ID.
	 * @param array<string, mixed> $input   Submitted fields.
	 */
	public static function save( int $post_id, array $input ): void {
		$text = static fn( string $key, int $length = 120 ): string => mb_substr( sanitize_text_field( self::scalar( $input[ $key ] ?? '' ) ), 0, $length );

		foreach ( array( 'organisation', 'programme', 'field' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				self::save_master( $post_id, $key, $text( $key ) );
			}
		}

		$simple = array(
			'type'                 => static fn(): string => in_array( self::scalar( $input['type'] ?? '' ), self::TYPES, true ) ? self::scalar( $input['type'] ) : '',
			'mode'                 => static fn(): string => in_array( self::scalar( $input['mode'] ?? '' ), self::MODES, true ) ? self::scalar( $input['mode'] ) : '',
			'city'                 => static fn(): string => $text( 'city', 80 ),
			'country'              => static fn(): string => $text( 'country', 80 ),
			'start_month'          => static fn(): string => self::scalar( $input['start_month'] ?? '' ),
			'duration_weeks'       => static fn(): int => (int) self::scalar( $input['duration_weeks'] ?? '0' ),
			'stipend_amount_inr'   => static fn(): int => max( 0, (int) self::scalar( $input['stipend_amount_inr'] ?? '0' ) ),
			'selection_difficulty' => static fn(): int => (int) self::scalar( $input['selection_difficulty'] ?? '0' ),
			'advice'               => static fn(): string => mb_substr( sanitize_textarea_field( self::scalar( $input['advice'] ?? '' ) ), 0, self::MAX_ADVICE ),
			'paid'                 => static fn(): string => self::flag( $input['paid'] ?? '' ),
			'is_anonymous'         => static fn(): string => self::flag( $input['is_anonymous'] ?? '' ),
		);
		foreach ( self::RATINGS as $rating ) {
			$simple[ $rating ] = static fn(): int => (int) self::scalar( $input[ $rating ] ?? '0' );
		}

		foreach ( $simple as $key => $value ) {
			if ( array_key_exists( $key, $input ) ) {
				update_post_meta( $post_id, Fields::PREFIX . $key, wp_slash( $value() ) );
			}
		}

		if ( isset( $input['story'] ) && is_array( $input['story'] ) ) {
			$story = self::story( $post_id );
			foreach ( array_keys( Questions::all() ) as $key ) {
				if ( array_key_exists( $key, $input['story'] ) ) {
					$story[ $key ] = mb_substr( sanitize_textarea_field( self::scalar( $input['story'][ $key ] ) ), 0, self::MAX_ANSWER );
				}
			}
			update_post_meta( $post_id, Fields::PREFIX . 'story', wp_slash( (string) wp_json_encode( $story ) ) );
		}
	}

	/**
	 * Check every required field before submitting. Returns the first problem, with the field and step
	 * it is on, so the form can show it.
	 *
	 * @param int $post_id Draft experience ID.
	 * @return true|\WP_Error
	 */
	public static function validate( int $post_id ): true|\WP_Error {
		$v     = self::values( $post_id );
		$month = (string) $v['start_month'];

		$checks = array(
			array( 1, 'organisation', '' !== $v['organisation'], __( 'Enter the organisation.', 'internfeel' ) ),
			array( 1, 'type', '' !== $v['type'], __( 'Choose the type of internship.', 'internfeel' ) ),
			array( 1, 'field', '' !== $v['field'], __( 'Enter your field.', 'internfeel' ) ),
			array( 1, 'mode', '' !== $v['mode'], __( 'Choose on-site, remote or hybrid.', 'internfeel' ) ),
			array( 1, 'city', '' !== $v['city'], __( 'Enter the city.', 'internfeel' ) ),
			array( 1, 'country', '' !== $v['country'], __( 'Enter the country.', 'internfeel' ) ),
			array( 1, 'start_month', '' !== $month && $month <= gmdate( 'Y-m' ), __( 'Enter the month you started; it cannot be in the future.', 'internfeel' ) ),
			array( 1, 'duration_weeks', $v['duration_weeks'] >= 1 && $v['duration_weeks'] <= 104, __( 'Enter a duration between 1 and 104 weeks.', 'internfeel' ) ),
			array( 1, 'paid', null !== $v['paid'], __( 'Tell us whether it was paid.', 'internfeel' ) ),
			array( 1, 'selection_difficulty', $v['selection_difficulty'] >= 1 && $v['selection_difficulty'] <= 5, __( 'Rate how hard it was to get in.', 'internfeel' ) ),
		);

		foreach ( Questions::all() as $key => $question ) {
			if ( Questions::is_required( $key ) ) {
				/* translators: %s: the question. */
				$checks[] = array( 2, "story[{$key}]", '' !== trim( (string) ( $v['story'][ $key ] ?? '' ) ), sprintf( __( 'Answer: %s', 'internfeel' ), $question ) );
			}
		}

		$labels = array(
			'rating_overall'    => __( 'Rate the internship overall.', 'internfeel' ),
			'rating_learning'   => __( 'Rate what you learnt.', 'internfeel' ),
			'rating_mentorship' => __( 'Rate the mentorship.', 'internfeel' ),
			'rating_culture'    => __( 'Rate the work culture.', 'internfeel' ),
		);
		foreach ( $labels as $rating => $message ) {
			$checks[] = array( 3, $rating, $v[ $rating ] >= 1 && $v[ $rating ] <= 5, $message );
		}

		foreach ( $checks as list( $step, $field, $ok, $message ) ) {
			if ( ! $ok ) {
				$error = Rest::error( 'if_incomplete', $message, 400, $field );
				$error->add_data(
					array(
						'status' => 400,
						'field'  => $field,
						'step'   => $step,
					)
				);
				return $error;
			}
		}

		return true;
	}

	/**
	 * Build what the published page shows from the answers: title, story HTML and excerpt.
	 *
	 * @param int $post_id Experience ID.
	 * @return array{post_title: string, post_content: string, post_excerpt: string}
	 */
	public static function render( int $post_id ): array {
		$v     = self::values( $post_id );
		$types = array(
			'research'   => __( 'Research internship', 'internfeel' ),
			'industry'   => __( 'Industry internship', 'internfeel' ),
			'government' => __( 'Government internship', 'internfeel' ),
			'ngo'        => __( 'NGO internship', 'internfeel' ),
			'virtual'    => __( 'Virtual internship', 'internfeel' ),
		);
		// The programme when there is one, otherwise the type of internship, then the organisation.
		$what = '' !== $v['programme'] ? $v['programme'] : ( $types[ $v['type'] ] ?? __( 'Internship', 'internfeel' ) );
		/* translators: 1: programme, or type of internship, 2: organisation. */
		$title = sprintf( __( '%1$s at %2$s', 'internfeel' ), $what, $v['organisation'] );

		$html = '';
		foreach ( Questions::all() as $key => $question ) {
			$answer = trim( (string) ( $v['story'][ $key ] ?? '' ) );
			if ( '' === $answer ) {
				continue;
			}
			$html .= '<h2>' . esc_html( $question ) . "</h2>\n" . wpautop( esc_html( $answer ) ) . "\n";
		}

		$first = (string) ( $v['story']['q4'] ?? $v['story']['q1'] ?? '' );

		return array(
			'post_title'   => $title,
			'post_content' => $html,
			'post_excerpt' => wp_trim_words( $first, 30, '…' ),
		);
	}

	/**
	 * Master-list entries for the form's suggestions: organisations, or fields.
	 *
	 * @param string $kind organisation or field.
	 * @return list<string> Names, sorted.
	 */
	public static function suggestions( string $kind ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => Content::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'names',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, admin-curated list.
					array(
						'key'   => Fields::PREFIX . 'kind',
						'value' => $kind,
					),
				),
			)
		);

		return is_array( $terms ) ? array_map( 'strval', $terms ) : array();
	}

	/**
	 * The experience's answers keyed q1 to q7.
	 *
	 * @param int $post_id Experience ID.
	 * @return array<string, string>
	 */
	private static function story( int $post_id ): array {
		$decoded = json_decode( (string) get_post_meta( $post_id, Fields::PREFIX . 'story', true ), true );
		$story   = array();
		foreach ( array_keys( Questions::all() ) as $key ) {
			$story[ $key ] = is_array( $decoded ) && is_scalar( $decoded[ $key ] ?? null ) ? (string) $decoded[ $key ] : '';
		}

		return $story;
	}

	/**
	 * Link an organisation, programme or field to its master-list entry, or keep the typed name as a
	 * suggestion for an admin (students never create master-list entries).
	 *
	 * @param int    $post_id Experience ID.
	 * @param string $key     organisation, programme or field.
	 * @param string $name    What the student typed.
	 */
	private static function save_master( int $post_id, string $key, string $name ): void {
		$term = '' === $name ? false : get_term_by( 'name', $name, Content::TAXONOMY );
		if ( 'programme' === $key && $term instanceof \WP_Term ) {
			$organisation = (int) get_post_meta( $post_id, Fields::PREFIX . 'organisation_term', true );
			if ( $term->parent !== $organisation ) {
				$term = false;
			}
		}

		$term_id = $term instanceof \WP_Term ? $term->term_id : 0;
		update_post_meta( $post_id, Fields::PREFIX . $key . '_term', $term_id );
		update_post_meta( $post_id, Fields::PREFIX . $key . '_suggestion', wp_slash( $term_id > 0 ? '' : $name ) );

		$terms = array_filter(
			array(
				(int) get_post_meta( $post_id, Fields::PREFIX . 'organisation_term', true ),
				(int) get_post_meta( $post_id, Fields::PREFIX . 'programme_term', true ),
				(int) get_post_meta( $post_id, Fields::PREFIX . 'field_term', true ),
			)
		);
		wp_set_object_terms( $post_id, array_values( $terms ), Content::TAXONOMY );
	}

	/**
	 * A term's name, or the suggestion when there is no term.
	 *
	 * @param int    $term_id    Master-list term ID.
	 * @param string $suggestion Name the student typed.
	 * @return string
	 */
	private static function term_or_suggestion( int $term_id, string $suggestion ): string {
		$term = $term_id > 0 ? get_term( $term_id, Content::TAXONOMY ) : null;

		return $term instanceof \WP_Term ? $term->name : $suggestion;
	}

	/**
	 * A yes/no answer stored as "1" or "0"; empty when unanswered.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	private static function flag( mixed $value ): string {
		$value = self::scalar( $value );
		if ( '' === $value ) {
			return '';
		}

		return rest_sanitize_boolean( $value ) ? '1' : '0';
	}

	/**
	 * A submitted value as a string, or empty when it is not a single value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	private static function scalar( mixed $value ): string {
		return is_scalar( $value ) ? str_replace( '\\', '', (string) $value ) : '';
	}
}
