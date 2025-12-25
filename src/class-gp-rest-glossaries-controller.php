<?php
/**
 * REST API: GP_REST_Glossaries_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Glossary;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a glossaries via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Glossaries_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'glossaries';
		parent::__construct();
	}

	/**
	 * Registers the routes for the glossaries endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET glossaries .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		// POST glossaries .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		// GET glossaries/{id} .
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

		// PUT glossaries/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);

		// DELETE glossaries/{id} .
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
	 * Handles GET requests to /glossaries endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_items( $request ) {
		$glossaries = GP::$glossary->all();

		$data = array();

		foreach ( $glossaries as $glossary ) {
			$item   = $this->prepare_item_for_response( $glossary, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles POST requests to /glossaries endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function create_item( $request ) {
		// required translation_set_id parameter.
		$translation_set_id = absint( $request->get_param( 'translation_set_id' ) );

		$translation_set = GP::$translation_set->get( $translation_set_id );
		if ( ! $translation_set ) {
			return $this->response_404_translation_set_not_found();
		}

		// get Glossary by translation_set_id to ensure uniqueness.
		$existing_glossary = GP::$glossary->find( array( 'translation_set_id' => $translation_set_id ) );
		if ( ! empty( $existing_glossary ) ) {
			return $this->response_409_glossary_already_exists();
		}

		// optional description parameter.
		$description = (string) $request->get_param( 'description' );
		if ( ! empty( $description ) ) {
			$description = wp_kses_post( $description );
		}

		$glossary = GP::$glossary->create(
			array(
				'translation_set_id' => $translation_set_id,
				'description'        => $description,
			)
		);

		if ( ! $glossary ) {
			return $this->response_500_glossary_creation_failed();
		}

		$data = $this->prepare_item_for_response( $glossary, $request );

		$response = rest_ensure_response( $data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handles GET requests to /glossaries/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_item( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		$data = $this->prepare_item_for_response( $glossary, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles PUT requests to /glossaries/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function update_item( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		// translation_set_id parameter.
		$translation_set_id = $glossary->translation_set_id;

		// optional description parameter.
		$description = (string) $request->get_param( 'description' );
		if ( ! empty( $description ) ) {
			$description = wp_kses_post( $description );
		}

		GP::$glossary->id = $glossary_id;
		$updated          = GP::$glossary->update(
			array(
				'translation_set_id' => $translation_set_id,
				'description'        => $description,
			)
		);

		if ( ! $updated ) {
			return $this->response_500_glossary_update_failed();
		}

		$glossary = GP::$glossary->get( $glossary_id );

		$data = $this->prepare_item_for_response( $glossary, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles DELETE requests to /glossaries/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_item( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return $this->response_404_glossary_not_found();
		}

		// Set glossary ID and delete.
		GP::$glossary->id = $glossary_id;
		$deleted          = GP::$glossary->delete();
		if ( ! $deleted ) {
			return $this->response_500_glossary_deletion_failed();
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Permission check for retrieving glossaries.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		$translation_set_id = absint( $request->get_param( 'translation_set_id' ) );

		return $this->current_user_can( 'approve', 'translation-set', $translation_set_id );
	}

	/**
	 * Permission check for retrieving a glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for editing a glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return false;
		}

		return $this->current_user_can( 'approve', 'translation-set', $glossary->translation_set_id );
	}

	/**
	 * Permission check for deleting a glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		$glossary_id = absint( $request->get_param( 'id' ) );
		$glossary    = GP::$glossary->get( $glossary_id );
		if ( ! $glossary ) {
			return false;
		}

		return $this->current_user_can( 'delete', 'translation-set', $glossary->translation_set_id );
	}

	/**
	 * Prepares a single glossary output for response.
	 *
	 * @param GP_Glossary     $item    Glossary object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$glossary = $item;

		$data = array(
			'id'                 => $glossary->id,
			'translation_set_id' => $glossary->translation_set_id,
			'description'        => $glossary->description,
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a glossary returned from the REST API.
		 * Allows modification of the glossary right before it is returned.
		 *
		 * @param WP_REST_Response  $response The response object.
		 * @param GP_Glossary       $glossary The original object.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_glossary', $response, $glossary, $request );
	}

	/**
	 * Retrieves the glossary schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'glossary',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'description' => __( 'Unique identifier for the glossary.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'translation_set_id' => array(
					'description' => __( 'The ID of the translation set associated with the glossary.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'description'        => array(
					'description' => __( 'The description of the glossary.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
