<?php
/**
 * REST API: GP_REST_Projects_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Project;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a projects via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Projects_Controller extends GP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'projects';
		parent::__construct();
	}

	/**
	 * Registers the routes for the glossaries endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET projects with parent_project_id .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_projects' ),
					'permission_callback' => array( $this, 'get_projects_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// POST projects .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_project' ),
					'permission_callback' => array( $this, 'create_project_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET projects/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_project' ),
					'permission_callback' => array( $this, 'get_project_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// PUT projects/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'edit_project' ),
					'permission_callback' => array( $this, 'edit_project_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// DELETE projects/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_project' ),
					'permission_callback' => array( $this, 'delete_project_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Handles GET requests to /projects endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function get_projects( $request ) {
		$parent_project_id = absint( $request->get_param( 'parent_project_id' ) );
		if ( $parent_project_id ) {
			$parent_project = GP::$project->get( $parent_project_id );
			if ( ! $parent_project ) {
				return $this->response_404_project_not_found();
			}
			$projects = $parent_project->sub_projects();
		} else {
			$projects = GP::$project->top_level();
		}

		$data = array();
		foreach ( $projects as $project ) {
			$data[] = array(
				'id'                  => $project->id,
				'name'                => $project->name,
				'slug'                => $project->slug,
				'description'         => $project->description,
				'source_url_template' => $project->source_url_template,
				'parent_project_id'   => $project->parent_project_id,
				'active'              => $project->active,
			);
		}

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Handles POST requests to /projects endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function create_project( $request ) {
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_text_field( $request->get_param( 'slug' ) );
		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'project_missing_parameters',
					'message' => __( 'Name and slug are required.', 'gp-rest' ),
				),
				400
			);
		}

		$description         = sanitize_textarea_field( $request->get_param( 'description' ) );
		$source_url_template = esc_url_raw( $request->get_param( 'source_url_template' ) );

		$parent_project_id = absint( $request->get_param( 'parent_project_id' ) );
		if ( $parent_project_id ) {
			$parent_project = GP::$project->get( $parent_project_id );
			if ( ! $parent_project ) {
				return $this->response_404_project_not_found();
			}
		}

		// 0 or 1 expected.
		$active = absint( $request->get_param( 'active' ) );
		if ( 0 !== $active && 1 !== $active ) {
			$active = 1;
		}

		$data = array(
			'name'                => $name,
			'slug'                => $slug,
			'description'         => $description,
			'source_url_template' => $source_url_template,
			'parent_project_id'   => $parent_project_id,
			'active'              => $active,
		);

		$new_project = new GP_Project( $data );
		$project     = GP::$project->create_and_select( $new_project );

		if ( ! $project ) {
			return $this->response_500_project_creation_failed();
		}

		$response_data = array(
			'id'                  => $project->id,
			'name'                => $project->name,
			'slug'                => $project->slug,
			'description'         => $project->description,
			'source_url_template' => $project->source_url_template,
			'parent_project_id'   => $project->parent_project_id,
			'active'              => $project->active,
		);

		$response = new WP_REST_Response( $response_data, 201 );

		return $response;
	}

	/**
	 * Handles GET requests to /projects/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function get_project( $request ) {
		$project_id = absint( $request->get_param( 'id' ) );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		$response_data = array(
			'id'                  => $project->id,
			'name'                => $project->name,
			'slug'                => $project->slug,
			'description'         => $project->description,
			'source_url_template' => $project->source_url_template,
			'parent_project_id'   => $project->parent_project_id,
			'active'              => $project->active,
		);

		$response = new WP_REST_Response( $response_data, 200 );

		return $response;
	}

	/**
	 * Handles PUT requests to /projects/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function edit_project( $request ) {
		$project_id = absint( $request->get_param( 'id' ) );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_text_field( $request->get_param( 'slug' ) );
		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'project_missing_parameters',
					'message' => __( 'Name and slug are required.', 'gp-rest' ),
				),
				400
			);
		}

		$description         = sanitize_textarea_field( $request->get_param( 'description' ) );
		$source_url_template = esc_url_raw( $request->get_param( 'source_url_template' ) );

		$parent_project_id = absint( $request->get_param( 'parent_project_id' ) );
		if ( $parent_project_id ) {
			$parent_project = GP::$project->get( $parent_project_id );
			if ( ! $parent_project ) {
				return $this->response_404_project_not_found();
			}
		}

		// 0 or 1 expected.
		$active = absint( $request->get_param( 'active' ) );
		if ( 0 !== $active && 1 !== $active ) {
			$active = 1;
		}

		$data = array(
			'name'                => $name,
			'slug'                => $slug,
			'description'         => $description,
			'source_url_template' => $source_url_template,
			'parent_project_id'   => $parent_project_id,
			'active'              => $active,
		);

		$updated = GP::$project->update( $data, array( 'id' => $project_id ) );
		if ( ! $updated ) {
			return $this->response_500_project_update_failed();
		}

		$updated_project = GP::$project->get( $project_id );

		$response_data = array(
			'id'                  => $updated_project->id,
			'name'                => $updated_project->name,
			'slug'                => $updated_project->slug,
			'description'         => $updated_project->description,
			'source_url_template' => $updated_project->source_url_template,
			'parent_project_id'   => $updated_project->parent_project_id,
			'active'              => $updated_project->active,
		);

		$response = new WP_REST_Response( $response_data, 200 );

		return $response;
	}

	/**
	 * Handles DELETE requests to /projects/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function delete_project( $request ) {
		$project_id = absint( $request->get_param( 'id' ) );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		GP::$project->id = $project_id;
		$deleted         = GP::$project->delete();
		if ( ! $deleted ) {
			return $this->response_500_project_deletion_failed();
		}

		$response = new WP_REST_Response( null, 204 );

		return $response;
	}

	/**
	 * Permission check for retrieving projects.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_projects_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a project.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_project_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for retrieving a project.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_project_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for editing a project.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function edit_project_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for deleting a project.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_project_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}
}
