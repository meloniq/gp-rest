<?php
/**
 * REST API: GP_REST_Languages_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Locale;
use GP_Locales;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a languages via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Languages_Controller extends GP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'languages';
		parent::__construct();
	}

	/**
	 * Registers the routes for the languages endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// languages .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_languages' ),
					'permission_callback' => array( $this, 'get_languages_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Handles GET requests to /languages endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_languages( $request ) {
		$data = $this->get_locales();

		$response = new WP_REST_Response( $data, 200 );

		return $response;
	}

	/**
	 * Permission check for retrieving languages.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_languages_permissions_check( $request ) {
		return true;
	}

	/**
	 * Get the locales.
	 *
	 * @return array List of locales.
	 */
	public function get_locales() {
		$existing_locales = GP::$translation_set->existing_locales();
		$locales          = array();

		foreach ( $existing_locales as $locale ) {
			$locales[] = GP_Locales::by_slug( $locale );
		}

		usort( $locales, array( $this, 'sort_locales' ) );

		return $locales;
	}

	/**
	 * Sort locales by their English name.
	 *
	 * @param GP_Locale $a First locale.
	 * @param GP_Locale $b Second locale.
	 *
	 * @return int Comparison result.
	 */
	private function sort_locales( $a, $b ) {
		return $a->english_name <=> $b->english_name;
	}
}
