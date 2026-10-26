<?php
/**
 * Content types: experiences (the built-in post type, relabelled), the if_master taxonomy, the
 * if_list post type and the rejected and removed statuses (architecture.md 3.2 and 4).
 *
 * @package InternFeel
 */

declare(strict_types=1);

namespace InternFeel\Content;

use InternFeel\Fields\Fields;
use InternFeel\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registers InternFeel's content types and keeps anonymous authors out of public responses.
 */
final class Content implements Module {

	public const TAXONOMY  = 'if_master';
	public const LIST_TYPE = 'if_list';

	/**
	 * Add the module's hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_types' ), 5 );
		add_filter( 'post_type_labels_post', array( self::class, 'experience_labels' ) );
		add_filter( 'rest_prepare_post', array( self::class, 'hide_anonymous_author' ), 10, 2 );
		add_filter( 'rest_post_query', array( self::class, 'ignore_author_filter' ) );
		add_filter( 'the_author', array( self::class, 'anonymous_feed_author' ) );
	}

	/**
	 * Register the taxonomy, the listicle post type and the custom statuses.
	 */
	public static function register_types(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'post' ),
			array(
				'labels'            => array(
					'name'          => __( 'Master list', 'internfeel' ),
					'singular_name' => __( 'Master list entry', 'internfeel' ),
					'menu_name'     => __( 'Master list', 'internfeel' ),
					'add_new_item'  => __( 'Add organisation, programme or field', 'internfeel' ),
				),
				'description'       => __( 'Organisations (top level), their programmes (children) and fields of study.', 'internfeel' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'         => 'org',
					'with_front'   => false,
					'hierarchical' => true,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		register_post_type(
			self::LIST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Listicles', 'internfeel' ),
					'singular_name' => __( 'Listicle', 'internfeel' ),
					'add_new_item'  => __( 'Add listicle', 'internfeel' ),
					'edit_item'     => __( 'Edit listicle', 'internfeel' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-list-view',
				'menu_position'   => 6,
				'supports'        => array( 'title', 'editor', 'excerpt', 'custom-fields' ),
				'has_archive'     => 'lists',
				'rewrite'         => array(
					'slug'       => 'lists',
					'with_front' => false,
				),
				'capability_type' => array( 'if_list', 'if_lists' ),
				'map_meta_cap'    => true,
			)
		);

		register_post_status(
			'rejected',
			array(
				'label'                     => _x( 'Rejected', 'experience status', 'internfeel' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of experiences. */
				'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'internfeel' ),
			)
		);

		register_post_status(
			'removed',
			array(
				'label'                     => _x( 'Removed', 'experience status', 'internfeel' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of experiences. */
				'label_count'               => _n_noop( 'Removed <span class="count">(%s)</span>', 'Removed <span class="count">(%s)</span>', 'internfeel' ),
			)
		);
	}

	/**
	 * Call posts "Experiences" in wp-admin. The built-in post type is kept so the old /slug/ URLs work.
	 *
	 * @param object $labels Post type labels.
	 * @return object
	 */
	public static function experience_labels( object $labels ): object {
		$names = array(
			'name'                   => __( 'Experiences', 'internfeel' ),
			'singular_name'          => __( 'Experience', 'internfeel' ),
			'add_new'                => __( 'Add experience', 'internfeel' ),
			'add_new_item'           => __( 'Add experience', 'internfeel' ),
			'edit_item'              => __( 'Edit experience', 'internfeel' ),
			'new_item'               => __( 'New experience', 'internfeel' ),
			'view_item'              => __( 'View experience', 'internfeel' ),
			'view_items'             => __( 'View experiences', 'internfeel' ),
			'search_items'           => __( 'Search experiences', 'internfeel' ),
			'not_found'              => __( 'No experiences found.', 'internfeel' ),
			'not_found_in_trash'     => __( 'No experiences found in the bin.', 'internfeel' ),
			'all_items'              => __( 'All experiences', 'internfeel' ),
			'menu_name'              => __( 'Experiences', 'internfeel' ),
			'name_admin_bar'         => __( 'Experience', 'internfeel' ),
			'item_published'         => __( 'Experience published.', 'internfeel' ),
			'item_updated'           => __( 'Experience updated.', 'internfeel' ),
			'item_reverted_to_draft' => __( 'Experience reverted to draft.', 'internfeel' ),
		);

		foreach ( $names as $key => $value ) {
			$labels->{$key} = $value;
		}

		return $labels;
	}

	/**
	 * Remove the author from REST responses for anonymous experiences, for everyone except editors.
	 *
	 * @param \WP_REST_Response $response The prepared response.
	 * @param \WP_Post          $post     The experience.
	 * @return \WP_REST_Response
	 */
	public static function hide_anonymous_author( \WP_REST_Response $response, \WP_Post $post ): \WP_REST_Response {
		if ( current_user_can( 'edit_others_posts' ) || ! Fields::is_anonymous( $post->ID ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( is_array( $data ) ) {
			$data['author'] = 0;
			unset( $data['yoast_head'], $data['yoast_head_json'] );
			$response->set_data( $data );
		}
		$response->remove_link( 'author' );

		return $response;
	}

	/**
	 * Ignore ?author= on the public posts endpoint, so nobody can list an author's anonymous experiences.
	 *
	 * @param array<string, mixed> $args WP_Query arguments built from the request.
	 * @return array<string, mixed>
	 */
	public static function ignore_author_filter( array $args ): array {
		if ( current_user_can( 'edit_others_posts' ) ) {
			return $args;
		}

		unset( $args['author'], $args['author__in'], $args['author__not_in'] );

		return $args;
	}

	/**
	 * Show "Anonymous" as the author of anonymous experiences in feeds.
	 *
	 * @param string|null $display_name The author's display name.
	 * @return string|null
	 */
	public static function anonymous_feed_author( ?string $display_name ): ?string {
		$post = get_post();
		if ( ! is_feed() || ! $post instanceof \WP_Post || ! Fields::is_anonymous( $post->ID ) ) {
			return $display_name;
		}

		return __( 'Anonymous', 'internfeel' );
	}
}
