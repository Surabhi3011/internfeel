<?php
/**
 * The standard story questions (docs/approved-designs/README.md, "Submission form", step 2).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Submit;

defined( 'ABSPATH' ) || exit;

/**
 * Seven questions in a fixed order. Which are required is still Surabhi's call (plan.md section 10);
 * until she confirms, the first four are.
 */
final class Questions {

	/**
	 * How many of the questions, from the first, are required.
	 */
	public const REQUIRED = 4;

	/**
	 * Question key => question text.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
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

	/**
	 * Whether a question must be answered.
	 *
	 * @param string $key Question key, such as "q3".
	 * @return bool
	 */
	public static function is_required( string $key ): bool {
		return (int) substr( $key, 1 ) <= self::REQUIRED;
	}
}
