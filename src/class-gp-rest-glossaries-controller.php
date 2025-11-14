<?php
/**
 * REST API: GP_REST_Glossaries_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a glossaries via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Glossaries_Controller extends GP_REST_Controller {

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

		// glossaries/new .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/new',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Handles POST requests to /glossaries/new endpoint.
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
			return new WP_REST_Response(
				array(
					'code'    => 'glossary_creation_failed',
					'message' => __( 'Invalid translation set ID.', 'gp-rest' ),
				),
				400
			);
		}

		// get Glossary by translation_set_id to ensure uniqueness.
		$existing_glossary = GP::$glossary->find( array( 'translation_set_id' => $translation_set_id ) );
		if ( ! empty( $existing_glossary ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'glossary_creation_failed',
					'message' => __( 'A glossary for this translation set already exists.', 'gp-rest' ),
				),
				400
			);
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
			return new WP_REST_Response(
				array(
					'code'    => 'glossary_creation_failed',
					'message' => __( 'Failed to create glossary.', 'gp-rest' ),
				),
				500
			);
		}

		$data = array(
			'id'                 => $glossary->id,
			'translation_set_id' => $glossary->translation_set_id,
			'description'        => $glossary->description,
		);

		$response = new WP_REST_Response( $data, 201 );

		return $response;
	}

	/**
	 * Permission check for creating a new glossary.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		// Todo: Refine permission logic as needed.
		return current_user_can( 'manage_options' );
	}
}
