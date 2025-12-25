<?php
/**
 * REST API: GP_REST_Profile_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a profile via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Profile_Controller extends GP_REST_Controller {

	use GP_Profile_Helper;
	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'profile';
		parent::__construct();
	}

	/**
	 * Registers the routes for the user profile endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// profile/me .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_self_profile' ),
					'permission_callback' => array( $this, 'get_self_profile_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		// profile/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the user.', 'gp-rest' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Retrieves the profile of the currently authenticated user.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response Response object containing the user's profile data.
	 */
	public function get_self_profile( $request ) {
		$current_user_id = get_current_user_id();
		$request->set_param( 'id', $current_user_id );
		return $this->get_item( $request );
	}

	/**
	 * Retrieves a user's profile by ID.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response Response object containing the user's profile data.
	 */
	public function get_item( $request ) {
		$user_id = (int) $request->get_param( 'id' );
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return $this->response_404_user_not_found();
		}

		$data = $this->prepare_item_for_response( $user, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Permission check for getting the current user's profile.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool True if the user has permission, false otherwise.
	 */
	public function get_self_profile_permissions_check( $request ) {
		return is_user_logged_in();
	}

	/**
	 * Permission check for getting a user's profile by ID.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool True if the user has permission, false otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$requested_user_id = (int) $request->get_param( 'id' );
		$current_user_id   = get_current_user_id();

		// Users can only access their own profile.
		if ( $requested_user_id === $current_user_id ) {
			return true;
		}

		// Current user can edit other users.
		if ( current_user_can( 'edit_users' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Prepares a single user output for response.
	 *
	 * @param WP_User         $item    User object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$user = $item;

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( in_array( 'user_id', $fields, true ) ) {
			$data['user_id'] = $user->ID;
		}

		if ( in_array( 'user_display_name', $fields, true ) ) {
			$data['user_display_name'] = $user->display_name;
		}

		if ( in_array( 'user_registered', $fields, true ) ) {
			$data['user_registered'] = $user->user_registered;
		}

		if ( in_array( 'recent_projects', $fields, true ) ) {
			$data['recent_projects'] = $this->get_recent_translation_sets( $user, 5 );
		}

		if ( in_array( 'locales', $fields, true ) ) {
			$data['locales'] = $this->locales_known( $user );
		}

		if ( in_array( 'permissions', $fields, true ) ) {
			$data['permissions'] = $this->get_permissions( $user );
		}

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a user returned from the REST API.
		 * Allows modification of the user right before it is returned.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_User          $user     The original object.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_profile', $response, $user, $request );
	}

	/**
	 * Retrieves the user schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'user',
			'type'       => 'object',
			'properties' => array(
				'user_id'           => array(
					'description' => __( 'Unique identifier for the user.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'user_display_name' => array(
					'description' => __( 'The display name of the user.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'user_registered'   => array(
					'description' => __( 'The date the user registered.', 'gp-rest' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'recent_projects'   => array(
					'description' => __( 'Recent projects the user has contributed to.', 'gp-rest' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'items'       => array(
						'type' => 'object',
					),
				),
				'locales'           => array(
					'description' => __( 'Locales the user is familiar with.', 'gp-rest' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'items'       => array(
						'type' => 'array',
					),
				),
				'permissions'       => array(
					'description' => __( 'Permissions the user has.', 'gp-rest' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'items'       => array(
						'type' => 'object',
					),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
