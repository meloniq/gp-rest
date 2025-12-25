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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(),
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
	public function get_items( $request ) {
		$locales = $this->get_locales();

		$data = array();
		foreach ( $locales as $locale ) {
			$item   = $this->prepare_item_for_response( $locale, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Permission check for retrieving languages.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_items_permissions_check( $request ) {
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

	/**
	 * Prepares a single language output for response.
	 *
	 * @param GP_Translation_Set $item    Language object.
	 * @param WP_REST_Request    $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$language = $item;

		$data = array(
			'english_name' => $language->english_name,
			'native_name'  => $language->native_name,
			'code'         => $language->slug,
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a language returned from the REST API.
		 * Allows modification of the language right before it is returned.
		 *
		 * @param WP_REST_Response   $response        The response object.
		 * @param GP_Translation_Set $language The original object.
		 * @param WP_REST_Request    $request         Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_language', $response, $language, $request );
	}

	/**
	 * Retrieves the language schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'language',
			'type'       => 'object',
			'properties' => array(
				'english_name' => array(
					'description' => __( 'The English name of the language.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'native_name'  => array(
					'description' => __( 'The native name of the language.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'code'         => array(
					'description' => __( 'The language code.', 'gp-rest' ),
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
