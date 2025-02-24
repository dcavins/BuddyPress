<?php
/**
 * @group groups
 * @group BP_Groups_Member
 */
class BP_Tests_BP_Groups_Member_TestCases extends BP_UnitTestCase {
	static public $user_ids;
	static public $group_ids;
	protected $permalink_structure = '';

	public function set_up() {
		parent::set_up();
		$this->permalink_structure = get_option( 'permalink_structure', '' );
	}

	public function tear_down() {
		$this->set_permalink_structure( $this->permalink_structure );
		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( $factory ) {
		global $wpdb, $bp;
		self::$user_ids  = $factory->user->create_many( 4 );
		self::$group_ids = $factory->group->create_many( 3, array(
			'creator_id' => self::$user_ids[3],
		) );
	}

	public static function wpTearDownAfterClass() {
		array_map( array( __CLASS__, 'delete_user' ), self::$user_ids );
		array_map( 'groups_delete_group', self::$group_ids );
	}

	public function test_get_recently_joined_with_filter() {
		$g1 = self::factory()->group->create( array(
			'name' => 'Tab',
		) );
		$g2 = self::factory()->group->create( array(
			'name' => 'Diet Rite',
		) );

		$u = self::factory()->user->create();
		self::add_user_to_group( $u, $g1 );
		self::add_user_to_group( $u, $g2 );

		$groups = BP_Groups_Member::get_recently_joined( $u, false, false, 'Rite' );

		$ids = wp_list_pluck( $groups['groups'], 'id' );
		$this->assertEquals( $ids, array( $g2 ) );
	}

	public function test_get_is_admin_of_with_filter() {
		$g1 = self::factory()->group->create( array(
			'name' => 'RC Cola',
		) );
		$g2 = self::factory()->group->create( array(
			'name' => 'Pepsi',
		) );

		$u = self::factory()->user->create();
		self::add_user_to_group( $u, $g1 );
		self::add_user_to_group( $u, $g2 );

		$m1 = new BP_Groups_Member( $u, $g1 );
		$m1->promote( 'admin' );
		$m2 = new BP_Groups_Member( $u, $g2 );
		$m2->promote( 'admin' );

		$groups = BP_Groups_Member::get_is_admin_of( $u, false, false, 'eps' );

		$ids = wp_list_pluck( $groups['groups'], 'id' );
		$this->assertEquals( $ids, array( $g2 ) );
	}

	public function test_get_is_mod_of_with_filter() {
		$g1 = self::factory()->group->create( array(
			'name' => 'RC Cola',
		) );
		$g2 = self::factory()->group->create( array(
			'name' => 'Pepsi',
		) );

		$u = self::factory()->user->create();
		self::add_user_to_group( $u, $g1 );
		self::add_user_to_group( $u, $g2 );

		$m1 = new BP_Groups_Member( $u, $g1 );
		$m1->promote( 'mod' );
		$m2 = new BP_Groups_Member( $u, $g2 );
		$m2->promote( 'mod' );

		$groups = BP_Groups_Member::get_is_mod_of( $u, false, false, 'eps' );

		$ids = wp_list_pluck( $groups['groups'], 'id' );
		$this->assertEquals( $ids, array( $g2 ) );
	}

	public function test_get_is_banned_of_with_filter() {
		$g1 = self::factory()->group->create( array(
			'name' => 'RC Cola',
		) );
		$g2 = self::factory()->group->create( array(
			'name' => 'Pepsi',
		) );

		$u = self::factory()->user->create();
		self::add_user_to_group( $u, $g1 );
		self::add_user_to_group( $u, $g2 );

		$m1 = new BP_Groups_Member( $u, $g1 );
		$m1->ban();
		$m2 = new BP_Groups_Member( $u, $g2 );
		$m2->ban();

		$groups = BP_Groups_Member::get_is_banned_of( $u, false, false, 'eps' );

		$ids = wp_list_pluck( $groups['groups'], 'id' );
		$this->assertEquals( $ids, array( $g2 ) );
	}

	public function test_get_invites_with_exclude() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array(
			'status' => 'private',
			'creator_id' => $u1
		) );
		$g2 = self::factory()->group->create( array(
			'status' => 'private',
			'creator_id' => $u1
		) );

		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g1,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g2,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		$groups = BP_Groups_Member::get_invites( $u2, false, false, array( 'awesome', $g1 ) );

		$ids = wp_list_pluck( $groups['groups'], 'id' );
		$this->assertEquals( $ids, array( $g2 ) );
	}

	/**
	 * @expectedDeprecated BP_Groups_Member::get_all_for_group
	 */
	public function test_get_all_for_group_with_exclude() {
		$g1 = self::factory()->group->create();

		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		self::add_user_to_group( $u1, $g1 );
		self::add_user_to_group( $u2, $g1 );

		$members = BP_Groups_Member::get_all_for_group( $g1, false, false, true, true, array( $u1 ) );

		$mm = (array) $members['members'];
		$ids = wp_list_pluck( $mm, 'user_id' );
		$this->assertEquals( array( $u2 ), $ids );
	}

	/**
	 * @group bp_groups_user_can_send_invites
	 */
	public function test_bp_groups_user_can_send_invites() {
		$this->set_permalink_structure( '/%postname%/' );
		$u_nonmembers = self::factory()->user->create();
		$u_members    = self::factory()->user->create();
		$u_mods       = self::factory()->user->create();
		$u_admins     = self::factory()->user->create();
		$u_siteadmin  = self::factory()->user->create();

		$user_siteadmin = new WP_User( $u_siteadmin );
		$user_siteadmin->add_role( 'administrator' );

		$g = self::factory()->group->create();

		$time = time() - 60;
		$old_current_user = get_current_user_id();

		// Create member-level user
		$this->add_user_to_group( $u_members, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// Create mod-level user
		$this->add_user_to_group( $u_mods, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );
		$m_mod = new BP_Groups_Member( $u_mods, $g );
		$m_mod->promote( 'mod' );

		// Create admin-level user
		$this->add_user_to_group( $u_admins, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );
		$m_admin = new BP_Groups_Member( $u_admins, $g );
		$m_admin->promote( 'admin' );

		// Test with no status
		// In bp_group_get_invite_status(), no status falls back to "members"
		$this->assertFalse( bp_groups_user_can_send_invites( $g, $u_nonmembers ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_members ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_mods ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_admins ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_siteadmin ) );

		// Test with members status
		groups_update_groupmeta( $g, 'invite_status', 'members' );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, $u_nonmembers ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_members ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_mods ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_admins ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_siteadmin ) );
		// Falling back to current user
		wp_set_current_user( $u_members );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, null ) );

		// Test with mod status
		groups_update_groupmeta( $g, 'invite_status', 'mods' );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, $u_nonmembers ) );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, $u_members ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_mods ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_admins ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_siteadmin ) );
		// Falling back to current user
		wp_set_current_user( $u_members );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, null ) );
		wp_set_current_user( $u_mods );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, null ) );

		// Test with admin status
		groups_update_groupmeta( $g, 'invite_status', 'admins' );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, $u_nonmembers ) );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, $u_members ) );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, $u_mods ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_admins ) );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, $u_siteadmin ) );
		// Falling back to current user
		wp_set_current_user( $u_mods );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, null ) );
		wp_set_current_user( $u_admins );
		$this->assertTrue( bp_groups_user_can_send_invites( $g, null ) );

		// Bad or null parameters
		$this->assertFalse( bp_groups_user_can_send_invites( 59876454257, $u_members ) );
		$this->assertFalse( bp_groups_user_can_send_invites( $g, 958647515 ) );
		// Not in group context
		$this->assertFalse( bp_groups_user_can_send_invites( null, $u_members ) );
		// In group context
		$g_obj = groups_get_group( $g );
		$this->go_to( bp_get_group_url( $g_obj ) );
		groups_update_groupmeta( $g, 'invite_status', 'mods' );
		$this->assertFalse( bp_groups_user_can_send_invites( null, $u_nonmembers ) );
		$this->assertFalse( bp_groups_user_can_send_invites( null, $u_members ) );
		$this->assertTrue( bp_groups_user_can_send_invites( null, $u_mods ) );

		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_reject_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_bp_groups_reject_membership_request_remove_request() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		// Membership requests should be removed.
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g
		) );

		groups_reject_membership_request( null, $u1, $g );
		$u1_has_request = groups_check_for_membership_request( $u1, $g );
		$this->assertEquals( 0, $u1_has_request );
	}

	/**
	 * @group groups_reject_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_bp_groups_reject_membership_request_leave_memberships_intact() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$this->add_user_to_group( $u1, $g );

		// Confirmed memberships should be left intact.
		groups_reject_membership_request( null, $u1, $g );
		$u1_is_member = groups_is_user_member( $u1, $g );
		$this->assertTrue( is_numeric( $u1_is_member ) && $u1_is_member > 0 );
	}

	/**
	 * @group groups_reject_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_bp_groups_reject_membership_request_leave_invites_intact() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// Outstanding invitations should be left intact.
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_reject_membership_request( null, $u2, $g );
		$u2_has_invite = groups_check_user_has_invite( $u2, $g );
		$this->assertTrue( is_numeric( $u2_has_invite ) && $u2_has_invite > 0 );
	}

	/**
	 * @group groups_delete_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_bp_groups_delete_membership_request_remove_request() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		// Membership requests should be removed.
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g
		) );
		groups_delete_membership_request( null, $u1, $g );
		$u1_has_request = groups_check_for_membership_request( $u1, $g );
		$this->assertEquals( 0, $u1_has_request );
	}

	/**
	 * @group groups_delete_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_bp_groups_delete_membership_request_leave_memberships_intact() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$this->add_user_to_group( $u1, $g );

		// Confirmed memberships should be left intact.
		groups_delete_membership_request( null, $u1, $g );
		$u1_is_member = groups_is_user_member( $u1, $g );
		$this->assertTrue( is_numeric( $u1_is_member ) && $u1_is_member > 0 );
	}

	/**
	 * @group groups_delete_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_bp_groups_delete_membership_request_leave_invites_intact() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// Outstanding invitations should be left intact.
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		groups_delete_membership_request( null, $u2, $g );
		$u2_has_invite = groups_check_user_has_invite( $u2, $g );
		$this->assertTrue( is_numeric( $u2_has_invite ) && $u2_has_invite > 0 );
	}

	/**
	 * @group groups_reject_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_reject_invite_remove_invite() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// The invitation should be removed.
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		groups_reject_invite( $u2, $g );
		$u2_has_invite = groups_check_user_has_invite( $u2, $g, 'all' );
		$this->assertEquals( 0, $u2_has_invite );
	}

	/**
	 * @group groups_reject_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_reject_invite_leave_memberships_intact() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// Confirmed memberships should be left intact.
		groups_reject_invite( $u1, $g );
		$u1_is_member = groups_is_user_member( $u1, $g );
		$this->assertTrue( is_numeric( $u1_is_member ) && $u1_is_member > 0 );
	}

	/**
	 * @group groups_reject_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_reject_invite_leave_requests_intact() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		// Membership requests should be left intact.
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g
		) );
		groups_reject_invite( $u1, $g );
		$u1_has_request = groups_check_for_membership_request( $u1, $g );
		$this->assertTrue( is_numeric( $u1_has_request ) && $u1_has_request > 0 );
	}

	/**
	 * @group groups_delete_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_delete_invite_remove_invite() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// The invitation should be removed.
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		groups_delete_invite( $u2, $g );
		$u2_has_invite = groups_check_user_has_invite( $u2, $g, 'all' );
		$this->assertEquals( 0, $u2_has_invite );
	}

	/**
	 * @group groups_delete_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_delete_invite_remove_draft_invite() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g  = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// Create the draft invitation.
		groups_invite_user( array(
			'user_id'    => $u2,
			'group_id'   => $g,
			'inviter_id' => $u1
		) );

		// Check that the invite got created.
		$u2_has_invite = groups_check_user_has_invite( $u2, $g, 'all' );
		$this->assertTrue( is_numeric( $u2_has_invite ) && $u2_has_invite > 0 );

		// The invitation should be removed.
		groups_delete_invite( $u2, $g );
		$u2_has_invite = groups_check_user_has_invite( $u2, $g, 'all' );
		$this->assertEquals( 0, $u2_has_invite );
	}

	/**
	 * @group groups_delete_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_delete_invite_leave_memberships_intact() {
		$u1 = self::factory()->user->create();
		$g  = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		groups_delete_invite( $u1, $g );
		$u1_is_member = groups_is_user_member( $u1, $g );
		$this->assertTrue( is_numeric( $u1_is_member ) && $u1_is_member > 0 );
	}

	/**
	 * @group groups_delete_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_delete_invite_leave_requests_intact() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		// Membership requests should be left intact.
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g
		) );
		groups_delete_invite( $u1, $g );
		$u1_has_request = groups_check_for_membership_request( $u1, $g );
		$this->assertTrue( is_numeric( $u1_has_request ) && $u1_has_request > 0 );
	}

	/**
	 * @group groups_uninvite_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_uninvite_user_remove_invite() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// The invitation should be removed.
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_uninvite_user( $u2, $g );
		$u2_has_invite = groups_check_user_has_invite( $u2, $g, 'all' );
		$this->assertEquals( 0, $u2_has_invite );
	}

	/**
	 * @group groups_uninvite_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_uninvite_user_leave_memberships_intact() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		$time = time() - 60;
		$this->add_user_to_group( $u1, $g, array(
			'date_modified' => date( 'Y-m-d H:i:s', $time ),
		) );

		// Confirmed memberships should be left intact.
		groups_is_user_member( $u1, $g );
		groups_uninvite_user( $u1, $g );
		$u1_is_member = groups_is_user_member( $u1, $g );
		$this->assertTrue( is_numeric( $u1_is_member ) && $u1_is_member > 0 );
	}

	/**
	 * @group groups_uninvite_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_bp_groups_uninvite_user_leave_requests_intact() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array(
			'status' => 'private',
		) );

		// Membership requests should be left intact.
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g
		) );
		groups_uninvite_user( $u1, $g );
		$u1_has_request = groups_check_for_membership_request( $u1, $g );
		$this->assertTrue( is_numeric( $u1_has_request ) && $u1_has_request > 0 );
	}

	/**
	 * @group groups_join_group
	 * @group group_membership
	 */
	public function test_groups_join_group_basic_join() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create();

		groups_join_group( $g, $u1 );
		$membership_id = groups_is_user_member( $u1, $g );
		$this->assertTrue( is_numeric( $membership_id ) && $membership_id > 0 );
	}

	/**
	 * @group groups_join_group
	 * @group group_membership
	 */
	public function test_groups_join_group_basic_join_use_current_user() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create();
		$old_current_user = get_current_user_id();
		wp_set_current_user( $u1 );

		groups_join_group( $g );
		$membership_id = groups_is_user_member( $u1, $g );
		$this->assertTrue( is_numeric( $membership_id ) && $membership_id > 0 );
		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_join_group
	 * @group group_membership
	 */
	public function test_groups_join_group_already_member() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create();
		$this->add_user_to_group( $u1, $g );

		$this->assertTrue( groups_join_group( $g, $u1 ) );
	}

	/**
	 * @group groups_join_group
	 * @group group_membership
	 */
	public function test_groups_join_group_cleanup_invites() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g = self::factory()->group->create();
		$this->add_user_to_group( $u1, $g );

		$m1 = new BP_Groups_Member( $u1, $g );
		$m1->promote( 'admin' );

		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_join_group( $g, $u2 );
		// Upon joining the group, outstanding invitations should be cleaned up.
		$this->assertEquals( null, groups_check_user_has_invite( $u2, $g, 'any' ) );
	}

	/**
	 * @group groups_join_group
	 * @group group_membership
	 */
	public function test_groups_join_group_cleanup_requests() {
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create();

		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g
		) );

		groups_join_group( $g, $u1 );
		// Upon joining the group, outstanding requests should be cleaned up.
		$this->assertEquals( null, groups_check_for_membership_request( $u1, $g ) );
	}

	/**
	 * @group groups_leave_group
	 * @group group_membership
	 */
	public function test_groups_leave_group_basic_leave_self_initiated() {
		$old_current_user = get_current_user_id();
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array( 'creator_id' => $u1 ) );
		$u2 = self::factory()->user->create();
		$this->add_user_to_group( $u2, $g );

		$before = groups_get_total_member_count( $g );
		wp_set_current_user( $u2 );
		groups_leave_group( $g, $u2 );
		$after = groups_get_total_member_count( $g );

		$this->assertEquals( $before - 1, $after );
		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_leave_group
	 * @group group_membership
	 */
	public function test_groups_leave_group_basic_leave_use_current_user() {
		$old_current_user = get_current_user_id();
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array( 'creator_id' => $u1 ) );
		$u2 = self::factory()->user->create();
		$this->add_user_to_group( $u2, $g );

		$before = groups_get_total_member_count( $g );
		wp_set_current_user( $u2 );
		groups_leave_group( $g );
		$after = groups_get_total_member_count( $g );

		$this->assertEquals( $before - 1, $after );
		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_leave_group
	 * @group group_membership
	 */
	public function test_groups_leave_group_basic_leave_group_admin_initiated() {
		$old_current_user = get_current_user_id();
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array( 'creator_id' => $u1 ) );
		$u2 = self::factory()->user->create();
		$this->add_user_to_group( $u2, $g );

		$before = groups_get_total_member_count( $g );
		wp_set_current_user( $u1 );
		groups_leave_group( $g, $u2 );
		$after = groups_get_total_member_count( $g );

		$this->assertEquals( $before - 1, $after );
		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_leave_group
	 * @group group_membership
	 */
	public function test_groups_leave_group_basic_leave_site_admin_initiated() {
		$old_current_user = get_current_user_id();
		$u1 = self::factory()->user->create();
		$u1_siteadmin = new WP_User( $u1 );
		$u1_siteadmin->add_role( 'administrator' );
		$g = self::factory()->group->create( array( 'creator_id' => $u1 ) );
		$u2 = self::factory()->user->create();
		$this->add_user_to_group( $u2, $g );

		$before = groups_get_total_member_count( $g );
		wp_set_current_user( $u1 );
		groups_leave_group( $g, $u2 );
		$after = groups_get_total_member_count( $g );

		$this->assertEquals( $before - 1, $after );
		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_leave_group
	 * @group group_membership
	 */
	public function test_groups_leave_group_single_admin_prevent_leave() {
		$old_current_user = get_current_user_id();
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array( 'creator_id' => $u1 ) );
		$u2 = self::factory()->user->create();
		$this->add_user_to_group( $u2, $g );

		$before = groups_get_total_member_count( $g );
		wp_set_current_user( $u1 );
		groups_leave_group( $g, $u1 );
		$after = groups_get_total_member_count( $g );

		$this->assertEquals( $before, $after );
		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_leave_group
	 * @group group_membership
	 */
	public function test_groups_leave_group_multiple_admins_allow_leave() {
		$old_current_user = get_current_user_id();
		$u1 = self::factory()->user->create();
		$g = self::factory()->group->create( array( 'creator_id' => $u1 ) );
		$u2 = self::factory()->user->create();
		$this->add_user_to_group( $u2, $g );
		$m2 = new BP_Groups_Member( $u2, $g );
		$m2->promote( 'admin' );

		$before = groups_get_total_member_count( $g );
		wp_set_current_user( $u1 );
		groups_leave_group( $g, $u1 );
		$after = groups_get_total_member_count( $g );

		$this->assertEquals( $before - 1, $after );
		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_get_invites_for_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_get_invites_for_user() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g2 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g3 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g1,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g2,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g3,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		$groups = groups_get_invites_for_user( $u2 );

		$this->assertEqualSets( array( $g1, $g2, $g3 ), wp_list_pluck( $groups['groups'], 'id' ) );
		$this->assertEquals( 3, $groups['total'] );
	}

	/**
	 * @group groups_get_invites_for_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_get_invites_for_user_infer_user() {
		$old_current_user = get_current_user_id();

		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g2 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g3 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g1,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g2,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g3,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		wp_set_current_user( $u2 );
		$groups = groups_get_invites_for_user();
		$this->assertEqualSets( array( $g1, $g2, $g3 ), wp_list_pluck( $groups['groups'], 'id' ) );

		wp_set_current_user( $old_current_user );
	}

	/**
	 * @group groups_get_invites_for_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_get_invites_for_user_with_exclude() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g2 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g3 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g1,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g2,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g3,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		$groups = groups_get_invites_for_user( $u2, false, false, array( $g2 ) );
		$this->assertEqualSets( array( $g1, $g3 ), wp_list_pluck( $groups['groups'], 'id' ) );
		$this->assertEquals( 2, $groups['total'] );
	}

	/**
	 * @group groups_get_invite_count_for_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_get_invite_count_for_user() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g2 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );
		$g3 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g1,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g2,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g3,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		$this->assertEquals( 3, groups_get_invite_count_for_user( $u2 ) );
	}

	/**
	 * @group groups_get_invite_count_for_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_get_invite_count_for_user_ignore_drafts() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation.
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		// groups_get_invite_count_for_user should ignore draft invitations.
		$this->assertEquals( 0, groups_get_invite_count_for_user( $u2 ) );
	}

	/**
	 * @group groups_invite_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_invite_user() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		// Check that the draft invitation has been created.
		$draft = groups_check_user_has_invite( $u2, $g1, 'all' );
		$this->assertTrue( is_numeric( $draft ) && $draft > 0 );
	}

	/**
	 * @group groups_send_invites
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_send_invites() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		// Send the invitation
		groups_send_invites( array(
			'group_id'   => $g1,
			'inviter_id' => $u1,
		) );

		// Check that the invitation has been sent.
		$sent = groups_check_user_has_invite( $u2, $g1, $type = 'sent' );
		$this->assertTrue( is_numeric( $sent ) && $sent > 0 );
	}

	/**
	 * @group groups_send_invites
	 * @group group_invitations
	 * @group group_membership
	 * @expectedDeprecated groups_send_invites
	 */
	public function test_groups_send_invites_deprecated_args() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		// Send the invitation
		groups_send_invites( $u1, $g1 );

		// Check that the invitation has been sent.
		$sent = groups_check_user_has_invite( $u2, $g1, $type = 'sent' );
		$this->assertTrue( is_numeric( $sent ) && $sent > 0 );
	}

	/**
	 * @group groups_accept_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_accept_invite() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0,
			'send_invite'   => 1
		) );

		// Accept the invitation
		groups_accept_invite( $u2, $g1 );

		// Check that the user is a member of the group.
		$member = groups_is_user_member( $u2, $g1 );
		$this->assertTrue( is_numeric( $member ) && $member > 0 );
		// Check that the invite has been removed.
		$invite = groups_check_user_has_invite( $u2, $g1, 'all' );
		$this->assertFalse( $invite );
	}

	/**
	 * @group groups_accept_invite
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_accept_invite_removes_membership_requests() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		// Create membership request
		$request_id = groups_send_membership_request( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
		) );

		$request = groups_check_for_membership_request( $u2, $g1 );

		$this->assertTrue( is_numeric( $request ) && $request > 0 );

		// Send the invitation
		groups_send_invites( array(
			'group_id'   => $g1,
			'inviter_id' => $u1,
		) );

		// Accept the invitation
		groups_accept_invite( $u2, $g1 );

		// Check that the membership request has been removed.
		$this->assertTrue( 0 == groups_check_for_membership_request( $u2, $g1 ) );
	}

	/**
	 * @group groups_send_invites
	 * @group group_invitations
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_groups_sent_invite_plus_request_equals_member() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0,
			'send_invite'   => 1
		) );

		// Create membership request
		groups_send_membership_request( array(
			'user_id' => $u2,
			'group_id' => $g1
		) );

		// User should now be a group member
		$member = groups_is_user_member( $u2, $g1 );
		$this->assertTrue( is_numeric( $member ) && $member > 0 );
	}

	/**
	 * @group groups_delete_all_group_invites
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_delete_all_group_invites() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$u3 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		groups_invite_user( array(
			'user_id' => $u2,
			'group_id' => $g1,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );
		groups_invite_user( array(
			'user_id' => $u3,
			'group_id' => $g1,
			'inviter_id' => $u1,
			'send_invite' => 1,
		) );

		groups_delete_all_group_invites( $g1 );

		// Get group invitations of any type, from any user in the group.

		$invitees = groups_get_invites(	array(
			'group_id'     => $g1,
			'invite_sent'  => 'all',
		) );

		$this->assertTrue( empty( $invitees ) );
	}

	/**
	 * @group groups_invite_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_send_invites_fail_on_empty_group_id() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();

		// Create draft invitation with empty inviter_id
		$invite_created = groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => 0,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		$this->assertFalse( $invite_created );
	}

	/**
	 * @group groups_invite_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_send_invites_fail_on_empty_user_id() {
		$u1 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation with empty inviter_id
		$invite_created = groups_invite_user( array(
			'user_id'       => 0,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		$this->assertFalse( $invite_created );
	}

	/**
	 * @group groups_invite_user
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_send_invites_fail_on_empty_inviter_id() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation with empty inviter_id
		$invite_created = groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => 0,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0
		) );

		$this->assertFalse( $invite_created );
	}

	/**
	 * @group groups_get_invites_for_group
	 * @group group_send_invites
	 * @group group_invitations
	 * @group group_membership
	 */
	public function test_groups_get_invites_for_group_with_sent_parameter() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0,
			'send_invite'   => 1
		) );

		// Default groups_get_invites_for_group() call
		$i = groups_get_invites_for_group( $u1, $g1 );
		$this->assertEqualSets( array( $u2 ), $i );

		// Fetch users whose invites have been sent out; should be the same as above.
		$i = groups_get_invites_for_group( $u1, $g1 );
		$this->assertEqualSets( array( $u2 ), $i );

		// Fetch users whose invites haven't been sent yet.
		$i = groups_get_invites_for_group( $u1, $g1, 0 );
		$this->assertEmpty( $i );
	}

	/**
	 * @group groups_send_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_groups_send_membership_request() {
		$u1 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'status' => 'private' ) );

		// Create membership request
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g1
		) );

		$request = groups_check_for_membership_request( $u1, $g1 );
		$this->assertTrue( is_numeric( $request ) && $request > 0 );
	}

	/**
	 * @group groups_send_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 * @expectedDeprecated groups_send_membership_request
	 */
	public function test_groups_send_membership_request_deprecated_args() {
		$u1 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'status' => 'private' ) );

		// Create membership request
		groups_send_membership_request( $u1, $g1 );

		$request = groups_check_for_membership_request( $u1, $g1 );
		$this->assertTrue( is_numeric( $request ) && $request > 0 );
	}

	/**
	 * @group groups_accept_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_groups_accept_membership_request_by_membership_id() {
		$u1 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'status' => 'private' ) );

		// Create membership request
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g1
		) );

		// Get group invitations of any type, from any user in the group.
		$member = new BP_Groups_Member( $u1, $g1 );

		groups_accept_membership_request( false, $u1, $g1 );

		// User should now be a group member.
		$member = groups_is_user_member( $u1, $g1 );

		$this->assertTrue( is_numeric( $member ) && $member > 0 );
	}

	/**
	 * @group groups_accept_membership_request
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_groups_accept_membership_request_by_user_id_group_id() {
		$u1 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'status' => 'private' ) );

		// Create membership request
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g1
		) );

		groups_accept_membership_request( null, $u1, $g1 );

		// User should now be a group member
		$member = groups_is_user_member( $u1, $g1 );
		$this->assertTrue( is_numeric( $member ) && $member > 0 );
	}

	/**
	 * @group groups_send_invites
	 * @group group_invitations
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_groups_membership_request_plus_invite_equals_member() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'creator_id' => $u1, 'status' => 'private' ) );

		// Create membership request
		groups_send_membership_request( array(
			'user_id' => $u2,
			'group_id' => $g1
		) );

		// Create draft invitation
		groups_invite_user( array(
			'user_id'       => $u2,
			'group_id'      => $g1,
			'inviter_id'    => $u1,
			'date_modified' => bp_core_current_time(),
			'is_confirmed'  => 0,
			'send_invite'   => 1
		) );

		// User should now be a group member
		$member = groups_is_user_member( $u2, $g1 );
		$this->assertTrue( is_numeric( $member ) && $member > 0 );
	}

	/**
	 * @group groups_accept_all_pending_membership_requests
	 * @group group_membership_requests
	 * @group group_membership
	 */
	public function test_groups_accept_all_pending_membership_requests() {
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		$u3 = self::factory()->user->create();
		$g1 = self::factory()->group->create( array( 'status' => 'private' ) );

		// Create membership request
		groups_send_membership_request( array(
			'user_id' => $u1,
			'group_id' => $g1
		) );
		groups_send_membership_request( array(
			'user_id' => $u2,
			'group_id' => $g1
		) );
		groups_send_membership_request( array(
			'user_id' => $u3,
			'group_id' => $g1
		) );

		groups_accept_all_pending_membership_requests( $g1 );

		// All users should now be group members.
		$members = new BP_Group_Member_Query( array( 'group_id' => $g1 ) );
		$this->assertEqualSets( array( $u1, $u2, $u3 ), $members->user_ids );
	}

	/**
	 * @group total_group_count
	 * @ticket BP6813
	 */
	public function test_total_group_count_should_return_integer() {
		$this->assertIsInt( BP_Groups_Member::total_group_count( 123 ) );
	}

	/**
	 * @group get_memberships_by_id
	 */
	public function test_get_memberships_by_id_with_single_id() {
		$users = self::factory()->user->create_many( 2 );
		$groups = self::factory()->group->create_many( 2 );

		$m0 = $this->add_user_to_group( $users[0], $groups[0] );
		$m1 = $this->add_user_to_group( $users[1], $groups[1] );

		$found = BP_Groups_Member::get_memberships_by_id( $m0 );

		$this->assertSame( 1, count( $found ) );
		$this->assertEquals( $m0, $found[0]->id );
	}

	/**
	 * @group get_memberships_by_id
	 */
	public function test_get_memberships_by_id_with_multiple_ids() {
		$users = self::factory()->user->create_many( 2 );
		$groups = self::factory()->group->create_many( 2 );

		$m0 = $this->add_user_to_group( $users[0], $groups[0] );
		$m1 = $this->add_user_to_group( $users[1], $groups[1] );

		$found = BP_Groups_Member::get_memberships_by_id( array( $m0, $m1 ) );

		$this->assertSame( 2, count( $found ) );
		$this->assertEqualSets( array( $m0, $m1 ), wp_list_pluck( $found, 'id' ) );
	}

	/**
	 * @ticket BP7382
	 */
	public function test_user_property_should_be_accessible() {
		$user = self::factory()->user->create();
		$group = self::factory()->group->create();

		$this->add_user_to_group( $user, $group );

		$membership = new BP_Groups_Member( $user, $group );

		$user_obj = $membership->user;

		$this->assertInstanceOf( 'BP_Core_User', $user_obj );
		$this->assertEquals( $user, $user_obj->id );
	}

	/**
	 * @group get_group_moderator_ids
	 */
	public function test_groups_get_group_mods_bad_id() {
		$mods = groups_get_group_mods( null );

		$this->assertTrue( is_array( $mods ) && empty( $mods ) );
	}

	/**
	 * @group get_group_moderator_ids
	 */
	public function test_groups_get_group_admins_bad_id() {
		$admins = groups_get_group_admins( null );

		$this->assertTrue( is_array( $admins ) && empty( $admins ) );
	}

	/**
	 * @ticket BP7859
	 */
	public function test_get_user_memberships_type_membership() {
		groups_join_group( self::$group_ids[0], self::$user_ids[0] );

		$memberships = BP_Groups_Member::get_user_memberships( self::$user_ids[0], array(
			'type' => 'membership',
		) );

		$this->assertCount( 1, $memberships );
		$this->assertSame( self::$group_ids[0], $memberships[0]->group_id );
	}

	/**
	 * @ticket BP7476
	 */
	public function test_delete_all_for_user() {
		$new_user = self::factory()->user->create();

		$admin_users = get_users( array(
			'blog_id' => bp_get_root_blog_id(),
			'fields'  => 'id',
			'number'  => 1,
			'orderby' => 'ID',
			'role'    => 'administrator',
		) );

		$admin_user = (int) $admin_users[0];

		// Sole admin of group.
		$new_group = self::factory()->group->create( array(
			'creator_id' => $new_user,
		) );

		// One of two group admins.
		groups_join_group( self::$group_ids[0], $new_user );
		$m1 = new BP_Groups_Member( $new_user, self::$group_ids[0] );
		$m1->promote( 'admin' );

		// Not an admin.
		groups_join_group( self::$group_ids[1], $new_user );
		$m2 = new BP_Groups_Member( $new_user, self::$group_ids[1] );

		BP_Groups_Member::delete_all_for_user( $new_user );

		$new_group_members = BP_Groups_Member::get_group_administrator_ids( $new_group );
		$this->assertSame( array( $admin_user ), wp_list_pluck( $new_group_members, 'user_id' ) );

		$g0_members = BP_Groups_Member::get_group_administrator_ids( self::$group_ids[0] );
		$this->assertSame( array( self::$user_ids[3] ), wp_list_pluck( $g0_members, 'user_id' ) );

		$g1_members = BP_Groups_Member::get_group_administrator_ids( self::$group_ids[1] );
		$this->assertSame( array( self::$user_ids[3] ), wp_list_pluck( $g1_members, 'user_id' ) );
	}
}
