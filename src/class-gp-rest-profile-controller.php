<?php
/**
 * REST API: GP_REST_Profile_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use WP_User;
use WP_Error;
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
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// profile/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
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
			return new WP_Error( 'gp_rest_user_not_found', __( 'User not found.', 'gp-rest' ), array( 'status' => 404 ) );
		}

		$recent_projects = $this->get_recent_translation_sets( $user, 5 );
		$locales         = $this->locales_known( $user );
		$permissions     = $this->get_permissions( $user );

		$data     = array(
			'user_id'           => $user->ID,
			'user_display_name' => $user->display_name,
			'user_registered'   => $user->user_registered,
			'recent_projects'   => $recent_projects, // Placeholder for projects the user contributes to.
			'locales'           => $locales, // Placeholder for project locales the user contributes to.
			'permissions'       => $permissions, // Placeholder for projects the user validates.
		);
		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}
}
