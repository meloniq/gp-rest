<?php
/**
 * REST API: GP_REST_Originals_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Original;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a originals via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Originals_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'originals';
		parent::__construct();
	}

	/**
	 * Registers the routes for the originals endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET originals .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'project_id' => array(
							'description'       => __( 'The ID of the project to retrieve originals for.', 'gp-rest' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET originals/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		// DELETE originals/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
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
	 * Handles GET requests to /originals endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function get_items( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		if ( ! $project_id ) {
			return $this->response_404_project_not_found();
		}

		$originals = GP::$original->by_project_id( $project_id );

		$data = array();
		foreach ( $originals as $original ) {
			$item   = $this->prepare_item_for_response( $original, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles GET requests to /originals/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_item( $request ) {
		$original_id = absint( $request->get_param( 'id' ) );
		$original    = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $this->response_404_original_not_found();
		}

		$data = $this->prepare_item_for_response( $original, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles DELETE requests to /originals/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_item( $request ) {
		$original_id = absint( $request->get_param( 'id' ) );
		$original    = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $this->response_404_translation_original_not_found();
		}

		// Set original ID and delete.
		GP::$original->id = $original_id;
		$deleted          = GP::$original->delete();
		if ( ! $deleted ) {
			return $this->response_500_original_deletion_failed();
		}

		$response = new WP_REST_Response( null, 204 );

		return $response;
	}

	/**
	 * Permission check for retrieving originals.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for retrieving a original.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for deleting a original.
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
	 * Prepares a single original output for response.
	 *
	 * @param GP_Original     $item    Original object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$original = $item;

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( in_array( 'id', $fields, true ) ) {
			$data['id'] = $original->id;
		}

		if ( in_array( 'project_id', $fields, true ) ) {
			$data['project_id'] = $original->project_id;
		}

		if ( in_array( 'context', $fields, true ) ) {
			$data['context'] = $original->context;
		}

		if ( in_array( 'singular', $fields, true ) ) {
			$data['singular'] = $original->singular;
		}

		if ( in_array( 'plural', $fields, true ) ) {
			$data['plural'] = $original->plural;
		}

		if ( in_array( 'comment', $fields, true ) ) {
			$data['comment'] = $original->comment;
		}

		if ( in_array( 'references', $fields, true ) ) {
			$data['references'] = $original->references;
		}

		if ( in_array( 'status', $fields, true ) ) {
			$data['status'] = $original->status;
		}

		if ( in_array( 'priority', $fields, true ) ) {
			$data['priority'] = $original->priority;
		}

		if ( in_array( 'date_added', $fields, true ) ) {
			$data['date_added'] = mysql_to_rfc3339( $original->date_added );
		}

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a original returned from the REST API.
		 * Allows modification of the original right before it is returned.
		 *
		 * @param WP_REST_Response  $response The response object.
		 * @param GP_Original       $original The original object.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_original', $response, $original, $request );
	}

	/**
	 * Retrieves the original schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'original',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'description' => __( 'Unique identifier for the original.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'project_id' => array(
					'description' => __( 'The ID of the project this original belongs to.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'context'    => array(
					'description' => __( 'The context of the original.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'singular'   => array(
					'description' => __( 'The singular text of the original.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'plural'     => array(
					'description' => __( 'The plural text of the original, if applicable.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'comment'    => array(
					'description' => __( 'The comment associated with the original.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'references' => array(
					'description' => __( 'References for the original.', 'gp-rest' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'status'     => array(
					'description' => __( 'The status of the original.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'priority'   => array(
					'description' => __( 'The priority of the original.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'date_added' => array(
					'description' => __( 'The date the original was added.', 'gp-rest' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
