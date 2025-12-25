<?php
/**
 * REST API: GP_REST_Translations_Controller class
 *
 * @package Meloniq\GpRest
 */

namespace Meloniq\GpRest;

use GP;
use GP_Locales;
use GP_Translation;
use WP_REST_Response;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core class used to manage a translations via the REST API.
 *
 * @see GP_REST_Controller
 */
class GP_REST_Translations_Controller extends GP_REST_Controller {

	use GP_Responses_Helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'translations';
		parent::__construct();
	}

	/**
	 * Registers the routes for the translations endpoint.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		// GET translations?? .

		// POST translations .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_translation' ),
					'permission_callback' => array( $this, 'create_translation_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
			)
		);

		// GET translations/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_translation' ),
					'permission_callback' => array( $this, 'get_translation_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		// PUT translations/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'edit_translation' ),
					'permission_callback' => array( $this, 'edit_translation_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);

		// DELETE translations/{id} .
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_translation' ),
					'permission_callback' => array( $this, 'delete_translation_permissions_check' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Handles POST requests to /translations endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function create_translation( $request ) {
		$project_id = $request->get_param( 'project_id' );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		$translation_set_id = absint( $request->get_param( 'translation_set_id' ) );
		$translation_set    = GP::$translation_set->get( $translation_set_id );
		if ( ! $translation_set ) {
			return $this->response_404_translation_set_not_found();
		}

		$locale = GP_Locales::by_slug( $translation_set->locale );
		if ( ! $locale ) {
			return $this->response_404_locale_not_found();
		}

		$original_id = absint( $request->get_param( 'original_id' ) );
		$original    = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $this->response_404_original_not_found();
		}

		$range = $this->get_translations_range();

		$translations = array();
		foreach ( $range as $index ) {
			$translation_text = $request->get_param( 'translation_' . $index );
			if ( null !== $translation_text ) {
				$translations[ 'translation_' . $index ] = $translation_text;
			}
		}

		$warnings = GP::$translation_warnings->check( $original->singular, $original->plural, $translations, $locale );
		$errors   = GP::$translation_errors->check( $original, $translations, $locale );
		if ( ! empty( $errors ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'translation_errors',
					'message' => __( 'Translation contains errors.', 'gp-rest' ),
					'errors'  => $errors,
				),
				400
			);
		}

		$existing_translations = GP::$translation->for_translation(
			$project,
			$translation_set,
			'no-limit',
			array(
				'original_id' => $original_id,
				'status'      => 'current_or_waiting',
			),
			array()
		);

		foreach ( $existing_translations as $existing_translation ) {
			if ( array_pad( $translations, $locale->nplurals, null ) === $existing_translation->translations ) {
				return $this->response_409_translation_already_exists();
			}
		}

		$can_approve_set   = $this->current_user_can( 'approve', 'translation-set', $translation_set_id );
		$can_write_project = $this->current_user_can( 'write', 'project', $project_id );
		if ( $can_approve_set || $can_write_project ) {
			$set_status = 'current';
		} else {
			$set_status = 'waiting';
		}

		$data = array(
			'translation_set_id' => $translation_set_id,
			'original_id'        => $original_id,
			'user_id'            => get_current_user_id(),
			'status'             => $set_status,
			'warnings'           => $warnings,
		);
		$data = array_merge( $data, $translations );

		$new_translation = GP::$translation->create( $data );
		if ( ! $new_translation ) {
			return $this->response_500_translation_creation_failed();
		}

		if ( ! $new_translation->validate() ) {
			$new_translation->delete();
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_translation_data',
					'message' => __( 'Invalid translation data.', 'gp-rest' ),
					'errors'  => $new_translation->errors,
				),
				400
			);
		}

		$data = $this->prepare_item_for_response( $new_translation, $request );

		$response = rest_ensure_response( $data );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Handles GET requests to /translations/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function get_translation( $request ) {
		$translation_id = absint( $request->get_param( 'id' ) );
		$translation    = GP::$translation->get( $translation_id );
		if ( ! $translation ) {
			return $this->response_404_translation_not_found();
		}

		$data = $this->prepare_item_for_response( $translation, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles PUT requests to /translations/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function edit_translation( $request ) {
		$translation_id = absint( $request->get_param( 'id' ) );
		$translation    = GP::$translation->get( $translation_id );
		if ( ! $translation ) {
			return $this->response_404_translation_not_found();
		}

		$project_id = $request->get_param( 'project_id' );
		$project    = GP::$project->get( $project_id );
		if ( ! $project ) {
			return $this->response_404_project_not_found();
		}

		$translation_set_id = absint( $request->get_param( 'translation_set_id' ) );
		$translation_set    = GP::$translation_set->get( $translation_set_id );
		if ( ! $translation_set ) {
			return $this->response_404_translation_set_not_found();
		}

		$locale = GP_Locales::by_slug( $translation_set->locale );
		if ( ! $locale ) {
			return $this->response_404_locale_not_found();
		}

		$original_id = absint( $request->get_param( 'original_id' ) );
		$original    = GP::$original->get( $original_id );
		if ( ! $original ) {
			return $this->response_404_original_not_found();
		}

		$range = $this->get_translations_range();

		$translations = array();
		foreach ( $range as $index ) {
			$translation_text = $request->get_param( 'translation_' . $index );
			if ( null !== $translation_text ) {
				$translations[ 'translation_' . $index ] = $translation_text;
			}
		}

		$warnings = GP::$translation_warnings->check( $original->singular, $original->plural, $translations, $locale );
		$errors   = GP::$translation_errors->check( $original, $translations, $locale );
		if ( ! empty( $errors ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'translation_errors',
					'message' => __( 'Translation contains errors.', 'gp-rest' ),
					'errors'  => $errors,
				),
				400
			);
		}

		$data = array(
			'translation_set_id' => $translation_set_id,
			'original_id'        => $original_id,
			'user_id'            => get_current_user_id(),
			'status'             => $translation->status,
			'warnings'           => $warnings,
		);
		$data = array_merge( $data, $translations );

		$updated = GP::$translation->update( $data, array( 'id' => $translation_id ) );
		if ( ! $updated ) {
			return $this->response_500_translation_update_failed();
		}

		$updated_translation = GP::$translation->get( $translation_id );

		$data = $this->prepare_item_for_response( $updated_translation, $request );

		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Handles DELETE requests to /translations/{id} endpoint.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The REST response.
	 */
	public function delete_translation( $request ) {
		$translation_id = absint( $request->get_param( 'id' ) );
		$translation    = GP::$translation->get( $translation_id );
		if ( ! $translation ) {
			return $this->response_404_translation_not_found();
		}

		$deleted = $translation->delete();
		if ( ! $deleted ) {
			return $this->response_500_translation_deletion_failed();
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Permission check for creating a new translation.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function create_translation_permissions_check( $request ) {
		$translation_set_id = absint( $request->get_param( 'translation_set_id' ) );

		return $this->current_user_can( 'edit', 'translation-set', $translation_set_id );
	}

	/**
	 * Permission check for retrieving a translation.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function get_translation_permissions_check( $request ) {
		return true;
	}

	/**
	 * Permission check for editing a translation.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function edit_translation_permissions_check( $request ) {
		$translation_id = absint( $request->get_param( 'id' ) );

		return $this->current_user_can( 'approve', 'translation', $translation_id );
	}

	/**
	 * Permission check for deleting a translation.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool True if the request has permission, false otherwise.
	 */
	public function delete_translation_permissions_check( $request ) {
		$translation_id = absint( $request->get_param( 'id' ) );

		return $this->current_user_can( 'approve', 'translation', $translation_id );
	}

	/**
	 * Prepares a single translation output for response.
	 *
	 * @param GP_Translation  $item    Translation object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		// Restores the more descriptive, specific name for use within this method.
		$translation = $item;

		$data = array(
			'id'                 => $translation->id,
			'translation_set_id' => $translation->translation_set_id,
			'original_id'        => $translation->original_id,
			'translations'       => $this->get_translations_data( $translation ),
			'status'             => $translation->status,
			'warnings'           => $translation->warnings,
		);

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		/**
		 * Filters a translation returned from the REST API.
		 * Allows modification of the translation right before it is returned.
		 *
		 * @param WP_REST_Response  $response    The response object.
		 * @param GP_Translation    $translation The original object.
		 * @param WP_REST_Request   $request     Request used to generate the response.
		 */
		return apply_filters( 'gp_rest_prepare_translation', $response, $translation, $request );
	}

	/**
	 * Retrieves the translation schema, conforming to JSON Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'translation',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'description' => __( 'Unique identifier for the translation.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'translation_set_id' => array(
					'description' => __( 'Identifier for the translation set.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'original_id'        => array(
					'description' => __( 'Identifier for the original string.', 'gp-rest' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'translations'       => array(
					'description' => __( 'An array of translations for the original string.', 'gp-rest' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'status'             => array(
					'description' => __( 'The status of the translation.', 'gp-rest' ),
					'type'        => 'string',
					'enum'        => array( 'current', 'waiting', 'fuzzy', 'old' ),
					'context'     => array( 'view', 'edit', 'embed' ),
				),
				'warnings'           => array(
					'description' => __( 'An array of warnings associated with the translation.', 'gp-rest' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'context'     => array( 'view', 'edit', 'embed' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Get translations data.
	 *
	 * @param GP_Translation $translation Translation object.
	 *
	 * @return array Translations.
	 */
	protected function get_translations_data( $translation ) {
		$range = $this->get_translations_range();

		$translations = array();
		foreach ( $range as $index ) {
			$tr_id = 'translation_' . $index;
			if ( ! empty( $translation->$tr_id ) ) {
				$translations[ 'translation_' . $index ] = $translation->$tr_id;
			}
		}

		return $translations;
	}

	/**
	 * Get translations range.
	 *
	 * @return array Range of translation indexes.
	 */
	protected function get_translations_range() {
		// Reduce range by one since we're starting at 0, see GH#516.
		return range( 0, GP::$translation->get_static( 'number_of_plural_translations' ) - 1 );
	}
}
