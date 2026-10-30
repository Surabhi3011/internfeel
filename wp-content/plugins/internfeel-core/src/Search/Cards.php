<?php
/**
 * What an experience card shows (plan.md 5.2, approved-designs README "Experience card"). The same
 * data feeds the theme's PHP card and the JavaScript results grid, so both look the same.
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Search;

use InternFeel\Fields\Fields;
use InternFeel\Submit\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Public card data only: never the author of an anonymous experience, never private meta.
 *
 * @phpstan-type Card array{id: int, url: string, title: string, organisation: string, initial: string, place: string, type: string, field: string, when: string, pay: string, rating: float, verified: bool, views: int, invited: bool}
 */
final class Cards {

	/**
	 * Labels for the type field.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array(
			'research'   => __( 'Research', 'internfeel' ),
			'industry'   => __( 'Industry', 'internfeel' ),
			'government' => __( 'Government', 'internfeel' ),
			'ngo'        => __( 'NGO', 'internfeel' ),
			'virtual'    => __( 'Virtual', 'internfeel' ),
		);
	}

	/**
	 * The stipend bands cards show (a month, in rupees).
	 *
	 * @return array<string, string>
	 */
	public static function bands(): array {
		return array(
			'under10' => __( 'under ₹10k', 'internfeel' ),
			'10to25'  => __( '₹10k–25k', 'internfeel' ),
			'over25'  => __( 'above ₹25k', 'internfeel' ),
		);
	}

	/**
	 * The band a monthly stipend falls in, or an empty string.
	 *
	 * @param int $amount Rupees a month.
	 * @return string
	 */
	public static function band( int $amount ): string {
		if ( $amount <= 0 ) {
			return '';
		}

		return $amount < 10000 ? 'under10' : ( $amount <= 25000 ? '10to25' : 'over25' );
	}

	/**
	 * Card data for one experience.
	 *
	 * @param \WP_Post $post Experience.
	 * @return array<string, mixed>
	 * @phpstan-return Card
	 */
	public static function data( \WP_Post $post ): array {
		$v      = Form::values( $post->ID );
		$meta   = static fn( string $key ): string => (string) get_post_meta( $post->ID, Fields::PREFIX . $key, true );
		$types  = self::types();
		$bands  = self::bands();
		$year   = '' !== (string) $v['start_month'] ? substr( (string) $v['start_month'], 0, 4 ) : ( '0' !== $meta( 'legacy_published_year' ) ? $meta( 'legacy_published_year' ) : '' );
		$weeks  = (int) $v['duration_weeks'];
		$band   = self::band( (int) $v['stipend_amount_inr'] );
		$pay    = '';
		$org    = (string) $v['organisation'];
		$rating = (float) $meta( 'rating_overall' );

		if ( true === $v['paid'] ) {
			$pay = '' === $band ? __( 'Paid', 'internfeel' ) : __( 'Paid', 'internfeel' ) . ' · ' . $bands[ $band ];
		} elseif ( false === $v['paid'] ) {
			$pay = __( 'Unpaid', 'internfeel' );
		}

		$when = $year;
		if ( $weeks > 0 ) {
			/* translators: %d: number of weeks. */
			$when = trim( $year . ' · ' . sprintf( _n( '%d week', '%d weeks', $weeks, 'internfeel' ), $weeks ), ' ·' );
		}

		return array(
			'id'           => $post->ID,
			'url'          => (string) get_permalink( $post ),
			'title'        => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'organisation' => $org,
			'initial'      => '' === $org ? '' : mb_strtoupper( mb_substr( $org, 0, 1 ) ),
			'place'        => implode( ', ', array_filter( array( (string) $v['city'], (string) $v['country'] ) ) ),
			'type'         => $types[ (string) $v['type'] ] ?? '',
			'field'        => (string) $v['field'],
			'when'         => $when,
			'pay'          => $pay,
			'rating'       => round( $rating * 2 ) / 2,
			'verified'     => 'verified' === $meta( 'verification_status' ),
			'views'        => (int) $meta( 'view_count' ),
			'invited'      => (int) $meta( 'referrer_user_id' ) > 0,
		);
	}
}
