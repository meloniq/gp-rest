<?php
/**
 * REST API: GP_REST_Formats_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
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
					'callback'            => array( $this, 'get_formats' ),
					'permission_callback' => array( $this, 'get_formats_permissions_check' ),
					'args'                => $this->get_collection_params(),
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
	public function get_formats( $request ) {
		$data = array();
		foreach ( GP::$formats as $format ) {
			$data[] = array(
				'name'             => $format->name,
				'extension'        => $format->extension,
				'alt_extensions'   => $format->alt_extensions,
				'filename_pattern' => $format->filename_pattern,
			);
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
	public function get_formats_permissions_check( $request ) {
		return true;
	}
}
