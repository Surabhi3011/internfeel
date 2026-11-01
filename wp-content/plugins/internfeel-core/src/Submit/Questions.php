<?php
/**
 * The standard story questions (docs/approved-designs/README.md, "Submission form", step 2).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Submit;

use InternFeel\Fields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Questions come in versioned sets. Every experience records the set it was written against
 * (_if_story_version), so answers always appear under the questions the student actually answered:
 * version 1 is the launch set of seven, version 2 the set of six from 1 November 2026, whose advice
 * question is the Advice field in step 3. The first four questions of each set are required
 * (plan.md section 10).
 */
final class Questions {

	/**
	 * The set new experiences use.
	 */
	public const CURRENT = 2;

	/**
	 * How many of the questions, from the first, are required.
	 */
	public const REQUIRED = 4;

	/**
	 * Question key => question text.
	 *
	 * @param int $version Question set.
	 * @return array<string, string>
	 */
	public static function all( int $version = self::CURRENT ): array {
		if ( 1 === $version ) {
			return array(
				'q1' => __( 'Tell us about yourself. Where and what are you studying?', 'internfeel' ),
				'q2' => __( 'How did you apply, and what were the stipend, duration and place?', 'internfeel' ),
				'q3' => __( 'How competitive was the selection, and what helped you get in?', 'internfeel' ),
				'q4' => __( 'What work did you do during the internship?', 'internfeel' ),
				'q5' => __( 'What was good about the experience, and what was hard?', 'internfeel' ),
				'q6' => __( 'What are your key takeaways?', 'internfeel' ),
				'q7' => __( 'Has it changed what you want to do next?', 'internfeel' ),
			);
		}

		return array(
			'q1' => __( 'Tell us about yourself.', 'internfeel' ),
			'q2' => __( 'How did you apply, and how were you selected?', 'internfeel' ),
			'q3' => __( 'What work did you do during the internship?', 'internfeel' ),
			'q4' => __( 'What delighted you most about the internship?', 'internfeel' ),
			'q5' => __( 'What was not so great about it?', 'internfeel' ),
			'q6' => __( 'How did this experience change you?', 'internfeel' ),
		);
	}

	/**
	 * A line under each question that says what a good answer covers (set 2 only).
	 *
	 * @param int $version Question set.
	 * @return array<string, string>
	 */
	public static function hints( int $version = self::CURRENT ): array {
		if ( 1 === $version ) {
			return array();
		}

		return array(
			'q1' => __( 'Your studies, your hobbies, your quirks and your dreams.', 'internfeel' ),
			'q2' => __( 'The application and selection process: forms, tests, interviews, and what helped you get in.', 'internfeel' ),
			'q3' => __( 'Give readers a good picture of the kind of work an internship like this involves.', 'internfeel' ),
			'q4' => __( 'It can be anything: the work, the food, the people, or something else.', 'internfeel' ),
			'q5' => __( 'We understand that not every experience is 100% great.', 'internfeel' ),
			'q6' => __( 'Any transformation or change it brought about in you.', 'internfeel' ),
		);
	}

	/**
	 * The advice question (the Advice field in step 3) and its hint.
	 *
	 * @param int $version Question set.
	 * @return array{0: string, 1: string} Label and hint.
	 */
	public static function advice( int $version = self::CURRENT ): array {
		if ( 1 === $version ) {
			return array( __( 'Advice for students applying', 'internfeel' ), __( 'Optional. Shown in its own box after your story.', 'internfeel' ) );
		}

		return array(
			__( 'Any advice or words of caution for students who want this internship?', 'internfeel' ),
			__( 'Optional. Help them get this internship, or avoid it if your experience was bad. Shown in its own box after your story.', 'internfeel' ),
		);
	}

	/**
	 * Whether a question must be answered.
	 *
	 * @param string $key Question key, such as "q3".
	 * @return bool
	 */
	public static function is_required( string $key ): bool {
		return (int) substr( $key, 1 ) <= self::REQUIRED;
	}

	/**
	 * The question set an experience was written against. Experiences from before sets were recorded
	 * used set 1.
	 *
	 * @param int $post_id Experience ID, or 0 for a new one.
	 * @return int
	 */
	public static function version( int $post_id ): int {
		if ( $post_id <= 0 ) {
			return self::CURRENT;
		}

		$version = (int) get_post_meta( $post_id, Fields::PREFIX . 'story_version', true );

		return in_array( $version, array( 1, 2 ), true ) ? $version : 1;
	}
}
