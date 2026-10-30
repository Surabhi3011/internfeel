<?php
/**
 * Invites, referral credit and the abuse rules (plan.md 5.5, architecture.md 5.3).
 *
 * @package InternFeel
 */

use InternFeel\Auth\Roles;
use InternFeel\Fields\Fields;
use InternFeel\Invites\Invites;
use InternFeel\Moderation\Decisions;
use InternFeel\Moderation\Log;
use InternFeel\Notify\Notify;
use InternFeel\Schema;
use InternFeel\Submit\Submit;

/**
 * @covers \InternFeel\Invites\Invites
 */
class InvitesTest extends WP_UnitTestCase {

	private int $admin;
	private int $inviter;

	public function set_up(): void {
		parent::set_up();
		if ( ! registered_meta_key_exists( 'post', '_if_paid', 'post' ) ) {
			Fields::register_meta();
		}
		update_option( Schema::OPTION, 0 );
		Schema::upgrade();
		$this->admin   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->inviter = self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
	}

	private function friend(): int {
		return self::factory()->user->create( array( 'role' => Roles::STUDENT ) );
	}

	public function test_a_published_and_verified_experience_earns_three_credits_once(): void {
		wp_set_current_user( $this->admin );
		$id = self::factory()->post->create(
			array(
				'post_status' => 'pending',
				'post_author' => $this->inviter,
			)
		);
		update_post_meta( $id, '_if_verification_status', 'pending' );

		Decisions::apply( 'approve', $id, $this->admin );
		$this->assertSame( 0, Invites::credits( $this->inviter ), 'Not verified yet.' );

		Decisions::apply( 'verify', $id, $this->admin );
		$this->assertSame( 3, Invites::credits( $this->inviter ) );

		Decisions::maybe_count_verified( $id );
		$this->assertSame( 3, Invites::credits( $this->inviter ), 'Only once per experience.' );
	}

	public function test_each_link_spends_a_credit_and_needs_one(): void {
		$this->assertWPError( Invites::create( $this->inviter ) );

		Invites::change_credits( $this->inviter, 1 );
		$invite = Invites::create( $this->inviter );

		$this->assertIsArray( $invite );
		$this->assertMatchesRegularExpression( '/^[A-Z0-9]{8}$/', $invite['code'] );
		$this->assertSame( 0, Invites::credits( $this->inviter ) );
		$this->assertSame( $this->inviter, Invites::owner( $invite['code'] ) );
		$this->assertStringContainsString( '/join/?ref=' . $invite['code'], Invites::link( $invite['code'] ) );
		$this->assertWPError( Invites::create( $this->inviter ) );
	}

	public function test_signing_up_through_a_link_credits_the_inviter_once(): void {
		Invites::change_credits( $this->inviter, 1 );
		$code   = Invites::create( $this->inviter, '10.0.0.1' )['code'];
		$friend = $this->friend();

		Invites::accept( $friend, $this->inviter, $code, '10.0.0.2' );

		$this->assertSame( (string) $this->inviter, get_user_meta( $friend, '_if_referred_by_user_id', true ) );
		$this->assertSame( '1', get_user_meta( $this->inviter, '_if_invites_joined', true ) );
		$this->assertNull( Invites::open_invite( $code ), 'Single use.' );
		$this->assertSame( 'invite_used', Notify::all( $this->inviter )[0]['type'] );
		$this->assertSame( '', get_user_meta( $friend, '_if_referral_flagged', true ) );

		// A second friend cannot reuse the code.
		$this->assertNull( Invites::open_invite( $code ) );
	}

	public function test_the_cookie_path_attributes_on_user_register(): void {
		Invites::change_credits( $this->inviter, 1 );
		$code                       = Invites::create( $this->inviter )['code'];
		$_COOKIE[ Invites::COOKIE ] = $code;

		$friend = $this->friend();

		$this->assertSame( (string) $this->inviter, get_user_meta( $friend, '_if_referred_by_user_id', true ) );
		$this->assertArrayNotHasKey( Invites::COOKIE, $_COOKIE );
	}

	public function test_every_submission_is_credited_to_the_inviter(): void {
		Invites::change_credits( $this->inviter, 1 );
		$code   = Invites::create( $this->inviter )['code'];
		$friend = $this->friend();
		Invites::accept( $friend, $this->inviter, $code, '' );

		foreach ( array( 1, 2 ) as $n ) {
			$id = self::factory()->post->create(
				array(
					'post_status' => 'draft',
					'post_author' => $friend,
				)
			);
			Submit::submit( $id );
			$this->assertSame( (string) $this->inviter, get_post_meta( $id, '_if_referrer_user_id', true ), "Submission {$n}" );
		}
	}

	public function test_a_sign_up_from_the_inviters_ip_within_a_day_is_flagged(): void {
		Invites::change_credits( $this->inviter, 1 );
		$code   = Invites::create( $this->inviter, '203.0.113.7' )['code'];
		$friend = $this->friend();

		Invites::accept( $friend, $this->inviter, $code, '203.0.113.7' );

		$this->assertSame( 'same_ip', get_user_meta( $friend, '_if_referral_flagged', true ) );
		$this->assertSame( 'flag', Log::for_target( 'referral', $friend )[0]['action'] );
	}

	public function test_admins_can_remove_a_referral_or_a_credit_and_it_is_logged(): void {
		Invites::change_credits( $this->inviter, 2 );
		$code   = Invites::create( $this->inviter )['code'];
		$friend = $this->friend();
		Invites::accept( $friend, $this->inviter, $code, '' );

		$this->assertTrue( Invites::remove_referral( $friend, $this->admin, 'Same person' ) );
		$this->assertFalse( metadata_exists( 'user', $friend, '_if_referred_by_user_id' ) );
		$this->assertSame( '0', get_user_meta( $this->inviter, '_if_invites_joined', true ) );
		$this->assertSame( 'remove_referral', Log::for_target( 'referral', $friend )[0]['action'] );

		$this->assertTrue( Invites::remove_credit( $this->inviter, $this->admin ) );
		$this->assertSame( 0, Invites::credits( $this->inviter ) );
		$this->assertFalse( Invites::remove_credit( $this->inviter, $this->admin ), 'Never below 0.' );
		$this->assertSame( 'remove_credit', Log::for_target( 'user', $this->inviter )[0]['action'] );
	}

	public function test_the_rest_route_makes_a_link_for_the_signed_in_student(): void {
		Invites::change_credits( $this->inviter, 1 );
		wp_set_current_user( $this->inviter );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/internfeel/v1/invites' ) );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 0, $response->get_data()['credits'] );
		$this->assertCount( 1, $response->get_data()['invites'] );

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_do_request( new WP_REST_Request( 'GET', '/internfeel/v1/invites' ) )->get_status() );
	}
}
