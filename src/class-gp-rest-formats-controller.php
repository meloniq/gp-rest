<?php
/**
 * REST API: GP_REST_Formats_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Format;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a formats via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Formats_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'formats';
		parent::__construct();
	}

	/**
	 * Registers the routes for the formats endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET formats .
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
	}

	/**
	 * Handles GET requests to /formats endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( GP::$formats as $format ) {
			$item   = $this->prepare_item_for_response( $format, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Permission check for getting formats.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Prepares a single format output for response.
	 *
	 * @param GP_Format       $item    Format object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$format = $item;

		$data = array(
			'name'             => $format->name,
			'extension'        => $format->extension,
			'alt_extensions'   => $format->alt_extensions,
			'filename_pattern' => $format->filename_pattern,
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a format returned from the REST API.
		 * Allows modification of the format right before it is returned.
		 *
		 * @param WP_REST_Response  $response The response object.
		 * @param GP_Format         $format   The original object.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_format', $response, $format, $request );
	}

	/**
	 * Retrieves the format schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'format',
			'type'       => 'object',
			'properties' => array(
				'name'             => array(
					'description' => __( 'The name of the format.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'extension'        => array(
					'description' => __( 'The file extension for the format.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'alt_extensions'   => array(
					'description' => __( 'Alternative file extensions for the format.', 'gp-rest' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'filename_pattern' => array(
					'description' => __( 'The filename pattern for the format.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
