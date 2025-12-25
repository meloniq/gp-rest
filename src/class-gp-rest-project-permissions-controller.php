<?php
/**
 * REST API: GP_REST_Project_Permissions_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Locales;
use GP_Validator_Permission;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a projects via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Project_Permissions_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'projects';
		parent::__construct();
	}

	/**
	 * Registers the routes for the project permissions endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		// GET projects/{id}/permissions .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/permissions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_project_permissions' ),
					'permission_callback' => array( $this, 'get_project_permissions_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// POST projects/{id}/permissions .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/permissions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_project_permission' ),
					'permission_callback' => array( $this, 'create_project_permission_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET projects/{id}/permissions/{permission_id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/permissions/(?P<permission_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_project_permission' ),
					'permission_callback' => array( $this, 'get_project_permission_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// DELETE projects/{id}/permissions/{permission_id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/permissions/(?P<permission_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_project_permission' ),
					'permission_callback' => array( $this, 'delete_project_permission_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Handles GET requests to /projects/{id}/permissions endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_project_permissions( WP_REST_Request $request ) {
		$project_id = (int) $request->get_param( 'id' );
		if ( ! $project_id ) {
			return $this->response_404_project_not_found();
		}

		$permissions = GP::$validator_permission->by_project_id( $project_id );

		$data = array();
		foreach ( $permissions as $permission ) {
			$item   = $this->prepare_item_for_response( $permission, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles POST requests to /projects/{id}/permissions endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function create_project_permission( WP_REST_Request $request ) {
		$project_id = (int) $request->get_param( 'id' );
		if ( ! $project_id ) {
			return $this->response_404_project_not_found();
		}

		$user_login = $request->get_param( 'user_login' );
		$user       = get_user_by( 'login', $user_login );
		if ( ! $user ) {
			return $this->response_404_user_not_found();
		}

		$locale_slug = sanitize_text_field( $request->get_param( 'locale_slug' ) );
		$locale      = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			return $this->response_404_locale_not_found();
		}

		$set_slug        = sanitize_text_field( $request->get_param( 'set_slug' ) );
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project_id, $set_slug, $locale_slug );
		if ( ! $translation_set ) {
			return $this->response_404_translation_set_not_found();
		}

		// Todo: Check if permission already exists?

		$data = array(
			'user_id'     => $user->ID,
			'project_id'  => $project_id,
			'locale_slug' => $locale_slug,
			'set_slug'    => $set_slug,
			'action'      => 'approve',
		);

		$permission = GP::$validator_permission->create_and_select( $data );
		if ( ! $permission ) {
			return $this->response_500_project_permission_creation_failed();
		}

		$data = $this->prepare_item_for_response( $permission, $request );

		$response = rest_ensure_response( $data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handles GET requests to /projects/{id}/permissions/{permission_id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_project_permission( WP_REST_Request $request ) {
		$project_id = (int) $request->get_param( 'id' );
		if ( ! $project_id ) {
			return $this->response_404_project_not_found();
		}

		$permission_id = (int) $request->get_param( 'permission_id' );
		if ( ! $permission_id ) {
			return $this->response_404_project_permission_not_found();
		}

		$permission = GP::$validator_permission->get( $permission_id );
		if ( ! $permission || (int) $permission->project_id !== $project_id ) {
			return $this->response_404_project_permission_not_found();
		}

		$data = $this->prepare_item_for_response( $permission, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles DELETE requests to /projects/{id}/permissions/{permission_id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_project_permission( WP_REST_Request $request ) {
		$project_id = (int) $request->get_param( 'id' );
		if ( ! $project_id ) {
			return $this->response_404_project_not_found();
		}

		$permission_id = (int) $request->get_param( 'permission_id' );
		if ( ! $permission_id ) {
			return $this->response_404_project_permission_not_found();
		}

		$permission = GP::$validator_permission->get( $permission_id );
		if ( ! $permission ) {
			return $this->response_404_project_permission_not_found();
		}

		if ( (int) $permission->project_id !== $project_id ) {
			return $this->response_404_project_permission_not_found();
		}

		GP::$validator_permission->id = $permission_id;
		$deleted                      = GP::$validator_permission->delete();
		if ( ! $deleted ) {
			return $this->response_500_project_permission_deletion_failed();
		}

		$response = new WP_REST_Response( null, 204 );

		return $response;
	}

	/**
	 * Permission check for getting project permissions.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_project_permissions_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new project permission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_project_permission_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		// $can_edit = $this->can( 'approve', 'translation-set', $translation_set->id );.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for retrieving a project permission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_project_permission_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for deleting a project permission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_project_permission_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Prepares a single project permission output for response.
	 *
	 * @param GP_Validator_Permission $item    Project permission object.
	 * @param WP_REST_Request         $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$permission = $item;

		$data = array(
			'id'          => $permission->id,
			'user_id'     => $permission->user_id,
			'project_id'  => $permission->project_id,
			'action'      => $permission->action,
			'locale_slug' => $permission->locale_slug,
			'set_slug'    => $permission->set_slug,
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a project permission returned from the REST API.
		 * Allows modification of the project permission right before it is returned.
		 *
		 * @param WP_REST_Response        $response The response object.
		 * @param GP_Validator_Permission $permission   The original object.
		 * @param WP_REST_Request         $request  Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_project_permission', $response, $permission, $request );
	}
}
