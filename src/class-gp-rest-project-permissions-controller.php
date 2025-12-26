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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(),
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
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
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
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(),
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
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(),
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
	public function get_items( $request ) {
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

		$total_items = count( $data );
		$response->header( 'X-WP-Total', $total_items );

		return $response;
	}

	/**
	 * Handles POST requests to /projects/{id}/permissions endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function create_item( $request ) {
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
	public function get_item( $request ) {
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
	public function delete_item( $request ) {
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
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new project permission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		$project_id = absint( $request->get_param( 'id' ) );

		return $this->current_user_can( 'write', 'project', $project_id );
	}

	/**
	 * Permission check for retrieving a project permission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for deleting a project permission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		$project_id = absint( $request->get_param( 'id' ) );

		return $this->current_user_can( 'write', 'project', $project_id );
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

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( in_array( 'id', $fields, true ) ) {
			$data['id'] = $permission->id;
		}

		if ( in_array( 'user_id', $fields, true ) ) {
			$data['user_id'] = $permission->user_id;
		}

		if ( in_array( 'project_id', $fields, true ) ) {
			$data['project_id'] = $permission->project_id;
		}

		if ( in_array( 'action', $fields, true ) ) {
			$data['action'] = $permission->action;
		}

		if ( in_array( 'locale_slug', $fields, true ) ) {
			$data['locale_slug'] = $permission->locale_slug;
		}

		if ( in_array( 'set_slug', $fields, true ) ) {
			$data['set_slug'] = $permission->set_slug;
		}

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

	/**
	 * Retrieves the project permission schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'project_permission',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the project permission.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'user_id'     => array(
					'description' => __( 'User ID associated with the permission.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'project_id'  => array(
					'description' => __( 'Project ID associated with the permission.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'action'      => array(
					'description' => __( 'Action permitted (e.g., approve).', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'locale_slug' => array(
					'description' => __( 'Locale slug associated with the permission.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'set_slug'    => array(
					'description' => __( 'Translation set slug associated with the permission.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
