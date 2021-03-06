<?php

/**
 * Forums BuddyBoss Component Class
 *
 * Forums and BuddyBoss are designed to connect together seamlessly and
 * invisibly, and this is the hunk of code necessary to make that happen.
 *
 * The code in this BuddyBoss Extension does some pretty complicated stuff,
 * far outside the realm of the simplicity Forums is traditionally known for.
 *
 * While the rest of Forums serves as an example of how to write pretty, simple
 * code, what's in these files is pure madness. It should not be used as an
 * example of anything other than successfully juggling chainsaws and puppy-dogs.
 *
 * @package BuddyBoss\Forums
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BBP_Forums_Component' ) ) :
	/**
	 * Loads Forums Component
	 *
	 * @since bbPress (r3552)
	 */
	class BP_Forums_Component extends BP_Component {

		/**
		 * Start the forums component creation process
		 *
		 * @since bbPress (r3552)
		 */
		public function __construct() {
			parent::start(
				'forums',
				__( 'Forums', 'buddyboss' ),
				buddypress()->plugin_dir
			);
			// $this->includes();
			// $this->setup_globals();
			// $this->setup_actions();
			// $this->fully_loaded();
		}

		/**
		 * Include BuddyBoss classes and functions
		 */
		public function includes( $includes = array() ) {
			$includes = $includes ?: array();

			// Helper BuddyBoss functions
			$includes[] = 'admin.php';

			// Helper BuddyBoss functions
			$includes[] = 'functions.php';

			// Members modifications
			$includes[] = 'members.php';

			// BuddyBoss Notfications Extension functions
			if ( bp_is_active( 'notifications' ) ) {
				$includes[] = 'notifications.php';
			}

			// BuddyBoss Activity Extension class
			if ( bp_is_active( 'activity' ) ) {
				$includes[] = 'activity.php';
			}

			// BuddyBoss Group Extension class
			if ( bp_is_active( 'groups' ) ) {
				$includes[] = 'groups.php';
			}

			parent::includes( $includes );
		}

		/**
		 * Setup globals
		 *
		 * The BP_FORUMS_SLUG constant is deprecated, and only used here for
		 * backwards compatibility.
		 *
		 * @since bbPress (r3552)
		 */
		public function setup_globals( $args = array() ) {
			$bp = buddypress();

			// Define the parent forum ID
			if ( ! defined( 'BP_FORUMS_PARENT_FORUM_ID' ) ) {
				define( 'BP_FORUMS_PARENT_FORUM_ID', 1 );
			}

			// Define a slug, if necessary
			if ( ! defined( 'BP_FORUMS_SLUG' ) ) {
				define( 'BP_FORUMS_SLUG', $this->id );
			}

			// All arguments for forums component
			$args = array(
				'path'          => BP_PLUGIN_DIR,
				'slug'          => bp_get_option( '_bbp_root_slug', BP_FORUMS_SLUG ),
				'root_slug'     => isset( $bp->pages->forums->slug ) ? $bp->pages->forums->slug : BP_FORUMS_SLUG,
				'has_directory' => false,
				'search_string' => __( 'Search Forums&hellip;', 'buddyboss' ),
			);

			parent::setup_globals( $args );
		}

		/**
		 * Setup the actions
		 *
		 * @since bbPress (r3395)
		 * @access private
		 * @uses add_filter() To add various filters
		 * @uses add_action() To add various actions
		 * @link http://bbpress.trac.wordpress.org/ticket/2176
		 */
		public function setup_actions() {

			// Setup the components
			add_action( 'bp_init', array( $this, 'setup_components' ), 7 );

			parent::setup_actions();
		}

		/**
		 * Instantiate classes for BuddyBoss integration
		 *
		 * @since bbPress (r3395)
		 */
		public function setup_components() {

			// Always load the members component
			bbpress()->extend->buddypress->members = new BBP_BuddyPress_Members();

			// Create new activity class
			if ( bp_is_active( 'activity' ) ) {
				bbpress()->extend->buddypress->activity = new BBP_BuddyPress_Activity();
			}

			// Register the group extension only if groups are active
			if ( bbp_is_group_forums_active() && bp_is_active( 'groups' ) ) {

				/**
				 * need to remove this hooks before group extension because
				 * it was checking for access to that post before wp handles the post id assign
				 */
				if ( bp_is_group() ) {
					remove_action( 'bbp_template_redirect', 'bbp_check_forum_edit', 10 );
					remove_action( 'bbp_template_redirect', 'bbp_check_topic_edit', 10 );
					remove_action( 'bbp_template_redirect', 'bbp_check_reply_edit', 10 );
				}

				bp_register_group_extension( 'BBP_Forums_Group_Extension' );
			}
		}

		/**
		 * Allow the variables, actions, and filters to be modified by third party
		 * plugins and themes.
		 *
		 * @since bbPress (r3902)
		 */
		private function fully_loaded() {
			do_action_ref_array( 'bbp_buddypress_loaded', array( $this ) );
		}

		/**
		 * Setup BuddyBar navigation
		 *
		 * @since bbPress (r3552)
		 */
		public function setup_nav( $main_nav = array(), $sub_nav = array() ) {

			// Stop if there is no user displayed or logged in
			if ( ! is_user_logged_in() && ! bp_displayed_user_id() ) {
				return;
			}

			// Define local variable(s)
			$user_domain = '';

			// Add 'Forums' to the main navigation
			$main_nav = array(
				'name'                => __( 'Forums', 'buddyboss' ),
				'slug'                => $this->slug,
				'position'            => 80,
				'screen_function'     => 'bbp_member_forums_screen_topics',
				'default_subnav_slug' => bbp_get_topic_archive_slug(),
				'item_css_id'         => $this->id,
			);

			// Determine user to use
			if ( bp_displayed_user_id() ) {
				$user_domain = bp_displayed_user_domain();
			} elseif ( bp_loggedin_user_domain() ) {
				$user_domain = bp_loggedin_user_domain();
			} else {
				return;
			}

			// User link
			$forums_link = trailingslashit( $user_domain . $this->slug );

			// Topics started
			$sub_nav[] = array(
				'name'            => ( bp_loggedin_user_id() === bp_displayed_user_id() ? __( 'My Discussions', 'buddyboss' ) : __( 'Discussions', 'buddyboss' ) ),
				'slug'            => bbp_get_topic_archive_slug(),
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_topics',
				'position'        => 20,
				'item_css_id'     => 'topics',
			);

			// Replies to topics
			$sub_nav[] = array(
				'name'            => ( bp_loggedin_user_id() === bp_displayed_user_id() ? __( 'My Replies', 'buddyboss' ) : __( 'Replies', 'buddyboss' ) ),
				'slug'            => bbp_get_reply_archive_slug(),
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_replies',
				'position'        => 40,
				'item_css_id'     => 'replies',
			);

			// Favorite topics
			$sub_nav[] = array(
				'name'            => ( bp_loggedin_user_id() === bp_displayed_user_id() ? __( 'My Favorites', 'buddyboss' ) : __( 'Favorites', 'buddyboss' ) ),
				'slug'            => bbp_get_user_favorites_slug(),
				'parent_url'      => $forums_link,
				'parent_slug'     => $this->slug,
				'screen_function' => 'bbp_member_forums_screen_favorites',
				'position'        => 60,
				'item_css_id'     => 'favorites',
			);

			// Subscribed topics (my profile only)
			if ( bp_is_my_profile() ) {
				$sub_nav[] = array(
					'name'            => __( 'Subscriptions', 'buddyboss' ),
					'slug'            => bbp_get_user_subscriptions_slug(),
					'parent_url'      => $forums_link,
					'parent_slug'     => $this->slug,
					'screen_function' => 'bbp_member_forums_screen_subscriptions',
					'position'        => 60,
					'item_css_id'     => 'subscriptions',
				);
			}

			parent::setup_nav( $main_nav, $sub_nav );
		}

		/**
		 * Set up the admin bar
		 *
		 * @since bbPress (r3552)
		 */
		public function setup_admin_bar( $wp_admin_nav = array() ) {

			// Menus for logged in user
			if ( is_user_logged_in() ) {

				// Setup the logged in user variables
				$user_domain = bp_loggedin_user_domain();
				$forums_link = trailingslashit( $user_domain . $this->slug );

				// Add the "My Account" sub menus
				$wp_admin_nav[] = array(
					'parent' => buddypress()->my_account_menu_id,
					'id'     => 'my-account-' . $this->id,
					'title'  => __( 'Forums', 'buddyboss' ),
					'href'   => trailingslashit( $forums_link ),
				);

				// Topics
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-topics',
					'title'  => __( 'My Discussions', 'buddyboss' ),
					'href'   => trailingslashit( $forums_link . bbp_get_topic_archive_slug() ),
				);

				// Replies
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-replies',
					'title'  => __( 'My Replies', 'buddyboss' ),
					'href'   => trailingslashit( $forums_link . bbp_get_reply_archive_slug() ),
				);

				// Favorites
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-favorites',
					'title'  => __( 'My Favorites', 'buddyboss' ),
					'href'   => trailingslashit( $forums_link . bbp_get_user_favorites_slug() ),
				);

				// Subscriptions
				$wp_admin_nav[] = array(
					'parent' => 'my-account-' . $this->id,
					'id'     => 'my-account-' . $this->id . '-subscriptions',
					'title'  => __( 'Subscriptions', 'buddyboss' ),
					'href'   => trailingslashit( $forums_link . bbp_get_user_subscriptions_slug() ),
				);
			}

			parent::setup_admin_bar( $wp_admin_nav );
		}

		/**
		 * Sets up the title for pages and <title>
		 *
		 * @since bbPress (r3552)
		 */
		public function setup_title() {
			$bp = buddypress();

			// Adjust title based on view
			if ( bp_is_forums_component() ) {
				if ( bp_is_my_profile() ) {
					$bp->bp_options_title = __( 'Forums', 'buddyboss' );
				} elseif ( bp_is_user() ) {
					$bp->bp_options_avatar = bp_core_fetch_avatar(
						array(
							'item_id' => bp_displayed_user_id(),
							'type'    => 'thumb',
						)
					);
					$bp->bp_options_title  = bp_get_displayed_user_fullname();
				}
			}

			parent::setup_title();
		}
	}
endif;
