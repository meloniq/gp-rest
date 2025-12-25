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
					'callback'            => array( $this, 'get_glossaries' ),
					'permission_callback' => array( $this, 'get_glossaries_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
					'callback'            => array( $this, 'create_glossary' ),
					'permission_callback' => array( $this, 'create_glossary_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
					'callback'            => array( $this, 'get_glossary' ),
					'permission_callback' => array( $this, 'get_glossary_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
					'callback'            => array( $this, 'edit_glossary' ),
					'permission_callback' => array( $this, 'edit_glossary_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
					'callback'            => array( $this, 'delete_glossary' ),
					'permission_callback' => array( $this, 'delete_glossary_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
	public function get_glossaries( $request ) {
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
	public function create_glossary( $request ) {
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
	public function get_glossary( $request ) {
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
	public function edit_glossary( $request ) {
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
	public function delete_glossary( $request ) {
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
	public function get_glossaries_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_glossary_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for retrieving a glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_glossary_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for editing a glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function edit_glossary_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for deleting a glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_glossary_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
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
}
