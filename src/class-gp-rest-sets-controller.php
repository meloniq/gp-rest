<?php
/**
 * REST API: GP_REST_Sets_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Locales;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a sets via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Sets_Controller extends GP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'sets';
		parent::__construct();
	}

	/**
	 * Registers the routes for the glossaries endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET sets with project_id .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sets' ),
					'permission_callback' => array( $this, 'get_sets_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// POST sets .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_set' ),
					'permission_callback' => array( $this, 'create_set_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET sets/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_set' ),
					'permission_callback' => array( $this, 'get_set_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// PUT sets/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'edit_set' ),
					'permission_callback' => array( $this, 'edit_set_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// DELETE sets/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_set' ),
					'permission_callback' => array( $this, 'delete_set_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Handles GET requests to /sets endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function get_sets( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		if ( ! $project_id ) {
			return new WP_REST_Response(
				array(
					'code'    => 'sets_missing_project_id',
					'message' => __( 'Project ID is required.', 'gp-rest' ),
				),
				400
			);
		}

		$params = array(
			'project_id' => $project_id,
		);
		$sets   = GP::$translation_set->find( $params );

		$data = array();
		foreach ( $sets as $set ) {
			$data[] = array(
				'id'         => $set->id,
				'project_id' => $set->project_id,
				'locale'     => $set->locale,
				'name'       => $set->name,
				'slug'       => $set->slug,
			);
		}

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles POST requests to /sets endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function create_set( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_invalid_project',
					'message' => __( 'Invalid project ID.', 'gp-rest' ),
				),
				400
			);
		}

		$locale     = sanitize_text_field( $request->get_param( 'locale' ) );
		$locale_obj = GP_Locales::by_slug( $locale );
		if ( ! $locale_obj ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_invalid_locale',
					'message' => __( 'Invalid locale.', 'gp-rest' ),
				),
				400
			);
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_text_field( $request->get_param( 'slug' ) );
		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_missing_parameters',
					'message' => __( 'Name and slug are required.', 'gp-rest' ),
				),
				400
			);
		}

		// Check for existing set with same project and locale.
		$params = array(
			'project_id' => $project_id,
			'locale'     => $locale,
			'slug'       => $slug,
		);
		if ( GP::$translation_set->find_one( $params ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_exists',
					'message' => __( 'A translation set with the same project, locale, and slug already exists.', 'gp-rest' ),
				),
				400
			);
		}

		$data = array(
			'project_id' => $project_id,
			'locale'     => $locale,
			'name'       => $name,
			'slug'       => $slug,
		);
		$set  = GP::$translation_set->create( $data );

		if ( ! $set ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_creation_failed',
					'message' => __( 'Failed to create translation set.', 'gp-rest' ),
				),
				500
			);
		}

		$response_data = array(
			'id'         => $set->id,
			'project_id' => $set->project_id,
			'locale'     => $set->locale,
			'name'       => $set->name,
			'slug'       => $set->slug,
		);

		$response = new WP_REST_Response( $response_data, 201 );

		return $response;
	}

	/**
	 * Handles GET requests to /sets/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_set( $request ) {
		$set_id = absint( $request->get_param( 'id' ) );
		$set    = GP::$translation_set->get( $set_id );
		if ( ! $set ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_not_found',
					'message' => __( 'Translation set not found.', 'gp-rest' ),
				),
				404
			);
		}

		$data = array(
			'id'         => $set->id,
			'project_id' => $set->project_id,
			'locale'     => $set->locale,
			'name'       => $set->name,
			'slug'       => $set->slug,
		);

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles PUT requests to /sets/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function edit_set( $request ) {
		$set_id = absint( $request->get_param( 'id' ) );
		$set    = GP::$translation_set->get( $set_id );
		if ( ! $set ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_not_found',
					'message' => __( 'Translation set not found.', 'gp-rest' ),
				),
				404
			);
		}

		$project_id = absint( $request->get_param( 'project_id' ) );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_invalid_project',
					'message' => __( 'Invalid project ID.', 'gp-rest' ),
				),
				400
			);
		}

		$locale     = sanitize_text_field( $request->get_param( 'locale' ) );
		$locale_obj = GP_Locales::by_slug( $locale );
		if ( ! $locale_obj ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_invalid_locale',
					'message' => __( 'Invalid locale.', 'gp-rest' ),
				),
				400
			);
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_text_field( $request->get_param( 'slug' ) );
		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_missing_parameters',
					'message' => __( 'Name and slug are required.', 'gp-rest' ),
				),
				400
			);
		}

		$data = array(
			'project_id' => $project_id,
			'locale'     => $locale,
			'name'       => $name,
			'slug'       => $slug,
		);

		$updated_set = GP::$translation_set->update( $data, array( 'id' => $set_id ) );
		if ( ! $updated_set ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_update_failed',
					'message' => __( 'Failed to update translation set.', 'gp-rest' ),
				),
				500
			);
		}

		$response_data = array(
			'id'         => $updated_set->id,
			'project_id' => $updated_set->project_id,
			'locale'     => $updated_set->locale,
			'name'       => $updated_set->name,
			'slug'       => $updated_set->slug,
		);

		$response = new WP_REST_Response( $response_data, 200 );

		return $response;
	}

	/**
	 * Handles DELETE requests to /sets/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_set( $request ) {
		$set_id = absint( $request->get_param( 'id' ) );
		$set    = GP::$translation_set->get( $set_id );
		if ( ! $set ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_not_found',
					'message' => __( 'Translation set not found.', 'gp-rest' ),
				),
				404
			);
		}

		// Set set ID and delete.
		GP::$translation_set->id = $set_id;
		$deleted                 = GP::$translation_set->delete();
		if ( ! $deleted ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_deletion_failed',
					'message' => __( 'Failed to delete translation set.', 'gp-rest' ),
				),
				500
			);
		}

		$response = new WP_REST_Response( null, 204 );

		return $response;
	}

	/**
	 * Permission check for retrieving sets.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_sets_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_set_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for retrieving a set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_set_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for editing a set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function edit_set_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for deleting a set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_set_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}
}
