<?php
/**
 * Schema step 1: moderation log, roles, archive user, pages, permalinks.
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Schema;

/**
 * @covers \InternFeel\Schema
 * @covers \InternFeel\Auth\Roles
 */
class SchemaTest extends WP_UnitTestCase {

	/**
	 * The test framework deletes users and posts between test classes, so run every step again
	 * (inside the test's transaction) instead of relying on what the bootstrap left behind.
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();
	}

	public function test_schema_is_at_the_latest_version(): void {
		$this->assertSame( Schema::latest(), (int) get_option( Schema::OPTION ) );
	}

	public function test_moderation_log_table_exists_with_its_columns(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'if_moderation_log';

		$this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame(
			array( 'id', 'actor_id', 'target_type', 'target_id', 'action', 'reason', 'note', 'created_at' ),
			$columns
		);
	}

	public function test_student_role_can_only_read(): void {
		$role = get_role( Roles::STUDENT );

		$this->assertInstanceOf( WP_Role::class, $role );
		$this->assertTrue( $role->has_cap( 'read' ) );
		$this->assertTrue( $role->has_cap( 'if_submit' ) );
		$this->assertFalse( $role->has_cap( 'edit_posts' ) );
		$this->assertFalse( $role->has_cap( 'upload_files' ) );
		$this->assertSame( Roles::STUDENT, get_option( 'default_role' ) );
	}

	public function test_administrators_can_moderate_and_manage_listicles(): void {
		$admin = get_role( 'administrator' );

		$this->assertTrue( $admin->has_cap( Roles::MODERATE ) );
		$this->assertTrue( $admin->has_cap( 'publish_if_lists' ) );
	}

	public function test_archive_user_exists_and_cannot_sign_in(): void {
		$user = get_userdata( (int) get_option( Roles::ARCHIVE_OPTION ) );

		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( array( Roles::ARCHIVE ), array_values( $user->roles ) );
		$this->assertWPError( apply_filters( 'authenticate', $user, $user->user_login, 'anything' ) );
	}

	public function test_signup_and_login_pages_exist(): void {
		$this->assertInstanceOf( WP_Post::class, get_page_by_path( 'signup' ) );
		$this->assertInstanceOf( WP_Post::class, get_page_by_path( 'login' ) );
	}

	public function test_experiences_use_slug_permalinks(): void {
		$this->assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
	}

	public function test_running_every_step_again_changes_nothing(): void {
		$archive_id = (int) get_option( Roles::ARCHIVE_OPTION );

		update_option( Schema::OPTION, 0 );
		Schema::upgrade();

		$this->assertSame( Schema::latest(), (int) get_option( Schema::OPTION ) );
		$this->assertSame( $archive_id, (int) get_option( Roles::ARCHIVE_OPTION ) );
		$this->assertCount( 1, get_posts( array( 'post_type' => 'page', 'name' => 'signup', 'post_status' => 'any' ) ) );
	}
}
