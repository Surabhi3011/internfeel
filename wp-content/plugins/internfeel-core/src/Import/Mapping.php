<?php
/**
 * How the old site's categories map onto the new data model (development.md section 9, step 3).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Import;

defined( 'ABSPATH' ) || exit;

/**
 * The old site used one flat category list for five different things: what kind of post it was,
 * where the internship was, what kind of organisation ran it, what type of internship it was, and the
 * field. This class splits them into the new fields. Anything it cannot place is left empty for review.
 */
final class Mapping {

	/**
	 * Category slug => what it says. Keys: kind (experience|article), type, mode, field, country,
	 * organisation, anonymous. Slugs that carry no information (interviews, multiple, ...) map to
	 * kind only, or to nothing.
	 *
	 * @var array<string, array<string, string|bool>>
	 */
	private const CATEGORIES = array(
		// What kind of post it is.
		'interviews'           => array( 'kind' => 'experience' ),
		'national-internships' => array(
			'kind'    => 'experience',
			'country' => 'India',
		),
		'international-internships' => array( 'kind' => 'experience' ),
		'research-interviews'       => array(
			'kind' => 'experience',
			'type' => 'research',
		),
		'anonymous-interviews' => array(
			'kind'      => 'experience',
			'anonymous' => true,
		),
		'short-interviews'      => array( 'kind' => 'experience' ),
		'internship-narrative'  => array( 'kind' => 'experience' ),
		'internship-guide'      => array( 'kind' => 'article' ),
		'blog'                  => array( 'kind' => 'article' ),

		// Internship type.
		'research-internships'  => array( 'type' => 'research' ),
		'summer-fellowship'     => array( 'type' => 'research' ),
		'institutes'            => array( 'type' => 'research' ),
		'companies'             => array( 'type' => 'industry' ),
		'industrial-internship' => array( 'type' => 'industry' ),
		'marketing-internship'  => array(
			'type'  => 'industry',
			'field' => 'Marketing',
		),
		'operations-internship' => array(
			'type'  => 'industry',
			'field' => 'Management',
		),
		'legal-internship' => array( 'field' => 'Law' ),
		'government'       => array( 'type' => 'government' ),
		'social-sector'    => array( 'type' => 'ngo' ),
		'virtual'          => array(
			'type' => 'virtual',
			'mode' => 'remote',
		),
		'virtual-internships' => array(
			'type' => 'virtual',
			'mode' => 'remote',
		),
		'virtual-internships-institutes' => array(
			'type' => 'virtual',
			'mode' => 'remote',
		),

		// Field of study.
		'engineering'                  => array( 'field' => 'Engineering' ),
		'b-tech'                       => array( 'field' => 'Engineering' ),
		'computer-science'             => array( 'field' => 'Computer Science' ),
		'electrical-engineering'       => array( 'field' => 'Electrical Engineering' ),
		'civil-engineering'            => array( 'field' => 'Civil Engineering' ),
		'marine-and-ocean-engineering' => array( 'field' => 'Marine and Ocean Engineering' ),
		'law'                          => array( 'field' => 'Law' ),
		'ara'                          => array( 'field' => 'Law' ),
		'management'                   => array( 'field' => 'Management' ),
		'economics'                    => array( 'field' => 'Economics' ),
		'commerce'                     => array( 'field' => 'Commerce' ),
		'biotechnology'                => array( 'field' => 'Biotechnology' ),
		'fashion'                      => array( 'field' => 'Fashion' ),
		'politics'                     => array( 'field' => 'Political Science' ),
		'digital-marketing'            => array( 'field' => 'Marketing' ),
		'pr'                           => array( 'field' => 'Marketing' ),
		'content-writing'              => array( 'field' => 'Content Writing' ),
		'graphic-designing'            => array( 'field' => 'Design' ),

		// Location. Continents (asia, europe, africa) are too broad to set a country.
		'usa'                          => array( 'country' => 'United States' ),
		'canada'                       => array( 'country' => 'Canada' ),
		'france'                       => array( 'country' => 'France' ),
		'germany'                      => array( 'country' => 'Germany' ),
		'denmark'                      => array( 'country' => 'Denmark' ),
		'italy'                        => array( 'country' => 'Italy' ),
		'hong-kong'                    => array( 'country' => 'Hong Kong' ),

		// Organisations named by a category. "iit" is left out: it does not say which IIT.
		'iisc'                         => array(
			'organisation' => 'Indian Institute of Science',
			'country'      => 'India',
		),
		'rbi' => array(
			'organisation' => 'Reserve Bank of India',
			'type'         => 'government',
			'country'      => 'India',
		),
		'google' => array(
			'organisation' => 'Google',
			'type'         => 'industry',
		),
		'nift' => array(
			'organisation' => 'National Institute of Fashion Technology',
			'field'        => 'Fashion',
			'country'      => 'India',
		),
	);

	/**
	 * When categories suggest more than one type, the most specific wins.
	 */
	private const TYPE_PRIORITY = array( 'virtual', 'government', 'ngo', 'research', 'industry' );

	/**
	 * Fields that a more specific field replaces (a post tagged engineering and computer-science is
	 * Computer Science).
	 */
	private const GENERIC_FIELDS = array( 'Engineering' );

	/**
	 * What a post's categories say about it.
	 *
	 * @param string[] $slugs The post's category slugs.
	 * @return array{kind: string, type: string, mode: string, field: string, country: string, organisation: string, anonymous: bool}
	 */
	public static function for_post( array $slugs ): array {
		$result = array(
			'kind'         => '',
			'type'         => '',
			'mode'         => '',
			'field'        => '',
			'country'      => '',
			'organisation' => '',
			'anonymous'    => false,
		);
		$types  = array();
		$fields = array();

		foreach ( $slugs as $slug ) {
			$rule = self::CATEGORIES[ $slug ] ?? array();

			// An experience category always wins over an article one: interviews filed under "blog" are interviews.
			if ( isset( $rule['kind'] ) && 'experience' !== $result['kind'] ) {
				$result['kind'] = (string) $rule['kind'];
			}
			if ( isset( $rule['type'] ) ) {
				$types[] = (string) $rule['type'];
			}
			if ( isset( $rule['field'] ) ) {
				$fields[] = (string) $rule['field'];
			}
			foreach ( array( 'mode', 'country', 'organisation' ) as $key ) {
				if ( isset( $rule[ $key ] ) && '' === $result[ $key ] ) {
					$result[ $key ] = (string) $rule[ $key ];
				}
			}
			if ( ! empty( $rule['anonymous'] ) ) {
				$result['anonymous'] = true;
			}
		}

		foreach ( self::TYPE_PRIORITY as $type ) {
			if ( in_array( $type, $types, true ) ) {
				$result['type'] = $type;
				break;
			}
		}

		$specific        = array_values( array_diff( array_unique( $fields ), self::GENERIC_FIELDS ) );
		$result['field'] = $specific[0] ?? ( $fields[0] ?? '' );

		// Posts with no kind category at all were filed only by topic; treat them as experiences for review.
		// A "blog" post that also names an internship type is an experience ("NETT4U, Virtual Internship |
		// Diksha Bharti"); internship guides stay articles.
		if ( '' === $result['kind'] || ( 'article' === $result['kind'] && array() !== $types && ! in_array( 'internship-guide', $slugs, true ) ) ) {
			$result['kind'] = 'experience';
		}

		return $result;
	}

	/**
	 * Every mapped category with what it sets, for the admin's mapping table.
	 *
	 * @return array<string, array<string, string|bool>>
	 */
	public static function table(): array {
		return self::CATEGORIES;
	}
}
