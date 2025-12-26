<?php
/**
 * REST API: GP_REST_Translation_Sets_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Locales;
use GP_Translation_Set;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a sets via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Translation_Sets_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'translation-sets';
		parent::__construct();
	}

	/**
	 * Registers the routes for the translation sets endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET translation-sets with project_id .
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
							'description'       => __( 'The ID of the project to retrieve translation sets for.', 'gp-rest' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST translation-sets .
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

		// GET translation-sets/{id} .
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

		// PUT translation-sets/{id} .
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

		// DELETE translation-sets/{id} .
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
	 * Handles GET requests to /translation-sets endpoint.
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

		$params = array(
			'project_id' => $project_id,
		);
		$sets   = GP::$translation_set->find( $params );

		$data = array();
		foreach ( $sets as $set ) {
			$item   = $this->prepare_item_for_response( $set, $request );
			$data[] = $this->prepare_response_for_collection( $item );
		}

		$response = rest_ensure_response( $data );

		$total_items = count( $data );
		$response->header( 'X-WP-Total', $total_items );

		return $response;
	}

	/**
	 * Handles POST requests to /translation-sets endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response.
	 */
	public function create_item( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		$locale     = sanitize_text_field( $request->get_param( 'locale' ) );
		$locale_obj = GP_Locales::by_slug( $locale );
		if ( ! $locale_obj ) {
			return $this->response_404_locale_not_found();
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_text_field( $request->get_param( 'slug' ) );
		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_missing_parameters',
					'message' => __( 'Name and slug are required.', 'gp-rest' ),
				),
				400
			);
		}

		// Check for existing set with same project and locale.
		$params = array(
			'project_id' => $project_id,
			'locale'     => $locale,
			'slug'       => $slug,
		);
		if ( GP::$translation_set->find_one( $params ) ) {
			return $this->response_409_translation_set_already_exists();
		}

		$data = array(
			'project_id' => $project_id,
			'locale'     => $locale,
			'name'       => $name,
			'slug'       => $slug,
		);
		$set  = GP::$translation_set->create( $data );

		if ( ! $set ) {
			return $this->response_500_translation_set_creation_failed();
		}

		$data = $this->prepare_item_for_response( $set, $request );

		$response = rest_ensure_response( $data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handles GET requests to /translation-sets/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_item( $request ) {
		$set_id = absint( $request->get_param( 'id' ) );
		$set    = GP::$translation_set->get( $set_id );
		if ( ! $set ) {
			return $this->response_404_translation_set_not_found();
		}

		$data = $this->prepare_item_for_response( $set, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles PUT requests to /translation-sets/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function update_item( $request ) {
		$set_id = absint( $request->get_param( 'id' ) );
		$set    = GP::$translation_set->get( $set_id );
		if ( ! $set ) {
			return $this->response_404_translation_set_not_found();
		}

		$project_id = absint( $request->get_param( 'project_id' ) );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		$locale     = sanitize_text_field( $request->get_param( 'locale' ) );
		$locale_obj = GP_Locales::by_slug( $locale );
		if ( ! $locale_obj ) {
			return $this->response_404_locale_not_found();
		}

		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_text_field( $request->get_param( 'slug' ) );
		if ( empty( $name ) || empty( $slug ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'set_missing_parameters',
					'message' => __( 'Name and slug are required.', 'gp-rest' ),
				),
				400
			);
		}

		$data = array(
			'project_id' => $project_id,
			'locale'     => $locale,
			'name'       => $name,
			'slug'       => $slug,
		);

		$updated = GP::$translation_set->update( $data, array( 'id' => $set_id ) );
		if ( ! $updated ) {
			return $this->response_500_translation_set_update_failed();
		}

		$updated_set = GP::$translation_set->get( $set_id );

		$data = $this->prepare_item_for_response( $updated_set, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles DELETE requests to /translation-sets/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_item( $request ) {
		$set_id = absint( $request->get_param( 'id' ) );
		$set    = GP::$translation_set->get( $set_id );
		if ( ! $set ) {
			return $this->response_404_translation_set_not_found();
		}

		// Set set ID and delete.
		GP::$translation_set->id = $set_id;
		$deleted                 = GP::$translation_set->delete();
		if ( ! $deleted ) {
			return $this->response_500_translation_set_deletion_failed();
		}

		$response = new WP_REST_Response( null, 204 );

		return $response;
	}

	/**
	 * Permission check for retrieving translation sets.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for creating a new translation set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		$project_id = absint( $request->get_param( 'project_id' ) );

		return $this->current_user_can( 'write', 'project', $project_id );
	}

	/**
	 * Permission check for retrieving a translation set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for editing a translation set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		$translation_set_id = absint( $request->get_param( 'id' ) );
		$translation_set    = GP::$translation_set->get( $translation_set_id );
		if ( ! $translation_set ) {
			return false;
		}

		return $this->current_user_can( 'write', 'project', $translation_set->project_id );
	}

	/**
	 * Permission check for deleting a translation set.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		$translation_set_id = absint( $request->get_param( 'id' ) );
		$translation_set    = GP::$translation_set->get( $translation_set_id );
		if ( ! $translation_set ) {
			return false;
		}

		return $this->current_user_can( 'delete', 'project', $translation_set->project_id );
	}

	/**
	 * Prepares a single translation set output for response.
	 *
	 * @param GP_Translation_Set $item    Translation set object.
	 * @param WP_REST_Request    $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$translation_set = $item;

		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		if ( in_array( 'id', $fields, true ) ) {
			$data['id'] = $translation_set->id;
		}

		if ( in_array( 'project_id', $fields, true ) ) {
			$data['project_id'] = $translation_set->project_id;
		}

		if ( in_array( 'locale', $fields, true ) ) {
			$data['locale'] = $translation_set->locale;
		}

		if ( in_array( 'name', $fields, true ) ) {
			$data['name'] = $translation_set->name;
		}

		if ( in_array( 'slug', $fields, true ) ) {
			$data['slug'] = $translation_set->slug;
		}

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a translation set returned from the REST API.
		 * Allows modification of the translation set right before it is returned.
		 *
		 * @param WP_REST_Response   $response        The response object.
		 * @param GP_Translation_Set $translation_set The original object.
		 * @param WP_REST_Request    $request         Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_translation_set', $response, $translation_set, $request );
	}

	/**
	 * Retrieves the translation set schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'translation_set',
			'type'       => 'object',
			'properties' => array(
				'id'         => array(
					'description' => __( 'Unique identifier for the translation.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'project_id' => array(
					'description' => __( 'The ID of the project this translation set belongs to.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'locale'     => array(
					'description' => __( 'The locale of the translation set.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'name'       => array(
					'description' => __( 'The name of the translation set.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'slug'       => array(
					'description' => __( 'The slug of the translation set.', 'gp-rest' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
