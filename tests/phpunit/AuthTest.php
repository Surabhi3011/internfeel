<?php
/**
 * Sign-up, log-in and profile REST routes (plan.md 5.1, architecture.md 5.2).
 *
 * @package InternFeel
 */

use InternFeel\Auth\Profile;
use InternFeel\Auth\Roles;

/**
 * @covers \InternFeel\Auth\Routes
 * @covers \InternFeel\Auth\Profile
 * @covers \InternFeel\Auth\Auth
 */
class AuthTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( 0 );
	}

	/**
	 * POST to an auth route with a valid form nonce.
	 *
	 * @param string                $route   Route under internfeel/v1.
	 * @param array<string, mixed>  $params  Body parameters.
	 * @param array<string, string> $headers Request headers.
	 */
	private function post( string $route, array $params, array $headers = array() ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/internfeel/v1/' . $route );
		$request->set_body_params( $params + array( '_if_nonce' => wp_create_nonce( 'if_auth' ) ) );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}

		return rest_do_request( $request );
	}

	public function test_signup_creates_a_signed_in_student_and_asks_for_the_profile(): void {
		$response = $this->post( 'auth/signup', array( 'email' => 'new.student@example.com', 'password' => 'long enough' ) );

		$this->assertSame( 201, $response->get_status() );
		$user = get_user_by( 'email', 'new.student@example.com' );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( array( Roles::STUDENT ), array_values( $user->roles ) );
		$this->assertStringNotContainsString( 'new.student', $user->user_login );
		$this->assertSame( $user->ID, get_current_user_id() );
		$this->assertStringContainsString( 'step=profile', $response->get_data()['redirect'] );
	}

	public function test_signup_rejects_a_short_password(): void {
		$response = $this->post( 'auth/signup', array( 'email' => 'short@example.com', 'password' => 'short' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'password', $response->get_data()['data']['field'] );
		$this->assertFalse( get_user_by( 'email', 'short@example.com' ) );
	}

	public function test_signup_rejects_an_existing_email(): void {
		self::factory()->user->create( array( 'user_email' => 'taken@example.com' ) );

		$this->assertSame( 409, $this->post( 'auth/signup', array( 'email' => 'taken@example.com', 'password' => 'long enough' ) )->get_status() );
	}

	public function test_signup_rejects_the_honeypot(): void {
		$response = $this->post( 'auth/signup', array( 'email' => 'bot@example.com', 'password' => 'long enough', 'website' => 'http://spam.example.com' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_user_by( 'email', 'bot@example.com' ) );
	}

	public function test_signup_needs_the_form_nonce(): void {
		$request = new WP_REST_Request( 'POST', '/internfeel/v1/auth/signup' );
		$request->set_body_params( array( 'email' => 'nonce@example.com', 'password' => 'long enough' ) );

		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}

	public function test_signup_refuses_other_sites(): void {
		$response = $this->post( 'auth/signup', array( 'email' => 'csrf@example.com', 'password' => 'long enough' ), array( 'origin' => 'https://evil.example.com' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_signup_is_rate_limited(): void {
		$limit = static fn(): int => 2;
		add_filter( 'if_rate_limit', $limit );

		$this->post( 'auth/signup', array( 'email' => 'a@example.com', 'password' => 'long enough' ) );
		wp_set_current_user( 0 );
		$this->post( 'auth/signup', array( 'email' => 'b@example.com', 'password' => 'long enough' ) );
		wp_set_current_user( 0 );
		$third = $this->post( 'auth/signup', array( 'email' => 'c@example.com', 'password' => 'long enough' ) );

		remove_filter( 'if_rate_limit', $limit );
		$this->assertSame( 429, $third->get_status() );
	}

	public function test_login_signs_in_a_student(): void {
		$id = self::factory()->user->create(
			array(
				'role'       => Roles::STUDENT,
				'user_email' => 'student@example.com',
				'user_pass'  => 'correct horse',
			)
		);

		$response = $this->post( 'auth/login', array( 'login' => 'student@example.com', 'password' => 'correct horse' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $id, get_current_user_id() );
		$this->assertSame( Profile::url(), $response->get_data()['redirect'] );
	}

	public function test_login_rejects_a_wrong_password_without_saying_which_part_was_wrong(): void {
		self::factory()->user->create(
			array(
				'role'       => Roles::STUDENT,
				'user_email' => 'student2@example.com',
				'user_pass'  => 'correct horse',
			)
		);

		$wrong_password = $this->post( 'auth/login', array( 'login' => 'student2@example.com', 'password' => 'wrong' ) );
		$unknown_email  = $this->post( 'auth/login', array( 'login' => 'nobody@example.com', 'password' => 'wrong' ) );

		$this->assertSame( 401, $wrong_password->get_status() );
		$this->assertSame( $wrong_password->get_data()['message'], $unknown_email->get_data()['message'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_admins_are_sent_to_wp_login(): void {
		self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin@example.com',
				'user_pass'  => 'correct horse',
			)
		);

		$response = $this->post( 'auth/login', array( 'login' => 'admin@example.com', 'password' => 'correct horse' ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'if_use_admin_login', $response->get_data()['code'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_profile_needs_a_signed_in_user(): void {
		$this->assertSame( 401, rest_do_request( new WP_REST_Request( 'GET', '/internfeel/v1/me' ) )->get_status() );
	}

	public function test_profile_validates_then_completes(): void {
		$id = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		wp_set_current_user( $id );
		$this->assertFalse( Profile::is_complete( $id ) );

		$fields  = array(
			'full_name'       => 'Priya Sharma',
			'course'          => 'BTech Computer Science',
			'graduation_year' => 2027,
			'is_under_18'     => '0',
		);
		$missing = new WP_REST_Request( 'POST', '/internfeel/v1/me' );
		$missing->set_body_params( $fields );
		$response = rest_do_request( $missing );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'college', $response->get_data()['data']['field'] );

		$complete = new WP_REST_Request( 'POST', '/internfeel/v1/me' );
		$complete->set_body_params( $fields + array( 'college' => 'IIT Bombay' ) );
		$this->assertSame( 200, rest_do_request( $complete )->get_status() );

		$this->assertTrue( Profile::is_complete( $id ) );
		// A blank display name shows the full name (sign-off decision, 26 October 2026).
		$this->assertSame( 'Priya Sharma', get_userdata( $id )->display_name );
		$this->assertSame( 'Priya Sharma', Profile::get( $id )['full_name'] );
		$this->assertSame( '', Profile::get( $id )['display_name'] );
		$this->assertFalse( Profile::get( $id )['is_under_18'] );
	}

	public function test_profile_rejects_a_non_linkedin_link(): void {
		$id = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		wp_set_current_user( $id );

		$request = new WP_REST_Request( 'POST', '/internfeel/v1/me' );
		$request->set_body_params(
			array(
				'full_name'       => 'Priya Sharma',
				'college'         => 'IIT Bombay',
				'course'          => 'BTech',
				'graduation_year' => 2027,
				'is_under_18'     => '1',
				'linkedin_url'    => 'https://example.com/priya',
			)
		);

		$this->assertSame( 'linkedin_url', rest_do_request( $request )->get_data()['data']['field'] );
	}

	public function test_students_get_no_admin_bar(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => Roles::STUDENT ) ) );
		$this->assertFalse( apply_filters( 'show_admin_bar', true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( apply_filters( 'show_admin_bar', true ) );
	}

	public function test_password_reset_email_comes_from_no_reply(): void {
		$this->assertSame( constant( 'IF_MAIL_FROM' ), apply_filters( 'wp_mail_from', 'wordpress@localhost' ) );
	}

	public function test_a_chosen_display_name_is_kept(): void {
		$id = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
		wp_set_current_user( $id );

		$request = new WP_REST_Request( 'POST', '/internfeel/v1/me' );
		$request->set_body_params(
			array(
				'full_name'       => 'Priya Sharma',
				'display_name'    => 'Priya from Pune',
				'college'         => 'IIT Bombay',
				'course'          => 'BTech',
				'graduation_year' => 2027,
				'is_under_18'     => '0',
			)
		);
		rest_do_request( $request );

		$this->assertSame( 'Priya from Pune', get_userdata( $id )->display_name );
		$this->assertSame( 'Priya from Pune', Profile::get( $id )['display_name'] );
	}
}
