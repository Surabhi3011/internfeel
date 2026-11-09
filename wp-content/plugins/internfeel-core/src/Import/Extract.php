<?php
/**
 * Facts the importer can read from an old post's title and text (development.md section 9, step 4).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Conservative extraction: when a fact is not clearly there, it is left empty for the review, never guessed.
 */
final class Extract {

	/**
	 * Words that mean a title segment is not a person's name.
	 */
	private const NOT_A_NAME = '/\b(internship|intern|experience|narrative|programme|program|research|summer|winter|fellowship|scholarship|institute|university|college|iit|iisc|nit|bits|labs?|india|virtual|interview|company|centre|center|anonymous|with|at|in)\b/i';

	/**
	 * The student's name from titles such as "Internship Experience | Anurag Roy | IIT Bombay",
	 * "Internship Experience of Abhinav Gangwar: GSoC with KDE" or "Summer Internship at DRDO by
	 * Krishna Sharma".
	 *
	 * @param string $title Decoded post title.
	 * @return string The name, or an empty string when the title does not clearly contain one.
	 */
	public static function student_name( string $title ): string {
		$title = trim( preg_replace( '/\s+/u', ' ', $title ) ?? '' );

		$patterns = array(
			'/\bby\s+(\p{Lu}[\p{L}.\'-]+(?:\s+\p{Lu}[\p{L}.\'-]+){0,2})\s*$/u',
			'/\bexperience\s+of\s+(\p{Lu}[\p{L}.\'-]+(?:\s+\p{Lu}[\p{L}.\'-]+){0,2})\s*[:|,–-]/iu',
			'/^(\p{Lu}[\p{L}.\'-]+(?:\s+\p{Lu}[\p{L}.\'-]+){0,2})\s+shares\b/u',
			'/[–-]\s*(\p{Lu}[\p{L}.\'-]+(?:\s+\p{Lu}[\p{L}.\'-]+){0,2})\s*$/u',
		);
		foreach ( $patterns as $pattern ) {
			if ( 1 === preg_match( $pattern, $title, $match ) && self::looks_like_name( $match[1] ) ) {
				return $match[1];
			}
		}

		// Pipe-separated titles: the first segment that looks like a person's name.
		if ( str_contains( $title, '|' ) ) {
			foreach ( array_map( 'trim', explode( '|', $title ) ) as $segment ) {
				if ( 1 === preg_match( '/^\p{Lu}[\p{L}.\'-]+(?:\s+\p{Lu}[\p{L}.\'-]+){1,2}$/u', $segment ) && self::looks_like_name( $segment ) ) {
					return $segment;
				}
			}
		}

		return '';
	}

	/**
	 * The overall rating on the new 1 to 5 scale, from lines such as "I would rate this internship
	 * 9 out of 10" or "Rating: 9.5/10".
	 *
	 * @param string $text Plain text of the post.
	 * @return int 1 to 5, or 0 when the post gives no rating.
	 */
	public static function rating( string $text ): int {
		$found = preg_match( '/\brat(?:e|ing|ed)\b[^.\n]{0,60}?\b(\d{1,2}(?:\.\d)?)\s*(?:out\s+of|\/)\s*10\b/i', $text, $match );
		if ( 1 !== $found ) {
			return 0;
		}

		$score = (float) $match[1];
		if ( $score < 0 || $score > 10 ) {
			return 0;
		}

		return (int) max( 1, min( 5, round( $score / 2 ) ) );
	}

	/**
	 * Whether a short string reads as a person's name rather than a programme or organisation.
	 *
	 * @param string $candidate Possible name.
	 * @return bool
	 */
	private static function looks_like_name( string $candidate ): bool {
		return mb_strlen( $candidate ) <= 40 && 1 !== preg_match( self::NOT_A_NAME, $candidate );
	}
}
