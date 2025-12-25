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
	 * Registers the routes for the projects endpoint.
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
					'args'                => array(
						'parent_project_id' => array(
							'description'       => __( 'Filter projects by parent project ID.', 'gp-rest' ),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'required'          => false,
						),
					),
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
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
					'args'                => array(),
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
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
					'args'                => array(),
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
			$item   = $this->prepare_item_for_response( $project, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );

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

		$data = $this->prepare_item_for_response( $project, $request );

		$response = rest_ensure_response( $data );
		$response->set_status( 201 );

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

		$data = $this->prepare_item_for_response( $project, $request );

		$response = rest_ensure_response( $data );

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

		$data = $this->prepare_item_for_response( $updated_project, $request );

		$response = rest_ensure_response( $data );

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
		$parent_project_id = absint( $request->get_param( 'parent_project_id' ) );

		return $this->current_user_can( 'write', 'project', $parent_project_id );
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
		$project_id = absint( $request->get_param( 'id' ) );

		return $this->current_user_can( 'write', 'project', $project_id );
	}

	/**
	 * Permission check for deleting a project.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_project_permissions_check( $request ) {
		$project_id = absint( $request->get_param( 'id' ) );

		return $this->current_user_can( 'delete', 'project', $project_id );
	}

	/**
	 * Prepares a single project output for response.
	 *
	 * @param GP_Project      $item    Project object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$project = $item;

		$data = array(
			'id'                  => $project->id,
			'name'                => $project->name,
			'slug'                => $project->slug,
			'description'         => $project->description,
			'source_url_template' => $project->source_url_template,
			'parent_project_id'   => $project->parent_project_id,
			'active'              => $project->active,
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a project returned from the REST API.
		 * Allows modification of the project right before it is returned.
		 *
		 * @param WP_REST_Response  $response The response object.
		 * @param GP_Project        $project   The original object.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_project', $response, $project, $request );
	}

	/**
	 * Retrieves the project schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'project',
			'type'       => 'object',
			'properties' => array(
				'id'                  => array(
					'description' => __( 'Unique identifier for the project.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'name'                => array(
					'description' => __( 'The name of the project.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'slug'                => array(
					'description' => __( 'The slug of the project.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'description'         => array(
					'description' => __( 'The description of the project.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'source_url_template' => array(
					'description' => __( 'The source URL template of the project.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'parent_project_id'   => array(
					'description' => __( 'The parent project ID.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'active'              => array(
					'description' => __( 'Whether the project is active.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
