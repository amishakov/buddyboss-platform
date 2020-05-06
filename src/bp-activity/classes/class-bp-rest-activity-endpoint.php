<?php
/**
 * BP REST: BP_REST_Activity_Endpoint class
 *
 * @package BuddyPress
 * @since 1.3.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity endpoints.
 *
 * @since 1.3.5
 */
class BP_REST_Activity_Endpoint extends WP_REST_Controller {

	/**
	 * User favorites.
	 *
	 * @since 1.3.5
	 *
	 * @var array|null
	 */
	protected $user_favorites = null;

	/**
	 * Constructor.
	 *
	 * @since 1.3.5
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->activity->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 1.3.5
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		$activity_endpoint = '/' . $this->rest_base . '/(?P<id>[\d]+)';

		register_rest_route(
			$this->namespace,
			$activity_endpoint,
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'A unique numeric ID for the activity.', 'buddyboss' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// Register the favorite route.
		register_rest_route(
			$this->namespace,
			$activity_endpoint . '/favorite',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'A unique numeric ID for the activity.', 'buddyboss' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_favorite' ),
					'permission_callback' => array( $this, 'update_favorite_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve activities.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 1.3.5
	 *
	 * @api            {GET} /wp-json/buddyboss/v1/activity Get Activities
	 * @apiName        GetBBActivities
	 * @apiGroup       Activity
	 * @apiDescription Retrieve activities
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} [page] Current page of the collection.
	 * @apiParam {Number} [per_page=10] Maximum number of items to be returned in result set.
	 * @apiParam {String} [search] Limit results to those matching a string.
	 * @apiParam {Array} [exclude] Ensure result set excludes specific IDs.
	 * @apiParam {Array} [include] Ensure result set includes specific IDs.
	 * @apiParam {Array=asc,desc} [order=desc] Ensure result set includes specific IDs.
	 * @apiParam {String} [after] Limit result set to items published after a given ISO8601 compliant date.
	 * @apiParam {Number} [user_id] Limit result set to items created by a specific user (ID).
	 * @apiParam {String=ham_only,spam_only,all} [status=ham_only] Limit result set to items with a specific status.
	 * @apiParam {String=just-me,friends,groups,favorites,mentions,following} [scope] Limit result set to items with a specific scope.
	 * @apiParam {Number} [group_id] Limit result set to items created by a specific group.
	 * @apiParam {Number} [site_id] Limit result set to items created by a specific site.
	 * @apiParam {Number} [primary_id] Limit result set to items with a specific prime association ID.
	 * @apiParam {Number} [secondary_id] Limit result set to items with a specific secondary association ID.
	 * @apiParam {String} [component] Limit result set to items with a specific active component.
	 * @apiParam {String} [type] Limit result set to items with a specific activity type.
	 * @apiParam {String=stream,threaded,false} [display_comments=false] No comments by default, stream for within stream display, threaded for below each activity item.
	 * @apiParam {Array=public,loggedin,onlyme,friends,media} [privacy=public] Privacy of the activity.
	 */
	public function get_items( $request ) {
		$args = array(
			'exclude'           => $request['exclude'],
			'in'                => $request['include'],
			'page'              => $request['page'],
			'per_page'          => $request['per_page'],
			'search_terms'      => $request['search'],
			'sort'              => $request['order'],
			'spam'              => $request['status'],
			'display_comments'  => $request['display_comments'],
			'site_id'           => $request['site_id'],
			'group_id'          => $request['group_id'],
			'scope'             => $request['scope'],
			'privacy'           => ( is_array( $request['privacy'] ) ? $request['privacy'] : (array) $request['privacy'] ),
			'count_total'       => true,
			'fields'            => 'all',
			'show_hidden'       => false,
			'update_meta_cache' => true,
			'filter'            => false,
		);

		if ( empty( $args['display_comments'] ) || 'false' === $args['display_comments'] ) {
			$args['display_comments'] = false;
		}

		if ( empty( $request['exclude'] ) ) {
			$args['exclude'] = false;
		}

		if ( empty( $request['include'] ) ) {
			$args['in'] = false;
		}

		if ( isset( $request['after'] ) ) {
			$args['since'] = $request['after'];
		}

		if ( isset( $request['user_id'] ) ) {
			$args['filter']['user_id'] = $request['user_id'];
		}

		$item_id = 0;
		if ( ! empty( $args['group_id'] ) ) {
			$args['filter']['object']     = 'groups';
			$args['filter']['primary_id'] = $args['group_id'];

			$item_id = $args['group_id'];
		}

		if ( ! empty( $args['site_id'] ) ) {
			$args['filter']['object']     = 'blogs';
			$args['filter']['primary_id'] = $args['site_id'];

			$item_id = $args['site_id'];
		}

		if ( empty( $args['group_id'] ) && empty( $args['site_id'] ) ) {
			if ( isset( $request['component'] ) ) {
				$args['filter']['object'] = $request['component'];
			}

			if ( ! empty( $request['primary_id'] ) ) {
				$item_id                      = $request['primary_id'];
				$args['filter']['primary_id'] = $item_id;
			}
		}

		if ( empty( $request['scope'] ) ) {
			$args['scope'] = false;
		}

		if ( isset( $request['type'] ) ) {
			$args['filter']['action'] = $request['type'];
		}

		if ( ! empty( $request['secondary_id'] ) ) {
			$args['filter']['secondary_id'] = $request['secondary_id'];
		}

		if ( $args['in'] ) {
			$args['count_total'] = false;
		}

		if ( $this->show_hidden( $request['component'], $item_id ) ) {
			$args['show_hidden'] = true;
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		$args = apply_filters( 'bp_rest_activity_get_items_query_args', $args, $request );

		// Actually, query it.
		$activities = bp_activity_get( $args );

		$retval = array();
		foreach ( $activities['activities'] as $activity ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $activities['total'], $args['per_page'] );

		/**
		 * Fires after a list of activities is fetched via the REST API.
		 *
		 * @param array            $activities Fetched activities.
		 * @param WP_REST_Response $response   The response data.
		 * @param WP_REST_Request  $request    The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		do_action( 'bp_rest_activity_get_items', $activities, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to activity items.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return bool|WP_Error
	 * @since 1.3.5
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filter the activity `get_items` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_get_items_permissions_check', true, $request );
	}

	/**
	 * Retrieve an activity.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 * @since 1.3.5
	 *
	 * @api            {GET} /wp-json/buddyboss/v1/activity/:id Get Activity
	 * @apiName        GetBBActivity
	 * @apiGroup       Activity
	 * @apiDescription Retrieve single activity
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} id A unique numeric ID for the activity.
	 */
	public function get_item( $request ) {
		$activity = $this->get_activity_object( $request );

		if ( empty( $activity->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid activity ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is fetched via the REST API.
		 *
		 * @param BP_Activity_Activity $activity Fetched activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		do_action( 'bp_rest_activity_get_item', $activity, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get information about a specific activity.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return bool|WP_Error
	 * @since 1.3.5
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;

		if ( ! $this->can_see( $request ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you cannot view the activities.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the activity `get_item` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Create an activity.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 1.3.5
	 *
	 * @api            {POST} /wp-json/buddyboss/v1/activity Create activity
	 * @apiName        CreateBBActivity
	 * @apiGroup       Activity
	 * @apiDescription Create activity
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} primary_item_id The ID of some other object primarily associated with this one.
	 * @apiParam {Number} secondary_item_id The ID of some other object also associated with this one.
	 * @apiParam {Number} user_id The ID for the author of the activity.
	 * @apiParam {String} link The permalink to this activity on the site.
	 * @apiParam {String=settings,notifications,groups,forums,activity,media,messages,friends,invites,search,members,xprofile,blogs} component The active component the activity relates to.
	 * @apiParam {String=new_member,new_avatar,updated_profile,activity_update,created_group,joined_group,group_details_updated,bbp_topic_create,bbp_reply_create,activity_comment,friendship_accepted,friendship_created,new_blog_post,new_blog_comment} type The activity type of the activity.
	 * @apiParam {String} content Allowed HTML content for the activity.
	 * @apiParam {String} date The date the activity was published, in the site's timezone.
	 * @apiParam {Boolean=true,false} hidden Whether the activity object should be sitewide hidden or not.
	 * @apiParam {string=public,loggedin,onlyme,friends,media} [privacy=public] Privacy of the activity.
	 * @apiParam {Array} [bp_media_ids] Media specific IDs when Media component is enable.
	 * @apiParam {Array} [media_gif] Save gif data into activity when Media component is enable. param(url,mp4)
	 */
	public function create_item( $request ) {
		$request->set_param( 'context', 'edit' );

		if ( empty( $request['content'] ) ) {
			return new WP_Error(
				'bp_rest_create_activity_empty_content',
				__( 'Please, enter some content.', 'buddyboss' ),
				array(
					'status' => 500,
				)
			);
		}

		$prepared_activity = $this->prepare_item_for_database( $request );

		// Fallback for the activity_update type.
		$type = 'activity_update';
		if ( ! empty( $request['type'] ) ) {
			$type = $request['type'];
		}

		$prime       = $request['primary_item_id'];
		$activity_id = 0;

		// Post a regular activity update.
		if ( 'activity_update' === $type ) {
			if ( bp_is_active( 'groups' ) && ! is_null( $prime ) ) {
				$activity_id = groups_post_update( $prepared_activity );
			} else {
				$activity_id = bp_activity_post_update( $prepared_activity );
			}

			// Post an activity comment.
		} elseif ( 'activity_comment' === $type ) {

			// ID of the root activity item.
			if ( isset( $prime ) ) {
				$prepared_activity->activity_id = (int) $prime;
			}

			// ID of a parent comment.
			if ( isset( $request['secondary_item_id'] ) ) {
				$prepared_activity->parent_id = (int) $request['secondary_item_id'];
			}

			$activity_id = bp_activity_new_comment( $prepared_activity );

			// Otherwise add an activity.
		} else {
			$activity_id = bp_activity_add( $prepared_activity );
		}

		if ( ! is_numeric( $activity_id ) ) {
			return new WP_Error(
				'bp_rest_user_cannot_create_activity',
				__( 'Cannot create new activity.', 'buddyboss' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity = bp_activity_get(
			array(
				'in'               => $activity_id,
				'display_comments' => 'stream',
				'show_hidden'      => $request['hidden'],
			)
		);

		$activity      = current( $activity['activities'] );
		$fields_update = $this->update_additional_fields_for_object( $activity, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after an activity item is created via the REST API.
		 *
		 * @param BP_Activity_Activity $activity The created activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		do_action( 'bp_rest_activity_create_item', $activity, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to create an activity.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error
	 * @since 1.3.5
	 */
	public function create_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to create activities.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$item_id   = $request['primary_item_id'];
		$component = $request['component'];

		if ( true === $retval && bp_is_active( 'groups' ) && buddypress()->groups->id === $component && ! is_null( $item_id ) ) {
			if ( ! $this->show_hidden( $component, $item_id ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to create activities.', 'buddyboss' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		/**
		 * Filter the activity `create_item` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Update an activity.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 1.3.5
	 *
	 * @api            {PATCH} /wp-json/buddyboss/v1/activity/:id Update activity
	 * @apiName        UpdateBBActivity
	 * @apiGroup       Activity
	 * @apiDescription Update single activity
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} id A unique numeric ID for the activity.
	 * @apiParam {Number} [primary_item_id] The ID of some other object primarily associated with this one.
	 * @apiParam {Number} [secondary_item_id] The ID of some other object also associated with this one.
	 * @apiParam {Number} [user_id] The ID for the author of the activity.
	 * @apiParam {string} [link] The permalink to this activity on the site.
	 * @apiParam {String=settings,notifications,groups,forums,activity,media,messages,friends,invites,search,members,xprofile,blogs} [component] The active component the activity relates to.
	 * @apiParam {String=new_member,new_avatar,updated_profile,activity_update,created_group,joined_group,group_details_updated,bbp_topic_create,bbp_reply_create,activity_comment,friendship_accepted,friendship_created,new_blog_post,new_blog_comment} [type] The activity type of the activity.
	 * @apiParam {String} [content] Allowed HTML content for the activity.
	 * @apiParam {String} [date] The date the activity was published, in the site's timezone.
	 * @apiParam {Boolean=true,false} [hidden] Whether the activity object should be sitewide hidden or not.
	 * @apiParam {string=public,loggedin,onlyme,friends,media} [privacy=public] Privacy of the activity.
	 * @apiParam {Array} [bp_media_ids] Media specific IDs when Media component is enable.
	 * @apiParam {Array} [media_gif] Save gif data into activity when Media component is enable. param(url,mp4)
	 */
	public function update_item( $request ) {
		$request->set_param( 'context', 'edit' );

		if ( empty( $request['content'] ) ) {
			return new WP_Error(
				'bp_rest_update_activity_empty_content',
				__( 'Please, enter some content.', 'buddyboss' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity_id = bp_activity_add( $this->prepare_item_for_database( $request ) );

		if ( ! is_numeric( $activity_id ) ) {
			return new WP_Error(
				'bp_rest_user_cannot_update_activity',
				__( 'Cannot update existing activity.', 'buddyboss' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity      = $this->get_activity_object( $activity_id );
		$fields_update = $this->update_additional_fields_for_object( $activity, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is updated via the REST API.
		 *
		 * @param BP_Activity_Activity $activity The updated activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		do_action( 'bp_rest_activity_update_item', $activity, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update an activity.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error
	 * @since 1.3.5
	 */
	public function update_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to update this activity.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$activity = $this->get_activity_object( $request );

		if ( true === $retval && empty( $activity->id ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid activity ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! bp_activity_user_can_delete( $activity ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to update this activity.', 'buddyboss' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Filter the activity `update_item` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete activity.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 * @since 1.3.5
	 *
	 * @api            {DELETE} /wp-json/buddyboss/v1/activity/:id Delete activity
	 * @apiName        DeleteBBActivity
	 * @apiGroup       Activity
	 * @apiDescription Delete single activity
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} id A unique numeric ID for the activity.
	 */
	public function delete_item( $request ) {
		// Setting context.
		$request->set_param( 'context', 'edit' );

		// Get the activity before it's deleted.
		$activity = $this->get_activity_object( $request );
		$previous = $this->prepare_item_for_response( $activity, $request );

		if ( 'activity_comment' === $activity->type ) {
			$retval = bp_activity_delete_comment( $activity->item_id, $activity->id );
		} else {
			$retval = bp_activity_delete(
				array(
					'id' => $activity->id,
				)
			);
		}

		if ( ! $retval ) {
			return new WP_Error(
				'bp_rest_activity_cannot_delete',
				__( 'Could not delete the activity.', 'buddyboss' ),
				array(
					'status' => 500,
				)
			);
		}

		// Build the response.
		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		/**
		 * Fires after an activity is deleted via the REST API.
		 *
		 * @param BP_Activity_Activity $activity The deleted activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		do_action( 'bp_rest_activity_delete_item', $activity, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete an activity.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error
	 * @since 1.3.5
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this activity.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$activity = $this->get_activity_object( $request );

		if ( true === $retval && empty( $activity->id ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid activity ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! bp_activity_user_can_delete( $activity ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this activity.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the activity `delete_item` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Gets the current user's favorites.
	 *
	 * @return array Array of activity IDs.
	 * @since 1.3.5
	 */
	public function get_user_favorites() {
		if ( null === $this->user_favorites ) {
			if ( is_user_logged_in() ) {
				$user_favorites       = bp_activity_get_user_favorites( get_current_user_id() );
				$this->user_favorites = array_filter( wp_parse_id_list( $user_favorites ) );
			} else {
				$this->user_favorites = array();
			}
		}

		return $this->user_favorites;
	}

	/**
	 * Adds or removes the activity from the current user's favorites.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response | WP_Error
	 *
	 * @since 1.3.5
	 *
	 * @api            {PATCH} /wp-json/buddyboss/v1/activity/:id/favorite Activity favorite
	 * @apiName        UpdateBBActivityFavorite
	 * @apiGroup       Activity
	 * @apiDescription Make activity favorite/unfavorite
	 * @apiVersion     1.0.0
	 * @apiPermission  LoggedInUser
	 * @apiParam {Number} id A unique numeric ID for the activity
	 */
	public function update_favorite( $request ) {
		$activity = $this->get_activity_object( $request );

		if ( empty( $activity->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid activity ID.', 'buddyboss' ),
				array(
					'status' => 404,
				)
			);
		}

		$user_id = get_current_user_id();

		$result = false;
		if ( in_array( $activity->id, $this->get_user_favorites(), true ) ) {
			$result  = bp_activity_remove_user_favorite( $activity->id, $user_id );
			$message = __( 'Sorry, you cannot remove the activity from your favorites.', 'buddyboss' );

			// Update the user favorites, removing the activity ID.
			$this->user_favorites = array_diff( $this->get_user_favorites(), array( $activity->id ) );
		} else {
			$result  = bp_activity_add_user_favorite( $activity->id, $user_id );
			$message = __( 'Sorry, you cannot add the activity to your favorites.', 'buddyboss' );

			// Update the user favorites, adding the activity ID.
			$this->user_favorites[] = (int) $activity->id;
		}

		if ( ! $result ) {
			return new WP_Error(
				'bp_rest_user_cannot_update_activity_favorite',
				$message,
				array(
					'status' => 500,
				)
			);
		}

		// Setting context.
		$request->set_param( 'context', 'edit' );

		// Prepare the response now the user favorites has been updated.
		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after user favorited activities has been updated via the REST API.
		 *
		 * @param BP_Activity_Activity $activity       The updated activity.
		 * @param array                $user_favorites The updated user favorites.
		 * @param WP_REST_Response     $response       The response data.
		 * @param WP_REST_Request      $request        The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		do_action( 'bp_rest_activity_update_favorite', $activity, $this->get_user_favorites(), $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update user favorites.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool|WP_Error
	 * @since 1.3.5
	 */
	public function update_favorite_permissions_check( $request ) {
		$retval = true;

		if ( ! ( is_user_logged_in() && bp_activity_can_favorite() ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to update favorites.', 'buddyboss' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the activity `update_favorite` permissions check.
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_update_favorite_permissions_check', $retval, $request );
	}

	/**
	 * Renders the content of an activity.
	 *
	 * @param BP_Activity_Activity $activity Activity data.
	 *
	 * @return string The rendered activity content.
	 * @since 1.3.5
	 */
	public function render_item( $activity ) {
		$rendered = '';

		if ( empty( $activity->content ) ) {
			return $rendered;
		}

		// Do not truncate activities.
		add_filter( 'bp_activity_maybe_truncate_entry', '__return_false' );

		if ( 'activity_comment' === $activity->type ) {
			$rendered = apply_filters( 'bp_get_activity_content', $activity->content );
		} else {
			$activities_template = null;

			if ( isset( $GLOBALS['activities_template'] ) ) {
				$activities_template = $GLOBALS['activities_template'];
			}

			// Set the `activities_template` global for the current activity.
			$GLOBALS['activities_template']           = new stdClass();
			$GLOBALS['activities_template']->activity = $activity;

			// Set up activity oEmbed cache.
			bp_activity_embed();

			$rendered = apply_filters_ref_array(
				'bp_get_activity_content_body',
				array(
					$activity->content,
					&$activity,
				)
			);

			// Restore the `activities_template` global.
			$GLOBALS['activities_template'] = $activities_template;
		}

		// Restore the filter to truncate activities.
		remove_filter( 'bp_activity_maybe_truncate_entry', '__return_false' );

		return $rendered;
	}

	/**
	 * Prepares activity data for return as an object.
	 *
	 * @param BP_Activity_Activity $activity Activity data.
	 * @param WP_REST_Request      $request  Full details about the request.
	 *
	 * @return WP_REST_Response
	 * @since 1.3.5
	 */
	public function prepare_item_for_response( $activity, $request ) {
		$top_level_parent_id = 'activity_comment' === $activity->type ? $activity->item_id : 0;
		global $activities_template;
		$activities_template                            = new \stdClass();
		$activities_template->disable_blogforum_replies = (bool) bp_core_get_root_option( 'bp-disable-blogforum-comments' );
		$activities_template->activity                  = $activity;

		$data = array(
			'user_id'           => $activity->user_id,
			'name'              => bp_core_get_user_displayname( $activity->user_id ),
			'component'         => $activity->component,
			'content'           => array(
				'raw'      => $activity->content,
				'rendered' => $this->render_item( $activity ),
			),
			'date'              => bp_rest_prepare_date_response( $activity->date_recorded ),
			'id'                => $activity->id,
			'link'              => bp_activity_get_permalink( $activity->id ),
			'primary_item_id'   => $activity->item_id,
			'secondary_item_id' => $activity->secondary_item_id,
			'status'            => $activity->is_spam ? 'spam' : 'published',
			'title'             => $activity->action,
			'type'              => $activity->type,
			'favorited'         => in_array( $activity->id, $this->get_user_favorites(), true ),

			// extend response.
			'can_favorite'      => bp_activity_can_favorite(),
			'favorite_count'    => $this->get_activity_favorite_count( $activity->id ),
			'can_comment'       => ( 'activity_comment' === $activity->type ) ? bp_activity_can_comment_reply( $activity ) : bp_activity_can_comment(),
			'can_delete'        => bp_activity_user_can_delete( $activity ),
			'content_stripped'  => html_entity_decode( wp_strip_all_tags( $activity->content ) ),
			'privacy'           => ( isset( $activity->privacy ) ? $activity->privacy : false ),
		);

		// Get item schema.
		$schema = $this->get_item_schema();

		// Get comments (count).
		if ( ! empty( $activity->children ) ) {
			$comment_count         = wp_filter_object_list( $activity->children, array( 'type' => 'activity_comment' ), 'AND', 'id' );
			$data['comment_count'] = ! empty( $comment_count ) ? count( $comment_count ) : 0;

			if ( ! empty( $schema['properties']['comments'] ) && 'threaded' === $request['display_comments'] ) {
				$data['comments'] = $this->prepare_activity_comments( $activity->children, $request );
			}
		} else {
			$activities            = BP_Activity_Activity::get_activity_comments( $activity->id, $activity->mptt_left, $activity->mptt_right, $request['status'], $top_level_parent_id );
			$data['comment_count'] = ! empty( $activities ) ? count( $activities ) : 0;
		}

		if ( ! empty( $schema['properties']['user_avatar'] ) ) {
			$data['user_avatar'] = array(
				'full'  => bp_core_fetch_avatar(
					array(
						'item_id' => $activity->user_id,
						'html'    => false,
						'type'    => 'full',
					)
				),

				'thumb' => bp_core_fetch_avatar(
					array(
						'item_id' => $activity->user_id,
						'html'    => false,
					)
				),
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $activity ) );

		/**
		 * Filter an activity value returned from the API.
		 *
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  Request used to generate the response.
		 * @param BP_Activity_Activity $activity The activity object.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_prepare_value', $response, $request, $activity );
	}

	/**
	 * Prepare activity comments.
	 *
	 * @param array           $comments Comments.
	 * @param WP_REST_Request $request  Full details about the request.
	 *
	 * @return array           An array of activity comments.
	 * @since 1.3.5
	 */
	protected function prepare_activity_comments( $comments, $request ) {
		$data = array();

		if ( empty( $comments ) ) {
			return $data;
		}

		foreach ( $comments as $comment ) {
			$data[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $comment, $request )
			);
		}

		/**
		 * Filter activity comments returned from the API.
		 *
		 * @param array           $data     An array of activity comments.
		 * @param array           $comments Comments.
		 * @param WP_REST_Request $request  Request used to generate the response.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_prepare_comments', $data, $comments, $request );
	}

	/**
	 * Prepare an activity for create or update.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return stdClass|WP_Error Object or WP_Error.
	 * @since 1.3.5
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_activity = new stdClass();
		$schema            = $this->get_item_schema();
		$activity          = $this->get_activity_object( $request );

		if ( ! empty( $schema['properties']['id'] ) && ! empty( $activity->id ) ) {
			$prepared_activity->id = $activity->id;

			if ( 'activity_comment' !== $request['type'] ) {
				$prepared_activity->error_type = 'wp_error';
			}
		}

		// Activity author ID.
		if ( ! empty( $schema['properties']['user_id'] ) && isset( $request['user_id'] ) ) {
			$prepared_activity->user_id = (int) $request['user_id'];
		} else {
			$prepared_activity->user_id = get_current_user_id();
		}

		// Activity component.
		if ( ! empty( $schema['properties']['component'] ) && isset( $request['component'] ) ) {
			$prepared_activity->component = $request['component'];
		} else {
			$prepared_activity->component = buddypress()->activity->id;
		}

		// Activity Item ID.
		if ( ! empty( $schema['properties']['primary_item_id'] ) && isset( $request['primary_item_id'] ) ) {
			$item_id = (int) $request['primary_item_id'];

			// Set the group ID of the activity.
			if ( bp_is_active( 'groups' ) && isset( $prepared_activity->component ) && buddypress()->groups->id === $prepared_activity->component ) {
				$prepared_activity->group_id = $item_id;

				// Use a generic item ID for other components.
			} else {
				$prepared_activity->item_id = $item_id;
			}
		}

		// Secondary Item ID.
		if ( ! empty( $schema['properties']['secondary_item_id'] ) && isset( $request['secondary_item_id'] ) ) {
			$prepared_activity->secondary_item_id = (int) $request['secondary_item_id'];
		}

		// Activity type.
		if ( ! empty( $schema['properties']['type'] ) && isset( $request['type'] ) ) {
			$prepared_activity->type = $request['type'];
		}

		// Activity content.
		if ( ! empty( $schema['properties']['content'] ) && isset( $request['content'] ) ) {
			if ( is_string( $request['content'] ) ) {
				$prepared_activity->content = $request['content'];
			} elseif ( isset( $request['content']['raw'] ) ) {
				$prepared_activity->content = $request['content']['raw'];
			}
		}

		// Activity Sitewide visibility.
		if ( ! empty( $schema['properties']['hidden'] ) && isset( $request['hidden'] ) ) {
			$prepared_activity->hide_sitewide = (bool) $request['hidden'];
		}

		// Activity Privacy.
		if ( ! empty( $schema['properties']['privacy'] ) && isset( $request['privacy'] ) ) {
			$prepared_activity->privacy = $request['privacy'];
		}

		/**
		 * Filters an activity before it is inserted or updated via the REST API.
		 *
		 * @param stdClass        $prepared_activity An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request           Request object.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_pre_insert_value', $prepared_activity, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param BP_Activity_Activity $activity Activity object.
	 *
	 * @return array
	 * @since 1.3.5
	 */
	protected function prepare_links( $activity ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $activity->id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $activity->user_id ) ),
				'embeddable' => true,
			),
		);

		if ( 'activity_comment' === $activity->type ) {
			$links['up'] = array(
				'href' => rest_url( $url ),
			);
		}

		if ( bp_activity_can_favorite() ) {
			$links['favorite'] = array(
				'href' => rest_url( $url . '/favorite' ),
			);
		}

		if ( bp_is_active( 'groups' ) && 'groups' === $activity->component && ! empty( $activity->item_id ) ) {
			$group = groups_get_group( $activity->item_id );

			$links['group'] = array(
				'href'       => bp_get_group_permalink( $group ),
				'embeddable' => true,
			);
		}

		/**
		 * Filter links prepared for the REST response.
		 *
		 * @param array                $links    The prepared links of the REST response.
		 * @param BP_Activity_Activity $activity Activity object.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( 'bp_rest_activity_prepare_links', $links, $activity );
	}

	/**
	 * Can this user see the activity?
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return boolean
	 * @since 1.3.5
	 */
	protected function can_see( $request ) {
		return bp_activity_user_can_read(
			$this->get_activity_object( $request ),
			bp_loggedin_user_id()
		);
	}

	/**
	 * Show hidden activity?
	 *
	 * @param string $component The activity component.
	 * @param int    $item_id   The activity item ID.
	 *
	 * @return boolean
	 * @since 1.3.5
	 */
	protected function show_hidden( $component, $item_id ) {
		$user_id = get_current_user_id();
		$retval  = false;

		if ( ! is_null( $component ) ) {
			// If activity is from a group, do an extra cap check.
			if ( ! $retval && ! empty( $item_id ) && bp_is_active( $component ) && buddypress()->groups->id === $component ) {
				// Group admins and mods have access as well.
				if ( groups_is_user_admin( $user_id, $item_id ) || groups_is_user_mod( $user_id, $item_id ) ) {
					$retval = true;

					// User is a member of the group.
				} elseif ( (bool) groups_is_user_member( $user_id, $item_id ) ) {
					$retval = true;
				}
			}
		}

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		return (bool) $retval;
	}

	/**
	 * Get activity object.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return BP_Activity_Activity|string An activity object.
	 * @since 1.3.5
	 */
	public function get_activity_object( $request ) {
		$activity_id = is_numeric( $request ) ? $request : (int) $request['id'];

		$activity = bp_activity_get_specific(
			array(
				'activity_ids'     => array( $activity_id ),
				'display_comments' => true,
			)
		);

		if ( is_array( $activity ) && ! empty( $activity['activities'][0] ) ) {
			return $activity['activities'][0];
		}

		return '';
	}

	/**
	 * Edit the type of the some properties for the CREATABLE & EDITABLE methods.
	 *
	 * @param string $method Optional. HTTP method of the request.
	 *
	 * @return array Endpoint arguments.
	 * @since 1.3.5
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		$args = WP_REST_Controller::get_endpoint_args_for_item_schema( $method );
		$key  = 'get_item';

		if ( WP_REST_Server::CREATABLE === $method || WP_REST_Server::EDITABLE === $method ) {
			$key                     = 'create_item';
			$args['content']['type'] = 'string';
			unset( $args['content']['properties'] );

			if ( WP_REST_Server::EDITABLE === $method ) {
				$key                      = 'update_item';
				$args['type']['required'] = true;
			}
		} elseif ( WP_REST_Server::DELETABLE === $method ) {
			$key = 'delete_item';
		}

		/**
		 * Filters the method query arguments.
		 *
		 * @param array  $args   Query arguments.
		 * @param string $method HTTP method of the request.
		 *
		 * @since 1.3.5
		 */
		return apply_filters( "bp_rest_activity_{$key}_query_arguments", $args, $method );
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @return array
	 * @since 1.3.5
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_activity',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the activity.', 'buddyboss' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'primary_item_id'   => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The ID of some other object primarily associated with this one.', 'buddyboss' ),
					'type'        => 'integer',
				),
				'secondary_item_id' => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'buddyboss' ),
					'type'        => 'integer',
				),
				'user_id'           => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The ID for the author of the activity.', 'buddyboss' ),
					'type'        => 'integer',
				),
				'name'              => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'User\'s display name for the activity.', 'buddyboss' ),
					'type'        => 'string',
				),
				'link'              => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The permalink to this activity on the site.', 'buddyboss' ),
					'format'      => 'uri',
					'type'        => 'string',
				),
				'component'         => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The active BuddyPress component the activity relates to.', 'buddyboss' ),
					'type'        => 'string',
					'enum'        => array_keys( buddypress()->active_components ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
				'type'              => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The activity type of the activity.', 'buddyboss' ),
					'type'        => 'string',
					'enum'        => array_keys( bp_activity_get_types() ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
				'title'             => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'The description of the activity\'s type (eg: Username posted an update)', 'buddyboss' ),
					'type'        => 'string',
					'readonly'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'content'           => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Allowed HTML content for the activity.', 'buddyboss' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null,
						// Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null,
						// Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Content for the activity, as it exists in the database.', 'buddyboss' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML content for the activity, transformed for display.', 'buddyboss' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'date'              => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( "The date the activity was published, in the site's timezone.", 'buddyboss' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'status'            => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Whether the activity has been marked as spam or not.', 'buddyboss' ),
					'type'        => 'string',
					'enum'        => array( 'published', 'spam' ),
					'readonly'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
				'comments'          => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'A list of objects children of the activity object.', 'buddyboss' ),
					'type'        => 'array',
					'readonly'    => true,
				),
				'comment_count'     => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Total number of comments of the activity object.', 'buddyboss' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'hidden'            => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Whether the activity object should be sitewide hidden or not.', 'buddyboss' ),
					'type'        => 'boolean',
				),
				'favorited'         => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Whether the activity object has been favorited by the current user.', 'buddyboss' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'can_favorite'      => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Whether or not user have the favorite access for the activity object.', 'buddyboss' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'favorite_count'    => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Favorite count for the activity object.', 'buddyboss' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'can_comment'       => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Whether or not user have the comment access for the activity object.', 'buddyboss' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'comment_count'     => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Comment count for the activity object.', 'buddyboss' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'can_delete'        => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Whether or not user have the delete access for the activity object.', 'buddyboss' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'content_stripped'  => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Content for the activity without HTML tags.', 'buddyboss' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'privacy'           => array(
					'context'     => array( 'embed', 'view', 'edit' ),
					'description' => __( 'Privacy of the activity.', 'buddyboss' ),
					'type'        => 'string',
					'enum'        => array( 'public', 'loggedin', 'onlyme', 'friends', 'media' ),
				),
			),
		);

		// Avatars.
		if ( true === buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				'context'     => array( 'view', 'edit' ),
				/* translators: 1: Full avatar width in pixels. 2: Full avatar height in pixels */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'buddyboss' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
			);

			$avatar_properties['thumb'] = array(
				'context'     => array( 'view', 'edit' ),
				/* translators: 1: Thumb avatar width in pixels. 2: Thumb avatar height in pixels */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'buddyboss' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
			);

			$schema['properties']['user_avatar'] = array(
				'context'     => array( 'view', 'edit' ),
				'description' => __( 'Avatar URLs for the author of the activity.', 'buddyboss' ),
				'type'        => 'object',
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		/**
		 * Filters the activity schema.
		 *
		 * @param string $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_activity_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for collections of plugins.
	 *
	 * @return array
	 * @since 1.3.5
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddyboss' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes specific IDs.', 'buddyboss' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddyboss' ),
			'default'           => 'desc',
			'type'              => 'string',
			'enum'              => array( 'asc', 'desc' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['after'] = array(
			'description'       => __( 'Limit result set to items published after a given ISO8601 compliant date.', 'buddyboss' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit result set to items created by a specific user (ID).', 'buddyboss' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['status'] = array(
			'description'       => __( 'Limit result set to items with a specific status.', 'buddyboss' ),
			'default'           => 'ham_only',
			'type'              => 'string',
			'enum'              => array( 'ham_only', 'spam_only', 'all' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['scope'] = array(
			'description'       => __( 'Limit result set to items with a specific scope.', 'buddyboss' ),
			'type'              => 'string',
			'enum'              => array( 'just-me', 'friends', 'groups', 'favorites', 'mentions', 'following' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['group_id'] = array(
			'description'       => __( 'Limit result set to items created by a specific group.', 'buddyboss' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['site_id'] = array(
			'description'       => __( 'Limit result set to items created by a specific site.', 'buddyboss' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['primary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific prime association ID.', 'buddyboss' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['secondary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific secondary association ID.', 'buddyboss' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['component'] = array(
			'description'       => __( 'Limit result set to items with a specific active BuddyPress component.', 'buddyboss' ),
			'type'              => 'string',
			'enum'              => array_keys( buddypress()->active_components ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Limit result set to items with a specific activity type.', 'buddyboss' ),
			'type'              => 'string',
			'enum'              => array_keys( bp_activity_get_types() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['display_comments'] = array(
			'description'       => __( 'No comments by default, stream for within stream display, threaded for below each activity item.', 'buddyboss' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['privacy'] = array(
			'description'       => __( 'Privacy of the activity.', 'buddyboss' ),
			'type'              => 'array',
			'default'           => array( 'public' ),
			'items'             => array(
				'type' => 'string',
				'enum' => array( 'public', 'loggedin', 'onlyme', 'friends', 'media' ),
			),
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_activity_collection_params', $params );
	}

	/**
	 * Get favorite count for activity.
	 *
	 * @param int $activity_id The activity id.
	 *
	 * @return int|mixed
	 */
	public function get_activity_favorite_count( $activity_id ) {

		if ( empty( $activity_id ) ) {
			return;
		}

		$fav_count = bp_activity_get_meta( $activity_id, 'favorite_count', true );

		return ( ! empty( $fav_count ) ? $fav_count : 0 );
	}
}