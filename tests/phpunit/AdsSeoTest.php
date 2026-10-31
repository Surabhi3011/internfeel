<?php
/**
 * Ad slots, search-engine rules and the draft legal pages (plan.md 5.6, architecture.md 10 and 11).
 * The ad and SEO helpers live in the theme (inc/ads.php, inc/seo.php), which wp-env activates.
 *
 * @package InternFeel
 */

use InternFeel\Content\Pages;
use InternFeel\Schema;

/**
 * @covers \InternFeel\Content\Pages::draft
 */
class AdsSeoTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'if_the_ad' ) ) {
			$this->markTestSkipped( 'The InternFeel theme is not active.' );
		}
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();
	}

	public function test_ads_stay_off_without_a_real_publisher_and_slot(): void {
		// wp-env sets IF_ADSENSE_CLIENT empty, as staging does.
		$this->assertSame( '', if_adsense_client() );
		$this->assertSame( '', if_ad_slot_id( 'inline' ) );
	}

	public function test_no_slot_renders_without_adsense_settings(): void {
		ob_start();
		if_the_ad( 'inline' );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_dashboard_and_legal_pages_never_carry_ads(): void {
		$this->go_to( get_permalink( get_page_by_path( 'dashboard' ) ) );
		$this->assertFalse( if_page_allows_ads() );

		$terms = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => 'terms',
				'post_status' => 'draft',
			)
		)[0];
		wp_publish_post( $terms );
		$this->go_to( get_permalink( $terms ) );
		$this->assertFalse( if_page_allows_ads() );

		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$this->assertTrue( if_page_allows_ads() );
	}

	public function test_staging_and_utility_pages_are_noindex(): void {
		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$robots = if_robots( array() );
		if ( 'production' === wp_get_environment_type() ) {
			$this->assertArrayNotHasKey( 'noindex', $robots );
		} else {
			$this->assertTrue( $robots['noindex'], 'Everything outside production is noindex.' );
		}
	}

	public function test_the_sitemap_lists_only_experiences_pages_listicles_and_hubs(): void {
		$types = apply_filters(
			'wp_sitemaps_post_types',
			array(
				'post'       => 1,
				'page'       => 1,
				'if_list'    => 1,
				'attachment' => 1,
			)
		);
		$this->assertSame( array( 'post', 'page', 'if_list' ), array_keys( $types ) );

		$taxonomies = apply_filters(
			'wp_sitemaps_taxonomies',
			array(
				'category'  => 1,
				'post_tag'  => 1,
				'if_master' => 1,
			)
		);
		$this->assertSame( array( 'if_master' ), array_keys( $taxonomies ) );
		$this->assertFalse( apply_filters( 'wp_sitemaps_add_provider', 'users-provider', 'users' ) );
	}

	public function test_the_about_draft_takes_the_new_text_only_while_untouched(): void {
		$about = get_posts(
			array(
				'post_type'   => 'page',
				'name'        => 'about',
				'post_status' => 'draft',
			)
		)[0];
		$this->assertStringContainsString( 'InternFeel began in 2012', $about->post_content );

		// A draft still holding the old placeholder is refreshed; an edited one is left alone.
		wp_update_post(
			array(
				'ID'           => $about->ID,
				'post_content' => '[TO CONFIRM: Surabhi to rewrite this]',
			)
		);
		$this->assertTrue( Pages::refresh_draft( 'about', 'TO CONFIRM: Surabhi to rewrite' ) );
		$this->assertStringContainsString( 'InternFeel began in 2012', get_post( $about->ID )->post_content );

		wp_update_post(
			array(
				'ID'           => $about->ID,
				'post_content' => 'Our own words',
			)
		);
		$this->assertFalse( Pages::refresh_draft( 'about', 'TO CONFIRM: Surabhi to rewrite' ) );
		$this->assertSame( 'Our own words', get_post( $about->ID )->post_content );
	}

	public function test_legal_pages_start_as_drafts_and_are_never_overwritten(): void {
		$id   = Pages::draft( 'privacy-policy', 'Privacy policy', 'template-legal.php' );
		$post = get_post( $id );

		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( 'template-legal.php', get_page_template_slug( $id ) );
		$this->assertStringContainsString( 'TO CONFIRM', $post->post_content );

		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'Edited by an admin',
			)
		);
		$this->assertSame( $id, Pages::draft( 'privacy-policy', 'Privacy policy', 'template-legal.php' ) );
		$this->assertSame( 'Edited by an admin', get_post( $id )->post_content );
	}
}
